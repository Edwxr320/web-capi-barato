// admin/js/productos.js -- CRUD de productos: datos, categoría, precio, descuento, stock, envío, imágenes y características.
// Un producto con ventas no se borra: el servidor lo desactiva para conservar el historial de pedidos.
"use strict";

registrarVista("productos", {
    titulo: "Productos",
    filtros: { q: "", categoria: "", estado: "todos" },

    // parametro === "nuevo" (#/productos/nuevo) abre directamente el alta, para el acceso rápido del dashboard.
    async mostrar(main, parametro){
        this.categorias = (await apiGet("admin/categorias.php")).categorias;
        const zona = el("div", {});
        const q = el("input", { type: "search", placeholder: "Nombre o proveedor", "aria-label": "Buscar producto", value: this.filtros.q });
        const cat = opcionesSelect(el("select", { "aria-label": "Categoría" }), [["", "Todas"], ...this.categorias.map(c => [c.slug, c.nombre])], this.filtros.categoria);
        const est = opcionesSelect(el("select", { "aria-label": "Estado" }), [["todos", "Todos"], ["activo", "Activos"], ["inactivo", "Inactivos"]], this.filtros.estado);
        const recargar = () => { this.filtros = { q: q.value.trim(), categoria: cat.value, estado: est.value }; this.cargar(); };
        q.addEventListener("input", debounce(recargar)); cat.addEventListener("change", recargar); est.addEventListener("change", recargar);
        vaciar(main).append(
            el("h2", {}, "Productos"),
            el("p", { class: "subtitulo" }, "El precio final lo calcula siempre el servidor: precio original menos el descuento."),
            el("section", { class: "tarjeta" },
                el("div", { class: "barra" },
                    el("label", { class: "campo crece" }, "Buscar", q), el("label", { class: "campo" }, "Categoría", cat), el("label", { class: "campo" }, "Estado", est),
                    el("button", { type: "button", class: "btn empuja", id: "btn-nuevo-producto", onclick: () => this.abrirFormulario(null) }, icono("bi-plus-lg"), "Nuevo producto")),
                zona));
        this.zona = zona;
        await this.cargar();
        if(parametro === "nuevo") this.abrirFormulario(null);
    },

    async cargar(){
        let d;
        try{ d = await apiGet("admin/productos.php", this.filtros); }
        catch(err){ vaciar(this.zona).append(el("p", { class: "vacio", role: "alert" }, err.message)); return; }
        const filas = d.productos.map(p => [
            { nodo: el("div", { class: "producto-celda" }, el("img", { src: rutaImagen(p.imagen_principal), alt: "", loading: "lazy" }), el("span", {}, p.nombre)), claseCelda: "sin-etiqueta" },
            p.categoria.nombre,
            { nodo: el("span", {}, p.descuento_porcentaje ? el("span", { class: "precio-tachado" }, formatearPrecio(p.precio_original)) : null,
                el("strong", {}, formatearPrecio(p.precio_final)), p.descuento_porcentaje ? el("small", { class: "nota" }, ` −${p.descuento_porcentaje} %`) : null) },
            String(p.stock),
            { nodo: insigniaActivo(p.estado) },
            String(p.unidades_vendidas),
            { nodo: el("div", { class: "acciones" },
                el("button", { type: "button", class: "btn btn-sec btn-chico", "data-editar": p.id_producto, onclick: () => this.abrirFormulario(p.id_producto) }, icono("bi-pencil"), "Editar"),
                el("button", { type: "button", class: "btn btn-sec btn-chico", "data-estado-producto": p.id_producto,
                    onclick: () => this.cambiarEstado(p) }, icono(p.estado === "activo" ? "bi-eye-slash" : "bi-eye"), p.estado === "activo" ? "Desactivar" : "Activar"),
                el("button", { type: "button", class: "btn btn-peligro btn-chico", "data-eliminar": p.id_producto, onclick: () => this.eliminar(p) }, icono("bi-trash"), "Eliminar")) },
        ]);
        vaciar(this.zona).append(crearTabla(
            [{ titulo: "Producto" }, { titulo: "Categoría" }, { titulo: "Precio", clase: "num" }, { titulo: "Stock", clase: "num" }, { titulo: "Estado" }, { titulo: "Vendidos", clase: "num" }, { titulo: "Acciones" }],
            filas, "No hay productos con ese filtro."));
    },

    async cambiarEstado(p){
        const nuevo = p.estado === "activo" ? "inactivo" : "activo";
        if(nuevo === "inactivo" && !await confirmar("Desactivar producto", `«${p.nombre}» dejará de verse en la tienda y no se podrá comprar. Se puede volver a activar.`, "Desactivar")) return;
        try{
            const r = await apiPost("admin/productos.php", { accion: "estado", id_producto: p.id_producto, estado: nuevo });
            avisar(r.mensaje); this.cargar();
        }catch(err){ avisar(err.message, "error"); }
    },

    async eliminar(p){
        const mensaje = p.tiene_ventas
            ? `«${p.nombre}» tiene ${p.unidades_vendidas} unidad(es) vendida(s): para conservar el historial NO se elimina, se desactiva.`
            : `«${p.nombre}» no tiene ventas: se eliminará definitivamente junto con sus imágenes y características.`;
        if(!await confirmar("Eliminar producto", mensaje, p.tiene_ventas ? "Desactivar" : "Eliminar", true)) return;
        try{
            const r = await apiPost("admin/productos.php", { accion: "eliminar", id_producto: p.id_producto });
            avisar(r.mensaje); this.cargar();
        }catch(err){ avisar(err.message, "error"); }
    },

    async abrirFormulario(id){
        let p = null;
        try{
            if(id) p = (await apiGet("admin/productos.php", { id })).producto;
        }catch(err){ avisar(err.message, "error"); return; }
        const disponibles = await apiGet("admin/imagenes.php").then(d => d.imagenes).catch(() => []);
        const v = p || { nombre: "", categoria: { id_categoria: this.categorias[0] && this.categorias[0].id_categoria }, descripcion: "", precio_original: "",
            descuento_porcentaje: null, stock: 0, proveedor: "", envio_gratis: true, costo_envio: null, estado: "activo", imagenes: [], caracteristicas: [] };

        const campo = (idCampo, etiqueta, control, ayuda, ancho) => el("div", { class: "form-campo" + (ancho ? " ancho" : "") },
            el("label", { for: idCampo }, etiqueta), control, ayuda ? el("span", { class: "ayuda" }, ayuda) : null);
        const f = {
            nombre: el("input", { type: "text", id: "p-nombre", value: v.nombre, maxlength: "150", required: true }),
            categoria: opcionesSelect(el("select", { id: "p-categoria" }), this.categorias.map(c => [c.id_categoria, c.nombre]), v.categoria.id_categoria),
            estado: opcionesSelect(el("select", { id: "p-estado" }), [["activo", "Activo (visible en la tienda)"], ["inactivo", "Inactivo (oculto)"]], v.estado),
            precio: el("input", { type: "number", id: "p-precio", min: "0.01", step: "0.01", inputmode: "decimal", value: v.precio_original }),
            descuento: el("input", { type: "number", id: "p-descuento", min: "0", max: "99", step: "1", inputmode: "numeric", value: v.descuento_porcentaje ?? "" }),
            stock: el("input", { type: "number", id: "p-stock", min: "0", max: "1000000", step: "1", inputmode: "numeric", value: v.stock }),
            proveedor: el("input", { type: "text", id: "p-proveedor", value: v.proveedor || "", maxlength: "100" }),
            envioGratis: el("input", { type: "checkbox", id: "p-envio-gratis", checked: v.envio_gratis }),
            costo: el("input", { type: "number", id: "p-costo", min: "0", step: "0.01", inputmode: "decimal", value: v.costo_envio ?? "" }),
            descripcion: el("textarea", { id: "p-descripcion", maxlength: "5000" }, v.descripcion || ""),
            caracteristicas: el("textarea", { id: "p-caracteristicas", rows: "6" }, (v.caracteristicas || []).join("\n")),
        };
        const vistaPrecio = el("p", { class: "vista-precio", "aria-live": "polite" });
        const actualizarVista = () => {
            const precio = Number(f.precio.value), desc = Number(f.descuento.value) || 0;
            vistaPrecio.replaceChildren("Precio final en la tienda: ",
                el("strong", {}, precio > 0 ? formatearPrecio(Math.round(precio * (1 - desc / 100) * 100) / 100) : "—"));
        };
        f.precio.addEventListener("input", actualizarVista); f.descuento.addEventListener("input", actualizarVista); actualizarVista();
        const campoCosto = campo("p-costo", "Costo de envío (S/.)", f.costo, "Se cobra una sola vez por pedido (el mayor del carrito).");
        const sincronizarEnvio = () => { campoCosto.classList.toggle("oculto", f.envioGratis.checked); };
        f.envioGratis.addEventListener("change", sincronizarEnvio); sincronizarEnvio();

        // --- Imágenes: lista ordenable; la primera es la principal ---
        let imagenes = [...(v.imagenes || [])];
        const listaImg = el("div", { class: "lista-imagenes", id: "p-imagenes" });
        const pintarImagenes = () => {
            vaciar(listaImg);
            if(imagenes.length === 0) listaImg.append(el("p", { class: "nota" }, "Agregá al menos una imagen."));
            imagenes.forEach((ruta, i) => listaImg.append(el("div", { class: "item-imagen", "data-ruta": ruta },
                el("img", { src: rutaImagen(ruta), alt: `Imagen ${i + 1}` }),
                el("span", { class: "ruta" }, i === 0 ? el("span", { class: "principal" }, "PRINCIPAL · ") : null, ruta),
                el("button", { type: "button", class: "btn btn-sec btn-chico", "aria-label": `Subir imagen ${i + 1}`, disabled: i === 0,
                    onclick: () => { [imagenes[i - 1], imagenes[i]] = [imagenes[i], imagenes[i - 1]]; pintarImagenes(); } }, icono("bi-arrow-up")),
                el("button", { type: "button", class: "btn btn-sec btn-chico", "aria-label": `Bajar imagen ${i + 1}`, disabled: i === imagenes.length - 1,
                    onclick: () => { [imagenes[i + 1], imagenes[i]] = [imagenes[i], imagenes[i + 1]]; pintarImagenes(); } }, icono("bi-arrow-down")),
                el("button", { type: "button", class: "btn btn-peligro btn-chico", "aria-label": `Quitar imagen ${i + 1}`,
                    onclick: () => { imagenes.splice(i, 1); pintarImagenes(); } }, icono("bi-x-lg")))));
        };
        pintarImagenes();
        const selExistente = opcionesSelect(el("select", { "aria-label": "Imagen existente" }), [["", "Elegir una imagen existente…"], ...disponibles.map(r => [r, r])], "");
        const btnAgregar = el("button", { type: "button", class: "btn btn-sec btn-chico", onclick: () => {
            const r = selExistente.value;
            if(!r) return;
            if(imagenes.includes(r)){ avisar("Esa imagen ya está en la lista.", "error"); return; }
            if(imagenes.length >= 10){ avisar("Máximo 10 imágenes.", "error"); return; }
            imagenes.push(r); pintarImagenes(); selExistente.value = "";
        } }, icono("bi-plus"), "Agregar");
        const inputArchivo = el("input", { type: "file", accept: "image/png,image/jpeg,image/webp", id: "p-subir", class: "solo-lectores" });
        const lblSubir = el("label", { for: "p-subir", class: "btn btn-sec btn-chico", tabindex: "0", role: "button" }, icono("bi-upload"), "Subir imagen");
        lblSubir.addEventListener("keydown", e => { if(e.key === "Enter" || e.key === " "){ e.preventDefault(); inputArchivo.click(); } });
        inputArchivo.addEventListener("change", async () => {
            const archivo = inputArchivo.files[0];
            inputArchivo.value = "";
            if(!archivo) return;
            if(archivo.size > 2 * 1024 * 1024){ avisar("La imagen supera el máximo de 2 MB.", "error"); return; }
            if(imagenes.length >= 10){ avisar("Máximo 10 imágenes.", "error"); return; }
            const fd = new FormData(); fd.append("imagen", archivo);
            try{
                const r = await apiSubir("admin/imagenes.php", fd);
                imagenes.push(r.ruta); pintarImagenes(); avisar("Imagen subida.");
            }catch(err){ avisar(err.message, "error"); }
        });

        const error = el("p", { class: "error-form", role: "alert", id: "p-error" });
        const guardar = el("button", { type: "submit", class: "btn", form: "form-producto", id: "p-guardar" }, icono("bi-check-lg"), p ? "Guardar cambios" : "Crear producto");
        const form = el("form", { id: "form-producto", novalidate: true },
            el("div", { class: "form-grilla" },
                campo("p-nombre", "Nombre", f.nombre, null, true),
                campo("p-categoria", "Categoría", f.categoria),
                campo("p-estado", "Estado", f.estado),
                campo("p-precio", "Precio original (S/.)", f.precio),
                campo("p-descuento", "Descuento (%)", f.descuento, "Vacío o 0 = sin descuento. Máximo 99."),
                el("div", { class: "ancho" }, vistaPrecio),
                campo("p-stock", "Stock", f.stock, "Para ajustes rápidos también podés usar Inventario."),
                campo("p-proveedor", "Proveedor (vendido por)", f.proveedor),
                el("div", { class: "form-campo" }, el("span", { class: "etiqueta" }, "Envío"),
                    el("label", { class: "form-check" }, f.envioGratis, "Envío gratis")),
                campoCosto,
                campo("p-descripcion", "Descripción (opcional)", f.descripcion, null, true),
                campo("p-caracteristicas", "Características", f.caracteristicas, "Una por línea (se muestran en «Lo que tenés que saber de este producto»).", true),
                el("div", { class: "form-campo ancho" }, el("span", { class: "etiqueta" }, "Imágenes (la primera es la principal)"), listaImg,
                    el("div", { class: "imagenes-acciones" }, selExistente, btnAgregar, lblSubir, inputArchivo),
                    el("span", { class: "ayuda" }, "PNG, JPG o WebP, hasta 2 MB. Máximo 10 imágenes."))),
            error);

        const dlg = abrirDialogo({
            titulo: p ? `Editar «${p.nombre}»` : "Nuevo producto",
            contenido: form,
            pie: [el("button", { type: "button", class: "btn btn-sec", onclick: () => dlg.close() }, "Cancelar"), guardar],
        });

        form.addEventListener("submit", e => {
            e.preventDefault();
            conBoton(guardar, async () => {
                error.textContent = "";
                const problema = validarProducto(f, imagenes);
                if(problema){ error.textContent = problema; return; }
                const cuerpo = {
                    accion: p ? "actualizar" : "crear",
                    id_producto: p ? p.id_producto : undefined,
                    nombre: f.nombre.value.trim(),
                    id_categoria: Number(f.categoria.value),
                    estado: f.estado.value,
                    precio_original: f.precio.value.trim(),
                    descuento_porcentaje: f.descuento.value.trim() === "" ? null : Number(f.descuento.value),
                    stock: Number(f.stock.value),
                    proveedor: f.proveedor.value.trim(),
                    envio_gratis: f.envioGratis.checked,
                    costo_envio: f.envioGratis.checked ? null : f.costo.value.trim(),
                    descripcion: f.descripcion.value.trim(),
                    imagenes,
                    caracteristicas: f.caracteristicas.value.split("\n").map(t => t.trim()).filter(Boolean),
                };
                try{
                    const r = await apiPost("admin/productos.php", cuerpo);
                    avisar(r.mensaje); dlg.close(); this.cargar();
                }catch(err){ error.textContent = err.message; }
            });
        });
    },
});

/** Validación en el navegador (comodidad). La validación que cuenta es la del servidor. */
function validarProducto(f, imagenes){
    const nombre = f.nombre.value.trim();
    if(nombre.length < 3 || nombre.length > 150) return "El nombre debe tener entre 3 y 150 caracteres.";
    if(!/^\d{1,8}(\.\d{1,2})?$/.test(f.precio.value.trim()) || Number(f.precio.value) <= 0) return "Ingresá un precio mayor a 0, con hasta 2 decimales.";
    const desc = f.descuento.value.trim();
    if(desc !== "" && (!/^\d{1,2}$/.test(desc))) return "El descuento debe ser un número entero entre 0 y 99.";
    if(!/^\d{1,7}$/.test(f.stock.value.trim()) || Number(f.stock.value) > 1000000) return "El stock debe ser un número entero entre 0 y 1.000.000.";
    if(!f.envioGratis.checked && !/^\d{1,8}(\.\d{1,2})?$/.test(f.costo.value.trim())) return "Ingresá el costo de envío (o marcá envío gratis).";
    if(imagenes.length === 0) return "Agregá al menos una imagen.";
    if(f.caracteristicas.value.split("\n").some(t => t.trim().length > 255)) return "Cada característica puede tener hasta 255 caracteres.";
    return "";
}
