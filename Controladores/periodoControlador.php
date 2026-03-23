<?php

require_once __DIR__ . '/../Modelos/periodo.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class periodoControlador
{



    //Validar rol
    private function esSupervisor($rol)
    {
        return $rol === 'supervisor';
    }

    //Sanitizar datos
    private function limpiar($dato)
    {
        return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
    }

    //Obtener datos
    public function index($rol, $buscar = null)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 3);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar($rol, $id_periodo)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoEditar((int)$id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles($rol, $id_periodo)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoDetalles((int)$id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function eliminar($id_periodo, $rol)
    {
        //Obtener datos
        global $conn;
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar periodo.");
        }

        try {
            $Periodo = new Periodo($conn);
            $Periodo->eliminar_periodo((int)$id_periodo, 0);
            return 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return -1;
        }
    }

    public function encabezadosPrincipal($rol)
    {
        return $this->esSupervisor($rol) ? [
            'Periodo',
            'Fecha Inicio',
            'Fecha Final',
            'Estado',
            'Acciones'
        ] : [];
    }

    public function opciones($rol, $filtros)
    {
        if (!$this->esSupervisor($rol) || empty($filtros)) return [];

        return [
            'Total' => "Total ({$filtros[0]['Total']} en total)",
            'Activo' => "Activos ({$filtros[0]['Activo']} en total)",
            'Pendiente' => "Pendientes ({$filtros[0]['Pendiente']} en total)",
            'Terminado' => "Terminados ({$filtros[0]['Terminado']} en total)"
        ];
    }

    public function numerofiltro($action)
    {
        return match ($action) {
            'Total' => 3,
            'Activo' => 0,
            'Desactivado' => 1,
            'Pendiente' => 2,
            default => 0,
        };
    }

    public function filtros($rol)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoDatosFiltro($rol);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Total($rol, $buscar = null)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoTablaFiltro(3, $rol, $buscar);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Activo($rol, $buscar = null)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoTablaFiltro(0, $rol, $buscar);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Pendiente($rol, $buscar = null)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoTablaFiltro(1, $rol, $buscar);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Terminado($rol, $buscar = null)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoTablaFiltro(3, $rol, $buscar);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function EstiloEstadoLista($estado)
    {
        return match ($estado) {
            'Activo' => "success",
            'Pendiente' => "warning",
            'Terminado' => "danger",
            default => "info"
        };
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
        if (!$this->esSupervisor($rol)) return "";

        $boton = "";

        if (in_array($estado, ["Activo", "Pendiente"])) {
            $boton .= $this->obtenerbotones("Editar Periodo", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Terminado") {
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }

    //Crear temática
    public function registrarPeriodo($rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso.");
        }

        //Obtener datos
        global $conn;
        try {
            $nombre = $this->limpiar($_POST['periodo']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_final = $_POST['fecha_final'];

            $Periodo = new Periodo($conn);

            $id_periodo = $Periodo->registrarPeriodo($nombre, $fecha_inicio, $fecha_final);

            if (!$id_periodo) {
                header("Location: crear.php?error=1");
                exit;
            }

            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: crear.php?error=2");
            exit;
        }
    }

    public function editarPeriodo($rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso.");
        }        //Obtener datos
        global $conn;


        $conn->begin_transaction();

        try {
            $Periodo = new Periodo($conn);

            $id_periodo = (int)$_POST['id_periodos'];
            $nombre = $this->limpiar($_POST['periodo']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_final = $_POST['fecha_final'];

            $Periodo->editarPeriodo($nombre, $fecha_inicio, $fecha_final, $id_periodo);

            $conn->commit();

            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            header("Location: tabla.php?error=1");
            exit;
        }
    }
}
