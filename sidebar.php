<?php
if (!isset($_SESSION)) session_start();

$rol = strtolower($_SESSION["rol"] ?? "");

// Función para crear links según nombre y rol
function crearLink($nombre, $rol)
{
    $links = [

        // PRINCIPALES
        "Principal" => "/Modules/Principal/Views/index.php",
        "Dashboard" => "/Modules/Dashboard/Views/index.php",

        // PROYECTOS
        "Seguimiento" => "/Modules/Proyectos/Views/index.php",

        // SOLICITUDES
        "Integracion a proyecto" => "/Modules/Solicitudes_integracion_proyecto/Views/index.php",
        "Proyecto" => "/Modules/Solicitudes_proyecto/Views/index.php",
        "Actualizar datos" => "/Modules/Solicitudes_actualizacion/Views/index.php",
        "Carta de terminación" => "/Modules/Solicitudes_carta_terminacion/Views/index.php",

        // PROFESOR
        "Mis alumnos" => "/Modules/Mis_alumnos/Views/index.php",

        // ESTUDIANTE
        "Mis solicitudes" => "/Modules/Mis_solicitudes/Views/index.php",

        // GENERALES
        "Calendario" => "/Modules/Calendario/Views/index.php",

        // SUPERVISOR
        "Panel Supervisor" => "/Modules/Supervisor/Views/panel_supervisor.php",

        // CATÁLOGOS
        "Usuarios" => "/Modules/Usuarios/Views/index.php",
        "Línea de investigación" => "/Modules/Linea_investigacion/Views/index.php",
        "Temática" => "/Modules/Tematica/Views/index.php",
        "Área de conocimiento" => "/Modules/Areas_conocimiento/Views/index.php",
        "Ajuste de documentos" => "/Modules/Tipos_documentos/Views/index.php",
        "Plantillas de documentos" => "/Modules/Plantillas_documentos/Views/index.php",
        "Período" => "/Modules/Periodo/Views/index.php",
        "Instituto" => "/Modules/Instituto/Views/index.php",
        "Director" => "/Modules/Director/Views/index.php",
        "Carreras" => "/Modules/Carreras/Views/index.php",
        "Niveles SNI" => "/Modules/Niveles_SNI/Views/index.php",
        "Grados académicos" => "/Modules/Grados_academicos/Views/index.php",

        // CONFIGURACIÓN
        "Grado académico" => "/Modules/Configuracion/Views/grado_academico.php",
        "Nivel SNI" => "/Modules/Configuracion/Views/nivel_sni.php",

        // SISTEMA
        "Soporte" => "/Modules/soporte/Views/soporte.php",
        "Ajustes" => "/Modules/Ajustes/Views/index.php",
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
    $submenuSolicitudesProyecto = ["Integracion a proyecto", "Proyecto"];
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
            <img src="/public/icons/sidebar.svg" alt="Toggle">
        </button>

        <!-- MENÚ PRINCIPAL -->
        <?php foreach ($mainMenu as $item): ?>
            <?php $link = crearLink($item, $rol); ?>
            <a class="menu-item <?= isActive($link, $current_url) ?>" href="<?= $link ?>" data-tooltip="<?= $item ?>">
                <span class="menu-icon">
                    <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
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
                    <img src="/public/icons/Proyectos.svg" alt="Proyectos">
                </span>
                <span>Proyectos</span>
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $proyectosActive ? 'open' : '' ?>" id="submenuProyectos">
                <?php foreach ($submenuProyectos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
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
                    <img src="/public/icons/mis_alumnos.svg" alt="Mis alumnos">
                </span>
                <span>Mis alumnos</span>
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $alumnosActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuMisAlumnos as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/public/icons/solicitudes_.svg" alt="Solicitudes">
                </span>
                <span>Solicitudes</span>
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $solicitudProyectoActive ? 'open' : '' ?>" id="subsolicitudesProyecto">
                <?php foreach ($submenuSolicitudesProyecto as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/public/icons/configuracion.svg" alt="Configuración">
                </span>
                <span>Configuración</span>
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $configuracionActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuConfiguracion as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/public/icons/solicitudes_.svg" alt="Solicitudes">
                </span>
                <span>Solicitudes</span>
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $solicitudActive ? 'open' : '' ?>" id="submenuMisAlumnos">
                <?php foreach ($submenuSolicitudes as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                <img class="dropdown-arrow" src="/public/icons/more.svg" alt="Expandir">
            </div>
            <div class="submenu <?= $verMasActive ? 'open' : '' ?>" id="submenuVerMas">
                <?php foreach ($submenuVerMas as $sub): ?>
                    <?php $subLink = crearLink($sub, $rol); ?>
                    <a class="menu-item sub-item <?= isActive($subLink, $current_url) ?>" href="<?= $subLink ?>" data-tooltip="<?= $sub ?>" data-id="<?= strtolower(str_replace(" ", "_", $sub)) ?>">
                        <span class="menu-icon">
                            <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $sub)) ?>.svg" alt="<?= $sub ?>">
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
                    <img src="/public/icons/<?= strtolower(str_replace(" ", "_", $item)) ?>.svg" alt="<?= $item ?>">
                </span>
                <span><?= $item ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>