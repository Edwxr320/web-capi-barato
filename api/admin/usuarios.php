<?php
/**
 * Usuarios y clientes (solo rol admin) — Bloque 5.
 *
 * GET  /api/admin/usuarios.php     ?q=texto (nombre o correo) ?rol=admin|cliente ?estado=activo|inactivo
 *      Nunca devuelve password_hash.
 * GET  /api/admin/usuarios.php?id=7  ficha con sus pedidos
 * POST /api/admin/usuarios.php  (X-CSRF-Token)
 *      { "accion": "estado", "id_usuario": 7, "estado": "activo" | "inactivo" }
 *      { "accion": "rol",    "id_usuario": 7, "rol": "admin" | "cliente" }
 *
 * Controles:
 *  - Un administrador no puede desactivarse ni quitarse el rol a sí mismo (evita quedarse afuera por error).
 *  - Nunca puede quedar el sistema sin al menos un administrador activo.
 *  - Una cuenta desactivada pierde su sesión en la petición siguiente (auth_helpers.php relee el estado) y no
 *    puede volver a iniciar sesión. Sus pedidos se conservan.
 *  - No hay alta de usuarios ni cambio de contraseña desde el panel: no hay un comportamiento definido para eso
 *    (pendiente de decisión, igual que la recuperación de contraseña).
 */
require_once __DIR__ . '/../includes/admin_helpers.php';

$admin = iniciarEndpointAdmin(['GET', 'POST']);
$pdo = obtenerConexion();

function formatearUsuario(array $f): array
{
    return [
        'id_usuario'     => (int) $f['id_usuario'],
        'nombre'         => $f['nombre'],
        'email'          => $f['email'],
        'rol'            => $f['rol'],
        'estado'         => $f['estado'],
        'fecha_registro' => $f['fecha_registro'],
        'pedidos'        => (int) $f['pedidos'],
        'total_comprado' => round((float) $f['total_comprado'], 2),
    ];
}

const SQL_USUARIOS = 'SELECT u.id_usuario, u.nombre, u.email, u.rol, u.estado, u.fecha_registro,
                             COUNT(p.id_pedido) AS pedidos,
                             COALESCE(SUM(CASE WHEN p.estado IN ("pagado","enviado","completado") THEN p.total END), 0) AS total_comprado
                      FROM usuarios u LEFT JOIN pedidos p ON p.id_usuario = u.id_usuario';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = parametroIdOpcional('id');
    if ($id !== null) {
        $stmt = $pdo->prepare(SQL_USUARIOS . ' WHERE u.id_usuario = :id GROUP BY u.id_usuario');
        $stmt->execute(['id' => $id]);
        $f = $stmt->fetch();
        if (!$f) {
            responderError('Usuario no encontrado.', 404);
        }
        $ped = $pdo->prepare('SELECT id_pedido, fecha_pedido, estado, total FROM pedidos WHERE id_usuario = :id ORDER BY fecha_pedido DESC, id_pedido DESC');
        $ped->execute(['id' => $id]);
        responderJson(['usuario' => formatearUsuario($f) + ['lista_pedidos' => array_map(fn($p) => [
            'id_pedido' => (int) $p['id_pedido'], 'fecha_pedido' => $p['fecha_pedido'], 'estado' => $p['estado'], 'total' => (float) $p['total'],
        ], $ped->fetchAll())]]);
    }

    $where = [];
    $par = [];
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) > 100) {
        responderError("El parámetro 'q' es demasiado largo.", 400);
    }
    if ($q !== '') {
        $where[] = '(u.nombre LIKE :q OR u.email LIKE :q2)';
        $par['q'] = '%' . $q . '%';
        $par['q2'] = '%' . $q . '%';
    }
    foreach (['rol' => ['admin', 'cliente'], 'estado' => ['activo', 'inactivo']] as $campo => $validos) {
        $v = $_GET[$campo] ?? '';
        if ($v === '' || $v === 'todos') {
            continue;
        }
        if (!in_array($v, $validos, true)) {
            responderError("El parámetro '$campo' no es válido.", 400);
        }
        $where[] = "u.$campo = :$campo";
        $par[$campo] = $v;
    }
    $sql = SQL_USUARIOS . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' GROUP BY u.id_usuario ORDER BY u.fecha_registro DESC, u.id_usuario DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);
    responderJson(['usuarios' => array_map('formatearUsuario', $stmt->fetchAll())]);
}

$d = leerCuerpoJson();
$accion = campoOpcion($d, 'accion', ['estado', 'rol']);
$id = campoIdRequerido($d, 'id_usuario');
$valor = $accion === 'estado'
    ? campoOpcion($d, 'estado', ['activo', 'inactivo'])
    : campoOpcion($d, 'rol', ['admin', 'cliente']);

if ($id === $admin['id_usuario']) {
    responderError($accion === 'estado' ? 'No podés desactivar tu propia cuenta.' : 'No podés cambiar tu propio rol.', 409);
}

$resultado = enTransaccion($pdo, function () use ($pdo, $id, $accion, $valor) {
    $stmt = $pdo->prepare('SELECT nombre, rol, estado FROM usuarios WHERE id_usuario = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $u = $stmt->fetch();
    if (!$u) {
        $pdo->rollBack();
        responderError('Usuario no encontrado.', 404);
    }
    // ¿Este cambio dejaría al sistema sin administradores activos?
    $dejaDeSerAdminActivo = $u['rol'] === 'admin' && $u['estado'] === 'activo'
        && (($accion === 'estado' && $valor === 'inactivo') || ($accion === 'rol' && $valor === 'cliente'));
    if ($dejaDeSerAdminActivo) {
        $otros = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = "admin" AND estado = "activo" AND id_usuario <> :id FOR UPDATE');
        $otros->execute(['id' => $id]);
        if ((int) $otros->fetchColumn() === 0) {
            $pdo->rollBack();
            responderError('Tiene que quedar al menos un administrador activo.', 409);
        }
    }
    $columna = $accion === 'estado' ? 'estado' : 'rol';
    $pdo->prepare("UPDATE usuarios SET $columna = :v WHERE id_usuario = :id")->execute(['v' => $valor, 'id' => $id]);
    return $u['nombre'];
});

$mensaje = $accion === 'estado'
    ? ($valor === 'activo' ? "Cuenta de $resultado activada." : "Cuenta de $resultado desactivada: ya no puede iniciar sesión.")
    : "$resultado ahora tiene el rol " . ($valor === 'admin' ? 'administrador' : 'cliente') . '.';
$stmt = $pdo->prepare(SQL_USUARIOS . ' WHERE u.id_usuario = :id GROUP BY u.id_usuario');
$stmt->execute(['id' => $id]);
responderJson(['mensaje' => $mensaje, 'usuario' => formatearUsuario($stmt->fetch())]);
