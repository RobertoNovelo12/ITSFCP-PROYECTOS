<?php
// Controlador de tareas
require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class TareaControlador
{
    //  ACCIONES PRINCIPALES
    public function index_Principal($id_proyecto, $id_usuario, $rol)
    {
        global $conn;
        try {
            $tareas = new Tarea($conn);
            if (in_array($rol, ['investigador', 'estudiante', 'supervisor'])) {
                $tareas->actualizarTareasVencidos();
                return $tareas->obtenerTareas($id_proyecto, $id_usuario, $rol);
            }
            return [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function index_Lista($id_tarea, $rol)
    {
        global $conn;
        try {
            $tarea = new Tarea($conn);
            if (in_array($rol, ['investigador', 'estudiante', 'supervisor'])) {
                $tarea->actualizarTareasVencidos();
                return $tarea->obtenerTareasLista($id_tarea, $rol);
            }
            return [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //  CONTENIDO DE LA TAREA (textarea, archivo, comentario)
    public function tareas($descripcion, $rol, $datos)
    {
        $soloLectura = ($rol !== 'estudiante');

        $map = [
            "Resumen"                              => "Resumen",
            "Introducción"                         => "Introduccion",
            "Introduccion"                         => "Introduccion",
            "Planteamiento del Problema"           => "PlanteamientoProblema",
            "Planteamiento del problema"           => "PlanteamientoProblema",
            "Justificación"                        => "Justificacion",
            "Justificacion"                        => "Justificacion",
            "Objetivos"                            => "Objetivos",
            "Marco Teórico"                        => "MarcoTeorico",
            "Marco teórico y/o de referencia"      => "MarcoTeorico",
            "MarcoTeorico"                         => "MarcoTeorico",
            "Metodología"                          => "Metodologia",
            "Metodologia"                          => "Metodologia",
            "Metas, productos esperados e impacto" => "MetasProductosImpacto",
            "Metas, productos esperados e impactos"=> "MetasProductosImpacto",
            "Cronograma y recursos"                => "Cronograma",
            "Cronograma"                           => "Cronograma",
            "Referencias bibliograficas"           => "Bibliografia",
            "Bibliografía"                         => "Bibliografia",
            "Anexos"                               => "Anexos",
            "Reporte Final"                        => "ReporteFinal",
        ];

        $descripcionNorm = $map[$descripcion] ?? $descripcion;
        $campo  = "<h5 class='fw-semibold mb-2'>{$this->titulo($descripcionNorm)}</h5>";
        $valor  = $datos['contenido'] ?? '';
        $campo .= $this->textarea($descripcion, $descripcionNorm, $valor, $soloLectura);
        $campo .= $this->archivo($datos, $soloLectura);
        $campo .= $this->comentario($datos);
        return $campo;
    }

    private function textarea($label, $name, $value, $disabled = false, $rows = 7)
    {
        $dis = $disabled ? "disabled" : "";

        return "
    <div class='mb-3'>
        <label class='form-label text-muted small'>$label:</label>
        <textarea class='form-control editor' name='contenido' rows='$rows' $dis>" . htmlspecialchars($value) . "</textarea>
    </div>";
    }

    private function archivo($datos, $disabled = false)
    {
        $dis  = $disabled ? "disabled" : "";
        $file = !empty($datos['archivo_nombre'])
            ? "<a href='descargar.php?id={$datos['id_tarea']}' class='d-block mb-2 small'>
                <svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='currentColor' class='bi bi-paperclip me-1' viewBox='0 0 16 16'>
                  <path d='M4.5 3a2.5 2.5 0 0 1 5 0v9a1.5 1.5 0 0 1-3 0V5a.5.5 0 0 1 1 0v7a.5.5 0 0 0 1 0V3a1.5 1.5 0 1 0-3 0v9a2.5 2.5 0 0 0 5 0V5a.5.5 0 0 1 1 0v7a3.5 3.5 0 1 1-7 0z'/>
                </svg>" . htmlspecialchars($datos['archivo_nombre']) . "</a>"
            : "<p class='text-muted small mb-2'>Sin archivo adjunto.</p>";

        $inputFile = !$disabled
            ? "<div class='mt-1'><label class='form-label text-muted small'>Adjuntar archivo:</label>
               <input type='file' class='form-control form-control-sm' name='archivo'></div>"
            : "";

        return "
        <div class='mb-3'>
            <label class='form-label text-muted small'>Archivo de guía:</label>
            $file
            $inputFile
        </div>";
    }

    private function comentario($datos)
    {
        return "
    <div class='mb-3'>
        <label class='form-label text-muted small'>Comentarios:</label>
        <textarea class='form-control' name='comentarios' rows='3'>" . htmlspecialchars($datos['comentarios'] ?? '') . "</textarea>
    </div>";
    }

    private function titulo($desc)
    {
        $titulos = [
            "Resumen"              => "1. Resumen / Abstract",
            "Introduccion"         => "2. Introducción",
            "PlanteamientoProblema"=> "3. Planteamiento del Problema",
            "Justificacion"        => "4. Justificación",
            "Objetivos"            => "5. Objetivos",
            "MarcoTeorico"         => "6. Marco teórico y/o de referencia",
            "Metodologia"          => "7. Metodología",
            "MetasProductosImpacto"=> "8. Metas, productos esperados e impacto",
            "Cronograma"           => "9. Cronograma",
            "Bibliografia"         => "10. Bibliografía",
            "Anexos"               => "11. Anexos",
            "ReporteFinal"         => "12. Reporte Final",
        ];
        return $titulos[$desc] ?? $desc;
    }

    private function numerofiltro($action)
    {
        return match ($action) {
            'Total'      => 0,
            'Pendiente'  => 1,
            'Revisar'    => 2,
            'Corregir'   => 3,
            'SinActivar' => 4,
            'Aprobado'   => 5,
            'Vencido'    => 6,
            'Entregado'  => 7,
            'Borrador'   => 8,   // ← estado borrador correcto (antes era Guardar=10, inconsistente)
            default      => 0,
        };
    }

    //  ENCABEZADOS
    public function encabezadosPrincipal($rol)
    {
        return match ($rol) {
            'estudiante'                              => ['Actividad', 'Estado', 'Guía', 'Fecha Entrega', 'Acciones'],
            'investigador', 'profesor', 'supervisor'  => ['Actividad', 'Entregas', 'Estado', 'Guía', 'Fecha Entrega', 'Acciones'],
            default => [],
        };
    }

    public function encabezadosLista($rol)
    {
        return match ($rol) {
            'investigador', 'profesor', 'supervisor' => ['ID', 'Estudiante', 'Estado', 'Entrega', 'Acciones'],
            default => [],
        };
    }

    //  BOTONES
    public function obtenerbotones($tipo, $id1 = null, $id2 = null, $id3 = null, $id4 = null, $estado = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver Tarea':
                $boton = '<a href="tarea.php?id_asignacion=' . $id1 . '&tipo=' . $id2 . '&id_proyectos=' . $id3 . '&id_tarea=' . $id4 . '&estado=' . $estado . '"
                    class="btn btn-sm btn-primary" title="Ver tarea">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                      <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                      <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                    </svg> Ver</a>';
                break;
            case 'Ver lista':
                $boton = '<a href="lista_tareas.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-secondary" title="Ver lista de estudiantes" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de alumnos">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                      <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
                    </svg></a>';
                break;
            case 'Editar Tarea':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-warning" title="Editar tarea" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar tarea">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                      <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                    </svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"
                    class="btn btn-info" title="Ver detalles" data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
                      <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                      <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>';
                break;
        }
        return $boton;
    }

    public function obtenerbotonesTarea($tipo, $id1 = null, $id2 = null)
    {
        return match ($tipo) {
            'Aprobar'            => '<button type="submit" name="tipo" value="Aprobado"  class="btn btn-success btn-sm">✓ Aprobar</button>',
            'EnviarTarea'        => '<button type="submit" name="tipo" value="Revisar"   class="btn btn-primary btn-sm">Enviar tarea</button>',
            'ReenviarTarea'      => '<button type="submit" name="tipo" value="Revisar"   class="btn btn-primary btn-sm">Volver a enviar</button>',
            'Solicitar Corregir' => '<button type="submit" name="tipo" value="Corregir"  class="btn btn-warning btn-sm">Solicitar corrección</button>',
            // El botón Guardar Borrador usa action propio, no tipo (se maneja por separado en la vista)
            'Guardar'            => '<button type="submit" form="form-borrador" class="btn btn-outline-secondary btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-floppy me-1" viewBox="0 0 16 16">
                                          <path d="M11 2H9v3h2z"/><path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zm6 6.5v3h-4v-3h4a.5.5 0 0 1 0 0"/>
                                        </svg> Guardar borrador</button>',
            'Activar'            => '<a href="editar.php?action=actualizarestado&id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '&tipo=Pendiente" class="btn btn-success btn-sm">Activar tarea</a>',
            default              => '',
        };
    }

    public function botonesAccionPrincipal($id, $rol, $estado = null, $id_proyectos = null)
    {
        $boton = "";
        switch ($rol) {
            case 'estudiante':
                if ($estado != "") {
                    $boton = $this->obtenerbotones("Ver Tarea", $id);
                }
                break;
            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido", "Sin activar"])) {
                    $boton  = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton .= " " . $this->obtenerbotones("Editar Tarea", $id, $id_proyectos);
                }
                break;
            case 'supervisor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido", "Sin activar"])) {
                    $boton  = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton .= " " . $this->obtenerbotones("Detalles", $id, $id_proyectos);
                }
                break;
        }
        return $boton;
    }

    public function botonesAccionLista($id1, $rol, $estado = null, $id2 = null, $id3 = null, $id4 = null)
    {
        $boton = "";
        switch ($rol) {
            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente", "Borrador"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3, $id4, $estado);
                }
                break;
            case 'supervisor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente", "Borrador"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3, $id4, $estado);
                }
                break;
        }
        return $boton;
    }

    public function botonesAccionTarea($id_tarea, $rol, $estado, $id2 = null)
    {
        $boton = "";
        switch ($rol) {
            case 'estudiante':
                // Estado Pendiente o Borrador: puede enviar o guardar borrador
                if (in_array($estado, ["Pendiente", "Borrador"])) {
                    $boton  = $this->obtenerbotonesTarea("EnviarTarea");
                    $boton .= " " . $this->obtenerbotonesTarea("Guardar");
                }
                // Estado Corregir: puede reenviar o guardar borrador
                elseif ($estado === "Corregir") {
                    $boton  = $this->obtenerbotonesTarea("ReenviarTarea");
                    $boton .= " " . $this->obtenerbotonesTarea("Guardar");
                }
                // Estado Revisar: solo puede guardar borrador (ya envió, espera revisión)
                elseif ($estado === "Revisar") {
                    $boton = $this->obtenerbotonesTarea("Guardar");
                }
                break;
            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir"])) {
                    $boton  = $this->obtenerbotonesTarea("Aprobar");
                    $boton .= " " . $this->obtenerbotonesTarea("Solicitar Corregir");
                } elseif ($estado == "Sin activar") {
                    $boton = $this->obtenerbotonesTarea("Activar", $id_tarea, $id2);
                }
                break;
        }
        return $boton;
    }

    //  EDITAR TAREA GENERAL (investigador - plantilla)
    public function editarTarea($datos, $rol, $id_proyectos = null)
    {
        global $conn;
        $conn->begin_transaction();
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Método no permitido.");
            if (!in_array($rol, ['investigador', 'profesor'])) die("Sin permiso.");

            $id_tarea      = intval($datos['id_tarea']);
            $id_proyectos  = intval($datos['id_proyectos'] ?? ($_GET['id_proyectos'] ?? 0));
            $descripcion   = $datos['descripcion'] ?? '';
            $instrucciones = $datos['instrucciones'] ?? '';
            $fecha_entrega = $datos['fecha_entrega'] ?? '';
            $id_usuario    = intval($datos['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);

            $tarea = new Tarea($conn);
            $tarea->actualizarTareasVencidos();

            $id_documento_recurso = null;

            if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $archivo      = $_FILES['archivo'];
                $extension    = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                $nombreFinal  = uniqid() . '_' . basename($archivo['name']);
                $tipo_mime    = $archivo['type'];
                $tamano_bytes = $archivo['size'];

                $base       = "/ITSFCP-PROYECTOS/storage/recursos/investigador_{$id_usuario}/proyecto_{$id_proyectos}/";
                $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
                $rutaBD     = $base . $nombreFinal;

                if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0755, true);
                if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) throw new Exception("Error al guardar archivo.");

                $id_documento_recurso = $tarea->registrarDocumento(
                    nombre: basename($archivo['name']),
                    nombre_archivo: $nombreFinal,
                    ruta: $rutaBD,
                    tipo_mime: $tipo_mime,
                    extension: $extension,
                    tamano_bytes: $tamano_bytes,
                    tipo: 'recurso',
                    visibilidad: 'privado',
                    id_usuario: $id_usuario,
                    id_proyecto: $id_proyectos
                );
            }

            $tarea->editarTareaGeneral($id_tarea, $descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_usuario);
            $conn->commit();
            header("Location: editar.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&mensaje=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }

    //  EDITAR (enrutador estudiante / investigador)
    //  Solo se usa para cambios de estado (Revisar, Corregir, Aprobado).
    //  El borrador tiene su propio método: guardar_borrador().
    public function editar($datos, $rol, $id_proyecto, $id_asignacion, $id)
    {
        global $conn;
        $conn->begin_transaction();
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Método no permitido");
            if (!in_array($rol, ['estudiante', 'investigador', 'profesor'])) die("Sin permiso.");

            if ($rol === 'estudiante') {
                // Primero guarda el contenido/archivo y luego cambia el estado
                $this->_guardarContenidoEstudiante($datos, $id_proyecto, $id_asignacion);
            }
            // El investigador solo cambia estado (Aprobar / Solicitar Corregir) y deja comentario
            $this->actualizarestado(
                $datos['id_tarea'],
                $rol,
                $datos['tipo'],
                $id_proyecto,
                $id_asignacion,
                $id,
                $datos['comentarios'] ?? ''
            );
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            header("Location: tarea.php?error=1");
            exit;
        }
    }

    // 
    //  GUARDAR CONTENIDO DEL ESTUDIANTE (privado, sin cambio de estado)
    //  Utilizado tanto por editar() como por guardar_borrador().
    //  Devuelve el id_documento_entrega generado (o null si no hubo archivo).
    // 
    private function _guardarContenidoEstudiante(array $datos, $id_proyecto, $id_asignacion): ?int
    {
        $id_asignacion = intval($datos['id_asignacion'] ?? 0);
        $id_tarea      = intval($datos['id_tarea']      ?? 0);
        if ($id_asignacion <= 0 || $id_tarea <= 0) die("Datos incompletos.");

        require_once __DIR__ . '/../../vendor/autoload.php';
        $config   = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);

        $contenido   = $purifier->purify($datos['contenido']   ?? '');
        $comentarios = $purifier->purify($datos['comentarios'] ?? '');

        $id_usuario = intval($_SESSION['id_usuario'] ?? 0);

        global $conn;
        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();

        $id_documento_entrega = null;

        if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo      = $_FILES['archivo'];
            $extension    = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $nombreFinal  = uniqid() . '_' . basename($archivo['name']);
            $tipo_mime    = $archivo['type'];
            $tamano_bytes = $archivo['size'];
            $etapa_num    = intval($datos['etapa'] ?? 0);
            $subcarpeta   = $etapa_num > 0 ? "actividad_{$etapa_num}" : 'actividad';

            $base       = "/ITSFCP-PROYECTOS/storage/entregas/alumno_{$id_usuario}/proyecto_{$id_proyecto}/{$subcarpeta}/";
            $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
            $rutaBD     = $base . $nombreFinal;

            if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0755, true);
            if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) throw new Exception("Error al guardar archivo.");

            $id_documento_entrega = $tarea->registrarDocumento(
                nombre: basename($archivo['name']),
                nombre_archivo: $nombreFinal,
                ruta: $rutaBD,
                tipo_mime: $tipo_mime,
                extension: $extension,
                tamano_bytes: $tamano_bytes,
                tipo: 'entrega',
                visibilidad: 'privado',
                id_usuario: $id_usuario,
                id_proyecto: intval($id_proyecto),
                etapa: $etapa_num ?: null
            );
        }

        $tarea->editarTareaEstudiante($id_asignacion, $id_tarea, $contenido, $comentarios, $id_documento_entrega);

        return $id_documento_entrega;
    }

    // 
    //  GUARDAR BORRADOR (estudiante)
    //  Guarda contenido + archivo opcional y cambia estado a Borrador (8).
    //  No cambia a Revisar ni ningún otro estado.
    // 
    public function guardar_borrador(
        int    $id_tarea,
        int    $id_proyectos,
        int    $id_asignacion,
        int    $id_usuarios,
        string $contenido,
        string $comentarios = ''
    ): void {
        global $conn;
        $conn->begin_transaction();
        try {
            $tarea = new Tarea($conn);
            $tarea->actualizarTareasVencidos();

            require_once __DIR__ . '/../../vendor/autoload.php';
            $config   = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($config);
            $contenido_p   = $purifier->purify($contenido);
            $comentarios_p = $purifier->purify($comentarios);

            // Subida de archivo opcional (misma lógica que _guardarContenidoEstudiante)
            $id_documento_entrega = null;
            if (!empty($_FILES['archivo']['tmp_name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $archivo      = $_FILES['archivo'];
                $extension    = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                $nombreFinal  = uniqid() . '_' . basename($archivo['name']);
                $tipo_mime    = $archivo['type'];
                $tamano_bytes = $archivo['size'];

                // Sin número de etapa en el borrador (usamos carpeta genérica)
                $base       = "/ITSFCP-PROYECTOS/storage/entregas/alumno_{$id_usuarios}/proyecto_{$id_proyectos}/actividad/";
                $rutaFisica = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
                $rutaBD     = $base . $nombreFinal;

                if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0755, true);
                if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) throw new Exception("Error al guardar archivo del borrador.");

                $id_documento_entrega = $tarea->registrarDocumento(
                    nombre: basename($archivo['name']),
                    nombre_archivo: $nombreFinal,
                    ruta: $rutaBD,
                    tipo_mime: $tipo_mime,
                    extension: $extension,
                    tamano_bytes: $tamano_bytes,
                    tipo: 'entrega',
                    visibilidad: 'privado',
                    id_usuario: $id_usuarios,
                    id_proyecto: $id_proyectos
                );
            }

            $tarea->guardar_borrador($id_tarea, $id_asignacion, $id_usuarios, $contenido_p, $comentarios_p, $id_documento_entrega);
            $conn->commit();

            header("Location: tarea.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}&mensaje=borrador");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            header("Location: tarea.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}&error=1");
            exit;
        }
    }

    //  ACTUALIZAR ESTADO (público, llamado desde vistas)
    public function actualizarestado($id_tarea, $rol, $tipo, $id_proyectos, $id_asignacion = null, $id_usuarios = null, $comentarios = null)
    {
        global $conn;
        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();

        if (!$id_usuarios) $id_usuarios = intval($_SESSION['id_usuario'] ?? 0);

        $numeroEstado = $this->numerofiltro($tipo);

        require_once __DIR__ . '/../../vendor/autoload.php';
        $config        = HTMLPurifier_Config::createDefault();
        $purifier      = new HTMLPurifier($config);
        $comentarios_p = $purifier->purify($comentarios ?? '');

        $tarea->actualizarestado($id_tarea, $numeroEstado, $id_proyectos, $id_asignacion, $id_usuarios, $comentarios_p);

        if (in_array($tipo, ["Aprobado", "Corregir", "Revisar"])) {
            header("Location: tarea.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}&mensaje=1");
        } else {
            header("Location: editar.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&mensaje=1");
        }
        exit();
    }

    //  LÍNEA DE TIEMPO
    public function info_linea_tiempo($id_asignacion)
    {
        global $conn;
        try {
            $pagina = $_GET['pagina'] ?? 1;
            $tarea  = new Tarea($conn);
            $tarea->actualizarTareasVencidos();
            if ($id_asignacion) {
                return $tarea->linea_tiempo_tarea($id_asignacion, $pagina);
            }
            return ["datos" => [], "paginacion" => ["total" => 0, "por_pagina" => 10, "pagina" => 1, "total_paginas" => 1]];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ["datos" => [], "paginacion" => ["total" => 0, "por_pagina" => 10, "pagina" => 1, "total_paginas" => 1]];
        }
    }

    //  MOSTRAR TAREA GENERAL (editar/detalles)
    public function mostrarEditarTarea($id_tarea, $rol)
    {
        global $conn;
        try {
            $tareas = new Tarea($conn);
            if (in_array($rol, ['investigador', 'supervisor'])) {
                $tareas->actualizarTareasVencidos();
                return $tareas->obtenerTareaGeneral($id_tarea);
            }
            return [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function mostrarTarea($id_asignacion, $rol)
    {
        global $conn;
        try {
            $tareas = new Tarea($conn);
            if (in_array($rol, ['investigador', 'estudiante', 'supervisor'])) {
                $tareas->actualizarTareasVencidos();
                $datos = $tareas->obtenerTareaAlumno($id_asignacion);
                if (!is_array($datos) || empty($datos)) $datos = [];
                return array_merge([
                    "id_tarea"           => null,
                    "id_asignacion"      => $id_asignacion,
                    "id_proyectos"       => "",
                    "descripcion"        => "",
                    "instrucciones"      => "",
                    "estado"             => "",
                    "id_estadoT"         => 0,
                    "tipo_tarea"         => "",
                    "contenido"          => "",
                    "comentarios"        => "",
                    "fecha_modificacion" => null,
                    "guia_nombre"        => null,
                    "guia_ruta"          => null,
                ], $datos);
            }
            return [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //  LISTAR TAREAS ESTUDIANTE
    public function listarTareasEstudiante($id_usuario)
    {
        global $conn;
        try {
            $tareas = new Tarea($conn);
            return $tareas->obtenerTareasEstudiante($id_usuario);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //  ESTILOS DE ESTADO
    public function estiloEstado($estado)
    {
        return match ((int)$estado) {
            1 => "primary",
            2 => "warning",
            3 => "danger",
            5 => "success",
            6 => "secondary",
            7 => "info",
            8 => "light",    // borrador
            default => "light",
        };
    }

    public function EstiloEstadoLista($estado)
    {
        return match ($estado) {
            'Pendiente'   => "primary",
            'Revisar'     => "warning",
            'Corregir'    => "danger",
            'Vencido'     => "dark",
            'Aprobado'    => "success",
            'Sin activar' => "secondary",
            'Borrador'    => "light",
            default       => "light",
        };
    }

    //  OBTENER EDICIONES RECIENTES
    public function obtenerEdicionesRecientes($id_tarea, $limite = 5)
    {
        global $conn;
        $tarea = new Tarea($conn);
        return $tarea->obtenerEdicionesRecientes($id_tarea, $limite);
    }
}