<?php
// Controladores/AreaConocimientoControlador.php

require_once __DIR__ . '/../Modelos/areaconocimiento.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

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
        return match ($tipo) {
            'Editar Area' =>
                '<a href="editar.php?id_area=' . $id1 . '" type="button" class="btn btn-sm btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Editar área de conocimiento">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg>
                </a>',

            'Detalles' =>
                '<a href="detalles.php?id_area=' . $id1 . '" type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Ver detalles del área de conocimiento">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-eye-fill" style="padding:0px;margin:auto;" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg>
                </a>',

            'Desactivar' =>
                '<a href="index.php?id_area=' . $id1 . '&action=desactivar_area" type="button" class="btn btn-sm btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Desactivar área de conocimiento">
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