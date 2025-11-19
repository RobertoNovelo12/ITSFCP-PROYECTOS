<?php
if (!isset($_SESSION)) session_start();
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

                    <button class="btn btn-primary btn-sm" id="btnNuevoEvento">
                        <i class="bi bi-plus-lg"></i> Nuevo evento
                    </button>
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

</div>

<!-- JS -->
<script src="/ITSFCP-PROYECTOS/publico/js/javascript.js"></script>
<script src="/ITSFCP-PROYECTOS/publico/js/calendario.js"></script>
';

include __DIR__ . '/../../layout.php';
?>