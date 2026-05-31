<?php
// Vistas/Instituto/editar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id_usuario = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

require_once __DIR__ . '/../../Controladores/institutoControlador.php';

$controlador = new institutoControlador();

//  Procesar POST antes de cargar datos 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador->editar($rol, $_POST);
    // editar() siempre redirige; no llega aquí.
}

//  Cargar datos 
$instituto  = $controlador->indexDetalles($rol);
$directores = $controlador->directores();

//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Instituto actualizado',  'mensaje' => 'Los datos del instituto fueron guardados correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al guardar',        'mensaje' => 'No fue posible guardar los datos del instituto. Intenta de nuevo.'],
    'error_director'      => ['tipo' => 'alerta', 'titulo_msg' => 'Director no válido',      'mensaje' => 'El director seleccionado no está activo. Elige otro e intenta de nuevo.'],
    'error_sin_registro'  => ['tipo' => 'error',  'titulo_msg' => 'Error al no tener registro',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo o actualizar la información del instituto.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',     'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>


<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4">
        <?php
        $titulo      = 'Datos del Instituto';
        $descripcion = 'Modificar información general del instituto';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6"></div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>
    <form method="POST" action="">

        <input type="hidden" name="id_instituto" value="<?= (int)$instituto['id_instituto'] ?>">

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre"
                value="<?= htmlspecialchars($instituto['nombre'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Unidad Académica</label>
            <input type="text" class="form-control" name="unidad_academica"
                value="<?= htmlspecialchars($instituto['unidad_academica'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Dirección</label>
            <input type="text" class="form-control" name="direccion"
                value="<?= htmlspecialchars($instituto['direccion'] ?? '') ?>">
        </div>

        <div class="row">
            <div class="col-md mb-3">
                <label class="form-label">Estado</label>
                <input type="text" class="form-control" name="estado"
                    value="<?= htmlspecialchars($instituto['estado'] ?? '') ?>">
            </div>
            <div class="col-md mb-3">
                <label class="form-label">Ciudad</label>
                <input type="text" class="form-control" name="ciudad"
                    value="<?= htmlspecialchars($instituto['ciudad'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md mb-3">
                <label class="form-label">Correo</label>
                <input type="email" class="form-control" name="correo_instituto"
                    value="<?= htmlspecialchars($instituto['correo_instituto'] ?? '') ?>">
            </div>
            <div class="col-md mb-3">
                <label class="form-label">Teléfono</label>
                <input type="text" class="form-control" name="telefono"
                    value="<?= htmlspecialchars($instituto['telefono'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md mb-3">
                <label class="form-label">Clave Plantel</label>
                <input type="text" class="form-control" name="clave_plantel"
                    value="<?= htmlspecialchars($instituto['clave_plantel'] ?? '') ?>">
            </div>

            <div class="col-md mb-3">
                <label class="form-label">Director</label>
                <?php
                $hayActivos = false;
                foreach ($directores as $d) {
                    if ((int)$d['estado'] === 1) {
                        $hayActivos = true;
                        break;
                    }
                }
                ?>
                <select name="id_director" class="form-select" required>
                    <option value="">Seleccione un director</option>
                    <?php foreach ($directores as $d):
                        $activo    = (int)$d['estado'] === 1;
                        $etiqueta  = $activo ? '(Activo)' : '(Inactivo)';
                        $selected  = ((int)$instituto['id_director'] === (int)$d['id_director']) ? 'selected' : '';
                        $disabled  = $activo ? '' : 'disabled';
                    ?>
                        <option value="<?= (int)$d['id_director'] ?>"
                            <?= $selected ?>
                            <?= $disabled ?>>
                            <?= htmlspecialchars($d['nombre'] . ' ' . $d['apellido']) ?>
                            <?= $etiqueta ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (!$hayActivos): ?>
                    <small class="text-danger">
                        No hay directores activos disponibles. Contacta al administrador.
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-3">
            <button type="submit" class="btn btn-guardar"><i class="bi bi-floppy me-1"></i> Guardar cambios</button>
        </div>

    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Instituto';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
