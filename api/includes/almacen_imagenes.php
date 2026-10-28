<?php
/**
 * Almacenamiento de las imágenes de producto subidas desde el panel.
 *
 * Dos modos, elegidos por configuración (ALMACEN_IMAGENES en api/config/entorno.php):
 *
 *   'local'    -> img/productos/ del propio sitio. Es lo que siempre hizo el proyecto y lo que se
 *                 sigue usando en XAMPP.
 *   'supabase' -> Supabase Storage vía su API REST. En Vercel el disco es de SOLO LECTURA y además
 *                 no se comparte entre instancias: un archivo escrito ahí se pierde, así que la
 *                 subida tiene que ir a un almacenamiento externo.
 *
 * Qué se guarda en producto_imagenes.url_imagen:
 *   - modo local    -> una ruta relativa, como siempre: "img/productos/ab12….png"
 *   - modo supabase -> la URL pública completa del objeto
 * Las dos funcionan tal cual en el atributo src de las páginas de la tienda.
 *
 * SEGURIDAD: la service role key de Supabase da acceso total al proyecto. Vive únicamente en una
 * variable de entorno del servidor, se usa solo acá y JAMÁS se envía al navegador ni se registra
 * en un log. Las URLs que se aceptan al guardar un producto están restringidas al host y al bucket
 * configurados (ver referenciaImagenValida), para que nadie pueda inyectar una URL arbitraria.
 */

require_once __DIR__ . '/../config/entorno.php';

function usaSupabaseStorage(): bool
{
    return ALMACEN_IMAGENES === 'supabase' && SUPABASE_URL !== '' && SUPABASE_SERVICE_ROLE_KEY !== '';
}

/** Raíz del sitio en disco (donde vive la carpeta img/). */
function raizSitio(): string
{
    return realpath(__DIR__ . '/../..');
}

/** Prefijo público de los objetos del bucket, sin barra final. */
function prefijoPublicoSupabase(): string
{
    return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . SUPABASE_BUCKET;
}

/**
 * Guarda el archivo recién subido y devuelve la referencia a persistir en la base.
 * Devuelve null si no se pudo guardar.
 */
function almacenarImagenSubida(string $rutaTemporal, string $extension, string $tipoMime): ?string
{
    $nombre = bin2hex(random_bytes(12)) . '.' . $extension;

    if (!usaSupabaseStorage()) {
        $carpeta = raizSitio() . '/img/productos';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
            return null;
        }
        if (!move_uploaded_file($rutaTemporal, $carpeta . '/' . $nombre)) {
            return null;
        }
        @chmod($carpeta . '/' . $nombre, 0644);
        return 'img/productos/' . $nombre;
    }

    $contenido = file_get_contents($rutaTemporal);
    if ($contenido === false) {
        return null;
    }
    $destino = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $nombre;
    [$codigo] = peticionSupabase('POST', $destino, $contenido, [
        'Content-Type: ' . $tipoMime,
        'x-upsert: false',
    ]);
    return ($codigo >= 200 && $codigo < 300) ? prefijoPublicoSupabase() . '/' . $nombre : null;
}

/**
 * Imágenes que el panel ofrece para elegir.
 * Siempre incluye las del repositorio (img/…) y, en modo Supabase, también las ya subidas al bucket.
 */
function listarImagenesDisponibles(): array
{
    $raiz = raizSitio();
    $imagenes = [];
    if (is_dir($raiz . '/img')) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz . '/img', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $archivo) {
            $rel = 'img/' . str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz . '/img/')));
            if (preg_match('#^img/[A-Za-z0-9_\-/]+\.(png|jpe?g|webp|gif)$#', $rel)
                && !str_starts_with($rel, 'img/Imagen7')
                && !str_starts_with($rel, 'img/logo/')) {
                $imagenes[] = $rel;
            }
        }
    }
    sort($imagenes, SORT_NATURAL);

    if (usaSupabaseStorage()) {
        $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/list/' . SUPABASE_BUCKET;
        $cuerpo = json_encode(['prefix' => '', 'limit' => 200, 'sortBy' => ['column' => 'name', 'order' => 'asc']]);
        [$codigo, $respuesta] = peticionSupabase('POST', $url, $cuerpo, ['Content-Type: application/json']);
        if ($codigo >= 200 && $codigo < 300) {
            foreach ((array) json_decode($respuesta, true) as $objeto) {
                if (isset($objeto['name']) && preg_match('#\.(png|jpe?g|webp|gif)$#i', $objeto['name'])) {
                    $imagenes[] = prefijoPublicoSupabase() . '/' . $objeto['name'];
                }
            }
        }
    }
    return $imagenes;
}

/**
 * ¿Esta referencia se puede guardar como imagen de un producto?
 *
 * Acepta exactamente dos formas y nada más:
 *   - ruta relativa "img/…" de un archivo que EXISTE en el sitio (igual que siempre)
 *   - URL pública del bucket de Supabase configurado
 * No acepta ninguna otra URL: así no se puede inyectar un "javascript:" ni apuntar a un sitio ajeno.
 */
function referenciaImagenValida($ref): bool
{
    if (!is_string($ref) || strlen($ref) > 255 || str_contains($ref, '..')) {
        return false;
    }
    if (preg_match('#^img/[A-Za-z0-9_\-/]+\.(png|jpe?g|webp|gif)$#', $ref)) {
        return is_file(raizSitio() . '/' . $ref);
    }
    if (usaSupabaseStorage()) {
        $prefijo = prefijoPublicoSupabase() . '/';
        return str_starts_with($ref, $prefijo)
            && (bool) preg_match('#^[A-Za-z0-9_\-/]+\.(png|jpe?g|webp|gif)$#', substr($ref, strlen($prefijo)));
    }
    return false;
}

/**
 * Petición autenticada a la API de Supabase. Devuelve [códigoHttp, cuerpo].
 * La clave viaja solo en la cabecera Authorization; nunca se escribe en la respuesta ni en un log.
 */
function peticionSupabase(string $metodo, string $url, ?string $cuerpo, array $cabeceras = []): array
{
    $cabeceras[] = 'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY;
    $cabeceras[] = 'apikey: ' . SUPABASE_SERVICE_ROLE_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    }
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$codigo, $respuesta === false ? '' : (string) $respuesta];
}
