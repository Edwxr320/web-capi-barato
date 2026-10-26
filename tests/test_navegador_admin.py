"""Panel administrativo en Chromium real (Playwright): acceso, inventario, CRUD de productos con subida de imagen,
categorías, usuarios, pedidos con cambio de estado, XSS en datos del catálogo, modales que no bloquean y consola limpia.

Datos que modifica (solo en la base de pruebas): registra un cliente, crea un pedido, ajusta stock, crea/edita/
elimina productos y categorías de prueba, sube una imagen a img/productos (se borra al final), cambia estados.
Capturas: adm-01-dashboard.png … adm-09-tienda-xss.png"""
import asyncio, os, sys, time, zlib, struct, tempfile, json, glob
sys.path.insert(0, os.path.dirname(__file__))
from playwright.async_api import async_playwright
from navegador_comun import BASE, Contador, opciones_lanzamiento, nuevo_contexto, ir, captura, realmente_visible

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
TS = int(time.time()); EMAIL_C = f"navadm{TS}@test.com"; CLAVE = "clave12345"
t = Contador(); v = t.verificar
XSS = '<img src=x onerror="window.__xss=1">Parlante XSS'


def png(ruta, w=160, h=120):
    def chunk(tipo, datos): return struct.pack(">I", len(datos)) + tipo + datos + struct.pack(">I", zlib.crc32(tipo + datos) & 0xffffffff)
    raw = b"".join(b"\x00" + bytes([255, 241, 89]) * w for _ in range(h))
    open(ruta, "wb").write(b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b""))


async def login(pg, email, clave, next_=None):
    await ir(pg, "/iniciorsesion/iniciarsesion.html" + (f"?next={next_}" if next_ else ""))
    await pg.wait_for_selector("#login-email")
    await pg.fill("#login-email", email); await pg.fill("#login-password", clave)
    await pg.click("#form-login button[type=submit]")


async def confirmar_dialogo(pg):
    await pg.wait_for_selector("dialog [data-confirmar]")
    await pg.click("dialog [data-confirmar]")
    await pg.wait_for_selector("dialog [data-confirmar]", state="detached")


async def api(pg, ruta, cuerpo=None):
    """fetch desde la página (misma sesión y token CSRF que la interfaz)."""
    return await pg.evaluate("""async ([ruta, cuerpo]) => {
        const token = (document.cookie.split('; ').find(c => c.startsWith('mw_csrf=')) || '=').split('=')[1];
        const r = await fetch(ruta, cuerpo ? {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':decodeURIComponent(token)}, body: JSON.stringify(cuerpo)} : {});
        return {status: r.status, datos: await r.json()};
    }""", [ruta, cuerpo])


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(**opciones_lanzamiento())

        print("1) Acceso: sin sesión y como cliente")
        cc = await nuevo_contexto(browser); pc = await cc.new_page()
        await ir(pc, "/admin/index.html"); await pc.wait_for_url("**iniciarsesion.html?next=admin")
        v(True, "sin sesión, el panel redirige a login con next=admin")
        await pc.click("#tab-registro")
        await pc.fill("#registro-nombre", "Cliente Panel"); await pc.fill("#registro-email", EMAIL_C)
        await pc.fill("#registro-password", CLAVE); await pc.fill("#registro-confirmar", CLAVE)
        await pc.click("#form-registro button[type=submit]")
        await pc.wait_for_url(f"{BASE}/admin/index.html")
        await pc.wait_for_selector("#sin-permiso:not(.oculto)")
        v(await realmente_visible(pc, "#sin-permiso"), "un cliente ve 'No tenés permiso' en el panel")
        v(not await realmente_visible(pc, "#admin-layout"), "…y no ve el menú ni las secciones")
        r = await api(pc, "../api/admin/inventario.php", {"id_producto": 1, "operacion": "fijar", "cantidad": 0})
        v(r["status"] == 403, f"forzar la petición desde el navegador del cliente: 403 (obtuvo {r['status']})")
        await ir(pc, "/index.html"); await pc.wait_for_selector(".producto")
        v(await pc.locator(".info-user .enlace-panel").count() == 0, "el cliente no tiene el enlace 'Panel' en la tienda")
        # el cliente compra algo para tener un pedido que gestionar
        await api(pc, "api/carrito/agregar.php", {"id_producto": 3, "cantidad": 1})
        r = await api(pc, "api/pedidos/crear.php", {"direccion_envio": "Av. Panel 742, Miraflores"})
        id_ped = r["datos"]["pedido"]["id_pedido"]

        print("2) Admin: login con next=admin, dashboard")
        ctx = await nuevo_contexto(browser); pg = await ctx.new_page()
        await login(pg, "admin@capibarato.pe", "CapiBarato2026!", "admin")
        await pg.wait_for_url(f"{BASE}/admin/index.html"); await pg.wait_for_selector(".kpi")
        v(await pg.locator(".kpi").count() == 6, "el dashboard muestra 6 indicadores")
        d = (await api(pg, "../api/admin/estadisticas.php"))["datos"]["indicadores"]
        txt = await pg.text_content(".kpis")
        v(f"{d['pedidos_pendientes']}" in txt and "Ventas confirmadas" in txt, "los indicadores son los del servidor")
        v((await pg.text_content("#nav-pendientes")).strip() == str(d["pedidos_pendientes"]), "contador de pendientes en el menú")
        await captura(pg, "adm-01-dashboard.png")
        await ir(pg, "/index.html"); await pg.wait_for_selector(".info-user .enlace-panel")
        v(True, "el administrador ve el enlace 'Panel' en la tienda")

        print("3) Inventario: filtros y ajuste de stock")
        await ir(pg, "/admin/index.html#/inventario"); await pg.wait_for_selector("form.ajuste")
        await pg.click(".chip[data-filtro=bajo]"); await pg.wait_for_timeout(500)
        v(await pg.locator("form.ajuste").count() == 1, "filtro 'Stock bajo' deja solo el Galaxy Book3")
        await captura(pg, "adm-02-inventario-bajo.png")
        form = pg.locator("form.ajuste[data-producto='5']")
        await form.locator("select").select_option("sumar"); await form.locator("input").fill("10")
        await form.locator("button").click(); await pg.wait_for_timeout(700)
        r = await api(pg, "../api/productos/detalle.php?id=5")
        v(r["datos"]["producto"]["stock"] == 12, f"sumar 10 al stock 2 → 12 en MySQL (obtuvo {r['datos']['producto']['stock']})")
        await pg.click(".chip[data-filtro=todos]"); await pg.wait_for_timeout(500)
        form = pg.locator("form.ajuste[data-producto='5']")
        await form.locator("select").select_option("restar"); await form.locator("input").fill("2.5")
        await form.locator("button").click(); await pg.wait_for_timeout(300)
        v("entero" in (await pg.locator("form.ajuste[data-producto='5'] + .mensaje-fila").text_content()), "cantidad decimal: error en la fila, sin enviar")
        await form.locator("input").fill("999"); await form.locator("button").click(); await pg.wait_for_timeout(600)
        msj = await pg.locator("form.ajuste[data-producto='5'] + .mensaje-fila").text_content()
        v("negativo" in msj, f"restar más que el stock: muestra el error del servidor ({msj.strip()[:60]}…)")
        v((await api(pg, "../api/productos/detalle.php?id=5"))["datos"]["producto"]["stock"] == 12, "…y el stock sigue en 12")

        print("4) Productos: crear con imagen subida y nombre con HTML")
        tmp = tempfile.mkdtemp(); ruta_png = os.path.join(tmp, "producto.png"); png(ruta_png)
        await ir(pg, "/admin/index.html#/productos"); await pg.wait_for_selector("[data-editar]")
        await pg.click("#btn-nuevo-producto"); await pg.wait_for_selector("#form-producto")
        await pg.click("#p-guardar"); await pg.wait_for_timeout(200)
        v((await pg.text_content("#p-error")).strip() != "", "guardar vacío: muestra el error de validación")
        await pg.fill("#p-nombre", XSS); await pg.fill("#p-precio", "150.50"); await pg.fill("#p-descuento", "10")
        v("S/. 135.45" in await pg.text_content(".vista-precio"), "vista previa del precio final (150.50 − 10 %)")
        await pg.fill("#p-stock", "4"); await pg.fill("#p-proveedor", "AUDIO SAC")
        await pg.uncheck("#p-envio-gratis"); await pg.fill("#p-costo", "15")
        await pg.fill("#p-caracteristicas", "Bluetooth 5.3\nBatería de 12 h")
        await pg.set_input_files("#p-subir", ruta_png)
        await pg.wait_for_selector("#p-imagenes .item-imagen")
        ruta_subida = await pg.get_attribute("#p-imagenes .item-imagen", "data-ruta")
        v(ruta_subida.startswith("img/productos/"), f"imagen subida y agregada ({ruta_subida})")
        await pg.select_option(".imagenes-acciones select", "img/celulares/11.png"); await pg.click(".imagenes-acciones button")
        v(await pg.locator("#p-imagenes .item-imagen").count() == 2, "se agregó además una imagen existente")
        await captura(pg, "adm-03-form-producto.png", completa=False)
        await pg.click("#p-guardar"); await pg.wait_for_selector("dialog", state="detached")
        await pg.wait_for_timeout(500)
        lista = (await api(pg, "../api/admin/productos.php?q=XSS"))["datos"]["productos"]
        v(len(lista) == 1, "el producto quedó creado")
        nuevo = lista[0]
        v(nuevo["precio_final"] == 135.45 and nuevo["stock"] == 4 and nuevo["costo_envio"] == 15.0 and nuevo["imagen_principal"] == ruta_subida,
          "con precio, stock, envío e imagen principal correctos")
        v(await pg.evaluate("typeof window.__xss") == "undefined", "el nombre con HTML no se ejecutó en el panel")
        v(await pg.locator(f"text={XSS}").count() >= 1, "…y se ve como texto literal en la tabla")
        await captura(pg, "adm-04-productos.png")

        print("5) Productos: editar, desactivar, eliminar")
        await pg.click(f"[data-editar='{nuevo['id_producto']}']"); await pg.wait_for_selector("#form-producto")
        await pg.fill("#p-precio", "199.90"); await pg.fill("#p-descuento", "")
        await pg.click("#p-guardar"); await pg.wait_for_selector("dialog", state="detached"); await pg.wait_for_timeout(400)
        v((await api(pg, f"../api/admin/productos.php?id={nuevo['id_producto']}"))["datos"]["producto"]["precio_final"] == 199.9, "editar el precio: 199.90 sin descuento")
        await pg.click(f"[data-estado-producto='{nuevo['id_producto']}']"); await confirmar_dialogo(pg); await pg.wait_for_timeout(400)
        v((await api(pg, f"../api/productos/detalle.php?id={nuevo['id_producto']}"))["status"] == 404, "desactivado: la tienda ya no lo muestra")
        await pg.click(f"[data-eliminar='{nuevo['id_producto']}']"); await confirmar_dialogo(pg); await pg.wait_for_timeout(400)
        v((await api(pg, f"../api/admin/productos.php?id={nuevo['id_producto']}"))["status"] == 404, "sin ventas: se eliminó definitivamente")
        await pg.click("[data-eliminar='3']")
        v("NO se elimina" in await pg.text_content("dialog"), "el Samsung A04s tiene ventas: el diálogo avisa que se desactivará")
        await pg.click("dialog .btn-sec"); await pg.wait_for_selector("dialog", state="detached")
        v((await api(pg, "../api/productos/detalle.php?id=3"))["status"] == 200, "cancelar no cambia nada")

        print("6) Categorías")
        await ir(pg, "/admin/index.html#/categorias"); await pg.wait_for_selector("#cat-nombre")
        await pg.fill("#cat-nombre", "Audio"); await pg.click("form.barra button[type=submit]"); await pg.wait_for_timeout(500)
        v(await pg.locator("[data-eliminar-categoria=audio]").count() == 1, "categoría 'Audio' creada")
        await pg.click("[data-eliminar-categoria=celulares]"); await pg.wait_for_timeout(300)
        v("movelos" in await pg.text_content("#avisos"), "eliminar 'Celulares' (con productos) se impide con un aviso")
        await captura(pg, "adm-05-categorias.png")
        await ir(pg, "/index.html"); await pg.wait_for_selector("#audio")
        v(True, "la tienda muestra la categoría nueva en el menú")
        await ir(pg, "/admin/index.html#/categorias"); await pg.wait_for_selector("[data-eliminar-categoria=audio]")
        await pg.click("[data-eliminar-categoria=audio]"); await confirmar_dialogo(pg); await pg.wait_for_timeout(400)
        v(await pg.locator("[data-eliminar-categoria=audio]").count() == 0, "categoría vacía eliminada")

        print("7) Pedidos: detalle y avance de estado")
        await ir(pg, f"/admin/index.html#/pedidos/{id_ped}"); await pg.wait_for_selector("dialog .flujo")
        v("Av. Panel 742" in await pg.text_content("dialog .direccion-envio"), "el detalle muestra la dirección")
        v(await pg.locator("dialog [data-estado=cancelado]").count() == 0, "no se ofrece cancelar (regla pendiente de definición)")
        await captura(pg, "adm-06-pedido.png", completa=False)
        await pg.click("dialog [data-estado=pagado]"); await confirmar_dialogo(pg)
        await pg.wait_for_selector("dialog [data-estado=enviado]")
        v((await api(pg, f"../api/admin/pedidos.php?id={id_ped}"))["datos"]["pedido"]["estado"] == "pagado", "Registrar pago → 'pagado' en MySQL")
        await pg.click("dialog [data-estado=enviado]"); await confirmar_dialogo(pg)
        await pg.wait_for_selector("dialog [data-estado=completado]")
        v((await api(pg, f"../api/admin/pedidos.php?id={id_ped}"))["datos"]["pedido"]["estado"] == "enviado", "Marcar como enviado → 'enviado'")
        await pg.click("dialog .btn-cerrar"); await pg.wait_for_selector("dialog", state="detached")
        await pg.click(".admin-nav a[data-vista=usuarios]", timeout=3000)
        v(True, "tras cerrar el diálogo el menú sigue respondiendo (no queda una capa bloqueando)")
        estado_cli = (await api(pc, "api/pedidos/listar.php"))["datos"]["pedidos"][0]["estado"]
        v(estado_cli == "enviado", "el cliente ve 'enviado' en Mis Compras")

        print("8) Usuarios: desactivar y reactivar al cliente")
        await pg.wait_for_selector("[data-accion-estado]")
        id_cli = (await api(pg, f"../api/admin/usuarios.php?q={EMAIL_C}"))["datos"]["usuarios"][0]["id_usuario"]
        await pg.click(f"[data-accion-estado='{id_cli}']"); await confirmar_dialogo(pg); await pg.wait_for_timeout(500)
        v((await api(pc, "api/pedidos/listar.php"))["status"] == 401, "cliente desactivado: su sesión deja de valer")
        await captura(pg, "adm-07-usuarios.png")
        await pg.click(f"[data-accion-estado='{id_cli}']"); await confirmar_dialogo(pg); await pg.wait_for_timeout(400)
        v((await api(pg, f"../api/admin/usuarios.php?id={id_cli}"))["datos"]["usuario"]["estado"] == "activo", "reactivado")
        v(await pg.locator(f"[data-accion-estado='{(await api(pg, '../api/auth/sesion.php'))['datos']['usuario']['id_usuario']}']").count() == 0,
          "el admin no tiene botones para cambiarse a sí mismo")

        print("9) Estadísticas y teclado")
        await ir(pg, "/admin/index.html#/estadisticas"); await pg.wait_for_selector(".serie")
        v(await pg.locator(".serie .col").count() == 14, "serie de 14 días")
        await captura(pg, "adm-08-estadisticas.png")
        await ir(pg, "/admin/index.html#/pedidos"); await pg.wait_for_selector("[data-ver-pedido]")
        await pg.focus("[data-ver-pedido]"); await pg.keyboard.press("Enter"); await pg.wait_for_selector("dialog")
        await pg.keyboard.press("Escape"); await pg.wait_for_selector("dialog", state="detached")
        v(await pg.evaluate("document.activeElement && document.activeElement.matches('[data-ver-pedido]')"), "Enter abre el detalle; Escape lo cierra y el foco vuelve al botón")

        print("10) La tienda escapa nombres con HTML (XSS almacenado)")
        r = await api(pg, "../api/admin/productos.php", {"accion": "crear", "nombre": XSS, "id_categoria": 1, "precio_original": 99, "stock": 3,
                      "proveedor": '<b onmouseover="window.__xss=2">Prov</b>', "envio_gratis": True, "estado": "activo", "imagenes": ["img/celulares/11.png"],
                      "caracteristicas": ['<script>window.__xss=3</script>']})
        id_x = r["datos"]["producto"]["id_producto"]
        tienda = await ctx.new_page()
        await ir(tienda, "/index.html"); await tienda.wait_for_selector(f"[id='{id_x}']")
        await tienda.click(f"[id='{id_x}']"); await tienda.wait_for_selector(".producto-visitado")
        await tienda.click(".btn-agregar-al-carrito"); await tienda.wait_for_url("**/carrito.html"); await tienda.wait_for_selector(".carrito-producto")
        await ir(tienda, "/checkout.html"); await tienda.wait_for_selector(".checkout-item-resumen"); await tienda.wait_for_timeout(300)
        v(await tienda.evaluate("typeof window.__xss") == "undefined", "catálogo, detalle, carrito y checkout no ejecutaron el HTML del nombre/proveedor")
        v(XSS in await tienda.text_content(".checkout-item-resumen"), "el nombre se ve literal en el checkout")
        await captura(tienda, "adm-09-tienda-xss.png")
        await api(pg, "../api/admin/productos.php", {"accion": "eliminar", "id_producto": id_x})

        for c in (ctx, cc):
            print("Errores JS:", c.errores_js, "| Consola:", c.errores_consola, "| 5xx:", c.respuestas_5xx)
        # Los 403/404/409 esperados de las pruebas negativas aparecen en consola como "Failed to load resource";
        # se descartan solo esos códigos, cualquier otro error cuenta.
        esperados = ("status of 403", "status of 404", "status of 409", "status of 400", "status of 401")
        otros = [e for c in (ctx, cc) for e in c.errores_consola if not any(x in e for x in esperados)]
        v(not ctx.errores_js and not cc.errores_js, "cero errores de JavaScript")
        v(not otros, f"sin errores de consola inesperados ({otros})")
        v(not ctx.respuestas_5xx and not cc.respuestas_5xx, "ninguna respuesta 5xx")
        await browser.close()
    for f in glob.glob(os.path.join(RAIZ, "img", "productos", "*")):
        if not f.endswith(".htaccess"):
            os.remove(f)
    t.final()

asyncio.run(main())
