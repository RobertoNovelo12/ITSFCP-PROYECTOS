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
 *  - Solo se sirven archivos dentro del directorio /storage
 *  - Limpieza de buffer antes de enviar cabeceras
 */

session_start();

require_once __DIR__ . "/../../publico/config/conexion.php";
require_once __DIR__ . "/../../Modelos/solicitudes.php";

$rol        = strtolower($_SESSION['rol'] ?? '');
// Solo la usa el estudiante
if ($rol !== 'estudiante') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

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
$S    = new Solicitud($conn);
$file = $S->obtenerPlantillaPorId($id_plantilla);

if (!$file) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

if (!$file['plantilla_activa'] || !$file['archivo_activo']) {
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
 * Las rutas en BD pueden estar en dos formatos según quien las guardó:
 *   a) Absoluta web:   /ITSFCP-PROYECTOS/storage/plantillas/supervisor_5/carta/archivo.docx
 *   b) Relativa pura:  storage/plantillas/supervisor_5/carta/archivo.docx
 *   c) Ruta en disco:  C:\xampp\htdocs\ITSFCP-PROYECTOS\storage\...  (Windows)
 *
 * Normalizamos eliminando el prefijo web y resolvemos desde la raíz del proyecto.
 */
$rutaBD       = $file['ruta'];
$proyectoRoot = realpath(__DIR__ . '/../../');   // raíz de ITSFCP-PROYECTOS

// Quitar barra inicial y prefijo /ITSFCP-PROYECTOS/ si existe
$rutaBD = ltrim($rutaBD, '/\\');
$rutaBD = preg_replace('#^ITSFCP-PROYECTOS[\\/]#i', '', $rutaBD);

$rutaCompleta = realpath($proyectoRoot . DIRECTORY_SEPARATOR . $rutaBD);

//  Validar existencia 
if (!$rutaCompleta || !file_exists($rutaCompleta)) {
    http_response_code(404);
    exit('El archivo no existe en el servidor. Contacta al administrador.');
}

//  Prevenir path traversal ─
// La ruta resuelta DEBE comenzar con el storageBase
if (strpos($rutaCompleta, $storageBase) !== 0) {
    http_response_code(403);
    exit('Acceso denegado: ruta fuera del área permitida.');
}

//  MIME real (ignora extensión declarada en el nombre del archivo) ─
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $rutaCompleta);
finfo_close($finfo);

// Whitelist de MIME permitidos para plantillas
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

//  Nombre de descarga: usar nombre_archivo del registro 
$nombre_descarga = basename($file['nombre_archivo']);

//  Cabeceras HTTP 
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
header('Content-Length: ' . filesize($rutaCompleta));
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

//  Transferir archivo 
readfile($rutaCompleta);
exit;