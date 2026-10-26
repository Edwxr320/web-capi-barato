#!/bin/bash
# Bloque 1: productos, categorías y búsqueda (endpoints públicos).
source "$(dirname "$0")/lib.sh"
n(){ json 'len(d["productos"])'; }

echo "== CATEGORÍAS =="
check "Listar" 200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/categorias/listar.php")"
igual "Son 3" 3 "$(curl -s "$BASE/categorias/listar.php" | json 'len(d["categorias"])')"

echo "== PRODUCTOS: LISTAR =="
igual "Catálogo completo" 12 "$(curl -s "$BASE/productos/listar.php" | n)"
igual "Filtro celulares" 4 "$(curl -s "$BASE/productos/listar.php?categoria=celulares" | n)"
check "Categoría inexistente" 404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/listar.php?categoria=noexiste")"
check "POST rechazado" 405 "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/productos/listar.php")"

echo "== PRODUCTOS: DETALLE =="
R=$(curl -s "$BASE/productos/detalle.php?id=1")
igual "Producto 1: 3 imágenes" 3 "$(echo "$R" | json 'len(d["producto"]["imagenes"])')"
igual "Producto 1: precio final" 429.14 "$(echo "$R" | json 'd["producto"]["precio_final"]')"
check "Inexistente" 404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php?id=9999")"
check "id no numérico" 400 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php?id=abc")"
check "Falta id" 400 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php")"
check "Inyección SQL en id" 400 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/detalle.php?id=1%3BDROP%20TABLE%20productos%3B--")"
igual "La tabla productos sigue intacta" 12 "$(sql 'SELECT COUNT(*) FROM productos;')"

echo "== PRODUCTOS: BUSCAR =="
igual "samsung" 4 "$(curl -s "$BASE/productos/buscar.php?q=samsung" | n)"
igual "SAMSUNG (mayúsculas)" 4 "$(curl -s "$BASE/productos/buscar.php?q=SAMSUNG" | n)"
igual "Por categoría: notebooks" 4 "$(curl -s "$BASE/productos/buscar.php?q=notebooks" | n)"
[ "$(curl -s "$BASE/productos/buscar.php?q=bluetooth" | n)" -gt 0 ] && ok "Por característica (bluetooth)" || ko "Por característica (bluetooth)"
igual "Sin resultados" 0 "$(curl -s "$BASE/productos/buscar.php?q=xyzxyz123" | n)"
check "Falta q" 400 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/buscar.php")"
R=$(curl -s -w "\n%{http_code}" -G "$BASE/productos/buscar.php" --data-urlencode "q=' OR '1'='1")
check "Inyección SQL en q no rompe la consulta" 200 "$(codigo "$R")"
igual "…y se trata como texto literal (0 resultados)" 0 "$(cuerpo "$R" | n)"

echo "== PÚBLICOS (no exigen sesión) =="
for e in "categorias/listar.php" "productos/listar.php" "productos/detalle.php?id=1" "productos/buscar.php?q=moto"; do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/$e")
    [ "$c" == "401" -o "$c" == "403" ] && ko "$e exige sesión" || ok "$e es público (HTTP $c)"
done
resumen
