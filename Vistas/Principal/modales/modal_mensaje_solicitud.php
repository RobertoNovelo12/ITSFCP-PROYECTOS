<?php
// Vistas/Proyectos/partials/modal_mensaje_solicitud.php
// Componente reutilizable: muestra un modal con el mensaje de respuesta
// tras una acción de solicitud (?solicitud=sent|pending|accepted|cancelled|error).
//
// Requiere que $mensaje_solicitud esté definido en el scope que lo incluye:
//   $mensaje_solicitud = $controlador->leerMensajeSolicitud();
//   // null → no se renderiza nada
//   // array ['title', 'body', 'tipo'] → renderiza el modal
//
// Uso:
//   include __DIR__ . '/partials/modal_mensaje_solicitud.php';

if (empty($mensaje_solicitud)) return;

$tipo      = $mensaje_solicitud['tipo']  ?? 'info';
$icono_map = [
    'success' => 'bi-check-circle-fill text-success',
    'warning' => 'bi-exclamation-triangle-fill text-warning',
    'info'    => 'bi-info-circle-fill text-info',
    'error'   => 'bi-x-circle-fill text-danger',
];
$icono = $icono_map[$tipo] ?? 'bi-info-circle-fill text-info';
?>

<!-- ===== MODAL: Mensaje de solicitud ===== -->
<div class="modal-overlay" id="modalMensajeSolicitud" style="display:flex;">
    <div class="modal-content text-center">
        <i class="bi <?= $icono ?>" style="font-size:2.2rem;"></i>
        <h2 class="mt-2"><?= htmlspecialchars($mensaje_solicitud['title']) ?></h2>
        <p><?= htmlspecialchars($mensaje_solicitud['body']) ?></p>
        <button class="submit-btn" onclick="cerrarModalMensajeSolicitud()">Aceptar</button>
    </div>
</div>

<script>
function cerrarModalMensajeSolicitud() {
    // Eliminar el parámetro ?solicitud= de la URL sin recargar la página
    const url = new URL(window.location.href);
    url.searchParams.delete('solicitud');
    window.history.replaceState({}, '', url.toString());
    document.getElementById('modalMensajeSolicitud').style.display = 'none';
}
</script>