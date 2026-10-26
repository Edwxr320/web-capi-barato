// admin/js/inicio.js -- arranque del panel: verifica la sesión y enruta por hash (#/inventario, #/pedidos, ...).
"use strict";

async function iniciarPanel(){
    let sesion;
    try{
        const r = await fetch(`${Panel.API}/auth/sesion.php`);
        sesion = await r.json();
    }catch{
        document.querySelector("#sin-permiso").classList.remove("oculto");
        document.querySelector("#sin-permiso h2").textContent = "No se pudo conectar con el servidor";
        return;
    }
    if(!sesion.autenticado){
        location.replace("../iniciorsesion/iniciarsesion.html?next=admin");
        return;
    }
    Panel.usuario = sesion.usuario;
    document.querySelector("#admin-usuario").textContent = sesion.usuario.nombre;
    const btnSalir = document.querySelector("#btn-cerrar-sesion");
    btnSalir.classList.remove("oculto");
    btnSalir.addEventListener("click", () => conBoton(btnSalir, async () => {
        try{ await apiPost("auth/logout.php", {}); }finally{ location.href = "../index.html"; }
    }));

    if(sesion.usuario.rol !== "admin"){
        // Solo interfaz: aunque alguien muestre el panel a la fuerza, cada endpoint responde 403.
        document.querySelector("#sin-permiso").classList.remove("oculto");
        return;
    }
    document.querySelector("#admin-layout").classList.remove("oculto");
    window.addEventListener("hashchange", mostrarVistaDesdeHash);
    mostrarVistaDesdeHash();
    actualizarContadoresNav();
}

async function mostrarVistaDesdeHash(){
    const [nombre, parametro] = location.hash.replace(/^#\/?/, "").split("/");
    const vista = Panel.vistas[nombre] ? nombre : "dashboard";
    Panel.vistaActual = vista;
    document.querySelectorAll(".admin-nav a").forEach(a => {
        if(a.dataset.vista === vista) a.setAttribute("aria-current", "page");
        else a.removeAttribute("aria-current");
    });
    const main = document.querySelector("#admin-main");
    vaciar(main).append(el("p", { class: "cargando" }, "Cargando…"));
    document.title = `${Panel.vistas[vista].titulo} - Panel Capi-Barato`;
    try{
        await Panel.vistas[vista].mostrar(main, parametro ? decodeURIComponent(parametro) : null);
    }catch(err){
        mensajeError(main, err);
    }
}

iniciarPanel();
