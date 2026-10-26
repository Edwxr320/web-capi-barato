# Instalación y puesta en marcha

## 1. Requisitos
| Componente | Versión | Notas |
|---|---|---|
| PHP | 8.0 o superior (probado con 8.3.6) | Extensiones `pdo_mysql` y `mbstring` habilitadas |
| MySQL o MariaDB | MySQL 8.0.16+ o MariaDB 10.2.1+ (probado con MariaDB 10.11.14) | Hace falta soporte de `CHECK` y `InnoDB` |
| Navegador | Cualquiera actual | Sirviendo por HTTP (no abrir los `.html` con `file://`) |
| Internet en el navegador | Para Bootstrap Icons 1.11.1 (jsDelivr) y la fuente Figtree (Google Fonts) | Sin Internet el sitio funciona igual, pero sin iconos ni Figtree |

Solo para las pruebas automáticas: `bash`, `curl`, `python3` y, para las de navegador,
`pip install playwright` + `python3 -m playwright install chromium`.

## 2. Instalar PHP y la base de datos

**Windows (lo más simple: XAMPP o Laragon).** Instalá XAMPP, iniciá *Apache* y *MySQL* desde el panel y copiá la
carpeta `Mercadoweb` dentro de `C:\xampp\htdocs\`. XAMPP ya trae `pdo_mysql` y `mbstring`. Revisá en
`C:\xampp\php\php.ini` que no estén comentadas las líneas `extension=pdo_mysql` y `extension=mbstring`.

**Linux (Debian/Ubuntu).**
```bash
sudo apt install php php-mysql php-mbstring mariadb-server
sudo systemctl start mariadb
```

**macOS (Homebrew).** `brew install php mariadb && brew services start mariadb`

Comprobá las extensiones con `php -m` (deben aparecer `pdo_mysql` y `mbstring`).

## 3. Crear la base de datos
Desde la carpeta del proyecto:
```bash
mysql -u root -p < sql/mercadoweb.sql
```
En XAMPP también podés usar phpMyAdmin → *Importar* → `sql/mercadoweb.sql`. El script crea la base
`mercadoweb` (utf8mb4) con las 8 tablas, las 3 categorías, los 12 productos y el usuario administrador de
demostración. Si la base ya existía con tablas, borrala antes (el script no la sobreescribe a medias).

## 4. Configurar la conexión
```bash
cp api/config/config.example.php api/config/config.php
```
Editá `api/config/config.php` con tus datos (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Ese archivo **no se
sube al repositorio** (está en `.gitignore`) y **no viene en los ZIP**: cada instalación crea el suyo.
`api/config/.htaccess` impide pedirlo por HTTP en Apache. Los demás valores configurables (umbral de stock bajo,
límite de intentos de login) están documentados dentro de `config.example.php`.

## 5. Iniciar el sitio
- **Con el servidor de PHP** (desde la carpeta del proyecto): `php -S localhost:8080` → abrir
  <http://localhost:8080/index.html>.
- **Con XAMPP/Apache:** <http://localhost/Mercadoweb/index.html>.

Panel administrativo: <http://localhost:8080/admin/index.html> (requiere iniciar sesión con una cuenta `admin`).

## 6. Cuentas
- Administrador de **demostración** (credencial académica, cambiala apenas instales):
  `admin@capibarato.pe` / `CapiBarato2026!`
- Clientes: se crean desde "Creá tu cuenta".

## 7. Ejecutar las pruebas (sin tocar tu base)
Ver `tests/README.md`. Resumen: `bash tests/run_all.sh` crea y usa **solo** la base `mercadoweb_test`; nunca
modifica `mercadoweb`.
