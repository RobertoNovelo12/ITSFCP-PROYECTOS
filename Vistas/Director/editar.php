<?php
// Vistas/Director/editar.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol         = strtolower($_SESSION['rol'] ?? '');
$id_usuario  = intval($_SESSION['id_usuario']);

if ($rol !== 'supervisor') {
    header("Location: /Vistas/Principal/index.php");
    exit;
}

$id_director = $_GET['id_director'] ?? null;

require_once __DIR__ .  '/../../Controladores/directorControlador.php';

$directorControlador = new directorControlador();
$datos  = $directorControlador->indexEditar($rol, $id_director);
$grados = $directorControlador->obtenerGrados($rol);


//  Mapa de mensajes 
$msg   = $_GET['msg'] ?? '';
$_mapa = [
    'exito_editar'        => ['tipo' => 'exito',  'titulo_msg' => 'Director actualizado', 'mensaje' => 'Los datos del director fueron editados correctamente.'],
    'exito_desactivar'    => ['tipo' => 'exito',  'titulo_msg' => 'Director desactivado', 'mensaje' => 'El director fue desactivado correctamente.'],
    'exito_reactivar'     => ['tipo' => 'exito',  'titulo_msg' => 'Director reactivado',  'mensaje' => 'El director fue reactivado correctamente.'],
    'error_editar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',      'mensaje' => 'No fue posible editar el director. Verifica los datos e intenta de nuevo.'],
    'error_desactivar'    => ['tipo' => 'error',  'titulo_msg' => 'Error al desactivar',  'mensaje' => 'No fue posible desactivar el director.'],
    'error_reactivar'     => ['tipo' => 'error',  'titulo_msg' => 'Error al reactivar',   'mensaje' => 'No fue posible reactivar el director.'],
    'error_duplicado'     => ['tipo' => 'alerta', 'titulo_msg' => 'Registro duplicado',   'mensaje' => 'Ya existe un director con ese correo. Intenta con otro.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',  'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

//  Procesar POST 
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'Guardar') {
        $directorControlador->editarDirector(
            $rol,
            $_POST['id_director']  ?? 0,
            $_POST['IdGrado']      ?? '',
            $_POST['Nombre']       ?? '',
            $_POST['Apellido']     ?? '',
            $_POST['Correo']       ?? null,
            $_POST['Telefono']     ?? null,
            $_POST['Fecha_inicio'] ?? null,
            $_POST['Fecha_final']  ?? null,
            $_POST['Motivo_fin']   ?? null
        );
        // editarDirector() siempre redirige; no llega aquí.
    } elseif ($action === 'Reactivar') {
        $directorControlador->reactivar($rol, $_POST['id_director'] ?? 0);
    } elseif ($action === 'Desactivar') {
        $directorControlador->eliminar($rol, $_POST['id_director'] ?? 0);
    }
}

ob_start();
?>

<div class="container-fluid py-4 ancho_container">

    <!-- ENCABEZADO -->
    <div class="row mb-4 align-items-center">
        <?php
        $titulo      = 'Editar Director';
        $descripcion = 'Modificar datos del director';
        include __DIR__ . '../../../publico/incluido/_encabezado.php';
        ?>
        <div class="col-md-6 text-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '../../../publico/incluido/_mensaje.php';
    endif; ?>


    <!-- FORMULARIO -->
    <form method="POST" action="">
        <input type="hidden" name="id_director" value="<?= (int)$datos['id_director'] ?>">

        <div class="mb-3">
            <label class="form-label">Grado Académico</label>
            <select name="IdGrado" class="form-select" required>
                <option value="">-- Selecciona un grado --</option>
                <?php foreach ($grados as $grado): ?>
                    <option value="<?= (int)$grado['id_grado'] ?>"
                        <?= ((int)$grado['id_grado'] === (int)$datos['id_grado']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($grado['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="Nombre" class="form-control"
                value="<?= htmlspecialchars($datos['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Apellido</label>
            <input type="text" name="Apellido" class="form-control"
                value="<?= htmlspecialchars($datos['apellido']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo</label>
            <input type="email" name="Correo" class="form-control"
                value="<?= htmlspecialchars($datos['correo'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input type="text" name="Telefono" class="form-control" maxlength="10"
                value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha inicio</label>
            <input type="date" name="Fecha_inicio" class="form-control"
                value="<?= htmlspecialchars($datos['inicio'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Fecha final</label>
            <input type="date" name="Fecha_final" class="form-control"
                value="<?= htmlspecialchars($datos['fin'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Motivo de salida</label>
            <textarea name="Motivo_fin" class="form-control" rows="3"><?= htmlspecialchars($datos['motivo_fin'] ?? '') ?></textarea>
        </div>

        <div class="mb-3 d-flex gap-2">
            <?= $directorControlador->botonesAccionEditar($rol, $datos['estado']) ?>
        </div>

    </form>

</div>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Director';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../layout.php';
