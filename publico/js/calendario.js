// --- CALENDARIO --- //
document.addEventListener("DOMContentLoaded", () => {

    const calendarGrid = document.getElementById("calendar");
    const monthTitle = document.getElementById("monthTitle");
    const prevMonthBtn = document.getElementById("prevMonth");
    const nextMonthBtn = document.getElementById("nextMonth");
    const todayBtn = document.getElementById("todayBtn");
    const viewSelect = document.getElementById("calendarViewSelect");

    // Si no existe el calendario, no ejecutar nada
    if (!calendarGrid || !monthTitle) return;

    let currentDate = new Date();
    let eventosUsuario = [];

    // =============================
    // CARGAR EVENTOS DESDE EL API
    // =============================
    fetch("/ITSFCP-PROYECTOS/Vistas/Calendario/calendario_eventos.php")
        .then(res => res.json())
        .then(data => {
            eventosUsuario = data;
            renderCalendar();
        })
        .catch(err => {
            console.error("Error cargando eventos:", err);
            renderCalendar();
        });

    // =============================
    // RENDERIZAR CALENDARIO
    // =============================
    function renderCalendar() {
        calendarGrid.innerHTML = "";

        let year = currentDate.getFullYear();
        let month = currentDate.getMonth();

        // Título del mes
        monthTitle.textContent = currentDate.toLocaleString("es-MX", {
            month: "long",
            year: "numeric"
        });

        // ---- Encabezado de días ----
        const daysOfWeek = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
        daysOfWeek.forEach(d => {
            const header = document.createElement("div");
            header.classList.add("day-header");
            header.textContent = d;
            calendarGrid.appendChild(header);
        });

        // Primer día
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Ajuste para que la semana empiece en lunes
        const prevDays = (firstDay + 6) % 7;

        // Días vacíos antes del 1
        for (let i = 0; i < prevDays; i++) {
            const emptyCell = document.createElement("div");
            emptyCell.classList.add("day-cell", "other-month");
            calendarGrid.appendChild(emptyCell);
        }

        // =============================
        // DÍAS DEL MES
        // =============================
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

            // =============================
            // CONTENEDOR DE EVENTOS
            // =============================
            const eventsContainer = document.createElement("div");
            eventsContainer.classList.add("events-container");

            // =============================
            // AGREGAR EVENTOS DEL USUARIO
            // =============================
            const fechaComparar = date.toISOString().split('T')[0]; // YYYY-MM-DD

            let eventosDelDia = [];

            eventosUsuario.forEach(ev => {
                // Extraer la parte de fecha (YYYY-MM-DD) del formato "YYYY-MM-DD HH:MM:SS"
                const fechaEvento = ev.start.split(" ")[0];

                if (fechaEvento === fechaComparar) {
                    eventosDelDia.push(ev);
                }
            });

            // Mostrar hasta 3 eventos, si hay más mostrar contador
            eventosDelDia.slice(0, 3).forEach(ev => {
                const eventDiv = document.createElement("div");
                eventDiv.classList.add("event-item");
                eventDiv.textContent = ev.title;
                eventDiv.title = ev.title; // Tooltip al pasar el mouse
                eventsContainer.appendChild(eventDiv);
            });

            // Si hay más de 3 eventos, mostrar un contador
            if (eventosDelDia.length > 3) {
                const countDiv = document.createElement("div");
                countDiv.classList.add("event-count");
                countDiv.textContent = `+${eventosDelDia.length - 3} más`;
                eventsContainer.appendChild(countDiv);
            }

            cell.appendChild(eventsContainer);
            calendarGrid.appendChild(cell);
        }
    }

    // =============================
    // NAVEGACIÓN ENTRE MESES
    // =============================
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });
    }

    if (nextMonthBtn) {
        nextMonthBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
    }

    if (todayBtn) {
        todayBtn.addEventListener("click", () => {
            currentDate = new Date();
            renderCalendar();
        });
    }

    // =============================
    // SELECTOR DE VISTA
    // =============================
    if (viewSelect) {
        viewSelect.addEventListener("change", () => {
            if (viewSelect.value !== "mes") {
                alert("Solo la vista de mes está activa por ahora.");
                viewSelect.value = "mes";
            }
        });
    }
});