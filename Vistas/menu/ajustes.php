<?php
if (!isset($_SESSION))
    session_start();

$titulo = "Ajustes";

// Recuperar datos del usuario desde la base de datos
require_once __DIR__ . '/../../publico/config/conexion.php';

$id_usuario = $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) {
    die("Error: no hay sesión activa.");
}

// ===============================
//  CONSULTAR USUARIO
// ===============================
$queryUsuario = "SELECT * FROM usuarios WHERE id_usuarios = ?";
$stmtUsuario = $conn->prepare($queryUsuario);
$stmtUsuario->bind_param("i", $id_usuario);
$stmtUsuario->execute();
$resultUsuario = $stmtUsuario->get_result();
$usuario = $resultUsuario->fetch_assoc();

// ===============================
//  CONSULTAR CONFIGURACIONES
// ===============================
$queryConfig = "SELECT * FROM configuraciones WHERE id_usuarios = ?";
$stmtConfig = $conn->prepare($queryConfig);
$stmtConfig->bind_param("i", $id_usuario);
$stmtConfig->execute();
$resultConfig = $stmtConfig->get_result();
$config = $resultConfig->fetch_assoc();

// ===============================
//  VALORES POR DEFECTO
// ===============================
$valores_por_defecto = [
    'localidad' => '',
    'fecha_nacimiento' => $usuario['fecha_nacimiento'] ?? NULL,
    'institucion_academica' => '',
    'notif_todas' => 0,
    'notif_tareas_nuevas' => 0,
    'notif_tareas_atrasadas' => 0,
    'notif_modificaciones_proyecto' => 0,
    'notif_admin_proyecto' => 0,
    'priv_ver_tareas' => 0,
    'priv_ver_proyectos' => 0,
    'priv_ver_datos' => 0
];

// MEZCLAR CONFIG BD + DEFAULTS
$config = array_merge($valores_por_defecto, $config ?: []);

// ===============================
//  PREPARAR FECHA
// ===============================
$fecha_mostrar = $config['fecha_nacimiento'] ?? $usuario['fecha_nacimiento'];
$fecha_nac = new DateTime($fecha_mostrar);
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

$fecha_formateada = $fecha_nac->format('d') . ' de ' .
    $meses[(int)$fecha_nac->format('m')] .
    ' de ' . $fecha_nac->format('Y');

// ===============================
//  DATOS DEL USUARIO
// ===============================
$inicial = strtoupper(substr($usuario['nombre'], 0, 1));
$nombre_completo = trim($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']);

// ===============================
//  JSON PARA DEBUG EN JS
// ===============================
$config_json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$usuario_json = json_encode($usuario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ===============================
//  CONTENIDO HTML
// ===============================
$contenido = '
<div class="container-ajustes py-4">
    <div class="row">
        <!-- Columna izquierda: Perfil -->
        <div class="col-lg-5">
            <div class="perfil-card">
                <div class="perfil-header">
                    <div class="avatar-container">
                        <div class="avatar">' . $inicial . '</div>
                    </div>
                    <div class="perfil-info">
                        <h2 class="perfil-nombre">' . htmlspecialchars($nombre_completo) . '</h2>
                        <p class="perfil-email">' . htmlspecialchars($usuario['correo_institucional']) . '</p>
                    </div>
                </div>

                <div class="perfil-detalles">
                    <div class="detalle-item">
                        <i class="bi bi-calendar"></i>
                        <span>Nací el ' . $fecha_formateada . '</span>
                    </div>
                    <div class="detalle-item">
                        <i class="bi bi-mortarboard"></i>
                        <span>' . htmlspecialchars($config['institucion_academica']) . '</span>
                    </div>
                </div>

                <div class="perfil-section">
                    <h3 class="section-title">
                        <i class="bi bi-person"></i>
                        Perfil
                    </h3>
                    <div class="perfil-opciones">
                        <button class="opcion-btn"><i class="bi bi-key"></i><span>Cambiar contraseña</span></button>
                        <button class="opcion-btn"><i class="bi bi-person"></i><span>Cambiar nombre de usuario</span></button>
                        <button class="opcion-btn"><i class="bi bi-geo-alt"></i><span>Cambiar localidad</span></button>
                        <button class="opcion-btn"><i class="bi bi-calendar"></i><span>Cambiar fecha de nacimiento</span></button>
                        <button class="opcion-btn"><i class="bi bi-mortarboard"></i><span>Agregar institución académica</span></button>
                    </div>
                    
                    <button class="btn-confirmar">Confirmar cambios</button>
                </div>
            </div>
        </div>

        <!-- DERECHA: Notificaciones y privacidad -->
        <div class="col-lg-7">
            <div class="config-card">

                <div class="config-section">
                    <h3 class="section-title"><i class="bi bi-bell"></i> Notificaciones</h3>
                    <div class="config-opciones">
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="notif_todas" ' . ($config['notif_todas'] ? 'checked' : '') . '> Recibir todas las notificaciones</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="notif_tareas_nuevas" ' . ($config['notif_tareas_nuevas'] ? 'checked' : '') . '> Recibir notificaciones de tareas nuevas</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="notif_tareas_atrasadas" ' . ($config['notif_tareas_atrasadas'] ? 'checked' : '') . '> Recibir notificaciones de tareas atrasadas</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="notif_modificaciones_proyecto" ' . ($config['notif_modificaciones_proyecto'] ? 'checked' : '') . '> Recibir notificaciones de modificaciones al proyecto</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="notif_admin_proyecto" ' . ($config['notif_admin_proyecto'] ? 'checked' : '') . '> Recibir notificaciones del administrador del proyecto</label>
                    </div>
                </div>

                <div class="config-section">
                    <h3 class="section-title"><i class="bi bi-shield-check"></i> Privacidad</h3>
                    <div class="config-opciones">
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="priv_ver_tareas" ' . ($config['priv_ver_tareas'] ? 'checked' : '') . '> Los demás usuarios pueden ver mis tareas</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="priv_ver_proyectos" ' . ($config['priv_ver_proyectos'] ? 'checked' : '') . '> Los demás usuarios pueden ver mis proyectos</label>
                        <label class="config-label"><input type="checkbox" class="config-checkbox" name="priv_ver_datos" ' . ($config['priv_ver_datos'] ? 'checked' : '') . '> Los demás usuarios pueden ver mis datos</label>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
console.log("=== DEBUG AJUSTES ===");
console.log("Usuario:", ' . $usuario_json . ');
console.log("Config:", ' . $config_json . ');
console.log("====================");
</script>

<script src="/ITSFCP-PROYECTOS/publico/js/ajustes.js"></script>
';

include __DIR__ . '/../../layout.php';
?>