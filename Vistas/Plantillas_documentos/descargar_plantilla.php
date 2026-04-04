<?php
require "../../publico/config/conexion.php";


if (!isset($_GET['id_plantilla'])) {
    exit;
}

$id = intval($_GET['id_plantilla']);

$sql = "SELECT nombre_archivo, ruta 
        FROM plantillas_documentos 
        WHERE id_plantilla = ? AND activo = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    exit;
}

$ruta = $_SERVER['DOCUMENT_ROOT'] . $file['ruta'];

if (!file_exists($ruta)) {
    exit;
}

// Limpiar buffer (si existe)
if (ob_get_length()) {
    ob_end_clean();
}

// MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $ruta);
finfo_close($finfo);

// Headers
header("Content-Description: File Transfer");
header("Content-Type: " . $mime);
header("Content-Disposition: attachment; filename=\"" . basename($ruta) . "\"");
header("Content-Length: " . filesize($ruta));
header("Pragma: public");

// Enviar archivo
readfile($ruta);
exit;