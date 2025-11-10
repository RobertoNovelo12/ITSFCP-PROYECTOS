document.addEventListener("DOMContentLoaded", () => {
    const eyeOpen = "./publico/icons/iconoir_eye-solid.webp";
    const eyeClosed = "./publico/icons/solar_eye-closed-broken.webp";

    function setupPasswordToggle(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const wrapper = icon?.parentElement;

        if (!input || !icon || !wrapper) return;

        wrapper.addEventListener("click", (e) => {
            e.preventDefault();
            
            // Toggle password visibility
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            icon.src = isPassword ? eyeOpen : eyeClosed;
            icon.alt = isPassword ? "Ocultar contraseña" : "Mostrar contraseña";

            // Re-enfocar el input
            input.focus();

            // Activar animación de ripple
            wrapper.classList.add("ripple");
            
            setTimeout(() => {
                wrapper.classList.remove("ripple");
            }, 500);
        });
    }

    // Contraseña principal
    setupPasswordToggle("password", "togglePassword");

    // Confirmar contraseña
    setupPasswordToggle("confirmar", "toggleConfirm");
});