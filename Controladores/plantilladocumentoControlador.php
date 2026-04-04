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
    private function obtenerbotones($estado, $id1 = null, $id2 = null)
    {
        $boton = "";
        switch ($estado) {
            case 'Historial':
                $boton = '<a href="historial.php?id_plantilla=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver historial de Plantilla"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '<a href="tabla.php?&id_plantilla=' . $id1 . '&id_tipo_documento=' . $id2 . '&action=desactivar" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            case 'Reactivar':
                $boton = '<a href="tabla.php?&id_plantilla=' . $id1 . '&id_tipo_documento=' . $id2 . '&action=reactivar" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
</svg></a>';
                break;
            default:
                break;
        }
        return $boton;
    }

    // Botones de acción en la tabla
    public function botonesAccionPrincipal($id, $rol, $estado = null, $id2)
    {
        if (!$this->esSupervisor($rol)) return "";

        $boton = "";

        if (in_array($estado, ["Activo"])) {
            $boton = $this->obtenerbotones("Desactivar", $id, $id2);
            $boton .= $this->obtenerbotones("Historial", $id);
        } elseif (in_array($estado, ["Desactivado"])) {
            $boton = $this->obtenerbotones("Reactivar", $id, $id2);
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
    public function registrar($rol, $nombre, $nombre_archivo, $rutaDestino, $id_tipo_documento, $id_usuarios_supervisor)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $obj->bloquear_tabla($id_tipo_documento);

            $tipo = $obj->obtenerInfoTipo_Registrar($id_tipo_documento);

            $info = $obj->obtenerInfoTipos($id_tipo_documento);

            $obj->desactivarPorTipo($id_tipo_documento);

            $version = $obj->obtenerSiguienteVersion($id_tipo_documento);


            $id = $obj->registrar($id_tipo_documento, $nombre, $version, $nombre_archivo, $rutaDestino);
            if (!$id) {
                header("Location: tabla.php?error=1");
                exit;
            }


            $accion = "";
            $descripcion = "";
            if ($info['ultima_version'] == null) {
                $accion = "CREACION";
                $descripcion = $this->generarDescripcion($accion, $tipo);
            } else {
                $accion = "NUEVA_VERSION";
                $descripcion = $this->generarDescripcion($accion, $tipo);
            }

            $obj->registrarHistorial($id, $id_usuarios_supervisor, $accion, $descripcion);



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

    public function desactivar($rol, $id_plantilla, $id_tipo_documento, $id_usuarios_supervisor)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar Plantilla de documento.");
        }
        if (!$id_tipo_documento) {
            throw new Exception("ID inválido");
        }
        global $conn;
        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $registro = $obj->obtenerPorId((int)$id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe");
            }
            $filas = $obj->desactivarPorTipo((int)$id_tipo_documento);
            // NO  excepción

            $datos = $obj->obtenerInfoPlantilla($id_plantilla);

            $accion = "DESACTIVACION";
            $descripcion = $this->generarDescripcion($accion, $datos);
            // solo registra historial si hubo cambio
            if ($filas > 0) {
                $obj->registrarHistorial($id_plantilla, $id_usuarios_supervisor, $accion, $descripcion);
            }


            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Error en eliminar(): " . $e->getMessage());
            header("Location: tabla.php?error=10");
            exit;
        }
    }

    public function reactivar($rol, $id_plantilla, $id_usuarios_supervisor)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new plantilladocumento($conn);
            $registro = $obj->obtenerPorId($id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe");
            }

            if ((int)$registro['activo'] === 1) {
                throw new Exception("Ya está activa");
            }
            $obj->activarVersion($id_plantilla);

            $datos = $obj->obtenerInfoPlantilla($id_plantilla);

            $accion = "REACTIVACION";
            $descripcion = $this->generarDescripcion($accion, $datos);

            $obj->registrarHistorial($id_plantilla, $id_usuarios_supervisor, $accion, $descripcion);


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

    private function generarDescripcion($accion, $datos)
    {
        switch ($accion) {
            case 'CREACION':
                return 'Se creó la plantilla versión ' . $datos['version'] . ' para el tipo de documento "' . $datos['nombre'] . '".';

            case 'NUEVA_VERSION':
                return 'Se registró una nueva versión ' . $datos['version'] . ' de la plantilla. La versión anterior fue desactivada automáticamente.';

            case 'REACTIVACION':
                return 'Se reactivó la versión ' . $datos['version'] . ' y se desactivaron las demás versiones.';

            case 'DESACTIVACION':
                return 'Se desactivaron las plantillas activas del tipo de documento "' . $datos['nombre'] . '".';
        }
    }

    public function info_linea_tiempo($id_plantilla)
    {
        global $conn;
        try {
            $plantilla = new plantilladocumento($conn);

            if ($id_plantilla) {
                return $plantilla->linea_tiempo($id_plantilla);
            } else {
                $datos = []; // evita undefined variable
                return $datos;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: editar.php?error=1");
            exit;
        }
    }
}
