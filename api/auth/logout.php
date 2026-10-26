<?php
/**
 * POST /api/auth/logout.php
 * Cierra la sesión. Es idempotente: si ya no había sesión, igual responde éxito.
 * Solo POST (un GET desde una imagen de otro sitio no puede cerrar tu sesión) y, si hay sesión,
 * con token CSRF.
 */
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}

if (usuarioActual() !== null) {
    requiereCsrf();
}
cerrarSesion();

responderJson(['mensaje' => 'Sesión cerrada correctamente.']);
