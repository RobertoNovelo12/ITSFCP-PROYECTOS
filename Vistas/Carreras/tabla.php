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

include "../../Controladores/carreraControlador.php";

$carreraControlador = new carreraControlador();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_carrera') {
    $id_carrera = intval($_GET['id_carrera']);

    $carreraControlador->eliminar($rol, $id_carrera);

    header("Location: tabla.php");
    exit;
}

if (!method_exists($carreraControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $carreraControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$carreras = $resultado['carrera'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($carreras),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($carreras) / 6))
];

$filtros = $carreraControlador->filtros($rol);
$encabezados = $carreraControlador->encabezadosPrincipal($rol);
$opciones = $carreraControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Carreras</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Carrera
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
                    placeholder="Buscar..."
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
                    <?php if (!empty($carreras)) { ?>
                        <?php foreach ($carreras as $car): ?>
                            <tr>
                                <td><?= htmlspecialchars($car['nombre_carrera']) ?></td>
                                <td>
                                    <?= date("d/m/Y", strtotime($car['crear'])) ?>
                                </td>
                                <td>
                                    <?= date("H:i", strtotime($car['crear'])) ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?php echo $carreraControlador->EstiloEstadoLista($car['estados']); ?>">
                                        <?= htmlspecialchars($car['estados']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $carreraControlador->botonesAccionPrincipal($car['id_carrera'], $rol, $car['estados']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php } else { ?>
                        <td colspan="5">
                            <div class="alert alert-danger">
                                No hay carreras registradas
                            </div>
                        </td>
                        </tr>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="alert alert-danger">
                                No tiene permiso para ver las carreras
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MOVIL -->

    <div class="d-block d-md-none">
        <?php foreach ($carreras as $car): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= htmlspecialchars($car['nombre_carrera']) ?>
                    </h5>
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $carreraControlador->EstiloEstadoLista($car['estados']); ?>">
                            <?= htmlspecialchars($car['estados']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($car['crear'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Hora creación</strong>
                                <p class="mb-0">
                                    <?= date("H:i", strtotime($car['crear'])) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $carreraControlador->botonesAccionPrincipal($car['id_carrera'], $rol, $car['estados']); ?>
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
$titulo = "Carreras";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>
