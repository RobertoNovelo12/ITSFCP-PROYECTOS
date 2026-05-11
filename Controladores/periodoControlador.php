<?php

require_once __DIR__ . '/../Modelos/periodo.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class periodoControlador
{
    /**
     * Verifica si el usuario tiene rol de supervisor.
     *
     * @param string $rol
     * @return bool
     */
    private function esSupervisor($rol): bool
    {
        return isset($rol) && $rol === 'supervisor';
    }

    /**
     * Sanitiza datos de entrada para prevenir XSS.
     *
     * @param string|null $dato
     * @return string|null
     */
    private function limpiar($dato): ?string
    {
        return isset($dato)
            ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8')
            : null;
    }

    /**
     * Obtiene listado de periodos con filtro opcional.
     *
     * @param string $rol
     * @param string|null $buscar
     * @return array
     */
    public function index($rol, $buscar = null): array
    {
        global $conn;

        try {
            // Validación de acceso
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            // Sanitización del filtro (evita XSS en vistas)
            $buscar = $this->limpiar($buscar);

            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos de un periodo para edición.
     *
     * @param string $rol
     * @param int $id_periodos
     * @return array
     */
    public function indexEditar($rol, $id_periodos): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            // Validación estricta de ID
            $id = filter_var($id_periodos, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene detalles de un periodo específico.
     *
     * @param string $rol
     * @param int $id_periodo
     * @return array
     */
    public function indexDetalles($rol, $id_periodo): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $id = filter_var($id_periodo, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoDetalles($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Desactiva un periodo (borrado lógico).
     * Implementa control transaccional, bloqueo y validaciones.
     *
     * @param int $id_periodo
     * @param string $rol
     * @throws Exception
     */
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
            $Periodo = new Periodo($conn);
            $Periodo->bloquear_tabla(); // BLOQUEO 
            $periodo = $Periodo->obtenerPorId((int)$id_periodo); // OBTENER EL PERIODO 
            if ($periodo['estado'] == 1) {
                $Periodo->desactivarActivos();
                // desactiva primero 
            }
            if (!$periodo) {
                throw new Exception("Periodo no encontrado");
            }
            $filas = $Periodo->eliminar_periodo((int)$id_periodo);
            if ($filas < 0) {
                throw new Exception("Error al eliminar");
            }
            $conn->commit();
            header("Location: index.php?mensaje=1");
            exit;
        } catch (Throwable $e) {

            // Reversión segura
            if ($conn->errno === 0) {
                $conn->rollback();
            }

            error_log("Error en eliminar(): " . $e->getMessage());

            header("Location: index.php?error=10");
            exit;
        }
    }

    /**
     * Retorna los encabezados de la tabla principal.
     *
     * @param string $rol
     * @return array
     */
    public function encabezadosPrincipal($rol): array
    {
        if (!$this->esSupervisor($rol)) {
            return [];
        }

        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Final',
            'Fecha Creación',
            'Hora Creación',
            'Estado',
            'Acciones'
        ];
    }

    /**
     * Genera las opciones de filtro con conteo.
     *
     * @param string $rol
     * @param array $filtros
     * @return array
     */
    public function opciones($rol, $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros) || !isset($filtros[0])) {
            return [];
        }

        // Validación defensiva
        $data = $filtros[0];

        return [
            'Total' => "Total (" . ($data['Total'] ?? 0) . " en total)",
            'Activo' => "Activos (" . ($data['Activo'] ?? 0) . " en total)",
            'Terminado' => "Terminados (" . ($data['Terminado'] ?? 0) . " en total)"
        ];
    }

    /**
     * Convierte acción a número de filtro.
     *
     * @param string $action
     * @return int
     */
    public function numerofiltro($action): int
    {
        return match ($action) {
            'Total' => 2,
            'Activo' => 1,
            'Terminado' => 0,
            default => 2 // fallback lógico
        };
    }

    /**
     * Obtiene datos para filtros.
     *
     * @param string $rol
     * @return array
     */
    public function filtros($rol): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método base para evitar duplicación de lógica en filtros.
     *
     * @param string $rol
     * @param int $tipoFiltro
     * @param string|null $buscar
     * @return array
     */
    private function obtenerPorFiltro($rol, int $tipoFiltro, $buscar = null): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            // Sanitización preventiva
            $buscar = $this->limpiar($buscar);

            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todos los periodos.
     */
    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    /**
     * Obtiene periodos activos.
     */
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    /**
     * Obtiene periodos terminados.
     */
    public function Terminado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    /**
     * Retorna clase de estilo según estado.
     *
     * @param string $estado
     * @return string
     */
    public function EstiloEstadoLista($estado): string
    {
        /**
         * Normalización para evitar errores por mayúsculas/minúsculas
         */
        $estado = strtolower(trim($estado));

        return match ($estado) {
            'activo' => "success",
            'terminado' => "danger",
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
                $boton = '<a href="detalles.php?id_periodos=' . $id1 . '" type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la periodo"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '<a href="index.php?&id_periodos=' . $id1 . '&action=desactivar_periodo" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
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
            $Periodo->bloquear_tabla();

            $datos = $this->generarPeriodoAutomatico();

            if ($datos['fin'] < $datos['inicio']) {
                throw new Exception("La fecha final no puede ser menor...");
            }

            $id_periodo = $Periodo->registrarPeriodo($datos['nombre'], $datos['inicio'], $datos['fin']);

            if (!$id_periodo) {
                header("Location: index.php?error=1");
                exit;
            }
            $conn->commit();
            header("Location: index.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            if ($e->getCode() == 1062) {
                header("Location: index.php?error=duplicado");
            } else {
                header("Location: index.php?error=2");
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
            header("Location: index.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();

            if ($e->getCode() == 1062) {
                header("Location: index.php?error=duplicado");
            } else {
                header("Location: index.php?error=2");
            }

            exit;
        }
    }
    /**
     * Genera automáticamente el periodo actual basado en la fecha del sistema.
     *
     * Reglas:
     * - Enero-Junio → periodo 1
     * - Julio-Diciembre → periodo 2
     *
     * @return array
     */
    public function generarPeriodoAutomatico(): array
    {

        $anio = (int) date("Y");
        $mes  = (int) date("n");

        if ($mes <= 6) {
            return [
                "nombre" => "{$anio}-1",
                "inicio" => "{$anio}-01-01",
                "fin"    => "{$anio}-06-30"
            ];
        }

        return [
            "nombre" => "{$anio}-2",
            "inicio" => "{$anio}-07-01",
            "fin"    => "{$anio}-12-31"
        ];
    }


    /**
     * Verifica existencia de conflictos de periodo.
     *
     * @param string $nombre
     * @param string $fecha_inicio
     * @param string $fecha_final
     * @return array
     */
    public function verificarPeriodo($nombre, $fecha_inicio, $fecha_final): array
    {
        global $conn;

        try {
            /**
             * Validación defensiva básica
             */
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_final)) {
                return ["activo" => 0, "desactivado" => 0];
            }

            $Periodo = new Periodo($conn);

            return $Periodo->verificarPeriodo($nombre, $fecha_inicio, $fecha_final);
        } catch (Throwable $e) {
            error_log("Error en verificarPeriodo(): " . $e->getMessage());

            // Respuesta segura por defecto
            return ["activo" => 0, "desactivado" => 0];
        }
    }


    /**
     * Determina el estado de la vista (crear, reactivar o bloquear).
     *
     * Lógica:
     * - Si hay activo → bloquear creación
     * - Si existe desactivado → permitir reactivación
     * - Si no existe → permitir creación
     *
     * @return array
     */
    public function obtenerEstadoVista(): array
    {
        /**
         * 1. Generar periodo automático
         */
        $datos = $this->generarPeriodoAutomatico();

        /**
         * 2. Validar conflictos
         */
        $verificar = $this->verificarPeriodo(
            $datos['nombre'],
            $datos['inicio'],
            $datos['fin']
        );

        /**
         * Validación defensiva (evita errores si modelo falla)
         */
        $activo = $verificar['activo'] ?? 0;
        $desactivado = $verificar['desactivado'] ?? 0;

        /**
         * 3. Decisión centralizada
         */
        if ($activo) {
            return [
                "datos" => $datos,
                "accion" => "bloqueado",
                "mensaje" => "Existe un periodo activo, no puede crear otro hasta que termine el activo"
            ];
        }

        if ($desactivado) {
            return [
                "datos" => $datos,
                "accion" => "reactivar",
                "mensaje" => null
            ];
        }

        return [
            "datos" => $datos,
            "accion" => "crear",
            "mensaje" => null
        ];
    }
}
