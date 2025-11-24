<?php
session_start();
include("../../publico/config/conexion.php");

// Si no hay sesión, redirigir
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener nombre del usuario
$stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id_usuarios = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

$nombre_usuario = $usuario['nombre'] ?? 'U';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rol_nombre = strtolower(trim($_POST['user-role']));

    // Obtener ID del rol seleccionado
    $stmtRol = $conn->prepare("SELECT id_roles FROM roles WHERE nombre = ?");
    $stmtRol->bind_param("s", $rol_nombre);
    $stmtRol->execute();
    $resultadoRol = $stmtRol->get_result();

    if ($resultadoRol->num_rows == 0) {
        die("Rol no encontrado.");
    }

    $id_rol = $resultadoRol->fetch_assoc()['id_roles'];

    // Verificar si ya existe asignación
    $check = $conn->prepare("SELECT id_usuarioR FROM usuarios_roles WHERE id_usuario = ?");
    $check->bind_param("i", $id_usuario);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $update = $conn->prepare("
            UPDATE usuarios_roles 
            SET id_rol = ?, fecha_asignacion = NOW()
            WHERE id_usuario = ?
        ");
        $update->bind_param("ii", $id_rol, $id_usuario);
        $update->execute();
    } else {
        $insert = $conn->prepare("
            INSERT INTO usuarios_roles (id_usuario, id_rol, fecha_asignacion)
            VALUES (?, ?, NOW())
        ");
        $insert->bind_param("ii", $id_usuario, $id_rol);
        $insert->execute();
    }

    $_SESSION['rol'] = $rol_nombre;

    header("Location: usuario.php?rol=$rol_nombre");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Crear Perfil</title>
    <link rel="stylesheet" href="../../publico/css/styles.css">
</head>

<body class="body-register">
    <div class="container-perfil">
        <h1 class="title-perfil">¡Vamos a empezar la solicitud de alta!</h1>

        <form class="form-perfil" action="" method="POST">

            <div class="avatar-section">
                <div class="avatar-container">
                    <div class="avatar">
                        <?= strtoupper($nombre_usuario[0]) ?>
                    </div>
                </div>
            </div>

            <div class="role-section">
                <label class="role-label">¿Qué tipo de usuario eres?</label>
                <select class="role-select" id="user-role" name="user-role" required>
                    <option value="estudiante">Alumno</option>
                    <option value="investigador">Investigador / Profesor</option>
                    <option value="supervisor">Supervisor</option>
                </select>

            </div>

            <button type="submit" class="submit-btn">Confirmar</button>

        </form>
    </div>
    <script src="/ITSFCP-PROYECTOS/publico/js/javascript.js"></script>
</body>

</html>