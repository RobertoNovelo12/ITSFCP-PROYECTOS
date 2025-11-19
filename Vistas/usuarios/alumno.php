<?php
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['id_usuario']) || strtolower($_SESSION['rol']) !== 'estudiante') {
    header("Location: /ITSFCP-PROYECTOS/login.php");
    exit;
}

// Contenido específico de la página
$contenido = <<<HTML
<div class="main-content">
    <h1 class="title">Panel del Estudiante</h1>
    <p>Bienvenido, {$_SESSION['nombre']}.</p>
</div>
HTML;

// Incluir layout
include __DIR__ . '/../../layout.php';
?>