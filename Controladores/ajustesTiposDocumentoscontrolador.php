<?php

require_once __DIR__ . '/../Modelos/ajustestiposdocumentos.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class ajustesTiposDocumentoscontrolador
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
     * Obtiene listado de tipos de documentos con filtro opcional.
     *
     * @param string $rol
     * @param string|null $buscar
     * @return array
     */
    public function index($rol): array
    {
        global $conn;

        try {
            // Validación de acceso
            if (!$this->esSupervisor($rol)) {
                return [];
            }


            $ajustes = new ajustesdocumentos($conn);

            return $ajustes->obtenerTablaFiltro(['proceso', 'final']);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

        public function EstiloEstado($estado): string
    {
        $estado = strtolower(trim($estado));

        return match ($estado) {
            'activo' => "success",
            'desactivado' => "danger",
            default => "info"
        };
    }

    /**
     * Obtiene datos de un ajuste de documento para edición.
     *
     * @param string $rol
     * @param int $id_tipo_documento
     * @return array
     */
    public function indexEditar($rol, $id_tipo_documento): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            // Validación estricta de ID
            $id = filter_var($id_tipo_documento, FILTER_VALIDATE_INT);

            if (!$id) {
                return [];
            }

            $ajustes = new ajustesdocumentos($conn);

            return $ajustes->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar(): " . $e->getMessage());
            return [];
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
            'Nombre',
            'Categoria',
            'Descripción',
            'Orden',
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

        return [
            'Todos' => "Todos",
            'Proceso' => "Proceso",
            'Final' => "Final"
        ];
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

            $ajustes = new ajustesdocumentos($conn);

            return $ajustes->obtenerDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método base para evitar duplicación de lógica en filtros.
     *
     * @param string $rol
     * @param array $tipoFiltro
     * @return array
     */
    private function obtenerPorFiltro(string $rol, array $tipoFiltro): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $ajustes = new ajustesdocumentos($conn);

            return $ajustes->obtenerTablaFiltro($tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todos los tipos de documentos.
     */
    public function Todos($rol): array
    {
        return $this->obtenerPorFiltro($rol, ['proceso', 'final']);
    }

    /**
     * Obtiene los tipos proceso de documentos.
     */
    public function Proceso($rol): array
    {
        return $this->obtenerPorFiltro($rol, ['proceso']);
    }

    /**
     * Obtiene los tipos final de documentos.
     */
    public function Final($rol): array
    {
        return $this->obtenerPorFiltro($rol, ['final']);
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

    //Registrar

    //Editar ajuste de documento
    //REVISAR
    public function editar($rol, $datos)
    {

        $id_tipo_documento = $datos['id_tipo'];
        $descripcion = trim($datos['Descripcion']);
        $orden = trim($datos['Orden']);

        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $ajustes = new ajustesdocumentos($conn);

            $ajustes ->editar($descripcion, $orden, $id_tipo_documento);


            if (!$id_tipo_documento) {
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

    //Cambia de estado a 0 - Desactivado administrativamente 
    public function desactivar($rol, $id_tipo_documento)
    {
        if (!$this->esSupervisor($rol)) {
            throw new Exception("No tienes permiso para desactivar un documento.");
        }
        if (!$id_tipo_documento) {
            throw new Exception("ID inválido");
        }
        global $conn;
        $conn->begin_transaction();
        try {
            $ajustes = new ajustesdocumentos($conn);
            //$ajustes->bloquear_tabla(); // BLOQUEO 
            $ajustes->obtenerPorId((int)$id_tipo_documento); // OBTENER EL AJUSTE DE DOCUMENTO

            $filas = $ajustes->desactivar((int)$id_tipo_documento);
            if ($filas < 0) {
                throw new Exception("Error al eliminar");
            }
            $conn->commit();
            header("Location: tabla.php?mensaje=1");
            exit;
        } catch (Throwable $e) {

            // Reversión segura
            if ($conn->errno === 0) {
                $conn->rollback();
            }

            error_log("Error en eliminar(): " . $e->getMessage());

            header("Location: tabla.php?error=10");
            exit;
        }
    }

    //REVISAR
    public function reactivar($rol, $id_tipo_documento)
    {

        if (!$this->esSupervisor($rol)) return "";
        global $conn;

        $conn->begin_transaction();
        try {
            $ajustes = new ajustesdocumentos($conn);
            // BLOQUEO DE CONCURRENCIA
            $ajustes->bloquear_tabla();

            $ajustes->obtenerPorId($id_tipo_documento, true);
            //Reactivar
            $ajustes->reactivar($id_tipo_documento);

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
