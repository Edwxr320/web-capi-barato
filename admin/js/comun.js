// admin/js/comun.js -- utilidades del panel (Bloque 5).
//
// Seguridad: el panel NUNCA usa innerHTML con datos. Todo se construye con el(), que crea nodos y agrega el
// texto como nodos de texto: un nombre de producto como "<img onerror=...>" se ve literal y no se ejecuta.
// La autorización real está en el servidor (requiereRol('admin') en cada endpoint); esto es solo la interfaz.
"use strict";

const Panel = {
    API: "../api",
    LOCALE: "es-PE",
    usuario: null,
    vistas: {},
    vistaActual: null,
};

// ------------------------------------------------------------ DOM seguro
function el(tag, attrs, ...hijos){
    const nodo = document.createElement(tag);
    for(const [clave, valor] of Object.entries(attrs || {})){
        if(valor === null || valor === undefined || valor === false) continue;
        if(clave === "class") nodo.className = valor;
        else if(clave === "dataset") Object.assign(nodo.dataset, valor);
        else if(clave.startsWith("on") && typeof valor === "function") nodo.addEventListener(clave.slice(2), valor);
        else if(clave === "value") nodo.value = valor;
        else if(clave === "checked" || clave === "selected" || clave === "disabled") nodo[clave] = !!valor;
        else if(valor === true) nodo.setAttribute(clave, "");
        else nodo.setAttribute(clave, String(valor));
    }
    for(const hijo of hijos.flat(Infinity)){
        if(hijo === null || hijo === undefined || hijo === false) continue;
        nodo.append(hijo instanceof Node ? hijo : document.createTextNode(String(hijo)));
    }
    return nodo;
}
function icono(nombre){ return el("i", { class: "bi " + nombre, "aria-hidden": "true" }); }
function vaciar(nodo){ while(nodo.firstChild) nodo.removeChild(nodo.firstChild); return nodo; }

// ------------------------------------------------------------ Formatos (es-PE, 2 decimales)
function formatearPrecio(n){
    return "S/. " + Number(n).toLocaleString(Panel.LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatearEntero(n){ return Number(n).toLocaleString(Panel.LOCALE); }
function formatearFecha(sql, conHora = true){
    if(!sql) return "—";
    const f = new Date(String(sql).replace(" ", "T"));
    if(isNaN(f)) return String(sql);
    const opciones = { day: "2-digit", month: "short", year: "numeric" };
    if(conHora){ opciones.hour = "2-digit"; opciones.minute = "2-digit"; }
    return f.toLocaleString(Panel.LOCALE, opciones);
}
function capitalizar(t){ t = String(t || ""); return t.charAt(0).toUpperCase() + t.slice(1); }
const NOMBRE_ESTADO = { pendiente: "Pendiente", pagado: "Pagado", enviado: "Enviado", completado: "Completado", cancelado: "Cancelado" };
function insigniaEstadoPedido(estado){
    return el("span", { class: "insignia estado-" + (NOMBRE_ESTADO[estado] ? estado : "x") }, NOMBRE_ESTADO[estado] || capitalizar(estado));
}
function insigniaStock(p){
    if(p.stock === 0) return el("span", { class: "insignia insignia-sin-stock" }, "Sin stock");
    if(p.stock_bajo) return el("span", { class: "insignia insignia-bajo" }, "Stock bajo");
    return el("span", { class: "insignia insignia-ok" }, "OK");
}
function insigniaActivo(estado){
    return estado === "activo"
        ? el("span", { class: "insignia insignia-ok" }, "Activo")
        : el("span", { class: "insignia insignia-inactivo" }, "Inactivo");
}
/** Ruta de imagen relativa al sitio -> relativa a /admin. Solo acepta rutas "img/…" (vienen validadas del servidor). */
function rutaImagen(ruta){
    return typeof ruta === "string" && /^img\/[A-Za-z0-9_\-\/]+\.(png|jpe?g|webp|gif)$/.test(ruta) ? "../" + ruta : "../img/logo/capi-barato-isotipo.png";
}

// ------------------------------------------------------------ API (con CSRF)
function tokenCsrf(){
    const par = document.cookie.split("; ").find(c => c.startsWith("mw_csrf="));
    return par ? decodeURIComponent(par.slice(8)) : "";
}
async function pedir(ruta, opciones = {}, reintentar = true){
    let respuesta;
    try{
        respuesta = await fetch(`${Panel.API}/${ruta}`, opciones);
    }catch{
        throw new Error("No se pudo conectar con el servidor. Revisá tu conexión.");
    }
    let datos = null;
    try{ datos = await respuesta.json(); }catch{ /* respuesta sin JSON */ }
    if(respuesta.status === 401){
        location.href = "../iniciorsesion/iniciarsesion.html?next=admin";
        throw new Error("Tu sesión terminó. Iniciá sesión de nuevo.");
    }
    if(respuesta.status === 403 && reintentar && opciones.method === "POST"){
        // Token CSRF vencido: se pide uno nuevo y se reintenta una sola vez.
        await fetch(`${Panel.API}/auth/sesion.php`);
        return pedir(ruta, { ...opciones, headers: { ...opciones.headers, "X-CSRF-Token": tokenCsrf() } }, false);
    }
    if(!respuesta.ok){
        const error = new Error((datos && datos.error) || `Error del servidor (${respuesta.status}).`);
        error.status = respuesta.status;
        throw error;
    }
    if(datos === null) throw new Error("El servidor no devolvió una respuesta válida.");
    return datos;
}
function apiGet(ruta, parametros){
    const qs = parametros ? "?" + new URLSearchParams(Object.entries(parametros).filter(([, v]) => v !== "" && v !== null && v !== undefined)) : "";
    return pedir(ruta + (qs === "?" ? "" : qs));
}
function apiPost(ruta, cuerpo){
    return pedir(ruta, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": tokenCsrf() },
        body: JSON.stringify(cuerpo),
    });
}
function apiSubir(ruta, formData){
    return pedir(ruta, { method: "POST", headers: { "X-CSRF-Token": tokenCsrf() }, body: formData });
}

// ------------------------------------------------------------ Avisos, diálogos y confirmaciones
function avisar(texto, tipo = "ok"){
    const caja = document.querySelector("#avisos");
    const aviso = el("div", { class: "aviso" + (tipo === "error" ? " error" : ""), role: tipo === "error" ? "alert" : "status" },
        icono(tipo === "error" ? "bi-exclamation-triangle" : "bi-check-circle"), el("span", {}, texto));
    caja.append(aviso);
    setTimeout(() => aviso.remove(), tipo === "error" ? 7000 : 4000);
}

/** Crea un <dialog> accesible: título, botón × (44 px, aria-label), contenido y pie. Se elimina al cerrarse. */
function abrirDialogo({ titulo, contenido, pie, chico = false, alCerrar }){
    const idTitulo = "dlg-" + Math.random().toString(36).slice(2);
    const dialogo = el("dialog", { class: chico ? "dialogo-chico" : "", "aria-labelledby": idTitulo },
        el("div", { class: "dialogo-cabecera" },
            el("h3", { id: idTitulo }, titulo),
            el("button", { type: "button", class: "btn-cerrar", "aria-label": "Cerrar", onclick: () => dialogo.close() }, icono("bi-x-lg"))),
        el("div", { class: "dialogo-cuerpo" }, contenido),
        pie ? el("div", { class: "dialogo-pie" }, pie) : null);
    const foco = document.activeElement;
    dialogo.addEventListener("close", () => {
        dialogo.remove();                      // no queda ningún overlay bloqueando la página
        if(foco && document.contains(foco)) foco.focus();
        if(alCerrar) alCerrar(dialogo.returnValue);
    });
    document.body.append(dialogo);
    dialogo.showModal();
    return dialogo;
}

/** Confirmación propia (no usa window.confirm). Devuelve una promesa con true/false. */
function confirmar(titulo, mensaje, textoSi = "Confirmar", peligro = false){
    return new Promise(resolver => {
        let respuesta = false;
        const dlg = abrirDialogo({
            titulo, chico: true,
            contenido: el("p", {}, mensaje),
            pie: [
                el("button", { type: "button", class: "btn btn-sec", onclick: () => dlg.close() }, "Cancelar"),
                el("button", { type: "button", class: "btn" + (peligro ? " btn-peligro" : ""), "data-confirmar": "1",
                    onclick: () => { respuesta = true; dlg.close(); } }, textoSi),
            ],
            alCerrar: () => resolver(respuesta),
        });
    });
}

function filaVacia(columnas, texto, nombreIcono = "bi-inbox"){
    return el("tr", {}, el("td", { colspan: columnas, class: "vacio sin-etiqueta" }, icono(nombreIcono), texto));
}
function mensajeError(contenedor, err){
    vaciar(contenedor).append(el("div", { class: "tarjeta vacio", role: "alert" },
        icono("bi-exclamation-triangle"), "No se pudo cargar esta sección: ", el("strong", {}, err.message)));
}
/** Ejecuta fn con el botón deshabilitado (evita doble clic / doble envío). */
async function conBoton(boton, fn){
    if(boton.disabled) return;
    boton.disabled = true;
    try{ return await fn(); } finally { if(document.contains(boton)) boton.disabled = false; }
}
function debounce(fn, ms = 300){
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}
function opcionesSelect(select, opciones, seleccionado){
    vaciar(select);
    for(const [valor, texto] of opciones){
        select.append(el("option", { value: valor, selected: String(valor) === String(seleccionado) }, texto));
    }
    return select;
}
/** Tabla estándar: encabezados + filas. Cada <td> lleva data-etiqueta para la vista de tarjetas en móvil. */
function crearTabla(columnas, filas, textoVacio){
    const tbody = el("tbody", {});
    if(filas.length === 0) tbody.append(filaVacia(columnas.length, textoVacio));
    for(const celdas of filas){
        const tr = el("tr", {});
        celdas.forEach((celda, i) => {
            const col = columnas[i];
            tr.append(el("td", { "data-etiqueta": col.titulo, class: [col.clase, celda && celda.claseCelda].filter(Boolean).join(" ") || null },
                celda && celda.nodo !== undefined ? celda.nodo : celda));
        });
        tbody.append(tr);
    }
    return el("div", { class: "tabla-envoltura" },
        el("table", { class: "tabla tabla-tarjetas" },
            el("thead", {}, el("tr", {}, columnas.map(c => el("th", { scope: "col", class: c.clase || null }, c.titulo)))),
            tbody));
}

/** Actualiza los contadores del menú (pedidos pendientes y alertas de stock). */
async function actualizarContadoresNav(){
    try{
        const d = await apiGet("admin/estadisticas.php", { dias: 1 });
        const p = document.querySelector("#nav-pendientes"), a = document.querySelector("#nav-alertas");
        p.textContent = d.indicadores.pedidos_pendientes; p.classList.toggle("oculto", !d.indicadores.pedidos_pendientes);
        a.textContent = d.indicadores.alertas_stock; a.classList.toggle("oculto", !d.indicadores.alertas_stock);
    }catch{ /* el menú funciona igual sin contadores */ }
}

function registrarVista(nombre, definicion){ Panel.vistas[nombre] = definicion; }
