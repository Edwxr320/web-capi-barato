<?php
/**
 * POST /api/auth/registro.php
 * Body (JSON o form): { "nombre": "...", "email": "...", "password": "..." }
 *
 * Crea una cuenta de cliente e inicia sesión automáticamente.
 * La confirmación de contraseña se valida en el frontend (Fase 4); acá se
 * valida de nuevo el formato y el largo mínimo, porque el frontend nunca es
 * la única línea de defensa.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/validacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}

verificarOrigen();

$datos    = leerCuerpoJson();
$nombre   = is_string($datos['nombre'] ?? null) ? trim($datos['nombre']) : '';
$email    = is_string($datos['email'] ?? null) ? trim(mb_strtolower($datos['email'])) : '';
$password = is_string($datos['password'] ?? null) ? $datos['password'] : '';

if (mb_strlen($nombre) < 2) {
    responderError('Ingresá tu nombre completo.');
}
if (mb_strlen($nombre) > 100) {
    responderError('El nombre es demasiado largo (máximo 100 caracteres).');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    responderError('Ingresá un correo electrónico válido.');
}
if (mb_strlen($password) < 6) {
    responderError('La contraseña debe tener al menos 6 caracteres.');
}
if (mb_strlen($password) > 200) {
    responderError('La contraseña es demasiado larga (máximo 200 caracteres).');
}

$pdo = obtenerConexion();

$stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    responderError('Ese correo ya está registrado.', 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nombre, email, password_hash, rol, estado)
     VALUES (:nombre, :email, :hash, "cliente", "activo")'
);
try {
    $stmt->execute(['nombre' => $nombre, 'email' => $email, 'hash' => $hash]);
} catch (PDOException $e) {
    // Dos registros simultáneos con el mismo correo: el UNIQUE de la tabla frena al segundo.
    if (($e->errorInfo[1] ?? 0) == 1062) {
        responderError('Ese correo ya está registrado.', 409);
    }
    throw $e;
}
$idUsuario = (int) $pdo->lastInsertId();

abrirSesionUsuario(['id_usuario' => $idUsuario, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente']);

responderJson([
    'mensaje' => 'Cuenta creada correctamente.',
    'usuario' => ['id_usuario' => $idUsuario, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente'],
    'csrf_token' => tokenCsrf(),
], 201);
