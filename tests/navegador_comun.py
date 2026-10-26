"""Utilidades compartidas por las pruebas de navegador (Playwright + Chromium).

Variables de entorno:
  BASE_WEB           URL del sitio (default http://127.0.0.1:8099, el servidor que levanta run_all.sh)
  CAPTURAS           carpeta donde se guardan las capturas (default tests/resultados/capturas)
  CHROME_PATH        ejecutable de Chrome/Chromium a usar (opcional)
  CDN_LOCAL          (opcional) carpeta con copias locales de Bootstrap Icons 1.11.1 y Figtree, para entornos
                     SIN acceso a Internet: las peticiones a cdn.jsdelivr.net / fonts.googleapis.com /
                     fonts.gstatic.com se responden desde esa carpeta. No cambia nada de la aplicación:
                     solo hace que las capturas muestren los mismos iconos y tipografía que con Internet.
                     Estructura esperada: CDN_LOCAL/bootstrap-icons/{bootstrap-icons.css,fonts/*}
                     y CDN_LOCAL/figtree/figtree-latin-<peso>-normal.woff2
"""
import os, re
from urllib.parse import urlparse

BASE = os.environ.get("BASE_WEB", "http://127.0.0.1:8099").rstrip("/")
CAPTURAS = os.environ.get("CAPTURAS", os.path.join(os.path.dirname(__file__), "resultados", "capturas"))
os.makedirs(CAPTURAS, exist_ok=True)
CDN_LOCAL = os.environ.get("CDN_LOCAL")
HOST_SITIO = urlparse(BASE).netloc


class Contador:
    def __init__(self):
        self.ok = 0
        self.fallas = 0

    def verificar(self, cond, msg):
        print(("  OK   - " if cond else "  FALLA - ") + msg)
        if cond:
            self.ok += 1
        else:
            self.fallas += 1
        return cond

    def final(self):
        print(f"\nRESULTADO FINAL: {self.ok} pruebas OK, {self.fallas} con FALLA")
        raise SystemExit(1 if self.fallas else 0)


def opciones_lanzamiento():
    opciones = {"args": ["--no-sandbox"]}
    if os.environ.get("CHROME_PATH"):
        opciones["executable_path"] = os.environ["CHROME_PATH"]
    return opciones


async def _servir_cdn_local(route):
    url = route.request.url
    if "bootstrap-icons" in url:
        nombre = url.split("?")[0].split("/font/")[-1]
        ruta = os.path.join(CDN_LOCAL, "bootstrap-icons", nombre)
        tipo = "text/css" if nombre.endswith(".css") else ("font/woff2" if nombre.endswith("woff2") else "font/woff")
        if os.path.exists(ruta):
            return await route.fulfill(path=ruta, content_type=tipo)
    elif "fonts.googleapis.com" in url:
        css = "".join(
            f"@font-face{{font-family:'Figtree';font-style:normal;font-weight:{p};font-display:swap;"
            f"src:url(https://fonts.gstatic.com/local/figtree-latin-{p}-normal.woff2) format('woff2');}}"
            for p in range(300, 1000, 100))
        return await route.fulfill(body=css, content_type="text/css")
    elif "fonts.gstatic.com/local/" in url:
        ruta = os.path.join(CDN_LOCAL, "figtree", url.rsplit("/", 1)[-1])
        if os.path.exists(ruta):
            return await route.fulfill(path=ruta, content_type="font/woff2")
    await route.abort()


async def nuevo_contexto(browser, ancho=1400, alto=1000):
    """Contexto aislado (cookies y localStorage propios) que registra errores de JS, errores de consola
    y peticiones fallidas al propio sitio."""
    contexto = await browser.new_context(viewport={"width": ancho, "height": alto}, locale="es-PE")
    if CDN_LOCAL:
        await contexto.route(re.compile(r"https://(cdn\.jsdelivr\.net|fonts\.googleapis\.com|fonts\.gstatic\.com)/.*"),
                             _servir_cdn_local)
    contexto.errores_js = []
    contexto.errores_consola = []
    contexto.respuestas_5xx = []

    def al_crear_pagina(pagina):
        pagina.on("pageerror", lambda e: contexto.errores_js.append(str(e)))
        pagina.on("console", lambda m: m.type == "error" and contexto.errores_consola.append(m.text))
        pagina.on("response", lambda r: (urlparse(r.url).netloc == HOST_SITIO and r.status >= 500)
                  and contexto.respuestas_5xx.append(f"{r.status} {r.url}"))
    contexto.on("page", al_crear_pagina)
    return contexto


async def ir(pagina, ruta, **kw):
    """Navega sin esperar 'load' completo: varias páginas redirigen por JS apenas cargan, y esperar el
    'load' de la página original produce un falso error de 'navegación interrumpida'."""
    return await pagina.goto(BASE + ruta, wait_until="commit", **kw)


async def captura(pagina, nombre, completa=True):
    ruta = os.path.join(CAPTURAS, nombre)
    await pagina.wait_for_timeout(250)
    await pagina.screenshot(path=ruta, full_page=completa)
    return ruta


async def realmente_visible(pagina, selector):
    """Visible de verdad: existe, no tiene display:none/visibility:hidden en él ni en sus ancestros y ocupa
    espacio en pantalla. Más estricto que comprobar el atributo hidden (que el CSS puede anular)."""
    return await pagina.evaluate("""(sel) => {
        const el = document.querySelector(sel);
        if (!el) return false;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return false;
        for (let n = el; n; n = n.parentElement) {
            const cs = getComputedStyle(n);
            if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
        }
        return true;
    }""", selector)
