<?php
if (!isset($_SESSION)) {
    session_start();
}

$usuario_logeado = isset($_SESSION['id_usuario']);

$nombre = $usuario_logeado && !empty($_SESSION['nombre'])
    ? $_SESSION['nombre']
    : "";

$inicial = $nombre !== "" ? strtoupper(substr($nombre, 0, 1)) : "";
?>

<div class="header">
    <div class="logo">ITSFCP/PROYECTOS</div>

    <div class="header-right">

        <input type="text" class="search-box" placeholder="Buscar">

        <!-- BOTÓN DE MODO OSCURO -->
        <button id="darkModeToggle" class="dark-mode-btn" aria-label="Cambiar modo oscuro">
            <i class="bi bi-sun"></i>
        </button>

        <?php if (!$usuario_logeado): ?>
            <!-- Usuario NO logeado -->
            <a class="login-btn" href="/ITSFCP-PROYECTOS/login.php">Iniciar sesión</a>

        <?php else: ?>

            <!-- ICONO DE NOTIFICACIONES -->
            <div class="notif-icon">
                <img src="/ITSFCP-PROYECTOS/publico/icons/notificacion.svg" alt="Notificaciones">
            </div>

            <!-- CONTENEDOR DE PERFIL -->
            <div class="profile-wrapper" id="userProfileBtn">

                <?php if (!empty($_SESSION['foto_url'])): ?>
                    <img class="avatar-img" src="/ITSFCP-PROYECTOS/img/avatars/<?php echo $_SESSION['foto_url']; ?>" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?= $inicial ?>
                    </div>
                <?php endif; ?>

                <img class="avatar-arrow" src="/ITSFCP-PROYECTOS/publico/icons/caretaa.svg" alt="Menú">
            </div>

            <!-- MENÚ DESPLEGABLE -->
            <div class="profile-dropdown" id="profileDropdown">
                <a href="/ITSFCP-PROYECTOS/logout.php" class="logout-btn">Cerrar sesión</a>
            </div>

        <?php endif; ?>

        <!-- Botón hamburguesa para móvil (al final, a la derecha) -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
    </div>
</div>

<!-- Overlay para cerrar sidebar en móvil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>