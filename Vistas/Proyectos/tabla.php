<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

require_once "../../Controladores/proyectoControlador.php";
$proyectoControlador = new ProyectoControlador();

if (!method_exists($proyectoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $proyectoControlador->$action($id_usuario, $rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

if (!is_array($resultado)) {
    die("Error: La acción '$action' no devolvió un array válido.");
}

$proyectos = $resultado['proyectos'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total_proyectos' => count($proyectos),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($proyectos) / 6))
];

$filtros = $proyectoControlador->filtros($id_usuario, $rol);
$encabezados = $proyectoControlador->encabezados($rol);
$opciones = $proyectoControlador->datosopciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0 fw-bold">Proyectos</h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if ($rol == "investigador" || $rol == "profesor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear proyecto
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="row mb-3">
        <div class="col-12 text-end">
            <div class="row justify-content-end">

                <div class="col-md-6 mb-3">
                    <select class="form-select"
                        onchange="location.href='tabla.php?action=' + this.value;">
                        <?php foreach ($opciones as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= ($action === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <form class="d-flex gap-2" method="GET">
                        <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                        <input type="text" name="buscar" class="form-control"
                            placeholder="Buscar por titulo..."
                            value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA -->
    <div class="row">
        <div class="col-12">

            <?php if (!empty($proyectos)): ?>

                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover text-center align-middle">
                        <thead>
                            <tr>
                                <?php foreach ($encabezados as $encabezado): ?>
                                    <th><?= htmlspecialchars($encabezado) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <tr>

                                    <th><?= $proyecto['id_proyectos'] ?></th>

                                    <td title="<?= htmlspecialchars($proyecto['titulo']) ?>">
                                        <?= strlen($proyecto['titulo']) > 60
                                            ? substr($proyecto['titulo'], 0, 60) . '...'
                                            : $proyecto['titulo']; ?>
                                    </td>

                                    <td><?= $proyecto['fecha_inicio'] ?></td>
                                    <td><?= $proyecto['fecha_fin'] ?></td>

                                    <!-- ESTADO PROYECTO -->
                                    <td>
                                        <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                            <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                        </span>
                                    </td>

                                    <!-- ESTADO ESTUDIANTE SOLO SI ES ESTUDIANTE -->
                                    <?php if ($rol === 'estudiante'): ?>
                                        <td>
                                            <?php
                                            $estadoEst = $proyecto['estado_estudiante'] ?? 'activo';
                                            $clase = ($estadoEst == 'baja') ? 'danger'
                                                : (($estadoEst == 'concluido') ? 'success' : 'primary');
                                            ?>
                                            <span class="badge text-bg-<?= $clase ?>">
                                                <?= strtoupper($estadoEst) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td><?= $proyecto['periodo'] ?></td>
                                    <td><?= $proyecto['total'] ?></td>

                                    <td>
                                        <?= $proyectoControlador->botonesAccion(
                                            $proyecto['id_proyectos'],
                                            $rol,
                                            $proyecto['estado_proyecto'],
                                            $id_usuario,
                                            $proyecto['puede_cerrar'] ?? 0,
                                            $proyecto['estado_estudiante'] ?? null
                                        ); ?>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MÓVIL -->
                <div class="d-block d-md-none mt-4">
                    <?php foreach ($proyectos as $proyecto): ?>
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body">

                                <h5>ID: <?= $proyecto['id_proyectos'] ?></h5>
                                <p><strong>Título:</strong> <?= htmlspecialchars($proyecto['titulo']) ?></p>

                                <p>
                                    <strong>Estado proyecto:</strong>
                                    <span class="badge text-bg-<?= $proyectoControlador->EstiloEstado($proyecto['estado_proyecto']) ?>">
                                        <?= htmlspecialchars($proyecto['estado_proyecto']) ?>
                                    </span>
                                </p>

                                <?php if ($rol === 'estudiante'): ?>
                                    <p>
                                        <strong>Estado estudiante:</strong>
                                        <?php
                                        $estadoEst = $proyecto['estado_estudiante'] ?? 'activo';
                                        $clase = ($estadoEst == 'baja') ? 'danger'
                                            : (($estadoEst == 'concluido') ? 'success' : 'primary');
                                        ?>
                                        <span class="badge text-bg-<?= $clase ?>">
                                            <?= strtoupper($estadoEst) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>

                                <p><strong>Periodo:</strong> <?= $proyecto['periodo'] ?></p>
                                <p><strong>Pendientes:</strong> <?= $proyecto['total'] ?></p>

                                <div class="d-flex gap-2">
                                    <?= $proyectoControlador->botonesAccion(
                                        $proyecto['id_proyectos'],
                                        $rol,
                                        $proyecto['estado_proyecto'],
                                        null,
                                        $proyecto['puede_cerrar'] ?? 0,
                                        $proyecto['estado_estudiante'] ?? null
                                    ); ?>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="alert alert-info text-center">
                    No hay proyectos para mostrar
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Proyectos";
$bodyClass = "proyectos-page";
include __DIR__ . '/../../layout.php';
?>