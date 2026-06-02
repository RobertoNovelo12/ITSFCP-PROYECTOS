<?php
// Vistas/Solicitudes_actualizacion/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header('Location: /Vistas/Principal/index.php');
    exit;
}

require_once __DIR__ .  '/../../Controladores/solicitudActualizacionControlador.php';

$ctrl   = new SolicitudActualizacionControlador();
$action = $_GET['action'] ?? 'index';
$buscar = trim($_GET['buscar'] ?? '');
$tipo   = trim($_GET['tipo']   ?? '');

//  Acción: aprobar (GET desde botón tabla) ─
if ($action === 'aprobar' && isset($_GET['id_solicitud'])) {
    $ctrl->aprobar((int)$_GET['id_solicitud'], $id_usuario);
    // aprobar() redirige; no llega aquí
}

//  Validar y ejecutar acción de listado 
$accionesPermitidas = ['index', 'Pendiente', 'Aprobado', 'Rechazado'];
if (!in_array($action, $accionesPermitidas, true)) {
    $action = 'index';
}

$resultado   = $ctrl->$action($buscar ?: null, $tipo ?: null);
$solicitudes = $resultado['solicitudes'] ?? [];
$paginacion  = $resultado['paginacion']  ?? ['total' => 0, 'por_pagina' => 8, 'pagina' => 1, 'total_paginas' => 1];

$opciones    = $ctrl->opciones();
$encabezados = $ctrl->encabezados();

//  Mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_aprobar'      => ['tipo' => 'exito',  'titulo_msg' => 'Solicitud aprobada',    'mensaje' => 'La solicitud fue aprobada correctamente y se notificó al investigador.'],
    'exito_rechazar'     => ['tipo' => 'exito',  'titulo_msg' => 'Solicitud rechazada',   'mensaje' => 'La solicitud fue rechazada y se notificó al investigador.'],
    'error_aprobar'      => ['tipo' => 'error',  'titulo_msg' => 'Error al aprobar',      'mensaje' => 'No fue posible aprobar la solicitud. Intenta de nuevo.'],
    'error_rechazar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al rechazar',     'mensaje' => 'No fue posible rechazar la solicitud. Intenta de nuevo.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Solicitudes de Actualización';
        $descripcion = 'Solicitudes de actualización de grado académico o nivel SNI';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6"></div>
    </div>
    <!-- ALERTAS -->
    <?php
    if (isset($_mapa[$msg])) {
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    }
    ?>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <?php
            include __DIR__ . '../../../publico/incluido/_total_registros.php';
            ?>
            <div class="row g-2 align-items-end">
                <!-- Select estado -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
                    <select class="form-select"
                        onchange="location.href='index.php?action=' + this.value
                        + '&buscar=<?= urlencode($buscar) ?>&tipo=<?= urlencode($tipo) ?>';">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select tipo -->
                <div class="col-md-3 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Tipo</label>
                    <select class="form-select"
                        onchange="location.href='index.php?action=<?= urlencode($action) ?>&buscar=<?= urlencode($buscar) ?>&tipo=' + this.value;">
                        <option value="" <?= ($tipo === '')      ? 'selected' : '' ?>>Todos los tipos</option>
                        <option value="sni" <?= ($tipo === 'sni')   ? 'selected' : '' ?>>Nivel SNI</option>
                        <option value="grado" <?= ($tipo === 'grado') ? 'selected' : '' ?>>Grado académico</option>
                    </select>
                </div>

                <!-- Búsqueda -->
                <div class="col-md-6 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Buscar</label>
                    <form class="d-flex gap-2" method="GET" action="index.php">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
                        <input type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por investigador..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if (!empty($buscar)): ?>
                            <a href="index.php?action=<?= urlencode($action) ?>&tipo=<?= urlencode($tipo) ?>"
                                class="btn btn-secondary" title="Limpiar">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA ESCRITORIO -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $enc): ?>
                                <th><?= htmlspecialchars($enc) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($solicitudes)): ?>
                            <?php foreach ($solicitudes as $s): ?>
                                <tr>
                                    <td class="text-start">
                                        <span class="fw-semibold"><?= htmlspecialchars($s['investigador']) ?></span><br>
                                        <small class="text-muted"><?= htmlspecialchars($s['correo_institucional']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $s['tipo'] === 'sni' ? 'primary' : 'success' ?>">
                                            <?= $ctrl->etiquetaTipo($s['tipo']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($s['valor_actual_nombre'] ?? '—') ?></td>
                                    <td><strong><?= htmlspecialchars($s['valor_nuevo_nombre'] ?? '—') ?></strong></td>
                                    <td>
                                        <?php if (!empty($s['nombre_archivo'])): ?>
                                            <a href="<?= htmlspecialchars($s['ruta']) ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip" title="Ver PDF">
                                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin doc.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $ctrl->estiloEstado($s['estado']) ?>">
                                            <i class="bi <?= $ctrl->iconoEstado($s['estado']) ?> me-1"></i>
                                            <?= ucfirst($s['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <?= $ctrl->botonesAccion($s['id_solicitudes_actualizacion'], $s['estado']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($encabezados) ?>">
                                    <div class="alert alert-info mb-0">No se encontraron solicitudes.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TARJETAS MÓVIL -->
    <div class="d-block d-md-none">
        <?php if (!empty($solicitudes)): ?>
            <?php foreach ($solicitudes as $s): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body pb-2">

                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div style="min-width:0;flex:1 1 0;">
                                <h6 class="fw-bold mb-0" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($s['investigador']) ?>
                                </h6>
                                <small class="text-muted d-block" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($s['correo_institucional']) ?>
                                </small>
                            </div>
                            <span class="badge rounded-pill bg-<?= $ctrl->estiloEstado($s['estado']) ?> flex-shrink-0">
                                <?= ucfirst($s['estado']) ?>
                            </span>
                        </div>

                        <hr class="my-2">
                        <div class="mb-2">
                            <small class="text-muted fw-semibold d-block mb-1">Tipo</small>
                            <span class="badge bg-<?= $s['tipo'] === 'sni' ? 'primary' : 'success' ?> rounded-pill">
                                <?= $ctrl->etiquetaTipo($s['tipo']) ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted fw-semibold d-block mb-1">Solicitud</small>
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= htmlspecialchars($s['valor_actual_nombre'] ?? '—') ?>
                                </span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-primary text-white">
                                    <?= htmlspecialchars($s['valor_nuevo_nombre'] ?? '—') ?>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                            <div>
                                <small class="text-muted fw-semibold d-block mb-1">Fecha</small>
                                <small><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></small>
                            </div>
                            <div>
                                <small class="text-muted fw-semibold d-block mb-1">Documento</small>
                                <?php if (!empty($s['nombre_archivo'])): ?>
                                    <a href="<?= htmlspecialchars($s['ruta']) ?>" target="_blank"
                                        class="btn btn-sm btn-outline-danger py-0 px-2">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted">Sin doc.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-center">
                            <?= $ctrl->botonesAccion($s['id_solicitudes_actualizacion'], $s['estado']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No se encontraron solicitudes.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $qBase = 'action=' . urlencode($action)
        . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '')
        . (!empty($tipo)   ? '&tipo='   . urlencode($tipo)   : '');
    $entidad = 'solicitudes';
    include __DIR__ . '../../../publico/incluido/_paginacion.php';
    ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Solicitudes académicas';
$bodyClass = 'solicitudes-page';
include __DIR__ . '/../../layout.php';
?>