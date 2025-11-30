<?php
session_start();
require_once __DIR__ . "/conexion.php"; // ajusta si tu include apunta distinto

// Verifica sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$id_usuario  = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
$id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;

if (!$id_usuario || !$id_proyecto) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=error");
    exit;
}

// Datos del formulario
$promedio    = isset($_POST['promedio']) && $_POST['promedio'] !== '' ? floatval($_POST['promedio']) : null;
$motivacion  = isset($_POST['motivacion']) ? trim($_POST['motivacion']) : '';
$experiencia = isset($_POST['experiencia']) ? trim($_POST['experiencia']) : '';
$carrera     = isset($_POST['carrera']) && $_POST['carrera'] !== '' ? intval($_POST['carrera']) : null;
$semestre    = isset($_POST['semestre']) && $_POST['semestre'] !== '' ? intval($_POST['semestre']) : null;

// -------------- Evitar solicitudes duplicadas --------------
$checkSql = "SELECT id_solicitud_proyecto, estado FROM solicitud_proyecto WHERE id_proyectos = ? AND id_estudiante = ? ORDER BY fecha_envio DESC LIMIT 1";
$chkStmt = $conn->prepare($checkSql);
$chkStmt->bind_param("ii", $id_proyecto, $id_usuario);
$chkStmt->execute();
$chkRes = $chkStmt->get_result();

if ($chkRes && $chkRes->num_rows > 0) {
    $row = $chkRes->fetch_assoc();
    $estado = $row['estado'];
    if ($estado === 'pendiente') {
        header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=pending");
        exit;
    } elseif ($estado === 'aceptado') {
        header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=accepted");
        exit;
    }
    // si fue rechazado, permitimos volver a aplicar
}

// -------------- Manejo de archivo: guardamos en disco y guardamos el nombre --------------
$filename_db = null;
if (!empty($_FILES['documento']['tmp_name']) && is_uploaded_file($_FILES['documento']['tmp_name'])) {
    $allowed = ['pdf','doc','docx'];
    $orig = $_FILES['documento']['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=error");
        exit;
    }

    $uploadsDir = __DIR__ . "/../docs/solicitudes/";
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

    $safeName = "solicitud_" . $id_usuario . "_" . time() . "." . $ext;
    $dest = $uploadsDir . $safeName;

    if (move_uploaded_file($_FILES['documento']['tmp_name'], $dest)) {
        $filename_db = $safeName; // esto guardamos en la DB (varchar)
    } else {
        // si falla, lo consideramos no crítico: redirigimos con error
        header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=error");
        exit;
    }
}

// -------------- Insertar solicitud --------------
$sql = "
    INSERT INTO solicitud_proyecto
    (id_proyectos, id_estudiante, promedio, motivacion, experiencia, carrera, semestre,
     carta_presentacion, estado, fecha_envio)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', CURDATE())
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=error");
    exit;
}

// bind: i i d s s i i s
// si $promedio es null, bind_param acepta null
$stmt->bind_param(
    "iidssiis",
    $id_proyecto,
    $id_usuario,
    $promedio,
    $motivacion,
    $experiencia,
    $carrera,
    $semestre,
    $filename_db
);

if ($stmt->execute()) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=sent");
    exit;
} else {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}&solicitud=error");
    exit;
}