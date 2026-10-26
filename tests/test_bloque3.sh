#!/bin/bash
# Bloque 3: checkout y pedidos (transacción, stock, rollback REAL, precio histórico, seguridad).
# Datos que modifica (solo en la base de pruebas): registra 2 clientes, crea pedidos, descuenta stock,
# cambia temporalmente el precio del producto 1 y el estado del producto 3 (y los restaura), y crea y
# borra triggers temporales en detalle_pedido / productos / carrito_items para simular fallas a mitad
# de la transacción.
source "$(dirname "$0")/lib.sh"
JAR_A=$(mktemp); JAR_B=$(mktemp); JAR_X=$(mktemp); TS=$(date +%s)
registrar "$JAR_A" "Pedido A" "pedA$TS@test.com" "claveA123"
registrar "$JAR_B" "Pedido B" "pedB$TS@test.com" "claveB123"
ID_A=$(sql "SELECT id_usuario FROM usuarios WHERE email='pedA$TS@test.com';")
ID_B=$(sql "SELECT id_usuario FROM usuarios WHERE email='pedB$TS@test.com';")
agregar(){ post "$BASE/carrito/agregar.php" "{\"id_producto\":$2,\"cantidad\":$3}" -b "$1" >/dev/null; }
crear(){ post "$BASE/pedidos/crear.php" "$2" -b "$1"; }
foto(){   # estado completo relevante de la base, para comparar antes/después
    sql "SELECT CONCAT((SELECT COUNT(*) FROM pedidos),'|',(SELECT COUNT(*) FROM detalle_pedido),'|',
         (SELECT GROUP_CONCAT(id_producto,':',stock ORDER BY id_producto) FROM productos),'|',
         (SELECT IFNULL(GROUP_CONCAT(id_usuario,':',id_producto,':',cantidad ORDER BY id_usuario,id_producto),'') FROM carrito_items));"
}
trigger(){   # trigger <nombre> <tabla> <evento> <condición>  -> falla simulada cuando se cumple la condición
    $MYSQL_CLI "$DB_NAME" <<SQL
DROP TRIGGER IF EXISTS $1;
DELIMITER //
CREATE TRIGGER $1 BEFORE $3 ON $2 FOR EACH ROW
BEGIN
    IF $4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FALLA_SIMULADA_PRUEBA'; END IF;
END//
DELIMITER ;
SQL
}

echo "== 1) CHECKOUT: carrito vacío / sin sesión / dirección =="
R=$(crear "$JAR_A" '{"direccion_envio":"Av. Siempre Viva 123"}'); check "Carrito vacío" 400 "$(codigo "$R")"
check "Sin sesión" 401 "$(codigo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Test 123"}')")"
check "Cookie de sesión inventada" 401 "$(codigo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Test 123"}' -H 'Cookie: PHPSESSID=inventada123')")"
agregar "$JAR_A" 1 1
check "Dirección ausente" 400 "$(codigo "$(crear "$JAR_A" '{}')")"
check "Dirección vacía" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":""}')")"
check "Dirección solo con espacios" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":"         "}')")"
check "Dirección demasiado corta (2 caracteres)" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":"Ab"}')")"
check "Dirección como lista (tipo inválido)" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":["Av. Lista 123"]}')")"
check "Dirección como número (tipo inválido)" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":123456}')")"
LARGA=$(python3 -c "print('D'*256)")
check "Dirección de 256 caracteres (máximo 255)" 400 "$(codigo "$(crear "$JAR_A" "{\"direccion_envio\":\"$LARGA\"}")")"
check "Cuerpo que no es JSON" 400 "$(codigo "$(crear "$JAR_A" 'esto no es json')")"
igual "Ninguno de esos rechazos creó un pedido" 0 "$(sql 'SELECT COUNT(*) FROM pedidos;')"
igual "…ni vació el carrito de A" 1 "$(sql "SELECT COUNT(*) FROM carrito_items WHERE id_usuario=$ID_A;")"

echo "== 2) CHECKOUT EXITOSO + CONSISTENCIA EN MYSQL =="
STOCK1=$(sql "SELECT stock FROM productos WHERE id_producto=1;")
R=$(crear "$JAR_A" '{"direccion_envio":"Av. Javier Prado 456, San Isidro, Lima"}')
check "Checkout" 201 "$(codigo "$R")"; ID_PED=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
igual "Pedido existe" 1 "$(sql "SELECT COUNT(*) FROM pedidos WHERE id_pedido=$ID_PED;")"
igual "Detalle existe (1 línea)" 1 "$(sql "SELECT COUNT(*) FROM detalle_pedido WHERE id_pedido=$ID_PED;")"
igual "Detalle: cantidad 1, precio unitario 429.14, subtotal 429.14" "1|429.14|429.14" "$(sql "SELECT CONCAT(cantidad,'|',precio_unitario,'|',subtotal) FROM detalle_pedido WHERE id_pedido=$ID_PED;")"
igual "Stock descontado" $((STOCK1-1)) "$(sql "SELECT stock FROM productos WHERE id_producto=1;")"
igual "Carrito vaciado" 0 "$(sql "SELECT COUNT(*) FROM carrito_items WHERE id_usuario=$ID_A;")"
igual "Pedido: subtotal|envío|total" "429.14|0.00|429.14" "$(sql "SELECT CONCAT(subtotal,'|',costo_envio,'|',total) FROM pedidos WHERE id_pedido=$ID_PED;")"
igual "total = subtotal + envío, y subtotal = suma del detalle" 1 "$(sql "SELECT p.total = p.subtotal + p.costo_envio AND p.subtotal = (SELECT SUM(subtotal) FROM detalle_pedido WHERE id_pedido=p.id_pedido) FROM pedidos p WHERE id_pedido=$ID_PED;")"
igual "Estado inicial" pendiente "$(sql "SELECT estado FROM pedidos WHERE id_pedido=$ID_PED;")"
igual "Pedido de A pertenece a A" "$ID_A" "$(sql "SELECT id_usuario FROM pedidos WHERE id_pedido=$ID_PED;")"
igual "Dirección guardada" "Av. Javier Prado 456, San Isidro, Lima" "$(sql "SELECT direccion_envio FROM pedidos WHERE id_pedido=$ID_PED;")"
check "Repetir el checkout con el carrito ya vacío" 400 "$(codigo "$(crear "$JAR_A" '{"direccion_envio":"Av. Javier Prado 456"}')")"

echo "== 3) PRECIO HISTÓRICO =="
PRECIO_ORIG=$(sql "SELECT precio_original FROM productos WHERE id_producto=1;")
DESC_ORIG=$(sql "SELECT descuento_porcentaje FROM productos WHERE id_producto=1;")
sql "UPDATE productos SET precio_original=999.00, descuento_porcentaje=NULL WHERE id_producto=1;"
igual "El catálogo ya muestra el precio nuevo" 999.0 "$(curl -s "$BASE/productos/detalle.php?id=1" | json 'd["producto"]["precio_final"]')"
R=$(curl -s -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_PED")
igual "El pedido conserva el precio unitario de la compra" 429.14 "$(echo "$R" | json 'd["pedido"]["items"][0]["precio_unitario"]')"
igual "…y el total original" 429.14 "$(echo "$R" | json 'd["pedido"]["total"]')"
igual "En MySQL detalle_pedido.precio_unitario no cambió" 429.14 "$(sql "SELECT precio_unitario FROM detalle_pedido WHERE id_pedido=$ID_PED;")"
sql "UPDATE productos SET precio_original=$PRECIO_ORIG, descuento_porcentaje=$DESC_ORIG WHERE id_producto=1;"
igual "Precio del producto 1 restaurado" 429.14 "$(curl -s "$BASE/productos/detalle.php?id=1" | json 'd["producto"]["precio_final"]')"

echo "== 4) STOCK INSUFICIENTE (validación previa, antes de escribir) =="
S5=$(sql "SELECT stock FROM productos WHERE id_producto=5;")
sql "INSERT INTO carrito_items (id_usuario,id_producto,cantidad) VALUES ($ID_B,5,3);"   # stock real = 2
P0=$(sql "SELECT COUNT(*) FROM pedidos WHERE id_usuario=$ID_B;")
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Falsa 456"}'); check "Stock insuficiente" 409 "$(codigo "$R")"
verdadero "El mensaje dice qué producto tiene el problema" "$(cuerpo "$R" | json '"Samsung Galaxy Book3" in d["error"]')"
igual "No se creó pedido" "$P0" "$(sql "SELECT COUNT(*) FROM pedidos WHERE id_usuario=$ID_B;")"
igual "Stock intacto" "$S5" "$(sql "SELECT stock FROM productos WHERE id_producto=5;")"

echo "== 5) VARIOS PRODUCTOS: uno OK + otro sin stock (validación previa, sin escrituras) =="
# Nota: este caso lo frena la validación ANTES de escribir; no ejercita el ROLLBACK. El rollback
# real (falla a mitad de la transacción, con escrituras ya hechas) se prueba en la sección 6.
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_B;"
sql "INSERT INTO carrito_items (id_usuario,id_producto,cantidad) VALUES ($ID_B,2,1),($ID_B,5,3);"
ANTES=$(foto)
check "El checkout completo falla" 409 "$(codigo "$(crear "$JAR_B" '{"direccion_envio":"Calle Rollback 789"}')")"
igual "Base idéntica: pedidos, detalle, stock de todos los productos y carritos" "$ANTES" "$(foto)"

echo "== 6) ROLLBACK REAL: falla del servidor A MITAD de la transacción =="
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_B;"
sql "INSERT INTO carrito_items (id_usuario,id_producto,cantidad) VALUES ($ID_B,2,1),($ID_B,7,2);"
# 6a) Falla al insertar el detalle del 2º producto: el pedido, el detalle del 1º y su descuento de stock YA se escribieron.
trigger tg_prueba_falla detalle_pedido INSERT "NEW.id_producto = 7"
ANTES=$(foto)
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Falla Detalle 101"}')
check "6a) Falla en el detalle del 2º producto -> error de servidor" 500 "$(codigo "$R")"
verdadero "6a) La respuesta sigue siendo JSON" "$(cuerpo "$R" | es_json)"
verdadero "6a) No filtra el error interno de MySQL" "$(cuerpo "$R" | json '"FALLA_SIMULADA" not in d["error"] and "SQLSTATE" not in d["error"]')"
igual "6a) ROLLBACK: pedidos, detalle, stock (prod. 2 incluido) y carrito idénticos a antes" "$ANTES" "$(foto)"
# 6b) Falla al descontar el stock del 2º producto (pedido + 2 detalles + stock del 1º ya escritos).
sql "DROP TRIGGER tg_prueba_falla;"; trigger tg_prueba_falla productos UPDATE "NEW.id_producto = 7 AND NEW.stock < OLD.stock"
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Falla Stock 202"}')
check "6b) Falla al descontar stock del 2º producto -> error de servidor" 500 "$(codigo "$R")"
igual "6b) ROLLBACK completo" "$ANTES" "$(foto)"
# 6c) Falla en el último paso (vaciar el carrito): todo lo anterior ya se había escrito.
sql "DROP TRIGGER tg_prueba_falla;"; trigger tg_prueba_falla carrito_items DELETE "1 = 1"
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Falla Carrito 303"}')
check "6c) Falla al vaciar el carrito -> error de servidor" 500 "$(codigo "$R")"
igual "6c) ROLLBACK completo (no queda pedido sin carrito vaciado ni stock descontado)" "$ANTES" "$(foto)"
sql "DROP TRIGGER tg_prueba_falla;"
igual "Triggers de prueba eliminados" 0 "$(sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DB_NAME';")"
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Sin Falla 404"}')
check "Sin la falla simulada, el mismo carrito se compra normalmente" 201 "$(codigo "$R")"
igual "…con 2 líneas de detalle" 2 "$(sql "SELECT COUNT(*) FROM detalle_pedido WHERE id_pedido=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]');")"

echo "== 7) PRODUCTO DESACTIVADO CON EL CARRITO ARMADO =="
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_B;"; agregar "$JAR_B" 3 1; agregar "$JAR_B" 1 1
sql "UPDATE productos SET estado='inactivo' WHERE id_producto=3;"
ANTES=$(foto)
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Inactivo 321"}')
check "Producto desactivado rechazado" 409 "$(codigo "$R")"
verdadero "El mensaje nombra el producto desactivado" "$(cuerpo "$R" | json '"Samsung A04s" in d["error"]')"
igual "Nada cambió (tampoco el producto activo del mismo carrito)" "$ANTES" "$(foto)"
sql "UPDATE productos SET estado='activo' WHERE id_producto=3;"

echo "== 8) ENVÍO EN UN CHECKOUT REAL =="
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_B;"; agregar "$JAR_B" 2 1; agregar "$JAR_B" 11 1
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Envios 111"}'); check "Checkout con envíos S/50 y S/100" 201 "$(codigo "$R")"
ID_PED_B=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
igual "Envío = el mayor (100), no la suma (150)" 100.0 "$(cuerpo "$R" | json 'd["pedido"]["costo_envio"]')"
igual "Subtotal (463.97 + 583.27)" 1047.24 "$(cuerpo "$R" | json 'd["pedido"]["subtotal"]')"
igual "Total" 1147.24 "$(cuerpo "$R" | json 'd["pedido"]["total"]')"
igual "En MySQL: envío 100.00, total 1147.24" "100.00|1147.24" "$(sql "SELECT CONCAT(costo_envio,'|',total) FROM pedidos WHERE id_pedido=$ID_PED_B;")"
agregar "$JAR_B" 2 3   # un producto con envío S/50, 3 unidades
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Envio Unico 333"}')
igual "Un producto con envío pago y 3 unidades: envío S/50 una sola vez" 50.0 "$(cuerpo "$R" | json 'd["pedido"]["costo_envio"]')"
igual "…total = 3 × 463.97 + 50" 1441.91 "$(cuerpo "$R" | json 'd["pedido"]["total"]')"
agregar "$JAR_B" 1 5; agregar "$JAR_B" 9 2   # todos con envío gratis, varias unidades
R=$(crear "$JAR_B" '{"direccion_envio":"Calle Gratis 222"}')
igual "Todo con envío gratis = S/0.00" 0.0 "$(cuerpo "$R" | json 'd["pedido"]["costo_envio"]')"
igual "…total = 5 × 429.14 + 2 × 494.10" 3133.9 "$(cuerpo "$R" | json 'd["pedido"]["total"]')"

echo "== 9) HISTORIAL =="
igual "A ve exactamente 1 pedido" 1 "$(curl -s -b "$JAR_A" "$BASE/pedidos/listar.php" | json 'len(d["pedidos"])')"
verdadero "B ve sus pedidos del más reciente al más antiguo" "$(curl -s -b "$JAR_B" "$BASE/pedidos/listar.php" | json '[p["id_pedido"] for p in d["pedidos"]] == sorted([p["id_pedido"] for p in d["pedidos"]], reverse=True) and len(d["pedidos"]) == 4')"
R=$(curl -s -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_PED")
igual "Detalle: dirección" "Av. Javier Prado 456, San Isidro, Lima" "$(echo "$R" | json 'd["pedido"]["direccion_envio"]')"
igual "Detalle: 1 ítem" 1 "$(echo "$R" | json 'len(d["pedido"]["items"])')"

echo "== 10) SEGURIDAD: pedidos ajenos, inyección, métodos =="
R_AJENO=$(curl -s -w "\n%{http_code}" -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_PED_B")
R_INEX=$(curl -s -w "\n%{http_code}" -b "$JAR_A" "$BASE/pedidos/detalle.php?id=999999")
check "A no puede ver el pedido de B" 404 "$(codigo "$R_AJENO")"
igual "La respuesta es IDÉNTICA a la de un pedido inexistente (no revela que existe)" "$(cuerpo "$R_INEX")" "$(cuerpo "$R_AJENO")"
verdadero "El listado de A no incluye pedidos de B (comparado por id_pedido)" "$(curl -s -b "$JAR_A" "$BASE/pedidos/listar.php" | json "$ID_PED_B not in [p['id_pedido'] for p in d['pedidos']]")"
check "Inyección SQL en el id" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$BASE/pedidos/detalle.php?id=1%20OR%201=1")"
check "id negativo" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$BASE/pedidos/detalle.php?id=-1")"
check "Detalle sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/pedidos/detalle.php?id=$ID_PED")"
check "Listado sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/pedidos/listar.php")"
check "GET en crear.php" 405 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$BASE/pedidos/crear.php")"
check "POST en listar.php" 405 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' -X POST "$BASE/pedidos/listar.php")"
check "POST en detalle.php" 405 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' -X POST "$BASE/pedidos/detalle.php?id=$ID_PED")"
# Tras cerrar sesión, la cookie vieja no sirve para comprar.
registrar "$JAR_X" "Pedido X" "pedX$TS@test.com" "claveX123"; agregar "$JAR_X" 1 1
post "$BASE/auth/logout.php" '' -b "$JAR_X" >/dev/null
check "Checkout con la cookie de una sesión ya cerrada" 401 "$(codigo "$(crear "$JAR_X" '{"direccion_envio":"Calle Cerrada 555"}')")"

echo "== 11) INYECCIÓN SQL Y XSS EN LA DIRECCIÓN (se guardan como texto literal) =="
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_A;"; agregar "$JAR_A" 1 1
SQLI="Calle 1'); DROP TABLE pedidos; -- \" OR 1=1"
R=$(post "$BASE/pedidos/crear.php" "$(python3 -c 'import json,sys; print(json.dumps({"direccion_envio": sys.argv[1]}))' "$SQLI")" -b "$JAR_A")
check "Dirección con SQL: se acepta como texto" 201 "$(codigo "$R")"; ID_SQLI=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
igual "La tabla pedidos sigue existiendo" 1 "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='pedidos';")"
igual "…y la dirección quedó guardada literalmente" "$SQLI" "$(curl -s -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_SQLI" | json 'd["pedido"]["direccion_envio"]')"
agregar "$JAR_A" 1 1
XSS='<img src=x onerror="alert(1)"><script>alert(2)</script>'
R=$(post "$BASE/pedidos/crear.php" "$(python3 -c 'import json,sys; print(json.dumps({"direccion_envio": sys.argv[1]}))' "$XSS")" -b "$JAR_A")
ID_XSS=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
H=$(curl -s -D - -o /dev/null -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_XSS" | tr -d '\r' | grep -i '^content-type:')
igual "La API responde como JSON (no como HTML)" "content-type: application/json; charset=utf-8" "$(echo "$H" | tr 'A-Z' 'a-z')"
igual "La API devuelve el texto tal cual (el escape es tarea de la vista; se prueba en el navegador)" "$XSS" "$(curl -s -b "$JAR_A" "$BASE/pedidos/detalle.php?id=$ID_XSS" | json 'd["pedido"]["direccion_envio"]')"

echo "== 12) EL CLIENTE NO PUEDE IMPONER DATOS (id_usuario, estado, total, precio, envío) =="
sql "DELETE FROM carrito_items WHERE id_usuario=$ID_A;"
post "$BASE/carrito/agregar.php" '{"id_producto":3,"cantidad":1,"precio":1,"precio_final":1,"id_usuario":'"$ID_B"'}' -b "$JAR_A" >/dev/null
igual "El carrito ignoró id_usuario/precio del cliente: el ítem quedó en el carrito de A" 1 "$(sql "SELECT COUNT(*) FROM carrito_items WHERE id_usuario=$ID_A AND id_producto=3;")"
R=$(crear "$JAR_A" "{\"direccion_envio\":\"Calle Manipulada 999\",\"id_usuario\":$ID_B,\"estado\":\"pagado\",\"total\":1,\"subtotal\":1,\"costo_envio\":0,\"precio\":1,\"stock\":999,\"items\":[{\"id_producto\":3,\"cantidad\":1,\"precio_unitario\":1}]}")
check "Se crea el pedido ignorando esos campos" 201 "$(codigo "$R")"; ID_M=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
igual "id_usuario = el de la sesión (A), no el que mandó el cliente" "$ID_A" "$(sql "SELECT id_usuario FROM pedidos WHERE id_pedido=$ID_M;")"
igual "estado = pendiente (no 'pagado')" pendiente "$(sql "SELECT estado FROM pedidos WHERE id_pedido=$ID_M;")"
igual "total calculado por el servidor (521.10 + envío 50), no 1" 571.1 "$(sql "SELECT total FROM pedidos WHERE id_pedido=$ID_M;")"
igual "precio_unitario del servidor, no 1" 521.1 "$(sql "SELECT precio_unitario FROM detalle_pedido WHERE id_pedido=$ID_M;")"
igual "B no recibió ningún pedido nuevo de A" 4 "$(sql "SELECT COUNT(*) FROM pedidos WHERE id_usuario=$ID_B;")"

echo "== 13) INTEGRIDAD GLOBAL DE TODOS LOS PEDIDOS CREADOS =="
igual "Ningún stock negativo" 0 "$(sql "SELECT COUNT(*) FROM productos WHERE stock < 0;")"
igual "Todo pedido tiene al menos una línea de detalle" 0 "$(sql "SELECT COUNT(*) FROM pedidos p WHERE NOT EXISTS (SELECT 1 FROM detalle_pedido d WHERE d.id_pedido=p.id_pedido);")"
igual "En todo pedido: subtotal = Σ detalle y total = subtotal + envío" 0 "$(sql "SELECT COUNT(*) FROM pedidos p WHERE p.subtotal <> (SELECT SUM(subtotal) FROM detalle_pedido d WHERE d.id_pedido=p.id_pedido) OR p.total <> p.subtotal + p.costo_envio;")"
igual "En todo detalle: subtotal = cantidad × precio_unitario" 0 "$(sql "SELECT COUNT(*) FROM detalle_pedido WHERE ROUND(cantidad*precio_unitario,2) <> subtotal;")"

echo "== 14) ESTADOS =="
igual "El ENUM admite los 5 estados" "pendiente,pagado,enviado,completado,cancelado" \
  "$(sql "SELECT REPLACE(REPLACE(REPLACE(COLUMN_TYPE,'enum(',''),')',''),'\'','') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='pedidos' AND COLUMN_NAME='estado';")"
rm -f "$JAR_A" "$JAR_B" "$JAR_X"
resumen
