<?php
/**
 * Estadísticas y dashboard (solo rol admin) — Bloque 5. Todo se calcula en el momento desde las tablas reales.
 *
 * GET /api/admin/estadisticas.php?dias=14&periodo=dia
 *   dias:    1..90, default 14 — tamaño de la serie diaria.
 *   periodo: dia|semana|mes, default dia — serie agrupada del gráfico del dashboard, con su propia
 *            ventana (14 días / 8 semanas / 6 meses) y la comparación con el período anterior.
 *
 * Definiciones (las mismas que se muestran en el panel):
 *  - "Ventas confirmadas": pedidos en estado pagado, enviado o completado (pago registrado por un administrador).
 *  - "Pendiente de pago": pedidos en estado pendiente. No se suman a las ventas confirmadas.
 *  - Los pedidos "cancelado" no se suman a nada.
 *  - Ticket promedio = ventas confirmadas / cantidad de pedidos confirmados.
 *  - Ventas por categoría y productos más vendidos: se calculan sobre detalle_pedido (precio histórico) de los
 *    pedidos confirmados; la categoría es la ACTUAL del producto.
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

iniciarEndpointAdmin(['GET']);
$pdo = obtenerConexion();
$umbral = umbralStockBajo();
$dias = 14;
if (isset($_GET['dias']) && $_GET['dias'] !== '') {
    $dias = parametroIdRequerido('dias');
    if ($dias > 90) {
        responderError("El parámetro 'dias' debe estar entre 1 y 90.", 400);
    }
}
const CONFIRMADOS = "('pagado','enviado','completado')";

$k = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM pedidos) AS pedidos_total,
        (SELECT COUNT(*) FROM pedidos WHERE estado IN ' . CONFIRMADOS . ') AS pedidos_confirmados,
        (SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado IN ' . CONFIRMADOS . ') AS ventas_confirmadas,
        (SELECT COUNT(*) FROM pedidos WHERE estado = \'pendiente\') AS pedidos_pendientes,
        (SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado = \'pendiente\') AS monto_pendiente,
        (SELECT COUNT(*) FROM usuarios WHERE rol = \'cliente\') AS clientes,
        (SELECT COUNT(*) FROM usuarios WHERE rol = \'cliente\' AND estado = \'activo\') AS clientes_activos,
        (SELECT COUNT(*) FROM productos) AS productos,
        (SELECT COUNT(*) FROM productos WHERE estado = \'activo\') AS productos_activos,
        (SELECT COUNT(*) FROM categorias) AS categorias'
)->fetch();
$alertas = $pdo->prepare(
    'SELECT p.id_producto, p.nombre, p.stock FROM productos p WHERE p.estado = \'activo\' AND p.stock <= :u ORDER BY p.stock, p.id_producto'
);
$alertas->execute(['u' => $umbral]);
$listaAlertas = array_map(fn($f) => ['id_producto' => (int) $f['id_producto'], 'nombre' => $f['nombre'], 'stock' => (int) $f['stock']], $alertas->fetchAll());

$porEstado = array_fill_keys(['pendiente', 'pagado', 'enviado', 'completado', 'cancelado'], ['pedidos' => 0, 'monto' => 0.0]);
foreach ($pdo->query('SELECT estado, COUNT(*) AS n, SUM(total) AS monto FROM pedidos GROUP BY estado') as $f) {
    $porEstado[$f['estado']] = ['pedidos' => (int) $f['n'], 'monto' => round((float) $f['monto'], 2)];
}

$porCategoria = array_map(fn($f) => [
    'categoria' => $f['nombre'], 'unidades' => (int) $f['unidades'], 'monto' => round((float) $f['monto'], 2),
], $pdo->query(
    'SELECT c.nombre, COALESCE(SUM(d.cantidad),0) AS unidades, COALESCE(SUM(d.subtotal),0) AS monto
     FROM categorias c
     LEFT JOIN productos pr ON pr.id_categoria = c.id_categoria
     LEFT JOIN detalle_pedido d ON d.id_producto = pr.id_producto
          AND d.id_pedido IN (SELECT id_pedido FROM pedidos WHERE estado IN ' . CONFIRMADOS . ')
     GROUP BY c.id_categoria ORDER BY monto DESC, c.nombre'
)->fetchAll());

$top = array_map(fn($f) => [
    'id_producto' => (int) $f['id_producto'], 'nombre' => $f['nombre'], 'unidades' => (int) $f['unidades'], 'monto' => round((float) $f['monto'], 2),
], $pdo->query(
    'SELECT pr.id_producto, pr.nombre, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS monto
     FROM detalle_pedido d JOIN pedidos p ON p.id_pedido = d.id_pedido JOIN productos pr ON pr.id_producto = d.id_producto
     WHERE p.estado IN ' . CONFIRMADOS . '
     GROUP BY pr.id_producto ORDER BY unidades DESC, monto DESC, pr.id_producto LIMIT 5'
)->fetchAll());

// Serie diaria de los últimos N días (incluye días sin ventas, con 0).
$serie = [];
// "Hoy" según MySQL (mismo reloj que fecha_pedido), para no desfasar días si PHP y MySQL usan otra zona horaria.
$hoy = new DateTimeImmutable($pdo->query('SELECT CURRENT_DATE')->fetchColumn());
for ($i = $dias - 1; $i >= 0; $i--) {
    $serie[$hoy->modify("-$i days")->format('Y-m-d')] = ['pedidos' => 0, 'ventas_confirmadas' => 0.0];
}
$stmt = $pdo->prepare(
    'SELECT ' . sqlInicioDe('day', 'fecha_pedido') . ' AS dia, COUNT(*) AS pedidos,
            COALESCE(SUM(CASE WHEN estado IN ' . CONFIRMADOS . ' THEN total END), 0) AS confirmadas
     FROM pedidos WHERE fecha_pedido >= :desde GROUP BY ' . sqlInicioDe('day', 'fecha_pedido')
);
$stmt->execute(['desde' => $hoy->modify('-' . ($dias - 1) . ' days')->format('Y-m-d 00:00:00')]);
foreach ($stmt as $f) {
    if (isset($serie[$f['dia']])) {
        $serie[$f['dia']] = ['pedidos' => (int) $f['pedidos'], 'ventas_confirmadas' => round((float) $f['confirmadas'], 2)];
    }
}

// ---------------------------------------------------------------------------------------------------
// Gráfico del dashboard: serie agrupada por día / semana / mes + comparación con el período anterior.
// Es una vista APARTE de "serie_diaria" (que sigue dependiendo solo de "dias"): tiene su propia ventana.
// Mismas reglas de negocio que los indicadores: "ventas_confirmadas" suma solo pedidos pagado/enviado/completado.
// ---------------------------------------------------------------------------------------------------
$periodo = isset($_GET['periodo']) && $_GET['periodo'] !== ''
    ? campoOpcion($_GET, 'periodo', ['dia', 'semana', 'mes'])
    : 'dia';

// Cada período define: cuántos puntos, cómo agrupar en SQL y cómo avanzar un punto en PHP.
$config = [
    'dia'    => ['puntos' => 14, 'paso' => '1 day',   'etiqueta' => 'Últimos 14 días',
                 'sql' => sqlInicioDe('day', 'fecha_pedido')],
    'semana' => ['puntos' => 8,  'paso' => '1 week',  'etiqueta' => 'Últimas 8 semanas',
                 'sql' => sqlInicioDe('week', 'fecha_pedido')],
    'mes'    => ['puntos' => 6,  'paso' => '1 month', 'etiqueta' => 'Últimos 6 meses',
                 'sql' => sqlInicioDe('month', 'fecha_pedido')],
][$periodo];

// Inicio del punto que contiene a "hoy" (lunes de la semana / día 1 del mes), y de ahí hacia atrás.
$puntoActual = match ($periodo) {
    'semana' => $hoy->modify('-' . ((int) $hoy->format('N') - 1) . ' days'),
    'mes'    => $hoy->modify('first day of this month'),
    default  => $hoy,
};
$inicioVentana = $puntoActual->modify('-' . ($config['puntos'] - 1) . ' ' . substr($config['paso'], 2));
$inicioAnterior = $inicioVentana->modify('-' . $config['puntos'] . ' ' . substr($config['paso'], 2));

$puntos = [];
for ($i = 0; $i < $config['puntos']; $i++) {
    $desdePunto = $inicioVentana->modify("+$i " . substr($config['paso'], 2));
    $finPunto = $desdePunto->modify('+' . $config['paso'])->modify('-1 day');
    $puntos[$desdePunto->format('Y-m-d')] = [
        'inicio' => $desdePunto->format('Y-m-d'),
        'fin'    => $finPunto->format('Y-m-d'),
        'pedidos' => 0, 'pedidos_confirmados' => 0, 'ventas_confirmadas' => 0.0,
    ];
}

$stmtGrafico = $pdo->prepare(
    'SELECT ' . $config['sql'] . ' AS punto,
            COUNT(*) AS pedidos,
            COALESCE(SUM(CASE WHEN estado IN ' . CONFIRMADOS . ' THEN 1 ELSE 0 END), 0) AS confirmados,
            COALESCE(SUM(CASE WHEN estado IN ' . CONFIRMADOS . ' THEN total END), 0) AS ventas
     FROM pedidos WHERE fecha_pedido >= :desde GROUP BY punto'
);
$stmtGrafico->execute(['desde' => $inicioVentana->format('Y-m-d 00:00:00')]);
foreach ($stmtGrafico as $f) {
    if (isset($puntos[$f['punto']])) {
        $puntos[$f['punto']]['pedidos'] = (int) $f['pedidos'];
        $puntos[$f['punto']]['pedidos_confirmados'] = (int) $f['confirmados'];
        $puntos[$f['punto']]['ventas_confirmadas'] = round((float) $f['ventas'], 2);
    }
}

// Total del período y del período anterior (misma cantidad de puntos, inmediatamente antes).
$totalVentas = round(array_sum(array_column($puntos, 'ventas_confirmadas')), 2);
$totalPedidos = array_sum(array_column($puntos, 'pedidos'));
$totalConfirmados = array_sum(array_column($puntos, 'pedidos_confirmados'));

$stmtAnterior = $pdo->prepare(
    'SELECT COUNT(*) AS pedidos,
            COALESCE(SUM(CASE WHEN estado IN ' . CONFIRMADOS . ' THEN 1 ELSE 0 END), 0) AS confirmados,
            COALESCE(SUM(CASE WHEN estado IN ' . CONFIRMADOS . ' THEN total END), 0) AS ventas
     FROM pedidos WHERE fecha_pedido >= :desde AND fecha_pedido < :hasta'
);
$stmtAnterior->execute([
    'desde' => $inicioAnterior->format('Y-m-d 00:00:00'),
    'hasta' => $inicioVentana->format('Y-m-d 00:00:00'),
]);
$ant = $stmtAnterior->fetch();
$ventasAnterior = round((float) $ant['ventas'], 2);
$confirmadosAnterior = (int) $ant['confirmados'];

/** Variación porcentual contra el período anterior. null si el anterior fue 0: no se inventa un "+100 %". */
$variacion = static fn(float $actual, float $previo): ?float
    => $previo > 0 ? round(($actual - $previo) / $previo * 100, 1) : null;

$grafico = [
    'periodo'  => $periodo,
    'etiqueta' => $config['etiqueta'],
    'desde'    => $inicioVentana->format('Y-m-d'),
    'hasta'    => $puntoActual->modify('+' . $config['paso'])->modify('-1 day')->format('Y-m-d'),
    'puntos'   => array_values($puntos),
    'total'    => [
        'ventas_confirmadas'  => $totalVentas,
        'pedidos'             => $totalPedidos,
        'pedidos_confirmados' => $totalConfirmados,
    ],
    'anterior' => [
        'desde' => $inicioAnterior->format('Y-m-d'),
        'hasta' => $inicioVentana->modify('-1 day')->format('Y-m-d'),
        'ventas_confirmadas'  => $ventasAnterior,
        'pedidos'             => (int) $ant['pedidos'],
        'pedidos_confirmados' => $confirmadosAnterior,
    ],
    'variacion' => [
        'ventas'              => $variacion($totalVentas, $ventasAnterior),
        'pedidos_confirmados' => $variacion((float) $totalConfirmados, (float) $confirmadosAnterior),
    ],
];

$ultimos = array_map(fn($f) => [
    'id_pedido' => (int) $f['id_pedido'], 'fecha_pedido' => $f['fecha_pedido'], 'estado' => $f['estado'],
    'total' => (float) $f['total'], 'cliente' => $f['nombre'],
], $pdo->query('SELECT p.id_pedido, p.fecha_pedido, p.estado, p.total, u.nombre FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_usuario ORDER BY p.fecha_pedido DESC, p.id_pedido DESC LIMIT 5')->fetchAll());

$confirmados = (int) $k['pedidos_confirmados'];
responderJson([
    'generado'   => $pdo->query(sqlAhoraTexto())->fetchColumn(),
    'indicadores' => [
        'ventas_confirmadas'  => round((float) $k['ventas_confirmadas'], 2),
        'pedidos_confirmados' => $confirmados,
        'ticket_promedio'     => $confirmados ? round((float) $k['ventas_confirmadas'] / $confirmados, 2) : 0.0,
        'pedidos_total'       => (int) $k['pedidos_total'],
        'pedidos_pendientes'  => (int) $k['pedidos_pendientes'],
        'monto_pendiente'     => round((float) $k['monto_pendiente'], 2),
        'clientes'            => (int) $k['clientes'],
        'clientes_activos'    => (int) $k['clientes_activos'],
        'productos'           => (int) $k['productos'],
        'productos_activos'   => (int) $k['productos_activos'],
        'categorias'          => (int) $k['categorias'],
        'alertas_stock'       => count($listaAlertas),
    ],
    'umbral_stock_bajo'    => $umbral,
    'alertas_stock'        => $listaAlertas,
    'pedidos_por_estado'   => $porEstado,
    'ventas_por_categoria' => $porCategoria,
    'productos_mas_vendidos' => $top,
    'serie_diaria'         => array_map(fn($dia, $v) => ['dia' => $dia] + $v, array_keys($serie), $serie),
    'grafico'              => $grafico,
    'ultimos_pedidos'      => $ultimos,
]);
