# API de MercadoWeb / Capi-Barato

Todas las respuestas son JSON (`Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`).
Los errores tienen la forma `{"error": "mensaje"}`. Un error interno inesperado responde **500** con un mensaje
genérico (el detalle queda solo en el log del servidor).

## Reglas comunes
| Tema | Regla |
|---|---|
| Cuerpo de los POST | JSON con `Content-Type: application/json`. Otro tipo → **415**. JSON inválido → **400**. (Excepción: subida de imágenes, `multipart/form-data`.) |
| Sesión | Cookie `PHPSESSID` (HttpOnly, SameSite=Lax, modo estricto). El rol y el estado de la cuenta se releen de MySQL en cada petición. |
| CSRF | Todo POST con sesión exige el encabezado `X-CSRF-Token` con el token de la sesión (lo entrega la cookie legible `mw_csrf`, `sesion.php`, `login.php` y `registro.php`). Sin token o con token incorrecto → **403**. Si llega `Origin` de otro sitio → **403**. |
| Códigos | 200 OK · 201 creado · 400 dato inválido · 401 sin sesión · 403 sin permiso / CSRF · 404 no existe (o no es tuyo) · 405 método · 409 conflicto de negocio (stock, estado, duplicado) · 415 tipo de contenido · 429 demasiados intentos · 500 error interno |
| Montos | Números con 2 decimales. El precio final = `precio_original × (1 − descuento/100)`, redondeado a 2 decimales, siempre calculado en el servidor. |

## Públicos (sin sesión)
| Método y ruta | Parámetros | Respuesta |
|---|---|---|
| `GET /api/categorias/listar.php` | — | `{categorias:[{id_categoria,nombre,slug}]}` |
| `GET /api/productos/listar.php` | `?categoria=slug` (opcional; inexistente → 404) | `{productos:[…]}` solo activos |
| `GET /api/productos/detalle.php` | `?id=N` | `{producto:{…, imagenes:[], caracteristicas:[]}}`; inactivo o inexistente → 404 |
| `GET /api/productos/buscar.php` | `?q=texto` (1–100 caracteres) | busca en nombre, categoría y características |

## Autenticación
| Método y ruta | Cuerpo | Notas |
|---|---|---|
| `POST /api/auth/registro.php` | `{nombre (2–100), email, password (6–200)}` | 201 + inicia sesión como `cliente`. Correo repetido → 409. Devuelve `csrf_token`. |
| `POST /api/auth/login.php` | `{email, password}` | Mensaje idéntico si falla el correo o la clave (401). Cuenta inactiva → 403. 5 fallos en 15 min (mismo correo + IP) → **429** con `Retry-After`. Devuelve `csrf_token`. |
| `POST /api/auth/logout.php` | — | Idempotente. Con sesión exige CSRF. GET → 405. |
| `GET /api/auth/sesion.php` | — | `{autenticado:false}` o `{autenticado:true, usuario:{id_usuario,nombre,email,rol}, csrf_token}` |

## Carrito (sesión de cualquier rol + CSRF en POST)
| Método y ruta | Cuerpo | Notas |
|---|---|---|
| `GET /api/carrito/listar.php` | — | `{items:[{id_item,cantidad,producto:{…}}], resumen:{subtotal,costo_envio,total}}` |
| `POST /api/carrito/agregar.php` | `{id_producto, cantidad?=1}` | Suma a lo que ya había; nunca supera el stock (409). |
| `POST /api/carrito/actualizar.php` | `{id_producto, cantidad}` | Fija la cantidad (entero ≥ 1). Producto que no está en tu carrito → 404. |
| `POST /api/carrito/eliminar.php` | `{id_producto}` | 404 si no estaba. |
| `POST /api/carrito/fusionar.php` | `{items:[{id_producto,cantidad}]}` | Fusión del carrito de invitado tras el login: suma sin duplicar, limita al stock, ignora inválidos/inactivos (los informa). Transaccional. |

**Envío:** se cobra una sola vez por pedido: el mayor costo entre los productos con envío pago; si todos son gratis, 0.

## Pedidos del cliente (sesión + CSRF en POST)
| Método y ruta | Cuerpo / parámetros | Notas |
|---|---|---|
| `POST /api/pedidos/crear.php` | `{direccion_envio}` (texto 5–255) | El carrito se lee de MySQL; precios, envío y total los calcula el servidor; cualquier `id_usuario`, `estado`, `total`, `precio`, `items` enviados se ignoran. Una transacción con `FOR UPDATE` (orden fijo por producto): pedido + detalle con precio histórico + descuento de stock + vaciado del carrito, o nada. Carrito vacío → 400; producto inactivo o sin stock → 409 (nombra el producto); deadlock → 409 "intentá de nuevo". 201 → `{pedido:{id_pedido,estado:"pendiente",subtotal,costo_envio,total}}` |
| `GET /api/pedidos/listar.php` | — | Mis pedidos, del más reciente al más antiguo. |
| `GET /api/pedidos/detalle.php` | `?id=N` | Solo pedidos propios: uno ajeno responde **404 idéntico** al de uno inexistente. |

## Administración (rol `admin` verificado en el servidor + CSRF en POST)
Sin sesión → 401. Con sesión de cliente → 403. Todos los listados aceptan los filtros indicados.

### `/api/admin/inventario.php` (Bloque 4)
- `GET ?filtro=todos|bajo|sin_stock&q=&categoria=` → `{umbral, resumen:{productos,activos,stock_bajo,sin_stock,unidades_totales}, resumen_categorias:[…], productos:[{…, stock, stock_bajo, sin_stock}]}`.
  `resumen_categorias` = una fila por categoría con `{categoria, slug, productos, activos, unidades, stock_bajo, sin_stock}`,
  siempre sobre el catálogo completo (no se ve afectada por `filtro`, `q` ni `categoria`).
  Stock bajo = `stock <= STOCK_BAJO_UMBRAL` (config.php, 5 por defecto), incluye 0. Las alertas cuentan solo productos activos.
- `POST {id_producto, operacion: "fijar"|"sumar"|"restar", cantidad}` — fijar 0…1.000.000; sumar/restar 1…1.000.000;
  nunca negativo (409); transacción con `FOR UPDATE`. Responde `{producto:{stock_anterior, stock, stock_bajo, sin_stock}}`.

### `/api/admin/productos.php`
- `GET ?q=&categoria=&estado=todos|activo|inactivo` · `GET ?id=N` (con descripción, imágenes y características).
- `POST {accion:"crear"|"actualizar", [id_producto], nombre (3–150), id_categoria, descripcion?, precio_original (0.01–99.999.999,99; 2 decimales), descuento_porcentaje? (0/vacío = sin descuento, 1–99), stock (0–1.000.000), proveedor?, envio_gratis (bool), costo_envio (obligatorio si no es gratis), estado, imagenes:[1–10 rutas "img/…" existentes], caracteristicas:[0–30 textos ≤255]}`.
- `POST {accion:"estado", id_producto, estado}`.
- `POST {accion:"eliminar", id_producto}` → con ventas: **no se borra**, se desactiva (`resultado:"desactivado"`); sin ventas: se borra (`resultado:"eliminado"`).

### `/api/admin/imagenes.php`
- `GET` → `{imagenes:[rutas dentro de img/]}`.
- `POST multipart/form-data` campo `imagen` → PNG/JPG/WebP reales (se analiza el contenido), ≤ 2 MB, 50–4000 px. Nombre aleatorio generado por el servidor en `img/productos/`. 201 → `{ruta}`.

### `/api/admin/categorias.php`
- `GET` → categorías con cantidad de productos (total y activos).
- `POST {accion:"crear", nombre (2–50), slug?}` (slug: minúsculas, números y guiones; se genera del nombre; "todos" reservado; nombre y slug únicos → 409).
- `POST {accion:"actualizar", id_categoria, nombre, slug}` · `POST {accion:"eliminar", id_categoria}` → con productos: **409** (FK `ON DELETE RESTRICT`).

### `/api/admin/usuarios.php`
- `GET ?q=&rol=&estado=` (nunca devuelve `password_hash`) · `GET ?id=N` (con sus pedidos).
- `POST {accion:"estado", id_usuario, estado}` · `POST {accion:"rol", id_usuario, rol}`.
  No se puede cambiar el propio estado/rol (409) ni dejar el sistema sin administradores activos (409).
  Una cuenta desactivada pierde la sesión en su siguiente petición.

### `/api/admin/pedidos.php`
- `GET ?estado=&q=` (`q` = `#id`, nombre o correo) → `{pedidos:[…, estados_siguientes], por_estado}` · `GET ?id=N` → detalle con cliente, dirección y líneas.
- `POST {accion:"estado", id_pedido, estado}` — solo `pendiente → pagado → enviado → completado`, de a un paso
  (`pagado` = pago registrado manualmente). Cualquier otra transición → 409. **`cancelado` no está habilitado**
  (reglas pendientes de definición) → 409. Con `FOR UPDATE`: dos cambios simultáneos no se pisan.

### `/api/admin/estadisticas.php`
- `GET ?dias=1..90&periodo=dia|semana|mes` (default `dias=14`, `periodo=dia`) → `{indicadores, umbral_stock_bajo, alertas_stock, pedidos_por_estado, ventas_por_categoria, productos_mas_vendidos, serie_diaria, grafico, ultimos_pedidos}`.
  Ventas confirmadas = pedidos `pagado`/`enviado`/`completado`. Los pendientes se informan aparte; los cancelados no suman.
- `dias` controla solo `serie_diaria` (una entrada por día). `periodo` controla solo `grafico`, que es la serie del
  dashboard y tiene su propia ventana: `dia` → 14 días, `semana` → 8 semanas (de lunes a domingo), `mes` → 6 meses.
  `grafico` = `{periodo, etiqueta, desde, hasta, puntos:[{inicio, fin, pedidos, pedidos_confirmados, ventas_confirmadas}],
  total:{…}, anterior:{desde, hasta, …}, variacion:{ventas, pedidos_confirmados}}`.
  `anterior` es la ventana inmediatamente previa, del mismo tamaño. `variacion` es el % contra esa ventana y vale
  `null` cuando el período anterior fue 0 (no se informa un «+100 %» inventado). Un `periodo` distinto de los tres → 400.
