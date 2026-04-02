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

/* CONTROLADOR DE FIRMANTES */
require_once '../../Controladores/firmanteControlador.php';

$firmanteControlador = new firmanteControlador();

$rol          = strtolower($_SESSION['rol'] ?? '');
$id_usuario   = intval($_SESSION['id_usuario']);
$id_firmantes = isset($_GET['id_firmantes']) ? intval($_GET['id_firmantes']) : 0;

// Obtener datos completos del firmante
$firmante = $firmanteControlador->indexDetalles($rol, $id_firmantes);

if (empty($firmante)) {
    die("No se encontró el firmante.");
}

/* OBTENER IMAGEN DE FIRMA DESENCRIPTADA PARA PREVISUALIZACIÓN
 * La imagen se desencripta en servidor y se pasa como Data URI
 * para renderizarla en el <img> sin exponer la ruta real del .enc */
$firmaBase64 = $firmanteControlador->obtenerFirmaBase64($firmante['firma_digital'] ?? null);

ob_start();
?>

<div class="container-fluid py-4">

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Detalles del Firmante</h3>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="tabla.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
            <?php if (!empty($firmante['firma_digital'])): ?>
                <!-- Botón de descarga: descarga la imagen PNG desencriptada -->
                <a
                    href="descargar_firma.php?id_firmantes=<?= $firmante['id_firmantes'] ?>"
                    class="btn btn-outline-primary ms-2">
                    <i class="bi bi-download"></i> Descargar firma (PNG)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- INFORMACIÓN GENERAL DEL FIRMANTE -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Información del firmante</h5>
        </div>
        <div class="card-body">

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>Nombre</dt>
                        <dd><?= htmlspecialchars($firmante['nombre']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Cargo</dt>
                        <dd><?= htmlspecialchars($firmante['cargo']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <dl>
                        <dt>ID Instituto</dt>
                        <dd><?= intval($firmante['id_instituto']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl>
                        <dt>Estado</dt>
                        <dd>
                            <span class="badge rounded-pill text-bg-<?= $firmanteControlador->EstiloEstadoLista($firmante['estado']) ?>">
                                <?= htmlspecialchars($firmante['estado']) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <dl>
                        <dt>Archivo de firma almacenado</dt>
                        <dd class="text-muted" style="font-size:0.85rem;">
                            <?php if (!empty($firmante['firma_digital'])): ?>
                                <i class="bi bi-shield-lock-fill text-success"></i>
                                Encriptado · <?= htmlspecialchars($firmante['firma_digital']) ?>
                            <?php else: ?>
                                <span class="text-warning">Sin firma registrada</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>

        </div>
    </div>

    <!-- APARTADO DE VISUALIZACIÓN DEL BLOQUE DE FIRMA
         Muestra cómo se verá el bloque nombre + cargo + firma
         tal como aparece en los reportes generados.
         El botón "Visualizar" descarga/muestra la imagen desencriptada. -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-person-badge"></i> Vista previa del bloque de firma
            </h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-ver-firma">
                <i class="bi bi-eye"></i> Visualizar firma
            </button>
        </div>

        <div class="card-body text-center" id="contenedor-firma-preview">

            <!-- Placeholder inicial: oculto hasta que el usuario pulse "Visualizar" -->
            <div id="placeholder-firma" class="py-4 text-muted">
                <i class="bi bi-image" style="font-size:2rem;"></i>
                <p class="mt-2 mb-0">Haz clic en <strong>Visualizar firma</strong> para mostrar el bloque.</p>
            </div>

            <!-- Bloque de firma (oculto hasta activar) -->
            <div id="bloque-firma" class="d-none py-3">

                <?php if ($firmaBase64): ?>
                    <!-- Imagen de firma desencriptada (base64 desde servidor) -->
                    <img
                        src="<?= $firmaBase64 ?>"
                        alt="Firma digital de <?= htmlspecialchars($firmante['nombre']) ?>"
                        id="img-firma-detalle"
                        class="d-block mx-auto mb-2"
                        style="max-width:300px; max-height:100px; object-fit:contain; border-bottom:1px solid #dee2e6; padding-bottom:6px;">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center text-muted mx-auto mb-2"
                         style="width:300px; height:100px; border:1px dashed #adb5bd; font-size:0.85rem;">
                        Sin imagen de firma registrada
                    </div>
                <?php endif; ?>
                <hr>

                <!-- Nombre del firmante -->
                <p class="fw-bold mb-0" style="font-size:0.95rem;">
                    <?= htmlspecialchars($firmante['nombre']) ?>
                </p>

                <!-- Cargo del firmante -->
                <p class="text-muted mb-0" style="font-size:0.85rem;">
                    <?= htmlspecialchars($firmante['cargo']) ?>
                </p>

            </div>

        </div>
    </div>

</div>

<!-- SCRIPT: Mostrar/ocultar el bloque de firma con botón -->
<script>
    const btnVerFirma      = document.getElementById('btn-ver-firma');
    const placeholderFirma = document.getElementById('placeholder-firma');
    const bloqueFirma      = document.getElementById('bloque-firma');
    let   firmaVisible     = false;

    btnVerFirma.addEventListener('click', () => {
        firmaVisible = !firmaVisible;

        if (firmaVisible) {
            placeholderFirma.classList.add('d-none');
            bloqueFirma.classList.remove('d-none');
            btnVerFirma.innerHTML = '<i class="bi bi-eye-slash"></i> Ocultar firma';
        } else {
            placeholderFirma.classList.remove('d-none');
            bloqueFirma.classList.add('d-none');
            btnVerFirma.innerHTML = '<i class="bi bi-eye"></i> Visualizar firma';
        }
    });
</script>

<?php

$contenido = ob_get_clean();
$titulo    = "Detalles del Firmante";
$bodyClass = "proyectos-page";

include __DIR__ . '/../../layout.php';
?>
