<?php
// Controladores/periodoControlador.php

require_once __DIR__ . '/../Modelos/periodo.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class periodoControlador
{
    // 
    //  UTILIDADES PRIVADAS
    // 

    private function esSupervisor($rol): bool
    {
        return isset($rol) && $rol === 'supervisor';
    }

    private function limpiar($dato): ?string
    {
        return isset($dato)
            ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8')
            : null;
    }

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
     *
     * @param string      $inicio      Inicio del semestre (Y-m-d)
     * @param string      $fin         Fin del semestre   (Y-m-d)
     * @param string|null $fip         fecha_inicio_proyectos
     * @param string|null $ffp         fecha_fin_proyectos
     * @param string|null $fii         fecha_inicio_solicitud
     * @param string|null $ffi         fecha_fin_solicitud
     * @return array  ['ok' => bool, 'error' => string|null]
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
            'fecha_inicio_proyectos'    => $fip,
            'fecha_fin_proyectos'       => $ffp,
            'fecha_inicio_solicitud'  => $fii,
            'fecha_fin_solicitud'     => $ffi,
        ];

        foreach ($reglas as $campo => $valor) {
            if ($valor === null || $valor === '') continue; // nullable → se permite vacío

            if (!$this->esFechaValida($valor)) {
                return ['ok' => false, 'error' => "El campo {$campo} no tiene un formato de fecha válido."];
            }

            $d = new DateTime($valor);

            if ($d < $dInicio || $d > $dFin) {
                return [
                    'ok'    => false,
                    'error' => "La fecha '{$campo}' ({$valor}) está fuera del rango del semestre ({$inicio} – {$fin})."
                ];
            }
        }

        // Coherencia interna: inicio ≤ fin dentro de cada sub-periodo
        if ($fip && $ffp && $fip > $ffp) {
            return ['ok' => false, 'error' => "La fecha de inicio de proyectos no puede ser mayor que la fecha fin de proyectos."];
        }

        if ($fii && $ffi && $fii > $ffi) {
            return ['ok' => false, 'error' => "La fecha de inicio de integración no puede ser mayor que la fecha fin de integración."];
        }

        return ['ok' => true, 'error' => null];
    }

    // 
    //  LISTADO / FILTROS
    // 

    public function index($rol, $buscar = null): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) return [];

            $buscar  = $this->limpiar($buscar);
            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log("Error en index(): " . $e->getMessage());
            return [];
        }
    }

    public function indexEditar($rol, $id_periodos): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) return [];

            $id = filter_var($id_periodos, FILTER_VALIDATE_INT);
            if (!$id) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoEditar($id);
        } catch (Throwable $e) {
            error_log("Error en indexEditar(): " . $e->getMessage());
            return [];
        }
    }

    public function indexDetalles($rol, $id_periodo): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) return [];

            $id = filter_var($id_periodo, FILTER_VALIDATE_INT);
            if (!$id) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoDetalles($id);
        } catch (Throwable $e) {
            error_log("Error en indexDetalles(): " . $e->getMessage());
            return [];
        }
    }

    public function filtros($rol): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) return [];

            $Periodo = new Periodo($conn);
            return $Periodo->obtenerPeriodoDatosFiltro($rol);
        } catch (Throwable $e) {
            error_log("Error en filtros(): " . $e->getMessage());
            return [];
        }
    }

    // 
    //  OPCIONES / ENCABEZADOS
    // 

    public function encabezadosPrincipal($rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

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

    public function opciones($rol, $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros) || !isset($filtros[0])) {
            return [];
        }

        $data = $filtros[0];

        return [
            'Total'      => "Total ("        . ($data['Total']      ?? 0) . " en total)",
            'Activo'     => "Activos ("      . ($data['Activo']     ?? 0) . " en total)",
            'Terminado'  => "Terminados ("   . ($data['Terminado']  ?? 0) . " en total)",
            'Desactivado' => "Desactivados (" . ($data['Desactivado'] ?? 0) . " en total)",
        ];
    }

    public function numerofiltro($action): int
    {
        return match ($action) {
            'Total'    => 2,
            'Activo'   => 1,
            'Terminado' => 0,
            default    => 2
        };
    }

    // 
    //  FILTROS DE VISTA
    // Se incluye completa porque $filtro=3 se pasa directamente al modelo.
    // 

    private function obtenerPorFiltro($rol, int $tipoFiltro, $buscar = null): array
    {
        global $conn;

        try {
            if (!$this->esSupervisor($rol)) {
                return [];
            }

            $buscar  = $this->limpiar($buscar);
            $Periodo = new Periodo($conn);

            return $Periodo->obtenerPeriodoTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log("Error en obtenerPorFiltro(): " . $e->getMessage());
            return [];
        }
    }

    public function Total($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }
    public function Activo($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }
    public function Terminado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    // 
    //  CREAR PERIODO (con fechas de proyectos e integración)
    // 

    /**
     * Registra un nuevo periodo.
     * Lee las fechas de proyectos/integración del POST y las valida.
     */
    public function registrarPeriodo($rol): void
    {
        global $conn;

        if (!$this->esSupervisor($rol)) {
            header("Location: index.php?error=sin_permiso");
            exit;
        }

        $conn->begin_transaction();

        try {
            $Periodo = new Periodo($conn);
            $Periodo->bloquear_tabla();

            $datos = $this->generarPeriodoAutomatico();

            // Leer fechas del POST
            $fip = !empty($_POST['fecha_inicio_proyectos'])   ? trim($_POST['fecha_inicio_proyectos'])   : null;
            $ffp = !empty($_POST['fecha_fin_proyectos'])      ? trim($_POST['fecha_fin_proyectos'])       : null;
            $fii = !empty($_POST['fecha_inicio_solicitud']) ? trim($_POST['fecha_inicio_solicitud'])  : null;
            $ffi = !empty($_POST['fecha_fin_solicitud'])    ? trim($_POST['fecha_fin_solicitud'])     : null;

            // Validar rango
            $validacion = $this->validarFechasSubperiodos(
                $datos['inicio'],
                $datos['fin'],
                $fip,
                $ffp,
                $fii,
                $ffi
            );

            if (!$validacion['ok']) {
                $conn->rollback();
                $msg = urlencode($validacion['error']);
                header("Location: crear.php?error_fecha={$msg}");
                exit;
            }

            $id_periodo = $Periodo->registrarPeriodo(
                $datos['nombre'],
                $datos['inicio'],
                $datos['fin'],
                $fip,
                $ffp,
                $fii,
                $ffi
            );

            if (!$id_periodo) {
                $conn->rollback();
                header("Location: crear.php?error=1");
                exit;
            }

            $conn->commit();
            header("Location: index.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            header(
                $e->getCode() == 1062
                    ? "Location: crear.php?error=duplicado"
                    : "Location: crear.php?error=2"
            );
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Error en registrarPeriodo(): " . $e->getMessage());
            header("Location: crear.php?error=2");
            exit;
        }
    }

    // 
    //  EDITAR FECHAS DE PROYECTOS / INTEGRACIÓN
    // 

    /**
     * Actualiza únicamente las fechas de proyectos e integración.
     * No permite cambiar nombre, semestre ni ciclo escolar.
     */
    public function actualizarFechasSubperiodos($rol, int $id_periodos): void
    {
        global $conn;

        if (!$this->esSupervisor($rol)) {
            header("Location: index.php?error=sin_permiso");
            exit;
        }

        $conn->begin_transaction();

        try {
            $Periodo = new Periodo($conn);

            // Obtener datos actuales para validar rango
            $datosActuales = $Periodo->obtenerPeriodoEditar($id_periodos);

            if (empty($datosActuales)) {
                throw new Exception("Periodo no encontrado.");
            }

            $fip = !empty($_POST['fecha_inicio_proyectos'])   ? trim($_POST['fecha_inicio_proyectos'])   : null;
            $ffp = !empty($_POST['fecha_fin_proyectos'])      ? trim($_POST['fecha_fin_proyectos'])       : null;
            $fii = !empty($_POST['fecha_inicio_solicitud']) ? trim($_POST['fecha_inicio_solicitud'])  : null;
            $ffi = !empty($_POST['fecha_fin_solicitud'])    ? trim($_POST['fecha_fin_solicitud'])     : null;

            $validacion = $this->validarFechasSubperiodos(
                $datosActuales['inicio'],
                $datosActuales['fin'],
                $fip,
                $ffp,
                $fii,
                $ffi
            );

            if (!$validacion['ok']) {
                $conn->rollback();
                $msg = urlencode($validacion['error']);
                header("Location: editar.php?id_periodos={$id_periodos}&error_fecha={$msg}");
                exit;
            }

            $Periodo->actualizarFechasSubperiodos(
                $id_periodos,
                $fip,
                $ffp,
                $fii,
                $ffi
            );

            $conn->commit();
            header("Location: index.php?mensaje=2");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Error en actualizarFechasSubperiodos(): " . $e->getMessage());
            header("Location: editar.php?id_periodos={$id_periodos}&error=2");
            exit;
        }
    }

    // 
    //  ELIMINAR (borrado lógico)
    // 

    public function eliminar($id_periodo, $rol): void
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
            $Periodo->bloquear_tabla();

            $periodo = $Periodo->obtenerPorId((int)$id_periodo);

            if (!$periodo) {
                throw new Exception("Periodo no encontrado");
            }

            if ($periodo['estado'] == 1) {
                $Periodo->desactivarActivos();
            }

            $filas = $Periodo->eliminar_periodo((int)$id_periodo);

            if ($filas < 0) {
                throw new Exception("Error al eliminar");
            }

            $conn->commit();
            header("Location: index.php?mensaje=1");
            exit;
        } catch (Throwable $e) {
            if ($conn->errno === 0) $conn->rollback();
            error_log("Error en eliminar(): " . $e->getMessage());
            header("Location: index.php?error=10");
            exit;
        }
    }

    // 
    //  REACTIVAR
    // 

    public function reactivar($nombre): void
    {
        global $conn;

        $conn->begin_transaction();

        try {
            $Periodo = new Periodo($conn);
            $Periodo->bloquear_tabla();
            $periodoExistente = $Periodo->obtenerPorNombre($nombre);
            $Periodo->desactivarActivos();
            $Periodo->reactivarPeriodo($periodoExistente['id_periodos']);
            $conn->commit();
            header("Location: index.php?mensaje=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            header(
                $e->getCode() == 1062
                    ? "Location: index.php?error=duplicado"
                    : "Location: index.php?error=2"
            );
            exit;
        }
    }

    // ---------------------------------------------------------------
    // Desactivado() — nuevo método de filtro de vista
    //
    // Equivalente a Total(), Activo(), Terminado().
    // Necesario porque index.php llama $periodoControlador->$action()
    // dinámicamente.
    // ---------------------------------------------------------------

    public function Desactivado($rol, $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 3, $buscar);
    }

    // 
    //  ESTADO VISTA (crear)
    // 

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
// Lógica completa:
//
//   1. Se genera el periodo automático del semestre ACTUAL.
//   2. Se verifica en BD:
//      a. ¿Hay uno ACTIVO?           → bloqueado (ya existe uno activo)
//      b. ¿Hay uno DESACTIVADO vigente (semestre actual, no terminado)?
//                                    → ofrecer reactivar
//      c. ¿Solo hay uno PASADO desactivado (semestre anterior)?
//                                    → permitir crear el nuevo (semestre nuevo)
//      d. ¿No hay ninguno?           → permitir crear
// fecha_fin_solicitud

    /**
     * Wrapper para verificarPeriodo del modelo.
     */
    public function verificarPeriodo($nombre, $fecha_inicio, $fecha_final): array
    {
        global $conn;

        try {
            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_final)) {
                return ["activo" => 0, "desactivado" => 0, "desactivado_pasado" => 0];
            }

            $Periodo = new Periodo($conn);
            return $Periodo->verificarPeriodo($nombre, $fecha_inicio, $fecha_final);
        } catch (Throwable $e) {
            error_log("Error en verificarPeriodo(): " . $e->getMessage());
            return ["activo" => 0, "desactivado" => 0, "desactivado_pasado" => 0];
        }
    }

    /**
     * Determina el estado de la vista de creación de periodo.
     *
     * Posibles acciones:
     *   "bloqueado"  → ya hay un periodo activo, no se puede crear otro
     *   "reactivar"  → hay uno desactivado del semestre actual, se debe reactivar
     *   "crear"      → no hay conflicto vigente, se puede crear
     *
     * Nota: si solo existe un periodo PASADO desactivado (año anterior),
     * se permite crear el nuevo semestre directamente sin reactivar,
     * porque reactivar un periodo que ya terminó no tiene sentido.
     */
    public function obtenerEstadoVista(): array
    {
        $datos    = $this->generarPeriodoAutomatico();
        $verificar = $this->verificarPeriodo($datos['nombre'], $datos['inicio'], $datos['fin']);

        $activo            = $verificar['activo']             ?? 0;
        $desactivado       = $verificar['desactivado']        ?? 0;  // vigente (semestre actual)
        $desactivado_pasado = $verificar['desactivado_pasado'] ?? 0; // ya terminó

        // 1. Hay un periodo activo → bloquear
        if ($activo) {
            return [
                "datos"   => $datos,
                "accion"  => "bloqueado",
                "mensaje" => "Ya existe un periodo activo. No puede crear otro hasta que el actual termine."
            ];
        }

        // 2. Hay un desactivado vigente del semestre actual → solo reactivar
        //    (el semestre aún no termina, tiene sentido retomarlo)
        if ($desactivado) {
            return [
                "datos"   => $datos,
                "accion"  => "reactivar",
                "mensaje" => null
            ];
        }

        // 3. Solo hay un desactivado PASADO (semestre anterior o más antiguo)
        //    → se ignora, ese periodo ya terminó, no tiene sentido reactivarlo.
        //    Se permite crear el nuevo semestre normalmente.
        //    (desactivado_pasado se puede loguear/mostrar como info si se desea)

        // 4. No hay conflicto → crear
        return [
            "datos"   => $datos,
            "accion"  => "crear",
            "mensaje" => null
        ];
    }
    // 
    //  ESTILOS / BOTONES
    // 

    public function EstiloEstadoLista($estado): string
    {
        $estado = strtolower(trim($estado));

        return match ($estado) {
            'activo'      => 'success',
            'terminado'   => 'danger',
            'desactivado' => 'secondary',
            default       => 'info'
        };
    }

    private function obtenerbotones($tipo, $id1 = null): string
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

            // Reactivar: lleva a crear.php que centraliza toda la lógica de validación.
            // La vista detecta automáticamente que hay un desactivado vigente y muestra
            // el formulario de reactivar con sus validaciones correspondientes.
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

            default => ''
        };
    }

    public function botonesAccionPrincipal($id, $rol, $estado = null, $puede_reactivar = 0): string
    {
        if (!$this->esSupervisor($rol)) {
            return "";
        }

        $boton = "";

        if (in_array($estado, ['Activo'])) {
            $boton .= $this->obtenerbotones('Editar Periodo', $id);
            $boton .= $this->obtenerbotones('Detalles', $id);
            $boton .= $this->obtenerbotones('Desactivar', $id);
        } elseif ($estado === 'Terminado') {
            $boton .= $this->obtenerbotones('Detalles', $id);
        } elseif ($estado === 'Desactivado') {
            $boton .= $this->obtenerbotones('Detalles', $id);

            if ($puede_reactivar) {
                // Solo si el semestre aún no terminó → tiene sentido reactivar
                $boton .= $this->obtenerbotones('Reactivar', $id);
            }
            // Si puede_reactivar = 0 → semestre pasado, no se ofrece reactivar
        }

        return $boton;
    }
}
