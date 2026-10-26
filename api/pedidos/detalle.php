<?php
/**
 * GET /api/pedidos/detalle.php?id=1
 * Requiere sesión. Devuelve el detalle completo de UN pedido -- pero solo si
 * pertenece al usuario de la sesión. La consulta filtra SIEMPRE por
 * "id_pedido = :id AND id_usuario = :usuario_de_la_sesion": si el pedido es
 * de otro usuario, el resultado es el mismo que si no existiera (404).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$usuario = requiereLogin();
$idPedido = parametroIdRequerido('id');

$pdo = obtenerConexion();

$stmtPedido = $pdo->prepare(
    'SELECT id_pedido, fecha_pedido, estado, subtotal, costo_envio, total, direccion_envio
     FROM pedidos
     WHERE id_pedido = :id AND id_usuario = :u'
);
$stmtPedido->execute(['id' => $idPedido, 'u' => $usuario['id_usuario']]);
$pedido = $stmtPedido->fetch();

if (!$pedido) {
    responderError('Pedido no encontrado.', 404);
}

$stmtDetalle = $pdo->prepare(
    'SELECT dp.cantidad, dp.precio_unitario, dp.subtotal,
            p.nombre, pi.url_imagen AS imagen_principal
     FROM detalle_pedido dp
     JOIN productos p ON p.id_producto = dp.id_producto
     LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
     WHERE dp.id_pedido = :id
     ORDER BY dp.id_detalle'
);
$stmtDetalle->execute(['id' => $idPedido]);
$items = $stmtDetalle->fetchAll();

responderJson([
    'pedido' => [
        'id_pedido'       => (int) $pedido['id_pedido'],
        'fecha_pedido'    => $pedido['fecha_pedido'],
        'estado'          => $pedido['estado'],
        'subtotal'        => (float) $pedido['subtotal'],
        'costo_envio'     => (float) $pedido['costo_envio'],
        'total'           => (float) $pedido['total'],
        'direccion_envio' => $pedido['direccion_envio'],
        'items'           => array_map(function ($item) {
            return [
                'nombre'           => $item['nombre'],
                'cantidad'         => (int) $item['cantidad'],
                'precio_unitario'  => (float) $item['precio_unitario'],
                'subtotal'         => (float) $item['subtotal'],
                'imagen_principal' => $item['imagen_principal'],
            ];
        }, $items),
    ],
]);
