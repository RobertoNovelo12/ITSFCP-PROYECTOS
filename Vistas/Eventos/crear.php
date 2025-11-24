<?php
if (!isset($_SESSION))
    session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

$necesitaQuill = true;
$titulo = "Crear Evento";

// Rol e ID del usuario
$rol = $_SESSION['rol'] ?? 'estudiante';
$idUsuario = $_SESSION['id_usuario']; 

// ===============================
//  CARGAR PROYECTOS
// ===============================
$proyectos = [];

if ($rol === 'estudiante') {
    // Proyectos donde participa el estudiante
    $stmt = $conn->prepare("
        SELECT p.id_proyectos, p.titulo
        FROM proyectos p
        INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
        WHERE pu.id_usuarios = ? AND pu.estado = 'activo'
    ");
    $stmt->bind_param("i", $idUsuario);

} elseif ($rol === 'investigador') {
    // Proyectos del investigador (también considerado profesor)
    $stmt = $conn->prepare("
        SELECT id_proyectos, titulo
        FROM proyectos
        WHERE id_investigador = ?
    ");
    $stmt->bind_param("i", $idUsuario);

} else {
    // Administrador o Supervisor: todos los proyectos
    $stmt = $conn->prepare("
        SELECT id_proyectos, titulo
        FROM proyectos
    ");
}


$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $proyectos[] = $row;
}


// ===============================
//  PROCESAR FORMULARIO
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = $_POST['nombreEvento'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $ubicacion = $_POST['ubicacion'] ?? '';

    $fechaInicio = $_POST['fechaEvento'] . ' ' . $_POST['horaInicio'] . ':00';
    $fechaFin = $_POST['fechaFin'] . ' ' . $_POST['horaFin'] . ':00';


    $proyecto = $_POST['proyecto'] ?? null;

    if (!$proyecto) {
        $mensaje = "Debes seleccionar un proyecto.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO eventos_calendario (id_proyectos, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssss",
            $proyecto,
            $nombre,
            $descripcion,
            $fechaInicio,
            $fechaFin,
            $ubicacion
        );

        $stmt->execute();
        $mensaje = "Evento guardado correctamente.";
    }
}


// ===============================
//  HTML
// ===============================
$contenido = '
<div class="container-fluid py-4 h-100">
    <div class="col-lg-12 h-100">
        <div class="card-evento">
            <div class="card-header">
                <h2>Nuevo evento</h2>
            </div>

            <div class="card-body-evento">
                ' . (isset($mensaje) ? '<div class="alert alert-success">' . $mensaje . '</div>' : '') . '

                <form method="POST" id="formEvento">

                    <div class="form-group-evento">
                        <label for="nombreEvento">Nombre del evento <span class="required">*</span></label>
                        <input type="text" name="nombreEvento" id="nombreEvento" required>
                    </div>

                    <div class="form-row-evento">
                        <div class="form-group-evento">
                            <label for="fechaEvento">Fecha de inicio <span class="required">*</span></label>
                            <input type="date" name="fechaEvento" id="fechaEvento" required>
                        </div>

                        <div class="form-group-evento">
                            <label for="fechaFin">Fecha de finalización <span class="required">*</span></label>
                            <input type="date" name="fechaFin" id="fechaFin" required>
                        </div>

                        <div class="form-group-evento">
                            <label for="horaInicio">Hora de inicio <span class="required">*</span></label>
                            <input type="time" name="horaInicio" id="horaInicio" required>
                        </div>

                        <div class="form-group-evento">
                            <label for="horaFin">Hora de finalización <span class="required">*</span></label>
                            <input type="time" name="horaFin" id="horaFin" required>
                        </div>

                    </div>

                    <div class="form-group-evento">
                        <label for="proyecto">Proyecto <span class="required">*</span></label>
                        <select name="proyecto" id="proyecto" required>
                            <option value="">Seleccionar proyecto</option>';

foreach ($proyectos as $p) {
    $contenido .= '<option value="' . $p['id_proyectos'] . '">' . htmlspecialchars($p['titulo']) . '</option>';
}

$contenido .= '
                        </select>
                    </div>

                    <div class="form-group-evento">
                        <label for="descripcion">Descripción</label>
                        <div id="editorDescripcion" style="height: 150px;"></div>
                        <input type="hidden" name="descripcion" id="descripcion">
                    </div>

                    <div class="form-group-evento">
                        <label for="ubicacion">Ubicación / Plataforma</label>
                        <input type="text" name="ubicacion" id="ubicacion">
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-check-lg"></i> Crear evento
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>';

include __DIR__ . "/../../layout.php";
?>