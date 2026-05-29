<?php
// Controladores/nivelsniControlador.php

require_once __DIR__ . '/../Modelos/nivelsni.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class NivelsniControlador extends BaseControlador
{

    // ─
    // LECTURA / FILTROS
    // ─

    /** Tabla principal (filtro = 2 → todos). */
    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            return (new NivelSNI($conn))->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::index — ' . $e->getMessage());
            return [];
        }
    }

    /** Datos para el formulario de edición. */
    public function indexEditar(string $rol, mixed $id_nivel): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_nivel, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new NivelSNI($conn))->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::indexEditar — ' . $e->getMessage());
            return [];
        }
    }

    /** Datos para la vista de detalles. */
    public function indexDetalles(string $rol, mixed $id_nivel): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_nivel, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new NivelSNI($conn))->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::indexDetalles — ' . $e->getMessage());
            return [];
        }
    }


    // ─
    // FILTROS POR ESTADO (usados desde index.php vía $action)
    // ─

    private function obtenerPorFiltro(string $rol, int $tipoFiltro, ?string $buscar = null): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $buscar = $this->limpiar($buscar);
            return (new NivelSNI($conn))->obtenerTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::obtenerPorFiltro — ' . $e->getMessage());
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
    // CRUD
    // ─

    /**
     * Registra un nuevo Nivel SNI.
     * Acción POST → redirige con msg.
     */
    public function registrarNivelSNI(string $rol, string $nombre): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new NivelSNI($conn);
            $modelo->bloquear_tabla();

            $verificacion = $modelo->verificarNivelSNI($nombre);
            if ($verificacion['activo'] > 0) {
                throw new Exception('error_duplicado');
            }

            $id = $modelo->registrarNivelSNI($nombre);
            if (!$id) throw new Exception('error_crear');

            $conn->commit();
            $this->redirigir('exito_crear');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('NivelsniControlador::registrarNivelSNI — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_duplicado', 'error_crear'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_crear';
            $this->redirigir($msg);
        }
    }

    /**
     * Edita un Nivel SNI existente.
     * Acción POST → redirige con msg.
     */
    public function editarNivelSNI(string $rol, mixed $id_nivel, string $nombre): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_nivel;
            if (!$id) throw new Exception('error_editar');

            $conn->begin_transaction();
            $modelo = new NivelSNI($conn);

            $verificacion = $modelo->obtenerPorIdDiferente($id, $nombre);
            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception('error_duplicado');
            }

            $modelo->editarNivelSNI($nombre, $id);
            $conn->commit();
            $this->redirigir('exito_editar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('NivelsniControlador::editarNivelSNI — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_duplicado', 'error_editar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_editar';
            $this->redirigir($msg);
        }
    }

    /**
     * Desactiva (soft delete) un Nivel SNI.
     * Invocado desde GET (enlace tabla) o POST (formulario editar).
     */
    public function eliminar(string $rol, mixed $id_nivel): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = (int)$id_nivel;
            if (!$id) throw new Exception('error_desactivar');

            $conn->begin_transaction();
            $modelo = new NivelSNI($conn);
            $modelo->obtenerPorId($id);
            $filas = $modelo->eliminar_niveles_sni($id);
            if ($filas < 0) throw new Exception('error_desactivar');
            $conn->commit();
            $this->redirigir('exito_desactivar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('NivelsniControlador::eliminar — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_desactivar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_desactivar';
            $this->redirigir($msg);
        }
    }

    /**
     * Reactiva un Nivel SNI desactivado.
     * Acción POST → redirige con msg.
     */
    public function reactivar(string $rol, mixed $id_nivel): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_nivel;
            if (!$id) throw new Exception('error_reactivar');

            $conn->begin_transaction();
            $modelo = new NivelSNI($conn);
            $modelo->bloquear_tabla();
            $modelo->obtenerPorId($id, true);
            $modelo->reactivar($id);
            $conn->commit();
            $this->redirigir('exito_reactivar');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('NivelsniControlador::reactivar — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_reactivar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_reactivar';
            $this->redirigir($msg);
        }
    }

    // ─
    // VERIFICACIONES (usadas en vistas antes de enviar)
    // ─

    public function verificarNivelSNI(string $nombre): array
    {
        global $conn;
        try {
            if (empty($nombre)) return ['activo' => 0, 'desactivado' => 0];
            return (new NivelSNI($conn))->verificarNivelSNI($nombre);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::verificarNivelSNI — ' . $e->getMessage());
            return ['activo' => 0, 'desactivado' => 0];
        }
    }

    public function obtenerPorIdDiferente(mixed $id_nivel, string $nombre): array
    {
        global $conn;
        try {
            if (empty($nombre)) return ['activo' => 0, 'desactivado' => 0];
            return (new NivelSNI($conn))->obtenerPorIdDiferente((int)$id_nivel, $nombre);
        } catch (Throwable $e) {
            error_log('NivelsniControlador::obtenerPorIdDiferente — ' . $e->getMessage());
            return ['activo' => 0, 'desactivado' => 0];
        }
    }

    // ─
    // HELPERS DE VISTA
    // ─

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Nivel SNI', 'Fecha Creación', 'Hora Creación', 'Estado', 'Acciones'];
    }

    public function opciones(): array
    {
        return [
            'Total'       => 'Total',
            'Activo'      => 'Activos',
            'Desactivado' => 'Desactivados',
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

    public function EstiloEstadoLista(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }

    // ─ Botones tabla principal ─

    private function obtenerbotones(string $tipo, int $id): string
    {
        return match ($tipo) {
            'Editar' =>
                '<a href="editar.php?id_nivel=' . $id . '" class="btn btn-sm btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar Nivel SNI">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-pencil-square" viewBox="0 0 16 16">
                      <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                      <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>',

            'Detalles' =>
                '<a href="detalles.php?id_nivel=' . $id . '" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles de Nivel SNI">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-eye-fill" viewBox="0 0 16 16">
                      <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                      <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>',

            'Desactivar' =>
                '<a href="index.php?id_nivel=' . $id . '&action=desactivar_nivel_sni"
                    class="btn btn-sm btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar Nivel SNI">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                      <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>',

            default => '',
        };
    }

    public function botonesAccionPrincipal(int $id, string $rol, string $estado = ''): string
    {
        if (!$this->esSupervisor($rol)) return '';

        $boton = '';
        if ($estado === 'Activo') {
            $boton .= $this->obtenerbotones('Editar', $id);
            $boton .= $this->obtenerbotones('Detalles', $id);
            $boton .= $this->obtenerbotones('Desactivar', $id);
        } elseif ($estado === 'Desactivado') {
            $boton .= $this->obtenerbotones('Editar', $id);
            $boton .= $this->obtenerbotones('Detalles', $id);
        }
        return $boton;
    }

    // ─ Botones formulario editar ─

    private function obtenerbotonesEditar(string $tipo): string
    {
        return match ($tipo) {
            'Desactivar' => '<button type="submit" name="action" value="Desactivar" class="btn btn-danger">Desactivar</button>',
            'Reactivar'  => '<button type="submit" name="action" value="Reactivar"  class="btn btn-warning">Reactivar</button>',
            'Guardar'    => '<button type="submit" name="action" value="Guardar"    class="btn btn-guardar">Guardar cambios</button>',
            default      => '',
        };
    }

    public function botonesAccionEditar(string $rol, string $estado = ''): string
    {
        if (!$this->esSupervisor($rol)) return '';

        $boton = '';
        if ($estado === 'Activo') {
            $boton .= $this->obtenerbotonesEditar('Desactivar');
            $boton .= $this->obtenerbotonesEditar('Guardar');
        } elseif ($estado === 'Desactivado') {
            $boton .= $this->obtenerbotonesEditar('Reactivar');
            $boton .= $this->obtenerbotonesEditar('Guardar');
        }
        return $boton;
    }
}
