<?php

require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';

// Encabezados según rol

class ProyectoControlador
{

    public function index($id, $rol, $buscar = null)
    {
        global $conn;

        $proyecto = new Proyectos($conn);

        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            //General
            $proyecto->actualizarProyectosVencidos();
            $numerofiltro =  $this-> numerofiltro("Total");
            $proyectos = $proyecto->obtenerProyectos($id, $rol, $numerofiltro, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Datos filtros GENERAL
    public function filtros($id, $rol)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosDatosFiltro($id, $rol);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Datos tabla por filtro
    //Total
    public function Total($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 0, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Cierre
    public function Cierre($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 1, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Activos
    public function Activos($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 2, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //PorAprobar
    public function PorAprobar($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 3, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }
    //Rechazados
    public function Rechazados($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 4, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //PorCerrar
    public function PorCerrar($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 5, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //Vencidos
    public function Vencido($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 6, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //Rechazado cierre
    public function Cierrerechazado($id, $rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        //Datos filtros
        if ($rol == "investigador" || $rol == "estudiante" || $rol == "supervisor") {
            $proyecto->actualizarProyectosVencidos();
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 7, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
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
            case 'Cierre':
                $numerofiltro = 1;
                break;

            case 'Activos':
                $numerofiltro = 2;
                break;
            case 'PorAprobar':
                $numerofiltro = 3;
                break;
            case 'Rechazados':
                $numerofiltro = 4;
                break;
            case 'PorCerrar':
                $numerofiltro = 5;
                break;
            case 'Vencido':
                $numerofiltro = 6;
                break;
            case 'CierreRechazado':
                $numerofiltro = 7;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }

    //Para obtener los encabezados de las tablas
    public function encabezados($rol)
    {
        switch ($rol) {
            case 'estudiante':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Comentarios',
                    'Pendientes',
                    'Acciones'
                ];
                break;
            case 'investigador':
            case 'profesor':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Comentarios',
                    'Pendientes',
                    'Acciones'
                ];
                break;
            case 'supervisor':
                $encabezados = [
                    'ID',
                    'Título',
                    'Fecha Inicio',
                    'Fecha Fin',
                    'Estado',
                    'Período',
                    'Comentarios',
                    'Acciones'
                ];
                break;
            default:
                $encabezados = [];
                break;
        }
        return $encabezados;
    }

    public function datosopciones($rol, $filtros)
    {
        switch ($rol) {
            case 'estudiante':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                    'Vencido'   => "Vencidos ({$filtros[0]['Vencido']})"
                ];
                break;
            case 'investigador':
            case 'profesor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                    'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                    'Cierrerechazado'   => "Cierre rechazado ({$filtros[0]['Cierrerechazado']})",
                    'Vencido'  => "Vencidos ({$filtros[0]['Vencido']})"
                ];
                break;
            case 'supervisor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']})",
                    'Activos'     => "Activos ({$filtros[0]['Activos']})",
                    'PorAprobar'  => "Por Aprobar ({$filtros[0]['PorAprobar']})",
                    'Rechazados'  => "Rechazados ({$filtros[0]['Rechazados']})",
                    'Cierre'      => "Cierre ({$filtros[0]['Cierre']})",
                    'PorCerrar'   => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                    'Cierrerechazado'   => "Cierre rechazado ({$filtros[0]['Cierrerechazado']})",
                    'Vencido'  => "Vencidos ({$filtros[0]['Vencido']})"
                ];
                break;
            default:
                $opciones = [];
                break;
        }
        return $opciones;
    }

    public function obtenerbotones($tipo, $id_proyecto)
    {
        $boton = "";
        switch ($tipo) {
            case 'Detalles':
                $boton = '<a href="detalles.php?id_proyectos=' . $id_proyecto . '"><button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></button></a>';
                break;
            case 'Tareas':
                $boton = '<a href="../Tareas/tabla.php?id_proyectos=' . $id_proyecto . '"><button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
        data-bs-title="Tareas"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-task" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
  <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/><path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/></svg></button></a>';
                break;
            case 'Aprobar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Solicitar cerrar':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"><button type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Solicitar cerrar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></button></a>';
                break;
            case 'Aprobar cierre':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre"><button type="button" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Aprobar cierre de proyecto"> <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg></button></a>';
                break;
            case 'Rechazar cierre':
                $boton = '<button type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Rechazar proyecto" data-bs-target="#modalRechazoCierre" onclick="abrirRechazo(' . $id_proyecto . ')"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-ban" style="padding:0;margin:auto;" viewBox="0 0 16 16">
  <path d="M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0"/></svg></button>';
                break;
            case 'Volver a enviar cierre':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"><button type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Volver a enviar cierre"><svg xmlns=\"http://www.w3.org/2000/svg\" width="18" height="18" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
  <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
  <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
</svg></button></a>';
                break;
            case 'Volver a enviar proyecto':
                $boton = '<a href="tabla.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorAprobar">
    <button type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
    data-bs-custom-class="custom-tooltip" data-bs-title="Volver a enviar proyecto">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" 
    class="bi bi-arrow-repeat" viewBox="0 0 16 16">
      <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
      <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
    </svg>
    </button></a>';
                break;
            case 'GenerarConstancia':
                $boton = '<a href="constancias.php?action=generar&id_proyectos=' . $id_proyecto . '"> <button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Descargar constancia de terminación"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-earmark-pdf-fill" viewBox="0 0 16 16">
  <path d="M5.523 12.424q.21-.124.459-.238a8 8 0 0 1-.45.606c-.28.337-.498.516-.635.572l-.035.012a.3.3 0 0 1-.026-.044c-.056-.11-.054-.216.04-.36.106-.165.319-.354.647-.548m2.455-1.647q-.178.037-.356.078a21 21 0 0 0 .5-1.05 12 12 0 0 0 .51.858q-.326.048-.654.114m2.525.939a4 4 0 0 1-.435-.41q.344.007.612.054c.317.057.466.147.518.209a.1.1 0 0 1 .026.064.44.44 0 0 1-.06.2.3.3 0 0 1-.094.124.1.1 0 0 1-.069.015c-.09-.003-.258-.066-.498-.256M8.278 6.97c-.04.244-.108.524-.2.829a5 5 0 0 1-.089-.346c-.076-.353-.087-.63-.046-.822.038-.177.11-.248.196-.283a.5.5 0 0 1 .145-.04c.013.03.028.092.032.198q.008.183-.038.465z"/>
  <path fill-rule="evenodd" d="M4 0h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2m5.5 1.5v2a1 1 0 0 0 1 1h2zM4.165 13.668c.09.18.23.343.438.419.207.075.412.04.58-.03.318-.13.635-.436.926-.786.333-.401.683-.927 1.021-1.51a11.7 11.7 0 0 1 1.997-.406c.3.383.61.713.91.95.28.22.603.403.934.417a.86.86 0 0 0 .51-.138c.155-.101.27-.247.354-.416.09-.181.145-.37.138-.563a.84.84 0 0 0-.2-.518c-.226-.27-.596-.4-.96-.465a5.8 5.8 0 0 0-1.335-.05 11 11 0 0 1-.98-1.686c.25-.66.437-1.284.52-1.794.036-.218.055-.426.048-.614a1.24 1.24 0 0 0-.127-.538.7.7 0 0 0-.477-.365c-.202-.043-.41 0-.601.077-.377.15-.576.47-.651.823-.073.34-.04.736.046 1.136.088.406.238.848.43 1.295a20 20 0 0 1-1.062 2.227 7.7 7.7 0 0 0-1.482.645c-.37.22-.699.48-.897.787-.21.326-.275.714-.08 1.103"/>
</svg></button></a>';
                break;
            case 'Editar':
                $boton = '<a href="editar.php?id_proyectos=' . $id_proyecto . '"> <button type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar proyecto"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></button></a>';
                break;
            case 'Rechazar creacion':
                $boton = '<button type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Rechazar proyecto" data-bs-target="#modalRechazoSolicitud" onclick="abrirRechazoSolicitud(' . $id_proyecto . ')"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-ban" style="padding:0;margin:auto;" viewBox="0 0 16 16">
  <path d="M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/></svg></button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones de acción en la tabla 
    public function botonesAccion($id, $rol, $estado = null, $extra = null)
    {

        $boton = "";
        switch ($rol) {
            case 'estudiante':
                if ($estado == "Activo" || $estado == "Por cerrar") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Cerrado") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                    $boton .= $this->obtenerbotones("GenerarConstancia", $id);
                } else if ($estado == "Vencido") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                }
                break;
            case 'investigador':
            case 'profesor':
                if ($estado == "Activo") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                    $boton .= $this->obtenerbotones("Editar", $id);
                    $boton .= $this->obtenerbotones("Solicitar cerrar", $id);
                } else if ($estado == "Por aprobar") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                } else if ($estado == "Por cerrar") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Cierre") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Cierre rechazado") {
                    $boton = $this->obtenerbotones("Volver a enviar cierre", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Editar", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Rechazado") {
                    $boton = $this->obtenerbotones("Volver a enviar proyecto", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Editar", $id);
                } else if ($estado == "Vencido") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                }
                break;
            case 'supervisor':
                if ($estado == "Activo") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Por aprobar") {
                    $boton = $this->obtenerbotones("Aprobar", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                    $boton .= $this->obtenerbotones("Rechazar creacion", $id);
                } else if ($estado == "Por cerrar") {
                    $boton = $this->obtenerbotones("Aprobar cierre", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                    $boton .= $this->obtenerbotones("Rechazar cierre", $id);
                } else if ($estado == "Cierre rechazado") {
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Cierre") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Vencido") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Tareas", $id);
                } else if ($estado == "Rechazado") {
                    $boton = $this->obtenerbotones("Detalles", $id);
                }
                break;
            default:
                $boton = null;
                break;
        }
        return $boton;
    }

    //CREAR PROYECTO
    public function tematica()
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $proyecto->actualizarProyectosVencidos();
        $tematica = $proyecto->tematica();
        if ($tematica != []) {
            return $tematica;
        } else {
            $tematica = []; // evita undefined variable
            return $tematica;
        }
    }

    public function subtematicas($id)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $proyecto->actualizarProyectosVencidos();
        return $proyecto->obtenersubtematica($id);
    }

    public function obtenerperiodo()
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $proyecto->actualizarProyectosVencidos();
        return $proyecto->obtenerperiodo();
    }

    public function obtenerInstituto()
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        $proyecto->actualizarProyectosVencidos();
        return $proyecto->obtenerinstituto();
    }

    public function registrarProyecto($datos, $id, $rol)
    {
        $periodoData = $this->obtenerperiodo();
        $periodo = $periodoData[0]; // tomas el primer registro
        $estado_periodo = $periodo["estado"]; // O "periodo", según lo que necesites

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($estado_periodo == "Activo" && $estado_periodo != "") {
                if ($rol == "investigador" || $rol == "profesor") {

                    $action = $datos['action'] ?? '';

                    $id_investigador = $id;
                    $id_estadoP = 3;
                    $id_tematica = $datos['Tematica'];
                    if ($id_tematica == "") {
                        die("Se debe elegir una temática");
                    }
                    $institutoData = $this->obtenerInstituto();
                    $instituto = $institutoData[0]; // tomas el primer registro
                    $id_instituto = $instituto['id_instituto'];
                    $id_periodos = $periodo["id_periodos"];
                    $titulo = $datos['NombreProyecto'];
                    $descripcion = $datos['Descripcion'];
                    $objetivo = $datos['Objetivos'];
                    $fecha_inicio = $datos['FechaInicio'];
                    $fecha_final = $datos['FechaFinal'];
                    $presupuesto = $datos['Presupuesto'];

                    $requisitos = $datos['Requisitos'];

                    $Pre_requisitos = $datos['Pre_requisitos'];
                    $AlumnosCantidad = $datos['AlumnosCantidad'];

                    $modalidad = $datos['Modalidad'];
                    if ($action === 'registrarProyecto') {
                        global $conn;
                        $proyecto = new Proyectos($conn);
                        $proyecto->actualizarProyectosVencidos();
                        $proyecto->registrarProyecto($id_investigador, $id_estadoP, $id_tematica, $id_instituto, $id_periodos, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad);
                    }
                } else {
                    die("El usuario no tiene permiso para crear el proyecto");
                }
            } else {
                die("El periodo ha acabado para registrar proyectos");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar proyectos");
        }
    }

    /* EDITAR PROYECTO */
    public function editarProyecto($datos, $id_usuario, $rol)
    {
        $periodoData = $this->obtenerperiodo();
        $periodo = $periodoData[0]; 
        $estado_periodo = $periodo["estado"]; 

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($estado_periodo == "Activo" && $estado_periodo != "") {
                if ($rol == "investigador" || $rol == "profesor") {
                    
                    $action = $datos['action'] ?? '';
                    
                    $id_proyecto = $datos['id_proyectos'];
                    $id_investigador = $id_usuario;
                    $id_tematica = $datos['Tematica'];
                    if ($id_tematica == "") {
                        die("Se debe elegir una temática");
                    }
                    
                    $titulo = $datos['NombreProyecto'];
                    $descripcion = $datos['Descripcion'];
                    $objetivo = $datos['Objetivos'];
                    $fecha_inicio = $datos['FechaInicio'];
                    $fecha_final = $datos['FechaFinal'];
                    $presupuesto = $datos['Presupuesto'];

                    $requisitos = $datos['Requisitos'];

                    $Pre_requisitos = $datos['Pre_requisitos'];
                    $AlumnosCantidad = $datos['AlumnosCantidad'];

                    $modalidad = $datos['Modalidad'];
                    $id_subtematica = $datos['Subtematica'];

                    if ($id_subtematica == "") {
                        die("Se debe elegir una Subtematica");
                    }

                    if ($action == 'editarProyecto') {
                        global $conn;
                        $proyecto = new Proyectos($conn);
                        $proyecto->editarProyecto($id_proyecto, $id_investigador, $id_tematica, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad);
                    }
                } else {
                    die("El usuario no tiene permiso para crear el proyecto");
                }
            } else {
                die("El periodo ha acabado para registrar proyectos");
            }
        } else {
            die("Los datos no fueron enviados ha acabado para registrar proyectos");
        }
    }


    /* ACCIÓN DE RECHAZAR CIERRE */
    public function actualizarestadoRechazo($data, $id_usuario, $rol) //En vez de buscar será el motivo
    {
        $action = $data['action'] ?? '';
        if (!empty($comentario = $data['comentario']) && !empty($id_proyectos = $data['id_proyectos'])) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($rol == "supervisor") {
                    if ($action == 'actualizarestadoRechazo') {
                        $id_proyectos = $data['id_proyectos'];
                        $tipo = $data['tipo'];
                        $comentario = $data['comentario'];

                        global $conn;
                        $proyecto = new Proyectos($conn);
                        $proyecto->actualizarProyectosVencidos();
                        $proyecto->actualizarEstadoProyectoRechazo($id_usuario, $id_proyectos, $tipo, $comentario);
                    } else {
                        die("No es la acción correspondiente");
                    }
                } else {
                    die("El usuario no tiene permiso para crear el proyecto");
                }
            } else {
                die("Los datos no fueron enviados proyectos");
            }
        }
    }
    //Actualizar estado de proyectos sin comentarios
    public function actualizarestado($id_proyecto, $rol, $tipo)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if ($rol == "supervisor" || $rol == "investigador" || $rol == "profesor") {
                global $conn;
                $proyecto = new Proyectos($conn);
                $proyecto->actualizarProyectosVencidos();
                $numeroEstado = $this->numerofiltro($tipo);
                $proyecto->actualizarestado($id_proyecto, $numeroEstado);
            } else {
                die("El usuario no tiene permiso para crear el proyecto");
            }
        } else {
            die("Los datos no fueron enviados");
        }
    }

    public function datosproyecto($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerProyecto($id_proyecto);
    }
    public function datosinvestigador($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerProyectoInvestigador($id_proyecto);
    }
    public function datosestudiantes($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerProyectoEstudiante($id_proyecto);
    }

    public function comentarios($id_proyecto)
    {
        global $conn;
        $proyecto = new Proyectos($conn);
        return $proyecto->obtenerProyectoComentarios($id_proyecto);
    }
}
