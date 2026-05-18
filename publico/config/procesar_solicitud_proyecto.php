<?php
//  Única responsabilidad: recibir el POST del formulario, delegar al
//  controlador y redirigir (patrón PRG).
//  Todo el negocio vive en solicitudesControlador::enviarSolicitud().
// ════════════════════════════════════════════════════════════════════════════
session_start();
 
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
 
$id_usuario  = (int)$_SESSION['id_usuario'];         
$rol         = strtolower($_SESSION['rol'] ?? '');
$id_proyecto = (int)($_POST['id_proyecto'] ?? 0);
 
if ($id_proyecto <= 0) {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}
 
require_once __DIR__ . '/../../Controladores/solicitudesControlador.php';
 
$ctrl = new solicitudesControlador();
$ctrl->enviarSolicitud($id_proyecto, $id_usuario, $rol);
// enviarSolicitud() hace su propio header() + exit() internamente (PRG)
 
?>