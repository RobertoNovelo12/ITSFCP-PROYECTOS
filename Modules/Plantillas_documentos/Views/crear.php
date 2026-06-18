<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = (int)$_SESSION['id_usuario'];

if ($rol !== 'supervisor') {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}

require_once __DIR__ . '/../Controller/plantilla_documento_controller.php';
require_once __DIR__ . '/../../../public/incluido/FileUploader.php';

$ctrl   = new plantilladocumentoControlador();
$action = $_POST['action'] ?? null;

//  Mapa de mensajes del sistema $_mapa (errores que llegan tras redirect) 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'error_crear'         => ['tipo' => 'error',  'titulo_msg' => 'Error al crear',      'mensaje' => 'No fue posible registrar la plantilla. Intenta de nuevo.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe una plantilla con esos datos.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

//  Mapa de errores de validación de archivo (llegan por ?error=clave) 
$errorMsgs = [
    'datos_invalidos' => 'Faltan datos obligatorios. Intenta de nuevo.',
    'upload_error'    => 'Ocurrió un error al subir el archivo. Verifica el archivo e intenta de nuevo.',
    'file_error'      => 'El archivo no es válido. Solo se aceptan .doc y .docx de hasta 10MB.',
];
$errorCode = $_GET['error'] ?? null;
$errorMsg  = $errorMsgs[$errorCode] ?? ($errorCode ? 'Error desconocido.' : null);

//  Procesar POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'Registrar') {

    $id_tipo_documento = (int)($_POST['id_tipo_documento'] ?? 0);
    $nombre            = trim($_POST['nombre'] ?? '');
    $archivo           = $_FILES['archivo'] ?? null;

    if ($id_tipo_documento <= 0 || $nombre === '') {
        header("Location: crear.php?error=datos_invalidos");
        exit;
    }

    try {
        // Subir archivo usando FileUploader
        $nombreTipo = strtok($nombre, ' v');
        $carpeta    = $ctrl->carpetaPorTipo($nombreTipo);
        $subDir     = "storage/plantillas/supervisor_{$id_usuario}/{$carpeta}";

        $uploader = new FileUploader($subDir);
        $allowedMimes = ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxSize = 10 * 1024 * 1024; // 10 MB
        $prefix = 'plantilla';

        try {
            $fileInfo = $uploader->upload($archivo, $allowedMimes, $maxSize, $prefix);
        } catch (RuntimeException $e) {
            error_log("[crear.php] FileUpload Error: " . $e->getMessage());
            header("Location: crear.php?error=file_error");
            exit;
        }

        // Registrar en BD
        $ctrl->registrar(
            $rol,
            $nombre,
            $fileInfo['nombre_archivo'],
            $fileInfo['ruta_bd'],
            $fileInfo['extension'],
            $fileInfo['mime'],
            $fileInfo['tamano'],
            $id_tipo_documento,
            $id_usuario
        );
        // registrar() siempre redirige; no llega aquí.
    } catch (Throwable $e) {
        error_log("[crear.php] Error tras move_uploaded_file: " . $e->getMessage());
        header("Location: crear.php?msg=error_crear");
        exit;
    }
}

//  Datos para la vista 
$tipos = $ctrl->indexCrear($rol);

// Validación
$registro = $tipos;
require_once __DIR__ . '/../../../public/incluido/_validar_datos.php';


ob_start();
?>

<!-- ALERTAS del sistema de mensajes ($msg por redirect) -->
<?php if (isset($_mapa[$msg])):
    extract($_mapa[$msg]);
    require_once __DIR__ . '/../../../public/incluido/_mensaje.php';
endif; ?>


<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-3 align-items-center">
        <?php
        $titulo      = 'Nueva Plantilla';
        $descripcion = 'Registro de una nueva plantilla de documento';
        require_once __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>
        <div class="col-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>
    <!-- ALERTA de validación de archivo (?error=clave) -->
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <div class="card border-0 shadow-sm">
        <div class="card-header"><b>Datos de la plantilla</b></div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="form-crear">
                <input type="hidden" name="action" value="Registrar">

                <!-- Tipo de documento -->
                <div class="mb-3">
                    <label for="select-tipo" class="form-label tarea-seccion-label">
                        Tipo de documento
                    </label>
                    <select class="form-select" name="id_tipo_documento" id="select-tipo" required>
                        <option value="">— Selecciona un tipo —</option>
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?= (int)$tipo['id_tipo_documento'] ?>"
                                data-nombre="<?= htmlspecialchars($tipo['nombre']) ?>"
                                data-categoria="<?= htmlspecialchars($tipo['categoria']) ?>">
                                <?= htmlspecialchars($tipo['nombre']) ?>
                                — <?= ucfirst(htmlspecialchars($tipo['categoria'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nombre (solo lectura, calculado vía Ajax) -->
                <div class="mb-3">
                    <label for="nombre" class="form-label tarea-seccion-label">Nombre</label>
                    <input type="text" class="form-control" name="nombre" id="nombre"
                        readonly required placeholder="Se calcula automáticamente">
                    <div class="form-text">
                        <i class="bi bi-info-circle"></i>
                        El nombre incluye la versión y se genera al seleccionar el tipo.
                    </div>
                </div>

                <!-- Versión (solo lectura) -->
                <div class="mb-3">
                    <label for="version" class="form-label tarea-seccion-label">Versión</label>
                    <input type="number" class="form-control" name="version" id="version"
                        readonly required>
                </div>

                <!-- Archivo -->
                <div class="mb-4">
                    <label for="archivo" class="form-label tarea-seccion-label">
                        Plantilla de documento
                    </label>
                    <input type="file" name="archivo" id="archivo" class="form-control"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        required>
                    <div class="form-text">
                        <i class="bi bi-info-circle"></i>
                        Solo se aceptan archivos <strong>.doc y .docx</strong> (máx. 10 MB).
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="btn-guardar">
                    <i class="bi bi-floppy"></i> Guardar plantilla
                </button>
            </form>
        </div>
    </div>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Crear Plantilla de documento';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const selectTipo = document.getElementById('select-tipo');
        const inputNombre = document.getElementById('nombre');
        const inputVersion = document.getElementById('version');
        const btnGuardar = document.getElementById('btn-guardar');

        function cargarDatos() {
            const idTipo = selectTipo.value;
            if (!idTipo) {
                inputNombre.value = '';
                inputVersion.value = '';
                return;
            }

            btnGuardar.disabled = true;
            btnGuardar.textContent = 'Cargando…';

            fetch('/public/Ajax/plantilla_documento.php?tipo_documento=' + encodeURIComponent(idTipo))
                .then(r => {
                    if (!r.ok) throw new Error('Error de red: ' + r.status);
                    return r.json();
                })
                .then(data => {
                    if (data.nombre && data.version) {
                        inputNombre.value = data.nombre;
                        inputVersion.value = data.version;
                    } else {
                        throw new Error('Respuesta incompleta del servidor');
                    }
                })
                .catch(err => {
                    console.error(err);
                    inputNombre.value = '';
                    inputVersion.value = '';
                    alert('No se pudo obtener la información del tipo seleccionado. Intenta de nuevo.');
                })
                .finally(() => {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="bi bi-floppy"></i> Guardar plantilla';
                });
        }

        selectTipo.addEventListener('change', cargarDatos);

        if (selectTipo.value) {
            cargarDatos();
        }
    });
</script>