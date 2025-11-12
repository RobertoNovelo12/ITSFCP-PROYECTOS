<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar y recoger datos del formulario
    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $curp = trim($_POST['curp']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $dia = (int)$_POST['dia'];
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];
    $genero = trim($_POST['genero']);
    $password = trim($_POST['contraseña']);
    $confirmar = trim($_POST['confirmar']);

    // Validar contraseñas
    if ($password !== $confirmar) {
        die("❌ Las contraseñas no coinciden.");
    }

    // Generar fecha de nacimiento (YYYY-MM-DD)
    $fecha_nacimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

    // Hashear contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar nuevo usuario con estado_usuario = 0 (NO ACEPTADO)
    $stmt = $conn->prepare("INSERT INTO usuarios 
        (curp, correo_institucional, fecha_nacimiento, password_hash, nombre, apellido_paterno, apellido_materno, genero, telefono, estado_usuario, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");

    $stmt->bind_param("sssssssss", $curp, $correo, $fecha_nacimiento, $password_hash, $nombre, $apellido_paterno, $apellido_materno, $genero, $telefono);

    if ($stmt->execute()) {
        $id_usuario = $conn->insert_id;

        // Crear sesión
        $_SESSION['id_usuario'] = $id_usuario;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['correo'] = $correo;
        $_SESSION['estado_usuario'] = 0; // no aceptado aún

        // Redirigir a creación de perfil
        header("Location: /ITSFCP-PROYECTOS/Vistas/usuarios/crear_perfil.php");
        exit;
    } else {
        echo "❌ Error al registrar usuario: " . $conn->error;
    }
}
?>