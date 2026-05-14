<?php

/**
 * detalle_estudiante.php
 * Vista de detalle de un estudiante — exclusivo supervisor, solo lectura.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
$rol        = strtolower($_SESSION['rol'] ?? '');

if (strtolower($_SESSION['rol'] ?? '') !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/SupervisorControlador.php';
$ctrl = new SupervisorControlador();
$data = $ctrl->detalleEstudiante();

extract($data);  // $usuario, $proyectos, $tareas, $solicitudes

if (empty($usuario)) {
    header("Location: panel_supervisor.php?tab=usuarios");
    exit;
}

$tareas_por_proyecto = [];
foreach ($tareas as $t) {
    $tareas_por_proyecto[$t['id_proyectos']][] = $t;
}

$total_tareas     = count($tareas);
$tareas_aprobadas = count(array_filter($tareas, fn($t) => $t['estado'] === 'Aprobado'));
$tareas_vencidas  = count(array_filter($tareas, fn($t) => $t['estado'] === 'Vencido'));
$pct_global       = $total_tareas > 0 ? round(($tareas_aprobadas / $total_tareas) * 100) : 0;
$color_global     = $pct_global >= 80 ? 'success' : ($pct_global >= 40 ? 'warning' : 'danger');

// Paginación de proyectos en móvil (client-side por JS)
ob_start();
?>

<div cclass="container-fluid py-4" style="max-width:95%;">

    <!-- ENCABEZADO -->
    <div class="row align-items-center mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="panel_supervisor.php">Panel</a></li>
                    <li class="breadcrumb-item"><a href="panel_supervisor.php?tab=usuarios">Estudiantes</a></li>
                    <li class="breadcrumb-item active">Detalle</li>
                </ol>
            </nav>
            <h4 class="mb-0 fw-bold">
                <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']) ?>
            </h4>
            <span class="text-muted small">
                <?= htmlspecialchars($usuario['matricula']) ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($usuario['carrera']) ?>
            </span>
        </div>
        <div class="col-auto">
            <?= $ctrl->badgeEstadoUsuario($usuario['estado_usuario']) ?>
            <a href="panel_supervisor.php?tab=usuarios" class="btn btn-secondary btn-sm ms-2">
                <i class="bi bi-arrow-left me-1"></i>Regresar
            </a>
        </div>
    </div>

    <!-- MÉTRICAS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
                <div class="fs-3 fw-bold text-primary"><?= count($proyectos) ?></div>
                <div class="small text-muted">Proyectos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
                <div class="fs-3 fw-bold text-success"><?= $tareas_aprobadas ?></div>
                <div class="small text-muted">Tareas aprobadas</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
                <div class="fs-3 fw-bold text-danger"><?= $tareas_vencidas ?></div>
                <div class="small text-muted">Tareas vencidas</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-light text-center py-3">
                <div class="fs-3 fw-bold text-<?= $color_global ?>"><?= $pct_global ?>%</div>
                <div class="small text-muted">Avance global</div>
            </div>
        </div>
    </div>

    <!-- BARRA PROGRESO GLOBAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Avance total del estudiante</span>
                <span class="small text-muted"><?= $tareas_aprobadas ?> / <?= $total_tareas ?> tareas aprobadas</span>
            </div>
            <div class="progress progress-lg">
                <div class="progress-bar bg-<?= $color_global ?>" style="width:<?= $pct_global ?>%"><?= $pct_global ?>%</div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUMNA IZQUIERDA -->
        <div class="col-md-4">

            <!-- Datos personales -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-person-circle me-2 text-primary"></i>Datos personales
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center"
                            style="width:64px;height:64px">
                            <i class="bi bi-person-fill fs-2 text-primary"></i>
                        </div>
                    </div>
                    <dl class="info-dl row mb-0">
                        <div class="col-12">
                            <dt>Correo institucional</dt>
                            <dd><?= htmlspecialchars($usuario['correo_institucional']) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Matrícula</dt>
                            <dd><?= htmlspecialchars($usuario['matricula']) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Género</dt>
                            <dd><?= htmlspecialchars($usuario['genero'] ?? '—') ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Fecha nacimiento</dt>
                            <dd><?= date('d/m/Y', strtotime($usuario['fecha_nacimiento'])) ?></dd>
                        </div>
                        <div class="col-6">
                            <dt>Teléfono</dt>
                            <dd><?= htmlspecialchars($usuario['telefono']) ?></dd>
                        </div>
                        <div class="col-12">
                            <dt>Carrera</dt>
                            <dd><?= htmlspecialchars($usuario['carrera']) ?></dd>
                        </div>
                        <div class="col-12">
                            <dt>Fecha de registro</dt>
                            <dd><?= date('d/m/Y H:i', strtotime($usuario['fecha_registro'])) ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Solicitudes del estudiante -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-envelope-paper me-2 text-warning"></i>Solicitudes enviadas</span>
                    <span class="badge bg-warning text-dark"><?= count($solicitudes) ?></span>
                </div>
                <div class="card-body" style="max-height:260px;overflow-y:auto">
                    <?php if (!empty($solicitudes)): ?>
                        <ul class="timeline">
                            <?php foreach ($solicitudes as $sol):
                                $dotMap = ['aceptado' => 'verde', 'rechazado' => 'rojo', 'pendiente' => 'gris', 'en_revision' => 'azul', 'correcciones' => 'naranja'];
                                $dot = $dotMap[$sol['estado']] ?? 'gris';
                            ?>
                                <li class="tl-item">
                                    <span class="tl-dot <?= $dot ?>"></span>
                                    <div class="tl-content">
                                        <div class="d-flex justify-content-between">
                                            <span class="small fw-semibold">
                                                <?= htmlspecialchars(mb_substr($sol['proyecto_titulo'], 0, 35)) ?>…
                                            </span>
                                            <?= $ctrl->badgeEstadoSolicitud($sol['estado']) ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <?= date('d/m/Y', strtotime($sol['fecha_envio'])) ?>
                                            <?php if ($sol['semestre']): ?> · Semestre <?= $sol['semestre'] ?>°<?php endif; ?>
                                                <?php if ($sol['promedio']): ?> · Promedio <?= number_format($sol['promedio'], 1) ?><?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted text-center small">Sin solicitudes registradas.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA -->
        <div class="col-md-8">

            <!-- Proyectos del estudiante -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-folder2-open me-2 text-success"></i>Proyectos en los que ha participado
                    <span class="badge bg-success float-end"><?= count($proyectos) ?></span>
                </div>
                <div class="card-body">
                    <?php foreach ($proyectos as $proy):
                        $tot_pt  = max(1, $proy['tareas_total']);
                        $aprob_pt = (int)$proy['tareas_aprobadas'];
                        $pct_pt  = round(($aprob_pt / $tot_pt) * 100);
                        $col_pt  = $pct_pt >= 80 ? 'success' : ($pct_pt >= 40 ? 'warning' : 'danger');
                        $claseProy = $proy['estado_participacion'];
                    ?>
                        <div class="proy-card <?= $claseProy ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($proy['titulo']) ?></div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($proy['investigador_nombre']) ?>
                                        &nbsp;·&nbsp;
                                        <i class="bi bi-calendar2 me-1"></i>Periodo <?= htmlspecialchars($proy['periodo']) ?>
                                        &nbsp;·&nbsp;
                                        <span class="text-capitalize"><?= $proy['modalidad'] ?></span>
                                        &nbsp;·&nbsp;
                                        <?= $ctrl->badgeEstadoEtapa($usuario['estado_proceso']) ?>

                                    </div>
                                </div>
                                <div class="text-end">
                                    <?= $ctrl->badgeEstadoProyecto($proy['estado_proyecto']) ?>
                                    <br>
                                    <span class="small text-muted"><?= $ctrl->badgeParticipacion($proy['estado_participacion']) ?></span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <small>Avance en este proyecto</small>
                                <small><?= $aprob_pt ?>/<?= $proy['tareas_total'] ?> tareas aprobadas</small>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-<?= $col_pt ?>" style="width:<?= $pct_pt ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">
                                    Ingresó: <?= date('d/m/Y', strtotime($proy['fecha_asignacion'])) ?>
                                    <?php if ($proy['fecha_terminacion']): ?>
                                        · Finalizó: <?= date('d/m/Y', strtotime($proy['fecha_terminacion'])) ?>
                                    <?php endif; ?>
                                </small>
                                <a href="detalle_proyecto.php?id=<?= $proy['id_proyectos'] ?>"
                                    class="btn btn-outline-secondary btn-sm py-0">
                                    <i class="bi bi-eye me-1"></i>Ver proyecto
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($proyectos)): ?>
                        <p class="text-muted text-center small">Sin proyectos asignados.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial de tareas por proyecto -->
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-list-check me-2 text-info"></i>Historial de tareas por proyecto
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($tareas_por_proyecto)): ?>
                        <?php foreach ($tareas_por_proyecto as $id_p => $tareasP):
                            $nombreP = '—';
                            foreach ($proyectos as $pp) {
                                if ($pp['id_proyectos'] == $id_p) {
                                    $nombreP = $pp['titulo'];
                                    break;
                                }
                            }
                            $aprob_p = count(array_filter($tareasP, fn($t) => $t['estado'] === 'Aprobado'));
                            $total_p = count($tareasP);
                            $pct_p   = $total_p > 0 ? round(($aprob_p / $total_p) * 100) : 0;
                            $col_p   = $pct_p >= 80 ? 'success' : ($pct_p >= 40 ? 'warning' : 'danger');
                        ?>
                            <div class="px-3 pt-3 pb-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold small">
                                        <?= htmlspecialchars(mb_substr($nombreP, 0, 60)) ?><?= strlen($nombreP) > 60 ? '…' : '' ?>
                                    </span>
                                    <span class="small text-muted"><?= $aprob_p ?>/<?= $total_p ?></span>
                                </div>
                                <div class="progress mb-2" style="height:5px">
                                    <div class="progress-bar bg-<?= $col_p ?>" style="width:<?= $pct_p ?>%"></div>
                                </div>

                                <!-- TABLA DESKTOP -->
                                <div class="d-none d-md-block">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="small">Sección</th>
                                                <th class="text-center small">Estado</th>
                                                <th class="text-center small">Entrega</th>
                                                <th class="small">Contenido</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tareasP as $t):
                                                $hasCont = !empty(trim($t['contenido'] ?? ''));
                                            ?>
                                                <tr class="tarea-row">
                                                    <td><?= htmlspecialchars($t['seccion']) ?></td>
                                                    <td class="text-center"><?= $ctrl->badgeEstadoTarea($t['estado']) ?></td>
                                                    <td class="text-center text-muted">
                                                        <?= $t['fecha_entrega'] ? date('d/m/Y', strtotime($t['fecha_entrega'])) : '—' ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($hasCont): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                                <i class="bi bi-check2 me-1"></i>Enviado
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Sin envío</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- TARJETAS MÓVIL tareas -->
                                <div class="d-block d-md-none mt-2">
                                    <?php foreach ($tareasP as $t):
                                        $hasCont = !empty(trim($t['contenido'] ?? ''));
                                    ?>
                                        <div class="card shadow-sm mb-2">
                                            <div class="card-body text-center py-2">
                                                <strong class="small"><?= htmlspecialchars($t['seccion']) ?></strong><br>
                                                <?= $ctrl->badgeEstadoTarea($t['estado']) ?>
                                            </div>
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item text-center small">
                                                    <strong>Entrega:</strong>
                                                    <?= $t['fecha_entrega'] ? date('d/m/Y', strtotime($t['fecha_entrega'])) : '—' ?>
                                                    &nbsp;·&nbsp;
                                                    <?php if ($hasCont): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success">
                                                            <i class="bi bi-check2"></i> Enviado
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin envío</span>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Sin tareas registradas.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalle Estudiante — Supervisor";
$bodyClass = "supervisor-page";
include __DIR__ . '/../../layout.php';
?>