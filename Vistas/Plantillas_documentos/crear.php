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
$plantilladocumento = new plantilladocumentoControlador();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {
    $nombre = $_POST['nombre'] ?? '';
    $archivo = $_FILES['archivo'] ?? null;

    if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        die("Error al subir archivo");
    }

    // Validar extensión
    $extensionesPermitidas = ['doc', 'docx'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        die("Solo se permiten archivos Word (.doc, .docx)");
    }

    // Validar MIME (seguridad extra)
    $mimePermitidos = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($archivo['type'], $mimePermitidos)) {
        die("Tipo de archivo no válido");
    }

    // Crear nombre único
    $nombreFinal = uniqid() . "_" . basename($archivo['name']);

    $parte_carta = substr(strtolower($nombre), 0, 5);
    $parte_informe_reporte = substr(strtolower($nombre), 0, 7);
    $carpeta = "";
    if ($parte_carta == "carta") {
        $carpeta = "carta";
    } elseif ($parte_informe_reporte == "informe") {
        $carpeta = "informe";
    } elseif ($parte_informe_reporte == "reporte") {
        $carpeta = "reporte";
    }
    $rutaDestino = "../publico/docs/plantillas/.$carpeta./" . $nombreFinal;

    // Crear carpeta si no existe
    if (!is_dir("../publico/docs/plantillas")) {
        mkdir("../publico/docs/plantillas", 0777, true);
    }

    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        die("Error al guardar el archivo");
    }

    // Guardar en BD
    $plantilla = new plantilladocumento($conn);
    $plantilla->registrar($nombre, $version, $rutaDestino);
}


$resultado = $plantilladocumento->indexcrear($rol, $buscar);

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
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>
    <!-- DATOS Plantilla de documento -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="registrar">
        <div class="col-md">
            <div class="mb-3">
                <label for="select" class="form-label">Tipo de documento</label>
                <select class="form-select" name="Tematica" id="select" aria-label="Default select example">
                    <?php foreach ($resultado as $res): ?>
                        <option value="<?php echo $res['id_plantilla'] ?>"><?php echo $res['nombre'] ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="exampleFormControlInput" class="form-label">Nombre</label>
            <input type="text" class="form-control" name="NombreProyecto" id="nombre" readonly required>
        </div>
        <div class="mb-3">
            <label for="exampleFormControlInput1" class="form-label">Versión</label>
            <input type="text" class="form-control" name="NombreProyecto" id="version" readonly required>
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

        <button type="submit" name="action" value="Registrar" class="btn btn-guardar">Guardar cambios</button>
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


        function cargarSubtematicas() {

            const idTipoDocumento = selectTipo_documento.value;
            if (!idTematica) return;

            fetch("/ITSFCP-PROYECTOS/Ajax/tipo_documento.php?tipo_documento=" + idTipoDocumento)
                .then(r => r.json())
                .then(data => {                  

                    data.forEach(item => {
                    selectNombre.innerHTML = item.nombre;
                    selectVersion.innerHTML = item.version;
                    });
                });
        }

        // Evento al cambiar temática
        selectTematica.addEventListener("change", cargarSubtematicas);

        // cargar automáticamente al abrir
        if (selectTematica.value) {
            cargarSubtematicas();
        }
    });
</script>