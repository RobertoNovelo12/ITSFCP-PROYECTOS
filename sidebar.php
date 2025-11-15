<?php
if (!isset($_SESSION)) session_start();

$rol = strtolower($_SESSION["rol"] ?? "");

if ($rol === "alumno") {

    // Botones principales
    $mainMenu = [
        "Principal",
        "Dashboard",
    ];

    // Submenús
    $submenuProyectos = ["Seguimiento", "Tareas"];

    $submenuVerMas = [
        "Solicitudes",
        "Documentos",
        "Plan de trabajo",
        "Constancias"
    ];

    // Botones adicionales luego de proyectos
    $middleMenu = ["Calendario"];

    // Footer fijo
    $footerMenus = ["Soporte", "Ajustes"];


} elseif ($rol === "profesor" || $rol === "investigador") {

    // Botones principales
    $mainMenu = [
        "Principal",
        "Dashboard",
    ];

    // Submenús
    $submenuProyectos = ["Seguimiento", "Tareas"];
    $submenuMisAlumnos = [
        "Solicitudes",
        "Documentos",
        "Plan de trabajo",
        "Constancias"
    ];

    // Botones después de proyectos
    $middleMenu = ["Calendario", "Reportes"];

    // Footer fijo
    $footerMenus = ["Soporte", "Ajustes"];


} elseif ($rol === "supervisor") {

    $mainMenu = [
        "Principal",
        "Dashboard",
    ];

    $submenuProyectos = ["Seguimiento", "Tareas"];

    $middleMenu = [
        "Calendario",
        "Reportes"
    ];

    $submenuVerMas = [
        "Usuarios",
        "Línea de investigación",
        "Temática",
        "Subtemática",
        "Área de conocimiento",
        "Subárea de conocimiento",
        "Ajuste de constancias",
        "Período",
        "Instituto",
        "Carreras"
    ];

    $footerMenus = ["Soporte", "Ajustes"];
}
?>

<div class="sidebar">
    <!-- CONTENIDO PRINCIPAL DEL MENÚ -->
    <div class="sidebar-main">
        <!-- MENÚ PRINCIPAL (primero siempre) -->
        <?php foreach ($mainMenu as $item): ?>
            <div class="menu-item">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg">
                </span>
                <span><?= $item ?></span>
            </div>
        <?php endforeach; ?>

        <!-- SUBMENÚ PROYECTOS -->
        <?php if (isset($submenuProyectos)): ?>
            <div class="menu-item dropdown-btn" id="btnProyectos">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/proyectos.svg">
                </span>
                <span>Proyectos</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg">
            </div>

            <div class="submenu" id="submenuProyectos">
                <?php foreach ($submenuProyectos as $sub): ?>
                    <div class="menu-item sub-item">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg">
                        </span>
                        <span><?= $sub ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- MENÚS INTERMEDIOS -->
        <?php if (isset($middleMenu)):
            foreach ($middleMenu as $item): ?>
                <div class="menu-item">
                    <span class="menu-icon">
                        <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg">
                    </span>
                    <span><?= $item ?></span>
                </div>
            <?php endforeach;
        endif; ?>

        <!-- SUBMENÚ MIS ALUMNOS -->
        <?php if (isset($submenuMisAlumnos)): ?>
            <div class="menu-item dropdown-btn" id="btnMisAlumnos">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/mis_alumnos.svg">
                </span>
                <span>Mis alumnos</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg">
            </div>

            <div class="submenu" id="submenuMisAlumnos">
                <?php foreach ($submenuMisAlumnos as $sub): ?>
                    <div class="menu-item sub-item">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg">
                        </span>
                        <span><?= $sub ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- VER MÁS -->
        <?php if (isset($submenuVerMas)): ?>
            <div class="menu-item dropdown-btn" id="btnVerMas">
                <span class="menu-icon">-</span>
                <span>Ver más</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg">
            </div>

            <div class="submenu" id="submenuVerMas">
                <?php foreach ($submenuVerMas as $sub): ?>
                    <div class="menu-item sub-item">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg">
                        </span>
                        <span><?= $sub ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER AL FINAL -->
    <div class="menu-footer">
        <?php if (isset($footerMenus)):
            foreach ($footerMenus as $item): ?>
                <div class="menu-item">
                    <span class="menu-icon">
                        <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg">
                    </span>
                    <span><?= $item ?></span>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>