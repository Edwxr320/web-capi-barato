// admin/js/inventario.js -- Bloque 4: existencias, alertas de stock bajo y ajustes de stock.
"use strict";

registrarVista("inventario", {
    titulo: "Inventario",
    filtros: { filtro: "todos", q: "", categoria: "" },

    async mostrar(main, parametro){
        if(parametro === "bajo" || parametro === "sin_stock") this.filtros.filtro = parametro;
        const categorias = await apiGet("admin/categorias.php").then(d => d.categorias).catch(() => []);
        const zonaResumen = el("div", { class: "kpis" });
        const zonaTabla = el("div", {});
        const chips = el("div", { class: "chips", role: "group", "aria-label": "Filtrar por nivel de stock" });
        const buscador = el("input", { type: "search", placeholder: "Buscar producto…", "aria-label": "Buscar producto", value: this.filtros.q });
        const selCategoria = opcionesSelect(el("select", { "aria-label": "Categoría" }),
            [["", "Todas las categorías"], ...categorias.map(c => [c.slug, c.nombre])], this.filtros.categoria);

        const recargar = () => this.cargar(zonaResumen, zonaTabla, chips);
        this.recargar = recargar;
        for(const [valor, texto] of [["todos", "Todos"], ["bajo", "Stock bajo"], ["sin_stock", "Sin stock"]]){
            chips.append(el("button", { type: "button", class: "chip", "aria-pressed": String(this.filtros.filtro === valor), dataset: { filtro: valor },
                onclick: () => { this.filtros.filtro = valor; recargar(); } }, texto));
        }
        buscador.addEventListener("input", debounce(() => { this.filtros.q = buscador.value.trim(); recargar(); }));
        selCategoria.addEventListener("change", () => { this.filtros.categoria = selCategoria.value; recargar(); });

        vaciar(main).append(
            el("h2", {}, "Inventario"),
            el("p", { class: "subtitulo" }, "Existencias de todos los productos y ajustes manuales de stock."),
            zonaResumen,
            el("section", { class: "tarjeta", "aria-label": "Existencias" },
                el("div", { class: "barra" }, chips,
                    el("label", { class: "campo crece" }, "Buscar", buscador),
                    el("label", { class: "campo" }, "Categoría", selCategoria)),
                zonaTabla,
                el("p", { class: "nota", id: "nota-umbral" })));
        await recargar();
    },

    async cargar(zonaResumen, zonaTabla, chips){
        chips.querySelectorAll(".chip").forEach(c => c.setAttribute("aria-pressed", String(c.dataset.filtro === this.filtros.filtro)));
        let datos;
        try{
            datos = await apiGet("admin/inventario.php", this.filtros);
        }catch(err){
            vaciar(zonaTabla).append(el("p", { class: "vacio", role: "alert" }, err.message));
            return;
        }
        const r = datos.resumen;
        vaciar(zonaResumen).append(
            kpi("bi-box-seam", formatearEntero(r.productos), "Productos", `${r.activos} activos`),
            kpi("bi-stack", formatearEntero(r.unidades_totales), "Unidades en stock"),
            kpi("bi-exclamation-triangle", formatearEntero(r.stock_bajo), "Con stock bajo", `stock ≤ ${datos.umbral} (activos)`, r.stock_bajo ? "alerta" : ""),
            kpi("bi-x-octagon", formatearEntero(r.sin_stock), "Sin stock", "productos activos", r.sin_stock ? "alerta" : ""));
        document.querySelector("#nota-umbral").replaceChildren(icono("bi-info-circle"),
            ` Umbral de stock bajo: ${datos.umbral} unidades (se cambia en api/config/config.php, STOCK_BAJO_UMBRAL). `
            + "Las alertas cuentan solo productos activos. No se guarda un historial de movimientos: cada ajuste reemplaza el valor actual.");

        const columnas = [
            { titulo: "Producto" }, { titulo: "Categoría" }, { titulo: "Estado" },
            { titulo: "Stock", clase: "num" }, { titulo: "Nivel" }, { titulo: "Ajustar stock" },
        ];
        const filas = datos.productos.map(p => [
            { nodo: el("div", { class: "producto-celda" }, el("img", { src: rutaImagen(p.imagen_principal), alt: "", loading: "lazy" }), el("span", {}, p.nombre)), claseCelda: "sin-etiqueta" },
            p.categoria.nombre,
            { nodo: insigniaActivo(p.estado) },
            { nodo: el("strong", { "data-stock": p.id_producto }, formatearEntero(p.stock)) },
            { nodo: insigniaStock(p) },
            { nodo: this.formAjuste(p) },
        ]);
        vaciar(zonaTabla).append(crearTabla(columnas, filas,
            this.filtros.filtro === "todos" ? "No hay productos que coincidan." : "Ningún producto en este nivel de stock."));
    },

    formAjuste(p){
        const operacion = opcionesSelect(el("select", { "aria-label": `Operación para ${p.nombre}` }),
            [["sumar", "Sumar"], ["restar", "Restar"], ["fijar", "Fijar en"]], "sumar");
        const cantidad = el("input", { type: "number", min: "0", max: "1000000", step: "1", inputmode: "numeric", value: "1", "aria-label": `Cantidad para ${p.nombre}` });
        const mensaje = el("span", { class: "mensaje-fila", role: "status" });
        const boton = el("button", { type: "submit", class: "btn btn-chico" }, "Aplicar");
        const form = el("form", { class: "ajuste", "data-producto": p.id_producto, novalidate: true }, operacion, cantidad, boton);
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            conBoton(boton, async () => {
                const texto = cantidad.value.trim();
                mensaje.className = "mensaje-fila error";
                if(!/^\d+$/.test(texto)){ mensaje.textContent = "Ingresá un número entero, sin decimales ni signos."; return; }
                if(operacion.value !== "fijar" && Number(texto) < 1){ mensaje.textContent = "La cantidad debe ser al menos 1."; return; }
                try{
                    const r = await apiPost("admin/inventario.php", { id_producto: p.id_producto, operacion: operacion.value, cantidad: Number(texto) });
                    avisar(r.mensaje);
                    actualizarContadoresNav();
                    await this.recargar();
                }catch(err){
                    mensaje.textContent = err.message;
                }
            });
        });
        return el("div", {}, form, mensaje);
    },
});

function kpi(nombreIcono, valor, etiqueta, detalle, clase = ""){
    return el("div", { class: "kpi " + clase }, icono(nombreIcono),
        el("div", {}, el("div", { class: "kpi-valor" }, valor), el("div", { class: "kpi-etiqueta" }, etiqueta),
            detalle ? el("div", { class: "kpi-detalle" }, detalle) : null));
}
