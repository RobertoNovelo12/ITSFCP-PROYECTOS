<?php
session_start();
include("../../publico/config/conexion.php");

// Si no hay sesión, redirigir al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario (por si ya tiene foto o nombre guardado)
$stmt = $conn->prepare("SELECT username, foto_url FROM usuarios WHERE id_usuarios = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

$usernameActual = $usuario['username'] ?? '';
$foto_url = $usuario['foto_url'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $rol_nombre = strtolower(trim($_POST['user-role']));
    $institucion = trim($_POST['institucion']);

    // Manejo de imagen
    $foto_final = $foto_url; // por defecto conserva la foto actual

    if (!empty($_FILES['avatar-upload']['name'])) {
        $carpeta_destino = __DIR__ . "/../../publico/img/avatars/";
        $carpeta_web = "/ITSFCP-PROYECTOS/publico/img/avatars/";

        if (!file_exists($carpeta_destino)) {
            mkdir($carpeta_destino, 0777, true);
        }

        $nombre_archivo = uniqid("avatar_") . "_" . basename($_FILES['avatar-upload']['name']);
        $ruta_final = $carpeta_destino . $nombre_archivo;

        if (move_uploaded_file($_FILES['avatar-upload']['tmp_name'], $ruta_final)) {
            $foto_final = $carpeta_web . $nombre_archivo;
        }
    }

    // Actualizar datos del usuario
    $stmt = $conn->prepare("UPDATE usuarios 
                            SET username = ?, foto_url = ?, descripcion = ?, estado_usuario = 0
                            WHERE id_usuarios = ?");
    $stmt->bind_param("sssi", $username, $foto_final, $institucion, $id_usuario);
    $stmt->execute();

    // Asignar rol 
    $stmtRol = $conn->prepare("SELECT id_roles FROM roles WHERE nombre = ?");
    $stmtRol->bind_param("s", $rol_nombre);
    $stmtRol->execute();
    $resultadoRol = $stmtRol->get_result();

    if ($resultadoRol->num_rows > 0) {
        $rol = $resultadoRol->fetch_assoc();
        $id_rol = $rol['id_roles'];

        // Verificar si ya existe el rol asignado
        $check = $conn->prepare("SELECT id_usuarioR FROM usuarios_roles WHERE usuario_id = ?");
        $check->bind_param("i", $id_usuario);
        $check->execute();
        $resCheck = $check->get_result();

        if ($resCheck->num_rows > 0) {
            $updateRol = $conn->prepare("UPDATE usuarios_roles 
                                         SET rol_id = ?, fecha_asignacion = NOW() 
                                         WHERE usuario_id = ?");
            $updateRol->bind_param("ii", $id_rol, $id_usuario);
            $updateRol->execute();
        } else {
            $insertRol = $conn->prepare("INSERT INTO usuarios_roles 
                                         (usuario_id, rol_id, fecha_asignacion) 
                                         VALUES (?, ?, NOW())");
            $insertRol->bind_param("ii", $id_usuario, $id_rol);
            $insertRol->execute();
        }

        $_SESSION['rol'] = $rol_nombre;

        // Redirigir al formulario de solicitud según el rol
        header("Location: usuario.php?rol={$rol_nombre}");
        exit;
    } else {
        echo "rol no encontrado en la base de datos.";
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
        <h1 class="title-perfil">¡Vamos a empezar la solicitud de alta!</h1>

        <form class="form-perfil" action="" method="POST" enctype="multipart/form-data">
            <div class="avatar-section">
                <div class="avatar-container">
                    <?php if (!empty($foto_url)): ?>
                        <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="Avatar" class="avatar" id="avatar-img">
                    <?php else: ?>
                        <div class="avatar" id="avatar-letter">
                            <?php echo !empty($usernameActual) ? strtoupper($usernameActual[0]) : 'U'; ?>
                        </div>
                    <?php endif; ?>

                    <label for="avatar-upload" class="edit-avatar-btn">
                        <img src="../../publico/icons/ri_pencil-line.webp" alt="Editar Avatar">
                    </label>
                    <input type="file" id="avatar-upload" name="avatar-upload" accept="image/*,capture=camera">
                </div>
            </div>

            <div class="input-group">
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($usernameActual); ?>" 
                       class="input-field" placeholder=" " required>
                <label for="username" class="floating-label">Nombre de usuario</label>
            </div>

            <div class="role-section">
                <label class="role-label">¿Qué tipo de usuario eres?</label>
                <select class="role-select" id="user-role" name="user-role" required>
                    <option value="alumno">Alumno</option>
                    <option value="profesor">Profesor / Investigador</option>
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
    <script src="../../publico/js/javascript.js"></script>
</body>
</html>