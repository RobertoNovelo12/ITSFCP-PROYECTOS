<?php
if (!isset($_SESSION)) session_start();
$titulo = "Calendario";
$necesitaQuill = true;

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

                <button class="btn btn-primary btn-sm" id="btnAbrirCrearEvento">
                    <i class="bi bi-plus-lg"></i> Nuevo evento
                </button>
            </div>

            <!-- ENCABEZADO CALENDARIO -->
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

    <!-- MODAL VER DETALLE -->
    <div id="modalEvento" class="modal-evento" style="display:none;">
        <div class="modal-content-evento">
            <span class="close-modal" id="closeModal">&times;</span>

            <h2 id="modalTitulo"></h2>

            <div class="modal-body-evento">
                <div class="info-group">
                    <label><i class="bi bi-folder"></i> Proyecto:</label>
                    <p id="modalProyecto"></p>
                </div>
                <div class="info-group">
                    <label><i class="bi bi-calendar-event"></i> Fecha inicio:</label>
                    <p id="modalFechaInicio"></p>
                </div>
                <div class="info-group">
                    <label><i class="bi bi-calendar-check"></i> Fecha fin:</label>
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

    <!-- MODAL CREAR EVENTO -->
    <div id="modalCrearEvento" class="modal-evento" style="display:none;">
        <div class="modal-content-evento large-modal">
            <span class="close-modal" id="cerrarCrearEvento">&times;</span>

            <h2>Nuevo evento</h2>

            <div id="alertaEvento" style="display:none;" class="alert alert-success mb-2"></div>

            <form id="formCrearEvento">

                <div class="form-group-evento">
                    <label>Nombre del evento *</label>
                    <input type="text" name="nombreEvento" id="nombreEventoModal" required>
                </div>

                <div class="form-row-evento">
                    <div class="form-group-evento">
                        <label>Fecha inicio</label>
                        <input type="date" name="fechaEvento" id="fechaEventoModal" required>
                    </div>

                    <div class="form-group-evento">
                        <label>Fecha fin</label>
                        <input type="date" name="fechaFin" id="fechaFinModal" required>
                    </div>

                    <div class="form-group-evento">
                        <label>Hora inicio</label>
                        <input type="time" name="horaInicio" id="horaInicioModal" required>
                    </div>

                    <div class="form-group-evento">
                        <label>Hora fin</label>
                        <input type="time" name="horaFin" id="horaFinModal" required>
                    </div>
                </div>

                <div class="form-group-evento">
                    <label>Proyecto</label>
                    <select name="proyecto" id="proyectoModal" required>
                        <option value="">Seleccionar proyecto</option>
                    </select>
                </div>

                <div class="form-group-evento" id="boxInvitadosModal" style="display:none;">
                    <label>Invitar estudiantes</label>
                    <div id="listaEstudiantesModal" class="invitados-box">
                        <p class="small text-muted">Seleccione un proyecto...</p>
                    </div>
                </div>

                <div class="form-group-evento">
                    <label>Descripción</label>
                    <div id="editorDescripcionModal" style="height:150px;"></div>
                    <input type="hidden" name="descripcion" id="descripcionModal">
                </div>

                <div class="form-group-evento">
                    <label>Ubicación</label>
                    <input type="text" name="ubicacion" id="ubicacionModal">
                </div>

                <button class="btn btn-primary mt-3" id="btnSubmitModal">
                    <i class="bi bi-check-lg"></i> Crear evento
                </button>

            </form>
        </div>
    </div>

</div>

<style>
.large-modal {
    width: 60%;
    max-height: 90vh;
    overflow-y: auto;
}
</style>

<script src="/ITSFCP-PROYECTOS/publico/js/calendario.js"></script>
<script src="/ITSFCP-PROYECTOS/publico/js/evento.js"></script>
';

include __DIR__ . "/../../layout.php";
?>