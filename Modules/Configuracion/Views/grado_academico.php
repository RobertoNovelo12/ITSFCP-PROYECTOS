<?php
require_once __DIR__ . '/../Controller/solicitud_grado_controller.php';

session_start();
$id_usuario = $_SESSION['id_usuario'] ?? 0;
if (!$id_usuario) { header("Location: login.php"); exit; }

//Solo investigador puede acceder
if (!in_array(strtolower($_SESSION['rol']), ['investigador', 'profesor', 'supervisor'], true)) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

$ctrl = new SolicitudGradoControlador();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_grado') {
    $ctrl->crearSolicitud($id_usuario);
    exit;
}

$datos     = $ctrl->datosInvestigador($id_usuario);
$inv       = $datos['investigador'];
$grados    = $datos['grados'];
$pendiente = $datos['pendiente_grado'];
$historial = $ctrl->historialInvestigador($id_usuario);

$timeline_configuracion  = $historial['datos'];
$pagina    = $historial['paginacion'];

$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

ob_start();
include __DIR__ . '/../../../mensaje.php';
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- HEADER -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Configuración de Grado Académico';
        $descripcion = 'Solicita cambios y revisa el historial';
        include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_encabezado.php';
        ?>
        <div class="col-16 col-md-6 text-md-end">
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if ($msg === 'enviado'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Solicitud enviada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars(urldecode($error)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- INFO -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><b>Información del investigador</b></div>
        <div class="card-body">

            <div class="row g-3">
                <div class="col-md-6">
                    <small class="text-muted">Nombre</small>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($inv['nombre'] . ' ' . $inv['apellido_paterno'] . ' ' . ($inv['apellido_materno'] ?? '')) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <small class="text-muted">Grado actual</small>
                    <div>
                        <?php if (!empty($inv['grado_nombre'])): ?>
                            <span class="badge text-bg-success">
                                <?= htmlspecialchars($inv['grado_nombre']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">No asignado</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- FORMULARIO -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><b>Solicitar cambio de grado</b></div>
        <div class="card-body">

            <?php if ($pendiente): ?>
                <div class="alert alert-warning mb-0">
                    Ya tienes una solicitud en revisión.
                </div>
            <?php else: ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="crear_grado">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nuevo grado</label>
                            <select class="form-select" name="valor_nuevo_id" required>
                                <option value="">Selecciona</option>
                                <?php foreach ($grados as $g): ?>
                                    <option value="<?= $g['id_grado'] ?>"
                                        <?= (int)$g['id_grado'] === (int)$inv['id_grado'] ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($g['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Documento (PDF)</label>
                            <input type="file" class="form-control" name="documento" required>
                        </div>

                    </div>

                    <div class="pt-3">
                        <button class="btn btn-primary px-4">
                            Enviar solicitud
                        </button>
                    </div>

                </form>

            <?php endif; ?>

        </div>
    </div>

    <!-- HISTORIAL -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <p class="fw-semibold mb-3">Historial de solicitudes</p>

            <?php if (empty($timeline_configuracion)): ?>
                <p class="text-muted text-center">Sin registros</p>
            <?php else: ?>

                <?php foreach ($timeline_configuracion as $fecha => $eventos): ?>

                    <small class="text-muted d-block mb-2"><?= $fecha ?></small>

                    <?php foreach ($eventos as $ev): ?>
                        <?php
                            $est = strtolower($ev['estado_nuevo']);
                            $color = match($est) {
                                'aprobado' => 'success',
                                'rechazado' => 'danger',
                                default => 'warning'
                            };
                        ?>

                        <div class="border rounded p-2 mb-2">

                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <span class="badge text-bg-secondary">
                                        <?= htmlspecialchars($ev['valor_actual_nombre'] ?? '—') ?>
                                    </span>
                                    →
                                    <span class="badge text-bg-success">
                                        <?= htmlspecialchars($ev['valor_nuevo_nombre']) ?>
                                    </span>
                                </div>

                                <span class="badge text-bg-<?= $color ?>">
                                    <?= ucfirst($est) ?>
                                </span>
                            </div>

                            <?php if (!empty($ev['comentario'])): ?>
                                <small class="text-muted d-block mt-1">
                                    <?= htmlspecialchars($ev['comentario']) ?>
                                </small>
                            <?php endif; ?>

                            <small class="text-muted">
                                <?= htmlspecialchars($ev['usuario_accion'] ?? '') ?>
                                · <?= date('H:i', strtotime($ev['fecha'])) ?>
                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo = "Configurar Grado Académico";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../../layout.php';
?>