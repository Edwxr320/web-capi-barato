<?php
/**
 * Resolución de configuración por entorno.
 *
 * Un único punto que decide de dónde salen las credenciales y los parámetros de negocio:
 *
 *   1. PRODUCCIÓN (Vercel): de variables de entorno. No existe config.php en el servidor
 *      —está en .gitignore y nunca se sube—, así que todo llega por el entorno.
 *   2. LOCAL (XAMPP): de api/config/config.php, exactamente como siempre.
 *
 * El resto del código sigue usando las MISMAS constantes de antes (DB_HOST, DB_NAME, …), así que
 * no cambia nada para quien ya tenía su config.php andando.
 *
 * Nunca se imprime el contenido de estas variables: ante un error se responde un mensaje genérico.
 */

/** Lee una variable de entorno y devuelve null si no está definida o está vacía. */
function entorno(string $nombre): ?string
{
    $valor = getenv($nombre);
    if ($valor === false || $valor === '') {
        $valor = $_ENV[$nombre] ?? $_SERVER[$nombre] ?? null;
    }
    return ($valor === false || $valor === null || $valor === '') ? null : (string) $valor;
}

/**
 * Supabase (y la mayoría de los proveedores) entregan la conexión como una URI.
 * Si existe, se desarma en las partes que necesita PDO. Soporta los nombres más comunes.
 */
function conexionDesdeUrl(): ?array
{
    foreach (['DATABASE_URL', 'POSTGRES_URL', 'SUPABASE_DB_URL'] as $nombre) {
        $url = entorno($nombre);
        if ($url === null) {
            continue;
        }
        $p = parse_url($url);
        if ($p === false || !isset($p['host'])) {
            continue;
        }
        $esquema = $p['scheme'] ?? 'postgresql';
        return [
            'driver' => str_starts_with($esquema, 'mysql') ? 'mysql' : 'pgsql',
            'host'   => $p['host'],
            'port'   => $p['port'] ?? null,
            'nombre' => isset($p['path']) ? ltrim($p['path'], '/') : 'postgres',
            'usuario' => isset($p['user']) ? rawurldecode($p['user']) : '',
            'clave'  => isset($p['pass']) ? rawurldecode($p['pass']) : '',
        ];
    }
    return null;
}

/** Define una constante solo si nadie la definió antes (config.php tiene prioridad en local). */
function definirSiFalta(string $nombre, $valor): void
{
    if (!defined($nombre)) {
        define($nombre, $valor);
    }
}

// --------------------------------------------------------------------------------------------
// 1) Local: si existe config.php, manda él. Así XAMPP sigue funcionando sin tocar nada.
// --------------------------------------------------------------------------------------------
$rutaLocal = __DIR__ . '/config.php';
$hayConfigLocal = is_file($rutaLocal);
if ($hayConfigLocal) {
    require_once $rutaLocal;
}

// --------------------------------------------------------------------------------------------
// 1.b) En producción, los avisos de PHP NO se imprimen en la respuesta.
//
// Se descubrió desplegando: PHP 8.5 marcó curl_close() como obsoleta y el runtime de Vercel trae
// display_errors activado, así que el aviso salía ANTES del JSON. Dos consecuencias, las dos malas:
// el JSON dejaba de ser parseable para el panel, y el aviso incluía la ruta del archivo en el
// servidor. Los errores se siguen registrando (log_errors), y la aplicación ya responde siempre
// con un mensaje genérico en JSON, así que no se pierde nada de diagnóstico.
//
// En local (donde existe config.php) no se toca nada: ahí ver los avisos es justamente lo útil.
// --------------------------------------------------------------------------------------------
if (!$hayConfigLocal) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// --------------------------------------------------------------------------------------------
// 2) Producción: todo desde el entorno.
// --------------------------------------------------------------------------------------------
$url = conexionDesdeUrl();

definirSiFalta('DB_DRIVER', entorno('MW_DB_DRIVER') ?? ($url['driver'] ?? 'mysql'));
definirSiFalta('DB_HOST',   entorno('MW_DB_HOST')   ?? ($url['host']   ?? 'localhost'));
definirSiFalta('DB_NAME',   entorno('MW_DB_NAME')   ?? ($url['nombre'] ?? 'mercadoweb'));
definirSiFalta('DB_USER',   entorno('MW_DB_USER')   ?? ($url['usuario'] ?? 'root'));
definirSiFalta('DB_PASS',   entorno('MW_DB_PASS')   ?? ($url['clave']  ?? ''));
definirSiFalta('DB_PORT',   (int) (entorno('MW_DB_PORT') ?? ($url['port'] ?? (DB_DRIVER === 'pgsql' ? 6543 : 3306))));

// ¿Hay configuración de base de datos utilizable?
define('CONFIG_COMPLETA', $hayConfigLocal || $url !== null || entorno('MW_DB_HOST') !== null);

// Si no hay config.php NI variables de entorno, es un error de despliegue: se avisa sin filtrar datos.
//
// Excepción: api/diagnostico.php define MW_SOLO_DIAGNOSTICO antes de incluir esto, porque su
// trabajo es justamente informar de QUÉ falta. Si cortáramos acá, el diagnóstico sería inútil
// precisamente en el momento en que hace falta.
if (!CONFIG_COMPLETA && !defined('MW_SOLO_DIAGNOSTICO')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'La aplicación no está configurada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Cookies: en producción (HTTPS) tienen que ir con Secure.
definirSiFalta('SESSION_COOKIE_SECURE', entorno('MW_SESSION_COOKIE_SECURE') === '1');

// Zona horaria de la aplicación. Importa de verdad: las estadísticas agrupan ventas POR DÍA y
// Supabase corre en UTC. Sin esto, en Perú (UTC-5) las ventas de 19:00 en adelante se contarían
// al día siguiente. Se aplica tanto a PHP como a la conexión con la base (ver db.php).
definirSiFalta('APP_TZ', entorno('MW_TZ') ?? 'America/Lima');
date_default_timezone_set(APP_TZ);

// Valores de negocio (mismos valores por defecto que config.example.php).
definirSiFalta('STOCK_BAJO_UMBRAL', (int) (entorno('MW_STOCK_BAJO_UMBRAL') ?? 5));
definirSiFalta('LOGIN_MAX_INTENTOS', (int) (entorno('MW_LOGIN_MAX_INTENTOS') ?? 5));
definirSiFalta('LOGIN_VENTANA_SEGUNDOS', (int) (entorno('MW_LOGIN_VENTANA_SEGUNDOS') ?? 900));
definirSiFalta('LOGIN_DIR_INTENTOS', '');

// Almacenamiento de imágenes subidas desde el panel.
//   'local'    -> img/productos/ (XAMPP; en Vercel el disco es de solo lectura)
//   'supabase' -> Supabase Storage vía su API REST
// La service role key es SOLO de servidor: jamás se envía al navegador.
definirSiFalta('ALMACEN_IMAGENES', entorno('MW_ALMACEN_IMAGENES') ?? (entorno('SUPABASE_URL') ? 'supabase' : 'local'));
definirSiFalta('SUPABASE_URL', entorno('SUPABASE_URL') ?? '');
definirSiFalta('SUPABASE_SERVICE_ROLE_KEY', entorno('SUPABASE_SERVICE_ROLE_KEY') ?? '');
definirSiFalta('SUPABASE_BUCKET', entorno('SUPABASE_BUCKET') ?? 'productos');

// Dónde se guardan las sesiones y los intentos de login:
//   'bd'       -> tablas sesiones / intentos_login (único modo que funciona en serverless)
//   'archivos' -> manejador de archivos de PHP (solo para un servidor tradicional)
definirSiFalta('ALMACEN_SESIONES', entorno('MW_ALMACEN_SESIONES') ?? 'bd');
