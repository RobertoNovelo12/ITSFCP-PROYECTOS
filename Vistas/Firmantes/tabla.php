<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// VALIDACIÓN DE SESIÓN
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

$action  = isset($_GET['action']) ? $_GET['action'] : 'index';
$buscar  = $_GET['buscar'] ?? '';
$pagina  = intval($_GET['pagina'] ?? 1);

// CONTROLADOR DE FIRMANTES
include "../../Controladores/firmanteControlador.php";

$firmanteControlador = new firmanteControlador();

// ACCIÓN: Desactivar firmante (soft delete desde la tabla)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar_firmante') {
    $id_firmantes = intval($_GET['id_firmantes']);
    $firmanteControlador->eliminar($rol, $id_firmantes);
    header("Location: tabla.php");
    exit;
}

// ACCIÓN: Descargar firma digital (redirige al script de descarga)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'descargar_firma') {
    $id_firmantes = intval($_GET['id_firmantes']);
    $firmanteControlador->descargarFirma($rol, $id_firmantes);
    exit;
}

// OBTENER DATOS PARA LA TABLA
if (!method_exists($firmanteControlador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $firmanteControlador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$firmantes = $resultado['firmante'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total'         => count($firmantes),
    'por_pagina'    => 6,
    'pagina'        => $pagina,
    'total_paginas' => max(1, ceil(count($firmantes) / 6))
];

$filtros     = $firmanteControlador->filtros($rol);
$encabezados = $firmanteControlador->encabezadosPrincipal($rol);
$opciones    = $firmanteControlador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Firmantes</h3>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Firmante
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <!-- Selector de estado (Total / Activos / Desactivados) -->
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
        <!-- Buscador por nombre o cargo -->
        <div class="col-12 col-md-8">
            <form class="d-flex gap-2" method="GET" action="tabla.php">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar por nombre o cargo..."
                    value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-primary">
                    Buscar
                </button>
            </form>
        </div>
    </div>

         <!--TABLA ESCRITORIO-->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover text-center align-middle">
            <thead>
                <tr>
                    <?php foreach ($encabezados as $encabezado): ?>
                        <th><?= $encabezado ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rol == "supervisor"): ?>
                    <?php if (!empty($firmantes)): ?>
                        <?php foreach ($firmantes as $firm): ?>
                            <tr>
                                <!-- Nombre del firmante -->
                                <td><?= htmlspecialchars($firm['nombre']) ?></td>

                                <!-- Cargo -->
                                <td><?= htmlspecialchars($firm['cargo']) ?></td>

                                <!-- Firma digital: ícono de candado + botón de descarga -->
                                <td>
                                    <?php if (!empty($firm['firma_digital'])): ?>
                                        <span class="badge bg-success me-1">
                                            <i class="bi bi-shield-lock-fill"></i> Encriptada
                                        </span>
                                        <a
                                            href="tabla.php?action=descargar_firma&id_firmantes=<?= $firm['id_firmantes'] ?>"
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Descargar firma PNG">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Sin firma</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Estado -->
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= $firmanteControlador->EstiloEstadoLista($firm['estados']) ?>">
                                        <?= htmlspecialchars($firm['estados']) ?>
                                    </span>
                                </td>

                                <!-- Botones de acción -->
                                <td>
                                    <?= $firmanteControlador->botonesAccionPrincipal($firm['id_firmantes'], $rol, $firm['estados']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="alert alert-danger mb-0">
                                    No hay firmantes registrados
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="alert alert-danger mb-0">
                                No tiene permiso para administrar los firmantes
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MÓVIL  -->
    <div class="d-block d-md-none">
        <?php foreach ($firmantes as $firm): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= htmlspecialchars($firm['nombre']) ?>
                    </h5>
                    <p class="text-muted mb-1" style="font-size:0.9rem;">
                        <?= htmlspecialchars($firm['cargo']) ?>
                    </p>
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?= $firmanteControlador->EstiloEstadoLista($firm['estados']) ?>">
                            <?= htmlspecialchars($firm['estados']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-12">
                                <strong>Firma Digital</strong>
                                <p class="mb-0 mt-1">
                                    <?php if (!empty($firm['firma_digital'])): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-shield-lock-fill"></i> Encriptada
                                        </span>
                                        <a
                                            href="tabla.php?action=descargar_firma&id_firmantes=<?= $firm['id_firmantes'] ?>"
                                            class="btn btn-outline-primary btn-sm ms-1">
                                            <i class="bi bi-download"></i> Descargar
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Sin firma</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?= $firmanteControlador->botonesAccionPrincipal($firm['id_firmantes'], $rol, $firm['estados']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!--  PAGINACIÓN -->
    <?php if ($paginacion['total_paginas'] > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php
                $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                $fin    = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                ?>
                <li class="page-item disabled">
                    <span class="page-link">
                        Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?> firmantes
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
$titulo    = "Firmantes";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>
