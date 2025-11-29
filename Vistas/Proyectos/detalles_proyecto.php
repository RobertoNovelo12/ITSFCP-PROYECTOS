<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Obtener el ID del proyecto
$id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_proyecto <= 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/index.php");
    exit;
}

// Consulta para obtener los detalles completos del proyecto
$sql = "
    SELECT 
        p.id_proyectos,
        p.titulo,
        p.descripcion,
        p.objetivo,
        p.requisitos,
        p.pre_requisitos,
        p.modalidad,
        p.cantidad_estudiante,
        t.nombre_tematica AS tematica,
        u.nombre AS investigador,
        u.correo_institucional AS email_investigador,
        DATE_FORMAT(p.fecha_inicio, '%d/%m/%Y') AS fecha_inicio,
        DATE_FORMAT(p.fecha_fin, '%d/%m/%Y') AS fecha_fin,
        DATE_FORMAT(p.creado_en, '%d/%m/%Y') AS fecha_creacion
    FROM proyectos p
    LEFT JOIN tematica t ON p.id_tematica = t.id_tematica
    LEFT JOIN usuarios u ON p.id_investigador = u.id_usuarios
    WHERE p.id_proyectos = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/menu/principal.php");
    exit;
}

$proyecto = $result->fetch_assoc();
$titulo = "Detalles del Proyecto - " . htmlspecialchars($proyecto['titulo']);

// Función para mostrar valor o "No especificado"
function mostrarValor($valor, $tipo = 'texto') {
    if (empty($valor) || is_null($valor)) {
        return '<span class=" fst-italic">No especificado</span>';
    }
    
    if ($tipo === 'html') {
        return nl2br(htmlspecialchars($valor));
    }
    
    return htmlspecialchars($valor);
}

// Crear el contenido
$contenido = '

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold mb-0">'.htmlspecialchars($proyecto['titulo']).'</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                <!-- Descripción -->
                <div class="detalle-section-collapsible">
                    <button class="detalle-header" type="button" onclick="toggleSection(\'descripcion\')">
                        <h5 class="mb-0">Descripción</h5>
                        <i class="bi bi-chevron-down toggle-icon rotated" id="icon-descripcion"></i>
                    </button>
                    <div class="detalle-content" id="content-descripcion">
                        <p class="">'.mostrarValor($proyecto['descripcion'], 'html').'</p>
                    </div>
                </div>

                    <!-- Objetivo -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button" onclick="toggleSection(\'objetivo\')">
                            <h5 class="mb-0">Objetivo</h5>
                            <i class="bi bi-chevron-down toggle-icon" id="icon-objetivo"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-objetivo">
                            <p class="">'.mostrarValor($proyecto['objetivo'], 'html').'</p>
                        </div>
                    </div>

                    <!-- Pre-requisitos -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button" onclick="toggleSection(\'prerequisitos\')">
                            <h5 class="mb-0">Pre-requisitos</h5>
                            <i class="bi bi-chevron-down toggle-icon" id="icon-prerequisitos"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-prerequisitos">
                            <p class="">'.mostrarValor($proyecto['pre_requisitos'], 'html').'</p>
                        </div>
                    </div>

                    <!-- Requisitos -->
                    <div class="detalle-section-collapsible mt-3">
                        <button class="detalle-header" type="button" onclick="toggleSection(\'requisitos\')">
                            <h5 class="mb-0">Requisitos</h5>
                            <i class="bi bi-chevron-down toggle-icon" id="icon-requisitos"></i>
                        </button>
                        <div class="detalle-content collapsed" id="content-requisitos">
                            <div class="requisitos-list">
                                '.mostrarValor($proyecto['requisitos'], 'html').'
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header-proyecto">
                    <h6 class="mb-0 fw-bold">Información del Proyecto</h6>
                </div>
                <div class="card-body">
                    <div class="info-item mb-3">
                        <small class="info-label">Temática</small>
                        <span>'.mostrarValor($proyecto['tematica']).'</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Modalidad</small>
                        <span>'.(!empty($proyecto['modalidad']) ? ucfirst(htmlspecialchars($proyecto['modalidad'])) : '<span class=" fst-italic">No especificado</span>').'</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Alumnos permitidos</small>
                        <span>'.mostrarValor($proyecto['cantidad_estudiante']).'</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Investigador</small>
                        <span>'.mostrarValor($proyecto['investigador']).'</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Email del investigador</small>
                        <span>';

if (!empty($proyecto['email_investigador'])) {
    $contenido .= '<a href="mailto:'.htmlspecialchars($proyecto['email_investigador']).'" class="email-link">
                            '.htmlspecialchars($proyecto['email_investigador']).'
                          </a>';
} else {
    $contenido .= '<span class=" fst-italic">No especificado</span>';
}

$contenido .= '</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Fecha de inicio</small>
                        <span>'.mostrarValor($proyecto['fecha_inicio']).'</span>
                    </div>
                    
                    <div class="info-item mb-3">
                        <small class="info-label">Fecha de fin</small>
                        <span>'.mostrarValor($proyecto['fecha_fin']).'</span>
                    </div>
                    
                    <div class="info-item">
                        <small class="info-label">Fecha de creación</small>
                        <span>'.mostrarValor($proyecto['fecha_creacion']).'</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="acciones-container">
                <a href="/ITSFCP-PROYECTOS/Vistas/menu/principal.php" class="home-btn">
                    <i class="bi bi-arrow-left"></i>
                    Regresar
                </a>
                <a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/solicitud_integracion.php?id='.$proyecto['id_proyectos'].'" class="btn-enviar-solicitud">
                    <i class="bi bi-send"></i>
                    Enviar Solicitud de integración
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSection(sectionId) {
    const content = document.getElementById("content-" + sectionId);
    const icon = document.getElementById("icon-" + sectionId);
    
    content.classList.toggle("collapsed");
    icon.classList.toggle("rotated");
}
</script>
';

$stmt->close();
$conn->close();

include __DIR__ . '/../../layout.php';
?>