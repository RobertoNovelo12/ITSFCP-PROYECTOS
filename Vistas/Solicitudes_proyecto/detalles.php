<!--Solicitudes_proyecto/detalles.php - Página para ver detalles de cada solicitud.-->

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

// Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

$id_proyecto = intval($_GET['id_proyectos'] ?? 0);

//Validación de argumentos en url
$id_validar = $id_proyecto;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';


require_once __DIR__ . '/../../Controladores/solicitudes_proyectoControlador.php';
$SolicitudesProyectoControlador = new SolicitudesProyectoControlador();

$proyecto = $SolicitudesProyectoControlador->datosproyecto($id_proyecto);

// Validación
$registro = $proyecto;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$investigador = $SolicitudesProyectoControlador->datosinvestigador($id_proyecto);

// Validación
$registro = $investigador;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$subtematicas = $SolicitudesProyectoControlador->subtematicasProyecto($id_proyecto);

// Validación
$registro = $subtematicas;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


$comentarios    = $SolicitudesProyectoControlador->comentarios($id_proyecto);
$estudiantes    = $SolicitudesProyectoControlador->estudiantes($id_proyecto);

$dat_inv         = $investigador['investigador'] ?? [];
$dat_area_inv    = $investigador['area']         ?? [];
$datos_linea_inv = $investigador['lineas']       ?? [];

// Determinar tipo de solicitud según estado
$tipo_solicitud = 'creacion';
if (in_array($proyecto['estado_proyecto'] ?? '', ['Por cerrar', 'Cierre rechazado'])) {
    $tipo_solicitud = 'cierre';
}

// Procesar aprobación via GET cuando viene desde los botones de esta misma vista
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'actualizarestado') {
    $error = $SolicitudesProyectoControlador->actualizarestado(
        $id_proyecto,
        $rol,
        $_GET['tipo'] ?? ''
    );
}

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-1">

        <?php
        $titulo      = 'Detalle de Solicitud de Proyecto';
        $descripcion = 'Información de la solicitud seleccionada';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 text-end">
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
                    <span class="badge text-bg-<?= $SolicitudesProyectoControlador->EstiloEstado($proyecto['estado_proyecto'] ?? '') ?> fs-6">
                        <?= htmlspecialchars($proyecto['estado_proyecto'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Periodo</div>
                    <strong><?= htmlspecialchars($proyecto['periodo'] ?? '') ?></strong>
                    <span class="badge text-bg-<?= ($proyecto['estado_periodo'] === 'Activo') ? 'success' : 'secondary' ?> ms-2">
                        <?= htmlspecialchars($proyecto['estado_periodo'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DEL PROYECTO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-info-circle me-2"></i> Información del proyecto</div>
        <div class="card-body">

            <h5><?= htmlspecialchars($proyecto['titulo'] ?? '') ?></h5>
            <p class="text-muted"><?= nl2br(htmlspecialchars($proyecto['descripcion'] ?? '')) ?></p>

            <hr>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Objetivos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['objetivo'] ?? '')) ?></dd>

                        <dt>Pre-requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['pre_requisitos'] ?? '')) ?></dd>

                        <dt>Requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['requisitos'] ?? '')) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Cantidad alumnos</dt>
                        <dd><?= $proyecto['cantidad_estudiante'] ?? '' ?></dd>

                        <dt>Temática</dt>
                        <dd><?= htmlspecialchars($proyecto['tematica'] ?? '') ?></dd>

                        <dt>Modalidad</dt>
                        <dd><?= htmlspecialchars($proyecto['modalidad'] ?? '') ?></dd>

                        <dt>Presupuesto</dt>
                        <dd>$<?= number_format($proyecto['presupuesto'] ?? 0, 2) ?></dd>

                        <dt>Fecha inicio</dt>
                        <dd><?= $proyecto['fecha_inicio'] ?? '' ?></dd>

                        <dt>Fecha final</dt>
                        <dd><?= $proyecto['fecha_fin'] ?? '' ?></dd>

                        <dt>Fecha creación</dt>
                        <dd><?= $proyecto['creado_en'] ?? '' ?></dd>
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
        <div class="card-header fw-semibold"><i class="bi bi-person-badge me-2"></i> Investigador responsable</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre completo</dt>
                        <dd><?= htmlspecialchars(($dat_inv['nombre'] ?? '') . ' ' . ($dat_inv['apellido_paterno'] ?? '') . ' ' . ($dat_inv['apellido_materno'] ?? '')) ?></dd>

                        <dt>Área de conocimiento</dt>
                        <dd><?= $dat_area_inv['area_conocimiento'] ?? 'No tiene área asignada' ?></dd>

                        <dt>Subárea</dt>
                        <dd><?= $dat_area_inv['subarea'] ?? 'No tiene subárea asignada' ?></dd>
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
            <div class="card-header fw-semibold"><i class="bi bi-people-fill"></i> Estudiantes del proyecto</div>
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
                                    <td><?= htmlspecialchars(($alumno['nombre'] ?? '') . ' ' . ($alumno['apellido_paterno'] ?? '') . ' ' . ($alumno['apellido_materno'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($alumno['carrera'] ?? '') ?></td>
                                    <td>
                                        <?php
                                        $estEst   = $alumno['estado'] ?? 'activo';
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
            <div class="card-header fw-semibold">
                <i class="bi bi-clock-history me-2"></i> <b>Historial de observaciones</b>
            </div>
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
            <div class="card-header fw-semibold"><i class="bi bi-gear-fill"></i> Acciones sobre la solicitud</div>
            <div class="card-body">

                <?php if ($tipo_solicitud === 'creacion' && ($proyecto['estado_proyecto'] ?? '') === 'Por aprobar'): ?>
                    <p class="mb-3">Esta es una solicitud de <strong>creación de proyecto</strong>. Revise la información y decida:</p>
                    <div class="d-flex gap-3 flex-wrap">

                        <!-- APROBAR CREACIÓN — apunta a index.php del mismo módulo -->
                        <a href="index.php?action=actualizarestado&id_proyectos=<?= $id_proyecto ?>&tipo=Activos"
                            class="btn btn-success btn-lg"
                            onclick="return confirm('¿Confirma que desea APROBAR este proyecto?')">
                            <i class="bi bi-check-circle-fill"></i> Aprobar proyecto
                        </a>

                        <!-- RECHAZAR CREACIÓN -->
                        <a href="comentarios.php?id_proyectos=<?= $id_proyecto ?>&motivo=creacion_rechazada&desde=solicitudes"
                            class="btn btn-danger btn-lg">
                            <i class="bi bi-x-circle-fill"></i> Rechazar proyecto
                        </a>

                    </div>

                <?php elseif ($tipo_solicitud === 'cierre' && ($proyecto['estado_proyecto'] ?? '') === 'Por cerrar'): ?>
                    <p class="mb-3">Esta es una solicitud de <strong>cierre de proyecto</strong>. Revise el avance y decida:</p>
                    <div class="d-flex gap-3 flex-wrap">

                        <!-- APROBAR CIERRE — apunta a index.php del mismo módulo -->
                        <a href="index.php?action=actualizarestado&id_proyectos=<?= $id_proyecto ?>&tipo=Cierre"
                            class="btn btn-success btn-sm"
                            onclick="return confirm('¿Confirma que desea APROBAR el cierre de este proyecto?')">
                            <i class="bi bi-check-circle-fill"></i> Aprobar cierre
                        </a>

                        <!-- RECHAZAR CIERRE -->
                        <a href="comentarios.php?id_proyectos=<?= $id_proyecto ?>&motivo=cierre_rechazado&desde=solicitudes"
                            class="btn btn-danger btn-sm">
                            <i class="bi bi-x-circle-fill"></i> Rechazar cierre
                        </a>

                    </div>

                <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                        Esta solicitud ya fue procesada. Estado actual:
                        <strong><?= htmlspecialchars($proyecto['estado_proyecto'] ?? '') ?></strong>
                    </div>
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