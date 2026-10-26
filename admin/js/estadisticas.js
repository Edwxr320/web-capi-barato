// admin/js/estadisticas.js -- estadísticas básicas a partir de los registros reales (api/admin/estadisticas.php).
"use strict";

registrarVista("estadisticas", {
    titulo: "Estadísticas",
    dias: 14,
    async mostrar(main){
        const d = await apiGet("admin/estadisticas.php", { dias: this.dias });
        const selDias = opcionesSelect(el("select", { "aria-label": "Período" }), [["7", "Últimos 7 días"], ["14", "Últimos 14 días"], ["30", "Últimos 30 días"], ["90", "Últimos 90 días"]], this.dias);
        selDias.addEventListener("change", () => { this.dias = Number(selDias.value); this.mostrar(main); });

        const maxEstado = Math.max(1, ...Object.values(d.pedidos_por_estado).map(v => v.pedidos));
        const porEstado = el("div", { class: "barras" }, Object.entries(d.pedidos_por_estado).map(([estado, v]) =>
            filaBarra(insigniaEstadoPedido(estado), v.pedidos / maxEstado, `${v.pedidos} · ${formatearPrecio(v.monto)}`)));

        const maxCat = Math.max(0.01, ...d.ventas_por_categoria.map(c => c.monto));
        const porCategoria = el("div", { class: "barras" }, d.ventas_por_categoria.map(c =>
            filaBarra(c.categoria, c.monto / maxCat, `${formatearPrecio(c.monto)} · ${c.unidades} u.`)));

        const top = crearTabla([{ titulo: "#" }, { titulo: "Producto" }, { titulo: "Unidades", clase: "num" }, { titulo: "Monto", clase: "num" }],
            d.productos_mas_vendidos.map((p, i) => [String(i + 1), p.nombre, formatearEntero(p.unidades), formatearPrecio(p.monto)]),
            "Todavía no hay ventas confirmadas.");

        const maxDia = Math.max(0.01, ...d.serie_diaria.map(s => s.ventas_confirmadas));
        const serie = el("div", { class: "serie", role: "img", "aria-label": `Ventas confirmadas por día, ${this.dias} días` },
            d.serie_diaria.map(s => el("div", { class: "col", title: `${formatearFecha(s.dia, false)}: ${formatearPrecio(s.ventas_confirmadas)} (${s.pedidos} pedidos)` },
                el("div", { class: "b" + (s.ventas_confirmadas ? "" : " cero"), style: `height:${Math.max(1, Math.round(s.ventas_confirmadas / maxDia * 100))}%` }))));
        const paso = Math.ceil(d.serie_diaria.length / 7);
        const etiquetas = el("div", { class: "serie-etiquetas", "aria-hidden": "true" },
            d.serie_diaria.map((s, i) => el("span", {}, i % paso === 0 ? s.dia.slice(8) + "/" + s.dia.slice(5, 7) : "")));
        const tablaSerie = el("details", {}, el("summary", { class: "nota" }, "Ver los datos en tabla"),
            crearTabla([{ titulo: "Día" }, { titulo: "Pedidos", clase: "num" }, { titulo: "Ventas confirmadas", clase: "num" }],
                d.serie_diaria.slice().reverse().map(s => [formatearFecha(s.dia, false), String(s.pedidos), formatearPrecio(s.ventas_confirmadas)]), ""));

        const k = d.indicadores;
        vaciar(main).append(
            el("h2", {}, "Estadísticas"),
            el("p", { class: "subtitulo" }, "Calculadas en el momento a partir de los pedidos registrados."),
            el("div", { class: "kpis" },
                kpi("bi-cash-stack", formatearPrecio(k.ventas_confirmadas), "Ventas confirmadas", null, "verde"),
                kpi("bi-bag-check", formatearEntero(k.pedidos_confirmados), "Pedidos confirmados"),
                kpi("bi-receipt", formatearPrecio(k.ticket_promedio), "Ticket promedio"),
                kpi("bi-bag", formatearEntero(k.pedidos_total), "Pedidos totales")),
            el("section", { class: "tarjeta" },
                el("div", { class: "barra" }, el("h3", { style: "margin:0" }, icono("bi-graph-up"), " Ventas confirmadas por día"), el("label", { class: "campo empuja" }, "Período", selDias)),
                serie, etiquetas, tablaSerie),
            el("div", { class: "grilla-2" },
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-diagram-3"), "Pedidos por estado"), porEstado),
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-tags"), "Ventas por categoría"), porCategoria)),
            el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-trophy"), "Productos más vendidos"), top),
            el("p", { class: "nota" }, icono("bi-info-circle"),
                " Ventas confirmadas: pedidos pagados, enviados o completados, con el precio histórico de cada línea. ",
                "La categoría es la actual del producto."));
    },
});

function filaBarra(etiqueta, proporcion, valor){
    return el("div", { class: "barra-fila" },
        el("span", {}, etiqueta),
        el("div", { class: "pista", "aria-hidden": "true" }, el("div", { class: "relleno", style: `width:${Math.round(Math.max(0, Math.min(1, proporcion)) * 100)}%` })),
        el("span", { class: "valor" }, valor));
}
