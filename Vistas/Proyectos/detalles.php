<?php
/* Proyectos/detalles.php — Página secundaria para ver detalles del proyecto */

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id = $_SESSION['id_usuario'];

include __DIR__ . '../../../publico/incluido/_validar_get.php';


if (!in_array($rol, ['investigador', 'profesor', 'estudiante', 'supervisor'], true)) {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

$id_proyecto = (int)($_GET['id_proyectos'] ?? 0);

$id_validar = $id_proyecto;
include __DIR__ . '../../../publico/incluido/_validar_id.php';


require_once __DIR__ .  '/../../Controladores/proyectoControlador.php';

$proyectoControlador = new ProyectoControlador();

// 
// ACCIONES POST — deben procesarse ANTES de cualquier output para que
// el header() de redirigir() funcione sin errores de "headers already sent".
// 
$action = $_POST['action'] ?? null;

if (
    in_array($action, ['baja', 'reactivar'], true) &&
    in_array($rol, ['investigador', 'profesor'], true)
) {
    $proyectoControlador->accionEstudiante($_POST);
}

// 
// CARGA DE DATOS
// 
$proyecto = $proyectoControlador->datosproyecto($id_proyecto);

$investigador = $proyectoControlador->datosinvestigador($id_proyecto);

$subtematicas = $proyectoControlador->subtematicasProyecto($id_proyecto);

// Validación
$registro = $subtematicas;
include __DIR__ . '../../../publico/incluido/_validar_datos.php';


$dat_inv         = $investigador['investigador'] ?? [];
$dat_area_inv    = $investigador['area']         ?? [];
$datos_linea_inv = $investigador['lineas']       ?? [];

$estudiantes = null;
if (in_array($rol, ['investigador', 'profesor', 'supervisor'], true)) {
    $estudiantes = $proyectoControlador->estudiantes($id_proyecto);
}

// 
// MAPA DE ALERTAS — igual que en index.php, usando el nuevo _mensaje.php
// 
$msg  = $_GET['msg'] ?? '';
$_mapa = [
    'exito_operacion'    => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',  'mensaje' => 'La operación sobre el estudiante fue realizada correctamente.'],
    'error_operacion'    => ['tipo' => 'error',  'titulo_msg' => 'Error en la operación', 'mensaje' => 'No fue posible completar la operación sobre el estudiante.'],
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',    'mensaje' => 'No tienes permiso para ver este proyecto.'],
    'accion_no_permitida'=> ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
    'error_cargar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',       'mensaje' => 'No fue posible cargar la información del proyecto.'],
    'error_estado'       => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',       'mensaje' => 'No fue posible actualizar el estado del proyecto.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <div class="row mb-3">

        <?php
        $titulo      = 'Detalle de Proyecto';
        $descripcion = 'Información completa del proyecto';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>

    </div>

    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- INFORMACIÓN DEL PROYECTO -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Información del proyecto</h5>
        </div>
        <div class="card-body">
            <h5><?= htmlspecialchars($proyecto['titulo']) ?></h5>
            <p class="text-muted">
                <?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?>
            </p>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Objetivos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['objetivo'])) ?></dd>

                        <dt>Pre-requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['pre_requisitos'])) ?></dd>

                        <dt>Requisitos</dt>
                        <dd><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Cantidad alumnos</dt>
                        <dd><?= $proyecto['cantidad_estudiante'] ?></dd>

                        <dt>Temática</dt>
                        <dd><?= $proyecto['tematica'] ?></dd>

                        <dt>Modalidad</dt>
                        <dd><?= $proyecto['modalidad'] ?></dd>

                        <dt>Presupuesto</dt>
                        <dd>$<?= number_format($proyecto['presupuesto'], 2) ?></dd>

                        <dt>Periodo</dt>
                        <dd><?= $proyecto['periodo'] ?> - <?= $proyecto['estado_periodo'] ?></dd>

                        <dt>Fecha inicio</dt>
                        <dd><?= $proyecto['fecha_inicio'] ?></dd>

                        <dt>Fecha final</dt>
                        <dd><?= $proyecto['fecha_fin'] ?></dd>

                        <dt>Estado</dt>
                        <dd>
                            <span class="badge bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                <?= $proyecto['estado_proyecto'] ?>
                            </span>
                        </dd>

                        <dt>Fecha creación</dt>
                        <dd><?= $proyecto['creado_en'] ?></dd>
                    </dl>
                </div>
            </div>
            <hr>
            <b>Subtemáticas</b>
            <div class="mt-2">
                <?php foreach ($subtematicas as $sub): ?>
                    <span class="badge bg-primary me-2 mb-2">
                        <?= htmlspecialchars(trim($sub['nombre'])) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- INVESTIGADOR -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Investigador</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre completo</dt>
                        <dd>
                            <?= htmlspecialchars(
                                $dat_inv['nombre'] . ' ' .
                                $dat_inv['apellido_paterno'] . ' ' .
                                $dat_inv['apellido_materno']
                            ) ?>
                        </dd>

                        <dt>Área conocimiento</dt>
                        <dd><?= $dat_area_inv['area_conocimiento'] ?: 'No tiene área asignada' ?></dd>

                        <dt>Subárea</dt>
                        <dd><?= $dat_area_inv['subarea'] ?: 'No tiene subárea asignada' ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Nivel SNI</dt>
                        <dd><?= $dat_inv['nivel_sni'] ?></dd>

                        <dt>Grado académico</dt>
                        <dd><?= $dat_inv['grado_academico'] ?></dd>

                        <dt>Línea investigación</dt>
                        <dd><?= $datos_linea_inv['linea'] ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO OCULTO PARA ACCIONES DE ESTUDIANTE -->
    <form id="formAccion" method="POST">
        <input type="hidden" name="action"        id="action">
        <input type="hidden" name="id_estudiante" id="id_estudiante">
        <input type="hidden" name="id_proyecto"   value="<?= $id_proyecto ?>">
    </form>

    <!-- ESTUDIANTES -->
    <?php if (in_array($rol, ['supervisor', 'profesor', 'investigador'], true)): ?>
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-people-fill"></i> <b>Estudiantes involucrados</b></h5>
        </div>
        <?php if (!empty($estudiantes)): ?>

            <!-- TABLA (DESKTOP) -->
            <div class="card shadow-sm d-none d-md-block">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Carrera</th>
                                    <th>Área</th>
                                    <th>Subárea</th>
                                    <th>Estado Proceso</th>
                                    <th>Historial</th>
                                    <?php if ($rol === 'investigador'): ?>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $alumno):
                                    $clase = $proyectoControlador->EstiloEstado($alumno['estado_proceso']);
                                    $estado_proceso = match ($alumno['estado_proceso']) {
                                        'en_proceso'          => 'En proceso',
                                        'carta_subida'        => 'Carta subida',
                                        'en_correccion'       => 'En corrección',
                                        'liberado_supervisor' => 'Liberado por supervisor',
                                        default               => 'Sin estado',
                                    };
                                ?>
                                    <tr>
                                        <td><?= $alumno['id_usuarios'] ?></td>
                                        <td><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']) ?></td>
                                        <td><?= $alumno['carrera'] ?></td>
                                        <td><?= $alumno['area']['area']    ?? 'Sin área' ?></td>
                                        <td><?= $alumno['area']['subarea'] ?? 'Sin subárea' ?></td>
                                        <td>
                                            <span class="badge text-bg-<?= $clase ?>">
                                                <?= htmlspecialchars($estado_proceso) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="historial_estudiante.php?id_proyecto=<?= $id_proyecto ?>&id_usuario=<?= $alumno['id_usuarios'] ?>"
                                               class="btn btn-info btn-sm">
                                                Historial
                                            </a>
                                        </td>
                                        <?php if ($rol === 'investigador'): ?>
                                            <td>
                                                <?= $proyectoControlador->botonesAccionEditarEstudiante(
                                                    $alumno['id_usuarios'],
                                                    $rol,
                                                    $alumno['estado'],
                                                    $id_proyecto,
                                                    $proyecto['estado_proyecto']
                                                ) ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TARJETAS (MÓVIL) -->
            <div class="d-md-none">
                <?php foreach ($estudiantes as $alumno):
                    $clase = $proyectoControlador->EstiloEstado($alumno['estado_proceso']);
                    $estado_proceso = match ($alumno['estado_proceso']) {
                        'en_proceso'          => 'En proceso',
                        'carta_subida'        => 'Carta subida',
                        'en_correccion'       => 'En corrección',
                        'liberado_supervisor' => 'Liberado por supervisor',
                        default               => 'Sin estado',
                    };
                ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido_paterno']) ?>
                            </h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><b>ID:</b> <?= $alumno['id_usuarios'] ?></li>
                                <li class="list-group-item"><b>Carrera:</b> <?= $alumno['carrera'] ?></li>
                                <li class="list-group-item"><b>Área:</b> <?= $alumno['area']['area']    ?? 'Sin área' ?></li>
                                <li class="list-group-item"><b>Subárea:</b> <?= $alumno['area']['subarea'] ?? 'Sin subárea' ?></li>
                                <li class="list-group-item">
                                    <b>Estado proceso:</b>
                                    <span class="badge text-bg-<?= $clase ?>">
                                        <?= htmlspecialchars($estado_proceso) ?>
                                    </span>
                                </li>
                                <li class="list-group-item">
                                    <a href="historial_estudiante.php?id_proyecto=<?= $id_proyecto ?>&id_usuario=<?= $alumno['id_usuarios'] ?>"
                                       class="btn btn-info btn-sm">
                                        Historial
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center">
                No hay estudiantes para mostrar
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- JS: conecta los botones data-accion con el formulario oculto -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accion]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('action').value        = btn.dataset.accion;
            document.getElementById('id_estudiante').value = btn.dataset.id;
            document.getElementById('formAccion').submit();
        });
    });
});
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Detalles de proyecto';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>