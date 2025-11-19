<?php
if (!isset($_SESSION)) session_start();

$rol = strtolower($_SESSION["rol"] ?? "");

// LUIS POR FAVOR CREA LOS ARCHIVOS CORRESPONDIENTES A CADA LINK
function crearLink($nombre, $rol) {
    $links = [
        "Principal" => "/ITSFCP-PROYECTOS/Vistas/menu/principal.php",
        "Dashboard" => "/ITSFCP-PROYECTOS/Vistas/Dashboard/dashboard.php",
        "Seguimiento" => "/ITSFCP-PROYECTOS/Vistas/Proyectos/tabla.php",
        "Tareas" => "/ITSFCP-PROYECTOS/Vistas/Tareas/tabla.php",
        "Solicitudes" => "/ITSFCP-PROYECTOS/Vistas/Constancias/tabla.php",
        "Documentos" => "/ITSFCP-PROYECTOS/Vistas/menu/documentos.php",
        "Plan de trabajo" => "/ITSFCP-PROYECTOS/Vistas/menu/plan_de_trabajo.php",
        "Constancias" => "/ITSFCP-PROYECTOS/Vistas/Constancias/constancias.php",
        "Calendario" => "/ITSFCP-PROYECTOS/Vistas/Calendario/calendario.php",
        "Reportes" => "/ITSFCP-PROYECTOS/Vistas/Periodo/reportes.php",
        "Mis alumnos" => "/ITSFCP-PROYECTOS/Vistas/usuarios/alumno.php",
        "Usuarios" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Línea de investigación" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Temática" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Subtemática" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Área de conocimiento" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Subárea de conocimiento" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Ajuste de constancias" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Período" => "/ITSFCP-PROYECTOS/Vistas/Periodo/tabla.php",
        "Instituto" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Carreras" => "/ITSFCP-PROYECTOS/Vistas/usuarios/usuario.php",
        "Soporte" => "#",
        "Ajustes" => "#"
    ];

    return $links[$nombre] ?? "#";
}

/* ====================================================
   CONFIGURACIÓN DE MENÚS POR ROL
   ==================================================== */
$mainMenu = $middleMenu = $submenuProyectos = $submenuMisAlumnos = $submenuVerMas = $footerMenus = [];

if ($rol === "estudiante") {
    $mainMenu = ["Principal","Dashboard"];
    $submenuProyectos = ["Seguimiento","Tareas"];
    $submenuVerMas = ["Solicitudes","Documentos","Plan de trabajo","Constancias"];
    $middleMenu = ["Calendario"];
    $footerMenus = ["Soporte","Ajustes"];
} elseif ($rol === "profesor" || $rol === "investigador") {
    $mainMenu = ["Principal","Dashboard"];
    $submenuProyectos = ["Seguimiento","Tareas"];
    $submenuMisAlumnos = ["Solicitudes","Documentos","Plan de trabajo","Constancias"];
    $middleMenu = ["Calendario","Reportes"];
    $footerMenus = ["Soporte","Ajustes"];
} elseif ($rol === "supervisor") {
    $mainMenu = ["Principal","Dashboard"];
    $submenuProyectos = ["Seguimiento","Tareas"];
    $middleMenu = ["Calendario","Reportes"];
    $submenuVerMas = ["Usuarios","Línea de investigación","Temática","Subtemática","Área de conocimiento","Subárea de conocimiento","Ajuste de constancias","Período","Instituto","Carreras"];
    $footerMenus = ["Soporte","Ajustes"];
}

/* ====================================================
   OBTENER URL ACTUAL PARA ACTIVE
   ==================================================== */
$current_url = $_SERVER['REQUEST_URI'];

function isActive($link, $current_url) {
    return rtrim($current_url, "/") === rtrim($link, "/") ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="sidebar-main">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <img src="/ITSFCP-PROYECTOS/publico/icons/sidebar.svg" alt="Toggle">
        </button>

        <!-- MENÚ PRINCIPAL -->
        <?php foreach ($mainMenu as $item): ?>
            <?php $link = crearLink($item, $rol); ?>
            <a class="menu-item <?= isActive($link, $current_url) ?>" href="<?= $link ?>" data-tooltip="<?= $item ?>">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>

        <!-- SUBMENÚ PROYECTOS -->
        <?php if ($submenuProyectos): ?>
            <div class="menu-item dropdown-btn <?= array_filter($submenuProyectos, fn($sub) => isActive(crearLink($sub, $rol), $current_url)) ? 'active' : '' ?>" id="btnProyectos" data-tooltip="Proyectos">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/proyectos.svg" alt="Proyectos">
                </span>
                <span>Proyectos</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu" id="submenuProyectos">
                <?php foreach ($submenuProyectos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- MIDDLE MENUS -->
        <?php foreach ($middleMenu as $item): ?>
            <?php $link = crearLink($item, $rol); ?>
            <a class="menu-item <?= isActive($link, $current_url) ?>" href="<?= $link ?>" data-tooltip="<?= $item ?>">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>

        <!-- SUBMENÚ MIS ALUMNOS -->
        <?php if ($submenuMisAlumnos): ?>
            <div class="menu-item dropdown-btn <?= array_filter($submenuMisAlumnos, fn($sub) => isActive(crearLink($sub, $rol), $current_url)) ? 'active' : '' ?>" id="btnMisAlumnos" data-tooltip="Mis alumnos">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/mis_alumnos.svg" alt="Mis alumnos">
                </span>
                <span>Mis alumnos</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu" id="submenuMisAlumnos">
                <?php foreach ($submenuMisAlumnos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SUBMENÚ VER MÁS -->
        <?php if ($submenuVerMas): ?>
            <div class="menu-item dropdown-btn <?= array_filter($submenuVerMas, fn($sub) => isActive(crearLink($sub, $rol), $current_url)) ? 'active' : '' ?>" id="btnVerMas" data-tooltip="Ver más">
                <span class="menu-icon">-</span>
                <span>Ver más</span>
                <img class="dropdown-arrow" src="/ITSFCP-PROYECTOS/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu" id="submenuVerMas">
                <?php foreach ($submenuVerMas as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>">
                        <span class="menu-icon">
                            <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <div class="menu-footer">
        <?php foreach ($footerMenus as $item): ?>
            <?php $link = crearLink($item, $rol); ?>
            <a class="menu-item <?= isActive($link, $current_url) ?>" href="<?= $link ?>" data-tooltip="<?= $item ?>">
                <span class="menu-icon">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>