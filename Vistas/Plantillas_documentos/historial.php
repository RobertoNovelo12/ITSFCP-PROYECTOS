<?php
// Vistas/Plantillas_documentos/historial.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');

include __DIR__ .  '../../../publico/incluido/_validar_get.php';

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$id_tipo_documento = (int)($_GET['id_tipo_documento'] ?? 0);

$id_validar = $id_tipo_documento;
include __DIR__ .  '../../../publico/incluido/_validar_id.php';

require_once __DIR__ .  '/../../Controladores/plantilladocumentoControlador.php';

$ctrl = new plantilladocumentoControlador();

$resultado = $ctrl->info_linea_tiempo($id_tipo_documento);

// Validación
$registro = $resultado;
include __DIR__ .  '../../../publico/incluido/_validar_datos.php';

$historialAgrupado = $resultado['datos']      ?? [];
$paginacion        = $resultado['paginacion'] ?? [
    'total'         => 0,
    'por_pagina'    => 5,
    'pagina'        => 1,
    'total_paginas' => 1,
];

// Obtener la última fecha real del historial
$ultima_fecha = null;
foreach ($historialAgrupado as $items) {
    foreach ($items as $item) {
        if (!$ultima_fecha || strtotime($item['fecha']) > strtotime($ultima_fecha)) {
            $ultima_fecha = $item['fecha'];
        }
    }
}

//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',      'mensaje' => 'No fue posible cargar el historial. Intenta de nuevo.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- CABECERA -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Historial de Plantilla';
        $descripcion = 'Versiones anteriores de la plantilla';
        require_once __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6 text-md-end mb-2 mb-md-0 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>
    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        require_once __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>


    <!-- RESUMEN -->
    <div class="card shadow-sm p-3 mb-4">
        <h5 class="mb-3"><b>Resumen</b></h5>
        <div class="row">
            <div class="col-md-4">
                <strong>Tipo de documento ID:</strong><br>
                <?= $id_tipo_documento ?>
            </div>
            <div class="col-md-4">
                <strong>Total de eventos:</strong><br>
                <?= $paginacion['total'] ?? 0 ?>
            </div>
            <div class="col-md-4">
                <strong>Última actualización:</strong><br>
                <?= $ultima_fecha
                    ? date('d/m/Y H:i', strtotime($ultima_fecha))
                    : 'Sin historial' ?>
            </div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <?php if (empty($historialAgrupado)): ?>

        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle"></i><br><br>
            No hay historial registrado para este tipo de documento.
        </div>

    <?php else: ?>

        <ul class="timeline_historial list-unstyled">

            <?php foreach ($historialAgrupado as $version => $items): ?>

                <li class="mb-4">

                    <!-- VERSIÓN como título de grupo -->
                    <div class="fw-bold text-primary mb-2">
                        <?= htmlspecialchars($version) ?>
                    </div>

                    <?php foreach ($items as $item): ?>

                        <div class="card shadow-sm mb-2">
                            <div class="card-body p-2">

                                <!-- BADGE de evento -->
                                <span class="badge bg-<?= $ctrl->EstiloTimeLine($item['tipo_evento']) ?>">
                                    <?= ucfirst(strtolower(htmlspecialchars($item['tipo_evento']))) ?>
                                </span>

                                <!-- HORA -->
                                <small class="text-muted ms-2">
                                    <?= date('H:i', strtotime($item['fecha'])) ?>
                                </small>

                                <!-- FECHA COMPLETA -->
                                <small class="text-muted ms-1">
                                    · <?= date('d/m/Y', strtotime($item['fecha'])) ?>
                                </small>

                                <!-- DESCRIPCIÓN -->
                                <p class="mb-1 mt-2">
                                    <?= htmlspecialchars($item['descripcion'] ?? 'Sin descripción') ?>
                                </p>

                                <!-- USUARIO -->
                                <small class="text-secondary">
                                    <?= htmlspecialchars($item['usuario'] ?? 'Sistema') ?>
                                </small>

                                <!-- ARCHIVO (si tiene documento adjunto) -->
                                <?php if (!empty($item['nombre_archivo'])): ?>
                                    <small class="descargar ms-2">
                                        <a href="descargar_plantilla.php?id_plantilla=<?= (int)$item['id_plantilla'] ?>"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Descargar <?= htmlspecialchars($item['nombre_archivo']) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                fill="red" class="bi bi-file-earmark-word-fill" viewBox="0 0 16 16">
                                                <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0
                                                         2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293
                                                         0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M5.485 6.879l1.036
                                                         4.144.997-3.655a.5.5 0 0 1 .964 0l.997 3.655 1.036-4.144a.5.5
                                                         0 0 1 .97.242l-1.5 6a.5.5 0 0 1-.967.01L8
                                                         9.402l-1.018 3.73a.5.5 0 0 1-.967-.01l-1.5-6a.5.5 0 1 1 .97-.242z" />
                                            </svg>
                                        </a>
                                    </small>
                                <?php endif; ?>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </li>

            <?php endforeach; ?>

        </ul>

        <!-- PAGINACIÓN -->
        <?php if ($paginacion['total_paginas'] > 1):
            $qBase   = 'id_tipo_documento=' . $id_tipo_documento;
            $entidad = 'eventos';
            require_once __DIR__ . '../../../publico/incluido/_paginacion.php';
        endif; ?>

    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Historial de plantilla de documento';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
