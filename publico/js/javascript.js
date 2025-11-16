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
        modal.style.display = response.ok
          ? "flex"
          : alert("Hubo un problema al enviar la solicitud.");
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

  /* --- Módulo 5: Sidebar y submenús MEJORADO --- */
  function setupSubmenu(btnId, submenuId) {
    const btn = document.getElementById(btnId);
    const submenu = document.getElementById(submenuId);
    if (!btn || !submenu) return;

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      
      const isSidebarCollapsed = document.body.classList.contains("sidebar-collapsed");
      const isCurrentlyOpen = submenu.classList.contains("open");

      // Si el sidebar está COLAPSADO: solo un submenú abierto a la vez
      if (isSidebarCollapsed) {
        // Cerrar todos los otros submenús
        document.querySelectorAll(".submenu").forEach((s) => {
          if (s !== submenu) {
            s.classList.remove("open");
          }
        });
        document.querySelectorAll(".dropdown-btn").forEach((b) => {
          if (b !== btn) {
            b.classList.remove("dropdown-open");
          }
        });

        // Toggle del submenú actual
        submenu.classList.toggle("open");
        btn.classList.toggle("dropdown-open");

        // Posicionar el submenú flotante
        if (!isCurrentlyOpen) {
          const btnRect = btn.getBoundingClientRect();
          submenu.style.top = `${btnRect.top}px`;
        }
      } else {
        // Si el sidebar está EXPANDIDO: permitir múltiples submenús abiertos
        submenu.classList.toggle("open");
        btn.classList.toggle("dropdown-open");
      }
    });
  }

  setupSubmenu("btnProyectos", "submenuProyectos");
  setupSubmenu("btnVerMas", "submenuVerMas");
  setupSubmenu("btnMisAlumnos", "submenuMisAlumnos");

  /* --- Módulo 6: Toggle del Sidebar --- */
  const sidebarToggle = document.getElementById("sidebarToggle");

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      document.body.classList.toggle("sidebar-collapsed");

      // Cerrar todos los submenús al cambiar de modo
      document.querySelectorAll(".submenu").forEach((s) => {
        s.classList.remove("open");
        s.style.top = ""; 
      });
      document.querySelectorAll(".dropdown-btn").forEach((b) => {
        b.classList.remove("dropdown-open");
      });
    });
  }

  /* --- Cerrar submenús al hacer clic fuera --- */
  document.addEventListener("click", (e) => {
    const isSidebarCollapsed = document.body.classList.contains("sidebar-collapsed");
    const clickedDropdown = e.target.closest(".dropdown-btn");
    const clickedSubmenu = e.target.closest(".submenu");

    if (isSidebarCollapsed) {
      if (!clickedDropdown && !clickedSubmenu) {
        document.querySelectorAll(".submenu").forEach((s) => {
          s.classList.remove("open");
          s.style.top = ""; 
        });
        document.querySelectorAll(".dropdown-btn").forEach((b) => {
          b.classList.remove("dropdown-open");
        });
      }
    }
  });

  const sidebar = document.querySelector(".sidebar");
  if (sidebar) {
    sidebar.addEventListener("scroll", () => {
      const isSidebarCollapsed = document.body.classList.contains("sidebar-collapsed");
      
      if (isSidebarCollapsed) {
        document.querySelectorAll(".submenu.open").forEach((submenu) => {
          const btnId = submenu.id.replace("submenu", "btn");
          const btn = document.getElementById(btnId);
          
          if (btn) {
            const btnRect = btn.getBoundingClientRect();
            submenu.style.top = `${btnRect.top}px`;
          }
        });
      }
    });
  }
});