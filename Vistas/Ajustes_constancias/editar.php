<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);


ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>


<?php

$contenido = ob_get_clean();
$titulo = "Crear Línea de investigación";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>