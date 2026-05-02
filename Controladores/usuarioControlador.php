<?php
require_once __DIR__ . '/../Modelos/usuario.php';
require_once __DIR__ . '/../publico/config/conexion.php';

// PHPMailer (instalado vía Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class UsuariosControlador
{
    // 
    //  LISTADO PRINCIPAL (acción por defecto: index)
    // 
    public function index($rol, $buscar = null, $tipo = null)
    {
        if ($rol !== 'supervisor') return json_encode(["usuarios" => [], "paginacion" => []]);
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuarios(null, $buscar, $tipo);
    }

    // 
    //  FILTROS POR ESTADO
    // 
    public function Espera($rol, $buscar = null, $tipo = null)
    {
        if ($rol !== 'supervisor') return json_encode(["usuarios" => [], "paginacion" => []]);
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuarios('espera', $buscar, $tipo);
    }

    /*public function Aprobado($rol, $buscar = null, $tipo = null)
    {
        if ($rol !== 'supervisor') return json_encode(["usuarios" => [], "paginacion" => []]);
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuarios('aprobado', $buscar, $tipo);
    }*/

    public function Activo($rol, $buscar = null, $tipo = null)
    {
        if ($rol !== 'supervisor') return json_encode(["usuarios" => [], "paginacion" => []]);
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuarios('activo', $buscar, $tipo);
    }

    public function Cancelado($rol, $buscar = null, $tipo = null)
    {
        if ($rol !== 'supervisor') return json_encode(["usuarios" => [], "paginacion" => []]);
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuarios('cancelado', $buscar, $tipo);
    }

    // 
    //  DATOS PARA LOS BOTONES DE FILTRO
    // 
    public function filtros($rol)
    {
        if ($rol !== 'supervisor') return [];
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuariosDatosFiltro();
    }

    // 
    //  OPCIONES DEL SELECT DE FILTRO
    // 
    public function opciones($rol, $filtros)
    {
        if ($rol !== 'supervisor') return [];
        return [
            'index'     => "Todos ({$filtros['Total']})",
            'Espera'    => "En espera ({$filtros['Espera']})",
            'Activo'    => "Activos ({$filtros['Activo']})",
            'Cancelado' => "Cancelados ({$filtros['Cancelado']})",
        ];
    }

    // 
    //  ENCABEZADOS DE LA TABLA
    // 
    public function encabezados($rol)
    {
        if ($rol !== 'supervisor') return [];
        return ['Nombre completo', 'Correo', 'Teléfono', 'Tipo', 'Fecha registro', 'Estado', 'Acciones'];
    }

    // 
    //  DETALLE DE USUARIO
    // 
    public function indexDetalles($rol, $id_usuario)
    {
        if ($rol !== 'supervisor') return [];
        global $conn;
        $usuario = new Usuarios($conn);
        return $usuario->obtenerUsuario($id_usuario);
    }

    // 
    //  APROBAR USUARIO (GET con action=aprobar)
    // 
    public function aprobar($id_usuario, $rol)
    {
        if ($rol !== 'supervisor') die("Sin permiso.");
        global $conn;
        $modelo = new Usuarios($conn);

        $ok = $modelo->actualizarEstado($id_usuario, 'activo');
        if ($ok) {
            // Enviar correo de aprobación
            $datos = $modelo->obtenerCorreo($id_usuario);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'activo',
                    ''
                );
            }
        }
        header("Location: tabla.php?msg=activo");
        exit;
    }

    // 
    //  RECHAZAR USUARIO CON COMENTARIO (POST)
    // 
    public function rechazar($data, $rol)
    {
        if ($rol !== 'supervisor') die("Sin permiso.");
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Método no permitido.");

        $id_usuario = intval($data['id_usuario'] ?? 0);
        $comentario = trim($data['comentario'] ?? '');

        if ($id_usuario <= 0) die("ID inválido.");

        global $conn;
        $modelo = new Usuarios($conn);
        $ok = $modelo->rechazarUsuario($id_usuario, $comentario);

        if ($ok) {
            $datos = $modelo->obtenerCorreo($id_usuario);
            if ($datos) {
                $this->enviarCorreo(
                    $datos['correo_institucional'],
                    $datos['nombre'] . ' ' . $datos['apellido_paterno'],
                    'cancelado',
                    $comentario
                );
            }
        }
        header("Location: tabla.php?msg=rechazado");
        exit;
    }

    // 
    //  ENVÍO DE CORREO CON PHPMAILER
    //  SE DEBE ACTUALIZAR CON LA INFORMACIÓN DEL INSTITUTO
    // 
    private function enviarCorreo($destinatario, $nombre, $estado, $comentario)
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) return; // PHPMailer no instalado

        $mail = new PHPMailer(true);
        try {
            // Configuración SMTP — ajusta según tu servidor
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';       // Cambia según proveedor
            $mail->SMTPAuth   = true;
            $mail->Username   = 'luismarioiretaxiu1110@gmail.com';  // Correo remitente - Cambiar a institucional
            $mail->Password   = 'alsu vdxr vbpb tgkr';    // Contraseña de aplicación
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('luismarioiretaxiu1110@gmail.com', 'Sistema de Proyectos ITSFCP');
            $mail->addAddress($destinatario, $nombre);

            $mail->isHTML(true);
            $mail->Subject = 'Resultado de tu solicitud en el sistema de proyectos';

            $estadoTexto = ($estado === 'actjvo') ? 'Activo' : 'Cancelado / Rechazado';
            $colorEstado = ($estado === 'activo') ? '#198754' : '#dc3545';
            $comentarioHtml = !empty($comentario)
                ? "<p><strong>Comentario del supervisor:</strong><br>" . nl2br(htmlspecialchars($comentario)) . "</p>"
                : "";

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

    // 
    //  ESTILOS DE BADGE POR ESTADO
    // 
    public function EstiloEstado($estado)
    {
        switch (strtolower($estado)) {
            case 'espera':    return 'warning';
            case 'activo':    return 'success';
            case 'cancelado': return 'danger';
            default:          return 'secondary';
        }
    }

    // 
    //  BOTONES DE ACCIÓN POR ESTADO
    // 
    public function botonesAccion($id, $rol, $estado)
    {
        if ($rol !== 'supervisor') return '';
        $html = '';

        // Botón ver detalles — siempre visible
        $html .= '<a href="detalles.php?id_usuarios=' . $id . '"
                     class="btn btn-sm btn-primary"
                     data-bs-toggle="tooltip" title="Ver detalles">
                    <i class="bi bi-eye-fill"></i>
                  </a> ';

        if ($estado === 'espera') {
            // Aprobar
            $html .= '<a href="tabla.php?action=aprobar&id_usuarios=' . $id . '"
                         class="btn btn-sm btn-success"
                         data-bs-toggle="tooltip" title="Aprobar acceso"
                         onclick="return confirm(\'¿Aprobar este usuario?\')">
                        <i class="bi bi-check-circle-fill"></i>
                      </a> ';
            // Rechazar
            $html .= '<a href="respuesta.php?id_usuarios=' . $id . '"
                         class="btn btn-sm btn-danger"
                         data-bs-toggle="tooltip" title="Rechazar solicitud">
                        <i class="bi bi-x-circle-fill"></i>
                      </a>';
        }

        return $html;
    }
}