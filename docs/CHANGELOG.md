# Changelog — MercadoWeb / Capi-Barato (Fase 5)

Punto de partida: `Mercadoweb-Fase5-Bloque3-Checkout-Pedidos.zip` (Bloque 2 aprobado; Bloque 3 sin aprobar).
Se conservó una copia intacta de esa versión antes de tocar nada. El `HANDOFF-MercadoWeb-CapiBarato.md` y el
ZIP `…-WIP-NO-VERIFICADO.zip` **no estuvieron disponibles** durante este trabajo: no se afirma haberlos revisado
y no se infirió su contenido. Las reglas usadas son las del prompt de finalización y las que ya documenta el código.

---

## Bloque 3 — Checkout y pedidos (verificación y cierre)

### Resultado de la verificación
- Línea base (código original, sin cambios): `test_auth` 24/24, `test_bloque1` 25/25, `test_bloque2` 31/31,
  `test_bloque3` 46/46, `test_concurrencia` correcta; `test_navegador_bloque3` **se interrumpía** en el paso 10
  (`Page.goto … is interrupted by another navigation`), así que el flujo de navegador no llegaba a comprobar el
  retorno con `next=mis-compras` ni el open redirect. Evidencia: `docs/evidencia/01-baseline-run_all-original.txt` y `docs/evidencia/02-pruebas-nuevas-contra-codigo-original.txt` (las pruebas nuevas corridas contra el código original: 21 fallas).
- Tras las correcciones: 6 suites, **233 aserciones aprobadas, 0 fallidas** (`docs/MATRIZ-PRUEBAS-BLOQUE3.md`).

### Defectos encontrados y corregidos
| Id | Defecto | Cómo se demostró | Corrección |
|---|---|---|---|
| B3-01 | `tests/run_all.sh` hacía `DROP DATABASE mercadoweb` (la base de uso normal) y seguía aunque el reinicio o la importación fallaran. | Lectura del script. | Las pruebas solo corren contra una base cuyo nombre termina en `_test` (por defecto `mercadoweb_test`); el script se niega a correr si no; se detiene con error si falla el `DROP`, la importación o el conteo (8 tablas, 12 productos, 3 categorías). |
| B3-02 | Nombre de base incoherente: el servidor usaba `config.php` y los scripts/SQL tenían `mercadoweb` fijo. | Lectura de `run_all.sh`, `lib.sh`, `test_concurrencia.py`. | `run_all.sh` levanta su propio servidor PHP con `MERCADOWEB_DB_PRUEBAS=<base>_test`; `db.php` acepta esa variable **solo** si el nombre termina en `_test`; el SQL se importa reescribiendo únicamente `CREATE DATABASE`/`USE`; un registro de control verifica que el servidor escribe en la base de pruebas. |
| B3-03 | **Falso positivo en concurrencia**: `php -S` atiende una petición a la vez si no se configura, así que las solicitudes "simultáneas" se procesaban en fila. | Nueva prueba "0) paralelismo" con un trigger que demora 1 s cada `INSERT` en `pedidos`. | `PHP_CLI_SERVER_WORKERS=8`. La prueba confirma 2 checkouts en 1,02 s (en paralelo). Con paralelismo real, las 4 situaciones de carrera × 10 rondas siguen correctas. |
| B3-04 | La "prueba de rollback" no probaba el rollback: el caso lo frena la validación previa, **antes** de cualquier escritura. | Lectura de `crear.php` y de la prueba. | Se reetiquetó como validación previa y se agregó un **rollback real**: triggers temporales que hacen fallar el `INSERT` del detalle, el `UPDATE` de stock del 2.º producto o el `DELETE` del carrito, cuando ya hay escrituras hechas. En los tres casos la base queda idéntica (pedidos, detalle, stock de todos los productos, carritos). |
| B3-05 | Ante un error de MySQL en mitad del checkout, la excepción salía como error fatal de PHP (sin JSON). | Prueba 6a ejecutada contra el código original: HTTP 500 con cuerpo que no es JSON. (El rollback en sí ya funcionaba: la base quedaba idéntica.) | Manejador global en `respuesta.php`: JSON 500 genérico, sin texto de SQL; el detalle va solo al log del servidor. |
| B3-06 | Posible deadlock entre dos checkouts con los mismos productos (bloqueo en distinto orden) y, si ocurría, respuesta 500. | Escenario C de concurrencia. | `ORDER BY ci.id_producto` antes del `FOR UPDATE` (orden de bloqueo fijo) y deadlock / lock-wait (1213/1205) responden 409 "Intentá confirmar de nuevo". |
| B3-07 | `direccion_envio` enviada como lista u otro tipo se convertía a la fuerza (`"Array"`) y podía crear el pedido. | Prueba 1 contra el código original: HTTP 201 y pedido guardado con `direccion_envio = 'Array'`. | Se exige texto; si no, 400. |
| B3-08 | "Cerrar sesión" enviaba **dos** POST de logout en `index.html` y `carrito.html` (`actualizarEnlaceCuenta()` se llamaba dos veces). | Prueba de navegador 10 (cuenta los POST). | Llamada duplicada eliminada y la función ahora es idempotente. |
| B3-09 | Orden del historial y del detalle no determinista con pedidos del mismo segundo. | Contra el código original falló "B ve sus pedidos del más reciente al más antiguo" (4 pedidos en el mismo segundo). | `ORDER BY fecha_pedido DESC, id_pedido DESC` y `ORDER BY dp.id_detalle`. |
| B3-10 | Si la sesión vencía estando en el checkout, el error 401 se mostraba como texto en vez de pedir login. | Revisión de `checkout.js`. | 401 → `iniciarsesion.html?next=checkout` (el carrito sigue en MySQL). |
| B3-11 | Campo de dirección sin nombre accesible ni límite en el navegador. | Revisión de `checkout.html`. | `aria-label`, `maxlength="255"` y `role="alert"` en el mensaje de error. |
| B3-12 | Prueba de navegador interrumpida (ver arriba) y sin verificar el retorno real a Mis Compras. | Línea base. | Navegación sin esperar `load` en páginas que redirigen por JS; verificación de `next=mis-compras` completa; 6 variantes de open redirect. |

### Bug B-02 (confirmación)
Verificado en Chromium real con un criterio estricto (el elemento y todos sus ancestros sin `display:none`,
`visibility:hidden` ni tamaño 0): tras confirmar, el bloque formulario + resumen, el campo de dirección, el botón
"Confirmar pedido" y el resumen **no se ven**, y la confirmación sí. Capturas: `b3-02-checkout.png`, `b3-04-confirmacion.png`.

### Pruebas agregadas
- `test_bloque3.sh` (46 → 91 aserciones): tipos inválidos y límites de la dirección (vacía, espacios, 2 y 256
  caracteres, lista, número, cuerpo no JSON), consistencia completa en MySQL, precio histórico con restauración
  del valor real, rollback real (3 puntos de falla), respuesta idéntica para pedido ajeno e inexistente, cookie de
  sesión cerrada, inyección SQL y XSS en la dirección guardadas como texto literal, manipulación de
  `id_usuario`/`estado`/`total`/`precio`/`items`, e integridad global de todos los pedidos.
- `test_concurrencia.py`: paralelismo real comprobado; 4 escenarios × 10 rondas (2×2 sobre stock 2; 3×1 sobre
  stock 2; dos carritos con los mismos productos en orden inverso; 6×1 sobre stock 3).
- `test_navegador_bloque3.py` (→ 56): carrito de invitado en `localStorage`, fusión tras registro, B-02 estricto,
  un solo logout, `next=mis-compras`, 6 open redirects, errores de consola y respuestas 5xx.
- `tests/navegador_comun.py`, `tests/generar_matriz.py`.

### Sin cambios (a propósito)
Esquema (8 tablas), reglas de precio y envío, datos semilla, identidad visual. B-03 (precio del Moto G13) se
mantuvo con los datos actuales: S/. 429.14 = 499.00 − 14 %, a la espera de tu decisión.

### Limitaciones que quedan del Bloque 3
- La regla de cancelación de pedidos no existe todavía (corresponde a la decisión pendiente sobre `cancelado`).
- En el carrito de un usuario autenticado, un producto desactivado se sigue listando hasta el checkout, que lo
  rechaza con un mensaje que lo nombra (comportamiento heredado del Bloque 2; no se cambió).

---

## Base de seguridad (adelantada de la Etapa 4 para que los Bloques 4 y 5 nacieran protegidos)
- **CSRF** en todo POST con sesión: token sincronizado (sesión + cookie legible `mw_csrf` + encabezado
  `X-CSRF-Token`), verificación de `Origin` y `application/json` obligatorio (415 si no). El frontend lo envía solo
  (`apiPost`) y, si el token venció, pide uno nuevo y reintenta una vez.
- **Límite de intentos de login**: 5 fallos en 15 min por correo + IP → 429 con `Retry-After` (sin tablas nuevas;
  configurable en `config.php`).
- **Revalidación de la cuenta en cada petición**: si un admin desactiva una cuenta o le cambia el rol, rige en la
  petición siguiente (antes la sesión conservaba el rol hasta cerrar sesión).
- `session.use_strict_mode`, logout solo por POST, `login`/`registro` validan tipos y largos, registro simultáneo con
  el mismo correo → 409 (antes la violación del UNIQUE terminaba en un 500; detectado por revisión de código, la carrera no
  se forzó en una prueba), encabezados `nosniff` y `no-store` en la API.
- Pruebas: `test_auth.sh` 24 → 70 aserciones.

## Bloque 4 — Inventario
- `api/admin/inventario.php`: consulta de existencias (filtros todos / stock bajo / sin stock, búsqueda y categoría),
  resumen de alertas y ajuste de stock (fijar / sumar / restar) con validación en el servidor, sin negativos, con
  transacción y `FOR UPDATE` (un ajuste y una compra simultáneos no se pisan).
- Umbral de stock bajo **5**, configurable en `config.php` (`STOCK_BAJO_UMBRAL`) sin tocar el código; stock bajo =
  stock ≤ umbral (incluye 0). Las alertas cuentan solo productos activos.
- Sin tabla de movimientos (decisión del proyecto): no hay historial de ajustes.
- Interfaz: sección **Inventario** del panel (indicadores, filtros, ajuste por fila con mensajes de error en la fila).
- Pruebas: `test_inventario.sh` (74 aserciones: permisos —incluido un cliente con token CSRF válido—, consulta,
  alertas, ajustes verificados en MySQL, 17 validaciones y límites, inactivos, efecto en catálogo y checkout,
  5 ajustes simultáneos, umbral configurable probado con un segundo servidor con `STOCK_BAJO_UMBRAL=10`).

## Bloque 5 — Panel administrativo
Los seis stubs que respondían 501 se reemplazaron por lógica real; se agregó `api/admin/imagenes.php`.
- **Dashboard y estadísticas** (`estadisticas.php`): ventas confirmadas (pagado/enviado/completado), pendientes
  aparte, ticket promedio, clientes, productos, alertas, pedidos por estado, ventas por categoría (precio histórico),
  productos más vendidos, serie diaria 1–90 días, últimos pedidos. Cada número de la prueba se compara contra una
  consulta SQL independiente.
- **Productos**: CRUD completo (categoría, precio, descuento, stock, proveedor, envío, estado, descripción, imágenes
  ordenables, características). Con ventas → se **desactiva** en lugar de borrarse. Editar el precio no altera pedidos
  anteriores (probado).
- **Imágenes**: subida PNG/JPG/WebP validada por contenido real, ≤ 2 MB, 50–4000 px, nombre aleatorio; `.htaccess`
  que impide ejecutar scripts en `img/productos/`. Rechaza PHP disfrazado de PNG, SVG, rutas con `..` y URLs externas.
- **Categorías**: alta/edición/baja respetando `UNIQUE(slug)` y la FK `ON DELETE RESTRICT` (con productos → 409).
  El menú de categorías de la tienda ahora se carga desde MySQL (antes estaba fijo en el HTML, así que una categoría
  nueva nunca aparecía); los botones conservan exactamente el mismo marcado y estilo.
- **Usuarios**: listado con filtros (nunca expone `password_hash`), activar/desactivar y cambiar rol; nadie puede
  cambiarse a sí mismo y siempre queda un admin activo.
- **Pedidos**: listado con conteo por estado y búsqueda, detalle, transiciones solo `pendiente → pagado → enviado →
  completado` de a un paso. **Cancelación no habilitada** (reglas pendientes). Cambios simultáneos: uno gana, el resto 409.
- Interfaz `admin/` (HTML + CSS + JS vanilla, Bootstrap Icons, misma paleta, Figtree, encabezado amarillo con el
  logo). Construida **sin `innerHTML`**: todo texto entra como nodo de texto. Diálogos nativos `<dialog>` con botón ×
  de 44 px y `aria-label`, Escape y devolución del foco; confirmaciones propias (sin `alert`/`confirm`).
- Acceso: `?next=admin` en el login; un cliente ve "No tenés permiso" y la API responde 403 aunque fuerce la petición;
  enlace "Panel" en la tienda solo para administradores.
- Pruebas: `test_admin.sh` (193 aserciones) y `test_navegador_admin.py` (47, en Chromium).

## Etapa 4 — Seguridad, responsive y UX (tienda)
| Id | Problema | Corrección |
|---|---|---|
| E4-01 | `main.js` y `carrito.js` insertaban nombre, proveedor e imagen con `innerHTML` **sin escapar** (XSS almacenado posible al existir el CRUD de productos). | `escaparHtml()` propio en cada página (sin colisiones de nombres entre scripts). Probado en navegador con un producto cuyo nombre, proveedor y característica contienen HTML/JS: nada se ejecuta en catálogo, detalle, carrito, checkout ni panel. |
| E4-02 | `mostrarError()` metía el mensaje con `innerHTML`. | Texto con `textContent`. |
| E4-03 | Bug: tras recrear el mensaje de "sin resultados", `main.js` seguía apuntando a un `<strong>` viejo y el término buscado no aparecía. | Se busca el nodo en cada uso. |
| E4-04 | `alert()` en el detalle de producto y de pedido. | Mensajes en la página. |
| E4-05 | Tarjetas de producto, pedidos, miniaturas y botones × no se podían usar con teclado. | `tabindex`, `role="button"`, Enter/Espacio, Escape para cerrar, el foco va al × y vuelve al elemento de origen. |
| E4-06 | Botones × de 34 px. | 44 px. |
| E4-07 | Ids duplicados en el carrito (tres botones con el mismo `id`). | `data-id`. Botón "−" deshabilitado con cantidad 1 (antes no hacía nada). |
| E4-08 | Importes partidos en dos líneas ("S/." arriba, número abajo) en el resumen del checkout y en Mis Compras; textos del encabezado partidos. | `white-space: nowrap` en importes y enlaces del encabezado. |
| E4-09 | Objetivos táctiles de 16–23 px (enlaces del encabezado, "Eliminar", categorías, "¿Olvidaste…?"). | Solo padding adicional (sin cambio visual): todos ≥ 24×24 px (WCAG 2.2). Excepción: enlaces dentro de una oración. |
| E4-10 | Foco visible solo en `a`, `button`, `input`. | También en tarjetas, `[role=button]`, `select`, `textarea`. |

Responsive verificado **midiendo el DOM** en Chromium a 375, 768, 1024 y 1400 px en 9 páginas de la tienda y 9 del
panel (`test_responsive.py`, 260 aserciones): sin scroll horizontal, sin elementos fuera de la pantalla, campos
≥ 120 px, objetivos ≥ 24 px, cero errores de JavaScript. Capturas `resp-<ancho>-<página>.png`.

No se cambiaron textos heredados, la paleta, el logo ni la estructura de las páginas.

## Etapa 5 — Documentación y entrega
`README.md`, `docs/ENDPOINTS.md`, `docs/INSTALACION.md`, `docs/ARCHIVOS.md`, `docs/MATRIZ-PRUEBAS.md` (generada del
log real), `tests/README.md`. Regresión completa ejecutada **desde el ZIP descomprimido** en una carpeta limpia,
siguiendo las instrucciones del README.

## Pendiente (requiere tu decisión)
B-03 (Moto G13) · "54 GB" ×2 y el nombre "…Test" · reglas de cancelación · "Recordarme", "¿Olvidaste tu contraseña?"
y demás enlaces `#` heredados · alta de usuarios y cambio de contraseña desde el panel. Ver README §8.
