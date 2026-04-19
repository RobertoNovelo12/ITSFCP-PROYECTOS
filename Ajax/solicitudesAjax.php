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
require_once __DIR__ . '/../Modelos/solicitudes.php';

function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }
}

/**
 * Sube el archivo a disco, lo registra en documentos_subidos
 * y devuelve el id_documento generado. Devuelve null si no hay archivo.
 */
function procesarArchivo(int $id_solicitud, int $id_usuario, mysqli $conn): ?int
{
    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['pdf', 'docx', 'png', 'jpg'], true)) return null;
    if ($file['size'] > 8 * 1024 * 1024)                      return null;

    $mimePermitidos = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/png',
        'image/jpeg',
    ];
    if (!in_array($file['type'], $mimePermitidos, true)) return null;

    $nombreFinal = 'sol_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
    $base        = "/ITSFCP-PROYECTOS/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
    $rutaFisica  = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
    $rutaBD      = $base . $nombreFinal;

    if (!is_dir(dirname($rutaFisica))) {
        mkdir(dirname($rutaFisica), 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
        throw new Exception("Error al guardar el archivo en disco.");
    }

    $modelo = new Solicitud($conn);
    return $modelo->registrarDocumento(
        nombre:         basename($file['name']),
        nombre_archivo: $nombreFinal,
        ruta:           $rutaBD,
        tipo_mime:      $file['type'],
        extension:      $ext,
        tamano_bytes:   $file['size'],
        tipo:           'recurso',
        visibilidad:    'privado',
        id_usuario:     $id_usuario
    );
}

try {
    $modelo = new Solicitud($conn);

    switch ($action) {

        // ── DETALLE ──────────────────────────────────────────────
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

            if (!$solicitud) {
                echo json_encode(['error' => 'No se encontró la solicitud.']);
                exit;
            }

            echo json_encode([
                'solicitud'   => $solicitud,
                'comentarios' => $comentarios,
            ]);
            break;

        // ── ACEPTAR ──────────────────────────────────────────────
        case 'aceptar':
            requirePost();

            if (!in_array($rol, ['investigador', 'profesor'], true)) {
                throw new Exception('Sin permiso.');
            }

            $id = intval($_POST['id_solicitud'] ?? 0);
            if (!$id || !$modelo->verificarPermiso($id, $id_usuario)) {
                throw new Exception('Sin permiso.');
            }

            $conn->begin_transaction();
            try {
                $datos = $modelo->obtenerDatosSolicitud($id);
                $ok    = $modelo->aceptar($id);
                if (!$ok) throw new Exception("Error al aceptar solicitud.");

                $modelo->vincularTareasAlNuevoEstudiante(
                    $datos['id_proyectos'],
                    $datos['id_usuarios']
                );

                $conn->commit();
                echo json_encode(['ok' => true, 'msg' => 'Solicitud aceptada y tareas vinculadas.']);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        // ── CORRECCIONES ─────────────────────────────────────────
        case 'correcciones':
            requirePost();

            if (!in_array($rol, ['investigador', 'profesor'], true)) {
                throw new Exception('Sin permiso.');
            }

            $id  = intval($_POST['id_solicitud'] ?? 0);
            $com = trim($_POST['comentario'] ?? '');

            if (!$id || $com === '') throw new Exception('Datos incompletos.');

            if (!$modelo->verificarPermiso($id, $id_usuario)) {
                throw new Exception('Sin permiso.');
            }

            $id_documento = procesarArchivo($id, $id_usuario, $conn);

            $ok = $modelo->pedirCorrecciones($id, $id_usuario, $com, $id_documento);

            echo json_encode([
                'ok'  => $ok,
                'msg' => $ok ? 'Correcciones solicitadas.' : 'Error.'
            ]);
            break;

        // ── RECHAZAR ─────────────────────────────────────────────
        case 'rechazar':
            requirePost();

            if (!in_array($rol, ['investigador', 'profesor'], true)) {
                throw new Exception('Sin permiso.');
            }

            $id  = intval($_POST['id_solicitud'] ?? 0);
            $com = trim($_POST['comentario'] ?? '');

            if (!$id || $com === '') throw new Exception('El motivo es obligatorio.');

            if (!$modelo->verificarPermiso($id, $id_usuario)) {
                throw new Exception('Sin permiso.');
            }

            $id_documento = procesarArchivo($id, $id_usuario, $conn);

            $ok = $modelo->rechazar($id, $id_usuario, $com, $id_documento);

            echo json_encode([
                'ok'  => $ok,
                'msg' => $ok ? 'Solicitud rechazada.' : 'Error.'
            ]);
            break;

        // ── ENVIAR CORRECCIONES (estudiante) ─────────────────────
        case 'enviarCorrecciones':
            requirePost();

            if ($rol !== 'estudiante') throw new Exception('Sin permiso.');

            $id  = intval($_POST['id_solicitud'] ?? 0);
            $com = trim($_POST['comentario'] ?? '');

            if (!$id) throw new Exception('ID inválido.');

            $id_documento = procesarArchivo($id, $id_usuario, $conn);

            $ok = $modelo->enviarCorrecciones($id, $id_usuario, $com, $id_documento);

            echo json_encode([
                'ok'  => $ok,
                'msg' => $ok ? 'Correcciones enviadas.' : 'Error.'
            ]);
            break;

        // ── COMENTARIOS ──────────────────────────────────────────
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
    echo json_encode(['error' => $e->getMessage()]);
}