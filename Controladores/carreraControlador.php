<?php
// Controladores/carreraControlador.php

require_once __DIR__ . '/../Modelos/carrera.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class carreraControlador extends BaseControlador
{

    // ─
    // DATOS PARA TABLA E INDEX
    // ─

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Carrera($conn))->obtenerTablaFiltro($this->limpiar($buscar), 2);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar(string $rol, int $id_carrera): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_carrera, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Carrera($conn))->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, int $id_carrera): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_carrera, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Carrera($conn))->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    private function obtenerPorFiltro(string $rol, int $tipoFiltro, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Carrera($conn))->obtenerTablaFiltro($this->limpiar($buscar), $tipoFiltro);
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

    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }


    // ─
    // ENCABEZADOS Y OPCIONES DE FILTRO
    // ─

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

        return ['Carrera', 'Fecha Creación', 'Hora Creación', 'Estado', 'Acciones'];
    }

    public function opciones(): array
    {

        return [
            'Total'       => "Total",
            'Activo'      => "Activos",
            'Desactivado' => "Desactivados",
        ];
    }

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,
        };
    }


    // ─
    // ESTILO DE ESTADO (badge Bootstrap)
    // ─

    public function EstiloEstadoLista(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }


    // ─
    // BOTONES TABLA PRINCIPAL
    // ─

    private function obtenerbotones(string $tipo, ?int $id1 = null): string
    {
        return match ($tipo) {
            'Editar Carrera' =>
                '<a href="editar.php?id_carrera=' . $id1 . '" type="button" class="btn btn-sm btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar carrera">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg>
                </a>',

            'Detalles' =>
                '<a href="detalles.php?id_carrera=' . $id1 . '" type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de la carrera">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg>
                </a>',

            'Desactivar' =>
                '<a href="index.php?id_carrera=' . $id1 . '&action=desactivar_carrera" type="button" class="btn btn-sm btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar carrera">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg>
                </a>',

            default => '',
        };
    }

    public function botonesAccionPrincipal(int $id, string $rol, ?string $estado = null): string
    {
        if (!$this->esSupervisor($rol)) return '';

        if ($estado === 'Activo') {
            return $this->obtenerbotones('Editar Carrera', $id)
                 . $this->obtenerbotones('Detalles', $id)
                 . $this->obtenerbotones('Desactivar', $id);
        }

        if ($estado === 'Desactivado') {
            return $this->obtenerbotones('Editar Carrera', $id)
                 . $this->obtenerbotones('Detalles', $id);
        }

        return '';
    }


    // ─
    // BOTONES FORMULARIO EDITAR
    // ─

    public function obtenerbotonesEditar(string $tipo): string
    {
        return match ($tipo) {
            'Desactivar' => '<button type="submit" name="action" value="Desactivar" class="btn btn-sm btn-danger">Desactivar</button>',
            'Reactivar'  => '<button type="submit" name="action" value="Reactivar"  class="btn btn-sm btn-warning">Reactivar</button>',
            'Guardar'    => '<button type="submit" name="action" value="Guardar"    class="btn btn-sm btn-guardar">Guardar cambios</button>',
            default      => '',
        };
    }

    public function botonesAccionEditar(string $rol, ?string $estado = null): string
    {
        if (!$this->esSupervisor($rol)) return '';

        return match ($estado) {
            'Activo'      => $this->obtenerbotonesEditar('Desactivar') . $this->obtenerbotonesEditar('Guardar'),
            'Desactivado' => $this->obtenerbotonesEditar('Reactivar')  . $this->obtenerbotonesEditar('Guardar'),
            default       => '',
        };
    }


    // ─
    // REGISTRAR
    // Acción de formulario POST → redirige con msg.
    // ─

    public function registrarCarrera(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $nombre = trim($datos['NombreCarrera'] ?? '');

            if ($nombre === '') {
                throw new Exception('error_crear');
            }

            $conn->begin_transaction();
            $modelo = new Carrera($conn);
            $modelo->bloquear_tabla();
            $modelo->registrarCarrera($nombre);   // lanza Exception si hay duplicado activo
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
    // EDITAR
    // Acción de formulario POST → redirige con msg.
    // ─

    public function editarCarrera(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_carrera = (int)($datos['id_carrera']    ?? 0);
            $nombre     = trim($datos['NombreCarrera']  ?? '');

            if (!$id_carrera || $nombre === '') {
                throw new Exception('error_editar');
            }

            $conn->begin_transaction();
            $modelo = new Carrera($conn);

            // Verificar que no exista otra carrera con el mismo nombre
            $conflicto = $modelo->obtenerPorIdDiferente($id_carrera, $nombre);
            if ($conflicto['activo'] > 0 || $conflicto['desactivado'] > 0) {
                throw new Exception('error_duplicado');
            }

            $modelo->editarCarrera($nombre, $id_carrera);
            $conn->commit();

            $this->redirigir('exito_editar');

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $msg = ($e->getCode() == 1062) ? 'error_duplicado' : 'error_editar';
            $this->redirigir($msg);

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_editar', 'error_duplicado'])
                ? $e->getMessage()
                : 'error_editar';
            $this->redirigir($msg);
        }
    }


    // ─
    // DESACTIVAR
    // Acción GET desde enlace → redirige con msg.
    // ─

    public function desactivarCarrera(string $rol, int $id_carrera): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_carrera) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            $modelo = new Carrera($conn);
            $modelo->obtenerPorId($id_carrera);

            $filas = $modelo->eliminar_carrera($id_carrera);
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
    // Acción de formulario POST → redirige con msg.
    // ─

    public function reactivarCarrera(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_carrera = (int)($datos['id_carrera'] ?? 0);

            if (!$id_carrera) {
                throw new Exception('error_reactivar');
            }

            $conn->begin_transaction();
            $modelo = new Carrera($conn);
            $modelo->bloquear_tabla();
            $modelo->obtenerPorId($id_carrera, true);
            $modelo->reactivar($id_carrera);
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
}