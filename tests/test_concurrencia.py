"""Concurrencia real del checkout: solicitudes simultáneas sobre stock limitado.

Escenarios (cada uno se repite RONDAS veces, default 10, porque una carrera puede salir bien por suerte):
  A) Dos usuarios piden 2 unidades c/u de un producto con stock 2  -> 1 éxito, 1 rechazo, stock final 0.
  B) Tres usuarios piden 1 unidad c/u de un producto con stock 2   -> 2 éxitos, 1 rechazo, stock final 0.
  C) Dos usuarios con los MISMOS dos productos en su carrito (orden de alta invertido), stock de sobra
     -> los dos checkouts deben terminar bien (sin deadlock ni error 500).
  D) Seis usuarios, 1 unidad c/u, stock 3 -> exactamente 3 éxitos.

Datos que modifica (solo en la base de pruebas): registra usuarios, cambia el stock de los productos 5, 6 y 7,
borra carritos y crea pedidos.
Variables de entorno: BASE, MYSQL_CLI, DB_NAME (debe terminar en _test), RONDAS."""
import subprocess, json, time, os, re, sys, tempfile, concurrent.futures

BASE = os.environ.get("BASE", "http://127.0.0.1:8099/api")
MYSQL_CLI = os.environ.get("MYSQL_CLI", "mysql -u root").split()
DB_NAME = os.environ.get("DB_NAME", "mercadoweb_test")
RONDAS = int(os.environ.get("RONDAS", "10"))
if not re.fullmatch(r"[A-Za-z0-9_]+_test", DB_NAME):
    print(f"ERROR: DB_NAME='{DB_NAME}' no termina en '_test'. Abortado sin tocar nada.", file=sys.stderr)
    sys.exit(2)
TMP = tempfile.mkdtemp(prefix="mw_conc_")

def token_csrf(jar):
    """Token CSRF de la cookie mw_csrf guardada en el cookie jar (lo mismo que hace el frontend)."""
    if not jar or not os.path.exists(jar):
        return ""
    token = ""
    for linea in open(jar):
        partes = linea.rstrip("\n").split("\t")
        if len(partes) == 7 and partes[5] == "mw_csrf":
            token = partes[6]
    return token

def curl(metodo, ruta, jar=None, guardar=None, datos=None):
    cmd = ["curl", "-s", "-w", "\n%{http_code}"]
    if jar: cmd += ["-b", jar]
    if guardar: cmd += ["-c", guardar]
    if metodo == "POST":
        cmd += ["-X", "POST", "-H", "Content-Type: application/json", "-H", f"X-CSRF-Token: {token_csrf(jar)}"]
        if datos is not None: cmd += ["-d", json.dumps(datos)]
    cmd.append(f"{BASE}{ruta}")
    salida = subprocess.run(cmd, capture_output=True, text=True).stdout
    *lineas, codigo = salida.split("\n")
    cuerpo = "\n".join(lineas)
    try: cuerpo = json.loads(cuerpo)
    except Exception: pass
    return codigo, cuerpo

def sql(q):
    r = subprocess.run(MYSQL_CLI + [DB_NAME, "-s", "-N", "-e", q], capture_output=True, text=True)
    if r.returncode != 0:
        raise RuntimeError(r.stderr)
    return r.stdout.strip()

TS = int(time.time()); fallas = 0; total_ok = 0
def verificar(cond, msg):
    global fallas, total_ok
    print(("  OK   - " if cond else "  FALLA - ") + msg)
    if cond: total_ok += 1
    else: fallas += 1

contador_usuarios = 0
def nuevos_usuarios(n):
    global contador_usuarios
    jars = []
    for _ in range(n):
        contador_usuarios += 1
        jar = os.path.join(TMP, f"u{contador_usuarios}.txt")
        codigo, _c = curl("POST", "/auth/registro.php", guardar=jar,
                          datos={"nombre": f"Conc {contador_usuarios}", "email": f"conc{TS}_{contador_usuarios}@test.com", "password": "clave123"})
        assert codigo == "201", f"no se pudo registrar el usuario de prueba ({codigo})"
        jars.append(jar)
    return jars

def disparar(jars):
    """Lanza todos los checkouts a la vez (barrera: todos los hilos arrancan juntos)."""
    with concurrent.futures.ThreadPoolExecutor(max_workers=len(jars)) as ex:
        futuros = [ex.submit(curl, "POST", "/pedidos/crear.php", jar, None, {"direccion_envio": f"Direccion concurrencia {i} 123"})
                   for i, jar in enumerate(jars)]
        return [f.result() for f in futuros]

def escenario_stock(nombre, id_producto, stock, usuarios, cantidad, exitos_esperados):
    print(f"\n== {nombre} ({RONDAS} rondas) ==")
    resumen = []
    for ronda in range(RONDAS):
        sql("DELETE FROM carrito_items;")
        sql(f"UPDATE productos SET stock={stock} WHERE id_producto={id_producto};")
        jars = nuevos_usuarios(usuarios)
        for jar in jars:
            c, _ = curl("POST", "/carrito/agregar.php", jar=jar, datos={"id_producto": id_producto, "cantidad": cantidad})
            assert c == "201", "no se pudo preparar el carrito"
        pedidos_antes = int(sql("SELECT COUNT(*) FROM pedidos;"))
        ultimo_id = int(sql("SELECT IFNULL(MAX(id_pedido),0) FROM pedidos;"))
        resultados = disparar(jars)
        codigos = sorted(r[0] for r in resultados)
        stock_final = int(sql(f"SELECT stock FROM productos WHERE id_producto={id_producto};"))
        nuevos = int(sql("SELECT COUNT(*) FROM pedidos;")) - pedidos_antes
        vendidas = int(sql(f"SELECT IFNULL(SUM(cantidad),0) FROM detalle_pedido WHERE id_producto={id_producto} AND id_pedido > {ultimo_id};"))
        carritos_restantes = int(sql("SELECT COUNT(*) FROM carrito_items;"))
        ok = (codigos.count("201") == exitos_esperados and codigos.count("409") == usuarios - exitos_esperados
              and stock_final == stock - exitos_esperados * cantidad and stock_final >= 0
              and nuevos == exitos_esperados and vendidas == exitos_esperados * cantidad
              and carritos_restantes == usuarios - exitos_esperados)
        resumen.append(ok)
        print(f"   ronda {ronda+1:2d}: HTTP {codigos}  stock final {stock_final}  pedidos nuevos {nuevos}  "
              f"unidades vendidas {vendidas}  carritos sin vaciar {carritos_restantes}  -> {'OK' if ok else 'FALLA'}")
    verificar(all(resumen), f"{sum(resumen)}/{RONDAS} rondas correctas: exactamente {exitos_esperados} éxito(s), el resto 409, "
                            f"stock final {stock - exitos_esperados*cantidad} (nunca negativo), un pedido por éxito, "
                            f"carrito vaciado solo en los éxitos")

def escenario_deadlock():
    print(f"\n== C) Dos usuarios con los mismos 2 productos (orden de alta invertido), stock de sobra ({RONDAS} rondas) ==")
    resumen = []
    for ronda in range(RONDAS):
        sql("DELETE FROM carrito_items;")
        sql("UPDATE productos SET stock=50 WHERE id_producto IN (6,7);")
        j1, j2 = nuevos_usuarios(2)
        curl("POST", "/carrito/agregar.php", jar=j1, datos={"id_producto": 6, "cantidad": 1})
        curl("POST", "/carrito/agregar.php", jar=j1, datos={"id_producto": 7, "cantidad": 1})
        curl("POST", "/carrito/agregar.php", jar=j2, datos={"id_producto": 7, "cantidad": 1})
        curl("POST", "/carrito/agregar.php", jar=j2, datos={"id_producto": 6, "cantidad": 1})
        resultados = disparar([j1, j2])
        codigos = [r[0] for r in resultados]
        s6, s7 = (int(x) for x in sql("SELECT GROUP_CONCAT(stock ORDER BY id_producto) FROM productos WHERE id_producto IN (6,7);").split(","))
        ok = codigos == ["201", "201"] and s6 == 48 and s7 == 48
        resumen.append(ok)
        print(f"   ronda {ronda+1:2d}: HTTP {codigos}  stock 6={s6} 7={s7}  -> {'OK' if ok else 'FALLA'}")
    verificar(all(resumen), f"{sum(resumen)}/{RONDAS} rondas: ambos checkouts terminan bien (sin deadlock ni 500) y el stock baja 2 en cada producto")

def verificar_paralelismo():
    """Sin esto la prueba podría pasar "por suerte": si el servidor atendiera una petición a la vez, los
    checkouts nunca competirían de verdad. Un trigger temporal hace que cada INSERT en pedidos tarde 1 s;
    dos checkouts de productos DISTINTOS (sin lock en común) deben terminar en ~1 s si corren en paralelo,
    y en ~2 s o más si el servidor los serializa.
    Nota: el servidor de desarrollo "php -S" con PHP_CLI_SERVER_WORKERS a veces entrega dos conexiones al mismo
    worker y las atiende en fila; por eso se hacen hasta 3 intentos y se informan todos. Basta uno en paralelo
    para demostrar que el servidor de pruebas sí puede generar competencia real."""
    print("\n== 0) El servidor procesa solicitudes en paralelo de verdad ==")
    duraciones = []
    sql("DROP TRIGGER IF EXISTS tg_prueba_lento; CREATE TRIGGER tg_prueba_lento BEFORE INSERT ON pedidos FOR EACH ROW SET @espera = SLEEP(1);")
    try:
        for intento in range(3):
            sql("DELETE FROM carrito_items;")
            j1, j2 = nuevos_usuarios(2)
            curl("POST", "/carrito/agregar.php", jar=j1, datos={"id_producto": 1, "cantidad": 1})
            curl("POST", "/carrito/agregar.php", jar=j2, datos={"id_producto": 9, "cantidad": 1})
            inicio = time.time(); codigos = [r[0] for r in disparar([j1, j2])]; duracion = time.time() - inicio
            duraciones.append(duracion)
            print(f"   intento {intento+1}: HTTP {codigos}, duración total {duracion:.2f} s")
            if codigos == ["201", "201"] and duracion < 1.8:
                break
    finally:
        sql("DROP TRIGGER IF EXISTS tg_prueba_lento;")
    verificar(min(duraciones) < 1.8,
              f"dos checkouts con 1 s de espera cada uno terminaron en {min(duraciones):.2f} s (< 1.8 s => corrieron en paralelo; intentos: "
              + ", ".join(f"{d:.2f} s" for d in duraciones) + ")")

verificar_paralelismo()
escenario_stock("A) Dos usuarios, 2 unidades c/u, stock 2", 5, stock=2, usuarios=2, cantidad=2, exitos_esperados=1)
escenario_stock("B) Tres usuarios, 1 unidad c/u, stock 2", 5, stock=2, usuarios=3, cantidad=1, exitos_esperados=2)
escenario_deadlock()
escenario_stock("D) Seis usuarios, 1 unidad c/u, stock 3", 5, stock=3, usuarios=6, cantidad=1, exitos_esperados=3)
verificar(sql("SELECT COUNT(*) FROM productos WHERE stock < 0;") == "0", "ningún producto quedó con stock negativo")

print(f"\nRESULTADO FINAL: {total_ok} pruebas OK, {fallas} con FALLA")
raise SystemExit(1 if fallas else 0)
