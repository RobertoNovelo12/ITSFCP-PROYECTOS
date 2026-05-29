<?php
// Controladores/directorControlador.php

require_once __DIR__ . '/../Modelos/director.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class directorControlador extends BaseControlador
{

    // ─
    //  LISTADO / FILTROS
    // ─

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Director($conn))->obtenerTablaFiltro($this->limpiar($buscar), 2);
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
            return (new Director($conn))->obtenerTablaFiltro($this->limpiar($buscar), $tipoFiltro);
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

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,
        };
    }

    // ─
    //  DETALLE / EDICIÓN (solo lectura)
    // ─

    public function indexEditar(string $rol, mixed $id_director): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_director, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Director($conn))->obtenerEditar($id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, mixed $id_director): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $id = filter_var($id_director, FILTER_VALIDATE_INT);
            if (!$id) return [];
            return (new Director($conn))->obtenerDetalles($id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function obtenerGrados(string $rol): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Director($conn))->obtenerGradosActivos();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // ─
    //  CRUD — redirigen con msg
    // ─

    public function registrarDirector(
        string $rol,
        mixed $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        ?string $fecha_inicio,
        ?string $fecha_final
    ): void {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new Director($conn);
            $modelo->bloquearTabla();

            $verificacion = $modelo->verificarDirector($correo);
            if ($verificacion['activo'] > 0) {
                throw new Exception("error_duplicado");
            }

            $modelo->registrarDirector(
                (int)$id_grado,
                $nombre,
                $apellido,
                $correo ?: null,
                $telefono ?: null,
                $fecha_inicio ?: null,
                $fecha_final ?: null
            );

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

    public function editarDirector(
        string $rol,
        mixed $id_director,
        mixed $id_grado,
        string $nombre,
        string $apellido,
        ?string $correo,
        ?string $telefono,
        ?string $fecha_inicio,
        ?string $fecha_final,
        ?string $motivo_fin
    ): void {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id = (int)$id_director;

            $conn->begin_transaction();
            $modelo = new Director($conn);

            $verificacion = $modelo->verificarDirectorOtroId($id, $correo);
            if ($verificacion['activo'] > 0 || $verificacion['desactivado'] > 0) {
                throw new Exception("error_duplicado");
            }

            $modelo->editarDirector(
                (int)$id_grado,
                $nombre,
                $apellido,
                $correo ?: null,
                $telefono ?: null,
                $id,
                $fecha_inicio ?: null,
                $fecha_final ?: null,
                $motivo_fin ?: null
            );

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

    public function reactivar(string $rol, mixed $id_director): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            $conn->begin_transaction();
            $modelo = new Director($conn);
            $modelo->bloquearTabla();
            $modelo->obtenerPorId((int)$id_director, true);
            $modelo->reactivar((int)$id_director);

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

    public function eliminar(string $rol, mixed $id_director): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            if (!$id_director) throw new Exception("ID inválido");

            $conn->begin_transaction();
            $modelo = new Director($conn);

            if (!$modelo->obtenerPorId((int)$id_director)) {
                throw new Exception("Director no encontrado");
            }

            $filas = $modelo->eliminarDirector((int)$id_director);
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

    // ─
    //  LÍNEA DE TIEMPO
    // ─

    public function infoLineaTiempo(mixed $id_director)
    {
        global $conn;
        try {
            if (!$id_director) {
                return [
                    "datos"      => [],
                    "paginacion" => ["total" => 0, "por_pagina" => 5, "pagina" => 1, "total_paginas" => 1],
                ];
            }
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            return (new Director($conn))->lineaTiempoDirector((int)$id_director, $pagina);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar');
        }
    }

    // ─
    //  UI HELPERS
    // ─

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Nombre', 'Apellidos', 'Correo', 'Teléfono', 'Grado Académico', 'Estado', 'Acciones'];
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

    public function EstiloTimeLine(string $tipo): string
    {
        return match (strtoupper($tipo)) {
            'CREACION'     => 'success',
            'ACTUALIZACION' => 'primary',
            'BAJA'         => 'danger',
            default        => 'secondary',
        };
    }

    // ─ Botones tabla principal ─

    private function obtenerbotones(string $tipo, int $id): string
    {
        return match ($tipo) {
            'Editar' =>
                '<a href="editar.php?id_director=' . $id . '" class="btn btn-sm btn-warning"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Editar director">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-pencil-square" viewBox="0 0 16 16">
                      <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                      <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>',
            'Detalles' =>
                '<a href="detalles.php?id_director=' . $id . '" class="btn btn-sm btn-info"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Ver detalles del director">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                         class="bi bi-eye-fill" viewBox="0 0 16 16">
                      <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                      <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>',
            'Desactivar' =>
                '<a href="index.php?id_director=' . $id . '&action=desactivar_director"
                    class="btn btn-sm btn-danger"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip" data-bs-title="Desactivar director">
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
            'Desactivar' => '<button type="submit" name="action" value="Desactivar" class="btn btn-sm btn-danger">Desactivar</button>',
            'Reactivar'  => '<button type="submit" name="action" value="Reactivar"  class="btn btn-sm btn-warning">Reactivar</button>',
            'Guardar'    => '<button type="submit" name="action" value="Guardar"    class="btn btn-sm btn-guardar">Guardar cambios</button>',
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
