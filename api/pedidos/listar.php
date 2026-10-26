<?php
/**
 * GET /api/pedidos/listar.php
 * Requiere sesión. "Mis Compras": el historial del usuario logueado,
 * más reciente primero. Siempre scopeado por el usuario de la sesión.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$usuario = requiereLogin();
$pdo = obtenerConexion();

$stmt = $pdo->prepare(
    'SELECT id_pedido, fecha_pedido, estado, subtotal, costo_envio, total
     FROM pedidos
     WHERE id_usuario = :u
     ORDER BY fecha_pedido DESC, id_pedido DESC'
);
$stmt->execute(['u' => $usuario['id_usuario']]);
$filas = $stmt->fetchAll();

$pedidos = array_map(function ($fila) {
    return [
        'id_pedido'    => (int) $fila['id_pedido'],
        'fecha_pedido' => $fila['fecha_pedido'],
        'estado'       => $fila['estado'],
        'subtotal'     => (float) $fila['subtotal'],
        'costo_envio'  => (float) $fila['costo_envio'],
        'total'        => (float) $fila['total'],
    ];
}, $filas);

responderJson(['pedidos' => $pedidos]);
