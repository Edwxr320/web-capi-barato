<?php
/**
 * Conexión única y reutilizable vía PDO, para MySQL (XAMPP) y PostgreSQL (Supabase).
 *
 * Regla no negociable del proyecto: toda consulta usa sentencias preparadas.
 * Nunca se concatenan datos de entrada directamente en el SQL.
 *
 * El 95 % del SQL del proyecto es portable y se escribe una sola vez. Para lo que NO se puede
 * escribir igual en los dos motores, este archivo expone ayudantes (ultimoId, sqlContiene,
 * sqlInicioDe, valorBooleano, aBooleano) en vez de repartir ifs por todos los endpoints.
 */

require_once __DIR__ . '/../config/entorno.php';

/**
 * Nombre de la base a usar. Por defecto es DB_NAME (config.php o variables de entorno).
 *
 * Solo para las pruebas automáticas: tests/run_all.sh levanta su propio servidor
 * PHP con la variable de entorno MERCADOWEB_DB_PRUEBAS para apuntar a una base
 * AISLADA. Por seguridad, ese nombre solo se acepta si termina en "_test": así es
 * imposible que la variable desvíe la aplicación hacia otra base real.
 */
function nombreBaseDatos(): string
{
    $bdPruebas = getenv('MERCADOWEB_DB_PRUEBAS');
    if ($bdPruebas === false || $bdPruebas === '') {
        return DB_NAME;
    }
    if (!preg_match('/^[A-Za-z0-9_]+_test$/', $bdPruebas)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Configuración de pruebas inválida.']);
        exit;
    }
    return $bdPruebas;
}

/** Motor en uso: 'mysql' o 'pgsql'. */
function driverBd(): string
{
    return DB_DRIVER === 'pgsql' ? 'pgsql' : 'mysql';
}

function esPostgres(): bool
{
    return driverBd() === 'pgsql';
}

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $base = nombreBaseDatos();
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (esPostgres()) {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, $base);
            // El pooler de Supabase en modo transacción (puerto 6543) NO admite sentencias
            // preparadas del servidor. Con la emulación activada PDO sigue escapando los valores
            // con el driver (no es concatenación manual): la protección contra inyección se mantiene.
            $opciones[PDO::ATTR_EMULATE_PREPARES] = true;
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, $base);
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            // Misma zona horaria que PHP: las estadísticas agrupan por día con el reloj de la base.
            if (esPostgres()) {
                $pdo->exec("SET TIME ZONE '" . str_replace("'", '', APP_TZ) . "'");
            }
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
            exit;
        }
    }

    return $pdo;
}

// ---------------------------------------------------------------------------------------------
// Ayudantes de portabilidad entre MySQL y PostgreSQL
// ---------------------------------------------------------------------------------------------

/**
 * Id generado por el último INSERT.
 *
 * MySQL lo devuelve sin más; PostgreSQL necesita el nombre de la secuencia asociada a la columna
 * (el que crea GENERATED ... AS IDENTITY: "<tabla>_<columna>_seq").
 */
function ultimoId(PDO $pdo, string $tabla, string $columna): int
{
    return (int) (esPostgres() ? $pdo->lastInsertId($tabla . '_' . $columna . '_seq') : $pdo->lastInsertId());
}

/**
 * Condición "este texto contiene lo buscado", ignorando mayúsculas y acentos.
 *
 * En MySQL lo resuelve la collation utf8mb4_unicode_ci de las columnas, así que alcanza con LIKE.
 * PostgreSQL distingue mayúsculas Y acentos, así que se usa ILIKE sobre unaccent() en ambos lados:
 * sin esto, buscar "camara" dejaría de encontrar "Cámara" (comportamiento que el proyecto ya tenía).
 */
function sqlContiene(string $columna, string $parametro): string
{
    return esPostgres()
        ? "unaccent($columna) ILIKE unaccent(:$parametro)"
        : "$columna LIKE :$parametro";
}

/**
 * Fecha de inicio del período ('day', 'week', 'month') al que pertenece una columna de fecha.
 * La semana empieza el LUNES en los dos motores.
 */
function sqlInicioDe(string $periodo, string $columna): string
{
    if (esPostgres()) {
        // date_trunc('week', …) ya empieza en lunes en PostgreSQL.
        return "date_trunc('$periodo', $columna)::date";
    }
    return match ($periodo) {
        'week'  => "DATE($columna - INTERVAL WEEKDAY($columna) DAY)",
        'month' => "DATE($columna - INTERVAL (DAYOFMONTH($columna) - 1) DAY)",
        default => "DATE($columna)",
    };
}

/**
 * Consulta que devuelve la fecha y hora actuales SIEMPRE como 'YYYY-MM-DD HH:MM:SS'.
 *
 * NOW() de PostgreSQL devuelve un timestamptz con desfase horario ("2026-10-27 03:42:00.12-05"),
 * que el Date() de JavaScript del panel no sabe parsear. Con to_char el formato es idéntico al
 * de MySQL y el frontend no cambia.
 */
function sqlAhoraTexto(): string
{
    return esPostgres()
        ? "SELECT to_char(CURRENT_TIMESTAMP, 'YYYY-MM-DD HH24:MI:SS')"
        : 'SELECT NOW()';
}

/**
 * Valor a enlazar para una columna booleana.
 * PostgreSQL rechaza 1/0 en una columna boolean; MySQL guarda el booleano como TINYINT.
 */
function valorBooleano(bool $valor)
{
    return esPostgres() ? ($valor ? 'true' : 'false') : ($valor ? 1 : 0);
}

/**
 * Normaliza a bool lo que devuelve la base.
 *
 * Imprescindible: con la emulación de sentencias preparadas, PostgreSQL devuelve los booleanos
 * como las cadenas 't' y 'f', y un (bool)'f' en PHP vale TRUE. Sin esta función, un producto sin
 * envío gratis pasaría a tenerlo.
 */
function aBooleano($valor): bool
{
    if (is_bool($valor)) {
        return $valor;
    }
    if ($valor === null) {
        return false;
    }
    $texto = strtolower(trim((string) $valor));
    return $texto === 't' || $texto === 'true' || $texto === '1' || $texto === 'y';
}
