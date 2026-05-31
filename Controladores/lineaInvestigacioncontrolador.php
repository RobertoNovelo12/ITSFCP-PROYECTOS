<?php
// Controladores/lineaInvestigacionControlador.php

require_once __DIR__ . '/../Modelos/lineaInvestigacion.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class LineaInvestigacionControlador extends BaseControlador
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
            return (new Linea($conn))->obtenerTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::index — ' . $e->getMessage());
            return [];
        }
    }

    /** Datos para el formulario de edición. */
    public function indexEditar(string $rol, mixed $id_linea): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_linea, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Linea($conn))->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::indexEditar — ' . $e->getMessage());
            return [];
        }
    }

    /** Datos para la vista de detalles. */
    public function indexDetalles(string $rol, mixed $id_linea): array
    {
        global $conn;
        try {
            if (!$this->esSupervisor($rol)) return [];
            $id = filter_var($id_linea, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Linea($conn))->obtenerDetalles($id);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::indexDetalles — ' . $e->getMessage());
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
            return (new Linea($conn))->obtenerTablaFiltro($buscar, $tipoFiltro);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::obtenerPorFiltro — ' . $e->getMessage());
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
     * Registra una nueva línea de investigación.
     * Acción POST → redirige con msg.
     */
    public function registrarLinea(string $rol, string $nombre, string $descripcion): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new Linea($conn);
            $modelo->bloquear_tabla();

            $verificacion = $modelo->verificarLinea($nombre);
            if ($verificacion['activo'] > 0) {
                throw new Exception('error_duplicado');
            }

            $id = $modelo->registrarLinea($nombre, $descripcion);
            if (!$id) throw new Exception('error_crear');

            $conn->commit();
            $this->redirigir('exito_crear');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('LineaInvestigacionControlador::registrarLinea — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_duplicado', 'error_crear'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_crear';
            $this->redirigir($msg);
        }
    }

    /**
     * Edita una línea de investigación existente.
     * Acción POST → redirige con msg.
     */
    public function editarLinea(string $rol, mixed $id_linea, string $nombre, string $descripcion): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_linea;
            if (!$id) throw new Exception('error_editar');

            $conn->begin_transaction();
            $modelo = new Linea($conn);

            $verificacion = $modelo->obtenerPorIdDiferente($id, $nombre);
            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception('error_duplicado');
            }

            $modelo->editarLinea($nombre, $descripcion, $id);
            $conn->commit();
            $this->redirigir('exito_editar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('LineaInvestigacionControlador::editarLinea — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_duplicado', 'error_editar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_editar';
            $this->redirigir($msg);
        }
    }

    /**
     * Desactiva (soft delete) una línea de investigación.
     * Invocado desde GET (enlace tabla) o POST (formulario editar).
     */
    public function eliminar(string $rol, mixed $id_linea): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = (int)$id_linea;
            if (!$id) throw new Exception('error_desactivar');

            $conn->begin_transaction();
            $modelo = new Linea($conn);
            $modelo->obtenerPorId($id);
            $filas = $modelo->eliminar_linea($id);
            if ($filas < 0) throw new Exception('error_desactivar');
            $conn->commit();
            $this->redirigir('exito_desactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('LineaInvestigacionControlador::eliminar — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_desactivar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_desactivar';
            $this->redirigir($msg);
        }
    }

    /**
     * Reactiva una línea de investigación desactivada.
     * Acción POST → redirige con msg.
     */
    public function reactivar(string $rol, mixed $id_linea): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_linea;
            if (!$id) throw new Exception('error_reactivar');

            $conn->begin_transaction();
            $modelo = new Linea($conn);
            $modelo->bloquear_tabla();
            $modelo->obtenerPorId($id, true);
            $modelo->reactivar($id);
            $conn->commit();
            $this->redirigir('exito_reactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log('LineaInvestigacionControlador::reactivar — ' . $e->getMessage());
            $claves = ['accion_no_permitida', 'error_reactivar'];
            $msg = in_array($e->getMessage(), $claves) ? $e->getMessage() : 'error_reactivar';
            $this->redirigir($msg);
        }
    }

    // ─
    // VERIFICACIONES (usadas en vistas antes de enviar)
    // ─

    public function verificarLinea(string $nombre): array
    {
        global $conn;
        try {
            if (empty($nombre)) return ['activo' => 0, 'desactivado' => 0];
            return (new Linea($conn))->verificarLinea($nombre);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::verificarLinea — ' . $e->getMessage());
            return ['activo' => 0, 'desactivado' => 0];
        }
    }

    public function obtenerPorIdDiferente(mixed $id_linea, string $nombre): array
    {
        global $conn;
        try {
            if (empty($nombre)) return ['activo' => 0, 'desactivado' => 0];
            return (new Linea($conn))->obtenerPorIdDiferente((int)$id_linea, $nombre);
        } catch (Throwable $e) {
            error_log('LineaInvestigacionControlador::obtenerPorIdDiferente — ' . $e->getMessage());
            return ['activo' => 0, 'desactivado' => 0];
        }
    }

    // ─
    // HELPERS DE VISTA
    // ─

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Línea de investigación', 'Descripción', 'Fecha Creación', 'Hora Creación', 'Estado', 'Acciones'];
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
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Editar' => Botones::botonIcono(
                'editar.php?id_linea=' . $id,
                'warning',
                $iconos['tabla']['editar'],
                'Editar línea de investigación'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_linea=' . $id,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles de la línea de investigación'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?id_linea=' . $id . '&action=desactivar_linea',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar línea de investigación'
            ),

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
            'Desactivar' => '<button type="submit" name="action" value="Desactivar" class="btn btn-sm btn-danger">Desactivar</button>',
            'Reactivar'  => '<button type="submit" name="action" value="Reactivar"  class="btn btn-sm btn-warning">Reactivar</button>',
            'Guardar'    => '<button type="submit" name="action" value="Guardar"    class="btn btn-sm btn-guardar">Guardar cambios</button>',
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
