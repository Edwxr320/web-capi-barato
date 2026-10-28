-- =====================================================================
-- Cerrar el acceso público a las tablas de la aplicación
-- =====================================================================
--
-- EL PROBLEMA
-- Supabase publica el esquema "public" a través de su API REST (PostgREST). Las tablas creadas por
-- las migraciones anteriores no tenían Row Level Security, y los roles "anon" y "authenticated"
-- reciben permisos por omisión sobre lo que se crea en ese esquema. Resultado comprobado: una
-- petición a /rest/v1/productos con la clave anon devolvía los 12 productos reales, y /rest/v1/usuarios
-- y /rest/v1/sesiones respondían 200.
--
-- Por qué importa: "usuarios" guarda los hashes de contraseña y "sesiones" guarda el contenido de la
-- sesión, que incluye el id de usuario y el token CSRF. Con eso se puede suplantar una sesión.
--
-- LA SOLUCIÓN
-- Esta aplicación NO usa PostgREST: el backend PHP se conecta directo a PostgreSQL con PDO. Así que
-- no hace falta ninguna política pública; lo correcto es cerrar esa puerta por completo, en capas:
--
--   1. RLS habilitado en las 10 tablas, SIN políticas  -> nadie pasa, salvo quien omite RLS.
--   2. Permisos revocados a anon y authenticated       -> no pueden ni mirar las tablas.
--   3. USAGE del esquema revocado a esos dos roles     -> PostgREST no puede ni resolver los nombres.
--   4. Privilegios por omisión corregidos              -> las tablas FUTURAS nacen cerradas.
--
-- POR QUÉ ESTO NO ROMPE LA APLICACIÓN
-- El backend se conecta como "postgres" (usuario "postgres.<ref>" del pooler), que es el PROPIETARIO
-- de estas tablas. En PostgreSQL el propietario de una tabla OMITE su RLS, salvo que se active
-- FORCE ROW LEVEL SECURITY. Acá NO se activa FORCE, a propósito.
--
-- Y no se deja al azar: el bloque de verificación del final comprueba, ya con RLS activo y como el
-- mismo rol que usa PDO, que se puede leer y escribir de verdad. Si no pudiera, lanza una excepción
-- y la migración entera falla en lugar de dejar la aplicación sin acceso a su propia base.
--
-- QUÉ NO TOCA ESTA MIGRACIÓN
--   - No borra ni modifica ninguna tabla, fila, secuencia ni dato.
--   - No toca el esquema "storage" ni sus políticas: Storage sigue igual para las imágenes.
--   - No toca los esquemas "auth", "graphql" ni ningún otro esquema de Supabase.
--   - No toca el rol "service_role" (sirve a la propia plataforma y su clave es de servidor).
--   - No concede NINGÚN permiso nuevo a roles públicos.
--
-- CÓMO REVERTIRLA, si alguna vez se quisiera usar PostgREST:
--   ALTER TABLE <tabla> DISABLE ROW LEVEL SECURITY;
--   GRANT USAGE ON SCHEMA public TO anon, authenticated;
--   GRANT SELECT ON <tabla> TO anon;      -- y recién ahí, políticas RLS explícitas
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. RLS en las 10 tablas, sin ninguna política.
--    Sin políticas, RLS deniega todo a cualquier rol que no lo omita.
-- ---------------------------------------------------------------------
ALTER TABLE public.categorias               ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.productos                ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.producto_imagenes        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.producto_caracteristicas ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.usuarios                 ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.carrito_items            ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pedidos                  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.detalle_pedido           ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sesiones                 ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.intentos_login           ENABLE ROW LEVEL SECURITY;


-- ---------------------------------------------------------------------
-- 2. Quitar a anon y authenticated todo permiso sobre lo que ya existe.
--    Solo se nombran esos dos roles: postgres (el de PDO) no se toca.
-- ---------------------------------------------------------------------
-- Se revoca tambien a PUBLIC (el pseudo-rol "todos"), porque un permiso heredado de PUBLIC
-- alcanzaria igual para leer la tabla. Esto afecta solo a las tablas y secuencias de ESTE esquema,
-- que son las 10 de la aplicacion: no toca el esquema en si ni ningun otro.
REVOKE ALL ON ALL TABLES    IN SCHEMA public FROM PUBLIC, anon, authenticated;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC, anon, authenticated;

-- En funciones se revoca solo a los dos roles de la API. No se toca PUBLIC: la unica funcion es el
-- disparador de fecha_actualizacion, y su propietario (el rol de la aplicacion) conserva EXECUTE.
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM anon, authenticated;


-- ---------------------------------------------------------------------
-- 3. Quitarles el USAGE del esquema que tengan concedido de forma explicita.
--
--    OJO con una sutileza de PostgreSQL: el esquema "public" concede USAGE al pseudo-rol PUBLIC
--    (es decir, a todos), asi que despues de este REVOKE los roles SIGUEN teniendo USAGE efectivo
--    por herencia. Revocarselo a PUBLIC si cerraria esa puerta, pero en una plataforma gestionada
--    como Supabase eso afecta a roles internos de la propia plataforma, y el beneficio no compensa
--    el riesgo: sin permisos sobre las tablas no se puede leer nada aunque se resuelva el nombre.
--
--    La proteccion real son los pasos 1 y 2. Este REVOKE deja el permiso explicito limpio, para
--    que no reaparezca si alguna vez se endurece PUBLIC.
-- ---------------------------------------------------------------------
REVOKE USAGE ON SCHEMA public FROM anon, authenticated;


-- ---------------------------------------------------------------------
-- 4. Privilegios por omisión: que las tablas futuras no nazcan abiertas.
--
--    ALTER DEFAULT PRIVILEGES solo afecta a los objetos que cree el rol indicado. Supabase define
--    los suyos desde "postgres" y/o "supabase_admin". Se intenta con los dos; si no hay permiso
--    sobre supabase_admin, se avisa y se sigue (el resto de las capas ya protege igual).
-- ---------------------------------------------------------------------
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES    FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON FUNCTIONS FROM anon, authenticated;

DO $$
BEGIN
    EXECUTE 'ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA public '
         || 'REVOKE ALL ON TABLES FROM anon, authenticated';
    EXECUTE 'ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA public '
         || 'REVOKE ALL ON SEQUENCES FROM anon, authenticated';
    EXECUTE 'ALTER DEFAULT PRIVILEGES FOR ROLE supabase_admin IN SCHEMA public '
         || 'REVOKE ALL ON FUNCTIONS FROM anon, authenticated';
EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE 'No se pudieron ajustar los privilegios por omision de supabase_admin (%). '
                     'Las demas capas siguen aplicando.', SQLERRM;
END $$;


-- ---------------------------------------------------------------------
-- 5. VERIFICACIÓN. Corre como el MISMO rol que usa PDO en produccion y con RLS ya activo.
--    Cualquier fallo lanza una excepcion: es preferible que la migracion falle entera a dejar la
--    aplicacion sin acceso a su propia base.
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_tabla       text;
    v_faltan      text[] := '{}';
    v_cuenta      bigint;
    v_rol_actual  text := current_user;
    v_propietario text;
BEGIN
    -- 5.1 RLS activo en las 10 tablas, y ninguna con FORCE (que afectaria al propietario).
    FOR v_tabla IN
        SELECT unnest(ARRAY['categorias','productos','producto_imagenes','producto_caracteristicas',
                            'usuarios','carrito_items','pedidos','detalle_pedido',
                            'sesiones','intentos_login'])
    LOOP
        IF NOT EXISTS (SELECT 1 FROM pg_class c
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                       WHERE n.nspname = 'public' AND c.relname = v_tabla AND c.relrowsecurity) THEN
            v_faltan := v_faltan || v_tabla;
        END IF;
        IF EXISTS (SELECT 1 FROM pg_class c
                   JOIN pg_namespace n ON n.oid = c.relnamespace
                   WHERE n.nspname = 'public' AND c.relname = v_tabla AND c.relforcerowsecurity) THEN
            RAISE EXCEPTION 'La tabla % quedo con FORCE ROW LEVEL SECURITY: eso dejaria sin acceso al backend.', v_tabla;
        END IF;
    END LOOP;
    IF array_length(v_faltan, 1) IS NOT NULL THEN
        RAISE EXCEPTION 'Estas tablas quedaron sin RLS: %', array_to_string(v_faltan, ', ');
    END IF;

    -- 5.2 Ninguna politica publica creada por accidente.
    IF EXISTS (SELECT 1 FROM pg_policies WHERE schemaname = 'public') THEN
        RAISE EXCEPTION 'Hay politicas RLS en public. Esta migracion no debe crear ninguna.';
    END IF;

    -- 5.3 anon y authenticated no conservan permisos sobre las tablas de la aplicacion.
    FOR v_tabla IN
        SELECT unnest(ARRAY['categorias','productos','usuarios','sesiones','pedidos'])
    LOOP
        IF has_table_privilege('anon', 'public.' || v_tabla, 'SELECT')
           OR has_table_privilege('anon', 'public.' || v_tabla, 'INSERT')
           OR has_table_privilege('authenticated', 'public.' || v_tabla, 'SELECT')
           OR has_table_privilege('authenticated', 'public.' || v_tabla, 'INSERT') THEN
            RAISE EXCEPTION 'anon o authenticated siguen con permisos sobre %', v_tabla;
        END IF;
    END LOOP;

    -- 5.4 El USAGE del esquema NO se comprueba a proposito: el esquema "public" se lo concede al
    --     pseudo-rol PUBLIC, asi que has_schema_privilege devuelve verdadero igual. Eso no es un
    --     problema: resolver el nombre de una tabla sin ningun permiso sobre ella no sirve de nada.
    --     Lo que de verdad cierra el acceso se comprueba en 5.3 (permisos) y 5.1 (RLS), y despues
    --     se confirma contra la API REST real.

    -- 5.5 LO IMPORTANTE: el rol de la aplicacion sigue LEYENDO los datos con RLS activo.
    SELECT tableowner INTO v_propietario FROM pg_tables
     WHERE schemaname = 'public' AND tablename = 'productos';

    SELECT count(*) INTO v_cuenta FROM public.productos;
    IF v_cuenta <> 12 THEN
        RAISE EXCEPTION 'RLS dejo sin acceso al rol de la aplicacion (%): productos devolvio % filas en vez de 12. Propietario de la tabla: %.',
                        v_rol_actual, v_cuenta, v_propietario;
    END IF;

    SELECT count(*) INTO v_cuenta FROM public.categorias;
    IF v_cuenta <> 3 THEN
        RAISE EXCEPTION 'categorias devolvio % filas en vez de 3 con RLS activo.', v_cuenta;
    END IF;

    SELECT count(*) INTO v_cuenta FROM public.producto_imagenes;
    IF v_cuenta <> 36 THEN
        RAISE EXCEPTION 'producto_imagenes devolvio % filas en vez de 36 con RLS activo.', v_cuenta;
    END IF;

    SELECT count(*) INTO v_cuenta FROM public.producto_caracteristicas;
    IF v_cuenta <> 91 THEN
        RAISE EXCEPTION 'producto_caracteristicas devolvio % filas en vez de 91 con RLS activo.', v_cuenta;
    END IF;

    -- 5.6 Y tambien ESCRIBE. Se prueba sobre "sesiones" (vacia) y se limpia enseguida:
    --     es exactamente lo que hace el manejador de sesiones de PHP en cada peticion.
    INSERT INTO public.sesiones (id, datos, fecha_expiracion)
    VALUES ('__verificacion_rls__', '', CURRENT_TIMESTAMP + INTERVAL '1 minute');

    SELECT count(*) INTO v_cuenta FROM public.sesiones WHERE id = '__verificacion_rls__';
    IF v_cuenta <> 1 THEN
        RAISE EXCEPTION 'Con RLS activo el rol % no puede leer lo que acaba de escribir.', v_rol_actual;
    END IF;

    UPDATE public.sesiones SET datos = 'x' WHERE id = '__verificacion_rls__';
    DELETE FROM public.sesiones WHERE id = '__verificacion_rls__';

    SELECT count(*) INTO v_cuenta FROM public.sesiones;
    IF v_cuenta <> 0 THEN
        RAISE EXCEPTION 'Quedo basura en sesiones tras la verificacion (% filas).', v_cuenta;
    END IF;

    RAISE NOTICE 'Verificacion OK: RLS activo en 10 tablas, anon/authenticated sin acceso, y el rol % lee y escribe normalmente.', v_rol_actual;
END $$;
