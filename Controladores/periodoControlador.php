<?php
// Controladores/periodoControlador.php

require_once __DIR__ . '/../Modelos/periodo.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class periodoControlador extends BaseControlador
{

    // ─
    // UTILIDADES PRIVADAS
    // ─

    /**
     * Valida que una fecha tenga formato Y-m-d y sea válida.
     */
    private function esFechaValida(?string $fecha): bool
    {
        if (empty($fecha)) return false;
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Valida que las fechas de proyectos/integración estén dentro
     * del rango [inicio_semestre, fin_semestre].
     */
    private function validarFechasSubperiodos(
        string  $inicio,
        string  $fin,
        ?string $fip,
        ?string $ffp,
        ?string $fii,
        ?string $ffi
    ): array {
        $dInicio = new DateTime($inicio);
        $dFin    = new DateTime($fin);

        $reglas = [
            'fecha_inicio_proyectos'  => $fip,
            'fecha_fin_proyectos'     => $ffp,
            'fecha_inicio_solicitud'  => $fii,
            'fecha_fin_solicitud'     => $ffi,
        ];

        foreach ($reglas as $campo => $valor) {
            if ($valor === null || $valor === '') continue;

            if (!$this->esFechaValida($valor)) {
                return ['ok' => false, 'error' => "El campo {$campo} no tiene un formato de fecha válido."];
            }

            $d = new DateTime($valor);

            if ($d < $dInicio || $d > $dFin) {
                return [
                    'ok'    => false,
                    'error' => "La fecha '{$campo}' ({$valor}) está fuera del rango del semestre ({$inicio} – {$fin}).",
                ];
            }
        }

        if ($fip && $ffp && $fip > $ffp) {
            return ['ok' => false, 'error' => "La fecha de inicio de proyectos no puede ser mayor que la fecha fin de proyectos."];
        }

        if ($fii && $ffi && $fii > $ffi) {
            return ['ok' => false, 'error' => "La fecha de inicio de integración no puede ser mayor que la fecha fin de integración."];
        }

        return ['ok' => true, 'error' => null];
    }


    // ─
    // LISTADO / FILTROS
    // ─

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Periodo($conn))->obtenerPeriodoTablaFiltro($this->limpiar($buscar), 2);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar(string $rol, int $id_periodos): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_periodos, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Periodo($conn))->obtenerPeriodoEditar($id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, int $id_periodo): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_periodo, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Periodo($conn))->obtenerPeriodoDetalles($id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function filtros(string $rol): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Periodo($conn))->obtenerPeriodoDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // ─
    // OPCIONES / ENCABEZADOS
    // ─

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

        return [
            'Periodo',
            'Fecha Inicio',
            'Fecha Final',
            'Fecha Creación',
            'Hora Creación',
            'Estado',
            'Acciones',
        ];
    }

    public function opciones(string $rol, array $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros) || !isset($filtros[0])) return [];

        $data = $filtros[0];

        return [
            'Total'       => "Total ("        . ($data['Total']       ?? 0) . " en total)",
            'Activo'      => "Activos ("       . ($data['Activo']      ?? 0) . " en total)",
            'Terminado'   => "Terminados ("    . ($data['Terminado']   ?? 0) . " en total)",
            'Desactivado' => "Desactivados ("  . ($data['Desactivado'] ?? 0) . " en total)",
        ];
    }

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Terminado'   => 0,
            'Desactivado' => 3,
            default       => 2,
        };
    }


    // ─
    // FILTROS DE VISTA
    // ─

    private function obtenerPorFiltro(string $rol, int $tipoFiltro, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Periodo($conn))->obtenerPeriodoTablaFiltro($this->limpiar($buscar), $tipoFiltro);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Total(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    public function Activo(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    public function Terminado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 3, $buscar);
    }


    // ─
    // REGISTRAR PERIODO
    // Acción POST → redirige con msg.
    // error_fecha es especial: viaja como string en la URL.
    // ─

    public function registrarPeriodo(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new Periodo($conn);
            $modelo->bloquear_tabla();

            $periodoAuto = $this->generarPeriodoAutomatico();

            $fip = !empty($datos['fecha_inicio_proyectos']) ? trim($datos['fecha_inicio_proyectos']) : null;
            $ffp = !empty($datos['fecha_fin_proyectos'])    ? trim($datos['fecha_fin_proyectos'])    : null;
            $fii = !empty($datos['fecha_inicio_solicitud']) ? trim($datos['fecha_inicio_solicitud']) : null;
            $ffi = !empty($datos['fecha_fin_solicitud'])    ? trim($datos['fecha_fin_solicitud'])    : null;

            $validacion = $this->validarFechasSubperiodos(
                $periodoAuto['inicio'], $periodoAuto['fin'],
                $fip, $ffp, $fii, $ffi
            );

            if (!$validacion['ok']) {
                $conn->rollback();
                $msg = urlencode($validacion['error']);
                header("Location: crear.php?error_fecha={$msg}");
                exit;
            }

            $id = $modelo->registrarPeriodo(
                $periodoAuto['nombre'],
                $periodoAuto['inicio'],
                $periodoAuto['fin'],
                $fip, $ffp, $fii, $ffi
            );

            if (!$id) {
                throw new Exception('error_crear');
            }

            $conn->commit();
            $this->redirigir('exito_crear');

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $msg = ($e->getCode() == 1062) ? 'error_duplicado' : 'error_crear';
            $this->redirigir($msg);

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_crear', 'error_duplicado'])
                ? $e->getMessage()
                : 'error_crear';
            $this->redirigir($msg);
        }
    }


    // ─
    // ACTUALIZAR FECHAS DE SUBPERIODOS
    // Acción POST → redirige con msg.
    // error_fecha viaja como string en la URL.
    // ─

    public function actualizarFechasSubperiodos(string $rol, int $id_periodos, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new Periodo($conn);

            $datosActuales = $modelo->obtenerPeriodoEditar($id_periodos);
            if (empty($datosActuales)) {
                throw new Exception('error_editar');
            }

            $fip = !empty($datos['fecha_inicio_proyectos']) ? trim($datos['fecha_inicio_proyectos']) : null;
            $ffp = !empty($datos['fecha_fin_proyectos'])    ? trim($datos['fecha_fin_proyectos'])    : null;
            $fii = !empty($datos['fecha_inicio_solicitud']) ? trim($datos['fecha_inicio_solicitud']) : null;
            $ffi = !empty($datos['fecha_fin_solicitud'])    ? trim($datos['fecha_fin_solicitud'])    : null;

            $validacion = $this->validarFechasSubperiodos(
                $datosActuales['inicio'], $datosActuales['fin'],
                $fip, $ffp, $fii, $ffi
            );

            if (!$validacion['ok']) {
                $conn->rollback();
                $msg = urlencode($validacion['error']);
                header("Location: editar.php?id_periodos={$id_periodos}&error_fecha={$msg}");
                exit;
            }

            $modelo->actualizarFechasSubperiodos($id_periodos, $fip, $ffp, $fii, $ffi);
            $conn->commit();

            $this->redirigir('exito_editar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_editar'])
                ? $e->getMessage()
                : 'error_editar';
            $this->redirigir($msg);
        }
    }


    // ─
    // DESACTIVAR (borrado lógico)
    // Acción GET → redirige con msg.
    // ─

    public function desactivarPeriodo(string $rol, int $id_periodos): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_periodos) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            $modelo  = new Periodo($conn);
            $modelo->bloquear_tabla();

            $periodo = $modelo->obtenerPorId($id_periodos);
            if (!$periodo) {
                throw new Exception('error_desactivar');
            }

            if ((int)$periodo['estado'] === 1) {
                $modelo->desactivarActivos();
            }

            $filas = $modelo->eliminar_periodo($id_periodos);
            if ($filas < 0) {
                throw new Exception('error_desactivar');
            }

            $conn->commit();
            $this->redirigir('exito_desactivar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_desactivar'])
                ? $e->getMessage()
                : 'error_desactivar';
            $this->redirigir($msg);
        }
    }

    // Alias POST para el formulario de editar.php
    public function desactivarPeriodoPost(string $rol, int $id_periodos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_periodos) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            $modelo  = new Periodo($conn);
            $modelo->bloquear_tabla();

            $periodo = $modelo->obtenerPorId($id_periodos);
            if (!$periodo) {
                throw new Exception('error_desactivar');
            }

            if ((int)$periodo['estado'] === 1) {
                $modelo->desactivarActivos();
            }

            $filas = $modelo->eliminar_periodo($id_periodos);
            if ($filas < 0) {
                throw new Exception('error_desactivar');
            }

            $conn->commit();
            $this->redirigir('exito_desactivar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_desactivar'])
                ? $e->getMessage()
                : 'error_desactivar';
            $this->redirigir($msg);
        }
    }


    // ─
    // REACTIVAR
    // Acción POST → redirige con msg.
    // ─

    public function reactivar(string $rol, string $nombre): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo           = new Periodo($conn);
            $modelo->bloquear_tabla();
            $periodoExistente = $modelo->obtenerPorNombre($nombre);

            if (!$periodoExistente) {
                throw new Exception('error_reactivar');
            }

            $modelo->desactivarActivos();
            $modelo->reactivarPeriodo((int)$periodoExistente['id_periodos']);
            $conn->commit();

            $this->redirigir('exito_reactivar');

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $msg = ($e->getCode() == 1062) ? 'error_duplicado' : 'error_reactivar';
            $this->redirigir($msg);

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_reactivar', 'error_duplicado'])
                ? $e->getMessage()
                : 'error_reactivar';
            $this->redirigir($msg);
        }
    }


    // ─
    // ESTADO VISTA (crear.php)
    // ─

    public function generarPeriodoAutomatico(): array
    {
        $anio = (int)date('Y');
        $mes  = (int)date('n');

        if ($mes <= 6) {
            return [
                'nombre' => "{$anio}-1",
                'inicio' => "{$anio}-01-01",
                'fin'    => "{$anio}-06-30",
            ];
        }

        return [
            'nombre' => "{$anio}-2",
            'inicio' => "{$anio}-07-01",
            'fin'    => "{$anio}-12-31",
        ];
    }

    public function verificarPeriodo(string $nombre, string $fecha_inicio, string $fecha_final): array
    {
        global $conn;
        try {
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_final)) {
                return ['activo' => 0, 'desactivado' => 0, 'desactivado_pasado' => 0];
            }
            return (new Periodo($conn))->verificarPeriodo($nombre, $fecha_inicio, $fecha_final);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return ['activo' => 0, 'desactivado' => 0, 'desactivado_pasado' => 0];
        }
    }

    public function obtenerEstadoVista(): array
    {
        $datos    = $this->generarPeriodoAutomatico();
        $verificar = $this->verificarPeriodo($datos['nombre'], $datos['inicio'], $datos['fin']);

        $activo             = $verificar['activo']             ?? 0;
        $desactivado        = $verificar['desactivado']        ?? 0;
        $desactivado_pasado = $verificar['desactivado_pasado'] ?? 0;

        if ($activo) {
            return [
                'datos'   => $datos,
                'accion'  => 'bloqueado',
                'mensaje' => 'Ya existe un periodo activo. No puede crear otro hasta que el actual termine.',
            ];
        }

        if ($desactivado) {
            return [
                'datos'   => $datos,
                'accion'  => 'reactivar',
                'mensaje' => null,
            ];
        }

        return [
            'datos'   => $datos,
            'accion'  => 'crear',
            'mensaje' => null,
        ];
    }


    // ─
    // ESTILOS / BOTONES
    // ─

    public function EstiloEstadoLista(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'terminado'   => 'danger',
            'desactivado' => 'secondary',
            default       => 'info',
        };
    }

    private function obtenerbotones(string $tipo, ?int $id1 = null): string
    {
        return match ($tipo) {

            'Editar Periodo' =>
                '<a href="editar.php?id_periodos=' . $id1 . '"
                    class="btn btn-sm btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-title="Editar periodo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg>
                </a>',

            'Detalles' =>
                '<a href="detalles.php?id_periodos=' . $id1 . '"
                    class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-title="Ver detalles del periodo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg>
                </a>',

            'Desactivar' =>
                '<a href="index.php?id_periodos=' . $id1 . '&action=desactivar_periodo"
                    class="btn btn-sm btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-title="Desactivar periodo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg>
                </a>',

            'Reactivar' =>
                '<a href="crear.php"
                    class="btn btn-sm btn-success"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-title="Ir a reactivar este periodo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
                    </svg>
                </a>',

            default => '',
        };
    }

    public function botonesAccionPrincipal(int $id, string $rol, ?string $estado = null, int $puede_reactivar = 0): string
    {
        if (!$this->esSupervisor($rol)) return '';

        if ($estado === 'Activo') {
            return $this->obtenerbotones('Editar Periodo', $id)
                 . $this->obtenerbotones('Detalles', $id)
                 . $this->obtenerbotones('Desactivar', $id);
        }

        if ($estado === 'Terminado') {
            return $this->obtenerbotones('Detalles', $id);
        }

        if ($estado === 'Desactivado') {
            $botones = $this->obtenerbotones('Detalles', $id);
            if ($puede_reactivar) {
                $botones .= $this->obtenerbotones('Reactivar', $id);
            }
            return $botones;
        }

        return '';
    }
}