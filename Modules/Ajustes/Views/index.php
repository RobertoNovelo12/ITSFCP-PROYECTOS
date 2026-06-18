<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../Controller/ajustes_controller.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /login.php");
    exit;
}

$titulo = "Ajustes";

$ctrl  = new AjustesControlador($conn, intval($_SESSION['id_usuario']));
$datos = $ctrl->getDatos();

ob_start();
include __DIR__ . '/vista.php';
$contenido = ob_get_clean();

include __DIR__ . '/../../../layout.php';