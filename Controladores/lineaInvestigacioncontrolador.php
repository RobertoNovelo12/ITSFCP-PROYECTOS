<?php

require_once __DIR__ . '/../Modelos/lineainvestigacion.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class lineaControlador
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
     * Obtiene listado de líneas de investigación con filtro opcional.
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

            $Linea = new Linea($conn);

            return $Linea->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos de una línea de investigación para edición.
     *
     * @param string $rol
     * @param int $id_linea
     * @return array
     */
    public function indexEditar($rol, $id_linea): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            // Validación estricta de ID
            $id = filter_var($id_linea, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Linea = new Linea($conn);

            return $Linea->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene detalles de una línea de investigación específico.
     *
     * @param string $rol
     * @param int $id_linea
     * @return array
     */
    public function indexDetalles($rol, $id_linea): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $id = filter_var($id_linea, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $Linea = new Linea($conn);

            return $Linea->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Desactiva una línea de investigación (borrado lógico).
     * Implementa control transaccional, bloqueo y validaciones.
     *
     * @param int $id_linea
     * @param string $rol
     * @throws Exception
     */
    //Cambia de estado a 0 - Desactivado administrativamente 
    public function eliminar($rol, $id_linea)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar línea de investigación.");
        }
        if (!$id_linea) {
            throw new Exception("ID inválido");
        }
        global $conn;
        $conn->begin_transaction();
        try {
            $Linea = new Linea($conn);
            //$Linea->bloquear_tabla(); // BLOQUEO 
            $Linea->obtenerPorId((int)$id_linea); // OBTENER LA LINEA DE INVESTIGACION 

            $filas = $Linea->eliminar_linea((int)$id_linea);
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
            'Línea de investigación',
            'Descripción',
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
            'Desactivado' => "Desactivados (" . ($data['Desactivado'] ?? 0) . " en total)"
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
            'Desactivado' => 0,
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

            $Linea = new Linea($conn);

            return $Linea->obtenerDatosFiltro($rol);
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

            $Linea = new Linea($conn);

            return $Linea->obtenerTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todos las líneas de investigación.
     */
    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    /**
     * Obtiene líneas de investigación activos.
     */
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    /**
     * Obtiene líneas de investigación desactivados.
     */
    public function Desactivado($rol, $buscar = null): array
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
            'desactivado' => "danger",
            default => "info"
        };
    }

    //BOTONES
    private function obtenerbotones($tipo, $id1 = null)
    {
        $boton = "";
        switch ($tipo) {
            case 'Editar Linea':
                $boton = '<a href="editar.php?id_linea=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar línea de investigación"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_linea=' . $id1 . '" type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la línea de investigación"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '<a href="index.php?&id_linea=' . $id1 . '&action=desactivar_linea" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar línea de investigación"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
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
            $boton .= $this->obtenerbotones("Editar Linea", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Desactivado") {
            $boton .= $this->obtenerbotones("Editar Linea", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }

    //BOTONES

    public function obtenerbotonesEditar($tipo)
    {
        $boton = "";
        switch ($tipo) {
            case 'Desactivar':
                $boton = '<button type="submit" name="action" value="Desactivar" class="btn btn-danger">Desactivar</button>';
                break;
            case 'Reactivar':
                $boton = '<button type="submit" name="action" value="Reactivar" class="btn btn-warning">Reactivar</button>';
                break;
            case 'Guardar':
                $boton = '<button type="submit" name="action" value="Guardar" class="btn btn-guardar">Guardar cambios</button>';
                break;
            default:
                break;
        }
        return $boton;
    }

    //Botones para panel de tareas
    public function botonesAccionEditar($rol, $estado = null)
    {
        $boton = "";

        switch ($rol) {
            case 'supervisor':
                if (in_array($estado, ["Activo"])) {
                    $boton  = $this->obtenerbotonesEditar("Desactivar");
                    $boton  .= $this->obtenerbotonesEditar("Guardar");
                } elseif (in_array($estado, ["Desactivado"])) {
                    $boton  = $this->obtenerbotonesEditar("Reactivar");
                    $boton  .= $this->obtenerbotonesEditar("Guardar");
                }
                break;
            default:
                break;
        }

        return $boton;
    }

    //Crear Línea de investigación
    function registrarLinea($rol, $nombre, $descripcion)
    {

        if (!$this->esSupervisor($rol)) return "";

        global $conn;

        $conn->begin_transaction();
        try {
            $Linea = new Linea($conn);
            // BLOQUEO DE CONCURRENCIA
            $Linea->bloquear_tabla();

            $verificacion = $Linea->verificarLinea($nombre);

            if ($verificacion['activo'] > 0 && $verificacion['desactivado'] > 0) {
                throw new Exception("Registro duplicado");
            }

            $id_linea = $Linea->registrarLinea($nombre, $descripcion);

            if (!$id_linea) {
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

    //Editar Línea de investigación
    function editarLinea($rol, $id_linea, $nombre, $descripcion)
    {

        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $Linea = new Linea($conn);
            // BLOQUEO DE CONCURRENCIA
            $verificacion = $this->obtenerPorIdDiferente((int)$id_linea, $nombre); // OBTENER LINEA DE INVESTIGACIÓN 

            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception("Registro duplicado");
            }

            $id_linea = $Linea->editarLinea($nombre, $descripcion, $id_linea);

            if (!$id_linea) {
                header("Location: index.php?error=10");
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

    public function reactivar($rol, $id_linea)
    {

        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $Linea = new Linea($conn);
            // BLOQUEO DE CONCURRENCIA
            $Linea->bloquear_tabla();

            $Linea->obtenerPorId($id_linea, true);
            //Reactivar
            $Linea->reactivar($id_linea);

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
     * Verifica existencia de conflictos de linea de investigación.
     *
     * @param string $nombre
     * @return array
     */
    public function verificarLinea($nombre): array
    {
        global $conn;

        try {
            /**
             * Validación defensiva básica
             */
            if (empty($nombre)) {
                return ["activo" => 0, "desactivado" => 0];
            }

            $Linea = new Linea($conn);

            return $Linea->verificarLinea($nombre);
        } catch (Throwable $e) {
            error_log("Error en verificarLinea(): " . $e->getMessage());

            // Respuesta segura por defecto
            return ["activo" => 0, "desactivado" => 0];
        }
    }

    public function obtenerPorIdDiferente($id_linea, $nombre): array
    {
        global $conn;

        try {
            /**
             * Validación defensiva básica
             */
            if (empty($nombre)) {
                return ["activo" => 0, "desactivado" => 0];
            }

            $Linea = new Linea($conn);

            return $Linea->obtenerPorIdDiferente($id_linea, $nombre);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorIdDiferente(): " . $e->getMessage());

            // Respuesta segura por defecto
            return ["activo" => 0, "desactivado" => 0];
        }
    }
}
