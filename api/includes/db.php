<?php
/**
 * Conexión única y reutilizable a MySQL vía PDO.
 *
 * Regla no negociable del proyecto: toda consulta usa sentencias preparadas.
 * Nunca se concatenan datos de entrada directamente en el SQL.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Nombre de la base a usar. Por defecto es DB_NAME (config.php).
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

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . nombreBaseDatos() . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
            exit;
        }
    }

    return $pdo;
}
