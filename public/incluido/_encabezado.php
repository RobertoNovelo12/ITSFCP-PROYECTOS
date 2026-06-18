<?php
// _encabezado.php
// Variables requeridas:
//   string $titulo
// Variables opcionales:
//   string $descripcion
?>
<div class="col-md-6">
    <h2 class="mb-0 fw-bold"><?= $titulo ?></h2>
    <?php if (!empty($descripcion)): ?>
        <p class="form-label mb-1 small"><?= $descripcion ?></p>
    <?php endif; ?>
</div>