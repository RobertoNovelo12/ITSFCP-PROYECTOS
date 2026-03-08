    <?php if (isset($_GET['Error'])): ?>
        <div class="modal-overlay" id="modalError">
            <div class="modal-content">
                <h2>¡Operación no realizada!</h2>
                <p><img src="publico/icons/error.png" alt=""></p>
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