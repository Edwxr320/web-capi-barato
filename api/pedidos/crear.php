<?php
/**
 * POST /api/pedidos/crear.php
 * Body: { "direccion_envio": "..." }
 * Requiere sesión. El carrito NO se recibe del cliente -- se lee siempre desde
 * MySQL (carrito_items del usuario de la sesión), así que no hay forma de que
 * el checkout use precios, cantidades o stock que mande el navegador.
 *
 * Todo pasa en UNA transacción: se bloquean (FOR UPDATE) las filas de producto
 * involucradas para que dos checkouts simultáneos no puedan dejar el stock en
 * negativo. Si cualquier paso falla, ROLLBACK completo -- no se crea nada a medias.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';
require_once __DIR__ . '/../includes/carrito_calculo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}

$usuario = requiereLogin();
requiereCsrf();
$datos = leerCuerpoJson();
$direccionCruda = $datos['direccion_envio'] ?? '';
if (!is_string($direccionCruda)) {
    // Ej. un array o un número: no se convierte "a la fuerza" (un array daría "Array").
    responderError('Ingresá una dirección de envío válida.', 400);
}
$direccion = trim($direccionCruda);
if (mb_strlen($direccion) < 5) {
    responderError('Ingresá una dirección de envío válida.', 400);
}
if (mb_strlen($direccion) > 255) {
    responderError('La dirección de envío es demasiado larga (máximo 255 caracteres).', 400);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    // 1) Carrito del usuario, con lock sobre las filas de producto involucradas.
    //    ORDER BY id_producto: todos los checkouts bloquean los productos en el MISMO
    //    orden, lo que evita deadlocks entre dos carritos con productos en común.
    $stmtCarrito = $pdo->prepare(
        'SELECT ci.id_producto, ci.cantidad,
                p.nombre, p.precio_original, p.descuento_porcentaje, p.stock,
                p.envio_gratis, p.costo_envio, p.estado
         FROM carrito_items ci
         JOIN productos p ON p.id_producto = ci.id_producto
         WHERE ci.id_usuario = :u
         ORDER BY ci.id_producto
         FOR UPDATE'
    );
    $stmtCarrito->execute(['u' => $usuario['id_usuario']]);
    $items = $stmtCarrito->fetchAll();

    if (empty($items)) {
        $pdo->rollBack();
        responderError('Tu carrito está vacío.', 400);
    }

    // 2) Validamos cada línea ANTES de escribir nada.
    foreach ($items as $item) {
        if ($item['estado'] !== 'activo') {
            $pdo->rollBack();
            responderError("El producto \"{$item['nombre']}\" ya no está disponible.", 409);
        }
        if ((int) $item['cantidad'] > (int) $item['stock']) {
            $pdo->rollBack();
            responderError(
                "No hay stock suficiente de \"{$item['nombre']}\". Disponible: {$item['stock']}, en tu carrito: {$item['cantidad']}.",
                409
            );
        }
    }

    // 3) Recalculamos todo en el servidor -- nunca se usa nada que mande el cliente.
    $lineas = [];
    foreach ($items as $item) {
        $precioFinal = calcularPrecioFinal((float) $item['precio_original'], $item['descuento_porcentaje']);
        $lineas[] = [
            'id_producto'  => (int) $item['id_producto'],
            'nombre'       => $item['nombre'],
            'cantidad'     => (int) $item['cantidad'],
            'precio_final' => $precioFinal,
            'envio_gratis' => aBooleano($item['envio_gratis']),
            'costo_envio'  => $item['costo_envio'] !== null ? (float) $item['costo_envio'] : null,
        ];
    }
    $resumen = calcularResumenCarrito($lineas);

    // 4) Pedido.
    $stmtPedido = $pdo->prepare(
        'INSERT INTO pedidos (id_usuario, estado, subtotal, costo_envio, total, direccion_envio)
         VALUES (:u, \'pendiente\', :subtotal, :envio, :total, :direccion)'
    );
    $stmtPedido->execute([
        'u'         => $usuario['id_usuario'],
        'subtotal'  => $resumen['subtotal'],
        'envio'     => $resumen['costo_envio'],
        'total'     => $resumen['total'],
        'direccion' => $direccion,
    ]);
    $idPedido = ultimoId($pdo, 'pedidos', 'id_pedido');

    // 5) Detalle (con el precio ya congelado) + descuento de stock, línea por línea.
    $stmtDetalle = $pdo->prepare(
        'INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
         VALUES (:pedido, :producto, :cantidad, :precio, :subtotal)'
    );
    $stmtStock = $pdo->prepare(
        'UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :producto AND stock >= :cantidad2'
    );

    foreach ($lineas as $linea) {
        $stmtDetalle->execute([
            'pedido'   => $idPedido,
            'producto' => $linea['id_producto'],
            'cantidad' => $linea['cantidad'],
            'precio'   => $linea['precio_final'],
            'subtotal' => round($linea['cantidad'] * $linea['precio_final'], 2),
        ]);

        $stmtStock->execute([
            'cantidad'  => $linea['cantidad'],
            'producto'  => $linea['id_producto'],
            'cantidad2' => $linea['cantidad'],
        ]);
        if ($stmtStock->rowCount() === 0) {
            $pdo->rollBack();
            responderError("No se pudo confirmar el stock de \"{$linea['nombre']}\". Intentá de nuevo.", 409);
        }
    }

    // 6) Vaciamos el carrito.
    $stmtVaciar = $pdo->prepare('DELETE FROM carrito_items WHERE id_usuario = :u');
    $stmtVaciar->execute(['u' => $usuario['id_usuario']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 1213 = deadlock, 1205 = lock wait timeout: el motor ya deshizo la transacción.
    // No es un error del cliente ni un 500 genérico: se puede reintentar.
    $codigoMysql = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int) $e->errorInfo[1] : 0;
    if ($codigoMysql === 1213 || $codigoMysql === 1205) {
        responderError('Hay mucha demanda sobre estos productos en este momento. Intentá confirmar de nuevo.', 409);
    }
    throw $e; // el manejador global responde un JSON 500 genérico y lo registra en el log
}

responderJson([
    'mensaje' => 'Pedido creado correctamente.',
    'pedido'  => [
        'id_pedido'   => $idPedido,
        'estado'      => 'pendiente',
        'subtotal'    => $resumen['subtotal'],
        'costo_envio' => $resumen['costo_envio'],
        'total'       => $resumen['total'],
    ],
], 201);
