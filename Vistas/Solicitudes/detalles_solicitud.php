<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if (!in_array($rol, ['investigador', 'profesor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

require_once '../../Controladores/solicitudesControlador.php';

$ctrl = new solicitudesControlador();

$id = intval($_GET['id'] ?? 0);

$data = $ctrl->detallePagina($id, $id_usuario, $rol);

$sol = $data['solicitud'];
$comentarios = $data['comentarios'];

ob_start();
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalle de solicitud</h3>
        </div>

        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- INFORMACIÓN GENERAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Información del estudiante</h5>
        </div>

        <div class="card-body">
            <div class="row">

                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd><?= htmlspecialchars($sol['estudiante_nombre']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Matrícula</dt>
                        <dd><?= htmlspecialchars($sol['matricula']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Correo</dt>
                        <dd><?= htmlspecialchars($sol['correo_institucional']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Carrera</dt>
                        <dd><?= htmlspecialchars($sol['carrera']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Semestre</dt>
                        <dd><?= $sol['semestre'] ?>°</dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Promedio</dt>
                        <dd><?= $sol['promedio'] ?></dd>
                    </dl>
                </div>

            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DEL PROYECTO -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Proyecto solicitado</h5>
        </div>

        <div class="card-body">

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Título</dt>
                        <dd><?= htmlspecialchars($sol['proyecto_titulo']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd><?= $ctrl->badgeEstado($sol['estado']) ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Fecha de solicitud</dt>
                        <dd><?= $sol['fecha_envio'] ?></dd>
                    </dl>
                </div>

                <div class="col-md-6">
                    <dl>
                        <dt>Modalidad</dt>
                        <dd><?= htmlspecialchars($sol['modalidad']) ?></dd>
                    </dl>
                </div>
            </div>

        </div>
    </div>

    <?php if (!empty($data['etapas'])): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Seguimiento</h5>
            </div>
            <div class="card-body">

                <?php foreach ($data['etapas'] as $i => $e): ?>
                    <div class="mb-2">
                        <span class="badge bg-secondary"><?= $e['estado'] ?></span>
                        <?= ($i + 1) . '. ' . htmlspecialchars($e['nombre']) ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    <?php endif; ?>

    <!-- MOTIVACIÓN -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Motivación</h5>
        </div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars($sol['motivacion'] ?? 'Sin información')) ?>
        </div>
    </div>

    <!-- EXPERIENCIA -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Experiencia</h5>
        </div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars($sol['experiencia'] ?? 'Sin información')) ?>
        </div>
    </div>

    <!-- ARCHIVO -->
    <?php if (!empty($sol['carta_nombre'])): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Carta compromiso</h5>
            </div>
            <div class="card-body">
                <a href="/ITSFCP-PROYECTOS/<?= $sol['carta_ruta'] ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> <?= htmlspecialchars($sol['carta_nombre']) ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- COMENTARIOS -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Historial de comentarios</h5>
        </div>

        <div class="card-body">

            <?php if (!empty($comentarios)): ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="border rounded p-2 mb-2">
                        <p class="mb-1"><?= htmlspecialchars($c['comentario']) ?></p>

                        <?php if (!empty($c['archivo'])): ?>
                            <a href="/ITSFCP-PROYECTOS/<?= $c['archivo'] ?>" target="_blank">
                                📎 <?= htmlspecialchars($c['nombre_archivo']) ?>
                            </a>
                        <?php endif; ?>

                        <div class="text-muted small">
                            <?= $c['fecha'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Sin comentarios aún.</p>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo = "Detalle solicitud";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>