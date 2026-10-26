# Matriz de pruebas — resultados reales

Generada automáticamente por `tests/generar_matriz.py` a partir de `run_all.log` (ejecución del 2026-10-24 03:40). Cada fila es una aserción que se ejecutó de verdad contra PHP + MariaDB y/o Chromium; la columna *Evidencia* indica la suite y la sección del log donde está la salida.

**Total: 853 aprobadas, 0 fallidas, en 10 suites.**

| Suite | Aprobadas | Fallidas | Resultado del log |
|---|---:|---:|---|
| `test_auth` | 70 | 0 | RESULTADO FINAL: 70 pruebas OK, 0 con FALLA |
| `test_bloque1` | 25 | 0 | RESULTADO FINAL: 25 pruebas OK, 0 con FALLA |
| `test_bloque2` | 31 | 0 | RESULTADO FINAL: 31 pruebas OK, 0 con FALLA |
| `test_bloque3` | 91 | 0 | RESULTADO FINAL: 91 pruebas OK, 0 con FALLA |
| `test_inventario` | 74 | 0 | RESULTADO FINAL: 74 pruebas OK, 0 con FALLA |
| `test_admin` | 193 | 0 | RESULTADO FINAL: 193 pruebas OK, 0 con FALLA |
| `test_concurrencia` | 6 | 0 | RESULTADO FINAL: 6 pruebas OK, 0 con FALLA |
| `test_navegador_bloque3` | 56 | 0 | RESULTADO FINAL: 56 pruebas OK, 0 con FALLA |
| `test_navegador_admin` | 47 | 0 | RESULTADO FINAL: 47 pruebas OK, 0 con FALLA |
| `test_responsive` | 260 | 0 | RESULTADO FINAL: 260 pruebas OK, 0 con FALLA |

## test_auth

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 1 | 1) REGISTRO | Registro válido (HTTP 201) | Aprobado | log `test_auth` |
| 2 | 1) REGISTRO | Email duplicado rechazado (HTTP 409) | Aprobado | log `test_auth` |
| 3 | 1) REGISTRO | Email con formato inválido (HTTP 400) | Aprobado | log `test_auth` |
| 4 | 1) REGISTRO | Contraseña corta (HTTP 400) | Aprobado | log `test_auth` |
| 5 | 2) LOGIN | Contraseña incorrecta (HTTP 401) | Aprobado | log `test_auth` |
| 6 | 2) LOGIN | Email inexistente (HTTP 401) | Aprobado | log `test_auth` |
| 7 | 2) LOGIN | Mensaje idéntico (no revela si el email existe) ({"error":"Correo o contraseña incorrectos."}) | Aprobado | log `test_auth` |
| 8 | 2) LOGIN | Inyección SQL en el email no autentica (HTTP 401) | Aprobado | log `test_auth` |
| 9 | 2) LOGIN | Login de cliente (HTTP 200) | Aprobado | log `test_auth` |
| 10 | 2) LOGIN | Rol detectado (cliente) | Aprobado | log `test_auth` |
| 11 | 2) LOGIN | Login de admin (HTTP 200) | Aprobado | log `test_auth` |
| 12 | 2) LOGIN | Rol detectado (admin) | Aprobado | log `test_auth` |
| 13 | 3) SESIÓN | Invitado (False) | Aprobado | log `test_auth` |
| 14 | 3) SESIÓN | Cliente logueado (True) | Aprobado | log `test_auth` |
| 15 | 4) PROTECCIÓN POR ROL | admin sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 16 | 4) PROTECCIÓN POR ROL | admin con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 17 | 4) PROTECCIÓN POR ROL | admin/productos.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 18 | 4) PROTECCIÓN POR ROL | admin/productos.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 19 | 4) PROTECCIÓN POR ROL | admin/productos.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 20 | 4) PROTECCIÓN POR ROL | admin/categorias.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 21 | 4) PROTECCIÓN POR ROL | admin/categorias.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 22 | 4) PROTECCIÓN POR ROL | admin/categorias.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 23 | 4) PROTECCIÓN POR ROL | admin/usuarios.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 24 | 4) PROTECCIÓN POR ROL | admin/usuarios.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 25 | 4) PROTECCIÓN POR ROL | admin/usuarios.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 26 | 4) PROTECCIÓN POR ROL | admin/pedidos.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 27 | 4) PROTECCIÓN POR ROL | admin/pedidos.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 28 | 4) PROTECCIÓN POR ROL | admin/pedidos.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 29 | 4) PROTECCIÓN POR ROL | admin/inventario.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 30 | 4) PROTECCIÓN POR ROL | admin/inventario.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 31 | 4) PROTECCIÓN POR ROL | admin/inventario.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 32 | 4) PROTECCIÓN POR ROL | admin/estadisticas.php sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 33 | 4) PROTECCIÓN POR ROL | admin/estadisticas.php con sesión de cliente (HTTP 403) | Aprobado | log `test_auth` |
| 34 | 4) PROTECCIÓN POR ROL | admin/estadisticas.php con sesión de admin (HTTP 200) | Aprobado | log `test_auth` |
| 35 | 4) PROTECCIÓN POR ROL | carrito sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 36 | 4) PROTECCIÓN POR ROL | carrito con sesión de cliente (HTTP 200) | Aprobado | log `test_auth` |
| 37 | 4) PROTECCIÓN POR ROL | pedidos sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 38 | 4) PROTECCIÓN POR ROL | catálogo público (HTTP 200) | Aprobado | log `test_auth` |
| 39 | 5) LOGOUT | Logout (HTTP 200) | Aprobado | log `test_auth` |
| 40 | 5) LOGOUT | La sesión quedó destruida en el servidor (False) | Aprobado | log `test_auth` |
| 41 | 5) LOGOUT | carrito con la cookie vieja (HTTP 401) | Aprobado | log `test_auth` |
| 42 | 6) CSRF (token sincronizado + Origin + application/json) | La respuesta de registro deja el token en la cookie mw_csrf (64) | Aprobado | log `test_auth` |
| 43 | 6) CSRF (token sincronizado + Origin + application/json) | sesion.php devuelve el mismo token (b4738038b5414a2671dd5a52292ddc104abe6cecc033ea5eee54e71200e8f4da) | Aprobado | log `test_auth` |
| 44 | 6) CSRF (token sincronizado + Origin + application/json) | POST de carrito SIN token (HTTP 403) | Aprobado | log `test_auth` |
| 45 | 6) CSRF (token sincronizado + Origin + application/json) | POST de carrito con token INCORRECTO (HTTP 403) | Aprobado | log `test_auth` |
| 46 | 6) CSRF (token sincronizado + Origin + application/json) | POST de checkout SIN token (HTTP 403) | Aprobado | log `test_auth` |
| 47 | 6) CSRF (token sincronizado + Origin + application/json) | Logout SIN token (no se puede forzar el cierre de sesión) (HTTP 403) | Aprobado | log `test_auth` |
| 48 | 6) CSRF (token sincronizado + Origin + application/json) | POST con token correcto pero Origin de otro sitio (HTTP 403) | Aprobado | log `test_auth` |
| 49 | 6) CSRF (token sincronizado + Origin + application/json) | POST con token correcto y Origin del propio sitio (HTTP 201) | Aprobado | log `test_auth` |
| 50 | 6) CSRF (token sincronizado + Origin + application/json) | Cuerpo enviado como text/plain (lo que puede mandar un formulario ajeno) (HTTP 415) | Aprobado | log `test_auth` |
| 51 | 6) CSRF (token sincronizado + Origin + application/json) | Login desde un formulario de otro sitio (Origin ajeno) (HTTP 403) | Aprobado | log `test_auth` |
| 52 | 6) CSRF (token sincronizado + Origin + application/json) | Los rechazos no tocaron el carrito (sigue 1 unidad) (1) | Aprobado | log `test_auth` |
| 53 | 6) CSRF (token sincronizado + Origin + application/json) | Un login nuevo genera un token nuevo | Aprobado | log `test_auth` |
| 54 | 6) CSRF (token sincronizado + Origin + application/json) | El token viejo ya no sirve (HTTP 403) | Aprobado | log `test_auth` |
| 55 | 6) CSRF (token sincronizado + Origin + application/json) | GET a logout no cierra la sesión (HTTP 405) | Aprobado | log `test_auth` |
| 56 | 6) CSRF (token sincronizado + Origin + application/json) | …la sesión sigue activa (True) | Aprobado | log `test_auth` |
| 57 | 7) LÍMITE DE INTENTOS DE LOGIN | 5.º intento fallido sigue siendo 401 (HTTP 401) | Aprobado | log `test_auth` |
| 58 | 7) LÍMITE DE INTENTOS DE LOGIN | 6.º intento: bloqueado (HTTP 429) | Aprobado | log `test_auth` |
| 59 | 7) LÍMITE DE INTENTOS DE LOGIN | Bloqueado incluso con la contraseña CORRECTA (HTTP 429) | Aprobado | log `test_auth` |
| 60 | 7) LÍMITE DE INTENTOS DE LOGIN | Responde Retry-After (899 s) | Aprobado | log `test_auth` |
| 61 | 7) LÍMITE DE INTENTOS DE LOGIN | Otro correo no queda bloqueado (HTTP 200) | Aprobado | log `test_auth` |
| 62 | 7) LÍMITE DE INTENTOS DE LOGIN | Login correcto tras 4 fallos (debajo del límite) (HTTP 200) | Aprobado | log `test_auth` |
| 63 | 7) LÍMITE DE INTENTOS DE LOGIN | …y el éxito reinició el contador (4 fallos nuevos no bloquean) (HTTP 401) | Aprobado | log `test_auth` |
| 64 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Cliente activo accede a su carrito (HTTP 200) | Aprobado | log `test_auth` |
| 65 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Cuenta desactivada: la sesión abierta deja de valer (HTTP 401) | Aprobado | log `test_auth` |
| 66 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | …y no puede volver a iniciar sesión (HTTP 403) | Aprobado | log `test_auth` |
| 67 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Cliente no entra a /admin (HTTP 403) | Aprobado | log `test_auth` |
| 68 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Promovido a admin: entra sin volver a loguearse (HTTP 200) | Aprobado | log `test_auth` |
| 69 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Vuelto a cliente: pierde el acceso en el acto (HTTP 403) | Aprobado | log `test_auth` |
| 70 | 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE | Cookie de sesión inventada no autentica (HTTP 401) | Aprobado | log `test_auth` |

## test_bloque1

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 71 | CATEGORÍAS | Listar (HTTP 200) | Aprobado | log `test_bloque1` |
| 72 | CATEGORÍAS | Son 3 (3) | Aprobado | log `test_bloque1` |
| 73 | PRODUCTOS: LISTAR | Catálogo completo (12) | Aprobado | log `test_bloque1` |
| 74 | PRODUCTOS: LISTAR | Filtro celulares (4) | Aprobado | log `test_bloque1` |
| 75 | PRODUCTOS: LISTAR | Categoría inexistente (HTTP 404) | Aprobado | log `test_bloque1` |
| 76 | PRODUCTOS: LISTAR | POST rechazado (HTTP 405) | Aprobado | log `test_bloque1` |
| 77 | PRODUCTOS: DETALLE | Producto 1: 3 imágenes (3) | Aprobado | log `test_bloque1` |
| 78 | PRODUCTOS: DETALLE | Producto 1: precio final (429.14) | Aprobado | log `test_bloque1` |
| 79 | PRODUCTOS: DETALLE | Inexistente (HTTP 404) | Aprobado | log `test_bloque1` |
| 80 | PRODUCTOS: DETALLE | id no numérico (HTTP 400) | Aprobado | log `test_bloque1` |
| 81 | PRODUCTOS: DETALLE | Falta id (HTTP 400) | Aprobado | log `test_bloque1` |
| 82 | PRODUCTOS: DETALLE | Inyección SQL en id (HTTP 400) | Aprobado | log `test_bloque1` |
| 83 | PRODUCTOS: DETALLE | La tabla productos sigue intacta (12) | Aprobado | log `test_bloque1` |
| 84 | PRODUCTOS: BUSCAR | samsung (4) | Aprobado | log `test_bloque1` |
| 85 | PRODUCTOS: BUSCAR | SAMSUNG (mayúsculas) (4) | Aprobado | log `test_bloque1` |
| 86 | PRODUCTOS: BUSCAR | Por categoría: notebooks (4) | Aprobado | log `test_bloque1` |
| 87 | PRODUCTOS: BUSCAR | Por característica (bluetooth) | Aprobado | log `test_bloque1` |
| 88 | PRODUCTOS: BUSCAR | Sin resultados (0) | Aprobado | log `test_bloque1` |
| 89 | PRODUCTOS: BUSCAR | Falta q (HTTP 400) | Aprobado | log `test_bloque1` |
| 90 | PRODUCTOS: BUSCAR | Inyección SQL en q no rompe la consulta (HTTP 200) | Aprobado | log `test_bloque1` |
| 91 | PRODUCTOS: BUSCAR | …y se trata como texto literal (0 resultados) (0) | Aprobado | log `test_bloque1` |
| 92 | PÚBLICOS (no exigen sesión) | categorias/listar.php es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 93 | PÚBLICOS (no exigen sesión) | productos/listar.php es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 94 | PÚBLICOS (no exigen sesión) | productos/detalle.php?id=1 es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 95 | PÚBLICOS (no exigen sesión) | productos/buscar.php?q=moto es público (HTTP 200) | Aprobado | log `test_bloque1` |

## test_bloque2

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 96 | 1) CARRITO VACÍO | Carrito vacío (HTTP 200) | Aprobado | log `test_bloque2` |
| 97 | 1) CARRITO VACÍO | Resumen en cero (0) | Aprobado | log `test_bloque2` |
| 98 | 2) AGREGAR | Agregar producto válido (HTTP 201) | Aprobado | log `test_bloque2` |
| 99 | 2) AGREGAR | Producto repetido: suma (1+2) (3) | Aprobado | log `test_bloque2` |
| 100 | 2) AGREGAR | …y no duplica la fila (1) | Aprobado | log `test_bloque2` |
| 101 | 2) AGREGAR | Producto inexistente (HTTP 404) | Aprobado | log `test_bloque2` |
| 102 | 2) AGREGAR | Más que el stock (producto 5, stock 2) (HTTP 409) | Aprobado | log `test_bloque2` |
| 103 | 2) AGREGAR | Sin sesión (HTTP 401) | Aprobado | log `test_bloque2` |
| 104 | 3) ACTUALIZAR | Cantidad válida (HTTP 200) | Aprobado | log `test_bloque2` |
| 105 | 3) ACTUALIZAR | Cantidad 0 (HTTP 400) | Aprobado | log `test_bloque2` |
| 106 | 3) ACTUALIZAR | Cantidad negativa (HTTP 400) | Aprobado | log `test_bloque2` |
| 107 | 3) ACTUALIZAR | Cantidad decimal (HTTP 400) | Aprobado | log `test_bloque2` |
| 108 | 3) ACTUALIZAR | Superior al stock (HTTP 409) | Aprobado | log `test_bloque2` |
| 109 | 3) ACTUALIZAR | Producto inexistente (HTTP 404) | Aprobado | log `test_bloque2` |
| 110 | 3) ACTUALIZAR | Los rechazos no modificaron nada (sigue en 5) (5) | Aprobado | log `test_bloque2` |
| 111 | 4) SEGURIDAD: carrito ajeno | A no puede actualizar el producto 4 de B (HTTP 404) | Aprobado | log `test_bloque2` |
| 112 | 4) SEGURIDAD: carrito ajeno | A no puede eliminar el producto 4 de B (HTTP 404) | Aprobado | log `test_bloque2` |
| 113 | 4) SEGURIDAD: carrito ajeno | El carrito de B quedó intacto ({4: 1}) | Aprobado | log `test_bloque2` |
| 114 | 4) SEGURIDAD: carrito ajeno | Inyección SQL en id_producto (HTTP 400) | Aprobado | log `test_bloque2` |
| 115 | 5) ELIMINAR | Eliminar propio (HTTP 200) | Aprobado | log `test_bloque2` |
| 116 | 5) ELIMINAR | Eliminar de nuevo (HTTP 404) | Aprobado | log `test_bloque2` |
| 117 | 6) PERSISTENCIA (logout + login) | Tras logout no accesible (HTTP 401) | Aprobado | log `test_bloque2` |
| 118 | 6) PERSISTENCIA (logout + login) | El carrito persistió en MySQL ({2: 3}) | Aprobado | log `test_bloque2` |
| 119 | 7) FUSIÓN | Fusionar (HTTP 200) | Aprobado | log `test_bloque2` |
| 120 | 7) FUSIÓN | 2 suma (3+2), 6 nuevo, 5 limitado al stock, 'abc' ignorado ({2: 5, 5: 2, 6: 1}) | Aprobado | log `test_bloque2` |
| 121 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Subtotal (2 × 521.10) (1042.2) | Aprobado | log `test_bloque2` |
| 122 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Envío (S/50, no se multiplica por 2) (50) | Aprobado | log `test_bloque2` |
| 123 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Total (1092.2) | Aprobado | log `test_bloque2` |
| 124 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Envío con 50 y 100 en el carrito = el MAYOR (100), no la suma (100) | Aprobado | log `test_bloque2` |
| 125 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | …y no cambia al subir la cantidad (100) | Aprobado | log `test_bloque2` |
| 126 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Todos con envío gratis = S/0.00 (0) | Aprobado | log `test_bloque2` |

## test_bloque3

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 127 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Carrito vacío (HTTP 400) | Aprobado | log `test_bloque3` |
| 128 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 129 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Cookie de sesión inventada (HTTP 401) | Aprobado | log `test_bloque3` |
| 130 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección ausente (HTTP 400) | Aprobado | log `test_bloque3` |
| 131 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección vacía (HTTP 400) | Aprobado | log `test_bloque3` |
| 132 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección solo con espacios (HTTP 400) | Aprobado | log `test_bloque3` |
| 133 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección demasiado corta (2 caracteres) (HTTP 400) | Aprobado | log `test_bloque3` |
| 134 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección como lista (tipo inválido) (HTTP 400) | Aprobado | log `test_bloque3` |
| 135 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección como número (tipo inválido) (HTTP 400) | Aprobado | log `test_bloque3` |
| 136 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección de 256 caracteres (máximo 255) (HTTP 400) | Aprobado | log `test_bloque3` |
| 137 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Cuerpo que no es JSON (HTTP 400) | Aprobado | log `test_bloque3` |
| 138 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Ninguno de esos rechazos creó un pedido (0) | Aprobado | log `test_bloque3` |
| 139 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | …ni vació el carrito de A (1) | Aprobado | log `test_bloque3` |
| 140 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Checkout (HTTP 201) | Aprobado | log `test_bloque3` |
| 141 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido existe (1) | Aprobado | log `test_bloque3` |
| 142 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Detalle existe (1 línea) (1) | Aprobado | log `test_bloque3` |
| 143 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Detalle: cantidad 1, precio unitario 429.14, subtotal 429.14 (1\|429.14\|429.14) | Aprobado | log `test_bloque3` |
| 144 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Stock descontado (99) | Aprobado | log `test_bloque3` |
| 145 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Carrito vaciado (0) | Aprobado | log `test_bloque3` |
| 146 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido: subtotal\|envío\|total (429.14\|0.00\|429.14) | Aprobado | log `test_bloque3` |
| 147 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | total = subtotal + envío, y subtotal = suma del detalle (1) | Aprobado | log `test_bloque3` |
| 148 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Estado inicial (pendiente) | Aprobado | log `test_bloque3` |
| 149 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido de A pertenece a A (2) | Aprobado | log `test_bloque3` |
| 150 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Dirección guardada (Av. Javier Prado 456, San Isidro, Lima) | Aprobado | log `test_bloque3` |
| 151 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Repetir el checkout con el carrito ya vacío (HTTP 400) | Aprobado | log `test_bloque3` |
| 152 | 3) PRECIO HISTÓRICO | El catálogo ya muestra el precio nuevo (999) | Aprobado | log `test_bloque3` |
| 153 | 3) PRECIO HISTÓRICO | El pedido conserva el precio unitario de la compra (429.14) | Aprobado | log `test_bloque3` |
| 154 | 3) PRECIO HISTÓRICO | …y el total original (429.14) | Aprobado | log `test_bloque3` |
| 155 | 3) PRECIO HISTÓRICO | En MySQL detalle_pedido.precio_unitario no cambió (429.14) | Aprobado | log `test_bloque3` |
| 156 | 3) PRECIO HISTÓRICO | Precio del producto 1 restaurado (429.14) | Aprobado | log `test_bloque3` |
| 157 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | Stock insuficiente (HTTP 409) | Aprobado | log `test_bloque3` |
| 158 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | El mensaje dice qué producto tiene el problema | Aprobado | log `test_bloque3` |
| 159 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | No se creó pedido (0) | Aprobado | log `test_bloque3` |
| 160 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | Stock intacto (2) | Aprobado | log `test_bloque3` |
| 161 | 5) VARIOS PRODUCTOS: uno OK + otro sin stock (validación previa, sin escrituras) | El checkout completo falla (HTTP 409) | Aprobado | log `test_bloque3` |
| 162 | 5) VARIOS PRODUCTOS: uno OK + otro sin stock (validación previa, sin escrituras) | Base idéntica: pedidos, detalle, stock de todos los productos y carritos (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:5:3) | Aprobado | log `test_bloque3` |
| 163 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) Falla en el detalle del 2º producto -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 164 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) La respuesta sigue siendo JSON | Aprobado | log `test_bloque3` |
| 165 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) No filtra el error interno de MySQL | Aprobado | log `test_bloque3` |
| 166 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) ROLLBACK: pedidos, detalle, stock (prod. 2 incluido) y carrito idénticos a antes (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 167 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6b) Falla al descontar stock del 2º producto -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 168 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6b) ROLLBACK completo (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 169 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6c) Falla al vaciar el carrito -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 170 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6c) ROLLBACK completo (no queda pedido sin carrito vaciado ni stock descontado) (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 171 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | Triggers de prueba eliminados (0) | Aprobado | log `test_bloque3` |
| 172 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | Sin la falla simulada, el mismo carrito se compra normalmente (HTTP 201) | Aprobado | log `test_bloque3` |
| 173 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | …con 2 líneas de detalle (2) | Aprobado | log `test_bloque3` |
| 174 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | Producto desactivado rechazado (HTTP 409) | Aprobado | log `test_bloque3` |
| 175 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | El mensaje nombra el producto desactivado | Aprobado | log `test_bloque3` |
| 176 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | Nada cambió (tampoco el producto activo del mismo carrito) (2\|3\|1:99,2:20,3:21,4:21,5:2,6:35,7:33,8:35,9:10,10:10,11:10,12:10\|3:1:1,3:3:1) | Aprobado | log `test_bloque3` |
| 177 | 8) ENVÍO EN UN CHECKOUT REAL | Checkout con envíos S/50 y S/100 (HTTP 201) | Aprobado | log `test_bloque3` |
| 178 | 8) ENVÍO EN UN CHECKOUT REAL | Envío = el mayor (100), no la suma (150) (100) | Aprobado | log `test_bloque3` |
| 179 | 8) ENVÍO EN UN CHECKOUT REAL | Subtotal (463.97 + 583.27) (1047.24) | Aprobado | log `test_bloque3` |
| 180 | 8) ENVÍO EN UN CHECKOUT REAL | Total (1147.24) | Aprobado | log `test_bloque3` |
| 181 | 8) ENVÍO EN UN CHECKOUT REAL | En MySQL: envío 100.00, total 1147.24 (100.00\|1147.24) | Aprobado | log `test_bloque3` |
| 182 | 8) ENVÍO EN UN CHECKOUT REAL | Un producto con envío pago y 3 unidades: envío S/50 una sola vez (50) | Aprobado | log `test_bloque3` |
| 183 | 8) ENVÍO EN UN CHECKOUT REAL | …total = 3 × 463.97 + 50 (1441.91) | Aprobado | log `test_bloque3` |
| 184 | 8) ENVÍO EN UN CHECKOUT REAL | Todo con envío gratis = S/0.00 (0) | Aprobado | log `test_bloque3` |
| 185 | 8) ENVÍO EN UN CHECKOUT REAL | …total = 5 × 429.14 + 2 × 494.10 (3133.9) | Aprobado | log `test_bloque3` |
| 186 | 9) HISTORIAL | A ve exactamente 1 pedido (1) | Aprobado | log `test_bloque3` |
| 187 | 9) HISTORIAL | B ve sus pedidos del más reciente al más antiguo | Aprobado | log `test_bloque3` |
| 188 | 9) HISTORIAL | Detalle: dirección (Av. Javier Prado 456, San Isidro, Lima) | Aprobado | log `test_bloque3` |
| 189 | 9) HISTORIAL | Detalle: 1 ítem (1) | Aprobado | log `test_bloque3` |
| 190 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | A no puede ver el pedido de B (HTTP 404) | Aprobado | log `test_bloque3` |
| 191 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | La respuesta es IDÉNTICA a la de un pedido inexistente (no revela que existe) ({"error":"Pedido no encontrado."}) | Aprobado | log `test_bloque3` |
| 192 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | El listado de A no incluye pedidos de B (comparado por id_pedido) | Aprobado | log `test_bloque3` |
| 193 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Inyección SQL en el id (HTTP 400) | Aprobado | log `test_bloque3` |
| 194 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | id negativo (HTTP 400) | Aprobado | log `test_bloque3` |
| 195 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Detalle sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 196 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Listado sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 197 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | GET en crear.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 198 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | POST en listar.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 199 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | POST en detalle.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 200 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Checkout con la cookie de una sesión ya cerrada (HTTP 401) | Aprobado | log `test_bloque3` |
| 201 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | Dirección con SQL: se acepta como texto (HTTP 201) | Aprobado | log `test_bloque3` |
| 202 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La tabla pedidos sigue existiendo (1) | Aprobado | log `test_bloque3` |
| 203 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | …y la dirección quedó guardada literalmente (Calle 1'); DROP TABLE pedidos; -- " OR 1=1) | Aprobado | log `test_bloque3` |
| 204 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La API responde como JSON (no como HTML) (content-type: application/json; charset=utf-8) | Aprobado | log `test_bloque3` |
| 205 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La API devuelve el texto tal cual (el escape es tarea de la vista; se prueba en el navegador) (<img src=x onerror="alert(1)"><script>alert(2)</script>) | Aprobado | log `test_bloque3` |
| 206 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | El carrito ignoró id_usuario/precio del cliente: el ítem quedó en el carrito de A (1) | Aprobado | log `test_bloque3` |
| 207 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | Se crea el pedido ignorando esos campos (HTTP 201) | Aprobado | log `test_bloque3` |
| 208 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | id_usuario = el de la sesión (A), no el que mandó el cliente (2) | Aprobado | log `test_bloque3` |
| 209 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | estado = pendiente (no 'pagado') (pendiente) | Aprobado | log `test_bloque3` |
| 210 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | total calculado por el servidor (521.10 + envío 50), no 1 (571.10) | Aprobado | log `test_bloque3` |
| 211 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | precio_unitario del servidor, no 1 (521.10) | Aprobado | log `test_bloque3` |
| 212 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | B no recibió ningún pedido nuevo de A (4) | Aprobado | log `test_bloque3` |
| 213 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | Ningún stock negativo (0) | Aprobado | log `test_bloque3` |
| 214 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | Todo pedido tiene al menos una línea de detalle (0) | Aprobado | log `test_bloque3` |
| 215 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | En todo pedido: subtotal = Σ detalle y total = subtotal + envío (0) | Aprobado | log `test_bloque3` |
| 216 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | En todo detalle: subtotal = cantidad × precio_unitario (0) | Aprobado | log `test_bloque3` |
| 217 | 14) ESTADOS | El ENUM admite los 5 estados (pendiente,pagado,enviado,completado,cancelado) | Aprobado | log `test_bloque3` |

## test_inventario

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 218 | 1) PERMISOS (en el servidor) | GET sin sesión (HTTP 401) | Aprobado | log `test_inventario` |
| 219 | 1) PERMISOS (en el servidor) | POST sin sesión (HTTP 401) | Aprobado | log `test_inventario` |
| 220 | 1) PERMISOS (en el servidor) | GET con sesión de cliente (HTTP 403) | Aprobado | log `test_inventario` |
| 221 | 1) PERMISOS (en el servidor) | POST de cliente con su token CSRF válido (HTTP 403) | Aprobado | log `test_inventario` |
| 222 | 1) PERMISOS (en el servidor) | POST de cliente intentando 'sumar' 999 (HTTP 403) | Aprobado | log `test_inventario` |
| 223 | 1) PERMISOS (en el servidor) | POST de admin SIN token CSRF (HTTP 403) | Aprobado | log `test_inventario` |
| 224 | 1) PERMISOS (en el servidor) | POST de admin con Origin ajeno (HTTP 403) | Aprobado | log `test_inventario` |
| 225 | 1) PERMISOS (en el servidor) | PUT no permitido (HTTP 405) | Aprobado | log `test_inventario` |
| 226 | 1) PERMISOS (en el servidor) | Ningún intento rechazado cambió el stock (1:100,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10) | Aprobado | log `test_inventario` |
| 227 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Umbral de stock bajo por defecto (5) | Aprobado | log `test_inventario` |
| 228 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Lista los 12 productos (12) | Aprobado | log `test_inventario` |
| 229 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Unidades totales = suma real en MySQL (310) | Aprobado | log `test_inventario` |
| 230 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Alertas: 1 con stock bajo (Galaxy Book3, stock 2) (1) | Aprobado | log `test_inventario` |
| 231 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Alertas: 0 sin stock (0) | Aprobado | log `test_inventario` |
| 232 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Ordenado de menor a mayor stock: primero el producto 5 (5) | Aprobado | log `test_inventario` |
| 233 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | stock_bajo coincide con stock <= 5 en cada producto | Aprobado | log `test_inventario` |
| 234 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | filtro=bajo devuelve solo el producto 5 ([5]) | Aprobado | log `test_inventario` |
| 235 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | filtro=sin_stock vacío (0) | Aprobado | log `test_inventario` |
| 236 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | filtro inválido (HTTP 400) | Aprobado | log `test_inventario` |
| 237 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | q=samsung (4 productos) (4) | Aprobado | log `test_inventario` |
| 238 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | categoria=notebooks (4) (4) | Aprobado | log `test_inventario` |
| 239 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | categoría inexistente (HTTP 404) | Aprobado | log `test_inventario` |
| 240 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | Inyección SQL en q no rompe la consulta (HTTP 200) | Aprobado | log `test_inventario` |
| 241 | 2) CONSULTA DE EXISTENCIAS Y ALERTAS | …y se trata como texto (0 resultados) (0) | Aprobado | log `test_inventario` |
| 242 | 3) AJUSTES DE STOCK (verificados en MySQL) | Fijar el producto 6 en 0 (HTTP 200) | Aprobado | log `test_inventario` |
| 243 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: stock 0 (0) | Aprobado | log `test_inventario` |
| 244 | 3) AJUSTES DE STOCK (verificados en MySQL) | Respuesta: stock anterior 35 y marca sin_stock (35\|0\|True) | Aprobado | log `test_inventario` |
| 245 | 3) AJUSTES DE STOCK (verificados en MySQL) | Alertas actualizadas: 2 con stock bajo, 1 sin stock (2\|1) | Aprobado | log `test_inventario` |
| 246 | 3) AJUSTES DE STOCK (verificados en MySQL) | Sumar 7 (HTTP 200) | Aprobado | log `test_inventario` |
| 247 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: 0 + 7 = 7 (7) | Aprobado | log `test_inventario` |
| 248 | 3) AJUSTES DE STOCK (verificados en MySQL) | 7 > umbral: ya no es stock bajo (False) | Aprobado | log `test_inventario` |
| 249 | 3) AJUSTES DE STOCK (verificados en MySQL) | Restar 2 (HTTP 200) | Aprobado | log `test_inventario` |
| 250 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: 7 - 2 = 5 (5) | Aprobado | log `test_inventario` |
| 251 | 3) AJUSTES DE STOCK (verificados en MySQL) | Stock 5 = umbral: SÍ es stock bajo (límite incluido) (True) | Aprobado | log `test_inventario` |
| 252 | 3) AJUSTES DE STOCK (verificados en MySQL) | Restar más de lo que hay (HTTP 409) | Aprobado | log `test_inventario` |
| 253 | 3) AJUSTES DE STOCK (verificados en MySQL) | El mensaje explica el stock actual | Aprobado | log `test_inventario` |
| 254 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: sigue en 5 (nunca negativo) (5) | Aprobado | log `test_inventario` |
| 255 | 3) AJUSTES DE STOCK (verificados en MySQL) | Restar exactamente todo (5) (HTTP 200) | Aprobado | log `test_inventario` |
| 256 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: 0 (0) | Aprobado | log `test_inventario` |
| 257 | 3) AJUSTES DE STOCK (verificados en MySQL) | Restar 1 con stock 0 (HTTP 409) | Aprobado | log `test_inventario` |
| 258 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: sigue en 0 (0) | Aprobado | log `test_inventario` |
| 259 | 3) AJUSTES DE STOCK (verificados en MySQL) | Cantidad como texto numérico ("12") (HTTP 200) | Aprobado | log `test_inventario` |
| 260 | 3) AJUSTES DE STOCK (verificados en MySQL) | MySQL: 12 (12) | Aprobado | log `test_inventario` |
| 261 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | fijar negativo (-1) (HTTP 400) | Aprobado | log `test_inventario` |
| 262 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | decimal (2.5) (HTTP 400) | Aprobado | log `test_inventario` |
| 263 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | texto ("abc") (HTTP 400) | Aprobado | log `test_inventario` |
| 264 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | booleano (true) (HTTP 400) | Aprobado | log `test_inventario` |
| 265 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | sumar 0 (HTTP 400) | Aprobado | log `test_inventario` |
| 266 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | restar 0 (HTTP 400) | Aprobado | log `test_inventario` |
| 267 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | sin cantidad (HTTP 400) | Aprobado | log `test_inventario` |
| 268 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | operación inválida (HTTP 400) | Aprobado | log `test_inventario` |
| 269 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | sin operación (HTTP 400) | Aprobado | log `test_inventario` |
| 270 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | producto inexistente (HTTP 404) | Aprobado | log `test_inventario` |
| 271 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | id con inyección SQL (HTTP 400) | Aprobado | log `test_inventario` |
| 272 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | fijar por encima del máximo (1.000.001) (HTTP 400) | Aprobado | log `test_inventario` |
| 273 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | fijar en el máximo exacto (1.000.000) (HTTP 200) | Aprobado | log `test_inventario` |
| 274 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | sumar que haría superar el máximo (HTTP 400) | Aprobado | log `test_inventario` |
| 275 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | número enorme (fuera de rango INT) (HTTP 400) | Aprobado | log `test_inventario` |
| 276 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | cuerpo que no es JSON (HTTP 400) | Aprobado | log `test_inventario` |
| 277 | 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) | El stock del producto 6 no cambió con los rechazos (12) | Aprobado | log `test_inventario` |
| 278 | 5) PRODUCTOS INACTIVOS: se gestionan pero no generan alertas | El inactivo aparece en la lista con su estado | Aprobado | log `test_inventario` |
| 279 | 5) PRODUCTOS INACTIVOS: se gestionan pero no generan alertas | …pero no suma a las alertas (solo productos activos) (1) | Aprobado | log `test_inventario` |
| 280 | 5) PRODUCTOS INACTIVOS: se gestionan pero no generan alertas | Se puede reponer stock de un inactivo (HTTP 200) | Aprobado | log `test_inventario` |
| 281 | 5) PRODUCTOS INACTIVOS: se gestionan pero no generan alertas | MySQL: 5 (5) | Aprobado | log `test_inventario` |
| 282 | 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT | El catálogo público muestra el stock nuevo (1) | Aprobado | log `test_inventario` |
| 283 | 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT | Checkout con 2 en el carrito y stock 1: rechazado (HTTP 409) | Aprobado | log `test_inventario` |
| 284 | 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT | Tras reponer (stock 2), el checkout pasa (HTTP 201) | Aprobado | log `test_inventario` |
| 285 | 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT | …y deja el stock en 0 (0) | Aprobado | log `test_inventario` |
| 286 | 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT | Ahora el producto 5 figura sin stock (True) | Aprobado | log `test_inventario` |
| 287 | 7) AJUSTES SIMULTÁNEOS (5 'restar 1' a la vez sobre stock 3) | Exactamente 3 aceptados (3) | Aprobado | log `test_inventario` |
| 288 | 7) AJUSTES SIMULTÁNEOS (5 'restar 1' a la vez sobre stock 3) | …y 2 rechazados con 409 (2) | Aprobado | log `test_inventario` |
| 289 | 7) AJUSTES SIMULTÁNEOS (5 'restar 1' a la vez sobre stock 3) | Stock final 0, nunca negativo (0) | Aprobado | log `test_inventario` |
| 290 | 8) UMBRAL CONFIGURABLE SIN TOCAR EL CÓDIGO | Con STOCK_BAJO_UMBRAL=10 el endpoint informa umbral 10 (10) | Aprobado | log `test_inventario` |
| 291 | 8) UMBRAL CONFIGURABLE SIN TOCAR EL CÓDIGO | …y marca como stock bajo los productos activos con stock <= 10 (5) | Aprobado | log `test_inventario` |

## test_admin

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 292 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET productos sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 293 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET productos como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 294 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET categorias sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 295 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET categorias como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 296 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET usuarios sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 297 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET usuarios como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 298 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET pedidos sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 299 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET pedidos como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 300 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET inventario sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 301 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET inventario como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 302 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET estadisticas sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 303 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET estadisticas como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 304 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET imagenes sin sesión (HTTP 401) | Aprobado | log `test_admin` |
| 305 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | GET imagenes como cliente (HTTP 403) | Aprobado | log `test_admin` |
| 306 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Cliente crea producto (con su token válido) (HTTP 403) | Aprobado | log `test_admin` |
| 307 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Cliente se autopromueve a admin (HTTP 403) | Aprobado | log `test_admin` |
| 308 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Cliente marca un pedido como pagado (HTTP 403) | Aprobado | log `test_admin` |
| 309 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Cliente borra una categoría (HTTP 403) | Aprobado | log `test_admin` |
| 310 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Cliente desactiva al admin (HTTP 403) | Aprobado | log `test_admin` |
| 311 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Sin sesión: crear producto (HTTP 401) | Aprobado | log `test_admin` |
| 312 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Admin SIN token CSRF: crear categoría (HTTP 403) | Aprobado | log `test_admin` |
| 313 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Admin SIN token CSRF: cambiar rol (HTTP 403) | Aprobado | log `test_admin` |
| 314 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | POST a estadísticas (solo lectura) (HTTP 405) | Aprobado | log `test_admin` |
| 315 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | DELETE no permitido (HTTP 405) | Aprobado | log `test_admin` |
| 316 | 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) | Ningún intento rechazado cambió datos (12\|3\|1adminactivo,2clienteactivo,3clienteactivo\|\|1activo499.00,2activo559.00,3activo579.00,4activo709.00,5activo1349.00,6activo1291.00,7activo1649.00,8activo4328.00,9activo549.00,10activo599.00,11activo799.00,12activo1049.00) | Aprobado | log `test_admin` |
| 317 | 2) PRODUCTOS: LISTADO Y DETALLE | Lista los 12 productos (12) | Aprobado | log `test_admin` |
| 318 | 2) PRODUCTOS: LISTADO Y DETALLE | Filtro estado=activo (12) | Aprobado | log `test_admin` |
| 319 | 2) PRODUCTOS: LISTADO Y DETALLE | Filtro q=samsung (4) | Aprobado | log `test_admin` |
| 320 | 2) PRODUCTOS: LISTADO Y DETALLE | Filtro categoria=televisores (4) | Aprobado | log `test_admin` |
| 321 | 2) PRODUCTOS: LISTADO Y DETALLE | estado inválido (HTTP 400) | Aprobado | log `test_admin` |
| 322 | 2) PRODUCTOS: LISTADO Y DETALLE | Detalle: 3 imágenes y 6 características (3\|6) | Aprobado | log `test_admin` |
| 323 | 2) PRODUCTOS: LISTADO Y DETALLE | Detalle: precio final 429.14 (datos de B-03 sin tocar) (429.14) | Aprobado | log `test_admin` |
| 324 | 2) PRODUCTOS: LISTADO Y DETALLE | Detalle inexistente (HTTP 404) | Aprobado | log `test_admin` |
| 325 | 2) PRODUCTOS: LISTADO Y DETALLE | Detalle con id inválido (HTTP 400) | Aprobado | log `test_admin` |
| 326 | 3) PRODUCTOS: CREAR | Crear producto válido (HTTP 201) | Aprobado | log `test_admin` |
| 327 | 3) PRODUCTOS: CREAR | MySQL: fila con todos los campos (1\|199.90\|10\|8\|AUDIO SAC\|0\|25.50\|activo) | Aprobado | log `test_admin` |
| 328 | 3) PRODUCTOS: CREAR | MySQL: nombre guardado literal (sin alterar) (Parlante <b>Prueba</b> <img src=x onerror=alert(1)>) | Aprobado | log `test_admin` |
| 329 | 3) PRODUCTOS: CREAR | MySQL: 2 imágenes en orden (img/celulares/11.png,img/celulares/12.png) | Aprobado | log `test_admin` |
| 330 | 3) PRODUCTOS: CREAR | MySQL: 2 características (la vacía se descarta) (Bluetooth 5.0\|Batería de 10 h) | Aprobado | log `test_admin` |
| 331 | 3) PRODUCTOS: CREAR | Precio final calculado por el servidor (199.90 − 10 %) (179.91) | Aprobado | log `test_admin` |
| 332 | 3) PRODUCTOS: CREAR | La tienda ya lo muestra (13 productos) (13) | Aprobado | log `test_admin` |
| 333 | 3) PRODUCTOS: CREAR | Con envío gratis el costo se guarda NULL aunque se mande 50 (1\|NULL) | Aprobado | log `test_admin` |
| 334 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | nombre de 2 caracteres (HTTP 400) | Aprobado | log `test_admin` |
| 335 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | nombre de 151 caracteres (HTTP 400) | Aprobado | log `test_admin` |
| 336 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | categoría inexistente (HTTP 400) | Aprobado | log `test_admin` |
| 337 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | precio 0 (HTTP 400) | Aprobado | log `test_admin` |
| 338 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | precio negativo (HTTP 400) | Aprobado | log `test_admin` |
| 339 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | precio con 3 decimales (HTTP 400) | Aprobado | log `test_admin` |
| 340 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | precio como texto (HTTP 400) | Aprobado | log `test_admin` |
| 341 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | descuento 100 (HTTP 400) | Aprobado | log `test_admin` |
| 342 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | descuento negativo (HTTP 400) | Aprobado | log `test_admin` |
| 343 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | descuento decimal (HTTP 400) | Aprobado | log `test_admin` |
| 344 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | stock negativo (HTTP 400) | Aprobado | log `test_admin` |
| 345 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | stock decimal (HTTP 400) | Aprobado | log `test_admin` |
| 346 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | envío pago sin costo (HTTP 400) | Aprobado | log `test_admin` |
| 347 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | envio_gratis no booleano ("si") (HTTP 400) | Aprobado | log `test_admin` |
| 348 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | estado inválido (HTTP 400) | Aprobado | log `test_admin` |
| 349 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | sin imágenes (HTTP 400) | Aprobado | log `test_admin` |
| 350 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imagen que no existe (HTTP 400) | Aprobado | log `test_admin` |
| 351 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imagen con ../ (path traversal) (HTTP 400) | Aprobado | log `test_admin` |
| 352 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imagen fuera de img/ (HTTP 400) | Aprobado | log `test_admin` |
| 353 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imagen como URL externa (HTTP 400) | Aprobado | log `test_admin` |
| 354 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imagen con javascript: (HTTP 400) | Aprobado | log `test_admin` |
| 355 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | 11 imágenes (HTTP 400) | Aprobado | log `test_admin` |
| 356 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | imágenes repetidas (HTTP 400) | Aprobado | log `test_admin` |
| 357 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | características que no son lista (HTTP 400) | Aprobado | log `test_admin` |
| 358 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | característica de 256 caracteres (HTTP 400) | Aprobado | log `test_admin` |
| 359 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | acción inválida (HTTP 400) | Aprobado | log `test_admin` |
| 360 | 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) | No se creó ningún producto con esos datos (14) | Aprobado | log `test_admin` |
| 361 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | Pedido con precio 179.91 × 2 + envío 25.50 (385.32) | Aprobado | log `test_admin` |
| 362 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | Actualizar producto (HTTP 200) | Aprobado | log `test_admin` |
| 363 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | MySQL: nombre, precio, sin descuento, stock (Parlante Editado\|249.90\|NULL\|20) | Aprobado | log `test_admin` |
| 364 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | MySQL: imágenes reemplazadas y reordenadas (img/celulares/13.png,img/celulares/11.png) | Aprobado | log `test_admin` |
| 365 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | MySQL: características reemplazadas (Nueva característica) | Aprobado | log `test_admin` |
| 366 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | La tienda muestra el precio nuevo (249.90) (249.9) | Aprobado | log `test_admin` |
| 367 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | El pedido anterior conserva 179.91 por unidad (179.91) | Aprobado | log `test_admin` |
| 368 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | …y su total 385.32 (385.32) | Aprobado | log `test_admin` |
| 369 | 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO | Actualizar producto inexistente (HTTP 404) | Aprobado | log `test_admin` |
| 370 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | Desactivar (HTTP 200) | Aprobado | log `test_admin` |
| 371 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | La tienda ya no lo muestra (detalle 404) (HTTP 404) | Aprobado | log `test_admin` |
| 372 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | …ni se puede agregar al carrito (HTTP 404) | Aprobado | log `test_admin` |
| 373 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | …pero sigue en el panel (inactivo) | Aprobado | log `test_admin` |
| 374 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | Activar (HTTP 200) | Aprobado | log `test_admin` |
| 375 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | Vuelve a la tienda (HTTP 200) | Aprobado | log `test_admin` |
| 376 | 6) PRODUCTOS: ACTIVAR / DESACTIVAR | Estado inválido (HTTP 400) | Aprobado | log `test_admin` |
| 377 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Eliminar producto CON ventas responde OK (HTTP 200) | Aprobado | log `test_admin` |
| 378 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | …pero el resultado es 'desactivado' (desactivado) | Aprobado | log `test_admin` |
| 379 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | MySQL: el producto sigue existiendo, inactivo (inactivo) | Aprobado | log `test_admin` |
| 380 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | MySQL: su historial de ventas está intacto (1) | Aprobado | log `test_admin` |
| 381 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | El cliente sigue viendo su pedido con ese producto (1) | Aprobado | log `test_admin` |
| 382 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Eliminar producto SIN ventas: 'eliminado' (eliminado) | Aprobado | log `test_admin` |
| 383 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | MySQL: sin rastros (producto, imágenes, características, carritos) (0\|0\|0\|0) | Aprobado | log `test_admin` |
| 384 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Eliminar uno que ya no existe (HTTP 404) | Aprobado | log `test_admin` |
| 385 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Se vende 1 Moto G13 (producto original) (HTTP 201) | Aprobado | log `test_admin` |
| 386 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Producto original con ventas (Moto G13) tampoco se borra: se desactiva (desactivado) | Aprobado | log `test_admin` |
| 387 | 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) | Siguen los 12 productos originales (12) | Aprobado | log `test_admin` |
| 388 | 8) IMÁGENES: SUBIDA SEGURA | Lista imágenes existentes (incluye img/celulares/11.png, excluye el logo) (True\|False) | Aprobado | log `test_admin` |
| 389 | 8) IMÁGENES: SUBIDA SEGURA | PNG válido (120×90) (HTTP 201) | Aprobado | log `test_admin` |
| 390 | 8) IMÁGENES: SUBIDA SEGURA | Nombre generado por el servidor (img/productos/<aleatorio>.png) | Aprobado | log `test_admin` |
| 391 | 8) IMÁGENES: SUBIDA SEGURA | El archivo existe en el disco | Aprobado | log `test_admin` |
| 392 | 8) IMÁGENES: SUBIDA SEGURA | …y se sirve por HTTP (HTTP 200) | Aprobado | log `test_admin` |
| 393 | 8) IMÁGENES: SUBIDA SEGURA | La imagen subida se puede usar en un producto (HTTP 201) | Aprobado | log `test_admin` |
| 394 | 8) IMÁGENES: SUBIDA SEGURA | PHP disfrazado de .png (HTTP 400) | Aprobado | log `test_admin` |
| 395 | 8) IMÁGENES: SUBIDA SEGURA | SVG (puede llevar scripts) (HTTP 400) | Aprobado | log `test_admin` |
| 396 | 8) IMÁGENES: SUBIDA SEGURA | Imagen de 20×20 (muy chica) (HTTP 400) | Aprobado | log `test_admin` |
| 397 | 8) IMÁGENES: SUBIDA SEGURA | Archivo de 2,2 MB (HTTP 400) | Aprobado | log `test_admin` |
| 398 | 8) IMÁGENES: SUBIDA SEGURA | Sin archivo (HTTP 400) | Aprobado | log `test_admin` |
| 399 | 8) IMÁGENES: SUBIDA SEGURA | Cliente no puede subir (HTTP 403) | Aprobado | log `test_admin` |
| 400 | 8) IMÁGENES: SUBIDA SEGURA | Admin sin token CSRF no puede subir (HTTP 403) | Aprobado | log `test_admin` |
| 401 | 8) IMÁGENES: SUBIDA SEGURA | Solo quedó 1 archivo subido en img/productos (los rechazados no se guardaron) (1) | Aprobado | log `test_admin` |
| 402 | 9) CATEGORÍAS | Lista las 3 categorías (3) | Aprobado | log `test_admin` |
| 403 | 9) CATEGORÍAS | Cuenta productos por categoría (Celulares: 4 originales + 2 de prueba) (6) | Aprobado | log `test_admin` |
| 404 | 9) CATEGORÍAS | Crear 'Audio y Sonido' (HTTP 201) | Aprobado | log `test_admin` |
| 405 | 9) CATEGORÍAS | Slug generado automáticamente (audio-y-sonido) | Aprobado | log `test_admin` |
| 406 | 9) CATEGORÍAS | Crear 'Cámaras' (slug sin tilde) (HTTP 201) | Aprobado | log `test_admin` |
| 407 | 9) CATEGORÍAS | Slug: camaras (1) | Aprobado | log `test_admin` |
| 408 | 9) CATEGORÍAS | Nombre duplicado (aunque cambien mayúsculas) (HTTP 409) | Aprobado | log `test_admin` |
| 409 | 9) CATEGORÍAS | Slug duplicado (HTTP 409) | Aprobado | log `test_admin` |
| 410 | 9) CATEGORÍAS | Slug con espacios (HTTP 400) | Aprobado | log `test_admin` |
| 411 | 9) CATEGORÍAS | Slug reservado 'todos' (HTTP 400) | Aprobado | log `test_admin` |
| 412 | 9) CATEGORÍAS | Nombre de 1 carácter (HTTP 400) | Aprobado | log `test_admin` |
| 413 | 9) CATEGORÍAS | Nombre de 51 caracteres (HTTP 400) | Aprobado | log `test_admin` |
| 414 | 9) CATEGORÍAS | La tienda ya lista la categoría nueva (True) | Aprobado | log `test_admin` |
| 415 | 9) CATEGORÍAS | …y filtrar por ella responde 0 productos (no 404) (0) | Aprobado | log `test_admin` |
| 416 | 9) CATEGORÍAS | Renombrar (HTTP 200) | Aprobado | log `test_admin` |
| 417 | 9) CATEGORÍAS | MySQL: renombrada (Audio\|audio) | Aprobado | log `test_admin` |
| 418 | 9) CATEGORÍAS | Eliminar 'Celulares' (tiene productos) — FK RESTRICT (HTTP 409) | Aprobado | log `test_admin` |
| 419 | 9) CATEGORÍAS | El mensaje explica cuántos productos tiene | Aprobado | log `test_admin` |
| 420 | 9) CATEGORÍAS | Con un producto movido a 'Audio', tampoco se puede eliminar (HTTP 409) | Aprobado | log `test_admin` |
| 421 | 9) CATEGORÍAS | Vacía: se elimina (HTTP 200) | Aprobado | log `test_admin` |
| 422 | 9) CATEGORÍAS | Eliminar inexistente (HTTP 404) | Aprobado | log `test_admin` |
| 423 | 10) USUARIOS | Nunca expone password_hash | Aprobado | log `test_admin` |
| 424 | 10) USUARIOS | Filtro rol=admin (1) | Aprobado | log `test_admin` |
| 425 | 10) USUARIOS | Búsqueda por correo (1) | Aprobado | log `test_admin` |
| 426 | 10) USUARIOS | Ficha del cliente con sus pedidos (1) | Aprobado | log `test_admin` |
| 427 | 10) USUARIOS | Desactivar al cliente (HTTP 200) | Aprobado | log `test_admin` |
| 428 | 10) USUARIOS | …su sesión abierta deja de valer (HTTP 401) | Aprobado | log `test_admin` |
| 429 | 10) USUARIOS | …y no puede iniciar sesión (HTTP 403) | Aprobado | log `test_admin` |
| 430 | 10) USUARIOS | …sus pedidos se conservan (1) | Aprobado | log `test_admin` |
| 431 | 10) USUARIOS | Reactivar (HTTP 200) | Aprobado | log `test_admin` |
| 432 | 10) USUARIOS | …vuelve a entrar (HTTP 200) | Aprobado | log `test_admin` |
| 433 | 10) USUARIOS | El admin no puede desactivarse a sí mismo (HTTP 409) | Aprobado | log `test_admin` |
| 434 | 10) USUARIOS | …ni quitarse el rol (HTTP 409) | Aprobado | log `test_admin` |
| 435 | 10) USUARIOS | Promover a admin a un cliente (HTTP 200) | Aprobado | log `test_admin` |
| 436 | 10) USUARIOS | …entra al panel sin volver a loguearse (HTTP 200) | Aprobado | log `test_admin` |
| 437 | 10) USUARIOS | El nuevo admin desactiva al admin original (queda él activo) (HTTP 200) | Aprobado | log `test_admin` |
| 438 | 10) USUARIOS | …el admin original pierde el acceso (HTTP 401) | Aprobado | log `test_admin` |
| 439 | 10) USUARIOS | Reactivado, el admin original vuelve a entrar (HTTP 200) | Aprobado | log `test_admin` |
| 440 | 10) USUARIOS | Quitarle el rol al segundo admin (HTTP 200) | Aprobado | log `test_admin` |
| 441 | 10) USUARIOS | …pierde el acceso en el acto (HTTP 403) | Aprobado | log `test_admin` |
| 442 | 10) USUARIOS | Rol inválido (HTTP 400) | Aprobado | log `test_admin` |
| 443 | 10) USUARIOS | Usuario inexistente (HTTP 404) | Aprobado | log `test_admin` |
| 444 | 10) USUARIOS | Acción inválida (no hay cambio de contraseña) (HTTP 400) | Aprobado | log `test_admin` |
| 445 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Lista todos los pedidos (3) | Aprobado | log `test_admin` |
| 446 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Conteo por estado coincide con MySQL (3) | Aprobado | log `test_admin` |
| 447 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Buscar por #id ([3]) | Aprobado | log `test_admin` |
| 448 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Buscar por correo del cliente (todos sus pedidos, del más reciente al más antiguo) ([3, 2]) | Aprobado | log `test_admin` |
| 449 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Detalle: cliente, dirección, línea con precio histórico, siguiente estado (Cliente Dos\|Calle Estados 456\|463.97\|['pagado']) | Aprobado | log `test_admin` |
| 450 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Detalle inexistente (HTTP 404) | Aprobado | log `test_admin` |
| 451 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pendiente → enviado (salteando pagado) (HTTP 409) | Aprobado | log `test_admin` |
| 452 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pendiente → completado (HTTP 409) | Aprobado | log `test_admin` |
| 453 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pendiente → cancelado (reglas pendientes de definir) (HTTP 409) | Aprobado | log `test_admin` |
| 454 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | …el mensaje explica que la cancelación está pendiente de definición | Aprobado | log `test_admin` |
| 455 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | estado inexistente (HTTP 400) | Aprobado | log `test_admin` |
| 456 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pendiente → pagado (pago registrado por el admin) (HTTP 200) | Aprobado | log `test_admin` |
| 457 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pagado → pendiente (hacia atrás) (HTTP 409) | Aprobado | log `test_admin` |
| 458 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pagado → pagado (repetido) (HTTP 409) | Aprobado | log `test_admin` |
| 459 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | pagado → enviado (HTTP 200) | Aprobado | log `test_admin` |
| 460 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | enviado → completado (HTTP 200) | Aprobado | log `test_admin` |
| 461 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | completado → cualquier otro (HTTP 409) | Aprobado | log `test_admin` |
| 462 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | MySQL: completado (completado) | Aprobado | log `test_admin` |
| 463 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | El cliente ve el estado nuevo en Mis Compras (completado) | Aprobado | log `test_admin` |
| 464 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | Cambiar estado no alteró montos ni detalle (463.97\|50.00\|513.97) | Aprobado | log `test_admin` |
| 465 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | 3 'pagado' simultáneos: exactamente 1 aceptado (1) | Aprobado | log `test_admin` |
| 466 | 11) PEDIDOS Y TRANSICIONES DE ESTADO | …y 2 rechazados (409) (2) | Aprobado | log `test_admin` |
| 467 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Ventas confirmadas = Σ total (pagado/enviado/completado) (1470.39) | Aprobado | log `test_admin` |
| 468 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Pedidos confirmados (3) | Aprobado | log `test_admin` |
| 469 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Ticket promedio (490.13) | Aprobado | log `test_admin` |
| 470 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Pedidos pendientes y su monto (1\|429.14) | Aprobado | log `test_admin` |
| 471 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Clientes (2) | Aprobado | log `test_admin` |
| 472 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Productos activos (14) | Aprobado | log `test_admin` |
| 473 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Alertas de stock (activos con stock <= 5) (1) | Aprobado | log `test_admin` |
| 474 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Σ por estado = total de pedidos (4) | Aprobado | log `test_admin` |
| 475 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Σ ventas por categoría = Σ detalle de pedidos confirmados (1344.89) | Aprobado | log `test_admin` |
| 476 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Producto más vendido (unidades confirmadas) (13) | Aprobado | log `test_admin` |
| 477 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Serie diaria de 14 días; pedidos de hoy (14\|4) | Aprobado | log `test_admin` |
| 478 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | Últimos pedidos: el más reciente primero (4) | Aprobado | log `test_admin` |
| 479 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | dias=30 (30) | Aprobado | log `test_admin` |
| 480 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | dias=0 (HTTP 400) | Aprobado | log `test_admin` |
| 481 | 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) | dias=91 (HTTP 400) | Aprobado | log `test_admin` |
| 482 | 13) INTEGRIDAD FINAL | Ningún stock negativo (0) | Aprobado | log `test_admin` |
| 483 | 13) INTEGRIDAD FINAL | Siguen las 3 categorías originales y las 8 tablas (3\|8) | Aprobado | log `test_admin` |
| 484 | 13) INTEGRIDAD FINAL | Hay al menos un administrador activo (1) | Aprobado | log `test_admin` |

## test_concurrencia

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 485 | 0) El servidor procesa solicitudes en paralelo de verdad | dos checkouts con 1 s de espera cada uno terminaron en 1.01 s (< 1.8 s => corrieron en paralelo; intentos: 1.01 s) | Aprobado | log `test_concurrencia` |
| 486 | A) Dos usuarios, 2 unidades c/u, stock 2 (10 rondas) | 10/10 rondas correctas: exactamente 1 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 487 | B) Tres usuarios, 1 unidad c/u, stock 2 (10 rondas) | 10/10 rondas correctas: exactamente 2 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 488 | C) Dos usuarios con los mismos 2 productos (orden de alta invertido), stock de sobra (10 rondas) | 10/10 rondas: ambos checkouts terminan bien (sin deadlock ni 500) y el stock baja 2 en cada producto | Aprobado | log `test_concurrencia` |
| 489 | D) Seis usuarios, 1 unidad c/u, stock 3 (10 rondas) | 10/10 rondas correctas: exactamente 3 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 490 | D) Seis usuarios, 1 unidad c/u, stock 3 (10 rondas) | ningún producto quedó con stock negativo | Aprobado | log `test_concurrencia` |

## test_navegador_bloque3

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 491 | 1) Checkout sin sesión | checkout.html sin sesión redirige a login con next=checkout | Aprobado | log `test_navegador_bloque3` |
| 492 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | el invitado guarda el producto en localStorage | Aprobado | log `test_navegador_bloque3` |
| 493 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | el carrito avisa al invitado que necesitará iniciar sesión | Aprobado | log `test_navegador_bloque3` |
| 494 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | 'Continuar Compra' sin sesión lleva a login con next=checkout (no se compra como invitado) | Aprobado | log `test_navegador_bloque3` |
| 495 | 3) Registro -> fusión del carrito -> vuelta al checkout | tras registrarse vuelve a checkout.html | Aprobado | log `test_navegador_bloque3` |
| 496 | 3) Registro -> fusión del carrito -> vuelta al checkout | el carrito de invitado se fusionó y se limpió localStorage | Aprobado | log `test_navegador_bloque3` |
| 497 | 3) Registro -> fusión del carrito -> vuelta al checkout | el producto del invitado ahora está en el carrito de MySQL | Aprobado | log `test_navegador_bloque3` |
| 498 | 4) Agregar otro producto con sesión y volver al checkout | hay 2 productos en el carrito | Aprobado | log `test_navegador_bloque3` |
| 499 | 4) Agregar otro producto con sesión y volver al checkout | con sesión ya no aparece el aviso de login | Aprobado | log `test_navegador_bloque3` |
| 500 | 4) Agregar otro producto con sesión y volver al checkout | muestra el nombre de la sesión | Aprobado | log `test_navegador_bloque3` |
| 501 | 4) Agregar otro producto con sesión y volver al checkout | muestra el correo de la sesión | Aprobado | log `test_navegador_bloque3` |
| 502 | 4) Agregar otro producto con sesión y volver al checkout | subtotal S/. 923.24 (429.14 + 494.10) | Aprobado | log `test_navegador_bloque3` |
| 503 | 4) Agregar otro producto con sesión y volver al checkout | envío: Gratis | Aprobado | log `test_navegador_bloque3` |
| 504 | 4) Agregar otro producto con sesión y volver al checkout | total S/. 923.24 | Aprobado | log `test_navegador_bloque3` |
| 505 | 4) Agregar otro producto con sesión y volver al checkout | el resumen lista los 2 productos | Aprobado | log `test_navegador_bloque3` |
| 506 | 4) Agregar otro producto con sesión y volver al checkout | la confirmación NO se ve antes de confirmar | Aprobado | log `test_navegador_bloque3` |
| 507 | 5) Validación de la dirección | dirección vacía: muestra el error | Aprobado | log `test_navegador_bloque3` |
| 508 | 5) Validación de la dirección | dirección demasiado corta: muestra el error | Aprobado | log `test_navegador_bloque3` |
| 509 | 5) Validación de la dirección | esos intentos no crearon pedidos | Aprobado | log `test_navegador_bloque3` |
| 510 | 6) Confirmar pedido (bug B-02) | aparece '¡Gracias por tu compra!' | Aprobado | log `test_navegador_bloque3` |
| 511 | 6) Confirmar pedido (bug B-02) | B-02: el bloque formulario+resumen queda oculto de verdad | Aprobado | log `test_navegador_bloque3` |
| 512 | 6) Confirmar pedido (bug B-02) | B-02: el campo de dirección no se ve | Aprobado | log `test_navegador_bloque3` |
| 513 | 6) Confirmar pedido (bug B-02) | B-02: el botón 'Confirmar pedido' no se ve (no se puede confirmar dos veces) | Aprobado | log `test_navegador_bloque3` |
| 514 | 6) Confirmar pedido (bug B-02) | B-02: el resumen del pedido no se ve | Aprobado | log `test_navegador_bloque3` |
| 515 | 6) Confirmar pedido (bug B-02) | muestra el número de pedido (#1) | Aprobado | log `test_navegador_bloque3` |
| 516 | 6) Confirmar pedido (bug B-02) | el contador del header queda en 0 | Aprobado | log `test_navegador_bloque3` |
| 517 | 7) Carrito vacío tras el pedido | el carrito quedó vacío en MySQL | Aprobado | log `test_navegador_bloque3` |
| 518 | 7) Carrito vacío tras el pedido | la página muestra 'Carrito Vacío' | Aprobado | log `test_navegador_bloque3` |
| 519 | 7) Carrito vacío tras el pedido | y no muestra productos | Aprobado | log `test_navegador_bloque3` |
| 520 | 8) Mis Compras y detalle | lista 1 pedido | Aprobado | log `test_navegador_bloque3` |
| 521 | 8) Mis Compras y detalle | es el pedido recién creado | Aprobado | log `test_navegador_bloque3` |
| 522 | 8) Mis Compras y detalle | estado 'Pendiente' | Aprobado | log `test_navegador_bloque3` |
| 523 | 8) Mis Compras y detalle | total S/. 923.24 | Aprobado | log `test_navegador_bloque3` |
| 524 | 8) Mis Compras y detalle | el detalle muestra los 2 productos | Aprobado | log `test_navegador_bloque3` |
| 525 | 8) Mis Compras y detalle | el detalle muestra la dirección | Aprobado | log `test_navegador_bloque3` |
| 526 | 8) Mis Compras y detalle | cantidades y precios unitarios correctos (['1 × S/. 429.14', '1 × S/. 494.10']) | Aprobado | log `test_navegador_bloque3` |
| 527 | 8) Mis Compras y detalle | el modal se cierra con la × | Aprobado | log `test_navegador_bloque3` |
| 528 | 8) Mis Compras y detalle | tras cerrarlo, la lista sigue siendo clickeable (el modal no bloquea) | Aprobado | log `test_navegador_bloque3` |
| 529 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | ahora hay 2 pedidos | Aprobado | log `test_navegador_bloque3` |
| 530 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | el más reciente aparece primero | Aprobado | log `test_navegador_bloque3` |
| 531 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | no se ejecutó el código inyectado | Aprobado | log `test_navegador_bloque3` |
| 532 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | no se creó ningún <img> a partir de la dirección | Aprobado | log `test_navegador_bloque3` |
| 533 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | la dirección aparece como texto literal | Aprobado | log `test_navegador_bloque3` |
| 534 | 10) Cerrar sesión: un solo POST de logout | se envió exactamente un POST de logout (enviados: ['POST']) | Aprobado | log `test_navegador_bloque3` |
| 535 | 10) Cerrar sesión: un solo POST de logout | la sesión quedó cerrada en el servidor | Aprobado | log `test_navegador_bloque3` |
| 536 | 11) next=mis-compras: login y vuelta a Mis Compras | Mis Compras sin sesión redirige a login con next=mis-compras | Aprobado | log `test_navegador_bloque3` |
| 537 | 11) next=mis-compras: login y vuelta a Mis Compras | tras el login vuelve a Mis Compras y ve sus 2 pedidos | Aprobado | log `test_navegador_bloque3` |
| 538 | 12) Open redirect: destinos externos o raros terminan en index.html | next='https://evil.example/' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 539 | 12) Open redirect: destinos externos o raros terminan en index.html | next='//evil.example' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 540 | 12) Open redirect: destinos externos o raros terminan en index.html | next='javascript:alert(1)' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 541 | 12) Open redirect: destinos externos o raros terminan en index.html | next='../../../etc/passwd' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 542 | 12) Open redirect: destinos externos o raros terminan en index.html | next='checkout.html' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 543 | 12) Open redirect: destinos externos o raros terminan en index.html | next='CHECKOUT' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 544 | 12) Open redirect: destinos externos o raros terminan en index.html | cero errores de JavaScript en todo el flujo | Aprobado | log `test_navegador_bloque3` |
| 545 | 12) Open redirect: destinos externos o raros terminan en index.html | cero errores en la consola del navegador | Aprobado | log `test_navegador_bloque3` |
| 546 | 12) Open redirect: destinos externos o raros terminan en index.html | ninguna respuesta 5xx del servidor | Aprobado | log `test_navegador_bloque3` |

## test_navegador_admin

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 547 | 1) Acceso: sin sesión y como cliente | sin sesión, el panel redirige a login con next=admin | Aprobado | log `test_navegador_admin` |
| 548 | 1) Acceso: sin sesión y como cliente | un cliente ve 'No tenés permiso' en el panel | Aprobado | log `test_navegador_admin` |
| 549 | 1) Acceso: sin sesión y como cliente | …y no ve el menú ni las secciones | Aprobado | log `test_navegador_admin` |
| 550 | 1) Acceso: sin sesión y como cliente | forzar la petición desde el navegador del cliente: 403 (obtuvo 403) | Aprobado | log `test_navegador_admin` |
| 551 | 1) Acceso: sin sesión y como cliente | el cliente no tiene el enlace 'Panel' en la tienda | Aprobado | log `test_navegador_admin` |
| 552 | 2) Admin: login con next=admin, dashboard | el dashboard muestra 6 indicadores | Aprobado | log `test_navegador_admin` |
| 553 | 2) Admin: login con next=admin, dashboard | los indicadores son los del servidor | Aprobado | log `test_navegador_admin` |
| 554 | 2) Admin: login con next=admin, dashboard | contador de pendientes en el menú | Aprobado | log `test_navegador_admin` |
| 555 | 2) Admin: login con next=admin, dashboard | el administrador ve el enlace 'Panel' en la tienda | Aprobado | log `test_navegador_admin` |
| 556 | 3) Inventario: filtros y ajuste de stock | filtro 'Stock bajo' deja solo el Galaxy Book3 | Aprobado | log `test_navegador_admin` |
| 557 | 3) Inventario: filtros y ajuste de stock | sumar 10 al stock 2 → 12 en MySQL (obtuvo 12) | Aprobado | log `test_navegador_admin` |
| 558 | 3) Inventario: filtros y ajuste de stock | cantidad decimal: error en la fila, sin enviar | Aprobado | log `test_navegador_admin` |
| 559 | 3) Inventario: filtros y ajuste de stock | restar más que el stock: muestra el error del servidor (No se puede restar 999: el stock actual de "Samsung Galaxy B…) | Aprobado | log `test_navegador_admin` |
| 560 | 3) Inventario: filtros y ajuste de stock | …y el stock sigue en 12 | Aprobado | log `test_navegador_admin` |
| 561 | 4) Productos: crear con imagen subida y nombre con HTML | guardar vacío: muestra el error de validación | Aprobado | log `test_navegador_admin` |
| 562 | 4) Productos: crear con imagen subida y nombre con HTML | vista previa del precio final (150.50 − 10 %) | Aprobado | log `test_navegador_admin` |
| 563 | 4) Productos: crear con imagen subida y nombre con HTML | imagen subida y agregada (img/productos/4c7b40f48437e11fd5018590.png) | Aprobado | log `test_navegador_admin` |
| 564 | 4) Productos: crear con imagen subida y nombre con HTML | se agregó además una imagen existente | Aprobado | log `test_navegador_admin` |
| 565 | 4) Productos: crear con imagen subida y nombre con HTML | el producto quedó creado | Aprobado | log `test_navegador_admin` |
| 566 | 4) Productos: crear con imagen subida y nombre con HTML | con precio, stock, envío e imagen principal correctos | Aprobado | log `test_navegador_admin` |
| 567 | 4) Productos: crear con imagen subida y nombre con HTML | el nombre con HTML no se ejecutó en el panel | Aprobado | log `test_navegador_admin` |
| 568 | 4) Productos: crear con imagen subida y nombre con HTML | …y se ve como texto literal en la tabla | Aprobado | log `test_navegador_admin` |
| 569 | 5) Productos: editar, desactivar, eliminar | editar el precio: 199.90 sin descuento | Aprobado | log `test_navegador_admin` |
| 570 | 5) Productos: editar, desactivar, eliminar | desactivado: la tienda ya no lo muestra | Aprobado | log `test_navegador_admin` |
| 571 | 5) Productos: editar, desactivar, eliminar | sin ventas: se eliminó definitivamente | Aprobado | log `test_navegador_admin` |
| 572 | 5) Productos: editar, desactivar, eliminar | el Samsung A04s tiene ventas: el diálogo avisa que se desactivará | Aprobado | log `test_navegador_admin` |
| 573 | 5) Productos: editar, desactivar, eliminar | cancelar no cambia nada | Aprobado | log `test_navegador_admin` |
| 574 | 6) Categorías | categoría 'Audio' creada | Aprobado | log `test_navegador_admin` |
| 575 | 6) Categorías | eliminar 'Celulares' (con productos) se impide con un aviso | Aprobado | log `test_navegador_admin` |
| 576 | 6) Categorías | la tienda muestra la categoría nueva en el menú | Aprobado | log `test_navegador_admin` |
| 577 | 6) Categorías | categoría vacía eliminada | Aprobado | log `test_navegador_admin` |
| 578 | 7) Pedidos: detalle y avance de estado | el detalle muestra la dirección | Aprobado | log `test_navegador_admin` |
| 579 | 7) Pedidos: detalle y avance de estado | no se ofrece cancelar (regla pendiente de definición) | Aprobado | log `test_navegador_admin` |
| 580 | 7) Pedidos: detalle y avance de estado | Registrar pago → 'pagado' en MySQL | Aprobado | log `test_navegador_admin` |
| 581 | 7) Pedidos: detalle y avance de estado | Marcar como enviado → 'enviado' | Aprobado | log `test_navegador_admin` |
| 582 | 7) Pedidos: detalle y avance de estado | tras cerrar el diálogo el menú sigue respondiendo (no queda una capa bloqueando) | Aprobado | log `test_navegador_admin` |
| 583 | 7) Pedidos: detalle y avance de estado | el cliente ve 'enviado' en Mis Compras | Aprobado | log `test_navegador_admin` |
| 584 | 8) Usuarios: desactivar y reactivar al cliente | cliente desactivado: su sesión deja de valer | Aprobado | log `test_navegador_admin` |
| 585 | 8) Usuarios: desactivar y reactivar al cliente | reactivado | Aprobado | log `test_navegador_admin` |
| 586 | 8) Usuarios: desactivar y reactivar al cliente | el admin no tiene botones para cambiarse a sí mismo | Aprobado | log `test_navegador_admin` |
| 587 | 9) Estadísticas y teclado | serie de 14 días | Aprobado | log `test_navegador_admin` |
| 588 | 9) Estadísticas y teclado | Enter abre el detalle; Escape lo cierra y el foco vuelve al botón | Aprobado | log `test_navegador_admin` |
| 589 | 10) La tienda escapa nombres con HTML (XSS almacenado) | catálogo, detalle, carrito y checkout no ejecutaron el HTML del nombre/proveedor | Aprobado | log `test_navegador_admin` |
| 590 | 10) La tienda escapa nombres con HTML (XSS almacenado) | el nombre se ve literal en el checkout | Aprobado | log `test_navegador_admin` |
| 591 | 10) La tienda escapa nombres con HTML (XSS almacenado) | cero errores de JavaScript | Aprobado | log `test_navegador_admin` |
| 592 | 10) La tienda escapa nombres con HTML (XSS almacenado) | sin errores de consola inesperados ([]) | Aprobado | log `test_navegador_admin` |
| 593 | 10) La tienda escapa nombres con HTML (XSS almacenado) | ninguna respuesta 5xx | Aprobado | log `test_navegador_admin` |

## test_responsive

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 594 | 375 px | 375px catalogo: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 595 | 375 px | 375px catalogo: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 596 | 375 px | 375px catalogo: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 597 | 375 px | 375px catalogo: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 598 | 375 px | 375px detalle-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 599 | 375 px | 375px detalle-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 600 | 375 px | 375px detalle-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 601 | 375 px | 375px detalle-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 602 | 375 px | 375px login: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 603 | 375 px | 375px login: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 604 | 375 px | 375px login: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 605 | 375 px | 375px login: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 606 | 375 px | 375px carrito: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 607 | 375 px | 375px carrito: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 608 | 375 px | 375px carrito: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 609 | 375 px | 375px carrito: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 610 | 375 px | 375px checkout: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 611 | 375 px | 375px checkout: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 612 | 375 px | 375px checkout: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 613 | 375 px | 375px checkout: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 614 | 375 px | 375px mis-compras: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 615 | 375 px | 375px mis-compras: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 616 | 375 px | 375px mis-compras: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 617 | 375 px | 375px mis-compras: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 618 | 375 px | 375px detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 619 | 375 px | 375px detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 620 | 375 px | 375px detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 621 | 375 px | 375px detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 622 | 375 px | 375px admin-dashboard: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 623 | 375 px | 375px admin-dashboard: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 624 | 375 px | 375px admin-dashboard: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 625 | 375 px | 375px admin-dashboard: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 626 | 375 px | 375px admin-pedidos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 627 | 375 px | 375px admin-pedidos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 628 | 375 px | 375px admin-pedidos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 629 | 375 px | 375px admin-pedidos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 630 | 375 px | 375px admin-productos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 631 | 375 px | 375px admin-productos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 632 | 375 px | 375px admin-productos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 633 | 375 px | 375px admin-productos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 634 | 375 px | 375px admin-inventario: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 635 | 375 px | 375px admin-inventario: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 636 | 375 px | 375px admin-inventario: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 637 | 375 px | 375px admin-inventario: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 638 | 375 px | 375px admin-categorias: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 639 | 375 px | 375px admin-categorias: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 640 | 375 px | 375px admin-categorias: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 641 | 375 px | 375px admin-categorias: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 642 | 375 px | 375px admin-usuarios: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 643 | 375 px | 375px admin-usuarios: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 644 | 375 px | 375px admin-usuarios: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 645 | 375 px | 375px admin-usuarios: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 646 | 375 px | 375px admin-estadisticas: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 647 | 375 px | 375px admin-estadisticas: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 648 | 375 px | 375px admin-estadisticas: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 649 | 375 px | 375px admin-estadisticas: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 650 | 375 px | 375px admin-form-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 651 | 375 px | 375px admin-form-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 652 | 375 px | 375px admin-form-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 653 | 375 px | 375px admin-form-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 654 | 375 px | 375px admin-detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 655 | 375 px | 375px admin-detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 656 | 375 px | 375px admin-detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 657 | 375 px | 375px admin-detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 658 | 375 px | 375px: cero errores de JavaScript | Aprobado | log `test_responsive` |
| 659 | 768 px | 768px catalogo: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 660 | 768 px | 768px catalogo: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 661 | 768 px | 768px catalogo: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 662 | 768 px | 768px catalogo: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 663 | 768 px | 768px detalle-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 664 | 768 px | 768px detalle-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 665 | 768 px | 768px detalle-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 666 | 768 px | 768px detalle-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 667 | 768 px | 768px login: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 668 | 768 px | 768px login: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 669 | 768 px | 768px login: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 670 | 768 px | 768px login: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 671 | 768 px | 768px carrito: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 672 | 768 px | 768px carrito: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 673 | 768 px | 768px carrito: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 674 | 768 px | 768px carrito: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 675 | 768 px | 768px checkout: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 676 | 768 px | 768px checkout: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 677 | 768 px | 768px checkout: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 678 | 768 px | 768px checkout: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 679 | 768 px | 768px mis-compras: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 680 | 768 px | 768px mis-compras: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 681 | 768 px | 768px mis-compras: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 682 | 768 px | 768px mis-compras: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 683 | 768 px | 768px detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 684 | 768 px | 768px detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 685 | 768 px | 768px detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 686 | 768 px | 768px detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 687 | 768 px | 768px admin-dashboard: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 688 | 768 px | 768px admin-dashboard: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 689 | 768 px | 768px admin-dashboard: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 690 | 768 px | 768px admin-dashboard: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 691 | 768 px | 768px admin-pedidos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 692 | 768 px | 768px admin-pedidos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 693 | 768 px | 768px admin-pedidos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 694 | 768 px | 768px admin-pedidos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 695 | 768 px | 768px admin-productos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 696 | 768 px | 768px admin-productos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 697 | 768 px | 768px admin-productos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 698 | 768 px | 768px admin-productos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 699 | 768 px | 768px admin-inventario: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 700 | 768 px | 768px admin-inventario: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 701 | 768 px | 768px admin-inventario: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 702 | 768 px | 768px admin-inventario: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 703 | 768 px | 768px admin-categorias: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 704 | 768 px | 768px admin-categorias: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 705 | 768 px | 768px admin-categorias: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 706 | 768 px | 768px admin-categorias: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 707 | 768 px | 768px admin-usuarios: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 708 | 768 px | 768px admin-usuarios: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 709 | 768 px | 768px admin-usuarios: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 710 | 768 px | 768px admin-usuarios: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 711 | 768 px | 768px admin-estadisticas: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 712 | 768 px | 768px admin-estadisticas: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 713 | 768 px | 768px admin-estadisticas: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 714 | 768 px | 768px admin-estadisticas: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 715 | 768 px | 768px admin-form-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 716 | 768 px | 768px admin-form-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 717 | 768 px | 768px admin-form-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 718 | 768 px | 768px admin-form-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 719 | 768 px | 768px admin-detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 720 | 768 px | 768px admin-detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 721 | 768 px | 768px admin-detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 722 | 768 px | 768px admin-detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 723 | 768 px | 768px: cero errores de JavaScript | Aprobado | log `test_responsive` |
| 724 | 1024 px | 1024px catalogo: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 725 | 1024 px | 1024px catalogo: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 726 | 1024 px | 1024px catalogo: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 727 | 1024 px | 1024px catalogo: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 728 | 1024 px | 1024px detalle-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 729 | 1024 px | 1024px detalle-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 730 | 1024 px | 1024px detalle-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 731 | 1024 px | 1024px detalle-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 732 | 1024 px | 1024px login: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 733 | 1024 px | 1024px login: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 734 | 1024 px | 1024px login: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 735 | 1024 px | 1024px login: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 736 | 1024 px | 1024px carrito: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 737 | 1024 px | 1024px carrito: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 738 | 1024 px | 1024px carrito: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 739 | 1024 px | 1024px carrito: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 740 | 1024 px | 1024px checkout: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 741 | 1024 px | 1024px checkout: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 742 | 1024 px | 1024px checkout: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 743 | 1024 px | 1024px checkout: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 744 | 1024 px | 1024px mis-compras: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 745 | 1024 px | 1024px mis-compras: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 746 | 1024 px | 1024px mis-compras: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 747 | 1024 px | 1024px mis-compras: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 748 | 1024 px | 1024px detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 749 | 1024 px | 1024px detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 750 | 1024 px | 1024px detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 751 | 1024 px | 1024px detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 752 | 1024 px | 1024px admin-dashboard: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 753 | 1024 px | 1024px admin-dashboard: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 754 | 1024 px | 1024px admin-dashboard: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 755 | 1024 px | 1024px admin-dashboard: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 756 | 1024 px | 1024px admin-pedidos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 757 | 1024 px | 1024px admin-pedidos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 758 | 1024 px | 1024px admin-pedidos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 759 | 1024 px | 1024px admin-pedidos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 760 | 1024 px | 1024px admin-productos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 761 | 1024 px | 1024px admin-productos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 762 | 1024 px | 1024px admin-productos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 763 | 1024 px | 1024px admin-productos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 764 | 1024 px | 1024px admin-inventario: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 765 | 1024 px | 1024px admin-inventario: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 766 | 1024 px | 1024px admin-inventario: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 767 | 1024 px | 1024px admin-inventario: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 768 | 1024 px | 1024px admin-categorias: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 769 | 1024 px | 1024px admin-categorias: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 770 | 1024 px | 1024px admin-categorias: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 771 | 1024 px | 1024px admin-categorias: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 772 | 1024 px | 1024px admin-usuarios: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 773 | 1024 px | 1024px admin-usuarios: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 774 | 1024 px | 1024px admin-usuarios: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 775 | 1024 px | 1024px admin-usuarios: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 776 | 1024 px | 1024px admin-estadisticas: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 777 | 1024 px | 1024px admin-estadisticas: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 778 | 1024 px | 1024px admin-estadisticas: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 779 | 1024 px | 1024px admin-estadisticas: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 780 | 1024 px | 1024px admin-form-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 781 | 1024 px | 1024px admin-form-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 782 | 1024 px | 1024px admin-form-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 783 | 1024 px | 1024px admin-form-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 784 | 1024 px | 1024px admin-detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 785 | 1024 px | 1024px admin-detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 786 | 1024 px | 1024px admin-detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 787 | 1024 px | 1024px admin-detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 788 | 1024 px | 1024px: cero errores de JavaScript | Aprobado | log `test_responsive` |
| 789 | 1400 px | 1400px catalogo: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 790 | 1400 px | 1400px catalogo: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 791 | 1400 px | 1400px catalogo: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 792 | 1400 px | 1400px catalogo: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 793 | 1400 px | 1400px detalle-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 794 | 1400 px | 1400px detalle-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 795 | 1400 px | 1400px detalle-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 796 | 1400 px | 1400px detalle-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 797 | 1400 px | 1400px login: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 798 | 1400 px | 1400px login: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 799 | 1400 px | 1400px login: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 800 | 1400 px | 1400px login: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 801 | 1400 px | 1400px carrito: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 802 | 1400 px | 1400px carrito: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 803 | 1400 px | 1400px carrito: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 804 | 1400 px | 1400px carrito: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 805 | 1400 px | 1400px checkout: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 806 | 1400 px | 1400px checkout: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 807 | 1400 px | 1400px checkout: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 808 | 1400 px | 1400px checkout: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 809 | 1400 px | 1400px mis-compras: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 810 | 1400 px | 1400px mis-compras: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 811 | 1400 px | 1400px mis-compras: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 812 | 1400 px | 1400px mis-compras: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 813 | 1400 px | 1400px detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 814 | 1400 px | 1400px detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 815 | 1400 px | 1400px detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 816 | 1400 px | 1400px detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 817 | 1400 px | 1400px admin-dashboard: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 818 | 1400 px | 1400px admin-dashboard: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 819 | 1400 px | 1400px admin-dashboard: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 820 | 1400 px | 1400px admin-dashboard: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 821 | 1400 px | 1400px admin-pedidos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 822 | 1400 px | 1400px admin-pedidos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 823 | 1400 px | 1400px admin-pedidos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 824 | 1400 px | 1400px admin-pedidos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 825 | 1400 px | 1400px admin-productos: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 826 | 1400 px | 1400px admin-productos: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 827 | 1400 px | 1400px admin-productos: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 828 | 1400 px | 1400px admin-productos: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 829 | 1400 px | 1400px admin-inventario: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 830 | 1400 px | 1400px admin-inventario: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 831 | 1400 px | 1400px admin-inventario: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 832 | 1400 px | 1400px admin-inventario: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 833 | 1400 px | 1400px admin-categorias: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 834 | 1400 px | 1400px admin-categorias: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 835 | 1400 px | 1400px admin-categorias: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 836 | 1400 px | 1400px admin-categorias: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 837 | 1400 px | 1400px admin-usuarios: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 838 | 1400 px | 1400px admin-usuarios: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 839 | 1400 px | 1400px admin-usuarios: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 840 | 1400 px | 1400px admin-usuarios: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 841 | 1400 px | 1400px admin-estadisticas: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 842 | 1400 px | 1400px admin-estadisticas: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 843 | 1400 px | 1400px admin-estadisticas: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 844 | 1400 px | 1400px admin-estadisticas: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 845 | 1400 px | 1400px admin-form-producto: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 846 | 1400 px | 1400px admin-form-producto: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 847 | 1400 px | 1400px admin-form-producto: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 848 | 1400 px | 1400px admin-form-producto: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 849 | 1400 px | 1400px admin-detalle-pedido: sin scroll horizontal (sobra 0 px) | Aprobado | log `test_responsive` |
| 850 | 1400 px | 1400px admin-detalle-pedido: ningún elemento se sale de la pantalla | Aprobado | log `test_responsive` |
| 851 | 1400 px | 1400px admin-detalle-pedido: campos de formulario de 120 px o más | Aprobado | log `test_responsive` |
| 852 | 1400 px | 1400px admin-detalle-pedido: botones y enlaces de 24×24 px o más | Aprobado | log `test_responsive` |
| 853 | 1400 px | 1400px: cero errores de JavaScript | Aprobado | log `test_responsive` |
