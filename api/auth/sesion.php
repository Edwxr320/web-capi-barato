<?php
/**
 * GET /api/auth/sesion.php
 * Le permite al frontend preguntar "¿hay alguien logueado ahora?" sin intentar
 * ninguna acción. Nunca falla: si no hay sesión, responde autenticado=false.
 * Con sesión, devuelve también el token CSRF que el frontend debe mandar en X-CSRF-Token.
 */
require_once __DIR__ . '/../includes/respuesta.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

$usuario = usuarioActual();

if ($usuario === null) {
    responderJson(['autenticado' => false]);
}

responderJson(['autenticado' => true, 'usuario' => $usuario, 'csrf_token' => tokenCsrf()]);
