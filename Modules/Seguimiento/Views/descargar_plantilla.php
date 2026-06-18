<?php
/**
 * descargar_plantilla.php
 * Descarga una plantilla de documento para el módulo de Seguimiento.
 * Utiliza FileDownloader para la lógica de descarga segura.
 */

session_start();

require_once __DIR__ . "/../../../public/config/conexion.php";
require_once __DIR__ . '/../../../public/incluido/FileDownloader.php';


//  Autenticación ─
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Acceso no autorizado. Inicia sesión para descargar este archivo.');
}

//Solo estudiante accede
if (strtolower($_SESSION['rol']) !== 'estudiante') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
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

// Usar el helper para la descarga
$downloader = new FileDownloader();
$downloader->download($file['ruta'], $file['nombre_archivo']);