<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']); //trim sirvev para eliminar los espacios de delanta y atras 😏
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $curp = trim($_POST['curp']);
    $correo = trim($_POST['correo']);
    $dia = (int)$_POST['dia'];
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];
    $genero = trim($_POST['genero']);
    $estado = trim($_POST['estado']);
    $password = trim($_POST['contraseña']);
    $confirmar = trim($_POST['confirmar']);

    //validar contraseñas
    if ($password !== $confirmar) {
        die("Las contraseñas no coinciden.");
    }

    // generar fecha de nacimiento
    $fecha_nacimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

    //hashear contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // isnertar usuario
    $stmt = $conn->prepare("INSERT INTO usuarios 
        (curp, correo_institucional, fecha_nacimiento, password_hash, nombre, apellido_paterno, apellido_materno, genero, estado_usuario, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");

    $stmt->bind_param("ssssssss", $curp, $correo, $fecha_nacimiento, $password_hash, $nombre, $apellido_paterno, $apellido_materno, $genero);

    if ($stmt->execute()) {
        $id_usuario = $conn->insert_id;

        // Crear sesion
        $_SESSION['id_usuario'] = $id_usuario;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['correo'] = $correo;

        // Redirigir a creación de perfil
        header("Location: /ITSFCP-PROYECTOS/vistas/usuarios/crear_perfil.php");
        exit;
    } else {
        echo "Error al registrar usuario: " . $conn->error;
    }
}
?>