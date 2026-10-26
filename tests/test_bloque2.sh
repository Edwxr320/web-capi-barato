#!/bin/bash
# Bloque 2: carrito conectado a MySQL (agregar, listar, actualizar, eliminar, fusionar, cálculo, seguridad).
source "$(dirname "$0")/lib.sh"
JAR_A=$(mktemp); JAR_B=$(mktemp); TS=$(date +%s)
curl -s -c "$JAR_A" -X POST "$BASE/auth/registro.php" -H "Content-Type: application/json" -d "{\"nombre\":\"Cliente A\",\"email\":\"carA$TS@test.com\",\"password\":\"claveA123\"}" >/dev/null
curl -s -c "$JAR_B" -X POST "$BASE/auth/registro.php" -H "Content-Type: application/json" -d "{\"nombre\":\"Cliente B\",\"email\":\"carB$TS@test.com\",\"password\":\"claveB123\"}" >/dev/null
items(){ json 'dict(sorted({i["producto"]["id_producto"]: i["cantidad"] for i in d["items"]}.items()))'; }

echo "== 1) CARRITO VACÍO =="
R=$(curl -s -b "$JAR_A" -w "\n%{http_code}" "$BASE/carrito/listar.php")
check "Carrito vacío" 200 "$(codigo "$R")"; igual "Resumen en cero" 0 "$(cuerpo "$R" | json 'd["resumen"]["total"]')"

echo "== 2) AGREGAR =="
check "Agregar producto válido" 201 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_A")")"
R=$(post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":2}' -b "$JAR_A")
igual "Producto repetido: suma (1+2)" 3 "$(cuerpo "$R" | json 'd["cantidad"]')"
igual "…y no duplica la fila" 1 "$(curl -s -b "$JAR_A" "$BASE/carrito/listar.php" | json 'len(d["items"])')"
check "Producto inexistente" 404 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":9999,"cantidad":1}' -b "$JAR_A")")"
check "Más que el stock (producto 5, stock 2)" 409 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":5,"cantidad":5}' -b "$JAR_A")")"
check "Sin sesión" 401 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}')")"

echo "== 3) ACTUALIZAR =="
check "Cantidad válida" 200 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":1,"cantidad":5}' -b "$JAR_A")")"
check "Cantidad 0" 400 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":1,"cantidad":0}' -b "$JAR_A")")"
check "Cantidad negativa" 400 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":1,"cantidad":-3}' -b "$JAR_A")")"
check "Cantidad decimal" 400 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":1,"cantidad":2.5}' -b "$JAR_A")")"
check "Superior al stock" 409 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":1,"cantidad":999}' -b "$JAR_A")")"
check "Producto inexistente" 404 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":9999,"cantidad":1}' -b "$JAR_A")")"
igual "Los rechazos no modificaron nada (sigue en 5)" 5 "$(curl -s -b "$JAR_A" "$BASE/carrito/listar.php" | items | python3 -c 'import sys,ast; print(ast.literal_eval(sys.stdin.read())[1])')"

echo "== 4) SEGURIDAD: carrito ajeno =="
post "$BASE/carrito/agregar.php" '{"id_producto":4,"cantidad":1}' -b "$JAR_B" >/dev/null
check "A no puede actualizar el producto 4 de B" 404 "$(codigo "$(post "$BASE/carrito/actualizar.php" '{"id_producto":4,"cantidad":99}' -b "$JAR_A")")"
check "A no puede eliminar el producto 4 de B" 404 "$(codigo "$(post "$BASE/carrito/eliminar.php" '{"id_producto":4}' -b "$JAR_A")")"
igual "El carrito de B quedó intacto" "{4: 1}" "$(curl -s -b "$JAR_B" "$BASE/carrito/listar.php" | items)"
check "Inyección SQL en id_producto" 400 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":"1 OR 1=1","cantidad":1}' -b "$JAR_A")")"

echo "== 5) ELIMINAR =="
check "Eliminar propio" 200 "$(codigo "$(post "$BASE/carrito/eliminar.php" '{"id_producto":1}' -b "$JAR_A")")"
check "Eliminar de nuevo" 404 "$(codigo "$(post "$BASE/carrito/eliminar.php" '{"id_producto":1}' -b "$JAR_A")")"

echo "== 6) PERSISTENCIA (logout + login) =="
post "$BASE/carrito/agregar.php" '{"id_producto":2,"cantidad":3}' -b "$JAR_A" >/dev/null
post "$BASE/auth/logout.php" '' -b "$JAR_A" -c "$JAR_A" >/dev/null
check "Tras logout no accesible" 401 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"
post "$BASE/auth/login.php" "{\"email\":\"carA$TS@test.com\",\"password\":\"claveA123\"}" -c "$JAR_A" >/dev/null
igual "El carrito persistió en MySQL" "{2: 3}" "$(curl -s -b "$JAR_A" "$BASE/carrito/listar.php" | items)"

echo "== 7) FUSIÓN =="
check "Fusionar" 200 "$(codigo "$(post "$BASE/carrito/fusionar.php" '{"items":[{"id_producto":2,"cantidad":2},{"id_producto":6,"cantidad":1},{"id_producto":5,"cantidad":10},{"id_producto":"abc","cantidad":1}]}' -b "$JAR_A")")"
igual "2 suma (3+2), 6 nuevo, 5 limitado al stock, 'abc' ignorado" "{2: 5, 5: 2, 6: 1}" "$(curl -s -b "$JAR_A" "$BASE/carrito/listar.php" | items)"

echo "== 8) CÁLCULO: subtotal, descuento (precio final), envío, total =="
sql "DELETE FROM carrito_items WHERE id_usuario=(SELECT id_usuario FROM usuarios WHERE email='carB$TS@test.com');"
post "$BASE/carrito/agregar.php" '{"id_producto":3,"cantidad":2}' -b "$JAR_B" >/dev/null   # 579 con 10% => 521.10, envío 50
R=$(curl -s -b "$JAR_B" "$BASE/carrito/listar.php")
igual "Subtotal (2 × 521.10)" 1042.2 "$(echo "$R" | json 'd["resumen"]["subtotal"]')"
igual "Envío (S/50, no se multiplica por 2)" 50.0 "$(echo "$R" | json 'd["resumen"]["costo_envio"]')"
igual "Total" 1092.2 "$(echo "$R" | json 'd["resumen"]["total"]')"
post "$BASE/carrito/agregar.php" '{"id_producto":8,"cantidad":1}' -b "$JAR_B" >/dev/null   # envío 100
igual "Envío con 50 y 100 en el carrito = el MAYOR (100), no la suma" 100.0 "$(curl -s -b "$JAR_B" "$BASE/carrito/listar.php" | json 'd["resumen"]["costo_envio"]')"
post "$BASE/carrito/actualizar.php" '{"id_producto":3,"cantidad":5}' -b "$JAR_B" >/dev/null
igual "…y no cambia al subir la cantidad" 100.0 "$(curl -s -b "$JAR_B" "$BASE/carrito/listar.php" | json 'd["resumen"]["costo_envio"]')"
sql "DELETE FROM carrito_items WHERE id_usuario=(SELECT id_usuario FROM usuarios WHERE email='carB$TS@test.com');"
post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":2}' -b "$JAR_B" >/dev/null   # envío gratis
igual "Todos con envío gratis = S/0.00" 0.0 "$(curl -s -b "$JAR_B" "$BASE/carrito/listar.php" | json 'd["resumen"]["costo_envio"]')"
resumen
