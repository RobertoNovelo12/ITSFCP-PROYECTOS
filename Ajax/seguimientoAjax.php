<?php

/**
 * Ajax/seguimientoAjax.php
 *
 * Endpoint AJAX para acciones del investigador sobre el seguimiento.
 * Acciones disponibles:
 *   POST responderCierre  → Etapa 3: aprobar / correcciones / rechazar
 *   POST actualizarEstado → Estado genérico de un seguimiento_documento
 */

ini_set('display_errors', 0);   // Nunca mostrar errores en respuestas AJAX
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/../public/config/conexion.php';
require_once __DIR__ . '/../Modules/Seguimiento/Controller/seguimiento_controller.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $ctrl = new SeguimientoControlador();

    $accionesPermitidas = ['responderCierre', 'actualizarEstado'];

    if (!in_array($action, $accionesPermitidas, true)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Acción no encontrada.']);
        exit;
    }

    // Llama al método del controlador (ambos terminan con json() + exit)
    $ctrl->$action();

} catch (Throwable $e) {
    http_response_code(500);
    error_log('seguimientoAjax: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error interno del servidor.']);
}
