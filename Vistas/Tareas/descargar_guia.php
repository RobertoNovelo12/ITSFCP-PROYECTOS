<?php
/**
 * descargar_guia.php
 *
 * Descarga segura del documento guía/recurso adjunto a una tarea
 * por el investigador (campo id_documento_recurso en tareas).
 *
 * Requiere:
 *  - Sesión activa
 *  - GET['id_tarea'] → int
 *
 * Seguridad aplicada:
 *  - Autenticación de sesión obligatoria
 *  - Validación de que la tarea tenga recurso adjunto
 *  - El documento debe ser tipo 'recurso' y estar activo
 *  - Resolución de ruta física con realpath()
 *  - Prevención de path traversal (strpos contra storageBase)
 *  - Detección de MIME real con finfo (ignora extensión declarada)
 *  - Solo se sirven archivos dentro del directorio /storage
 *  - Limpieza de buffer antes de enviar cabeceras
 */

session_start();

require_once __DIR__ . '/../../publico/config/conexion.php';
require_once __DIR__ . "/../../Modelos/tareas.php";

// Solo investigador, supervisor y estudiante pueden acceder 
if (!in_array($_SESSION['rol'], ['investigador', 'supervisor', 'estudiante'])) {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

//  Autenticación 
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Acceso no autorizado. Inicia sesión para descargar este archivo.');
}

//  Parámetro 
$id_documento_recurso = isset($_GET['id_documento_recurso']) ? intval($_GET['id_documento_recurso']) : 0;
if ($id_documento_recurso <= 0) {
    http_response_code(400);
    exit('Tarea inválida.');
}

//  Consultar guía en BD 
// Obtiene el documento recurso activo vinculado a la tarea.
// Valida en un solo JOIN que: la tarea exista, tenga recurso asignado,
// el documento sea de tipo 'recurso' y esté activo.
$S    = new Tarea($conn);
$file = $S->obtenerTareaPorId($id_documento_recurso);

if (!$file) {
    http_response_code(404);
    exit('Esta tarea no tiene archivo adjunto o no está disponible.');

}

//  Resolver base segura del storage 
$storageBase = realpath(__DIR__ . '/../../storage');

if (!$storageBase) {
    http_response_code(500);
    exit('El directorio de almacenamiento no está disponible. Contacta al administrador.');
}

//  Construir ruta física 
// Las rutas en BD pueden venir en distintos formatos:
//   a) Absoluta web:   /storage/recursos/tarea_5/archivo.pdf
//   b) Relativa pura:  storage/recursos/tarea_5/archivo.pdf
//   c) Ruta en disco:  C:\xampp\htdocs\ITSFCP-PROYECTOS\storage\...  (Windows)
// Normalizamos eliminando el prefijo web y resolvemos desde la raíz del proyecto.

$rutaBD       = $file['ruta'];
$proyectoRoot = realpath(__DIR__ . '/../../');   // raíz de ITSFCP-PROYECTOS

$rutaBD = ltrim($rutaBD, '/\\');
$rutaBD = preg_replace('#^ITSFCP-PROYECTOS[\\/]#i', '', $rutaBD);

$rutaCompleta = realpath($proyectoRoot . DIRECTORY_SEPARATOR . $rutaBD);

//  Validar existencia 
if (!$rutaCompleta || !file_exists($rutaCompleta)) {
    http_response_code(404);
    exit('El archivo no existe en el servidor. Contacta al administrador.');
}

//  Prevenir path traversal 
// La ruta resuelta DEBE comenzar con el storageBase.
if (strpos($rutaCompleta, $storageBase) !== 0) {
    http_response_code(403);
    exit('Acceso denegado: ruta fuera del área permitida.');
}

//  MIME real 
// Se detecta desde el archivo físico; ignora lo declarado en BD o en el nombre.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $rutaCompleta);
finfo_close($finfo);

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

//  Nombre de descarga 
// Se usa el nombre de display (nombre), no el nombre físico en disco (con hash).
$nombre_descarga = basename($file['nombre'] ?: $file['nombre_archivo']);

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