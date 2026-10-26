<?php
/**
 * GET /api/categorias/listar.php
 * Endpoint público -- no requiere sesión.
 * Respuesta: { "categorias": [ {id_categoria, nombre, slug}, ... ] }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/respuesta.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError('Método no permitido.', 405);
}

$pdo = obtenerConexion();
$stmt = $pdo->query('SELECT id_categoria, nombre, slug FROM categorias ORDER BY nombre');
$categorias = $stmt->fetchAll();

responderJson(['categorias' => $categorias]);
