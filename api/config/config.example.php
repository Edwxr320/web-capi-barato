<?php
/**
 * Plantilla de configuración LOCAL (XAMPP).
 *
 * Copiá este archivo como "config.php" (mismo directorio) y completá los datos
 * de tu MySQL/MariaDB local. "config.php" NUNCA se sube al repositorio -- ya
 * está listado en .gitignore -- justamente para no exponer credenciales reales.
 *
 * EN PRODUCCIÓN NO EXISTE ESTE ARCHIVO. En Vercel no hay config.php: todos estos valores
 * llegan por variables de entorno, que resuelve api/config/entorno.php. El nombre de cada
 * variable está anotado debajo de la constante correspondiente, y la guía completa está en
 * docs/DEPLOYMENT_VERCEL_SUPABASE.md.
 *
 * Nunca pongas acá credenciales de producción: este archivo es para tu máquina.
 */

// Motor de base de datos: 'mysql' en local, 'pgsql' en producción (Supabase).
// En producción: MW_DB_DRIVER
define('DB_DRIVER', 'mysql');

// En producción: MW_DB_HOST / MW_DB_PORT / MW_DB_NAME / MW_DB_USER / MW_DB_PASS,
// o bien una sola variable DATABASE_URL con la cadena de conexión completa.
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'mercadoweb');
define('DB_USER', 'root');
define('DB_PASS', '');

// Zona horaria de la tienda. Importa: las estadísticas agrupan las ventas POR DÍA, y Supabase
// corre en UTC. Sin esto, en Perú (UTC-5) las ventas desde las 19:00 se contarían al día siguiente.
// En producción: MW_TZ
define('APP_TZ', 'America/Lima');

// Usado por includes/auth_helpers.php para las cookies de sesión.
// En producción (HTTPS) tiene que ser true.  En producción: MW_SESSION_COOKIE_SECURE=1
define('SESSION_COOKIE_SECURE', false);

// Dónde se guardan las sesiones de PHP y el contador de intentos de login:
//   'bd'       -> tablas "sesiones" e "intentos_login" (ver api/includes/sesion_bd.php)
//   'archivos' -> manejador de archivos de PHP
// Se recomienda 'bd' también en local, para que lo que probás sea lo mismo que corre publicado.
// En serverless 'archivos' NO funciona: el disco no se comparte entre instancias ni sobrevive.
// En producción: MW_ALMACEN_SESIONES
define('ALMACEN_SESIONES', 'bd');

// Dónde se guardan las imágenes que se suben desde el panel:
//   'local'    -> img/productos/ del propio sitio (XAMPP)
//   'supabase' -> Supabase Storage (obligatorio en Vercel: el disco es de solo lectura)
// En producción: MW_ALMACEN_IMAGENES, junto con SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY y
// SUPABASE_BUCKET. Esa clave (la secreta 'sb_secret_...' actual o la service_role heredada: el
// código acepta las dos) es solo de servidor y nunca se envía al navegador.
define('ALMACEN_IMAGENES', 'local');

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
