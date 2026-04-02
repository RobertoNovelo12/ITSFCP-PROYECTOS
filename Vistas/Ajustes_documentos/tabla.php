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

include "../../Controladores/ajustesDocumentoscontrolador.php";

$ajustesConstanciascontrolador = new ajustesDocumentoscontrolador();
if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar') {
    $id_tipo_documento = intval($_GET['id_tipo_documento']);

    $ajustesConstanciascontrolador->desactivar($id_tipo_documento, $rol);

    // Redirigir para evitar doble ejecución
    header("Location: tabla.php");
    exit;
}

if (!method_exists($ajustesConstanciascontrolador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $ajustesConstanciascontrolador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$documentos = $resultado['documentos'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($documentos),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($documentos) / 6))
];

$filtros = $ajustesConstanciascontrolador->filtros($rol);
$encabezados = $ajustesConstanciascontrolador->encabezadosPrincipal($rol);
$opciones = $ajustesConstanciascontrolador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Ajustes de documentación</h3>
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
                    placeholder="Buscar categoría..."
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
                    <?php foreach ($documentos as $cate): ?>
                        <tr>
                            <th scope="row"><?= $cate['id_tipo_documento'] ?? '-' ?></th>
                            <th scope="row"><?= $cate['nombre'] ?? '-' ?></th>
                            <td><?= htmlspecialchars($cate['categoria'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td title="<?= htmlspecialchars($cate['descripcion']) ?>">
                                <?= strlen($cate['descripcion']) > 60
                                    ? substr($cate['descripcion'], 0, 60) . '...'
                                    : $cate['descripcion']; ?>
                            </td>
                            <td>
                                <?= date("d/m/Y", strtotime($cate['fecha_creado'])) ?>
                            </td>

                            <!-- Acciones -->
                            <td>
                                <?= $ajustesConstanciascontrolador->botonesAccion(
                                    $cate['id_tipo_documento'] ?? 0,
                                    $rol,
                                    $cate['estado'] ?? '-',
                                    $id_usuario
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="alert alert-danger">
                                No tiene permiso para editar los documentos
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MOVIL -->
    <th scope="row"><?= $cate['id_tipo_documento'] ?? '-' ?></th>
    <td><?= htmlspecialchars($cate['categoria'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
    <td title="<?= htmlspecialchars($cate['descripcion']) ?>">
        <?= strlen($cate['descripcion']) > 60
            ? substr($cate['descripcion'], 0, 60) . '...'
            : $cate['descripcion']; ?>
    </td>

    <!-- Acciones -->
    <td>
        <?= $ajustesConstanciascontrolador->botonesAccion(
            $cate['id_tipo_documento'] ?? 0,
            $rol,
            $cate['estado'] ?? '-',
            $id_usuario
        ); ?>
    </td>
    <div class="d-block d-md-none">
        <?php foreach ($documentos as $doc_item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= $doc_item['nombre'] ?>
                    </h5>
                </div>
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $ajustesConstanciascontrolador->EstiloEstadoLista($doc_item['estado']); ?>">
                            <?= htmlspecialchars($tematica_item['estado']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>Descripción</strong>
                        <p class="mb-0" title="<?= htmlspecialchars($doc_item['descripcion']) ?>">
                            <?= strlen($doc_item['descripcion']) > 60
                                ? substr($doc_item['descripcion'], 0, 60) . '...'
                                : $doc_item['descripcion']; ?>
                        </p>
                    </li>
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-12">
                                <strong>Creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($cate['fecha_creado'])) ?>

                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $ajustesConstanciascontrolador->botonesAccionPrincipal($doc_item['id_tipo_documento'], $rol, $doc_item['estado']); ?>
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
$titulo = "Ajustes de documentos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>