<?php
/**
 * descargar_plantilla.php
 *
 * Descarga segura de plantillas de documentos (carta compromiso, etc.).
 *
 * Requiere:
 *  - Sesión activa
 *  - GET['id_plantilla'] → int
 *
 * Seguridad aplicada:
 *  - Autenticación de sesión
 *  - Validación de plantilla activa en BD
 *  - Resolución de ruta física con realpath()
 *  - Prevención de path traversal (strpos contra storageBase)
 *  - Detección de MIME real con finfo (ignora extensión declarada)
 *  - Whitelist de tipos MIME permitidos
 *  - Solo se sirven archivos dentro del directorio /storage
 *  - Limpieza de buffer antes de enviar cabeceras
 */

session_start();

require __DIR__ . "/../../publico/config/conexion.php";

//  Autenticación ─
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Acceso no autorizado. Inicia sesión para descargar este archivo.');
}

//  Parámetro ─
$id_plantilla = isset($_GET['id_plantilla']) ? intval($_GET['id_plantilla']) : 0;
if ($id_plantilla <= 0) {
    http_response_code(400);
    exit('Plantilla inválida.');
}

//  Consultar plantilla en BD ─
// Se consulta directamente la tabla plantillas_documentos.
// La columna 'ruta' almacena la ruta relativa al directorio /storage.
$sql = "
    SELECT
        nombre_archivo,
        ruta,
        activo
    FROM plantillas_documentos
    WHERE id_plantilla = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit('Error interno. Contacta al administrador.');
}

$stmt->bind_param("i", $id_plantilla);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

//  Validar que la plantilla exista y esté activa ─
if (!$file) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

if (!$file['activo']) {
    http_response_code(403);
    exit('Este archivo no está disponible.');
}

//  Resolver base segura del storage ─
$storageBase = realpath(__DIR__ . '/../../storage');

if (!$storageBase) {
    http_response_code(500);
    exit('El directorio de almacenamiento no está disponible. Contacta al administrador.');
}

//  Construir ruta física ─
/*
 * La columna 'ruta' en plantillas_documentos guarda la ruta relativa al
 * directorio /storage (ej: plantillas/supervisor_5/carta/archivo.docx).
 * Se concatena directamente con $storageBase.
 *
 * Si eventualmente llegara con prefijos de URL o barras iniciales, se normalizan.
 */
$rutaBD = ltrim($file['ruta'], '/\\');

$rutaCompleta = realpath($storageBase . DIRECTORY_SEPARATOR . $rutaBD);

//  Validar existencia 
if (!$rutaCompleta || !file_exists($rutaCompleta)) {
    http_response_code(404);
    exit('El archivo no existe en el servidor. Contacta al administrador.');
}

//  Prevenir path traversal ─
// La ruta resuelta DEBE comenzar con el storageBase para garantizar
// que el archivo esté dentro del área permitida.
if (strpos($rutaCompleta, $storageBase) !== 0) {
    http_response_code(403);
    exit('Acceso denegado: ruta fuera del área permitida.');
}

//  MIME real ─
// Se detecta el tipo real del archivo ignorando la extensión declarada.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $rutaCompleta);
finfo_close($finfo);

// Whitelist de tipos MIME permitidos para plantillas
$mimes_permitidos = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/png',
    'image/jpeg',
];

if (!in_array($mime, $mimes_permitidos, true)) {
    http_response_code(415);
    exit('Tipo de archivo no permitido.');
}

//  Limpiar buffer de salida 
if (ob_get_length()) {
    ob_end_clean();
}

//  Cabeceras HTTP 
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($file['nombre_archivo']) . '"');
header('Content-Length: ' . filesize($rutaCompleta));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

//  Transferir archivo 
readfile($rutaCompleta);
exit;