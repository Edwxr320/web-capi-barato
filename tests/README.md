# Pruebas de MercadoWeb / Capi-Barato

Baterías reales contra PHP + MySQL/MariaDB y Chromium (no son revisiones "a ojo"). Todas **modifican datos**,
por eso se ejecutan **solo** contra una base aislada de pruebas.

## Seguridad de tus datos
- `run_all.sh` usa la base `DB_NAME` (por defecto **`mercadoweb_test`**) y se niega a correr si el nombre no
  termina en `_test`. Cada suite (`lib.sh`, los `.py`) repite ese control.
- Antes de cada suite borra y recrea **solo** esa base, importando `sql/mercadoweb.sql` con el nombre reescrito
  (únicamente las líneas `CREATE DATABASE` y `USE`). La base `mercadoweb` no se lee ni se modifica.
- Levanta su **propio** servidor PHP en `127.0.0.1:8099` con `MERCADOWEB_DB_PRUEBAS=mercadoweb_test`;
  `api/includes/db.php` solo acepta esa variable si el nombre termina en `_test`. No hace falta tocar tu `config.php`
  (solo tiene que existir, con un usuario de MySQL que pueda crear la base de pruebas).
- Se detiene con error si falla el borrado, la importación, el conteo de control (8 tablas, 12 productos,
  3 categorías) o si el servidor no está escribiendo en la base de pruebas. Al terminar la deja limpia.
- Si el puerto 8099 está ocupado, no prueba contra ese servidor ajeno: usá `PUERTO=8100 bash tests/run_all.sh`.

## Requisitos
- PHP 8+ con `pdo_mysql` y `mbstring`, MySQL/MariaDB con el cliente `mysql` en el PATH, `curl` y `python3`.
- `api/config/config.php` creado a partir de `config.example.php`.
- Pruebas de navegador: `pip install playwright` y `python3 -m playwright install chromium`
  (si falta Playwright, esas suites se omiten y lo dice).

## Ejecutar
```bash
bash tests/run_all.sh                    # todo
bash tests/run_all.sh bash               # solo bash + curl (rápido)
bash tests/run_all.sh python             # concurrencia + navegador + responsive
bash tests/run_all.sh test_bloque3       # una suite
bash tests/run_all.sh 2>&1 | tee tests/resultados/run_all.log
python3 tests/generar_matriz.py tests/resultados/run_all.log > docs/MATRIZ-PRUEBAS.md   # matriz desde el log real
```
Variables: `MYSQL_CLI` (default `mysql -u root`; ej. `"mysql -u root -pclave"`), `DB_NAME`, `PUERTO`,
`CAPTURAS` (default `tests/resultados/capturas`), `RONDAS` (concurrencia, default 10), `CHROME_PATH`,
`CDN_LOCAL` (solo en equipos sin Internet: carpeta con copias de Bootstrap Icons 1.11.1 y Figtree para que las
capturas salgan con iconos; ver `navegador_comun.py`).

## Qué cubre cada suite y qué datos modifica (siempre en la base de pruebas)
| Suite | Cubre | Modifica |
|---|---|---|
| `test_auth.sh` | registro, login, mensajes genéricos, SQLi, sesión, roles 401/403, logout real, límite de intentos | registra usuarios |
| `test_bloque1.sh` | catálogo, filtro, detalle, búsqueda, SQLi, endpoints públicos | nada (solo lectura) |
| `test_bloque2.sh` | carrito MySQL: agregar/sumar, cantidades inválidas, stock, carrito ajeno, persistencia, fusión, cálculo de envío | usuarios y carritos |
| `test_bloque3.sh` | checkout y pedidos: validaciones, consistencia SQL, precio histórico, rollback REAL (triggers de falla), envío, historial, pedidos ajenos, SQLi/XSS, manipulación de campos | usuarios, carritos, pedidos, stock; cambia y restaura precio/estado; crea y borra triggers temporales |
| `test_inventario.sh` | Bloque 4: consulta de stock, alertas, actualización (fijar/sumar/restar), límites, permisos, CSRF | stock de productos |
| `test_admin.sh` | Bloque 5: dashboard, CRUD de productos/categorías, usuarios, pedidos y transiciones de estado, permisos, CSRF | todo el catálogo de pruebas |
| `test_concurrencia.py` | paralelismo real del servidor + 4 carreras × 10 rondas | usuarios, stock de 5/6/7, pedidos |
| `test_navegador_bloque3.py` | flujo real tienda → checkout → confirmación (B-02) → Mis Compras; XSS; `next`; open redirect | un cliente y 2 pedidos |
| `test_navegador_admin.py` | panel administrativo en el navegador | catálogo y pedidos |
| `test_responsive.py` | 375, 768, 1024 y 1400 px: desbordes, formularios, carrito y panel | un cliente y un pedido |

Las suites que todavía no existan en una entrega se omiten automáticamente.

## Notas
- Se compara siempre JSON parseado, nunca subcadenas con `grep`. `igual` compara números como números (`50` = `50.0`).
- `PHP_CLI_SERVER_WORKERS=8`: sin varios workers, `php -S` atiende de a una petición y las pruebas de
  concurrencia serían falsas. `test_concurrencia.py` lo comprueba antes de empezar.
- La salida del servidor queda en `tests/resultados/servidor-php.log` (ahí se ven los errores internos simulados).
