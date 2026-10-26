<?php
/**
 * POST /api/carrito/eliminar.php
 * Body: { "id_producto": 5 }
 * Requiere sesión. Igual que actualizar.php, el DELETE está scopeado por
 * id_usuario de la sesión -- no se puede borrar un ítem de otro usuario.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}

$usuario = requiereLogin();
requiereCsrf();
$datos = leerCuerpoJson();
$idProducto = campoIdRequerido($datos, 'id_producto');

$pdo = obtenerConexion();
$stmt = $pdo->prepare('DELETE FROM carrito_items WHERE id_usuario = :u AND id_producto = :p');
$stmt->execute(['u' => $usuario['id_usuario'], 'p' => $idProducto]);

if ($stmt->rowCount() === 0) {
    responderError('Ese producto no estaba en tu carrito.', 404);
}

responderJson(['mensaje' => 'Producto eliminado del carrito.', 'id_producto' => $idProducto]);
