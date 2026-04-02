<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol        = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

// CONTROLADOR DE FIRMANTES  */
require_once '../../Controladores/firmanteControlador.php';

$action            = $_POST['action'] ?? null;
$firmanteControlador = new firmanteControlador();
$estadoVista       = ["activo" => 0, "desactivado" => 0];
$mensaje           = "";
$errores           = [];

/*  
 * PROCESAMIENTO DEL FORMULARIO
 * Acción: Registrar nuevo firmante con imagen de firma digital
 *  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action === 'Registrar') {

    $nombre = trim($_POST['Nombre'] ?? '');
    $cargo = trim($_POST['Cargo']  ?? '');
    $id_instituto = intval($_POST['id_instituto'] ?? 0);

    // Validar que se haya subido la imagen de firma
    $archivoFirma = $_FILES['firma_digital'] ?? null;
    $hayFirma = !empty($archivoFirma['tmp_name']) && $archivoFirma['error'] === UPLOAD_ERR_OK;

    if (!$hayFirma) {
        $errores[] = "Debe subir una imagen de firma digital en formato PNG.";
    }

    if (empty($nombre)) {
        $errores[] = "El nombre del firmante es obligatorio.";
    }

    if (empty($cargo)) {
        $errores[] = "El cargo del firmante es obligatorio.";
    }

    // Solo continuar si no hay errores de validación básica
    if (empty($errores)) {
        // Verificar duplicado antes de procesar imagen
        $estadoVista = $firmanteControlador->verificarFirmante($nombre, $cargo, $id_instituto);

        if ($estadoVista['activo'] == 0) {
            // Registrar firmante (procesa imagen internamente: redimensiona + encripta)
            $firmanteControlador->registrarFirmante($rol, $id_instituto, $nombre, $cargo, $archivoFirma);
        } else {
            $mensaje = "Ya existe un firmante activo con ese nombre y cargo en el instituto.";
        }
    }
}

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!--  ENCABEZADO  -->
    <div class="row mb-3">
        <div class="col-6">
            <h3>Crear Firmante</h3>
        </div>
        <div class="col-6 text-end">
            <a href="tabla.php" class="btn btn-danger">Regresar</a>
        </div>
    </div>

    <!-- FORMULARIO DE REGISTRO DE FIRMANTE
         Campos: nombre, cargo, id_instituto, firma_digital (PNG)
         La imagen se redimensiona a 300×100 px y se encripta antes de guardarse.
     -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="Registrar">

        <!-- Nombre del firmante -->
        <div class="mb-3">
            <label class="form-label">Nombre del firmante</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                placeholder="Ej. Dr. Juan Pérez López"
                value="<?= htmlspecialchars($_POST['Nombre'] ?? '') ?>"
                required>
        </div>

        <!-- Cargo del firmante -->
        <div class="mb-3">
            <label class="form-label">Cargo</label>
            <input
                type="text"
                name="Cargo"
                class="form-control"
                placeholder="Ej. Director de Investigación"
                value="<?= htmlspecialchars($_POST['Cargo'] ?? '') ?>"
                required>
        </div>

        <!-- ID del instituto (campo oculto o selector según el sistema) -->
        <div class="mb-3">
            <label class="form-label">ID del Instituto</label>
            <input
                type="number"
                name="id_instituto"
                class="form-control"
                value="<?= intval($_POST['id_instituto'] ?? 1) ?>"
                min="1"
                required>
            <div class="form-text">Identificador del instituto al que pertenece el firmante.</div>
        </div>

        <!-- Imagen de firma digital -->
        <div class="mb-3">
            <label class="form-label">
                Imagen de Firma Digital
                <span class="badge bg-secondary ms-1">PNG obligatorio</span>
            </label>
            <input
                type="file"
                name="firma_digital"
                class="form-control"
                accept=".png,image/png"
                required>
            <div class="form-text">
                <i class="bi bi-info-circle"></i>
                Solo se aceptan archivos <strong>PNG</strong>. La imagen será redimensionada automáticamente
                a <strong>300 × 100 píxeles</strong> (tamaño estándar para firmas en reportes)
                y encriptada para su almacenamiento seguro. Peso máximo: <strong>2 MB</strong>.
                Se recomienda usar fondo transparente.
            </div>
        </div>

        <!--  PREVISUALIZACIÓN DE LA FIRMA (antes de guardar)
             El usuario puede revisar cómo lucirá el bloque de firma
             en los reportes antes de confirmar el registro. -->
        <div class="mb-4">
            <div id="previsualizacion-firma" class="d-none">
                <label class="form-label fw-semibold">
                    <i class="bi bi-eye"></i> Vista previa del bloque de firma
                </label>
                <div class="card border shadow-sm" style="max-width: 360px;">
                    <div class="card-body text-center py-3">
                        <!-- Imagen de firma previsualizada localmente -->
                        <img
                            id="img-preview"
                            src=""
                            alt="Vista previa de la firma"
                            class="mb-2 d-block mx-auto"
                            style="max-width: 300px; max-height: 100px; object-fit: contain; border-bottom: 1px solid #dee2e6; padding-bottom: 6px;">
                        <hr>
                            <!-- Nombre del firmante (en tiempo real) -->
                        <p class="mb-0 fw-bold" id="preview-nombre" style="font-size:0.95rem;">—</p>
                        <!-- Cargo del firmante (en tiempo real) -->
                        <p class="mb-0 text-muted" id="preview-cargo" style="font-size:0.85rem;">—</p>
                    </div>
                </div>
            </div>

            <!-- Botón para activar la previsualización -->
            <button type="button" class="btn btn-outline-secondary mt-2" id="btn-previsualizar">
                <i class="bi bi-eye"></i> Previsualizar firma
            </button>
        </div>

        <!-- Mensajes de validación / error / duplicado -->
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <button type="submit" name="action" value="Registrar" class="btn btn-guardar">
            Guardar firmante
        </button>
    </form>
</div>

<!-- SCRIPT: Previsualización local de la firma antes de guardar
     Muestra la imagen seleccionada + nombre y cargo del formulario.
     No realiza ninguna solicitud al servidor. -->
<script>
    const inputFirma = document.querySelector('input[name="firma_digital"]');
    const inputNombre = document.querySelector('input[name="Nombre"]');
    const inputCargo = document.querySelector('input[name="Cargo"]');
    const btnPrevisualizar = document.getElementById('btn-previsualizar');
    const divPreview = document.getElementById('previsualizacion-firma');
    const imgPreview = document.getElementById('img-preview');
    const previewNombre = document.getElementById('preview-nombre');
    const previewCargo = document.getElementById('preview-cargo');

    // Actualizar nombre y cargo en la previsualización en tiempo real
    [inputNombre, inputCargo].forEach(input => {
        input.addEventListener('input', actualizarTextos);
    });

    function actualizarTextos() {
        previewNombre.textContent = inputNombre.value.trim() || '—';
        previewCargo.textContent = inputCargo.value.trim() || '—';
    }

    // Mostrar previsualización al hacer clic en el botón
    btnPrevisualizar.addEventListener('click', () => {
        const archivo = inputFirma.files[0];

        if (!archivo) {
            alert('Por favor, selecciona primero la imagen de firma PNG.');
            return;
        }

        // Validar formato PNG en el cliente
        if (archivo.type !== 'image/png') {
            alert('Solo se aceptan imágenes en formato PNG.');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            imgPreview.src = e.target.result;
            actualizarTextos();
            divPreview.classList.remove('d-none');
        };
        reader.readAsDataURL(archivo);
    });

    // Ocultar previsualización si el usuario cambia la imagen
    inputFirma.addEventListener('change', () => {
        divPreview.classList.add('d-none');
        imgPreview.src = '';
    });
</script>

<?php

$contenido = ob_get_clean();
$titulo    = "Crear Firmante";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>