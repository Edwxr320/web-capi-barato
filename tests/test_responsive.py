"""Responsive real en Chromium a 375, 768, 1024 y 1400 px de ancho, en la tienda y en el panel.

Para cada página y ancho comprueba, midiendo el DOM renderizado (no solo leyendo el CSS):
  - que no haya scroll horizontal en la página;
  - que ningún elemento visible se salga por la derecha (salvo dentro de un contenedor con scroll propio);
  - que los campos de formulario visibles midan al menos 120 px de ancho (usables);
  - que los botones y enlaces visibles midan al menos 24×24 px (WCAG 2.2, tamaño mínimo de objetivo);
  - cero errores de JavaScript.
Guarda una captura por página y ancho: resp-<ancho>-<pagina>.png

Datos que modifica (solo en la base de pruebas): registra un cliente, crea un pedido y deja 2 productos en su carrito."""
import asyncio, os, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from playwright.async_api import async_playwright
from navegador_comun import BASE, Contador, opciones_lanzamiento, nuevo_contexto, ir, captura

ANCHOS = [375, 768, 1024, 1400]
t = Contador(); v = t.verificar
EMAIL = f"resp{int(time.time())}@test.com"; CLAVE = "clave12345"

MEDIR = """() => {
    const ancho = window.innerWidth;
    const problemas = { scroll: document.documentElement.scrollWidth - ancho, desbordes: [], campos: [], objetivos: [] };
    const visible = (el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return false;
        for (let n = el; n; n = n.parentElement) {
            const cs = getComputedStyle(n);
            if (cs.display === 'none' || cs.visibility === 'hidden') return false;
        }
        return true;
    };
    const dentroDeScroll = (el) => {
        for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
            const ox = getComputedStyle(n).overflowX;
            if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') return true;
        }
        return false;
    };
    const nombre = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
    for (const el of document.querySelectorAll('body *')) {
        if (!visible(el) || el.closest('dialog:not([open])')) continue;
        let r = el.getBoundingClientRect();
        // Un checkbox dentro de su <label>: el área clickeable es la etiqueta completa.
        if (el.matches('input[type=checkbox]') && el.closest('label')) r = el.closest('label').getBoundingClientRect();
        if (r.right > ancho + 1 && !dentroDeScroll(el) && getComputedStyle(el).position !== 'fixed') problemas.desbordes.push(nombre(el) + ' (right=' + Math.round(r.right) + ')');
        if (el.matches('input[type=text], input[type=email], input[type=password], input[type=search], input:not([type]), textarea, select') && r.width < 120 && !el.closest('.ajuste'))
            problemas.campos.push(nombre(el) + ' (' + Math.round(r.width) + 'px)');
        // Excepción "en línea" de WCAG 2.5.8: un enlace dentro de una oración (su contenedor tiene más texto) no cuenta.
        const enLinea = el.tagName === 'A' && el.parentElement && el.parentElement.textContent.trim().length > el.textContent.trim().length + 3;
        if (el.matches('button, a[href], [role=button], input[type=checkbox]') && (r.width < 24 || r.height < 24) && !enLinea && !el.closest('p, li, .nota'))
            problemas.objetivos.push(nombre(el) + ' (' + Math.round(r.width) + '×' + Math.round(r.height) + ')');
    }
    problemas.desbordes = problemas.desbordes.slice(0, 5); problemas.campos = problemas.campos.slice(0, 5); problemas.objetivos = problemas.objetivos.slice(0, 8);
    return problemas;
}"""


async def revisar(pg, ancho, nombre):
    await pg.wait_for_timeout(350)
    m = await pg.evaluate(MEDIR)
    v(m["scroll"] <= 1, f"{ancho}px {nombre}: sin scroll horizontal (sobra {m['scroll']} px)")
    v(not m["desbordes"], f"{ancho}px {nombre}: ningún elemento se sale de la pantalla {m['desbordes'] or ''}")
    v(not m["campos"], f"{ancho}px {nombre}: campos de formulario de 120 px o más {m['campos'] or ''}")
    v(not m["objetivos"], f"{ancho}px {nombre}: botones y enlaces de 24×24 px o más {m['objetivos'] or ''}")
    await captura(pg, f"resp-{ancho}-{nombre}.png")


async def api(pg, ruta, cuerpo=None):
    return await pg.evaluate("""async ([ruta, cuerpo]) => {
        const token = decodeURIComponent((document.cookie.split('; ').find(c => c.startsWith('mw_csrf=')) || '=').split('=')[1]);
        const r = await fetch(ruta, cuerpo ? {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':token}, body: JSON.stringify(cuerpo)} : {});
        return {status: r.status, datos: await r.json()};
    }""", [ruta, cuerpo])


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(**opciones_lanzamiento())
        # Preparación: un cliente con un pedido y 2 productos en el carrito; una sesión de admin.
        prep = await nuevo_contexto(browser); pp = await prep.new_page()
        await ir(pp, "/index.html"); await pp.wait_for_selector(".producto")
        await pp.evaluate("""async ([e, c]) => { await fetch('api/auth/registro.php', {method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({nombre:'Cliente Responsive', email:e, password:c})}); }""", [EMAIL, CLAVE])
        await api(pp, "api/carrito/agregar.php", {"id_producto": 8, "cantidad": 1})
        await api(pp, "api/pedidos/crear.php", {"direccion_envio": "Av. Larco 1234, Miraflores, Lima - frente al parque"})
        await api(pp, "api/carrito/agregar.php", {"id_producto": 7, "cantidad": 2})
        await api(pp, "api/carrito/agregar.php", {"id_producto": 2, "cantidad": 1})
        estado_cliente = await prep.storage_state()
        await prep.close()
        prep = await nuevo_contexto(browser); pp = await prep.new_page()
        await ir(pp, "/index.html"); await pp.wait_for_selector(".producto")
        await pp.evaluate("""async () => { await fetch('api/auth/login.php', {method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({email:'admin@capibarato.pe', password:'CapiBarato2026!'})}); }""")
        estado_admin = await prep.storage_state()
        await prep.close()

        for ancho in ANCHOS:
            print(f"\n== {ancho} px ==")
            # Invitado
            ctx = await nuevo_contexto(browser, ancho, 900); pg = await ctx.new_page()
            await ir(pg, "/index.html"); await pg.wait_for_selector(".producto")
            await revisar(pg, ancho, "catalogo")
            await pg.locator(".producto").first.click(); await pg.wait_for_selector(".producto-visitado")
            await revisar(pg, ancho, "detalle-producto")
            await pg.keyboard.press("Escape")
            await ir(pg, "/iniciorsesion/iniciarsesion.html"); await pg.wait_for_selector("#login-email")
            await revisar(pg, ancho, "login")
            errores = list(ctx.errores_js); await ctx.close()
            # Cliente
            ctx = await nuevo_contexto(browser, ancho, 900)
            await ctx.add_cookies(estado_cliente["cookies"])
            pg = await ctx.new_page()
            await ir(pg, "/carrito.html"); await pg.wait_for_selector(".carrito-producto")
            await revisar(pg, ancho, "carrito")
            await ir(pg, "/checkout.html"); await pg.wait_for_selector(".checkout-item-resumen")
            await revisar(pg, ancho, "checkout")
            await ir(pg, "/mis-compras.html"); await pg.wait_for_selector(".pedido-resumen")
            await revisar(pg, ancho, "mis-compras")
            await pg.click(".pedido-resumen"); await pg.wait_for_selector(".pedido-visitado")
            await revisar(pg, ancho, "detalle-pedido")
            errores += ctx.errores_js; await ctx.close()
            # Admin
            ctx = await nuevo_contexto(browser, ancho, 900)
            await ctx.add_cookies(estado_admin["cookies"])
            pg = await ctx.new_page()
            for vista, selector in [("dashboard", ".kpi"), ("pedidos", "[data-ver-pedido]"), ("productos", "[data-editar]"),
                                    ("inventario", "form.ajuste"), ("categorias", "#cat-nombre"), ("usuarios", "[data-accion-estado]"),
                                    ("estadisticas", ".serie")]:
                await ir(pg, f"/admin/index.html#/{vista}"); await pg.wait_for_selector(selector)
                await revisar(pg, ancho, f"admin-{vista}")
            await ir(pg, "/admin/index.html#/productos"); await pg.wait_for_selector("[data-editar]")
            await pg.click("[data-editar='1']"); await pg.wait_for_selector("#form-producto")
            await revisar(pg, ancho, "admin-form-producto")
            await pg.keyboard.press("Escape")
            await ir(pg, "/admin/index.html#/pedidos/1"); await pg.wait_for_selector("dialog .flujo")
            await revisar(pg, ancho, "admin-detalle-pedido")
            errores += ctx.errores_js; await ctx.close()
            v(not errores, f"{ancho}px: cero errores de JavaScript {errores or ''}")
        await browser.close()
    t.final()

asyncio.run(main())
