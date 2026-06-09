<?php

/**
 * Vistas/Seguimiento/correcciones_carta.php
 *
 * Vista para el estudiante cuando su carta de terminación fue rechazada
 * por el supervisor. Muestra el hilo de comentarios y permite:
 *   1. Enviar un comentario de corrección (submit normal con PRG).
 *   2. Adjuntar un archivo de apoyo opcional.
 *   3. Reenviar una carta completamente nueva en PDF/DOCX.
 *
 * Usa: SeguimientoControlador->enviarCorreccionesCarta() → POST ?action=enviarCorreccionesCarta
 *      SeguimientoControlador->subirCartaTerminacion()   → POST ?action=subirCartaTerminacion
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'estudiante') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

$id_cierre_est = intval($_GET['id'] ?? 0);

require_once __DIR__ . '/../../Controladores/seguimientoControlador.php';
require_once __DIR__ . '/../../publico/config/conexion.php';

$ctrl = new SeguimientoControlador();

require_once __DIR__ . '/../../Modelos/seguimiento.php';
$modelo = new SeguimientoModelo($conn);

$cierre      = $modelo->CierrePorId($id_cierre_est);
$comentarios = $modelo->ComentariosCierre($id_cierre_est);

// Verificar que el cierre pertenece al estudiante
if (!$cierre || (int)$cierre['id_usuarios'] !== $id_usuario) {
    header('Location: index.php');
    exit;
}

$id_proyecto = (int)$cierre['id_proyectos'];

// El estudiante puede responder si está rechazado o en finalizacion_pendiente
$estados_activos = ['rechazado', 'finalizacion_pendiente'];
$puede_responder = in_array($cierre['estado'], $estados_activos, true);

// Mapa de mensajes (viene por ?msg= tras redirigir)
$_mapa_msg = [
    'exito_correcciones_enviadas' => ['tipo' => 'exito',  'texto' => 'Correcciones enviadas. El supervisor será notificado.'],
    'error_correcciones'          => ['tipo' => 'error',  'texto' => 'No fue posible enviar las correcciones. El comentario es obligatorio.'],
    'error_archivo_invalido'      => ['tipo' => 'error',  'texto' => 'Solo se aceptan archivos PDF, DOCX, JPG o PNG de máximo 10 MB.'],
    'error_interno'               => ['tipo' => 'error',  'texto' => 'Ocurrió un error al procesar tu solicitud. Intenta de nuevo.'],
    'sin_permiso'                 => ['tipo' => 'alerta', 'texto' => 'No tienes permiso para realizar esta acción.'],
    'accion_no_permitida'         => ['tipo' => 'alerta', 'texto' => 'La acción solicitada no está disponible en el estado actual.'],
    'exito_carta_enviada'         => ['tipo' => 'exito',  'texto' => 'Tu carta fue reenviada. El supervisor la revisará pronto.'],
];

$msg_key  = $_GET['msg'] ?? '';
$feedback = isset($_mapa_msg[$msg_key]) ? $_mapa_msg[$msg_key] : null;

// Iconos reutilizables
include __DIR__ . '../../../publico/incluido/_iconos.php';

// Mapa visual de estados
$estado_labels = [
    'pendiente'              => ['texto' => 'Pendiente',                           'clase' => 'est-proceso'],
    'finalizacion_pendiente' => ['texto' => 'Terminación pendiente de validación', 'clase' => 'est-proceso'],
    'aprobado'               => ['texto' => 'Aprobada',                            'clase' => 'est-aceptado'],
    'rechazado'              => ['texto' => 'Correcciones requeridas',              'clase' => 'est-correcciones'],
];
$estado_vis = $estado_labels[$cierre['estado']] ?? ['texto' => $cierre['estado'], 'clase' => ''];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <?php
        $titulo      = 'Carta de terminación #' . $id_cierre_est;
        $descripcion = 'Revisión y correcciones de la carta de terminación';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <span class="estado-pill <?= $estado_vis['clase'] ?>">
                <?= htmlspecialchars($estado_vis['texto']) ?>
            </span>
            <a href="index.php?id_proyectos=<?= $id_proyecto ?>" class="btn btn-secondary ms-2">
                <i class="<?= $iconos['tabla']['regresar'] ?>"></i> Regresar al seguimiento
            </a>
        </div>
    </div>

    <div class="hilo-wrap">

        <!-- Mensaje de feedback (PRG) -->
        <?php if ($feedback): ?>
            <div class="alert <?= $feedback['tipo'] === 'exito' ? 'alert-success' : ($feedback['tipo'] === 'alerta' ? 'alert-warning' : 'alert-danger') ?> mb-3">
                <?= htmlspecialchars($feedback['texto']) ?>
            </div>
        <?php endif; ?>

        <!-- Info del proyecto y documento enviado -->
        <div class="card border-0 mb-4 p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-8">
                    <div class="fw-semibold">
                        <i class="bi bi-folder2 me-1 text-primary"></i>
                        <?= htmlspecialchars($cierre['titulo_proyecto']) ?>
                    </div>
                    <div class="text-muted small mt-1">
                        Carta enviada el <?= date('d/m/Y H:i', strtotime($cierre['fecha_solicitud'])) ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if (!empty($cierre['ruta_documento'])): ?>
                        <a href="/<?= htmlspecialchars($cierre['ruta_documento']) ?>"
                            target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-paperclip me-1"></i>Ver carta enviada
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hilo de comentarios -->
        <div class="mb-4" id="hiloComentarios">
            <?php if (empty($comentarios)): ?>
                <?php if (!empty($cierre['comentarios'])): ?>
                    <div class="msg-burbuja msg-inv">
                        <div><?= nl2br(htmlspecialchars($cierre['comentarios'])) ?></div>
                        <div class="msg-meta">
                            <strong>Supervisor</strong> · Rechazo
                            <?php if ($cierre['fecha_respuesta']): ?>
                                · <?= date('d/m/Y H:i', strtotime($cierre['fecha_respuesta'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3">Sin comentarios aún.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!empty($cierre['comentarios'])): ?>
                    <div class="msg-burbuja msg-inv">
                        <div><?= nl2br(htmlspecialchars($cierre['comentarios'])) ?></div>
                        <div class="msg-meta">
                            <strong>Supervisor</strong> · Rechazo inicial
                            <?php if ($cierre['fecha_respuesta']): ?>
                                · <?= date('d/m/Y H:i', strtotime($cierre['fecha_respuesta'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="msg-burbuja <?= $c['tipo'] === 'supervisor' ? 'msg-inv' : 'msg-est' ?>">
                        <div><?= nl2br(htmlspecialchars($c['comentario'])) ?></div>
                        <?php if (!empty($c['archivo_nombre'])): ?>
                            <div class="mt-1">
                                <a href="/<?= htmlspecialchars($c['archivo_ruta']) ?>"
                                    target="_blank" class="small text-primary">
                                    <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($c['archivo_nombre']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="msg-meta">
                            <strong><?= htmlspecialchars($c['autor_nombre']) ?></strong>
                            · <?= $c['tipo'] === 'supervisor' ? 'Supervisor' : 'Tú' ?>
                            · <?= date('d/m/Y H:i', strtotime($c['fecha'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulario de respuesta (solo si puede responder) -->
        <?php if ($puede_responder): ?>

            <!-- Sección 1: Enviar comentario de correcciones (submit normal + PRG) -->
            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="<?= $iconos['tabla']['volver_enviar'] ?>"></i>
                        Responder al supervisor
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="index.php?action=enviarCorreccionesCarta&id_proyectos=<?= $id_proyecto ?>"
                        enctype="multipart/form-data"
                        id="formCorrecciones"
                        onsubmit="prepararEnvioCorrecciones(this)">

                        <input type="hidden" name="id_cierre_est" value="<?= $id_cierre_est ?>">
                        <input type="hidden" name="id_proyecto"   value="<?= $id_proyecto ?>">

                        <div class="mb-3">
                            <label for="txtComentario" class="form-label fw-medium">
                                Explicación de correcciones <span class="text-danger">*</span>
                            </label>
                            <textarea id="txtComentario" name="comentario" class="form-control" rows="4"
                                required
                                placeholder="Describe qué corregiste o ajustaste en tu carta de terminación…"></textarea>
                            <div class="form-text">
                                El supervisor recibirá este mensaje y revisará tu carta nuevamente.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fileArchivo" class="form-label fw-medium">
                                Adjuntar documento de apoyo
                                <span class="text-muted small">(opcional — PDF, DOCX, JPG, PNG)</span>
                            </label>
                            <input type="file" id="fileArchivo" name="archivo" class="form-control"
                                accept=".pdf,.docx,.png,.jpg">
                        </div>

                        <div id="spinnerCorrecciones" class="d-none mb-3">
                            <span class="spinner-border spinner-border-sm text-danger me-1"></span>
                            <small>Enviando correcciones…</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php?id_proyectos=<?= $id_proyecto ?>"
                                class="btn btn-outline-secondary btn-sm">Cancelar</a>
                            <button type="submit" class="btn btn-danger btn-sm" id="btnEnviarCorrecciones">
                                <i class="bi bi-send-fill me-1"></i>Enviar correcciones
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Sección 2: Reenviar carta completa corregida (submit normal + PRG) -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="<?= $iconos['tabla']['subir'] ?>"></i>
                        Reenviar carta de terminación corregida
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Si necesitas reemplazar el archivo de tu carta, carga aquí la versión corregida
                        firmada en PDF o DOCX. Esto reemplazará la carta anterior.
                    </p>

                    <form method="POST"
                        action="index.php?action=subirCartaTerminacion&id_proyectos=<?= $id_proyecto ?>"
                        enctype="multipart/form-data"
                        id="formReenvio"
                        onsubmit="prepararReenvioCarta(this)">

                        <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">

                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <label class="btn btn-sm btn-warning mb-0" id="btnCartaReenvio">
                                <i class="<?= $iconos['tabla']['subir'] ?>"></i>
                                <span id="labelCartaReenvio">Seleccionar carta corregida (PDF/DOCX)</span>
                                <input type="file" name="documento" hidden accept=".pdf,.docx"
                                    onchange="this.closest('form').submit(); prepararReenvioCarta(this.closest('form'))">
                            </label>
                            <div class="spinner-etapa d-none" id="spinnerReenvio">
                                <span class="spinner-border spinner-border-sm text-warning"></span>
                                <small class="ms-1">Enviando carta…</small>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        <?php elseif ($cierre['estado'] === 'finalizacion_pendiente'): ?>
            <div class="alert alert-warning text-center">
                <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                <strong>Terminación pendiente de validación.</strong><br>
                Tu carta fue enviada y está esperando la revisión del supervisor.
                Recibirás una notificación cuando sea procesada.
            </div>

        <?php elseif ($cierre['estado'] === 'aprobado'): ?>
            <div class="alert alert-success text-center">
                <i class="<?= $iconos['detalles']['exito_verificado'] ?>"></i>
                Tu carta de terminación fue <strong>aprobada</strong>.
                Tu participación en el proyecto ha concluido oficialmente.
                <br>
                <a href="index.php?id_proyectos=<?= $id_proyecto ?>"
                    class="btn btn-sm btn-success mt-2">
                    <i class="<?= $iconos['tabla']['ver'] ?>"></i> Ver mi seguimiento
                </a>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="<?= $iconos['detalles']['espera'] ?>"></i>
                Tu carta está siendo revisada. Recibirás una respuesta próximamente.
            </div>
        <?php endif; ?>

    </div><!-- /.hilo-wrap -->
</div>

<!-- Estilos del hilo -->
<style>
    .hilo-wrap {
        max-width: 820px;
        margin: 0 auto;
    }

    .estado-pill {
        display: inline-block;
        padding: .3em .85em;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 600;
        letter-spacing: .02em;
    }

    .est-proceso {
        background: var(--badge-poraprobar-bg);
        color: var(--badge-poraprobar-color);
        border: 1px solid var(--badge-poraprobar-border);
    }

    .est-aceptado {
        background: var(--badge-activo-bg);
        color: var(--badge-activo-color);
        border: 1px solid var(--badge-activo-border);
    }

    .est-correcciones {
        background: var(--badge-porcerrar-bg);
        color: var(--badge-porcerrar-color);
        border: 1px solid var(--badge-porcerrar-border);
    }

    .msg-burbuja {
        padding: .75rem 1rem;
        border-radius: 10px;
        margin-bottom: .75rem;
        max-width: 85%;
        font-size: .875rem;
        line-height: 1.5;
    }

    .msg-inv {
        background: rgba(26, 46, 74, .06);
        border: 1px solid var(--borde-menu, #e2e8f0);
        margin-right: auto;
        border-top-left-radius: 2px;
    }

    .msg-est {
        background: rgba(30, 95, 173, .07);
        border: 1px solid var(--badge-poraprobar-border, #1e5fad);
        margin-left: auto;
        border-top-right-radius: 2px;
    }

    .msg-meta {
        font-size: .75rem;
        color: var(--color-texto-secundario, #718096);
        margin-top: .35rem;
    }
</style>

<script>
    /**
     * Muestra spinner y deshabilita el botón de enviar correcciones
     * antes del submit normal. El servidor redirige de vuelta con ?msg=.
     */
    function prepararEnvioCorrecciones(form) {
        const comentario = form.querySelector('[name="comentario"]').value.trim();
        if (!comentario) return false; // el atributo required lo atrapa, pero doble seguridad

        const btn     = document.getElementById('btnEnviarCorrecciones');
        const spinner = document.getElementById('spinnerCorrecciones');

        if (btn)     { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…'; }
        if (spinner) { spinner.classList.remove('d-none'); }

        return true; // deja continuar el submit normal
    }

    /**
     * Muestra spinner y deshabilita el label antes del submit normal
     * de reenvío de carta.
     */
    function prepararReenvioCarta(form) {
        const spinner = document.getElementById('spinnerReenvio');
        const label   = document.getElementById('btnCartaReenvio');
        const texto   = document.getElementById('labelCartaReenvio');

        if (spinner) spinner.classList.remove('d-none');
        if (label)   { label.style.pointerEvents = 'none'; label.style.opacity = '0.65'; }
        if (texto)   { texto.textContent = 'Enviando…'; }
    }
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Correcciones — Carta de terminación';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>