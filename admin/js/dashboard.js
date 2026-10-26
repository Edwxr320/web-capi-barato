// admin/js/dashboard.js -- indicadores principales calculados en el servidor desde los registros reales.
"use strict";

registrarVista("dashboard", {
    titulo: "Dashboard",
    async mostrar(main){
        const d = await apiGet("admin/estadisticas.php", { dias: 7 });
        const k = d.indicadores;
        const ultimos = crearTabla(
            [{ titulo: "Pedido" }, { titulo: "Fecha" }, { titulo: "Cliente" }, { titulo: "Estado" }, { titulo: "Total", clase: "num" }],
            d.ultimos_pedidos.map(p => [
                { nodo: el("a", { href: `#/pedidos/${p.id_pedido}` }, `#${p.id_pedido}`) },
                formatearFecha(p.fecha_pedido), p.cliente, { nodo: insigniaEstadoPedido(p.estado) }, formatearPrecio(p.total),
            ]), "Todavía no hay pedidos.");
        const alertas = el("ul", { class: "barras" },
            d.alertas_stock.length === 0
                ? el("li", { class: "vacio" }, icono("bi-check2-circle"), "No hay productos activos con stock bajo.")
                : d.alertas_stock.map(a => el("li", { class: "barra-fila" },
                    el("span", { style: null }, a.stock === 0 ? el("span", { class: "insignia insignia-sin-stock" }, "Sin stock") : el("span", { class: "insignia insignia-bajo" }, `${a.stock} u.`)),
                    el("span", {}, a.nombre),
                    el("a", { href: "#/inventario/bajo" }, "Reponer"))));

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
            el("div", { class: "grilla-2" },
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-clock-history"), "Últimos pedidos"), ultimos,
                    el("p", { class: "nota", style: null }, el("a", { href: "#/pedidos" }, "Ver todos los pedidos"))),
                el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-exclamation-triangle"), "Alertas de stock bajo"), alertas)),
            el("p", { class: "nota" }, icono("bi-info-circle"),
                " Ventas confirmadas = pedidos con pago registrado (pagado, enviado o completado). Los pendientes no se suman. ",
                "No hay pasarela de pagos: el estado «pagado» lo registra un administrador."));
    },
});
