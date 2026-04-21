<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$action = $_GET['action'] ?? 'index';

// VALIDAR ID
$id_proyecto = isset($_GET["id_proyectos"]) ? intval($_GET["id_proyectos"]) : 0;

require_once "../../Controladores/seguimientoControlador.php";
$seguimientoControlador = new SeguimientoControlador();

// LLAMADA CORRECTA
$resultado = $seguimientoControlador->$action($id_usuario, $rol, $id_proyecto);

$etapas   = $resultado['etapas'] ?? [];
$proyecto = $resultado['proyecto'] ?? null;
$progreso = $resultado['progreso'] ?? ['completadas' => 0, 'total' => 0, 'pct' => 0];
$mensaje  = $resultado['mensaje'] ?? null;

$id_estudiante = $id_usuario;

// VALIDACIONES SEGURAS
$fase1_ok = ($proyecto['estado_integracion'] ?? '') === 'aceptado';
$fase2_ok = $proyecto
    ? $seguimientoControlador->todasSeccionesAprobadas($id_proyecto, $id_estudiante)
    : false;

$cierre_ok = ($proyecto['estado'] ?? '') === 'cerrado';

$estados = [
    'pendiente'  => ['clase' => 'pendiente', 'icono' => '○', 'badge' => 'badge-pend', 'texto' => 'Pendiente'],
    'proceso'    => ['clase' => 'proceso', 'icono' => '→', 'badge' => 'badge-proc', 'texto' => 'En revisión'],
    'completado' => ['clase' => 'completado', 'icono' => '✓', 'badge' => 'badge-comp', 'texto' => 'Aprobado'],
    'rechazado'  => ['clase' => 'rechazado', 'icono' => '✗', 'badge' => 'badge-rech', 'texto' => 'Rechazado'],
];

ob_start();
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-12 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <div class="portal">

        <!-- HEADER -->
        <div class="portal-header">
            <div class="initials">
                <?= strtoupper(substr($_SESSION['nombre'] ?? 'US', 0, 2)) ?>
            </div>
            <div>
                <h2>Seguimiento de Documentación</h2>

                <?php if ($proyecto): ?>
                    <p>
                        <?= htmlspecialchars($_SESSION['nombre']) ?> ·
                        <?= htmlspecialchars($proyecto['titulo']) ?>
                    </p>
                <?php else: ?>
                    <p><?= $mensaje ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($proyecto): ?>

            <!-- PROGRESO -->
            <div class="progress-bar-wrap">
                <div class="prog-label">
                    <span>Progreso general</span>
                    <span><?= $progreso['completadas'] ?> de <?= $progreso['total'] ?></span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width: <?= $progreso['pct'] ?>%"></div>
                </div>
            </div>

            <!-- TIMELINE -->
            <div class="etapas-container">
                <div class="etapas-titulo">Flujo de documentación</div>

                <div class="timeline">
                    <?php foreach ($etapas as $i => $etapa):

                        $cfg = $estados[$etapa['estado']] ?? $estados['pendiente'];
                        $bloqueada = ($i === 0) ? !$fase1_ok : !$fase2_ok;
                        $activa = ($etapa['estado'] === 'proceso') ? 'active' : '';
                    ?>

                        <div class="timeline-item <?= $cfg['clase'] ?> <?= $bloqueada ? 'locked' : '' ?> <?= $activa ?>">

                            <div class="timeline-left">
                                <div class="timeline-dot"><?= $cfg['icono'] ?></div>
                                <?php if ($i < count($etapas) - 1): ?>
                                    <div class="timeline-line"></div>
                                <?php endif; ?>
                            </div>

                            <div class="timeline-content">
                                <div class="timeline-card">

                                    <div class="timeline-header">
                                        <div class="timeline-title">
                                            <?= $i + 1 ?>. <?= htmlspecialchars($etapa['nombre']) ?>
                                        </div>
                                        <span class="timeline-badge <?= $cfg['badge'] ?>">
                                            <?= $cfg['texto'] ?>
                                        </span>
                                    </div>

                                    <div class="timeline-desc">
                                        <?= htmlspecialchars($etapa['descripcion']) ?>
                                    </div>

                                    <?php if (!empty($etapa['comentario_supervisor'])): ?>
                                        <div class="observacion">
                                            <?= htmlspecialchars($etapa['comentario_supervisor']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="docs-adjuntos">

                                        <?php if (($etapa['plantilla'] == 1) && (!empty($etapa['id_plantilla']))) { ?>
                                            <a href="descargar_plantilla.php?id_plantilla=<?= $etapa['id_plantilla'] ?>" class="doc-descargar">
                                                ⬇ Descargar
                                            </a>
                                        <?php } elseif ($etapa['plantilla'] == 0) { ?>
                                        <?php } else { ?>
                                            <span class="text-muted small">
                                                Sin plantilla disponible
                                            </span>
                                        <?php } ?>

                                        <?php if ($etapa['requiere_subida'] && !$bloqueada): ?>
                                            <form method="POST" action="?action=subirDocumento" enctype="multipart/form-data">
                                                <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                                                <input type="hidden" name="id_tipo_documento" value="<?= $etapa['id_tipo_documento'] ?>">

                                                <label class="btn btn-sm btn-success">
                                                    ⬆ Subir
                                                    <input type="file" name="documento" hidden onchange="this.form.submit()">
                                                </label>
                                            </form>
                                        <?php endif; ?>

                                    </div>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Seguimiento de documentación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>