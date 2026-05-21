<?php
// Periodo/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

//Solo supervisor
if (strtolower($rol ?? '') !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = $_GET['action'] ?? 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

include "../../Controladores/periodoControlador.php";

$periodoControlador = new periodoControlador();

/* Desactivar periodo por GET */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'desactivar_periodo') {
    $id_periodos = intval($_GET['id_periodos']);
    $periodoControlador->eliminar($id_periodos, $rol);
    header("Location: index.php");
    exit;
}

if (!method_exists($periodoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $periodoControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$periodos   = $resultado['periodo']    ?? [];
$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($periodos),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, ceil(count($periodos) / 6))
];

$filtros     = $periodoControlador->filtros($rol);
$encabezados = $periodoControlador->encabezadosPrincipal($rol);
$opciones    = $periodoControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Periodos</h3>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol === 'supervisor'): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Periodo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS Y BÚSQUEDA -->
    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <select class="form-select"
                    onchange="location.href='index.php?action=' + this.value;">
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
                       placeholder="Buscar..."
                       value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>
        </div>
    </div>

    <!-- AVISO CONTEXTUAL cuando se está viendo desactivados -->
    <?php if ($action === 'Desactivado'): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <div>
                Los periodos <strong>desactivados administrativamente</strong> se muestran aquí.
                Solo pueden reactivarse aquellos cuyo semestre aún no ha terminado.
                Los periodos de semestres pasados no pueden reactivarse.
            </div>
        </div>
    <?php endif; ?>

    <!-- TABLA (escritorio) -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $encabezado): ?>
                                <th><?= $encabezado ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rol === 'supervisor'): ?>
                            <?php if (!empty($periodos)): ?>
                                <?php foreach ($periodos as $per): ?>
                                    <tr class="<?= ($per['estados'] === 'Desactivado') ? 'table-secondary' : '' ?>">
                                        <td><?= htmlspecialchars($per['periodo']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($per['inicio'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($per['final'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($per['crear']))  ?></td>
                                        <td><?= date("H:i",   strtotime($per['crear']))  ?></td>
                                        <td>
                                            <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($per['estados']) ?>">
                                                <?= htmlspecialchars($per['estados']) ?>
                                            </span>
                                            <?php if ($per['estados'] === 'Desactivado' && !$per['puede_reactivar']): ?>
                                                <br>
                                                <small class="text-muted">Semestre pasado</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $periodoControlador->botonesAccionPrincipal(
                                                $per['id_periodos'],
                                                $rol,
                                                $per['estados'],
                                                $per['puede_reactivar'] ?? 0
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="alert alert-info mb-0">
                                            No hay periodos en esta categoría.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="alert alert-danger mb-0">
                                        No tiene permiso para ver los periodos.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TARJETAS (móvil) -->
    <div class="d-block d-md-none">
        <?php if (!empty($periodos)): ?>
            <?php foreach ($periodos as $periodo_item): ?>
                <div class="card shadow-sm mb-3 <?= ($periodo_item['estados'] === 'Desactivado') ? 'border-secondary' : '' ?>">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($periodo_item['periodo']) ?></h5>
                        <h5 class="fw-bold">
                            <span class="badge rounded-pill text-bg-<?= $periodoControlador->EstiloEstadoLista($periodo_item['estados']) ?>">
                                <?= htmlspecialchars($periodo_item['estados']) ?>
                            </span>
                        </h5>
                        <?php if ($periodo_item['estados'] === 'Desactivado' && !$periodo_item['puede_reactivar']): ?>
                            <small class="text-muted">Semestre pasado — no reactivable</small>
                        <?php endif; ?>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong>Fecha inicio</strong>
                                    <p class="mb-0">
                                        <?= date("d/m/Y", strtotime($periodo_item['inicio'])) ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <strong>Fecha final</strong>
                                    <p class="mb-0">
                                        <?= date("d/m/Y", strtotime($periodo_item['final'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-6">
                                    <strong>Fecha Creación</strong>
                                    <p class="mb-0">
                                        <?= date("d/m/Y", strtotime($periodo_item['crear'])) ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <strong>Hora Creación</strong>
                                    <p class="mb-0">
                                        <?= date("H:i", strtotime($periodo_item['crear'])) ?>
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div class="card-body">
                        <div class="d-flex justify-content-center gap-2">
                            <?= $periodoControlador->botonesAccionPrincipal(
                                $periodo_item['id_periodos'],
                                $rol,
                                $periodo_item['estados'],
                                $periodo_item['puede_reactivar'] ?? 0
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay periodos en esta categoría.</div>
        <?php endif; ?>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin    = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>
                <li class="page-item disabled">
                    <span class="page-link">
                        Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?> entradas
                    </span>
                </li>
                <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                    <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?action=<?= htmlspecialchars($action) ?>&pagina=<?= $i ?><?= !empty($buscar) ? '&buscar=' . urlencode($buscar) : '' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = "Períodos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>