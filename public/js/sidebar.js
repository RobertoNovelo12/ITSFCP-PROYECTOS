document.addEventListener("DOMContentLoaded", () => {
  const toggleBtn = document.getElementById("sidebarToggle");
  const mobileMenuToggle = document.getElementById("mobileMenuToggle");
  const sidebar = document.querySelector(".sidebar");
  const sidebarOverlay = document.getElementById("sidebarOverlay");
  const body = document.body;
  const html = document.documentElement;

  // ============================
  // DETECTAR SI ES MÓVIL
  // ============================
  function isMobile() {
    return window.innerWidth <= 768;
  }

  // ============================
  // RESTAURAR ESTADO DEL SIDEBAR (SOLO DESKTOP)
  // ============================
  const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");

  setTimeout(() => {
    html.classList.remove("sidebar-collapsed-initial");
  }, 10);

  if (!isMobile() && sidebarCollapsed === "true") {
    sidebar.classList.add("collapsed");
    body.classList.add("sidebar-collapsed");

    // Cerrar todos los submenús si está colapsado
    document
      .querySelectorAll(".submenu")
      .forEach((sm) => sm.classList.remove("open"));
    document
      .querySelectorAll(".dropdown-btn")
      .forEach((btn) => btn.classList.remove("dropdown-open"));
  } else if (!isMobile()) {
    sidebar.classList.remove("collapsed");
    body.classList.remove("sidebar-collapsed");
  }

  // ============================
  // BOTÓN COLAPSAR/EXPANDIR (DESKTOP)
  // ============================
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      // Solo funciona en desktop
      if (!isMobile()) {
        const isCollapsed = sidebar.classList.toggle("collapsed");
        body.classList.toggle("sidebar-collapsed", isCollapsed);
        localStorage.setItem("sidebar-collapsed", isCollapsed.toString());

        // Si se colapsa, cerrar todos los submenús
        if (isCollapsed) {
          document
            .querySelectorAll(".submenu")
            .forEach((sm) => sm.classList.remove("open"));
          document
            .querySelectorAll(".dropdown-btn")
            .forEach((btn) => btn.classList.remove("dropdown-open"));
          localStorage.removeItem("sidebar-open-submenus");
        } else {
          // Al expandir, restaurar submenús guardados
          restaurarSubmenus();
        }
      }
    });
  }

  // ============================
  // MENÚ HAMBURGUESA (MÓVIL)
  // ============================
  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener("click", function () {
      if (isMobile()) {
        this.classList.toggle("active");
        sidebar.classList.toggle("mobile-open");
        sidebarOverlay.classList.toggle("active");
        body.style.overflow = sidebar.classList.contains("mobile-open")
          ? "hidden"
          : "";
      }
    });
  }

  // ============================
  // CERRAR SIDEBAR MÓVIL
  // ============================
  function closeMobileSidebar() {
    if (mobileMenuToggle) {
      mobileMenuToggle.classList.remove("active");
    }
    sidebar.classList.remove("mobile-open");
    sidebarOverlay.classList.remove("active");
    body.style.overflow = "";
  }

  // Cerrar al hacer click en el overlay
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", function () {
      closeMobileSidebar();
    });
  }

  // Cerrar al hacer click en un enlace del sidebar (en móvil)
  const sidebarLinks = sidebar.querySelectorAll(".menu-item a, .sub-item");
  sidebarLinks.forEach((link) => {
    link.addEventListener("click", function () {
      if (isMobile()) {
        closeMobileSidebar();
      }
    });
  });

  // Cerrar con tecla ESC (en móvil)
  document.addEventListener("keydown", function (e) {
    if (
      e.key === "Escape" &&
      isMobile() &&
      sidebar.classList.contains("mobile-open")
    ) {
      closeMobileSidebar();
    }
  });

  // ============================
  // MANEJAR CAMBIOS DE TAMAÑO
  // ============================
  let resizeTimer;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      // Si cambiamos a desktop, cerrar el menú móvil
      if (!isMobile()) {
        closeMobileSidebar();

        // Restaurar estado desktop si existe
        const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");
        if (sidebarCollapsed === "true") {
          sidebar.classList.add("collapsed");
          body.classList.add("sidebar-collapsed");
        }
      } else {
        // Si cambiamos a móvil, quitar clases de desktop
        sidebar.classList.remove("collapsed");
        body.classList.remove("sidebar-collapsed");
      }
    }, 250);
  });

  // ============================
  // RESTAURAR SUBMENÚS GUARDADOS
  // ============================
  function restaurarSubmenus() {
    if (body.classList.contains("sidebar-collapsed") || isMobile()) {
      return;
    }

    const openSubmenusStr = localStorage.getItem("sidebar-open-submenus");

    if (openSubmenusStr) {
      try {
        const openSubmenus = JSON.parse(openSubmenusStr);
        openSubmenus.forEach((id) => {
          const submenuBtn = document.querySelector(
            `.dropdown-btn[data-id="${id}"]`
          );
          if (submenuBtn) {
            submenuBtn.classList.add("dropdown-open");
            const submenu = submenuBtn.nextElementSibling;
            if (submenu && submenu.classList.contains("submenu")) {
              submenu.classList.add("open");
            }
          }
        });
      } catch (e) {
        localStorage.removeItem("sidebar-open-submenus");
      }
    }
  }

  // Solo restaurar si NO está colapsado y NO es móvil
  if (!body.classList.contains("sidebar-collapsed") && !isMobile()) {
    restaurarSubmenus();
  }

  // ============================
  // FUNCIÓN PARA GUARDAR SUBMENÚS
  // ============================
  function guardarSubmenus() {
    if (body.classList.contains("sidebar-collapsed") || isMobile()) {
      localStorage.removeItem("sidebar-open-submenus");
      return;
    }

    const openBtns = Array.from(
      document.querySelectorAll(".dropdown-btn.dropdown-open")
    );
    const ids = openBtns.map((btn) => btn.dataset.id).filter((id) => id);

    if (ids.length > 0) {
      localStorage.setItem("sidebar-open-submenus", JSON.stringify(ids));
    } else {
      localStorage.removeItem("sidebar-open-submenus");
    }
  }

  // ============================
  // SUBMENÚS DESPLEGABLES
  // ============================
  const dropdownButtons = document.querySelectorAll(".dropdown-btn");

  dropdownButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();

      const submenu = button.nextElementSibling;
      if (!submenu || !submenu.classList.contains("submenu")) return;

      const isOpen = submenu.classList.contains("open");

      // En sidebar colapsado (desktop) o móvil: solo uno abierto a la vez
      if (body.classList.contains("sidebar-collapsed") || isMobile()) {
        document.querySelectorAll(".submenu").forEach((sm) => {
          if (sm !== submenu) sm.classList.remove("open");
        });
        document.querySelectorAll(".dropdown-btn").forEach((btn) => {
          if (btn !== button) btn.classList.remove("dropdown-open");
        });
      }
      // Si el sidebar está expandido en desktop, permitir múltiples abiertos

      // Toggle del actual
      submenu.classList.toggle("open", !isOpen);
      button.classList.toggle("dropdown-open", !isOpen);

      guardarSubmenus();
    });
  });

  // Activar transiciones suaves después de cargar
  setTimeout(() => {
    body.classList.add("js-loaded");
  }, 50);
});
