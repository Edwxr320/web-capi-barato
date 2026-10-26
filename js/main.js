// main.js -- Fase 5, Bloque 1: el catálogo, el filtro por categoría, el detalle
// de producto y el buscador ahora vienen de MySQL a través de la API PHP.
// Ya no hay ningún array de productos hardcodeado en este archivo.
//
// El carrito (agregarAlCarrito en adelante) todavía es el de la Fase 4
// (localStorage) -- el Bloque 2 (carrito conectado a MySQL) todavía no fue
// aprobado, así que no se tocó esa parte. Para que carrito.js siga
// funcionando exactamente igual sin modificarlo, normalizarProducto()
// convierte la forma de los datos que devuelve la API a la misma forma
// que ya esperaba el resto del frontend de la Fase 4.

const contenedorProductos = document.querySelector("#contenedor-productos");
const sinResultados = document.querySelector("#sin-resultados");
const tituloSeccion = document.querySelector("#titulo-seccion");
const cantCarrito = document.querySelector("#num-en-carrito");

const LOCALE = "es-PE";
function formatearPrecio(numero){
    return Number(numero).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// Todo texto que viene de la base de datos (nombres, proveedores, rutas) se escapa antes de entrar en
// una plantilla HTML: un nombre como "<img onerror=...>" se muestra literal y nunca se ejecuta.
function escaparHtml(texto){
    return String(texto ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
}
// Teclado: Enter o Espacio activan un elemento con role="button" igual que un clic.
function activarConTeclado(elemento, accion){
    elemento.addEventListener("keydown", (e)=>{
        if(e.key === "Enter" || e.key === " "){
            e.preventDefault();
            accion(e);
        }
    });
}

//--------------------------------------------------
// A P I  --  helpers de acceso a la base de datos vía PHP
//--------------------------------------------------
const API = "api";

async function pedirJson(url){
    const respuesta = await fetch(url);
    let datos;
    try{
        datos = await respuesta.json();
    }catch{
        throw new Error("El servidor no devolvió una respuesta válida.");
    }
    if(!respuesta.ok){
        throw new Error(datos.error || `Error del servidor (${respuesta.status}).`);
    }
    return datos;
}

//Convierte un producto tal como lo devuelve la API a la forma que ya
//esperaba el resto del frontend (carrito incluido) desde la Fase 4.
function normalizarProducto(p){
    return {
        id: String(p.id_producto),
        titulo: p.nombre,
        imagen: p.imagenes && p.imagenes.length ? p.imagenes : [p.imagen_principal],
        categoria: { id: p.categoria.slug, nombre: p.categoria.nombre },
        precio: p.precio_final,
        descuento: p.descuento_porcentaje || 0,
        informacion: p.caracteristicas || [],
        envio: p.envio_gratis ? "Envío Gratis" : p.costo_envio,
        stock: p.stock,
        proveedor: p.proveedor,
    };
}

//--------------------------------------------------
// R E N D E R  del catálogo
//--------------------------------------------------
function armarBloquePrecio(producto, claseFinal, claseOriginal){
    if(producto.descuento){
        const original = producto.precio / (1 - producto.descuento / 100);
        return `
            <span class="${claseOriginal}">S/. ${formatearPrecio(original)}</span>
            <span class="${claseFinal}">S/. ${formatearPrecio(producto.precio)}</span>
        `;
    }
    return `<span class="${claseFinal}">S/. ${formatearPrecio(producto.precio)}</span>`;
}
function armarInfoEnvio(producto){
    if(producto.envio === "Envío Gratis"){
        return { html: "Envío gratis", clase: "" };
    }
    return { html: `Envío: S/. ${formatearPrecio(producto.envio)}`, clase: "pago" };
}

function cargarProductos(listaProductos){
    contenedorProductos.innerHTML = "";

    listaProductos.forEach(producto =>{
        const div = document.createElement("div");
        div.classList.add("producto");

        const envioInfo = armarInfoEnvio(producto);

        div.innerHTML = `
            <img src="${escaparHtml(producto.imagen[0])}" alt="${escaparHtml(producto.titulo)}" class="producto-imagen">
            <div class="producto-detalles">
                <h3 class="producto-titulo">${escaparHtml(producto.titulo)}</h3>
                ${producto.descuento ? `<span class="descuento">${Number(producto.descuento)}% OFF</span>` : ""}
                <div class="producto-precios">
                    ${armarBloquePrecio(producto, "producto-precio", "producto-precio-original")}
                </div>
                <p class="producto-tipo-envio ${envioInfo.clase}">${envioInfo.html}</p>
            </div>
        `;

        div.id = producto.id;
        // Accesible con teclado: la tarjeta se enfoca con Tab y se abre con Enter/Espacio.
        div.setAttribute("tabindex", "0");
        div.setAttribute("role", "button");
        div.setAttribute("aria-label", `Ver detalle de ${producto.titulo}`);
        contenedorProductos.append(div);
    })
}

function mostrarResultados(listaProductos, textoVacio){
    if(listaProductos.length === 0){
        contenedorProductos.style.display = "none";
        sinResultados.style.display = "block";
        if(textoVacio){
            const destino = document.querySelector("#texto-busqueda-vacia");
            if(destino) destino.textContent = textoVacio;
        }
    }else{
        contenedorProductos.style.display = "grid";
        sinResultados.style.display = "none";
        cargarProductos(listaProductos);
    }
}

function mostrarError(mensaje){
    contenedorProductos.style.display = "none";
    sinResultados.style.display = "block";
    const p = sinResultados.querySelector("p");
    p.textContent = "No pudimos conectar con el servidor. ";
    const fuerte = document.createElement("strong");
    fuerte.textContent = mensaje;
    p.append(fuerte);
}
function restaurarMensajeSinResultados(){
    // Se busca el <strong> en cada llamada: antes se guardaba una referencia al cargar la página y,
    // después de recrear el párrafo, quedaba apuntando a un nodo viejo (el término buscado no aparecía).
    const p = sinResultados.querySelector("p");
    p.textContent = "No encontramos productos para ";
    const fuerte = document.createElement("strong");
    fuerte.id = "texto-busqueda-vacia";
    p.append(fuerte);
}

function marcarCategoriaActiva(idCategoria){
    document.querySelectorAll(".nav-menu .boton-categoria").forEach(boton=>{
        boton.classList.toggle("activo", boton.id === idCategoria);
    });
}

//--------------------------------------------------
// C A T Á L O G O  /  C A T E G O R Í A S  (vía API)
//--------------------------------------------------
async function mostrarCatalogo(slugCategoria){
    try{
        restaurarMensajeSinResultados();
        const parametro = slugCategoria && slugCategoria !== "todos" ? `?categoria=${encodeURIComponent(slugCategoria)}` : "";
        const datos = await pedirJson(`${API}/productos/listar.php${parametro}`);
        const productos = datos.productos.map(normalizarProducto);
        mostrarResultados(productos, "en esta categoría por ahora");
        asignarEventListenerProductos();
    }catch(err){
        mostrarError(err.message);
    }
}

// Un solo listener en el menú (delegación): sirve también para los botones que se crean después.
document.querySelector(".nav-menu").addEventListener("click", async (e)=>{
    const boton = e.target.closest(".boton-categoria");
    if(!boton) return;
    limpiarBusqueda();
    marcarCategoriaActiva(boton.id);
    tituloSeccion.textContent = boton.id !== "todos" ? boton.textContent : "Todos los productos";
    await mostrarCatalogo(boton.id);
});

// Las categorías del menú vienen de MySQL: si el administrador crea, renombra o borra una categoría,
// el menú lo refleja. Los botones conservan exactamente el mismo marcado y estilo que en el HTML original
// (que queda como respaldo si la API no responde).
async function cargarCategoriasMenu(){
    try{
        const datos = await pedirJson(`${API}/categorias/listar.php`);
        const nav = document.querySelector(".nav-menu");
        const actuales = [...nav.querySelectorAll(".boton-categoria:not(#todos)")].map(b => `${b.id}|${b.textContent}`).join(",");
        const nuevas = datos.categorias.map(c => `${c.slug}|${c.nombre}`).join(",");
        if(actuales === nuevas) return;
        nav.querySelectorAll(".boton-categoria:not(#todos)").forEach(b => b.remove());
        datos.categorias.forEach(c => {
            const boton = document.createElement("button");
            boton.className = "boton-categoria";
            boton.id = c.slug;
            boton.textContent = c.nombre;
            nav.append(boton);
        });
    }catch{
        // Sin conexión: quedan los botones del HTML.
    }
}

//--------------------------------------------------
// B U S C A D O R  (vía API -- ya no filtra un array local)
//--------------------------------------------------
const formBuscador = document.querySelector("#form-buscador");
const inputBuscador = document.querySelector("#input-buscador");
const btnLimpiarBusqueda = document.querySelector("#btn-limpiar-busqueda");
const btnLimpiarBusquedaVacio = document.querySelector("#btn-limpiar-busqueda-vacio");

async function ejecutarBusqueda(consulta){
    const consultaLimpia = consulta.trim();
    if(consultaLimpia === ""){
        limpiarBusqueda();
        return;
    }
    inputBuscador.value = consultaLimpia;
    btnLimpiarBusqueda.hidden = false;
    marcarCategoriaActiva(null);

    try{
        restaurarMensajeSinResultados();
        const datos = await pedirJson(`${API}/productos/buscar.php?q=${encodeURIComponent(consultaLimpia)}`);
        const productos = datos.productos.map(normalizarProducto);
        tituloSeccion.textContent = `Resultados para "${consultaLimpia}"`;
        mostrarResultados(productos, `"${consultaLimpia}"`);
        asignarEventListenerProductos();
    }catch(err){
        mostrarError(err.message);
    }
}

function limpiarBusqueda(){
    inputBuscador.value = "";
    btnLimpiarBusqueda.hidden = true;
}

if(formBuscador){
    formBuscador.addEventListener("submit", (e)=>{
        e.preventDefault();
        ejecutarBusqueda(inputBuscador.value);
    });
    inputBuscador.addEventListener("input", ()=>{
        btnLimpiarBusqueda.hidden = inputBuscador.value === "";
    });
    btnLimpiarBusqueda.addEventListener("click", ()=>{
        limpiarBusqueda();
        marcarCategoriaActiva("todos");
        tituloSeccion.textContent = "Todos los productos";
        mostrarCatalogo("todos");
    });
}
if(btnLimpiarBusquedaVacio){
    btnLimpiarBusquedaVacio.addEventListener("click", ()=>{
        limpiarBusqueda();
        marcarCategoriaActiva("todos");
        tituloSeccion.textContent = "Todos los productos";
        mostrarCatalogo("todos");
    });
}

//--------------------------------------------------
// D E T A L L E  de producto (vía API)
//--------------------------------------------------
const contenedorProductoVisitado = document.querySelector("#contenedor-producto-visitado");
let productoEnVistaDetalle = null; // producto normalizado actualmente mostrado en el modal
let cantidadEnVistaDetalle = 1;    // unidades elegidas en el selector del modal (1 por defecto)

function asignarEventListenerProductos(){
    const divsProductos = document.querySelectorAll(".producto");

    divsProductos.forEach(productoDiv=>{
        const abrir = async ()=>{
            const idProducto = productoDiv.id;
            try{
                const datos = await pedirJson(`${API}/productos/detalle.php?id=${encodeURIComponent(idProducto)}`);
                mostrarDetalleProducto(normalizarProducto(datos.producto));
            }catch(err){
                // Sin alert(): el aviso queda en la página (y no bloquea el navegador).
                mostrarError("No pudimos cargar el detalle de este producto: " + err.message);
            }
        };
        productoDiv.addEventListener("click", abrir);
        activarConTeclado(productoDiv, abrir);
    })
}

function mostrarDetalleProducto(prod){
    productoEnVistaDetalle = prod;
    cantidadEnVistaDetalle = 1;

    const stock = Number(prod.stock) || 0;
    const sinStock = stock <= 0;
    // Solo se muestran las fotos que el producto realmente tiene cargadas en la base de datos.
    const imagenes = prod.imagen.filter(Boolean);

    const div = document.createElement("div");
    div.classList.add("producto-visitado");
    div.id = prod.id;
    div.setAttribute("role", "dialog");
    div.setAttribute("aria-modal", "true");
    div.setAttribute("aria-labelledby", "titulo-detalle-producto");

    const miniaturas = imagenes.map((src, i) => `
            <img src="${escaparHtml(src)}" alt="${escaparHtml(prod.titulo)} - foto ${i + 1}"
                 class="img-miniatura${i === 0 ? " activa" : ""}" tabindex="0" role="button">`).join("");

    div.innerHTML = `
    <header class="pv-cabecera">
        <div class="pv-marca">
            <img src="img/logo/capi-barato-logo-horizontal.png" alt="Capi-Barato, tienda online" class="pv-logo">
        </div>
        <p class="pv-lema">Calidad y confianza en cada compra</p>
        <span class="cerrar" role="button" tabindex="0" aria-label="Cerrar"> <i class="bi bi-x-lg" aria-hidden="true"></i> </span>
    </header>

    <div class="pv-cuerpo">
        <div class="fotos-galeria">
            <div class="miniaturas">${miniaturas}</div>
            <div class="foto-principal">
                <img src="${escaparHtml(imagenes[0] || "")}" alt="${escaparHtml(prod.titulo)}" id="foto-principal">
            </div>
        </div>

        <div class="info-producto">
            <h2 class="titulo-producto" id="titulo-detalle-producto">${escaparHtml(prod.titulo)}</h2>

            ${prod.descuento ? `<span class="descuento">${Number(prod.descuento)}% OFF</span>` : ""}
            <p class="precio-producto">
                ${armarBloquePrecio(prod, "precio-actual", "precio-original")}
            </p>

            <span class="txt">Lo que tenés que saber de este producto</span>
            <ul id="listado-info">

            </ul>
        </div>

        <div class="info-compra">
            <span class="info-stock${sinStock ? " agotado" : ""}">${sinStock ? "Sin stock" : "Stock disponible"}</span>

            <div class="pv-cantidad">
                <span class="pv-cantidad-rotulo" id="rotulo-cantidad-detalle">Cantidad:</span>
                <div class="selector" role="group" aria-labelledby="rotulo-cantidad-detalle">
                    <button type="button" class="restar-cantidad" aria-label="Restar una unidad" disabled> <i class="bi bi-dash-lg" aria-hidden="true"></i> </button>
                    <span class="cantidad" id="cantidad-detalle" role="status">1</span>
                    <button type="button" class="sumar-cantidad" aria-label="Sumar una unidad" ${stock <= 1 ? "disabled" : ""}> <i class="bi bi-plus-lg" aria-hidden="true"></i> </button>
                </div>
                <span class="stock">(${stock} disponibles)</span>
            </div>

            <button class="btn-agregar-al-carrito" id="${escaparHtml(prod.id)}" ${sinStock ? "disabled" : ""}><i class="bi bi-cart-fill" aria-hidden="true"></i> Agregar al carrito</button>
            <span class="mensaje-stock" id="mensaje-stock-detalle" role="status"></span>

            <span class="vendedor">Vendido por <a href="#">${escaparHtml(prod.proveedor)}</a></span>

            <div class="beneficios">
                <div class="beneficio">
                    <div class="col">
                        <i class="bi bi-arrow-return-left" aria-hidden="true"></i>
                    </div>
                    <div class="col">
                        <a href="#">Devolución Gratis</a> Tenés 30 días desde que lo recibís
                    </div>
                </div>
                <div class="beneficio">
                    <div class="col">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                    </div>
                    <div class="col">
                        <a href="#">Compra protegida</a> recibí el producto que esperabas o te devolvemos tu dinero.
                    </div>
                </div>
                <div class="beneficio">
                    <div class="col">
                        <i class="bi bi-trophy" aria-hidden="true"></i>
                    </div>
                    <div class="col">
                        <a href="#">Mercado Puntos</a> Sumá 900 puntos.
                    </div>
                </div>
            </div>

        </div>
    </div>
    `;

    contenedorProductoVisitado.innerHTML = "";
    contenedorProductoVisitado.append(div);
    contenedorProductoVisitado.style.display = "flex";
    // El catálogo sigue dibujado detrás del modal: el fondo oscuro del overlay lo atenua.
    // Solo se bloquea el desplazamiento de la página para que no se mueva por debajo.
    document.body.classList.add("con-modal-abierto");

    const listadoInfo = div.querySelector("#listado-info")
    prod.informacion.forEach(info =>{
        const li = document.createElement("li");
        li.textContent = info;
        listadoInfo.append(li);
    })

    asignarEventListenerImgMiniatura();
    conectarSelectorCantidad(div, stock);

    // Cierre: clic o teclado en la x, y también la tecla Escape. El foco va al botón de cerrar.
    const btnCerrar = div.querySelector(".cerrar");
    btnCerrar.addEventListener("click", cerrar);
    activarConTeclado(btnCerrar, cerrar);
    btnCerrar.focus();

    const btnAgregar = div.querySelector(".btn-agregar-al-carrito");
    btnAgregar.addEventListener("click", agregarAlCarrito)
}

// Selector de unidades del modal: nunca baja de 1 ni supera el stock real del producto.
function conectarSelectorCantidad(contenedor, stock){
    const btnRestar = contenedor.querySelector(".restar-cantidad");
    const btnSumar = contenedor.querySelector(".sumar-cantidad");
    const salida = contenedor.querySelector("#cantidad-detalle");
    if(!btnRestar || !btnSumar || !salida) return;

    const pintar = ()=>{
        salida.textContent = cantidadEnVistaDetalle;
        btnRestar.disabled = cantidadEnVistaDetalle <= 1;
        btnSumar.disabled = cantidadEnVistaDetalle >= stock;
    };
    btnRestar.addEventListener("click", ()=>{
        if(cantidadEnVistaDetalle > 1){
            cantidadEnVistaDetalle--;
            pintar();
        }
    });
    btnSumar.addEventListener("click", ()=>{
        if(cantidadEnVistaDetalle < stock){
            cantidadEnVistaDetalle++;
            pintar();
        }
    });
    pintar();
}

function cerrar(){
    const idAbierto = productoEnVistaDetalle ? productoEnVistaDetalle.id : null;
    contenedorProductoVisitado.innerHTML = "";
    contenedorProductoVisitado.style.display = "none";
    document.body.classList.remove("con-modal-abierto");
    productoEnVistaDetalle = null;
    cantidadEnVistaDetalle = 1;
    // Devuelve el foco a la tarjeta del producto que se había abierto.
    const tarjeta = idAbierto ? document.getElementById(idAbierto) : null;
    if(tarjeta && tarjeta.classList.contains("producto")) tarjeta.focus();
}
document.addEventListener("keydown", (e)=>{
    if(e.key === "Escape" && productoEnVistaDetalle) cerrar();
});

function asignarEventListenerImgMiniatura(){
    const imgMiniatura = document.querySelectorAll(".img-miniatura");
    const fotoPrincipal = document.querySelector("#foto-principal")

    imgMiniatura.forEach(mini=>{
        const elegir = ()=>{
            fotoPrincipal.src = mini.src;
            // Se marca cual de las miniaturas se esta viendo en grande.
            imgMiniatura.forEach(otra => otra.classList.toggle("activa", otra === mini));
        };
        mini.addEventListener("click", elegir);
        activarConTeclado(mini, elegir);
    });
}

//--------------------------------------------------
// C A R R I T O  (Bloque 2: invitado = localStorage exactamente como en la
// Fase 4; usuario autenticado = MySQL a través de la API de carrito)
//--------------------------------------------------
function mostrarMensajeStock(idMensaje, texto){
    const mensaje = document.querySelector(idMensaje);
    if(!mensaje) return;
    mensaje.textContent = texto;
    clearTimeout(mensaje._timeoutId);
    mensaje._timeoutId = setTimeout(()=>{ mensaje.textContent = ""; }, 3000);
}

async function actualizarContadorCarrito(){
    const sesion = await obtenerSesionActual();
    let total = 0;
    if(sesion){
        try{
            const datos = await apiCarritoListar();
            total = datos.items.reduce((acc, item)=> acc + item.cantidad, 0);
        }catch{
            total = 0;
        }
    }else{
        const local = JSON.parse(localStorage.getItem("productos-en-carrito")) || [];
        total = local.reduce((acc, p)=> acc + p.cantidad, 0);
    }
    cantCarrito.textContent = total;
}

async function agregarAlCarrito(e){
    const idBoton = e.currentTarget.id;
    if(!productoEnVistaDetalle || productoEnVistaDetalle.id !== idBoton) return;

    // El selector del modal decide cuántas unidades se agregan. Por defecto es 1,
    // o sea exactamente el comportamiento que había antes del selector.
    const cantidadElegida = Math.min(Math.max(1, cantidadEnVistaDetalle), Number(productoEnVistaDetalle.stock) || 1);

    const sesion = await obtenerSesionActual();

    if(sesion){
        // Usuario autenticado: MySQL es la fuente de verdad. El servidor valida
        // stock (incluyendo lo que ya tenía en el carrito) antes de guardar.
        try{
            await apiCarritoAgregar(idBoton, cantidadElegida);
            await actualizarContadorCarrito();
            location.href = "./carrito.html";
        }catch(err){
            mostrarMensajeStock("#mensaje-stock-detalle", err.message);
        }
        return;
    }

    // Invitado: misma lógica de la Fase 4, sin cambios.
    const productosEnCarrito = JSON.parse(localStorage.getItem("productos-en-carrito")) || [];
    const yaEnCarrito = productosEnCarrito.some(producto=>producto.id===idBoton);
    const cantidadActual = yaEnCarrito
        ? productosEnCarrito.find(producto=>producto.id===idBoton).cantidad
        : 0;

    if(cantidadActual + cantidadElegida > productoEnVistaDetalle.stock){
        const disponibles = productoEnVistaDetalle.stock - cantidadActual;
        mostrarMensajeStock("#mensaje-stock-detalle", disponibles > 0
            ? `Solo podés agregar ${disponibles} unidad(es) más: ya tenés ${cantidadActual} en el carrito.`
            : `Ya tenés en el carrito todo el stock disponible (${productoEnVistaDetalle.stock}).`);
        return;
    }

    if(yaEnCarrito){
        const index = productosEnCarrito.findIndex(producto=> producto.id === idBoton);
        productosEnCarrito[index].cantidad += cantidadElegida;
    }else{
        const productoParaCarrito = {...productoEnVistaDetalle, cantidad: cantidadElegida};
        productosEnCarrito.push(productoParaCarrito);
    }
    localStorage.setItem("productos-en-carrito", JSON.stringify(productosEnCarrito));
    await actualizarContadorCarrito();
    location.href = "./carrito.html";
}

//--------------------------------------------------
// I N I C I O
//--------------------------------------------------
async function iniciar(){
    await cargarCategoriasMenu();
    marcarCategoriaActiva("todos");
    actualizarContadorCarrito();
    actualizarEnlaceCuenta();

    const parametrosUrl = new URLSearchParams(window.location.search);
    const busquedaDesdeUrl = parametrosUrl.get("buscar");
    if(busquedaDesdeUrl){
        await ejecutarBusqueda(busquedaDesdeUrl);
    }else{
        await mostrarCatalogo("todos");
    }
}
iniciar();
