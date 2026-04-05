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

include "../../Controladores/plantilladocumentoControlador.php";

$plantilladocumento = new plantilladocumentoControlador();

if (!method_exists($plantilladocumento, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'desactivar') {
    $id_plantilla = intval($_GET['id_plantilla']);
    $plantilladocumento->desactivar($rol, $id_plantilla, $id_usuario);

    // Redirigir para evitar doble ejecución
    header("Location: tabla.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && $action == 'reactivar') {
    $id_plantilla = intval($_GET['id_plantilla']);
    $plantilladocumento->reactivar($rol, $id_plantilla, $id_usuario);

    // Redirigir para evitar doble ejecución
    header("Location: tabla.php");
    exit;
}

$resultado = $plantilladocumento->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$registros = $resultado['plantillas'] ?? [];

$paginacion = $resultado['paginacion'] ?? [
    'total' => count($registros),
    'por_pagina' => 6,
    'pagina' => $pagina,
    'total_paginas' => max(1, ceil(count($registros) / 6))
];

$filtros = $plantilladocumento->filtros($rol);
$encabezados = $plantilladocumento->encabezadosPrincipal($rol);
$opciones = $plantilladocumento->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Plantillas de documentos</h3>
        </div>

        <div class="col-12 col-md-6 text-md-end">
            <?php if ($rol == "supervisor"): ?>
                <a href="crear.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Crear Plantilla
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
                    <?php if (!empty($registros)) { ?>
                        <?php foreach ($registros as $reg): ?>
                            <tr>
                                <td><?= htmlspecialchars($reg['nombre']) ?></td>
                                <td>
                                    <?= htmlspecialchars($reg['version']) ?>
                                </td>
                                <td>
                                    <?= date("d/m/Y", strtotime($reg['crear'])) . "<br>" . date("H:i", strtotime($reg['crear'])) ?>
                                </td>
                                <td>
                                    <?= date("d/m/Y", strtotime($reg['modificar'])) . "<br>" . date("H:i", strtotime($reg['modificar'])) ?>
                                </td>

                                <td>
                                    <?php if (!empty($reg['nombre_archivo'])): ?>
                                        <a href="descargar_plantilla.php?id_plantilla=<?= $reg['id_plantilla'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="red" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                                <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z" />
                                                <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span>SN</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?php echo $plantilladocumento->EstiloEstado($reg['activo']); ?>">
                                        <?= htmlspecialchars($reg['activo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $plantilladocumento->botonesAccionPrincipal($reg['id_plantilla'], $rol, $reg['activo'], $reg['id_tipo_documento']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php } else { ?>
                        <td colspan="7">
                            <div class="alert alert-danger">
                                No hay Plantillas registradas
                            </div>
                        </td>
                        </tr>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="alert alert-danger">
                                No tiene permiso para ver Plantillas
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MOVIL -->
    <div class="d-block d-md-none">
        <?php foreach ($registros as $reg): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= htmlspecialchars($reg['nombre']) ?>
                    </h5>
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $plantilladocumento->EstiloEstado($reg['activo']); ?>">
                            <?= htmlspecialchars($reg['activo']) ?>
                        </span>
                    </h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($reg['crear'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Hora creación</strong>
                                <p class="mb-0">
                                    <?= date("H:i", strtotime($reg['crear'])) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha modificación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($reg['modificar'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Hora modificación</strong>
                                <p class="mb-0">
                                    <?= date("H:i", strtotime($reg['modificar'])) ?>
                                </p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Versión</strong>
                                <p class="mb-0">
                                    <?= htmlspecialchars($reg['version']) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Versión</strong>
                                <p class="mb-0">

                                    <td>
                                        <?php if (!empty($reg['nombre_archivo'])): ?>
                                            <a href="descargar_plantilla.php?id_plantilla=<?= $reg['id_plantilla'] ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="red" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                                    <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z" />
                                                    <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103" />
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span>SN</span>
                                        <?php endif; ?>
                                    </td>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <?php echo $plantilladocumento->botonesAccionPrincipal($reg['id_plantilla'], $rol, $reg['activo'], $reg['id_tipo_documento']); ?>
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
$titulo = "Plantillas de documentos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>