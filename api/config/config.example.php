<?php
/**
 * Plantilla de configuración local.
 *
 * Copiá este archivo como "config.php" (mismo directorio) y completá los datos
 * de tu MySQL/MariaDB local. "config.php" NUNCA se sube al repositorio -- ya
 * está listado en .gitignore -- justamente para no exponer credenciales reales.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'mercadoweb');
define('DB_USER', 'root');
define('DB_PASS', '');

// Usado por includes/auth_helpers.php para las cookies de sesión.
// En producción (HTTPS) esto debería ser true.
define('SESSION_COOKIE_SECURE', false);

// --- Valores de negocio configurables (si faltan, se usan los valores por defecto indicados) ---

// Inventario (Bloque 4): un producto se marca con "stock bajo" cuando su stock es MENOR O IGUAL
// a este número (incluye stock 0). Valor por defecto: 5.
define('STOCK_BAJO_UMBRAL', 5);

// Login: tras LOGIN_MAX_INTENTOS fallos para el mismo correo desde la misma IP, se bloquea ese
// correo desde esa IP durante LOGIN_VENTANA_SEGUNDOS. Por defecto: 5 intentos / 900 s (15 min).
define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_VENTANA_SEGUNDOS', 900);
// Carpeta donde se guardan esos contadores. Vacío = carpeta temporal del sistema.
define('LOGIN_DIR_INTENTOS', '');
