"""Genera la matriz de pruebas en Markdown A PARTIR DEL LOG REAL de tests/run_all.sh (no de lo esperado).

Uso:
    bash tests/run_all.sh 2>&1 | tee tests/resultados/run_all.log
    python3 tests/generar_matriz.py tests/resultados/run_all.log > docs/MATRIZ-PRUEBAS.md

Cada línea "OK - ..." / "FALLA - ..." del log se vuelve una fila, agrupada por suite y por sección."""
import re, sys, datetime, os

if len(sys.argv) < 2:
    print(__doc__); sys.exit(2)
ruta = sys.argv[1]
texto = open(ruta, encoding="utf-8", errors="replace").read()

suites = []          # [(nombre, [(seccion, estado, descripcion)], resultado_final)]
actual = None; seccion = ""
for linea in texto.splitlines():
    m = re.match(r"^#+ (\S+) #+$", linea.strip())
    if m:
        actual = [m.group(1), [], ""]; suites.append(actual); seccion = ""; continue
    if actual is None:
        continue
    s = linea.strip()
    if re.match(r"^(==.*==|\d+[a-z]?\)\s.*)$", s):
        seccion = s.strip("= ").strip(); continue
    m = re.match(r"^(OK|FALLA)\s+-\s+(.*)$", s)
    if m:
        actual[1].append((seccion, "Aprobado" if m.group(1) == "OK" else "**FALLIDO**", m.group(2))); continue
    if s.startswith("RESULTADO FINAL"):
        actual[2] = s

fecha = datetime.datetime.fromtimestamp(os.path.getmtime(ruta)).strftime("%Y-%m-%d %H:%M")
total_ok = sum(1 for _, filas, _ in suites for f in filas if f[1] == "Aprobado")
total_ko = sum(1 for _, filas, _ in suites for f in filas if f[1] != "Aprobado")
print("# Matriz de pruebas — resultados reales\n")
print(f"Generada automáticamente por `tests/generar_matriz.py` a partir de `{os.path.basename(ruta)}` "
      f"(ejecución del {fecha}). Cada fila es una aserción que se ejecutó de verdad contra PHP + MariaDB "
      f"y/o Chromium; la columna *Evidencia* indica la suite y la sección del log donde está la salida.\n")
print(f"**Total: {total_ok} aprobadas, {total_ko} fallidas, en {len(suites)} suites.**\n")
print("| Suite | Aprobadas | Fallidas | Resultado del log |")
print("|---|---:|---:|---|")
for nombre, filas, res in suites:
    ok = sum(1 for f in filas if f[1] == "Aprobado")
    print(f"| `{nombre}` | {ok} | {len(filas) - ok} | {res or '(sin línea de resultado)'} |")
n = 0
for nombre, filas, res in suites:
    print(f"\n## {nombre}\n")
    print("| # | Sección | Caso | Resultado | Evidencia |")
    print("|---:|---|---|---|---|")
    for sec, estado, desc in filas:
        n += 1
        d = desc.replace("|", "\\|")
        print(f"| {n} | {sec.replace('|', '/')} | {d} | {estado} | log `{nombre}` |")
