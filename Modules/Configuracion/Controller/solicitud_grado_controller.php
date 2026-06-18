<?php
require_once __DIR__ . '/../Model/solicitud_grado_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_botones.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

class SolicitudGradoControlador
{
    // 
    //  INVESTIGADOR — DATOS PARA LA VISTA
    // 

    public function datosInvestigador($id_usuario)
    {
        global $conn;
        $modelo = new SolicitudGrado($conn);
        return [
            'investigador'    => $modelo->obtenerDatosInvestigador($id_usuario),
            'grados'          => $modelo->obtenerGrados(),
            'pendiente_grado' => $modelo->tieneSolicitudPendiente($id_usuario),
        ];
    }

    // 
    //  INVESTIGADOR — HISTORIAL (línea de tiempo)
    // 

    public function historialInvestigador($id_usuario)
    {
        global $conn;
        $pagina = max(1, intval($_GET['pagina'] ?? 1));
        $modelo = new SolicitudGrado($conn);
        return $modelo->historialInvestigador($id_usuario, $pagina);
    }

    // 
    //  INVESTIGADOR — CREAR SOLICITUD (POST)
    // 

    public function crearSolicitud($id_usuario)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: grado_academico.php");
            exit;
        }

        global $conn;
        $modelo = new SolicitudGrado($conn);

        $valor_nuevo = intval($_POST['valor_nuevo_id'] ?? 0);

        $inv = $modelo->obtenerDatosInvestigador($id_usuario);
        if (empty($inv)) die("Investigador no encontrado.");

        $valor_actual = (int)$inv['id_grado'];
        $archivo      = $_FILES['documento'] ?? [];

        $resultado = $modelo->crearSolicitud($id_usuario, $valor_actual, $valor_nuevo, $archivo);

        if ($resultado['ok']) {
            header("Location: grado_academico.php?msg=1");
        } else {
            header("Location: grado_academico.php?error=" . urlencode($resultado['msg']));
        }
        exit;
    }

    // 
    //  SUPERVISOR — LISTADO
    // 

    public function index($buscar = null)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->obtenerSolicitudes(null, $buscar);
        if (is_string($resultado)) $resultado = json_decode($resultado, true);
        return $resultado;
    }

    public function Pendiente($buscar = null)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->obtenerSolicitudes('pendiente', $buscar);
        if (is_string($resultado)) $resultado = json_decode($resultado, true);
        return $resultado;
    }

    public function Aprobado($buscar = null)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->obtenerSolicitudes('aprobado', $buscar);
        if (is_string($resultado)) $resultado = json_decode($resultado, true);
        return $resultado;
    }

    public function Rechazado($buscar = null)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->obtenerSolicitudes('rechazado', $buscar);
        if (is_string($resultado)) $resultado = json_decode($resultado, true);
        return $resultado;
    }

    // 
    //  SUPERVISOR — FILTROS (conteos para el select)
    // 

    public function filtros()
    {
        global $conn;
        $modelo = new SolicitudGrado($conn);
        return $modelo->conteosFiltros();
    }

    public function opciones($filtros)
    {
        return [
            'index'     => "Todas ({$filtros['Total']})",
            'Pendiente' => "Pendientes ({$filtros['Pendiente']})",
            'Aprobado'  => "Aprobadas ({$filtros['Aprobado']})",
            'Rechazado' => "Rechazadas ({$filtros['Rechazado']})",
        ];
    }

    public function encabezados()
    {
        return ['Investigador', 'Grado actual', 'Grado solicitado', 'Documento', 'Estado', 'Fecha', 'Acciones'];
    }

    // 
    //  SUPERVISOR — DETALLE
    // 

    public function detalle($id_solicitud)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $solicitud = $modelo->obtenerDetalle($id_solicitud);
        $historial = $modelo->historialDeSolicitud($id_solicitud);
        return ['solicitud' => $solicitud, 'historial' => $historial];
    }

    // 
    //  SUPERVISOR — APROBAR (GET)
    // 

    public function aprobar($id_solicitud, $id_supervisor)
    {
        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->aprobarSolicitud($id_solicitud, $id_supervisor);

        if ($resultado['ok']) {
            $det   = $modelo->obtenerDetalle($id_solicitud);
            $datos = $modelo->obtenerCorreoInvestigador($det['id_usuarios']);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'aprobado',
                    ''
                );
            }
            header("Location: index_grado.php?msg=aprobado");
        } else {
            header("Location: index_grado.php?error=" . urlencode($resultado['msg']));
        }
        exit;
    }

    // 
    //  SUPERVISOR — RECHAZAR (POST)
    // 

    public function rechazar($data, $id_supervisor)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Método no permitido.");

        $id_solicitud = intval($data['id_solicitud'] ?? 0);
        $comentario   = trim($data['comentario'] ?? '');

        if ($id_solicitud <= 0) die("ID inválido.");

        global $conn;
        $modelo    = new SolicitudGrado($conn);
        $resultado = $modelo->rechazarSolicitud($id_solicitud, $id_supervisor, $comentario);

        if ($resultado['ok']) {
            $det   = $resultado['detalle'];
            $datos = $modelo->obtenerCorreoInvestigador($det['id_usuarios']);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'rechazado',
                    $comentario
                );
            }
            header("Location: index_grado.php?msg=rechazado");
        } else {
            header("Location: respuesta_grado.php?id_solicitud=" . $id_solicitud . "&error=" . urlencode($resultado['msg']));
        }
        exit;
    }

    // 
    //  HELPERS DE UI
    // 

    public function estiloEstado($estado)
    {
        switch (strtolower($estado)) {
            case 'pendiente': return 'warning';
            case 'aprobado':  return 'success';
            case 'rechazado': return 'danger';
            default:          return 'secondary';
        }
    }

    public function iconoEstado($estado)
    {
        switch (strtolower($estado)) {
            case 'pendiente': return 'bi-hourglass-split';
            case 'aprobado':  return 'bi-check-circle-fill';
            case 'rechazado': return 'bi-x-circle-fill';
            default:          return 'bi-circle';
        }
    }

    public function botonesAccion($id_solicitud, $estado)
    {
        $html  = '<a href="detalles_grado.php?id_solicitud=' . $id_solicitud . '"
                     class="btn btn-sm btn-primary"
                     data-bs-toggle="tooltip" title="Ver detalles">
                    <i class="bi bi-eye-fill"></i>
                  </a> ';

        if ($estado === 'pendiente') {
            $html .= '<a href="index_grado.php?action=aprobar&id_solicitud=' . $id_solicitud . '"
                         class="btn btn-sm btn-success"
                         data-bs-toggle="tooltip" title="Aprobar"
                         onclick="return confirm(\'¿Aprobar esta solicitud de grado académico?\')">
                        <i class="bi bi-check-circle-fill"></i>
                      </a> ';
            $html .= '<a href="respuesta_grado.php?id_solicitud=' . $id_solicitud . '"
                         class="btn btn-sm btn-danger"
                         data-bs-toggle="tooltip" title="Rechazar">
                        <i class="bi bi-x-circle-fill"></i>
                      </a>';
        }
        return $html;
    }

    // 
    //  CORREO (PHPMailer)
    // 

    private function enviarCorreo($destinatario, $nombre, $estado, $comentario)
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'luismarioiretaxiu1110@gmail.com';    // ← Cambiar
            $mail->Password   = 'alsu vdxr vbpb tgkr';         // ← Cambiar
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('luismarioiretaxiu1110@gmail.com', 'Sistema de Proyectos ITSFCP');
            $mail->addAddress($destinatario, $nombre);
            $mail->isHTML(true);
            $mail->Subject = 'Resultado de tu solicitud de Grado Académico';

            $estadoTexto = ($estado === 'aprobado') ? 'Aprobada' : 'Rechazada';
            $colorEstado = ($estado === 'aprobado') ? '#198754' : '#dc3545';
            $comentHtml  = !empty($comentario)
                ? "<p><strong>Motivo:</strong><br>" . nl2br(htmlspecialchars($comentario)) . "</p>"
                : "";

            $mail->Body = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
            <body style='font-family:Arial,sans-serif;background:#f8f9fa;padding:20px;'>
              <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,.1);'>
                <h2 style='color:#0d6efd;'>Sistema de Gestión de Proyectos</h2>
                <p>Hola, <strong>" . htmlspecialchars($nombre) . "</strong>:</p>
                <p>Tu solicitud de cambio de <strong>Grado Académico</strong> ha sido revisada.</p>
                <p><strong>Estado:</strong> <span style='color:{$colorEstado};font-weight:bold;'>{$estadoTexto}</span></p>
                {$comentHtml}
                <hr>
                <p style='font-size:12px;color:#6c757d;'>Correo automático, no respondas a este mensaje.</p>
              </div>
            </body></html>";
            $mail->AltBody = "Hola $nombre. Tu solicitud de Grado Académico fue: $estadoTexto. $comentario";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo Grado: " . $mail->ErrorInfo);
        }
    }
}
