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

require_once "../../Controladores/seguimientoControlador.php";
$seguimientoControlador = new seguimientoControlador();

$resultado = $seguimientoControlador->$action($id_usuario, $rol);

$etapas    = $resultado['etapas']    ?? [];
$progreso  = $resultado['progreso']  ?? ['completadas' => 0, 'total' => 0, 'pct' => 0];
$id_proyecto   = $proyecto['id_proyectos'] ?? 0;
$id_estudiante = $id_usuario;

// Fase 1: integración aceptada
$fase1_ok = ($proyecto['estado_integracion'] ?? '') === 'aceptado';

// Fase 2: todas las secciones aprobadas
$fase2_ok = $seguimientoControlador->todasSeccionesAprobadas($id_proyecto, $id_estudiante);

// Fase 3: cierre
$cierre_ok = ($proyecto['estado'] ?? '') === 'cerrado';

$proyecto  = $resultado['proyecto']  ?? null;
$mensaje   = $resultado['mensaje']   ?? null;

$estados = [
    'pendiente'  => ['clase' => 'pendiente', 'icono' => '○', 'badge' => 'badge-pend', 'texto' => 'Pendiente'],
    'proceso'    => ['clase' => 'proceso', 'icono' => '→', 'badge' => 'badge-proc', 'texto' => 'En revisión'],
    'completado' => ['clase' => 'completado', 'icono' => '✓', 'badge' => 'badge-comp', 'texto' => 'Aprobado'],
    'rechazado'  => ['clase' => 'rechazado', 'icono' => '✗', 'badge' => 'badge-rech', 'texto' => 'Rechazado'],
];


ob_start();
?>
<div class="container-fluid py-4">

    <div class="portal">

        <!-- HEADER -->
        <div class="portal-header">
            <div class="initials">
                <?= strtoupper(substr($_SESSION['nombre'] ?? 'US', 0, 2)) ?>
            </div>
            <div>
                <h2>Portal de Seguimiento — Documentación</h2>
                <?php if ($proyecto): ?>
                    <p>
                        <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>
                        · <?= htmlspecialchars($_SESSION['matricula'] ?? '') ?>
                        · <?= htmlspecialchars($proyecto['titulo']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABS -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="setTab(0,this)">Mis etapas</button>
            <button class="tab-btn" onclick="setTab(1,this)">Documentos</button>
        </div>

        <!-- PROGRESO -->
        <div class="progress-bar-wrap">
            <div class="prog-label">
                <span>Progreso general</span>
                <span><?= $progreso['completadas'] ?> de <?= $progreso['total'] ?> etapas</span>
            </div>
            <div class="prog-track">
                <div class="prog-fill" style="width: <?= $progreso['pct'] ?>%"></div>
            </div>
        </div>

        <!-- TAB ETAPAS -->
        <div id="tab-etapas" class="etapas-container">
            <div class="etapas-titulo">Flujo de documentación</div>

            <div class="timeline">

                <?php $bloqueada = false;
                foreach ($etapas as $i => $etapa):
                    $cfg = $estados[$etapa['estado']] ?? $estados['pendiente'];

                    // lógica de bloqueo
                    if ($i === 0) {
                        $bloqueada = !$fase1_ok;
                    } elseif ($i > 0) {
                        $bloqueada = !$fase2_ok;
                    }
                ?>

                    <div class="timeline-item <?= $cfg['clase'] ?> <?= $bloqueada ? 'locked' : '' ?>">
                        <div class="timeline-left">
                            <div class="timeline-dot">
                                <?= $cfg['icono'] ?>
                            </div>
                            <?php if ($i !== count($etapas) - 1): ?>
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

                                    <?php if (!empty($etapa['doc_nombre'])): ?>
                                        <a href="/ITSFCP-PROYECTOS/publico/<?= $etapa['doc_ruta'] ?>" target="_blank" class="doc-pill">
                                            📄 <?= htmlspecialchars($etapa['doc_nombre']) ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($etapa['plantilla_nombre'])): ?>
                                        <a href="/ITSFCP-PROYECTOS/publico/<?= $etapa['plantilla_ruta'] ?>" target="_blank" class="doc-pill">
                                            ⬇ Plantilla
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($etapa['requiere_subida'] && !$bloqueada): ?> 
                                        <form method="POST" action="?action=subirDocumento" enctype="multipart/form-data" style="display:inline-block;">
                                            <input type="hidden" name="id_proyecto" value="<?= $proyecto['id_proyectos'] ?>">
                                            <input type="hidden" name="id_tipo_documento" value="<?= $etapa['id_tipo_documento'] ?>">

                                            <label class="doc-subir">
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

        <!-- TAB DOCUMENTOS -->
        <div id="tab-docs" class="etapas-container" style="display:none">
            <div class="etapas-titulo">Plantillas disponibles</div>

            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Etapa</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($etapas as $i => $etapa): ?>
                        <?php if (!empty($etapa['plantilla_nombre'])): ?>
                            <tr>
                                <td><?= htmlspecialchars($etapa['plantilla_nombre']) ?></td>
                                <td>Etapa <?= $i + 1 ?></td>
                                <td>
                                    <a href="/ITSFCP-PROYECTOS/publico/<?= $etapa['plantilla_ruta'] ?>" class="doc-pill" target="_blank">
                                        ⬇ Descargar
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    </div>
</div>
<script>
    function setTab(i, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('tab-etapas').style.display = i === 0 ? '' : 'none';
        document.getElementById('tab-docs').style.display = i === 1 ? '' : 'none';
    }
    document.querySelector('.timeline-item.active')?.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo = "Seguimiento de documentación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>