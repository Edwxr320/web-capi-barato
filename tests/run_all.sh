#!/bin/bash
# Corre las suites contra una base AISLADA de pruebas, reimportándola limpia antes de cada una.
#
#   bash tests/run_all.sh            # todo
#   bash tests/run_all.sh bash       # solo las suites de bash + curl (rápido)
#   bash tests/run_all.sh python     # solo concurrencia y navegador
#   bash tests/run_all.sh test_bloque3 test_inventario   # suites puntuales
#
# Qué hace, y qué NO hace:
#   - Usa la base DB_NAME (default "mercadoweb_test"). Se niega a correr si el nombre no termina en "_test".
#   - Antes de cada suite borra y recrea SOLO esa base de pruebas, importando sql/mercadoweb.sql con el
#     nombre de base reescrito. La base "mercadoweb" (u otra de tu instalación) no se lee ni se modifica.
#   - Levanta su PROPIO servidor PHP (127.0.0.1:PUERTO, default 8099) con MERCADOWEB_DB_PRUEBAS=DB_NAME,
#     así la aplicación habla con la base de pruebas sin tocar tu config.php. Lo apaga al terminar.
#   - Si el reinicio o la importación fallan, o el servidor no apunta a la base de pruebas, se DETIENE con error.
set -u
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
export MYSQL_CLI="${MYSQL_CLI:-mysql -u root}"
export DB_NAME="${DB_NAME:-mercadoweb_test}"
PUERTO="${PUERTO:-8099}"
export BASE="http://127.0.0.1:$PUERTO/api"
export BASE_WEB="http://127.0.0.1:$PUERTO"
export CAPTURAS="${CAPTURAS:-$RAIZ/tests/resultados/capturas}"
LOG_SERVIDOR="${LOG_SERVIDOR:-$RAIZ/tests/resultados/servidor-php.log}"
mkdir -p "$(dirname "$LOG_SERVIDOR")" "$CAPTURAS"

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+_test$ ]]; then
    echo "ERROR: DB_NAME='$DB_NAME' no termina en '_test'. No se ejecuta nada." >&2; exit 2
fi
if [ ! -f "$RAIZ/api/config/config.php" ]; then
    echo "ERROR: falta api/config/config.php (copiá config.example.php y completá host/usuario/clave)." >&2; exit 2
fi
command -v php >/dev/null || { echo "ERROR: no se encontró php" >&2; exit 2; }
command -v curl >/dev/null || { echo "ERROR: no se encontró curl" >&2; exit 2; }
$MYSQL_CLI -e "SELECT 1" >/dev/null 2>&1 || { echo "ERROR: no se pudo conectar con '$MYSQL_CLI'" >&2; exit 2; }

fallar(){ echo ""; echo "ERROR FATAL: $1" >&2; exit 2; }

reiniciar_bd(){
    $MYSQL_CLI -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || fallar "no se pudo borrar la base de pruebas $DB_NAME"
    # Reescribe SOLO las dos sentencias que nombran la base; el resto del SQL se importa tal cual.
    sed -E "s/^CREATE DATABASE IF NOT EXISTS mercadoweb$/CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`/; s/^USE mercadoweb;$/USE \`$DB_NAME\`;/" \
        "$RAIZ/sql/mercadoweb.sql" > "$RAIZ/tests/resultados/.import.sql"
    grep -q "^USE \`$DB_NAME\`;" "$RAIZ/tests/resultados/.import.sql" || fallar "no se pudo reescribir el nombre de la base en el SQL"
    if grep -Eq "^(CREATE DATABASE|USE) .*mercadoweb[^_]" "$RAIZ/tests/resultados/.import.sql"; then
        fallar "el SQL de importación sigue apuntando a 'mercadoweb': abortado para no tocar esa base"
    fi
    $MYSQL_CLI < "$RAIZ/tests/resultados/.import.sql" || fallar "falló la importación de sql/mercadoweb.sql en $DB_NAME"
    local t p c
    t=$($MYSQL_CLI -s -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME';")
    p=$($MYSQL_CLI "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM productos;")
    c=$($MYSQL_CLI "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM categorias;")
    [ "$t" == "8" ] && [ "$p" == "12" ] && [ "$c" == "3" ] || fallar "la base importada no es la esperada (tablas=$t productos=$p categorias=$c)"
}

PID_PHP=""
apagar_servidor(){ [ -n "$PID_PHP" ] && kill "$PID_PHP" 2>/dev/null; }
trap apagar_servidor EXIT
iniciar_servidor(){
    if curl -s -o /dev/null "http://127.0.0.1:$PUERTO/"; then
        fallar "el puerto $PUERTO ya está en uso; definí PUERTO=<otro> para no probar contra un servidor ajeno"
    fi
    # PHP_CLI_SERVER_WORKERS: sin esto "php -S" atiende UNA petición a la vez y las pruebas de concurrencia
    # serían falsas (las solicitudes "simultáneas" se procesarían en fila).
    PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}" MERCADOWEB_DB_PRUEBAS="$DB_NAME" php -S "127.0.0.1:$PUERTO" -t "$RAIZ" > "$LOG_SERVIDOR" 2>&1 &
    PID_PHP=$!
    for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PUERTO/index.html" && break; sleep 0.2; done
    curl -s -o /dev/null "http://127.0.0.1:$PUERTO/index.html" || fallar "no arrancó el servidor PHP de pruebas"
}
verificar_aislamiento(){
    # Un registro hecho por la API tiene que aparecer en la base de pruebas: prueba de que el servidor
    # está conectado a $DB_NAME y no a otra base.
    local marca="aislamiento$(date +%s%N)@test.com"
    curl -s -X POST "$BASE/auth/registro.php" -H "Content-Type: application/json" \
        -d "{\"nombre\":\"Verificación\",\"email\":\"$marca\",\"password\":\"clave12345\"}" >/dev/null
    [ "$($MYSQL_CLI "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM usuarios WHERE email='$marca';")" == "1" ] \
        || fallar "el servidor PHP no está usando la base $DB_NAME (¿config.php con otra base y sin soporte de MERCADOWEB_DB_PRUEBAS?)"
}

SUITES_BASH="test_auth test_bloque1 test_bloque2 test_bloque3 test_inventario test_admin"
SUITES_PY="test_concurrencia test_navegador_bloque3 test_navegador_admin test_responsive"
case "${1:-todo}" in
    todo)   ELEGIDAS="$SUITES_BASH $SUITES_PY" ;;
    bash)   ELEGIDAS="$SUITES_BASH" ;;
    python) ELEGIDAS="$SUITES_PY" ;;
    *)      ELEGIDAS="$*" ;;
esac

iniciar_servidor
reiniciar_bd
verificar_aislamiento
echo "Base de pruebas: $DB_NAME  |  Servidor: $BASE_WEB  |  Capturas: $CAPTURAS"

fallas=0; corridas=0
for suite in $ELEGIDAS; do
    if [ -f "$RAIZ/tests/$suite.sh" ]; then cmd=(bash "$RAIZ/tests/$suite.sh")
    elif [ -f "$RAIZ/tests/$suite.py" ]; then
        if [[ "$suite" == test_navegador* || "$suite" == test_responsive ]] && ! python3 -c "import playwright" 2>/dev/null; then
            echo ""; echo "(se omite $suite: falta Playwright; ver tests/README.md)"; continue
        fi
        cmd=(python3 "$RAIZ/tests/$suite.py")
    else
        echo "(no existe la suite $suite)"; continue
    fi
    echo ""; echo "################ $suite ################"
    reiniciar_bd
    corridas=$((corridas+1))
    "${cmd[@]}" || fallas=$((fallas+1))
done

reiniciar_bd   # deja la base de pruebas limpia al terminar
echo ""; echo "Suites ejecutadas: $corridas  |  Suites con fallas: $fallas"
[ "$fallas" -eq 0 ]
