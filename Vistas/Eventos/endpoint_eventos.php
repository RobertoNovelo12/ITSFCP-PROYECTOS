<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
require __DIR__ . "/../../publico/config/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$idUsuario = $_SESSION["id_usuarios"] ?? 0;
$rol = $_SESSION["rol"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

    $fechaInicioDT = $fechaInicio . " " . $horaInicio;
    $fechaFinDT    = $fechaFin . " " . $horaFin;

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

        if (!empty($invitados)) {
            $stmt2 = $conn->prepare("
                INSERT INTO eventos_usuarios (id_eventos, id_usuarios)
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