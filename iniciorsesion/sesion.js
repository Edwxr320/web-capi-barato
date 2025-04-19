// sesion.js

// Obtención de los elementos del formulario
const loginButton = document.getElementById('loginButton');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');

// Agregar el event listener al botón de login
loginButton.addEventListener('click', function () {
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    // Verificación si los campos están vacíos
    if (email === '' || password === '') {
        alert('Por favor, completa todos los campos.');
    } else {
        // Redirigir a index.html si ambos campos están completos
        window.location.href = "index.html"; // Redirige a la página principal
    }
});

  