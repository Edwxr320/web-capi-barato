// admin/js/categorias.js -- alta, edición y baja de categorías (respetando la clave foránea con productos).
"use strict";

registrarVista("categorias", {
    titulo: "Categorías",
    async mostrar(main){
        const zona = el("div", {});
        const nombre = el("input", { type: "text", id: "cat-nombre", maxlength: "50", required: true, autocomplete: "off" });
        const slug = el("input", { type: "text", id: "cat-slug", maxlength: "50", placeholder: "se genera solo", autocomplete: "off", pattern: "[a-z0-9]+(-[a-z0-9]+)*" });
        const error = el("p", { class: "error-form", role: "alert" });
        const boton = el("button", { type: "submit", class: "btn" }, icono("bi-plus-lg"), "Crear categoría");
        const form = el("form", { class: "barra", novalidate: true },
            el("label", { class: "campo crece", for: "cat-nombre" }, "Nombre", nombre),
            el("label", { class: "campo crece", for: "cat-slug" }, "Identificador en la URL (slug)", slug),
            boton);
        form.addEventListener("submit", e => {
            e.preventDefault();
            conBoton(boton, async () => {
                error.textContent = "";
                if(nombre.value.trim().length < 2){ error.textContent = "El nombre debe tener al menos 2 caracteres."; nombre.focus(); return; }
                try{
                    const r = await apiPost("admin/categorias.php", { accion: "crear", nombre: nombre.value.trim(), slug: slug.value.trim() });
                    avisar(r.mensaje); form.reset(); this.pintar(zona, r.categorias);
                }catch(err){ error.textContent = err.message; }
            });
        });
        vaciar(main).append(
            el("h2", {}, "Categorías"),
            el("p", { class: "subtitulo" }, "Una categoría con productos no se puede eliminar: primero hay que mover sus productos."),
            el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-plus-circle"), "Nueva categoría"), form, error,
                el("p", { class: "nota" }, "El slug es el que usa la tienda en la dirección (?categoria=slug): minúsculas, números y guiones.")),
            el("section", { class: "tarjeta" }, el("h3", {}, icono("bi-tags"), "Categorías existentes"), zona));
        this.pintar(zona, (await apiGet("admin/categorias.php")).categorias);
    },

    pintar(zona, categorias){
        const filas = categorias.map(c => [
            { nodo: el("strong", {}, c.nombre) },
            { nodo: el("code", {}, c.slug) },
            `${c.productos_activos} activos / ${c.productos} total`,
            { nodo: el("div", { class: "acciones" },
                el("button", { type: "button", class: "btn btn-sec btn-chico", onclick: () => this.editar(c, zona) }, icono("bi-pencil"), "Editar"),
                el("button", { type: "button", class: "btn btn-peligro btn-chico", "data-eliminar-categoria": c.slug,
                    title: c.productos ? "Tiene productos: no se puede eliminar" : null,
                    onclick: () => this.eliminar(c, zona) }, icono("bi-trash"), "Eliminar")) },
        ]);
        vaciar(zona).append(crearTabla([{ titulo: "Nombre" }, { titulo: "Slug" }, { titulo: "Productos" }, { titulo: "Acciones" }], filas, "No hay categorías."));
    },

    editar(c, zona){
        const nombre = el("input", { type: "text", id: "editar-cat-nombre", value: c.nombre, maxlength: "50" });
        const slug = el("input", { type: "text", id: "editar-cat-slug", value: c.slug, maxlength: "50" });
        const error = el("p", { class: "error-form", role: "alert" });
        const guardar = el("button", { type: "button", class: "btn" }, "Guardar");
        const dlg = abrirDialogo({
            titulo: `Editar «${c.nombre}»`, chico: true,
            contenido: [el("div", { class: "form-campo" }, el("label", { for: "editar-cat-nombre" }, "Nombre"), nombre),
                el("div", { class: "form-campo", style: "margin-top:12px" }, el("label", { for: "editar-cat-slug" }, "Slug"), slug,
                    el("span", { class: "ayuda" }, "Si lo cambiás, cambian los enlaces de la tienda a esta categoría.")), error],
            pie: [el("button", { type: "button", class: "btn btn-sec", onclick: () => dlg.close() }, "Cancelar"), guardar],
        });
        guardar.addEventListener("click", () => conBoton(guardar, async () => {
            try{
                const r = await apiPost("admin/categorias.php", { accion: "actualizar", id_categoria: c.id_categoria, nombre: nombre.value.trim(), slug: slug.value.trim() });
                avisar(r.mensaje); dlg.close(); this.pintar(zona, r.categorias);
            }catch(err){ error.textContent = err.message; }
        }));
    },

    async eliminar(c, zona){
        if(c.productos > 0){
            avisar(`«${c.nombre}» tiene ${c.productos} producto(s): movelos a otra categoría antes de eliminarla.`, "error");
            return;
        }
        if(!await confirmar("Eliminar categoría", `¿Eliminar «${c.nombre}»? No tiene productos.`, "Eliminar", true)) return;
        try{
            const r = await apiPost("admin/categorias.php", { accion: "eliminar", id_categoria: c.id_categoria });
            avisar(r.mensaje); this.pintar(zona, r.categorias);
        }catch(err){ avisar(err.message, "error"); }
    },
});
