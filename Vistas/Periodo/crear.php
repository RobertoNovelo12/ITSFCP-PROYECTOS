<?php
// Periodo/crear.php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ .  '/../../Controladores/periodoControlador.php';

$periodoControlador = new periodoControlador();
$estadoVista        = $periodoControlador->obtenerEstadoVista();

// Validación
$registro = $estadoVista;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';


$datos              = $estadoVista['datos'];

//  Acciones POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'registrarPeriodo') {
        $periodoControlador->registrarPeriodo($rol, $_POST);
        // Siempre redirige → el código no continúa.
    }

    if ($action === 'reactivarPeriodo') {
        $periodoControlador->reactivar($rol, $datos['nombre']);
        // Siempre redirige → el código no continúa.
    }
}

//  Repoblar campos en caso de error de rango (redirección GET) 
$post_fip    = $_POST['fecha_inicio_proyectos']   ?? '';
$post_ffp    = $_POST['fecha_fin_proyectos']       ?? '';
$post_fii    = $_POST['fecha_inicio_solicitud']    ?? '';
$post_ffi    = $_POST['fecha_fin_solicitud']       ?? '';
$error_fecha = isset($_GET['error_fecha']) ? htmlspecialchars($_GET['error_fecha']) : null;

//  Mapa de mensajes ─
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_crear'         => ['tipo' => 'exito',  'titulo_msg' => 'Periodo creado',      'mensaje' => 'El periodo fue creado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Periodo reactivado',  'mensaje' => 'El periodo fue reactivado correctamente.'],
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',       'mensaje' => 'No fue posible crear el periodo. Verifica los datos e intenta de nuevo.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',   'mensaje' => 'No fue posible reactivar el periodo.'],
    'error_duplicado'     => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe un periodo con esas fechas o nombre.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>


<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Nuevo Periodo';
        $descripcion = 'Registro de un nuevo periodo académico';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <?php if (isset($_mapa[$msg])): extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>

    <?php if ($error_fecha): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= $error_fecha ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- INFORMACIÓN DEL PERIODO (solo lectura) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Información del periodo <span class="text-muted fs-6 fw-normal">(generado automáticamente)</span></h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Nombre del periodo</label>
                    <p class="mb-0 fw-bold"><?= htmlspecialchars($datos['nombre']) ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Fecha de inicio del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['inicio']) ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted small">Fecha de fin del semestre</label>
                    <p class="mb-0"><?= htmlspecialchars($datos['fin']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($estadoVista['accion'] === 'bloqueado'): ?>

        <div class="alert alert-warning" role="alert">
            <i class="bi bi-lock-fill me-1"></i>
            <?= htmlspecialchars($estadoVista['mensaje']) ?>
        </div>

    <?php else: ?>

        <form method="POST" action="crear.php" novalidate>

            <?php if ($estadoVista['accion'] === 'reactivar'): ?>
                <input type="hidden" name="action" value="reactivarPeriodo">
            <?php else: ?>
                <input type="hidden" name="action" value="registrarPeriodo">
            <?php endif; ?>

            <!-- FECHAS DE PROYECTOS E INTEGRACIÓN -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Fechas de proyectos e integración</h5>
                </div>
                <div class="card-body">

                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Todas las fechas deben estar dentro del rango del semestre
                        (<strong><?= htmlspecialchars($datos['inicio']) ?></strong> –
                        <strong><?= htmlspecialchars($datos['fin']) ?></strong>).
                        Son opcionales; puede completarlas ahora o editarlas después.
                    </p>

                    <!-- Proyectos -->
                    <h6 class="fw-semibold mb-3 border-bottom pb-1">Periodo de Proyectos</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="fecha_inicio_proyectos" class="form-label">Fecha inicio de proyectos</label>
                            <input type="date"
                                id="fecha_inicio_proyectos"
                                name="fecha_inicio_proyectos"
                                class="form-control"
                                value="<?= htmlspecialchars($post_fip) ?>"
                                min="<?= htmlspecialchars($datos['inicio']) ?>"
                                max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin_proyectos" class="form-label">Fecha fin de proyectos</label>
                            <input type="date"
                                id="fecha_fin_proyectos"
                                name="fecha_fin_proyectos"
                                class="form-control"
                                value="<?= htmlspecialchars($post_ffp) ?>"
                                min="<?= htmlspecialchars($datos['inicio']) ?>"
                                max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                    </div>

                    <!-- Integración -->
                    <h6 class="fw-semibold mb-3 border-bottom pb-1">Periodo de Integración</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="fecha_inicio_solicitud" class="form-label">Fecha inicio de integración</label>
                            <input type="date"
                                id="fecha_inicio_solicitud"
                                name="fecha_inicio_solicitud"
                                class="form-control"
                                value="<?= htmlspecialchars($post_fii) ?>"
                                min="<?= htmlspecialchars($datos['inicio']) ?>"
                                max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin_solicitud" class="form-label">Fecha fin de integración</label>
                            <input type="date"
                                id="fecha_fin_solicitud"
                                name="fecha_fin_solicitud"
                                class="form-control"
                                value="<?= htmlspecialchars($post_ffi) ?>"
                                min="<?= htmlspecialchars($datos['inicio']) ?>"
                                max="<?= htmlspecialchars($datos['fin']) ?>">
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if ($estadoVista['accion'] === 'reactivar'): ?>
                    <button type="submit" class="btn btn-guardar">
                        <i class="bi bi-arrow-repeat me-1"></i> Reactivar Periodo
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-guardar">
                        <i class="bi bi-plus-lg me-1"></i> Crear Periodo
                    </button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>

        </form>

    <?php endif; ?>

</div>

<script>
    (function() {
        const pares = [
            ['fecha_inicio_proyectos', 'fecha_fin_proyectos'],
            ['fecha_inicio_solicitud', 'fecha_fin_solicitud'],
        ];

        pares.forEach(([inicioId, finId]) => {
            const inputInicio = document.getElementById(inicioId);
            const inputFin = document.getElementById(finId);
            if (!inputInicio || !inputFin) return;

            inputInicio.addEventListener('change', () => {
                if (inputInicio.value) {
                    inputFin.min = inputInicio.value;
                    if (inputFin.value && inputFin.value < inputInicio.value) {
                        inputFin.value = '';
                    }
                }
            });

            inputFin.addEventListener('change', () => {
                if (inputFin.value) {
                    inputInicio.max = inputFin.value;
                }
            });
        });
    })();
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear periodo';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>