<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Verifica sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_usuario  = $_POST['id_usuario'] ?? 0;
$id_proyecto = $_POST['id_proyecto'] ?? 0;

if (!$id_usuario || !$id_proyecto) {
    die("Error: Datos incompletos.");
}

// Obtener datos del formulario
$promedio     = !empty($_POST['promedio']) ? floatval($_POST['promedio']) : null;
$motivacion   = trim($_POST['motivacion']);
$experiencia  = trim($_POST['experiencia']);
$semestre     = !empty($_POST['semestre']) ? intval($_POST['semestre']) : null;
$carrera      = !empty($_POST['carrera']) ? intval($_POST['carrera']) : null;

// Manejo de archivo adjunto
$documento_blob = null;

if (!empty($_FILES['documento']['tmp_name'])) {
    $documento_blob = file_get_contents($_FILES['documento']['tmp_name']);
}

// Preparar INSERT completo
$sql = "
    INSERT INTO solicitud_proyecto 
    (id_proyectos, id_estudiante, promedio, motivacion, experiencia, carrera, semestre,
     id_constancia, carta_presentacion, carta_aceptacion,
     estado, comentarios, fecha_envio, motivo_rechazo)
    VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, 'pendiente', NULL, CURDATE(), NULL)
";

$stmt = $conn->prepare($sql);

// Tipos:
// i  id_proyectos
// i  id_estudiante
// d  promedio
// s  motivacion
// s  experiencia
// i  carrera
// i  semestre
// b  carta_presentacion (BLOB)
$stmt->bind_param(
    "iidssiib",
    $id_proyecto,
    $id_usuario,
    $promedio,
    $motivacion,
    $experiencia,
    $carrera,
    $semestre,
    $documento_blob
);

// Enviar blob si existe
if ($documento_blob !== null) {
    $stmt->send_long_data(7, $documento_blob);
}

$stmt->execute();
$id_solicitud = $stmt->insert_id;

// Redirigir
header("Location: detalles_proyecto.php?id=$id_proyecto&solicitud=1");
exit;

?>