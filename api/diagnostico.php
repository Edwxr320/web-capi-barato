<?php
/**
 * Diagnóstico de despliegue. Sirve para comprobar, una sola vez tras el primer despliegue en
 * Vercel, que el runtime trae lo que la aplicación necesita (sobre todo pdo_pgsql) y que la
 * conexión con la base y el almacenamiento están bien configurados.
 *
 * SEGURIDAD — está pensado para no filtrar NADA:
 *
 *   1. Si no está definida la variable de entorno MW_DIAGNOSTICO_TOKEN, responde 404 y no hace
 *      nada. Es decir: por omisión no existe. No hay que acordarse de borrarlo.
 *   2. Si está definida, exige ?token=... y lo compara con hash_equals (tiempo constante).
 *   3. Solo devuelve true/false. Nunca versiones de PHP, rutas, nombres de host, usuarios,
 *      credenciales ni mensajes de error del motor: un atacante no aprende nada aunque acierte.
 *   4. No escribe en la base. La comprobación de conexión es un SELECT 1.
 *
 * USO:
 *   - Definí MW_DIAGNOSTICO_TOKEN en Vercel con un valor largo y al azar.
 *   - Abrí https://<tu-dominio>/api/diagnostico.php?token=<ese-valor>
 *   - Cuando termines de verificar, BORRÁ esa variable de entorno en Vercel. El endpoint vuelve
 *     a responder 404 sin necesidad de tocar el código ni volver a desplegar nada.
 */

// Permite que entorno.php no corte la ejecucion si todavia falta configuracion: este endpoint
// existe para decir QUE falta.
define('MW_SOLO_DIAGNOSTICO', true);

// db.php trae entorno.php y, ademas, los ayudantes esPostgres()/driverBd() que se usan aqui.
// Incluirlo solo define funciones: no abre ninguna conexion por si mismo.
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$tokenEsperado = entorno('MW_DIAGNOSTICO_TOKEN');

// Sin token configurado, este endpoint no existe.
if ($tokenEsperado === null || strlen($tokenEsperado) < 16) {
    http_response_code(404);
    echo json_encode(['error' => 'No encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenRecibido = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
if (!hash_equals($tokenEsperado, $tokenRecibido)) {
    http_response_code(404);
    echo json_encode(['error' => 'No encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Prueba la conexión con la base sin revelar ningún dato de la conexión. */
function baseResponde(): bool
{
    try {
        $dsn = esPostgres()
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => esPostgres(),
            PDO::ATTR_TIMEOUT          => 8,
        ]);
        return (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;   // el motivo NO se devuelve: solo si funciona o no
    }
}

/** ¿Están las tablas que la aplicación necesita? Solo cuenta, no devuelve contenido. */
function tablasPresentes(): bool
{
    try {
        $dsn = esPostgres()
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => esPostgres(),
            PDO::ATTR_TIMEOUT          => 8,
        ]);
        $sql = esPostgres()
            ? "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'
               AND table_name IN ('categorias','productos','producto_imagenes','producto_caracteristicas',
                                  'usuarios','carrito_items','pedidos','detalle_pedido','sesiones','intentos_login')"
            : "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
               AND table_name IN ('categorias','productos','producto_imagenes','producto_caracteristicas',
                                  'usuarios','carrito_items','pedidos','detalle_pedido','sesiones','intentos_login')";
        return (int) $pdo->query($sql)->fetchColumn() === 10;
    } catch (Throwable $e) {
        return false;
    }
}

$conecta = CONFIG_COMPLETA ? baseResponde() : false;

echo json_encode([
    // Extensiones que el runtime tiene que traer.
    'pdo'                 => extension_loaded('pdo'),
    'pdo_pgsql'           => extension_loaded('pdo_pgsql'),
    'curl'                => extension_loaded('curl'),
    'fileinfo'            => extension_loaded('fileinfo'),
    'sesiones_php'        => function_exists('session_start'),
    // Configuración resuelta (sin valores: solo si es la esperada en producción).
    'configuracion_completa' => CONFIG_COMPLETA,
    'motor_es_postgres'   => esPostgres(),
    'cookies_seguras'     => (bool) SESSION_COOKIE_SECURE,
    'sesiones_en_base'    => ALMACEN_SESIONES === 'bd',
    'imagenes_en_supabase' => ALMACEN_IMAGENES === 'supabase',
    'storage_configurado' => SUPABASE_URL !== '' && SUPABASE_SERVICE_ROLE_KEY !== '',
    // Conectividad real.
    'base_responde'       => $conecta,
    'tablas_completas'    => $conecta ? tablasPresentes() : false,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
