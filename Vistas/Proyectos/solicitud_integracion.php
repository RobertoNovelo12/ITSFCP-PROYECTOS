<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_proyecto <= 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/menu/principal.php");
    exit;
}

// Obtener información del proyecto
$sql_proyecto = "
    SELECT 
        p.id_proyectos,
        p.titulo,
        p.descripcion,
        u.nombre AS investigador
    FROM proyectos p
    LEFT JOIN usuarios u ON p.id_investigador = u.id_usuarios
    WHERE p.id_proyectos = ? AND p.id_estadoP = 1
";

$stmt_proyecto = $conn->prepare($sql_proyecto);
$stmt_proyecto->bind_param("i", $id_proyecto);
$stmt_proyecto->execute();
$result_proyecto = $stmt_proyecto->get_result();

if ($result_proyecto->num_rows === 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/menu/principal.php");
    exit;
}

$proyecto = $result_proyecto->fetch_assoc();

// Obtener información del estudiante
$sql_estudiante = "
    SELECT 
        u.id_usuarios,
        u.nombre,
        u.apellido_paterno,
        u.apellido_materno,
        u.correo_institucional,
        e.matricula,
        e.id_carrera,
        c.nombre_carrera
    FROM usuarios u
    LEFT JOIN estudiantes e ON u.id_usuarios = e.id_usuario
    LEFT JOIN carreras c ON e.id_carrera = c.id_carrera
    WHERE u.id_usuarios = ?
";

$stmt_estudiante = $conn->prepare($sql_estudiante);
$stmt_estudiante->bind_param("i", $id_usuario);
$stmt_estudiante->execute();
$result_estudiante = $stmt_estudiante->get_result();

if ($result_estudiante->num_rows === 0) {
    echo "Error: No se encontró información del estudiante.";
    exit;
}

$estudiante = $result_estudiante->fetch_assoc();

// Obtener lista de carreras para el select
$sql_carreras = "SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera";
$result_carreras = $conn->query($sql_carreras);

$titulo = "Solicitud de Integración - " . htmlspecialchars($proyecto['titulo']);

$nombre_completo = trim($estudiante['nombre'] . ' ' . ($estudiante['apellido_paterno'] ?? '') . ' ' . ($estudiante['apellido_materno'] ?? ''));

// Crear el contenido
$contenido = '
<link rel="stylesheet" href="/ITSFCP-PROYECTOS/publico/css/solicitud_integracion.css">

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id='.$id_proyecto.'" class="home-btn mb-3">
                <i class="bi bi-arrow-left"></i>
                Regresar al proyecto
            </a>
            <h2 class="fw-bold mb-0">Formulario de solicitud de integración al proyecto</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card-evento">
                <form id="formSolicitud" method="POST" action="/ITSFCP-PROYECTOS/Vistas/Proyectos/procesar_solicitud.php" enctype="multipart/form-data">
                    <input type="hidden" name="id_proyecto" value="'.$id_proyecto.'">
                    <input type="hidden" name="id_usuario" value="'.$id_usuario.'">
                    
                    <div class="card-header">
                        <h2>INFORMACIÓN DEL PROYECTO</h2>
                    </div>

                    <div class="card-body-evento">
                        <div class="form-group-evento">
                            <label>Título del proyecto</label>
                            <input type="text" value="'.htmlspecialchars($proyecto['titulo']).'" readonly class="readonly-input">
                        </div>
                    </div>

                    <div class="card-header mt-4">
                        <h2>INFORMACIÓN DEL ESTUDIANTE</h2>
                    </div>

                    <div class="card-body-evento">
                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Nombre <span class="required">*</span></label>
                                <input type="text" name="nombre" value="'.htmlspecialchars($nombre_completo).'" readonly class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Promedio general <span class="text-muted">(opcional)</span></label>
                                <input type="number" name="promedio" step="0.01" min="0" max="100" placeholder="96.5">
                            </div>
                        </div>

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Matrícula <span class="required">*</span></label>
                                <input type="text" name="matricula" value="'.htmlspecialchars($estudiante['matricula'] ?? 'Sin matrícula').'" readonly class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Motivación o intereses <span class="required">*</span></label>
                                <input type="text" name="motivacion" placeholder="Descripción breve" required>
                            </div>
                        </div>

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Correo institucional <span class="required">*</span></label>
                                <input type="email" name="correo" value="'.htmlspecialchars($estudiante['correo_institucional']).'" readonly class="readonly-input">
                            </div>
                            <div class="form-group-evento">
                                <label>Experiencia o habilidades relacionadas <span class="required">*</span></label>
                                <input type="text" name="experiencia" placeholder="Descripción breve" required>
                            </div>
                        </div>

                        <div class="form-row-evento">
                            <div class="form-group-evento">
                                <label>Carrera <span class="required">*</span></label>
                                <select name="carrera" required>
                                    <option value="">Seleccione una carrera</option>';

if ($result_carreras && $result_carreras->num_rows > 0) {
    while ($carrera = $result_carreras->fetch_assoc()) {
        $selected = ($carrera['id_carrera'] == $estudiante['id_carrera']) ? 'selected' : '';
        $contenido .= '<option value="'.$carrera['id_carrera'].'" '.$selected.'>'.htmlspecialchars($carrera['nombre_carrera']).'</option>';
    }
}

$contenido .= '
                                </select>
                            </div>
                            <div class="form-group-evento">
                                <label>Semestre actual <span class="text-muted">(opcional)</span></label>
                                <select name="semestre">
                                    <option value="">Seleccione</option>';

for ($i = 1; $i <= 12; $i++) {
    $contenido .= '<option value="'.$i.'">'.$i.'</option>';
}

$contenido .= '
                                </select>
                            </div>
                        </div>

                        <div class="form-group-evento">
                            <label>Adjuntar CV o constancias <span class="text-muted">(opcional)</span></label>
                            <div class="file-upload-wrapper">
                                <input type="file" class="file-input" name="documento" id="documento" accept=".pdf,.doc,.docx">
                                <label for="documento" class="file-upload-label">
                                    <i class="bi bi-cloud-upload me-2"></i>
                                    <span class="file-name">Seleccionar archivo</span>
                                </label>
                                <small class="text-muted d-block mt-1">Ningún archivo seleccionado</small>
                            </div>
                        </div>

                        <div class="confirmacion-box">
                            <input type="checkbox" id="confirmacion" name="confirmacion" required>
                            <label for="confirmacion">
                                <strong>Confirmación / Declaración</strong><br>
                                <small class="text-muted">Confirmo que la información proporcionada es verídica</small>
                            </label>
                        </div>

                        <div class="botones-solicitud">
                            <button type="button" class="btn-cancelar-solicitud" onclick="window.history.back()">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-enviar-solicitud-form">
                                <i class="bi bi-send me-2"></i>
                                Enviar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/ITSFCP-PROYECTOS/publico/js/solicitud_integracion.js"></script>
';

$stmt_proyecto->close();
$stmt_estudiante->close();
$conn->close();

include __DIR__ . '/../../layout.php';
?>