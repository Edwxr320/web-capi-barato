<?php
/**
 * Sesión, autenticación, autorización por rol y protección CSRF.
 * Toda la API pasa por acá para saber quién es el usuario actual y qué puede hacer.
 */

require_once __DIR__ . '/respuesta.php';
require_once __DIR__ . '/../config/entorno.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sesion_bd.php';

const COOKIE_CSRF = 'mw_csrf';

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Modo estricto: PHP no acepta un id de sesión que él mismo no generó (evita session fixation
        // con un id inventado por un atacante).
        ini_set('session.use_strict_mode', '1');
        // Sesiones en la base: imprescindible en serverless, donde el disco no se comparte entre
        // instancias ni sobrevive. Se registra antes de session_start(). Ver sesion_bd.php.
        registrarManejadorSesiones();
        session_set_cookie_params([
            'lifetime' => 0,       // cookie de sesión (se borra al cerrar el navegador)
            'path'     => '/',
            'httponly' => true,    // JavaScript del frontend no puede leer la cookie de sesión
            'samesite' => 'Lax',
            'secure'   => cookieSegura(),
        ]);
        session_start();
    }
}

function cookieSegura(): bool
{
    return defined('SESSION_COOKIE_SECURE') ? (bool) SESSION_COOKIE_SECURE : false;
}

/**
 * Devuelve los datos del usuario logueado, o null si no hay sesión activa.
 *
 * El rol y el estado se vuelven a leer de MySQL en cada petición: si un administrador desactiva una
 * cuenta o le cambia el rol, el cambio rige en la siguiente petición de esa persona, sin esperar a que
 * cierre sesión. Una cuenta desactivada o borrada pierde la sesión en el acto.
 */
function usuarioActual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    iniciarSesionSegura();
    if (!isset($_SESSION['id_usuario'])) {
        return $cache = null;
    }
    $stmt = obtenerConexion()->prepare('SELECT id_usuario, nombre, email, rol, estado FROM usuarios WHERE id_usuario = :id');
    $stmt->execute(['id' => $_SESSION['id_usuario']]);
    $fila = $stmt->fetch();
    if (!$fila || $fila['estado'] !== 'activo') {
        cerrarSesion();
        return $cache = null;
    }
    $_SESSION['nombre'] = $fila['nombre'];
    $_SESSION['email']  = $fila['email'];
    $_SESSION['rol']    = $fila['rol'];
    return $cache = [
        'id_usuario' => (int) $fila['id_usuario'],
        'nombre'     => $fila['nombre'],
        'email'      => $fila['email'],
        'rol'        => $fila['rol'],
    ];
}

/** Corta la ejecución con 401 si no hay sesión. Devuelve el usuario si la hay. */
function requiereLogin(): array
{
    $usuario = usuarioActual();
    if ($usuario === null) {
        responderError('Necesitás iniciar sesión para hacer esto.', 401);
    }
    return $usuario;
}

/** Corta la ejecución con 401/403 si no hay sesión o el rol no coincide. */
function requiereRol(string $rolRequerido): array
{
    $usuario = requiereLogin();
    if ($usuario['rol'] !== $rolRequerido) {
        responderError('No tenés permiso para acceder a esto.', 403);
    }
    return $usuario;
}

/** Inicia la sesión de un usuario ya autenticado (login o registro). */
function abrirSesionUsuario(array $usuario): void
{
    iniciarSesionSegura();
    session_regenerate_id(true); // previene session fixation: id nuevo al autenticarse
    $_SESSION = [];
    $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['email']      = $usuario['email'];
    $_SESSION['rol']        = $usuario['rol'];
    tokenCsrf(); // token nuevo con cada sesión nueva
}

/** Destruye la sesión en el servidor y borra las cookies de sesión y CSRF. */
function cerrarSesion(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    setcookie(COOKIE_CSRF, '', ['expires' => time() - 42000, 'path' => '/', 'samesite' => 'Lax', 'secure' => cookieSegura()]);
    session_destroy();
}

// ---------------------------------------------------------------------------------------------
// C S R F
// ---------------------------------------------------------------------------------------------
// Defensa en capas para toda petición que cambia datos con sesión iniciada:
//  1) La cookie de sesión es SameSite=Lax (un formulario de otro sitio no la envía en un POST).
//  2) Si el navegador manda el encabezado Origin, tiene que ser este mismo sitio.
//  3) Token sincronizado: se guarda en la sesión y se entrega al JavaScript del sitio en la cookie
//     legible "mw_csrf" (y en sesion.php). Cada POST debe devolverlo en el encabezado X-CSRF-Token.
//     Otro sitio no puede leer esa cookie ni poner encabezados propios sin pasar por CORS.

/** Devuelve el token CSRF de la sesión actual (lo crea si no existe) y lo publica en la cookie mw_csrf. */
function tokenCsrf(): string
{
    iniciarSesionSegura();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    if (($_COOKIE[COOKIE_CSRF] ?? '') !== $_SESSION['csrf']) {
        setcookie(COOKIE_CSRF, $_SESSION['csrf'], ['expires' => 0, 'path' => '/', 'samesite' => 'Lax', 'secure' => cookieSegura(), 'httponly' => false]);
    }
    return $_SESSION['csrf'];
}

/** Rechaza (403) peticiones cuyo Origin declarado no es este mismo sitio. */
function verificarOrigen(): void
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origen === '') {
        return; // no todos los clientes lo envían; las otras capas siguen aplicando
    }
    // Origin "null" (iframes con sandbox, file://) no tiene host: se rechaza abajo.
    $hostOrigen = parse_url($origen, PHP_URL_HOST);
    $puertoOrigen = parse_url($origen, PHP_URL_PORT);
    $hostOrigenCompleto = $hostOrigen . ($puertoOrigen ? ':' . $puertoOrigen : '');
    $hostPropio = $_SERVER['HTTP_HOST'] ?? '';
    if ($hostOrigen === null || !hash_equals(strtolower($hostPropio), strtolower($hostOrigenCompleto))) {
        responderError('Solicitud rechazada: origen no permitido.', 403);
    }
}

/** Exige un token CSRF válido. Usar en todo POST que cambie datos de un usuario autenticado. */
function requiereCsrf(): void
{
    verificarOrigen();
    iniciarSesionSegura();
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $esperado = $_SESSION['csrf'] ?? '';
    if (!is_string($esperado) || $esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        responderError('La solicitud no es válida o tu sesión cambió. Recargá la página e intentá de nuevo.', 403);
    }
}
