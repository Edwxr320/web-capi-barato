<?php
/**
 * Inventario (solo rol admin) — Bloque 4.
 *
 * GET  /api/admin/inventario.php
 *        ?filtro=todos|bajo|sin_stock   (default todos)
 *        ?q=texto                       (busca en el nombre)
 *        ?categoria=slug
 *      Lista existencias de TODOS los productos (activos e inactivos) con su marca de stock bajo.
 *      Respuesta: { umbral, resumen:{...}, resumen_categorias:[...], productos:[...] }
 *
 * POST /api/admin/inventario.php       (requiere X-CSRF-Token)
 *      Body: { "id_producto": 5, "operacion": "fijar"|"sumar"|"restar", "cantidad": 10 }
 *        - fijar:  el stock pasa a ser exactamente "cantidad" (0..1.000.000)
 *        - sumar:  ingreso de mercadería (1..1.000.000)
 *        - restar: baja manual (1..1.000.000); nunca deja el stock negativo (409)
 *      Se hace dentro de una transacción con la fila bloqueada (FOR UPDATE), igual que el checkout:
 *      un ajuste y una compra simultáneos no se pisan.
 *
 * Stock bajo: stock <= umbral (STOCK_BAJO_UMBRAL en config.php, 5 por defecto). Incluye stock 0.
 * Las alertas (resumen.stock_bajo / resumen.sin_stock) cuentan solo productos ACTIVOS, porque los
 * inactivos no se venden. No existe tabla de movimientos (decisión del proyecto): no hay historial
 * de ajustes, solo el valor actual.
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

iniciarEndpointAdmin(['GET', 'POST']);
$pdo = obtenerConexion();
$umbral = umbralStockBajo();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filtro = $_GET['filtro'] ?? 'todos';
    if (!in_array($filtro, ['todos', 'bajo', 'sin_stock'], true)) {
        responderError("El parámetro 'filtro' debe ser todos, bajo o sin_stock.", 400);
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) > 100) {
        responderError("El parámetro 'q' es demasiado largo.", 400);
    }
    $slug = parametroCategoriaOpcional('categoria');

    $sql = 'SELECT p.id_producto, p.nombre, p.stock, p.estado, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                   pi.url_imagen AS imagen_principal
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
            WHERE 1 = 1';
    $parametros = [];
    if ($filtro === 'bajo') {
        $sql .= ' AND p.stock <= :umbral';
        $parametros['umbral'] = $umbral;
    } elseif ($filtro === 'sin_stock') {
        $sql .= ' AND p.stock = 0';
    }
    if ($q !== '') {
        $sql .= ' AND ' . sqlContiene('p.nombre', 'q');
        $parametros['q'] = '%' . $q . '%';
    }
    if ($slug !== null) {
        $cat = $pdo->prepare('SELECT 1 FROM categorias WHERE slug = :slug');
        $cat->execute(['slug' => $slug]);
        if (!$cat->fetch()) {
            responderError('La categoría indicada no existe.', 404);
        }
        $sql .= ' AND c.slug = :slug';
        $parametros['slug'] = $slug;
    }
    $sql .= ' ORDER BY p.stock ASC, p.id_producto ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    $productos = array_map(function ($f) use ($umbral) {
        $stock = (int) $f['stock'];
        return [
            'id_producto'      => (int) $f['id_producto'],
            'nombre'           => $f['nombre'],
            'categoria'        => ['nombre' => $f['categoria_nombre'], 'slug' => $f['categoria_slug']],
            'stock'            => $stock,
            'estado'           => $f['estado'],
            'stock_bajo'       => $stock <= $umbral,
            'sin_stock'        => $stock === 0,
            'imagen_principal' => $f['imagen_principal'],
        ];
    }, $stmt->fetchAll());

    $r = $pdo->prepare(
        'SELECT COUNT(*) AS productos,
                SUM(CASE WHEN estado = \'activo\' THEN 1 ELSE 0 END) AS activos,
                SUM(CASE WHEN estado = \'activo\' AND stock <= :umbral THEN 1 ELSE 0 END) AS stock_bajo,
                SUM(CASE WHEN estado = \'activo\' AND stock = 0 THEN 1 ELSE 0 END) AS sin_stock,
                COALESCE(SUM(stock), 0) AS unidades
         FROM productos'
    );
    $r->execute(['umbral' => $umbral]);
    $res = $r->fetch();

    // Resumen por categoría (siempre sobre el inventario completo, sin aplicar los filtros de la lista).
    $porCategoria = $pdo->prepare(
        'SELECT c.id_categoria, c.nombre, c.slug,
                COUNT(p.id_producto) AS productos,
                COALESCE(SUM(CASE WHEN p.estado = \'activo\' THEN 1 ELSE 0 END), 0) AS activos,
                COALESCE(SUM(p.stock), 0) AS unidades,
                COALESCE(SUM(CASE WHEN p.estado = \'activo\' AND p.stock <= :umbral THEN 1 ELSE 0 END), 0) AS stock_bajo,
                COALESCE(SUM(CASE WHEN p.estado = \'activo\' AND p.stock = 0 THEN 1 ELSE 0 END), 0) AS sin_stock
         FROM categorias c
         LEFT JOIN productos p ON p.id_categoria = c.id_categoria
         GROUP BY c.id_categoria ORDER BY c.nombre'
    );
    $porCategoria->execute(['umbral' => $umbral]);
    $resumenCategorias = array_map(fn($f) => [
        'categoria'  => $f['nombre'],
        'slug'       => $f['slug'],
        'productos'  => (int) $f['productos'],
        'activos'    => (int) $f['activos'],
        'unidades'   => (int) $f['unidades'],
        'stock_bajo' => (int) $f['stock_bajo'],
        'sin_stock'  => (int) $f['sin_stock'],
    ], $porCategoria->fetchAll());

    responderJson([
        'umbral'    => $umbral,
        'resumen'   => [
            'productos'        => (int) $res['productos'],
            'activos'          => (int) $res['activos'],
            'stock_bajo'       => (int) $res['stock_bajo'],
            'sin_stock'        => (int) $res['sin_stock'],
            'unidades_totales' => (int) $res['unidades'],
        ],
        'resumen_categorias' => $resumenCategorias,
        'productos' => $productos,
    ]);
}

// ---------------------------------------------------------------- POST: ajuste de stock
$datos = leerCuerpoJson();
$idProducto = campoIdRequerido($datos, 'id_producto');
$operacion = campoOpcion($datos, 'operacion', ['fijar', 'sumar', 'restar']);
$cantidad = campoEnteroRango($datos, 'cantidad', $operacion === 'fijar' ? 0 : 1, STOCK_MAXIMO);

$resultado = enTransaccion($pdo, function () use ($pdo, $idProducto, $operacion, $cantidad) {
    $stmt = $pdo->prepare('SELECT nombre, stock FROM productos WHERE id_producto = :id FOR UPDATE');
    $stmt->execute(['id' => $idProducto]);
    $producto = $stmt->fetch();
    if (!$producto) {
        $pdo->rollBack();
        responderError("No existe el producto con id $idProducto.", 404);
    }
    $anterior = (int) $producto['stock'];
    $nuevo = match ($operacion) {
        'fijar'  => $cantidad,
        'sumar'  => $anterior + $cantidad,
        'restar' => $anterior - $cantidad,
    };
    if ($nuevo < 0) {
        $pdo->rollBack();
        responderError("No se puede restar $cantidad: el stock actual de \"{$producto['nombre']}\" es $anterior y no puede quedar negativo.", 409);
    }
    if ($nuevo > STOCK_MAXIMO) {
        $pdo->rollBack();
        responderError('El stock resultante supera el máximo permitido (' . STOCK_MAXIMO . ').', 400);
    }
    $pdo->prepare('UPDATE productos SET stock = :stock WHERE id_producto = :id')
        ->execute(['stock' => $nuevo, 'id' => $idProducto]);
    return ['nombre' => $producto['nombre'], 'anterior' => $anterior, 'nuevo' => $nuevo];
});

responderJson([
    'mensaje'  => "Stock de \"{$resultado['nombre']}\" actualizado: {$resultado['anterior']} → {$resultado['nuevo']}.",
    'producto' => [
        'id_producto'    => $idProducto,
        'nombre'         => $resultado['nombre'],
        'stock_anterior' => $resultado['anterior'],
        'stock'          => $resultado['nuevo'],
        'stock_bajo'     => $resultado['nuevo'] <= $umbral,
        'sin_stock'      => $resultado['nuevo'] === 0,
    ],
]);
