<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";
require_once __DIR__ . "/../../Controladores/DashboardControlador.php";

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$rol         = strtolower($_SESSION['rol'] ?? '');
$id_usuario  = intval($_SESSION['id_usuario']);
$nombre_user = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario');

$controlador = new DashboardControlador($conn, $rol, $id_usuario);
$datos       = $controlador->getDatos();

// Captura el HTML de la vista en $contenido
ob_start();
include __DIR__ . "/vista.php";
$contenido = ob_get_clean();

// El layout usa $contenido para renderizar con menú y CSS
include __DIR__ . "/../../layout.php";