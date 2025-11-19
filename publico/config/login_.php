<?php
session_start();
require_once(__DIR__ . "/conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST['correo']);
    $clave  = trim($_POST['contraseña']);

    $sql = "
        SELECT 
            u.id_usuarios,
            u.nombre,
            u.password,
            u.estado_usuario,
            r.nombre AS rol
        FROM usuarios u
        LEFT JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        LEFT JOIN roles r ON ur.id_rol = r.id_roles
        WHERE u.correo_institucional = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        if ($usuario["estado_usuario"] !== "activo") {
            header("Location: /ITSFCP-PROYECTOS/login.php?solicitud_enviada=1");
            exit;
        }

        if (!password_verify($clave, $usuario['password'])) {
            echo "<script>alert('Contraseña incorrecta'); window.location.href='/ITSFCP-PROYECTOS/login.php';</script>";
            exit;
        }

        // Guardamos sesión
        $_SESSION['id_usuario'] = $usuario['id_usuarios'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['rol']        = $usuario['rol'] ? strtolower($usuario['rol']) : 'invitado';

        $base_url = "/ITSFCP-PROYECTOS/";

        switch ($_SESSION['rol']) {

            case 'estudiante':
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
?>