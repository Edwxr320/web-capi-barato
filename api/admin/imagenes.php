<?php
/**
 * Imágenes de productos (solo rol admin) — Bloque 5.
 *
 * GET  /api/admin/imagenes.php                  lista las imágenes disponibles dentro de img/ (para elegir existentes)
 * POST /api/admin/imagenes.php  (X-CSRF-Token)  multipart/form-data con el campo "imagen" -> la guarda en img/productos/
 *
 * Validaciones de la subida: un solo archivo, hasta 2 MB, contenido REAL de imagen PNG, JPEG o WebP (se analiza el
 * archivo, no la extensión ni el tipo que declara el navegador), dimensiones entre 50 y 4000 px. El nombre final lo
 * genera el servidor (aleatorio + extensión según el tipo detectado): nunca se usa el nombre enviado por el cliente.
 * La respuesta devuelve la ruta (ej. "img/productos/3f2a….png") para usarla en api/admin/productos.php.
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

iniciarEndpointAdmin(['GET', 'POST']);

$raiz = realpath(__DIR__ . '/../..');
const TAMANIO_MAXIMO = 2 * 1024 * 1024;
const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $imagenes = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/img', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $archivo) {
        $rel = 'img/' . str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz . '/img/')));
        if (preg_match('#^img/[A-Za-z0-9_\-/]+\.(png|jpe?g|webp|gif)$#', $rel) && !str_starts_with($rel, 'img/Imagen7') && !str_starts_with($rel, 'img/logo/')) {
            $imagenes[] = $rel;
        }
    }
    sort($imagenes, SORT_NATURAL);
    responderJson(['imagenes' => $imagenes]);
}

$archivo = $_FILES['imagen'] ?? null;
if (!$archivo || !is_array($archivo) || is_array($archivo['error'])) {
    responderError("Enviá un único archivo en el campo 'imagen'.", 400);
}
if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE || $archivo['size'] > TAMANIO_MAXIMO) {
    responderError('La imagen supera el máximo de 2 MB.', 400);
}
if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
    responderError('No se pudo recibir el archivo. Intentá de nuevo.', 400);
}
$tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
if (!isset(TIPOS[$tipo])) {
    responderError('Formato no permitido. Usá PNG, JPG o WebP.', 400);
}
$info = @getimagesize($archivo['tmp_name']);
if ($info === false || $info[0] < 50 || $info[1] < 50 || $info[0] > 4000 || $info[1] > 4000) {
    responderError('La imagen debe medir entre 50 y 4000 píxeles por lado.', 400);
}

$carpeta = $raiz . '/img/productos';
if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
    throw new RuntimeException('No se pudo crear img/productos');
}
$nombre = bin2hex(random_bytes(12)) . '.' . TIPOS[$tipo];
if (!move_uploaded_file($archivo['tmp_name'], $carpeta . '/' . $nombre)) {
    throw new RuntimeException('No se pudo guardar la imagen subida');
}
@chmod($carpeta . '/' . $nombre, 0644);

responderJson(['mensaje' => 'Imagen subida.', 'ruta' => 'img/productos/' . $nombre], 201);
