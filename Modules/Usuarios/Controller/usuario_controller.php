<?php

require_once __DIR__ . '/../Model/usuario_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';
include __DIR__ . '/../../../public/incluido/_botones.php';

// 1. Primero el require, sin condicional
require_once __DIR__ . '../../../../vendor/autoload.php';

// 2. Luego los use
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


class UsuariosControlador extends BaseControlador
{

    // ─
    // LISTADO PRINCIPAL Y FILTROS
    // ─

    public function index(string $rol, ?string $buscar = null, ?string $tipo = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $resultado = (new Usuarios($conn))->obtenerUsuarios(null, $buscar, $tipo);
            return $resultado;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return ['usuarios' => [], 'paginacion' => []];
        }
    }

    private function obtenerPorFiltro(string $rol, ?string $estado, ?string $buscar, ?string $tipo): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $resultado = (new Usuarios($conn))->obtenerUsuarios($estado, $buscar, $tipo);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return ['usuarios' => [], 'paginacion' => []];
        }
    }

    public function Espera(string $rol, ?string $buscar = null, ?string $tipo = null): array
    {
        return $this->obtenerPorFiltro($rol, 'espera', $buscar, $tipo);
    }

    public function Activo(string $rol, ?string $buscar = null, ?string $tipo = null): array
    {
        return $this->obtenerPorFiltro($rol, 'activo', $buscar, $tipo);
    }

    public function Cancelado(string $rol, ?string $buscar = null, ?string $tipo = null): array
    {
        return $this->obtenerPorFiltro($rol, 'cancelado', $buscar, $tipo);
    }


    // ─
    // DETALLE DE USUARIO
    // ─

    public function indexDetalles(string $rol, int $id_usuario): ?array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Usuarios($conn))->obtenerUsuario($id_usuario);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return null;
        }
    }


    // ─
    // OPCIONES Y ENCABEZADOS
    // ─

    public function opciones(): array
    {
        return [
            'index'     => 'Todos',
            'Espera'    => 'En espera',
            'Activo'    => 'Activos',
            'Cancelado' => 'Cancelados',
        ];
    }

    public function encabezados(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Nombre completo', 'Correo', 'Teléfono', 'Tipo', 'Fecha registro', 'Estado', 'Acciones'];
    }


    // ─
    // APROBAR USUARIO (GET)
    // ─

    public function aprobar(int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            if ($id_usuario <= 0) {
                throw new Exception('error_operacion');
            }


            $modelo = new Usuarios($conn);
            $modelo->actualizarEstado($id_usuario, 'activo');

            $datos = $modelo->obtenerCorreo($id_usuario);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'activo',
                    ''
                );
            }

            $this->redirigir('exito_operacion', 'index.php');
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_operacion'])
                ? $e->getMessage()
                : 'error_operacion';
            $this->redirigir($msg, 'index.php');
        }
    }


    // ─
    // RECHAZAR USUARIO CON COMENTARIO (POST)
    // ─

    public function rechazar(array $data, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_usuario = (int)($data['id_usuario'] ?? 0);
            $comentario = $this->limpiar($data['comentario'] ?? '');

            if ($id_usuario <= 0) {
                throw new Exception('error_operacion');
            }

            $modelo = new Usuarios($conn);
            $modelo->rechazarUsuario($id_usuario, $comentario);

            $datos = $modelo->obtenerCorreo($id_usuario);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'cancelado',
                    $comentario
                );
            }

            $this->redirigir('exito_operacion', 'index.php');
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_operacion'])
                ? $e->getMessage()
                : 'error_operacion';
            $this->redirigir($msg, 'index.php');
        }
    }


    // ─
    // ESTILO DE BADGE POR ESTADO
    // ─

    public function EstiloEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'espera'    => 'warning',
            'activo'    => 'success',
            'cancelado' => 'danger',
            default     => 'secondary',
        };
    }


    // ─
    // BOTONES DE ACCIÓN POR ESTADO
    // Usa iconos de _iconos.php['tabla']
    // ─

    public function botonesAccion(int $id, string $rol, string $estado): string
    {
        if (!$this->esSupervisor($rol)) return '';

        include __DIR__ . '/../../../public/incluido/_iconos.php';

        // Botón "Ver detalles" siempre presente
        $html = Botones::botonIcono(
            'detalles.php?id_usuarios=' . $id,
            'primary',
            $iconos['tabla']['ver'],
            'Ver detalles'
        );

        if (strtolower($estado) === 'espera') {

            $html .= Botones::botonConfirmacion(
                'index.php?action=aprobar&id_usuarios=' . $id,
                'success',
                $iconos['detalles']['exito_verificado'],
                'Aprobar acceso',
                '¿Aprobar este usuario?'
            );

            $html .= Botones::botonIcono(
                'respuesta.php?id_usuarios=' . $id,
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Rechazar solicitud'
            );
        }

        return $html;
    }



    // ─
    // ENVÍO DE CORREO CON PHPMAILER
    // ACTUALIZAR CON LA INFORMACIÓN DEL INSTITUTO
    // ─

    private function enviarCorreo(string $destinatario, string $nombre, string $estado, string $comentario): void
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'luismarioiretaxiu1110@gmail.com'; // Cambiar a institucional
            $mail->Password   = 'alsu vdxr vbpb tgkr';                   // Contraseña de aplicación
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('luismarioiretaxiu1110@gmail.com', 'Sistema de Proyectos ITSFCP');
            $mail->addAddress($destinatario, $nombre);
            $mail->isHTML(true);
            $mail->Subject = 'Resultado de tu solicitud en el sistema de proyectos';

            $estadoTexto    = ($estado === 'activo') ? 'Activo' : 'Cancelado / Rechazado';
            $colorEstado    = ($estado === 'activo') ? '#198754' : '#dc3545';
            $comentarioHtml = !empty($comentario)
                ? '<p><strong>Comentario del supervisor:</strong><br>' . nl2br(htmlspecialchars($comentario)) . '</p>'
                : '';

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; background:#f8f9fa; padding:20px;'>
                <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px;
                            padding:30px; box-shadow:0 2px 8px rgba(0,0,0,.1);'>
                    <h2 style='color:#0d6efd;'>Sistema de Gestión de Proyectos</h2>
                    <p>Hola, <strong>" . htmlspecialchars($nombre) . "</strong>:</p>
                    <p>Tu solicitud de acceso al sistema ha sido revisada.</p>
                    <p><strong>Estado:</strong>
                        <span style='color:{$colorEstado}; font-weight:bold;'>{$estadoTexto}</span>
                    </p>
                    {$comentarioHtml}
                    <hr>
                    <p style='font-size:12px; color:#6c757d;'>
                        Este es un correo automático, por favor no respondas a este mensaje.
                    </p>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Hola $nombre, tu solicitud fue: $estadoTexto. Comentario: $comentario";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $mail->ErrorInfo);
        }
    }
}
