// js/carrito-api.js -- Fase 5, Bloque 2.
// Funciones compartidas entre main.js (agregar al carrito), carrito.js (la
// página del carrito) y sesion.js (login/registro, para la fusión) para
// hablar con el backend de carrito conectado a MySQL. El carrito de invitado
// (localStorage) sigue exactamente igual a la Fase 4; estas funciones solo
// se usan cuando HAY una sesión iniciada.
//
// Este archivo se carga tanto desde la raíz (index.html, carrito.html) como
// desde iniciorsesion/iniciarsesion.html, un nivel más abajo. RAIZ_SITIO
// resuelve las rutas de la API correctamente en los dos casos -- sin esto,
// las llamadas hechas desde la página de login apuntaban a
// "iniciorsesion/api/..." (que no existe) en vez de "api/...".
const RAIZ_SITIO = window.location.pathname.includes("/iniciorsesion/") ? "../" : "./";
const CARRITO_API = `${RAIZ_SITIO}api/carrito`;
const AUTH_API = `${RAIZ_SITIO}api/auth`;

async function obtenerSesionActual(){
    try{
        const resp = await fetch(`${AUTH_API}/sesion.php`);
        const datos = await resp.json();
        return datos.autenticado ? datos.usuario : null;
    }catch{
        return null;
    }
}

async function apiPedirJson(url, opciones){
    const respuesta = await fetch(url, opciones);
    let datos;
    try{
        datos = await respuesta.json();
    }catch{
        throw new Error("El servidor no devolvió una respuesta válida.");
    }
    if(!respuesta.ok){
        const error = new Error(datos.error || `Error del servidor (${respuesta.status}).`);
        error.status = respuesta.status;
        throw error;
    }
    return datos;
}

// Token CSRF: el servidor lo publica en la cookie legible "mw_csrf" (y en sesion.php). Todo POST
// con sesión iniciada lo devuelve en el encabezado X-CSRF-Token; otro sitio no puede leerlo.
function leerTokenCsrf(){
    const par = document.cookie.split("; ").find(c => c.startsWith("mw_csrf="));
    return par ? decodeURIComponent(par.slice("mw_csrf=".length)) : "";
}

async function apiPost(url, cuerpo, reintento = true){
    const opciones = ()=> ({
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": leerTokenCsrf() },
        body: JSON.stringify(cuerpo),
    });
    try{
        return await apiPedirJson(url, opciones());
    }catch(err){
        // Si el token quedó viejo (ej. se inició sesión en otra pestaña), se pide uno nuevo y se
        // reintenta UNA sola vez. Cualquier otro error se propaga tal cual.
        if(err.status === 403 && reintento){
            await obtenerSesionActual();
            return apiPedirJson(url, opciones());
        }
        throw err;
    }
}

async function apiCarritoListar(){
    return apiPedirJson(`${CARRITO_API}/listar.php`);
}
async function apiCarritoAgregar(idProducto, cantidad = 1){
    return apiPost(`${CARRITO_API}/agregar.php`, { id_producto: Number(idProducto), cantidad });
}
async function apiCarritoActualizar(idProducto, cantidad){
    return apiPost(`${CARRITO_API}/actualizar.php`, { id_producto: Number(idProducto), cantidad });
}
async function apiCarritoEliminar(idProducto){
    return apiPost(`${CARRITO_API}/eliminar.php`, { id_producto: Number(idProducto) });
}
async function apiCarritoFusionar(itemsLocales){
    return apiPost(`${CARRITO_API}/fusionar.php`, {
        items: itemsLocales.map(p => ({ id_producto: Number(p.id), cantidad: p.cantidad })),
    });
}

// Convierte un ítem tal como lo devuelve la API (con el producto anidado) a la
// misma forma "plana" que ya usaba el carrito de la Fase 4 (localStorage), para
// poder reusar exactamente el mismo código de render en los dos modos.
function normalizarItemCarritoAPI(item){
    const p = item.producto;
    return {
        id: String(p.id_producto),
        titulo: p.nombre,
        imagen: [p.imagen_principal],
        precio: p.precio_final,
        cantidad: item.cantidad,
        stock: p.stock,
        envio: p.envio_gratis ? "Envío Gratis" : p.costo_envio,
        proveedor: p.proveedor,
    };
}

// Fusiona el carrito de invitado (localStorage) con el de MySQL, una sola vez,
// justo después de un login/registro exitoso. Se llama desde sesion.js.
async function fusionarCarritoInvitadoSiExiste(){
    const local = JSON.parse(localStorage.getItem("productos-en-carrito"));
    if(!local || local.length === 0){
        return;
    }
    try{
        await apiCarritoFusionar(local);
        localStorage.removeItem("productos-en-carrito");
    }catch(err){
        // Si la fusión falla (ej. problema de red), dejamos el carrito local
        // como estaba -- se reintentará la próxima vez que haya sesión, en vez
        // de perder silenciosamente lo que la persona ya había elegido.
        console.error("No se pudo fusionar el carrito de invitado:", err.message);
    }
}

// Reutiliza el mismo enlace "Creá tu cuenta" del header (sin agregar elementos
// nuevos ni tocar el CSS): si hay sesión, muestra el nombre y permite cerrar
// sesión. Es lo mínimo necesario para poder probar login/logout desde el sitio
// real y no solo con curl.
async function actualizarEnlaceCuenta(){
    const enlace = document.querySelector('.info-user a[href*="iniciarsesion.html"]');
    if(!enlace) return;
    // Idempotente: si alguna página la llama dos veces, no se registran dos
    // listeners (antes main.js y carrito.js la llamaban dos veces y "Cerrar
    // sesión" disparaba dos POST de logout).
    if(enlace.dataset.cuentaLista === "1") return;
    enlace.dataset.cuentaLista = "1";
    const sesion = await obtenerSesionActual();
    if(!sesion) return;

    const primerNombre = sesion.nombre.split(" ")[0];
    // Se escribe solo dentro del <span>, para no borrar el icono del enlace.
    (enlace.querySelector(".txt-cuenta") || enlace).textContent = `${primerNombre} (Cerrar sesión)`;
    // Con sesión abierta el botón amarillo "Iniciar sesión" del encabezado
    // sobra: el propio enlace de arriba ya ofrece cerrar sesión.
    const botonAcceso = document.querySelector("#btn-acceso");
    if(botonAcceso) botonAcceso.hidden = true;
    // Solo para administradores: acceso directo al panel. Es navegación, no seguridad: el panel y su API
    // verifican el rol en el servidor.
    if(sesion.rol === "admin" && !document.querySelector(".info-user .enlace-panel")){
        const panel = document.createElement("a");
        panel.href = `${RAIZ_SITIO}admin/index.html`;
        panel.className = "enlace-panel";
        panel.textContent = "Panel";
        enlace.insertAdjacentElement("afterend", panel);
    }
    enlace.href = "#";
    enlace.addEventListener("click", async (e)=>{
        e.preventDefault();
        try{
            await apiPost(`${AUTH_API}/logout.php`, {});
        }finally{
            window.location.reload();
        }
    });
}
