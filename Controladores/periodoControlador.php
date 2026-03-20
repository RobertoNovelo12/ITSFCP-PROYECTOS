<?php

require_once __DIR__ . '/../Modelos/periodo.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class periodoControlador{

    //Obtener datos
    public function index($rol, $buscar = null)
    {
        global $conn;

        $Periodo = new Periodo($conn);

        if ($rol == "supervisor") {
            //Revisión de estados de tarea
            $tema = $Periodo->obtenerPeriodo($rol, $buscar);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    //Obtener datos para editar
    public function indexEditar($rol, $id_periodo)
    {
        global $conn;

        if ($rol == "supervisor") {
            $Periodo = new Periodo($conn);
            $tema = $Periodo->obtenerPeriodoEditar($id_periodo);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

        public function indexDetalles($rol, $id_periodo)
    {
        global $conn;

        if ($rol == "supervisor") {
            $Periodo = new Periodo($conn);
            $tema = $Periodo->obtenerPeriodoDetalles($id_periodo);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    public function eliminar_periodo($id_periodo, $rol)
    {
        // Lógica para cambiar de estado una temática a desactivado
        if ($rol !== 'supervisor') {
            die("Error: No tienes permiso para eliminar temáticas.");
        }

        global $conn;

        $Periodo = new Periodo($conn);
        $Periodo->eliminar_periodo($id_periodo, 0);
        return 0;
    }

    public function encabezadosPrincipal($rol)
    {
        if ($rol == "supervisor") {
            $encabezados = [
                'Temática',
                'Descripción',
                'Subtemáticas',
                'Estado',
                'Creación',
                'Modificación',
                'Acciones'
            ];
        } else {
            $encabezados = [];
        }
        return $encabezados;
    }

    public function opciones($rol, $filtros)
    {
        switch ($rol) {
            case 'supervisor':
                $opciones = [
                    'Total'       => "Total ({$filtros[0]['Total']} en total)",
                    'Activo'     => "Activos ({$filtros[0]['Activo']} en total)",
                    'Desactivado'  => "Desactivados ({$filtros[0]['Desactivado']} en total)"
                ];
                break;
            default:
                $opciones = [];
                break;
        }
        return $opciones;
    }

    //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
    {

        $numerofiltro = 0;
        switch ($action) {
            case 'Total':
                $numerofiltro = 2;
                break;
            case 'Activo':
                $numerofiltro = 0;
                break;
            case 'Desactivado':
                $numerofiltro = 1;
                break;
            default:
                break;
        }
        return $numerofiltro;
    }

    //Datos filtros GENERAL
    public function filtros($rol)
    {
        global $conn;
        $Periodo = new Periodo($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $tema = $Periodo->obtenerPeriodoDatosFiltro($rol);
            return $tema;
        } else {
            $tema = []; // evita undefined variable
            return $tema;
        }
    }

    //Datos tabla por filtro
    //Total
    public function Total($rol, $buscar = null)
    {
        global $conn;
        $Periodo = new Periodo($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $Periodos = $Periodo->obtenerPeriodoTablaFiltro(2, $rol, $buscar);
            return $Periodos;
        } else {
            $Periodos = []; // evita undefined variable
            return $Periodos;
        }
    }

    //Activos
    public function Activo($rol, $buscar = null)
    {
        global $conn;
        $periodo = new Periodo($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $periodo = $periodo->obtenerPeriodoTablaFiltro(1, $rol, $buscar);
            return $periodo;
        } else {
            $periodo = []; // evita undefined variable
            return $periodo;
        }
    }

    //Desactivados
    public function Desactivado($rol, $buscar = null)
    {
        global $conn;
        $proyecto = new Periodo($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $periodo = $proyecto->obtenerPeriodoTablaFiltro(0, $rol, $buscar);
            return $periodo;
        } else {
            $periodo = []; // evita undefined variable
            return $periodo;
        }
    }

    public function EstiloEstadoLista($estado)
    {
        switch ($estado) {

            case 'Activo':
                $estilo = "success";
                break;
            case 'Desactivado':
                $estilo = "danger";
                break;
            default:
                $estilo = "info";
                break;
        }
        return $estilo;
    }

    //BOTONES
    public function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Editar Periodo':
                $boton = '<a href="editar.php?id_periodos=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_periodos=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '
                <a href="tabla.php?&id_periodos=' . $id1 . '&action=desactivar_periodo" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
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

            case 'supervisor':
                if (in_array($estado, ["Activo"])) {
                    $boton = $this->obtenerbotones("Editar Periodo", $id);
                    $boton .= $this->obtenerbotones("Detalles", $id);
                    $boton .= $this->obtenerbotones("Desactivar", $id);
                } elseif ($estado == "Desactivado") {
                    $boton .= $this->obtenerbotones("Detalles", $id);
                }
                break;
        }

        return $boton;
    }

    //Crear temática
    public function registrarPeriodo($rol)
    {
        if ($rol != 'supervisor') {
            die("No tienes permiso para registrar periodo.");
        }

        global $conn;

        $nombre = trim($_POST['NombrePeriodo']);
        $inicio = $_POST['fecha_inicio'];
        $final = $_POST['fecha_final'];

        $periodo = new Periodo($conn);

        // 1️ Insertar periodo
        $id_periodo = $periodo->registrarPeriodo($nombre, $inicio, $final);

        if (!$id_periodo) {
            header("Location: crear.php?error=1");
            exit;
        }

        header("Location: tabla.php?mensaje=1");
        exit;
    }

    //Editar periodo
    public function editarPeriodo($rol)
    {

        if ($rol != 'supervisor') {
            die("No tienes permiso.");
        }

        global $conn;

        $periodo = new Periodo($conn);

        $id_periodo = $_POST['id_periodos'];
        $nombre = trim($_POST['NombrePeriodo']);


        $conn->begin_transaction();

        try {
            $periodo->editarPeriodo($nombre, $id_periodo);
          
            $conn->commit();
        } catch (Exception $e) {

            $conn->rollback();
            die($e->getMessage());
        }

        header("Location: tabla.php?mensaje=1");
        exit;
    }
}
?>