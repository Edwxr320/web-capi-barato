<?php
/**
 * Límite de intentos fallidos de login, sin tablas nuevas (el esquema se mantiene en 8 tablas).
 *
 * Se cuenta por combinación IP + correo en archivos pequeños del directorio temporal del sistema
 * (o el que indique LOGIN_DIR_INTENTOS en config.php). Tras LOGIN_MAX_INTENTOS fallos dentro de
 * LOGIN_VENTANA_SEGUNDOS, ese correo queda bloqueado desde esa IP hasta que pase la ventana
 * (respuesta 429 con Retry-After), incluso si la contraseña siguiente es la correcta.
 * Un login exitoso borra el contador.
 *
 * Limitaciones (documentadas): no frena a quien rote muchas IP; el contador vive en el disco del
 * servidor y se pierde si se limpia el directorio temporal.
 */

function limiteMaxIntentos(): int
{
    return defined('LOGIN_MAX_INTENTOS') ? max(1, (int) LOGIN_MAX_INTENTOS) : 5;
}

function limiteVentanaSegundos(): int
{
    return defined('LOGIN_VENTANA_SEGUNDOS') ? max(1, (int) LOGIN_VENTANA_SEGUNDOS) : 900;
}

function archivoIntentos(string $email): string
{
    $dir = defined('LOGIN_DIR_INTENTOS') && LOGIN_DIR_INTENTOS !== ''
        ? LOGIN_DIR_INTENTOS
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mercadoweb-intentos-login';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    return $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|' . $email) . '.json';
}

/** Lee los fallos vigentes (dentro de la ventana) para este correo desde esta IP. */
function fallosVigentes(string $email): array
{
    $ruta = archivoIntentos($email);
    if (!is_file($ruta)) {
        return [];
    }
    $datos = json_decode((string) @file_get_contents($ruta), true);
    $limite = time() - limiteVentanaSegundos();
    return array_values(array_filter(is_array($datos) ? $datos : [], fn($t) => is_int($t) && $t > $limite));
}

/** Corta con 429 si ya se alcanzó el máximo de fallos. */
function verificarLimiteLogin(string $email): void
{
    $fallos = fallosVigentes($email);
    if (count($fallos) >= limiteMaxIntentos()) {
        $espera = max(1, min($fallos) + limiteVentanaSegundos() - time());
        header('Retry-After: ' . $espera);
        $minutos = (int) ceil($espera / 60);
        responderError("Demasiados intentos fallidos. Esperá $minutos minuto(s) e intentá de nuevo.", 429);
    }
}

function registrarFalloLogin(string $email): void
{
    $fallos = fallosVigentes($email);
    $fallos[] = time();
    @file_put_contents(archivoIntentos($email), json_encode($fallos), LOCK_EX);
}

function limpiarFallosLogin(string $email): void
{
    $ruta = archivoIntentos($email);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}
