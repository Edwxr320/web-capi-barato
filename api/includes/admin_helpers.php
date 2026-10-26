<?php
/**
 * Utilidades comunes de los endpoints administrativos (api/admin/*).
 *
 * Toda petición administrativa pasa por iniciarEndpointAdmin():
 *   - solo acepta los métodos indicados (405 si no);
 *   - exige sesión con rol admin EN EL SERVIDOR (401 sin sesión, 403 con otro rol);
 *   - en todo POST exige además el token CSRF.
 * Ocultar botones en la interfaz NO es la medida de seguridad: lo es esto.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/respuesta.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validacion.php';

function iniciarEndpointAdmin(array $metodos = ['GET', 'POST']): array
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, $metodos, true)) {
        responderError('Método no permitido.', 405);
    }
    $admin = requiereRol('admin');
    if ($metodo === 'POST') {
        requiereCsrf();
    }
    return $admin;
}

/** Umbral de stock bajo: config.php (STOCK_BAJO_UMBRAL) o 5 por defecto. */
function umbralStockBajo(): int
{
    if (defined('STOCK_BAJO_UMBRAL') && is_numeric(STOCK_BAJO_UMBRAL) && (int) STOCK_BAJO_UMBRAL >= 0) {
        return (int) STOCK_BAJO_UMBRAL;
    }
    return 5;
}

/** Máximo de unidades que se aceptan en stock (evita errores de tipeo como 10000000). */
const STOCK_MAXIMO = 1000000;

/** Entero dentro de [min, max] leído de un array; corta con 400 si no lo es. Acepta 5 o "5", rechaza 5.5, "5a", true. */
function campoEnteroRango(array $datos, string $nombre, int $min, int $max, bool $requerido = true): ?int
{
    if (!array_key_exists($nombre, $datos) || $datos[$nombre] === null || $datos[$nombre] === '') {
        if ($requerido) {
            responderError("El campo '$nombre' es obligatorio.", 400);
        }
        return null;
    }
    $v = $datos[$nombre];
    if (is_bool($v) || is_float($v) || !preg_match('/^-?\d{1,10}$/', (string) $v)) {
        responderError("El campo '$nombre' debe ser un número entero.", 400);
    }
    $v = (int) $v;
    if ($v < $min || $v > $max) {
        responderError("El campo '$nombre' debe estar entre $min y $max.", 400);
    }
    return $v;
}

/** Decimal >= 0 con hasta 2 decimales (precios, costos). */
function campoDecimal(array $datos, string $nombre, float $min, float $max, bool $requerido = true): ?float
{
    if (!array_key_exists($nombre, $datos) || $datos[$nombre] === null || $datos[$nombre] === '') {
        if ($requerido) {
            responderError("El campo '$nombre' es obligatorio.", 400);
        }
        return null;
    }
    $v = $datos[$nombre];
    if (is_bool($v) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : (string) $v)) {
        responderError("El campo '$nombre' debe ser un número con hasta 2 decimales.", 400);
    }
    $v = round((float) $v, 2);
    if ($v < $min || $v > $max) {
        responderError("El campo '$nombre' debe estar entre $min y $max.", 400);
    }
    return $v;
}

/** Texto obligatorio u opcional, recortado, con largo controlado. */
function campoTexto(array $datos, string $nombre, int $minLargo, int $maxLargo, bool $requerido = true): ?string
{
    if (!array_key_exists($nombre, $datos) || $datos[$nombre] === null) {
        if ($requerido) {
            responderError("El campo '$nombre' es obligatorio.", 400);
        }
        return null;
    }
    if (!is_string($datos[$nombre])) {
        responderError("El campo '$nombre' debe ser texto.", 400);
    }
    $v = trim($datos[$nombre]);
    if ($v === '' && !$requerido) {
        return null;
    }
    $largo = mb_strlen($v);
    if ($largo < $minLargo || $largo > $maxLargo) {
        responderError("El campo '$nombre' debe tener entre $minLargo y $maxLargo caracteres.", 400);
    }
    return $v;
}

/** Valor dentro de una lista cerrada. */
function campoOpcion(array $datos, string $nombre, array $opciones, bool $requerido = true): ?string
{
    $v = $datos[$nombre] ?? null;
    if ($v === null || $v === '') {
        if ($requerido) {
            responderError("El campo '$nombre' es obligatorio.", 400);
        }
        return null;
    }
    if (!is_string($v) || !in_array($v, $opciones, true)) {
        responderError("El campo '$nombre' debe ser uno de: " . implode(', ', $opciones) . '.', 400);
    }
    return $v;
}

/** Booleano estricto (true/false, 1/0, "1"/"0"). */
function campoBooleano(array $datos, string $nombre, bool $requerido = true): ?bool
{
    if (!array_key_exists($nombre, $datos) || $datos[$nombre] === null) {
        if ($requerido) {
            responderError("El campo '$nombre' es obligatorio.", 400);
        }
        return null;
    }
    $v = $datos[$nombre];
    if ($v === true || $v === 1 || $v === '1') return true;
    if ($v === false || $v === 0 || $v === '0') return false;
    responderError("El campo '$nombre' debe ser verdadero o falso.", 400);
}

/** Parámetro GET entero positivo opcional. */
function parametroIdOpcional(string $nombre): ?int
{
    if (!isset($_GET[$nombre]) || $_GET[$nombre] === '') {
        return null;
    }
    return parametroIdRequerido($nombre);
}

/** Ejecuta $fn dentro de una transacción; ante cualquier excepción hace rollback y la propaga. */
function enTransaccion(PDO $pdo, callable $fn)
{
    $pdo->beginTransaction();
    try {
        $resultado = $fn();
        $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
