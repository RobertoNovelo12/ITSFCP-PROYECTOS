<?php
// Controladores/ajustesTiposDocumentosControlador.php

require_once __DIR__ . '/../Modelos/ajustestiposdocumentos.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class ajustesTiposDocumentosControlador extends BaseControlador
{

    // 
    // DATOS PARA TABLA E INDEX
    // 

    public function index(string $rol): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new ajustesdocumentos($conn))->obtenerTablaFiltro(['proceso', 'final']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar(string $rol, int $id_tipo_documento): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_tipo_documento, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new ajustesdocumentos($conn))->obtenerEditar($id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // 
    // FILTROS DE TABLA
    // 

    private function obtenerPorFiltro(string $rol, array $tipoFiltro): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new ajustesdocumentos($conn))->obtenerTablaFiltro($tipoFiltro);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Todos(string $rol): array
    {
        return $this->obtenerPorFiltro($rol, ['proceso', 'final']);
    }

    public function Proceso(string $rol): array
    {
        return $this->obtenerPorFiltro($rol, ['proceso']);
    }

    public function Final(string $rol): array
    {
        return $this->obtenerPorFiltro($rol, ['final']);
    }


    // 
    // ENCABEZADOS Y OPCIONES DE FILTRO
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

        return ['Nombre', 'Categoría', 'Descripción', 'Orden', 'Estado', 'Acciones'];
    }

    public function opciones(): array
    {

        return [
            'Todos'   => 'Todos',
            'Proceso' => 'Proceso',
            'Final'   => 'Final',
        ];
    }


    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }


    // 
    // BOTONES
    // 

    public function obtenerbotonesEditar(string $tipo): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Desactivar' =>
            '<button type="submit" name="action" value="Desactivar" class="btn btn-sm btn-danger">
                    <i class="' . $iconos['tabla']['solicitar_cierre'] . ' me-1"></i>Desactivar
                </button>',

            'Reactivar' =>
            '<button type="submit" name="action" value="Reactivar" class="btn btn-sm btn-warning">
                    <i class="' . $iconos['editar']['reactivar'] . ' me-1"></i>Reactivar
                </button>',

            'Guardar' =>
            '<button type="submit" name="action" value="Guardar" class="btn btn-sm btn-guardar">
                    <i class="' . $iconos['editar']['guardar'] . ' me-1"></i>Guardar cambios
                </button>',

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


    // 
    // EDITAR
    // Acción de formulario POST → redirige con msg.
    // 

    public function editar(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_tipo_documento = (int)($datos['id_tipo'] ?? 0);
            $descripcion       = trim($datos['Descripcion'] ?? '');
            $orden             = (int)trim($datos['Orden']  ?? 0);

            if (!$id_tipo_documento) {
                throw new Exception('error_editar');
            }

            $conn->begin_transaction();
            (new ajustesdocumentos($conn))->editar($descripcion, $orden, $id_tipo_documento);
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
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_editar'])
                ? $e->getMessage()
                : 'error_editar';
            $this->redirigir($msg);
        }
    }


    // 
    // DESACTIVAR
    // Acción de formulario POST → redirige con msg.
    // 

    public function desactivar(string $rol, int $id_tipo_documento): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_tipo_documento) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            $ajustes = new ajustesdocumentos($conn);
            $ajustes->obtenerPorId($id_tipo_documento);

            $filas = $ajustes->desactivar($id_tipo_documento);
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


    // 
    // REACTIVAR
    // Acción de formulario POST → redirige con msg.
    // 

    public function reactivar(string $rol, int $id_tipo_documento): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_tipo_documento) {
                throw new Exception('error_reactivar');
            }

            $conn->begin_transaction();
            $ajustes = new ajustesdocumentos($conn);
            $ajustes->bloquear_tabla();
            $ajustes->obtenerPorId($id_tipo_documento, true);
            $ajustes->reactivar($id_tipo_documento);
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
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_reactivar'])
                ? $e->getMessage()
                : 'error_reactivar';
            $this->redirigir($msg);
        }
    }
}
