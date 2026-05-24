<?php
// Periodo/crear.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

/* CONTROLADOR */
require_once '../../Controladores/periodoControlador.php';

$action              = $_POST['action'] ?? null;
$periodoControlador  = new periodoControlador();
$estadoVista         = $periodoControlador->obtenerEstadoVista();
$datos               = $estadoVista['datos'];

/* POST – crear o reactivar */
if ($action === 'registrarPeriodo') {
    $periodoControlador->registrarPeriodo($rol);   // redirige internamente
}

if ($action === 'reactivarPeriodo') {
    $periodoControlador->reactivar($datos['nombre']); // redirige internamente
}

/* Recuperar valores del POST para re-poblar el formulario en caso de error */
$post_fip = $_POST['fecha_inicio_proyectos']   ?? '';
$post_ffp = $_POST['fecha_fin_proyectos']       ?? '';
$post_fii = $_POST['fecha_inicio_integracion']  ?? '';
$post_ffi = $_POST['fecha_fin_solicitud']     ?? '';

/* Mensaje de error de rango (viene por GET tras redirección) */
$error_fecha = isset($_GET['error_fecha']) ? htmlspecialchars($_GET['error_fecha']) : null;

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <div class="col-6">
            <h3 class="fw-bold mb-0">Crear Periodo</h3>
        </div>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-danger">
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
            <h5 class="mb-0">Información del periodo <span class="text-muted fs-6 fw-normal">(generado automáticamente)</span></h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Nombre del periodo</label>
                    <p class="mb-0 fw-bold"><?= htmlspecialchars($datos['nombre']) ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Fecha de inicio del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['inicio']) ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Fecha de fin del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['fin']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($estadoVista['accion'] === 'bloqueado'): ?>

        <div class="alert alert-warning" role="alert">
            <i class="bi bi-lock-fill me-1"></i>
            <?= htmlspecialchars($estadoVista['mensaje']) ?>
        </div>

    <?php else: ?>

        <form method="POST" action="crear.php" novalidate>

            <?php if ($estadoVista['accion'] === 'reactivar'): ?>
                <input type="hidden" name="action" value="reactivarPeriodo">
            <?php else: ?>
                <input type="hidden" name="action" value="registrarPeriodo">
            <?php endif; ?>

            <!-- FECHAS DE PROYECTOS E INTEGRACIÓN -->
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
                        Son opcionales; puede completarlas ahora o editarlas después.
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
                                   value="<?= htmlspecialchars($post_fip) ?>"
                                   min="<?= htmlspecialchars($datos['inicio']) ?>"
                                   max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin_proyectos" class="form-label">Fecha fin de proyectos</label>
                            <input type="date"
                                   id="fecha_fin_proyectos"
                                   name="fecha_fin_proyectos"
                                   class="form-control"
                                   value="<?= htmlspecialchars($post_ffp) ?>"
                                   min="<?= htmlspecialchars($datos['inicio']) ?>"
                                   max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                    </div>

                    <!-- Integración -->
                    <h6 class="fw-semibold mb-3 border-bottom pb-1">Periodo de Integración</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="fecha_inicio_integracion" class="form-label">Fecha inicio de integración</label>
                            <input type="date"
                                   id="fecha_inicio_integracion"
                                   name="fecha_inicio_integracion"
                                   class="form-control"
                                   value="<?= htmlspecialchars($post_fii) ?>"
                                   min="<?= htmlspecialchars($datos['inicio']) ?>"
                                   max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin_solicitud" class="form-label">Fecha fin de integración</label>
                            <input type="date"
                                   id="fecha_fin_solicitud"
                                   name="fecha_fin_solicitud"
                                   class="form-control"
                                   value="<?= htmlspecialchars($post_ffi) ?>"
                                   min="<?= htmlspecialchars($datos['inicio']) ?>"
                                   max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if ($estadoVista['accion'] === 'reactivar'): ?>
                    <button type="submit" class="btn btn-guardar">
                        <i class="bi bi-arrow-repeat me-1"></i> Reactivar Periodo
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-guardar">
                        <i class="bi bi-plus-lg me-1"></i> Crear Periodo
                    </button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>

        </form>

    <?php endif; ?>

</div>

<script>
    /*
     * Validación cliente: ajusta el min del campo "fin" al valor de "inicio"
     * para evitar seleccionar fin < inicio en proyectos e integración.
     */
    (function () {
        const pares = [
            ['fecha_inicio_proyectos',   'fecha_fin_proyectos'],
            ['fecha_inicio_integracion', 'fecha_fin_solicitud'],
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
$titulo    = "Crear periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>