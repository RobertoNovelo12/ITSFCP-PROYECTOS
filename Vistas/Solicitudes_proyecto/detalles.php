<!--detalles.php - > Página para ver detalles de cada solicitud.-->

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// Solo supervisor e investigador
if (!in_array($rol, ['investigador', 'profesor'])) {
    header("Location: ../proyectos/tabla.php");
    exit;
}

$id_proyecto = intval($_GET['id_proyectos'] ?? 0);
if (!$id_proyecto) {
    header("Location: index.php");
    exit;
}

require_once '../../Controladores/proyectoControlador.php';
$proyectoControlador = new ProyectoControlador();

$proyecto       = $proyectoControlador->datosproyecto($id_proyecto);
$investigador   = $proyectoControlador->datosinvestigador($id_proyecto);
$subtematicas   = $proyectoControlador->subtematicasProyecto($id_proyecto);
$comentarios    = $proyectoControlador->comentarios($id_proyecto);
$estudiantes    = $proyectoControlador->estudiantes($id_proyecto);

$dat_inv         = $investigador['investigador'] ?? [];
$dat_area_inv    = $investigador['area']         ?? [];
$datos_linea_inv = $investigador['lineas']       ?? [];

// Determinar tipo de solicitud según estado
$tipo_solicitud = 'creacion';
if (in_array($proyecto['estado_proyecto'], ['Por cerrar', 'Cierre rechazado'])) {
    $tipo_solicitud = 'cierre';
}

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <div class="col-8">
            <h3 class="mb-0 fw-bold">Detalle de Solicitud</h3>
        </div>
        <div class="col-4 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- RESUMEN DE SOLICITUD -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Tipo de solicitud</div>
                    <?php
                    $tipoLabel = ($tipo_solicitud === 'creacion') ? 'Creación de proyecto' : 'Cierre de proyecto';
                    $tipoBadge = ($tipo_solicitud === 'creacion') ? 'primary' : 'dark';
                    ?>
                    <span class="badge text-bg-<?= $tipoBadge ?> fs-6">
                        <?= $tipoLabel ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Estado actual</div>
                    <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?> fs-6">
                        <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Periodo</div>
                    <strong><?= htmlspecialchars($proyecto['periodo']) ?></strong>
                    <span class="badge text-bg-<?= ($proyecto['estado_periodo'] === 'Activo') ? 'success' : 'secondary' ?> ms-2">
                        <?= htmlspecialchars($proyecto['estado_periodo']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DEL PROYECTO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold">Información del proyecto</div>
        <div class="card-body">

            <h5><?= htmlspecialchars($proyecto['titulo']) ?></h5>
            <p class="text-muted"><?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?></p>

            <hr>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Objetivos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['objetivo'])) ?></dd>

                        <dt>Pre-requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['pre_requisitos'])) ?></dd>

                        <dt>Requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Cantidad alumnos</dt>
                        <dd><?= $proyecto['cantidad_estudiante'] ?></dd>

                        <dt>Temática</dt>
                        <dd><?= htmlspecialchars($proyecto['tematica']) ?></dd>

                        <dt>Modalidad</dt>
                        <dd><?= htmlspecialchars($proyecto['modalidad']) ?></dd>

                        <dt>Presupuesto</dt>
                        <dd>$<?= number_format($proyecto['presupuesto'], 2) ?></dd>

                        <dt>Fecha inicio</dt>
                        <dd><?= $proyecto['fecha_inicio'] ?></dd>

                        <dt>Fecha final</dt>
                        <dd><?= $proyecto['fecha_fin'] ?></dd>

                        <dt>Fecha creación</dt>
                        <dd><?= $proyecto['creado_en'] ?></dd>
                    </dl>
                </div>
            </div>

            <hr>
            <strong>Subtemáticas</strong>
            <div class="mt-2">
                <?php foreach ($subtematicas as $sub): ?>
                    <span class="badge bg-primary me-2 mb-2"><?= htmlspecialchars(trim($sub['nombre'])) ?></span>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- INVESTIGADOR -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold">Investigador responsable</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre completo</dt>
                        <dd><?= htmlspecialchars($dat_inv['nombre'] . ' ' . $dat_inv['apellido_paterno'] . ' ' . $dat_inv['apellido_materno']) ?></dd>

                        <dt>Área de conocimiento</dt>
                        <dd><?= $dat_area_inv['area_conocimiento'] ?: 'No tiene área asignada' ?></dd>

                        <dt>Subárea</dt>
                        <dd><?= $dat_area_inv['subarea'] ?: 'No tiene subárea asignada' ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Nivel SNI</dt>
                        <dd><?= htmlspecialchars($dat_inv['nivel_sni'] ?? '-') ?></dd>

                        <dt>Grado académico</dt>
                        <dd><?= htmlspecialchars($dat_inv['grado_academico'] ?? '-') ?></dd>

                        <dt>Línea de investigación</dt>
                        <dd><?= htmlspecialchars($datos_linea_inv['linea'] ?? '-') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- ESTUDIANTES (solo para solicitudes de cierre) -->
    <?php if ($tipo_solicitud === 'cierre' && !empty($estudiantes)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header fw-semibold">Estudiantes del proyecto</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped text-center mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre completo</th>
                                <th>Carrera</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $alumno): ?>
                                <tr>
                                    <td><?= $alumno['id_usuarios'] ?></td>
                                    <td><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']) ?></td>
                                    <td><?= htmlspecialchars($alumno['carrera']) ?></td>
                                    <td>
                                        <?php
                                        $estEst  = $alumno['estado'] ?? 'activo';
                                        $claseEst = ($estEst === 'baja') ? 'danger' : (($estEst === 'concluido') ? 'success' : 'primary');
                                        ?>
                                        <span class="badge text-bg-<?= $claseEst ?>"><?= strtoupper($estEst) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- COMENTARIOS PREVIOS -->
    <?php if (!empty($comentarios)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header fw-semibold">Historial de observaciones</div>
            <div class="card-body">
                <?php foreach ($comentarios as $com): ?>
                    <div class="border-start border-danger border-3 ps-3 mb-3">
                        <div class="fw-semibold text-danger"><?= htmlspecialchars($com['tipo']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($com['nombre_completo']) ?> &mdash; <?= $com['fecha'] ?></div>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($com['comentario'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ACCIONES DEL SUPERVISOR -->
    <?php if ($rol === 'supervisor'): ?>
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header fw-semibold text-white">Acciones sobre la solicitud</div>
            <div class="card-body">

                <?php if ($tipo_solicitud === 'creacion' && $proyecto['estado_proyecto'] === 'Por aprobar'): ?>
                    <!-- ACCIONES: SOLICITUD DE CREACIÓN -->
                    <p class="mb-3">Esta es una solicitud de <strong>creación de proyecto</strong>. Revise la información y decida:</p>
                    <div class="d-flex gap-3 flex-wrap">

                        <!-- APROBAR CREACIÓN -->
                        <a href="../proyectos/index.php?action=actualizarestado&id_proyectos=<?= $id_proyecto ?>&tipo=Activos"
                           class="btn btn-success btn-lg"
                           onclick="return confirm('¿Confirma que desea APROBAR este proyecto?')">
                            <i class="bi bi-check-circle-fill"></i> Aprobar proyecto
                        </a>

                        <!-- RECHAZAR CREACIÓN -->
                        <a href="../proyectos/comentarios.php?id_proyectos=<?= $id_proyecto ?>&motivo=creacion_rechazada&desde=solicitudes"
                           class="btn btn-danger btn-lg">
                            <i class="bi bi-x-circle-fill"></i> Rechazar proyecto
                        </a>

                    </div>

                <?php elseif ($tipo_solicitud === 'cierre' && $proyecto['estado_proyecto'] === 'Por cerrar'): ?>
                    <!-- ACCIONES: SOLICITUD DE CIERRE -->
                    <p class="mb-3">Esta es una solicitud de <strong>cierre de proyecto</strong>. Revise el avance y decida:</p>
                    <div class="d-flex gap-3 flex-wrap">

                        <!-- APROBAR CIERRE -->
                        <a href="../proyectos/index.php?action=actualizarestado&id_proyectos=<?= $id_proyecto ?>&tipo=Cierre"
                           class="btn btn-success btn-lg"
                           onclick="return confirm('¿Confirma que desea APROBAR el cierre de este proyecto?')">
                            <i class="bi bi-check-circle-fill"></i> Aprobar cierre
                        </a>

                        <!-- RECHAZAR CIERRE -->
                        <a href="../proyectos/comentarios.php?id_proyectos=<?= $id_proyecto ?>&motivo=cierre_rechazado&desde=solicitudes"
                           class="btn btn-danger btn-lg">
                            <i class="bi bi-x-circle-fill"></i> Rechazar cierre
                        </a>

                    </div>

                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Esta solicitud ya fue procesada. Estado actual:
                        <strong><?= htmlspecialchars($proyecto['estado_proyecto']) ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Ver tareas (disponible siempre para supervisor en cierre) -->
                <?php if ($tipo_solicitud === 'cierre'): ?>
                    <hr>
                    <a href="../Tareas/tabla.php?id_proyectos=<?= $id_proyecto ?>" class="btn btn-info">
                        <i class="bi bi-list-task"></i> Ver tareas del proyecto
                    </a>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

    <!-- ACCIONES DEL INVESTIGADOR (historial propio) -->
    <?php if (in_array($rol, ['investigador', 'profesor'])): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header fw-semibold">Estado de tu solicitud</div>
            <div class="card-body">
                <?php if ($proyecto['estado_proyecto'] === 'Por aprobar'): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-hourglass-split"></i> Solicitud <strong>en revisión</strong>. Espera la respuesta del supervisor.
                    </div>
                <?php elseif ($proyecto['estado_proyecto'] === 'Activo'): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> Solicitud <strong>aprobada</strong>. Proyecto activo.
                    </div>
                <?php elseif ($proyecto['estado_proyecto'] === 'Rechazado'): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle"></i> Solicitud <strong>rechazada</strong>. Revisa los comentarios del supervisor.
                    </div>
                <?php elseif ($proyecto['estado_proyecto'] === 'Por cerrar'): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-hourglass-split"></i> Solicitud de cierre <strong>en revisión</strong>.
                    </div>
                <?php elseif ($proyecto['estado_proyecto'] === 'Cierre rechazado'): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle"></i> Solicitud de cierre <strong>rechazada</strong>. Revisa los comentarios.
                    </div>
                <?php else: ?>
                    <p class="mb-0">Estado: <strong><?= htmlspecialchars($proyecto['estado_proyecto']) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Detalle de Solicitud";
$bodyClass = "solicitudes-page";
include __DIR__ . '/../../layout.php';
?>