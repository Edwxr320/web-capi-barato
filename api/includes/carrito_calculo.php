<?php
/**
 * Cálculo del resumen del carrito (subtotal, envío, total).
 * Reutilizable acá y, más adelante, en el checkout (Bloque 3).
 */

/**
 * Regla definitiva de envío (confirmada): se cobra UNA SOLA VEZ POR PEDIDO.
 * No se multiplica por cantidad de unidades. No se suma un envío por cada producto
 * distinto. Cuando hay varios productos con distintos costos de envío, se cobra
 * el MAYOR entre ellos. Si todos los productos tienen envío gratis, el envío es S/0.
 *
 * $items: array de ['cantidad'=>int, 'precio_final'=>float, 'envio_gratis'=>bool, 'costo_envio'=>?float]
 */
function calcularResumenCarrito(array $items): array
{
    $subtotal = 0.0;
    $costosEnvioPagos = [];

    foreach ($items as $item) {
        $subtotal += $item['cantidad'] * $item['precio_final'];
        if (!$item['envio_gratis']) {
            $costosEnvioPagos[] = (float) $item['costo_envio'];
        }
    }

    $costoEnvio = $costosEnvioPagos ? max($costosEnvioPagos) : 0.0;

    return [
        'subtotal'    => round($subtotal, 2),
        'costo_envio' => round($costoEnvio, 2),
        'total'       => round($subtotal + $costoEnvio, 2),
    ];
}
