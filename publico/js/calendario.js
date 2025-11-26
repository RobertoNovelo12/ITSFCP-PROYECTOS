document.addEventListener("DOMContentLoaded", () => {
  const calendarGrid = document.getElementById("calendar");
  const monthTitle = document.getElementById("monthTitle");
  const prevMonthBtn = document.getElementById("prevMonth");
  const nextMonthBtn = document.getElementById("nextMonth");
  const todayBtn = document.getElementById("todayBtn");
  const viewSelect = document.getElementById("calendarViewSelect");

  if (!calendarGrid || !monthTitle) return;

  // SIEMPRE comenzamos en el PRIMER día del mes actual
  let currentDate = new Date();
  currentDate.setDate(1);

  let eventosUsuario = [];

  // =============================
  // ELEMENTOS DEL MODAL
  // =============================
  const modal = document.getElementById("modalEvento");
  const closeModal = document.getElementById("closeModal");
  const modalTitulo = document.getElementById("modalTitulo");
  const modalProyecto = document.getElementById("modalProyecto");
  const modalFechaInicio = document.getElementById("modalFechaInicio");
  const modalFechaFin = document.getElementById("modalFechaFin");
  const modalUbicacion = document.getElementById("modalUbicacion");
  const modalDescripcion = document.getElementById("modalDescripcion");

  // =============================
  // CARGAR EVENTOS DESDE API
  // =============================
  fetch("/ITSFCP-PROYECTOS/Vistas/Calendario/calendario_eventos.php")
    .then((res) => res.json())
    .then((data) => {
      eventosUsuario = data;
      renderCalendar();
    })
    .catch((err) => {
      console.error("Error cargando eventos:", err);
      renderCalendar();
    });

  // =============================
  // FUNCIONES AUXILIARES
  // =============================
  function crearCeldaVacia() {
    const cell = document.createElement("div");
    cell.classList.add("day-cell", "other-month");
    return cell;
  }

  function formatearFecha(fechaStr) {
    if (!fechaStr) return "No especificada";
    const fecha = new Date(fechaStr.replace(" ", "T"));
    return fecha.toLocaleString("es-MX", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  // =============================
  // RENDERIZAR CALENDARIO
  // =============================
  function renderCalendar() {
    calendarGrid.innerHTML = "";

    let year = currentDate.getFullYear();
    let month = currentDate.getMonth();

    // Titulo
    monthTitle.textContent = currentDate.toLocaleString("es-MX", {
      month: "long",
      year: "numeric",
    });

    // Vista móvil = puntos
    if (window.innerWidth <= 768) {
      renderCalendarGridWithDots(year, month);
      return;
    }

    renderCalendarDesktop(year, month);
  }

  // =============================
  // VISTA MOVIL (puntos)
  // =============================
  function renderCalendarGridWithDots(year, month) {
    calendarGrid.innerHTML = "";

    const daysOfWeek = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
    daysOfWeek.forEach((d) => {
      const header = document.createElement("div");
      header.classList.add("day-header");
      header.textContent = d;
      calendarGrid.appendChild(header);
    });

    const firstDay = new Date(year, month, 1).getDay();
    const prevDays = (firstDay + 6) % 7;

    // Celdas vacías antes del 1
    for (let i = 0; i < prevDays; i++) {
      calendarGrid.appendChild(crearCeldaVacia());
    }

    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const isoDate = date.toISOString().split("T")[0];

      const cell = document.createElement("div");
      cell.classList.add("day-cell");

      const dayNumber = document.createElement("div");
      dayNumber.classList.add("day-number");
      dayNumber.textContent = day;

      // Punto azul si hay eventos
      if (eventosUsuario.some((ev) => ev.start.split(" ")[0] === isoDate)) {
        const dot = document.createElement("div");
        dot.classList.add("event-dot");
        dayNumber.appendChild(dot);
      }

      cell.appendChild(dayNumber);
      calendarGrid.appendChild(cell);
    }
  }

  // =============================
  // VISTA DESKTOP
  // =============================
  function renderCalendarDesktop(year, month) {
    const daysOfWeek = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
    daysOfWeek.forEach((d) => {
      const header = document.createElement("div");
      header.classList.add("day-header", "separate");
      header.textContent = d;
      calendarGrid.appendChild(header);
    });

    const firstDay = new Date(year, month, 1).getDay();
    const prevDays = (firstDay + 6) % 7;

    // Celdas vacías
    for (let i = 0; i < prevDays; i++) {
      calendarGrid.appendChild(crearCeldaVacia());
    }

    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
      const cell = document.createElement("div");
      cell.classList.add("day-cell");

      const date = new Date(year, month, day);
      const isToday = date.toDateString() === new Date().toDateString();

      if (isToday) cell.classList.add("today");

      const dayNumber = document.createElement("div");
      dayNumber.classList.add("day-number");
      dayNumber.textContent = day;
      cell.appendChild(dayNumber);

      const eventsContainer = document.createElement("div");
      eventsContainer.classList.add("events-container");

      const fechaComparar = date.toISOString().split("T")[0];
      const eventosDelDia = eventosUsuario.filter(
        (ev) => ev.start.split(" ")[0] === fechaComparar
      );

      eventosDelDia.slice(0, 3).forEach((ev) => {
        const eventDiv = document.createElement("div");
        eventDiv.classList.add("event-item");
        eventDiv.textContent = ev.title;
        eventDiv.title = ev.title;

        eventDiv.addEventListener("click", (e) => {
          e.stopPropagation();
          mostrarModal(ev);
        });

        eventsContainer.appendChild(eventDiv);
      });

      if (eventosDelDia.length > 3) {
        const countDiv = document.createElement("div");
        countDiv.classList.add("event-count");
        countDiv.textContent = `+${eventosDelDia.length - 3} más`;

        countDiv.addEventListener("click", (e) => {
          e.stopPropagation();
          mostrarTodosEventos(eventosDelDia);
        });

        eventsContainer.appendChild(countDiv);
      }

      cell.appendChild(eventsContainer);
      calendarGrid.appendChild(cell);
    }
  }

  // =============================
  // MODAL DETALLES
  // =============================
  function mostrarModal(evento) {
    modalTitulo.textContent = evento.title || "Sin título";
    modalProyecto.textContent = evento.proyecto || "Sin proyecto";

    modalFechaInicio.textContent = formatearFecha(evento.start);
    modalFechaFin.textContent = formatearFecha(evento.end);

    modalUbicacion.textContent = evento.ubicacion || "No especificada";
    modalDescripcion.innerHTML =
      evento.descripcion || "<em>Sin descripción</em>";

    modal.style.display = "flex";
  }

  function mostrarTodosEventos(eventos) {
    modalTitulo.textContent = `Eventos del día (${eventos.length})`;
    modalProyecto.textContent = "";
    modalFechaInicio.textContent = "";
    modalFechaFin.textContent = "";
    modalUbicacion.textContent = "";

    let listaHTML = "<ul style='list-style:none;padding:0;'>";
    eventos.forEach((ev) => {
      listaHTML += `
        <li style="margin-bottom:15px;padding:10px;background:#f5f5f5;border-radius:5px;cursor:pointer;">
          <strong>${ev.title}</strong><br>
          <small>${ev.proyecto || "Sin proyecto"}</small><br>
          <small>${formatearFecha(ev.start)}</small>
        </li>`;
    });
    listaHTML += "</ul>";

    modalDescripcion.innerHTML = listaHTML;
    modal.style.display = "flex";
  }

  // =============================
  // CERRAR MODAL
  // =============================
  if (closeModal) {
    closeModal.addEventListener("click", () => {
      modal.style.display = "none";
    });
  }

  modal?.addEventListener("click", (e) => {
    if (e.target === modal) modal.style.display = "none";
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") modal.style.display = "none";
  });

  // =============================
  // NAVEGACIÓN DE MESES
  // =============================
  prevMonthBtn?.addEventListener("click", () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    currentDate.setDate(1);
    renderCalendar();
  });

  nextMonthBtn?.addEventListener("click", () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    currentDate.setDate(1);
    renderCalendar();
  });

  todayBtn?.addEventListener("click", () => {
    currentDate = new Date();
    currentDate.setDate(1);
    renderCalendar();
  });

  // =============================
  // SELECT (por ahora solo mes)
  // =============================
  viewSelect?.addEventListener("change", () => {
    if (viewSelect.value !== "mes") {
      alert("Solo la vista de mes está activa por ahora.");
      viewSelect.value = "mes";
    }
  });
});