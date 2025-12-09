<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION))
    session_start();
require __DIR__ . "/../../publico/config/conexion.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$idUsuario = $_SESSION["id_usuario"] ?? 0;
$rol = $_SESSION["rol"] ?? "";

// =====================================================
// OBTENER PROYECTOS
// =====================================================
if (isset($_GET["getProyectos"])) {

    header("Content-Type: application/json; charset=UTF-8");

    $proyectos = [];

    if ($rol === "estudiante") {
        $stmt = $conn->prepare("
            SELECT p.id_proyectos AS id, p.titulo
            FROM proyectos p
            INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
            WHERE pu.id_usuarios = ? AND pu.estado = 'activo'
        ");
        $stmt->bind_param("i", $idUsuario);

    } elseif ($rol === "investigador" || $rol === "profesor") {
        $stmt = $conn->prepare("
            SELECT id_proyectos AS id, titulo
            FROM proyectos
            WHERE id_investigador = ?
        ");
        $stmt->bind_param("i", $idUsuario);

    } else {
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

// =====================================================
// OBTENER ESTUDIANTES DE UN PROYECTO
// =====================================================
if (isset($_GET["getEstudiantes"]) && isset($_GET["id_proyecto"])) {

    header("Content-Type: application/json; charset=UTF-8");

    $idProyecto = intval($_GET["id_proyecto"]);
    $idRolEstudiante = 3; // Rol Estudiante
    $estudiantes = [];

    $stmt = $conn->prepare("
        SELECT u.id_usuarios, u.nombre, u.apellido_paterno, u.apellido_materno
        FROM usuarios u
        INNER JOIN proyectos_usuarios pu ON u.id_usuarios = pu.id_usuarios
        INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        WHERE pu.id_proyectos = ? AND pu.estado = 'activo' AND ur.id_rol = ?
    ");
    $stmt->bind_param("ii", $idProyecto, $idRolEstudiante);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $estudiantes[] = $row;
    }

    echo json_encode($estudiantes);
    exit;
}

// =====================================================
// CREAR NUEVO EVENTO (PRIVADO - SOLO CREADOR)
// =====================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=UTF-8");
    if ($idUsuario == 0) {
        echo json_encode(["status" => "error", "msg" => "Usuario no autenticado"]);
        exit;
    }

    $nombre = $_POST["nombreEvento"] ?? "";
    $fechaInicio = $_POST["fechaEvento"] ?? "";
    $fechaFin = $_POST["fechaFin"] ?? "";
    $horaInicio = $_POST["horaInicio"] ?? "";
    $horaFin = $_POST["horaFin"] ?? "";
    $proyecto = $_POST["proyecto"] ?? "";
    $descripcion = $_POST["descripcion"] ?? "";
    $ubicacion = $_POST["ubicacion"] ?? "";

    if (!$nombre || !$fechaInicio || !$fechaFin || !$horaInicio || !$horaFin || !$proyecto) {
        echo json_encode(["status" => "error", "msg" => "Faltan datos obligatorios"]);
        exit;
    }

    $fechaInicioDT = $fechaInicio . " " . $horaInicio;
    $fechaFinDT = $fechaFin . " " . $horaFin;

    // INSERT EN eventos_calendario
    $stmt = $conn->prepare("
        INSERT INTO eventos_calendario 
        (id_proyectos, titulo, descripcion, fecha_inicio, fecha_fin, ubicacion)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssss",
        $proyecto,
        $nombre,
        $descripcion,
        $fechaInicioDT,
        $fechaFinDT,
        $ubicacion
    );

    if ($stmt->execute()) {

        $idEvento = $stmt->insert_id;
        $stmtInsert = $conn->prepare("
            INSERT INTO eventos_usuarios (id_usuarios, id_eventos)
            VALUES (?, ?)
        ");
        $stmtInsert->bind_param("ii", $idUsuario, $idEvento);
        
        if ($stmtInsert->execute()) {
            echo json_encode(["status" => "ok", "msg" => "Evento privado creado exitosamente"]);
        } else {
            echo json_encode(["status" => "error", "msg" => "Evento creado pero no se pudo asignar al usuario"]);
        }
        exit;
    }

    echo json_encode(["status" => "error", "msg" => "No se pudo crear el evento"]);
    exit;
}
?>