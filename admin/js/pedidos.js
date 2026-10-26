// admin/js/pedidos.js -- listado, detalle y cambio controlado de estado de los pedidos.
// Flujo autorizado: pendiente -> pagado -> enviado -> completado. La cancelación no está habilitada
// (sus reglas están pendientes de definición); el servidor lo impide aunque se fuerce la petición.
"use strict";

const ACCION_ESTADO = {
    pagado:     { texto: "Registrar pago", icono: "bi-cash-coin", confirmar: "Vas a registrar que el pago de este pedido se recibió. No hay pasarela de pagos: confirmalo solo si verificaste el pago." },
    enviado:    { texto: "Marcar como enviado", icono: "bi-truck", confirmar: "El pedido pasará a «Enviado»." },
    completado: { texto: "Marcar como completado", icono: "bi-check2-circle", confirmar: "El pedido pasará a «Completado» (entregado). Es un estado final." },
};
const FLUJO = ["pendiente", "pagado", "enviado", "completado"];

registrarVista("pedidos", {
    titulo: "Pedidos",
    filtros: { estado: "", q: "" },

    async mostrar(main, idParaAbrir){
        const zona = el("div", {});
        const chips = el("div", { class: "chips", role: "group", "aria-label": "Filtrar por estado" });
        const buscador = el("input", { type: "search", placeholder: "#número, nombre o correo", "aria-label": "Buscar pedido", value: this.filtros.q });
        buscador.addEventListener("input", debounce(() => { this.filtros.q = buscador.value.trim(); this.cargar(zona, chips); }));
        vaciar(main).append(
            el("h2", {}, "Pedidos"),
            el("p", { class: "subtitulo" }, "Consultá los pedidos y avanzá su estado: pendiente → pagado → enviado → completado."),
            el("section", { class: "tarjeta" },
                el("div", { class: "barra" }, chips, el("label", { class: "campo crece" }, "Buscar", buscador)),
                zona));
        this.zona = zona; this.chips = chips;
        await this.cargar(zona, chips);
        if(idParaAbrir && /^\d+$/.test(idParaAbrir)) this.abrirDetalle(Number(idParaAbrir));
    },

    async cargar(zona, chips){
        let d;
        try{ d = await apiGet("admin/pedidos.php", this.filtros); }
        catch(err){ vaciar(zona).append(el("p", { class: "vacio", role: "alert" }, err.message)); return; }
        const total = Object.values(d.por_estado).reduce((a, b) => a + b, 0);
        vaciar(chips);
        for(const [valor, texto, n] of [["", "Todos", total], ...Object.entries(d.por_estado).map(([e, c]) => [e, NOMBRE_ESTADO[e], c])]){
            chips.append(el("button", { type: "button", class: "chip", "aria-pressed": String(this.filtros.estado === valor),
                onclick: () => { this.filtros.estado = valor; this.cargar(zona, chips); } }, texto, el("span", { class: "n" }, String(n))));
        }
        const filas = d.pedidos.map(p => [
            { nodo: el("strong", {}, `#${p.id_pedido}`) },
            formatearFecha(p.fecha_pedido),
            { nodo: el("span", {}, p.cliente.nombre, el("br", {}), el("small", { class: "nota" }, p.cliente.email)) },
            String(p.unidades),
            formatearPrecio(p.total),
            { nodo: insigniaEstadoPedido(p.estado) },
            { nodo: el("button", { type: "button", class: "btn btn-sec btn-chico", "data-ver-pedido": p.id_pedido,
                onclick: () => this.abrirDetalle(p.id_pedido) }, icono("bi-eye"), "Ver") },
        ]);
        vaciar(zona).append(crearTabla(
            [{ titulo: "Pedido" }, { titulo: "Fecha" }, { titulo: "Cliente" }, { titulo: "Unid.", clase: "num" }, { titulo: "Total", clase: "num" }, { titulo: "Estado" }, { titulo: "" }],
            filas, this.filtros.q || this.filtros.estado ? "No hay pedidos con ese filtro." : "Todavía no hay pedidos."));
    },

    async abrirDetalle(id){
        let p;
        try{ p = (await apiGet("admin/pedidos.php", { id })).pedido; }
        catch(err){ avisar(err.message, "error"); return; }
        const indice = FLUJO.indexOf(p.estado);
        const flujo = el("div", { class: "flujo", "aria-label": "Avance del pedido" },
            FLUJO.map((e, i) => [i > 0 ? icono("bi-chevron-right") : null,
                el("span", { class: "paso" + (i < indice ? " hecho" : i === indice ? " actual" : ""), "aria-current": i === indice ? "step" : null }, NOMBRE_ESTADO[e])]));
        const items = crearTabla([{ titulo: "Producto" }, { titulo: "Cant.", clase: "num" }, { titulo: "Precio unit.", clase: "num" }, { titulo: "Subtotal", clase: "num" }],
            p.items.map(i => [
                { nodo: el("div", { class: "producto-celda" }, el("img", { src: rutaImagen(i.imagen_principal), alt: "" }),
                    el("span", {}, i.nombre, i.estado_producto === "inactivo" ? el("small", { class: "nota" }, " (producto desactivado)") : null)), claseCelda: "sin-etiqueta" },
                String(i.cantidad), formatearPrecio(i.precio_unitario), formatearPrecio(i.subtotal),
            ]), "");
        const acciones = p.estados_siguientes.map(siguiente => {
            const a = ACCION_ESTADO[siguiente];
            return el("button", { type: "button", class: "btn btn-exito", "data-estado": siguiente, onclick: (e) => this.cambiarEstado(p, siguiente, e.currentTarget, dlg) },
                icono(a ? a.icono : "bi-arrow-right"), a ? a.texto : `Pasar a ${siguiente}`);
        });
        const dlg = abrirDialogo({
            titulo: `Pedido #${p.id_pedido}`,
            contenido: [
                flujo,
                el("div", { class: "datos-pedido" },
                    el("div", { class: "caja" }, el("strong", {}, "Cliente"), p.cliente.nombre, el("br", {}), p.cliente.email),
                    el("div", { class: "caja" }, el("strong", {}, "Fecha"), formatearFecha(p.fecha_pedido), el("br", {}), "Estado: ", insigniaEstadoPedido(p.estado)),
                    el("div", { class: "caja", style: "grid-column:1/-1" }, el("strong", {}, "Dirección de envío"), el("span", { class: "direccion-envio" }, p.direccion_envio || "—"))),
                items,
                el("div", { class: "totales" },
                    el("div", {}, el("span", {}, "Subtotal"), el("span", {}, formatearPrecio(p.subtotal))),
                    el("div", {}, el("span", {}, "Envío"), el("span", {}, p.costo_envio === 0 ? "Gratis" : formatearPrecio(p.costo_envio))),
                    el("div", { class: "final" }, el("span", {}, "Total"), el("span", {}, formatearPrecio(p.total)))),
                el("p", { class: "nota", style: "margin-top:14px" }, icono("bi-info-circle"),
                    p.estados_siguientes.length ? " Los precios son los de la compra (no cambian si se edita el producto)."
                        : " Este pedido está en un estado final.",
                    " La cancelación de pedidos no está habilitada: sus reglas están pendientes de definición."),
            ],
            pie: [el("button", { type: "button", class: "btn btn-sec", onclick: () => dlg.close() }, "Cerrar"), ...acciones],
        });
    },

    async cambiarEstado(p, siguiente, boton, dlg){
        const a = ACCION_ESTADO[siguiente];
        if(!await confirmar(`Pedido #${p.id_pedido}: ${a.texto.toLowerCase()}`, a.confirmar, a.texto)) return;
        await conBoton(boton, async () => {
            try{
                const r = await apiPost("admin/pedidos.php", { accion: "estado", id_pedido: p.id_pedido, estado: siguiente });
                avisar(r.mensaje);
                dlg.close();
                actualizarContadoresNav();
                await this.cargar(this.zona, this.chips);
                this.abrirDetalle(p.id_pedido);
            }catch(err){
                avisar(err.message, "error");
            }
        });
    },
});
