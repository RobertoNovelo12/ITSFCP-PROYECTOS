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
            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 2);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar($rol, $id_periodos)
    {
        //Obtener datos
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoEditar((int)$id_periodos);
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
    //Cambia de estado a 0 - Desactivado administrativamente
    public function eliminar($id_periodo, $rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar periodo.");
        }

        if (!$id_periodo) {
            throw new Exception("ID inválido");
        }

        global $conn;
        $conn->begin_transaction();

        try {

            // BLOQUEO DE CONCURRENCIA
            $sql = "SELECT id_periodos FROM periodos WHERE estado = 1 FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                throw new Exception("Ya existe un periodo activo");
            }

            $Periodo = new Periodo($conn);

            $filas = $Periodo->eliminar_periodo((int)$id_periodo);

            if ($filas === 0) {
                throw new Exception("No se actualizó ningún registro");
            }

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

    public function encabezadosPrincipal($rol)
    {
        return $this->esSupervisor($rol) ? [
            'Periodo',
            'Fecha Inicio',
            'Fecha Final',
            'Fecha Creación',
            'Hora Creación',
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
            'Terminado' => "Terminados ({$filtros[0]['Terminado']} en total)"
        ];
    }

    public function numerofiltro($action)
    {
        return match ($action) {
            'Total' => 2,
            'Activo' => 1,
            'Terminado' => 0,
            default => 0
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

            $Periodo = new Periodo($conn); // 3 de filtro para no activar el filtro
            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 2);
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
            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 1);
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
            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 0);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function EstiloEstadoLista($estado)
    {
        return match ($estado) {
            'Activo' => "success",/*
            'Pendiente' => "warning",*/
            'Terminado' => "danger",
            default => "info"
        };
    }

    //BOTONES
    private function obtenerbotones($tipo, $id1 = null)
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

        if (in_array($estado, ["Activo"])) {
            $boton .= $this->obtenerbotones("Editar Periodo", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Terminado") {
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }
    //Crear Periodo
    function registrarPeriodo($rol)
    {

        global $conn;

        $conn->begin_transaction();
        try {
            $Periodo = new Periodo($conn);
            // BLOQUEO DE CONCURRENCIA
            $res = $Periodo->bloquear_tabla();

            if ($res->num_rows > 0) {
                throw new Exception("Ya existe un periodo activo");
            }

            $datos = $this->generarPeriodoAutomatico();

            if ($datos['fin'] < $datos['inicio']) {
                throw new Exception("La fecha final no puede ser menor...");
            }

            $id_periodo = $Periodo->registrarPeriodo($datos['nombre'], $datos['inicio'], $datos['fin']);

            if (!$id_periodo) {
                header("Location: tabla.php?error=1");
                exit;
            }
            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            if ($e->getCode() == 1062) {
                header("Location: tabla.php?error=duplicado");
            } else {
                header("Location: tabla.php?error=2");
            }

            exit;
        }
    }

    public function reactivar($nombre)
    {
        global $conn;

        $conn->begin_transaction();
        try {
            $Periodo = new Periodo($conn);
            // BLOQUEO DE CONCURRENCIA
            $Periodo->bloquear_tabla();

            $periodoExistente = $Periodo->obtenerPorNombre($nombre);
            // Desactiva el periodo actual - Caso de concurrencia
            $Periodo->desactivarActivos();
            // Si no existe, lo crea
            $Periodo->reactivarPeriodo($periodoExistente['id_periodos']);

            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            if ($e->getCode() == 1062) {
                header("Location: tabla.php?error=duplicado");
            } else {
                header("Location: tabla.php?error=2");
            }

            exit;
        }
    }

    function generarPeriodoAutomatico()
    {
        $anio = date("Y");
        $mes = date("n");

        if ($mes <= 6) {
            return [
                "nombre" => $anio . "-1",
                "inicio" => date("Y-m-d", strtotime("$anio-01-01")),
                "fin"    => date("Y-m-d", strtotime("$anio-06-30"))
            ];
        } else {
            return [
                "nombre" => $anio . "-2",
                "inicio" => date("Y-m-d", strtotime("$anio-07-01")),
                "fin"    => date("Y-m-d", strtotime("$anio-12-31"))
            ];
        }
    }

    public function verificarPeriodo($nombre, $fecha_inicio, $fecha_final)
    {
               global $conn;
        try {
        $Periodo = new Periodo($conn);
        // Verificar si ya existe
        return $Periodo->verificarPeriodo($nombre, $fecha_inicio, $fecha_final);
    } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
}
}