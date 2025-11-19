<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

$contenido = <<<HTML
<div class="main-content">
    <h1 class="title">Dashboard</h1>
    <p>Bienvenido, {$_SESSION['nombre']}.</p>
    <!-- Aquí puedes agregar tus widgets o estadísticas -->
</div>
HTML;

include __DIR__ . '/../../layout.php';
?>