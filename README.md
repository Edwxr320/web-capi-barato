# MercadoWeb — Capi-Barato

Tienda en línea académica de tecnología (celulares, notebooks y televisores). **Capi-Barato** es la marca visible
y **MercadoWeb** el nombre técnico del proyecto. Catálogo desde MySQL, cuentas de cliente y administrador,
carrito de invitado y persistente, checkout transaccional con control de inventario, historial de compras y un
panel administrativo completo.

Stack: HTML5, CSS3 y JavaScript sin frameworks · PHP 8 sin frameworks · PDO + MySQL/MariaDB ·
Bootstrap Icons 1.11.1 · fuente Figtree. Sin gestores de paquetes ni dependencias de servidor adicionales.

## 1. Requisitos
| Componente | Versión |
|---|---|
| PHP | 8.0 o superior (probado con **8.3.6**), con `pdo_mysql` y `mbstring` |
| MySQL / MariaDB | MySQL 8.0.16+ o MariaDB 10.2.1+ (probado con **MariaDB 10.11.14**) |
| Navegador | Cualquiera actual; el sitio se sirve por HTTP (no con `file://`) |

## 2. Instalación paso a paso
1. Copiá la carpeta `Mercadoweb` donde vayas a servirla (por ejemplo `C:\xampp\htdocs\` con XAMPP).
2. Creá la base importando el script (crea la base `mercadoweb` con 8 tablas, 3 categorías, 12 productos y el admin):
   ```bash
   mysql -u root -p < sql/mercadoweb.sql
   ```
   (En XAMPP también: phpMyAdmin → Importar → `sql/mercadoweb.sql`.)
3. Configurá la conexión:
   ```bash
   cp api/config/config.example.php api/config/config.php
   ```
   y completá `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. `config.php` **no** está en el ZIP ni en el repositorio
   (`.gitignore`); no subas tus credenciales.
4. Iniciá el sitio:
   - Servidor de PHP, desde la carpeta del proyecto: `php -S localhost:8080` → <http://localhost:8080/index.html>
   - XAMPP / Apache: <http://localhost/Mercadoweb/index.html>
5. Panel administrativo: <http://localhost:8080/admin/index.html>

Guía detallada (Windows, Linux y macOS): [`docs/INSTALACION.md`](docs/INSTALACION.md).

### Configuración opcional (`api/config/config.php`)
| Constante | Por defecto | Uso |
|---|---|---|
| `STOCK_BAJO_UMBRAL` | 5 | Un producto tiene "stock bajo" con stock ≤ este valor |
| `LOGIN_MAX_INTENTOS` / `LOGIN_VENTANA_SEGUNDOS` | 5 / 900 | Bloqueo temporal tras fallos de login (por correo + IP) |
| `LOGIN_DIR_INTENTOS` | carpeta temporal | Dónde se guardan esos contadores |
| `SESSION_COOKIE_SECURE` | false | Ponelo en `true` si servís por HTTPS |

## 3. Roles y permisos
| Rol | Puede |
|---|---|
| Invitado | Ver el catálogo, buscar, ver detalles y armar un carrito en el navegador (`localStorage`). Para comprar debe iniciar sesión o registrarse. |
| Cliente | Todo lo anterior + carrito guardado en MySQL (se fusiona con el de invitado al ingresar), checkout, Mis Compras y el detalle de **sus** pedidos. |
| Administrador | Además, el panel: dashboard, pedidos y sus estados, productos, inventario, categorías, usuarios y estadísticas. |

La autorización se verifica **en el servidor** en cada petición (rol y estado releídos de MySQL). Ocultar botones
es solo interfaz.

### Credencial de demostración
> **Solo para uso académico — cambiala apenas instales.**
> Administrador: `admin@capibarato.pe` / `CapiBarato2026!` (creado por `sql/mercadoweb.sql`).
> Los clientes se crean desde "Creá tu cuenta". No hay cambio de contraseña desde el sitio todavía (ver §8); para
> cambiar la del admin: `php -r "echo password_hash('NuevaClave', PASSWORD_DEFAULT);"` y actualizá
> `usuarios.password_hash` con ese valor.

## 4. Módulos y flujos
- **Catálogo** (`index.html`): productos activos desde MySQL, menú de categorías (también desde MySQL), búsqueda por
  nombre/categoría/características, detalle con galería. Precios en formato `es-PE` con 2 decimales.
- **Carrito** (`carrito.html`): invitado en `localStorage`; cliente en MySQL; nunca supera el stock. Envío: una sola
  vez por pedido, el mayor costo entre los productos con envío pago; S/. 0.00 si todos son gratis.
- **Login / registro** (`iniciorsesion/`): vuelve al destino permitido (`?next=checkout`, `mis-compras` o `admin`; cualquier
  otro valor lleva al inicio — sin open redirect). Al ingresar fusiona el carrito de invitado.
- **Checkout** (`checkout.html`): requiere sesión. El servidor recalcula todo y crea el pedido en una transacción.
- **Mis Compras** (`mis-compras.html`): historial y detalle con el precio pagado (no cambia si después cambia el precio).
- **Panel** (`admin/`): dashboard con indicadores reales; pedidos (`pendiente → pagado → enviado → completado`);
  productos (CRUD con imágenes y características; con ventas se desactiva en vez de borrarse); inventario (alertas y
  ajustes); categorías; usuarios (activar/desactivar, rol); estadísticas.

## 5. API
Documentación completa de cada endpoint, parámetros, validaciones y códigos: [`docs/ENDPOINTS.md`](docs/ENDPOINTS.md).

## 6. Pruebas
```bash
bash tests/run_all.sh      # TODAS las suites, contra la base aislada "mercadoweb_test"
```
Las pruebas **nunca** tocan la base `mercadoweb`: usan una base terminada en `_test`, levantan su propio servidor
PHP y se detienen ante cualquier error de preparación. Detalle, requisitos (Playwright para las de navegador) y qué
datos modifica cada suite: [`tests/README.md`](tests/README.md). Resultado de la última ejecución real:
[`docs/MATRIZ-PRUEBAS.md`](docs/MATRIZ-PRUEBAS.md) y `tests/resultados/`.

## 7. Seguridad aplicada
Consultas preparadas en toda la API · contraseñas con `password_hash` · sesión HttpOnly/SameSite=Lax/modo estricto
y regenerada al ingresar · token CSRF + verificación de `Origin` + JSON obligatorio en todo POST · límite de intentos
de login · revalidación de rol y estado en cada petición · pedidos ajenos responden 404 · precios y totales
calculados en el servidor · escape de todo texto en la interfaz (el panel no usa `innerHTML`) · subida de imágenes
validada por contenido · `config.php` protegido y fuera del repositorio.

## 8. Limitaciones conocidas y decisiones pendientes
Decisiones que corresponden al responsable del proyecto (no se resolvieron por inferencia):
1. **B-03 — precio del Moto G13.** Hoy: S/. 499.00 con 14 % = **S/. 429.14**. En la Fase 4 el S/. 499.00 se usaba de
   otra manera. Se mantuvieron los datos actuales.
2. **Datos dudosos del catálogo:** "Batería interna 54 GB" (Moto G13), "Memoria interna de 54 GB" (Moto E22) y el
   nombre "Moto E22 64gb 4gb Ram Negro **Test**": sin cambios.
3. **Cancelación de pedidos:** el estado `cancelado` existe, pero no está definido desde qué estados se puede cancelar
   ni si se devuelve el stock. El panel y la API no permiten cancelar hasta que se defina.
4. **Elementos heredados sin función definida:** casilla "Recordarme", enlace "¿Olvidaste tu contraseña?",
   "Ver más productos de …", "Vendido por …", "Devolución Gratis", "Compra protegida", "Mercado Puntos — Sumá 900 puntos"
   (enlaces `#`). Se dejaron como estaban.
5. **Alta de usuarios y cambio de contraseña desde el panel:** no definidos.

Limitaciones técnicas:
- Sin pasarela de pagos (por diseño): `pagado` lo registra un administrador.
- El inventario no guarda historial de movimientos (no se agregó tabla, por decisión del proyecto).
- El límite de intentos de login es por correo + IP y se guarda en archivos temporales del servidor; no frena a quien
  rote muchas IP.
- Las imágenes subidas que dejan de usarse no se borran del disco automáticamente.
- Bootstrap Icons y Figtree se cargan desde CDN: sin Internet el sitio funciona, pero sin iconos ni esa fuente.
- `php -S` sirve para desarrollo y pruebas; para uso real conviene Apache/Nginx con PHP-FPM y HTTPS
  (`SESSION_COOKIE_SECURE = true`).

## 9. Documentación
| Archivo | Contenido |
|---|---|
| `docs/CHANGELOG.md` | Qué se hizo en cada bloque, defectos encontrados y corregidos, y qué falta |
| `docs/ARCHIVOS.md` | Inventario de archivos creados y modificados |
| `docs/ENDPOINTS.md` | Referencia de la API |
| `docs/INSTALACION.md` | Instalación detallada |
| `docs/MATRIZ-PRUEBAS.md` | Matriz de pruebas con el resultado real de cada aserción |
| `docs/evidencia/` | Salidas de la línea base y de las pruebas nuevas contra el código original |
| `tests/resultados/` | Log de la última ejecución, log del servidor y capturas de pantalla |
