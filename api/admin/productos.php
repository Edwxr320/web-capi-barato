<?php
/**
 * CRUD de productos (solo rol admin) — Bloque 5.
 *
 * GET  /api/admin/productos.php                      listado (?q=texto, ?categoria=slug, ?estado=activo|inactivo|todos)
 * GET  /api/admin/productos.php?id=5                 detalle completo (descripción, imágenes, características)
 * POST /api/admin/productos.php  (X-CSRF-Token)      { "accion": "crear" | "actualizar" | "estado" | "eliminar", ... }
 *
 *   crear / actualizar: { [id_producto], nombre, id_categoria, descripcion?, precio_original, descuento_porcentaje?,
 *                         stock, proveedor?, envio_gratis, costo_envio?, estado, imagenes:[rutas], caracteristicas:[textos] }
 *     - Precio final = precio_original − descuento % (lo calcula siempre el servidor, igual que en la tienda).
 *     - envio_gratis = false exige costo_envio (CHECK de la tabla); con envío gratis el costo se guarda NULL.
 *     - imagenes: 1 a 10 rutas "img/...(png|jpg|jpeg|webp|gif)" de archivos que EXISTEN en el sitio
 *       (las nuevas se suben antes con api/admin/imagenes.php). El orden de la lista es el orden de la galería.
 *     - caracteristicas: 0 a 30 textos de 1 a 255 caracteres.
 *     - actualizar reemplaza imágenes y características por las enviadas. Cambiar el precio NO altera pedidos
 *       anteriores: detalle_pedido guarda su propio precio_unitario.
 *   estado:   { id_producto, estado: "activo" | "inactivo" }
 *   eliminar: { id_producto }  -> si el producto tiene ventas NO se borra: se desactiva (estado='inactivo') y se
 *             informa resultado="desactivado". Sin ventas se borra físicamente (con sus imágenes, características y
 *             las líneas de carrito que lo tuvieran, por las claves foráneas ON DELETE CASCADE).
 */
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/almacen_imagenes.php';

iniciarEndpointAdmin(['GET', 'POST']);
$pdo = obtenerConexion();


function productoCompleto(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                (SELECT COALESCE(SUM(d.cantidad),0) FROM detalle_pedido d WHERE d.id_producto = p.id_producto) AS unidades_vendidas
         FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE p.id_producto = :id'
    );
    $stmt->execute(['id' => $id]);
    $f = $stmt->fetch();
    if (!$f) {
        return null;
    }
    $img = $pdo->prepare('SELECT url_imagen FROM producto_imagenes WHERE id_producto = :id ORDER BY orden, id_imagen');
    $img->execute(['id' => $id]);
    $car = $pdo->prepare('SELECT descripcion FROM producto_caracteristicas WHERE id_producto = :id ORDER BY orden, id_caracteristica');
    $car->execute(['id' => $id]);
    return formatearProducto($f) + [
        'descripcion'     => $f['descripcion'],
        'imagenes'        => array_column($img->fetchAll(), 'url_imagen'),
        'caracteristicas' => array_column($car->fetchAll(), 'descripcion'),
    ];
}

function formatearProducto(array $f): array
{
    $descuento = $f['descuento_porcentaje'] !== null ? (int) $f['descuento_porcentaje'] : null;
    return [
        'id_producto'          => (int) $f['id_producto'],
        'nombre'               => $f['nombre'],
        'categoria'            => ['id_categoria' => (int) $f['id_categoria'], 'nombre' => $f['categoria_nombre'], 'slug' => $f['categoria_slug']],
        'precio_original'      => (float) $f['precio_original'],
        'descuento_porcentaje' => $descuento,
        'precio_final'         => calcularPrecioFinal((float) $f['precio_original'], $descuento),
        'stock'                => (int) $f['stock'],
        'proveedor'            => $f['proveedor'],
        'envio_gratis'         => aBooleano($f['envio_gratis']),
        'costo_envio'          => $f['costo_envio'] !== null ? (float) $f['costo_envio'] : null,
        'estado'               => $f['estado'],
        'imagen_principal'     => $f['imagen_principal'] ?? null,
        'unidades_vendidas'    => (int) $f['unidades_vendidas'],
        'tiene_ventas'         => (int) $f['unidades_vendidas'] > 0,
        'fecha_actualizacion'  => $f['fecha_actualizacion'] ?? null,
    ];
}

/**
 * Valida una referencia de imagen. Acepta una ruta "img/…" de un archivo que existe en el sitio
 * (lo de siempre) y, cuando el almacenamiento es Supabase, la URL pública del bucket configurado.
 * Ninguna otra URL se acepta: ver referenciaImagenValida() en includes/almacen_imagenes.php.
 */
function rutaImagenValida($ruta): bool
{
    return referenciaImagenValida($ruta);
}

/** Lee y valida todos los campos de crear/actualizar. Devuelve un array listo para guardar. */
function leerCamposProducto(PDO $pdo, array $d): array
{
    $c = [];
    $c['nombre'] = campoTexto($d, 'nombre', 3, 150);
    $c['id_categoria'] = campoIdRequerido($d, 'id_categoria');
    $cat = $pdo->prepare('SELECT 1 FROM categorias WHERE id_categoria = :id');
    $cat->execute(['id' => $c['id_categoria']]);
    if (!$cat->fetch()) {
        responderError('La categoría elegida no existe.', 400);
    }
    $c['descripcion'] = campoTexto($d, 'descripcion', 0, 5000, false);
    $c['precio_original'] = campoDecimal($d, 'precio_original', 0.01, 99999999.99);
    $desc = campoEnteroRango($d, 'descuento_porcentaje', 0, 99, false);
    $c['descuento_porcentaje'] = $desc ?: null; // 0 o vacío = sin descuento (el CHECK exige 1..99 o NULL)
    $c['stock'] = campoEnteroRango($d, 'stock', 0, STOCK_MAXIMO);
    $c['proveedor'] = campoTexto($d, 'proveedor', 0, 100, false);
    $c['envio_gratis'] = campoBooleano($d, 'envio_gratis');
    if ($c['envio_gratis']) {
        $c['costo_envio'] = null;
    } else {
        $c['costo_envio'] = campoDecimal($d, 'costo_envio', 0, 99999999.99);
    }
    $c['estado'] = campoOpcion($d, 'estado', ['activo', 'inactivo']);

    $imagenes = $d['imagenes'] ?? null;
    if (!is_array($imagenes) || count($imagenes) < 1 || count($imagenes) > 10 || !array_is_list($imagenes)) {
        responderError("El campo 'imagenes' debe ser una lista de 1 a 10 rutas.", 400);
    }
    foreach ($imagenes as $ruta) {
        if (!rutaImagenValida($ruta)) {
            responderError('Imagen no válida: ' . (is_string($ruta) ? mb_substr($ruta, 0, 80) : 'valor no textual') . '. Subila primero o elegí una existente.', 400);
        }
    }
    if (count(array_unique($imagenes)) !== count($imagenes)) {
        responderError('Hay imágenes repetidas.', 400);
    }
    $c['imagenes'] = $imagenes;

    $car = $d['caracteristicas'] ?? [];
    if (!is_array($car) || count($car) > 30 || !array_is_list($car)) {
        responderError("El campo 'caracteristicas' debe ser una lista de hasta 30 textos.", 400);
    }
    $limpias = [];
    foreach ($car as $texto) {
        if (!is_string($texto)) {
            responderError('Cada característica debe ser texto.', 400);
        }
        $texto = trim($texto);
        if ($texto === '') {
            continue;
        }
        if (mb_strlen($texto) > 255) {
            responderError('Cada característica puede tener hasta 255 caracteres.', 400);
        }
        $limpias[] = $texto;
    }
    $c['caracteristicas'] = $limpias;
    return $c;
}

function guardarImagenesYCaracteristicas(PDO $pdo, int $id, array $c): void
{
    $pdo->prepare('DELETE FROM producto_imagenes WHERE id_producto = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM producto_caracteristicas WHERE id_producto = :id')->execute(['id' => $id]);
    $insImg = $pdo->prepare('INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES (:id, :url, :orden)');
    foreach ($c['imagenes'] as $i => $url) {
        $insImg->execute(['id' => $id, 'url' => $url, 'orden' => $i + 1]);
    }
    $insCar = $pdo->prepare('INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES (:id, :d, :orden)');
    foreach ($c['caracteristicas'] as $i => $texto) {
        $insCar->execute(['id' => $id, 'd' => $texto, 'orden' => $i + 1]);
    }
}

// ------------------------------------------------------------------------------------- GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = parametroIdOpcional('id');
    if ($id !== null) {
        $producto = productoCompleto($pdo, $id);
        if (!$producto) {
            responderError('Producto no encontrado.', 404);
        }
        responderJson(['producto' => $producto]);
    }

    $estado = $_GET['estado'] ?? 'todos';
    if (!in_array($estado, ['todos', 'activo', 'inactivo'], true)) {
        responderError("El parámetro 'estado' debe ser todos, activo o inactivo.", 400);
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) > 100) {
        responderError("El parámetro 'q' es demasiado largo.", 400);
    }
    $slug = parametroCategoriaOpcional('categoria');

    $sql = 'SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug, pi.url_imagen AS imagen_principal,
                   COALESCE(v.unidades, 0) AS unidades_vendidas
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN producto_imagenes pi ON pi.id_producto = p.id_producto AND pi.orden = 1
            LEFT JOIN (SELECT id_producto, SUM(cantidad) AS unidades FROM detalle_pedido GROUP BY id_producto) v
                   ON v.id_producto = p.id_producto
            WHERE 1 = 1';
    $par = [];
    if ($estado !== 'todos') {
        $sql .= ' AND p.estado = :estado';
        $par['estado'] = $estado;
    }
    if ($q !== '') {
        $sql .= ' AND (' . sqlContiene('p.nombre', 'q') . ' OR ' . sqlContiene('p.proveedor', 'q2') . ')';
        $par['q'] = '%' . $q . '%';
        $par['q2'] = '%' . $q . '%';
    }
    if ($slug !== null) {
        $sql .= ' AND c.slug = :slug';
        $par['slug'] = $slug;
    }
    $sql .= ' ORDER BY p.id_producto';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);
    responderJson(['productos' => array_map('formatearProducto', $stmt->fetchAll())]);
}

// ------------------------------------------------------------------------------------- POST
$d = leerCuerpoJson();
$accion = campoOpcion($d, 'accion', ['crear', 'actualizar', 'estado', 'eliminar']);

if ($accion === 'crear') {
    $c = leerCamposProducto($pdo, $d);
    $id = enTransaccion($pdo, function () use ($pdo, $c) {
        $pdo->prepare(
            'INSERT INTO productos (id_categoria, nombre, descripcion, precio_original, descuento_porcentaje, stock,
                                    proveedor, envio_gratis, costo_envio, estado)
             VALUES (:cat, :nombre, :descripcion, :precio, :descuento, :stock, :proveedor, :gratis, :costo, :estado)'
        )->execute([
            'cat' => $c['id_categoria'], 'nombre' => $c['nombre'], 'descripcion' => $c['descripcion'],
            'precio' => $c['precio_original'], 'descuento' => $c['descuento_porcentaje'], 'stock' => $c['stock'],
            'proveedor' => $c['proveedor'], 'gratis' => valorBooleano($c['envio_gratis']), 'costo' => $c['costo_envio'],
            'estado' => $c['estado'],
        ]);
        $id = ultimoId($pdo, 'productos', 'id_producto');
        guardarImagenesYCaracteristicas($pdo, $id, $c);
        return $id;
    });
    responderJson(['mensaje' => 'Producto creado.', 'producto' => productoCompleto($pdo, $id)], 201);
}

$id = campoIdRequerido($d, 'id_producto');
$existe = $pdo->prepare('SELECT nombre, (SELECT COUNT(*) FROM detalle_pedido WHERE id_producto = :id2) AS ventas FROM productos WHERE id_producto = :id');
$existe->execute(['id' => $id, 'id2' => $id]);
$actual = $existe->fetch();
if (!$actual) {
    responderError('Producto no encontrado.', 404);
}

if ($accion === 'actualizar') {
    $c = leerCamposProducto($pdo, $d);
    enTransaccion($pdo, function () use ($pdo, $c, $id) {
        $pdo->prepare('SELECT id_producto FROM productos WHERE id_producto = :id FOR UPDATE')->execute(['id' => $id]);
        $pdo->prepare(
            'UPDATE productos SET id_categoria = :cat, nombre = :nombre, descripcion = :descripcion,
                    precio_original = :precio, descuento_porcentaje = :descuento, stock = :stock, proveedor = :proveedor,
                    envio_gratis = :gratis, costo_envio = :costo, estado = :estado
             WHERE id_producto = :id'
        )->execute([
            'cat' => $c['id_categoria'], 'nombre' => $c['nombre'], 'descripcion' => $c['descripcion'],
            'precio' => $c['precio_original'], 'descuento' => $c['descuento_porcentaje'], 'stock' => $c['stock'],
            'proveedor' => $c['proveedor'], 'gratis' => valorBooleano($c['envio_gratis']), 'costo' => $c['costo_envio'],
            'estado' => $c['estado'], 'id' => $id,
        ]);
        guardarImagenesYCaracteristicas($pdo, $id, $c);
    });
    responderJson(['mensaje' => 'Producto actualizado.', 'producto' => productoCompleto($pdo, $id)]);
}

if ($accion === 'estado') {
    $estado = campoOpcion($d, 'estado', ['activo', 'inactivo']);
    $pdo->prepare('UPDATE productos SET estado = :estado WHERE id_producto = :id')->execute(['estado' => $estado, 'id' => $id]);
    responderJson([
        'mensaje'  => $estado === 'activo' ? 'Producto activado: vuelve a verse en la tienda.' : 'Producto desactivado: ya no se ve ni se puede comprar.',
        'producto' => productoCompleto($pdo, $id),
    ]);
}

// eliminar
if ((int) $actual['ventas'] > 0) {
    // Regla del proyecto: un producto con historial de ventas nunca se borra físicamente.
    $pdo->prepare('UPDATE productos SET estado = \'inactivo\' WHERE id_producto = :id')->execute(['id' => $id]);
    responderJson([
        'resultado' => 'desactivado',
        'mensaje'   => "\"{$actual['nombre']}\" tiene ventas registradas: no se elimina, se desactivó para conservar el historial de pedidos.",
        'producto'  => productoCompleto($pdo, $id),
    ]);
}
try {
    $pdo->prepare('DELETE FROM productos WHERE id_producto = :id')->execute(['id' => $id]);
} catch (PDOException $e) {
    // Carrera: se vendió justo entre la verificación y el borrado (FK RESTRICT de detalle_pedido).
    if (($e->errorInfo[1] ?? 0) == 1451) {
        $pdo->prepare('UPDATE productos SET estado = \'inactivo\' WHERE id_producto = :id')->execute(['id' => $id]);
        responderJson(['resultado' => 'desactivado', 'mensaje' => 'El producto tiene ventas: se desactivó en lugar de eliminarse.']);
    }
    throw $e;
}
responderJson(['resultado' => 'eliminado', 'mensaje' => "\"{$actual['nombre']}\" se eliminó definitivamente."]);
