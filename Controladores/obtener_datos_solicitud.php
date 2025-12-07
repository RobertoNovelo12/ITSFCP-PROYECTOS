<?php
// Habilitar reporte de errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

try {
    // Verificar sesión
    if (!isset($_SESSION['id_usuario'])) {
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    // Verificar que se recibió el ID
    if (!isset($_GET['id'])) {
        echo json_encode(['error' => 'ID de solicitud no proporcionado']);
        exit;
    }

    $id_solicitud = intval($_GET['id']);

    require_once __DIR__ . '/solicitudesControlador.php';

    $solicitudesControlador = new solicitudesControlador();
    $datos = $solicitudesControlador->obtenerDatosSolicitud($id_solicitud);

    if (!$datos) {
        echo json_encode(['error' => 'No se encontró la solicitud']);
        exit;
    }

    // Si hay carta de presentación en formato BLOB, convertir a base64
    if (isset($datos['carta_presentacion']) && !empty($datos['carta_presentacion'])) {
        $datos['carta_presentacion'] = base64_encode($datos['carta_presentacion']);
    }

    // Si hay carta de aceptación en formato BLOB, convertir a base64
    if (isset($datos['carta_aceptacion']) && !empty($datos['carta_aceptacion'])) {
        $datos['carta_aceptacion'] = base64_encode($datos['carta_aceptacion']);
    }

    echo json_encode($datos);
    
} catch (Exception $e) {
    error_log("Error en obtener_datos_solicitud.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
}
?>