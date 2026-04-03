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

$ajustesTiposDocumentoscontrolador = new ajustesTiposDocumentoscontrolador();

if (!method_exists($ajustesTiposDocumentoscontrolador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$resultado = $ajustesTiposDocumentoscontrolador->$action($rol, $buscar);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}

$documentos = $resultado['documentos'] ?? [];

$encabezados = $ajustesTiposDocumentoscontrolador->encabezadosPrincipal($rol);
$opciones = $ajustesTiposDocumentoscontrolador->opciones($rol, $filtros);

ob_start();
include __DIR__ . '/../../mensaje.php';
?>

<div class="container-fluid py-4">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">

        <div class="col-12 col-md-6">
            <h3 class="fw-bold mb-2 mb-md-0">Ajustes de tipos de documentación</h3>
        </div>
    </div>

    <!-- FILTROS -->

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
                            <th scope="row"><?= $cate['orden'] ?? '-' ?></th>
                            <th scope="row"><?= $cate['obligatorio'] ?? '-' ?></th>
                            <!-- Acciones -->
                            <td>
                                <a href="editar.php?id_tipo_documento=<?= $id_tipo_documento ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="alert alert-danger">
                                No tiene permiso para editar los tipos de documentos
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS MOVIL -->

    <div class="d-block d-md-none">
        <?php foreach ($documentos as $doc_item): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <?= $doc_item['nombre'] ?>
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
                            <div class="col-6">
                                <strong>Creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($cate['orden'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Creación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($cate['fecha_obligatoriocreado'])) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <a href="editar.php?id_tipo_documento=<?= $id_tipo_documento ?>">Editar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<?php

$contenido = ob_get_clean();
$titulo = "Ajustes de tipos documentos";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';

?>