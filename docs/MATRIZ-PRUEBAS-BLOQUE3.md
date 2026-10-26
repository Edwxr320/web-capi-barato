# Matriz de pruebas — resultados reales

Generada automáticamente por `tests/generar_matriz.py` a partir de `run_all-bloque3.log` (ejecución del 2026-10-24 03:03). Cada fila es una aserción que se ejecutó de verdad contra PHP + MariaDB y/o Chromium; la columna *Evidencia* indica la suite y la sección del log donde está la salida.

**Total: 233 aprobadas, 0 fallidas, en 6 suites.**

| Suite | Aprobadas | Fallidas | Resultado del log |
|---|---:|---:|---|
| `test_auth` | 24 | 0 | RESULTADO FINAL: 24 pruebas OK, 0 con FALLA |
| `test_bloque1` | 25 | 0 | RESULTADO FINAL: 25 pruebas OK, 0 con FALLA |
| `test_bloque2` | 31 | 0 | RESULTADO FINAL: 31 pruebas OK, 0 con FALLA |
| `test_bloque3` | 91 | 0 | RESULTADO FINAL: 91 pruebas OK, 0 con FALLA |
| `test_concurrencia` | 6 | 0 | RESULTADO FINAL: 6 pruebas OK, 0 con FALLA |
| `test_navegador_bloque3` | 56 | 0 | RESULTADO FINAL: 56 pruebas OK, 0 con FALLA |

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
| 17 | 4) PROTECCIÓN POR ROL | admin con sesión de admin (501 = pasa la protección, endpoint aún pendiente) (HTTP 501) | Aprobado | log `test_auth` |
| 18 | 4) PROTECCIÓN POR ROL | carrito sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 19 | 4) PROTECCIÓN POR ROL | carrito con sesión de cliente (HTTP 200) | Aprobado | log `test_auth` |
| 20 | 4) PROTECCIÓN POR ROL | pedidos sin sesión (HTTP 401) | Aprobado | log `test_auth` |
| 21 | 4) PROTECCIÓN POR ROL | catálogo público (HTTP 200) | Aprobado | log `test_auth` |
| 22 | 5) LOGOUT | Logout (HTTP 200) | Aprobado | log `test_auth` |
| 23 | 5) LOGOUT | La sesión quedó destruida en el servidor (False) | Aprobado | log `test_auth` |
| 24 | 5) LOGOUT | carrito con la cookie vieja (HTTP 401) | Aprobado | log `test_auth` |

## test_bloque1

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 25 | CATEGORÍAS | Listar (HTTP 200) | Aprobado | log `test_bloque1` |
| 26 | CATEGORÍAS | Son 3 (3) | Aprobado | log `test_bloque1` |
| 27 | PRODUCTOS: LISTAR | Catálogo completo (12) | Aprobado | log `test_bloque1` |
| 28 | PRODUCTOS: LISTAR | Filtro celulares (4) | Aprobado | log `test_bloque1` |
| 29 | PRODUCTOS: LISTAR | Categoría inexistente (HTTP 404) | Aprobado | log `test_bloque1` |
| 30 | PRODUCTOS: LISTAR | POST rechazado (HTTP 405) | Aprobado | log `test_bloque1` |
| 31 | PRODUCTOS: DETALLE | Producto 1: 3 imágenes (3) | Aprobado | log `test_bloque1` |
| 32 | PRODUCTOS: DETALLE | Producto 1: precio final (429.14) | Aprobado | log `test_bloque1` |
| 33 | PRODUCTOS: DETALLE | Inexistente (HTTP 404) | Aprobado | log `test_bloque1` |
| 34 | PRODUCTOS: DETALLE | id no numérico (HTTP 400) | Aprobado | log `test_bloque1` |
| 35 | PRODUCTOS: DETALLE | Falta id (HTTP 400) | Aprobado | log `test_bloque1` |
| 36 | PRODUCTOS: DETALLE | Inyección SQL en id (HTTP 400) | Aprobado | log `test_bloque1` |
| 37 | PRODUCTOS: DETALLE | La tabla productos sigue intacta (12) | Aprobado | log `test_bloque1` |
| 38 | PRODUCTOS: BUSCAR | samsung (4) | Aprobado | log `test_bloque1` |
| 39 | PRODUCTOS: BUSCAR | SAMSUNG (mayúsculas) (4) | Aprobado | log `test_bloque1` |
| 40 | PRODUCTOS: BUSCAR | Por categoría: notebooks (4) | Aprobado | log `test_bloque1` |
| 41 | PRODUCTOS: BUSCAR | Por característica (bluetooth) | Aprobado | log `test_bloque1` |
| 42 | PRODUCTOS: BUSCAR | Sin resultados (0) | Aprobado | log `test_bloque1` |
| 43 | PRODUCTOS: BUSCAR | Falta q (HTTP 400) | Aprobado | log `test_bloque1` |
| 44 | PRODUCTOS: BUSCAR | Inyección SQL en q no rompe la consulta (HTTP 200) | Aprobado | log `test_bloque1` |
| 45 | PRODUCTOS: BUSCAR | …y se trata como texto literal (0 resultados) (0) | Aprobado | log `test_bloque1` |
| 46 | PÚBLICOS (no exigen sesión) | categorias/listar.php es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 47 | PÚBLICOS (no exigen sesión) | productos/listar.php es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 48 | PÚBLICOS (no exigen sesión) | productos/detalle.php?id=1 es público (HTTP 200) | Aprobado | log `test_bloque1` |
| 49 | PÚBLICOS (no exigen sesión) | productos/buscar.php?q=moto es público (HTTP 200) | Aprobado | log `test_bloque1` |

## test_bloque2

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 50 | 1) CARRITO VACÍO | Carrito vacío (HTTP 200) | Aprobado | log `test_bloque2` |
| 51 | 1) CARRITO VACÍO | Resumen en cero (0) | Aprobado | log `test_bloque2` |
| 52 | 2) AGREGAR | Agregar producto válido (HTTP 201) | Aprobado | log `test_bloque2` |
| 53 | 2) AGREGAR | Producto repetido: suma (1+2) (3) | Aprobado | log `test_bloque2` |
| 54 | 2) AGREGAR | …y no duplica la fila (1) | Aprobado | log `test_bloque2` |
| 55 | 2) AGREGAR | Producto inexistente (HTTP 404) | Aprobado | log `test_bloque2` |
| 56 | 2) AGREGAR | Más que el stock (producto 5, stock 2) (HTTP 409) | Aprobado | log `test_bloque2` |
| 57 | 2) AGREGAR | Sin sesión (HTTP 401) | Aprobado | log `test_bloque2` |
| 58 | 3) ACTUALIZAR | Cantidad válida (HTTP 200) | Aprobado | log `test_bloque2` |
| 59 | 3) ACTUALIZAR | Cantidad 0 (HTTP 400) | Aprobado | log `test_bloque2` |
| 60 | 3) ACTUALIZAR | Cantidad negativa (HTTP 400) | Aprobado | log `test_bloque2` |
| 61 | 3) ACTUALIZAR | Cantidad decimal (HTTP 400) | Aprobado | log `test_bloque2` |
| 62 | 3) ACTUALIZAR | Superior al stock (HTTP 409) | Aprobado | log `test_bloque2` |
| 63 | 3) ACTUALIZAR | Producto inexistente (HTTP 404) | Aprobado | log `test_bloque2` |
| 64 | 3) ACTUALIZAR | Los rechazos no modificaron nada (sigue en 5) (5) | Aprobado | log `test_bloque2` |
| 65 | 4) SEGURIDAD: carrito ajeno | A no puede actualizar el producto 4 de B (HTTP 404) | Aprobado | log `test_bloque2` |
| 66 | 4) SEGURIDAD: carrito ajeno | A no puede eliminar el producto 4 de B (HTTP 404) | Aprobado | log `test_bloque2` |
| 67 | 4) SEGURIDAD: carrito ajeno | El carrito de B quedó intacto ({4: 1}) | Aprobado | log `test_bloque2` |
| 68 | 4) SEGURIDAD: carrito ajeno | Inyección SQL en id_producto (HTTP 400) | Aprobado | log `test_bloque2` |
| 69 | 5) ELIMINAR | Eliminar propio (HTTP 200) | Aprobado | log `test_bloque2` |
| 70 | 5) ELIMINAR | Eliminar de nuevo (HTTP 404) | Aprobado | log `test_bloque2` |
| 71 | 6) PERSISTENCIA (logout + login) | Tras logout no accesible (HTTP 401) | Aprobado | log `test_bloque2` |
| 72 | 6) PERSISTENCIA (logout + login) | El carrito persistió en MySQL ({2: 3}) | Aprobado | log `test_bloque2` |
| 73 | 7) FUSIÓN | Fusionar (HTTP 200) | Aprobado | log `test_bloque2` |
| 74 | 7) FUSIÓN | 2 suma (3+2), 6 nuevo, 5 limitado al stock, 'abc' ignorado ({2: 5, 5: 2, 6: 1}) | Aprobado | log `test_bloque2` |
| 75 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Subtotal (2 × 521.10) (1042.2) | Aprobado | log `test_bloque2` |
| 76 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Envío (S/50, no se multiplica por 2) (50) | Aprobado | log `test_bloque2` |
| 77 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Total (1092.2) | Aprobado | log `test_bloque2` |
| 78 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Envío con 50 y 100 en el carrito = el MAYOR (100), no la suma (100) | Aprobado | log `test_bloque2` |
| 79 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | …y no cambia al subir la cantidad (100) | Aprobado | log `test_bloque2` |
| 80 | 8) CÁLCULO: subtotal, descuento (precio final), envío, total | Todos con envío gratis = S/0.00 (0) | Aprobado | log `test_bloque2` |

## test_bloque3

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 81 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Carrito vacío (HTTP 400) | Aprobado | log `test_bloque3` |
| 82 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 83 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Cookie de sesión inventada (HTTP 401) | Aprobado | log `test_bloque3` |
| 84 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección ausente (HTTP 400) | Aprobado | log `test_bloque3` |
| 85 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección vacía (HTTP 400) | Aprobado | log `test_bloque3` |
| 86 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección solo con espacios (HTTP 400) | Aprobado | log `test_bloque3` |
| 87 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección demasiado corta (2 caracteres) (HTTP 400) | Aprobado | log `test_bloque3` |
| 88 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección como lista (tipo inválido) (HTTP 400) | Aprobado | log `test_bloque3` |
| 89 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección como número (tipo inválido) (HTTP 400) | Aprobado | log `test_bloque3` |
| 90 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Dirección de 256 caracteres (máximo 255) (HTTP 400) | Aprobado | log `test_bloque3` |
| 91 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Cuerpo que no es JSON (HTTP 400) | Aprobado | log `test_bloque3` |
| 92 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | Ninguno de esos rechazos creó un pedido (0) | Aprobado | log `test_bloque3` |
| 93 | 1) CHECKOUT: carrito vacío / sin sesión / dirección | …ni vació el carrito de A (1) | Aprobado | log `test_bloque3` |
| 94 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Checkout (HTTP 201) | Aprobado | log `test_bloque3` |
| 95 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido existe (1) | Aprobado | log `test_bloque3` |
| 96 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Detalle existe (1 línea) (1) | Aprobado | log `test_bloque3` |
| 97 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Detalle: cantidad 1, precio unitario 429.14, subtotal 429.14 (1\|429.14\|429.14) | Aprobado | log `test_bloque3` |
| 98 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Stock descontado (99) | Aprobado | log `test_bloque3` |
| 99 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Carrito vaciado (0) | Aprobado | log `test_bloque3` |
| 100 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido: subtotal\|envío\|total (429.14\|0.00\|429.14) | Aprobado | log `test_bloque3` |
| 101 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | total = subtotal + envío, y subtotal = suma del detalle (1) | Aprobado | log `test_bloque3` |
| 102 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Estado inicial (pendiente) | Aprobado | log `test_bloque3` |
| 103 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Pedido de A pertenece a A (2) | Aprobado | log `test_bloque3` |
| 104 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Dirección guardada (Av. Javier Prado 456, San Isidro, Lima) | Aprobado | log `test_bloque3` |
| 105 | 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL | Repetir el checkout con el carrito ya vacío (HTTP 400) | Aprobado | log `test_bloque3` |
| 106 | 3) PRECIO HISTÓRICO | El catálogo ya muestra el precio nuevo (999) | Aprobado | log `test_bloque3` |
| 107 | 3) PRECIO HISTÓRICO | El pedido conserva el precio unitario de la compra (429.14) | Aprobado | log `test_bloque3` |
| 108 | 3) PRECIO HISTÓRICO | …y el total original (429.14) | Aprobado | log `test_bloque3` |
| 109 | 3) PRECIO HISTÓRICO | En MySQL detalle_pedido.precio_unitario no cambió (429.14) | Aprobado | log `test_bloque3` |
| 110 | 3) PRECIO HISTÓRICO | Precio del producto 1 restaurado (429.14) | Aprobado | log `test_bloque3` |
| 111 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | Stock insuficiente (HTTP 409) | Aprobado | log `test_bloque3` |
| 112 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | El mensaje dice qué producto tiene el problema | Aprobado | log `test_bloque3` |
| 113 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | No se creó pedido (0) | Aprobado | log `test_bloque3` |
| 114 | 4) STOCK INSUFICIENTE (validación previa, antes de escribir) | Stock intacto (2) | Aprobado | log `test_bloque3` |
| 115 | 5) VARIOS PRODUCTOS: uno OK + otro sin stock (validación previa, sin escrituras) | El checkout completo falla (HTTP 409) | Aprobado | log `test_bloque3` |
| 116 | 5) VARIOS PRODUCTOS: uno OK + otro sin stock (validación previa, sin escrituras) | Base idéntica: pedidos, detalle, stock de todos los productos y carritos (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:5:3) | Aprobado | log `test_bloque3` |
| 117 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) Falla en el detalle del 2º producto -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 118 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) La respuesta sigue siendo JSON | Aprobado | log `test_bloque3` |
| 119 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) No filtra el error interno de MySQL | Aprobado | log `test_bloque3` |
| 120 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6a) ROLLBACK: pedidos, detalle, stock (prod. 2 incluido) y carrito idénticos a antes (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 121 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6b) Falla al descontar stock del 2º producto -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 122 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6b) ROLLBACK completo (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 123 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6c) Falla al vaciar el carrito -> error de servidor (HTTP 500) | Aprobado | log `test_bloque3` |
| 124 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | 6c) ROLLBACK completo (no queda pedido sin carrito vaciado ni stock descontado) (1\|1\|1:99,2:21,3:21,4:21,5:2,6:35,7:35,8:35,9:10,10:10,11:10,12:10\|3:2:1,3:7:2) | Aprobado | log `test_bloque3` |
| 125 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | Triggers de prueba eliminados (0) | Aprobado | log `test_bloque3` |
| 126 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | Sin la falla simulada, el mismo carrito se compra normalmente (HTTP 201) | Aprobado | log `test_bloque3` |
| 127 | 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción | …con 2 líneas de detalle (2) | Aprobado | log `test_bloque3` |
| 128 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | Producto desactivado rechazado (HTTP 409) | Aprobado | log `test_bloque3` |
| 129 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | El mensaje nombra el producto desactivado | Aprobado | log `test_bloque3` |
| 130 | 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO | Nada cambió (tampoco el producto activo del mismo carrito) (2\|3\|1:99,2:20,3:21,4:21,5:2,6:35,7:33,8:35,9:10,10:10,11:10,12:10\|3:1:1,3:3:1) | Aprobado | log `test_bloque3` |
| 131 | 8) ENVÍO EN UN CHECKOUT REAL | Checkout con envíos S/50 y S/100 (HTTP 201) | Aprobado | log `test_bloque3` |
| 132 | 8) ENVÍO EN UN CHECKOUT REAL | Envío = el mayor (100), no la suma (150) (100) | Aprobado | log `test_bloque3` |
| 133 | 8) ENVÍO EN UN CHECKOUT REAL | Subtotal (463.97 + 583.27) (1047.24) | Aprobado | log `test_bloque3` |
| 134 | 8) ENVÍO EN UN CHECKOUT REAL | Total (1147.24) | Aprobado | log `test_bloque3` |
| 135 | 8) ENVÍO EN UN CHECKOUT REAL | En MySQL: envío 100.00, total 1147.24 (100.00\|1147.24) | Aprobado | log `test_bloque3` |
| 136 | 8) ENVÍO EN UN CHECKOUT REAL | Un producto con envío pago y 3 unidades: envío S/50 una sola vez (50) | Aprobado | log `test_bloque3` |
| 137 | 8) ENVÍO EN UN CHECKOUT REAL | …total = 3 × 463.97 + 50 (1441.91) | Aprobado | log `test_bloque3` |
| 138 | 8) ENVÍO EN UN CHECKOUT REAL | Todo con envío gratis = S/0.00 (0) | Aprobado | log `test_bloque3` |
| 139 | 8) ENVÍO EN UN CHECKOUT REAL | …total = 5 × 429.14 + 2 × 494.10 (3133.9) | Aprobado | log `test_bloque3` |
| 140 | 9) HISTORIAL | A ve exactamente 1 pedido (1) | Aprobado | log `test_bloque3` |
| 141 | 9) HISTORIAL | B ve sus pedidos del más reciente al más antiguo | Aprobado | log `test_bloque3` |
| 142 | 9) HISTORIAL | Detalle: dirección (Av. Javier Prado 456, San Isidro, Lima) | Aprobado | log `test_bloque3` |
| 143 | 9) HISTORIAL | Detalle: 1 ítem (1) | Aprobado | log `test_bloque3` |
| 144 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | A no puede ver el pedido de B (HTTP 404) | Aprobado | log `test_bloque3` |
| 145 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | La respuesta es IDÉNTICA a la de un pedido inexistente (no revela que existe) ({"error":"Pedido no encontrado."}) | Aprobado | log `test_bloque3` |
| 146 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | El listado de A no incluye pedidos de B (comparado por id_pedido) | Aprobado | log `test_bloque3` |
| 147 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Inyección SQL en el id (HTTP 400) | Aprobado | log `test_bloque3` |
| 148 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | id negativo (HTTP 400) | Aprobado | log `test_bloque3` |
| 149 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Detalle sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 150 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Listado sin sesión (HTTP 401) | Aprobado | log `test_bloque3` |
| 151 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | GET en crear.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 152 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | POST en listar.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 153 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | POST en detalle.php (HTTP 405) | Aprobado | log `test_bloque3` |
| 154 | 10) SEGURIDAD: pedidos ajenos, inyección, métodos | Checkout con la cookie de una sesión ya cerrada (HTTP 401) | Aprobado | log `test_bloque3` |
| 155 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | Dirección con SQL: se acepta como texto (HTTP 201) | Aprobado | log `test_bloque3` |
| 156 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La tabla pedidos sigue existiendo (1) | Aprobado | log `test_bloque3` |
| 157 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | …y la dirección quedó guardada literalmente (Calle 1'); DROP TABLE pedidos; -- " OR 1=1) | Aprobado | log `test_bloque3` |
| 158 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La API responde como JSON (no como HTML) (content-type: application/json; charset=utf-8) | Aprobado | log `test_bloque3` |
| 159 | 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) | La API devuelve el texto tal cual (el escape es tarea de la vista; se prueba en el navegador) (<img src=x onerror="alert(1)"><script>alert(2)</script>) | Aprobado | log `test_bloque3` |
| 160 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | El carrito ignoró id_usuario/precio del cliente: el ítem quedó en el carrito de A (1) | Aprobado | log `test_bloque3` |
| 161 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | Se crea el pedido ignorando esos campos (HTTP 201) | Aprobado | log `test_bloque3` |
| 162 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | id_usuario = el de la sesión (A), no el que mandó el cliente (2) | Aprobado | log `test_bloque3` |
| 163 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | estado = pendiente (no 'pagado') (pendiente) | Aprobado | log `test_bloque3` |
| 164 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | total calculado por el servidor (521.10 + envío 50), no 1 (571.10) | Aprobado | log `test_bloque3` |
| 165 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | precio_unitario del servidor, no 1 (521.10) | Aprobado | log `test_bloque3` |
| 166 | 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) | B no recibió ningún pedido nuevo de A (4) | Aprobado | log `test_bloque3` |
| 167 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | Ningún stock negativo (0) | Aprobado | log `test_bloque3` |
| 168 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | Todo pedido tiene al menos una línea de detalle (0) | Aprobado | log `test_bloque3` |
| 169 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | En todo pedido: subtotal = Σ detalle y total = subtotal + envío (0) | Aprobado | log `test_bloque3` |
| 170 | 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS | En todo detalle: subtotal = cantidad × precio_unitario (0) | Aprobado | log `test_bloque3` |
| 171 | 14) ESTADOS | El ENUM admite los 5 estados (pendiente,pagado,enviado,completado,cancelado) | Aprobado | log `test_bloque3` |

## test_concurrencia

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 172 | 0) El servidor procesa solicitudes en paralelo de verdad | dos checkouts con 1 s de espera cada uno terminaron en 1.01 s (< 1.8 s => corrieron en paralelo) | Aprobado | log `test_concurrencia` |
| 173 | A) Dos usuarios, 2 unidades c/u, stock 2 (10 rondas) | 10/10 rondas correctas: exactamente 1 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 174 | B) Tres usuarios, 1 unidad c/u, stock 2 (10 rondas) | 10/10 rondas correctas: exactamente 2 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 175 | C) Dos usuarios con los mismos 2 productos (orden de alta invertido), stock de sobra (10 rondas) | 10/10 rondas: ambos checkouts terminan bien (sin deadlock ni 500) y el stock baja 2 en cada producto | Aprobado | log `test_concurrencia` |
| 176 | D) Seis usuarios, 1 unidad c/u, stock 3 (10 rondas) | 10/10 rondas correctas: exactamente 3 éxito(s), el resto 409, stock final 0 (nunca negativo), un pedido por éxito, carrito vaciado solo en los éxitos | Aprobado | log `test_concurrencia` |
| 177 | D) Seis usuarios, 1 unidad c/u, stock 3 (10 rondas) | ningún producto quedó con stock negativo | Aprobado | log `test_concurrencia` |

## test_navegador_bloque3

| # | Sección | Caso | Resultado | Evidencia |
|---:|---|---|---|---|
| 178 | 1) Checkout sin sesión | checkout.html sin sesión redirige a login con next=checkout | Aprobado | log `test_navegador_bloque3` |
| 179 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | el invitado guarda el producto en localStorage | Aprobado | log `test_navegador_bloque3` |
| 180 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | el carrito avisa al invitado que necesitará iniciar sesión | Aprobado | log `test_navegador_bloque3` |
| 181 | 2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión | 'Continuar Compra' sin sesión lleva a login con next=checkout (no se compra como invitado) | Aprobado | log `test_navegador_bloque3` |
| 182 | 3) Registro -> fusión del carrito -> vuelta al checkout | tras registrarse vuelve a checkout.html | Aprobado | log `test_navegador_bloque3` |
| 183 | 3) Registro -> fusión del carrito -> vuelta al checkout | el carrito de invitado se fusionó y se limpió localStorage | Aprobado | log `test_navegador_bloque3` |
| 184 | 3) Registro -> fusión del carrito -> vuelta al checkout | el producto del invitado ahora está en el carrito de MySQL | Aprobado | log `test_navegador_bloque3` |
| 185 | 4) Agregar otro producto con sesión y volver al checkout | hay 2 productos en el carrito | Aprobado | log `test_navegador_bloque3` |
| 186 | 4) Agregar otro producto con sesión y volver al checkout | con sesión ya no aparece el aviso de login | Aprobado | log `test_navegador_bloque3` |
| 187 | 4) Agregar otro producto con sesión y volver al checkout | muestra el nombre de la sesión | Aprobado | log `test_navegador_bloque3` |
| 188 | 4) Agregar otro producto con sesión y volver al checkout | muestra el correo de la sesión | Aprobado | log `test_navegador_bloque3` |
| 189 | 4) Agregar otro producto con sesión y volver al checkout | subtotal S/. 923.24 (429.14 + 494.10) | Aprobado | log `test_navegador_bloque3` |
| 190 | 4) Agregar otro producto con sesión y volver al checkout | envío: Gratis | Aprobado | log `test_navegador_bloque3` |
| 191 | 4) Agregar otro producto con sesión y volver al checkout | total S/. 923.24 | Aprobado | log `test_navegador_bloque3` |
| 192 | 4) Agregar otro producto con sesión y volver al checkout | el resumen lista los 2 productos | Aprobado | log `test_navegador_bloque3` |
| 193 | 4) Agregar otro producto con sesión y volver al checkout | la confirmación NO se ve antes de confirmar | Aprobado | log `test_navegador_bloque3` |
| 194 | 5) Validación de la dirección | dirección vacía: muestra el error | Aprobado | log `test_navegador_bloque3` |
| 195 | 5) Validación de la dirección | dirección demasiado corta: muestra el error | Aprobado | log `test_navegador_bloque3` |
| 196 | 5) Validación de la dirección | esos intentos no crearon pedidos | Aprobado | log `test_navegador_bloque3` |
| 197 | 6) Confirmar pedido (bug B-02) | aparece '¡Gracias por tu compra!' | Aprobado | log `test_navegador_bloque3` |
| 198 | 6) Confirmar pedido (bug B-02) | B-02: el bloque formulario+resumen queda oculto de verdad | Aprobado | log `test_navegador_bloque3` |
| 199 | 6) Confirmar pedido (bug B-02) | B-02: el campo de dirección no se ve | Aprobado | log `test_navegador_bloque3` |
| 200 | 6) Confirmar pedido (bug B-02) | B-02: el botón 'Confirmar pedido' no se ve (no se puede confirmar dos veces) | Aprobado | log `test_navegador_bloque3` |
| 201 | 6) Confirmar pedido (bug B-02) | B-02: el resumen del pedido no se ve | Aprobado | log `test_navegador_bloque3` |
| 202 | 6) Confirmar pedido (bug B-02) | muestra el número de pedido (#1) | Aprobado | log `test_navegador_bloque3` |
| 203 | 6) Confirmar pedido (bug B-02) | el contador del header queda en 0 | Aprobado | log `test_navegador_bloque3` |
| 204 | 7) Carrito vacío tras el pedido | el carrito quedó vacío en MySQL | Aprobado | log `test_navegador_bloque3` |
| 205 | 7) Carrito vacío tras el pedido | la página muestra 'Carrito Vacío' | Aprobado | log `test_navegador_bloque3` |
| 206 | 7) Carrito vacío tras el pedido | y no muestra productos | Aprobado | log `test_navegador_bloque3` |
| 207 | 8) Mis Compras y detalle | lista 1 pedido | Aprobado | log `test_navegador_bloque3` |
| 208 | 8) Mis Compras y detalle | es el pedido recién creado | Aprobado | log `test_navegador_bloque3` |
| 209 | 8) Mis Compras y detalle | estado 'Pendiente' | Aprobado | log `test_navegador_bloque3` |
| 210 | 8) Mis Compras y detalle | total S/. 923.24 | Aprobado | log `test_navegador_bloque3` |
| 211 | 8) Mis Compras y detalle | el detalle muestra los 2 productos | Aprobado | log `test_navegador_bloque3` |
| 212 | 8) Mis Compras y detalle | el detalle muestra la dirección | Aprobado | log `test_navegador_bloque3` |
| 213 | 8) Mis Compras y detalle | cantidades y precios unitarios correctos (['1 × S/. 429.14', '1 × S/. 494.10']) | Aprobado | log `test_navegador_bloque3` |
| 214 | 8) Mis Compras y detalle | el modal se cierra con la × | Aprobado | log `test_navegador_bloque3` |
| 215 | 8) Mis Compras y detalle | tras cerrarlo, la lista sigue siendo clickeable (el modal no bloquea) | Aprobado | log `test_navegador_bloque3` |
| 216 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | ahora hay 2 pedidos | Aprobado | log `test_navegador_bloque3` |
| 217 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | el más reciente aparece primero | Aprobado | log `test_navegador_bloque3` |
| 218 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | no se ejecutó el código inyectado | Aprobado | log `test_navegador_bloque3` |
| 219 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | no se creó ningún <img> a partir de la dirección | Aprobado | log `test_navegador_bloque3` |
| 220 | 9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta | la dirección aparece como texto literal | Aprobado | log `test_navegador_bloque3` |
| 221 | 10) Cerrar sesión: un solo POST de logout | se envió exactamente un POST de logout (enviados: ['POST']) | Aprobado | log `test_navegador_bloque3` |
| 222 | 10) Cerrar sesión: un solo POST de logout | la sesión quedó cerrada en el servidor | Aprobado | log `test_navegador_bloque3` |
| 223 | 11) next=mis-compras: login y vuelta a Mis Compras | Mis Compras sin sesión redirige a login con next=mis-compras | Aprobado | log `test_navegador_bloque3` |
| 224 | 11) next=mis-compras: login y vuelta a Mis Compras | tras el login vuelve a Mis Compras y ve sus 2 pedidos | Aprobado | log `test_navegador_bloque3` |
| 225 | 12) Open redirect: destinos externos o raros terminan en index.html | next='https://evil.example/' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 226 | 12) Open redirect: destinos externos o raros terminan en index.html | next='//evil.example' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 227 | 12) Open redirect: destinos externos o raros terminan en index.html | next='javascript:alert(1)' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 228 | 12) Open redirect: destinos externos o raros terminan en index.html | next='../../../etc/passwd' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 229 | 12) Open redirect: destinos externos o raros terminan en index.html | next='checkout.html' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 230 | 12) Open redirect: destinos externos o raros terminan en index.html | next='CHECKOUT' -> /index.html (dentro del sitio, sin redirigir afuera) | Aprobado | log `test_navegador_bloque3` |
| 231 | 12) Open redirect: destinos externos o raros terminan en index.html | cero errores de JavaScript en todo el flujo | Aprobado | log `test_navegador_bloque3` |
| 232 | 12) Open redirect: destinos externos o raros terminan en index.html | cero errores en la consola del navegador | Aprobado | log `test_navegador_bloque3` |
| 233 | 12) Open redirect: destinos externos o raros terminan en index.html | ninguna respuesta 5xx del servidor | Aprobado | log `test_navegador_bloque3` |
