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
     * Obtiene detalles de Plantillas de documentos.
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

    /**
     * Retorna los encabezados de la tabla principal.
     */
    public function encabezadosPrincipal($rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return [
            'Plantilla',
            'Versión',
            'Creación',
            'Modificación',
            'Archivo',
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
    private function obtenerPorFiltro($rol, int $estado, $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            $obj = new plantilladocumento($conn);
            return $obj->obtenerTablaFiltro($buscar, $estado);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /** Obtiene todos las Plantillas. */
    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    /** Obtiene Grado Académico activos. */
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    /** Obtiene Plantilla desactivados. */
    public function Desactivado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
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
    private function obtenerbotones($estado, $id1 = null)
    {
        $boton = "";
        switch ($estado) {
            case 'Historial':
                $boton = '<a href="historial.php?id_plantilla=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver historial de Plantilla"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
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

        if (in_array($estado, ["Activo", "Desactivado"])) {
            $boton .= $this->obtenerbotones("Historial", $id);
        }

        return $boton;
    }

    public function obtenerPlantillas($id_tipo_documento)
    {
        global $conn;

        try {
            $id = filter_var($id_tipo_documento, FILTER_VALIDATE_INT);
            if (!$id) return [];

            $obj = new plantilladocumento($conn);
            $data = $obj->obtenerInfoTipos($id);

            $ultima = $data['ultima_version'];

            $version = ($ultima !== null) ? $ultima + 1 : 1;
            $nombre = $data['nombre'] . " v" . $version;

            // SOLO RETURN
            return [
                "nombre" => $nombre,
                "version" => $version
            ];
        } catch (Throwable $e) {
            error_log("Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registra una nueva Plantilla.
     */
    public function registrar($rol, $nombre, $version, $nombre_archivo, $rutaDestino, $id_tipo_documento)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $obj->bloquear_tabla();

            $obj->desactivarPorTipo($id_tipo_documento);

            $id = $obj->registrar($id_tipo_documento, $nombre, $version, $nombre_archivo, $rutaDestino);

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
