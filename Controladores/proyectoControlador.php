<?php
require_once '../../Modelos/proyecto.php';
require_once '../../publico/config/conexion.php';
// Encabezados según rol

class ProyectoControlador
{
    public function numerofiltro()
    {
    $action = $_GET['action'] ?? 'Total';
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
    default:
        break;
}
        return $numerofiltro;
}

    public function index($id, $rol, $buscar = null)
    {
        global $conn;

        $proyecto = new Proyectos($conn);

        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            //General
            $proyectos = $proyecto->obtenerProyectos($id, $rol, $buscar);
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
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
        if ($rol == "investigador" || $rol == "alumno" || $rol == "supervisor") {
            $proyectos = $proyecto->obtenerProyectosTablaFiltro($id, 5, $rol, $buscar);
            return $proyectos;
        } else {
            $proyectos = []; // evita undefined variable
            return $proyectos;
        }
    }

    //Paginación
public function tabla($id, $rol, $buscar = null)
{
    global $conn;
    $proyecto = new Proyectos($conn);

    // Filtro actual
    $numerofiltro = $this->numerofiltro();
    
    // Cantidad totals
    $total_proyectos = $proyecto->obtenerCantidadProyectos($id, $numerofiltro, $rol, $buscar);

    // Parámetros de paginación
    $por_pagina = 6;

    $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
    $desde = ($pagina - 1) * $por_pagina;

    $total_paginas = ($total_proyectos > 0)
        ? ceil($total_proyectos / $por_pagina)
        : 1;

    // Retornamos TODOS los datos en un arreglo
    return [
        "total_proyectos" => $total_proyectos,
        "por_pagina"     => $por_pagina,
        "pagina"         => $pagina,
        "desde"          => $desde,
        "total_paginas"  => $total_paginas
    ];
}

    //Botones de acción en la tabla
    public function botonesAccion($id, $rol)
    {
        global $conn;
        $proyecto = new Proyectos($conn);



if ($proyecto['nombre'] == "Activo") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-success\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-file-earmark-plus-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M8.5 7v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 1 0\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                <button type=\"button\" class=\"btn btn-danger\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-ban\" style=\"padding:0;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M15 8a6.97 6.97 0 0 0-1.71-4.584l-9.874 9.875A7 7 0 0 0 15 8M2.71 12.584l9.874-9.875a7 7 0 0 0-9.874 9.874ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0\"/>
</svg></button>";
                                    } else if ($proyecto['nombre'] == "Sin aprobar") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                {$proyecto['total']}</td>";
                                    } else if ($proyecto['nombre'] == "Por cerrar") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button>
                                {$proyecto['total']}</td>";
                                    } else if ($proyecto['nombre'] == "Cierre") {
                                        echo "<td>
                                <button type=\"button\" class=\"btn btn-primary\">
                                <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"currentColor\" class=\"bi bi-eye-fill\" style=\"padding:0px;margin:auto;\" viewBox=\"0 0 16 16\">
  <path d=\"M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0\"/>
  <path d=\"M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7\"/>
</svg>
                                </button></td>";
                                    }



        /*
        $botones = $proyecto->obtenerBotonesAccion($id_proyecto, $rol);
        */
        //return $botones;
    }
}
