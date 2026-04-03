<?php

require_once __DIR__ . '/../Modelos/plantilladocumento.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class plantilladocumentoControlador
{
    /**
     * Verifica si el usuario tiene rol de supervisor.
     */
    private function esSupervisor($rol): bool
    {
        return isset($rol) && $rol === 'supervisor';
    }

    /**
     * Sanitiza datos de entrada para prevenir XSS.
     */
    private function limpiar($dato): ?string
    {
        return isset($dato)
            ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8')
            : null;
    }

    /**
     * Obtiene listado de Grado Académico con filtro opcional.
     */
    public function index($rol, $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            $obj = new plantilladocumento($conn);
            return $obj->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene detalles de un Grado Académico específico.
     */
    public function indexCrear($rol): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $obj = new plantilladocumento($conn);
            return $obj->obtenerTipos_documentos();
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    public function obtenerTipos($id_tipo_documento): array
    {
        global $conn;
        try {
            $id = filter_var($id_tipo_documento, FILTER_VALIDATE_INT);
            if (!$id) return [];
            $obj = new plantilladocumento($conn);
            return $obj->obtenerTipos($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Desactiva un Grado Académico (borrado lógico).
     */
    public function desactivar($rol, $id_plantilla)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar Plantilla de documento.");
        }
        if (!$id_plantilla) {
            throw new Exception("ID inválido");
        }
        global $conn;
        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $obj->obtenerPorId((int)$id_plantilla);
            $filas = $obj->eliminar_grados_academicos((int)$id_plantilla);
            if ($filas < 0) throw new Exception("Error al eliminar");
            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Throwable $e) {
            if ($conn->errno === 0) $conn->rollback();
            error_log("Error en eliminar(): " . $e->getMessage());
            header("Location: tabla.php?error=10");
            exit;
        }
    }

    /**
     * Retorna los encabezados de la tabla principal.
     */
    public function encabezadosPrincipal($rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return [
            'Plantilla',
            'Fecha Creación',
            'Hora Creación',
            'Estado',
            'Acciones'
        ];
    }

    /**
     * Genera las opciones de filtro con conteo.
     */
    public function opciones($rol, $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros) || !isset($filtros[0])) return [];
        $data = $filtros[0];
        return [
            'Total' => "Total (" . ($data['Total'] ?? 0) . " en total)",
            'Activo' => "Activos (" . ($data['Activo'] ?? 0) . " en total)",
            'Desactivado' => "Desactivados (" . ($data['Desactivado'] ?? 0) . " en total)"
        ];
    }

    /**
     * Convierte acción a número de filtro.
     */
    public function numerofiltro($action): int
    {
        return match ($action) {
            'Total' => 2,
            'Activo' => 1,
            'Desactivado' => 0,
            default => 2
        };
    }

    /**
     * Obtiene datos para filtros.
     */
    public function filtros($rol): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $obj = new plantilladocumento($conn);
            return $obj->obtenerDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método base para evitar duplicación de lógica en filtros.
     */
    private function obtenerPorFiltro($rol, int $estado, $buscar = null, string $tipo): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            $obj = new plantilladocumento($conn);
            return $obj->obtenerTablaFiltro($buscar, $tipo, $estado);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /** Obtiene todos las Plantillas. */
    public function Total($rol, $buscar = null, $tipo): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar, $tipo);
    }

    /** Obtiene Grado Académico activos. */
    public function Activo($rol, $buscar = null, $tipo): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar, $tipo);
    }

    /** Obtiene Plantilla desactivados. */
    public function Desactivado($rol, $buscar = null, $tipo): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar, $tipo);
    }

    /**
     * Retorna clase de estilo según estado.
     */
    public function EstiloEstado($estado): string
    {
        $estado = strtolower(trim($estado));
        return match ($estado) {
            'activo' => "success",
            'desactivado' => "danger",
            default => "info"
        };
    }

    // BOTONES DE TABLA PRINCIPAL
    private function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Ver':
                $boton = '<a href="editar.php?id_grado=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar Plantilla"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Historial':
                $boton = '<a href="detalles.php?id_grado=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de Plantilla"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Nueva version':
                $boton = '<a href="tabla.php?&id_grado=' . $id1 . '&action=desactivar_grados_academicos" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar Plantilla"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            default:
                break;
        }
        return $boton;
    }

    // Botones de acción en la tabla
    public function botonesAccionPrincipal($id, $rol, $estado = null)
    {
        if (!$this->esSupervisor($rol)) return "";

        $boton = "";

        if (in_array($estado, ["Activo"])) {
            $boton .= $this->obtenerbotones("Ver", $id);
            $boton .= $this->obtenerbotones("Historial", $id);
            $boton .= $this->obtenerbotones("Nueva version", $id);
        }

        return $boton;
    }

    /**
     * Registra una nueva Plantilla.
     */
    function registrar($rol, $nombre, $version, $archivo)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $obj->bloquear_tabla();


            $id = $obj->registrar($nombre, $version, $archivo);

            if (!$id) {
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
}
