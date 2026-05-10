<?php
/**
 * Vistas/Seguimiento/seguimiento.php
 *
 * Panel de seguimiento de documentación del estudiante.
 *
 * Etapas:
 *   1. Solicitud de integración — Carta Compromiso
 *      • El estudiante descarga la plantilla, la firma y la sube.
 *      • Estado proviene de seguimiento_documento.
 *      • Bloqueada: nunca (el estudiante ya fue aceptado).
 *
 *   2. Desarrollo del documento
 *      • Estado calculado automáticamente desde tareas.
 *      • No requiere acción del estudiante en esta vista.
 *      • Bloqueada si Etapa 1 no está completada.
 *
 *   3. Cierre del proyecto — Carta de Terminación
 *      • El estudiante descarga la plantilla, la firma externamente y la sube.
 *      • Estado proviene de cierres_estudiante.
 *      • Bloqueada si Etapa 2 no está completada.
 *      • Si fue rechazada, el estudiante puede reenviarla con correcciones.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$action     = $_GET['action'] ?? 'index';

// Solo el estudiante accede a esta vista
if ($rol !== 'estudiante') {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

// Validar id_proyectos
$id_proyecto = isset($_GET['id_proyectos']) ? intval($_GET['id_proyectos']) : 0;

require_once "../../Controladores/seguimientoControlador.php";
$seguimientoControlador = new SeguimientoControlador();

// ── Procesar acciones POST antes de cargar la vista ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'subirDocumento') {
        $seguimientoControlador->subirDocumento();
        exit; // subirDocumento() responde JSON y sale
    }
    if ($action === 'subirCartaTerminacion') {
        $seguimientoControlador->subirCartaTerminacion();
        exit; // ídem
    }
}

// ── Cargar datos para la vista ───────────────────────────────────
$resultado = $seguimientoControlador->index($id_usuario, $rol, $id_proyecto);

$etapas   = $resultado['etapas']   ?? [];
$proyecto = $resultado['proyecto'] ?? null;
$progreso = $resultado['progreso'] ?? ['completadas' => 0, 'total' => 0, 'pct' => 0];
$mensaje  = $resultado['mensaje']  ?? null;

$id_estudiante = $id_usuario;

// ── Flags de desbloqueo por etapa ───────────────────────────────
// Etapa 1: siempre desbloqueada si el estudiante pertenece al proyecto.
$fase1_ok = $proyecto !== null;

// Etapa 2: desbloqueada si Etapa 1 está completada (Carta Compromiso aprobada).
$etapa1_estado = '';
foreach ($etapas as $e) {
    if ((int)$e['orden'] === 1) { $etapa1_estado = $e['estado']; break; }
}
$fase2_ok = ($etapa1_estado === 'completado');

// Etapa 3: desbloqueada si Etapa 2 está completada (todas las tareas aprobadas).
$fase3_ok = $proyecto
    ? $seguimientoControlador->todasSeccionesAprobadas($id_proyecto, $id_estudiante)
    : false;

// ── Mapa de estados → clases CSS y textos ──────────────────────
$estados = [
    'pendiente'    => ['clase' => 'pendiente',  'icono' => '○', 'badge' => 'badge-pend', 'texto' => 'Pendiente'],
    'proceso'      => ['clase' => 'proceso',    'icono' => '→', 'badge' => 'badge-proc', 'texto' => 'En revisión'],
    'completado'   => ['clase' => 'completado', 'icono' => '✓', 'badge' => 'badge-comp', 'texto' => 'Aprobado'],
    'rechazado'    => ['clase' => 'rechazado',  'icono' => '✗', 'badge' => 'badge-rech', 'texto' => 'Rechazado'],
    'correcciones' => ['clase' => 'rechazado',  'icono' => '✗', 'badge' => 'badge-rech', 'texto' => 'Correcciones'],
];

ob_start();
?>

<div class="container-fluid py-4" style="max-width:95%;">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-12 text-md-end">
            <a href="../Proyectos/tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <div class="portal">

        <!-- HEADER DEL PORTAL -->
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
                    <p><?= htmlspecialchars($mensaje ?? 'Sin proyecto asignado.') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($proyecto): ?>

            <!-- BARRA DE PROGRESO -->
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

                <div class="timeline_seguimiento">
                    <?php foreach ($etapas as $i => $etapa):

                        $orden  = (int)$etapa['orden'];
                        $estado = $etapa['estado'] ?? 'pendiente';
                        $cfg    = $estados[$estado] ?? $estados['pendiente'];

                        // Determinar si la etapa está bloqueada
                        if ($orden === 1)       $bloqueada = false;
                        elseif ($orden === 2)   $bloqueada = !$fase2_ok;
                        else                    $bloqueada = !$fase3_ok;   // Etapa 3

                        $activa = ($estado === 'proceso') ? 'active' : '';
                    ?>

                        <div class="timeline_seguimiento-item <?= $cfg['clase'] ?> <?= $bloqueada ? 'locked' : '' ?> <?= $activa ?>">

                            <div class="timeline_seguimiento-left">
                                <div class="timeline_seguimiento-dot"><?= $cfg['icono'] ?></div>
                                <?php if ($i < count($etapas) - 1): ?>
                                    <div class="timeline_seguimiento-line"></div>
                                <?php endif; ?>
                            </div>

                            <div class="timeline_seguimiento-content">
                                <div class="timeline_seguimiento-card">

                                    <div class="timeline_seguimiento-header">
                                        <div class="timeline_seguimiento-title">
                                            <?= $i + 1 ?>. <?= htmlspecialchars($etapa['nombre']) ?>
                                        </div>
                                        <span class="timeline_seguimiento-badge <?= $cfg['badge'] ?>">
                                            <?= $cfg['texto'] ?>
                                        </span>
                                    </div>

                                    <div class="timeline_seguimiento-desc">
                                        <?= htmlspecialchars($etapa['descripcion']) ?>
                                    </div>

                                    <!-- Comentario del revisor -->
                                    <?php if (!empty($etapa['comentario_supervisor'])): ?>
                                        <div class="observacion">
                                            <i class="bi bi-chat-left-text me-1"></i>
                                            <?= htmlspecialchars($etapa['comentario_supervisor']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- ACCIONES POR ETAPA -->
                                    <div class="docs-adjuntos">

                                        <?php
                                        // ── Etapa 1 y 3: plantilla descargable ──────────
                                        if ($etapa['plantilla'] == 1 && !empty($etapa['id_plantilla'])):
                                        ?>
                                            <a href="descargar_plantilla.php?id_plantilla=<?= $etapa['id_plantilla'] ?>"
                                               class="doc-descargar">
                                                ⬇ Descargar plantilla
                                            </a>
                                        <?php elseif ($etapa['plantilla'] == 1 && empty($etapa['id_plantilla'])): ?>
                                            <span class="text-muted small">Sin plantilla disponible</span>
                                        <?php endif; ?>

                                        <?php if ($orden === 1): ?>
                                            <!-- ── ETAPA 1: subir Carta Compromiso ─────── -->
                                            <?php if (!$bloqueada && in_array($estado, ['pendiente', 'proceso', 'rechazado', 'correcciones'])): ?>
                                                <form method="POST"
                                                      action="?action=subirDocumento&id_proyectos=<?= $id_proyecto ?>"
                                                      enctype="multipart/form-data"
                                                      class="form-subida"
                                                      data-etapa="1">
                                                    <input type="hidden" name="id_proyecto"        value="<?= $id_proyecto ?>">
                                                    <input type="hidden" name="id_tipo_documento"  value="<?= $etapa['id_tipo_documento'] ?>">
                                                    <input type="hidden" name="id_plantilla"       value="<?= $etapa['id_plantilla'] ?? '' ?>">
                                                    <input type="hidden" name="id_seguimiento"     value="<?= $etapa['id_seguimiento'] ?? '' ?>">

                                                    <label class="btn btn-sm btn-success">
                                                        ⬆ <?= $estado === 'rechazado' || $estado === 'correcciones' ? 'Reenviar corregido' : 'Subir carta firmada' ?>
                                                        <input type="file" name="documento" hidden accept=".pdf,.docx"
                                                               onchange="subirDocumentoEtapa(this, 1)">
                                                    </label>
                                                </form>
                                                <div class="spinner-etapa d-none" id="spinner-etapa-1">
                                                    <span class="spinner-border spinner-border-sm text-success"></span>
                                                    <small class="ms-1">Subiendo…</small>
                                                </div>
                                                <div class="msg-etapa d-none" id="msg-etapa-1"></div>
                                            <?php endif; ?>

                                        <?php elseif ($orden === 2): ?>
                                            <!-- ── ETAPA 2: sin subida, solo informativa ─ -->
                                            <?php
                                            $total_tareas = $seguimientoControlador->contarTareasTotales($id_proyecto, $id_estudiante) ?? 0;
                                            //  los conteos se inyectan desde el controlador como datos adicionales.
                                            //  Aquí usamos los valores ya calculados en el estado de la etapa.)
                                            ?>
                                            <span class="text-muted small">
                                                Las actividades se registran en el módulo de avances del proyecto.
                                            </span>
                                            <?php if ($estado === 'completado'): ?>
                                                <span class="badge bg-success ms-2">
                                                    <i class="bi bi-check2-all"></i> Todas las actividades aprobadas
                                                </span>
                                            <?php endif; ?>

                                        <?php elseif ($orden === 3): ?>
                                            <!-- ── ETAPA 3: Carta de Terminación ─────────
                                                 Solo disponible cuando Etapa 2 está completada.
                                                 Si fue rechazada, el estudiante puede reenviarla.
                                            -->
                                            <?php if ($bloqueada): ?>
                                                <span class="text-muted small">
                                                    <i class="bi bi-lock-fill me-1"></i>
                                                    Disponible cuando completes todas tus actividades.
                                                </span>

                                            <?php elseif ($estado === 'completado'): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-patch-check-fill"></i>
                                                    Carta de terminación aprobada por el supervisor.
                                                </span>

                                            <?php elseif ($estado === 'proceso'): ?>
                                                <!-- Carta subida, esperando revisión del supervisor -->
                                                <div class="alert alert-info py-2 px-3 mb-0 mt-2 small">
                                                    <i class="bi bi-hourglass-split me-1"></i>
                                                    Tu carta fue enviada y está siendo revisada por el supervisor.
                                                </div>

                                            <?php elseif ($estado === 'rechazado' || $estado === 'correcciones'): ?>
                                                <!-- Rechazada: permitir reenvío -->
                                                <div class="alert alert-warning py-2 px-3 mb-2 mt-2 small">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                    El supervisor solicitó correcciones. Descarga la plantilla,
                                                    corrígela y vuelve a subir tu carta firmada.
                                                </div>
                                                <form method="POST"
                                                      action="?action=subirCartaTerminacion&id_proyectos=<?= $id_proyecto ?>"
                                                      enctype="multipart/form-data"
                                                      class="form-subida"
                                                      data-etapa="3">
                                                    <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                                                    <label class="btn btn-sm btn-warning">
                                                        ⬆ Reenviar carta corregida
                                                        <input type="file" name="documento" hidden accept=".pdf,.docx"
                                                               onchange="subirDocumentoEtapa(this, 3)">
                                                    </label>
                                                </form>
                                                <div class="spinner-etapa d-none" id="spinner-etapa-3">
                                                    <span class="spinner-border spinner-border-sm text-warning"></span>
                                                    <small class="ms-1">Enviando…</small>
                                                </div>
                                                <div class="msg-etapa d-none" id="msg-etapa-3"></div>

                                            <?php else: ?>
                                                <!-- Estado pendiente: primera subida -->
                                                <div class="mt-2 small text-muted mb-2">
                                                    Descarga la plantilla, fírmala y súbela aquí en formato PDF o DOCX.
                                                </div>
                                                <form method="POST"
                                                      action="?action=subirCartaTerminacion&id_proyectos=<?= $id_proyecto ?>"
                                                      enctype="multipart/form-data"
                                                      class="form-subida"
                                                      data-etapa="3">
                                                    <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                                                    <label class="btn btn-sm btn-success">
                                                        ⬆ Subir carta de terminación firmada
                                                        <input type="file" name="documento" hidden accept=".pdf,.docx"
                                                               onchange="subirDocumentoEtapa(this, 3)">
                                                    </label>
                                                </form>
                                                <div class="spinner-etapa d-none" id="spinner-etapa-3">
                                                    <span class="spinner-border spinner-border-sm text-success"></span>
                                                    <small class="ms-1">Enviando…</small>
                                                </div>
                                                <div class="msg-etapa d-none" id="msg-etapa-3"></div>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div><!-- /.docs-adjuntos -->

                                </div><!-- /.timeline_seguimiento-card -->
                            </div><!-- /.timeline_seguimiento-content -->
                        </div><!-- /.timeline_seguimiento-item -->

                    <?php endforeach; ?>
                </div><!-- /.timeline_seguimiento -->
            </div><!-- /.etapas-container -->

        <?php else: ?>
            <div class="alert alert-info m-4">
                <i class="bi bi-info-circle me-2"></i>
                <?= htmlspecialchars($mensaje ?? 'No se encontró información del proyecto.') ?>
            </div>
        <?php endif; ?>

    </div><!-- /.portal -->
</div>

<!-- ── JavaScript para subida AJAX de documentos por etapa ──────── -->
<script>
/**
 * subirDocumentoEtapa(inputEl, numEtapa)
 *
 * Envía el formulario padre del input vía fetch (FormData) al action
 * configurado en el atributo action del <form>.
 * Muestra spinner mientras procesa y mensaje de resultado.
 */
function subirDocumentoEtapa(inputEl, numEtapa) {
    const form     = inputEl.closest('form');
    const spinner  = document.getElementById('spinner-etapa-' + numEtapa);
    const msgEl    = document.getElementById('msg-etapa-'     + numEtapa);

    if (!inputEl.files || !inputEl.files[0]) return;

    // Mostrar spinner
    spinner?.classList.remove('d-none');
    if (msgEl) { msgEl.className = 'msg-etapa d-none'; msgEl.textContent = ''; }

    const fd = new FormData(form);

    fetch(form.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            spinner?.classList.add('d-none');
            if (msgEl) {
                msgEl.textContent = data.msg || (data.ok ? 'Enviado correctamente.' : 'Error al enviar.');
                msgEl.className   = 'msg-etapa alert ' + (data.ok ? 'alert-success' : 'alert-danger') + ' py-1 px-2 mt-2 small';
            }
            // Recargar la página tras 1.8 s para reflejar el nuevo estado
            if (data.ok) setTimeout(() => location.reload(), 1800);
            else inputEl.value = ''; // limpiar input si falló
        })
        .catch(err => {
            spinner?.classList.add('d-none');
            if (msgEl) {
                msgEl.textContent = 'Error de conexión: ' + err.message;
                msgEl.className   = 'msg-etapa alert alert-danger py-1 px-2 mt-2 small';
            }
            inputEl.value = '';
        });
}
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Seguimiento de documentación";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>