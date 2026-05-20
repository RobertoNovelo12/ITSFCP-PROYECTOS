<?php
// Vistas/Proyectos/partials/modal_cancelar_solicitud.php
// la solicitud de integración de un estudiante.
//
// Variables requeridas en el scope que lo incluye:
//   $puede_cancelar  (bool)  → true para renderizar
//   $solicitud       (array) → ['id_solicitud_proyecto' => int, 'estado' => string]
//   $id_proyecto     (int)
//

if (empty($puede_cancelar) || empty($solicitud)) return;
?>

<!--  MODAL: Confirmación de cancelación de solicitud  -->
<div class="modal-overlay" id="modalCancelar" style="display:none;">
    <div class="modal-content text-center">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:2.2rem;"></i>
        <h2 class="mt-2">Cancelar solicitud</h2>
        <p>¿Estás seguro de que deseas cancelar tu solicitud?<br>
           <small class="text-muted">Esta acción no se puede deshacer.</small>
        </p>
        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
            <a href="/ITSFCP-PROYECTOS/Vistas/Proyectos/cancelar_solicitud.php?id_solicitud=<?= (int)$solicitud['id_solicitud_proyecto'] ?>&id_proyecto=<?= (int)$id_proyecto ?>"
               class="submit-btn" style="background:#d9534f;">
                <i class="bi bi-x-circle"></i> Sí, cancelar
            </a>
            <button class="submit-btn" style="background:#6c757d;"
                    onclick="cerrarModalCancelar()">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
function abrirModalCancelar()  { document.getElementById('modalCancelar').style.display = 'flex'; }
function cerrarModalCancelar() { document.getElementById('modalCancelar').style.display = 'none'; }
</script>