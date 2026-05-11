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

require_once '../../Controladores/plantilladocumentoControlador.php';

$action = $_POST['action'] ?? null;
$plantilladocumentoControlador = new plantilladocumentoControlador();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {
    $id_tipo_documento = $_POST['id_tipo_documento'] ?? '';
    $nombre            = $_POST['nombre'] ?? '';
    $archivo           = $_FILES['archivo'] ?? null;

    if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        die("Error al subir archivo");
    }

    // Validar extensión
    $extensionesPermitidas = ['doc', 'docx'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionesPermitidas)) {
        die("Solo se permiten archivos Word (.doc, .docx)");
    }

    // Validar MIME
    $mimePermitidos = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    if (!in_array($archivo['type'], $mimePermitidos)) {
        die("Tipo de archivo no válido");
    }

    // Nombre único en disco
    $nombreFinal = uniqid() . '_' . basename($archivo['name']);

    // Subcarpeta según tipo de documento
    $prefixNombre = strtolower($nombre);
    if (str_starts_with($prefixNombre, 'carta')) {
        $carpeta = 'carta';
    } elseif (str_starts_with($prefixNombre, 'informe')) {
        $carpeta = 'informe';
    } elseif (str_starts_with($prefixNombre, 'reporte')) {
        $carpeta = 'reporte';
    } else {
        $carpeta = 'general';
    }

    // Rutas — ahora bajo /storage/plantillas/ (visibilidad privada)
    $base        = "/ITSFCP-PROYECTOS/storage/plantillas/supervisor_{$id_usuario}/{$carpeta}/";
    $rutaFisica  = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
    $rutaBD      = $base . $nombreFinal;

    if (!is_dir(dirname($rutaFisica))) {
        mkdir(dirname($rutaFisica), 0755, true);
    }

    if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
        die("Error al guardar el archivo");
    }

    $plantilladocumentoControlador->registrar(
        $rol,
        $nombre,
        $nombreFinal,   // nombre físico en disco
        $rutaBD,        // ruta completa
        $extension,     // extensión sin punto
        $archivo['type'], // mime
        $archivo['size'],
        $id_tipo_documento,
        $id_usuario
    );
}


$resultado = $plantilladocumentoControlador->indexcrear($rol);

if (is_string($resultado)) {
    $resultado = json_decode($resultado, true);
}


ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">
    <!-- ENCABEZADO -->
    <div class="row mb-3">
        <div class="col-6">
            <h3>Crear Plantilla de documento</h3>
        </div>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>
    <!-- DATOS Plantilla de documento -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="Registrar">
        <div class="col-md">
            <div class="mb-3">
                <label for="select" class="form-label">Tipo de documento</label>
                <select class="form-select" name="id_tipo_documento" id="select" aria-label="Default select example">
                    <?php foreach ($resultado as $res): ?>
                        <option value="<?php echo $res['id_tipo_documento'] ?>" Select><?php echo $res['nombre'] . " - " . ucfirst($res['categoria']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="exampleFormControlInput" class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre" id="nombre" readonly required>
        </div>
        <div class="mb-3">
            <label for="exampleFormControlInput1" class="form-label">Versión</label>
            <input type="number" class="form-control" name="version" id="version" readonly required>
        </div>

        <div class="mb-3">
            <label class="form-label">Plantilla de documento</label>
            <input type="file" name="archivo" class="form-control"
                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                required>
            <div class="form-text">
                <i class="bi bi-info-circle"></i>
                Solo se aceptan archivos <strong>.doc y .docx</strong>.
            </div>
        </div>

        <button type="submit" class="btn btn-guardar">Guardar cambios</button>
    </form>
</div>

<?php

$contenido = ob_get_clean();
$titulo = "Crear Plantilla de documento";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>

<script>
    document.addEventListener("DOMContentLoaded", function() {

        const selectTipo_documento = document.getElementById("select");
        const selectNombre = document.getElementById("nombre");
        const selectVersion = document.getElementById("version");


        function cargarPlantillas() {

            const idTipoDocumento = selectTipo_documento.value;
            if (!idTipoDocumento) return;

            fetch("/ITSFCP-PROYECTOS/Ajax/plantilla_documento.php?tipo_documento=" + idTipoDocumento)
                .then(r => r.json())
                .then(data => {

                    document.getElementById("nombre").value = data.nombre;
                    document.getElementById("version").value = data.version;
                });
        }

        // Evento al cambiar tipo de documento
        selectTipo_documento.addEventListener("change", cargarPlantillas);

        // cargar automáticamente al abrir
        if (selectTipo_documento.value) {
            cargarPlantillas();
        }
    });
</script>