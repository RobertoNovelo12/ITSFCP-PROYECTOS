<?php
session_start();
require_once(__DIR__ . "/conexion.php");

// Procesar login solo si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST['correo']);
    $clave  = trim($_POST['contraseña']);

    // Consulta correcta según tu estructura de BD
    $sql = "
        SELECT 
            u.id_usuarios,
            u.nombre,
            u.password_hash,
            u.username,
            u.estado_usuario,
            r.nombre AS rol
        FROM usuarios u
        LEFT JOIN usuarios_roles ur ON u.id_usuarios = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id_roles
        WHERE u.correo_institucional = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    // Usuario encontrado
    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        // Validar estado activo
        if ($usuario["estado_usuario"] != 1) {
            echo "<script>alert('Tu cuenta aún no está activada.'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
            exit;
        }

        // Validar contraseña
        if (!password_verify($clave, $usuario['password_hash'])) {
            echo "<script>alert('Contraseña incorrecta'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
            exit;
        }

        // Guardar variables de sesión
        $_SESSION['id_usuario'] = $usuario['id_usuarios'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['rol']        = $usuario['rol'] ? strtolower($usuario['rol']) : 'invitado';

        $base_url = "/ITSFCP-PROYECTOS/";

        // Si falta username o rol → mandar a completar perfil
        if (empty($usuario['username']) || empty($usuario['rol'])) {
            header("Location: {$base_url}Vistas/usuarios/crear_perfil.php");
            exit;
        }

        // Redirección por rol
        switch ($_SESSION['rol']) {

            case 'alumno':
                header("Location: {$base_url}Vistas/usuarios/alumno.php");
                break;

            case 'profesor':
            case 'investigador':
                header("Location: {$base_url}Vistas/usuarios/profesor.php");
                break;

            case 'supervisor':
                header("Location: {$base_url}Vistas/usuarios/supervisor.php");
                break;

            default:
                header("Location: {$base_url}index.php");
        }
        exit;

    } else {
        echo "<script>alert('Usuario no encontrado'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
        exit;
    }
}


// Si el usuario intenta entrar directo y ya tiene sesión → redirigir
if (isset($_SESSION['id_usuario'], $_SESSION['rol'])) {

    $base_url = "/ITSFCP-PROYECTOS/";

    switch ($_SESSION['rol']) {
        case 'alumno':
            header("Location: {$base_url}Vistas/usuarios/alumno.php");
            break;

        case 'profesor':
        case 'investigador':
            header("Location: {$base_url}Vistas/usuarios/profesor.php");
            break;

        case 'supervisor':
            header("Location: {$base_url}Vistas/usuarios/supervisor.php");
            break;

        default:
            header("Location: {$base_url}index.php");
    }
    exit;
}
?>