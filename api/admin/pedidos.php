<?php
/**
 * Pedidos (solo rol admin) — Bloque 5.
 *
 * GET  /api/admin/pedidos.php        ?estado=pendiente|pagado|enviado|completado|cancelado  ?q=#id, nombre o correo
 * GET  /api/admin/pedidos.php?id=12  detalle completo (cliente, dirección, líneas con su precio histórico)
 * POST /api/admin/pedidos.php  (X-CSRF-Token)
 *      { "accion": "estado", "id_pedido": 12, "estado": "pagado" }
 *
 * Flujo de estados autorizado (solo hacia adelante, de a un paso):
 *      pendiente -> pagado -> enviado -> completado
 * "pagado" significa que un administrador REGISTRÓ manualmente el pago (no hay pasarela de pagos).
 *
 * "cancelado": existe en el ENUM, pero las reglas de cancelación (desde qué estados se puede cancelar y si se
 * devuelve el stock) NO están definidas. Por eso este endpoint no permite pasar a "cancelado" ni salir de él:
 * es una decisión pendiente del responsable del proyecto. Cuando se defina, se agrega a TRANSICIONES.
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

iniciarEndpointAdmin(['GET', 'POST']);
$pdo = obtenerConexion();

const ESTADOS = ['pendiente', 'pagado', 'enviado', 'completado', 'cancelado'];
const TRANSICIONES = [
    'pendiente'  => ['pagado'],
    'pagado'     => ['enviado'],
    'enviado'    => ['completado'],
    'completado' => [],
    'cancelado'  => [],
];

function formatearPedido(array $f): array
{
    return [
        'id_pedido'           => (int) $f['id_pedido'],
        'fecha_pedido'        => $f['fecha_pedido'],
        'estado'              => $f['estado'],
        'subtotal'            => (float) $f['subtotal'],
        'costo_envio'         => (float) $f['costo_envio'],
        'total'               => (float) $f['total'],
        'unidades'            => (int) $f['unidades'],
        'cliente'             => ['id_usuario' => (int) $f['id_usuario'], 'nombre' => $f['cliente_nombre'], 'email' => $f['cliente_email']],
        'estados_siguientes'  => TRANSICIONES[$f['estado']] ?? [],
    ];
}

const SQL_PEDIDOS = 'SELECT p.*, u.nombre AS cliente_nombre, u.email AS cliente_email,
                            (SELECT COALESCE(SUM(cantidad),0) FROM detalle_pedido d WHERE d.id_pedido = p.id_pedido) AS unidades
                     FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_usuario';

function pedidoCompleto(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(SQL_PEDIDOS . ' WHERE p.id_pedido = :id');
    $stmt->execute(['id' => $id]);
    $f = $stmt->fetch();
    if (!$f) {
        return null;
    }
    $det = $pdo->prepare(
        'SELECT d.id_producto, d.cantidad, d.precio_unitario, d.subtotal, pr.nombre, pr.estado AS estado_producto,
                pi.url_imagen AS imagen_principal
         FROM detalle_pedido d
         JOIN productos pr ON pr.id_producto = d.id_producto
         LEFT JOIN producto_imagenes pi ON pi.id_producto = d.id_producto AND pi.orden = 1
         WHERE d.id_pedido = :id ORDER BY d.id_detalle'
    );
    $det->execute(['id' => $id]);
    return formatearPedido($f) + [
        'direccion_envio' => $f['direccion_envio'],
        'items' => array_map(fn($i) => [
            'id_producto'      => (int) $i['id_producto'],
            'nombre'           => $i['nombre'],
            'cantidad'         => (int) $i['cantidad'],
            'precio_unitario'  => (float) $i['precio_unitario'],
            'subtotal'         => (float) $i['subtotal'],
            'imagen_principal' => $i['imagen_principal'],
            'estado_producto'  => $i['estado_producto'],
        ], $det->fetchAll()),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = parametroIdOpcional('id');
    if ($id !== null) {
        $p = pedidoCompleto($pdo, $id);
        if (!$p) {
            responderError('Pedido no encontrado.', 404);
        }
        responderJson(['pedido' => $p]);
    }
    $where = [];
    $par = [];
    $estado = $_GET['estado'] ?? '';
    if ($estado !== '' && $estado !== 'todos') {
        if (!in_array($estado, ESTADOS, true)) {
            responderError("El parámetro 'estado' no es válido.", 400);
        }
        $where[] = 'p.estado = :estado';
        $par['estado'] = $estado;
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) > 100) {
        responderError("El parámetro 'q' es demasiado largo.", 400);
    }
    if ($q !== '') {
        if (preg_match('/^#?(\d{1,10})$/', $q, $m)) {
            $where[] = 'p.id_pedido = :idq';
            $par['idq'] = (int) $m[1];
        } else {
            $where[] = '(' . sqlContiene('u.nombre', 'q') . ' OR ' . sqlContiene('u.email', 'q2') . ')';
            $par['q'] = '%' . $q . '%';
            $par['q2'] = '%' . $q . '%';
        }
    }
    $sql = SQL_PEDIDOS . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.fecha_pedido DESC, p.id_pedido DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);
    $conteo = $pdo->query('SELECT estado, COUNT(*) AS n FROM pedidos GROUP BY estado')->fetchAll();
    $porEstado = array_fill_keys(ESTADOS, 0);
    foreach ($conteo as $c) {
        $porEstado[$c['estado']] = (int) $c['n'];
    }
    responderJson(['pedidos' => array_map('formatearPedido', $stmt->fetchAll()), 'por_estado' => $porEstado]);
}

$d = leerCuerpoJson();
campoOpcion($d, 'accion', ['estado']);
$id = campoIdRequerido($d, 'id_pedido');
$nuevo = campoOpcion($d, 'estado', ESTADOS);

$anterior = enTransaccion($pdo, function () use ($pdo, $id, $nuevo) {
    $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $actual = $stmt->fetchColumn();
    if ($actual === false) {
        $pdo->rollBack();
        responderError('Pedido no encontrado.', 404);
    }
    if ($nuevo === 'cancelado' || $actual === 'cancelado') {
        $pdo->rollBack();
        responderError('La cancelación de pedidos todavía no está habilitada: sus reglas (desde qué estado y si se devuelve el stock) están pendientes de definición.', 409);
    }
    if (!in_array($nuevo, TRANSICIONES[$actual], true)) {
        $pdo->rollBack();
        $permitidos = TRANSICIONES[$actual] ? implode(', ', TRANSICIONES[$actual]) : 'ninguno (estado final)';
        responderError("No se puede pasar de \"$actual\" a \"$nuevo\". Siguiente estado permitido: $permitidos.", 409);
    }
    $pdo->prepare('UPDATE pedidos SET estado = :e WHERE id_pedido = :id')->execute(['e' => $nuevo, 'id' => $id]);
    return $actual;
});

responderJson([
    'mensaje' => "Pedido #$id: \"$anterior\" → \"$nuevo\".",
    'pedido'  => pedidoCompleto($pdo, $id),
]);
