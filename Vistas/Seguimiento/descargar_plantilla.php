<?php

session_start();

require "../../publico/config/conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    exit("Acceso no autorizado");
}

if (!isset($_GET['id_plantilla'])) {
    exit("Plantilla inválida");
}

$id = intval($_GET['id_plantilla']);


// OBTENER ARCHIVO


$sql = "SELECT
            nombre_archivo,
            ruta,
            activo
        FROM plantillas_documentos
        WHERE id_plantilla = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Error prepare");
}

$stmt->bind_param("i", $id);

$stmt->execute();

$file = $stmt->get_result()->fetch_assoc();

$stmt->close();


// VALIDAR


if (!$file || !$file['activo']) {
    exit("Archivo no disponible");
}


// STORAGE


$storageBase = realpath(__DIR__ . "/../../storage");

if (!$storageBase) {
    exit("Storage inválido");
}


// RUTA FINAL


$rutaCompleta =
    realpath($storageBase . DIRECTORY_SEPARATOR . $file['ruta']);


// VALIDAR EXISTENCIA


if (!$rutaCompleta || !file_exists($rutaCompleta)) {
    exit("Archivo inexistente");
}


// EVITAR PATH TRAVERSAL


if (strpos($rutaCompleta, $storageBase) !== 0) {
    exit("Ruta inválida");
}


// LIMPIAR BUFFER


if (ob_get_length()) {
    ob_end_clean();
}


// MIME


$finfo = finfo_open(FILEINFO_MIME_TYPE);

$mime = finfo_file($finfo, $rutaCompleta);

finfo_close($finfo);


// HEADERS


header("Content-Description: File Transfer");

header("Content-Type: " . $mime);

header(
    "Content-Disposition: attachment; filename=\"" .
    basename($file['nombre_archivo']) .
    "\""
);

header("Content-Length: " . filesize($rutaCompleta));

header("Pragma: public");


// DESCARGAR


readfile($rutaCompleta);

exit;