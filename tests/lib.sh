#!/bin/bash
# Utilidades comunes de las suites de prueba.
#
# SEGURIDAD DE DATOS: todas las suites MODIFICAN la base (registran usuarios, crean
# pedidos, cambian stock y precios). Por eso solo trabajan contra una base cuyo
# nombre termina en "_test" (por defecto "mercadoweb_test"). Si DB_NAME no cumple
# eso, la suite se niega a correr. La base "mercadoweb" (la de uso normal) NUNCA se
# toca desde las pruebas.
#
# Variables de entorno:
#   BASE       URL base de la API        (default http://127.0.0.1:8099/api, el servidor que levanta run_all.sh)
#   MYSQL_CLI  comando de cliente MySQL  (default "mysql -u root")
#   DB_NAME    base AISLADA de pruebas   (default mercadoweb_test; debe terminar en _test)
BASE="${BASE:-http://127.0.0.1:8099/api}"
MYSQL_CLI="${MYSQL_CLI:-mysql -u root}"
DB_NAME="${DB_NAME:-mercadoweb_test}"
if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+_test$ ]]; then
    echo "ERROR: DB_NAME='$DB_NAME' no termina en '_test'. Las pruebas modifican datos y solo se" >&2
    echo "       ejecutan contra una base aislada de pruebas. Abortado sin tocar nada." >&2
    exit 2
fi
pass=0; fail=0

ok(){ echo "  OK   - $1"; pass=$((pass+1)); }
ko(){ echo "  FALLA - $1"; fail=$((fail+1)); }
check(){   # check "descripción" esperado obtenido
    if [ "$2" == "$3" ]; then ok "$1 (HTTP $3)"; else ko "$1 (esperaba HTTP $2, obtuvo HTTP $3)"; fi
}
igual(){   # igual "descripción" esperado obtenido  (numérico si ambos son números: 50 == 50.0 == 50.00)
    if python3 - "$2" "$3" << 'PY'
import sys
a, b = sys.argv[1], sys.argv[2]
try:
    sys.exit(0 if abs(float(a) - float(b)) < 1e-9 else 1)
except ValueError:
    sys.exit(0 if a == b else 1)
PY
    then ok "$1 ($3)"; else ko "$1 (esperaba '$2', obtuvo '$3')"; fi
}
verdadero(){   # verdadero "descripción" "<salida de un json/condición: True|False>"
    if [ "$2" == "True" ]; then ok "$1"; else ko "$1 (obtuvo '$2')"; fi
}
token_de(){   # token CSRF guardado en un cookie jar de curl (cookie "mw_csrf")
    [ -n "$1" ] && [ -f "$1" ] && awk -F'\t' '$6=="mw_csrf"{t=$7} END{print t}' "$1"
}
# post URL JSON [opciones curl...]  -- si entre las opciones hay "-b <jar>", manda el token CSRF de ese jar
# en X-CSRF-Token, igual que el frontend. Para probar SIN token: post_sin_csrf.
post(){
    local url="$1" datos="$2" jar="" a; shift 2
    local args=("$@")
    for ((a=0; a<${#args[@]}; a++)); do [ "${args[$a]}" == "-b" ] && jar="${args[$((a+1))]}"; done
    # El cuerpo va por la ENTRADA ESTANDAR (--data-binary @-), no como argumento.
    # Motivo: en Windows (Git Bash + curl de mingw64) los argumentos de la linea de comandos se
    # reconvierten al codepage ANSI, asi que un cuerpo con acentos llegaba como UTF-8 invalido y
    # la API lo rechazaba, con razon, con 400 "JSON no valido": 16 fallas falsas en test_auth.
    # Por stdin los bytes llegan intactos. En Linux el comportamiento es equivalente.
    printf '%s' "$datos" | curl -s -w "\n%{http_code}" -X POST "$url" -H "Content-Type: application/json" -H "X-CSRF-Token: $(token_de "$jar")" --data-binary @- "$@"
}
post_sin_csrf(){ printf '%s' "$2" | curl -s -w "\n%{http_code}" -X POST "$1" -H "Content-Type: application/json" --data-binary @- "${@:3}"; }
cuerpo(){ echo "$1" | head -n -1; }
codigo(){ echo "$1" | tail -n1; }
sql(){ $MYSQL_CLI --default-character-set=utf8mb4 "$DB_NAME" -s -N -e "$1"; }
json(){ python3 -c "import json,sys; d=json.load(sys.stdin); print($1)"; }   # json 'd["pedido"]["id_pedido"]'
es_json(){ python3 -c "import json,sys; json.load(sys.stdin)" 2>/dev/null && echo True || echo False; }
registrar(){   # registrar <jar> <nombre> <email> <clave>
    curl -s -c "$1" -X POST "$BASE/auth/registro.php" -H "Content-Type: application/json" \
        -d "{\"nombre\":\"$2\",\"email\":\"$3\",\"password\":\"$4\"}" >/dev/null
}
login(){       # login <jar> <email> <clave>
    curl -s -c "$1" -X POST "$BASE/auth/login.php" -H "Content-Type: application/json" \
        -d "{\"email\":\"$2\",\"password\":\"$3\"}" >/dev/null
}
resumen(){
    echo ""; echo "================================================================"
    echo "RESULTADO FINAL: $pass pruebas OK, $fail con FALLA"
    echo "================================================================"
    [ "$fail" -eq 0 ]
}
