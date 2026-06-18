<?php
// Vistas/Solicitudes_proyecto/solicitud_integracion.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

//  Guard de sesión 
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$id_usuario  = (int)$_SESSION['id_usuario'];
$rol  = strtolower($_SESSION['rol'] ?? '');

// Solo estudiantes pueden ver este formulario
if ($rol !== 'estudiante') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

//  Parámetro de ruta 
$id_proyectos = isset($_GET['id_proyectos']) ? (int)$_GET['id_proyectos'] : 0;

//  Controlador 
require_once __DIR__ . '/../Controller/solicitudes_integracion_controller.php';

$ctrl  = new solicitudesControlador();

$periodoActualProyectos = $ctrl->periodoactualSolicitud();
$hoy = date('Y-m-d');
$puedeSolicitar = ($hoy >= $periodoActualProyectos['fecha_inicio_solicitud']
    && $hoy <= $periodoActualProyectos['fecha_fin_solicitud']);

if (!$puedeSolicitar) {
    header('Location: /Modules/Principal/Views/index.php');
    exit;
}

$datos = $ctrl->obtenerDatosFormulario($id_proyectos, $id_usuario);

// Redirigir si el proyecto o el estudiante no se encontraron
if (!$datos['proyecto']) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}
if (!$datos['estudiante']) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ctrl->enviarSolicitud($id_proyectos, $id_usuario, $rol);
}
//  Variables para la vista 
$proyecto   = $datos['proyecto'];
$estudiante = $datos['estudiante'];
$carreras   = $datos['carreras'];
$plantilla  = $datos['plantilla'];   // null si no hay plantilla activa aún
$puedeSolicitar = $datos['puede_solicitar'];

$nombre_completo = trim(
    htmlspecialchars($estudiante['nombre'])
        . ' ' . htmlspecialchars($estudiante['apellido_paterno'] ?? '')
        . ' ' . htmlspecialchars($estudiante['apellido_materno'] ?? '')
);

$titulo = "Solicitud de Integración — " . htmlspecialchars($proyecto['titulo']);

//  Inicio del buffer de salida 
ob_start();

//  Mensaje de éxito/error 
$msg = $_GET['msg'] ?? '';

$_mapa = [
    'exito_solicitud_enviada'       => ['tipo' => 'exito',  'titulo_msg' => 'Solicitud enviada',     'mensaje' => 'La solicitud fue enviada correctamente. Espere la respuesta del investigador.'],
    'error_solicitud'    => ['tipo' => 'error',  'titulo_msg' => 'Operación no realizada',   'mensaje' => 'La operación sobre la solicitud fue rechazada. Intenta de nuevo'],
    'error_datos'        => ['tipo' => 'error',  'titulo_msg' => 'Error con los datos',        'mensaje' => 'No fue posible la operación debido a un campo faltante por completar. Intenta de nuevo.'],
    'error_ventana_cerrada'       => ['tipo' => 'error',  'titulo_msg' => 'Error de ventana cerrada',        'mensaje' => 'No fue posible enviar la solicitud al vencer el periodo de solicitudes de integración.'],
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver la información del proyecto.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];
?>

<!-- 
     CONTENIDO
      -->


<div class="container-fluid py-4 ancho_container">
    <div class="row mb-4">
        <div class="row mb-4 align-items-center">
            <?php
            $titulo      = 'Solicitud de Integración';
            $descripcion = 'Envío de solicitud del estudiante al investigador';
            include __DIR__ . '/../../../public/incluido/_encabezado.php';
            ?>
            <div class="col-md-6 text-md-end">
                <a href="/Modules/Proyectos/Views/detalles.php?id_proyectos=<?= $id_proyectos ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            </div>
        </div>


        <!-- ALERTAS -->
        <?php

        if (isset($_mapa[$msg])) {
            extract($_mapa[$msg]);
            include __DIR__ . '/../../../public/incluido/_mensaje.php';
        }
        ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">

                <form id="formSolicitud"
                    method="POST"
                    action=""
                    enctype="multipart/form-data">

                    <input type="hidden" name="id_proyectos" value="<?= $id_proyectos ?>">
                    <input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">

                    <!--  1. Información del proyecto  -->
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
                                    class="readonly-input" disabled>
                            </div>
                            <?php if (!empty($proyecto['investigador'])): ?>
                                <div class="form-group-evento mt-3">
                                    <label>Investigador responsable</label>
                                    <input type="text"
                                        value="<?= htmlspecialchars($proyecto['investigador']) ?>"
                                        readonly
                                        class="readonly-input" disabled>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!--  2. Información del estudiante  -->
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
                                        class="readonly-input" disabled>
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
                                        <i class="bi bi-file-earmark-text"></i>
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
                                <i class="bi bi-info-circle"></i>
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
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <div class="plantilla-meta">
                                            <span><?= htmlspecialchars($plantilla['plantilla_nombre']) ?></span>
                                            <small>Versión <?= (int)$plantilla['version'] ?> · <?= strtoupper($plantilla['extension']) ?></small>
                                        </div>
                                    </div>
                                <a href="/Modules/Solicitudes_integracion_proyecto/Views/descargar_plantilla.php?id_plantilla=<?= (int)$plantilla['id_plantilla'] ?>"
                                        class="btn-descargar-plantilla"
                                        target="_blank">
                                        <i class="bi bi-file-earmark-text"></i>
                                        Descargar plantilla
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="nota-solicitud nota-warn mb-3">
                                    <i class="bi bi-info-circle"></i>
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
                                    <i class="bi bi-upload"></i>
                                    <span id="carta-label-text">Selecciona la carta compromiso firmada…</span>
                                </label>
                                <div class="carta-nombre-archivo" id="carta-nombre-preview">
                                    <i class="bi bi-file-earmark-text"></i>
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
                                <?php if ($puedeSolicitar): ?>
                                    <button type="submit" class="btn-enviar-solicitud-form">
                                        <i class="bi bi-send-fill me-1"></i>Enviar solicitud
                                    </button>
                                <?php else: ?>
                                    <div class="nota-solicitud nota-warn mt-3">
                                        <i class="bi bi-info-circle"></i>
                                        <span>Ya tienes una solicitud de integración para este proyecto o el periodo de solicitudes ha vencido.</span>
                                    </div>
                                <?php endif; ?>

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
        (function() {
            // CV opcional
            const inputCV = document.getElementById('documento');
            const labelCV = document.getElementById('cv-nombre');
            if (inputCV && labelCV) {
                inputCV.addEventListener('change', function() {
                    labelCV.textContent = this.files.length ?
                        this.files[0].name :
                        'Seleccionar archivo';
                });
            }

            // Carta compromiso obligatoria
            const inputCarta = document.getElementById('carta_compromiso');
            const preview = document.getElementById('carta-nombre-preview');
            const previewText = document.getElementById('carta-nombre-texto');
            const labelText = document.getElementById('carta-label-text');

            if (inputCarta) {
                inputCarta.addEventListener('change', function() {
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
                        labelText.textContent = 'Cambiar archivo';
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
    include __DIR__ . '/../../../layout.php';
    ?>