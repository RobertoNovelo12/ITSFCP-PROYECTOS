<?php if (isset($_GET['error'])): ?>
    <div class="modal-overlay" id="error">
        <div class="modal-content">

            <div class="img_modal">
                <img src="/ITSFCP-PROYECTOS/publico/icons/error.png" alt="icono">
            </div>


            <h2>¡Operación no realizada!</h2>
            <div class="modal-actions">
                <button class="submit-btn btn-primary" onclick="cerrarModal()">
                    Aceptar
                </button>
            </div>
        </div>
    </div>

    <script>
        const modalMensaje = document.getElementById("error");
        error.style.display = "flex";

        function cerrarModal() {
            error.style.display = "none";
        }

        // cerrar con ESC
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") cerrarModal();
        });

        // cerrar clic fuera
        error.addEventListener("click", (e) => {
            if (e.target === error) cerrarModal();
        });
    </script>
<?php endif; ?>