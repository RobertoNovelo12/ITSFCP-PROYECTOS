<?php
// Vistas/Principal/detalles_proyecto.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$rol = strtolower($_SESSION['rol'] ?? '');

//Todos los roles pueden acceder
if (!in_array($rol, ['investigador', 'profesor', 'estudiante', 'supervisor'], true)) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}


// Validar parámetro de ruta
$id_proyectos = isset($_GET['id_proyectos']) ? (int)$_GET['id_proyectos'] : 0;



//  Controlador 
require_once __DIR__ .  '/../../Controladores/principalControlador.php';

$controlador = new principalControlador();
$datos       = $controlador->obtenerDatos($id_proyectos, $id_usuario, $rol);

// Si el proyecto no existe, redirigir
$registro = $datos;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


// Extraer variables para la vista (evitar $datos['x'] en el HTML)
$proyecto        = $datos['proyecto'];
$es_integrante   = $datos['es_integrante'];
$solicitud       = $datos['solicitud'];       // null | ['id_solicitud_proyecto','estado']
$ventana_abierta = $datos['ventana_abierta'];
$puede_solicitar = $datos['puede_solicitar'];
$puede_cancelar  = $datos['puede_cancelar'];

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

//  Construcción del HTML del contenido 
ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- Encabezado -->
    <div class="row mb-4">
        <?php
        $titulo      = 'Detalle';
        $descripcion = 'Información detallada del proyecto seleccionado';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6">
        </div>
    </div>

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

                </div><!-- /card-body -->
            </div><!-- /card -->
        </div><!-- /col-lg-8 -->

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

                </div><!-- /card-body -->
            </div><!-- /card -->
        </div><!-- /col-lg-4 -->

    </div><!-- /row -->

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
                        <!-- Ventana abierta y sin solicitud activa -> puede enviar -->
                        <a href="/ITSFCP-PROYECTOS/Vistas/Solicitudes_integracion_proyecto/solicitud_integracion.php?id_proyectos=<?= $id_proyectos ?>"
                            class="btn-enviar-solicitud">
                            <i class="bi bi-send"></i> Solicitud
                        </a>

                    <?php elseif ($puede_cancelar): ?>
                        <!-- Tiene solicitud activa → puede cancelar -->
                        <button class="btn-enviar-solicitud" style="background:#d9534f;"
                            onclick="abrirModalCancelar()">
                            <i class="bi bi-x-circle"></i> Cancelar solicitud
                        </button>

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

</div><!-- /container-fluid -->

<!--  Sección de acordeón: JS  -->
<script>
    function toggleSection(sectionId) {
        const content = document.getElementById('content-' + sectionId);
        const icon = document.getElementById('icon-' + sectionId);
        content.classList.toggle('collapsed');
        icon.classList.toggle('rotated');
    }
</script>

<?php
//  Modales (se incluyen siempre; cada uno decide si renderiza) 
include __DIR__ . '/modales/modal_cancelar_solicitud.php';
include __DIR__ . '/modales/modal_mensaje_solicitud.php';
?>

<?php
//  Ensamblado final con layout 
$contenido  = ob_get_clean();
$bodyClass  = 'proyectos-page';

include __DIR__ . '/../../layout.php';
?>