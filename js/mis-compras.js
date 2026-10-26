// mis-compras.js -- Fase 5, Bloque 3.
// Historial de pedidos del usuario logueado. Exige sesión (igual que el
// checkout). El detalle de cada pedido se pide a demanda (no viaja todo de
// entrada), mismo patrón que el detalle de producto en main.js.

const LOCALE = "es-PE";
function formatearPrecio(numero){
    return Number(numero).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function capitalizar(texto){
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}
function formatearFecha(fechaSql){
    const fecha = new Date(fechaSql.replace(" ", "T"));
    return fecha.toLocaleDateString(LOCALE, { day: "2-digit", month: "long", year: "numeric" });
}
// La dirección de envío la escribe el cliente: SIEMPRE se escapa antes de
// insertarla con innerHTML (si no, sería un XSS almacenado, sobre todo cuando
// el admin vea los pedidos en el Bloque 5).
function escaparHtml(texto){
    return String(texto ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}

// Enter o Espacio activan un elemento con role="button" igual que un clic.
function activarConTeclado(elemento, accion){
    elemento.addEventListener("keydown", (e)=>{
        if(e.key === "Enter" || e.key === " "){ e.preventDefault(); accion(); }
    });
}
const ESTADOS_VALIDOS = ["pendiente", "pagado", "enviado", "completado", "cancelado"];
function claseEstado(estado){ return ESTADOS_VALIDOS.includes(estado) ? estado : "desconocido"; }

const listaPedidos = document.querySelector("#lista-pedidos");
const sinPedidos = document.querySelector("#sin-pedidos");
const contenedorPedidoVisitado = document.querySelector("#contenedor-pedido-visitado");
const cantCarrito = document.querySelector("#num-en-carrito");

async function iniciarMisCompras(){
    const sesion = await obtenerSesionActual();
    if(!sesion){
        location.href = "iniciorsesion/iniciarsesion.html?next=mis-compras";
        return;
    }
    actualizarEnlaceCuenta();
    actualizarContadorCarritoHeader();
    await cargarPedidos();
}

async function actualizarContadorCarritoHeader(){
    try{
        const datos = await apiCarritoListar();
        cantCarrito.textContent = datos.items.reduce((acc,i)=> acc + i.cantidad, 0);
    }catch{
        cantCarrito.textContent = "0";
    }
}

async function cargarPedidos(){
    try{
        const datos = await apiPedirJson("api/pedidos/listar.php");
        if(datos.pedidos.length === 0){
            listaPedidos.style.display = "none";
            sinPedidos.style.display = "block";
            return;
        }
        renderizarPedidos(datos.pedidos);
    }catch(err){
        listaPedidos.innerHTML = `<p>No pudimos cargar tus compras: ${escaparHtml(err.message)}</p>`;
    }
}

function renderizarPedidos(pedidos){
    listaPedidos.innerHTML = "";
    pedidos.forEach(pedido=>{
        const div = document.createElement("div");
        div.className = "pedido-resumen";
        div.setAttribute("role", "button");
        div.setAttribute("tabindex", "0");
        div.setAttribute("aria-label", `Ver detalle del pedido ${Number(pedido.id_pedido)}`);
        div.innerHTML = `
            <div class="pedido-resumen-info">
                <span class="pedido-numero">Pedido #${Number(pedido.id_pedido)}</span>
                <span class="pedido-fecha">${escaparHtml(formatearFecha(pedido.fecha_pedido))}</span>
                <span class="pedido-estado pedido-estado-${claseEstado(pedido.estado)}">${escaparHtml(capitalizar(pedido.estado))}</span>
            </div>
            <div class="pedido-resumen-total">S/. ${formatearPrecio(pedido.total)}</div>
        `;
        const abrir = ()=> mostrarDetallePedido(pedido.id_pedido, div);
        div.addEventListener("click", abrir);
        activarConTeclado(div, abrir);
        listaPedidos.append(div);
    });
}

let elementoQueAbrioDetalle = null;
async function mostrarDetallePedido(idPedido, origen){
    elementoQueAbrioDetalle = origen || null;
    try{
        const datos = await apiPedirJson(`api/pedidos/detalle.php?id=${encodeURIComponent(idPedido)}`);
        renderizarDetallePedido(datos.pedido);
    }catch(err){
        // Sin alert(): el aviso queda en la página, junto al pedido.
        let aviso = document.querySelector("#aviso-mis-compras");
        if(!aviso){
            aviso = document.createElement("p");
            aviso.id = "aviso-mis-compras";
            aviso.className = "mensaje-stock";
            aviso.setAttribute("role", "alert");
            listaPedidos.before(aviso);
        }
        aviso.textContent = "No se pudo cargar el detalle de este pedido: " + err.message;
    }
}

function renderizarDetallePedido(pedido){
    const div = document.createElement("div");
    div.className = "pedido-visitado";
    div.innerHTML = `
        <span class="cerrar" role="button" tabindex="0" aria-label="Cerrar"> <i class="bi bi-x-circle" aria-hidden="true"></i> </span>
        <h2>Pedido #${Number(pedido.id_pedido)}</h2>
        <p class="pedido-visitado-meta">
            ${escaparHtml(formatearFecha(pedido.fecha_pedido))} ·
            <span class="pedido-estado pedido-estado-${claseEstado(pedido.estado)}">${escaparHtml(capitalizar(pedido.estado))}</span>
        </p>
        <div class="pedido-visitado-direccion">
            <strong>Dirección de envío:</strong> ${escaparHtml(pedido.direccion_envio)}
        </div>
        <div id="pedido-visitado-items"></div>
        <div class="carrito-resumen-total-compra item-resumen">
            <span>Subtotal</span>
            <span>S/. ${formatearPrecio(pedido.subtotal)}</span>
        </div>
        <div class="carrito-resumen-total-envio item-resumen">
            <span>Envío</span>
            <span>${pedido.costo_envio === 0 ? "Gratis" : "S/. " + formatearPrecio(pedido.costo_envio)}</span>
        </div>
        <div class="carrito-resumen-total-final item-resumen">
            <span>Total</span>
            <span>S/. ${formatearPrecio(pedido.total)}</span>
        </div>
    `;

    contenedorPedidoVisitado.innerHTML = "";
    contenedorPedidoVisitado.append(div);
    contenedorPedidoVisitado.style.display = "flex";
    div.setAttribute("role", "dialog");
    div.setAttribute("aria-modal", "true");
    div.setAttribute("aria-label", `Detalle del pedido ${Number(pedido.id_pedido)}`);
    const btnCerrar = div.querySelector(".cerrar");
    btnCerrar.addEventListener("click", cerrarPedido);
    activarConTeclado(btnCerrar, cerrarPedido);
    btnCerrar.focus();

    const contenedorItems = div.querySelector("#pedido-visitado-items");
    pedido.items.forEach(item=>{
        const itemDiv = document.createElement("div");
        itemDiv.className = "pedido-detalle-item";
        itemDiv.innerHTML = `
            <img src="${escaparHtml(item.imagen_principal)}" alt="${escaparHtml(item.nombre)}">
            <div class="pedido-detalle-item-info">
                <div>${escaparHtml(item.nombre)}</div>
                <div class="cantidad">${Number(item.cantidad)} × S/. ${formatearPrecio(item.precio_unitario)}</div>
            </div>
            <strong>S/. ${formatearPrecio(item.subtotal)}</strong>
        `;
        contenedorItems.append(itemDiv);
    });
}

function cerrarPedido(){
    contenedorPedidoVisitado.innerHTML = "";
    contenedorPedidoVisitado.style.display = "none";
    if(elementoQueAbrioDetalle && document.contains(elementoQueAbrioDetalle)) elementoQueAbrioDetalle.focus();
}
document.addEventListener("keydown", (e)=>{
    if(e.key === "Escape" && contenedorPedidoVisitado.style.display === "flex") cerrarPedido();
});

//--------------------------------------------------
// B U S C A D O R
//--------------------------------------------------
const formBuscador = document.querySelector("#form-buscador");
const inputBuscador = document.querySelector("#input-buscador");
if(formBuscador){
    formBuscador.addEventListener("submit", (e)=>{
        e.preventDefault();
        const consulta = inputBuscador.value.trim();
        if(consulta !== ""){
            location.href = "index.html?buscar=" + encodeURIComponent(consulta);
        }
    });
}

iniciarMisCompras();
