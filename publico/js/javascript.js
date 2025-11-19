document.addEventListener("DOMContentLoaded", () => {

  /* --- Módulo 1: Mostrar/Ocultar contraseña --- */
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

  setupPasswordToggle("password", "togglePassword");
  setupPasswordToggle("confirmar", "toggleConfirm");

  /* --- Módulo 2: Modal solicitud y cierre de sesión --- */
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
          modal.style.display = "flex";
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
        await fetch("../../publico/config/logout.php");
      } catch (e) {
        console.error("Error al cerrar sesión:", e);
      }
      window.location.href = "../../index.php";
    });

    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.style.display = "none";
    });
  }

  /* --- Módulo 3: Avatar dinámico --- */
  const usernameInput = document.getElementById("username");
  const avatarLetter = document.getElementById("avatar-letter");
  const avatarUpload = document.getElementById("avatar-upload");
  const avatarImg = document.getElementById("avatar-img");

  if (usernameInput && avatarLetter && !avatarImg) {
    usernameInput.addEventListener("input", () => {
      const first = usernameInput.value.trim().charAt(0).toUpperCase();
      avatarLetter.textContent = first || "U";
    });
  }

  if (avatarUpload) {
    avatarUpload.addEventListener("change", (event) => {
      const file = event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (e) => {
        const src = e.target.result;

        if (avatarLetter) {
          const img = document.createElement("img");
          img.src = src;
          img.alt = "Avatar";
          img.className = "avatar";
          img.id = "avatar-img";
          avatarLetter.replaceWith(img);
        } else if (avatarImg) {
          avatarImg.src = src;
        }
      };

      reader.readAsDataURL(file);
    });
  }

  /* --- Módulo 4: Dropdown del perfil --- */
  const profileBtn = document.getElementById("userProfileBtn");
  const profileDropdown = document.getElementById("profileDropdown");

  if (profileBtn && profileDropdown) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileDropdown.classList.toggle("open");
    });

    document.addEventListener("click", (e) => {
      if (
        !profileBtn.contains(e.target) &&
        !profileDropdown.contains(e.target)
      ) {
        profileDropdown.classList.remove("open");
      }
    });
  }

});