// admin/js/usuarios.js -- clientes y administradores: activar/desactivar y cambiar rol.
// El servidor impide cambiarse a uno mismo y dejar el sistema sin administradores activos.
"use strict";

registrarVista("usuarios", {
    titulo: "Usuarios",
    filtros: { q: "", rol: "", estado: "" },
    async mostrar(main){
        const zona = el("div", {});
        const q = el("input", { type: "search", placeholder: "Nombre o correo", "aria-label": "Buscar usuario", value: this.filtros.q });
        const rol = opcionesSelect(el("select", { "aria-label": "Rol" }), [["", "Todos los roles"], ["cliente", "Clientes"], ["admin", "Administradores"]], this.filtros.rol);
        const estado = opcionesSelect(el("select", { "aria-label": "Estado" }), [["", "Todos los estados"], ["activo", "Activos"], ["inactivo", "Inactivos"]], this.filtros.estado);
        const recargar = () => { this.filtros = { q: q.value.trim(), rol: rol.value, estado: estado.value }; this.cargar(zona); };
        q.addEventListener("input", debounce(recargar)); rol.addEventListener("change", recargar); estado.addEventListener("change", recargar);
        vaciar(main).append(
            el("h2", {}, "Usuarios"),
            el("p", { class: "subtitulo" }, "Una cuenta desactivada pierde la sesión al instante y no puede volver a ingresar. Sus pedidos se conservan."),
            el("section", { class: "tarjeta" },
                el("div", { class: "barra" }, el("label", { class: "campo crece" }, "Buscar", q), el("label", { class: "campo" }, "Rol", rol), el("label", { class: "campo" }, "Estado", estado)),
                zona,
                el("p", { class: "nota" }, icono("bi-info-circle"), " El alta de usuarios y el cambio de contraseña desde el panel no están definidos todavía.")));
        this.zona = zona;
        await this.cargar(zona);
    },

    async cargar(zona){
        let d;
        try{ d = await apiGet("admin/usuarios.php", this.filtros); }
        catch(err){ vaciar(zona).append(el("p", { class: "vacio", role: "alert" }, err.message)); return; }
        const filas = d.usuarios.map(u => {
            const soyYo = u.id_usuario === Panel.usuario.id_usuario;
            return [
                { nodo: el("span", {}, el("strong", {}, u.nombre), soyYo ? el("small", { class: "nota" }, " (vos)") : null, el("br", {}), el("small", { class: "nota" }, u.email)) },
                { nodo: el("span", { class: "insignia" + (u.rol === "admin" ? " insignia-admin" : "") }, u.rol === "admin" ? "Administrador" : "Cliente") },
                { nodo: insigniaActivo(u.estado) },
                formatearFecha(u.fecha_registro, false),
                String(u.pedidos),
                formatearPrecio(u.total_comprado),
                { nodo: soyYo ? el("span", { class: "nota" }, "—") : el("div", { class: "acciones" },
                    el("button", { type: "button", class: "btn btn-chico " + (u.estado === "activo" ? "btn-peligro" : "btn-sec"), "data-accion-estado": u.id_usuario,
                        onclick: () => this.cambiar(u, "estado", u.estado === "activo" ? "inactivo" : "activo") },
                        icono(u.estado === "activo" ? "bi-person-x" : "bi-person-check"), u.estado === "activo" ? "Desactivar" : "Activar"),
                    el("button", { type: "button", class: "btn btn-sec btn-chico", "data-accion-rol": u.id_usuario,
                        onclick: () => this.cambiar(u, "rol", u.rol === "admin" ? "cliente" : "admin") },
                        icono("bi-shield"), u.rol === "admin" ? "Pasar a cliente" : "Hacer administrador")) },
            ];
        });
        vaciar(zona).append(crearTabla(
            [{ titulo: "Usuario" }, { titulo: "Rol" }, { titulo: "Estado" }, { titulo: "Registro" }, { titulo: "Pedidos", clase: "num" }, { titulo: "Comprado", clase: "num" }, { titulo: "Acciones" }],
            filas, "No hay usuarios con ese filtro."));
    },

    async cambiar(u, accion, valor){
        const textos = {
            "estado:inactivo": [`Desactivar a ${u.nombre}`, "No va a poder iniciar sesión y, si tiene una sesión abierta, se cierra en su próxima acción.", "Desactivar", true],
            "estado:activo":   [`Activar a ${u.nombre}`, "Va a poder volver a iniciar sesión.", "Activar", false],
            "rol:admin":       [`Hacer administrador a ${u.nombre}`, "Va a tener acceso completo al panel: productos, pedidos, usuarios e inventario.", "Hacer administrador", true],
            "rol:cliente":     [`Quitar el rol de administrador a ${u.nombre}`, "Pierde el acceso al panel de inmediato.", "Pasar a cliente", false],
        }[`${accion}:${valor}`];
        if(!await confirmar(...textos)) return;
        try{
            const r = await apiPost("admin/usuarios.php", { accion, id_usuario: u.id_usuario, [accion]: valor });
            avisar(r.mensaje); this.cargar(this.zona);
        }catch(err){ avisar(err.message, "error"); }
    },
});
