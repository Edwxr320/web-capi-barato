-- =====================================================================
-- MERCADOWEB (Capi-Barato) — Esquema de base de datos
-- Fase 5 — Versión definitiva para revisión (todavía no implementada)
-- =====================================================================
-- IMPORTANTE: fuerza el charset de la conexión del cliente a utf8mb4 antes
-- de insertar cualquier dato. Sin esto, importar este archivo con un cliente
-- mysql que no fuerce utf8mb4 (el caso por defecto en muchas instalaciones)
-- corrompe los nombres/características con tildes (ej. "Núcleos" -> "NÃºcleos"),
-- aunque el archivo en sí esté perfectamente codificado en UTF-8. Encontrado
-- y corregido durante las pruebas de esta fase -- ver el changelog.
SET NAMES utf8mb4;

-- Convenciones:
--   - Motor InnoDB en todas las tablas (requerido para claves foráneas).
--   - utf8mb4 en todas las tablas (soporta acentos, Ñ y símbolos correctamente).
--   - Toda clave primaria es INT UNSIGNED AUTO_INCREMENT (según lo aprobado en Fase 3).
--   - Los CHECK requieren MySQL 8.0.16+ o MariaDB 10.2.1+ (ambos son el estándar
--     en cualquier instalación local moderna: XAMPP, Laragon, MAMP o MySQL/MariaDB
--     standalone). Si el entorno fuera más viejo, los CHECK se ignoran en silencio
--     pero el resto del esquema funciona igual.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS mercadoweb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mercadoweb;

-- ---------------------------------------------------------------------
-- categorias
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id_categoria    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(50)  NOT NULL,
    slug            VARCHAR(50)  NOT NULL,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- productos
-- ---------------------------------------------------------------------
CREATE TABLE productos (
    id_producto           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria          INT UNSIGNED NOT NULL,
    nombre                VARCHAR(150)   NOT NULL,
    descripcion           TEXT           NULL,
    precio_original       DECIMAL(10,2)  NOT NULL,
    descuento_porcentaje  TINYINT UNSIGNED NULL,
    stock                 INT UNSIGNED   NOT NULL DEFAULT 0,
    proveedor             VARCHAR(100)   NULL,
    envio_gratis          BOOLEAN        NOT NULL DEFAULT FALSE,
    costo_envio           DECIMAL(10,2)  NULL,
    estado                ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_productos_descuento
        CHECK (descuento_porcentaje IS NULL OR descuento_porcentaje BETWEEN 1 AND 99),
    CONSTRAINT chk_productos_envio
        CHECK (envio_gratis = TRUE OR costo_envio IS NOT NULL),
    INDEX idx_productos_categoria_estado (id_categoria, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- producto_imagenes  (normaliza las 3 imágenes por producto del frontend)
-- ---------------------------------------------------------------------
CREATE TABLE producto_imagenes (
    id_imagen     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto   INT UNSIGNED NOT NULL,
    url_imagen    VARCHAR(255) NOT NULL,
    orden         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_imagenes_producto FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_imagenes_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- producto_caracteristicas  (normaliza el array "informacion" del frontend)
-- ---------------------------------------------------------------------
CREATE TABLE producto_caracteristicas (
    id_caracteristica  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto        INT UNSIGNED NOT NULL,
    descripcion        VARCHAR(255) NOT NULL,
    orden              TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_caracteristicas_producto FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_caracteristicas_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    rol             ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- carrito_items  (carrito de servidor — solo usuarios logueados, Fase 3 §12)
-- ---------------------------------------------------------------------
CREATE TABLE carrito_items (
    id_item              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario           INT UNSIGNED NOT NULL,
    id_producto          INT UNSIGNED NOT NULL,
    cantidad             INT UNSIGNED NOT NULL DEFAULT 1,
    fecha_agregado       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carrito_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_carrito_producto FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_carrito_cantidad CHECK (cantidad >= 1),
    UNIQUE KEY uq_carrito_usuario_producto (id_usuario, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- pedidos
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id_pedido        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT UNSIGNED NOT NULL,
    fecha_pedido     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado           ENUM('pendiente','pagado','enviado','completado','cancelado') NOT NULL DEFAULT 'pendiente',
    subtotal         DECIMAL(10,2) NOT NULL,
    costo_envio      DECIMAL(10,2) NOT NULL DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL,
    direccion_envio  VARCHAR(255) NULL,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_pedidos_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- detalle_pedido  (snapshot inmutable del precio al momento de la compra)
-- ---------------------------------------------------------------------
CREATE TABLE detalle_pedido (
    id_detalle       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pedido        INT UNSIGNED NOT NULL,
    id_producto      INT UNSIGNED NOT NULL,
    cantidad         INT UNSIGNED NOT NULL,
    precio_unitario  DECIMAL(10,2) NOT NULL,
    subtotal         DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedidos(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    -- RESTRICT es intencional: nunca debe poder borrarse un producto con historial de ventas.
    CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_detalle_pedido (id_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- sesiones  (sesiones de PHP guardadas en la base -- ver api/includes/sesion_bd.php)
--
-- El manejador de archivos de PHP guarda la sesion en el disco del servidor. En un entorno
-- serverless (Vercel) cada peticion puede atenderla otra instancia, con su propio disco temporal
-- que ademas se borra: la sesion se perderia al azar y, como el token CSRF vive en la sesion,
-- fallaria toda operacion de escritura. Con esta tabla la sesion es compartida y duradera.
-- Se usa el mismo manejador en XAMPP, para que lo que se prueba en local sea lo que corre publicado.
-- ---------------------------------------------------------------------
CREATE TABLE sesiones (
    id                VARCHAR(128) PRIMARY KEY,
    datos             MEDIUMTEXT   NOT NULL,
    fecha_expiracion  DATETIME     NOT NULL,
    INDEX idx_sesiones_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- intentos_login  (contador anti fuerza bruta -- ver api/includes/limite_intentos.php)
--
-- Antes vivia en archivos del directorio temporal. En serverless cada instancia tiene el suyo y se
-- borra, asi que el limite no frenaba nada. "clave" es un SHA-256 de IP + correo: la tabla no
-- guarda direcciones IP ni correos en claro.
-- ---------------------------------------------------------------------
CREATE TABLE intentos_login (
    id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave    CHAR(64) NOT NULL,
    momento  DATETIME NOT NULL,
    INDEX idx_intentos_clave_momento (clave, momento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATOS SEMILLA (catálogo original de la Fase 4 + usuario admin inicial)
-- =====================================================================

-- --------------------------------------------
-- Datos semilla: categorías
-- --------------------------------------------
INSERT INTO categorias (nombre, slug) VALUES
    ('Celulares', 'celulares'),
    ('Notebooks', 'notebooks'),
    ('Televisores', 'televisores');

-- --------------------------------------------
-- Datos semilla: productos (catálogo original de Fase 4)
-- Ortografía evidente corregida (Camara->Cámara, Procesaor->Procesador, etc.)
-- Datos técnicos ambiguos NO modificados -- ver nota al final de este archivo.
-- --------------------------------------------
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Moto G13 Dual Sim 128 Gb Azul Difuso 4 Gb Ram', 499, 14, 100, 'OVERHARD', TRUE, NULL
    FROM categorias WHERE slug = 'celulares';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/celulares/11.png', 1),
    (@id_prod, 'img/celulares/12.png', 2),
    (@id_prod, 'img/celulares/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Pantalla IPS de 6.5.', 1),
    (@id_prod, 'Cámara trasera 13Mpx.', 2),
    (@id_prod, 'Cámara delantera 5Mpx', 3),
    (@id_prod, 'Procesador 1.66 GHz con 2GB de RAM.', 4),
    (@id_prod, 'Batería interna 54 GB.', 5),
    (@id_prod, 'Con reconocimiento facial.', 6);
-- id legado del frontend Fase 4: celular-01

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Moto E22 64gb 4gb Ram Negro Test', 559, 17, 21, 'OVERHARD', FALSE, 50
    FROM categorias WHERE slug = 'celulares';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/celulares/21.png', 1),
    (@id_prod, 'img/celulares/22.png', 2),
    (@id_prod, 'img/celulares/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras', 1),
    (@id_prod, 'Pantalla de 6.5', 2),
    (@id_prod, 'Cámara delantera 5Mpx', 3),
    (@id_prod, 'Procesador 1.66 GHz con 2GB de RAM.', 4),
    (@id_prod, 'Batería 4020mAh.', 5),
    (@id_prod, 'Memoria interna de 54 GB', 6);
-- id legado del frontend Fase 4: celular-02

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung A04s 128 GB negro 4 GB RAM', 579, 10, 21, 'DIGIT', FALSE, 50
    FROM categorias WHERE slug = 'celulares';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/celulares/31.png', 1),
    (@id_prod, 'img/celulares/32.png', 2),
    (@id_prod, 'img/celulares/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras.', 1),
    (@id_prod, 'Pantalla PLS de 6.5\'.', 2),
    (@id_prod, 'Tiene 3 cámaras traseras de 50Mpx/2Mpx/2Mpx.', 3),
    (@id_prod, 'Cámara delantera de 5Mpx.', 4),
    (@id_prod, 'Procesador Exynos 850 Octa-Core de 2GHz con 4GB de RAM.', 5),
    (@id_prod, 'Batería de 5000mAh.', 6),
    (@id_prod, 'Memoria interna de 128GB.', 7),
    (@id_prod, 'Con reconocimiento facial y sensor de huella dactilar.', 8);
-- id legado del frontend Fase 4: celular-03

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung Galaxy A04s 128 GB blanco 4 GB RAM', 709, 13, 21, 'DIGIT', TRUE, NULL
    FROM categorias WHERE slug = 'celulares';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/celulares/41.png', 1),
    (@id_prod, 'img/celulares/42.png', 2),
    (@id_prod, 'img/celulares/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras.', 1),
    (@id_prod, 'Pantalla PLS de 6.5\'.', 2),
    (@id_prod, 'Tiene 3 cámaras traseras de 50Mpx/2Mpx/2Mpx.', 3),
    (@id_prod, 'Cámara delantera de 5Mpx.', 4),
    (@id_prod, 'Procesador Exynos 850 Octa-Core de 2GHz con 4GB de RAM.', 5),
    (@id_prod, 'Batería de 5000mAh.', 6),
    (@id_prod, 'Memoria interna de 128GB.', 7),
    (@id_prod, 'Con reconocimiento facial y sensor de huella dactilar.', 8);
-- id legado del frontend Fase 4: celular-04

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung Galaxy Book3 15.6 Intel Core I3 6 Núcleos 8gb 256gb Color Silver', 1349, 13, 2, 'ECOMODICO', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/notebooks/11.png', 1),
    (@id_prod, 'img/notebooks/12.png', 2),
    (@id_prod, 'img/notebooks/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Procesador Intel i3', 1),
    (@id_prod, 'Memoria RAM de 8GB.', 2),
    (@id_prod, 'Resolución de FHD', 3),
    (@id_prod, 'Placa de video Intel Iris Xe', 4),
    (@id_prod, 'Cuenta con puerto USB y puerto HDMI', 5);
-- id legado del frontend Fase 4: notebook-01

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Noblex N14x1000 14.1 Hd Intel Celeron 4gb/128gb Color Gris', 1291, 34, 35, 'Tienda New Sound', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/notebooks/21.png', 1),
    (@id_prod, 'img/notebooks/22.png', 2),
    (@id_prod, 'img/notebooks/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Procesador Intel Celeron.', 1),
    (@id_prod, 'Memoria RAM de 4GB.', 2),
    (@id_prod, 'Resolución de 1366x768 px.', 3),
    (@id_prod, 'Conexión wifi y bluetooth.', 4),
    (@id_prod, 'Cuenta con 2 puertos USB y puerto HDMI.', 5),
    (@id_prod, 'La duración de la batería depende del uso que se le dé al producto.', 6);
-- id legado del frontend Fase 4: notebook-02

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Hp 15-dy2061la Intel Core I3 1125g4 8gb 256gb Win10 Color Plateado', 1649, 47, 35, 'HP TIENDA OFICIAL', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/notebooks/31.png', 1),
    (@id_prod, 'img/notebooks/32.png', 2),
    (@id_prod, 'img/notebooks/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Procesador Intel Core i3.', 1),
    (@id_prod, 'Memoria RAM de 8GB.', 2),
    (@id_prod, 'Resolución de 1366x768 px.', 3),
    (@id_prod, 'Placa de video Intel UHD Graphics Xe G4 48EUs.', 4),
    (@id_prod, 'Conexión wifi y bluetooth.', 5),
    (@id_prod, 'Cuenta con 3 puertos USB y puerto HDMI.', 6),
    (@id_prod, 'Incluye lector de tarjeta de memoria.', 7),
    (@id_prod, 'Posee pad numérico.', 8),
    (@id_prod, 'Modo de sonido Stereo.', 9),
    (@id_prod, 'La duración de la batería depende del uso que se le dé al producto.', 10);
-- id legado del frontend Fase 4: notebook-03

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Gamer Rog Strix G15 G513rm-hq084w 15.6 R7 512gb Color Eclipse gray', 4328, 5, 35, 'ATAJO AR', FALSE, 100
    FROM categorias WHERE slug = 'notebooks';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/notebooks/41.png', 1),
    (@id_prod, 'img/notebooks/42.png', 2),
    (@id_prod, 'img/notebooks/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Procesador AMD Ryzen 7.', 1),
    (@id_prod, 'Memoria RAM de 16GB.', 2),
    (@id_prod, 'Resolución de 2560x1440 px.', 3),
    (@id_prod, 'Placa de video Nvidia RTX 3060.', 4),
    (@id_prod, 'Conexión wifi y bluetooth.', 5),
    (@id_prod, 'Cuenta con 4 puertos USB y puerto HDMI.', 6),
    (@id_prod, 'Con teclado retroiluminado.', 7),
    (@id_prod, 'La duración de la batería depende del uso que se le dé al producto.', 8);
-- id legado del frontend Fase 4: notebook-04

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Tv 24\' Kodak We-24tm005 Hd', 549, 10, 10, 'OPEN FACTORY', TRUE, NULL
    FROM categorias WHERE slug = 'televisores';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/televisores/11.png', 1),
    (@id_prod, 'img/televisores/12.png', 2),
    (@id_prod, 'img/televisores/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Su resolución es HD.', 1),
    (@id_prod, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (@id_prod, 'Modo de sonido: Dolby Digital.', 3),
    (@id_prod, 'Manejalo por comando de voz.', 4),
    (@id_prod, 'Sistema operativo Android.', 5),
    (@id_prod, 'Conectá tus dispositivos mediante sus 3 puertos HDMI y sus 2 puertos USB.', 6),
    (@id_prod, 'Dimensiones: 71.6cm de ancho, 43.3cm de alto, 7.6cm de profundidad.', 7),
    (@id_prod, 'Entretenimiento y conectividad en un mismo lugar.', 8);
-- id legado del frontend Fase 4: televisor-01

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Smart Led Fhd 40 Pulgadas Google Tv Chromecast Tda Android Kodak', 599, 27, 10, 'MALL WEB', TRUE, NULL
    FROM categorias WHERE slug = 'televisores';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/televisores/21.png', 1),
    (@id_prod, 'img/televisores/22.png', 2),
    (@id_prod, 'img/televisores/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Su resolución es Full HD.', 1),
    (@id_prod, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (@id_prod, 'Modo de sonido: Dolby Atmos.', 3),
    (@id_prod, 'Manejalo por comando de voz.', 4),
    (@id_prod, 'Tiene función Screen Share.', 5),
    (@id_prod, 'Con YouTube.', 6),
    (@id_prod, 'Google Assistant incorporado.', 7),
    (@id_prod, 'Sistema operativo Android 10.', 8),
    (@id_prod, 'Capacidad de almacenamiento de 8GB.', 9),
    (@id_prod, 'Conectá tus dispositivos mediante sus 3 puertos HDMI y sus 2 puertos USB.', 10);
-- id legado del frontend Fase 4: televisor-02

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Smart Tv Samsung Hd 32 T4300', 799, 27, 10, 'MALL WEB', FALSE, 100
    FROM categorias WHERE slug = 'televisores';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/televisores/31.png', 1),
    (@id_prod, 'img/televisores/32.png', 2),
    (@id_prod, 'img/televisores/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Su resolución es HD.', 1),
    (@id_prod, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (@id_prod, 'Modo de sonido: Dolby Digital Plus.', 3),
    (@id_prod, 'Manejalo por comando de voz.', 4),
    (@id_prod, 'Tiene función Screen Share.', 5),
    (@id_prod, 'Con Samsung TV Plus.', 6),
    (@id_prod, 'Sistema operativo Tizen.', 7),
    (@id_prod, 'Conectá tus dispositivos mediante sus 2 puertos HDMI y su puerto USB.', 8),
    (@id_prod, 'Dimensiones: 737.4mm de ancho, 438mm de alto, 74.1mm de profundidad.', 9),
    (@id_prod, 'Entretenimiento y conectividad en un mismo lugar.', 10);
-- id legado del frontend Fase 4: televisor-03

INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Tv Tcl Qled 55 4k C635 Uhd Google Tv Micro Dimming 120 Hz', 1049, 10, 10, 'MALL WEB', TRUE, NULL
    FROM categorias WHERE slug = 'televisores';
SET @id_prod = LAST_INSERT_ID();
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (@id_prod, 'img/televisores/41.png', 1),
    (@id_prod, 'img/televisores/42.png', 2),
    (@id_prod, 'img/televisores/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (@id_prod, 'Su resolución es 4K.', 1),
    (@id_prod, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (@id_prod, 'Con Google Duo.', 3),
    (@id_prod, 'Sistema operativo Google TV.', 4),
    (@id_prod, 'Conectá tus dispositivos mediante sus 3 puertos HDMI.', 5),
    (@id_prod, 'Entretenimiento y conectividad en un mismo lugar.', 6);
-- id legado del frontend Fase 4: televisor-04

-- --------------------------------------------
-- NOTA: los siguientes datos quedaron TAL CUAL estaban en el original.
-- No se modificaron porque corregirlos implicaría inventar un valor o
-- una unidad sin evidencia suficiente en el proyecto. Pendientes de que
-- el cliente confirme el dato real:
--   [celular-01] "Batería interna 54 GB."
--   [celular-02] "Memoria interna de 54 GB"
-- --------------------------------------------
-- --------------------------------------------
-- Usuario administrador inicial
-- --------------------------------------------
-- Contraseña inicial: CapiBarato2026!  (cambiarla apenas se ingrese la primera vez)
-- El hash de abajo se generó con la misma función que va a usar el backend real:
--   php -r "echo password_hash('CapiBarato2026!', PASSWORD_DEFAULT);"
-- No es un hash inventado a mano: es un bcrypt válido, verificado en este mismo entorno.
INSERT INTO usuarios (nombre, email, password_hash, rol, estado) VALUES
    ('Administrador', 'admin@capibarato.pe', '$2y$10$jI5UP2vdZoKB7/CmtEUOy.ewZ7HwCEVZALdCH54pfWRftIAWygFru', 'admin', 'activo');
