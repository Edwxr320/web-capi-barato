<?php
/**
 * Categorías (solo rol admin) — Bloque 5.
 *
 * GET  /api/admin/categorias.php                 listado con cantidad de productos (total y activos)
 * POST /api/admin/categorias.php  (X-CSRF-Token)
 *      { "accion": "crear", "nombre": "Audio", "slug": "audio" }          slug opcional: se genera del nombre
 *      { "accion": "actualizar", "id_categoria": 4, "nombre": "...", "slug": "..." }
 *      { "accion": "eliminar", "id_categoria": 4 }
 *
 * Restricciones respetadas:
 *  - slug único (UNIQUE uq_categorias_slug): 2–50 caracteres, minúsculas, números y guiones. Es el que usa la tienda
 *    en ?categoria=<slug>, por eso no admite espacios ni tildes.
 *  - nombre 2–50 caracteres (VARCHAR(50)); tampoco se permiten dos categorías con el mismo nombre.
 *  - Una categoría con productos (activos o inactivos) NO se puede eliminar: la clave foránea
 *    fk_productos_categoria es ON DELETE RESTRICT. Primero hay que mover esos productos a otra categoría.
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

iniciarEndpointAdmin(['GET', 'POST']);
$pdo = obtenerConexion();

function listarCategorias(PDO $pdo): array
{
    $filas = $pdo->query(
        'SELECT c.id_categoria, c.nombre, c.slug, c.fecha_creacion,
                COUNT(p.id_producto) AS productos, COALESCE(SUM(CASE WHEN p.estado = \'activo\' THEN 1 ELSE 0 END), 0) AS activos
         FROM categorias c LEFT JOIN productos p ON p.id_categoria = c.id_categoria
         GROUP BY c.id_categoria ORDER BY c.nombre'
    )->fetchAll();
    return array_map(fn($f) => [
        'id_categoria'       => (int) $f['id_categoria'],
        'nombre'             => $f['nombre'],
        'slug'               => $f['slug'],
        'fecha_creacion'     => $f['fecha_creacion'],
        'productos'          => (int) $f['productos'],
        'productos_activos'  => (int) $f['activos'],
    ], $filas);
}

function slugDesde(string $texto): string
{
    $t = mb_strtolower(trim($texto));
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim($t, '-');
}

function leerNombreYSlug(PDO $pdo, array $d, ?int $idExcluir): array
{
    $nombre = campoTexto($d, 'nombre', 2, 50);
    $slug = isset($d['slug']) && is_string($d['slug']) && trim($d['slug']) !== '' ? trim($d['slug']) : slugDesde($nombre);
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) < 2 || strlen($slug) > 50) {
        responderError('El identificador (slug) debe tener entre 2 y 50 caracteres: minúsculas, números y guiones.', 400);
    }
    if ($slug === 'todos') {
        responderError('"todos" está reservado por la tienda: elegí otro identificador.', 400);
    }
    $dup = $pdo->prepare('SELECT id_categoria, slug, nombre FROM categorias WHERE (slug = :s OR nombre = :n) AND id_categoria <> :id');
    $dup->execute(['s' => $slug, 'n' => $nombre, 'id' => $idExcluir ?? 0]);
    if ($f = $dup->fetch()) {
        responderError($f['slug'] === $slug ? "Ya existe una categoría con el identificador \"$slug\"." : "Ya existe una categoría llamada \"$nombre\".", 409);
    }
    return [$nombre, $slug];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    responderJson(['categorias' => listarCategorias($pdo)]);
}

$d = leerCuerpoJson();
$accion = campoOpcion($d, 'accion', ['crear', 'actualizar', 'eliminar']);

if ($accion === 'crear') {
    [$nombre, $slug] = leerNombreYSlug($pdo, $d, null);
    try {
        $pdo->prepare('INSERT INTO categorias (nombre, slug) VALUES (:n, :s)')->execute(['n' => $nombre, 's' => $slug]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            responderError("Ya existe una categoría con el identificador \"$slug\".", 409);
        }
        throw $e;
    }
    responderJson(['mensaje' => "Categoría \"$nombre\" creada.", 'id_categoria' => ultimoId($pdo, 'categorias', 'id_categoria'), 'categorias' => listarCategorias($pdo)], 201);
}

$id = campoIdRequerido($d, 'id_categoria');
$stmt = $pdo->prepare('SELECT nombre FROM categorias WHERE id_categoria = :id');
$stmt->execute(['id' => $id]);
$actual = $stmt->fetch();
if (!$actual) {
    responderError('Categoría no encontrada.', 404);
}

if ($accion === 'actualizar') {
    [$nombre, $slug] = leerNombreYSlug($pdo, $d, $id);
    try {
        $pdo->prepare('UPDATE categorias SET nombre = :n, slug = :s WHERE id_categoria = :id')->execute(['n' => $nombre, 's' => $slug, 'id' => $id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            responderError("Ya existe una categoría con el identificador \"$slug\".", 409);
        }
        throw $e;
    }
    responderJson(['mensaje' => 'Categoría actualizada.', 'categorias' => listarCategorias($pdo)]);
}

// eliminar
$cuenta = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = :id');
$cuenta->execute(['id' => $id]);
$n = (int) $cuenta->fetchColumn();
if ($n > 0) {
    responderError("No se puede eliminar \"{$actual['nombre']}\": tiene $n producto(s). Movelos a otra categoría primero.", 409);
}
try {
    $pdo->prepare('DELETE FROM categorias WHERE id_categoria = :id')->execute(['id' => $id]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1451) { // carrera: alguien le asignó un producto recién
        responderError("No se puede eliminar \"{$actual['nombre']}\": tiene productos asociados.", 409);
    }
    throw $e;
}
responderJson(['mensaje' => "Categoría \"{$actual['nombre']}\" eliminada.", 'categorias' => listarCategorias($pdo)]);
