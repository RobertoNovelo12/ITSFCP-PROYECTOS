<?php
session_start();
include("conexion.php");

// Procesar login solo si se envio el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST['correo']);
    $clave = trim($_POST['contraseña']);

    $sql = "
        SELECT 
            u.id_usuarios, 
            u.nombre, 
            u.password_hash, 
            u.username, 
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

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        // Verificar la contraseña
        if (password_verify($clave, $usuario['password_hash'])) {

            // Guardar sesión del usuario
            $_SESSION['id_usuario'] = $usuario['id_usuarios'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'] ? strtolower(trim($usuario['rol'])) : 'invitado';

            $base_url = "/ITSFCP-PROYECTOS/";

            // Si el usuario no tiene perfil completo
            if (empty($usuario['username']) || empty($usuario['rol'])) {
                header("Location: {$base_url}Vistas/usuarios/crear_perfil.php");
                exit;
            }

            // Redirigir según rol
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
            // Contraseña incorrecta
            echo "<script>alert('Contraseña incorrecta'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
            exit;
        }
    } else {
        // Usuario no encontrado
        echo "<script>alert('Usuario no encontrado'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
        exit;
    }
}

// Si ya hay sesin activa pos redirigir según rol
if (isset($_SESSION['id_usuario']) && isset($_SESSION['rol'])) {
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