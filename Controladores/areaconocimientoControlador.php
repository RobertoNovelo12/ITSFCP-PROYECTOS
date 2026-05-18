<?php

require_once __DIR__ . '/../Modelos/areaconocimiento.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class AreaConocimientoControlador
{

    //Obtener datos
    public function index($rol, $buscar = null)
    {
        global $conn;

        $areaConocimiento = new AreaConocimiento($conn);

        if ($rol == "supervisor") {
            //Revisión de estados de tarea
            $area = $areaConocimiento->obtenerAreasTablaFiltro($buscar, 2);
            return $area;
        } else {
            $area = []; // evita undefined variable
            return $area;
        }
    }

    //Obtener datos para editar
    public function indexEditar($rol, $id_area)
    {
        global $conn;

        if ($rol == "supervisor") {
            $areaConocimiento = new AreaConocimiento($conn);
            $area = $areaConocimiento->obtenerAreaEditar($id_area);
            return $area;
        } else {
            $area = []; // evita undefined variable
            return $area;
        }
    }

    public function indexDetalles($rol, $id_area)
    {
        global $conn;

        if ($rol == "supervisor") {
            $areaConocimiento = new AreaConocimiento($conn);
            $area = $areaConocimiento->obtenerAreasDetalles($id_area);
            return $area;
        } else {
            $area = []; // evita undefined variable
            return $area;
        }
    }

    public function eliminar_area($id_area, $rol)
    {
        // Lógica para cambiar de estado una temática a desactivado
        if ($rol !== 'supervisor') {
            die("Error: No tienes permiso para eliminar temáticas.");
        }

        global $conn;

        $areaConocimiento = new AreaConocimiento($conn);
        $areaConocimiento->eliminar_area($id_area, 0);
        return 0;
    }

    public function encabezadosPrincipal($rol)
    {
        if ($rol !== 'supervisor') {
            die("Error: No tienes permiso para eliminar temáticas.");
        }
        $encabezados = [
            'Área',
            'Descripción',
            'Subareas',
            'Estado',
            'Creación',
            'Modificación',
            'Acciones'
        ];

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
            case 'Activo':
                $numerofiltro = 0;
                break;
            case 'Desactivado':
                $numerofiltro = 1;
                break;
            case 'Total':
                $numerofiltro = 2;
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
        $areaConocimiento = new AreaConocimiento($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $area = $areaConocimiento->obtenerAreasDatosFiltro($rol);
            return $area;
        } else {
            $area = []; // evita undefined variable
            return $area;
        }
    }

    //Datos tabla por filtro
    //Total
    public function Total($rol, $buscar = null)
    {
        global $conn;
        $areaConocimiento = new AreaConocimiento($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $areaConocimientos = $areaConocimiento->obtenerAreasTablaFiltro($buscar, 2);
            return $areaConocimientos;
        } else {
            $areaConocimientos = []; // evita undefined variable
            return $areaConocimientos;
        }
    }

    //Activos
    public function Activo($rol, $buscar = null)
    {
        global $conn;
        $areaConocimiento = new AreaConocimiento($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $areaConocimientos = $areaConocimiento->obtenerAreasTablaFiltro($buscar, 1);
            return $areaConocimientos;
        } else {
            $areaConocimientos = []; // evita undefined variable
            return $areaConocimientos;
        }
    }

    //Desactivados
    public function Desactivado($rol, $buscar = null)
    {
        global $conn;
        $areaConocimiento = new AreaConocimiento($conn);
        //Datos filtros
        if ($rol == "supervisor") {
            $areaConocimientos = $areaConocimiento->obtenerAreasTablaFiltro($buscar, 0);
            return $areaConocimientos;
        } else {
            $areaConocimientos = []; // evita undefined variable
            return $areaConocimientos;
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
            case 'Editar Area':
                $boton = '<a href="editar.php?id_area=' . $id1 . '" type="button" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar área de conocimiento"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_area=' . $id1 . '" type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del área de conocimiento"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '
                <a href="index.php?&id_area=' . $id1 . '&action=desactivar_area" type="button" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar área de conocimiento"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
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
                    $boton = $this->obtenerbotones("Editar Area", $id);
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
    public function registrarArea($rol)
    {
        if ($rol != 'supervisor') {
            die("No tienes permiso para registrar Áreas.");
        }

        global $conn;

        $nombre = trim($_POST['NombreArea']);
        $descripcion = trim($_POST['Descripcion']);
        $subareas = $_POST['subarea'] ?? [];

        $areaConocimiento = new AreaConocimiento($conn);

        // 1️ Insertar temática
        $id_area = $areaConocimiento->crearAreaCompleta($nombre, $descripcion, $subareas);

        if (!$id_area) {
            header("Location: crear.php?error=1");
            exit;
        }

        header("Location: index.php?mensaje=1");
        exit;
    }

    //Editar area y subareas
    public function editarArea($rol)
    {

        if ($rol != 'supervisor') {
            die("No tienes permiso.");
        }

        global $conn;

        $areaConocimiento = new AreaConocimiento($conn);


        $id_area = $_POST['id_area'];
        $nombre = trim($_POST['NombreArea']);
        $descripcion = trim($_POST['Descripcion']);
        $estado = trim($_POST['Estado']);

        $subareas = $_POST['subarea'] ?? [];

        $ids_bd = $areaConocimiento->obtenerIdsSubareas($id_area);
        $conn->begin_transaction();


        try {
            $areaConocimiento->editarArea($nombre, $descripcion, $id_area);


            //Proceso de registrar y actualizar subareas

            $ids_form = [];
            //Procesar
            foreach ($subareas as $sub) {

                $id = $sub['id_subarea'] ?? null;
                $nombre_sub = trim($sub['nombre'] ?? '');

                if ($nombre_sub == '') continue;

                // Validar contra BD
                $areaConocimiento->comparar_Duplicidad_Subareas($id_area, $nombre_sub, $id);

                if ($id === 'nuevo' || $id === null || $id === '') {
                    $areaConocimiento->registrarsubarea(intval($id_area), $nombre_sub);
                } else {

                    $areaConocimiento->editarSubarea($id, $nombre_sub);
                    $ids_form[] = $id;
                }
            }
            //Proceso de eliminar subareas
            //Revisa las IDs del formulario y las extraidas de la base de datos para comparar
            
            $ids_eliminar = array_diff($ids_bd, $ids_form);
            if (!empty($ids_eliminar)) {
                foreach ($ids_eliminar as $id) {
                    $areaConocimiento->eliminar_subarea($id, 0);
                }
            }

            if ($estado == 0) {
                $areaConocimiento->eliminar_area($id_area, $estado);
            }

            $conn->commit();
        } catch (Exception $e) {

            $conn->rollback();
            die($e->getMessage());
        }

        header("Location: index.php?mensaje=1");
        exit;
    }
}
