<?php
//detalles_solicitud.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo investigador o profesor pueden acceder
if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/solicitudesControlador.php';

$ctrl         = new solicitudesControlador();


$id_solicitud = intval($_GET['id'] ?? 0);



//  Acción directa: aceptar desde esta página ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'aceptar') {
    $ctrl->aceptar($id_solicitud, $id_usuario, $rol);
    // aceptar() redirige internamente
}

$data = $ctrl->detallePagina($id_solicitud, $id_usuario, $rol);

// Validación
$registro = $data;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$sol         = $data['solicitud'];
$comentarios = $data['comentarios'];

$id_proyecto   = intval($sol['id_proyectos']);
$id_estudiante = intval($sol['id_estudiante']);

$seg           = $ctrl->DatosSeguimientoEstudiante($id_proyecto, $id_estudiante, $id_usuario);
$e1_estado     = $seg['e1_estado'];
$e2_estado     = $seg['e2_estado'];
$e3_estado     = $seg['e3_estado'];
$fase2_ok      = $seg['fase2_ok'];
$id_seg_cierre = $seg['id_seguimiento_cierre'];
$documentos    = $seg['documentos'];

// Mensaje de éxito o error pasado por GET tras redirección
$msg_ok  = htmlspecialchars($_GET['ok']    ?? '');
$msg_err = htmlspecialchars($_GET['error'] ?? '');

function badgeSeg(string $estado): string
{
    $map = [
        'pendiente'    => ['bg-secondary',        'Pendiente'],
        'proceso'      => ['bg-primary',           'En revisión'],
        'completado'   => ['bg-success',           'Aprobado'],
        'rechazado'    => ['bg-danger',            'Rechazado'],
        'correcciones' => ['bg-warning text-dark', 'Correcciones'],
        'aceptado'     => ['bg-success',           'Aceptado'],
        'en_revision'  => ['bg-info text-dark',    'En revisión'],
        'vencido'      => ['bg-dark',              'Vencido'],
    ];
    [$cls, $txt] = $map[$estado] ?? ['bg-secondary', $estado];
    return "<span class='badge {$cls}'>{$txt}</span>";
}

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Detalles de la solicitud de Integración a Proyecto';
        $descripcion = 'Información de la solicitud seleccionada';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <?php if ($msg_ok): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= $msg_ok ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg_err ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PANEL DE 3 ETAPAS -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header text-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Seguimiento del estudiante</h5>
        </div>
        <div class="card-body p-0">
            <div class="row g-0">

                <!-- ETAPA 1 -->
                <div class="col-md-4 border-end p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">1. Solicitud de integración</strong>
                        <?= badgeSeg($e1_estado) ?>
                    </div>
                    <p class="text-muted small mb-2">Carta Compromiso firmada y aceptación del investigador.</p>

                    <?php if (in_array($e1_estado, ['pendiente', 'en_revision', 'correcciones', 'proceso'])): ?>
                        <div class="d-flex gap-1 flex-wrap mt-2">
                            <!-- Aceptar: POST directo con confirmación JS mínima -->
                            <form method="POST"
                                action="detalles_solicitud.php?id=<?= $id_solicitud ?>"
                                onsubmit="return confirm('¿Confirmar aceptación? El estudiante quedará integrado al proyecto.')">
                                <input type="hidden" name="accion" value="aceptar">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg"></i> Aceptar
                                </button>
                            </form>
                            <!-- Correcciones / Rechazar: van a página dedicada -->
                            <a href="accion_solicitud.php?id=<?= $id_solicitud ?>&tipo=correcciones"
                                class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil"></i> Correcciones
                            </a>
                            <a href="accion_solicitud.php?id=<?= $id_solicitud ?>&tipo=rechazar"
                                class="btn btn-danger btn-sm">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </a>
                        </div>
                    <?php elseif ($e1_estado === 'aceptado'): ?>
                        <div class="alert alert-success py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i> Integración aceptada.
                        </div>
                    <?php elseif ($e1_estado === 'rechazado'): ?>
                        <div class="alert alert-danger py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-ban"></i> Solicitud rechazada.
                        </div>
                    <?php elseif ($e1_estado === 'vencido'): ?>
                        <div class="alert alert-secondary py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-clock"></i> Solicitud vencida.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ETAPA 2 -->
                <div class="col-md-4 border-end p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">2. Desarrollo del documento</strong>
                        <?= badgeSeg($e2_estado) ?>
                    </div>
                    <p class="text-muted small mb-1">
                        Secciones completadas y aprobadas automáticamente por el sistema.
                    </p>
                    <div class="text-muted small mb-2">
                        <?= $seg['actividades_aprobadas'] ?> / <?= $seg['actividades_total'] ?> actividades aprobadas
                    </div>
                    <?php if ($fase2_ok): ?>
                        <div class="alert alert-success py-1 px-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i>
                            Todas las secciones aprobadas. La carta de terminación está disponible.
                        </div>
                    <?php else: ?>
                        <?php
                        $pct = $seg['actividades_total'] > 0
                            ? round(($seg['actividades_aprobadas'] / $seg['actividades_total']) * 100)
                            : 0;
                        ?>
                        <div style="height:6px;background:#e9ecef;border-radius:4px;overflow:hidden">
                            <div style="height:100%;background:#198754;border-radius:4px;width:<?= $pct ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ETAPA 3 -->
                <div class="col-md-4 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">3. Cierre del proyecto</strong>
                        <?= badgeSeg($e3_estado) ?>
                    </div>
                    <p class="text-muted small mb-2">Reporte Final aprobado y Carta de Terminación.</p>

                    <?php if ($fase2_ok && $id_seg_cierre && in_array($e3_estado, ['pendiente', 'proceso', 'correcciones'])): ?>
                        <div class="d-flex gap-1 flex-wrap mt-2">
                            <a href="accion_cierre.php?id_seg=<?= $id_seg_cierre ?>&id_sol=<?= $id_solicitud ?>&estado=completado"
                                class="btn btn-success btn-sm">
                                <i class="bi bi-check-lg"></i> Aprobar
                            </a>
                            <a href="accion_cierre.php?id_seg=<?= $id_seg_cierre ?>&id_sol=<?= $id_solicitud ?>&estado=correcciones"
                                class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil"></i> Correcciones
                            </a>
                            <a href="accion_cierre.php?id_seg=<?= $id_seg_cierre ?>&id_sol=<?= $id_solicitud ?>&estado=rechazado"
                                class="btn btn-danger btn-sm">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </a>
                        </div>
                    <?php elseif (!$fase2_ok): ?>
                        <div class="text-muted small">
                            <i class="bi bi-lock"></i> Disponible al completar el desarrollo.
                        </div>
                    <?php elseif ($e3_estado === 'completado'): ?>
                        <div class="alert alert-success py-1 px-2 mt-2 small mb-0">
                            <i class="bi bi-check-circle-fill"></i> Cierre aprobado. Proyecto finalizado.
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- DOCUMENTOS DEL ESTUDIANTE -->
    <?php if (!empty($documentos)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header text-white">
                <h5 class="mb-0"><i class="bi bi-paperclip me-2"></i>Documentos subidos por el estudiante</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($documentos as $doc): ?>
                        <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($doc['ruta']) ?>"
                            target="_blank"
                            class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            <?= htmlspecialchars($doc['nombre']) ?>
                            <?php if (!empty($doc['tipo_nombre'])): ?>
                                <span class="text-muted small">(<?= htmlspecialchars($doc['tipo_nombre']) ?>)</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CARTA COMPROMISO -->
    <?php if (!empty($sol['carta_ruta'])): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check me-2"></i>Carta compromiso</h5>
            </div>
            <div class="card-body">
                <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($sol['carta_ruta']) ?>"
                    target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i>
                    <?= htmlspecialchars($sol['carta_nombre'] ?? 'Descargar carta') ?>
                    <?php if (!empty($sol['carta_extension'])): ?>
                        <span class="text-muted small">(.<?= htmlspecialchars($sol['carta_extension']) ?>)</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- DATOS DEL ESTUDIANTE -->
    <div class="card shadow-sm mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0">Información del estudiante</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $campos = [
                    'Nombre'    => $sol['estudiante_nombre'],
                    'Matrícula' => $sol['matricula']           ?? '—',
                    'Correo'    => $sol['correo_institucional'],
                    'Carrera'   => $sol['carrera'],
                    'Semestre'  => isset($sol['semestre']) ? $sol['semestre'] . '°' : '—',
                    'Promedio'  => $sol['promedio']            ?? '—',
                ];
                foreach ($campos as $lbl => $val): ?>
                    <div class="col-md-6">
                        <dl class="mb-2">
                            <dt class="small text-muted"><?= $lbl ?></dt>
                            <dd><?= htmlspecialchars((string)$val) ?></dd>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- PROYECTO -->
    <div class="card shadow-sm mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0">Proyecto solicitado</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Título</dt>
                        <dd><?= htmlspecialchars($sol['proyecto_titulo']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Estado de la solicitud</dt>
                        <dd><?= $ctrl->badgeEstado($sol['estado']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Fecha de solicitud</dt>
                        <dd><?= $sol['fecha_envio'] ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="mb-2">
                        <dt class="small text-muted">Modalidad</dt>
                        <dd><?= htmlspecialchars($sol['modalidad'] ?? '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- MOTIVACIÓN Y EXPERIENCIA -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header text-white">
                    <h5 class="mb-0">Motivación</h5>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($sol['motivacion'] ?? 'Sin información')) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header text-white">
                    <h5 class="mb-0">Experiencia</h5>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($sol['experiencia'] ?? 'Sin información')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HISTORIAL DE COMENTARIOS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0">Historial de comentarios</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($comentarios)): ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="border rounded p-3 mb-2 <?= $c['tipo'] === 'investigador' ? 'bg-light' : 'bg-white' ?>">
                        <p class="mb-1"><?= nl2br(htmlspecialchars($c['comentario'])) ?></p>
                        <?php if (!empty($c['archivo_nombre'])): ?>
                            <a href="/ITSFCP-PROYECTOS/<?= htmlspecialchars($c['archivo_ruta']) ?>"
                                target="_blank" class="small text-primary">
                                <i class="bi bi-paperclip"></i> <?= htmlspecialchars($c['archivo_nombre']) ?>
                            </a>
                        <?php endif; ?>
                        <div class="text-muted small mt-1">
                            <span class="badge <?= $c['tipo'] === 'investigador' ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= $c['tipo'] === 'investigador' ? 'Investigador' : 'Estudiante' ?>
                            </span>
                            <?= htmlspecialchars($c['fecha']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Sin comentarios aún.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACCIONES RÁPIDAS -->
    <?php if (in_array($sol['estado'], ['pendiente', 'en_revision', 'correcciones'])): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Acciones sobre la solicitud</h5>
            </div>
            <div class="card-body d-flex gap-2 flex-wrap">
                <form method="POST"
                    action="detalles_solicitud.php?id=<?= $id_solicitud ?>"
                    onsubmit="return confirm('¿Confirmar aceptación? El estudiante quedará integrado al proyecto.')">
                    <input type="hidden" name="accion" value="aceptar">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle-fill"></i> Aceptar
                    </button>
                </form>
                <a href="accion_solicitud.php?id=<?= $id_solicitud ?>&tipo=correcciones"
                    class="btn btn-warning">
                    <i class="bi bi-pencil-fill"></i> Pedir correcciones
                </a>
                <a href="accion_solicitud.php?id=<?= $id_solicitud ?>&tipo=rechazar"
                    class="btn btn-danger">
                    <i class="bi bi-ban"></i> Rechazar
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Detalle de solicitud';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>