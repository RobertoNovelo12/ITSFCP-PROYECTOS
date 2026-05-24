<?php
/**
 * Plantillas_documentos/index.php
 * Listado principal de plantillas de documentos — solo supervisor.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int) $_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once '../../Controladores/plantilladocumentoControlador.php';

$ctrl   = new plantilladocumentoControlador();
$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

// Acciones permitidas para el listado
$accionesPermitidas = ['index', 'Total', 'Activo', 'Desactivado'];
if (!in_array($action, $accionesPermitidas, true)) {
    $action = 'index';
}

// Acciones de escritura vía GET (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($action === 'desactivar') {
        $id_plantilla = (int) ($_GET['id_plantilla'] ?? 0);
        $ctrl->desactivar($rol, $id_plantilla, $id_usuario);
        // desactivar() hace header() + exit()
    }

    if ($action === 'reactivar') {
        $id_plantilla = (int) ($_GET['id_plantilla'] ?? 0);
        $ctrl->reactivar($rol, $id_plantilla, $id_usuario);
        // reactivar() hace header() + exit()
    }
}

// Obtener datos para la vista
$resultado   = $ctrl->$action($rol, $buscar);
$registros   = $resultado['plantillas']  ?? [];
$paginacion  = $resultado['paginacion']  ?? [
    'total'        => 0,
    'por_pagina'   => 6,
    'pagina'       => $pagina,
    'total_paginas'=> 1,
];

$filtros     = $ctrl->filtros($rol);       // array asociativo Total/Activo/Desactivado
$encabezados = $ctrl->encabezadosPrincipal($rol);
$opciones    = $ctrl->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TÍTULO -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Plantillas de documentos</h3>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <a href="crear.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Crear Plantilla
            </a>
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <select class="form-select"
                onchange="location.href='index.php?action=' + this.value">
                <?php foreach ($opciones as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"
                        <?= ($action === $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-8">
            <form class="d-flex gap-2" method="GET" action="index.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar por nombre..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>
    </div>

    <?php if (!empty($registros)): ?>

        <!-- TABLA ESCRITORIO -->
        <div class="card shadow-sm d-none d-md-block mb-3">
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
                            <?php foreach ($registros as $reg): ?>
                                <tr>
                                    <!-- Nombre -->
                                    <td><?= htmlspecialchars($reg['nombre']) ?></td>

                                    <!-- Versión -->
                                    <td><?= (int) $reg['version'] ?></td>

                                    <!-- Fecha creación -->
                                    <td>
                                        <?= !empty($reg['crear'])
                                            ? date('d/m/Y', strtotime($reg['crear'])) . '<br>'
                                              . '<small class="text-muted">' . date('H:i', strtotime($reg['crear'])) . '</small>'
                                            : '—' ?>
                                    </td>

                                    <!-- Fecha modificación -->
                                    <td>
                                        <?= !empty($reg['modificar'])
                                            ? date('d/m/Y', strtotime($reg['modificar'])) . '<br>'
                                              . '<small class="text-muted">' . date('H:i', strtotime($reg['modificar'])) . '</small>'
                                            : '—' ?>
                                    </td>

                                    <!-- Archivo -->
                                    <td>
                                        <?php if (!empty($reg['nombre_archivo'])): ?>
                                            <a href="descargar_plantilla.php?id_plantilla=<?= (int) $reg['id_plantilla'] ?>"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="<?= htmlspecialchars($reg['nombre_archivo']) ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    fill="red" class="bi bi-file-earmark-word-fill" viewBox="0 0 16 16">
                                                    <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0
                                                             2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293
                                                             0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M5.485 6.879l1.036
                                                             4.144.997-3.655a.5.5 0 0 1 .964 0l.997 3.655 1.036-4.144a.5.5
                                                             0 0 1 .97.242l-1.5 6a.5.5 0 0 1-.967.01L8
                                                             9.402l-1.018 3.73a.5.5 0 0 1-.967-.01l-1.5-6a.5.5 0 1 1 .97-.242z"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin archivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?= $ctrl->EstiloEstado($reg['estado_texto']) ?>">
                                            <?= htmlspecialchars($reg['estado_texto']) ?>
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <?= $ctrl->botonesAccionPrincipal(
                                            (int) $reg['id_plantilla'],
                                            $rol,
                                            $reg['estado_texto'],
                                            (int) $reg['id_tipo_documento']
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TARJETAS MÓVIL -->
        <div class="d-block d-md-none">
            <?php foreach ($registros as $reg): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($reg['nombre']) ?></h5>
                        <span class="badge rounded-pill text-bg-<?= $ctrl->EstiloEstado($reg['estado_texto']) ?>">
                            <?= htmlspecialchars($reg['estado_texto']) ?>
                        </span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Versión</strong>
                                    <p class="mb-0"><?= (int) $reg['version'] ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Archivo</strong>
                                    <p class="mb-0">
                                        <?php if (!empty($reg['nombre_archivo'])): ?>
                                            <a href="descargar_plantilla.php?id_plantilla=<?= (int) $reg['id_plantilla'] ?>">
                                                Descargar
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Sin archivo</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Creación</strong>
                                    <p class="mb-0 small">
                                        <?= !empty($reg['crear']) ? date('d/m/Y H:i', strtotime($reg['crear'])) : '—' ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <strong>Modificación</strong>
                                    <p class="mb-0 small">
                                        <?= !empty($reg['modificar']) ? date('d/m/Y H:i', strtotime($reg['modificar'])) : '—' ?>
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $ctrl->botonesAccionPrincipal(
                                (int) $reg['id_plantilla'],
                                $rol,
                                $reg['estado_texto'],
                                (int) $reg['id_tipo_documento']
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php
        $qBase   = 'action=' . urlencode($action)
                 . (!empty($buscar) ? '&buscar=' . urlencode($buscar) : '');
        $entidad = 'plantillas';
        include __DIR__ . '../../../publico/incluido/_paginacion.php';?>

    <?php else: ?>
        <div class="alert alert-info text-center">
            No hay plantillas registradas<?= !empty($buscar) ? ' para "' . htmlspecialchars($buscar) . '"' : '' ?>.
        </div>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Plantillas de documentos';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
?>