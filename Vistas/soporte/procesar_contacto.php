<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    
    if (empty($nombre) || empty($correo) || empty($mensaje)) {
        $_SESSION['mensaje_error'] = 'Todos los campos son obligatorios';
        header("Location: /ITSFCP-PROYECTOS/Vistas/soporte/soporte.php");
        exit;
    }

    // FALTA VALIDAR FORMATO DE CORREO ELECTRÓNICO
    
    $_SESSION['mensaje_exito'] = 'Tu mensaje ha sido enviado correctamente. Te responderemos pronto.';
    header("Location: /ITSFCP-PROYECTOS/Vistas/soporte/soporte.php");
    exit;
}

header("Location: /ITSFCP-PROYECTOS/Vistas/soporte/soporte.php");
exit;
?>