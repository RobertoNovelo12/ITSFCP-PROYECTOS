<?php
// publico/incluido/_mensaje.php
// Variables: $tipo, $mensaje, $titulo_msg (opcional)

$_cfg = [
    'exito'  => ['clase' => 'alrt-exito',  'icono' => 'bi-check-circle',   'role' => 'status', 'titulo' => 'Operación completada'],
    'error'  => ['clase' => 'alrt-error',  'icono' => 'bi-x-circle',       'role' => 'alert',  'titulo' => 'Error en la operación'],
    'alerta' => ['clase' => 'alrt-alerta', 'icono' => 'bi-exclamation-triangle', 'role' => 'alert',  'titulo' => 'Atención'],
    'nota'   => ['clase' => 'alrt-nota',   'icono' => 'bi-info-circle',    'role' => 'note',   'titulo' => 'Información'],
];

if (empty($tipo) || empty($mensaje) || !isset($_cfg[$tipo])) return;
$c      = $_cfg[$tipo];
$titulo = $titulo_msg ?? $c['titulo'];
$mensaje_ = $mensaje ?? $c['mensaje'];
unset($tipo, $mensaje, $titulo_msg);
?>
<div class="alert alrt <?= $c['clase'] ?> alert-dismissible fade show"
     role="<?= $c['role'] ?>">

    <i class="bi <?= $c['icono'] ?> alrt-icon" aria-hidden="true"></i>

    <div class="alrt-body">
        <span class="alrt-title"><?= htmlspecialchars($titulo) ?></span>
        <span class="alrt-text"><?= htmlspecialchars($mensaje_) ?></span>
    </div>

    <button type="button"
            class="alrt-close"
            data-bs-dismiss="alert"
            aria-label="Cerrar notificación">
        <i class="bi bi-x"></i>
    </button>
</div>