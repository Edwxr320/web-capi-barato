// sesion.js -- Fase 5: login y registro ya están conectados de verdad a
// api/auth/login.php y api/auth/registro.php (antes solo validaban formato y
// redirigían). Al conseguir sesión, se fusiona el carrito de invitado
// (localStorage) con el carrito de MySQL antes de volver al inicio.
//
// El rediseño de la pantalla (dos paneles) no cambió nada de eso: solo cambian
// los selectores de los botones que alternan entre los dos formularios y se
// agregan el ojo de "ver contraseña" y el "Recordarme" (que recuerda el correo
// en este navegador, nunca la contraseña).

const tabLogin = document.querySelector("#tab-login");
const tabRegistro = document.querySelector("#tab-registro");
const formLogin = document.querySelector("#form-login");
const formRegistro = document.querySelector("#form-registro");
const marcaSub = document.querySelector("#marca-sub");

const SUBTITULOS = {
    login: "Ingresa a tu cuenta para continuar",
    registro: "Crea tu cuenta para empezar a comprar",
};

function mostrarFormulario(cual){
    const esLogin = cual === "login";
    formLogin.classList.toggle("activo", esLogin);
    formRegistro.classList.toggle("activo", !esLogin);
    // El enlace de arriba a la derecha ofrece siempre la pantalla contraria.
    tabRegistro.hidden = !esLogin;
    tabLogin.hidden = esLogin;
    document.querySelector(".panel-top-texto").textContent = esLogin
        ? "¿Aún no tienes cuenta?"
        : "¿Ya tienes cuenta?";
    marcaSub.textContent = SUBTITULOS[esLogin ? "login" : "registro"];
    const primero = (esLogin ? formLogin : formRegistro).querySelector("input");
    if(primero && !primero.value) primero.focus();
}

tabLogin.addEventListener("click", ()=> mostrarFormulario("login"));
tabRegistro.addEventListener("click", ()=> mostrarFormulario("registro"));

const btnIrRegistro = document.querySelector("#btn-ir-registro");
const btnIrLogin = document.querySelector("#btn-ir-login");
btnIrRegistro.addEventListener("click", ()=> mostrarFormulario("registro"));
btnIrLogin.addEventListener("click", ()=> mostrarFormulario("login"));

// Ojo de "mostrar/ocultar contraseña" (solo cambia el type del input).
document.querySelectorAll(".ver-password").forEach(boton => {
    boton.addEventListener("click", ()=>{
        const campo = document.querySelector(`#${boton.dataset.objetivo}`);
        const mostrar = campo.type === "password";
        campo.type = mostrar ? "text" : "password";
        boton.setAttribute("aria-pressed", String(mostrar));
        boton.setAttribute("aria-label", mostrar ? "Ocultar contraseña" : "Mostrar contraseña");
        boton.querySelector("i").className = mostrar ? "bi bi-eye" : "bi bi-eye-slash";
        campo.focus();
    });
});

// "Recordarme": guarda SOLO el correo en este navegador para no tener que
// volver a escribirlo. La contraseña y la sesión siguen manejándose en el
// servidor (cookie de sesión de PHP), igual que antes.
const inputEmailLogin = document.querySelector("#login-email");
const checkRecordar = document.querySelector("#login-recordar");
const CORREO_RECORDADO = "capibarato-correo-recordado";

const correoGuardado = localStorage.getItem(CORREO_RECORDADO);
if(correoGuardado){
    inputEmailLogin.value = correoGuardado;
    checkRecordar.checked = true;
}

function guardarCorreoRecordado(email){
    if(checkRecordar.checked){
        localStorage.setItem(CORREO_RECORDADO, email);
    }else{
        localStorage.removeItem(CORREO_RECORDADO);
    }
}

// La recuperación de contraseña todavía no está implementada en el backend
// (ver api/admin/usuarios.php): el enlace lo dice en vez de llevar a una
// página que no existe.
document.querySelector("#link-olvidaste").addEventListener("click", (e)=>{
    e.preventDefault();
    document.querySelector("#error-login").textContent =
        "La recuperación de contraseña todavía no está disponible. Contactá al administrador de la tienda.";
});

function esEmailValido(email){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Adónde volver tras loguearse/registrarse. Lista blanca fija (no se acepta
// una URL arbitraria del parámetro, para evitar un open-redirect).
function destinoTrasAutenticar(){
    const destinos = { checkout: "../checkout.html", "mis-compras": "../mis-compras.html", admin: "../admin/index.html" };
    const next = new URLSearchParams(window.location.search).get("next");
    return destinos[next] || "../index.html";
}

async function llamarApiAuth(url, cuerpo){
    const respuesta = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(cuerpo),
    });
    const datos = await respuesta.json();
    if(!respuesta.ok){
        throw new Error(datos.error || "Ocurrió un error inesperado.");
    }
    return datos;
}

// El botón tiene un icono al lado del texto: se cambia solo el <span>, para no
// borrar la flecha mientras dice "Ingresando...".
function textoBoton(boton, texto){
    boton.querySelector(".btn-texto").textContent = texto;
}

//--- LOGIN ---
formLogin.addEventListener("submit", async (e)=>{
    e.preventDefault();
    const email = inputEmailLogin.value.trim();
    const password = document.querySelector("#login-password").value;
    const error = document.querySelector("#error-login");
    const boton = formLogin.querySelector("button[type=submit]");

    if(!esEmailValido(email)){
        error.textContent = "Ingresá un correo electrónico válido.";
        return;
    }
    if(password.length < 6){
        error.textContent = "La contraseña debe tener al menos 6 caracteres.";
        return;
    }
    error.textContent = "";
    boton.disabled = true;
    textoBoton(boton, "Ingresando...");

    try{
        await llamarApiAuth("../api/auth/login.php", { email, password });
        guardarCorreoRecordado(email);
        await fusionarCarritoInvitadoSiExiste();
        window.location.href = destinoTrasAutenticar();
    }catch(err){
        error.textContent = err.message;
        boton.disabled = false;
        textoBoton(boton, "Iniciar sesión");
    }
});

//--- REGISTRO ---
formRegistro.addEventListener("submit", async (e)=>{
    e.preventDefault();
    const nombre = document.querySelector("#registro-nombre").value.trim();
    const email = document.querySelector("#registro-email").value.trim();
    const password = document.querySelector("#registro-password").value;
    const confirmar = document.querySelector("#registro-confirmar").value;
    const error = document.querySelector("#error-registro");
    const boton = formRegistro.querySelector("button[type=submit]");

    if(nombre.length < 2){
        error.textContent = "Ingresá tu nombre completo.";
        return;
    }
    if(!esEmailValido(email)){
        error.textContent = "Ingresá un correo electrónico válido.";
        return;
    }
    if(password.length < 6){
        error.textContent = "La contraseña debe tener al menos 6 caracteres.";
        return;
    }
    if(password !== confirmar){
        error.textContent = "Las contraseñas no coinciden.";
        return;
    }
    error.textContent = "";
    boton.disabled = true;
    textoBoton(boton, "Creando cuenta...");

    try{
        await llamarApiAuth("../api/auth/registro.php", { nombre, email, password });
        await fusionarCarritoInvitadoSiExiste();
        window.location.href = destinoTrasAutenticar();
    }catch(err){
        error.textContent = err.message;
        boton.disabled = false;
        textoBoton(boton, "Crear cuenta");
    }
});
