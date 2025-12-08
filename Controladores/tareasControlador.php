<?php

require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';



class TareaControlador
{

    public function index_Principal($id_proyecto, $id_usuario, $rol)
    {
        global $conn;

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
    }
    public function index_Lista($id_tarea, $rol)
    {
        global $conn;

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
    }
    public function tareas($descripcion, $rol, $datos)
    {
        $c = fn($label, $name, $row = true) => $this->textarea($label, $name, $datos['contenido'][$name] ?? '', !$row);

        $soloLectura = $rol !== "estudiante";
        $editarComentarios = ($rol === "investigador");

        $campo = "<h4>{$this->titulo($descripcion)}</h4>";

        switch ($descripcion) {

            case 'hidden':
                return "<input type='hidden' name='action' value='editarTarea'>
                    <input type='hidden' name='id_tareas' value='{$datos['id_tarea']}'>";

            case 'Resumen':
                $campo .= $this->textarea(
                    "Resumen / Abstract",
                    "Resumen",
                    $datos['contenido']['Resumen'] ?? '',
                    $soloLectura
                );
                break;

            case 'Introducción':
                $campo .= $this->textarea(
                    "Introducción",
                    "Introduccion",
                    $datos['contenido']['Introduccion'] ?? '',
                    $soloLectura
                );
                break;

            case 'PlanteamientoProblema':
                $campo .= $this->textarea(
                    "Planteamiento del problema",
                    "PlanteamientoProblema",
                    $datos['contenido']['PlanteamientoProblema'] ?? '',
                    $soloLectura
                );
                break;

            case 'Justificacion':
                $campo .= $this->textarea(
                    "Justificación",
                    "Justificacion",
                    $datos['contenido']['Justificacion'] ?? '',
                    $soloLectura
                );
                break;

            case 'Objetivos':
                $campo .= $this->textarea(
                    "Objetivo general",
                    "ObjetivoGeneral",
                    $datos['contenido']['ObjetivoGeneral'] ?? '',
                    $soloLectura
                );
                $campo .= $this->textarea(
                    "Objetivos específicos",
                    "ObjetivosEspecificos",
                    $datos['contenido']['ObjetivosEspecificos'] ?? '',
                    $soloLectura
                );
                break;

            case 'MarcoTeorico':
                foreach (["Antecedentes", "Fundamentacion", "MarcoConceptual"] as $sec)
                    $campo .= $this->textarea(
                        $sec,
                        $sec,
                        $datos['contenido'][$sec] ?? '',
                        $soloLectura
                    );
                break;

            case 'Metodologia':
                foreach (["TipoEstudio", "MetodosTecnicas", "PoblacionMuestra", "Instrumentos"] as $sec)
                    $campo .= $this->textarea(
                        $sec,
                        $sec,
                        $datos['contenido'][$sec] ?? '',
                        $soloLectura
                    );
                break;

            case 'MetasProductosImpacto':
                foreach (["Metas", "ProductosEsperados", "Impacto"] as $sec)
                    $campo .= $this->textarea(
                        $sec,
                        $sec,
                        $datos['contenido'][$sec] ?? '',
                        $soloLectura
                    );
                break;

            case 'Cronograma':
                $campo .= $this->textarea(
                    "Cronograma",
                    "Cronograma",
                    $datos['contenido']['Cronograma'] ?? '',
                    $soloLectura,
                    6
                );
                break;

            case 'Bibliografia':
                $campo .= $this->textarea(
                    "Bibliografía",
                    "Bibliografia",
                    $datos['contenido']['Bibliografia'] ?? '',
                    $soloLectura,
                    6
                );
                break;

            case 'Anexos':
                foreach (["Instrumentos", "Consentimiento", "Mapas", "Documentacion"] as $sec)
                    $campo .= $this->textarea(
                        $sec,
                        $sec,
                        $datos['contenido'][$sec] ?? '',
                        $soloLectura
                    );
                break;
        }

        // ARCHIVO
        $campo .= $this->archivo($datos, $soloLectura);

        // COMENTARIO
        $campo .= $this->comentario($datos, $editarComentarios);

        return $campo;
    }

    private function textarea($label, $name, $value, $disabled = false, $rows = 4)
    {
        $dis = $disabled ? "disabled" : "";
        return "
    <div class='mb-3'>
        <label>$label:</label>
        <textarea class='form-control' name='$name' rows='$rows' $dis>$value</textarea>
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

    private function comentario($datos, $editable)
    {
        $dis = $editable ? "" : "disabled";
        return "
    <div class='mb-3'>
        <label>Comentarios:</label>
        <textarea class='form-control' name='Comentarios' rows='3' $dis>{$datos['comentarios']}</textarea>
    </div>";
    }

    private function titulo($desc)
    {
        $titulos = [
            "Resumen" => "1. Resumen / Abstract",
            "Introducción" => "2. Introducción",
            "PlanteamientoProblema" => "3. Planteamiento del Problema",
            "Justificacion" => "4. Justificación",
            "Objetivos" => "5. Objetivos",
            "MarcoTeorico" => "6. Marco teórico y/o de referencia",
            "Metodologia" => "7. Metodología",
            "MetasProductosImpacto" => "8. Metas, productos esperados e impacto",
            "Cronograma" => "9. Cronograma",
            "Bibliografia" => "10. Bibliografía",
            "Anexos" => "11. Anexos (Opcional)",
        ];
        return $titulos[$desc] ?? "";
    }



    //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
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
                    'estudiante',
                    'Estado',
                    'Fecha Revisión',
                    'Fecha Corrección',
                    'Fecha Aprobación',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    public function obtenerbotones($tipo, $id1 = null, $id2 = null, $id3 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver Tarea':
                $boton = '<a href="tarea.php?id_asignacion=' . $id1 . '&tipo=' . $id2 . '&id_proyectos=' . $id3 . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Ver lista':
                $boton = '<a href="lista_tareas.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de tareas"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="lista_tareas.php?action=actualizarestado&id_tarea=' . $id1 . '&tipo=Activos"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar tarea"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Activar':
                $boton = '<a href="editar.php?action=actualizarestado&id_tarea=' . $id1 . '&tipo=Pendiete"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Activar tarea"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Editar Tarea':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"><button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></button></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tarea=' . $id1 . '&id_proyectos=' . $id2 . '"><button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'EnviarTarea':
                $boton = '<a href="tarea.php?id_tarea=' . $id1 . '&action=editarTareaEstudiante&tipo=Revisar"><button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Solicitar Corregir':
                $boton = '<a href="editar.php?id_tarea=' . $id1 . '&action=editarTareaRevisar&tipo=Corregir"><button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Solicitar corregir"><<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2z"/>
  <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466"/>
</svg></button></a>';
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
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton .= $this->obtenerbotones("Editar Tarea", $id, $id_proyectos);
                } elseif ($estado == "Sin activar") {
                    $boton .= $this->obtenerbotones("Editar Tarea", $id, $id_proyectos);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton  = $this->obtenerbotones("Ver lista", $id, $id_proyectos);
                    $boton = $this->obtenerbotones("Detalles", $id, $id_proyectos);
                } elseif ($estado == "Sin activar") {
                    $boton = $this->obtenerbotones("Detalles", $id, $id_proyectos);
                }
                break;
        }

        return $boton;
    }

    public function botonesAccionLista($id1, $rol, $estado = null, $id2 = null, $id3 = null)
    {
        $boton = "";

        switch ($rol) {

            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir"])) {
                    $boton  = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                    $boton .= $this->obtenerbotones("Aprobar", $id1);
                    $boton .= $this->obtenerbotones("Solicitar Corregir", $id1);
                } elseif (in_array($estado, ["Aprobado", "Vencido", "Pendiente"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido", "Pendiente"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id1, $id2, $id3);
                }
                break;
        }

        return $boton;
    }

    /* EDITAR TAREA - investigador */
    public function editarTarea($datos, $rol,$id_proyectos)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Los datos no fueron enviados correctamente.");
        }

        if ($rol != "investigador" && $rol != "profesor") {
            die("El usuario no tiene permiso para editar tareas.");
        }

        $id_tarea = $datos['id_tarea'];
        $descripcion = $datos['descripcion'];
        $instrucciones = $datos['instrucciones']; // <-- corregido
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

        // REDIRECCIÓN SEGURA
        header("Location: editar.php?id_tarea=" . $id_tarea);
        exit();
    }


    public function editarTareaEstudiante($datos, $id_usuario, $rol)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "estudiante") {

                $action = $datos['action'] ?? '';

                $id_asignacion = $datos['id_asignacion'];
                $id_tarea = $datos['id_tarea'];
                $id_estudiante = $id_usuario;
                // Contenido anterior de la tarea (desde la BD)
                $contenidoActual = json_decode($datos['contenido'], true) ?? [];

                // Aquí guardaremos solo lo nuevo
                $contenidoNuevo = [];

                // Lista de TODOS los posibles campos que pueden existir en cualquier tarea
                $mapaCampos = [
                    // Resumen
                    "Resumen",

                    // Introducción
                    "Introducción",

                    // Planteamiento del problema
                    "PlanteamientoProblema",

                    // Justificación
                    "Justificacion",

                    // Objetivos
                    "ObjetivoGeneral",
                    "ObjetivosEspecificos",

                    // Marco teórico
                    "Antecedentes",
                    "Fundamentacion",
                    "MarcoConceptual",
                    "ComentariosMarcoTeorico",

                    // Metodología
                    "TipoEstudio",
                    "MetodosTecnicas",
                    "PoblacionMuestra",
                    "Instrumentos",

                    // Metas, productos e impacto
                    "Metas",
                    "ProductosEsperados",
                    "Impacto",

                    // Cronograma
                    "Cronograma",

                    // Bibliografía
                    "Bibliografia",

                    // Anexos
                    "Consentimiento",
                    "Mapas",
                    "Documentacion",
                ];

                // Recorrer cada posible campo y ver si vino en el POST
                foreach ($mapaCampos as $campo) {
                    if (isset($_POST[$campo])) {
                        $contenidoNuevo[$campo] = $_POST[$campo];
                    }
                }

                // Combinar: se mantienen los anteriores y solo sobrescriben los nuevos
                $contenidoFinal = array_merge($contenidoActual, $contenidoNuevo);

                // Convertir a JSON para guardar en BD
                $contenidoJson = json_encode($contenidoFinal, JSON_UNESCAPED_UNICODE);

                $archivo = null;

                if (!empty($_FILES['archivo']['tmp_name'])) {
                    $archivo = [
                        'data' => file_get_contents($_FILES['archivo']['tmp_name']),
                        'name' => $_FILES['archivo']['name'],
                        'type' => $_FILES['archivo']['type']
                    ];
                }

                if ($action == 'editarTareaEstudiante') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaEstudiante($id_asignacion, $id_tarea, $id_estudiante, $contenidoJson, $archivo);
                    header("Location: tarea.php?id_asignacion={$id_asignacion}");
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tarea");
        }
    }
    //investigador 
    public function editarTareaRevisar($datos, $id_usuario, $rol)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "investigador" || $rol == "profesor") {

                $action = $datos['action'] ?? '';

                $id_asignacion = $datos['id_asignacion'];
                $id_tareas = $datos['id_tarea'];
                $id_estudiante = $datos['id_usuario'];

                $comentarios = ($datos['comentarios'] ?? '');


                if ($action == 'RevisarTarea') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaRevisar($id_asignacion, $id_tareas, $id_estudiante, $comentarios);
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tareas");
        }
    }

    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_tarea, $rol, $tipo, $id_proyectos)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {

            if ($rol == "investigador" || $rol == "profesor") {

                global $conn;
                $tarea = new Tarea($conn);

                $tarea->actualizarTareasVencidos();
                $numeroEstado = $this->numerofiltro($tipo);

                $tarea->actualizarestado($id_tarea, $numeroEstado);

                header("Location: editar.php?id_tarea={$id_tarea}&id_proyectos={$id_proyectos}");
                exit();
            }
        }
    }


    public function mostrarEditarTarea($id_tarea, $rol)
    {

        global $conn;

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
    }

    public function mostrarTarea($id_asignacion, $rol)
    {

        global $conn;

        $tareas = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tareas->actualizarTareasVencidos();
            $json = $tareas->obtenerTareaAlumno($id_asignacion);
            // Convertir a array
            $datos = json_decode($json, true);

            return $datos;
        } else {
            $datos = []; // evita undefined variable
            return $datos;
        }
    }
}
