<?php
/**
 * POST /api/carrito/agregar.php
 * Body: { "id_producto": 5, "cantidad": 1 }  (cantidad es opcional, default 1)
 * Requiere sesión.
 *
 * Si el producto ya estaba en el carrito, SUMA la cantidad (no duplica la fila --
 * lo garantiza además la restricción UNIQUE(id_usuario,id_producto) de la tabla).
 * Nunca permite superar el stock disponible.
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
$cantidadAAgregar = campoCantidadRequerido($datos, 'cantidad', 1);

$pdo = obtenerConexion();

$stmtProd = $pdo->prepare('SELECT stock FROM productos WHERE id_producto = :id AND estado = \'activo\'');
$stmtProd->execute(['id' => $idProducto]);
$producto = $stmtProd->fetch();
if (!$producto) {
    responderError("No existe un producto activo con id $idProducto.", 404);
}
$stock = (int) $producto['stock'];

$pdo->beginTransaction();
try {
    $stmtActual = $pdo->prepare('SELECT cantidad FROM carrito_items WHERE id_usuario = :u AND id_producto = :p FOR UPDATE');
    $stmtActual->execute(['u' => $usuario['id_usuario'], 'p' => $idProducto]);
    $filaActual = $stmtActual->fetch();
    $cantidadActual = $filaActual ? (int) $filaActual['cantidad'] : 0;
    $cantidadFinal = $cantidadActual + $cantidadAAgregar;

    if ($cantidadFinal > $stock) {
        $pdo->rollBack();
        responderError("No hay stock suficiente. Disponible: $stock, ya tenés $cantidadActual en el carrito.", 409);
    }

    if ($filaActual) {
        $stmtUpd = $pdo->prepare('UPDATE carrito_items SET cantidad = :cantidad WHERE id_usuario = :u AND id_producto = :p');
        $stmtUpd->execute(['cantidad' => $cantidadFinal, 'u' => $usuario['id_usuario'], 'p' => $idProducto]);
    } else {
        $stmtIns = $pdo->prepare('INSERT INTO carrito_items (id_usuario, id_producto, cantidad) VALUES (:u, :p, :cantidad)');
        $stmtIns->execute(['u' => $usuario['id_usuario'], 'p' => $idProducto, 'cantidad' => $cantidadFinal]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

responderJson(['mensaje' => 'Producto agregado al carrito.', 'id_producto' => $idProducto, 'cantidad' => $cantidadFinal], 201);
