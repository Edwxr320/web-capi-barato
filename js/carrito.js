// carrito.js -- Fase 5, Bloque 2.
// Invitado (sin sesión): exactamente el mismo comportamiento de la Fase 4,
// 100% localStorage, sin llamar nunca al backend.
// Usuario autenticado: el carrito vive en MySQL. Cada acción (sumar, restar,
// eliminar) llama a la API y solo actualiza la pantalla si el servidor la
// confirma -- si el servidor rechaza algo (ej. por stock), no se aplica.

let modoAutenticado = false;
let productosEnCarrito = [];

const contenedorCarritoVacio = document.querySelector("#carrito-vacio");
const contenedorCarritoProductos = document.querySelector("#carrito-productos");
const contenedorResumen = document.querySelector("#carrito-resumen");
const cantCarrito = document.querySelector("#num-en-carrito");
const btnComprar = document.querySelector("#btn-comprar");

const LOCALE = "es-PE";
function formatearPrecio(numero){
    return Number(numero).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// Texto de la base de datos o del localStorage (que cualquiera puede editar) se escapa antes de entrar
// en la plantilla HTML.
function escaparHtml(texto){
    return String(texto ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}

function mostrarMensajeStock(idMensaje, texto){
    const mensaje = document.querySelector(idMensaje);
    if(!mensaje) return;
    mensaje.textContent = texto;
    clearTimeout(mensaje._timeoutId);
    mensaje._timeoutId = setTimeout(()=>{ mensaje.textContent = ""; }, 3000);
}

//--------------------------------------------------
// C A R G A   I N I C I A L
//--------------------------------------------------
async function recargarDesdeServidor(){
    try{
        const datos = await apiCarritoListar();
        productosEnCarrito = datos.items.map(normalizarItemCarritoAPI);
    }catch(err){
        console.error("No se pudo cargar el carrito desde el servidor:", err.message);
        productosEnCarrito = [];
    }
}

function actualizarNotaCheckout(){
    const nota = document.querySelector(".carrito-nota-login");
    if(!nota) return;
    nota.textContent = modoAutenticado
        ? ""
        : "Vas a necesitar iniciar sesión para finalizar la compra.";
}

async function iniciarCarrito(){
    const sesion = await obtenerSesionActual();
    modoAutenticado = !!sesion;
    actualizarEnlaceCuenta();

    if(modoAutenticado){
        await recargarDesdeServidor();
    }else{
        productosEnCarrito = JSON.parse(localStorage.getItem("productos-en-carrito")) || [];
    }

    actualizarNotaCheckout();
    cargarProductosCarrito();
}

//--------------------------------------------------
// R E N D E R  (idéntico entre los dos modos: misma forma de datos)
//--------------------------------------------------
function cargarProductosCarrito(){

    if(productosEnCarrito && productosEnCarrito.length > 0){
        contenedorCarritoVacio.classList.add("disabled");
        contenedorCarritoProductos.classList.remove("disabled");
        contenedorResumen.classList.remove("disabled");

        contenedorCarritoProductos.innerHTML = "";

        productosEnCarrito.forEach(producto => {

            const div = document.createElement("div");
            div.classList.add("carrito-producto");
            // data-id en vez de repetir el mismo id="" en tres botones (los id duplicados no son HTML válido).
            const id = escaparHtml(producto.id);
            const titulo = escaparHtml(producto.titulo);
            div.innerHTML = `
                <div class="carrito-producto-proveedor">
                    <span>${escaparHtml(producto.proveedor)}</span>
                </div>
                <div class="carrito-item">
                    <div class="container-producto-img">
                        <img src="${escaparHtml(producto.imagen[0])}" alt="${titulo}" class="carrito-producto-img">
                    </div>

                    <div class="carrito-producto-info">
                        <span class="carrito-producto-titulo">${titulo}</span>
                        <div class="carrito-producto-opciones">
                            <button class="carrito-producto-eliminar" data-id="${id}" aria-label="Eliminar ${titulo} del carrito">Eliminar</button>
                        </div>
                    </div>
                    <div class="container-selector-cantidad">
                        <div class="selector">
                            <button class="restar-cantidad" data-id="${id}" aria-label="Restar una unidad de ${titulo}" ${producto.cantidad <= 1 ? "disabled" : ""}> <i class="bi bi-dash-lg" aria-hidden="true"></i> </button>
                            <span class="cantidad" aria-label="Cantidad">${Number(producto.cantidad)}</span>
                            <button class="sumar-cantidad" data-id="${id}" aria-label="Sumar una unidad de ${titulo}" ${producto.cantidad >= producto.stock ? "disabled" : ""}> <i class="bi bi-plus-lg" aria-hidden="true"></i> </button>
                        </div>
                        <span class="mensaje-stock" id="mensaje-stock-${id}" role="status"></span>
                    </div>
                    <div class="carrito-producto-precio">
                        <p>S/. ${formatearPrecio(producto.precio * producto.cantidad)}</p>
                    </div>
                </div>
                <div class="carrito-producto-mas-info">
                    <span><a href="#">Ver más productos de ${escaparHtml(producto.proveedor)}</a></span>
                </div>
            `

            contenedorCarritoProductos.append(div);
        })

        actualizarNumEnCarrito();

        agregarEventListenerBotonSumarCantidad();
        agregarEventListenerBotonRestarCantidad();

        actualizarResumenCarrito();

    }else{
        contenedorCarritoVacio.classList.remove("disabled");
        contenedorCarritoProductos.classList.add("disabled");
        contenedorResumen.classList.add("disabled");

        actualizarNumEnCarrito();
    }

    agregarEventListenerBotonesEliminar();
}

iniciarCarrito();


function actualizarNumEnCarrito(){
    let nuevaCantEnCarrito = productosEnCarrito.reduce((acc,producto)=> acc + producto.cantidad,0)
    cantCarrito.textContent = nuevaCantEnCarrito;
    return nuevaCantEnCarrito;
}


function agregarEventListenerBotonesEliminar(){
    const botonesEliminar = document.querySelectorAll(".carrito-producto-eliminar");
    botonesEliminar.forEach(boton=>{
        boton.addEventListener("click", eliminarDelCarrito);
    })
}

function agregarEventListenerBotonSumarCantidad(){
    const botonesSumar = document.querySelectorAll(".sumar-cantidad");
    botonesSumar.forEach(boton=>{
        boton.addEventListener("click", sumarCantidad);
    })
}

//--------------------------------------------------
// M U T A C I O N E S  (bifurcan según haya o no sesión)
//--------------------------------------------------
async function sumarCantidad(e){
    const idProducto = e.currentTarget.dataset.id;
    const index = productosEnCarrito.findIndex(producto=> producto.id === idProducto);
    const producto = productosEnCarrito[index];

    if(producto.cantidad >= producto.stock){
        mostrarMensajeStock(`#mensaje-stock-${producto.id}`, `Solo hay ${producto.stock} unidades disponibles.`);
        return;
    }

    const cantidadNueva = producto.cantidad + 1;

    if(modoAutenticado){
        try{
            await apiCarritoActualizar(idProducto, cantidadNueva);
        }catch(err){
            mostrarMensajeStock(`#mensaje-stock-${idProducto}`, err.message);
            return;
        }
    }

    producto.cantidad = cantidadNueva;
    cargarProductosCarrito();
    actualizarResumenCarrito();
    if(!modoAutenticado){
        localStorage.setItem("productos-en-carrito", JSON.stringify(productosEnCarrito));
    }
}

function agregarEventListenerBotonRestarCantidad(){
    const botonesRestar = document.querySelectorAll(".restar-cantidad");
    botonesRestar.forEach(boton=>{
        boton.addEventListener("click", restarCantidad);
    })
}
async function restarCantidad(e){
    const idProducto = e.currentTarget.dataset.id;
    const index = productosEnCarrito.findIndex(producto=> producto.id === idProducto);
    if(productosEnCarrito[index].cantidad <= 1){
        return;
    }
    const cantidadNueva = productosEnCarrito[index].cantidad - 1;

    if(modoAutenticado){
        try{
            await apiCarritoActualizar(idProducto, cantidadNueva);
        }catch(err){
            mostrarMensajeStock(`#mensaje-stock-${idProducto}`, err.message);
            return;
        }
    }

    productosEnCarrito[index].cantidad = cantidadNueva;
    cargarProductosCarrito();
    actualizarResumenCarrito();
    if(!modoAutenticado){
        localStorage.setItem("productos-en-carrito", JSON.stringify(productosEnCarrito));
    }
}

async function eliminarDelCarrito(e){
    const idProducto = e.currentTarget.dataset.id;

    if(modoAutenticado){
        try{
            await apiCarritoEliminar(idProducto);
        }catch(err){
            mostrarMensajeStock(`#mensaje-stock-${idProducto}`, err.message);
            return;
        }
    }

    const index = productosEnCarrito.findIndex(producto => producto.id === idProducto);
    productosEnCarrito.splice(index,1);

    cargarProductosCarrito();
    if(!modoAutenticado){
        localStorage.setItem("productos-en-carrito", JSON.stringify(productosEnCarrito));
    }
}

//Regla de negocio confirmada: el envío se cobra UNA SOLA VEZ por pedido (no por producto ni por unidad).
//Se toma el mayor costo de envío entre los productos con envío pago del carrito; si todos tienen envío
//gratis, el pedido entero tiene envío gratis. La misma regla vive también en el servidor
//(api/includes/carrito_calculo.php) para cuando el carrito está en MySQL.
function calcularCostoEnvioPedido(items){
    const conEnvioPago = items.filter(producto => producto.envio !== "Envío Gratis");
    if(conEnvioPago.length === 0){
        return 0;
    }
    return Math.max(...conEnvioPago.map(producto => producto.envio));
}

function actualizarResumenCarrito(){
    const totalProductos = document.querySelectorAll(".num-total");
    totalProductos[0].textContent = actualizarNumEnCarrito();

    const subtotal = productosEnCarrito.reduce((acc,producto)=> acc + (producto.cantidad * producto.precio),0);
    const totalCompra = document.querySelectorAll(".precio-productos-compra");
    totalCompra[0].textContent = "S/. " + formatearPrecio(subtotal);

    const costoEnvioPedido = calcularCostoEnvioPedido(productosEnCarrito);
    const precioTotalEnvio = document.querySelectorAll(".carrito-resumen-precio-envio");
    precioTotalEnvio[0].textContent = costoEnvioPedido === 0 ? "Gratis" : ("S/. " + formatearPrecio(costoEnvioPedido));

    const precioFinal = document.querySelectorAll(".precio-final");
    const total = subtotal + costoEnvioPedido;
    precioFinal[0].textContent = "S/. " + formatearPrecio(total);
}

//--------------------------------------------------
// C H E C K O U T
//--------------------------------------------------
//El checkout requiere sesión (regla ya aprobada). Sin sesión, redirige a
//login conservando la vuelta al checkout; con sesión, va directo al checkout.
if(btnComprar){
    btnComprar.addEventListener("click", ()=>{
        if(!productosEnCarrito || productosEnCarrito.length === 0) return;
        if(!modoAutenticado){
            location.href = "iniciorsesion/iniciarsesion.html?next=checkout";
            return;
        }
        location.href = "checkout.html";
    });
}

//--------------------------------------------------
// B U S C A D O R (en el carrito no hay catálogo para filtrar: redirige al inicio con la búsqueda aplicada)
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
