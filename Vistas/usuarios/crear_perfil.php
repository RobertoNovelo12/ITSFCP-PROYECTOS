<?php
session_start();
include("../../publico/config/conexion.php");

//si no hay sesión, redirigir al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

//guardar los datos del perfil cuando se envíe el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $rol_nombre = trim($_POST['user-role']);
    $institucion = trim($_POST['institucion']);

    // Manejo de imagen
    $foto_url = '';
    if (!empty($_FILES['avatar-upload']['name'])) {
        $carpeta_destino = __DIR__ . "/../../publico/img/avatars/";
        $carpeta_web = "/ITSFCP-PROYECTOS/publico/img/avatars/";

        $nombre_archivo = uniqid("avatar_") . "_" . basename($_FILES['avatar-upload']['name']);
        $ruta_final = $carpeta_destino . $nombre_archivo; // POR QUE NO FUNCIONA ESTA PORQYEASDFRUIqwebfuiqweblIÑKFJASD

        if (move_uploaded_file($_FILES['avatar-upload']['tmp_name'], $ruta_final)) {
            $foto_url = $carpeta_web . $nombre_archivo;
        }
    }

    //actualizar usuario (solo datos propios)
    $stmt = $conn->prepare("UPDATE usuarios 
                            SET username = ?, foto_url = ?, descripcion = ?, estado_usuario = 1 
                            WHERE id_usuarios = ?");
    $stmt->bind_param("sssi", $username, $foto_url, $institucion, $id_usuario);
    $stmt->execute();

    //Obtener el ID del rol seleccionado
    $stmtRol = $conn->prepare("SELECT id_roles FROM roles WHERE nombre = ?");
    $stmtRol->bind_param("s", $rol_nombre);
    $stmtRol->execute();
    $resultadoRol = $stmtRol->get_result();

    if ($resultadoRol->num_rows > 0) {
        $rol = $resultadoRol->fetch_assoc();
        $id_rol = $rol['id_roles'];

        // Insertar o actualizar en usuarios_roles
        $check = $conn->prepare("SELECT id_usuarioR FROM usuarios_roles WHERE usuario_id = ?");
        $check->bind_param("i", $id_usuario);
        $check->execute();
        $resCheck = $check->get_result();

        if ($resCheck->num_rows > 0) {
            // Ya existe entonces actualizar
            $updateRol = $conn->prepare("UPDATE usuarios_roles SET rol_id = ?, fecha_asignacion = NOW() WHERE usuario_id = ?");
            $updateRol->bind_param("ii", $id_rol, $id_usuario);
            $updateRol->execute();
        } else {
            //no existe entonces insertar nuevo
            $insertRol = $conn->prepare("INSERT INTO usuarios_roles (usuario_id, rol_id, fecha_asignacion) VALUES (?, ?, NOW())");
            $insertRol->bind_param("ii", $id_usuario, $id_rol);
            $insertRol->execute();
        }

        $_SESSION['rol'] = strtolower($rol_nombre);

        //redirigir segun el rol
        $base_url = "/ITSFCP-PROYECTOS/Vistas/usuarios/";
        switch ($rol_nombre) {
            case 'alumno':
                header("Location: {$base_url}alumno.php");
                break;
            case 'profesor':
                header("Location: {$base_url}profesor.php");
                break;
            default:
                header("Location: {$base_url}supervisor.php");
                break;
        }
        exit;
    } else {
        echo "❌ Rol no encontrado en la base de datos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Perfil</title>
    <link rel="stylesheet" href="../../publico/css/styles.css">
</head>

<body class="body-register">
    <div class="container-perfil">
        <h1 class="title-perfil">¡Vamos a crear un perfil!</h1>

        <div class="avatar-section">
            <div class="avatar-container">
                <div class="avatar" id="avatar-letter"></div>
                <label for="avatar-upload" class="edit-avatar-btn">
                    <img src="../../publico/icons/ri_pencil-line.webp" alt="">
                </label>
                <input type="file" id="avatar-upload" name="avatar-upload" accept="image/*,capture=camera">
            </div>
        </div>

        <form class="form-perfil" action="" method="POST" enctype="multipart/form-data">
            <div class="input-group">
                <input type="text" id="username" name="username" class="input-field" placeholder=" " required>
                <label for="username" class="floating-label">Nombre de usuario</label>
            </div>

            <div class="role-section">
                <label class="role-label">¿Qué tipo de usuario eres?</label>
                <select class="role-select" id="user-role" name="user-role" required>
                    <option value="alumno">Alumno</option>
                    <option value="profesor">Profesor</option>
                    <option value="supervisor">Supervisor</option>
                </select>
            </div>

            <div class="input-group">
                <input type="text" id="institucion" name="institucion" class="input-field" placeholder=" " required>
                <label for="institucion" class="floating-label">Institución</label>
            </div>

            <button type="submit" class="submit-btn">Confirmar</button>
        </form>
    </div>
</body>
</html>