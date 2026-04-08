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

// 
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/../Modelos/solicitudes.php';

try {

    $modelo = new Solicitud($conn); // ✔ MYSQLI
    try {
        switch ($action) {

            // ───────────── DETALLE ─────────────
            case 'detalle':

                if (!in_array($rol, ['investigador', 'profesor'], true)) {
                    throw new Exception('Sin permiso.');
                }

                $id = intval($_GET['id'] ?? 0);
                if (!$id) throw new Exception('ID inválido.');

                if (!$modelo->verificarPermiso($id, $id_usuario)) {
                    throw new Exception('Sin permiso.');
                }

                $modelo->marcarEnRevision($id);

                $solicitud   = $modelo->obtenerDetalle($id);
                $comentarios = $modelo->obtenerComentarios($id);

                $etapas = [];

                if ($solicitud) {
                    // require_once __DIR__ . '/../Modelos/SeguimientoModelo.php';
                    // $segModelo = new SeguimientoModelo($conn);
                    // $etapas = $segModelo->getEtapasPorProyecto(
                    //     (int)$solicitud['id_proyectos'],
                    //     (int)$solicitud['id_estudiante']
                    // );
                }

                if (!$solicitud) {
                    echo json_encode(['error' => 'No se encontró la solicitud']);
                    exit;
                }

                echo json_encode([
                    'solicitud'   => $solicitud,
                    'comentarios' => $comentarios,
                    'etapas'      => $etapas
                ]);
                break;

            // ───────────── ACEPTAR ─────────────
            case 'aceptar':

                requirePost();

                if (!in_array($rol, ['investigador', 'profesor'], true)) {
                    throw new Exception('Sin permiso.');
                }

                $id = intval($_POST['id_solicitud'] ?? 0);

                if (!$id || !$modelo->verificarPermiso($id, $id_usuario)) {
                    throw new Exception('Sin permiso.');
                }

                $ok = $modelo->aceptar($id, $id_usuario);

                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Solicitud aceptada.' : 'Error al aceptar.'
                ]);
                break;

            // ───────────── CORRECCIONES ─────────────
            case 'correcciones':

                requirePost();

                if (!in_array($rol, ['investigador', 'profesor'], true)) {
                    throw new Exception('Sin permiso.');
                }

                $id  = intval($_POST['id_solicitud'] ?? 0);
                $com = trim($_POST['comentario'] ?? '');

                if (!$id || $com === '') {
                    throw new Exception('Datos incompletos.');
                }

                if (!$modelo->verificarPermiso($id, $id_usuario)) {
                    throw new Exception('Sin permiso.');
                }

                [$ruta, $nombre] = procesarArchivo($id);

                $ok = $modelo->pedirCorrecciones($id, $id_usuario, $com, $ruta, $nombre);

                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Correcciones solicitadas.' : 'Error.'
                ]);
                break;

            // ───────────── RECHAZAR ─────────────
            case 'rechazar':

                requirePost();

                if (!in_array($rol, ['investigador', 'profesor'], true)) {
                    throw new Exception('Sin permiso.');
                }

                $id  = intval($_POST['id_solicitud'] ?? 0);
                $com = trim($_POST['comentario'] ?? '');

                if (!$id || $com === '') {
                    throw new Exception('El motivo es obligatorio.');
                }

                if (!$modelo->verificarPermiso($id, $id_usuario)) {
                    throw new Exception('Sin permiso.');
                }

                [$ruta, $nombre] = procesarArchivo($id);

                $ok = $modelo->rechazar($id, $id_usuario, $com, $ruta, $nombre);

                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Solicitud rechazada.' : 'Error.'
                ]);
                break;

            // ───────────── ENVIAR CORRECCIONES ─────────────
            case 'enviarCorrecciones':

                requirePost();

                if ($rol !== 'estudiante') {
                    throw new Exception('Sin permiso.');
                }

                $id  = intval($_POST['id_solicitud'] ?? 0);
                $com = trim($_POST['comentario'] ?? '');

                if (!$id) throw new Exception('ID inválido.');

                [$ruta, $nombre] = procesarArchivo($id);

                $ok = $modelo->enviarCorrecciones($id, $id_usuario, $com, null, $ruta, $nombre);

                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Correcciones enviadas.' : 'Error.'
                ]);
                break;

            // ───────────── COMENTARIOS ─────────────
            case 'comentarios':

                $id = intval($_GET['id'] ?? 0);
                if (!$id) throw new Exception('ID inválido.');

                echo json_encode([
                    'comentarios' => $modelo->obtenerComentarios($id)
                ]);
                break;

            default:
                throw new Exception('Acción no encontrada.');
        }
    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }

    // ───────────── HELPERS ─────────────

    function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido.']);
            exit;
        }
    }

    function procesarArchivo(int $id): array
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return [null, null];
        }

        $file = $_FILES['archivo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx', 'png', 'jpg'], true)) return [null, null];
        if ($file['size'] > 8 * 1024 * 1024) return [null, null];

        $dir = __DIR__ . '/../publico/docs/solicitudes/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = 'sol_' . $id . '_' . date('YmdHis') . '.' . $ext;
        $ruta   = 'docs/solicitudes/' . $nombre;

        move_uploaded_file($file['tmp_name'], $dir . $nombre);

        return [$ruta, $file['name']];
    }
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
