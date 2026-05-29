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

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'index';

require_once __DIR__ . "/../../Controladores/ajustesTiposDocumentoscontrolador.php";

$ajustesTiposDocumentoscontrolador = new ajustesTiposDocumentoscontrolador();

if (!method_exists($ajustesTiposDocumentoscontrolador, $action)) {
    die("Error: La acción '$action' no existe en el controlador.");
}

$documentos = $ajustesTiposDocumentoscontrolador->$action($rol);
if (is_string($documentos)) {
    $documentos = json_decode($documentos, true);
}

$encabezados = $ajustesTiposDocumentoscontrolador->encabezadosPrincipal($rol);
$opciones = $ajustesTiposDocumentoscontrolador->opciones();

$msg   = $_GET['msg'] ?? '';
$_mapa = [
    // Éxitos
    'exito_editar'       => ['tipo' => 'exito',  'titulo_msg' => 'Cambios guardados',       'mensaje' => 'El tipo de documento fue actualizado correctamente.'],
    'exito_desactivar'   => ['tipo' => 'exito',  'titulo_msg' => 'Documento desactivado',   'mensaje' => 'El tipo de documento fue desactivado correctamente.'],
    'exito_reactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Documento reactivado',    'mensaje' => 'El tipo de documento fue reactivado correctamente.'],
    // Errores de operación
    'error_editar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al guardar',        'mensaje' => 'No fue posible guardar los cambios. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'   => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',     'mensaje' => 'No fue posible desactivar el tipo de documento.'],
    'error_reactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',      'mensaje' => 'No fue posible reactivar el tipo de documento.'],
    'error_duplicado'    => ['tipo' => 'error',  'titulo_msg' => 'Registro duplicado',      'mensaje' => 'Ya existe un tipo de documento con esos datos.'],
    'error_cargar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',         'mensaje' => 'No fue posible cargar la información. Intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    // Permisos
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',      'mensaje' => 'No tienes permiso para ver esta sección.'],
    'sin_argumentos_url' => ['tipo' => 'alerta', 'titulo_msg' => 'No se han proporcionado parámetros en la URL.',   'mensaje' => 'La acción solicitada no está disponible por falta de parámetros en la URL.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];
ob_start();

?>


<div class="container-fluid py-4 ancho_container">

    <!-- TITULO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Tipos de Documentos';
        $descripcion = 'Gestión de tipos de documentos del sistema';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-6 col-md-6">
        </div>
    </div>
    <!-- Mostrar la alerta: -->

    <?php if (isset($_mapa[$msg])) : extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>


    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-md-4 mb-1">
                    <label class="form-label mb-1 small fw-semibold">Estado</label>
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
            </div>
        </div>
    </div>
    <!-- TABLA LAPTOP -->
    <div class="card shadow-sm d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <th scope="row"><?= $doc['nombre'] ?? '-' ?></th>
                                    <td><?= htmlspecialchars(ucfirst($doc['categoria']) ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td title="<?= htmlspecialchars($doc['descripcion']) ?>">
                                        <?= strlen($doc['descripcion']) > 60
                                            ? substr($doc['descripcion'], 0, 60) . '...'
                                            : $doc['descripcion']; ?>
                                    </td>
                                    <th scope="row"><?= $doc['orden'] ?? '-' ?></th>
                                    <td>
                                        <span class="badge rounded-pill text-bg-<?php echo $ajustesTiposDocumentoscontrolador->EstiloEstado($doc['estados']); ?>">
                                            <?= htmlspecialchars($doc['estados']) ?>
                                        </span>
                                    </td>
                                    <!-- Acciones -->
                                    <td>
                                        <a href="editar.php?id_tipo_documento=<?= $doc['id_tipo_documento'] ?>" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
                                            data-bs-custom-class="custom-tooltip" data-bs-title="Editar Tipo de documento"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                                <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                                <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                            </svg></a>
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
        </div>
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
                <div class="card-body text-center">
                    <h5 class="fw-bold">
                        <span class="badge rounded-pill text-bg-<?php echo $ajustesTiposDocumentoscontrolador->EstiloEstado($doc_item['estados']); ?>">
                            <?= htmlspecialchars($doc_item['estados']) ?>
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
                            <div class="col-6">
                                <strong>Categoria</strong>
                                <p class="mb-0">
                                    <?= ucfirst($doc_item['categoria']) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Orden</strong>
                                <p class="mb-0">
                                    <?= $doc_item['orden'] ?>
                                </p>
                            </div>
                        </div>
                    </li>
                    <li class="list-group-item">
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>Fecha modificación</strong>
                                <p class="mb-0">
                                    <?= date("d/m/Y", strtotime($doc_item['modificar'])) ?>
                                </p>
                            </div>
                            <div class="col-6">
                                <strong>Hora modificación</strong>
                                <p class="mb-0">
                                    <?= date("H:i", strtotime($doc_item['modificar'])) ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="card-body">
                    <div class="d-flex justify-content-center gap-2">
                        <a href="editar.php?id_tipo_documento=<?= $doc['id_tipo_documento'] ?>" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-custom-class="custom-tooltip" data-bs-title="Editar Tipo de documento"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                                <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                            </svg></a>
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