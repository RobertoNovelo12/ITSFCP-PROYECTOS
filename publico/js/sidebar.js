// --- SIDEBAR LOGIC --- //
document.addEventListener("DOMContentLoaded", () => {
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.querySelector(".sidebar");
  const body = document.body;
  const html = document.documentElement;

  // ============================
  // RESTAURAR ESTADO DEL SIDEBAR
  // ============================
  const sidebarCollapsed = localStorage.getItem("sidebar-collapsed");
  
  html.classList.remove("sidebar-collapsed-initial");
  
  if (sidebarCollapsed === "true") {
    sidebar.classList.add("collapsed");
    body.classList.add("sidebar-collapsed");
    
    // Cerrar todos los submenús si está colapsado
    document.querySelectorAll(".submenu").forEach(sm => sm.classList.remove("open"));
    document.querySelectorAll(".dropdown-btn").forEach(btn => btn.classList.remove("dropdown-open"));
  } else {
    (sidebarCollapsed === "false") 
    sidebar.classList.remove("collapsed");
    body.classList.remove("sidebar-collapsed");
  };

  // ============================
  // BOTÓN COLAPSAR/EXPANDIR
  // ============================
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      const isCollapsed = sidebar.classList.toggle("collapsed");
      body.classList.toggle("sidebar-collapsed", isCollapsed);
      localStorage.setItem("sidebar-collapsed", isCollapsed.toString());

      // Si se colapsa, cerrar todos los submenús
      if (isCollapsed) {
        document.querySelectorAll(".submenu").forEach(sm => sm.classList.remove("open"));
        document.querySelectorAll(".dropdown-btn").forEach(btn => btn.classList.remove("dropdown-open"));
        localStorage.removeItem("sidebar-open-submenus");
      } else {
        // Al expandir, restaurar submenús guardados
        restaurarSubmenus();
      }
    });
  }

  // ============================
  // RESTAURAR SUBMENÚS GUARDADOS
  // ============================
  function restaurarSubmenus() {
    if (body.classList.contains("sidebar-collapsed")) {
      return;
    }
    
    const openSubmenusStr = localStorage.getItem("sidebar-open-submenus");
    
    if (openSubmenusStr) {
      try {
        const openSubmenus = JSON.parse(openSubmenusStr);
        openSubmenus.forEach(id => {
          const submenuBtn = document.querySelector(`.dropdown-btn[data-id="${id}"]`);
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

  // Solo restaurar si NO está colapsado
  if (!body.classList.contains("sidebar-collapsed")) {
    restaurarSubmenus();
  }

  // ============================
  // FUNCIÓN PARA GUARDAR SUBMENÚS
  // ============================
  function guardarSubmenus() {
    if (body.classList.contains("sidebar-collapsed")) {
      localStorage.removeItem("sidebar-open-submenus");
      return;
    }
    
    const openBtns = Array.from(document.querySelectorAll(".dropdown-btn.dropdown-open"));
    const ids = openBtns.map(btn => btn.dataset.id).filter(id => id);
    
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

  dropdownButtons.forEach(button => {
    button.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const submenu = button.nextElementSibling;
      if (!submenu || !submenu.classList.contains("submenu")) return;

      const isOpen = submenu.classList.contains("open");

      if (body.classList.contains("sidebar-collapsed")) {
        document.querySelectorAll(".submenu").forEach(sm => {
          if (sm !== submenu) sm.classList.remove("open");
        });
        document.querySelectorAll(".dropdown-btn").forEach(btn => {
          if (btn !== button) btn.classList.remove("dropdown-open");
        });
      }
      // Si el sidebar está expandido, NO cerrar otros (permitir múltiples abiertos)

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