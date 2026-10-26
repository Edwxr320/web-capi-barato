<?php
/**
 * Validación de parámetros de entrada, reutilizable en todos los endpoints públicos.
 */

/** Devuelve un entero positivo válido desde $_GET, o corta con 400 si no lo es. */
function parametroIdRequerido(string $nombre): int
{
    $valor = $_GET[$nombre] ?? null;
    if ($valor === null || $valor === '' || !ctype_digit((string) $valor) || (int) $valor < 1) {
        responderError("El parámetro '$nombre' es requerido y debe ser un número entero positivo.", 400);
    }
    return (int) $valor;
}

/** Devuelve un texto de búsqueda no vacío, o corta con 400 si falta. */
function parametroBusquedaRequerido(string $nombre): string
{
    $valor = trim((string) ($_GET[$nombre] ?? ''));
    if ($valor === '') {
        responderError("El parámetro '$nombre' es requerido para buscar.", 400);
    }
    if (mb_strlen($valor) > 100) {
        responderError("El parámetro '$nombre' es demasiado largo.", 400);
    }
    return $valor;
}

/** Devuelve el slug de categoría desde $_GET si vino, o null si no se pasó (filtro opcional). */
function parametroCategoriaOpcional(string $nombre): ?string
{
    $valor = trim((string) ($_GET[$nombre] ?? ''));
    return $valor === '' ? null : $valor;
}

/** Calcula el precio final a partir del original y el % de descuento, redondeado a 2 decimales. */
function calcularPrecioFinal(float $precioOriginal, ?int $descuentoPorcentaje): float
{
    if (!$descuentoPorcentaje) {
        return $precioOriginal;
    }
    return round($precioOriginal * (1 - ($descuentoPorcentaje / 100)), 2);
}

/**
 * Lee y decodifica el body JSON de la petición. Corta con 400 si no es JSON válido.
 */
function leerCuerpoJson(): array
{
    // Solo se acepta JSON declarado como tal. Un formulario HTML de otro sitio no puede enviar
    // application/json sin pasar por CORS, así que esto también suma contra CSRF.
    $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    $crudo = file_get_contents('php://input');
    if ($crudo !== '' && $crudo !== false && $tipo !== 'application/json') {
        responderError('El cuerpo de la petición debe enviarse como application/json.', 415);
    }
    if ($crudo === '' || $crudo === false) {
        return [];
    }
    $datos = json_decode($crudo, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        responderError('El cuerpo de la petición debe ser JSON válido.', 400);
    }
    return $datos;
}

/** Igual que parametroIdRequerido pero leyendo de un array (body ya decodificado), no de $_GET. */
function campoIdRequerido(array $datos, string $nombre): int
{
    $valor = $datos[$nombre] ?? null;
    if ($valor === null || $valor === '' || !ctype_digit((string) $valor) || (int) $valor < 1) {
        responderError("El campo '$nombre' es requerido y debe ser un número entero positivo.", 400);
    }
    return (int) $valor;
}

/**
 * Cantidad de carrito: entero >= 1. Rechaza explícitamente 0, negativos y decimales
 * (ej. "2.5" o 2.5 no son válidos aunque numéricamente parezcan razonables).
 */
function campoCantidadRequerido(array $datos, string $nombre = 'cantidad', ?int $porDefecto = 1): ?int
{
    if (!array_key_exists($nombre, $datos)) {
        return $porDefecto;
    }
    $valor = $datos[$nombre];
    if (is_bool($valor) || !is_numeric($valor) || !ctype_digit((string) $valor) || (int) $valor < 1) {
        responderError("El campo '$nombre' debe ser un número entero mayor o igual a 1 (no se aceptan decimales, negativos ni cero).", 400);
    }
    return (int) $valor;
}
