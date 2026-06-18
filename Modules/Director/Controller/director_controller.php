<?php
// Controladores/directorControlador.php

require_once __DIR__ . '/../Model/director_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/BaseControlador.php';
include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_botones.php';

class directorControlador extends BaseControlador
{

    // 
    //  LISTADO / FILTROS
    // 

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

    // 
    //  DETALLE / EDICIÓN (solo lectura)
    // 

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

    // 
    //  CRUD — redirigen con msg
    // 

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

    // 
    //  LÍNEA DE TIEMPO
    // 

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

    // 
    //  UI HELPERS
    // 

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

    //  Botones tabla principal 

    private function obtenerbotones(string $tipo, int $id): string
    {
        include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_iconos.php';

        return match ($tipo) {

            'Editar' => Botones::botonIcono(
                'editar.php?id_director=' . $id,
                'warning',
                $iconos['tabla']['editar'],
                'Editar director'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_director=' . $id,
                'info',
                $iconos['tabla']['ver'],
                'Ver detalles del director'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?id_director=' . $id . '&action=desactivar_director',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar director'
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
        include $_SERVER['DOCUMENT_ROOT'] . '/public/incluido/_iconos.php';

        return match ($tipo) {
            'Desactivar' => Botones::botonData(
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar director',
                ['accion' => 'Desactivar'],
                'sm',
                'Desactivar'
            ),
            'Reactivar' => Botones::botonData(
                'warning',
                $iconos['tabla']['reactivar'],
                'Reactivar director',
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
