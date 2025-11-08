document.addEventListener("DOMContentLoaded", () => {
    const eyeOpen = "./publico/icons/iconoir_eye-solid.webp";
    const eyeClosed = "./publico/icons/solar_eye-closed-broken.webp";

    function setupPasswordToggle(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (!input || !icon) return;

        icon.addEventListener("click", () => {
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            icon.src = isPassword ? eyeOpen : eyeClosed;
            icon.alt = isPassword ? "Ocultar contraseña" : "Mostrar contraseña";
        });
    }

    // 🔸 Contraseña principal
    setupPasswordToggle("password", "togglePassword");

    // 🔸 Confirmar contraseña
    setupPasswordToggle("confirmar", "toggleConfirm");
});