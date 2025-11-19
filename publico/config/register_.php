<?php
session_start();
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $curp = trim($_POST['curp']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $dia = (int)$_POST['dia'];
    $mes = (int)$_POST['mes'];
    $anio = (int)$_POST['anio'];
    $id_genero = intval($_POST['genero']);
    $password = trim($_POST['contraseña']);
    $confirmar = trim($_POST['confirmar']);

    if ($password !== $confirmar) {
        die("Las contraseñas no coinciden.");
    }

    $fecha_nacimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO usuarios 
        (curp, correo_institucional, fecha_nacimiento, password, nombre, apellido_paterno, apellido_materno, id_genero, telefono, estado_usuario, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'espera', NOW())");

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
        $_SESSION['id_usuario'] = $conn->insert_id;
        header("Location: /ITSFCP-PROYECTOS/Vistas/usuarios/crear_perfil.php");
        exit;
    } else {
        echo "Error al registrar usuario: " . $conn->error;
    }
}
?>