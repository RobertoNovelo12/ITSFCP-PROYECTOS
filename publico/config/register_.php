<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ============================
    // LIMPIAR Y OBTENER DATOS
    // ============================
    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $curp = strtoupper(trim($_POST['curp'])); // CURP en mayúsculas
    $correo = strtolower(trim($_POST['correo'])); // Correo en minúsculas
    $telefono = trim($_POST['telefono']);
    $dia = (int)$_POST['dia'];
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];
    $id_genero = intval($_POST['genero']);
    $password = trim($_POST['contraseña']);
    $confirmar = trim($_POST['confirmar']);

    // ============================
    // VALIDACIONES BÁSICAS
    // ============================
    if (empty($nombre) || empty($apellido_paterno) || empty($apellido_materno)) {
        die("Debes ingresar tu nombre completo.");
    }

    if (empty($curp) || empty($correo) || empty($password) || empty($confirmar)) {
        die("CURP, correo y contraseña son obligatorios.");
    }

    if ($password !== $confirmar) {
        die("Las contraseñas no coinciden.");
    }

    // Validar fecha
    if (!checkdate($mes, $dia, $anio)) {
        die("Fecha de nacimiento inválida.");
    }

    $fecha_nacimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // ============================
    // VALIDAR DUPLICADOS
    // ============================
    $stmtCheck = $conn->prepare("
        SELECT id_usuarios 
        FROM usuarios 
        WHERE curp = ? OR correo_institucional = ? OR (nombre = ? AND apellido_paterno = ? AND apellido_materno = ?)
        LIMIT 1
    ");
    $stmtCheck->bind_param("sssss", $curp, $correo, $nombre, $apellido_paterno, $apellido_materno);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        die("Ya existe esa cuenta, por favor inicia sesión con tus credenciales.");
    }

    $stmtCheck->close();

    // ============================
    // INSERTAR USUARIO
    // ============================
    $stmt = $conn->prepare("
        INSERT INTO usuarios 
        (curp, correo_institucional, fecha_nacimiento, password, nombre, apellido_paterno, apellido_materno, id_genero, telefono, estado_usuario, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'espera', NOW())
    ");

    $stmt->bind_param("sssssssss", 
        $curp, 
        $correo, 
        $fecha_nacimiento, 
        $password_hash, 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $id_genero, 
        $telefono
    );   

    if ($stmt->execute()) {

        // ID del usuario recién registrado
        $idUsuario = $conn->insert_id;
        $_SESSION['id_usuario'] = $idUsuario;

        // ============================
        // CONFIGURACIONES POR DEFECTO
        // ============================
        $stmtConfig = $conn->prepare("
            INSERT INTO configuraciones
            (
                id_usuarios,
                localidad,
                fecha_nacimiento,
                institucion_academica,
                notif_todas,
                notif_tareas_nuevas,
                notif_tareas_atrasadas,
                notif_modificaciones_proyecto,
                notif_admin_proyecto,
                priv_ver_tareas,
                priv_ver_proyectos,
                priv_ver_datos
            )
            VALUES (?, NULL, ?, 'TECNM Felipe Carrillo Puerto', 
                1, 1, 1, 1, 1,
                1, 1, 1
            )
        ");

        $stmtConfig->bind_param("is", 
            $idUsuario,
            $fecha_nacimiento
        );

        $stmtConfig->execute();

        // Redireccionar a crear perfil
        header("Location: /ITSFCP-PROYECTOS/Vistas/usuarios/crear_perfil.php");
        exit;

    } else {
        echo "Error al registrar usuario: " . $conn->error;
    }
}
?>