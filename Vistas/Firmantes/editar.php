<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* VALIDACIÓN DE SESIÓN */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_usuario   = intval($_SESSION['id_usuario']);
$id_firmantes = intval($_GET['id_firmantes'] ?? 0);

/* CONTROLADOR DE FIRMANTES */
require_once '../../Controladores/firmanteControlador.php';

$action              = $_POST['action'] ?? null;
$firmanteControlador = new firmanteControlador();
$datos               = $firmanteControlador->indexEditar($rol, $id_firmantes);
$mensaje             = "";
$errores             = [];

if (empty($datos)) {
    die("No se encontró el firmante.");
}


// GUARDAR CAMBIOS: actualiza nombre, cargo, instituto y opcionalmente la firma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Guardar') {

    $nombre       = trim($_POST['Nombre']       ?? '');
    $cargo        = trim($_POST['Cargo']        ?? '');
    $id_instituto  = intval($_POST['id_instituto'] ?? 0);
    $archivoFirma = $_FILES['firma_digital']   ?? null;

    if (empty($nombre)) {
        $errores[] = "El nombre del firmante es obligatorio.";
    }
    if (empty($cargo)) {
        $errores[] = "El cargo del firmante es obligatorio.";
    }

    if (empty($errores)) {
        $firmanteControlador->editarFirmante(
            $rol,
            $id_firmantes,
            $id_instituto,
            $nombre,
            $cargo,
            $archivoFirma,
            $datos['firma_digital'] // nombre del .enc actual para eliminarlo si hay nueva firma
        );
        // editarFirmante() hace el redirect internamente
    }

// REACTIVAR: cambia estado de Desactivado a Activo
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Reactivar') {
    $firmanteControlador->reactivar($rol, $_POST['id_firmantes']);

// DESACTIVAR: soft delete (estado = 0)
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) && $action == 'Desactivar') {
    $firmanteControlador->eliminar($rol, $_POST['id_firmantes']);
}

/* IMAGEN DE FIRMA ACTUAL (desencriptada para previsualización) */
$firmaBase64Actual = $firmanteControlador->obtenerFirmaBase64($datos['firma_digital'] ?? null);

ob_start();
include __DIR__ . '/../../mensaje.php';
include __DIR__ . '/../../error.php';
?>

<div class="container-fluid py-4">

    <!-- ENCABEZADO-->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Editar Firmante</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- FORMULARIO DE EDICIÓN-->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="id_firmantes" value="<?= $datos['id_firmantes'] ?>">

        <!-- Nombre del firmante -->
        <div class="mb-3">
            <label class="form-label">Nombre del firmante</label>
            <input
                type="text"
                name="Nombre"
                class="form-control"
                value="<?= htmlspecialchars($datos['nombre']) ?>"
                required>
        </div>

        <!-- Cargo del firmante -->
        <div class="mb-3">
            <label class="form-label">Cargo</label>
            <input
                type="text"
                name="Cargo"
                class="form-control"
                value="<?= htmlspecialchars($datos['cargo']) ?>"
                required>
        </div>

        <!-- ID del instituto -->
        <div class="mb-3">
            <label class="form-label">ID del Instituto</label>
            <input
                type="number"
                name="id_instituto"
                class="form-control"
                value="<?= intval($datos['id_instituto']) ?>"
                min="1"
                required>
        </div>

        <!-- Imagen de firma digital (opcional al editar) -->
        <div class="mb-3">
            <label class="form-label">
                Nueva imagen de Firma Digital
                <span class="badge bg-secondary ms-1">PNG — Opcional</span>
            </label>
            <input
                type="file"
                name="firma_digital"
                id="input-firma-nueva"
                class="form-control"
                accept=".png,image/png">
            <div class="form-text">
                <i class="bi bi-info-circle"></i>
                Si no seleccionas un archivo, se conserva la firma actual.
                Solo se aceptan archivos <strong>PNG</strong>. La imagen será redimensionada a
                <strong>300 × 100 px</strong> y encriptada. Peso máximo: <strong>2 MB</strong>.
            </div>
        </div>

        <!-- APARTADO DE VISUALIZACIÓN DE LA FIRMA
             Muestra: firma actual guardada + cambios en tiempo real.
             El botón "Previsualizar" actualiza el bloque con los datos
             actuales del formulario (incluyendo nueva imagen si se cargó). -->
        <div class="mb-4">
            <label class="form-label fw-semibold">
                <i class="bi bi-person-badge"></i> Vista previa del bloque de firma
            </label>

            <div class="card border shadow-sm" style="max-width: 360px;">
                <div class="card-body text-center py-3" id="bloque-firma-preview">

                    <?php if ($firmaBase64Actual): ?>
                        <!-- Firma actual almacenada (desencriptada) -->
                        <img
                            id="img-preview"
                            src="<?= $firmaBase64Actual ?>"
                            alt="Firma digital de <?= htmlspecialchars($datos['nombre']) ?>"
                            class="mb-2 d-block mx-auto"
                            style="max-width:300px; max-height:100px; object-fit:contain; border-bottom:1px solid #dee2e6; padding-bottom:6px;">
                    <?php else: ?>
                        <!-- Sin firma registrada -->
                        <div
                            id="img-preview"
                            class="mb-2 mx-auto d-flex align-items-center justify-content-center text-muted"
                            style="width:300px; height:100px; border:1px dashed #adb5bd; font-size:0.85rem;">
                            Sin firma registrada
                        </div>
                    <?php endif; ?>
                    <hr>

                    <!-- Nombre y cargo (actualizables con el botón) -->
                    <p class="mb-0 fw-bold" id="preview-nombre" style="font-size:0.95rem;">
                        <?= htmlspecialchars($datos['nombre']) ?>
                    </p>
                    <p class="mb-0 text-muted" id="preview-cargo" style="font-size:0.85rem;">
                        <?= htmlspecialchars($datos['cargo']) ?>
                    </p>
                </div>
            </div>

            <!-- Botón para actualizar la previsualización con los cambios del formulario -->
            <button type="button" class="btn btn-outline-secondary mt-2" id="btn-previsualizar">
                <i class="bi bi-arrow-clockwise"></i> Actualizar previsualización
            </button>
            <small class="text-muted ms-2">
                Refleja los datos actuales del formulario (sin guardar aún).
            </small>
        </div>

        <!-- Mensajes de validación / error -->
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

        <!-- Botones de acción (dependen del rol y del estado del firmante) -->
        <div class="mb-3">
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-warning" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php else: ?>
                <?php echo $firmanteControlador->botonesAccionEditar($rol, $datos['estado']); ?>
            <?php endif; ?>
        </div>

        <!-- Enlace de descarga de la firma actual -->
        <?php if (!empty($datos['firma_digital'])): ?>
            <div class="mt-2">
                <a
                    href="descargar_firma.php?id_firmantes=<?= $datos['id_firmantes'] ?>"
                    class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-download"></i> Descargar firma actual (PNG)
                </a>
            </div>
        <?php endif; ?>

    </form>
</div>

<!-- SCRIPT: Previsualización dinámica en edición
     Al hacer clic en "Actualizar previsualización":
       - Si hay nueva imagen cargada, la muestra.
       - Si no, mantiene la firma actual del servidor.
       - Siempre actualiza nombre y cargo con los valores del formulario.-->
<script>
    const inputFirma      = document.getElementById('input-firma-nueva');
    const inputNombre     = document.querySelector('input[name="Nombre"]');
    const inputCargo      = document.querySelector('input[name="Cargo"]');
    const btnPrevisualizar = document.getElementById('btn-previsualizar');
    const previewNombre   = document.getElementById('preview-nombre');
    const previewCargo    = document.getElementById('preview-cargo');
    const contenedorImg   = document.getElementById('img-preview');

    // Firma actual guardada en servidor (como base64 inyectado por PHP)
    const firmaActualBase64 = <?= $firmaBase64Actual ? json_encode($firmaBase64Actual) : 'null' ?>;

    btnPrevisualizar.addEventListener('click', () => {
        // Actualizar nombre y cargo
        previewNombre.textContent = inputNombre.value.trim() || '—';
        previewCargo.textContent  = inputCargo.value.trim()  || '—';

        const archivo = inputFirma.files[0];

        if (archivo) {
            // Validar PNG en cliente
            if (archivo.type !== 'image/png') {
                alert('Solo se aceptan imágenes PNG para la firma digital.');
                return;
            }

            // Mostrar nueva imagen seleccionada
            const reader = new FileReader();
            reader.onload = (e) => {
                if (contenedorImg.tagName === 'IMG') {
                    contenedorImg.src = e.target.result;
                } else {
                    // Si era un div placeholder, reemplazarlo con un <img>
                    const nuevoImg = document.createElement('img');
                    nuevoImg.id    = 'img-preview';
                    nuevoImg.alt   = 'Firma digital';
                    nuevoImg.src   = e.target.result;
                    nuevoImg.className = 'mb-2 d-block mx-auto';
                    nuevoImg.style     = 'max-width:300px; max-height:100px; object-fit:contain; border-bottom:1px solid #dee2e6; padding-bottom:6px;';
                    contenedorImg.replaceWith(nuevoImg);
                }
            };
            reader.readAsDataURL(archivo);
        } else if (firmaActualBase64) {
            // Sin nueva imagen: mantener la firma actual del servidor
            if (contenedorImg.tagName === 'IMG') {
                contenedorImg.src = firmaActualBase64;
            }
        }
    });
</script>

<?php

$contenido = ob_get_clean();
$titulo    = "Editar Firmante";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
