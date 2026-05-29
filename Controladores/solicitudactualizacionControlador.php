<?php
// Controladores/solicitudActualizacionControlador.php

require_once __DIR__ . '/../Modelos/solicitudActualizacion.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class SolicitudActualizacionControlador extends BaseControlador
{
    // ─
    //  LISTADO (supervisor)
    // ─

    public function index(?string $buscar = null, ?string $tipo = null): array
    {
        global $conn;
        try {
            $resultado = (new SolicitudActualizacion($conn))->obtenerSolicitudes(null, $buscar, $tipo);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::index() — ' . $e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    public function Pendiente(?string $buscar = null, ?string $tipo = null): array
    {
        global $conn;
        try {
            $resultado = (new SolicitudActualizacion($conn))->obtenerSolicitudes('pendiente', $buscar, $tipo);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::Pendiente() — ' . $e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    public function Aprobado(?string $buscar = null, ?string $tipo = null): array
    {
        global $conn;
        try {
            $resultado = (new SolicitudActualizacion($conn))->obtenerSolicitudes('aprobado', $buscar, $tipo);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::Aprobado() — ' . $e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    public function Rechazado(?string $buscar = null, ?string $tipo = null): array
    {
        global $conn;
        try {
            $resultado = (new SolicitudActualizacion($conn))->obtenerSolicitudes('rechazado', $buscar, $tipo);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::Rechazado() — ' . $e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    // ─
    //  ENCABEZADOS
    // ─

    public function opciones(): array
    {
        return [
            'index'     => "Todas",
            'Pendiente' => "Pendientes",
            'Aprobado'  => "Aprobadas",
            'Rechazado' => "Rechazadas",
        ];
    }

    public function encabezados(): array
    {
        return ['Investigador', 'Tipo', 'Valor actual', 'Valor solicitado', 'Documento', 'Estado', 'Fecha', 'Acciones'];
    }

    // ─
    //  DETALLE
    // ─

    public function detalle(int $id_solicitud): array
    {
        global $conn;
        try {
            $modelo    = new SolicitudActualizacion($conn);
            $solicitud = $modelo->obtenerDetalle($id_solicitud);
            $historial = $modelo->historialDeSolicitud($id_solicitud);
            return ['solicitud' => $solicitud, 'historial' => $historial];
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::detalle() — ' . $e->getMessage());
            return ['solicitud' => null, 'historial' => []];
        }
    }

    // ─
    //  APROBAR (GET)
    // ─

    /**
     * Aprueba una solicitud y redirige con msg al index.
     */
    public function aprobar(int $id_solicitud, int $id_supervisor): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($_SESSION['rol'] ?? '', ['supervisor']);

            $modelo    = new SolicitudActualizacion($conn);
            $resultado = $modelo->aprobarSolicitud($id_solicitud, $id_supervisor);

            if ($resultado['ok']) {
                $det   = $modelo->obtenerDetalle($id_solicitud);
                $datos = $modelo->obtenerCorreoInvestigador($det['id_usuarios']);
                if ($datos) {
                    $this->enviarCorreo(
                        $datos['correo_institucional'],
                        $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                        'aprobado',
                        '',
                        $det['tipo']
                    );
                }
                $this->redirigir('exito_aprobar');
            } else {
                $this->redirigir('error_aprobar');
            }
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::aprobar() — ' . $e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_aprobar';
            $this->redirigir($msg);
        }
    }

    // ─
    //  RECHAZAR (POST)
    // ─

    /**
     * Rechaza una solicitud con comentario y redirige con msg al index.
     */
    public function rechazar(array $data, int $id_supervisor): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($_SESSION['rol'] ?? '', ['supervisor']);

            $id_solicitud = (int)($data['id_solicitud'] ?? 0);
            $comentario   = trim($data['comentario'] ?? '');

            if ($id_solicitud <= 0 || empty($comentario)) {
                throw new Exception('datos_incompletos');
            }

            $modelo    = new SolicitudActualizacion($conn);
            $resultado = $modelo->rechazarSolicitud($id_solicitud, $id_supervisor, $comentario);

            if ($resultado['ok']) {
                $det   = $resultado['detalle'];
                $datos = $modelo->obtenerCorreoInvestigador($det['id_usuarios']);
                if ($datos) {
                    $this->enviarCorreo(
                        $datos['correo_institucional'],
                        $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                        'rechazado',
                        $comentario,
                        $det['tipo']
                    );
                }
                $this->redirigir('exito_rechazar');
            } else {
                $this->redirigir('error_rechazar', 'respuesta.php', "&id_solicitud={$id_solicitud}");
            }
        } catch (Exception $e) {
            error_log('SolicitudActualizacionControlador::rechazar() — ' . $e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_rechazar';
            $this->redirigir($msg);
        }
    }

    // ─
    //  HELPERS DE PRESENTACIÓN
    // ─

    public function estiloEstado(string $estado): string
    {
        return match (strtolower($estado)) {
            'pendiente' => 'warning',
            'aprobado'  => 'success',
            'rechazado' => 'danger',
            default     => 'secondary',
        };
    }

    public function iconoEstado(string $estado): string
    {
        return match (strtolower($estado)) {
            'pendiente' => 'bi-hourglass-split',
            'aprobado'  => 'bi-check-circle-fill',
            'rechazado' => 'bi-x-circle-fill',
            default     => 'bi-circle',
        };
    }

    public function etiquetaTipo(string $tipo): string
    {
        return $tipo === 'sni' ? 'Nivel SNI' : 'Grado académico';
    }

    public function botonesAccion(int $id_solicitud, string $estado): string
    {
        $html = '<a href="detalles.php?id_solicitud=' . $id_solicitud . '"
                    class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" title="Ver detalles">
                    <i class="bi bi-eye-fill"></i>
                 </a> ';

        if ($estado === 'pendiente') {
            $html .= '<a href="index.php?action=aprobar&id_solicitud=' . $id_solicitud . '"
                         class="btn btn-sm btn-success"
                         data-bs-toggle="tooltip" title="Aprobar"
                         onclick="return confirm(\'¿Aprobar esta solicitud?\')">
                         <i class="bi bi-check-circle-fill"></i>
                      </a> ';
            $html .= '<a href="respuesta.php?id_solicitud=' . $id_solicitud . '"
                         class="btn btn-sm btn-danger"
                         data-bs-toggle="tooltip" title="Rechazar">
                         <i class="bi bi-x-circle-fill"></i>
                      </a>';
        }
        return $html;
    }

    // ─
    //  CORREO (PHPMailer)
    // ─

    private function enviarCorreo(
        string $destinatario,
        string $nombre,
        string $estado,
        string $comentario,
        string $tipo
    ): void {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'correo@gmail.com';   // ← Cambiar
            $mail->Password   = 'app_password';        // ← Cambiar
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('correo@gmail.com', 'Sistema de Proyectos ITSFCP');
            $mail->addAddress($destinatario, $nombre);
            $mail->isHTML(true);
            $mail->Subject = 'Resultado de tu solicitud académica';

            $estadoTexto = ($estado === 'aprobado') ? 'Aprobada' : 'Rechazada';
            $colorEstado = ($estado === 'aprobado') ? '#198754' : '#dc3545';
            $tipoTexto   = ($tipo   === 'sni')      ? 'Nivel SNI' : 'Grado Académico';
            $comentHtml  = !empty($comentario)
                ? '<p><strong>Motivo:</strong><br>' . nl2br(htmlspecialchars($comentario)) . '</p>'
                : '';

            $mail->Body = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
            <body style='font-family:Arial,sans-serif;background:#f8f9fa;padding:20px;'>
              <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,.1);'>
                <h2 style='color:#0d6efd;'>Sistema de Gestión de Proyectos</h2>
                <p>Hola, <strong>" . htmlspecialchars($nombre) . "</strong>:</p>
                <p>Tu solicitud de cambio de <strong>{$tipoTexto}</strong> ha sido revisada.</p>
                <p><strong>Estado:</strong> <span style='color:{$colorEstado};font-weight:bold;'>{$estadoTexto}</span></p>
                {$comentHtml}
                <hr>
                <p style='font-size:12px;color:#6c757d;'>Correo automático, no respondas a este mensaje.</p>
              </div>
            </body></html>";
            $mail->AltBody = "Hola $nombre. Tu solicitud de $tipoTexto fue: $estadoTexto. $comentario";
            $mail->send();
        } catch (\Exception $e) {
            error_log('Error enviando correo: ' . $mail->ErrorInfo);
        }
    }
}