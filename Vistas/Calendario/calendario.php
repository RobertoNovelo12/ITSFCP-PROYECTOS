<?php
if (!isset($_SESSION))
    session_start();
$titulo = "Calendario";

$contenido = '
<div class="container-fluid py-4">

        <!-- CALENDARIO PRINCIPAL SIN SIDEBAR -->
        <div class="col-lg-12">
            <div class="calendar-container">

                <!-- BARRA SUPERIOR -->
                    <div class="calendar-topbar d-flex justify-content-between align-items-center mb-3">
                        <select class="form-select w-auto" id="calendarViewSelect">
                            <option value="mes">Mes</option>
                            <option value="dia">Día</option>
                            <option value="proximos">Próximos eventos</option>
                        </select>

                        <a href="/ITSFCP-PROYECTOS/Vistas/Eventos/crear.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i> Nuevo evento
                        </a>
                    </div>


                <!-- ENCABEZADO DEL CALENDARIO -->
                <div class="calendar-header">
                    <button class="nav-btn" id="prevMonth">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <h2 class="month-title" id="monthTitle"></h2>

                    <button class="nav-btn" id="nextMonth">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <button class="btn btn-outline-primary mb-3" id="todayBtn">Hoy</button>

                <div class="calendar-grid" id="calendar"></div>
            </div>
        </div>
<!-- Modal para ver detalles del evento -->
<div id="modalEvento" class="modal-evento" style="display: none;">
    <div class="modal-content-evento">
        <span class="close-modal" id="closeModal">&times;</span>
        
        <h2 id="modalTitulo">Título del Evento</h2>
        
        <div class="modal-body-evento">
            <div class="info-group">
                <label><i class="bi bi-folder"></i> Proyecto:</label>
                <p id="modalProyecto"></p>
            </div>
            
            <div class="info-group">
                <label><i class="bi bi-calendar-event"></i> Fecha de inicio:</label>
                <p id="modalFechaInicio"></p>
            </div>
            
            <div class="info-group">
                <label><i class="bi bi-calendar-check"></i> Fecha de fin:</label>
                <p id="modalFechaFin"></p>
            </div>
            
            <div class="info-group">
                <label><i class="bi bi-geo-alt"></i> Ubicación:</label>
                <p id="modalUbicacion"></p>
            </div>
            
            <div class="info-group">
                <label><i class="bi bi-text-paragraph"></i> Descripción:</label>
                <div id="modalDescripcion"></div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/ITSFCP-PROYECTOS/publico/js/calendario.js"></script>
';

include __DIR__ . '/../../layout.php';
?>