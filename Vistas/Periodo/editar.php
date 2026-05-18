<?php
// Periodo/editar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol         = strtolower($_SESSION['rol'] ?? '');
$id_usuario  = intval($_SESSION['id_usuario']);
$id_periodos = isset($_GET['id_periodos']) ? intval($_GET['id_periodos']) : 0;

/* CONTROLADOR */
require_once '../../Controladores/periodoControlador.php';

$action             = $_POST['action'] ?? null;
$periodoControlador = new periodoControlador();

/* Procesar acciones POST antes de cargar datos */
if ($action === 'guardar_fechas') {
    $periodoControlador->actualizarFechasSubperiodos($rol, $id_periodos); // redirige internamente
}

if ($action === 'desactivar') {
    $periodoControlador->eliminar($id_periodos, $rol); // redirige internamente
}

/* Cargar datos del periodo */
$datos = $periodoControlador->indexEditar($rol, $id_periodos);

if (empty($datos)) {
    die("No se encontró el periodo o no tiene permiso para editarlo.");
}

/* Mensaje de error de rango (viene por GET tras redirección) */
$error_fecha = isset($_GET['error_fecha']) ? htmlspecialchars($_GET['error_fecha']) : null;

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar periodo</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <?php if ($error_fecha): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= $error_fecha ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- INFORMACIÓN DEL PERIODO (solo lectura) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">
                Información del periodo
                <span class="text-muted fs-6 fw-normal">(no editable)</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Nombre</label>
                    <p class="mb-0 fw-bold"><?= htmlspecialchars($datos['nombre']) ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Estado</label>
                    <p class="mb-0">
                        <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($datos['estado']) ?>">
                            <?= htmlspecialchars($datos['estado']) ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Fecha inicio del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['inicio']) ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted small">Fecha fin del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['fin']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO: FECHAS EDITABLES -->
    <form method="POST" action="editar.php?id_periodos=<?= $id_periodos ?>" novalidate>
        <input type="hidden" name="action" value="guardar_fechas">

        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Fechas de proyectos e integración</h5>
            </div>
            <div class="card-body">

                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Todas las fechas deben estar dentro del rango del semestre
                    (<strong><?= htmlspecialchars($datos['inicio']) ?></strong> –
                    <strong><?= htmlspecialchars($datos['fin']) ?></strong>).
                </p>

                <!-- Proyectos -->
                <h6 class="fw-semibold mb-3 border-bottom pb-1">Periodo de Proyectos</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="fecha_inicio_proyectos" class="form-label">Fecha inicio de proyectos</label>
                        <input type="date"
                               id="fecha_inicio_proyectos"
                               name="fecha_inicio_proyectos"
                               class="form-control"
                               value="<?= htmlspecialchars($datos['fecha_inicio_proyectos'] ?? '') ?>"
                               min="<?= htmlspecialchars($datos['inicio']) ?>"
                               max="<?= htmlspecialchars($datos['fin']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_fin_proyectos" class="form-label">Fecha fin de proyectos</label>
                        <input type="date"
                               id="fecha_fin_proyectos"
                               name="fecha_fin_proyectos"
                               class="form-control"
                               value="<?= htmlspecialchars($datos['fecha_fin_proyectos'] ?? '') ?>"
                               min="<?= htmlspecialchars($datos['inicio']) ?>"
                               max="<?= htmlspecialchars($datos['fin']) ?>">
                    </div>
                </div>

                <!-- Integración -->
                <h6 class="fw-semibold mb-3 border-bottom pb-1">Periodo de Integración</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="fecha_inicio_solicitud" class="form-label">Fecha inicio de integración</label>
                        <input type="date"
                               id="fecha_inicio_solicitud"
                               name="fecha_inicio_solicitud"
                               class="form-control"
                               value="<?= htmlspecialchars($datos['fecha_inicio_solicitud'] ?? '') ?>"
                               min="<?= htmlspecialchars($datos['inicio']) ?>"
                               max="<?= htmlspecialchars($datos['fin']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_fin_solicitud" class="form-label">Fecha fin de integración</label>
                        <input type="date"
                               id="fecha_fin_solicitud"
                               name="fecha_fin_solicitud"
                               class="form-control"
                               value="<?= htmlspecialchars($datos['fecha_fin_solicitud'] ?? '') ?>"
                               min="<?= htmlspecialchars($datos['inicio']) ?>"
                               max="<?= htmlspecialchars($datos['fin']) ?>">
                    </div>
                </div>

            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-guardar">
                <i class="bi bi-floppy me-1"></i> Guardar cambios
            </button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>

    </form>

    <!-- Advertencia: DESACTIVAR PERIODO -->
    <div class="card border-danger shadow-sm">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> Advertencia</h5>
        </div>
        <div class="card-body">
            <p class="mb-3 text-muted">
                Al desactivar el periodo ya no estará disponible para nuevos registros.
                Esta acción se puede revertir desde la vista de crear periodo.
            </p>
            <form method="POST" action="editar.php?id_periodos=<?= $id_periodos ?>"
                  onsubmit="return confirm('¿Está seguro de que desea desactivar este periodo?');">
                <input type="hidden" name="action" value="desactivar">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-x-circle-fill me-1"></i> Desactivar Periodo
                </button>
            </form>
        </div>
    </div>

</div>

<script>
    (function () {
        const pares = [
            ['fecha_inicio_proyectos',   'fecha_fin_proyectos'],
            ['fecha_inicio_solicitud', 'fecha_fin_solicitud'],
        ];

        pares.forEach(([inicioId, finId]) => {
            const inputInicio = document.getElementById(inicioId);
            const inputFin    = document.getElementById(finId);

            if (!inputInicio || !inputFin) return;

            inputInicio.addEventListener('change', () => {
                if (inputInicio.value) {
                    inputFin.min = inputInicio.value;
                    if (inputFin.value && inputFin.value < inputInicio.value) {
                        inputFin.value = '';
                    }
                }
            });

            inputFin.addEventListener('change', () => {
                if (inputFin.value) {
                    inputInicio.max = inputFin.value;
                }
            });
        });
    })();
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Editar periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>