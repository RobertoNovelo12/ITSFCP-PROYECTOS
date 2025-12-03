<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

$id_usuario = $_SESSION['id_usuario'];
$id_solicitud = isset($_GET['id_solicitud']) ? intval($_GET['id_solicitud']) : 0;
$id_proyecto = isset($_GET['id_proyecto']) ? intval($_GET['id_proyecto']) : 0;

// ELIMINAR SOLICITUD
$sql = "DELETE FROM solicitud_proyecto WHERE id_solicitud_proyecto = ? AND id_estudiante = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_solicitud, $id_usuario);
$stmt->execute();

// Obtener título del proyecto
$sqlProy = "SELECT titulo FROM proyectos WHERE id_proyectos = ?";
$stmtProy = $conn->prepare($sqlProy);
$stmtProy->bind_param("i", $id_proyecto);
$stmtProy->execute();
$resProy = $stmtProy->get_result();
$proyecto = $resProy->fetch_assoc();
$titulo_proyecto = $proyecto['titulo'];

// Notificación al estudiante
$enlace = "/ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id=".$id_proyecto;

$sqlNotif = "
    INSERT INTO notificaciones (usuario_id, titulo, contenido, enlace, leido, creado_en)
    VALUES (?, 'Solicitud cancelada', ?, ?, 0, NOW())
";
$contenido = "Has cancelado tu solicitud para el proyecto: <b>".htmlspecialchars($titulo_proyecto)."</b>.";

$stmtNotif = $conn->prepare($sqlNotif);
$stmtNotif->bind_param("iss", $id_usuario, $contenido, $enlace);
$stmtNotif->execute();

// Redirigir con modal
header("Location: /ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id=$id_proyecto&cancelada=1");
exit;
?>