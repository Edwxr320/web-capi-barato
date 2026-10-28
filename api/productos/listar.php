<?php
/**
 * GET /api/productos/listar.php
 * GET /api/productos/listar.php?categoria=celulares
 *
 * Endpoint público -- no requiere sesión. Catálogo completo, o filtrado por
 * categoría si se pasa ?categoria=<slug>. Si el slug no existe, 404 (evita
 * que un typo en el frontend devuelva silenciosamente "sin productos").
 *
 * Respuesta: { "productos": [ {...}, ... ] }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$pdo = obtenerConexion();
$slugCategoria = parametroCategoriaOpcional('categoria');

$sql = 'SELECT p.id_producto, p.nombre, p.precio_original, p.descuento_porcentaje,
               p.stock, p.envio_gratis, p.costo_envio, p.proveedor,
               c.id_categoria, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
               pi.url_imagen AS imagen_principal
        FROM productos p
        JOIN categorias c ON p.id_categoria = c.id_categoria
        LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
        WHERE p.estado = \'activo\'';

$parametros = [];
if ($slugCategoria !== null) {
    // Validamos que la categoría exista antes de filtrar -- si no existe, es un error
    // del que hay que avisar, no una lista vacía silenciosa.
    $stmtCat = $pdo->prepare('SELECT id_categoria FROM categorias WHERE slug = :slug');
    $stmtCat->execute(['slug' => $slugCategoria]);
    if (!$stmtCat->fetch()) {
        responderError("La categoría '$slugCategoria' no existe.", 404);
    }
    $sql .= ' AND c.slug = :slug';
    $parametros['slug'] = $slugCategoria;
}
$sql .= ' ORDER BY p.id_producto';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$filas = $stmt->fetchAll();

$productos = array_map(function ($fila) {
    return [
        'id_producto'          => (int) $fila['id_producto'],
        'nombre'               => $fila['nombre'],
        'precio_original'      => (float) $fila['precio_original'],
        'descuento_porcentaje' => $fila['descuento_porcentaje'] !== null ? (int) $fila['descuento_porcentaje'] : null,
        'precio_final'         => calcularPrecioFinal((float) $fila['precio_original'], $fila['descuento_porcentaje']),
        'stock'                => (int) $fila['stock'],
        'envio_gratis'         => aBooleano($fila['envio_gratis']),
        'costo_envio'          => $fila['costo_envio'] !== null ? (float) $fila['costo_envio'] : null,
        'proveedor'            => $fila['proveedor'],
        'categoria'            => [
            'id_categoria' => (int) $fila['id_categoria'],
            'nombre'       => $fila['categoria_nombre'],
            'slug'         => $fila['categoria_slug'],
        ],
        'imagen_principal'     => $fila['imagen_principal'],
    ];
}, $filas);

responderJson(['productos' => $productos]);
