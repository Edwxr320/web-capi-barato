#!/bin/bash
# Bloque 4: inventario (consulta, alertas de stock bajo, ajustes de stock, permisos, validaciones, límites).
# Datos que modifica (solo en la base de pruebas): stock de los productos 5, 6, 7, 12 y estado del 12;
# registra un cliente; crea un pedido.
source "$(dirname "$0")/lib.sh"
JAR_A=$(mktemp); JAR_C=$(mktemp); TS=$(date +%s%N)
login "$JAR_A" "admin@capibarato.pe" "CapiBarato2026!"
registrar "$JAR_C" "Cliente Inventario" "inv$TS@test.com" "clave12345"
INV="$BASE/admin/inventario.php"
ajustar(){ post "$INV" "{\"id_producto\":$2,\"operacion\":\"$3\",\"cantidad\":$4}" -b "$1"; }
stock(){ sql "SELECT stock FROM productos WHERE id_producto=$1;"; }
FOTO_INICIAL=$(sql "SELECT GROUP_CONCAT(id_producto,':',stock ORDER BY id_producto) FROM productos;")

echo "== 1) PERMISOS (en el servidor) =="
check "GET sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$INV")"
check "POST sin sesión" 401 "$(codigo "$(post "$INV" '{"id_producto":1,"operacion":"fijar","cantidad":0}')")"
check "GET con sesión de cliente" 403 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$INV")"
check "POST de cliente con su token CSRF válido" 403 "$(codigo "$(ajustar "$JAR_C" 1 fijar 0)")"
check "POST de cliente intentando 'sumar' 999" 403 "$(codigo "$(ajustar "$JAR_C" 1 sumar 999)")"
check "POST de admin SIN token CSRF" 403 "$(codigo "$(post_sin_csrf "$INV" '{"id_producto":1,"operacion":"fijar","cantidad":0}' -b "$JAR_A")")"
check "POST de admin con Origin ajeno" 403 "$(codigo "$(post "$INV" '{"id_producto":1,"operacion":"fijar","cantidad":0}' -b "$JAR_A" -H 'Origin: https://evil.example')")"
check "PUT no permitido" 405 "$(curl -s -o /dev/null -w '%{http_code}' -X PUT -b "$JAR_A" "$INV")"
igual "Ningún intento rechazado cambió el stock" "$FOTO_INICIAL" "$(sql "SELECT GROUP_CONCAT(id_producto,':',stock ORDER BY id_producto) FROM productos;")"

echo "== 2) CONSULTA DE EXISTENCIAS Y ALERTAS =="
R=$(curl -s -b "$JAR_A" "$INV")
igual "Umbral de stock bajo por defecto" 5 "$(echo "$R" | json 'd["umbral"]')"
igual "Lista los 12 productos" 12 "$(echo "$R" | json 'len(d["productos"])')"
igual "Unidades totales = suma real en MySQL" "$(sql 'SELECT SUM(stock) FROM productos;')" "$(echo "$R" | json 'd["resumen"]["unidades_totales"]')"
igual "Alertas: 1 con stock bajo (Galaxy Book3, stock 2)" 1 "$(echo "$R" | json 'd["resumen"]["stock_bajo"]')"
igual "Alertas: 0 sin stock" 0 "$(echo "$R" | json 'd["resumen"]["sin_stock"]')"
igual "Ordenado de menor a mayor stock: primero el producto 5" 5 "$(echo "$R" | json 'd["productos"][0]["id_producto"]')"
verdadero "stock_bajo coincide con stock <= 5 en cada producto" "$(echo "$R" | json 'all(p["stock_bajo"] == (p["stock"] <= 5) for p in d["productos"])')"
igual "filtro=bajo devuelve solo el producto 5" "[5]" "$(curl -s -b "$JAR_A" "$INV?filtro=bajo" | json '[p["id_producto"] for p in d["productos"]]')"
igual "filtro=sin_stock vacío" 0 "$(curl -s -b "$JAR_A" "$INV?filtro=sin_stock" | json 'len(d["productos"])')"
check "filtro inválido" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$INV?filtro=raro")"
igual "q=samsung (4 productos)" 4 "$(curl -s -b "$JAR_A" "$INV?q=samsung" | json 'len(d["productos"])')"
igual "categoria=notebooks (4)" 4 "$(curl -s -b "$JAR_A" "$INV?categoria=notebooks" | json 'len(d["productos"])')"
check "categoría inexistente" 404 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$INV?categoria=noexiste")"
R=$(curl -s -w "\n%{http_code}" -b "$JAR_A" -G "$INV" --data-urlencode "q=' OR '1'='1")
check "Inyección SQL en q no rompe la consulta" 200 "$(codigo "$R")"; igual "…y se trata como texto (0 resultados)" 0 "$(cuerpo "$R" | json 'len(d["productos"])')"

echo "== 3) AJUSTES DE STOCK (verificados en MySQL) =="
R=$(ajustar "$JAR_A" 6 fijar 0); check "Fijar el producto 6 en 0" 200 "$(codigo "$R")"
igual "MySQL: stock 0" 0 "$(stock 6)"
igual "Respuesta: stock anterior 35 y marca sin_stock" "35|0|True" "$(cuerpo "$R" | json '"|".join(str(d["producto"][k]) for k in ("stock_anterior","stock","sin_stock"))')"
R=$(curl -s -b "$JAR_A" "$INV")
igual "Alertas actualizadas: 2 con stock bajo, 1 sin stock" "2|1" "$(echo "$R" | json '"|".join(str(d["resumen"][k]) for k in ("stock_bajo","sin_stock"))')"
check "Sumar 7" 200 "$(codigo "$(ajustar "$JAR_A" 6 sumar 7)")"; igual "MySQL: 0 + 7 = 7" 7 "$(stock 6)"
igual "7 > umbral: ya no es stock bajo" False "$(curl -s -b "$JAR_A" "$INV?q=Noblex" | json 'd["productos"][0]["stock_bajo"]')"
check "Restar 2" 200 "$(codigo "$(ajustar "$JAR_A" 6 restar 2)")"; igual "MySQL: 7 - 2 = 5" 5 "$(stock 6)"
igual "Stock 5 = umbral: SÍ es stock bajo (límite incluido)" True "$(curl -s -b "$JAR_A" "$INV?q=Noblex" | json 'd["productos"][0]["stock_bajo"]')"
R=$(ajustar "$JAR_A" 6 restar 6); check "Restar más de lo que hay" 409 "$(codigo "$R")"
verdadero "El mensaje explica el stock actual" "$(cuerpo "$R" | json '"es 5" in d["error"]')"
igual "MySQL: sigue en 5 (nunca negativo)" 5 "$(stock 6)"
check "Restar exactamente todo (5)" 200 "$(codigo "$(ajustar "$JAR_A" 6 restar 5)")"; igual "MySQL: 0" 0 "$(stock 6)"
check "Restar 1 con stock 0" 409 "$(codigo "$(ajustar "$JAR_A" 6 restar 1)")"; igual "MySQL: sigue en 0" 0 "$(stock 6)"
check "Cantidad como texto numérico (\"12\")" 200 "$(codigo "$(post "$INV" '{"id_producto":"6","operacion":"fijar","cantidad":"12"}' -b "$JAR_A")")"; igual "MySQL: 12" 12 "$(stock 6)"

echo "== 4) VALIDACIONES Y LÍMITES (ninguno cambia el stock) =="
ANTES=$(stock 6)
check "fijar negativo (-1)" 400 "$(codigo "$(ajustar "$JAR_A" 6 fijar -1)")"
check "decimal (2.5)" 400 "$(codigo "$(ajustar "$JAR_A" 6 sumar 2.5)")"
check "texto (\"abc\")" 400 "$(codigo "$(ajustar "$JAR_A" 6 sumar '"abc"')")"
check "booleano (true)" 400 "$(codigo "$(ajustar "$JAR_A" 6 sumar true)")"
check "sumar 0" 400 "$(codigo "$(ajustar "$JAR_A" 6 sumar 0)")"
check "restar 0" 400 "$(codigo "$(ajustar "$JAR_A" 6 restar 0)")"
check "sin cantidad" 400 "$(codigo "$(post "$INV" '{"id_producto":6,"operacion":"sumar"}' -b "$JAR_A")")"
check "operación inválida" 400 "$(codigo "$(ajustar "$JAR_A" 6 multiplicar 2)")"
check "sin operación" 400 "$(codigo "$(post "$INV" '{"id_producto":6,"cantidad":2}' -b "$JAR_A")")"
check "producto inexistente" 404 "$(codigo "$(ajustar "$JAR_A" 9999 sumar 1)")"
check "id con inyección SQL" 400 "$(codigo "$(post "$INV" '{"id_producto":"6 OR 1=1","operacion":"fijar","cantidad":0}' -b "$JAR_A")")"
check "fijar por encima del máximo (1.000.001)" 400 "$(codigo "$(ajustar "$JAR_A" 6 fijar 1000001)")"
check "fijar en el máximo exacto (1.000.000)" 200 "$(codigo "$(ajustar "$JAR_A" 7 fijar 1000000)")"
check "sumar que haría superar el máximo" 400 "$(codigo "$(ajustar "$JAR_A" 7 sumar 1)")"
check "número enorme (fuera de rango INT)" 400 "$(codigo "$(ajustar "$JAR_A" 6 fijar 99999999999999)")"
check "cuerpo que no es JSON" 400 "$(codigo "$(post "$INV" 'no-json' -b "$JAR_A")")"
igual "El stock del producto 6 no cambió con los rechazos" "$ANTES" "$(stock 6)"
ajustar "$JAR_A" 7 fijar 35 >/dev/null

echo "== 5) PRODUCTOS INACTIVOS: se gestionan pero no generan alertas =="
sql "UPDATE productos SET estado='inactivo', stock=1 WHERE id_producto=12;"
R=$(curl -s -b "$JAR_A" "$INV")
verdadero "El inactivo aparece en la lista con su estado" "$(echo "$R" | json 'any(p["id_producto"]==12 and p["estado"]=="inactivo" and p["stock_bajo"] for p in d["productos"])')"
igual "…pero no suma a las alertas (solo productos activos)" "$(sql "SELECT COUNT(*) FROM productos WHERE estado='activo' AND stock<=5;")" "$(echo "$R" | json 'd["resumen"]["stock_bajo"]')"
check "Se puede reponer stock de un inactivo" 200 "$(codigo "$(ajustar "$JAR_A" 12 sumar 4)")"; igual "MySQL: 5" 5 "$(stock 12)"
sql "UPDATE productos SET estado='activo', stock=10 WHERE id_producto=12;"

echo "== 6) EL INVENTARIO MANDA EN EL CATÁLOGO Y EN EL CHECKOUT =="
post "$BASE/carrito/agregar.php" '{"id_producto":5,"cantidad":2}' -b "$JAR_C" >/dev/null
ajustar "$JAR_A" 5 fijar 1 >/dev/null
igual "El catálogo público muestra el stock nuevo" 1 "$(curl -s "$BASE/productos/detalle.php?id=5" | json 'd["producto"]["stock"]')"
check "Checkout con 2 en el carrito y stock 1: rechazado" 409 "$(codigo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Inventario 123"}' -b "$JAR_C")")"
ajustar "$JAR_A" 5 sumar 1 >/dev/null
check "Tras reponer (stock 2), el checkout pasa" 201 "$(codigo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Inventario 123"}' -b "$JAR_C")")"
igual "…y deja el stock en 0" 0 "$(stock 5)"
igual "Ahora el producto 5 figura sin stock" True "$(curl -s -b "$JAR_A" "$INV?filtro=sin_stock" | json 'any(p["id_producto"]==5 for p in d["productos"])')"

echo "== 7) AJUSTES SIMULTÁNEOS (5 'restar 1' a la vez sobre stock 3) =="
ajustar "$JAR_A" 5 fijar 3 >/dev/null
TMPD=$(mktemp -d)
for i in 1 2 3 4 5; do ( c=$(ajustar "$JAR_A" 5 restar 1 | tail -n1); echo "$c" > "$TMPD/$i" ) & done; wait
igual "Exactamente 3 aceptados" 3 "$(cat "$TMPD"/* | grep -c '^200$')"
igual "…y 2 rechazados con 409" 2 "$(cat "$TMPD"/* | grep -c '^409$')"
igual "Stock final 0, nunca negativo" 0 "$(stock 5)"
rm -rf "$TMPD"

echo "== 8) UMBRAL CONFIGURABLE SIN TOCAR EL CÓDIGO =="
# Se levanta un segundo servidor cuya configuración define STOCK_BAJO_UMBRAL=10 antes de cargar config.php
# (php -d auto_prepend_file). Mismo código, misma base de pruebas: solo cambia la configuración.
PUERTO2=${BASE#http://127.0.0.1:}; PUERTO2=$(( ${PUERTO2%%/*} + 1 ))
PRE=$(mktemp --suffix=.php); echo "<?php define('STOCK_BAJO_UMBRAL', 10);" > "$PRE"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
MERCADOWEB_DB_PRUEBAS="$DB_NAME" php -d auto_prepend_file="$PRE" -d display_errors=0 -d log_errors=0 -S "127.0.0.1:$PUERTO2" -t "$RAIZ" >/dev/null 2>&1 &
PID2=$!; for _ in $(seq 1 25); do curl -s -o /dev/null "http://127.0.0.1:$PUERTO2/index.html" && break; sleep 0.2; done
JAR_A2=$(mktemp); curl -s -c "$JAR_A2" -X POST "http://127.0.0.1:$PUERTO2/api/auth/login.php" -H "Content-Type: application/json" -d '{"email":"admin@capibarato.pe","password":"CapiBarato2026!"}' >/dev/null
R=$(curl -s -b "$JAR_A2" "http://127.0.0.1:$PUERTO2/api/admin/inventario.php")
kill $PID2 2>/dev/null; rm -f "$PRE"
igual "Con STOCK_BAJO_UMBRAL=10 el endpoint informa umbral 10" 10 "$(echo "$R" | json 'd["umbral"]')"
igual "…y marca como stock bajo los productos activos con stock <= 10" "$(sql "SELECT COUNT(*) FROM productos WHERE estado='activo' AND stock<=10;")" "$(echo "$R" | json 'd["resumen"]["stock_bajo"]')"
rm -f "$JAR_A" "$JAR_C" "$JAR_A2"
resumen
