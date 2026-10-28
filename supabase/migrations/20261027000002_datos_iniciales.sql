-- =====================================================================
-- MERCADOWEB (Capi-Barato) -- Datos iniciales para PostgreSQL / Supabase
-- Conversion fiel de los datos semilla de sql/mercadoweb.sql.
-- =====================================================================
--
-- El original usaba "SET @id_prod = LAST_INSERT_ID()", una variable de sesion de MySQL que
-- PostgreSQL no tiene. Aca cada producto se inserta dentro de un bloque DO con
-- "RETURNING id_producto INTO v_id": mismo resultado, y sus imagenes y caracteristicas quedan
-- atadas al producto correcto sin depender de que los ids coincidan.
--
-- Las secuencias no necesitan setval: ningun INSERT fija ids explicitos, los genera la identidad.
--
-- Este archivo NO crea ningun usuario administrador: ver la nota al final.
-- =====================================================================

-- --------------------------------------------
-- Categorias
-- --------------------------------------------
INSERT INTO categorias (nombre, slug) VALUES
    ('Celulares', 'celulares'),
    ('Notebooks', 'notebooks'),
    ('Televisores', 'televisores');

-- --------------------------------------------
-- Productos, con sus imagenes y caracteristicas
-- --------------------------------------------
DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Moto G13 Dual Sim 128 Gb Azul Difuso 4 Gb Ram', 499, 14, 100, 'OVERHARD', TRUE, NULL
    FROM categorias WHERE slug = 'celulares'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/celulares/11.png', 1),
    (v_id, 'img/celulares/12.png', 2),
    (v_id, 'img/celulares/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Pantalla IPS de 6.5.', 1),
    (v_id, 'Cámara trasera 13Mpx.', 2),
    (v_id, 'Cámara delantera 5Mpx', 3),
    (v_id, 'Procesador 1.66 GHz con 2GB de RAM.', 4),
    (v_id, 'Batería interna 54 GB.', 5),
    (v_id, 'Con reconocimiento facial.', 6);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Moto E22 64gb 4gb Ram Negro Test', 559, 17, 21, 'OVERHARD', FALSE, 50
    FROM categorias WHERE slug = 'celulares'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/celulares/21.png', 1),
    (v_id, 'img/celulares/22.png', 2),
    (v_id, 'img/celulares/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras', 1),
    (v_id, 'Pantalla de 6.5', 2),
    (v_id, 'Cámara delantera 5Mpx', 3),
    (v_id, 'Procesador 1.66 GHz con 2GB de RAM.', 4),
    (v_id, 'Batería 4020mAh.', 5),
    (v_id, 'Memoria interna de 54 GB', 6);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung A04s 128 GB negro 4 GB RAM', 579, 10, 21, 'DIGIT', FALSE, 50
    FROM categorias WHERE slug = 'celulares'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/celulares/31.png', 1),
    (v_id, 'img/celulares/32.png', 2),
    (v_id, 'img/celulares/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras.', 1),
    (v_id, 'Pantalla PLS de 6.5''.', 2),
    (v_id, 'Tiene 3 cámaras traseras de 50Mpx/2Mpx/2Mpx.', 3),
    (v_id, 'Cámara delantera de 5Mpx.', 4),
    (v_id, 'Procesador Exynos 850 Octa-Core de 2GHz con 4GB de RAM.', 5),
    (v_id, 'Batería de 5000mAh.', 6),
    (v_id, 'Memoria interna de 128GB.', 7),
    (v_id, 'Con reconocimiento facial y sensor de huella dactilar.', 8);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung Galaxy A04s 128 GB blanco 4 GB RAM', 709, 13, 21, 'DIGIT', TRUE, NULL
    FROM categorias WHERE slug = 'celulares'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/celulares/41.png', 1),
    (v_id, 'img/celulares/42.png', 2),
    (v_id, 'img/celulares/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Dispositivo liberado para que elijas la compañía telefónica que prefieras.', 1),
    (v_id, 'Pantalla PLS de 6.5''.', 2),
    (v_id, 'Tiene 3 cámaras traseras de 50Mpx/2Mpx/2Mpx.', 3),
    (v_id, 'Cámara delantera de 5Mpx.', 4),
    (v_id, 'Procesador Exynos 850 Octa-Core de 2GHz con 4GB de RAM.', 5),
    (v_id, 'Batería de 5000mAh.', 6),
    (v_id, 'Memoria interna de 128GB.', 7),
    (v_id, 'Con reconocimiento facial y sensor de huella dactilar.', 8);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Samsung Galaxy Book3 15.6 Intel Core I3 6 Núcleos 8gb 256gb Color Silver', 1349, 13, 2, 'ECOMODICO', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/notebooks/11.png', 1),
    (v_id, 'img/notebooks/12.png', 2),
    (v_id, 'img/notebooks/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Procesador Intel i3', 1),
    (v_id, 'Memoria RAM de 8GB.', 2),
    (v_id, 'Resolución de FHD', 3),
    (v_id, 'Placa de video Intel Iris Xe', 4),
    (v_id, 'Cuenta con puerto USB y puerto HDMI', 5);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Noblex N14x1000 14.1 Hd Intel Celeron 4gb/128gb Color Gris', 1291, 34, 35, 'Tienda New Sound', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/notebooks/21.png', 1),
    (v_id, 'img/notebooks/22.png', 2),
    (v_id, 'img/notebooks/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Procesador Intel Celeron.', 1),
    (v_id, 'Memoria RAM de 4GB.', 2),
    (v_id, 'Resolución de 1366x768 px.', 3),
    (v_id, 'Conexión wifi y bluetooth.', 4),
    (v_id, 'Cuenta con 2 puertos USB y puerto HDMI.', 5),
    (v_id, 'La duración de la batería depende del uso que se le dé al producto.', 6);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Hp 15-dy2061la Intel Core I3 1125g4 8gb 256gb Win10 Color Plateado', 1649, 47, 35, 'HP TIENDA OFICIAL', TRUE, NULL
    FROM categorias WHERE slug = 'notebooks'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/notebooks/31.png', 1),
    (v_id, 'img/notebooks/32.png', 2),
    (v_id, 'img/notebooks/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Procesador Intel Core i3.', 1),
    (v_id, 'Memoria RAM de 8GB.', 2),
    (v_id, 'Resolución de 1366x768 px.', 3),
    (v_id, 'Placa de video Intel UHD Graphics Xe G4 48EUs.', 4),
    (v_id, 'Conexión wifi y bluetooth.', 5),
    (v_id, 'Cuenta con 3 puertos USB y puerto HDMI.', 6),
    (v_id, 'Incluye lector de tarjeta de memoria.', 7),
    (v_id, 'Posee pad numérico.', 8),
    (v_id, 'Modo de sonido Stereo.', 9),
    (v_id, 'La duración de la batería depende del uso que se le dé al producto.', 10);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Notebook Gamer Rog Strix G15 G513rm-hq084w 15.6 R7 512gb Color Eclipse gray', 4328, 5, 35, 'ATAJO AR', FALSE, 100
    FROM categorias WHERE slug = 'notebooks'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/notebooks/41.png', 1),
    (v_id, 'img/notebooks/42.png', 2),
    (v_id, 'img/notebooks/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Procesador AMD Ryzen 7.', 1),
    (v_id, 'Memoria RAM de 16GB.', 2),
    (v_id, 'Resolución de 2560x1440 px.', 3),
    (v_id, 'Placa de video Nvidia RTX 3060.', 4),
    (v_id, 'Conexión wifi y bluetooth.', 5),
    (v_id, 'Cuenta con 4 puertos USB y puerto HDMI.', 6),
    (v_id, 'Con teclado retroiluminado.', 7),
    (v_id, 'La duración de la batería depende del uso que se le dé al producto.', 8);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Tv 24'' Kodak We-24tm005 Hd', 549, 10, 10, 'OPEN FACTORY', TRUE, NULL
    FROM categorias WHERE slug = 'televisores'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/televisores/11.png', 1),
    (v_id, 'img/televisores/12.png', 2),
    (v_id, 'img/televisores/13.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Su resolución es HD.', 1),
    (v_id, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (v_id, 'Modo de sonido: Dolby Digital.', 3),
    (v_id, 'Manejalo por comando de voz.', 4),
    (v_id, 'Sistema operativo Android.', 5),
    (v_id, 'Conectá tus dispositivos mediante sus 3 puertos HDMI y sus 2 puertos USB.', 6),
    (v_id, 'Dimensiones: 71.6cm de ancho, 43.3cm de alto, 7.6cm de profundidad.', 7),
    (v_id, 'Entretenimiento y conectividad en un mismo lugar.', 8);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Smart Led Fhd 40 Pulgadas Google Tv Chromecast Tda Android Kodak', 599, 27, 10, 'MALL WEB', TRUE, NULL
    FROM categorias WHERE slug = 'televisores'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/televisores/21.png', 1),
    (v_id, 'img/televisores/22.png', 2),
    (v_id, 'img/televisores/23.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Su resolución es Full HD.', 1),
    (v_id, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (v_id, 'Modo de sonido: Dolby Atmos.', 3),
    (v_id, 'Manejalo por comando de voz.', 4),
    (v_id, 'Tiene función Screen Share.', 5),
    (v_id, 'Con YouTube.', 6),
    (v_id, 'Google Assistant incorporado.', 7),
    (v_id, 'Sistema operativo Android 10.', 8),
    (v_id, 'Capacidad de almacenamiento de 8GB.', 9),
    (v_id, 'Conectá tus dispositivos mediante sus 3 puertos HDMI y sus 2 puertos USB.', 10);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Smart Tv Samsung Hd 32 T4300', 799, 27, 10, 'MALL WEB', FALSE, 100
    FROM categorias WHERE slug = 'televisores'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/televisores/31.png', 1),
    (v_id, 'img/televisores/32.png', 2),
    (v_id, 'img/televisores/33.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Su resolución es HD.', 1),
    (v_id, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (v_id, 'Modo de sonido: Dolby Digital Plus.', 3),
    (v_id, 'Manejalo por comando de voz.', 4),
    (v_id, 'Tiene función Screen Share.', 5),
    (v_id, 'Con Samsung TV Plus.', 6),
    (v_id, 'Sistema operativo Tizen.', 7),
    (v_id, 'Conectá tus dispositivos mediante sus 2 puertos HDMI y su puerto USB.', 8),
    (v_id, 'Dimensiones: 737.4mm de ancho, 438mm de alto, 74.1mm de profundidad.', 9),
    (v_id, 'Entretenimiento y conectividad en un mismo lugar.', 10);
END $$;

DO $$
DECLARE v_id integer;
BEGIN
INSERT INTO productos (id_categoria, nombre, precio_original, descuento_porcentaje, stock, proveedor, envio_gratis, costo_envio)
    SELECT id_categoria, 'Tv Tcl Qled 55 4k C635 Uhd Google Tv Micro Dimming 120 Hz', 1049, 10, 10, 'MALL WEB', TRUE, NULL
    FROM categorias WHERE slug = 'televisores'
    RETURNING id_producto INTO v_id;
INSERT INTO producto_imagenes (id_producto, url_imagen, orden) VALUES
    (v_id, 'img/televisores/41.png', 1),
    (v_id, 'img/televisores/42.png', 2),
    (v_id, 'img/televisores/43.png', 3);
INSERT INTO producto_caracteristicas (id_producto, descripcion, orden) VALUES
    (v_id, 'Su resolución es 4K.', 1),
    (v_id, 'Tecnología HDR para una calidad de imagen mejorada.', 2),
    (v_id, 'Con Google Duo.', 3),
    (v_id, 'Sistema operativo Google TV.', 4),
    (v_id, 'Conectá tus dispositivos mediante sus 3 puertos HDMI.', 5),
    (v_id, 'Entretenimiento y conectividad en un mismo lugar.', 6);
END $$;

-- --------------------------------------------
-- Usuario administrador
-- --------------------------------------------
-- A PROPOSITO no se crea ningun administrador aca.
--
-- Este archivo vive en un repositorio publico. Un INSERT con un hash fijo haria que la
-- contrasena del panel fuera conocida por cualquiera: un hash bcrypt no protege nada si la
-- contrasena de la que sale ya es publica.
--
-- Para crear el administrador, en tu maquina:
--
--     php sql/crear_admin.php
--
-- Te pide una contrasena (o genera una fuerte), calcula el hash localmente e imprime el INSERT
-- que tenes que ejecutar UNA vez en el SQL Editor de Supabase. Ese SQL no se guarda en el repo.
--
-- Hasta que lo hagas, la tienda funciona pero nadie puede entrar al panel. Es intencional.
