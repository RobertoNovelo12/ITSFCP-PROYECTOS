<?php
if (!isset($_SESSION)) session_start();

// Usuario logeado
$usuario_logeado = isset($_SESSION['id_usuario']);
$nombre = $_SESSION['nombre'] ?? '';
$inicial = $nombre !== "" ? strtoupper(substr($nombre, 0, 1)) : "";

// Variables por defecto
$notificaciones = [];
$no_leidas = 0;

if ($usuario_logeado) {
    // Conexión correcta desde cualquier página
    require_once __DIR__ . '/../config/conexion.php';

    // Recuperar últimas 5 notificaciones
    $stmt = $conn->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        if ($fila['leido'] == 0) $no_leidas++;
        $notificaciones[] = $fila;
    }
}
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
            <a class="login-btn" href="/ITSFCP-PROYECTOS/login.php">Iniciar sesión</a>
        <?php else: ?>

            <!-- ICONO DE NOTIFICACIONES -->
            <div class="notif-wrapper">
                <div class="notif-icon" id="notifBtn">
                    <img src="/ITSFCP-PROYECTOS/publico/icons/notificacion.svg" alt="Notificaciones">
                    <?php if ($no_leidas > 0): ?>
                        <span class="notif-count" id="notifCount"><?= $no_leidas ?></span>
                    <?php endif; ?>
                </div>

                <div class="notif-dropdown" id="notifDropdown">
                    <?php if (empty($notificaciones)): ?>
                        <p class="notif-empty">No tienes notificaciones</p>
                    <?php else: ?>
                        <?php foreach ($notificaciones as $notif): ?>
                            <a href="<?= $notif['enlace'] ?>" class="notif-item <?= $notif['leido'] == 0 ? 'unread' : '' ?>">
                                <strong><?= htmlspecialchars($notif['titulo']) ?></strong>
                                <span><?= htmlspecialchars($notif['contenido']) ?></span>
                                <small><?= date('d/m/Y H:i', strtotime($notif['creado_en'])) ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PERFIL -->
            <div class="profile-wrapper" id="userProfileBtn">
                <?php if (!empty($_SESSION['foto_url'])): ?>
                    <img class="avatar-img" src="/ITSFCP-PROYECTOS/img/avatars/<?= $_SESSION['foto_url'] ?>" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-initial"><?= $inicial ?></div>
                <?php endif; ?>
                <img class="avatar-arrow" src="/ITSFCP-PROYECTOS/publico/icons/caretaa.svg" alt="Menú">
            </div>

            <!-- MENÚ DESPLEGABLE -->
            <div class="profile-dropdown" id="profileDropdown">
                <a href="/ITSFCP-PROYECTOS/logout.php" class="logout-btn">Cerrar sesión</a>
            </div>

        <?php endif; ?>

        <!-- Botón hamburguesa móvil -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- JS para dropdown y marcar notificaciones como leídas -->
<script>
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
const notifCount = document.getElementById('notifCount');

notifBtn.addEventListener('click', () => {
    // Toggle dropdown
    notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';

    // Si ya está cargado, no hacer nada
    if (notifDropdown.dataset.loaded) return;

    // AJAX para marcar como leídas y actualizar dropdown
    fetch('/ITSFCP-PROYECTOS/Vistas/Notificaciones/lista.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                notifDropdown.innerHTML = '';
                if (data.notificaciones.length === 0) {
                    notifDropdown.innerHTML = '<p class="notif-empty">No tienes notificaciones</p>';
                } else {
                    data.notificaciones.forEach(n => {
                        const a = document.createElement('a');
                        a.href = n.enlace;
                        a.className = 'notif-item';
                        a.innerHTML = `<strong>${n.titulo}</strong><span>${n.contenido}</span><small>${new Date(n.creado_en).toLocaleString()}</small>`;
                        notifDropdown.appendChild(a);
                    });
                }

                // Quitar contador
                if (notifCount) notifCount.remove();
                notifDropdown.dataset.loaded = true;
            }
        });
});

// Cerrar dropdown si clic afuera
document.addEventListener('click', e => {
    if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.style.display = 'none';
    }
});
</script>
