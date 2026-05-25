<?php

/**
 * detalle_proyecto.php
 * Vista de detalle de un proyecto — exclusivo supervisor, solo lectura.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
if (strtolower($_SESSION['rol'] ?? '') !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/SupervisorControlador.php';
$ctrl = new SupervisorControlador();
$data = $ctrl->detalleProyecto();

extract($data);  // $proyecto, $estudiantes, $solicitudes, $tareas, $historial

if (empty($proyecto)) {
    header("Location: panel_supervisor.php");
    exit;
}

$total_tar_proy = array_sum(array_column($tareas, 'total_asignados'));
$aprob_tar_proy = array_sum(array_column($tareas, 'aprobados'));
$pct_proy       = $total_tar_proy > 0 ? round(($aprob_tar_proy / $total_tar_proy) * 100) : 0;
$color_pct      = $pct_proy >= 80 ? 'success' : ($pct_proy >= 40 ? 'warning' : 'danger');

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalle de Proyecto <?= htmlspecialchars($proyecto["titulo"]) ?>';
        $descripcion = 'Información completa del proyecto seleccionado';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <?= $ctrl->badgeEstadoProyecto($proyecto['estado']) ?>
            <a href="panel_supervisor.php?tab=proyectos" class="btn btn-secondary btn-sm ms-2">
                <i class="bi bi-arrow-left me-1"></i>Regresar
            </a>
        </div>
    </div>

    <!-- MÉTRICAS RÁPIDAS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= count($estudiantes) ?></div>
                <div class="small text-muted">Participantes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= $aprob_tar_proy ?></div>
                <div class="small text-muted">Tareas aprobadas</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 text-center py-3">
                <div class="fs-3 fw-bold text-warning"><?= count($solicitudes) ?></div>
                <div class="small text-muted">Solicitudes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 text-center py-3">
                <div class="fs-3 fw-bold text-<?= $color_pct ?>"><?= $pct_proy ?>%</div>
                <div class="small text-muted">Avance global</div>
            </div>
        </div>
    </div>

    <!-- BARRA AVANCE GLOBAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Avance del proyecto</span>
                <span class="small text-muted"><?= $aprob_tar_proy ?> / <?= $total_tar_proy ?> tareas aprobadas</span>
            </div>
            <div class="progress progress-lg">
                <div class="progress-bar bg-<?= $color_pct ?>" style="width:<?= $pct_proy ?>%"><?= $pct_proy ?>%</div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUMNA IZQUIERDA: datos del proyecto -->
        <div class="col-md-5">

            <!-- Información general -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Información del proyecto
                </div>
                <div class="card-body">
                    <dl class="info-dl row mb-0">
                        <div class="col-6">
                            <dt>Periodo</dt>
                            <dd><?= htmlspecialchars($proyecto['periodo'] ?? '—') ?>
                                <?php if ($proyecto['estado_periodo']): ?>
                                    <span class="badge bg-success ms-1" style="font-size:.7rem">Activo</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="col-6">
                            <dt>Modalidad</dt>
                            <dd class="text-capitalize"><?= htmlspecialchars($proyecto['modalidad']) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Fecha inicio</dt>
                            <dd><?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Fecha fin</dt>
                            <dd><?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Cupo</dt>
                            <dd><?= count($estudiantes) ?> / <?= $proyecto['cantidad_estudiante'] ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Presupuesto</dt>
                            <dd>$<?= number_format($proyecto['presupuesto'], 2) ?></dd>
                        </div>
                        <div class="col-12">
                            <dt>Descripción</dt>
                            <dd><?= nl2br(htmlspecialchars($proyecto['descripcion'] ?? '')) ?></dd>
                        </div>
                        <?php if (!empty($proyecto['objetivo'])): ?>
                            <div class="col-12">
                                <dt>Objetivo</dt>
                                <dd><?= nl2br(htmlspecialchars($proyecto['objetivo'])) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Investigador -->
            <?php if (!empty($proyecto['investigador_nombre'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-person-badge me-2"></i>Investigador responsable
                    </div>
                    <div class="card-body">
                        <dl class="info-dl row mb-0">
                            <div class="col-12">
                                <dt>Nombre</dt>
                                <dd><?= htmlspecialchars($proyecto['investigador_nombre']) ?></dd>
                            </div>
                            <?php if (!empty($proyecto['investigador_correo'])): ?>
                                <div class="col-12">
                                    <dt>Correo</dt>
                                    <dd><?= htmlspecialchars($proyecto['investigador_correo']) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- COLUMNA DERECHA: estudiantes, tareas, solicitudes -->
        <div class="col-md-7">

            <!-- Participantes -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-people me-2"></i>Participantes
                    <span class="badge bg-primary float-end"><?= count($estudiantes) ?></span>
                </div>
                <div class="card-body">
                    <?php foreach ($estudiantes as $est):
                        $tot_e = max(1, $est['tareas_totales']);
                        $pct_e = round(($est['tareas_aprobadas'] / $tot_e) * 100);
                        $col_e = $pct_e >= 80 ? 'success' : ($pct_e >= 40 ? 'warning' : 'danger');
                    ?>
                        <div class="alumno-card <?= $est['estado_participacion'] === 'baja' ? 'baja' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($est['nombre_completo']) ?></div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($est['matricula']) ?> &nbsp;·&nbsp;
                                        <?= htmlspecialchars(mb_substr($est['carrera'], 0, 30)) ?>
                                    </div>
                                    <div class="small text-muted"><?= htmlspecialchars($est['correo_institucional']) ?></div>
                                </div>
                                <div class="text-end">
                                    <?= $ctrl->badgeParticipacion($est['estado_participacion']) ?>
                                    <div class="small text-muted mt-1">
                                        Desde <?= date('d/m/Y', strtotime($est['fecha_asignacion'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Avance</small>
                                    <small><?= $est['tareas_aprobadas'] ?>/<?= $est['tareas_totales'] ?> tareas</small>
                                </div>
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-<?= $col_e ?>" style="width:<?= $pct_e ?>%"></div>
                                </div>
                                <?php if (!empty($est['tarea_actual'])): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Trabajando en:
                                        <span class="fw-medium"><?= htmlspecialchars($est['tarea_actual']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2">
                                <a href="detalle_estudiante.php?id=<?= $est['id_usuarios'] ?>"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-person-lines-fill me-1"></i>Ver historial
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($estudiantes)): ?>
                        <p class="text-muted text-center small">Sin participantes registrados.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Secciones del documento — TABLA DESKTOP -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-list-task me-2"></i>Secciones del documento
                </div>
                <div class="card-body p-0">
                    <!-- Tabla desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sección</th>
                                        <th class="text-center">Estado tarea</th>
                                        <th class="text-center">Aprob.</th>
                                        <th class="text-center">Revisión</th>
                                        <th class="text-center">Venc.</th>
                                        <th class="text-center">Entrega</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tareas as $tar): ?>
                                        <tr class="seccion-row">
                                            <td class="fw-semibold small"><?= htmlspecialchars($tar['tipo']) ?></td>
                                            <td class="text-center"><?= $ctrl->badgeEstadoTarea($tar['estado']) ?></td>
                                            <td class="text-center text-success fw-bold"><?= $tar['aprobados'] ?></td>
                                            <td class="text-center text-info"><?= $tar['en_revision'] ?></td>
                                            <td class="text-center">
                                                <?= $tar['vencidos'] > 0 ? "<span class='badge bg-danger'>{$tar['vencidos']}</span>" : '<span class="text-muted">—</span>' ?>
                                            </td>
                                            <td class="text-center text-muted small">
                                                <?= $tar['fecha_entrega'] ? date('d/m/Y', strtotime($tar['fecha_entrega'])) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($tareas)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">Sin tareas activadas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- TARJETAS MÓVIL secciones -->
                    <div class="d-block d-md-none p-2">
                        <?php foreach ($tareas as $tar): ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($tar['tipo']) ?></h6>
                                    <?= $ctrl->badgeEstadoTarea($tar['estado']) ?>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <div class="row text-center">
                                            <div class="col-4"><strong class="text-success">Aprob.</strong>
                                                <p class="mb-0 fw-bold text-success"><?= $tar['aprobados'] ?></p>
                                            </div>
                                            <div class="col-4"><strong class="text-info">Revisión</strong>
                                                <p class="mb-0"><?= $tar['en_revision'] ?></p>
                                            </div>
                                            <div class="col-4"><strong class="text-danger">Venc.</strong>
                                                <p class="mb-0">
                                                    <?= $tar['vencidos'] > 0 ? "<span class='badge bg-danger'>{$tar['vencidos']}</span>" : '—' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item text-center">
                                        <strong>Fecha entrega:</strong>
                                        <?= $tar['fecha_entrega'] ? date('d/m/Y', strtotime($tar['fecha_entrega'])) : '—' ?>
                                    </li>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($tareas)): ?>
                            <p class="text-muted text-center py-3">Sin tareas activadas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Solicitudes del proyecto -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-envelope-paper me-2"></i>Solicitudes de ingreso</span>
                    <span class="badge bg-warning text-dark"><?= count($solicitudes) ?></span>
                </div>
                <div class="card-body p-0">
                    <!-- Tabla desktop -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Carrera</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Semestre</th>
                                        <th class="text-center">Promedio</th>
                                        <th class="text-center">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $sol): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= htmlspecialchars($sol['estudiante_nombre']) ?></div>
                                                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($sol['matricula']) ?></div>
                                            </td>
                                            <td class="small"><?= htmlspecialchars(mb_substr($sol['carrera'], 0, 25)) ?></td>
                                            <td class="text-center"><?= $ctrl->badgeEstadoSolicitud($sol['estado']) ?></td>
                                            <td class="text-center"><?= $sol['semestre'] ?? '—' ?>°</td>
                                            <td class="text-center"><?= $sol['promedio'] ? number_format($sol['promedio'], 1) : '—' ?></td>
                                            <td class="text-center text-muted small">
                                                <?= date('d/m/Y', strtotime($sol['fecha_envio'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($solicitudes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">Sin solicitudes.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- TARJETAS MÓVIL solicitudes -->
                    <div class="d-block d-md-none p-2">
                        <?php foreach ($solicitudes as $sol): ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($sol['estudiante_nombre']) ?></h6>
                                    <p class="text-muted small mb-1"><?= htmlspecialchars($sol['matricula']) ?></p>
                                    <?= $ctrl->badgeEstadoSolicitud($sol['estado']) ?>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <strong>Carrera:</strong> <?= htmlspecialchars(mb_substr($sol['carrera'], 0, 35)) ?>
                                    </li>
                                    <li class="list-group-item">
                                        <div class="row text-center">
                                            <div class="col-4"><strong>Semestre</strong>
                                                <p class="mb-0"><?= $sol['semestre'] ?? '—' ?>°</p>
                                            </div>
                                            <div class="col-4"><strong>Promedio</strong>
                                                <p class="mb-0"><?= $sol['promedio'] ? number_format($sol['promedio'], 1) : '—' ?></p>
                                            </div>
                                            <div class="col-4"><strong>Fecha</strong>
                                                <p class="mb-0"><?= date('d/m/Y', strtotime($sol['fecha_envio'])) ?></p>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($solicitudes)): ?>
                            <p class="text-muted text-center py-3">Sin solicitudes.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalle de Proyecto — Supervisor";
$bodyClass = "supervisor-page";
include __DIR__ . '/../../layout.php';
?>