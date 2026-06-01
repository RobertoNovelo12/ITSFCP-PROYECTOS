<?php
// Controladores/AreaConocimientoControlador.php

require_once __DIR__ . '/../Modelos/areaconocimiento.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';


class AreaConocimientoControlador extends BaseControlador
{

    // 
    // DATOS PARA TABLA E INDEX
    // 

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreasTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar(string $rol, int $id_area): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreaEditar($id_area);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, int $id_area): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreasDetalles($id_area);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    public function Total(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreasTablaFiltro($buscar, 2);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Activo(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreasTablaFiltro($buscar, 1);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new AreaConocimiento($conn))->obtenerAreasTablaFiltro($buscar, 0);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // 
    // ENCABEZADOS Y OPCIONES DE FILTRO
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

        return [
            'Área',
            'Descripción',
            'Subareas',
            'Estado',
            'Creación',
            'Modificación',
            'Acciones',
        ];
    }

    public function opciones(): array
    {
        return [
            'Total'       => "Total",
            'Activo'      => "Activos",
            'Desactivado' => "Desactivados",
        ];
    }

    /**
     * Convierte el nombre del filtro en el valor numérico que espera el modelo.
     *   'Activo'      → 1
     *   'Desactivado' → 0
     *   'Total'       → 2  (sin filtro de estado)
     */
    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,   // 'Total' o cualquier valor desconocido
        };
    }


    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstadoLista(string $estado): string
    {
        return match ($estado) {
            'Activo'      => 'success',
            'Desactivado' => 'danger',
            default       => 'info',
        };
    }


    // 
    // BOTONES
    // 

    public function obtenerbotones(string $tipo, ?int $id1 = null): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Editar Area' => Botones::botonIcono(
                'editar.php?id_area=' . $id1,
                'warning',
                $iconos['tabla']['editar'],
                'Editar área de conocimiento'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_area=' . $id1,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles del área de conocimiento'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?id_area=' . $id1 . '&action=desactivar_area',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar área de conocimiento'
            ),

            default => '',
        };
    }

    public function botonesAccionPrincipal(int $id, string $rol, ?string $estado = null): string
    {
        if (!$this->esSupervisor($rol)) return '';

        if ($estado === 'Activo') {
            return $this->obtenerbotones('Editar Area', $id)
                 . $this->obtenerbotones('Detalles', $id)
                 . $this->obtenerbotones('Desactivar', $id);
        }

        if ($estado === 'Desactivado') {
            return $this->obtenerbotones('Detalles', $id);
        }

        return '';
    }


    // 
    // REGISTRAR ÁREA
    // Acción de formulario POST → redirige con msg.
    // 

    public function registrarArea(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $nombre      = trim($datos['NombreArea']   ?? '');
            $descripcion = trim($datos['Descripcion']  ?? '');
            $subareas    = $datos['subarea']            ?? [];

            if ($nombre === '') {
                throw new Exception('error_crear');
            }

            $conn->begin_transaction();
            $id_area = (new AreaConocimiento($conn))->crearAreaCompleta($nombre, $descripcion, $subareas);

            if (!$id_area) {
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
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_crear'])
                ? $e->getMessage()
                : 'error_crear';
            $this->redirigir($msg);
        }
    }

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
    // EDITAR ÁREA Y SUBAREAS
    // Acción de formulario POST → redirige con msg.
    // 

    public function editarArea(string $rol, array $datos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_area     = (int)($datos['id_area']      ?? 0);
            $nombre      = trim($datos['NombreArea']    ?? '');
            $descripcion = trim($datos['Descripcion']   ?? '');
            $estado      = (int)($datos['Estado']       ?? 1);
            $subareas    = $datos['subarea']             ?? [];

            if (!$id_area || $nombre === '') {
                throw new Exception('error_editar');
            }

            $modelo  = new AreaConocimiento($conn);
            $ids_bd  = $modelo->obtenerIdsSubareas($id_area);

            $conn->begin_transaction();

            // 1. Actualizar datos del área
            $modelo->editarArea($nombre, $descripcion, $id_area);

            // 2. Registrar / editar subareas
            $ids_form = [];
            foreach ($subareas as $sub) {
                $id_sub      = $sub['id_subarea'] ?? null;
                $nombre_sub  = trim($sub['nombre'] ?? '');

                if ($nombre_sub === '') continue;

                // Validar duplicidad antes de persistir
                $modelo->comparar_Duplicidad_Subareas($id_area, $nombre_sub, $id_sub);

                if ($id_sub === 'nuevo' || $id_sub === null || $id_sub === '') {
                    $modelo->registrarsubarea($id_area, $nombre_sub);
                } else {
                    $modelo->editarSubarea((int)$id_sub, $nombre_sub);
                    $ids_form[] = (int)$id_sub;
                }
            }

            // 3. Eliminar subareas que ya no están en el formulario
            $ids_eliminar = array_diff($ids_bd, $ids_form);
            foreach ($ids_eliminar as $id_del) {
                $modelo->eliminar_subarea((int)$id_del, 0);
            }

            // 4. Desactivar área si el estado indica 0
            if ($estado === 0) {
                $modelo->eliminar_area($id_area, 0);
            }

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
    // DESACTIVAR ÁREA
    // Acción de enlace GET → redirige con msg.
    // 

    public function desactivarArea(string $rol, int $id_area): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            if (!$id_area) {
                throw new Exception('error_desactivar');
            }

            $conn->begin_transaction();
            (new AreaConocimiento($conn))->eliminar_area($id_area, 0);
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
}