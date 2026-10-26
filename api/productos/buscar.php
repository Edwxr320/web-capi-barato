<?php
/**
 * GET /api/productos/buscar.php?q=texto
 *
 * Endpoint público -- no requiere sesión. Busca en MySQL por nombre del
 * producto, nombre de categoría y características (donde antes buscaba
 * el JS en el array local de la Fase 4). Ignora mayúsculas/acentos gracias
 * a la collation utf8mb4_unicode_ci de las columnas de texto.
 *
 * Respuesta: { "productos": [ {...mismo formato que listar.php...} ] }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$consulta = parametroBusquedaRequerido('q');

$pdo = obtenerConexion();

$sql = 'SELECT DISTINCT p.id_producto, p.nombre, p.precio_original, p.descuento_porcentaje,
               p.stock, p.envio_gratis, p.costo_envio, p.proveedor,
               c.id_categoria, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
               pi.url_imagen AS imagen_principal
        FROM productos p
        JOIN categorias c ON p.id_categoria = c.id_categoria
        LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
        LEFT JOIN producto_caracteristicas pc ON pc.id_producto = p.id_producto
        WHERE p.estado = "activo"
          AND (p.nombre LIKE :q1 OR c.nombre LIKE :q2 OR pc.descripcion LIKE :q3)
        ORDER BY p.id_producto';

$comodin = '%' . $consulta . '%';
$stmt = $pdo->prepare($sql);
$stmt->execute(['q1' => $comodin, 'q2' => $comodin, 'q3' => $comodin]);
$filas = $stmt->fetchAll();

$productos = array_map(function ($fila) {
    return [
        'id_producto'          => (int) $fila['id_producto'],
        'nombre'               => $fila['nombre'],
        'precio_original'      => (float) $fila['precio_original'],
        'descuento_porcentaje' => $fila['descuento_porcentaje'] !== null ? (int) $fila['descuento_porcentaje'] : null,
        'precio_final'         => calcularPrecioFinal((float) $fila['precio_original'], $fila['descuento_porcentaje']),
        'stock'                => (int) $fila['stock'],
        'envio_gratis'         => (bool) $fila['envio_gratis'],
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

responderJson(['productos' => $productos, 'consulta' => $consulta]);
