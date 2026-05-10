<?php

require_once __DIR__ . '/../Modelos/director.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class directorControlador
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
     * Obtiene listado de directores con filtro opcional.
     */
    public function index($rol, $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            $obj = new Director($conn);
            return $obj->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos de un director para edición.
     */
    public function indexEditar($rol, $id_director): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_director, FILTER_VALIDATE_INT);
            if (!$id) return [];
            $obj = new Director($conn);
            return $obj->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene detalles de un director específico.
     */
    public function indexDetalles($rol, $id_director): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_director, FILTER_VALIDATE_INT);
            if (!$id) return [];
            $obj = new Director($conn);
            return $obj->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene grados académicos activos para el select del formulario.
     */
    public function obtenerGrados($rol): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $obj = new Director($conn);
            return $obj->obtenerGradosActivos();
        } catch (Throwable $e) {
            error_log("Error en obtenerGrados(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Desactiva un director (borrado lógico).
     */
    public function eliminar($rol, $id_director)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para eliminar directores.");
        }
        if (!$id_director) {
            throw new Exception("ID inválido");
        }
        global $conn;
        $conn->begin_transaction();
        try {
            $obj = new Director($conn);
            $registro = $obj->obtenerPorId((int)$id_director);

            if (!$registro) {
                throw new Exception("Director no encontrado");
            }
            $filas = $obj->eliminar_director((int)$id_director);
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
            'Nombre',
            'Apellidos',
            'Correo',
            'Teléfono',
            'Grado Académico',
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
            'Total' => "Total (" . ($data['Total'] ?? 0) . ")",
            'Activo' => "Activos (" . ($data['Activo'] ?? 0) . ")",
            'Desactivado' => "Desactivados (" . ($data['Desactivado'] ?? 0) . ")"
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
            $obj = new Director($conn);
            return $obj->obtenerDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método base para evitar duplicación de lógica en filtros.
     */
    private function obtenerPorFiltro($rol, int $tipoFiltro, $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            $obj = new Director($conn);
            return $obj->obtenerTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /** Obtiene todos los directores. */
    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    /** Obtiene directores activos. */
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    /** Obtiene directores desactivados. */
    public function Desactivado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    /**
     * Retorna clase de estilo según estado.
     */
    public function EstiloEstadoLista($estado): string
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
            case 'Editar Director':
                $boton = '<a href="editar.php?id_director=' . $id1 . '" type="button" class="btn btn-warning" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Editar director"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>';
                break;
            case 'Detalles':
                $boton = '<a href="detalles.php?id_director=' . $id1 . '" type="button" class="btn btn-info" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del director"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
  <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/></svg></a>';
                break;
            case 'Desactivar':
                $boton = '<a href="tabla.php?&id_director=' . $id1 . '&action=desactivar_director" type="button" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar director"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
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
            $boton .= $this->obtenerbotones("Editar Director", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
            $boton .= $this->obtenerbotones("Desactivar", $id);
        } elseif ($estado === "Desactivado") {
            $boton .= $this->obtenerbotones("Editar Director", $id);
            $boton .= $this->obtenerbotones("Detalles", $id);
        }

        return $boton;
    }

    // BOTONES FORMULARIO EDITAR
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

    // Botones para panel de edición
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

    /**
     * Registra un nuevo director.
     */
    function registrarDirector($rol, $id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new Director($conn);
            $obj->bloquear_tabla();

            // Verificar duplicado solo por correo (campo UNIQUE de la tabla)
            $verificacion = $obj->verificarDirector($correo);

            if ($verificacion['activo'] > 0 && $verificacion['desactivado'] > 0) {
                throw new Exception("Registro duplicado");
            }

            $id_director = $obj->registrarDirector((int)$id_grado, $nombre, $apellido, $correo ?: null, $telefono ?: null, $fecha_inicio, $fecha_final ?: null);

            if (!$id_director) {
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

    /**
     * Edita un director existente.
     */
    function editarDirector($rol, $id_director, $id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final, $motivo_fin)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new Director($conn);
            $verificacion = $this->obtenerPorIdDiferente((int)$id_director, $correo);

            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception("Registro duplicado");
            }

            $id_director = $obj->editarDirector((int)$id_grado, $nombre, $apellido, $correo ?: null, $telefono ?: null, (int)$id_director, $fecha_inicio, $fecha_final ?: null, $motivo_fin ?: null);

            if (!$id_director) {
                header("Location: tabla.php?error=10");
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

    /**
     * Reactiva un director.
     */
    public function reactivar($rol, $id_director)
    {
        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $obj = new Director($conn);
            $obj->bloquear_tabla();
            $obj->obtenerPorId($id_director, true);
            $obj->reactivar($id_director);

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

    /**
     * Verifica existencia de conflictos de director por correo.
     */
    public function verificarDirector($correo): array
    {
        global $conn;
        try {
            if (empty($correo)) return ["activo" => 0, "desactivado" => 0];
            $obj = new Director($conn);
            return $obj->verificarDirector($correo);
        } catch (Throwable $e) {
            error_log("Error en verificarDirector(): " . $e->getMessage());
            return ["activo" => 0, "desactivado" => 0];
        }
    }

    public function obtenerPorIdDiferente($id_director, $correo): array
    {
        global $conn;
        try {
            if (empty($correo)) return ["activo" => 0, "desactivado" => 0];
            $obj = new Director($conn);
            return $obj->obtenerPorIdDiferente($id_director, $correo);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorIdDiferente(): " . $e->getMessage());
            return ["activo" => 0, "desactivado" => 0];
        }
    }

    // CONTROLADOR: directorControlador.php

    public function info_linea_tiempo($id_director)
    {
        global $conn;

        try {
            $pagina = $_GET['pagina'] ?? 1;

            $director = new Director($conn);

            if ($id_director) {
                return $director->linea_tiempo_director($id_director, $pagina);
            } else {
                return [
                    "datos" => [],
                    "paginacion" => [
                        "total" => 0,
                        "por_pagina" => 5,
                        "pagina" => 1,
                        "total_paginas" => 1
                    ]
                ];
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: tabla.php?error=1");
            exit;
        }
    }

    public function EstiloTimeLine($tipo)
    {
        return match (strtoupper($tipo)) {
            'CREACION' => 'success',
            'ACTUALIZACION' => 'primary',
            'BAJA' => 'danger',
            default => 'secondary'
        };
    }
}
