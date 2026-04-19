<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado.']);
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$action     = $_GET['action'] ?? '';

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/../Controladores/solicitudesControlador.php';

function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }
}

try {
    $ctrl = new solicitudesControlador();

    switch ($action) {

        case 'detalle':
            if (!in_array($rol, ['investigador', 'profesor'], true)) throw new Exception('Sin permiso.');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');

            global $conn;
            $modelo = new Solicitud($conn);
            if (!$modelo->verificarPermiso($id, $id_usuario)) throw new Exception('Sin permiso.');

            $modelo->marcarEnRevision($id);
            $solicitud   = $modelo->obtenerDetalle($id);
            $comentarios = $modelo->obtenerComentarios($id);
            if (!$solicitud) throw new Exception('No encontrada.');

            // Datos de seguimiento para el modal
            $seg = $modelo->getDatosSeguimientoEstudiante(
                (int)$solicitud['id_proyectos'],
                (int)$solicitud['id_estudiante'],
                $id_usuario
            );

            echo json_encode([
                'solicitud'   => $solicitud,
                'comentarios' => $comentarios,
                'etapas'      => [], // legacy — se mantiene por compatibilidad
                'seguimiento' => $seg,
            ]);
            break;

        case 'aceptar':
            requirePost();
            $ctrl->aceptar();
            break;

        case 'correcciones':
            requirePost();
            $ctrl->pedirCorrecciones();
            break;

        case 'rechazar':
            requirePost();
            $ctrl->rechazar();
            break;

        case 'enviarCorrecciones':
            requirePost();
            $ctrl->enviarCorrecciones();
            break;

        case 'comentarios':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID inválido.');
            global $conn;
            $modelo = new Solicitud($conn);
            echo json_encode(['comentarios' => $modelo->obtenerComentarios($id)]);
            break;

        default:
            throw new Exception('Acción no encontrada.');
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}