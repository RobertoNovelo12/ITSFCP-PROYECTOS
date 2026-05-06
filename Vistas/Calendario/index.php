<?php
if (!isset($_SESSION)) session_start();

require_once __DIR__ . "/../../publico/config/conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$titulo        = "Calendario";
$necesitaQuill = true;

ob_start();
include __DIR__ . "/vista.php";
$contenido = ob_get_clean();

include __DIR__ . "/../../layout.php";