<?php
session_start();
include("conexion.php");

// Si ya hay sesión activa, redirigir al panel correspondiente
if (isset($_SESSION['id_usuario'])) {
    $base_url = "/ITSFCP-PROYECTOS/";
    switch ($_SESSION['rol']) {
        case 'Estudiante':
            header("Location: {$base_url}Vistas/proyectos/alumno.php");
            break;
        case 'Profesor':
        case 'Investigador':
            header("Location: {$base_url}Vistas/proyectos/profesor.php");
            break;
        case 'Supervisor':
            header("Location: {$base_url}Vistas/proyectos/supervisor.php");
            break;
        default:
            header("Location: {$base_url}index.php");
    }
    exit;
}

// Procesar el formulario de inicio de sesión
if (isset($_POST['login'])) {
    $correo = trim($_POST['correo']);
    $clave = trim($_POST['contraseña']);

    $stmt = $conn->prepare("SELECT u.id_usuario, u.nombre, u.contraseña, r.nombre_rol 
                            FROM Usuario u 
                            JOIN Rol r ON u.id_rol = r.id_rol 
                            WHERE u.correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        // Si aún no usas password_hash:
        if ($clave === $usuario['contraseña']) {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['nombre_rol'];

            $base_url = "/ITSFCP-PROYECTOS/";

            switch ($usuario['nombre_rol']) {
                case 'Estudiante':
                    header("Location: {$base_url}Vistas/proyectos/alumno.php");
                    break;
                case 'Profesor':
                case 'Investigador':
                    header("Location: {$base_url}Vistas/proyectos/profesor.php");
                    break;
                case 'Supervisor':
                    header("Location: {$base_url}Vistas/proyectos/supervisor.php");
                    break;
                default:
                    header("Location: {$base_url}index.php");
            }
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario no existe.";
    }
}
?>
