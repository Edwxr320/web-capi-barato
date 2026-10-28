# Despliegue en Vercel + Supabase

Guía para publicar Capi-Barato en Internet conservando **el mismo código, el mismo diseño y todas
las funciones**: catálogo, búsqueda, registro, login, roles, carrito, checkout, pedidos, Mis Compras
y panel de administración.

La versión local en XAMPP **sigue funcionando igual**. No hay que elegir entre una y otra.

---

## 1. Qué cambió y por qué

Vercel ejecuta el PHP en funciones *serverless*. Eso impone tres reglas que la aplicación no cumplía:
el disco es de solo lectura, no se comparte entre instancias y se borra. Además Supabase es
PostgreSQL, no MySQL. Estos son los cambios que se hicieron, todos compatibles con XAMPP:

| Qué | Antes | Ahora |
|---|---|---|
| Configuración | solo `api/config/config.php` | `config.php` en local; **variables de entorno** en producción (`api/config/entorno.php`) |
| Conexión | MySQL fijo | MySQL o PostgreSQL según `DB_DRIVER` (`api/includes/db.php`) |
| Sesiones de PHP | archivos en disco | tabla `sesiones` (`api/includes/sesion_bd.php`) |
| Límite de intentos de login | archivos en disco | tabla `intentos_login` (`api/includes/limite_intentos.php`) |
| Imágenes subidas | `img/productos/` | Supabase Storage en producción (`api/includes/almacen_imagenes.php`) |
| Literales SQL | `"activo"` (comillas dobles) | `'activo'` — PostgreSQL leía las dobles como nombre de columna |
| `SUM(condición)` | suma booleanos (MySQL) | `SUM(CASE WHEN … THEN 1 ELSE 0 END)` |
| Búsqueda | `LIKE` + collation de MySQL | `ILIKE` + `unaccent()` en PostgreSQL, para seguir ignorando mayúsculas y acentos |
| `lastInsertId()` | directo | `ultimoId()`, que en PostgreSQL usa el nombre de la secuencia |
| Fechas de estadísticas | `CURDATE`, `WEEKDAY`, `DAYOFMONTH` | `CURRENT_DATE` y `date_trunc`, vía `sqlInicioDe()` |
| Booleanos | `(bool)` directo | `aBooleano()` — PostgreSQL devuelve `'f'`, y `(bool)'f'` en PHP vale **true** |

**Por qué las sesiones en la base no son opcional:** el token CSRF vive en `$_SESSION`. Con sesiones
en archivos, en serverless se perdería al azar y fallaría *toda* operación de escritura (login,
carrito, checkout, panel), de forma intermitente y difícil de diagnosticar.

---

## 2. Antes de empezar

Necesitás una cuenta de [Supabase](https://supabase.com) y una de [Vercel](https://vercel.com), y el
repositorio ya publicado en GitHub (`Edwxr320/web-capi-barato`). Las dos tienen plan gratuito
suficiente para esta tienda.

---

## 3. Supabase: crear la base de datos

### 3.1 Crear el proyecto

1. En Supabase, **New project**. Elegí una región cercana (por ejemplo `South America (São Paulo)`).
2. Guardá la contraseña de la base que te muestra: **solo se ve una vez**.

### 3.2 Aplicar las migraciones

Los archivos están en `supabase/migrations/`, con el formato de nombre que exige la CLI
(`AAAAMMDDHHMMSS_nombre.sql`):

| Archivo | Qué hace |
|---|---|
| `20261027000001_esquema.sql` | 10 tablas, tipos enumerados, claves foráneas, índices, `CHECK` y disparadores |
| `20261027000002_datos_iniciales.sql` | 3 categorías y 12 productos con sus 36 imágenes y características |
| `20261027000003_proteger_api_publica.sql` | Cierra el acceso de `anon` / `authenticated` a las 10 tablas: RLS sin políticas, permisos revocados y privilegios por omisión corregidos |

Las tres son **acumulativas y van en ese orden**: la tercera verifica filas que inserta la segunda,
así que fuera de orden o por separado falla.

**La tercera no es opcional.** Supabase publica el esquema `public` a través de su API REST
(PostgREST), y las tablas que crean las dos primeras migraciones nacen legibles para los roles
`anon` y `authenticated`. Comprobado sobre un proyecto real: `/rest/v1/productos` con la clave
`anon` devolvía los 12 productos, y `/rest/v1/usuarios` y `/rest/v1/sesiones` respondían `200` — o
sea que los hashes de contraseña y el contenido de las sesiones (id de usuario y token CSRF)
quedaban al alcance de cualquiera. Esta aplicación **no** usa PostgREST: el backend PHP se conecta
directo con PDO, así que lo correcto es cerrar esa puerta por completo en lugar de abrir políticas.

Hay dos caminos. **Con la CLI** (recomendado, deja registro de versiones en la base):

```bash
npx supabase login
npx supabase link --project-ref <tu-project-ref>
npx supabase migration list      # qué hay aplicado y qué falta
npx supabase db push --dry-run   # qué se aplicaría, sin tocar nada
npx supabase db push             # aplicarlas de verdad
```

**O a mano**, pegando el contenido de cada archivo en el **SQL Editor** de Supabase, en ese orden.

> Nunca uses `supabase db reset --linked`: borra y reconstruye la base remota.

#### Verificar cada migración

**1 y 2 — esquema y datos.** No traen comprobación interna; se verifican con estas dos consultas.
Los conteos:

```sql
SELECT
  (SELECT COUNT(*) FROM categorias) AS categorias,                     -- 3
  (SELECT COUNT(*) FROM productos)  AS productos,                      -- 12
  (SELECT COUNT(*) FROM producto_imagenes) AS imagenes,                 -- 36
  (SELECT COUNT(*) FROM producto_caracteristicas) AS caracteristicas,   -- 91
  (SELECT COUNT(*) FROM usuarios)   AS usuarios;                       -- 0, es correcto: ver 3.2.1
```

Y que estén las 10 tablas, incluidas las dos que agrega el camino serverless:

```sql
SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;
-- carrito_items, categorias, detalle_pedido, intentos_login, pedidos,
-- producto_caracteristicas, producto_imagenes, productos, sesiones, usuarios
```

**3 — cierre de la API pública.** Esta **se verifica sola**: termina con un bloque `DO` que corre ya
con RLS activo y como el mismo rol que usa PDO en producción, y que lanza una excepción —abortando
la migración entera— si algo quedó mal. Comprueba que:

- las 10 tablas tienen RLS activo y **ninguna** con `FORCE ROW LEVEL SECURITY`, que afectaría
  también al propietario, es decir al backend;
- no quedó ninguna política RLS en `public` (esta migración no debe crear ninguna);
- `anon` y `authenticated` no conservan `SELECT` ni `INSERT` sobre las tablas;
- el rol de la aplicación **sigue leyendo** con RLS activo: 12 productos, 3 categorías, 36 imágenes
  y 91 características;
- y **sigue escribiendo**: hace `INSERT`, `SELECT`, `UPDATE` y `DELETE` sobre `sesiones` —
  exactamente lo que hace el manejador de sesiones en cada petición— y deja la tabla vacía.

Si todo pasó, imprime `Verificacion OK: RLS activo en 10 tablas, ...`. Con `db push` ese aviso sale
en la salida de la CLI; en el SQL Editor, en el panel de mensajes.

Lo que mantiene la aplicación en pie es que el **propietario** de una tabla omite su RLS: el backend
se conecta como `postgres.<ref>`, que es quien las creó. Por eso la migración no activa `FORCE`.

Confirmalo además **desde afuera**, contra la API REST real. La clave `anon` está en
**Project Settings → API**:

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -H "apikey: <anon-key>" \
  "https://<tu-ref>.supabase.co/rest/v1/usuarios?select=*"
```

Tiene que responder un error (`401`, `403` o `404` según cómo resuelva PostgREST), **nunca `200`
con filas**. Repetilo con `productos` y `sesiones`. Si alguna devuelve datos, la migración 3 no se
aplicó.

Y que el backend sí entra se comprueba con `tablas_completas: true` en el punto 5.1 y con el
recorrido del punto 6.

> La clave `anon` es pública por diseño —en un proyecto que use PostgREST viaja en el navegador—,
> pero acá no aparece en ninguna parte del código. Si la pegás en una terminal, no la dejes en el
> historial del shell ni la subas al repositorio.

### 3.2.1 Crear el administrador

**Los datos iniciales no crean ningún administrador, a propósito.** Este repositorio es público: un
`INSERT` con un hash fijo dejaría la contraseña del panel al alcance de cualquiera, porque un hash
bcrypt no protege nada si la contraseña de la que sale ya se conoce.

En tu máquina:

```bash
php sql/crear_admin.php
```

Genera una contraseña fuerte, la muestra **una sola vez** y te imprime el `INSERT` para ejecutar en
el SQL Editor de Supabase. Con `--escribir` podés elegir la tuya, y con `--email=` / `--nombre=`
cambiar esos datos. El mismo comando sirve después para rotar la contraseña.

No guardes ese SQL en el repositorio: lleva el hash de tu contraseña real.

Hasta que lo ejecutes, la tienda funciona pero nadie puede entrar al panel. Es intencional.

### 3.3 Crear el bucket de imágenes

1. **Storage → New bucket**, nombre `productos`.
2. Marcalo como **Public bucket**: las fotos del catálogo tienen que poder verse sin autenticación.

Subir imágenes sigue estando restringido al panel, que exige sesión de administrador y token CSRF;
el bucket público solo permite *leerlas*.

### 3.4 Copiar la cadena de conexión

**Project Settings → Database → Connection string**, y elegí **Transaction pooler** (puerto `6543`).

Anotá: host (`aws-N-REGION.pooler.supabase.com`), puerto `6543`, base `postgres`, usuario
(`postgres.<ref-del-proyecto>`) y la contraseña del paso 3.1.

> **Usá el pooler, no la conexión directa.** Cada invocación serverless abre su propia conexión; sin
> pooler se agota el límite de PostgreSQL enseguida. El modo transacción no admite sentencias
> preparadas del servidor, y por eso `db.php` activa la emulación de PDO cuando el motor es
> PostgreSQL. La protección contra inyección SQL se mantiene: los valores los sigue escapando el
> driver, nunca se concatenan a mano.

### 3.5 Claves de la API

En **Project Settings → API** copiá:

- **Project URL** → `SUPABASE_URL`
- la **clave secreta de servidor** → `SUPABASE_SERVICE_ROLE_KEY`

Supabase tiene dos generaciones de claves y **las dos sirven**, bajo ese mismo nombre de variable:

| En el panel de Supabase | Formato | Qué hace el código |
|---|---|---|
| **Secret key** (`sb_secret_…`), la actual | opaca, no es un JWT | la manda solo en la cabecera `apikey` |
| **service_role key**, la heredada | JWT (`eyJ…`) | la manda en `apikey` y en `Authorization: Bearer`, como siempre |

El nombre de la variable sigue siendo `SUPABASE_SERVICE_ROLE_KEY` por compatibilidad; el valor puede
ser cualquiera de las dos. `claveSupabaseEsJwt()` en `api/includes/almacen_imagenes.php` mira la
**forma** de la clave y arma las cabeceras en consecuencia.

> **Por qué no da lo mismo.** Las claves nuevas no son JWT, y el API Gateway de Supabase intenta
> decodificar como JWT lo que llegue en `Authorization: Bearer`: con una `sb_secret_` ahí, la
> petición falla entera. La vía documentada para estas claves es la cabecera `apikey`. Por eso el
> código manda `apikey` siempre y añade `Authorization` solo si la clave es un JWT.

> La clave secreta da **acceso total** al proyecto. Va **solo** como variable de entorno del
> servidor. Nunca en el HTML, en el JavaScript, ni en una variable con prefijo público. En este
> proyecto se usa únicamente dentro de `api/includes/almacen_imagenes.php`, que corre en el servidor.
>
> La **publishable key** (`sb_publishable_…`) no se usa en ninguna parte: esta aplicación no habla
> con PostgREST, va directo a PostgreSQL por PDO. No hace falta configurarla.

---

## 4. Vercel: variables de entorno

En **Project Settings → Environment Variables**, para *Production* y *Preview*:

| Variable | Valor | Para qué |
|---|---|---|
| `MW_DB_DRIVER` | `pgsql` | Usar PostgreSQL |
| `MW_DB_HOST` | `aws-N-REGION.pooler.supabase.com` | Host del pooler (3.4) |
| `MW_DB_PORT` | `6543` | Puerto del pooler en modo transacción |
| `MW_DB_NAME` | `postgres` | Nombre de la base |
| `MW_DB_USER` | `postgres.<ref-del-proyecto>` | Usuario del pooler |
| `MW_DB_PASS` | *(la del paso 3.1)* | Contraseña |
| `MW_SESSION_COOKIE_SECURE` | `1` | Cookies solo por HTTPS |
| `MW_TZ` | `America/Lima` | Zona horaria de la tienda |
| `MW_ALMACEN_SESIONES` | `bd` | Sesiones en la tabla `sesiones` |
| `MW_ALMACEN_IMAGENES` | `supabase` | Subidas a Storage |
| `SUPABASE_URL` | *(Project URL)* | API de Storage |
| `SUPABASE_SERVICE_ROLE_KEY` | *(la clave secreta del paso 3.5)* | Autenticación de Storage |
| `SUPABASE_BUCKET` | `productos` | Bucket de imágenes |

Opcionales (si no están, se usan los mismos valores por defecto de siempre): `MW_STOCK_BAJO_UMBRAL`
(5), `MW_LOGIN_MAX_INTENTOS` (5), `MW_LOGIN_VENTANA_SEGUNDOS` (900).

Y una **temporal**, solo para la comprobación del punto 5.1, que se borra después:
`MW_DIAGNOSTICO_TOKEN`.

En lugar de las seis `MW_DB_*` podés usar una sola `DATABASE_URL` con la cadena completa; `entorno.php`
la desarma. Si están las dos, mandan las `MW_DB_*`.

---

## 5. Desplegar

1. En Vercel, **Add New → Project** e importá `Edwxr320/web-capi-barato`.
2. Framework preset: **Other**. Sin *build command* ni *output directory*: el sitio es estático más
   funciones PHP.
3. Cargá las variables del punto 4 **antes** del primer despliegue.
4. **Deploy**.

`vercel.json` ya declara el runtime `vercel-php@0.9.0` para cada carpeta de endpoints y devuelve 404
en `/api/includes/` y `/api/config/`, para que esos archivos no puedan servirse como texto plano.

### 5.1 Comprobar que el runtime trae PostgreSQL

**Hacé esto antes de dar el despliegue por bueno.** El runtime es comunitario y ha habido versiones
publicadas sin la extensión de PostgreSQL.

El proyecto ya incluye `api/diagnostico.php`, pensado para no filtrar nada:

- **Sin la variable `MW_DIAGNOSTICO_TOKEN`, responde 404 y no hace nada.** Por omisión no existe,
  así que no hay riesgo de olvidarse de borrarlo.
- Con ella definida, exige `?token=...` y lo compara en tiempo constante.
- Devuelve **solo true/false**: ni versiones de PHP, ni rutas, ni host, ni usuario, ni credenciales,
  ni mensajes de error del motor. Quien acierte la URL sin el token no aprende nada.
- No escribe en la base: la prueba de conexión es un `SELECT 1`.
- La prueba de Storage es **opt-in** (`&storage=1`) y se limpia sola: ver 5.2.

Pasos:

1. En Vercel, definí `MW_DIAGNOSTICO_TOKEN` con un valor largo y al azar (por ejemplo el que
   genera `php -r "echo bin2hex(random_bytes(24));"`).
2. Abrí `https://<tu-dominio>/api/diagnostico.php?token=<ese-valor>`.
3. Tiene que responder, como mínimo:

```json
{
  "pdo_pgsql": true,
  "curl": true,
  "motor_es_postgres": true,
  "cookies_seguras": true,
  "sesiones_en_base": true,
  "imagenes_en_supabase": true,
  "storage_configurado": true,
  "base_responde": true,
  "tablas_completas": true,
  "storage_prueba": null
}
```

4. Cuando termines, **borrá `MW_DIAGNOSTICO_TOKEN` en Vercel y volvé a desplegar**. En Vercel las
   variables de entorno se fijan por despliegue: borrarla en el panel **no** apaga el endpoint en el
   despliegue que ya está vivo. Mientras no redespliegues, sigue respondiendo con el token viejo.

Si `pdo_pgsql` diera `false`, la alternativa es desplegar con contenedor (`Dockerfile.vercel` y
`"runtime": "container"`), donde vos elegís las extensiones. El resto del código no cambia.

### 5.2 Comprobar Supabase Storage de verdad

`storage_configurado` solo dice que `SUPABASE_URL` y la clave no están vacías: **no prueba que la
autenticación funcione**. Con las claves nuevas (`sb_secret_…`, opacas en lugar de JWT) esa
distinción importa, porque lo que puede fallar es la cabecera con la que viaja la clave (ver 3.5).

Agregá `&storage=1`:

```
https://<tu-dominio>/api/diagnostico.php?token=<ese-valor>&storage=1
```

Sube un PNG de 1×1 con nombre al azar, lo lee por la URL pública **sin ninguna cabecera de
autenticación** (como lo haría el navegador de un cliente), comprueba que los bytes son idénticos,
lo borra y confirma que quedó borrado. Usa el mismo `peticionSupabase()` que el panel, así que
prueba el código real, no una copia. Tiene que responder:

```json
"storage_prueba": {
  "clave_es_jwt": false,
  "subida_http": 200,
  "subida_ok": true,
  "lectura_publica_http": 200,
  "lectura_publica_ok": true,
  "borrado_http": 200,
  "borrado_ok": true,
  "quedo_limpio": true
}
```

`clave_es_jwt: false` es lo correcto con una clave `sb_secret_`; con la `service_role` heredada sería
`true`. Cómo leer un fallo, sin que haga falta ver ningún secreto:

| Síntoma | Qué significa |
|---|---|
| `storage_prueba: null` | no pediste `&storage=1`, o `MW_ALMACEN_IMAGENES` no es `supabase`, o falta `SUPABASE_URL` / la clave |
| `subida_http` 401 o 403 | la clave no autentica: valor incorrecto, o la cabecera no es la que Storage espera |
| `subida_http` 404 | el bucket de `SUPABASE_BUCKET` no existe |
| `subida_http` 413 | el objeto excede el límite del bucket |
| `lectura_publica_ok: false` con subida `ok` | el bucket **no** es público: marcalo como *Public bucket* (3.3) |
| `quedo_limpio: false` | quedó el objeto de prueba; borralo a mano desde Storage |

## 6. Verificación después de desplegar

Recorré esto en el sitio publicado, en este orden:

1. **Catálogo** — la portada muestra los 12 productos con sus fotos.
2. **Búsqueda** — buscá `camara` (sin tilde) y `NOTEBOOK` (en mayúsculas). Tienen que encontrar
   igual que en local: eso prueba que `unaccent` + `ILIKE` está funcionando.
3. **Registro** — creá una cuenta nueva.
4. **Login y sesión** — cerrá sesión, volvé a entrar, recargá varias veces. Si la sesión se cayera
   sola, revisá `MW_ALMACEN_SESIONES=bd` y que exista la tabla `sesiones`.
5. **Carrito** — agregá productos sin sesión, iniciá sesión y comprobá que el carrito se fusionó.
6. **Checkout** — completá un pedido y verificá en Supabase que se creó en `pedidos` y
   `detalle_pedido`, y que bajó el `stock` del producto.
7. **Mis Compras** — el pedido aparece con su estado.
8. **Panel** — entrá con el administrador. Dashboard, Pedidos, Productos, Inventario, Categorías,
   Usuarios y Estadísticas.
9. **Permisos** — con la cuenta de cliente, abrí `/admin/index.html`: tiene que decir que no tenés
   permiso, y `/api/admin/estadisticas.php` responder 403.
10. **Imágenes** — subí una desde Productos. Tiene que quedar en el bucket y verse en la tienda.
11. **Estadísticas** — creá un pedido y marcalo como pagado; comprobá que suma al **día de hoy** en
    el gráfico. Si apareciera en el día siguiente, revisá `MW_TZ`.
12. **Responsive** — abrí la tienda y el panel desde el teléfono.

---

## 7. Probar PostgreSQL desde XAMPP, antes de desplegar

Se puede ejecutar el código **contra Supabase desde tu máquina**, que es la forma más seria de
validar el camino PostgreSQL sin tocar producción:

1. En `C:\xampp\php\php.ini`, descomentá estas dos líneas (quitales el `;` del principio):
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```
   Reiniciá Apache.
2. Creá un **segundo proyecto de Supabase, de pruebas** (no uses el de producción) y aplicale las
   tres migraciones del punto 3.2, en orden.
3. Levantá el servidor apuntando a esa base:
   ```bash
   MW_DB_DRIVER=pgsql MW_DB_HOST=... MW_DB_PORT=6543 MW_DB_NAME=postgres \
   MW_DB_USER=postgres.<ref> MW_DB_PASS=... \
   php -S 127.0.0.1:8099 -t .
   ```
4. Recorré a mano la lista del punto 6 contra `http://127.0.0.1:8099`.

> Las suites automáticas de `tests/` **no** sirven para esto: `run_all.sh` importa el esquema MySQL
> y exige que el nombre de la base termine en `_test`, así que están atadas a MySQL. Adaptarlas a
> PostgreSQL es trabajo aparte, todavía pendiente.

---

## 8. La versión local sigue igual

No hay que cambiar nada en XAMPP. `api/config/config.php` manda en local y los valores nuevos tienen
los mismos valores por defecto de siempre.

Lo único que hay que correr **una vez**, si ya tenías la base `mercadoweb` creada de antes:

```bash
mysql -u root mercadoweb < sql/migraciones/001_sesiones_e_intentos.sql
```

Agrega las tablas `sesiones` e `intentos_login`. No borra, no vacía y no modifica nada existente.
En una instalación nueva desde `sql/mercadoweb.sql` ya vienen incluidas.

Al aplicarla se cierran las sesiones que tuvieras abiertas en el navegador (antes estaban en
archivos y ahora se leen de la base): hay que volver a entrar, nada más.

---

## 9. Límites conocidos

- **El runtime de PHP es comunitario**, no oficial de Vercel. Sus correcciones en GitHub a veces
  tardan en llegar a npm, por eso `vercel.json` fija `vercel-php@0.9.0` en lugar de usar la última.
- **Sin bloqueo de sesión.** El manejador de archivos de PHP serializa las peticiones de una misma
  sesión; el de base de datos no. Para esta aplicación es inofensivo (en la sesión solo hay id,
  nombre, email, rol y token CSRF, que no cambian entre peticiones).
- **Las imágenes del repositorio siguen sirviéndose desde Vercel** y solo las nuevas van a Storage.
  Conviven sin problema: el panel ofrece las dos.
- **El límite de intentos de login no frena a quien rote muchas IP**, igual que antes.
- **Memoria ajustada**: el runtime trabaja con alrededor de 90 MB por invocación. Alcanza de sobra
  para estos endpoints, pero no subas el límite de 2 MB por imagen sin medirlo.
