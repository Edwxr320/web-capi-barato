<?php
/**
 * POST /api/carrito/fusionar.php
 * Body: { "items": [ {"id_producto": 5, "cantidad": 2}, ... ] }
 * Requiere sesión. Se llama una sola vez, justo después del login/registro,
 * con el carrito de invitado que tenía en localStorage.
 *
 * Reglas (confirmadas):
 *  - No duplicar productos: si ya estaba en el carrito de MySQL, se SUMAN las
 *    cantidades en la misma fila (lo refuerza además el UNIQUE(id_usuario,id_producto)).
 *  - Nunca superar el stock disponible: si la suma excede el stock, se limita
 *    al máximo disponible (no se rechaza la fusión entera por un solo ítem).
 *  - Todo pasa dentro de una transacción: o se fusiona todo correctamente, o
 *    no se aplica ningún cambio.
 *  - Los ítems que ya no existen o fueron desactivados se ignoran y se listan
 *    en "omitidos" para que el frontend pueda avisar si quiere.
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

if (!isset($datos['items']) || !is_array($datos['items'])) {
    responderError("El campo 'items' es requerido y debe ser una lista.", 400);
}

$itemsLocales = [];
foreach ($datos['items'] as $item) {
    if (!is_array($item) || !isset($item['id_producto'])) {
        continue;
    }
    $idProducto = filter_var($item['id_producto'], FILTER_VALIDATE_INT);
    $cantidad = filter_var($item['cantidad'] ?? 1, FILTER_VALIDATE_INT);
    if ($idProducto === false || $idProducto < 1 || $cantidad === false || $cantidad < 1) {
        continue;
    }
    $itemsLocales[$idProducto] = ($itemsLocales[$idProducto] ?? 0) + $cantidad;
}

$ajustadosPorStock = [];
$omitidos = [];

$pdo = obtenerConexion();
$pdo->beginTransaction();
try {
    foreach ($itemsLocales as $idProducto => $cantidadLocal) {
        $stmtProd = $pdo->prepare('SELECT stock FROM productos WHERE id_producto = :id AND estado = \'activo\' FOR UPDATE');
        $stmtProd->execute(['id' => $idProducto]);
        $producto = $stmtProd->fetch();
        if (!$producto) {
            $omitidos[] = $idProducto;
            continue;
        }
        $stock = (int) $producto['stock'];

        $stmtActual = $pdo->prepare('SELECT cantidad FROM carrito_items WHERE id_usuario = :u AND id_producto = :p FOR UPDATE');
        $stmtActual->execute(['u' => $usuario['id_usuario'], 'p' => $idProducto]);
        $filaActual = $stmtActual->fetch();
        $cantidadEnMysql = $filaActual ? (int) $filaActual['cantidad'] : 0;

        $cantidadDeseada = $cantidadEnMysql + $cantidadLocal;
        $cantidadFinal = min($cantidadDeseada, $stock);
        if ($cantidadFinal < $cantidadDeseada) {
            $ajustadosPorStock[] = ['id_producto' => $idProducto, 'solicitado' => $cantidadDeseada, 'ajustado_a' => $cantidadFinal];
        }
        if ($cantidadFinal < 1) {
            continue;
        }

        if ($filaActual) {
            $stmt = $pdo->prepare('UPDATE carrito_items SET cantidad = :cantidad WHERE id_usuario = :u AND id_producto = :p');
        } else {
            $stmt = $pdo->prepare('INSERT INTO carrito_items (id_usuario, id_producto, cantidad) VALUES (:u, :p, :cantidad)');
        }
        $stmt->execute(['cantidad' => $cantidadFinal, 'u' => $usuario['id_usuario'], 'p' => $idProducto]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

responderJson([
    'mensaje'             => 'Carrito fusionado correctamente.',
    'ajustados_por_stock' => $ajustadosPorStock,
    'omitidos'            => $omitidos,
]);
