<?php
// editar
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: /index.php");
    exit;
}

$rol = strtolower($_SESSION['rol'] ?? '');
$id  = $_SESSION['id_usuario'];


if (!in_array($rol, ['investigador', 'profesor'])) {
    header("Location: /Modules/Principal/Views/index.php");
    exit;
}
include __DIR__ . '/../../../public/incluido/_validar_tareas.php';

$id_tarea = $_GET['id_tarea'] ?? $_POST['id_tarea'] ?? 0;

if ($id_tarea <= 0) {
    header("Location: /Modules/Proyectos/Views/index.php?msg=error_cargar");
    exit;
}

$id_proyectos = $_GET['id_proyectos'] ?? $_POST['id_proyectos'] ?? 0;

if ($id_proyectos <= 0) {
    header("Location: /Modules/Proyectos/Views/index.php?msg=error_cargar");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;


// Límites de caracteres
const MAX_DESCRIPCION   = 5000;
const MAX_INSTRUCCIONES = 1500;

require_once __DIR__ . '/../Controller/tareas_controller.php';
$tareaControlador = new TareaControlador();




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'Guardar') {
        $tareaControlador->guardar_borrador_Investigador(
            $id_proyectos,
            $id,
            $_POST
        );
        // guardar_borrador() redirige internamente
    }

    if ($action === 'Activar') {
        $tareaControlador->activarTarea($_POST, $rol, $id_proyectos);
    }

    if ($action === 'Editar') {
        $_POST['id_usuario'] = $id;
        $tareaControlador->editarTarea($_POST, $rol, $id_proyectos);
    }
}


$tarea    = $tareaControlador->mostrarEditarTarea($id_tarea, $rol, $id);
$ediciones = $tareaControlador->obtenerEdicionesRecientes($id_tarea, 8);
$periodo = $tareaControlador->obtenerperiodo();

$campoNombres = [
    'descripcion'   => 'Descripción',
    'instrucciones' => 'Instrucciones',
    'fecha_entrega' => 'Fecha de entrega',
    'archivo_guia'  => 'Archivo de guía',
];

// Longitud actual del contenido ya guardado (sin HTML)
$lenDescripcion   = mb_strlen(strip_tags($tarea['descripcion']   ?? ''), 'UTF-8');
$lenInstrucciones = mb_strlen(strip_tags($tarea['instrucciones'] ?? ''), 'UTF-8');

$msg = $_GET['msg'] ?? '';

$_mapa = [
    'fecha_invalida'     => ['tipo' => 'error',  'titulo_msg' => 'Fecha de entrega excedida',   'mensaje' => 'La fecha de entrega de la actividad se excede la fecha límite del propio proyecto. Intente con una fecha acorde al proyecto.'],
        'fecha_menor_invalida'     => ['tipo' => 'error',  'titulo_msg' => 'Fecha de entrega inválida',   'mensaje' => 'La fecha de entrega no puede ser menor a la fecha actual. Intente con una fecha válida.'],
    'exito_activar'       => ['tipo' => 'exito',  'titulo_msg' => 'Tarea activada',   'mensaje' => 'La tarea fue activada correctamente.'],
    'exito_editar'       => ['tipo' => 'exito',  'titulo_msg' => 'Tarea actualizado',   'mensaje' => 'La tarea fue editado correctamente.'],
    'exito_estado'       => ['tipo' => 'exito',  'titulo_msg' => 'Estado actualizado',     'mensaje' => 'El estado de la tarea fue actualizado correctamente.'],
    'exito_operacion'    => ['tipo' => 'exito',  'titulo_msg' => 'Operación completada',   'mensaje' => 'La operación sobre el estudiante fue realizada correctamente.'],
    'exito_borrador'          => ['tipo' => 'exito',  'titulo_msg' => 'Borrador guardado',       'mensaje' => 'El borrador fue guardado correctamente.'],
    'error_cargar'        => ['tipo' => 'error',  'titulo_msg' => 'Error al cargar',        'mensaje' => 'No fue posible cargar los datos. Intenta de nuevo.'],
    'error_editar'       => ['tipo' => 'error',  'titulo_msg' => 'Error al editar',        'mensaje' => 'No fue posible editar la tarea. Verifica los datos e intenta de nuevo.'],
    'error_estado'       => ['tipo' => 'error',  'titulo_msg' => 'Error de estado',        'mensaje' => 'No fue posible actualizar el estado de la tarea.'],
    'error_borrador'          => ['tipo' => 'error',  'titulo_msg' => 'Error al guardar',        'mensaje' => 'No fue posible guardar el borrador. Intenta de nuevo.'],
    'sin_permiso'        => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver la información de la tarea.'],
    'sin_permiso_tarea'   => ['tipo' => 'alerta', 'titulo_msg' => 'Acceso restringido',     'mensaje' => 'No tienes permiso para ver las tareas de la tarea.'],
    'accion_no_permitida' => ['tipo' => 'alerta', 'titulo_msg' => 'Acción no permitida',   'mensaje' => 'La acción solicitada no está disponible para tu rol.'],
];

ob_start();
?>


<div class="container-fluid py-4 ancho_container">

    <!--  Cabecera ─ -->

    <div class="row mb-4 align-items-center">

        <?php
        $titulo      = "Editar Actividad - " . $tarea['tipo'];
        $descripcion = 'Modificar instrucciones, descripción y archivo de guía';
        include __DIR__ . '/../../../public/incluido/_encabezado.php';
        ?>

        <div class="col-6 col-md-6 text-md-end">
            <div class="">
                <span class="badge rounded-pill text-bg-<?= $tareaControlador->EstiloEstadoLista($tarea['estado'] ?? '') ?>">
                    <?= htmlspecialchars($tarea['estado'] ?? '') ?>
                </span>
                <a href="/Modules/Tareas/Views/index.php?id_proyectos=<?= htmlspecialchars($id_proyectos) ?>" class="btn btn-secondary btn-sm px-3">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            </div>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if (isset($_mapa[$msg])):
        extract($_mapa[$msg]);
        include __DIR__ . '/../../../public/incluido/_mensaje.php';
    endif; ?>

    <!--  Última edición ─ -->
    <?php if (!empty($tarea['fecha_modificacion'])): ?>
        <div class="alert alert-info d-flex gap-2 align-items-center py-2 mb-3">
            <small>Última edición: <strong><?= date('d/m/Y H:i', strtotime($tarea['fecha_modificacion'])) ?></strong></small>
        </div>
    <?php endif; ?>

    <!--  Formulario ─ -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i> <b>Información de la tarea</b></div>
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data" id="form-editar">
                <input type="hidden" name="action" value="editarTarea">
                <input type="hidden" name="id_tarea" value="<?= $tarea['id_tarea'] ?>">
                <input type="hidden" name="id_avances" value="<?= $tarea['id_avances'] ?>">
                <input type="hidden" name="id_proyectos" value="<?= htmlspecialchars($id_proyectos ?? '') ?>">
                <input type="hidden" name="id_usuario" value="<?= $id ?>">

                <!--  Descripción  -->
                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Descripción</label>

                    <!-- Nota de límite -->
                    <div class="nota-explicacion mb-2">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>
                            Máximo <strong><?= number_format(MAX_DESCRIPCION, 0, '.', ',') ?> caracteres</strong>
                            (texto plano, sin contar etiquetas HTML).
                            Usa este campo para contextualizar la actividad de forma concisa.
                        </span>
                    </div>

                    <textarea class="form-control editor"
                        name="descripcion"
                        id="editor-descripcion"
                        rows="3"
                        data-max="<?= MAX_DESCRIPCION ?>"
                        data-counter="counter-descripcion"><?= htmlspecialchars($tarea['descripcion'] ?? '') ?></textarea>

                    <div class="char-counter" id="counter-descripcion">
                        <span id="counter-descripcion-val"><?= $lenDescripcion ?></span> / <?= number_format(MAX_DESCRIPCION, 0, '.', ',') ?> caracteres
                    </div>
                </div>

                <!--  Instrucciones  -->
                <div class="mb-3">
                    <label class="form-label tarea-seccion-label">Instrucciones</label>

                    <!-- Nota de límite -->
                    <div class="nota-explicacion mb-2">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>
                            Máximo <strong><?= number_format(MAX_INSTRUCCIONES, 0, '.', ',') ?> caracteres</strong>
                            (texto plano). Redacta pasos claros y concretos;
                            si el contenido es extenso, adjúntalo como archivo de guía.
                        </span>
                    </div>

                    <textarea class="form-control editor"
                        name="instrucciones"
                        id="editor-instrucciones"
                        rows="3"
                        data-max="<?= MAX_INSTRUCCIONES ?>"
                        data-counter="counter-instrucciones"><?= htmlspecialchars($tarea['instrucciones'] ?? '') ?></textarea>

                    <div class="char-counter" id="counter-instrucciones">
                        <span id="counter-instrucciones-val"><?= $lenInstrucciones ?></span> / <?= number_format(MAX_INSTRUCCIONES, 0, '.', ',') ?> caracteres
                    </div>
                </div>

                <!--  Fecha + Archivo ─ -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label tarea-seccion-label">Fecha de entrega</label>
                        <input
                            type="date" name="fecha_entrega" class="form-control" value="<?= htmlspecialchars($tarea['fecha_entrega'] ?? '') ?>" min="<?= htmlspecialchars($proyecto['fecha_inicio']) ?>" max="<?= htmlspecialchars($proyecto['fecha_fin']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label tarea-seccion-label">Archivo de guía actual</label>
                        <?php if (!empty($tarea['archivo_nombre'])): ?>
                            <div class="mb-1">
                                <a href="/Modules/Tareas/Views/descargar_guia.php?id=<?= $tarea['id_tarea'] ?>"
                                    class="small text-danger d-flex align-items-center gap-1">
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                    <?= htmlspecialchars($tarea['archivo_nombre']) ?>
                                </a>
                            </div>
                            <?php if (($tarea['id_tareatipo'] ?? 0) == 12): ?>
                                <small class="text-danger">La plantilla fue proporcionada por la plataforma.</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="small text-muted mb-1">Sin archivo cargado.</p>
                        <?php endif; ?>
                        <label class="form-label tarea-seccion-label mt-1">Subir nuevo archivo</label>
                        <input type="file" name="archivo" class="form-control form-control-sm">
                        <small class="text-muted">Si subes un nuevo archivo reemplazará el anterior.</small>
                    </div>
                </div>

                <!--  Botones ─ -->
                <div class="pt-3 d-flex flex-wrap align-items-center gap-2">
                    <?= $tareaControlador->botonesAccionTarea($tarea['id_tarea'], $rol, $tarea['estado'], $id_proyectos) ?>
                </div>
            </form>

        </div>
    </div>

    <!--  Historial de ediciones ─ -->
    <?php if (!empty($ediciones)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="tarea-seccion-label mb-3">Historial de cambios en esta actividad</p>
                <?php foreach ($ediciones as $ed): ?>
                    <div class="historial-edicion-item">
                        <div class="d-flex flex-wrap justify-content-between gap-1 mb-1">
                            <span class="small fw-semibold text-dark">
                                <?= $campoNombres[$ed['campo_modificado']] ?? htmlspecialchars($ed['campo_modificado']) ?>
                            </span>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($ed['fecha'])) ?>
                                &middot; <?= htmlspecialchars($ed['editor'] ?? '') ?>
                            </small>
                        </div>
                        <?php if ($ed['campo_modificado'] === 'fecha_entrega'): ?>
                            <small class="text-muted">
                                <?= htmlspecialchars($ed['valor_anterior'] ?? '—') ?>
                                <span class="mx-1">→</span>
                                <strong><?= htmlspecialchars($ed['valor_nuevo'] ?? '—') ?></strong>
                            </small>
                        <?php elseif ($ed['campo_modificado'] === 'archivo_guia'): ?>
                            <small class="text-muted">Se actualizó el archivo de guía.</small>
                        <?php else: ?>
                            <small class="text-muted">Contenido actualizado.</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- TinyMCE -->
<script src="/../../../vendor/tinymce/tinymce/tinymce.min.js"></script>
<script>
(function () {

    // 
    // LÍMITES PHP → JS
    // 
    const LIMITES = {
        'editor-descripcion': <?= MAX_DESCRIPCION ?>,
        'editor-instrucciones': <?= MAX_INSTRUCCIONES ?>,
    };

    // 
    // OBTENER TEXTO PLANO
    // 
    function obtenerTexto(editorId) {
        const editor = tinymce.get(editorId);
        return editor
            ? editor.getContent({ format: 'text' })
            : (document.getElementById(editorId)?.value || '');
    }

    // 
    // ACTUALIZAR CONTADOR
    // 
    function actualizarContador(editorId) {

        const max = LIMITES[editorId];
        const counter = document.getElementById(editorId.replace('editor-', 'counter-'));
        const valEl = document.getElementById(editorId.replace('editor-', 'counter-') + '-val');

        if (!counter || !valEl) return null;

        const texto = obtenerTexto(editorId);
        const len = [...texto].length; // UTF-8 safe

        valEl.textContent = len.toLocaleString('es-MX');

        counter.classList.remove('near-limit', 'over-limit');

        if (len > max) {
            counter.classList.add('over-limit');
        } else if (len >= max * 0.9) {
            counter.classList.add('near-limit');
        }

        return { len, max };
    }

    // 
    // VALIDAR LÍMITES
    // 
    function validarLimites() {

        let valido = true;

        for (const editorId of Object.keys(LIMITES)) {

            const result = actualizarContador(editorId);
            if (!result) continue;

            if (result.len > result.max) {
                valido = false;

                const campo = editorId.replace('editor-', '');
                const nombre = campo === 'descripcion' ? 'Descripción' : 'Instrucciones';
                const exceso = result.len - result.max;

                alert(
                    `"${nombre}" supera el límite en ${exceso.toLocaleString('es-MX')} carácter${exceso !== 1 ? 'es' : ''}.\n` +
                    `Reduce el contenido antes de continuar.`
                );

                tinymce.get(editorId)?.focus();
                break;
            }
        }

        return valido;
    }

    // 
    // INIT TINYMCE
    // 
    document.addEventListener('DOMContentLoaded', function () {

        tinymce.init({
            selector: '.editor',
            license_key: 'gpl',
            height: 350,

            plugins: 'lists link table code wordcount charmap insertdatetime',

            toolbar:
                'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table | charmap | insertdatetime | code',

            toolbar_mode: 'sliding',
            branding: false,
            statusbar: true,

            setup(editor) {

                editor.on('init', () => {
                    actualizarContador(editor.id);
                });

                editor.on('input keyup change SetContent', () => {
                    actualizarContador(editor.id);
                });
            }
        });

        // 
        // SUBMIT FORM
        // 
        const form = document.getElementById('form-editar');

        form?.addEventListener('submit', function (e) {

            if (!validarLimites()) {
                e.preventDefault();
                return;
            }

            // sincronizar TinyMCE → textarea
            document.querySelectorAll('.editor').forEach(el => {
                const inst = tinymce.get(el.id);
                inst?.save();
            });
        });

    });

})();
</script>

<?php
$contenido = ob_get_clean();
$titulo    = 'Editar Actividad';
$bodyClass = 'proyectos-page';
include __DIR__ . '/../../../layout.php';
?>