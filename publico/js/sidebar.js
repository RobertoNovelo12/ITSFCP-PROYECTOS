// --- SIDEBAR LOGIC --- //
document.addEventListener("DOMContentLoaded", () => {
  // Botón para colapsar el sidebar
  const toggleBtn = document.getElementById("sidebarToggle");
  const sidebar = document.querySelector(".sidebar");
  const body = document.body;

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("collapsed");
      body.classList.toggle("sidebar-collapsed");
    });
  }

  // Submenús desplegables
  const dropdownLinks = document.querySelectorAll(".sidebar-dropdown");

  dropdownLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const parent = link.closest(".sidebar-item");

      if (!parent) return;

      // Cerrar otros submenús
      document.querySelectorAll(".sidebar-item.open").forEach((openItem) => {
        if (openItem !== parent) openItem.classList.remove("open");
      });

      parent.classList.toggle("open");
    });
  });

  const dropdownButtons = document.querySelectorAll(".dropdown-btn");

  dropdownButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const submenu = button.nextElementSibling;

      if (!submenu || !submenu.classList.contains("submenu")) return;

      const isOpen = submenu.classList.contains("open");

      // Cerrar TODOS los demás submenús (solo si tu sidebar no está colapsado)
      if (!document.body.classList.contains("sidebar-collapsed")) {
        document.querySelectorAll(".submenu.open").forEach((sm) => {
          if (sm !== submenu) sm.classList.remove("open");
        });
        document
          .querySelectorAll(".dropdown-btn.dropdown-open")
          .forEach((btn) => {
            if (btn !== button) btn.classList.remove("dropdown-open");
          });
      }

      // Toggle
      submenu.classList.toggle("open", !isOpen);
      button.classList.toggle("dropdown-open", !isOpen);
    });
  });
});
