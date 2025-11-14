<?php
if (!isset($_SESSION)) session_start();

$rol = strtolower($_SESSION["rol"] ?? "");

// SEGÚN EL ROL
$menus = [];

if ($rol === "alumno") {
    $menus = [
        "Principal", "Dashboard", "Proyectos", "Seguimiento", "Tareas",
        "Calendario", "Reportes", "Solicitudes", "Documentos",
        "Plan de trabajo", "Constancias", "Soporte", "Ajustes"
    ];
}

elseif ($rol === "profesor" || $rol === "investigador") {
    $menus = [
        "Principal", "Dashboard", "Proyectos", "Seguimiento", "Tareas",
        "Calendario", "Reportes", "Mis alumnos", "Solicitudes",
        "Documentos", "Plan de trabajo", "Constancias", "Soporte", "Ajustes"
    ];
}

elseif ($rol === "supervisor") {
    $menus = [
        "Principal", "Dashboard", "Proyectos", "Tareas", "Calendario",
        "Reportes", "Otros", "Usuarios", "Línea de investigación",
        "Temática", "Subtemática", "Área de conocimiento",
        "Subárea de conocimiento", "Ajuste de constancias",
        "Período", "Instituto", "Carreras", "Soporte", "Ajustes"
    ];
}
?>

<div class="sidebar">

    <?php foreach ($menus as $item): ?>
        <div class="menu-item">
            <span class="menu-icon">
                <!-- ICONOS -->
                <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(' ', '_', $item)) ?>.svg">
            </span>

            <span><?= $item ?></span>
        </div>
    <?php endforeach; ?>

</div>