<?php
require "../../publico/config/conexion.php";

$id = $_GET['id'];

$sql = "SELECT archivo, archivo_nombre, archivo_tipo 
        FROM tareas_usuarios 
        WHERE id_tarea = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

header("Content-Type: " . $file['archivo_tipo']);
header("Content-Disposition: attachment; filename=" . $file['archivo_nombre']);
echo $file['archivo'];
exit;
