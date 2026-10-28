-- =====================================================================
-- Migración 001 (MySQL / XAMPP) — tablas "sesiones" e "intentos_login"
-- =====================================================================
--
-- PARA QUÉ: el proyecto pasó a guardar las sesiones de PHP y el contador de intentos de login en
-- la base de datos, en lugar de archivos en el disco del servidor. Es lo que permite que el mismo
-- código funcione en XAMPP y en un entorno serverless (Vercel), donde el disco no se comparte
-- entre instancias ni sobrevive.
--
-- CUÁNDO CORRERLA: solo si ya tenías la base "mercadoweb" creada de antes. En una instalación
-- nueva desde sql/mercadoweb.sql estas tablas ya vienen incluidas.
--
-- QUÉ NO HACE: no borra, no vacía y no modifica ninguna tabla ni fila existente. Solo agrega dos
-- tablas nuevas. Es seguro correrla sobre tu base actual.
--
-- CÓMO CORRERLA (desde la raíz del proyecto):
--     mysql -u root mercadoweb < sql/migraciones/001_sesiones_e_intentos.sql
--
-- EFECTO SECUNDARIO ESPERADO: al aplicarla, las sesiones abiertas en el navegador dejan de ser
-- válidas (estaban en archivos y ahora se leen de la base). Simplemente hay que volver a entrar.
-- =====================================================================

CREATE TABLE IF NOT EXISTS sesiones (
    id                VARCHAR(128) PRIMARY KEY,
    datos             MEDIUMTEXT   NOT NULL,
    fecha_expiracion  DATETIME     NOT NULL,
    INDEX idx_sesiones_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intentos_login (
    id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave    CHAR(64) NOT NULL,
    momento  DATETIME NOT NULL,
    INDEX idx_intentos_clave_momento (clave, momento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
