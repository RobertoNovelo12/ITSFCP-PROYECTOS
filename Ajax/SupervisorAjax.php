<?php
/**
 * SupervisorAjax.php
 * Endpoint centralizado para acciones AJAX del panel del supervisor.
 * Ruta: /ITSFCP-PROYECTOS/Controladores/SupervisorAjax.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado.']);
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
if ($rol !== 'supervisor') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado.']);
    exit;
}

$action = $_GET['action'] ?? '';

require_once __DIR__ . '/../public/config/conexion.php';
require_once __DIR__ . '/../Modules/Supervisor/Model/supervisor_model.php';

$modelo = new SupervisorModelo($conn);

switch ($action) {

    case 'toggleEtapa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
            exit;
        }
        $id_etapa = intval($_POST['id_etapa'] ?? 0);
        $estado   = intval($_POST['estado']   ?? 1);
        if (!$id_etapa) {
            echo json_encode(['ok' => false, 'msg' => 'ID de etapa inválido.']);
            exit;
        }
        $ok = $modelo->Etapa($id_etapa, $estado ? 1 : 0);
        echo json_encode([
            'ok'  => $ok,
            'msg' => $ok
                ? ($estado ? 'Etapa activada correctamente.' : 'Etapa desactivada correctamente.')
                : 'Error al actualizar la etapa.',
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Acción no encontrada.']);
        break;
}
