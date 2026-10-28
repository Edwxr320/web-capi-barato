#!/bin/bash
# Bloque 5: API del panel administrativo (productos, imágenes, categorías, usuarios, pedidos, estadísticas).
# Datos que modifica (solo en la base de pruebas): crea/edita/desactiva/elimina productos y categorías de prueba,
# sube imágenes a img/productos/ (las borra al final), registra clientes, cambia roles/estados, crea pedidos y
# cambia sus estados. Los 12 productos originales no se editan, salvo estados restaurados al final de cada caso.
source "$(dirname "$0")/lib.sh"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
JAR_A=$(mktemp); JAR_C=$(mktemp); JAR_D=$(mktemp); TS=$(date +%s%N); TMPD=$(mktemp -d)
login "$JAR_A" "admin@capibarato.pe" "CapiBarato2026!"
registrar "$JAR_C" "Cliente Admin Test" "cadm$TS@test.com" "clave12345"
registrar "$JAR_D" "Cliente Dos" "cdos$TS@test.com" "clave12345"
ID_ADMIN=$(sql "SELECT id_usuario FROM usuarios WHERE email='admin@capibarato.pe';")
ID_C=$(sql "SELECT id_usuario FROM usuarios WHERE email='cadm$TS@test.com';")
ID_D=$(sql "SELECT id_usuario FROM usuarios WHERE email='cdos$TS@test.com';")
P="$BASE/admin/productos.php"; CAT="$BASE/admin/categorias.php"; U="$BASE/admin/usuarios.php"
PED="$BASE/admin/pedidos.php"; EST="$BASE/admin/estadisticas.php"; IMG="$BASE/admin/imagenes.php"
padm(){ post "$1" "$2" -b "$JAR_A"; }   # POST como admin (con token CSRF)
json_de(){ python3 -c 'import json,sys; print(json.dumps(json.loads(sys.argv[1])))' "$1"; }
huella(){ sql "SELECT CONCAT((SELECT COUNT(*) FROM productos),'|',(SELECT COUNT(*) FROM categorias),'|',(SELECT GROUP_CONCAT(id_usuario,rol,estado ORDER BY id_usuario) FROM usuarios),'|',(SELECT IFNULL(GROUP_CONCAT(id_pedido,estado),'') FROM pedidos),'|',(SELECT GROUP_CONCAT(id_producto,estado,precio_original ORDER BY id_producto) FROM productos));"; }
png(){ python3 - "$1" "$2" "$3" <<'PY'
import sys, zlib, struct
ruta, w, h = sys.argv[1], int(sys.argv[2]), int(sys.argv[3])
def chunk(t, d): return struct.pack(">I", len(d)) + t + d + struct.pack(">I", zlib.crc32(t + d) & 0xffffffff)
raw = b"".join(b"\x00" + bytes([52, 131, 250]) * w for _ in range(h))
open(ruta, "wb").write(b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b""))
PY
}
subir(){ curl -s -w "\n%{http_code}" -X POST "$IMG" -b "$1" -H "X-CSRF-Token: $(token_de "$1")" -F "imagen=@$2${3:+;type=$3}"; }

echo "== 1) PERMISOS EN EL SERVIDOR (todas las rutas admin) =="
H0=$(huella)
for e in productos categorias usuarios pedidos inventario estadisticas imagenes; do
    check "GET $e sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/$e.php")"
    check "GET $e como cliente" 403 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/admin/$e.php")"
done
check "Cliente crea producto (con su token válido)" 403 "$(codigo "$(post "$P" '{"accion":"crear","nombre":"Hack","id_categoria":1,"precio_original":1,"stock":1,"envio_gratis":true,"estado":"activo","imagenes":["img/celulares/11.png"]}' -b "$JAR_C")")"
check "Cliente se autopromueve a admin" 403 "$(codigo "$(post "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_C,\"rol\":\"admin\"}" -b "$JAR_C")")"
check "Cliente marca un pedido como pagado" 403 "$(codigo "$(post "$PED" '{"accion":"estado","id_pedido":1,"estado":"pagado"}' -b "$JAR_C")")"
check "Cliente borra una categoría" 403 "$(codigo "$(post "$CAT" '{"accion":"eliminar","id_categoria":1}' -b "$JAR_C")")"
check "Cliente desactiva al admin" 403 "$(codigo "$(post "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_ADMIN,\"estado\":\"inactivo\"}" -b "$JAR_C")")"
check "Sin sesión: crear producto" 401 "$(codigo "$(post "$P" '{"accion":"crear"}')")"
check "Admin SIN token CSRF: crear categoría" 403 "$(codigo "$(post_sin_csrf "$CAT" '{"accion":"crear","nombre":"Sin Token"}' -b "$JAR_A")")"
check "Admin SIN token CSRF: cambiar rol" 403 "$(codigo "$(post_sin_csrf "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_C,\"rol\":\"admin\"}" -b "$JAR_A")")"
check "POST a estadísticas (solo lectura)" 405 "$(codigo "$(padm "$EST" '{}')")"
check "DELETE no permitido" 405 "$(curl -s -o /dev/null -w '%{http_code}' -X DELETE -b "$JAR_A" "$P")"
igual "Ningún intento rechazado cambió datos" "$H0" "$(huella)"

echo "== 2) PRODUCTOS: LISTADO Y DETALLE =="
igual "Lista los 12 productos" 12 "$(curl -s -b "$JAR_A" "$P" | json 'len(d["productos"])')"
igual "Filtro estado=activo" 12 "$(curl -s -b "$JAR_A" "$P?estado=activo" | json 'len(d["productos"])')"
igual "Filtro q=samsung" 4 "$(curl -s -b "$JAR_A" "$P?q=samsung" | json 'len(d["productos"])')"
igual "Filtro categoria=televisores" 4 "$(curl -s -b "$JAR_A" "$P?categoria=televisores" | json 'len(d["productos"])')"
check "estado inválido" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$P?estado=borrado")"
R=$(curl -s -b "$JAR_A" "$P?id=1")
igual "Detalle: 3 imágenes y 6 características" "3|6" "$(echo "$R" | json 'str(len(d["producto"]["imagenes"]))+"|"+str(len(d["producto"]["caracteristicas"]))')"
igual "Detalle: precio final 429.14 (datos de B-03 sin tocar)" 429.14 "$(echo "$R" | json 'd["producto"]["precio_final"]')"
check "Detalle inexistente" 404 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$P?id=9999")"
check "Detalle con id inválido" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$P?id=1%20OR%201=1")"

echo "== 3) PRODUCTOS: CREAR =="
NOMBRE_XSS='Parlante <b>Prueba</b> <img src=x onerror=alert(1)>'
BASEP=$(json_de "{\"accion\":\"crear\",\"nombre\":$(python3 -c 'import json,sys;print(json.dumps(sys.argv[1]))' "$NOMBRE_XSS"),\"id_categoria\":1,\"descripcion\":\"Parlante portátil\",\"precio_original\":199.90,\"descuento_porcentaje\":10,\"stock\":8,\"proveedor\":\"AUDIO SAC\",\"envio_gratis\":false,\"costo_envio\":25.5,\"estado\":\"activo\",\"imagenes\":[\"img/celulares/11.png\",\"img/celulares/12.png\"],\"caracteristicas\":[\"Bluetooth 5.0\",\"   \",\"Batería de 10 h\"]}")
R=$(padm "$P" "$BASEP"); check "Crear producto válido" 201 "$(codigo "$R")"
ID_N=$(cuerpo "$R" | json 'd["producto"]["id_producto"]')
igual "MySQL: fila con todos los campos" "1|199.90|10|8|AUDIO SAC|0|25.50|activo" "$(sql "SELECT CONCAT_WS('|',id_categoria,precio_original,descuento_porcentaje,stock,proveedor,envio_gratis,costo_envio,estado) FROM productos WHERE id_producto=$ID_N;")"
igual "MySQL: nombre guardado literal (sin alterar)" "$NOMBRE_XSS" "$(sql "SELECT nombre FROM productos WHERE id_producto=$ID_N;")"
igual "MySQL: 2 imágenes en orden" "img/celulares/11.png,img/celulares/12.png" "$(sql "SELECT GROUP_CONCAT(url_imagen ORDER BY orden) FROM producto_imagenes WHERE id_producto=$ID_N;")"
igual "MySQL: 2 características (la vacía se descarta)" "Bluetooth 5.0|Batería de 10 h" "$(sql "SELECT GROUP_CONCAT(descripcion ORDER BY orden SEPARATOR '|') FROM producto_caracteristicas WHERE id_producto=$ID_N;")"
igual "Precio final calculado por el servidor (199.90 − 10 %)" 179.91 "$(cuerpo "$R" | json 'd["producto"]["precio_final"]')"
igual "La tienda ya lo muestra (13 productos)" 13 "$(curl -s "$BASE/productos/listar.php" | json 'len(d["productos"])')"
B2=$(echo "$BASEP" | python3 -c 'import json,sys; d=json.load(sys.stdin); d["nombre"]="Producto Envío Gratis"; d["envio_gratis"]=True; d["costo_envio"]=50; print(json.dumps(d))')
R=$(padm "$P" "$B2"); ID_G=$(cuerpo "$R" | json 'd["producto"]["id_producto"]')
igual "Con envío gratis el costo se guarda NULL aunque se mande 50" "1|NULL" "$(sql "SELECT CONCAT(envio_gratis,'|',IFNULL(costo_envio,'NULL')) FROM productos WHERE id_producto=$ID_G;")"

echo "== 4) PRODUCTOS: VALIDACIONES (ninguna crea nada) =="
N0=$(sql "SELECT COUNT(*) FROM productos;")
mal(){ echo "$BASEP" | python3 -c "import json,sys; d=json.load(sys.stdin); $1; print(json.dumps(d))"; }
check "nombre de 2 caracteres" 400 "$(codigo "$(padm "$P" "$(mal 'd["nombre"]="ab"')")")"
check "nombre de 151 caracteres" 400 "$(codigo "$(padm "$P" "$(mal 'd["nombre"]="x"*151')")")"
check "categoría inexistente" 400 "$(codigo "$(padm "$P" "$(mal 'd["id_categoria"]=999')")")"
check "precio 0" 400 "$(codigo "$(padm "$P" "$(mal 'd["precio_original"]=0')")")"
check "precio negativo" 400 "$(codigo "$(padm "$P" "$(mal 'd["precio_original"]=-10')")")"
check "precio con 3 decimales" 400 "$(codigo "$(padm "$P" "$(mal 'd["precio_original"]="10.123"')")")"
check "precio como texto" 400 "$(codigo "$(padm "$P" "$(mal 'd["precio_original"]="caro"')")")"
check "descuento 100" 400 "$(codigo "$(padm "$P" "$(mal 'd["descuento_porcentaje"]=100')")")"
check "descuento negativo" 400 "$(codigo "$(padm "$P" "$(mal 'd["descuento_porcentaje"]=-5')")")"
check "descuento decimal" 400 "$(codigo "$(padm "$P" "$(mal 'd["descuento_porcentaje"]=10.5')")")"
check "stock negativo" 400 "$(codigo "$(padm "$P" "$(mal 'd["stock"]=-1')")")"
check "stock decimal" 400 "$(codigo "$(padm "$P" "$(mal 'd["stock"]=2.5')")")"
check "envío pago sin costo" 400 "$(codigo "$(padm "$P" "$(mal 'del d["costo_envio"]')")")"
check "envio_gratis no booleano (\"si\")" 400 "$(codigo "$(padm "$P" "$(mal 'd["envio_gratis"]="si"')")")"
check "estado inválido" 400 "$(codigo "$(padm "$P" "$(mal 'd["estado"]="borrado"')")")"
check "sin imágenes" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=[]')")")"
check "imagen que no existe" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["img/celulares/no-existe.png"]')")")"
check "imagen con ../ (path traversal)" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["img/../api/config/config.php"]')")")"
check "imagen fuera de img/" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["api/config/config.example.php"]')")")"
check "imagen como URL externa" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["https://evil.example/x.png"]')")")"
check "imagen con javascript:" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["javascript:alert(1)"]')")")"
check "11 imágenes" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["img/celulares/11.png"]*11')")")"
check "imágenes repetidas" 400 "$(codigo "$(padm "$P" "$(mal 'd["imagenes"]=["img/celulares/11.png","img/celulares/11.png"]')")")"
check "características que no son lista" 400 "$(codigo "$(padm "$P" "$(mal 'd["caracteristicas"]="texto"')")")"
check "característica de 256 caracteres" 400 "$(codigo "$(padm "$P" "$(mal 'd["caracteristicas"]=["x"*256]')")")"
check "acción inválida" 400 "$(codigo "$(padm "$P" '{"accion":"borrar_todo"}')")"
igual "No se creó ningún producto con esos datos" "$N0" "$(sql "SELECT COUNT(*) FROM productos;")"

echo "== 5) PRODUCTOS: ACTUALIZAR Y PRECIO HISTÓRICO =="
post "$BASE/carrito/agregar.php" "{\"id_producto\":$ID_N,\"cantidad\":2}" -b "$JAR_C" >/dev/null
R=$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Historico 123"}' -b "$JAR_C"); ID_PH=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
igual "Pedido con precio 179.91 × 2 + envío 25.50" 385.32 "$(sql "SELECT total FROM pedidos WHERE id_pedido=$ID_PH;")"
UPD=$(echo "$BASEP" | python3 -c "import json,sys; d=json.load(sys.stdin); d.update(accion='actualizar', id_producto=$ID_N, nombre='Parlante Editado', precio_original=249.90, descuento_porcentaje=None, stock=20, imagenes=['img/celulares/13.png','img/celulares/11.png'], caracteristicas=['Nueva característica']); print(json.dumps(d))")
check "Actualizar producto" 200 "$(codigo "$(padm "$P" "$UPD")")"
igual "MySQL: nombre, precio, sin descuento, stock" "Parlante Editado|249.90|NULL|20" "$(sql "SELECT CONCAT_WS('|',nombre,precio_original,IFNULL(descuento_porcentaje,'NULL'),stock) FROM productos WHERE id_producto=$ID_N;")"
igual "MySQL: imágenes reemplazadas y reordenadas" "img/celulares/13.png,img/celulares/11.png" "$(sql "SELECT GROUP_CONCAT(url_imagen ORDER BY orden) FROM producto_imagenes WHERE id_producto=$ID_N;")"
igual "MySQL: características reemplazadas" "Nueva característica" "$(sql "SELECT GROUP_CONCAT(descripcion) FROM producto_caracteristicas WHERE id_producto=$ID_N;")"
igual "La tienda muestra el precio nuevo (249.90)" 249.9 "$(curl -s "$BASE/productos/detalle.php?id=$ID_N" | json 'd["producto"]["precio_final"]')"
igual "El pedido anterior conserva 179.91 por unidad" 179.91 "$(sql "SELECT precio_unitario FROM detalle_pedido WHERE id_pedido=$ID_PH;")"
igual "…y su total 385.32" 385.32 "$(curl -s -b "$JAR_C" "$BASE/pedidos/detalle.php?id=$ID_PH" | json 'd["pedido"]["total"]')"
check "Actualizar producto inexistente" 404 "$(codigo "$(padm "$P" "$(echo "$UPD" | python3 -c 'import json,sys; d=json.load(sys.stdin); d["id_producto"]=99999; print(json.dumps(d))')")")"

echo "== 6) PRODUCTOS: ACTIVAR / DESACTIVAR =="
check "Desactivar" 200 "$(codigo "$(padm "$P" "{\"accion\":\"estado\",\"id_producto\":$ID_N,\"estado\":\"inactivo\"}")")"
check "La tienda ya no lo muestra (detalle 404)" 404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php?id=$ID_N")"
check "…ni se puede agregar al carrito" 404 "$(codigo "$(post "$BASE/carrito/agregar.php" "{\"id_producto\":$ID_N,\"cantidad\":1}" -b "$JAR_D")")"
igual "…pero sigue en el panel" inactivo "$(curl -s -b "$JAR_A" "$P?id=$ID_N" | json 'd["producto"]["estado"]')"
check "Activar" 200 "$(codigo "$(padm "$P" "{\"accion\":\"estado\",\"id_producto\":$ID_N,\"estado\":\"activo\"}")")"
check "Vuelve a la tienda" 200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php?id=$ID_N")"
check "Estado inválido" 400 "$(codigo "$(padm "$P" "{\"accion\":\"estado\",\"id_producto\":$ID_N,\"estado\":\"x\"}")")"

echo "== 7) PRODUCTOS: ELIMINAR (con ventas se desactiva; sin ventas se borra) =="
DET0=$(sql "SELECT COUNT(*) FROM detalle_pedido WHERE id_producto=$ID_N;")
R=$(padm "$P" "{\"accion\":\"eliminar\",\"id_producto\":$ID_N}")
check "Eliminar producto CON ventas responde OK" 200 "$(codigo "$R")"
igual "…pero el resultado es 'desactivado'" desactivado "$(cuerpo "$R" | json 'd["resultado"]')"
igual "MySQL: el producto sigue existiendo, inactivo" inactivo "$(sql "SELECT estado FROM productos WHERE id_producto=$ID_N;")"
igual "MySQL: su historial de ventas está intacto" "$DET0" "$(sql "SELECT COUNT(*) FROM detalle_pedido WHERE id_producto=$ID_N;")"
igual "El cliente sigue viendo su pedido con ese producto" 1 "$(curl -s -b "$JAR_C" "$BASE/pedidos/detalle.php?id=$ID_PH" | json 'len(d["pedido"]["items"])')"
post "$BASE/carrito/agregar.php" "{\"id_producto\":$ID_G,\"cantidad\":1}" -b "$JAR_D" >/dev/null
R=$(padm "$P" "{\"accion\":\"eliminar\",\"id_producto\":$ID_G}")
igual "Eliminar producto SIN ventas: 'eliminado'" eliminado "$(cuerpo "$R" | json 'd["resultado"]')"
igual "MySQL: sin rastros (producto, imágenes, características, carritos)" "0|0|0|0" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM productos WHERE id_producto=$ID_G),'|',(SELECT COUNT(*) FROM producto_imagenes WHERE id_producto=$ID_G),'|',(SELECT COUNT(*) FROM producto_caracteristicas WHERE id_producto=$ID_G),'|',(SELECT COUNT(*) FROM carrito_items WHERE id_producto=$ID_G));")"
check "Eliminar uno que ya no existe" 404 "$(codigo "$(padm "$P" "{\"accion\":\"eliminar\",\"id_producto\":$ID_G}")")"
post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_D" >/dev/null
check "Se vende 1 Moto G13 (producto original)" 201 "$(codigo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Venta Moto 321"}' -b "$JAR_D")")"
R=$(padm "$P" '{"accion":"eliminar","id_producto":1}')
igual "Producto original con ventas (Moto G13) tampoco se borra: se desactiva" desactivado "$(cuerpo "$R" | json 'd["resultado"]')"
padm "$P" '{"accion":"estado","id_producto":1,"estado":"activo"}' >/dev/null
igual "Siguen los 12 productos originales" 12 "$(sql "SELECT COUNT(*) FROM productos WHERE id_producto<=12;")"

echo "== 8) IMÁGENES: SUBIDA SEGURA =="
igual "Lista imágenes existentes (incluye img/celulares/11.png, excluye el logo)" "True|False" "$(curl -s -b "$JAR_A" "$IMG" | json 'str("img/celulares/11.png" in d["imagenes"])+"|"+str(any("Imagen7" in x for x in d["imagenes"]))')"
png "$TMPD/ok.png" 120 90
R=$(subir "$JAR_A" "$TMPD/ok.png"); check "PNG válido (120×90)" 201 "$(codigo "$R")"
RUTA=$(cuerpo "$R" | json 'd["ruta"]')
verdadero "Nombre generado por el servidor (img/productos/<aleatorio>.png)" "$(python3 -c "import re,sys; print(bool(re.fullmatch(r'img/productos/[0-9a-f]{24}\.png', sys.argv[1])))" "$RUTA")"
[ -f "$RAIZ/$RUTA" ] && ok "El archivo existe en el disco" || ko "El archivo no se guardó"
check "…y se sirve por HTTP" 200 "$(curl -s -o /dev/null -w '%{http_code}' "${BASE%/api}/$RUTA")"
check "La imagen subida se puede usar en un producto" 201 "$(codigo "$(padm "$P" "$(mal "d['nombre']='Con imagen subida'; d['imagenes']=['$RUTA']")")")"
printf '<?php echo "pwned"; ?>' > "$TMPD/falso.png"
check "PHP disfrazado de .png" 400 "$(codigo "$(subir "$JAR_A" "$TMPD/falso.png" image/png)")"
printf '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="100" height="100"/></svg>' > "$TMPD/x.svg"
check "SVG (puede llevar scripts)" 400 "$(codigo "$(subir "$JAR_A" "$TMPD/x.svg" image/png)")"
png "$TMPD/chica.png" 20 20
check "Imagen de 20×20 (muy chica)" 400 "$(codigo "$(subir "$JAR_A" "$TMPD/chica.png")")"
head -c 2200000 /dev/urandom > "$TMPD/grande.png"
check "Archivo de 2,2 MB" 400 "$(codigo "$(subir "$JAR_A" "$TMPD/grande.png" image/png)")"
check "Sin archivo" 400 "$(codigo "$(curl -s -w "\n%{http_code}" -X POST "$IMG" -b "$JAR_A" -H "X-CSRF-Token: $(token_de "$JAR_A")" -F "otro=1")")"
check "Cliente no puede subir" 403 "$(codigo "$(subir "$JAR_C" "$TMPD/ok.png")")"
check "Admin sin token CSRF no puede subir" 403 "$(codigo "$(curl -s -w "\n%{http_code}" -X POST "$IMG" -b "$JAR_A" -F "imagen=@$TMPD/ok.png")")"
igual "Solo quedó 1 archivo subido en img/productos (los rechazados no se guardaron)" 1 "$(ls "$RAIZ/img/productos" | grep -cv '^\.')"

echo "== 9) CATEGORÍAS =="
R=$(curl -s -b "$JAR_A" "$CAT"); igual "Lista las 3 categorías" 3 "$(echo "$R" | json 'len(d["categorias"])')"
igual "Cuenta productos por categoría (Celulares: 4 originales + 2 de prueba)" 6 "$(echo "$R" | json '[c["productos"] for c in d["categorias"] if c["slug"]=="celulares"][0]')"
R=$(padm "$CAT" '{"accion":"crear","nombre":"Audio y Sonido"}'); check "Crear 'Audio y Sonido'" 201 "$(codigo "$R")"
ID_CAT=$(cuerpo "$R" | json 'd["id_categoria"]')
igual "Slug generado automáticamente" audio-y-sonido "$(sql "SELECT slug FROM categorias WHERE id_categoria=$ID_CAT;")"
check "Crear 'Cámaras' (slug sin tilde)" 201 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"Cámaras"}')")"
igual "Slug: camaras" 1 "$(sql "SELECT COUNT(*) FROM categorias WHERE slug='camaras';")"
check "Nombre duplicado (aunque cambien mayúsculas)" 409 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"audio y sonido","slug":"otro-slug"}')")"
check "Slug duplicado" 409 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"Otra","slug":"celulares"}')")"
check "Slug con espacios" 400 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"Otra","slug":"con espacios"}')")"
check "Slug reservado 'todos'" 400 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"Todos"}')")"
check "Nombre de 1 carácter" 400 "$(codigo "$(padm "$CAT" '{"accion":"crear","nombre":"A"}')")"
check "Nombre de 51 caracteres" 400 "$(codigo "$(padm "$CAT" "{\"accion\":\"crear\",\"nombre\":\"$(printf 'x%.0s' $(seq 51))\"}")")"
igual "La tienda ya lista la categoría nueva" True "$(curl -s "$BASE/categorias/listar.php" | json 'any(c["slug"]=="audio-y-sonido" for c in d["categorias"])')"
igual "…y filtrar por ella responde 0 productos (no 404)" 0 "$(curl -s "$BASE/productos/listar.php?categoria=audio-y-sonido" | json 'len(d["productos"])')"
check "Renombrar" 200 "$(codigo "$(padm "$CAT" "{\"accion\":\"actualizar\",\"id_categoria\":$ID_CAT,\"nombre\":\"Audio\",\"slug\":\"audio\"}")")"
igual "MySQL: renombrada" "Audio|audio" "$(sql "SELECT CONCAT(nombre,'|',slug) FROM categorias WHERE id_categoria=$ID_CAT;")"
R=$(padm "$CAT" '{"accion":"eliminar","id_categoria":1}'); check "Eliminar 'Celulares' (tiene productos) — FK RESTRICT" 409 "$(codigo "$R")"
verdadero "El mensaje explica cuántos productos tiene" "$(cuerpo "$R" | json '"producto" in d["error"]')"
padm "$P" "$(echo "$UPD" | python3 -c "import json,sys; d=json.load(sys.stdin); d['id_categoria']=$ID_CAT; print(json.dumps(d))")" >/dev/null
check "Con un producto movido a 'Audio', tampoco se puede eliminar" 409 "$(codigo "$(padm "$CAT" "{\"accion\":\"eliminar\",\"id_categoria\":$ID_CAT}")")"
padm "$P" "$UPD" >/dev/null   # lo devuelve a Celulares
check "Vacía: se elimina" 200 "$(codigo "$(padm "$CAT" "{\"accion\":\"eliminar\",\"id_categoria\":$ID_CAT}")")"
check "Eliminar inexistente" 404 "$(codigo "$(padm "$CAT" "{\"accion\":\"eliminar\",\"id_categoria\":$ID_CAT}")")"

echo "== 10) USUARIOS =="
R=$(curl -s -b "$JAR_A" "$U")
verdadero "Nunca expone password_hash" "$(echo "$R" | json 'all("password_hash" not in u and "password" not in u for u in d["usuarios"])')"
igual "Filtro rol=admin" 1 "$(curl -s -b "$JAR_A" "$U?rol=admin" | json 'len(d["usuarios"])')"
igual "Búsqueda por correo" 1 "$(curl -s -b "$JAR_A" "$U?q=cadm$TS" | json 'len(d["usuarios"])')"
igual "Ficha del cliente con sus pedidos" 1 "$(curl -s -b "$JAR_A" "$U?id=$ID_C" | json 'len(d["usuario"]["lista_pedidos"])')"
check "Desactivar al cliente" 200 "$(codigo "$(padm "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_C,\"estado\":\"inactivo\"}")")"
check "…su sesión abierta deja de valer" 401 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/pedidos/listar.php")"
check "…y no puede iniciar sesión" 403 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"cadm$TS@test.com\",\"password\":\"clave12345\"}")")"
igual "…sus pedidos se conservan" 1 "$(sql "SELECT COUNT(*) FROM pedidos WHERE id_usuario=$ID_C;")"
check "Reactivar" 200 "$(codigo "$(padm "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_C,\"estado\":\"activo\"}")")"
login "$JAR_C" "cadm$TS@test.com" "clave12345"
check "…vuelve a entrar" 200 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/pedidos/listar.php")"
check "El admin no puede desactivarse a sí mismo" 409 "$(codigo "$(padm "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_ADMIN,\"estado\":\"inactivo\"}")")"
check "…ni quitarse el rol" 409 "$(codigo "$(padm "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_ADMIN,\"rol\":\"cliente\"}")")"
check "Promover a admin a un cliente" 200 "$(codigo "$(padm "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_D,\"rol\":\"admin\"}")")"
check "…entra al panel sin volver a loguearse" 200 "$(curl -s -b "$JAR_D" -o /dev/null -w '%{http_code}' "$EST")"
check "El nuevo admin desactiva al admin original (queda él activo)" 200 "$(codigo "$(post "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_ADMIN,\"estado\":\"inactivo\"}" -b "$JAR_D")")"
check "…el admin original pierde el acceso" 401 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$EST")"
post "$U" "{\"accion\":\"estado\",\"id_usuario\":$ID_ADMIN,\"estado\":\"activo\"}" -b "$JAR_D" >/dev/null
login "$JAR_A" "admin@capibarato.pe" "CapiBarato2026!"
check "Reactivado, el admin original vuelve a entrar" 200 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$EST")"
check "Quitarle el rol al segundo admin" 200 "$(codigo "$(padm "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_D,\"rol\":\"cliente\"}")")"
check "…pierde el acceso en el acto" 403 "$(curl -s -b "$JAR_D" -o /dev/null -w '%{http_code}' "$EST")"
check "Rol inválido" 400 "$(codigo "$(padm "$U" "{\"accion\":\"rol\",\"id_usuario\":$ID_D,\"rol\":\"superadmin\"}")")"
check "Usuario inexistente" 404 "$(codigo "$(padm "$U" '{"accion":"estado","id_usuario":99999,"estado":"activo"}')")"
check "Acción inválida (no hay cambio de contraseña)" 400 "$(codigo "$(padm "$U" "{\"accion\":\"password\",\"id_usuario\":$ID_D}")")"

echo "== 11) PEDIDOS Y TRANSICIONES DE ESTADO =="
post "$BASE/carrito/agregar.php" '{"id_producto":2,"cantidad":1}' -b "$JAR_D" >/dev/null
R=$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Estados 456"}' -b "$JAR_D"); ID_E=$(cuerpo "$R" | json 'd["pedido"]["id_pedido"]')
R=$(curl -s -b "$JAR_A" "$PED")
igual "Lista todos los pedidos" "$(sql 'SELECT COUNT(*) FROM pedidos;')" "$(echo "$R" | json 'len(d["pedidos"])')"
igual "Conteo por estado coincide con MySQL" "$(sql "SELECT COUNT(*) FROM pedidos WHERE estado='pendiente';")" "$(echo "$R" | json 'd["por_estado"]["pendiente"]')"
igual "Buscar por #id" "[$ID_E]" "$(curl -s -b "$JAR_A" -G "$PED" --data-urlencode "q=#$ID_E" | json '[p["id_pedido"] for p in d["pedidos"]]')"
igual "Buscar por correo del cliente (todos sus pedidos, del más reciente al más antiguo)" "[$(sql "SELECT GROUP_CONCAT(id_pedido ORDER BY id_pedido DESC SEPARATOR ', ') FROM pedidos WHERE id_usuario=$ID_D;")]" "$(curl -s -b "$JAR_A" "$PED?q=cdos$TS" | json '[p["id_pedido"] for p in d["pedidos"]]')"
R=$(curl -s -b "$JAR_A" "$PED?id=$ID_E")
igual "Detalle: cliente, dirección, línea con precio histórico, siguiente estado" "Cliente Dos|Calle Estados 456|463.97|['pagado']" "$(echo "$R" | json 'd["pedido"]["cliente"]["nombre"]+"|"+d["pedido"]["direccion_envio"]+"|"+str(d["pedido"]["items"][0]["precio_unitario"])+"|"+str(d["pedido"]["estados_siguientes"])')"
check "Detalle inexistente" 404 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$PED?id=99999")"
est(){ padm "$PED" "{\"accion\":\"estado\",\"id_pedido\":$ID_E,\"estado\":\"$1\"}"; }
check "pendiente → enviado (salteando pagado)" 409 "$(codigo "$(est enviado)")"
check "pendiente → completado" 409 "$(codigo "$(est completado)")"
R=$(est cancelado); check "pendiente → cancelado (reglas pendientes de definir)" 409 "$(codigo "$R")"
verdadero "…el mensaje explica que la cancelación está pendiente de definición" "$(cuerpo "$R" | json '"pendiente" in d["error"] and "cancel" in d["error"].lower()')"
check "estado inexistente" 400 "$(codigo "$(est perdido)")"
check "pendiente → pagado (pago registrado por el admin)" 200 "$(codigo "$(est pagado)")"
check "pagado → pendiente (hacia atrás)" 409 "$(codigo "$(est pendiente)")"
check "pagado → pagado (repetido)" 409 "$(codigo "$(est pagado)")"
check "pagado → enviado" 200 "$(codigo "$(est enviado)")"
check "enviado → completado" 200 "$(codigo "$(est completado)")"
check "completado → cualquier otro" 409 "$(codigo "$(est enviado)")"
igual "MySQL: completado" completado "$(sql "SELECT estado FROM pedidos WHERE id_pedido=$ID_E;")"
igual "El cliente ve el estado nuevo en Mis Compras" completado "$(curl -s -b "$JAR_D" "$BASE/pedidos/listar.php" | json "[p['estado'] for p in d['pedidos'] if p['id_pedido']==$ID_E][0]")"
igual "Cambiar estado no alteró montos ni detalle" "463.97|50.00|513.97" "$(sql "SELECT CONCAT(subtotal,'|',costo_envio,'|',total) FROM pedidos WHERE id_pedido=$ID_E;")"
# Dos administradores marcan el mismo pedido como pagado a la vez: uno gana, el otro recibe 409.
post "$BASE/carrito/agregar.php" '{"id_producto":3,"cantidad":1}' -b "$JAR_D" >/dev/null
ID_F=$(cuerpo "$(post "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle Carrera 789"}' -b "$JAR_D")" | json 'd["pedido"]["id_pedido"]')
for i in 1 2 3; do ( c=$(padm "$PED" "{\"accion\":\"estado\",\"id_pedido\":$ID_F,\"estado\":\"pagado\"}" | tail -n1); echo "$c" > "$TMPD/e$i" ) & done; wait
igual "3 'pagado' simultáneos: exactamente 1 aceptado" 1 "$(cat "$TMPD"/e* | grep -c '^200$')"
igual "…y 2 rechazados (409)" 2 "$(cat "$TMPD"/e* | grep -c '^409$')"

echo "== 12) ESTADÍSTICAS (comparadas con consultas SQL independientes) =="
padm "$PED" "{\"accion\":\"estado\",\"id_pedido\":$ID_PH,\"estado\":\"pagado\"}" >/dev/null
R=$(curl -s -b "$JAR_A" "$EST")
igual "Ventas confirmadas = Σ total (pagado/enviado/completado)" "$(sql "SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado IN ('pagado','enviado','completado');")" "$(echo "$R" | json 'd["indicadores"]["ventas_confirmadas"]')"
igual "Pedidos confirmados" "$(sql "SELECT COUNT(*) FROM pedidos WHERE estado IN ('pagado','enviado','completado');")" "$(echo "$R" | json 'd["indicadores"]["pedidos_confirmados"]')"
igual "Ticket promedio" "$(sql "SELECT ROUND(AVG(total),2) FROM pedidos WHERE estado IN ('pagado','enviado','completado');")" "$(echo "$R" | json 'd["indicadores"]["ticket_promedio"]')"
igual "Pedidos pendientes y su monto" "$(sql "SELECT CONCAT(COUNT(*),'|',ROUND(COALESCE(SUM(total),0),2)) FROM pedidos WHERE estado='pendiente';")" "$(echo "$R" | json 'str(d["indicadores"]["pedidos_pendientes"])+"|"+format(d["indicadores"]["monto_pendiente"],".2f")')"
igual "Clientes" "$(sql "SELECT COUNT(*) FROM usuarios WHERE rol='cliente';")" "$(echo "$R" | json 'd["indicadores"]["clientes"]')"
igual "Productos activos" "$(sql "SELECT COUNT(*) FROM productos WHERE estado='activo';")" "$(echo "$R" | json 'd["indicadores"]["productos_activos"]')"
igual "Alertas de stock (activos con stock <= 5)" "$(sql "SELECT COUNT(*) FROM productos WHERE estado='activo' AND stock<=5;")" "$(echo "$R" | json 'd["indicadores"]["alertas_stock"]')"
igual "Σ por estado = total de pedidos" "$(sql 'SELECT COUNT(*) FROM pedidos;')" "$(echo "$R" | json 'sum(v["pedidos"] for v in d["pedidos_por_estado"].values())')"
igual "Σ ventas por categoría = Σ detalle de pedidos confirmados" "$(sql "SELECT COALESCE(SUM(d.subtotal),0) FROM detalle_pedido d JOIN pedidos p ON p.id_pedido=d.id_pedido WHERE p.estado IN ('pagado','enviado','completado');")" "$(echo "$R" | json 'round(sum(c["monto"] for c in d["ventas_por_categoria"]),2)')"
igual "Producto más vendido (unidades confirmadas)" "$(sql "SELECT d.id_producto FROM detalle_pedido d JOIN pedidos p ON p.id_pedido=d.id_pedido WHERE p.estado IN ('pagado','enviado','completado') GROUP BY d.id_producto ORDER BY SUM(d.cantidad) DESC, SUM(d.subtotal) DESC, d.id_producto LIMIT 1;")" "$(echo "$R" | json 'd["productos_mas_vendidos"][0]["id_producto"]')"
igual "Serie diaria de 14 días; pedidos de hoy" "14|$(sql 'SELECT COUNT(*) FROM pedidos WHERE DATE(fecha_pedido)=CURDATE();')" "$(echo "$R" | json 'str(len(d["serie_diaria"]))+"|"+str(d["serie_diaria"][-1]["pedidos"])')"
igual "Últimos pedidos: el más reciente primero" "$(sql 'SELECT MAX(id_pedido) FROM pedidos;')" "$(echo "$R" | json 'd["ultimos_pedidos"][0]["id_pedido"]')"
igual "dias=30" 30 "$(curl -s -b "$JAR_A" "$EST?dias=30" | json 'len(d["serie_diaria"])')"
check "dias=0" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$EST?dias=0")"
check "dias=91" 400 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$EST?dias=91")"

echo "== 13) INTEGRIDAD FINAL =="
igual "Ningún stock negativo" 0 "$(sql 'SELECT COUNT(*) FROM productos WHERE stock<0;')"
igual "Siguen las 3 categorías originales y las 10 tablas" "3|10" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM categorias WHERE slug IN ('celulares','notebooks','televisores')),'|',(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME'));")"
igual "Hay al menos un administrador activo" 1 "$(sql "SELECT COUNT(*)>0 FROM usuarios WHERE rol='admin' AND estado='activo';")"
find "$RAIZ/img/productos" -type f ! -name '.htaccess' -delete
rm -rf "$TMPD" "$JAR_A" "$JAR_C" "$JAR_D"
resumen
