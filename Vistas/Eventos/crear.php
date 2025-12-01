<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
require __DIR__ . "/../../publico/config/conexion.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$idUsuario = $_SESSION["id_usuarios"] ?? 0;
$rol = $_SESSION["rol"] ?? "";

ob_clean();


if (isset($_GET["getProyectos"])) {

    header("Content-Type: application/json; charset=UTF-8");

    $proyectos = [];

    // Estudiante → solo proyectos donde participa
    if ($rol === "estudiante") {

        $stmt = $conn->prepare("
            SELECT p.id_proyectos AS id, p.titulo
            FROM proyectos p
            INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
            WHERE pu.id_usuarios = ? AND pu.estado = 'activo'
        ");
        $stmt->bind_param("i", $idUsuario);

    }
    // Investigador o profesor → proyectos que lidera
    elseif ($rol === "investigador" || $rol === "profesor") {

        $stmt = $conn->prepare("
            SELECT id_proyectos AS id, titulo
            FROM proyectos
            WHERE id_investigador = ?
        ");
        $stmt->bind_param("i", $idUsuario);

    }
    // Admin → todos los proyectos
    else {

        $stmt = $conn->prepare("
            SELECT id_proyectos AS id, titulo
            FROM proyectos
        ");
    }

    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $proyectos[] = $row;
    }

    echo json_encode($proyectos);
    exit;
}

if (isset($_GET["getEstudiantes"]) && isset($_GET["id_proyecto"])) {

    header("Content-Type: application/json; charset=UTF-8");

    $idProyecto = intval($_GET["id_proyecto"]);
    $estudiantes = [];

    $stmt = $conn->prepare("
        SELECT u.id_usuarios, u.nombre, u.apellido
        FROM usuarios u
        INNER JOIN proyectos_usuarios pu ON u.id_usuarios = pu.id_usuarios
        WHERE pu.id_proyectos = ? AND u.rol = 'estudiante' AND pu.estado = 'activo'
    ");
    $stmt->bind_param("i", $idProyecto);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $estudiantes[] = $row;
    }

    echo json_encode($estudiantes);
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=UTF-8");

    $nombre       = $_POST["nombreEvento"] ?? "";
    $fechaInicio  = $_POST["fechaEvento"] ?? "";
    $fechaFin     = $_POST["fechaFin"] ?? "";
    $horaInicio   = $_POST["horaInicio"] ?? "";
    $horaFin      = $_POST["horaFin"] ?? "";
    $proyecto     = $_POST["proyecto"] ?? "";
    $descripcion  = $_POST["descripcion"] ?? "";
    $ubicacion    = $_POST["ubicacion"] ?? "";
    $invitados    = $_POST["invitados"] ?? [];

    if (!$nombre || !$fechaInicio || !$fechaFin || !$horaInicio || !$horaFin || !$proyecto) {
        echo json_encode(["status" => "error", "msg" => "Faltan datos obligatorios"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO eventos (id_proyectos, nombre, descripcion, fecha_inicio, fecha_fin, hora_inicio, hora_fin, ubicacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssss",
        $proyecto,
        $nombre,
        $descripcion,
        $fechaInicio,
        $fechaFin,
        $horaInicio,
        $horaFin,
        $ubicacion
    );

    if ($stmt->execute()) {

        $idEvento = $stmt->insert_id;

        // Guardar invitados
        if (!empty($invitados)) {
            $stmt2 = $conn->prepare("
                INSERT INTO eventos_invitados (id_evento, id_usuario)
                VALUES (?, ?)
            ");
            foreach ($invitados as $idInv) {
                $stmt2->bind_param("ii", $idEvento, $idInv);
                $stmt2->execute();
            }
        }

        echo json_encode(["status" => "ok", "msg" => "Evento creado exitosamente"]);
        exit;
    }

    echo json_encode(["status" => "error", "msg" => "No se pudo crear el evento"]);
    exit;
}

?>
