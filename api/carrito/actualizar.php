<?php
/**
 * POST /api/carrito/actualizar.php
 * Body: { "id_producto": 5, "cantidad": 3 }  -- fija la cantidad (no la suma)
 * Requiere sesión.
 *
 * La consulta de UPDATE siempre incluye "AND id_usuario = :usuario_de_la_sesion":
 * el id_usuario viene del servidor (la sesión), nunca del cliente, así que no
 * existe forma de que un usuario modifique el carrito de otro manipulando IDs.
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
$cantidadNueva = campoCantidadRequerido($datos, 'cantidad', null);
if ($cantidadNueva === null) {
    responderError("El campo 'cantidad' es requerido.", 400);
}

$pdo = obtenerConexion();

$stmtProd = $pdo->prepare('SELECT stock FROM productos WHERE id_producto = :id AND estado = "activo"');
$stmtProd->execute(['id' => $idProducto]);
$producto = $stmtProd->fetch();
if (!$producto) {
    responderError("No existe un producto activo con id $idProducto.", 404);
}

// Primero confirmamos que la fila es del usuario de la sesión actual -- recién
// después validamos stock. Si lo hiciéramos al revés, alguien podría recibir un
// error de "stock insuficiente" para un producto que ni siquiera tiene en su
// carrito, lo cual es información engañosa y el orden lógico incorrecto.
$stmtActual = $pdo->prepare('SELECT 1 FROM carrito_items WHERE id_usuario = :u AND id_producto = :p');
$stmtActual->execute(['u' => $usuario['id_usuario'], 'p' => $idProducto]);
if (!$stmtActual->fetch()) {
    responderError('Ese producto no está en tu carrito.', 404);
}

if ($cantidadNueva > (int) $producto['stock']) {
    responderError("No hay stock suficiente. Disponible: {$producto['stock']}.", 409);
}

$stmt = $pdo->prepare('UPDATE carrito_items SET cantidad = :cantidad WHERE id_usuario = :u AND id_producto = :p');
$stmt->execute(['cantidad' => $cantidadNueva, 'u' => $usuario['id_usuario'], 'p' => $idProducto]);

responderJson(['mensaje' => 'Cantidad actualizada.', 'id_producto' => $idProducto, 'cantidad' => $cantidadNueva]);
