// Módulo: Control de contraseñas (login/registro)
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

      const isPassword = input.type === "password";
      input.type = isPassword ? "text" : "password";
      icon.src = isPassword ? eyeOpen : eyeClosed;
      icon.alt = isPassword ? "Ocultar contraseña" : "Mostrar contraseña";

      input.focus();

      wrapper.classList.add("ripple");
      setTimeout(() => wrapper.classList.remove("ripple"), 500);
    });
  }

  // para formularios que sí tienen contraseña:
  setupPasswordToggle("password", "togglePassword");
  setupPasswordToggle("confirmar", "toggleConfirm");

  // modal de solicitud
  const form = document.getElementById("formSolicitud");
  const modal = document.getElementById("modal-solicitud");
  const confirmarBtn = document.getElementById("confirmar-btn");

  if (form && modal && confirmarBtn) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const formData = new FormData(form);

      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: formData,
        });

        if (response.ok) {
          modal.style.display = "flex"; // muestra modal centrada
        } else {
          alert("Hubo un problema al enviar la solicitud.");
        }
      } catch (error) {
        console.error(error);
        alert("Error de conexión.");
      }
    });

    confirmarBtn.addEventListener("click", async () => {
      try {
        await fetch("../../publico/config/logout.php"); // Cierra sesión
      } catch (e) {
        console.error("Error al cerrar sesión:", e);
      }
      window.location.href = "../../index.php";
    });

    // Cerrar modal al hacer clic fuera del contenido
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.style.display = "none";
    });
  }

  // Módulo: Avatar dinámico (crear_perfil.php)

  const usernameInput = document.getElementById("username");
  const avatarLetter = document.getElementById("avatar-letter");
  const avatarUpload = document.getElementById("avatar-upload");
  const avatarImg = document.getElementById("avatar-img");

  // Actualiza la letra mientras escribe
  if (usernameInput && avatarLetter && !avatarImg) {
    usernameInput.addEventListener("input", () => {
      const firstLetter = usernameInput.value.trim().charAt(0).toUpperCase();
      avatarLetter.textContent = firstLetter || "U";
    });
  }

  // Muestra vita previa al subir imagen
  if (avatarUpload) {
    avatarUpload.addEventListener("change", (event) => {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          if (avatarLetter) {
            const img = document.createElement("img");
            img.src = e.target.result;
            img.alt = "Avatar";
            img.className = "avatar";
            img.id = "avatar-img";
            avatarLetter.replaceWith(img);
          } else if (avatarImg) {
            avatarImg.src = e.target.result;
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }
  // Dropdown de perfil (header)
  const profileBtn = document.getElementById("userProfileBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener("click", () => {
      const visible = profileDropdown.style.display === "block";
      profileDropdown.style.display = visible ? "none" : "block";
    });

    // Cerrar si se hace clic fuera
    document.addEventListener("click", (e) => {
      if (
        !profileBtn.contains(e.target) &&
        !profileDropdown.contains(e.target)
      ) {
        profileDropdown.style.display = "none";
      }
    });
  }
});
