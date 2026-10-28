<?php
/**
 * GET /api/productos/detalle.php?id=1
 *
 * Endpoint público -- no requiere sesión. Detalle completo de un producto:
 * todas sus imágenes y todas sus características, en el orden guardado.
 *
 * Respuesta: { "producto": {..., "imagenes":[...], "caracteristicas":[...]} }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$idProducto = parametroIdRequerido('id');

$pdo = obtenerConexion();

$stmt = $pdo->prepare(
    'SELECT p.id_producto, p.nombre, p.precio_original, p.descuento_porcentaje,
            p.stock, p.envio_gratis, p.costo_envio, p.proveedor,
            c.id_categoria, c.nombre AS categoria_nombre, c.slug AS categoria_slug
     FROM productos p
     JOIN categorias c ON p.id_categoria = c.id_categoria
     WHERE p.id_producto = :id AND p.estado = \'activo\''
);
$stmt->execute(['id' => $idProducto]);
$fila = $stmt->fetch();

if (!$fila) {
    responderError("No existe un producto activo con id $idProducto.", 404);
}

$stmtImg = $pdo->prepare('SELECT url_imagen FROM producto_imagenes WHERE id_producto = :id ORDER BY orden');
$stmtImg->execute(['id' => $idProducto]);
$imagenes = array_column($stmtImg->fetchAll(), 'url_imagen');

$stmtCar = $pdo->prepare('SELECT descripcion FROM producto_caracteristicas WHERE id_producto = :id ORDER BY orden');
$stmtCar->execute(['id' => $idProducto]);
$caracteristicas = array_column($stmtCar->fetchAll(), 'descripcion');

$producto = [
    'id_producto'          => (int) $fila['id_producto'],
    'nombre'               => $fila['nombre'],
    'precio_original'      => (float) $fila['precio_original'],
    'descuento_porcentaje' => $fila['descuento_porcentaje'] !== null ? (int) $fila['descuento_porcentaje'] : null,
    'precio_final'         => calcularPrecioFinal((float) $fila['precio_original'], $fila['descuento_porcentaje']),
    'stock'                => (int) $fila['stock'],
    'envio_gratis'         => aBooleano($fila['envio_gratis']),
    'costo_envio'          => $fila['costo_envio'] !== null ? (float) $fila['costo_envio'] : null,
    'proveedor'            => $fila['proveedor'],
    'categoria'            => [
        'id_categoria' => (int) $fila['id_categoria'],
        'nombre'       => $fila['categoria_nombre'],
        'slug'         => $fila['categoria_slug'],
    ],
    'imagenes'             => $imagenes,
    'caracteristicas'      => $caracteristicas,
];

responderJson(['producto' => $producto]);
