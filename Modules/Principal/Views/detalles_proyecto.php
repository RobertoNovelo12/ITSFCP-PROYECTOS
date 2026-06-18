<?php
// Vistas/Principal/detalles_proyecto.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$rol = strtolower($_SESSION['rol'] ?? '');

// Todos los roles pueden acceder
if (!in_array($rol, ['investigador', 'profesor', 'estudiante', 'supervisor'], true)) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

// Validar parámetro de ruta
$id_proyectos = isset($_GET['id_proyectos']) ? (int)$_GET['id_proyectos'] : 0;

// Controlador
require_once __DIR__ . '/../Controller/principal_controller.php';
$controlador = new principalControlador();

//  Acciones POST (antes de cualquier salida o consulta) 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar_solicitud' && $rol === 'estudiante') {
        $id_proy_post = (int)($_POST['id_proyectos'] ?? 0);
        $controlador->cancelar($id_usuario, $id_proy_post);
        // cancelar() redirige, nunca llega aquí
    }
}

$datos = $controlador->obtenerDatos($id_proyectos, $id_usuario, $rol);

// Si el proyecto no existe, redirigir
$registro = $datos;
include __DIR__ . '/../../../public/incluido/_validar_datos.php';


// Extraer variables para la vista
$proyecto             = $datos['proyecto'];
$es_integrante        = $datos['es_integrante'];
$solicitud            = $datos['solicitud'];       // null | ['id_solicitud_proyecto','estado']
$ventana_abierta      = $datos['ventana_abierta'];
$puede_solicitar      = $datos['puede_solicitar'];
$puede_cancelar       = $datos['puede_cancelar'];
$solicitud_bloqueante = $datos['solicitud_bloqueante']; 

// Mensaje de solicitud (tras redireccionamiento con ?solicitud=...)
$mensaje_solicitud = $controlador->leerMensajeSolicitud();

//  Helpers de presentación 

/**
 * Muestra un valor o "No especificado" en cursiva si está vacío.
 * Tipo 'html' aplica nl2br + htmlspecialchars.
 */
function mostrarValor($valor, string $tipo = 'texto'): string
{
    if ($valor === null || $valor === '') {
        return '<span class="fst-italic">No especificado</span>';
    }
    if ($tipo === 'html') {
        return nl2br(htmlspecialchars($valor));
    }
    return htmlspecialchars($valor);
}

$titulo = "Detalles del Proyecto - " . htmlspecialchars($proyecto['titulo']);

//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_cancelar'     => ['tipo' => 'exito',  'titulo_msg' => 'Solicitud cancelada',       'mensaje' => 'Tu solicitud ha sido cancelada exitosamente.'],
    'error_cancelar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al cancelar',         'mensaje' => 'La acción solicitada no fue posible realizarse. Intentelo más tarde.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.', 'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
];

//  Construcción del HTML del contenido 
ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- Encabezado -->
    <div class="row mb-4">
        <?php
        $titulo      = 'Detalle';
        $descripcion = 'Información detallada del proyecto seleccionado';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-6">
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '/../../../public/incluido/_mensaje.php';
    endif; ?>


    <div class="row">

        <!--  Columna principal: secciones desplegables  -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">

                    <!-- Descripción (expandida por defecto) -->
                    <div class="detalle-section-collapsible">
                        <button class="detalle-header" type="button"
                            onclick="toggleSection('descripcion')">
                            <h5 class="mb-0">Descripción</h5>
                            <i class="bi bi-chevron-down toggle-icon rotated"
                                id="icon-descripcion"></i>
                        </button>
                        <div class="detalle-content" id="content-descripcion">
                            <p><?= mostrarValor($proyecto['descripcion'], 'html') ?></p>
                        </div>
                    </div>

                    <!-- Objetivo -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button"
                            onclick="toggleSection('objetivo')">
                            <h5 class="mb-0">Objetivo</h5>
                            <i class="bi bi-chevron-down toggle-icon"
                                id="icon-objetivo"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-objetivo">
                            <p><?= mostrarValor($proyecto['objetivo'], 'html') ?></p>
                        </div>
                    </div>

                    <!-- Pre-requisitos -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button"
                            onclick="toggleSection('prerequisitos')">
                            <h5 class="mb-0">Pre-requisitos</h5>
                            <i class="bi bi-chevron-down toggle-icon"
                                id="icon-prerequisitos"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-prerequisitos">
                            <p><?= mostrarValor($proyecto['pre_requisitos'], 'html') ?></p>
                        </div>
                    </div>

                    <!-- Requisitos -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button"
                            onclick="toggleSection('requisitos')">
                            <h5 class="mb-0">Requisitos</h5>
                            <i class="bi bi-chevron-down toggle-icon"
                                id="icon-requisitos"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-requisitos">
                            <div class="requisitos-list">
                                <?= mostrarValor($proyecto['requisitos'], 'html') ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!--  Columna lateral: ficha del proyecto  -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header-proyecto">
                    <h6 class="mb-0 fw-bold">Información del Proyecto</h6>
                </div>
                <div class="card-body" style="border-radius:0 0 10px 10px !important;">

                    <div class="info-item mb-3">
                        <small class="info-label">Temática</small>
                        <span><?= mostrarValor($proyecto['tematica']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Subtémática</small>
                        <span><?= mostrarValor($proyecto['subtematica']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Modalidad</small>
                        <span>
                            <?php if (!empty($proyecto['modalidad'])): ?>
                                <span class="badge <?= $controlador->badgeModalidad($proyecto['modalidad']) ?>">
                                    <?= ucfirst(htmlspecialchars($proyecto['modalidad'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="fst-italic">No especificado</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Estado</small>
                        <span>
                            <span class="badge <?= $controlador->badgeEstado((int)$proyecto['id_estadoP']) ?>">
                                <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                            </span>
                        </span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Alumnos permitidos</small>
                        <span><?= mostrarValor($proyecto['cantidad_estudiante']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Lugares disponibles</small>
                        <span><?= mostrarValor($proyecto['lugares_disponibles']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Instituto</small>
                        <span><?= mostrarValor($proyecto['instituto']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Periodo</small>
                        <span><?= mostrarValor($proyecto['periodo']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Investigador</small>
                        <span><?= mostrarValor($proyecto['investigador']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Email del investigador</small>
                        <span>
                            <?php if (!empty($proyecto['email_investigador'])): ?>
                                <a href="mailto:<?= htmlspecialchars($proyecto['email_investigador']) ?>"
                                    class="email-link">
                                    <?= htmlspecialchars($proyecto['email_investigador']) ?>
                                </a>
                            <?php else: ?>
                                <span class="fst-italic">No especificado</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Fecha de inicio</small>
                        <span><?= mostrarValor($proyecto['fecha_inicio']) ?></span>
                    </div>

                    <div class="info-item mb-3">
                        <small class="info-label">Fecha de fin</small>
                        <span><?= mostrarValor($proyecto['fecha_fin']) ?></span>
                    </div>

                    <div class="info-item">
                        <small class="info-label">Fecha de creación</small>
                        <span><?= mostrarValor($proyecto['fecha_creacion']) ?></span>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!--  Botones de acción  -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="acciones-container">

                <!-- Botón Regresar -->
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>

                <?php if ($rol === 'estudiante'): ?>

                    <?php if ($es_integrante): ?>
                        <!-- Ya es miembro -->
                        <span class="fst-italic text-success">
                            <i class="bi bi-check-circle"></i> Ya eres miembro de este proyecto
                        </span>

                    <?php elseif ($puede_solicitar): ?>
                        <!-- Ventana abierta y sin solicitud activa → puede enviar -->
                        <a href="/Proyecto/ITSFCP-PROYECTOS/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos=<?= $id_proyectos ?>"
                            class="btn-enviar-solicitud">
                            <i class="bi bi-send"></i> Solicitud
                        </a>

                    <?php elseif ($puede_cancelar): ?>
                        <!-- Solicitud activa que puede cancelarse -->
                        <form method="POST" action="detalles_proyecto.php?id_proyectos=<?= $id_proyectos ?>"
                            onsubmit="return confirm('¿Deseas cancelar tu solicitud? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="accion" value="cancelar_solicitud">
                            <input type="hidden" name="id_proyectos" value="<?= $id_proyectos ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle"></i> Cancelar solicitud
                            </button>
                        </form>

                    <?php elseif ($solicitud_bloqueante): ?>
                        <!-- Solicitud rechazada o vencida en el período activo: no puede volver a solicitar -->
                        <?php
                            $estado_bloqueo = $solicitud['estado'] ?? '';
                            if ($estado_bloqueo === 'rechazado'):
                        ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2 px-3" role="alert">
                                <i class="bi bi-x-octagon-fill fs-5"></i>
                                <div>
                                    <strong>Solicitud rechazada.</strong>
                                    Tu solicitud para este proyecto fue rechazada en el período actual
                                    y no puedes volver a solicitarlo.
                                </div>
                            </div>
                        <?php elseif ($estado_bloqueo === 'vencido'): ?>
                            <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2 px-3" role="alert">
                                <i class="bi bi-hourglass-bottom fs-5"></i>
                                <div>
                                    <strong>Solicitud vencida.</strong>
                                    El período de revisión de tu solicitud anterior venció
                                    y no puedes volver a solicitarlo en este período.
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Estado bloqueante detectado vía BD (p.ej. la última solicitud visible era cancelado pero hay una rechazada/vencida anterior) -->
                            <div class="alert alert-secondary d-flex align-items-center gap-2 mb-0 py-2 px-3" role="alert">
                                <i class="bi bi-slash-circle fs-5"></i>
                                <div>
                                    <strong>No disponible.</strong>
                                    Ya no puedes enviar una solicitud para este proyecto en el período actual.
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif (!$ventana_abierta): ?>
                        <!-- Fuera de la ventana de solicitud -->
                        <span class="fst-italic text-muted">
                            <i class="bi bi-clock"></i> El período de solicitudes no está disponible
                        </span>

                    <?php endif; ?>

                <?php endif; ?>
                <!-- Investigador, profesor y supervisor NO ven ningún botón de solicitud -->

            </div>
        </div>
    </div>

</div>

<!--  Acordeón: JS  -->
<script>
    function toggleSection(sectionId) {
        const content = document.getElementById('content-' + sectionId);
        const icon    = document.getElementById('icon-'    + sectionId);
        content.classList.toggle('collapsed');
        icon.classList.toggle('rotated');
    }
</script>

<?php
//  Ensamblado final con layout 
$contenido = ob_get_clean();
$bodyClass = 'proyectos-page';

include __DIR__ . '/../../../layout.php';
?>