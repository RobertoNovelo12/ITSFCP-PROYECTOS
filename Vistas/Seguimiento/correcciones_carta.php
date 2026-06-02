<?php

/**
 * Vistas/Seguimiento/correcciones_carta.php
 *
 * Vista para el estudiante cuando su carta de terminación fue rechazada
 * por el supervisor. Muestra el hilo de comentarios y permite:
 *   1. Enviar un comentario de corrección (AJAX, sin reload).
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

// Usar el modelo directamente para obtener datos del cierre y comentarios
require_once __DIR__ . '/../../Modelos/seguimiento.php';
$modelo = new SeguimientoModelo($conn);

$cierre = $modelo->CierrePorId($id_cierre_est);

// Validación de registro encontrado
$registro = $cierre;
include __DIR__ . '/../../../publico/incluido/_validar_datos.php';

$comentarios = $modelo->ComentariosCierre($id_cierre_est);

// Verificar que el cierre pertenece al estudiante
if ((int)$cierre['id_usuarios'] !== $id_usuario) {
    header('Location: index.php');
    exit;
}

$id_proyecto = (int)$cierre['id_proyectos'];

// El estudiante puede responder si está rechazado o en finalizacion_pendiente
$estados_activos = ['rechazado', 'finalizacion_pendiente'];
$puede_responder = in_array($cierre['estado'], $estados_activos, true);

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
                <i class="<?= $iconos['regresar'] ?>"></i> Regresar al seguimiento
            </a>
        </div>
    </div>

    <div class="hilo-wrap">

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
                <!-- Sin comentarios en el hilo: mostrar comentario de rechazo directo del cierre -->
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
                <!-- Primero mostrar el comentario de rechazo si existe -->
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
                <!-- Hilo de correcciones -->
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

            <!-- Sección 1: Enviar comentario de correcciones -->
            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="<?= $iconos['volver_enviar'] ?>"></i>
                        Responder al supervisor
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">
                            Explicación de correcciones <span class="text-danger">*</span>
                        </label>
                        <textarea id="txtComentario" class="form-control" rows="4"
                            placeholder="Describe qué corregiste o ajustaste en tu carta de terminación…"></textarea>
                        <div class="form-text">
                            El supervisor recibirá este mensaje y revisará tu carta nuevamente.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">
                            Adjuntar documento de apoyo
                            <span class="text-muted small">(opcional — PDF, DOCX, JPG, PNG)</span>
                        </label>
                        <input type="file" id="fileArchivo" class="form-control"
                            accept=".pdf,.docx,.png,.jpg">
                    </div>
                    <div id="mensajeEnvio" class="alert d-none mb-3"></div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php?id_proyectos=<?= $id_proyecto ?>"
                            class="btn btn-outline-secondary btn-sm">Cancelar</a>
                        <button type="button" class="btn btn-danger btn-sm" id="btnEnviarCorrecciones">
                            <i class="bi bi-send-fill me-1"></i>Enviar correcciones
                            <span class="spinner-border spinner-border-sm d-none ms-1" id="spinnerEnvio"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Reenviar carta completa corregida -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="<?= $iconos['subir'] ?>"></i>
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
                        class="form-subida"
                        data-etapa="3r">
                        <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <label class="btn btn-sm btn-warning mb-0">
                                <i class="<?= $iconos['subir'] ?>"></i> Seleccionar carta corregida (PDF/DOCX)
                                <input type="file" name="documento" hidden accept=".pdf,.docx"
                                    onchange="subirCartaCorregida(this)">
                            </label>
                            <div class="spinner-etapa d-none" id="spinner-etapa-3r">
                                <span class="spinner-border spinner-border-sm text-warning"></span>
                                <small class="ms-1">Enviando carta…</small>
                            </div>
                        </div>
                    </form>
                    <div class="msg-etapa d-none mt-2" id="msg-etapa-3r"></div>
                </div>
            </div>

        <?php elseif ($cierre['estado'] === 'finalizacion_pendiente'): ?>
            <div class="alert alert-warning text-center">
                <i class="<?= $iconos['espera'] ?>"></i>
                <strong>Terminación pendiente de validación.</strong><br>
                Tu carta fue enviada y está esperando la revisión del supervisor.
                Recibirás una notificación cuando sea procesada.
            </div>

        <?php elseif ($cierre['estado'] === 'aprobado'): ?>
            <div class="alert alert-success text-center">
                <i class="<?= $iconos['exito_verificado'] ?>"></i>
                Tu carta de terminación fue <strong>aprobada</strong>.
                Tu participación en el proyecto ha concluido oficialmente.
                <br>
                <a href="index.php?id_proyectos=<?= $id_proyecto ?>"
                    class="btn btn-sm btn-success mt-2">
                    <i class="<?= $iconos['ver'] ?>"></i> Ver mi seguimiento
                </a>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="<?= $iconos['espera'] ?>"></i>
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

<!-- JavaScript del hilo -->
<script>
    // Enviar comentario de correcciones (sin reload)
    document.getElementById('btnEnviarCorrecciones')?.addEventListener('click', function() {
        const comentario = document.getElementById('txtComentario').value.trim();
        const archivo    = document.getElementById('fileArchivo').files[0] || null;
        const mensajeEl  = document.getElementById('mensajeEnvio');
        const spinner    = document.getElementById('spinnerEnvio');
        const hilo       = document.getElementById('hiloComentarios');

        if (!comentario) {
            mensajeEl.textContent = 'Por favor escribe una explicación de las correcciones.';
            mensajeEl.className   = 'alert alert-warning mb-3';
            return;
        }

        this.disabled = true;
        spinner.classList.remove('d-none');
        mensajeEl.className = 'alert d-none mb-3';

        const fd = new FormData();
        fd.append('id_cierre_est', <?= $id_cierre_est ?>);
        fd.append('comentario', comentario);
        if (archivo) fd.append('archivo', archivo);

        fetch('index.php?action=enviarCorreccionesCarta&id_proyectos=<?= $id_proyecto ?>', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (data.ok) {
                    const now  = new Date();
                    const hora = now.toLocaleDateString('es-MX') + ' ' + now.toLocaleTimeString('es-MX', {
                        hour: '2-digit', minute: '2-digit'
                    });
                    const burbuja = document.createElement('div');
                    burbuja.className = 'msg-burbuja msg-est';
                    burbuja.innerHTML = `
                        <div>${comentario.replace(/\n/g,'<br>')}</div>
                        ${archivo ? `<div class="mt-1"><i class="bi bi-paperclip me-1 text-primary"></i><span class="small text-primary">${archivo.name}</span></div>` : ''}
                        <div class="msg-meta"><strong>Tú</strong> · Corrección · ${hora}</div>
                    `;
                    hilo.appendChild(burbuja);

                    document.getElementById('txtComentario').value = '';
                    document.getElementById('fileArchivo').value   = '';

                    mensajeEl.textContent = '✓ Correcciones enviadas. El supervisor será notificado.';
                    mensajeEl.className   = 'alert alert-success mb-3';
                    this.disabled = false;
                } else {
                    mensajeEl.textContent = data.msg || 'Error al enviar.';
                    mensajeEl.className   = 'alert alert-danger mb-3';
                    this.disabled = false;
                }
            })
            .catch(e => {
                spinner.classList.add('d-none');
                mensajeEl.textContent = 'Error de conexión: ' + e.message;
                mensajeEl.className   = 'alert alert-danger mb-3';
                this.disabled = false;
            });
    });

    // Reenviar carta corregida (sustituye el archivo)
    function subirCartaCorregida(inputEl) {
        const form    = inputEl.closest('form');
        const spinner = document.getElementById('spinner-etapa-3r');
        const msgEl   = document.getElementById('msg-etapa-3r');

        if (!inputEl.files || !inputEl.files[0]) return;

        spinner?.classList.remove('d-none');
        if (msgEl) {
            msgEl.className   = 'msg-etapa d-none';
            msgEl.textContent = '';
        }

        const fd = new FormData(form);

        fetch(form.action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                spinner?.classList.add('d-none');
                if (data.ok) {
                    if (msgEl) {
                        msgEl.innerHTML = `
                        <div class="alert alert-warning py-2 px-3 small">
                            <i class="bi bi-hourglass-split me-1"></i>
                            <strong>Carta reenviada.</strong>
                            Terminación pendiente de validación. El supervisor será notificado.
                        </div>`;
                        msgEl.className = 'msg-etapa';
                    }
                    const lbl = form.querySelector('label');
                    lbl?.classList.add('disabled');
                    lbl?.setAttribute('style', 'pointer-events:none;opacity:.6');
                } else {
                    if (msgEl) {
                        msgEl.textContent = data.msg || 'Error al enviar la carta.';
                        msgEl.className   = 'msg-etapa alert alert-danger py-1 px-2 small';
                    }
                    inputEl.value = '';
                }
            })
            .catch(err => {
                spinner?.classList.add('d-none');
                if (msgEl) {
                    msgEl.textContent = 'Error de conexión: ' + err.message;
                    msgEl.className   = 'msg-etapa alert alert-danger py-1 px-2 small';
                }
                inputEl.value = '';
            });
    }
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Correcciones — Carta de terminación';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>
