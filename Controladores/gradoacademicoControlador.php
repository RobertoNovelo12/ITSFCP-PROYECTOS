<?php
// Controladores/gradoacademicoControlador.php

require_once __DIR__ . '/../Modelos/gradoacademico.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class gradoacademicoControlador extends BaseControlador
{

    // index
    //  LISTADO / FILTROS
    // index

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new GradoAcademico($conn))->obtenerTablaFiltro($this->limpiar($buscar), 2);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    private function obtenerPorFiltro(string $rol, int $tipoFiltro, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new GradoAcademico($conn))->obtenerTablaFiltro($this->limpiar($buscar), $tipoFiltro);
        } catch (Exception $e) {
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

    public function filtros(string $rol): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new GradoAcademico($conn))->obtenerDatosFiltro();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,
        };
    }

    // index
    //  DETALLE / EDICIÓN (solo lectura)
    // index

    public function indexEditar(string $rol, mixed $id_grado): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_grado, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new GradoAcademico($conn))->obtenerEditar($id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, mixed $id_grado): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_grado, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new GradoAcademico($conn))->obtenerDetalles($id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // index
    //  CRUD — redirigen con msg
    // index

    public function registrarGradoAcademico(string $rol, string $nombre): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new GradoAcademico($conn);
            $modelo->bloquearTabla();

            $verificacion = $modelo->verificarGradoAcademico($nombre);
            if ($verificacion['activo'] > 0) {
                throw new Exception("error_duplicado");
            }

            $modelo->registrarGradoAcademico($nombre);

            $conn->commit();
            $this->redirigir('exito_crear');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno !== 0) $conn->rollback();
            error_log($e->getMessage());

            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                'error_duplicado'     => 'error_duplicado',
                default               => 'error_crear',
            };
            $this->redirigir($msg);
        }
    }

    public function editarGradoAcademico(string $rol, mixed $id_grado, string $nombre): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_grado;

            $conn->begin_transaction();
            $modelo = new GradoAcademico($conn);

            $verificacion = $modelo->verificarGradoOtroId($id, $nombre);
            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception("error_duplicado");
            }

            $modelo->editarGradoAcademico($nombre, $id);

            $conn->commit();
            $this->redirigir('exito_editar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno !== 0) $conn->rollback();
            error_log($e->getMessage());

            $msg = match ($e->getMessage()) {
                'accion_no_permitida' => 'accion_no_permitida',
                'error_duplicado'     => 'error_duplicado',
                default               => 'error_editar',
            };
            $this->redirigir($msg);
        }
    }

    public function reactivar(string $rol, mixed $id_grado): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new GradoAcademico($conn);
            $modelo->bloquearTabla();
            $modelo->obtenerPorId((int)$id_grado, true);
            $modelo->reactivar((int)$id_grado);

            $conn->commit();
            $this->redirigir('exito_reactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno !== 0) $conn->rollback();
            error_log($e->getMessage());

            $msg = $e->getMessage() === 'accion_no_permitida'
                ? 'accion_no_permitida'
                : 'error_reactivar';
            $this->redirigir($msg);
        }
    }

    public function eliminar(string $rol, mixed $id_grado): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            if (!$id_grado) throw new Exception("ID inválido");

            $conn->begin_transaction();
            $modelo = new GradoAcademico($conn);

            if (!$modelo->obtenerPorId((int)$id_grado)) {
                throw new Exception("Grado Académico no encontrado");
            }

            $filas = $modelo->eliminarGradoAcademico((int)$id_grado);
            if ($filas < 0) throw new Exception("Error al desactivar");

            $conn->commit();
            $this->redirigir('exito_desactivar');
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno !== 0) $conn->rollback();
            error_log($e->getMessage());

            $msg = $e->getMessage() === 'accion_no_permitida'
                ? 'accion_no_permitida'
                : 'error_desactivar';
            $this->redirigir($msg);
        }
    }

    // index
    //  UI HELPERS
    // index

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Grado Académico', 'Fecha Creación', 'Hora Creación', 'Estado', 'Acciones'];
    }

    public function opciones(): array
    {
        return [
            'Total'       => "Total",
            'Activo'      => "Activos",
            'Desactivado' => "Desactivados",
        ];
    }

    public function EstiloEstadoLista(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }

    //  Botones tabla principal 

    private function obtenerbotones(string $tipo, ?int $id1 = null): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Editar' => Botones::botonIcono(
                'editar.php?id_grado=' . $id1,
                'warning',
                $iconos['tabla']['editar'],
                'Editar Grado Académico'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_grado=' . $id1,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles de Grado Académico'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?&id_grado=' . $id1 . '&action=desactivar_grados_academicos',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar Grado Académico'
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

    //  Botones formulario editar 

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

        if ($estado === 'Activo') {
            return $this->obtenerbotonesEditar('Desactivar')
                . $this->obtenerbotonesEditar('Guardar');
        }
        if ($estado === 'Desactivado') {
            return $this->obtenerbotonesEditar('Reactivar')
                . $this->obtenerbotonesEditar('Guardar');
        }

        return '';
    }
}
