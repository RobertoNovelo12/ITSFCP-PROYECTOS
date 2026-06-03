<?php
if (!isset($_SESSION)) session_start();

$rol = strtolower($_SESSION["rol"] ?? "");

// Función para crear links según nombre y rol
function crearLink($nombre, $rol)
{
    $links = [
        "Principal" => "/Vistas/Principal/index.php",
        "Dashboard" => "/Vistas/Dashboard/index.php",
        "Seguimiento" => "/Vistas/Proyectos/index.php",
        "Integracion a proyecto" => "/Vistas/Solicitudes_integracion_proyecto/index.php",
        "Proyecto" => "/Vistas/Solicitudes_proyecto/index.php",
        "Actualizar datos" => "/Vistas/Solicitudes_actualizacion/index.php",
        "Carta de terminación" => "/Vistas/Solicitudes_carta_terminacion/index.php",
        "Mis alumnos" => "/Vistas/Mis_alumnos/index.php",
        "Mis solicitudes" => "/Vistas/Mis_solicitudes/index.php",
        "Calendario" => "/Vistas/Calendario/index.php",
        //"Reportes" => "/Vistas/Periodo/reportes.php",
        "Usuarios" => "/Vistas/Usuarios/index.php",
        "Panel Supervisor" => "/Vistas/Supervisor/panel_supervisor.php",
        "Línea de investigación" => "/Vistas/Linea_investigacion/index.php",
        "Temática" => "/Vistas/Tematica/index.php",
        "Área de conocimiento" => "/Vistas/Areas_conocimiento/index.php",
        "Ajuste de documentos" => "/Vistas/Tipos_documentos/index.php",
        "Plantillas de documentos" => "/Vistas/Plantillas_documentos/index.php",
        "Período" => "/Vistas/Periodo/index.php",
        "Instituto" => "/Vistas/Instituto/index.php",
        "Director" => "/Vistas/Director/index.php",
        "Carreras" => "/Vistas/Carreras/index.php",
        "Niveles SNI" => "/Vistas/Niveles_SNI/index.php",
        "Grados académicos" => "/Vistas/Grados_academicos/index.php",
        "Grado académico" => "/Vistas/Configuracion/grado_academico.php",
        "Nivel SNI" => "/Vistas/Configuracion/nivel_sni.php",
        "Soporte" => "/Vistas/soporte/soporte.php",
        "Ajustes" => "/Vistas/Ajustes/index.php",
    ];

    return $links[$nombre] ?? "#";
}

// Menús por rol
$mainMenu = $middleMenu = $submenuProyectos = $submenuMisAlumnos = $submenuConfiguracion = $submenuSolicitudesProyecto = $submenuSolicitudes = $submenuVerMas = $footerMenus = [];

if ($rol === "estudiante" ||  $rol === "alumno") {
    $mainMenu = ["Principal", "Dashboard"];
    $submenuProyectos = ["Seguimiento"];
    $submenuVerMas = ["Mis solicitudes"];
    $middleMenu = ["Calendario"];
    $footerMenus = ["Soporte", "Ajustes"];
} elseif ($rol === "profesor" || $rol === "investigador") {
    $mainMenu = ["Principal", "Dashboard"];
    $submenuProyectos = ["Seguimiento"];
    $submenuMisAlumnos = ["Mis alumnos"];
    $submenuSolicitudesProyecto = ["Integracion a proyecto"];
    $submenuConfiguracion = ["Grado académico", "Nivel SNI"];
    $middleMenu = ["Calendario"];
    $footerMenus = ["Soporte", "Ajustes"];
} elseif ($rol === "supervisor") {
    $mainMenu = ["Principal", "Dashboard", "Panel Supervisor"];
    $submenuProyectos = ["Seguimiento"];
    $submenuSolicitudes = ["Proyecto", "Actualizar datos", "Carta de terminación"];
    $middleMenu = ["Calendario"];
    $submenuVerMas = ["Usuarios", "Línea de investigación", "Temática", "Área de conocimiento", "Ajuste de documentos", "Plantillas de documentos", "Niveles SNI", "Período", "Grados académicos", "Director", "Carreras", "Instituto"];
    $footerMenus = ["Soporte", "Ajustes"];
}

// URL actual
$current_url = $_SERVER['REQUEST_URI'];

function isActive($link, $current_url)
{
    return rtrim($current_url, "/") === rtrim($link, "/") ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="sidebar-main">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <img src="/publico/icons/sidebar.svg" alt="Toggle">
        </button>

        <!-- MENÚ PRINCIPAL -->
        <?php foreach ($mainMenu as $item): ?>
            <?php $link = crearLink($item, $rol); ?>
            <a class="menu-item <?= isActive($link, $current_url) ?>" href="<?= $link ?>" data-tooltip="<?= $item ?>">
                <span class="menu-icon">
                    <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>

        <!-- SUBMENÚ PROYECTOS -->
        <?php if ($submenuProyectos): ?>
            <?php
            $proyectosActive = array_filter($submenuProyectos, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $proyectosActive ? 'dropdown-open' : '' ?>"
                id="btnProyectos" data-tooltip="Proyectos" data-id="proyectos">
                <span class="menu-icon">
                    <img src="/publico/icons/Proyectos.svg" alt="Proyectos">
                </span>
                <span>Proyectos</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $proyectosActive ? 'open' : '' ?>" id="submenuProyectos">
                <?php foreach ($submenuProyectos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>

        <!-- SUBMENÚ MIS ALUMNOS -->
        <?php if ($submenuMisAlumnos): ?>
            <?php
            $alumnosActive = array_filter($submenuMisAlumnos, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $alumnosActive ? 'dropdown-open' : '' ?>"
                id="btnMisAlumnos" data-tooltip="Mis alumnos" data-id="misAlumnos">
                <span class="menu-icon">
                    <img src="/publico/icons/mis_alumnos.svg" alt="Mis alumnos">
                </span>
                <span>Mis alumnos</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $alumnosActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuMisAlumnos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SUBMENÚ SOLICITUDES DE INTEGRACIÓN DE PROYECTO -->
        <?php if ($submenuSolicitudesProyecto): ?>
            <?php
            $solicitudProyectoActive = array_filter($submenuSolicitudesProyecto, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $solicitudProyectoActive ? 'dropdown-open' : '' ?>"
                id="btnSolicitudes" data-tooltip="Solicitudes" data-id="solicitudes">
                <span class="menu-icon">
                    <img src="/publico/icons/solicitudes_.svg" alt="Solicitudes">
                </span>
                <span>Solicitudes</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $solicitudProyectoActive ? 'open' : '' ?>" id="subsolicitudesProyecto">
                <?php foreach ($submenuSolicitudesProyecto as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SUBMENÚ CONFIGURACION -->
        <?php if ($submenuConfiguracion): ?>
            <?php
            $configuracionActive = array_filter($submenuConfiguracion, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $configuracionActive ? 'dropdown-open' : '' ?>"
                id="btnConfiguracion" data-tooltip="Configuración" data-id="configuracion">
                <span class="menu-icon">
                    <img src="/publico/icons/configuracion.svg" alt="Configuración">
                </span>
                <span>Configuración</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $configuracionActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuConfiguracion as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

                <!-- SUBMENÚ Solicitud -->
        <?php if ($submenuSolicitudes): ?>
            <?php
            $solicitudActive = array_filter($submenuSolicitudes, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $solicitudActive ? 'dropdown-open' : '' ?>"
                id="btnSolicitudes" data-tooltip="Solicitudes" data-id="solicitudes">
                <span class="menu-icon">
                    <img src="/publico/icons/solicitudes_.svg" alt="Solicitudes">
                </span>
                <span>Solicitudes</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $solicitudActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuSolicitudes as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
                        </span>
                        <span><?= $sub ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SUBMENÚ VER MÁS -->
        <?php if ($submenuVerMas): ?>
            <?php
            $verMasActive = array_filter($submenuVerMas, fn($sub) => isActive(crearLink($sub, $rol), $current_url));
            ?>
            <div class="menu-item dropdown-btn <?= $verMasActive ? 'dropdown-open' : '' ?>"
                id="btnVerMas" data-tooltip="Ver más" data-id="verMas">
                <span class="menu-icon">-</span>
                <span>Ver más</span>
                <img class="dropdown-arrow" src="/publico/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $verMasActive ? 'open' : '' ?>" id="submenuVerMas">
                <?php foreach ($submenuVerMas as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/publico/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>