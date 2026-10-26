// checkout.js -- Fase 5, Bloque 3.
// El checkout SIEMPRE exige sesión (no se permite compra de invitado). El
// resumen se arma a partir del carrito real en MySQL (api/carrito/listar.php);
// al confirmar, el servidor vuelve a calcular todo desde cero -- este archivo
// nunca manda precios, stock ni totales, solo la dirección de envío.

const LOCALE = "es-PE";
function formatearPrecio(numero){
    return Number(numero).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// Todo texto que venga de la base de datos y se inserte con innerHTML se escapa.
function escaparHtml(texto){
    return String(texto).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}

const contenedorCheckout = document.querySelector("#contenedor-checkout");
const checkoutConfirmacion = document.querySelector("#checkout-confirmacion");
const btnConfirmar = document.querySelector("#btn-confirmar-pedido");
const cantCarrito = document.querySelector("#num-en-carrito");

async function iniciarCheckout(){
    const sesion = await obtenerSesionActual();
    if(!sesion){
        // El checkout exige sesión: mandamos a login y, apenas se autentique,
        // sesion.js lo trae de vuelta acá (parámetro "next").
        location.href = "iniciorsesion/iniciarsesion.html?next=checkout";
        return;
    }

    document.querySelector("#checkout-nombre").textContent = "Nombre: " + sesion.nombre;
    document.querySelector("#checkout-email").textContent = "Correo: " + sesion.email;

    actualizarEnlaceCuenta();
    await cargarResumenCheckout();
}

async function cargarResumenCheckout(){
    try{
        const datos = await apiCarritoListar();

        cantCarrito.textContent = datos.items.reduce((acc,i)=> acc + i.cantidad, 0);

        if(datos.items.length === 0){
            contenedorCheckout.innerHTML = "<p>Tu carrito está vacío. <a href='index.html'>Volvé al catálogo</a> para agregar productos.</p>";
            return;
        }

        const contenedorItems = document.querySelector("#checkout-items-resumen");
        contenedorItems.innerHTML = "";
        datos.items.forEach(item=>{
            const p = item.producto;
            const div = document.createElement("div");
            div.className = "checkout-item-resumen";
            div.innerHTML = `
                <span>${escaparHtml(p.nombre)} × ${item.cantidad}</span>
                <span>S/. ${formatearPrecio(p.precio_final * item.cantidad)}</span>
            `;
            contenedorItems.append(div);
        });

        document.querySelector("#checkout-cant-productos").textContent =
            datos.items.reduce((acc,i)=> acc + i.cantidad, 0);
        document.querySelector("#checkout-subtotal").textContent = "S/. " + formatearPrecio(datos.resumen.subtotal);
        document.querySelector("#checkout-envio").textContent =
            datos.resumen.costo_envio === 0 ? "Gratis" : "S/. " + formatearPrecio(datos.resumen.costo_envio);
        document.querySelector("#checkout-total").textContent = "S/. " + formatearPrecio(datos.resumen.total);
    }catch(err){
        contenedorCheckout.innerHTML = `<p>No pudimos cargar tu carrito: ${escaparHtml(err.message)}</p>`;
    }
}

btnConfirmar.addEventListener("click", async ()=>{
    const direccion = document.querySelector("#checkout-direccion").value.trim();
    const error = document.querySelector("#checkout-error");

    if(direccion.length < 5){
        error.textContent = "Ingresá una dirección de envío válida.";
        return;
    }
    error.textContent = "";
    btnConfirmar.disabled = true;
    btnConfirmar.textContent = "Confirmando...";

    try{
        const respuesta = await apiPost("api/pedidos/crear.php", { direccion_envio: direccion });
        mostrarConfirmacion(respuesta.pedido);
    }catch(err){
        if(err.status === 401){
            // La sesión venció entre que se cargó la página y se confirmó: se vuelve a
            // pedir login y se regresa acá. El carrito sigue intacto en MySQL.
            location.href = "iniciorsesion/iniciarsesion.html?next=checkout";
            return;
        }
        error.textContent = err.message;
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = "Confirmar pedido";
    }
});

function mostrarConfirmacion(pedido){
    // No usamos .hidden: .contenedor-checkout ya tiene su propio "display: grid"
    // en el CSS, con más especificidad que la regla [hidden] del navegador
    // (mismo motivo que en el buscador del catálogo, Fase 5 Bloque 1).
    contenedorCheckout.style.display = "none";
    contenedorCheckout.setAttribute("aria-hidden", "true");
    document.querySelector("#confirmacion-numero").textContent = "#" + pedido.id_pedido;
    checkoutConfirmacion.hidden = false;
    cantCarrito.textContent = "0";
}

//--------------------------------------------------
// B U S C A D O R (redirige al inicio, igual que en carrito.html)
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

iniciarCheckout();
