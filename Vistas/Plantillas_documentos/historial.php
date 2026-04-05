<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}
$rol = $_SESSION['rol'];
$id = $_SESSION['id_usuario'];
$id_tipo_documento = $_GET['id_tipo_documento'] ?? null;

if ($id_tipo_documento == null) {
    die("ERROR: No se recibió id_tipo_documento");
}
require_once '../../Controladores/plantilladocumentoControlador.php';
$plantilladocumentoControlador = new plantilladocumentoControlador();

// DATOS
$resultado = $plantilladocumentoControlador->info_linea_tiempo($id_tipo_documento);

$historialAgrupado = $resultado['datos'];
$paginacion = $resultado['paginacion'];

// GENERAR VISTA
ob_start();

?>

<div class="container-fluid py-4">
    <?php include __DIR__ . '/../../mensaje.php'; ?>
    <div class="row mb-3 align-items-center">

        <div class="row mb-1">
            <div class="col-6">
                <h3>Historial de plantilla de documento</h3>
            </div>
            <div class="col-6 text-end">
                <a href="tabla.php?id_tipo_documento=<?= $id_tipo_documento; ?>" class="btn btn-danger">Regresar</a>
            </div>
        </div>



        <div class="row mb-1">
            <div class="card mb-3 p-3">
                <strong>Resumen</strong><br>
                Última actualización: <?= date("d/m/Y H:i") ?><br>
                Tipo de documento ID: <?= $id_tipo_documento ?>
            </div>
            <div class="mb-3">
                <ul class="timeline">

                    <?php foreach ($historialAgrupado as $version => $items): ?>

                        <li class="mb-4">
                            <div class="fw-bold text-primary">
                                <?= $version ?>
                            </div>

                            <?php foreach ($items as $item): ?>

                        <li>

                            <div class="timeline-content">

                                <span class="badge bg-<?= $plantilladocumentoControlador->EstiloTimeLine($item['tipo_evento']) ?>">
                                    <?= ucfirst(strtolower($item['tipo_evento'])) ?>
                                </span>

                                <small class="text-muted">
                                    <?= date("d/m/Y H:i", strtotime($item['fecha'])) ?>
                                </small>

                                <p><?= $item['descripcion'] ?></p>

                                <small>
                                    <?= $item['usuario'] ?? 'Sistema' ?>
                                </small>
                                <small class="descargar">
                                    <?php if (!empty($item['nombre_archivo'])): ?>
                                        <a href="descargar_plantilla.php?id_plantilla=<?= $item['id_plantilla'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="red" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
                                                <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z" />
                                                <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span>SN</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </li>
                    <?php endforeach; ?>

                <?php endforeach; ?>
                </li>
                </ul>
            </div>
            <?php if ($paginacion['total_paginas'] > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">

                        <?php
                        $inicio = ($paginacion['pagina'] - 1) * $paginacion['por_pagina'] + 1;
                        $fin = min($inicio + $paginacion['por_pagina'] - 1, $paginacion['total']);
                        ?>

                        <li class="page-item disabled">
                            <span class="page-link">
                                Mostrando <?= $inicio ?> a <?= $fin ?> de <?= $paginacion['total'] ?> eventos
                            </span>
                        </li>

                        <?php for ($i = 1; $i <= $paginacion['total_paginas']; $i++): ?>
                            <li class="page-item <?= ($i == $paginacion['pagina']) ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="?id_tipo_documento=<?= $id_tipo_documento ?>&pagina=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                    </ul>
                </nav>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo = "Historial de plantilla de documento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>