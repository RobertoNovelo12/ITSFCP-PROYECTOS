<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

$necesitaQuill = true;
$titulo = "Crear Evento";

$rol = $_SESSION['rol'] ?? 'estudiante';
$idUsuario = $_SESSION['id_usuario'];


// ============================================================================
// 1) ENDPOINT INTERNO PARA OBTENER ESTUDIANTES DEL PROYECTO (AJAX)
// ============================================================================
if (isset($_GET['getEstudiantes']) && isset($_GET['id_proyecto'])) {

    $idProyecto = intval($_GET['id_proyecto']);

    $sql = "
        SELECT u.id_usuarios, u.nombre, u.apellido
        FROM usuarios u
        INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = u.id_usuarios
        WHERE pu.id_proyectos = ? AND pu.estado = 'activo'
        ORDER BY u.nombre
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idProyecto);
    $stmt->execute();
    $res = $stmt->get_result();

    $estudiantes = [];
    while ($row = $res->fetch_assoc()) {
        $estudiantes[] = $row;
    }

    echo json_encode($estudiantes);
    exit;
}



// ============================================================================
// 2) CARGAR PROYECTOS SEGÚN ROL
// ============================================================================
$proyectos = [];

if ($rol === 'estudiante') {

    $stmt = $conn->prepare("
        SELECT p.id_proyectos, p.titulo
        FROM proyectos p
        INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
        WHERE pu.id_usuarios = ? AND pu.estado = 'activo'
    ");
    $stmt->bind_param("i", $idUsuario);

} elseif ($rol === 'investigador' || $rol === 'profesor') {

    $stmt = $conn->prepare("
        SELECT id_proyectos, titulo
        FROM proyectos
        WHERE id_investigador = ?
    ");
    $stmt->bind_param("i", $idUsuario);

} else {
    // supervisor / admin
    $stmt = $conn->prepare("SELECT id_proyectos, titulo FROM proyectos");
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $proyectos[] = $row;
}



// ============================================================================
// 3) PROCESAR FORMULARIO (CREAR EVENTO)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = $_POST['nombreEvento'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $ubicacion = $_POST['ubicacion'] ?? '';
    $proyecto = $_POST['proyecto'] ?? null;

    $fechaInicio = $_POST['fechaEvento'] . ' ' . $_POST['horaInicio'] . ':00';
    $fechaFin = $_POST['fechaFin'] . ' ' . $_POST['horaFin'] . ':00';

    if (!$proyecto) {
        $mensaje = "Debes seleccionar un proyecto.";
    } else {

        // Insertar evento
        $stmt = $conn->prepare("
            INSERT INTO eventos_calendario (id_proyectos, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $proyecto, $nombre, $descripcion, $fechaInicio, $fechaFin, $ubicacion);
        $stmt->execute();

        $idEvento = $stmt->insert_id;
        if (($rol === "investigador" || $rol === "profesor" || $rol === "supervisor")
            && isset($_POST['invitados'])
            && is_array($_POST['invitados'])
            && count($_POST['invitados']) > 0
        ) {

            foreach ($_POST['invitados'] as $idInvitado) {

                // Crear una tarea por invitación
                $contenido = json_encode([
                    "descripcion" => "Invitación al evento: $nombre",
                    "evento_id" => $idEvento
                ]);

                $stmtTarea = $conn->prepare("
                    INSERT INTO tareas (contenido, fecha_creacion)
                    VALUES (?, NOW())
                ");
                $stmtTarea->bind_param("s", $contenido);
                $stmtTarea->execute();
                $idTarea = $stmtTarea->insert_id;

                // Relación tarea-estudiante
                $stmtRel = $conn->prepare("
                    INSERT INTO tareas_usuarios (id_tarea, id_usuario, id_proyecto, fecha_asignacion)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmtRel->bind_param("iii", $idTarea, $idInvitado, $proyecto);
                $stmtRel->execute();
            }
        }

        $mensaje = "Evento guardado correctamente.";
    }
}



// ============================================================================
// 4) HTML
// ============================================================================
$contenido = '
<div class="container-fluid py-4 h-100">
    <div class="col-lg-12 h-100">
        <div class="card-evento">
            <div class="card-header">
                <h2>Nuevo evento</h2>
            </div>

            <div class="card-body-evento">
                ' . (isset($mensaje) ? '<div class="alert alert-success">'.$mensaje.'</div>' : '') . '

                <form method="POST" id="formEvento">

                    <div class="form-group-evento">
                        <label for="nombreEvento">Nombre del evento <span class="required">*</span></label>
                        <input type="text" name="nombreEvento" id="nombreEvento" required>
                    </div>

                    <div class="form-row-evento">
                        <div class="form-group-evento">
                            <label>Fecha inicio</label>
                            <input type="date" name="fechaEvento" id="fechaEvento" required>
                        </div>

                        <div class="form-group-evento">
                            <label>Fecha fin</label>
                            <input type="date" name="fechaFin" id="fechaFin" required>
                        </div>

                        <div class="form-group-evento">
                            <label>Hora inicio</label>
                            <input type="time" name="horaInicio" id="horaInicio" required>
                        </div>

                        <div class="form-group-evento">
                            <label>Hora fin</label>
                            <input type="time" name="horaFin" id="horaFin" required>
                        </div>
                    </div>

                    <div class="form-group-evento">
                        <label for="proyecto">Proyecto</label>
                        <select name="proyecto" id="proyecto" required>
                            <option value="">Seleccionar proyecto</option>';

foreach ($proyectos as $p) {
    $contenido .= '<option value="' . $p['id_proyectos'] . '">' . htmlspecialchars($p['titulo']) . '</option>';
}

$contenido .= '
                        </select>
                    </div>';


// Mostrar lista de invitados solo a profesores/investigadores
if ($rol === "investigador" || $rol === "profesor" || $rol === "supervisor") {

$contenido .= '
                    <div class="form-group-evento">
                        <label>Invitar estudiantes:</label>
                        <div id="listaEstudiantes" class="invitados-box">
                            <p class="small text-muted">Seleccione un proyecto...</p>
                        </div>
                    </div>';
}

$contenido .= '

                    <div class="form-group-evento">
                        <label>Descripción</label>
                        <div id="editorDescripcion" style="height:150px;"></div>
                        <input type="hidden" name="descripcion" id="descripcion">
                    </div>

                    <div class="form-group-evento">
                        <label for="ubicacion">Ubicación / Plataforma</label>
                        <input type="text" name="ubicacion" id="ubicacion">
                    </div>

                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-check-lg"></i> Crear evento
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="/ITSFCP-PROYECTOS/publico/js/evento.js"></script>
';

include __DIR__ . "/../../layout.php";
?>