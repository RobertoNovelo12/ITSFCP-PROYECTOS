<?php
session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

// Obtener rol y usuario actual
$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Función para calcular progreso de proyecto en %
function progresoProyecto($conn, $id_proyecto)
{
    $total = $conn->query("SELECT COUNT(*) AS total FROM tareas_usuarios WHERE id_proyecto = $id_proyecto")->fetch_assoc()['total'];
    if ($total == 0) return 0;

    $completadas = $conn->query("SELECT COUNT(*) AS done FROM tareas_usuarios WHERE id_proyecto = $id_proyecto AND id_estadoT = 4")->fetch_assoc()['done'];
    return round(($completadas / $total) * 100);
}

$sql_proy = "
    SELECT p.id_proyectos, p.titulo 
    FROM proyectos p
    INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
    WHERE pu.id_usuarios = $id_usuario
    LIMIT 1
";
$proy_result = $conn->query($sql_proy);
$progreso_html = '';
if ($proy_result && $proy_result->num_rows > 0) {
    $proyecto = $proy_result->fetch_assoc();
    $porcentaje = progresoProyecto($conn, $proyecto['id_proyectos']);
    $progreso_html = '
    <div class="card card-progreso shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <h6 class="mb-3">Progreso</h6>
                    <div class="progress-circle">
                        <svg width="180" height="180">
                            <circle class="progress-circle-bg" cx="90" cy="90" r="70"></circle>
                            <circle class="progress-circle-bar" cx="90" cy="90" r="70"
                                    style="stroke-dasharray: 440; stroke-dashoffset: ' . (440 - 440 * $porcentaje / 100) . ';"></circle>
                        </svg>
                        <div class="progress-text">' . $porcentaje . '%</div>
                    </div>
                </div>
                <div class="col-md-7">
                    <h5 class="fw-bold mb-3">' . htmlspecialchars($proyecto['titulo']) . '</h5>
                </div>
            </div>
        </div>
    </div>';
} else {
    $progreso_html = '<div class="card card-progreso shadow-sm mb-4"><div class="card-body text-center">En este espacio encontrarás tu progreso cuando tengas proyectos asignados.</div></div>';
}

// 2. Tareas asignadas
$sql_tareas = "
    SELECT 
        tu.id_asignacion,
        tu.id_tarea,
        tu.id_usuario,
        tu.id_proyecto,
        tu.id_estadoT,
        tu.fecha_asignacion,
        tu.fecha_completacion,
        t.contenido,
        t.fecha_creacion,
        et.nombre AS estado
    FROM tareas_usuarios tu
    INNER JOIN tareas t ON tu.id_tarea = t.id_tareas
    INNER JOIN estados_tarea et ON tu.id_estadoT = et.id_estadoT
    WHERE tu.id_usuario = $id_usuario
    ORDER BY t.fecha_creacion DESC
";

$tareas_html = '';
$result_tareas = $conn->query($sql_tareas);
if ($result_tareas && $result_tareas->num_rows > 0) {
    while ($tarea = $result_tareas->fetch_assoc()) {
        $checked = ($tarea['estado'] == 'Aprobado') ? 'checked' : '';
        $completed = ($tarea['estado'] == 'Aprobado') ? 'task-completed' : '';
        $desc = json_decode($tarea['contenido'], true)['descripcion'] ?? '';
        $tareas_html .= '
        <div class="task-item">
            <div class="d-flex align-items-center">
                <input type="checkbox" class="task-checkbox me-3" ' . $checked . '>
                <div class="flex-grow-1">
                    <span class="' . $completed . '">' . htmlspecialchars(substr($desc, 0, 50)) . '</span>
                </div>
                <span class="badge-date">' . date('d/m/Y', strtotime($tarea['fecha_creacion'])) . '</span>
            </div>
        </div>';
    }
} else {
    $tareas_html = '<div class="fw-semibold">En este espacio encontrarás tus tareas asignadas.</div>';
}

// 3. Proyectos
$sql_proyectos = "
    SELECT p.id_proyectos, p.titulo, ep.nombre AS estado
    FROM proyectos p
    INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
    INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
    WHERE pu.id_usuarios = $id_usuario
    ORDER BY p.creado_en DESC
";
$proyectos_html = '';
$result_proyectos = $conn->query($sql_proyectos);
if ($result_proyectos && $result_proyectos->num_rows > 0) {
    while ($proyecto = $result_proyectos->fetch_assoc()) {
        $pct = progresoProyecto($conn, $proyecto['id_proyectos']);
        $color_class = 'proyecto-verde';
        if ($proyecto['estado'] == 'Completado') $color_class = 'proyecto-azul';
        $proyectos_html .= '
        <div class="d-flex align-items-center mb-3">
            <div class="proyecto-bar ' . $color_class . ' flex-grow-1" style="width:' . $pct . '%"></div>
            <span class="badge badge-estado ' . ($color_class == 'proyecto-verde' ? 'badge-en-curso' : 'badge-completado') . ' ms-3">' . $proyecto['estado'] . '</span>
        </div>';
    }
} else {
    $proyectos_html = '<div class= fw-semibold">En este espacio encontrarás tus proyectos.</div>';
}

// Obtener proyectos del usuario
$proyectos_ids = [];
$res = $conn->query("SELECT id_proyectos FROM proyectos_usuarios WHERE id_usuarios = $id_usuario");
while ($row = $res->fetch_assoc()) {
    $proyectos_ids[] = $row['id_proyectos'];
}

if ($rol == 'supervisor') {
    // Supervisor ve todo
    $sql_modificaciones = "
        SELECT t.contenido, tu.fecha_completacion, u.nombre, r.nombre AS rol
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON tu.id_tarea = t.id_tareas
        INNER JOIN usuarios u ON tu.id_usuario = u.id_usuarios
        INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        INNER JOIN roles r ON ur.id_rol = r.id_roles
        WHERE tu.fecha_completacion IS NOT NULL
        ORDER BY tu.fecha_completacion DESC
        LIMIT 5
    ";
} else {
    // Usuarios normales solo ven modificaciones de sus proyectos
    $ids_lista = empty($proyectos_ids) ? '0' : implode(',', $proyectos_ids);

    $sql_modificaciones = "
        SELECT t.contenido, tu.fecha_completacion, u.nombre, r.nombre AS rol
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON tu.id_tarea = t.id_tareas
        INNER JOIN usuarios u ON tu.id_usuario = u.id_usuarios
        INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        INNER JOIN roles r ON ur.id_rol = r.id_roles
        WHERE tu.id_proyecto IN ($ids_lista)
        AND tu.fecha_completacion IS NOT NULL
        ORDER BY tu.fecha_completacion DESC
        LIMIT 5
    ";
}

$modificaciones_html = '';
$result_mod = $conn->query($sql_modificaciones);
if ($result_mod && $result_mod->num_rows > 0) {
    while ($mod = $result_mod->fetch_assoc()) {
        $inicial = strtoupper(substr($mod['nombre'], 0, 1));
        $avatar_class = 'avatar-dash';
        if (strtolower($mod['rol']) == 'estudiante') $avatar_class .= ' avatar-u';
        else $avatar_class .= ' avatar-e';

        $desc_mod = json_decode($mod['contenido'], true)['descripcion'] ?? '';
        $modificaciones_html .= '
        <div class="modificacion-item">
            <div class="d-flex align-items-center">
                <div class="' . $avatar_class . ' me-3">' . $inicial . '</div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">' . htmlspecialchars(substr($desc_mod, 0, 50)) . '</div>
                </div>
                <span class= small">' . date('d/m/Y', strtotime($mod['fecha_completacion'])) . '</span>
            </div>
        </div>';
    }
} else {
    $modificaciones_html = '<div class= fw-semibold">En este espacio encontrarás las últimas modificaciones de tus tareas.</div>';
}

// Determinar si mostrar botón Nuevo proyecto
$mostrar_btn = ($rol == 'profesor' || $rol == 'supervisor') ? '<button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo proyecto</button>' : '';

$contenido = '
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Hola, ' . htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') . '! 👋</h2>
        </div>
        <div class="col-md-6 text-md-end">
            ' . $mostrar_btn . '
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6 mb-4">
            ' . $progreso_html . '
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold">Tareas asignadas</h5>
                    ' . $tareas_html . '
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-bold">Proyectos</h5>
                    ' . $proyectos_html . '
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold">Últimas modificaciones</h5>
                    ' . $modificaciones_html . '
                </div>
            </div>
        </div>
    </div>
</div>
';

// Incluir layout
include __DIR__ . '/../../layout.php';
?>