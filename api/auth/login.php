<?php
/**
 * POST /api/auth/login.php
 * Body JSON: { "email": "...", "password": "..." }
 *
 * Inicia sesión. El mensaje de error es el MISMO exista o no el correo,
 * para no revelar si el problema fue el email o la contraseña.
 * Tras varios fallos seguidos para el mismo correo desde la misma IP responde 429 (ver limite_intentos.php).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';
require_once __DIR__ . '/../includes/limite_intentos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}
verificarOrigen();

$datos    = leerCuerpoJson();
$email    = is_string($datos['email'] ?? null) ? trim(mb_strtolower($datos['email'])) : '';
$password = is_string($datos['password'] ?? null) ? $datos['password'] : '';

if ($email === '' || $password === '') {
    responderError('Completá tu correo y contraseña.');
}
if (mb_strlen($email) > 150 || mb_strlen($password) > 200) {
    responderError('Correo o contraseña incorrectos.', 401);
}

verificarLimiteLogin($email);

$pdo = obtenerConexion();
$stmt = $pdo->prepare('SELECT id_usuario, nombre, email, password_hash, rol, estado FROM usuarios WHERE email = :email');
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    registrarFalloLogin($email);
    responderError('Correo o contraseña incorrectos.', 401);
}
if ($usuario['estado'] !== 'activo') {
    responderError('Esta cuenta está inactiva. Contactá al administrador.', 403);
}

limpiarFallosLogin($email);
abrirSesionUsuario($usuario);

responderJson([
    'mensaje' => 'Sesión iniciada correctamente.',
    'usuario' => [
        'id_usuario' => (int) $usuario['id_usuario'],
        'nombre'     => $usuario['nombre'],
        'email'      => $usuario['email'],
        'rol'        => $usuario['rol'],
    ],
    'csrf_token' => tokenCsrf(),
]);
