<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../../publico/config/conexion.php";

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);
$nombre_user = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario');

/* =======================================================
   FUNCIÓN: PROGRESO DEL PROYECTO
   ======================================================= */
function progresoProyecto($conn, $id_proyecto)
{
    $id_proyecto = intval($id_proyecto);

    // Total tareas
    $total_q = $conn->query("
        SELECT COUNT(*) AS total
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
        INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
        WHERE s.id_proyectos = $id_proyecto
    ");

    $total = $total_q->fetch_assoc()['total'] ?? 0;
    if ($total == 0)
        return 0;

    // Tareas terminadas (estado=4)
    $done_q = $conn->query("
        SELECT COUNT(*) AS done
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
        INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
        WHERE s.id_proyectos = $id_proyecto
        AND tu.id_estadoT = 4
    ");

    $done = $done_q->fetch_assoc()['done'] ?? 0;

    return round(($done / $total) * 100);
}



/* =======================================================
   1. PROYECTOS (DEPENDIENDO DEL ROL)
   ======================================================= */

if ($rol === 'supervisor') {

    $sql_proyectos = "
        SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
        FROM proyectos p
        INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
        ORDER BY p.creado_en DESC
    ";

} elseif ($rol === 'investigador' || $rol === 'profesor') {

    $sql_proyectos = "
        SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
        FROM proyectos p
        INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
        WHERE p.id_investigador = $id_usuario
        ORDER BY p.creado_en DESC
    ";

} else {

    $sql_proyectos = "
        SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
        FROM proyectos p
        INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
        INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
        WHERE pu.id_usuarios = $id_usuario
        ORDER BY p.creado_en DESC
    ";
}

$result_proyectos = $conn->query($sql_proyectos);



/* =======================================================
   1B. PROYECTO PRINCIPAL (USA EL PRIMER PROYECTO)
   ======================================================= */

if ($result_proyectos && $result_proyectos->num_rows > 0) {

    $primer_proyecto = $result_proyectos->fetch_assoc();
    $porcentaje = progresoProyecto($conn, $primer_proyecto['id_proyectos']);

    $progreso_html = '
<div class="card card-progreso shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-5">
                <h5 class="mb-4 fw-bold">Progreso</h5>
                <div class="progress-circle">
                    <svg width="180" height="180">
                        <circle class="progress-circle-bg" cx="90" cy="90" r="70"></circle>
                        <circle class="progress-circle-bar" cx="90" cy="90" r="70"
                            style="stroke-dasharray: 440; stroke-dashoffset:' . (440 - 440 * $porcentaje / 100) . ';"></circle>
                    </svg>
                    <div class="progress-text">' . $porcentaje . '%</div>
                </div>
            </div>
            <div class="col-md-7">
                <h5 class="fw-bold mb-1">' . htmlspecialchars($primer_proyecto['titulo']) . '</h5>
                <p class="mb-3">' . htmlspecialchars($primer_proyecto['descripcion']) . '</p>
            </div>
        </div>
    </div>
</div>';

    // Reposicionar el puntero para volver a recorrer los proyectos
    $result_proyectos->data_seek(0);

} else {

    $progreso_html = '
    <div class="card card-progreso shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="mb-4 fw-bold">Progreso</h5>
            Aún no tienes proyectos asignados.
        </div>
    </div>';
}



/* =======================================================
   2. TAREAS ASIGNADAS (SOLO SI NO ES SUPERVISOR)
   ======================================================= */

$tareas_html = '';

if ($rol !== 'supervisor') {
    $sql_tareas = "
        SELECT 
            tu.id_tarea,
            tt.descripcion_tipo AS nombre_tarea,
            t.descripcion,
            t.fecha_entrega,
            tu.id_estadoT,
            et.nombre AS estado
        FROM tareas_usuarios tu
        INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
        INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
        INNER JOIN estados_tarea et ON tu.id_estadoT = et.id_estadoT
        WHERE tu.id_usuario = $id_usuario
        ORDER BY t.fecha_entrega DESC
    ";

    $result_tareas = $conn->query($sql_tareas);

    if ($result_tareas && $result_tareas->num_rows > 0) {
        while ($tarea = $result_tareas->fetch_assoc()) {
            $nombre = htmlspecialchars($tarea['nombre_tarea']);
            $fecha_entrega = $tarea['fecha_entrega'] ? date('d/m/Y', strtotime($tarea['fecha_entrega'])) : 'Sin fecha';
            $checked = ($tarea['estado'] === 'Aprobado') ? 'checked' : '';
            $completed = ($tarea['estado'] === 'Aprobado') ? 'task-completed' : '';

            $tareas_html .= '
            <div class="task-item">
                <div class="d-flex align-items-center">
                    <input type="checkbox" class="task-checkbox me-3" ' . $checked . '>
                    <div class="flex-grow-1">
                        <span class="' . $completed . '">' . $nombre . '</span>
                    </div>
                    <span class="badge-date">' . $fecha_entrega . '</span>
                </div>
            </div>';
        }
    } else {
        $tareas_html = '<div>En este espacio encontrarás tus tareas asignadas.</div>';
    }
}



/* =======================================================
   3. LISTA DE PROYECTOS
   ======================================================= */

$proyectos_html = '';

if ($result_proyectos && $result_proyectos->num_rows > 0) {

    while ($proyecto = $result_proyectos->fetch_assoc()) {

        $proyectos_html .= '
<a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/detalles.php?id_proyectos=' . $proyecto['id_proyectos'] . '" class="proyecto-link">
    <div class="proyecto-item d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="icon-proyecto">📁</span>
            <span class="proyecto-nombre">' . htmlspecialchars($proyecto['titulo']) . '</span>
        </div>
        <span class="estado-proyecto">' . htmlspecialchars($proyecto['estado']) . '</span>
    </div>
</a>';
    }

} else {
    $proyectos_html = '<div>En este espacio encontrarás tus proyectos.</div>';
}



/* =======================================================
   4. MODIFICACIONES
   ======================================================= */

// IDs de proyectos del usuario (para investigadores/alumnos)
$proyectos_ids = [];
$res = $conn->query("
    SELECT id_proyectos 
    FROM proyectos_usuarios 
    WHERE id_usuarios = $id_usuario
");

while ($row = $res->fetch_assoc()) {
    $proyectos_ids[] = $row['id_proyectos'];
}

if ($rol === 'supervisor') {

    $sql_modificaciones = "
        SELECT 
            tu.contenido,
            tu.fecha_revision AS fecha,
            u.nombre,
            r.nombre AS rol
        FROM tareas_usuarios tu
        INNER JOIN usuarios u ON tu.id_usuario = u.id_usuarios
        INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        INNER JOIN roles r ON ur.id_rol = r.id_roles
        WHERE tu.fecha_revision IS NOT NULL
        ORDER BY tu.fecha_revision DESC
        LIMIT 5
    ";

} else {

    $ids_lista = empty($proyectos_ids) ? '0' : implode(',', $proyectos_ids);

    $sql_modificaciones = "
        SELECT 
            tu.contenido,
            tu.fecha_revision AS fecha,
            u.nombre,
            r.nombre AS rol
        FROM tareas_usuarios tu
        INNER JOIN usuarios u ON tu.id_usuario = u.id_usuarios
        INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuario
        INNER JOIN roles r ON ur.id_rol = r.id_roles
        INNER JOIN proyectos_usuarios pu ON tu.id_usuario = pu.id_usuarios
        WHERE pu.id_proyectos IN ($ids_lista)
        AND tu.fecha_revision IS NOT NULL
        ORDER BY tu.fecha_revision DESC
        LIMIT 5
    ";
}

$result_mod = $conn->query($sql_modificaciones);
$modificaciones_html = '';

if ($result_mod && $result_mod->num_rows > 0) {

    while ($mod = $result_mod->fetch_assoc()) {

        $desc_raw = json_decode($mod['contenido'], true);
        $desc = htmlspecialchars(substr($desc_raw['descripcion'] ?? '', 0, 50));

        $inicial = strtoupper(substr($mod['nombre'], 0, 1));
        $avatar_class = "avatar-dash " . (strtolower($mod['rol']) === 'estudiante' ? 'avatar-u' : 'avatar-e');

        $modificaciones_html .= '
        <div class="modificacion-item">
            <div class="d-flex align-items-center">
                <div class="' . $avatar_class . ' me-3">' . $inicial . '</div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">' . $desc . '</div>
                </div>
                <span class="small">' . date('d/m/Y', strtotime($mod['fecha'])) . '</span>
            </div>
        </div>';
    }

} else {
    $modificaciones_html = '<div>En este espacio encontrarás las últimas modificaciones de tus tareas.</div>';
}



/* =======================================================
   BOTÓN NUEVO PROYECTO
   ======================================================= */
$mostrar_btn = ($rol === 'profesor' || $rol === 'supervisor')
    ? '<button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo proyecto</button>'
    : '';



/* =======================================================
   ENSAMBLAR CONTENIDO FINAL
   ======================================================= */

// Card de tareas solo si NO es supervisor
$card_tareas = '';
if ($rol !== 'supervisor') {
    $card_tareas = '
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold">Tareas asignadas</h5>
                    ' . $tareas_html . '
                </div>
            </div>';
}

$contenido = '
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">Hola, ' . $nombre_user . '! 👋</h2>
        </div>
        <div class="col-md-6 text-md-end">
            ' . $mostrar_btn . '
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            ' . $progreso_html . '
            ' . $card_tareas . '
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

include __DIR__ . "/../../layout.php";
?>