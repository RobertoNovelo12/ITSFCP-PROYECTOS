<?php

require_once __DIR__ . '/../Modelos/tematica.php';
require_once __DIR__ . '/../publico/config/conexion.php';



class tematicaControlador
{

private $conn;

        private function esSupervisor($rol)
    {
        return $rol === 'supervisor';
    }

        // Sanitizar entradas
    private function limpiar($dato)
    {
        return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
    }


    //Obtener datos
    public function index($rol, $buscar = null)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicas($rol, $buscar);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //Obtener datos para editar
    public function indexEditar($rol, $id_tematica)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasEditar((int)$id_tematica);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles($rol, $id_tematica)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasDetalles((int)$id_tematica);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function eliminar_tematica($id_tematica, $rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar temáticas.");
        }

        try {
            $tematica = new Tematica($this->conn);
            $tematica->eliminar_tematica((int)$id_tematica, 0);
            return 0;

        } catch (Exception $e) {
            error_log($e->getMessage());
            return -1;
        }
    }

    public function encabezadosPrincipal($rol)
    {
        return $this->esSupervisor($rol) ? [
            'Temática',
            'Descripción',
            'Subtemáticas',
            'Estado',
            'Creación',
            'Modificación',
            'Acciones'
        ] : [];
    }

    public function opciones($rol, $filtros)
    {
        if (!$this->esSupervisor($rol) || empty($filtros)) return [];

        return [
            'Total' => "Total ({$filtros[0]['Total']} en total)",
            'Activo' => "Activos ({$filtros[0]['Activo']} en total)",
            'Desactivado' => "Desactivados ({$filtros[0]['Desactivado']} en total)"
        ];
    }

    //Para obtener el número del filtro de la tabla
    public function numerofiltro($action)
    {
        return match ($action) {
            'Total' => 2,
            'Activo' => 0,
            'Desactivado' => 1,
            default => 0,
        };
    }

    //Datos filtros GENERAL
    public function filtros($rol)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasDatosFiltro($rol);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //Datos tabla por filtro
    //Total
    public function Total($rol, $buscar = null)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasTablaFiltro(2, $rol, $buscar);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    //Activos
    public function Activo($rol, $buscar = null)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasTablaFiltro(1, $rol, $buscar);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    //Desactivados
    public function Desactivado($rol, $buscar = null)
    {
        try {
            if (!$this->esSupervisor($rol)) return [];

            $tematica = new Tematica($this->conn);
            return $tematica->obtenerTematicasTablaFiltro(0, $rol, $buscar);

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function EstiloEstadoLista($estado)
    {
        return match ($estado) {
            'Activo' => "success",
            'Desactivado' => "danger",
            default => "info"
        };
    }

    //BOTONES
    public function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Editar Tematica':
                $boton = '<a href="editar.php?id_tematica=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_tematica=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '
                <a href="tabla.php?&id_tematica=' . $id1 . '&action=desactivar_tematica" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar temática"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
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

        if ($estado === "Activo") {
            $boton .= $this->obtenerbotones("Editar Tematica", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Desactivado") {
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }

    //Crear temática
        public function registrarTematica($rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso.");
        }

        try {
            $nombre = $this->limpiar($_POST['NombreTematica']);
            $descripcion = $this->limpiar($_POST['Descripcion']);
            $subtematicas = $_POST['subtematicas'] ?? [];

            $tematica = new Tematica($this->conn);

            $id_tematica = $tematica->registrarTematica($nombre, $descripcion);

            if (!$id_tematica) {
                header("Location: crear.php?error=1");
                exit;
            }

            foreach ($subtematicas as $sub) {
                if (!empty($sub['nombre'])) {
                    $tematica->registrarSubtematica($id_tematica, $this->limpiar($sub['nombre']));
                }
            }

            header("Location: tabla.php?mensaje=1");
            exit;

        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: crear.php?error=2");
            exit;
        }
    }

    //Editar temática y subtematica
    public function editarTematica($rol)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso.");
        }

        $this->conn->begin_transaction();

        try {
            $tematica = new Tematica($this->conn);

            $id_tematica = (int)$_POST['id_tematica'];
            $nombre = $this->limpiar($_POST['NombreTematica']);
            $descripcion = $this->limpiar($_POST['Descripcion']);
            $estado = (int)$_POST['Estado'];

            $subtematicas = $_POST['subtematicas'] ?? [];
            $ids_bd = $tematica->obtenerIdsSubtematicas($id_tematica);

            $tematica->editarTematica($nombre, $descripcion, $id_tematica);

            $ids_form = [];

            foreach ($subtematicas as $sub) {
                $id = $sub['id'] ?? null;
                $nombre_sub = $this->limpiar($sub['nombre'] ?? '');

                if ($nombre_sub === '') continue;

                $tematica->comparar_Duplicidad_Subtematica($id_tematica, $nombre_sub, $id);

                if ($id === 'nuevo' || empty($id)) {
                    $tematica->registrarsubtematica($id_tematica, $nombre_sub);
                } else {
                    $tematica->editarSubtematica((int)$id, $nombre_sub);
                    $ids_form[] = $id;
                }
            }

            $ids_eliminar = array_diff($ids_bd, $ids_form);

            foreach ($ids_eliminar as $id) {
                $tematica->eliminar_subtematica($id, 0);
            }

            if ($estado === 0) {
                $tematica->eliminar_tematica($id_tematica, $estado);
            }

            $this->conn->commit();

            header("Location: tabla.php?mensaje=1");
            exit;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log($e->getMessage());
            header("Location: tabla.php?error=1");
            exit;
        }
    }
}
