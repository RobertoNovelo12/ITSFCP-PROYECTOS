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

    // 📌 Retornamos TODOS los datos en un arreglo
    return [
        "total_proyectos" => $total_proyectos,
        "por_pagina"     => $por_pagina,
        "pagina"         => $pagina,
        "desde"          => $desde,
        "total_paginas"  => $total_paginas
    ];
}
}

