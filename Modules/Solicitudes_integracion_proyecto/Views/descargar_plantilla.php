<?php
/**
 * descargar_plantilla.php
 * Descarga una plantilla de documento.
 * Utiliza FileDownloader para la lógica de descarga segura.
 */

session_start();

require_once __DIR__ . "/../../../public/config/conexion.php";
require_once __DIR__ . "/../Model/solicitudes_model.php";
require_once __DIR__ . '/../../../public/incluido/FileDownloader.php';

$rol        = strtolower($_SESSION['rol'] ?? '');
// Solo la usa el estudiante
if ($rol !== 'estudiante') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Acceso no autorizado. Inicia sesión para descargar este archivo.');
}

$id_plantilla = isset($_GET['id_plantilla']) ? intval($_GET['id_plantilla']) : 0;
if ($id_plantilla <= 0) {
    http_response_code(400);
    exit('Plantilla inválida.');
}

// Consultar plantilla en BD
$solicitudModel = new Solicitud($conn);
$file = $solicitudModel->obtenerPlantillaPorId($id_plantilla);
if (!$file) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

// Validación específica de este script
if (!$file['plantilla_activa'] || !$file['archivo_activo']) {
    http_response_code(403);
    exit('Este archivo no está disponible.');
}

// Usar el helper para la descarga
$downloader = new FileDownloader();
$downloader->download($file['ruta'], $file['nombre_archivo']);