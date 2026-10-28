<?php
/**
 * Punto de entrada único de la API para el despliegue serverless (Vercel).
 *
 * POR QUÉ EXISTE
 * Vercel crea una función por cada archivo PHP declarado en vercel.json. Este proyecto tiene 24
 * endpoints y el plan Hobby admite como máximo 12 funciones por despliegue. Con un único punto de
 * entrada se despliega UNA sola función y, aun así, TODOS los endpoints siguen respondiendo en
 * EXACTAMENTE LAS MISMAS URLs de siempre (/api/auth/login.php, /api/admin/productos.php, …).
 * El JavaScript del sitio no cambia ni una línea.
 *
 * EN XAMPP NO SE USA: Apache sirve cada archivo .php directamente, igual que siempre. Este archivo
 * solo entra en juego cuando vercel.json reescribe /api/* hacia acá.
 *
 * SEGURIDAD
 * No se construye ninguna ruta a partir de lo que mande el cliente. Se compara contra una lista
 * cerrada de endpoints (ENDPOINTS) y solo se incluye un archivo si coincide exactamente con uno de
 * ellos. Así no hay forma de alcanzar api/includes/ ni api/config/, ni de salir del directorio con
 * "..", ni de incluir un archivo subido.
 */

/** Lista cerrada de endpoints públicos de la API. Nada fuera de esta lista se puede ejecutar. */
const ENDPOINTS = [
    'auth/login',
    'auth/logout',
    'auth/registro',
    'auth/sesion',
    'productos/listar',
    'productos/detalle',
    'productos/buscar',
    'categorias/listar',
    'carrito/listar',
    'carrito/agregar',
    'carrito/actualizar',
    'carrito/eliminar',
    'carrito/fusionar',
    'pedidos/crear',
    'pedidos/listar',
    'pedidos/detalle',
    'admin/productos',
    'admin/categorias',
    'admin/usuarios',
    'admin/pedidos',
    'admin/inventario',
    'admin/estadisticas',
    'admin/imagenes',
    'diagnostico',
];

/**
 * Qué endpoint se pidió.
 *
 * Se mira primero el parámetro que agrega la reescritura de vercel.json, y si no está se deduce de
 * la URL. Tener las dos vías evita depender de si la plataforma conserva o no la ruta original.
 */
function endpointPedido(): string
{
    $ruta = '';

    if (isset($_GET['__ruta']) && is_string($_GET['__ruta'])) {
        $ruta = $_GET['__ruta'];
        // Se quita para que los endpoints no vean un parámetro que no es suyo.
        unset($_GET['__ruta'], $_REQUEST['__ruta']);
    }

    if ($ruta === '') {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $ruta = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    }

    if ($ruta === '' && isset($_SERVER['PATH_INFO'])) {
        $ruta = (string) $_SERVER['PATH_INFO'];
    }

    $ruta = ltrim($ruta, '/');
    if (str_starts_with($ruta, 'api/')) {
        $ruta = substr($ruta, 4);
    }
    if (str_ends_with($ruta, '.php')) {
        $ruta = substr($ruta, 0, -4);
    }

    return $ruta;
}

$endpoint = endpointPedido();

// Comparación contra la lista cerrada. Si no coincide EXACTAMENTE, no se ejecuta nada.
if (!in_array($endpoint, ENDPOINTS, true)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Recurso no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/' . $endpoint . '.php';
