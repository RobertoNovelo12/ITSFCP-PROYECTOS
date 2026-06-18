<?php
/**
 * descargar_plantilla.php
 * Descarga una plantilla de documento para el módulo de Plantillas.
 * Utiliza FileDownloader para la lógica de descarga segura.
 */

session_start();

require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../Model/plantilla_documento_model.php';
require_once __DIR__ . '/../../../public/incluido/FileDownloader.php';

$rol        = strtolower($_SESSION['rol'] ?? '');
// Solo la usa el supervisor
if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
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
$S    = new plantilladocumento($conn);
$file = $S->obtenerPlantillaPorId($id_plantilla);

if (!$file) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

if (!$file['plantilla_activa'] || !$file['archivo_activo']) {
    http_response_code(403);
    exit('Este archivo no está disponible.');
}

// Usar el helper para la descarga
$downloader = new FileDownloader();
$downloader->download($file['ruta'], $file['nombre_archivo']);