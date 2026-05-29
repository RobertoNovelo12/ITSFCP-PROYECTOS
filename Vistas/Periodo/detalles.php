<?php
// Periodo/detalles.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}


$rol         = strtolower($_SESSION['rol'] ?? '');
$id_usuario  = intval($_SESSION['id_usuario']);

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

$id_periodo  = intval($_GET['id_periodos']) ?? 0;

$id_validar = $id_periodo;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/periodoControlador.php';


$periodoControlador = new periodoControlador();

/* Obtener datos */
$periodo = $periodoControlador->indexDetalles($rol, $id_periodo);

// Validación
$registro = $periodo;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


/* Pequeño helper para mostrar fecha o "No definida" */
function mostrarFecha(?string $fecha, string $formato = "d/m/Y"): string
{
    if (empty($fecha) || $fecha === '0000-00-00') {
        return '<span class="text-muted fst-italic">No definida</span>';
    }
    return htmlspecialchars(date($formato, strtotime($fecha)));
}

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de Periodo';
        $descripcion = 'Información del periodo seleccionado';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN GENERAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Información del periodo</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Nombre</label>
                    <p class="mb-0 fw-bold"><?= htmlspecialchars($periodo['periodo']) ?></p>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Estado</label>
                    <p class="mb-0">
                        <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($periodo['estado']) ?>">
                            <?= htmlspecialchars($periodo['estado']) ?>
                        </span>
                    </p>
                </div>

            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha de inicio del semestre</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_inicio']) ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha de fin del semestre</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_final']) ?></p>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha de creación</label>
                    <p class="mb-0">
                        <?= mostrarFecha($periodo['fecha_creacion']) ?>
                        <?php if (!empty($periodo['fecha_creacion'])): ?>
                            &nbsp;<span class="text-muted"><?= date("H:i", strtotime($periodo['fecha_creacion'])) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Última modificación</label>
                    <p class="mb-0">
                        <?php
                        if (!empty($periodo['fecha_modificacion']) && $periodo['fecha_modificacion'] !== '0000-00-00 00:00:00') {
                            echo htmlspecialchars(date("d/m/Y H:i", strtotime($periodo['fecha_modificacion'])));
                        } else {
                            echo '<span class="text-muted fst-italic">Sin modificaciones</span>';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- FECHAS DE PROYECTOS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Periodo de Proyectos</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha inicio de proyectos</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_inicio_proyectos']) ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha fin de proyectos</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_fin_proyectos']) ?></p>
                </div>
            </div>

            <?php
            // Barra visual de progreso del sub-periodo de proyectos
            $fip = $periodo['fecha_inicio_proyectos'] ?? null;
            $ffp = $periodo['fecha_fin_proyectos']    ?? null;

            if ($fip && $ffp && $fip !== '0000-00-00' && $ffp !== '0000-00-00'):
                $hoy     = new DateTime();
                $dInicio = new DateTime($fip);
                $dFin    = new DateTime($ffp);

                $totalDias      = max(1, $dInicio->diff($dFin)->days);
                $diasTranscurridos = min($totalDias, max(0, $dInicio->diff($hoy)->days));
                $porcentaje     = round($diasTranscurridos / $totalDias * 100);
                $porcentaje     = min(100, $porcentaje);

                $color = $porcentaje >= 100 ? 'bg-danger' : ($porcentaje >= 75 ? 'bg-warning' : 'bg-success');
            ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Avance del periodo de proyectos</span>
                        <span><?= $porcentaje ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $color ?>"
                            role="progressbar"
                            style="width: <?= $porcentaje ?>%"
                            aria-valuenow="<?= $porcentaje ?>"
                            aria-valuemin="0"
                            aria-valuemax="100">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FECHAS DE INTEGRACIÓN -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Periodo de Integración</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha inicio de integración</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_inicio_solicitud']) ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted small">Fecha fin de integración</label>
                    <p class="mb-0"><?= mostrarFecha($periodo['fecha_fin_solicitud']) ?></p>
                </div>
            </div>

            <?php
            $fii = $periodo['fecha_inicio_solicitud'] ?? null;
            $ffi = $periodo['fecha_fin_solicitud']    ?? null;

            if ($fii && $ffi && $fii !== '0000-00-00' && $ffi !== '0000-00-00'):
                $hoy     = new DateTime();
                $dInicio = new DateTime($fii);
                $dFin    = new DateTime($ffi);

                $totalDias         = max(1, $dInicio->diff($dFin)->days);
                $diasTranscurridos = min($totalDias, max(0, $dInicio->diff($hoy)->days));
                $porcentaje        = round($diasTranscurridos / $totalDias * 100);
                $porcentaje        = min(100, $porcentaje);

                $color = $porcentaje >= 100 ? 'bg-danger' : ($porcentaje >= 75 ? 'bg-warning' : 'bg-success');
            ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Avance del periodo de integración</span>
                        <span><?= $porcentaje ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $color ?>"
                            role="progressbar"
                            style="width: <?= $porcentaje ?>%"
                            aria-valuenow="<?= $porcentaje ?>"
                            aria-valuemin="0"
                            aria-valuemax="100">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalles periodo";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>