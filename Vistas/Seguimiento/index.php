<?php

/**
 * Vistas/Seguimiento/index.php
 *
 * Panel de seguimiento de documentación del estudiante.
 */

ob_start();
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$action     = $_GET['action'] ?? 'index';

if ($rol !== 'estudiante') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

$id_proyecto = isset($_GET['id_proyectos']) ? intval($_GET['id_proyectos']) : 0;

require_once __DIR__ . '/../../Controladores/seguimientoControlador.php';
$seguimientoControlador = new SeguimientoControlador();

// 
// PASO 2 — Interceptar acciones POST
// subirDocumento  → sigue siendo AJAX (responde JSON desde el controlador)
// subirCartaTerminacion / enviarCorreccionesCarta → redirigir (PRG)
// 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'subirDocumento') {
        // El controlador responde JSON → exit interno
        $seguimientoControlador->subirDocumento();
    }
    if ($action === 'subirCartaTerminacion') {
        // El controlador redirige → exit interno
        $seguimientoControlador->subirCartaTerminacion();
    }
    if ($action === 'enviarCorreccionesCarta') {
        // El controlador redirige → exit interno
        $seguimientoControlador->enviarCorreccionesCarta();
    }
}

// 
// PASO 3 — Construcción de la vista HTML (solo GET)
// 
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* Mapa de mensajes (patrón PRG — viene por ?msg=) */
$_mapa = [
    // Subir documento (Etapa 1)
    'exito_documento_subido'       => ['tipo' => 'exito',  'titulo_msg' => 'Documento enviado',         'mensaje' => 'Tu documento fue subido correctamente. El investigador lo revisará pronto.'],
    'error_archivo_invalido'       => ['tipo' => 'error',  'titulo_msg' => 'Archivo no válido',         'mensaje' => 'Solo se aceptan archivos PDF o DOCX de máximo 10 MB.'],
    'error_datos_incompletos'      => ['tipo' => 'error',  'titulo_msg' => 'Datos incompletos',         'mensaje' => 'Faltan datos requeridos para procesar la solicitud.'],
    // Subir carta de terminación (Etapa 3)
    'exito_carta_enviada'          => ['tipo' => 'exito',  'titulo_msg' => 'Carta enviada',             'mensaje' => 'Tu Carta de Terminación fue enviada. El supervisor la revisará pronto.'],
    'error_actividades_pendientes' => ['tipo' => 'alerta', 'titulo_msg' => 'Actividades pendientes',    'mensaje' => 'Debes completar todas tus actividades antes de enviar la Carta de Terminación.'],
    'error_no_supervisor'          => ['tipo' => 'error',  'titulo_msg' => 'Sin supervisor asignado',   'mensaje' => 'No hay supervisor asignado al proyecto. Contacta al administrador.'],
    // Correcciones (Etapa 3)
    'exito_correcciones_enviadas'  => ['tipo' => 'exito',  'titulo_msg' => 'Correcciones enviadas',     'mensaje' => 'Tus correcciones fueron enviadas. El supervisor será notificado.'],
    'error_correcciones'           => ['tipo' => 'error',  'titulo_msg' => 'Error al enviar',           'mensaje' => 'No fue posible enviar las correcciones. El comentario es obligatorio.'],
    // Comunes
    'error_interno'                => ['tipo' => 'error',  'titulo_msg' => 'Error interno',             'mensaje' => 'Ocurrió un error al procesar tu solicitud. Intenta de nuevo.'],
    'sin_permiso'                  => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',        'mensaje' => 'No tienes permiso para realizar esta acción.'],
    'accion_no_permitida'          => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',       'mensaje' => 'La acción solicitada no está disponible en el estado actual.'],
];

$msg_key    = $_GET['msg'] ?? '';
$tipo       = null;
$mensaje    = null;
$titulo_msg = null;
if ($msg_key && isset($_mapa[$msg_key])) {
    $tipo       = $_mapa[$msg_key]['tipo'];
    $mensaje    = $_mapa[$msg_key]['mensaje'];
    $titulo_msg = $_mapa[$msg_key]['titulo_msg'];
}

/* Cargar datos para la vista */
$resultado = $seguimientoControlador->index($id_usuario, $rol, $id_proyecto);

$etapas        = $resultado['etapas']   ?? [];
$proyecto      = $resultado['proyecto'] ?? null;
$progreso      = $resultado['progreso'] ?? ['completadas' => 0, 'total' => 0, 'pct' => 0];
$mensaje_vista = $resultado['mensaje']  ?? null;

$id_estudiante = $id_usuario;

/* Flags de desbloqueo */
$etapa1_estado = '';
foreach ($etapas as $e) {
    if ((int)$e['orden'] === 1) $etapa1_estado = $e['estado'];
}
$fase2_ok = ($etapa1_estado === 'completado');
$fase3_ok = $proyecto
    ? $seguimientoControlador->todasSeccionesAprobadas($id_proyecto, $id_estudiante)
    : false;

/* Iconos reutilizables */
include __DIR__ . '/../../publico/incluido/_iconos.php';

/* Mapa de estados → clases CSS y textos */
$estados = [
    'pendiente'              => ['clase' => 'pendiente',  'badge' => 'badge-pend',     'texto' => 'Pendiente'],
    'proceso'                => ['clase' => 'proceso',    'badge' => 'badge-proc',     'texto' => 'En revisión'],
    'completado'             => ['clase' => 'completado', 'badge' => 'badge-comp',     'texto' => 'Completado'],
    'rechazado'              => ['clase' => 'rechazado',  'badge' => 'badge-rech',     'texto' => 'Correcciones requeridas'],
    'correcciones'           => ['clase' => 'rechazado',  'badge' => 'badge-rech',     'texto' => 'Correcciones requeridas'],
    'finalizacion_pendiente' => ['clase' => 'proceso',    'badge' => 'badge-pend-val', 'texto' => 'Terminación pendiente de validación'],
    'baja_incompleta'        => ['clase' => 'rechazado',  'badge' => 'badge-baja',     'texto' => 'No completada'],
    'bloqueado'              => ['clase' => 'pendiente',  'badge' => 'badge-pend',     'texto' => 'Bloqueada'],
    'esperando_cierre'       => ['clase' => 'proceso',    'badge' => 'badge-proc',     'texto' => 'Esperando cierre'],
];

ob_end_clean();
ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Seguimiento';
        $descripcion = 'Control de avance de estudiantes por etapa';
        include __DIR__ . '/../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="../Proyectos/index.php" class="btn btn-secondary">
                <i class="<?= $iconos['tabla']['regresar'] ?>"></i> Regresar
            </a>
        </div>
    </div>

    <!-- MENSAJE DE FEEDBACK (viene por ?msg= tras redirigir) -->
    <?php if ($tipo && $mensaje): ?>
        <div class="row mb-2">
            <div class="col-12">
                <?php include __DIR__ . '/../../publico/incluido/_mensaje.php'; ?>
            </div>
        </div>
    <?php endif; ?>

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
                    <p><?= htmlspecialchars($mensaje_vista ?? 'Sin proyecto asignado.') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($proyecto): ?>

            <!-- BARRA DE PROGRESO GENERAL -->
            <div class="progress-bar-wrap">
                <div class="prog-label">
                    <span>Progreso general</span>
                    <span><?= $progreso['completadas'] ?> de <?= $progreso['total'] ?></span>
                </div>
                <div class="prog-track">
                    <div class="prog-fill" style="width: <?= $progreso['pct'] ?>%"></div>
                </div>
            </div>

            <!-- ETAPAS -->
            <div class="etapas-container">
                <div class="etapas-titulo">Flujo de documentación</div>

                <?php foreach ($etapas as $i => $etapa):

                    $orden  = (int)$etapa['orden'];
                    $estado = $etapa['estado'] ?? 'pendiente';
                    $cfg    = $estados[$estado] ?? $estados['pendiente'];

                    if ($orden === 1)      $bloqueada = false;
                    elseif ($orden === 2)  $bloqueada = !$fase2_ok;
                    else                   $bloqueada = !$fase3_ok;

                    $activa = in_array($estado, ['proceso', 'finalizacion_pendiente']) ? 'active' : '';
                ?>

                    <div class="etapa-card <?= $cfg['clase'] ?> <?= $bloqueada ? 'locked' : '' ?> <?= $activa ?>">

                        <!-- Header: número + nombre + badge -->
                        <div class="etapa-card-header">
                            <div class="etapa-card-title">
                                <?= $i + 1 ?>. <?= htmlspecialchars($etapa['nombre']) ?>
                            </div>
                            <span class="etapa-badge <?= $cfg['badge'] ?>">
                                <?= $cfg['texto'] ?>
                            </span>
                        </div>

                        <!-- Descripción -->
                        <div class="etapa-card-desc">
                            <?= htmlspecialchars($etapa['descripcion']) ?>
                        </div>

                        <!-- Comentario del revisor -->
                        <?php if (!empty($etapa['comentario_supervisor'])): ?>
                            <div class="observacion">
                                <i class="<?= $iconos['tabla']['comentarios'] ?>"></i>
                                <?= htmlspecialchars($etapa['comentario_supervisor']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- ACCIONES POR ETAPA -->
                        <div class="docs-adjuntos">
                            <?php $es_baja = isset($etapa['estado_baja']); ?>

                            <?php if ($es_baja && $orden === 2 && $estado === 'baja_incompleta'): ?>
                                <div class="alert-etapa alert-etapa-danger">
                                    <i class="<?= $iconos['tabla']['solicitar_cierre'] ?>"></i>
                                    <span>
                                        <strong>No completaste esta etapa.</strong>
                                        <?php if ($etapa['es_vencido']): ?>
                                            El proyecto venció
                                            <?= $etapa['fecha_baja'] ? 'el ' . date('d/m/Y', strtotime($etapa['fecha_baja'])) : '' ?>
                                            antes de que terminaras todas tus actividades.
                                        <?php else: ?>
                                            Tu participación finalizó: <em><?= htmlspecialchars($etapa['motivo_baja']) ?></em>.
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (($etapa['tareas_total'] ?? 0) > 0): ?>
                                    <div class="tareas-progreso mt-2">
                                        <span class="tareas-label">
                                            Actividades aprobadas al momento de la baja:
                                            <strong><?= $etapa['tareas_aprobadas'] ?> / <?= $etapa['tareas_total'] ?></strong>
                                        </span>
                                        <div class="tareas-track">
                                            <div class="tareas-fill"
                                                style="width:<?= round(($etapa['tareas_aprobadas'] / $etapa['tareas_total']) * 100) ?>%; background: #c41230;">
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small mt-2 mb-0">
                                        <i class="<?= $iconos['detalles']['Informacion'] ?>"></i>
                                        No se registraron actividades asignadas.
                                    </p>
                                <?php endif; ?>

                            <?php elseif ($es_baja && $orden === 3 && $estado === 'baja_incompleta'): ?>
                                <div class="alert-etapa alert-etapa-danger">
                                    <i class="<?= $iconos['tabla']['solicitar_cierre'] ?>"></i>
                                    <span>
                                        <strong>Etapa no alcanzada.</strong>
                                        <?php if ($etapa['es_vencido']): ?>
                                            El proyecto venció antes de que pudieras iniciar el cierre.
                                        <?php else: ?>
                                            Tu participación concluyó antes de llegar a esta etapa.
                                        <?php endif; ?>
                                    </span>
                                </div>

                            <?php elseif ($bloqueada): ?>
                                <span class="badge-bloqueado">
                                    <i class="bi bi-lock-fill me-1"></i>
                                    <?php if ($orden === 2): ?>
                                        Disponible cuando la Etapa 1 sea completada.
                                    <?php else: ?>
                                        Disponible cuando completes todas tus actividades.
                                    <?php endif; ?>
                                </span>

                            <?php elseif ($orden === 1): ?>
                                <!-- ETAPA 1 — Carta Compromiso -->
                                <div class="alert-etapa alert-etapa-success">
                                    <i class="<?= $iconos['detalles']['exito'] ?>"></i>
                                    <span><strong>¡Solicitud aceptada!</strong> Tu carta compromiso fue recibida y aceptada. Formas parte del proyecto.</span>
                                </div>
                                <?php if (!empty($etapa['documento_subido'])): ?>
                                    <a href="/<?= htmlspecialchars($etapa['documento_subido']['ruta']) ?>"
                                        target="_blank"
                                        class="btn-doc-descarga">
                                        <i class="<?= $iconos['tabla']['descargar'] ?>"></i>
                                        <span>Descargar mi carta compromiso</span>
                                        <span class="doc-ext"><?= strtoupper($etapa['documento_subido']['extension'] ?? '') ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">
                                        <i class="<?= $iconos['detalles']['informacion'] ?>"></i>
                                        No hay documento disponible para descarga.
                                    </span>
                                <?php endif; ?>

                            <?php elseif ($orden === 2): ?>
                                <!-- ETAPA 2 — Desarrollo del documento -->
                                <?php
                                $total_t     = $etapa['tareas_total']     ?? 0;
                                $aprobadas_t = $etapa['tareas_aprobadas'] ?? 0;
                                ?>
                                <?php if ($estado === 'completado'): ?>
                                    <div class="alert-etapa alert-etapa-success">
                                        <i class="<?= $iconos['detalles']['exito_todos'] ?>"></i>
                                        <span><strong>¡Etapa completada!</strong> Todas tus actividades han sido revisadas y aprobadas.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="alert-etapa alert-etapa-info">
                                        <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                                        <span>Esta etapa está en revisión. Las actividades se registran en el módulo de avances del proyecto.</span>
                                    </div>
                                    <?php if ($total_t > 0): ?>
                                        <div class="tareas-progreso">
                                            <span class="tareas-label">
                                                Actividades aprobadas:
                                                <strong><?= $aprobadas_t ?> / <?= $total_t ?></strong>
                                            </span>
                                            <div class="tareas-track">
                                                <div class="tareas-fill"
                                                    style="width:<?= $total_t > 0 ? round(($aprobadas_t / $total_t) * 100) : 0 ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                            <?php elseif ($orden === 3): ?>
                                <!-- ETAPA 3 — Carta de Terminación -->

                                <?php if ($estado === 'completado'): ?>
                                    <div class="alert-etapa alert-etapa-success">
                                        <i class="<?= $iconos['detalles']['exito_verificado'] ?>"></i>
                                        <span><strong>¡Etapa completada!</strong> Tu carta de terminación fue aprobada. Tu participación ha concluido oficialmente.</span>
                                    </div>
                                    <?php if (!empty($etapa['documento_subido'])): ?>
                                        <a href="/<?= htmlspecialchars($etapa['documento_subido']['ruta']) ?>"
                                            target="_blank"
                                            class="btn-doc-descarga">
                                            <i class="<?= $iconos['detalles']['descargar'] ?>"></i>
                                            <span>Descargar mi carta de terminación</span>
                                            <span class="doc-ext"><?= strtoupper($etapa['documento_subido']['extension'] ?? '') ?></span>
                                        </a>
                                    <?php endif; ?>

                                <?php elseif ($estado === 'finalizacion_pendiente'): ?>
                                    <div class="alert-etapa alert-etapa-warning">
                                        <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                                        <span><strong>Terminación pendiente de validación.</strong> Tu carta fue enviada y está esperando la revisión del supervisor.</span>
                                    </div>
                                    <?php if (!empty($etapa['documento_subido'])): ?>
                                        <a href="/<?= htmlspecialchars($etapa['documento_subido']['ruta']) ?>"
                                            target="_blank"
                                            class="btn-doc-descarga btn-doc-secondary">
                                            <i class="<?= $iconos['detalles']['subinformacion'] ?>"></i>
                                            <span>Ver carta enviada</span>
                                            <span class="doc-ext"><?= strtoupper($etapa['documento_subido']['extension'] ?? '') ?></span>
                                        </a>
                                    <?php endif; ?>

                                <?php elseif ($estado === 'rechazado' || $estado === 'correcciones'): ?>
                                    <div class="alert-etapa alert-etapa-danger">
                                        <i class="<?= $iconos['detalles']['advertencia'] ?>"></i>
                                        <span>
                                            <strong>El supervisor solicitó correcciones.</strong>
                                            <?php if (!empty($etapa['comentario_supervisor'])): ?>
                                                <div class="mt-1 text-dark">
                                                    <em>"<?= htmlspecialchars($etapa['comentario_supervisor']) ?>"</em>
                                                </div>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <?php if ($etapa['plantilla'] == 1 && !empty($etapa['id_plantilla'])): ?>
                                        <a href="descargar_plantilla.php?id_plantilla=<?= $etapa['id_plantilla'] ?>"
                                            class="doc-descargar">
                                            <i class="<?= $iconos['detalles']['bajar'] ?>"></i> Descargar plantilla
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($etapa['cierre']['id_cierre_est'])): ?>
                                        <a href="correcciones_carta.php?id=<?= $etapa['cierre']['id_cierre_est'] ?>"
                                            class="btn btn-sm btn-outline-danger mt-2 me-2">
                                            <i class="<?= $iconos['detalles']['comentarios'] ?>"></i>
                                            Ver comentarios y responder
                                        </a>
                                    <?php endif; ?>

                                    <!-- Reenvío con spinner (submit normal + JS para UX) -->
                                    <form method="POST"
                                        action="?action=subirCartaTerminacion&id_proyectos=<?= $id_proyecto ?>"
                                        enctype="multipart/form-data"
                                        class="form-carta-terminacion d-inline-block mt-2">
                                        <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                                        <label class="btn btn-sm btn-warning" id="btn-carta-reenvio">
                                            <i class="<?= $iconos['detalles']['subir'] ?>"></i>
                                            <span class="btn-label">Reenviar carta corregida</span>
                                            <input type="file" name="documento" hidden accept=".pdf,.docx"
                                                onchange="prepararSubidaCarta(this)">
                                        </label>
                                    </form>
                                    <div class="spinner-carta d-none mt-2" id="spinner-carta">
                                        <span class="spinner-border spinner-border-sm text-warning"></span>
                                        <small class="ms-1">Enviando carta…</small>
                                    </div>

                                <?php else: ?>
                                    <!-- Estado pendiente: primera subida -->
                                    <?php if ($etapa['plantilla'] == 1 && !empty($etapa['id_plantilla'])): ?>
                                        <a href="descargar_plantilla.php?id_plantilla=<?= $etapa['id_plantilla'] ?>"
                                            class="doc-descargar">
                                            <i class="<?= $iconos['detalles']['bajar'] ?>"></i> Descargar plantilla
                                        </a>
                                    <?php elseif ($etapa['plantilla'] == 1): ?>
                                        <span class="text-muted small">Sin plantilla disponible</span>
                                    <?php endif; ?>

                                    <div class="mt-2 small text-muted mb-2">
                                        Descarga la plantilla, fírmala y súbela aquí en formato PDF o DOCX.
                                    </div>

                                    <form method="POST"
                                        action="?action=subirCartaTerminacion&id_proyectos=<?= $id_proyecto ?>"
                                        enctype="multipart/form-data"
                                        class="form-carta-terminacion">
                                        <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                                        <label class="btn btn-sm btn-success" id="btn-carta-subir">
                                            <i class="<?= $iconos['detalles']['subir'] ?>"></i>
                                            <span class="btn-label">Subir carta de terminación firmada</span>
                                            <input type="file" name="documento" hidden accept=".pdf,.docx"
                                                onchange="prepararSubidaCarta(this)">
                                        </label>
                                    </form>
                                    <div class="spinner-carta d-none mt-2" id="spinner-carta">
                                        <span class="spinner-border spinner-border-sm text-success"></span>
                                        <small class="ms-1">Enviando carta…</small>
                                    </div>

                                <?php endif; ?>

                            <?php endif; ?>

                        </div><!-- /.docs-adjuntos -->
                    </div><!-- /.etapa-card -->

                <?php endforeach; ?>

            </div><!-- /.etapas-container -->

        <?php else: ?>
            <div class="alert alert-info m-4">
                <i class="<?= $iconos['detalles']['informacion'] ?>"></i>
                <?= htmlspecialchars($mensaje_vista ?? 'No se encontró información del proyecto.') ?>
            </div>
        <?php endif; ?>

    </div><!-- /.portal -->
</div>


<script>
/**
 * prepararSubidaCarta()
 *
 * Para subirCartaTerminacion: el controlador usa redirigir() (PRG),
 * por lo que NO se usa fetch. En su lugar se muestra el spinner
 * y se hace submit normal del formulario. La respuesta llega como
 * una redirección con ?msg= que muestra el mensaje en la vista.
 */
function prepararSubidaCarta(inputEl) {
    if (!inputEl.files || !inputEl.files[0]) return;

    const form    = inputEl.closest('form');
    const spinner = document.getElementById('spinner-carta');

    // Mostrar spinner y deshabilitar el botón para evitar doble envío
    if (spinner) spinner.classList.remove('d-none');
    const btn = form.querySelector('label.btn');
    if (btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity       = '0.65';
        const label = btn.querySelector('.btn-label');
        if (label) label.textContent = 'Enviando…';
    }

    // Submit normal — el servidor redirige de vuelta con ?msg=
    form.submit();
}

/**
 * subirDocumentoEtapa()
 *
 * Solo se usa para Etapa 1 (Carta Compromiso), cuyo controlador
 * aún responde JSON. Maneja la respuesta de forma defensiva.
 */
function subirDocumentoEtapa(inputEl, numEtapa) {
    const form    = inputEl.closest('form');
    const spinner = document.getElementById('spinner-etapa-' + numEtapa);
    const msgEl   = document.getElementById('msg-etapa-' + numEtapa);
    const card    = form.closest('.etapa-card');

    if (!inputEl.files || !inputEl.files[0]) return;

    if (spinner) spinner.classList.remove('d-none');
    if (msgEl) {
        msgEl.className   = 'msg-etapa d-none';
        msgEl.textContent = '';
    }

    const fd = new FormData(form);

    fetch(form.action, { method: 'POST', body: fd })
        .then(r => r.text().then(text => ({ status: r.status, text })))
        .then(({ status, text }) => {
            if (spinner) spinner.classList.add('d-none');

            let data;
            try {
                data = JSON.parse(text);
            } catch (_) {
                mostrarMsgEtapa(msgEl, 'Error del servidor. Intenta de nuevo o recarga la página.', false);
                inputEl.value = '';
                return;
            }

            if (data.ok) {
                const zona = card ? card.querySelector('.docs-adjuntos') : null;
                if (zona) {
                    zona.innerHTML = `
                        <div class="alert-etapa alert-etapa-success mt-2">
                            <i class="bi bi-check-circle me-2"></i>
                            <span>
                                <strong>Documento enviado.</strong>
                                El investigador lo revisará pronto.
                            </span>
                        </div>`;
                }
                const badge = card ? card.querySelector('.etapa-badge') : null;
                if (badge) {
                    badge.className   = 'etapa-badge badge-proc';
                    badge.textContent = 'En revisión';
                }
            } else {
                mostrarMsgEtapa(msgEl, data.msg || 'Error al enviar.', false);
                inputEl.value = '';
            }
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            mostrarMsgEtapa(msgEl, 'Error de conexión. Verifica tu internet e intenta de nuevo.', false);
            inputEl.value = '';
            console.error('[subirDocumentoEtapa]', err);
        });
}

function mostrarMsgEtapa(el, msg, ok) {
    if (!el) return;
    el.textContent = msg;
    el.className   = 'msg-etapa alert py-1 px-2 mt-2 small ' + (ok ? 'alert-success' : 'alert-danger');
}
</script>

<?php
$contenido = ob_get_clean();
$titulo    = "Seguimiento de documentación";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>