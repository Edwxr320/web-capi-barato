<?php
/**
 * GET /api/carrito/listar.php
 * Requiere sesión. Devuelve el carrito del usuario logueado, con cada línea
 * ya trayendo los datos del producto, más el resumen (subtotal/envío/total)
 * calculado en el servidor -- MySQL es la fuente de verdad para usuarios
 * autenticados, tal como se definió.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';
require_once __DIR__ . '/../includes/carrito_calculo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$usuario = requiereLogin();
$pdo = obtenerConexion();

$stmt = $pdo->prepare(
    'SELECT ci.id_item, ci.cantidad,
            p.id_producto, p.nombre, p.precio_original, p.descuento_porcentaje,
            p.stock, p.envio_gratis, p.costo_envio, p.proveedor,
            c.nombre AS categoria_nombre, c.slug AS categoria_slug,
            pi.url_imagen AS imagen_principal
     FROM carrito_items ci
     JOIN productos p ON p.id_producto = ci.id_producto
     JOIN categorias c ON c.id_categoria = p.id_categoria
     LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
     WHERE ci.id_usuario = :id_usuario
     ORDER BY ci.fecha_agregado'
);
$stmt->execute(['id_usuario' => $usuario['id_usuario']]);
$filas = $stmt->fetchAll();

$items = array_map(function ($fila) {
    $precioFinal = calcularPrecioFinal((float) $fila['precio_original'], $fila['descuento_porcentaje']);
    return [
        'id_item'  => (int) $fila['id_item'],
        'cantidad' => (int) $fila['cantidad'],
        'producto' => [
            'id_producto'          => (int) $fila['id_producto'],
            'nombre'               => $fila['nombre'],
            'precio_original'      => (float) $fila['precio_original'],
            'descuento_porcentaje' => $fila['descuento_porcentaje'] !== null ? (int) $fila['descuento_porcentaje'] : null,
            'precio_final'         => $precioFinal,
            'stock'                => (int) $fila['stock'],
            'envio_gratis'         => (bool) $fila['envio_gratis'],
            'costo_envio'          => $fila['costo_envio'] !== null ? (float) $fila['costo_envio'] : null,
            'proveedor'            => $fila['proveedor'],
            'categoria'            => ['nombre' => $fila['categoria_nombre'], 'slug' => $fila['categoria_slug']],
            'imagen_principal'     => $fila['imagen_principal'],
        ],
    ];
}, $filas);

$paraResumen = array_map(function ($item) {
    return [
        'cantidad'     => $item['cantidad'],
        'precio_final' => $item['producto']['precio_final'],
        'envio_gratis' => $item['producto']['envio_gratis'],
        'costo_envio'  => $item['producto']['costo_envio'],
    ];
}, $items);

responderJson([
    'items'   => $items,
    'resumen' => calcularResumenCarrito($paraResumen),
]);
