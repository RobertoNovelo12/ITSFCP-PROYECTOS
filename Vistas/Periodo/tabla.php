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

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar = $_GET['buscar'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);

include "../../Controladores/periodo.php";

$periodoControlador = new periodoControlador();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_periodo') {
    $id_periodos = intval($_GET['id_periodos']);
    $periodoControlador->$action= "eliminar";
}


if (!method_exists($periodoControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $periodoControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$periodos = $resultado['periodo'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($periodos),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($periodos) / 6))
];

$filtros = $periodoControlador->filtros($rol);
$encabezados = $periodoControlador->encabezadosPrincipal($rol);
$opciones = $periodoControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Temáticas</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear temática
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS Y BUSQUEDA -->

    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
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
        <div class="col-12 col-md-8">
            <form class="d-flex gap-2" method="GET" action="tabla.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar temática..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">
                    Buscar
                </button>
            </form>
        </div>
    </div>
    <!-- TABLA LAPTOP -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover text-center align-middle">
            <thead>
                <tr>
                    <?php
                    foreach ($encabezados as $encabezado) {
                        echo "<th>{$encabezado}</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rol == "supervisor"): ?>
                    <?php foreach ($periodos as $per): ?>
                        <tr>
                            <td><?= $per['periodo'] ?></td>
                            <td>
                                <span class="badge rounded-pill text-bg-<?php echo $peraticaControlador->EstiloEstadoLista($per['estado']); ?>">
                                    <?= htmlspecialchars($per['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <?= date("d/m/Y", strtotime($per['Inicio'])) ?>
                                <br>
                                <?= date("H:i", strtotime($per['Inicio'])) ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($per['final'])) {
                                    echo date("d/m/Y", strtotime($per['final'])) . "<br>";
                                    echo date("H:i", strtotime($per['final']));
                                } else {
                                    echo "No modificado";
                                }
                                ?>
                            </td>
                            <td>
                                <?= $periodoControlador->botonesAccionPrincipal($per['id_periodos'], $rol, $per['estado']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="alert alert-danger">
                                No tiene permiso para editar la temática y subtemática
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MOVIL -->

    <div class="d-block d-md-none">
        <?php foreach ($periodos as $periodo_item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= $periodo_item['periodo'] ?>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($periodo_item['creacion'])) ?>
                                    <br>
                                    <?= date("H:i", strtotime($periodo_item['creacion'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Final</strong>
                                <br>
                                <?php if (!empty($periodo_item['modificacion'])) {
                                    echo date("d/m/Y", strtotime($periodo_item['modificacion'])) . "<br>";
                                    echo date("H:i", strtotime($periodo_item['modificacion']));
                                } else {
                                    echo "No modificado";
                                } ?>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Subtemáticas</strong>
                                <p class="mb-0">
                                    <?= $periodo_item['total'] ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Estado</strong>
                                <br>
                                <span class="badge rounded-pill text-bg-<?php echo $periodoControlador->EstiloEstadoLista($periodo_item['estado']); ?>">
                                    <?= htmlspecialchars($periodo_item['estado']) ?>
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $periodoControlador->botonesAccionPrincipal($periodo_item['id_periodos'], $rol, $periodo_item['estado']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINACION -->

    <?php if ($paginacion['total_paginas'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
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
$titulo = "Períodos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>
