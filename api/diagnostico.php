<?php
/**
 * Diagnóstico de despliegue. Sirve para comprobar, una sola vez tras el primer despliegue en
 * Vercel, que el runtime trae lo que la aplicación necesita (sobre todo pdo_pgsql) y que la
 * conexión con la base y el almacenamiento están bien configurados.
 *
 * SEGURIDAD — está pensado para no filtrar NADA:
 *
 *   1. Si no está definida la variable de entorno MW_DIAGNOSTICO_TOKEN, responde 404 y no hace
 *      nada. Es decir: por omisión no existe. No hay que acordarse de borrarlo.
 *   2. Si está definida, exige ?token=... y lo compara con hash_equals (tiempo constante).
 *   3. Solo devuelve true/false. Nunca versiones de PHP, rutas, nombres de host, usuarios,
 *      credenciales ni mensajes de error del motor: un atacante no aprende nada aunque acierte.
 *   4. No escribe en la base. La comprobación de conexión es un SELECT 1.
 *   5. La prueba de Storage es OPT-IN: solo corre con &storage=1. Sube un PNG de 1x1 con nombre
 *      al azar, lo lee por la URL pública, lo borra y comprueba que quedó borrado. Devuelve solo
 *      booleanos y códigos HTTP: nunca la clave, ni el host, ni el cuerpo de la respuesta.
 *
 * USO:
 *   - Definí MW_DIAGNOSTICO_TOKEN en Vercel con un valor largo y al azar.
 *   - Abrí https://<tu-dominio>/api/diagnostico.php?token=<ese-valor>
 *     y, para probar Storage de verdad, https://<tu-dominio>/api/diagnostico.php?token=...&storage=1
 *   - Cuando termines de verificar, BORRÁ esa variable de entorno en Vercel. El endpoint vuelve
 *     a responder 404 sin necesidad de tocar el código ni volver a desplegar nada.
 */

// Permite que entorno.php no corte la ejecucion si todavia falta configuracion: este endpoint
// existe para decir QUE falta.
define('MW_SOLO_DIAGNOSTICO', true);

// db.php trae entorno.php y, ademas, los ayudantes esPostgres()/driverBd() que se usan aqui.
// Incluirlo solo define funciones: no abre ninguna conexion por si mismo.
require_once __DIR__ . '/includes/db.php';
// Trae claveSupabaseEsJwt(), peticionSupabase(), prefijoPublicoSupabase() y usaSupabaseStorage():
// la prueba de Storage usa EXACTAMENTE el mismo codigo que el panel, no una copia.
require_once __DIR__ . '/includes/almacen_imagenes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$tokenEsperado = entorno('MW_DIAGNOSTICO_TOKEN');

// Sin token configurado, este endpoint no existe.
if ($tokenEsperado === null || strlen($tokenEsperado) < 16) {
    http_response_code(404);
    echo json_encode(['error' => 'No encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenRecibido = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
if (!hash_equals($tokenEsperado, $tokenRecibido)) {
    http_response_code(404);
    echo json_encode(['error' => 'No encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Prueba la conexión con la base sin revelar ningún dato de la conexión. */
function baseResponde(): bool
{
    try {
        $dsn = esPostgres()
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => esPostgres(),
            PDO::ATTR_TIMEOUT          => 8,
        ]);
        return (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;   // el motivo NO se devuelve: solo si funciona o no
    }
}

/** ¿Están las tablas que la aplicación necesita? Solo cuenta, no devuelve contenido. */
function tablasPresentes(): bool
{
    try {
        $dsn = esPostgres()
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => esPostgres(),
            PDO::ATTR_TIMEOUT          => 8,
        ]);
        $sql = esPostgres()
            ? "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'
               AND table_name IN ('categorias','productos','producto_imagenes','producto_caracteristicas',
                                  'usuarios','carrito_items','pedidos','detalle_pedido','sesiones','intentos_login')"
            : "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
               AND table_name IN ('categorias','productos','producto_imagenes','producto_caracteristicas',
                                  'usuarios','carrito_items','pedidos','detalle_pedido','sesiones','intentos_login')";
        return (int) $pdo->query($sql)->fetchColumn() === 10;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Viaje de ida y vuelta real contra Supabase Storage: subir, leer por la URL pública y borrar.
 *
 * Es la única comprobación que ejercita de verdad la clave secreta y la cabecera con la que viaja
 * (ver claveSupabaseEsJwt en api/includes/almacen_imagenes.php). Se limpia sola: el objeto se borra
 * y después se confirma que ya no está.
 *
 * Qué devuelve: booleanos y códigos HTTP, nada más. Ni la clave, ni la URL del proyecto, ni el
 * cuerpo de las respuestas de Supabase. Los códigos alcanzan para diagnosticar: 401/403 es problema
 * de autenticación (clave o cabecera), 404 es bucket inexistente, 2xx es que funciona.
 */
function pruebaStorage(): array
{
    $r = [
        'clave_es_jwt'      => claveSupabaseEsJwt(SUPABASE_SERVICE_ROLE_KEY),
        'subida_http'       => 0,
        'subida_ok'         => false,
        'lectura_publica_http' => 0,
        'lectura_publica_ok'   => false,
        'borrado_http'      => 0,
        'borrado_ok'        => false,
        'quedo_limpio'      => false,
    ];

    // PNG de 1x1 valido, 70 bytes. Nombre al azar y con prefijo reconocible.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    $nombre = '__diagnostico__' . bin2hex(random_bytes(8)) . '.png';

    $base    = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $nombre;
    $publica = prefijoPublicoSupabase() . '/' . $nombre;

    [$r['subida_http']] = peticionSupabase('POST', $base, $png, [
        'Content-Type: image/png',
        'x-upsert: false',
    ]);
    $r['subida_ok'] = $r['subida_http'] >= 200 && $r['subida_http'] < 300;

    if ($r['subida_ok']) {
        // Lectura ANONIMA, sin ninguna cabecera de autenticacion: es lo que hace el navegador de
        // un cliente al ver la foto del producto, asi que prueba que el bucket es publico.
        $ch = curl_init($publica);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $cuerpo = curl_exec($ch);
        $r['lectura_publica_http'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Tiene que devolver EXACTAMENTE los bytes subidos.
        $r['lectura_publica_ok'] = $r['lectura_publica_http'] === 200 && $cuerpo === $png;

        // Limpieza, se intente o no la lectura con exito.
        [$r['borrado_http']] = peticionSupabase('DELETE', $base, null);
        $r['borrado_ok'] = $r['borrado_http'] >= 200 && $r['borrado_http'] < 300;

        // Y se confirma que ya no esta: la URL publica debe dejar de servirlo.
        $ch = curl_init($publica);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        $r['quedo_limpio'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400;
        curl_close($ch);
    }

    return $r;
}

$conecta = CONFIG_COMPLETA ? baseResponde() : false;

// La prueba de Storage solo corre si se pide explicitamente Y esta configurado.
$probarStorage = ($_GET['storage'] ?? '') === '1';
$storage = ($probarStorage && usaSupabaseStorage()) ? pruebaStorage() : null;

echo json_encode([
    // Extensiones que el runtime tiene que traer.
    'pdo'                 => extension_loaded('pdo'),
    'pdo_pgsql'           => extension_loaded('pdo_pgsql'),
    'curl'                => extension_loaded('curl'),
    'fileinfo'            => extension_loaded('fileinfo'),
    'sesiones_php'        => function_exists('session_start'),
    // Configuración resuelta (sin valores: solo si es la esperada en producción).
    'configuracion_completa' => CONFIG_COMPLETA,
    'motor_es_postgres'   => esPostgres(),
    'cookies_seguras'     => (bool) SESSION_COOKIE_SECURE,
    'sesiones_en_base'    => ALMACEN_SESIONES === 'bd',
    'imagenes_en_supabase' => ALMACEN_IMAGENES === 'supabase',
    'storage_configurado' => SUPABASE_URL !== '' && SUPABASE_SERVICE_ROLE_KEY !== '',
    // Conectividad real.
    'base_responde'       => $conecta,
    'tablas_completas'    => $conecta ? tablasPresentes() : false,
    // Viaje de ida y vuelta real contra Storage. null si no se pidio con &storage=1.
    'storage_prueba'      => $storage,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
