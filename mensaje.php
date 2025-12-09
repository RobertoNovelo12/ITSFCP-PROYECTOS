    <?php if (isset($_GET['mensaje'])): ?>
        <div class="modal-overlay" id="modalMensaje">
            <div class="modal-content">
                <h2>¡Operación realizada!</h2>
                <p><img src="publico/icons/comprobar.svg" alt=""></p>
                <button class="submit-btn" onclick="cerrarModal()">Aceptar</button>
            </div>
        </div>

        <script>
            document.getElementById("modalMensaje").style.display = "flex";

            function cerrarModal() {
                document.getElementById("modalMensaje").style.display = "none";
            }
        </script>
    <?php endif; ?>
