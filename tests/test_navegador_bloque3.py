"""Flujo real en Chromium (Playwright): tienda -> carrito de invitado -> login/registro con next=checkout ->
fusión -> checkout -> confirmación (bug B-02) -> carrito vacío -> Mis Compras -> detalle; XSS en la dirección;
retorno con next=mis-compras; open redirect; un solo logout; cero errores de JavaScript.

Datos que modifica (solo en la base de pruebas): registra un cliente y crea 2 pedidos.
Capturas: b3-01-carrito-invitado.png, b3-02-checkout.png, b3-03-checkout-error-direccion.png,
b3-04-confirmacion.png, b3-05-mis-compras.png, b3-06-detalle-pedido.png, b3-07-detalle-xss.png."""
import asyncio, re, time, sys, os
sys.path.insert(0, os.path.dirname(__file__))
from playwright.async_api import async_playwright
from navegador_comun import BASE, Contador, opciones_lanzamiento, nuevo_contexto, ir, captura, realmente_visible

EMAIL = f"nav{int(time.time())}@test.com"; CLAVE = "clavesegura1"
t = Contador(); v = t.verificar


async def abrir_detalle_y_agregar(page, indice):
    await ir(page, "/index.html")
    await page.wait_for_selector(".producto")
    await page.locator(".producto").nth(indice).click()
    await page.wait_for_selector(".btn-agregar-al-carrito")
    await page.click(".btn-agregar-al-carrito")
    await page.wait_for_url("**/carrito.html")
    await page.wait_for_selector(".carrito-producto")


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(**opciones_lanzamiento())
        ctx = await nuevo_contexto(browser)
        page = await ctx.new_page()

        print("1) Checkout sin sesión")
        await ir(page, "/checkout.html"); await page.wait_for_url("**iniciarsesion.html?next=checkout")
        v(page.url.endswith("iniciarsesion.html?next=checkout"), "checkout.html sin sesión redirige a login con next=checkout")

        print("2) Carrito de INVITADO (localStorage) y 'Continuar Compra' sin sesión")
        await abrir_detalle_y_agregar(page, 0)   # Moto G13: S/. 429.14, envío gratis
        local = await page.evaluate("JSON.parse(localStorage.getItem('productos-en-carrito'))")
        v(local and len(local) == 1 and local[0]["cantidad"] == 1, "el invitado guarda el producto en localStorage")
        v((await page.text_content(".carrito-nota-login")).strip() == "Vas a necesitar iniciar sesión para finalizar la compra.",
          "el carrito avisa al invitado que necesitará iniciar sesión")
        await captura(page, "b3-01-carrito-invitado.png")
        await page.click("#btn-comprar"); await page.wait_for_url("**iniciarsesion.html?next=checkout")
        v(True, "'Continuar Compra' sin sesión lleva a login con next=checkout (no se compra como invitado)")

        print("3) Registro -> fusión del carrito -> vuelta al checkout")
        await page.click("#tab-registro")
        await page.fill("#registro-nombre", "Cliente Navegador"); await page.fill("#registro-email", EMAIL)
        await page.fill("#registro-password", CLAVE); await page.fill("#registro-confirmar", CLAVE)
        await page.click("#form-registro button[type=submit]")
        await page.wait_for_url(f"{BASE}/checkout.html", timeout=8000)
        v(True, "tras registrarse vuelve a checkout.html")
        await page.wait_for_function("document.querySelector('#checkout-total').innerText.trim() !== 'S/. 0.00'")
        v(await page.evaluate("localStorage.getItem('productos-en-carrito')") is None, "el carrito de invitado se fusionó y se limpió localStorage")
        api = await page.evaluate("fetch('api/carrito/listar.php').then(r=>r.json())")
        v([(i["producto"]["id_producto"], i["cantidad"]) for i in api["items"]] == [(1, 1)], "el producto del invitado ahora está en el carrito de MySQL")

        print("4) Agregar otro producto con sesión y volver al checkout")
        await abrir_detalle_y_agregar(page, 8)   # TV Kodak 24": S/. 494.10, envío gratis
        v(await page.locator(".carrito-producto").count() == 2, "hay 2 productos en el carrito")
        v((await page.text_content(".carrito-nota-login")).strip() == "", "con sesión ya no aparece el aviso de login")
        await page.click("#btn-comprar"); await page.wait_for_url(f"{BASE}/checkout.html")
        await page.wait_for_function("document.querySelector('#checkout-total').innerText.trim() !== 'S/. 0.00'")
        v("Cliente Navegador" in await page.text_content("#checkout-nombre"), "muestra el nombre de la sesión")
        v(EMAIL in await page.text_content("#checkout-email"), "muestra el correo de la sesión")
        v((await page.text_content("#checkout-subtotal")).strip() == "S/. 923.24", "subtotal S/. 923.24 (429.14 + 494.10)")
        v((await page.text_content("#checkout-envio")).strip() == "Gratis", "envío: Gratis")
        v((await page.text_content("#checkout-total")).strip() == "S/. 923.24", "total S/. 923.24")
        v(await page.locator(".checkout-item-resumen").count() == 2, "el resumen lista los 2 productos")
        v(not await realmente_visible(page, "#checkout-confirmacion"), "la confirmación NO se ve antes de confirmar")
        await captura(page, "b3-02-checkout.png")

        print("5) Validación de la dirección")
        await page.click("#btn-confirmar-pedido")
        v((await page.text_content("#checkout-error")).strip() == "Ingresá una dirección de envío válida.", "dirección vacía: muestra el error")
        await page.fill("#checkout-direccion", "Ab")
        await page.click("#btn-confirmar-pedido")
        v((await page.text_content("#checkout-error")).strip() != "", "dirección demasiado corta: muestra el error")
        await captura(page, "b3-03-checkout-error-direccion.png")
        n = await page.evaluate("fetch('api/pedidos/listar.php').then(r=>r.json()).then(d=>d.pedidos.length)")
        v(n == 0, "esos intentos no crearon pedidos")

        print("6) Confirmar pedido (bug B-02)")
        await page.fill("#checkout-direccion", "Av. Arequipa 1234, Lince, Lima")
        await page.click("#btn-confirmar-pedido")
        await page.wait_for_selector("#checkout-confirmacion", state="visible")
        v(await realmente_visible(page, "#checkout-confirmacion"), "aparece '¡Gracias por tu compra!'")
        v(not await realmente_visible(page, "#contenedor-checkout"), "B-02: el bloque formulario+resumen queda oculto de verdad")
        v(not await realmente_visible(page, "#checkout-direccion"), "B-02: el campo de dirección no se ve")
        v(not await realmente_visible(page, "#btn-confirmar-pedido"), "B-02: el botón 'Confirmar pedido' no se ve (no se puede confirmar dos veces)")
        v(not await realmente_visible(page, "#checkout-resumen"), "B-02: el resumen del pedido no se ve")
        id1 = (await page.text_content("#confirmacion-numero")).strip().lstrip("#")
        v(id1.isdigit(), f"muestra el número de pedido (#{id1})")
        v((await page.text_content("#num-en-carrito")).strip() == "0", "el contador del header queda en 0")
        await captura(page, "b3-04-confirmacion.png")

        print("7) Carrito vacío tras el pedido")
        await ir(page, "/carrito.html"); await page.wait_for_load_state("networkidle")
        items = await page.evaluate("fetch('api/carrito/listar.php').then(r => r.json()).then(d => d.items.length)")
        v(items == 0, "el carrito quedó vacío en MySQL")
        v(await realmente_visible(page, "#carrito-vacio"), "la página muestra 'Carrito Vacío'")
        v(await page.locator(".carrito-producto").count() == 0, "y no muestra productos")

        print("8) Mis Compras y detalle")
        await ir(page, "/mis-compras.html"); await page.wait_for_selector(".pedido-resumen")
        v(await page.locator(".pedido-resumen").count() == 1, "lista 1 pedido")
        v(f"Pedido #{id1}" in await page.text_content(".pedido-numero"), "es el pedido recién creado")
        v("Pendiente" in await page.text_content(".pedido-estado"), "estado 'Pendiente'")
        v("S/. 923.24" in await page.text_content(".pedido-resumen-total"), "total S/. 923.24")
        await captura(page, "b3-05-mis-compras.png")
        await page.click(".pedido-resumen"); await page.wait_for_selector(".pedido-visitado")
        v(await page.locator(".pedido-detalle-item").count() == 2, "el detalle muestra los 2 productos")
        v("Av. Arequipa 1234" in await page.text_content(".pedido-visitado-direccion"), "el detalle muestra la dirección")
        textos = await page.locator(".pedido-detalle-item .cantidad").all_text_contents()
        v(sorted(x.strip() for x in textos) == ["1 × S/. 429.14", "1 × S/. 494.10"], f"cantidades y precios unitarios correctos ({textos})")
        await captura(page, "b3-06-detalle-pedido.png", completa=False)
        await page.click(".pedido-visitado .cerrar")
        v(not await realmente_visible(page, ".pedido-visitado"), "el modal se cierra con la ×")
        await page.locator(".pedido-resumen").first.click(timeout=3000)
        await page.wait_for_selector(".pedido-visitado", timeout=3000)
        v(await realmente_visible(page, ".pedido-visitado"), "tras cerrarlo, la lista sigue siendo clickeable (el modal no bloquea)")
        await page.click(".pedido-visitado .cerrar")

        print("9) XSS: una dirección maliciosa se muestra como texto y no se ejecuta")
        await abrir_detalle_y_agregar(page, 2)
        await page.click("#btn-comprar"); await page.wait_for_url(f"{BASE}/checkout.html")
        await page.wait_for_function("document.querySelector('#checkout-total').innerText.trim() !== 'S/. 0.00'")
        payload = '<img src=x onerror="window.__xss=1"> <script>window.__xss=2</script>'
        await page.fill("#checkout-direccion", payload)
        await page.click("#btn-confirmar-pedido"); await page.wait_for_selector("#checkout-confirmacion", state="visible")
        id2 = (await page.text_content("#confirmacion-numero")).strip().lstrip("#")
        await ir(page, "/mis-compras.html"); await page.wait_for_selector(".pedido-resumen")
        v(await page.locator(".pedido-resumen").count() == 2, "ahora hay 2 pedidos")
        v((await page.locator(".pedido-numero").first.text_content()).strip() == f"Pedido #{id2}", "el más reciente aparece primero")
        await page.locator(".pedido-resumen", has_text=re.compile(rf"Pedido #{id2}\b")).click()
        await page.wait_for_selector(".pedido-visitado"); await page.wait_for_timeout(500)
        v(await page.evaluate("typeof window.__xss") == "undefined", "no se ejecutó el código inyectado")
        v(await page.locator(".pedido-visitado-direccion img").count() == 0, "no se creó ningún <img> a partir de la dirección")
        v(payload in await page.text_content(".pedido-visitado-direccion"), "la dirección aparece como texto literal")
        await captura(page, "b3-07-detalle-xss.png", completa=False)
        await page.click(".pedido-visitado .cerrar")

        print("10) Cerrar sesión: un solo POST de logout")
        logouts = []
        page.on("request", lambda r: "logout.php" in r.url and logouts.append(r.method))
        await ir(page, "/index.html"); await page.wait_for_selector('.info-user a:has-text("Cerrar sesión")')
        async with page.expect_navigation(wait_until="load"):
            await page.click('.info-user a:has-text("Cerrar sesión")')
        await page.wait_for_timeout(300)
        v(logouts == ["POST"], f"se envió exactamente un POST de logout (enviados: {logouts})")
        ses = await page.evaluate("fetch('api/auth/sesion.php').then(r=>r.json()).then(d=>d.autenticado)")
        v(ses is False, "la sesión quedó cerrada en el servidor")

        print("11) next=mis-compras: login y vuelta a Mis Compras")
        await ir(page, "/mis-compras.html"); await page.wait_for_url("**iniciarsesion.html?next=mis-compras")
        v(True, "Mis Compras sin sesión redirige a login con next=mis-compras")
        await page.fill("#login-email", EMAIL); await page.fill("#login-password", CLAVE)
        await page.click("#form-login button[type=submit]")
        await page.wait_for_url(f"{BASE}/mis-compras.html", timeout=8000)
        await page.wait_for_selector(".pedido-resumen")
        v(await page.locator(".pedido-resumen").count() == 2, "tras el login vuelve a Mis Compras y ve sus 2 pedidos")

        print("12) Open redirect: destinos externos o raros terminan en index.html")
        for destino in ["https://evil.example/", "//evil.example", "javascript:alert(1)", "../../../etc/passwd", "checkout.html", "CHECKOUT"]:
            c2 = await nuevo_contexto(browser); pg = await c2.new_page()
            await ir(pg, f"/iniciorsesion/iniciarsesion.html?next={destino}")
            await pg.wait_for_selector("#login-email")
            await pg.fill("#login-email", EMAIL); await pg.fill("#login-password", CLAVE)
            await pg.click("#form-login button[type=submit]")
            await pg.wait_for_url(f"{BASE}/index.html", timeout=8000)
            v(pg.url == f"{BASE}/index.html", f"next={destino!r} -> {pg.url.replace(BASE, '')} (dentro del sitio, sin redirigir afuera)")
            await c2.close()

        print("\nErrores de JavaScript:", ctx.errores_js)
        print("Errores de consola:", ctx.errores_consola)
        print("Respuestas 5xx del sitio:", ctx.respuestas_5xx)
        v(not ctx.errores_js, "cero errores de JavaScript en todo el flujo")
        v(not ctx.errores_consola, "cero errores en la consola del navegador")
        v(not ctx.respuestas_5xx, "ninguna respuesta 5xx del servidor")
        await browser.close()
    t.final()

asyncio.run(main())
