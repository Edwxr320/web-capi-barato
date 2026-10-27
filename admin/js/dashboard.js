// admin/js/dashboard.js -- indicadores principales calculados en el servidor desde los registros reales.
// El gráfico, las tarjetas y las alertas usan la MISMA regla de negocio que el resto del panel:
// "ventas confirmadas" = pedidos pagado/enviado/completado. Nada se simula: si no hay registros, se ve 0.
"use strict";

const PERIODOS = [["dia", "Por día"], ["semana", "Por semana"], ["mes", "Por mes"]];

registrarVista("dashboard", {
    titulo: "Dashboard",
    periodo: "dia",

    async mostrar(main){
        const d = await apiGet("admin/estadisticas.php", { dias: 7, periodo: this.periodo });
        const k = d.indicadores;

        const ultimos = crearTabla(
            [{ titulo: "Pedido" }, { titulo: "Fecha" }, { titulo: "Cliente" }, { titulo: "Estado" }, { titulo: "Total", clase: "num" }],
            d.ultimos_pedidos.map(p => [
                { nodo: el("a", { href: `#/pedidos/${p.id_pedido}` }, `#${p.id_pedido}`) },
                formatearFecha(p.fecha_pedido), p.cliente, { nodo: insigniaEstadoPedido(p.estado) }, formatearPrecio(p.total),
            ]), "Todavía no hay pedidos.");

        // Cada alerta abre el inventario filtrado por ese producto, listo para ajustar su stock.
        const alertas = el("ul", { class: "barras" },
            d.alertas_stock.length === 0
                ? el("li", { class: "vacio" }, icono("bi-check2-circle"), "No hay productos activos con stock bajo.")
                : d.alertas_stock.map(a => el("li", { class: "barra-fila" },
                    el("span", {}, a.stock === 0
                        ? el("span", { class: "insignia insignia-sin-stock" }, "Sin stock")
                        : el("span", { class: "insignia insignia-bajo" }, `${a.stock} u.`)),
                    el("span", {}, a.nombre),
                    el("a", { href: `#/inventario/buscar=${encodeURIComponent(a.nombre)}`,
                        title: `Ajustar el stock de ${a.nombre}` }, "Reponer"))));

        vaciar(main).append(
            el("h2", {}, "Dashboard"),
            el("p", { class: "subtitulo" }, `Hola, ${Panel.usuario.nombre}. Datos al ${formatearFecha(d.generado)}.`),
            el("div", { class: "kpis" },
                kpi("bi-cash-stack", formatearPrecio(k.ventas_confirmadas), "Ventas confirmadas", `${k.pedidos_confirmados} pedido(s) con pago registrado`, "verde"),
                kpi("bi-hourglass-split", formatearEntero(k.pedidos_pendientes), "Pedidos pendientes", `${formatearPrecio(k.monto_pendiente)} sin pago registrado`, k.pedidos_pendientes ? "alerta" : ""),
                kpi("bi-receipt", formatearPrecio(k.ticket_promedio), "Ticket promedio", "sobre ventas confirmadas"),
                kpi("bi-people", formatearEntero(k.clientes_activos), "Clientes activos", `${k.clientes} registrados`),
                kpi("bi-box-seam", formatearEntero(k.productos_activos), "Productos activos", `${k.productos} en total · ${k.categorias} categorías`),
                kpi("bi-exclamation-triangle", formatearEntero(k.alertas_stock), "Alertas de stock", `stock ≤ ${d.umbral_stock_bajo}`, k.alertas_stock ? "alerta" : "")),
            this.accesosRapidos(),
            this.grafico(d.grafico, main),
            el("div", { class: "grilla-2" },
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-clock-history"), "Últimos pedidos"), ultimos,
                    el("p", { class: "nota", style: null }, el("a", { href: "#/pedidos" }, "Ver todos los pedidos"))),
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-exclamation-triangle"), "Alertas de stock bajo"), alertas,
                    el("p", { class: "nota", style: null }, el("a", { href: "#/inventario/bajo" }, "Ver todo el inventario con stock bajo")))),
            el("p", { class: "nota" }, icono("bi-info-circle"),
                " Ventas confirmadas = pedidos con pago registrado (pagado, enviado o completado). Los pendientes no se suman. ",
                "No hay pasarela de pagos: el estado «pagado» lo registra un administrador."));
    },

    /** Atajos a las tareas más frecuentes. Todas las rutas son vistas que ya existen en el panel. */
    accesosRapidos(){
        const atajos = [
            ["#/productos/nuevo", "bi-plus-circle", "Agregar producto", "Alta de un producto nuevo"],
            ["#/pedidos", "bi-receipt", "Gestionar pedidos", "Avanzar estados y ver detalles"],
            ["#/inventario/bajo", "bi-clipboard-data", "Revisar inventario", "Reponer stock bajo o agotado"],
            ["#/productos", "bi-box-seam", "Todos los productos", "Precios, imágenes y categorías"],
        ];
        return el("section", { class: "tarjeta" },
            el("h3", {}, icono("bi-lightning-charge"), "Accesos rápidos"),
            el("div", { class: "atajos" }, atajos.map(([href, ico, titulo, detalle]) =>
                el("a", { class: "atajo", href },
                    icono(ico),
                    el("span", {}, el("strong", {}, titulo), el("small", {}, detalle))))));
    },

    /** Gráfico de ventas confirmadas con filtro día/semana/mes y comparación con el período anterior. */
    grafico(g, main){
        const chips = el("div", { class: "chips", role: "group", "aria-label": "Agrupar las ventas por" },
            PERIODOS.map(([valor, texto]) => el("button", {
                type: "button", class: "chip", "aria-pressed": String(this.periodo === valor),
                onclick: () => { this.periodo = valor; this.mostrar(main); },
            }, texto)));

        const maximo = Math.max(0.01, ...g.puntos.map(p => p.ventas_confirmadas));
        const barras = el("div", { class: "serie", role: "img", "aria-label": `Ventas confirmadas, ${g.etiqueta.toLowerCase()}` },
            g.puntos.map(p => el("div", { class: "col", title: `${etiquetaPunto(p, g.periodo)}: ${formatearPrecio(p.ventas_confirmadas)} · ${p.pedidos_confirmados} pedido(s) confirmado(s) de ${p.pedidos}` },
                el("div", { class: "b" + (p.ventas_confirmadas ? "" : " cero"),
                    style: `height:${Math.max(1, Math.round(p.ventas_confirmadas / maximo * 100))}%` }))));
        const paso = Math.ceil(g.puntos.length / 7);
        const etiquetas = el("div", { class: "serie-etiquetas", "aria-hidden": "true" },
            g.puntos.map((p, i) => el("span", {}, i % paso === 0 || i === g.puntos.length - 1 ? etiquetaCorta(p, g.periodo) : "")));

        const sinDatos = g.total.pedidos === 0 && g.total.ventas_confirmadas === 0;
        const tabla = el("details", {}, el("summary", { class: "nota" }, "Ver los datos en tabla"),
            crearTabla([{ titulo: "Período" }, { titulo: "Pedidos", clase: "num" }, { titulo: "Confirmados", clase: "num" }, { titulo: "Ventas confirmadas", clase: "num" }],
                g.puntos.slice().reverse().map(p => [etiquetaPunto(p, g.periodo), String(p.pedidos), String(p.pedidos_confirmados), formatearPrecio(p.ventas_confirmadas)]), ""));

        return el("section", { class: "tarjeta" },
            el("div", { class: "barra" },
                el("h3", { style: "margin:0" }, icono("bi-graph-up-arrow"), " Evolución de las ventas"),
                el("div", { class: "empuja" }, chips)),
            el("p", { class: "nota", style: "margin:-6px 0 14px" },
                `${g.etiqueta} · del ${formatearFecha(g.desde, false)} al ${formatearFecha(g.hasta, false)}`),
            el("div", { class: "resumen-periodo" },
                resumenPeriodo("Ingresos confirmados", formatearPrecio(g.total.ventas_confirmadas), g.variacion.ventas, formatearPrecio(g.anterior.ventas_confirmadas)),
                resumenPeriodo("Pedidos confirmados", formatearEntero(g.total.pedidos_confirmados), g.variacion.pedidos_confirmados, formatearEntero(g.anterior.pedidos_confirmados)),
                resumenPeriodo("Pedidos recibidos", formatearEntero(g.total.pedidos), null, formatearEntero(g.anterior.pedidos))),
            sinDatos
                ? el("p", { class: "vacio" }, icono("bi-bar-chart-line"),
                    "Todavía no hay pedidos registrados en este período. El gráfico se llena solo con ventas reales.")
                : [barras, etiquetas, tabla],
            el("p", { class: "nota", style: "margin-top:12px" }, icono("bi-info-circle"),
                ` Período anterior comparado: del ${formatearFecha(g.anterior.desde, false)} al ${formatearFecha(g.anterior.hasta, false)}.`));
    },
});

/** Tarjeta de un total del período, con su variación contra el período anterior. */
function resumenPeriodo(etiqueta, valor, variacion, valorAnterior){
    let comparacion;
    if(variacion === null || variacion === undefined){
        comparacion = el("small", { class: "comparacion" }, `Período anterior: ${valorAnterior}`);
    }else{
        const sube = variacion > 0, baja = variacion < 0;
        comparacion = el("small", { class: "comparacion " + (sube ? "sube" : baja ? "baja" : "") },
            icono(sube ? "bi-arrow-up-right" : baja ? "bi-arrow-down-right" : "bi-dash"),
            `${variacion > 0 ? "+" : ""}${variacion.toLocaleString(Panel.LOCALE)} % vs. ${valorAnterior}`);
    }
    return el("div", { class: "resumen-dato" },
        el("span", { class: "resumen-etiqueta" }, etiqueta),
        el("strong", { class: "resumen-valor" }, valor),
        comparacion);
}

/** "26 oct. 2026" / "Semana del 20 oct. 2026" / "Octubre 2026", según el agrupamiento. */
function etiquetaPunto(p, periodo){
    if(periodo === "dia") return formatearFecha(p.inicio, false);
    if(periodo === "semana") return `Semana del ${formatearFecha(p.inicio, false)}`;
    const f = new Date(p.inicio + "T00:00:00");
    return capitalizar(f.toLocaleString(Panel.LOCALE, { month: "long", year: "numeric" }));
}

/** Etiqueta breve para el eje del gráfico. */
function etiquetaCorta(p, periodo){
    const f = new Date(p.inicio + "T00:00:00");
    if(periodo === "mes") return f.toLocaleString(Panel.LOCALE, { month: "short" });
    return p.inicio.slice(8) + "/" + p.inicio.slice(5, 7);
}
