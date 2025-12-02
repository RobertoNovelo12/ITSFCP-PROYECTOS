<?php

require_once __DIR__ . '/../Modelos/tareas.php';
require_once __DIR__ . '/../publico/config/conexion.php';

// Encabezados según rol

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
    public function index_Lista($id_proyecto, $id_usuario, $rol)
    {
        global $conn;

        $tarea = new Tarea($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //Revisión de estados de tarea
            $tarea->actualizarTareasVencidos();
            $tarea = $tarea->obtenerTareas($id_proyecto, $id_usuario, $rol);
            return $tarea;
        } else {
            $tarea = []; // evita undefined variable
            return $tarea;
        }
    }

    public function tareas($tipo, $rol, $id_usuario, $datos){
        switch($tipo){
            case 'estudiante':
                break;
            case 'investigador':
                break;
            case 'supervisor':
                break;
            default:
                break;
        }
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

    //Para obtener los encabezados de las tablas
    public function encabezadosPrincipal($rol)
    {
        switch ($rol) {
            case 'estudiante':
                $encabezados = [
                    'Tarea',
                    'Estado',
                    'Guía',
                    'FechaEntrega',
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
                    'FechaEntrega',
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
                    'estudiante',
                    'Estado',
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

    public function obtenerbotones($tipo, $id_tarea, $id_avances = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver Tarea':
                $boton = '<a href="tarea.php?id_tarea=' . $id_tarea . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la tarea"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Ver lista':
                $boton = '<a href="lista_tareas.php?id_avances=' . $id_avances . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver lista de tareas"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="lista_tareas.php?action=actualizarestado&id_tarea=' . $id_tarea . '&tipo=Activos"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar tarea"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Activar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_tarea . '&tipo=Pendiete"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar cierre de proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Editar Tarea':
                $boton = '<a href="editar.php?id_tarea=' . $id_tarea . '"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></button></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tarea=' . $id_tarea . '"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'EnviarTarea':
                $boton = '<a href="tarea.php?id_tarea=' . $id_tarea . '&action=editarTareaEstudiante&tipo=Revisar"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Solicitar Corregir':
                $boton = '<a href="editar.php?id_tarea=' . $id_tarea . '&action=editarTareaRevisar&tipo=Corregir"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
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
    public function botonesAccionPrincipal($id, $rol, $estado = null)
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
                    $boton = $this->obtenerbotones("Ver lista", $id);
                } elseif ($estado == "SinActivar") {
                    $boton  = $this->obtenerbotones("Activar", $id);
                    $boton .= $this->obtenerbotones("Editar Tarea", $id);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Pendiente", "Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton  = $this->obtenerbotones("Ver lista", $id);
                } elseif ($estado == "SinActivar") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                }
                break;
        }

        return $boton;
    }

    public function botonesAccionLista($id, $rol, $estado = null)
    {
        $boton = "";

        switch ($rol) {

            case 'investigador':
            case 'profesor':
                if (in_array($estado, ["Revisar", "Corregir"])) {
                    $boton  = $this->obtenerbotones("Ver Tarea", $id);
                    $boton .= $this->obtenerbotones("Aprobar", $id);
                    $boton .= $this->obtenerbotones("Solicitar Corregir", $id);
                } elseif (in_array($estado, ["Aprobado", "Vencido"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id);
                }
                break;

            case 'supervisor':
                if (in_array($estado, ["Revisar", "Corregir", "Aprobado", "Vencido"])) {
                    $boton = $this->obtenerbotones("Ver Tarea", $id);
                }
                break;
        }

        return $boton;
    }

    /*public function obtenerEstado($id_asignacion)
    {
        global $conn;
        $tarea = new Tarea($conn);
        $tarea->actualizarTareasVencidos();
        return $tarea->obtenerEstado($id_asignacion);
    }*/


    /* EDITAR TAREA - investigador */
    public function editarTarea($datos, $rol)
    {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "investigador" || $rol == "profesor") {

                $action = $datos['action'] ?? '';

                $id_tareas = $datos['id_tareas'];
                $descripcion = $datos['descripcion'];

                $instrucciones = $datos['Instrucciones'];
                //Tabla seguimiento
                $fecha_entrega = $datos['fecha_entrega'];
                $archivo = null;
                $archivo_nombre = null;
                $archivo_tipo = null;

                if (!empty($_FILES['archivo']['tmp_name'])) {
                    $archivo = file_get_contents($_FILES['archivo']['tmp_name']);
                    $archivo_nombre = $_FILES['archivo']['name'];
                    $archivo_tipo = $_FILES['archivo']['type'];
                }

                if ($action == 'editarTarea') {
                    global $conn;
                    $tarea = new Tarea($conn);
                    $tarea->actualizarTareasVencidos();
                    $tarea->editarTareaGeneral($id_tareas, $descripcion, $instrucciones, $fecha_entrega, $archivo, $archivo_nombre, $archivo_tipo);
                }
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar tareas");
        }
    }

    public function editarTareaEstudiante($datos, $id_usuario, $rol)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($rol == "estudiante") {

                $action = $datos['action'] ?? '';

                $id_asignacion = $datos['id_asignacion'];
                $id_tarea = $datos['id_Tareas'];
                $id_estudiante = $id_usuario;
                //Se recibe el contenido como array
                $contenidoArray = $datos['contenido'];
                //Se encodifica como JSON
                $contenidoJSON = json_encode($contenidoArray, JSON_UNESCAPED_UNICODE);
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
                    $tarea->editarTareaEstudiante($id_asignacion, $id_tarea, $id_estudiante, $contenidoJSON, $archivo);
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
                $id_tareas = $datos['id_tareas'];
                $id_estudiante = $datos['id_usuario'];

                $comentarios = $datos['comentarios'];


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
    public function actualizarestado($id_tarea, $rol, $tipo)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if ($rol == "supervisor" || $rol == "investigador" || $rol == "profesor") {
                global $conn;
                $proyecto = new Proyectos($conn);
                $proyecto->actualizarProyectosVencidos();
                $numeroEstado = $this->numerofiltro($tipo);
                $proyecto->actualizarestado($id_tarea, $numeroEstado);
            } else {
                die("El usuario no tiene permiso para crear el tarea");
            }
        } else {
            die("Los datos no fueron enviados");
        }
    }
}

