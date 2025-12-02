<?php
require "../ITSFCP-PROYECTOS/publico/config/conexion.php";

$id = $_GET['id'];

$sql = "SELECT archivo_guia, archivo_nombre, archivo_tipo 
        FROM tbl_seguimiento 
        WHERE id_tarea = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

header("Content-Type: " . $file['archivo_tipo']);
header("Content-Disposition: attachment; filename=" . $file['archivo_nombre']);
echo $file['archivo_guia'];
exit;
