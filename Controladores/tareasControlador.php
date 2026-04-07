<?php

require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';



class TareaControlador
{

    public function index_Principal($id_proyecto, $id_usuario, $rol)
    {


        global $conn;
        try {

            $tareas = new Tarea($conn);

            if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
                //Revisión de estados de tarea
                $tareas->actualizarTareasVencidos();
                $tarea = $tareas->obtenerTareas($id_proyecto, $id_usuario, $rol);
                return $tarea;
            } else {
                $tarea = []; // evita undefined variable
                return $tarea;
            }
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

            if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
                //Revisión de estados de tarea
                $tarea->actualizarTareasVencidos();
                $tarea = $tarea->obtenerTareasLista($id_tarea, $rol);
                return $tarea;
            } else {
                $tarea = []; // evita undefined variable
                return $tarea;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }
    public function tareas($descripcion, $rol, $datos)
    {
        // Solo lectura si NO es estudiante
        $soloLectura = $rol !== "estudiante";
        //$editarComentarios = ($rol === "investigador"); cuando los comentarios solo lo puede dar el docente

        // Normalización del nombre del campo
        $map = [
            "Resumen" => "Resumen",
            "Introducción" => "Introduccion",
            "Introduccion" => "Introduccion",

            "Planteamiento del Problema" => "PlanteamientoProblema",
            "Planteamiento del problema" => "PlanteamientoProblema",

            "Justificación" => "Justificacion",
            "Justificacion" => "Justificacion",

            "Objetivos" => "Objetivos",

            "Marco Teórico" => "MarcoTeorico",
            "Marco teórico y/o de referencia" => "MarcoTeorico",
            "MarcoTeorico" => "MarcoTeorico",

            "Metodología" => "Metodologia",
            "Metodologia" => "Metodologia",

            "Metas, productos esperados e impacto" => "MetasProductosImpacto",
            "Metas, productos esperados e impactos" => "MetasProductosImpacto",

            "Cronograma y recursos" => "Cronograma",
            "Cronograma" => "Cronograma",

            "Referencias bibliograficas" => "Bibliografia",
            "Bibliografía" => "Bibliografia",

            "Anexos" => "Anexos",
            "Reporte Final" => "ReporteFinal"
        ];

        // Normalizar descripción
        $descripcionNorm = $map[$descripcion] ?? $descripcion;

        // Título
        $campo = "<h4>{$this->titulo($descripcionNorm)}</h4>";

        // Obtener contenido directamente
        $valor = $datos['contenido'] ?? '';

        // Crear un único textarea
        $campo .= $this->textarea(
            $descripcion,
            $descripcionNorm,
            $valor,
            $soloLectura
        );

        // Archivo
        $campo .= $this->archivo($datos, $soloLectura);

        // Comentarios
        //$campo .= $this->comentario($datos, $editarComentarios); Cuando se bloquea el comentario para el estudiante
        $campo .= $this->comentario($datos);

        return $campo;
    }

    //Partes que complementan la tarea
    private function textarea($label, $name, $value, $disabled = false, $rows = 7)
    {
        $dis = $disabled ? "disabled" : "";
        return "
    <div class='mb-3'>
        <label>$label:</label>
        <textarea class='form-control' name='contenido' rows='$rows' $dis>$value</textarea>
    </div>";
    }


    private function archivo($datos, $disabled = false)
    {
        $dis = $disabled ? "disabled" : "";
        $file = !empty($datos['archivo_nombre'])
            ? "<a href='descargar.php?id={$datos['id_tarea']}'>Descargar archivo ({$datos['archivo_nombre']})</a>"
            : "<p>No hay archivo cargado.</p>";

        return "
    <div class='mb-3'>
        <label>Archivo actual:</label>
        $file
        <label class='mt-2'>Subir archivo nuevo:</label>
        <input type='file' class='form-control' name='archivo' $dis>
    </div>";
    }

    private function comentario($datos)
    {
        //$dis = $editable ? "" : "disabled";
        $dis = "";
        return "
    <div class='mb-3'>
        <label>Comentarios:</label>
        <textarea class='form-control' name='comentarios' rows='3' $dis>{$datos['comentarios']}</textarea>
    </div>";
    }

    private function titulo($desc)
    {
        $titulos = [
            "Resumen" => "1. Resumen / Abstract",
            "Introduccion" => "2. Introducción",
            "PlanteamientoProblema" => "3. Planteamiento del Problema",
            "Justificacion" => "4. Justificación",
            "Objetivos" => "5. Objetivos",
            "MarcoTeorico" => "6. Marco teórico y/o de referencia",
            "Metodologia" => "7. Metodología",
            "MetasProductosImpacto" => "8. Metas, productos esperados e impacto",
            "Cronograma" => "9. Cronograma",
            "Bibliografia" => "10. Bibliografía",
            "Anexos" => "11. Anexos",
            "ReporteFinal" => "12. Reporte Final"
        ];
        return $titulos[$desc] ?? $desc;
    }


    //Para obtener el número del filtro de la tabla
    private function numerofiltro($action)
    {

        $numerofiltro = 0;
        switch ($action) {
            case 'Total':
                $numerofiltro = 0;
                break;
            case 'Pendiente':
                $numerofiltro = 1;
                break;
            case 'Revisar':
                $numerofiltro = 2;
                break;
            case 'Corregir':
                $numerofiltro = 3;
                break;
            case 'SinActivar':
                $numerofiltro = 4;
                break;
            case 'Aprobado':
                $numerofiltro = 5;
                break;
            case 'Vencido':
                $numerofiltro = 6;
                break;
            case 'Entregado':
                $numerofiltro = 7;
                break;
            case 'Guardar':
                $numerofiltro = 10;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }
    // Encabezados según rol
    //Para obtener los encabezados de las tablas
    public function encabezadosPrincipal($rol)
    {
        switch ($rol) {
            case 'estudiante':
                $encabezados = [
                    'Tarea',
                    'Estado',
                    'Guía',
                    'Fecha Entrega',
                    'Acciones'
                ];
                break;
            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $encabezados = [
                    'Tarea',
                    'Entregado',
                    'Estado',
                    'Guía',
                    'Fecha Entrega',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    //Para obtener los encabezados de las tablas
    public function encabezadosLista($rol)
    {
        switch ($rol) {
            case 'investigador':
            case 'profesor':
            case 'supervisor':
                $encabezados = [
                    'ID',
                    'Estudiante',
                    'Estado',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    public function obtenerbotones($tipo, $id1 = null, $id2 = null, $id3 = null, $id4 = null, $estado = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver Tarea':
                $boton = '<a href="tarea.php?id_asignacion=' . $id1 . '&tipo=' . $id2 . '&id_proyectos=' . $id3 . '&id_tarea=' . $id4 . '&estado=' . $estado . '"  type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Ver lista':
                $boton = '<a href="lista_tareas.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '" type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de tareas"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="tarea.php?id_tarea=' . $id1 . '&id_asignacion=' . $id2 . '&id_proyectos=' . $id3 . '&action=actualizarestado&tipo=Aprobado"
                            class="btn btn-success">Aprobar tarea</a>';
                break;
            case 'Activar':
                $boton = '<a href="editar.php?action=actualizarestado&id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '&tipo=Pendiente" type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Activar tarea"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg> Activar tarea</a>';
                break;
            case 'Editar Tarea':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'EnviarTarea':
                $boton = '<a href="tarea.php?id_tarea=' . $id1 . '&id_asignacion=' . $id2 . '&id_proyectos=' . $id3 . '&action=actualizarestado&tipo=Revisar"
                            class="btn btn-success" type="button" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Enviar tarea al investigador">Enviar tarea</a>';
                break;
            case 'Solicitar Corregir':
                $boton = '<a href="tarea.php?id_tarea=' . $id1 . '&id_asignacion=' . $id2 . '&id_proyectos=' . $id3 . '&action=actualizarestado&tipo=Corregir"
                            class="btn btn-info">Solicitar corregir</a>';
                break;
            case 'Guardar':
                $boton = '<button type="submit" class="btn btn-primary">Guardar cambios</button>';
                break;
            default:
                break;
        }
        return $boton;
    }
    

    public function obtenerbotonesTarea($tipo, $id1 = null, $id2 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Aprobar': //EDITAR_TAREA
                $boton = '<button type="submit" name="tipo" value="Aprobado" class="btn btn-success">Aprobar</button>';
                break;
            case 'EnviarTarea': //EDITAR_TAREA
                $boton = '<button type="submit" name="tipo" value="Revisar" class="btn btn-success">Enviar tarea</button>';
                break;
            case 'Solicitar Corregir': //EDITAR_TAREA
                $boton = '<button type="submit" name="tipo" value="Corregir" class="btn btn-info">Solicitar corregir</button>';
                break;
            case 'Guardar':
                $boton = '<button type="submit" name="tipo" value="Guardar" class="btn btn-guardar">Guardar cambios</button>';
                break;
            case 'Activar':
                $boton = '<a href="editar.php?action=actualizarestado&id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '&tipo=Pendiente" type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Activar tarea"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg> Activar tarea</a>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
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
                    $boton = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton .= $this->obtenerbotones("Editar Tarea", $id, $id_proyectos);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido", "Sin activar"])) {
                    $boton = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton .= $this->obtenerbotones("Detalles", $id, $id_proyectos);
                } elseif ($estado == "Sin activar") {
                    $boton = $this->obtenerbotones("Detalles", $id, $id_proyectos);
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
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente"])) {
                    $boton  = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3, $id4, $estado);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3, $id4, $estado);
                }
                break;
        }

        return $boton;
    }
    //Botones para panel de tareas
    public function botonesAccionTarea($id1, $rol, $estado = null, $id2 = null)
    {
        $boton = "";

        switch ($rol) {
            case 'estudiante':
                if (in_array($estado, ["Revisar", "Corregir", "Pendiente"])) {
                    $boton  = $this->obtenerbotonesTarea("EnviarTarea");
                    $boton  .= $this->obtenerbotonesTarea("Guardar");
                } elseif (in_array($estado, ["Aprobado", "Vencido", "Sin activar"])) {
                }
                break;
            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir"])) {
                    $boton  = $this->obtenerbotonesTarea("Aprobar");
                    $boton  .= $this->obtenerbotonesTarea("Solicitar Corregir");
                    $boton  .= $this->obtenerbotonesTarea("Guardar");
                } elseif (in_array($estado, ["Aprobado", "Vencido", "Pendiente"])) {
                    //$boton  = $this->obtenerbotonesTarea("Guardar");
                } elseif (in_array($estado, ["Sin activar"])) {
                    $boton  = $this->obtenerbotonesTarea("Activar", $id1, $id2);
                    $boton  .= $this->obtenerbotonesTarea("Guardar");
                }
                break;
            case 'supervisor':
                break;
            default:
                break;
        }

        return $boton;
    }

    /* EDITAR TAREA - investigador */
    public function editarTarea($datos, $rol)
    {
        global $conn;

        $conn->begin_transaction();

        try {

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                die("Los datos no fueron enviados correctamente.");
            }

            if ($rol != "investigador" && $rol != "profesor") {
                die("El usuario no tiene permiso para editar tareas.");
            }

            $id_tarea = $datos['id_tarea'];
            $id_proyectos = $datos['id_proyectos'] ?? ($_GET['id_proyectos'] ?? null);
            $descripcion = $datos['descripcion'];
            $instrucciones = $datos['instrucciones'];
            $fecha_entrega = $datos['fecha_entrega'];

            // Datos del archivo
            $archivo = null;
            $archivo_nombre = null;
            $archivo_tipo = null;

            if (!empty($_FILES['archivo']['tmp_name'])) {
                $archivo = file_get_contents($_FILES['archivo']['tmp_name']);
                $archivo_nombre = $_FILES['archivo']['name'];
                $archivo_tipo = $_FILES['archivo']['type'];
            }

            global $conn;
            $tarea = new Tarea($conn);
            $tarea->VincularTareasAntiguas($id_proyectos, $id_tarea);
            $tarea->actualizarTareasVencidos();

            // pasar NULL si no subieron archivo para NO sobreescribir
            $tarea->editarTareaGeneral(
                $id_tarea,
                $descripcion,
                $instrucciones,
                $fecha_entrega,
                $archivo,
                $archivo_nombre,
                $archivo_tipo
            );

            $conn->commit();

            // REDIRECCIÓN SEGURA
            header("Location: editar.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&mensaje=1");
            exit();
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: crear.php?error=1");
            exit;
        }
    }

    //EDITAR TAREAS - GENERAL

    public function editar($datos, $rol, $id_proyecto, $id_asignacion, $id)
    {
        global $conn;

        $conn->begin_transaction();

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                die("Método no permitido");
            }

            if ($rol != "estudiante" && $rol != "investigador") {
                die("El usuario no tiene permiso para editar la tarea.");
            }

            if ($rol == "estudiante") {
                $this->editarTareaEstudiante($datos, $id_proyecto, $id_asignacion);
                $this->actualizarestado($datos['id_tarea'], $rol, $datos['tipo'], $id_proyectos, $id_asignacion, $id, $datos['comentarios']);
            } elseif ($rol == "investigador" || $rol == "profesor") {
                $this->editarTareaRevisar($datos, $id_proyecto);
                $this->actualizarestado($datos['id_tarea'], $rol, $datos['tipo'], $id_proyectos, $id_asignacion, $id, $datos['comentarios']);
            }
            $conn->commit();
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }


    private function editarTareaEstudiante($_datos, $id_proyecto, $id_asignacion)
    {

        // IDs seguros
        $id_asignacion = intval($_datos['id_asignacion'] ?? 0);
        $id_tarea      = intval($_datos['id_tarea'] ?? 0);

        if ($id_asignacion <= 0 || $id_tarea <= 0) {
            die("Datos incompletos para editar tarea.");
        }

        // CONTENIDO
        $contenido = $_datos["contenido"] ?? "";

        // ARCHIVO OPCIONAL
        $archivo = null;
        if (!empty($_FILES['archivo']['tmp_name'])) {
            $archivo = [
                'data' => file_get_contents($_FILES['archivo']['tmp_name']),
                'name' => $_FILES['archivo']['name'],
                'type' => $_FILES['archivo']['type']
            ];
        }

        global $conn;

        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos(); // Esto se mantiene

        // Guardar información
        $tarea->editarTareaEstudiante(
            $id_asignacion,
            $id_tarea,
            $contenido,
            $archivo
        );

        // Redirección luego de guardar
        header("Location: tarea.php?id_asignacion={$id_asignacion}&id_tarea={$id_tarea}&id_proyectos={$id_proyecto}&mensaje=1");
        exit();
    }

    //investigador 
    private function editarTareaRevisar($datos, $id_proyecto)
    {
        $id_asignacion = $datos['id_asignacion'];
        $id_tareas = $datos['id_tarea'];

        $comentarios = ($datos['comentarios'] ?? '');
        global $conn;
        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();
        $tarea->editarTareaRevisar($id_tareas, $comentarios);
        header("Location: tarea.php?id_asignacion={$id_asignacion}&id_tarea={$id_tareas}&id_proyectos={$id_proyecto}&mensaje=1");
        exit();
    }

    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_tarea, $rol, $tipo, $id_proyectos, $id_asignacion = null, $id_usuarios = null, $comentarios = null)
    {

        global $conn;
        $tarea = new Tarea($conn);

        $tarea->actualizarTareasVencidos();
        $tarea->VincularTareasAntiguas($id_proyectos, $id_tarea);
        $numeroEstado = $this->numerofiltro($tipo);

        $tarea->actualizarestado($id_tarea, $numeroEstado, $id_proyectos, $id_asignacion, $id_usuarios, $comentarios);
        if ($tipo == "Aprobado" || $tipo == "Corregir" || $tipo == "Revisar") {
            header("Location: tarea.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}&mensaje=1");
            exit();
        } else {
            header("Location: editar.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}&id_asignacion={$id_asignacion}&mensaje=1");
            exit();
        }
    }

    public function info_linea_tiempo($id_asignacion)
    {
        global $conn;
        try {
            $tarea = new Tarea($conn);

            $tarea->actualizarTareasVencidos();
            if ($id_asignacion) {
                return $tarea->linea_tiempo_tarea($id_asignacion);
            } else {
                $datos = []; // evita undefined variable
                return $datos;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }


    public function mostrarEditarTarea($id_tarea, $rol)
    {

        global $conn;

        try {

            $tareas = new Tarea($conn);

            if ($rol == "investigador" || $rol == "supervisor") {
                //Revisión de estados de tarea
                $tareas->actualizarTareasVencidos();
                $datos = $tareas->obtenerTareaGeneral($id_tarea);
                return $datos;
            } else {
                $datos = []; // evita undefined variable
                return $datos;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }

    public function mostrarTarea($id_asignacion, $rol)
    {
        global $conn;

        try {

            $tareas = new Tarea($conn);

            if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {

                // Actualiza estados vencidos
                $tareas->actualizarTareasVencidos();

                // Obtiene registro directo desde la BD (ya no es JSON)
                $datos = $tareas->obtenerTareaAlumno($id_asignacion);

                // Si no viene nada de la BD → asegurar estructura
                if (!is_array($datos) || empty($datos)) {
                    $datos = [];
                }

                // Asegurar que existan todas las claves, sin arrays internos
                return array_merge([
                    "id_tarea"       => null,
                    "id_asignacion"  => $id_asignacion,
                    "id_proyectos"  => "",
                    "descripcion"    => "",
                    "instrucciones"  => "",
                    "estado"         => "",
                    "tipo_tarea"     => "",
                    "contenido"      => "",
                    "comentarios"    => ""
                ], $datos);
            }

            return [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }

    //Para las tareas estilo classroom 
    public function listarTareasEstudiante($id_usuario)
    {
        global $conn;

        try {
            $tareas = new Tarea($conn);
            return $tareas->obtenerTareasEstudiante($id_usuario);
        } catch (Exception $e) {
            error_log($e->getMessage());
            exit;
        }
    }

    public function estiloEstado($estado)
    {
        return match ($estado) {
            1 => "primary",   // Pendiente
            2 => "warning",   // Revisar
            3 => "danger",    // Corregir
            5 => "success",   // Aprobado
            6 => "dark",      // Vencido
            7 => "info",      // Entregado
            default => "secondary",
        };
    }

    public function EstiloEstadoLista($estado)
    {
        switch ($estado) {

            case 'Pendiente':
            case 'Revisar':
            case 'Corregir':
                $estilo = "warning";
                break;
            case 'Vencido':
                $estilo = "secondary";
                break;
            case 'Aprobado':
                $estilo = "success";
                break;
            case 'Sin activar':
                $estilo = "dark";
                break;
            default:
                $estilo = "info";
                break;
        }
        return $estilo;
    }
}
