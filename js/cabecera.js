// cabecera.js -- menú desplegable del encabezado en tablet y celular.
//
// En escritorio el panel (#menu-cabecera) es `display:contents` y sus hijos se
// colocan solos en la rejilla del encabezado, así que este archivo no hace nada
// visible: el botón ☰ está oculto por CSS. A partir de 1000px hacia abajo el
// panel se vuelve una caja plegable y este script la abre y la cierra.
//
// No toca el buscador, las categorías, el carrito ni la sesión: esos siguen
// manejándose en main.js / carrito-api.js como hasta ahora.
"use strict";

(function(){
    const boton = document.querySelector("#btn-menu-cabecera");
    const panel = document.querySelector("#menu-cabecera");
    if(!boton || !panel) return;

    function abrir(si){
        panel.classList.toggle("abierto", si);
        boton.setAttribute("aria-expanded", String(si));
        boton.setAttribute("aria-label", si ? "Cerrar menú" : "Abrir menú");
        boton.querySelector("i").className = si ? "bi bi-x-lg" : "bi bi-list";
    }

    boton.addEventListener("click", () => abrir(!panel.classList.contains("abierto")));

    // Al elegir una categoría o un acceso, el panel se cierra solo.
    panel.addEventListener("click", (e) => {
        if(e.target.closest("a, .boton-categoria")) abrir(false);
    });

    document.addEventListener("keydown", (e) => {
        if(e.key === "Escape" && panel.classList.contains("abierto")){
            abrir(false);
            boton.focus();
        }
    });

    // Si se vuelve a escritorio con el panel abierto, se descarta el estado
    // para que no quede marcado como abierto al regresar a móvil.
    matchMedia("(min-width: 1001px)").addEventListener("change", (e) => {
        if(e.matches) abrir(false);
    });
})();
