<?php if (isset($_GET['mensaje'])): ?>
    <div class="modal-overlay" id="modalMensaje">
        <div class="modal-content">

            <div class="img_modal">
                <img src="/publico/icons/comprobar.svg" alt="icono">
            </div>

            <h2>¡Operación realizada!</h2>

            <div class="modal-actions">
                <button class="submit-btn btn-primary" onclick="cerrarModal()">
                    Aceptar
                </button>
            </div>

        </div>
    </div>

    <script>
        const modalMensaje = document.getElementById("modalMensaje");
        modalMensaje.style.display = "flex";

        function cerrarModal() {
            modalMensaje.style.display = "none";
        }

        // cerrar con ESC
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") cerrarModal();
        });

        // cerrar clic fuera
        modalMensaje.addEventListener("click", (e) => {
            if (e.target === modalMensaje) cerrarModal();
        });
    </script>
<?php endif; ?>