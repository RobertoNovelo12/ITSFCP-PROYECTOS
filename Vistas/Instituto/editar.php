<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /ITSFCP-PROYECTOS/index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
require_once '../../Controladores/institutoControlador.php';

//Solo supervisor
if ($rol !== 'supervisor') {
    header("Location: /ITSFCP-PROYECTOS/Vistas/Principal/index.php");
    exit;
}

$controlador = new institutoControlador();

// DATOS
$instituto = $controlador->indexDetalles($rol);
$director = $controlador->directores();

// ACCIONES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador->editar($_POST);
}

// MENSAJE DIRECTOR
/*
$mensajeDirector = "";
if (!$director) {
    $mensajeDirector = "<small class='text-danger'>No hay un director asignado o activo.</small>";
}*/

ob_start();
?>

<div class="container-fluid py-4">

    <div class="row mb-4">
        <div class="col-md-6">
            <h3 class="fw-bold">Editar Instituto</h3>
        </div>
    </div>

    <form method="POST">

        <input type="hidden" name="id_instituto" value="<?= $instituto['id_instituto']; ?>">

        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" class="form-control" name="nombre"
                value="<?= $instituto['nombre']; ?>" required>
        </div>

        <div class="mb-3">
            <label>Unidad Académica</label>
            <input type="text" class="form-control" name="unidad_academica"
                value="<?= $instituto['unidad_academica']; ?>">
        </div>

        <div class="mb-3">
            <label>Dirección</label>
            <input type="text" class="form-control" name="direccion"
                value="<?= $instituto['direccion']; ?>">
        </div>

        <div class="row">
            <div class="col-md">
                <label>Estado</label>
                <input type="text" class="form-control" name="estado"
                    value="<?= $instituto['estado']; ?>">
            </div>

            <div class="col-md">
                <label>Ciudad</label>
                <input type="text" class="form-control" name="ciudad"
                    value="<?= $instituto['ciudad']; ?>">
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md">
                <label>Correo</label>
                <input type="email" class="form-control" name="correo_instituto"
                    value="<?= $instituto['correo_instituto']; ?>">
            </div>

            <div class="col-md">
                <label>Teléfono</label>
                <input type="text" class="form-control" name="telefono"
                    value="<?= $instituto['telefono']; ?>">
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md">
                <label>Clave Plantel</label>
                <input type="text" class="form-control" name="clave_plantel"
                    value="<?= $instituto['clave_plantel']; ?>">
            </div>

            <?php
            $directores = $controlador->listaDirectores();
            $hayActivos = false;
            ?>

            <div class="col-md">
                <label>Director</label>

                <select name="id_director" class="form-select" required>
                    <option value="">Seleccione un director</option>

                    <?php foreach ($directores as $d):
                        if ($d['estado'] == 1) {
                            $activo = "activo";
                        } else {
                            $activo = "inactivo";
                        }
                        if ($activo) $hayActivos = true;
                    ?>
                        <option value="<?= $d['id_director']; ?>"
                            <?= ($instituto['id_director'] == $d['id_director']) ? 'selected' : ''; ?>
                            <?= !$activo ? 'disabled' : ''; ?>>
                            <?= $d['nombre'] . ' ' . $d['apellido']; ?>
                            <?= $activo ? '(Activo)' : '(Inactivo)'; ?>
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
            <button class="btn btn-primary">Guardar cambios</button>
        </div>

    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo = "Editar Instituto";
include __DIR__ . '/../../layout.php';
?>