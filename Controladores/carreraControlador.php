<?php
// Controladores/carreraControlador.php

require_once __DIR__ . '/../Modelos/carrera.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

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
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Editar Carrera' => Botones::botonIcono(
                'editar.php?id_carrera=' . $id1,
                'warning',
                $iconos['tabla']['editar'],
                'Editar carrera'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_carrera=' . $id1,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles de la carrera'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?id_carrera=' . $id1 . '&action=desactivar_carrera',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar carrera'
            ),

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


    // ─ Botones formulario editar ─

    private function obtenerbotonesEditar(string $tipo): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {
            'Desactivar' => Botones::botonData(
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar carrera',
                ['accion' => 'Desactivar'],
                'sm',
                'Desactivar'
            ),
            'Reactivar' => Botones::botonData(
                'warning',
                $iconos['tabla']['reactivar'],
                'Reactivar carrera',
                ['accion' => 'Reactivar'],
                'sm',
                'Reactivar'
            ),
            'Guardar' => Botones::botonData(
                'guardar',
                $iconos['tabla']['guardar'],
                'Guardar cambios',
                ['accion' => 'Guardar'],
                'sm',
                'Guardar'
            ),
            default => '',
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
