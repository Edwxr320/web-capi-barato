#!/bin/bash
# Autenticación: registro, login, sesión, roles, protección de rutas, logout.
source "$(dirname "$0")/lib.sh"
JAR_C=$(mktemp); JAR_A=$(mktemp)
TS=$(date +%s%N); EMAIL="juana$TS@ejemplo.com"

echo "== 1) REGISTRO =="
R=$(post "$BASE/auth/registro.php" "{\"nombre\":\"Juana Pérez\",\"email\":\"$EMAIL\",\"password\":\"secreta123\"}" -c "$JAR_C")
check "Registro válido" 201 "$(codigo "$R")"
R=$(post "$BASE/auth/registro.php" "{\"nombre\":\"Otra\",\"email\":\"$EMAIL\",\"password\":\"otraclave1\"}")
check "Email duplicado rechazado" 409 "$(codigo "$R")"
R=$(post "$BASE/auth/registro.php" '{"nombre":"Test","email":"no-es-email","password":"secreta123"}')
check "Email con formato inválido" 400 "$(codigo "$R")"
R=$(post "$BASE/auth/registro.php" '{"nombre":"Test","email":"otro@ejemplo.com","password":"123"}')
check "Contraseña corta" 400 "$(codigo "$R")"

echo "== 2) LOGIN =="
R=$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL\",\"password\":\"contraseña-mala\"}")
MALA=$(cuerpo "$R"); check "Contraseña incorrecta" 401 "$(codigo "$R")"
R=$(post "$BASE/auth/login.php" "{\"email\":\"no-existe$EMAIL\",\"password\":\"cualquiera1\"}")
INEX=$(cuerpo "$R"); check "Email inexistente" 401 "$(codigo "$R")"
igual "Mensaje idéntico (no revela si el email existe)" "$MALA" "$INEX"
R=$(post "$BASE/auth/login.php" "{\"email\":\"x' OR '1'='1$TS\",\"password\":\"x\"}")
check "Inyección SQL en el email no autentica" 401 "$(codigo "$R")"
R=$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL\",\"password\":\"secreta123\"}" -c "$JAR_C")
check "Login de cliente" 200 "$(codigo "$R")"; igual "Rol detectado" cliente "$(cuerpo "$R" | json 'd["usuario"]["rol"]')"
R=$(post "$BASE/auth/login.php" '{"email":"admin@capibarato.pe","password":"CapiBarato2026!"}' -c "$JAR_A")
check "Login de admin" 200 "$(codigo "$R")"; igual "Rol detectado" admin "$(cuerpo "$R" | json 'd["usuario"]["rol"]')"

echo "== 3) SESIÓN =="
igual "Invitado" False "$(curl -s "$BASE/auth/sesion.php" | json 'd["autenticado"]')"
igual "Cliente logueado" True "$(curl -s -b "$JAR_C" "$BASE/auth/sesion.php" | json 'd["autenticado"]')"

echo "== 4) PROTECCIÓN POR ROL =="
check "admin sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/productos.php")"
check "admin con sesión de cliente" 403 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/admin/productos.php")"
for e in productos categorias usuarios pedidos inventario estadisticas; do
    check "admin/$e.php sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/$e.php")"
    check "admin/$e.php con sesión de cliente" 403 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/admin/$e.php")"
    check "admin/$e.php con sesión de admin" 200 "$(curl -s -b "$JAR_A" -o /dev/null -w '%{http_code}' "$BASE/admin/$e.php")"
done
check "carrito sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"
check "carrito con sesión de cliente" 200 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"
check "pedidos sin sesión" 401 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/pedidos/listar.php")"
check "catálogo público" 200 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/productos/listar.php")"

echo "== 5) LOGOUT =="
R=$(post "$BASE/auth/logout.php" '' -b "$JAR_C" -c "$JAR_C"); check "Logout" 200 "$(codigo "$R")"
igual "La sesión quedó destruida en el servidor" False "$(curl -s -b "$JAR_C" "$BASE/auth/sesion.php" | json 'd["autenticado"]')"
check "carrito con la cookie vieja" 401 "$(curl -s -b "$JAR_C" -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"

echo "== 6) CSRF (token sincronizado + Origin + application/json) =="
JAR_K=$(mktemp); registrar "$JAR_K" "Csrf Test" "csrf$EMAIL" "clave12345"
igual "La respuesta de registro deja el token en la cookie mw_csrf" 64 "$(token_de "$JAR_K" | tr -d '\n' | wc -c)"
igual "sesion.php devuelve el mismo token" "$(token_de "$JAR_K")" "$(curl -s -b "$JAR_K" "$BASE/auth/sesion.php" | json 'd["csrf_token"]')"
check "POST de carrito SIN token" 403 "$(codigo "$(post_sin_csrf "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_K")")"
check "POST de carrito con token INCORRECTO" 403 "$(codigo "$(post_sin_csrf "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_K" -H 'X-CSRF-Token: 0000')")"
check "POST de checkout SIN token" 403 "$(codigo "$(post_sin_csrf "$BASE/pedidos/crear.php" '{"direccion_envio":"Calle CSRF 123"}' -b "$JAR_K")")"
check "Logout SIN token (no se puede forzar el cierre de sesión)" 403 "$(codigo "$(post_sin_csrf "$BASE/auth/logout.php" '' -b "$JAR_K")")"
check "POST con token correcto pero Origin de otro sitio" 403 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_K" -H 'Origin: https://evil.example')")"
check "POST con token correcto y Origin del propio sitio" 201 "$(codigo "$(post "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_K" -H "Origin: ${BASE%/api}")")"
check "Cuerpo enviado como text/plain (lo que puede mandar un formulario ajeno)" 415 "$(codigo "$(curl -s -w "\n%{http_code}" -X POST "$BASE/carrito/agregar.php" -b "$JAR_K" -H "X-CSRF-Token: $(token_de "$JAR_K")" -H 'Content-Type: text/plain' -d '{"id_producto":1,"cantidad":1}')")"
check "Login desde un formulario de otro sitio (Origin ajeno)" 403 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL\",\"password\":\"secreta123\"}" -H 'Origin: https://evil.example')")"
igual "Los rechazos no tocaron el carrito (sigue 1 unidad)" 1 "$(curl -s -b "$JAR_K" "$BASE/carrito/listar.php" | json 'd["items"][0]["cantidad"]')"
T1=$(token_de "$JAR_K"); login "$JAR_K" "csrf$EMAIL" "clave12345"
[ "$T1" != "$(token_de "$JAR_K")" ] && ok "Un login nuevo genera un token nuevo" || ko "El token no cambió con el login nuevo"
check "El token viejo ya no sirve" 403 "$(codigo "$(post_sin_csrf "$BASE/carrito/agregar.php" '{"id_producto":1,"cantidad":1}' -b "$JAR_K" -H "X-CSRF-Token: $T1")")"
check "GET a logout no cierra la sesión" 405 "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR_K" "$BASE/auth/logout.php")"
igual "…la sesión sigue activa" True "$(curl -s -b "$JAR_K" "$BASE/auth/sesion.php" | json 'd["autenticado"]')"

echo "== 7) LÍMITE DE INTENTOS DE LOGIN =="
EMAIL_L="limite$EMAIL"; JAR_L=$(mktemp); registrar "$JAR_L" "Limite" "$EMAIL_L" "correcta123"
for i in 1 2 3 4 5; do c=$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_L\",\"password\":\"mala$i\"}")"); done
check "5.º intento fallido sigue siendo 401" 401 "$c"
R=$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_L\",\"password\":\"mala6\"}")
check "6.º intento: bloqueado" 429 "$(codigo "$R")"
check "Bloqueado incluso con la contraseña CORRECTA" 429 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_L\",\"password\":\"correcta123\"}")")"
RA=$(curl -s -D - -o /dev/null -X POST "$BASE/auth/login.php" -H "Content-Type: application/json" -d "{\"email\":\"$EMAIL_L\",\"password\":\"x\"}" | tr -d '\r' | awk -F': ' 'tolower($1)=="retry-after"{print $2}')
[ -n "$RA" ] && [ "$RA" -gt 0 ] && ok "Responde Retry-After ($RA s)" || ko "Falta Retry-After"
check "Otro correo no queda bloqueado" 200 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL\",\"password\":\"secreta123\"}")")"
EMAIL_M="limite2$EMAIL"; registrar "$(mktemp)" "Limite2" "$EMAIL_M" "correcta123"
for i in 1 2 3 4; do post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_M\",\"password\":\"mala$i\"}" >/dev/null; done
check "Login correcto tras 4 fallos (debajo del límite)" 200 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_M\",\"password\":\"correcta123\"}")")"
for i in 1 2 3 4; do c=$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"$EMAIL_M\",\"password\":\"mala$i\"}")"); done
check "…y el éxito reinició el contador (4 fallos nuevos no bloquean)" 401 "$c"

echo "== 8) CAMBIOS DE CUENTA RIGEN EN LA PETICIÓN SIGUIENTE =="
JAR_D=$(mktemp); registrar "$JAR_D" "Desactivar" "desact$EMAIL" "clave12345"
check "Cliente activo accede a su carrito" 200 "$(curl -s -b "$JAR_D" -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"
sql "UPDATE usuarios SET estado='inactivo' WHERE email='desact$EMAIL';"
check "Cuenta desactivada: la sesión abierta deja de valer" 401 "$(curl -s -b "$JAR_D" -o /dev/null -w '%{http_code}' "$BASE/carrito/listar.php")"
check "…y no puede volver a iniciar sesión" 403 "$(codigo "$(post "$BASE/auth/login.php" "{\"email\":\"desact$EMAIL\",\"password\":\"clave12345\"}")")"
JAR_R=$(mktemp); registrar "$JAR_R" "Rol" "rol$EMAIL" "clave12345"
check "Cliente no entra a /admin" 403 "$(curl -s -b "$JAR_R" -o /dev/null -w '%{http_code}' "$BASE/admin/inventario.php")"
sql "UPDATE usuarios SET rol='admin' WHERE email='rol$EMAIL';"
check "Promovido a admin: entra sin volver a loguearse" 200 "$(curl -s -b "$JAR_R" -o /dev/null -w '%{http_code}' "$BASE/admin/inventario.php")"
sql "UPDATE usuarios SET rol='cliente' WHERE email='rol$EMAIL';"
check "Vuelto a cliente: pierde el acceso en el acto" 403 "$(curl -s -b "$JAR_R" -o /dev/null -w '%{http_code}' "$BASE/admin/inventario.php")"
check "Cookie de sesión inventada no autentica" 401 "$(curl -s -o /dev/null -w '%{http_code}' -H 'Cookie: PHPSESSID=abc123inventada' "$BASE/carrito/listar.php")"
resumen
