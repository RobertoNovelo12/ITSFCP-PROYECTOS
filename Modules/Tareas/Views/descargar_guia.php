<?php
/**
 * descargar_guia.php
 * Descarga el documento guía de una tarea.
 * Utiliza FileDownloader para la lógica de descarga segura.
 */

session_start();

require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . "/../Model/tareas_model.php";
require_once __DIR__ . '/../../../public/incluido/FileDownloader.php';

// Solo investigador, supervisor y estudiante pueden acceder 
if (!in_array($_SESSION['rol'], ['investigador', 'supervisor', 'estudiante'])) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Acceso no autorizado. Inicia sesión para descargar este archivo.');
}

$id_documento_recurso = isset($_GET['id_documento_recurso']) ? intval($_GET['id_documento_recurso']) : 0;
if ($id_documento_recurso <= 0) {
    http_response_code(400);
    exit('Tarea inválida.');
}

// Consultar guía en BD
$tareaModel = new Tarea($conn);
$file = $tareaModel->obtenerTareaPorId($id_documento_recurso);
if (!$file) {
    http_response_code(404);
    exit('Esta tarea no tiene archivo adjunto o no está disponible.');
}

// Usar el helper para la descarga
$downloader = new FileDownloader();
$downloader->download(
    $file['ruta'],
    $file['nombre'] ?: $file['nombre_archivo']
);