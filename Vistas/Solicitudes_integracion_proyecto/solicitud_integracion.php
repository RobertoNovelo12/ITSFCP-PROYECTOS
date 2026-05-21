<?php
// Vistas/Solicitudes_proyecto/solicitud_integracion.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

//  Guard de sesión ─
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_usuario  = (int)$_SESSION['id_usuario'];
$rol         = strtolower($_SESSION['rol'] ?? '');

// Solo estudiantes pueden ver este formulario
if ($rol !== 'estudiante') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

//  Parámetro de ruta ─
$id_proyecto = isset($_GET['id_proyecto']) ? (int)$_GET['id_proyecto'] : 0;
if ($id_proyecto <= 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

//  Controlador ─
require_once __DIR__ . '/../../Controladores/solicitudesControlador.php';

$ctrl  = new solicitudesControlador();

$periodoActualProyectos = $ctrl->periodoactualSolicitud();
$hoy = date('Y-m-d');
$puedeSolicitar = ($hoy >= $periodoActualProyectos['fecha_inicio_pfecha_ifecha_inicio_solicitudnicio_solicitudroyectos']
    && $hoy <= $periodoActualProyectos['fecha_fin_solicitud']);

if (!$puedeSolicitar) {
    header('Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php');
    exit;
}

$datos = $ctrl->obtenerDatosFormulario($id_proyecto, $id_usuario);

// Redirigir si el proyecto o el estudiante no se encontraron
if (!$datos['proyecto']) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}
if (!$datos['estudiante']) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

//  Variables para la vista ─
$proyecto   = $datos['proyecto'];
$estudiante = $datos['estudiante'];
$carreras   = $datos['carreras'];
$plantilla  = $datos['plantilla'];   // null si no hay plantilla activa aún

$nombre_completo = trim(
    htmlspecialchars($estudiante['nombre'])
    . ' ' . htmlspecialchars($estudiante['apellido_paterno'] ?? '')
    . ' ' . htmlspecialchars($estudiante['apellido_materno'] ?? '')
);

// Mensaje de error de sesión (enviado por solicitudesControlador vía PRG)
$error_msg = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : null;

$titulo = "Solicitud de Integración — " . htmlspecialchars($proyecto['titulo']);

//  Inicio del buffer de salida 
ob_start();
?>



<!-- 
     CONTENIDO
      -->
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-8">
            <h2 class="fw-bold mb-0" style="color:var(--color-primario);">
                Solicitud de integración al proyecto
            </h2>
            <p class="mb-0" style="font-size:13px;color:var(--color-texto-secundario);">
                Completa el formulario para enviar tu solicitud al investigador responsable.
            </p>
        </div>
        <div class="col-4">
            <!-- Botón Regresar -->
                <a href="/ITSFCP-PROYECTOS/Vistas/Principal/detalles_proyecto.php?id_proyecto=<?= $id_proyecto ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
        </div>
    </div>

    <?php if ($error_msg): ?>
        <div class="nota-solicitud nota-error mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 20 20">
                <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4"/>
                <path d="M7 7l6 6M13 7l-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span><?= $error_msg ?></span>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mx-auto">

            <form id="formSolicitud"
                  method="POST"
                  action="/ITSFCP-PROYECTOS/publico/config/procesar_solicitud_proyecto.php"
                  enctype="multipart/form-data">

                <input type="hidden" name="id_proyecto" value="<?= $id_proyecto ?>">
                <input type="hidden" name="id_usuario"  value="<?= $id_usuario ?>">

                <!--  1. Información del proyecto ─ -->
                <div class="card-evento">
                    <div class="card-header">
                        <h2>Información del proyecto</h2>
                    </div>
                    <div class="card-body-evento">
                        <div class="form-group-evento">
                            <label>Título del proyecto</label>
                            <input type="text"
                                   value="<?= htmlspecialchars($proyecto['titulo']) ?>"
                                   readonly
                                   class="readonly-input">
                        </div>
                        <?php if (!empty($proyecto['investigador'])): ?>
                            <div class="form-group-evento mt-3">
                                <label>Investigador responsable</label>
                                <input type="text"
                                       value="<?= htmlspecialchars($proyecto['investigador']) ?>"
                                       readonly
                                       class="readonly-input">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!--  2. Información del estudiante ─ -->
                <div class="card-evento">
                    <div class="card-header">
                        <h2>Información del estudiante</h2>
                    </div>
                    <div class="card-body-evento">

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Nombre completo <span class="required">*</span></label>
                                <input type="text"
                                       value="<?= $nombre_completo ?>"
                                       readonly
                                       class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Promedio general <span style="font-weight:400;color:var(--color-texto-secundario);">(opcional)</span></label>
                                <input type="number"
                                       name="promedio"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       placeholder="Ej. 88.5">
                            </div>
                        </div>

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Matrícula <span class="required">*</span></label>
                                <input type="text"
                                       value="<?= htmlspecialchars($estudiante['matricula'] ?? 'Sin matrícula') ?>"
                                       readonly
                                       class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Semestre actual <span style="font-weight:400;color:var(--color-texto-secundario);">(opcional)</span></label>
                                <select name="semestre">
                                    <option value="">Seleccione</option>
                                    <?php for ($i = 1; $i <= 7; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?>°</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Correo institucional <span class="required">*</span></label>
                                <input type="email"
                                       value="<?= htmlspecialchars($estudiante['correo_institucional']) ?>"
                                       readonly
                                       class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Carrera <span class="required">*</span></label>
                                <select name="carrera" required>
                                    <option value="">Seleccione una carrera</option>
                                    <?php foreach ($carreras as $c): ?>
                                        <option value="<?= $c['id_carrera'] ?>"
                                            <?= ((int)($estudiante['id_carrera'] ?? 0) === (int)$c['id_carrera']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['nombre_carrera']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group-evento">
                            <label>Motivación o intereses <span class="required">*</span></label>
                            <textarea name="motivacion"
                                      placeholder="Describe brevemente por qué te interesa este proyecto y qué esperas aprender…"
                                      required
                                      maxlength="500"></textarea>
                        </div>

                        <div class="form-group-evento mt-3">
                            <label>Experiencia o habilidades relacionadas <span class="required">*</span></label>
                            <textarea name="experiencia"
                                      placeholder="Menciona herramientas, conocimientos previos o proyectos similares en los que hayas participado…"
                                      required
                                      maxlength="500"></textarea>
                        </div>

                        <!-- CV / constancias (opcional) -->
                        <div class="form-group-evento mt-3">
                            <label>CV o constancias <span style="font-weight:400;color:var(--color-texto-secundario);">(opcional)</span></label>
                            <div class="file-upload-wrapper">
                                <input type="file"
                                       class="file-input"
                                       name="documento"
                                       id="documento"
                                       accept=".pdf,.doc,.docx">
                                <label for="documento" class="file-upload-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                                        <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/>
                                    </svg>
                                    <span id="cv-nombre">Seleccionar archivo</span>
                                </label>
                                <small class="carta-formatos">PDF, DOC o DOCX · Máx. 8 MB</small>
                            </div>
                        </div>

                    </div>
                </div>

                <!--  3. Carta compromiso  -->
                <div class="card-evento">
                    <div class="card-header">
                        <h2>Carta compromiso</h2>
                    </div>
                    <div class="card-body-evento">

                        <!-- Nota explicativa -->
                        <div class="nota-solicitud mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 20 20">
                                <circle cx="10" cy="10" r="8.5" stroke="currentColor" stroke-width="1.4"/>
                                <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="10" cy="6.5" r="0.8" fill="currentColor"/>
                            </svg>
                            <span>
                                La carta compromiso es un documento <strong>obligatorio</strong>.
                                Descarga la plantilla, fírmala y súbela en formato PDF, DOCX o PNG.
                                El investigador revisará el documento antes de aceptar tu solicitud.
                            </span>
                        </div>

                        <!-- Paso 1: descargar plantilla -->
                        <div class="paso-indicador mb-2">
                            <span class="paso-num">1</span>
                            Descarga la plantilla oficial
                        </div>

                        <?php if ($plantilla): ?>
                            <div class="plantilla-bloque">
                                <div class="plantilla-info">
                                    <div class="plantilla-icono">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
                                            <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2z"/>
                                        </svg>
                                    </div>
                                    <div class="plantilla-meta">
                                        <span><?= htmlspecialchars($plantilla['plantilla_nombre']) ?></span>
                                        <small>Versión <?= (int)$plantilla['version'] ?> · <?= strtoupper($plantilla['extension']) ?></small>
                                    </div>
                                </div>
                                <a href="/ITSFCP-PROYECTOS/Vistas/Solicitudes_integracion_proyecto/descargar_plantilla.php?id_plantilla=<?= (int)$plantilla['id_plantilla'] ?>"
                                   class="btn-descargar-plantilla"
                                   target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                                        <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/>
                                    </svg>
                                    Descargar plantilla
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="nota-solicitud nota-warn mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 20 20">
                                    <path d="M9.13 3.4L2.2 15.1A1 1 0 0 0 3.07 16.6h13.86a1 1 0 0 0 .87-1.5L10.87 3.4a1 1 0 0 0-1.74 0z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                                    <path d="M10 8.5v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="10" cy="13.8" r="0.75" fill="currentColor"/>
                                </svg>
                                <span>La plantilla de carta compromiso <strong>aún no está disponible</strong>. Contacta al coordinador del programa.</span>
                            </div>
                        <?php endif; ?>

                        <!-- Paso 2: subir carta firmada -->
                        <div class="paso-indicador mb-2 mt-3">
                            <span class="paso-num">2</span>
                            Sube la carta firmada
                        </div>

                        <div class="carta-upload-wrap">
                            <input type="file"
                                   class="file-input"
                                   name="carta_compromiso"
                                   id="carta_compromiso"
                                   accept=".pdf,.docx,.png"
                                   required>
                            <label for="carta_compromiso" class="carta-upload-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                                </svg>
                                <span id="carta-label-text">Selecciona la carta compromiso firmada…</span>
                            </label>
                            <div class="carta-nombre-archivo" id="carta-nombre-preview">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1L14 5.5z"/>
                                </svg>
                                <span id="carta-nombre-texto"></span>
                            </div>
                            <small class="carta-formatos">PDF, DOCX o PNG · Máx. 10 MB</small>
                        </div>

                    </div>
                </div>

                <!--  4. Confirmación y envío  -->
                <div class="card-evento">
                    <div class="card-body-evento">

                        <div class="confirmacion-box">
                            <input type="checkbox" id="confirmacion" name="confirmacion" required>
                            <label for="confirmacion">
                                <strong>Declaración de veracidad</strong><br>
                                <small>Confirmo que toda la información proporcionada en este formulario es verídica y que he leído los requisitos del proyecto.</small>
                            </label>
                        </div>

                        <div class="botones-solicitud">
                            <button type="button"
                                    class="btn-cancelar-solicitud"
                                    onclick="window.history.back()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-enviar-solicitud-form">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.109"/>
                                </svg>
                                Enviar solicitud
                            </button>
                        </div>

                    </div>
                </div>

            </form>
        </div><!-- /col -->
    </div><!-- /row -->
</div><!-- /container-fluid -->

<!-- 
     JS: nombre de archivo al seleccionar
      -->
<script>
(function () {
    // CV opcional
    const inputCV    = document.getElementById('documento');
    const labelCV    = document.getElementById('cv-nombre');
    if (inputCV && labelCV) {
        inputCV.addEventListener('change', function () {
            labelCV.textContent = this.files.length
                ? this.files[0].name
                : 'Seleccionar archivo';
        });
    }

    // Carta compromiso obligatoria
    const inputCarta  = document.getElementById('carta_compromiso');
    const preview     = document.getElementById('carta-nombre-preview');
    const previewText = document.getElementById('carta-nombre-texto');
    const labelText   = document.getElementById('carta-label-text');

    if (inputCarta) {
        inputCarta.addEventListener('change', function () {
            if (this.files.length) {
                const archivo = this.files[0];
                // Validar tamaño en cliente (10 MB)
                if (archivo.size > 10 * 1024 * 1024) {
                    alert('El archivo supera el tamaño máximo de 10 MB.');
                    this.value = '';
                    preview.classList.remove('visible');
                    labelText.textContent = 'Selecciona la carta compromiso firmada…';
                    return;
                }
                previewText.textContent = archivo.name;
                preview.classList.add('visible');
                labelText.textContent   = 'Cambiar archivo';
            } else {
                preview.classList.remove('visible');
                labelText.textContent = 'Selecciona la carta compromiso firmada…';
            }
        });
    }
})();
</script>

<?php
//  Ensamblado final con layout 
$contenido = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>