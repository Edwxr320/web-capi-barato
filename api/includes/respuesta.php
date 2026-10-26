<?php
/**
 * Respuestas JSON consistentes para toda la API.
 */

/**
 * Red de seguridad: cualquier excepción no capturada (por ejemplo un error de
 * MySQL en medio de una operación) termina en un JSON 500 genérico en vez de un
 * error fatal de PHP con HTML, rutas internas o el texto del SQL. El detalle real
 * queda solo en el log del servidor (error_log), nunca en la respuesta.
 */
set_exception_handler(function (Throwable $e) {
    error_log('[MercadoWeb] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Ocurrió un error interno. Intentá de nuevo en unos minutos.'], JSON_UNESCAPED_UNICODE);
    exit;
});

function responderJson(array $datos, int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError(string $mensaje, int $codigoHttp = 400): void
{
    responderJson(['error' => $mensaje], $codigoHttp);
}
