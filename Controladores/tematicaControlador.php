<?php
// Controladores/tematicaControlador.php

require_once __DIR__ . '/../Modelos/tematica.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class tematicaControlador extends BaseControlador
{

    // 
    // DATOS PARA TABLA E INDEX
    // 

    public function index(string $rol, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $resultado = (new Tematica($conn))->obtenerTematicas($rol, $buscar);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexEditar(string $rol, int $id_tematica): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Tematica($conn))->obtenerTematicasEditar($id_tematica);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function indexDetalles(string $rol, int $id_tematica): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            return (new Tematica($conn))->obtenerTematicasDetalles($id_tematica);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // 
    // FILTROS DE TABLA
    // 

    private function obtenerPorFiltro(string $rol, int $filtro, ?string $buscar = null): array
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $resultado = (new Tematica($conn))->obtenerTematicasTablaFiltro($filtro, $rol, $buscar);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 2 = Total (sin filtro de estado)
    public function Total(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    // 1 = Activos
    public function Activo(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    // 0 = Desactivados
    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }


    // 
    // ENCABEZADOS Y OPCIONES
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];

        return [
            'Temática',
            'Descripción',
            'Subtemáticas',
            'Estado',
            'Creación',
            'Modificación',
            'Acciones',
        ];
    }

    public function opciones(): array
    {
        return [
            'Total'       => 'Total',
            'Activo'      => 'Activos',
            'Desactivado' => 'Desactivados',
        ];
    }


    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstadoLista(string $estado): string
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

    public function obtenerbotones(string $tipo, ?int $id1 = null): string
    {
        return match ($tipo) {
            'Editar Tematica' =>
                '<a href="editar.php?id_tematica=' . $id1 . '" type="button"
                    class="btn btn-warning btn-sm"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Editar temática">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>',

            'Detalles' =>
                '<a href="detalles.php?id_tematica=' . $id1 . '" type="button"
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Ver detalles de la temática">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>',

            'Desactivar' =>
                '<a href="index.php?id_tematica=' . $id1 . '&action=desactivar_tematica" type="button"
                    class="btn btn-danger btn-sm"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-custom-class="custom-tooltip"
                    data-bs-title="Desactivar temática">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>',

            default => '',
        };
    }

    public function botonesAccionPrincipal(int $id, string $rol, ?string $estado = null): string
    {
        if (!$this->esSupervisor($rol)) return '';

        return match ($estado) {
            'Activo'      => $this->obtenerbotones('Editar Tematica', $id)
                           . $this->obtenerbotones('Detalles', $id)
                           . $this->obtenerbotones('Desactivar', $id),
            'Desactivado' => $this->obtenerbotones('Detalles', $id),
            default       => '',
        };
    }


    // 
    // CREAR TEMÁTICA
    // Acción POST → redirige con msg.
    // 

    public function registrarTematica(string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $nombre      = $this->limpiar($_POST['NombreTematica'] ?? '');
            $descripcion = $this->limpiar($_POST['Descripcion']    ?? '');
            $subtematicas = $_POST['subtematicas'] ?? [];

            if (empty($nombre) || empty($descripcion)) {
                throw new Exception('error_crear');
            }

            $conn->begin_transaction();
            $tematica    = new Tematica($conn);
            $id_tematica = $tematica->registrarTematica($nombre, $descripcion);

            if (!$id_tematica) {
                throw new Exception('error_crear');
            }

            foreach ($subtematicas as $sub) {
                $nombreSub = $this->limpiar($sub['nombre'] ?? '');
                if (!empty($nombreSub)) {
                    $tematica->registrarsubtematica($id_tematica, $nombreSub);
                }
            }

            $conn->commit();
            $this->redirigir('exito_crear', 'index.php');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['error_crear', 'accion_no_permitida'])
                ? $e->getMessage()
                : 'error_crear';
            $this->redirigir($msg, 'crear.php');
        }
    }


    // 
    // EDITAR TEMÁTICA Y SUBTEMÁTICAS
    // Acción POST → redirige con msg.
    // 

    public function editarTematica(string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_tematica  = (int)($_POST['id_tematica'] ?? 0);
            $nombre       = $this->limpiar($_POST['NombreTematica'] ?? '');
            $descripcion  = $this->limpiar($_POST['Descripcion']    ?? '');
            $estado       = (int)($_POST['Estado'] ?? 1);
            $subtematicas = $_POST['subtematicas'] ?? [];

            if (!$id_tematica || empty($nombre) || empty($descripcion)) {
                throw new Exception('error_editar');
            }

            $conn->begin_transaction();
            $tematica = new Tematica($conn);

            // Actualizar datos principales
            $tematica->editarTematica($nombre, $descripcion, $id_tematica);

            // Sincronizar subtemáticas
            $ids_bd   = $tematica->obtenerIdsSubtematicas($id_tematica);
            $ids_form = [];

            foreach ($subtematicas as $sub) {
                $id_sub    = $sub['id']     ?? null;
                $nombreSub = $this->limpiar($sub['nombre'] ?? '');

                if (empty($nombreSub)) continue;

                // Verifica duplicidad (lanza Exception si hay duplicado)
                $tematica->comparar_Duplicidad_Subtematica($id_tematica, $nombreSub, $id_sub);

                if ($id_sub === 'nuevo' || empty($id_sub)) {
                    $tematica->registrarsubtematica($id_tematica, $nombreSub);
                } else {
                    $tematica->editarSubtematica((int)$id_sub, $nombreSub);
                    $ids_form[] = (int)$id_sub;
                }
            }

            // Eliminar subtemáticas que ya no están en el formulario
            foreach (array_diff($ids_bd, $ids_form) as $id_eliminar) {
                $tematica->eliminar_subtematica((int)$id_eliminar, 0);
            }

            // Desactivar temática completa si se marcó estado = 0
            if ($estado === 0) {
                $tematica->eliminar_tematica($id_tematica, 0);
            }

            $conn->commit();
            $this->redirigir('exito_editar', 'index.php');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['error_editar', 'accion_no_permitida'])
                ? $e->getMessage()
                : 'error_editar';
            $this->redirigir($msg, 'index.php');
        }
    }


    // 
    // DESACTIVAR TEMÁTICA
    // Acción GET desde enlace → redirige con msg.
    // 

    public function desactivarTematica(int $id_tematica, string $rol): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            if ($id_tematica <= 0) {
                throw new Exception('error_estado');
            }

            $conn->begin_transaction();
            (new Tematica($conn))->eliminar_tematica($id_tematica, 0);
            $conn->commit();

            $this->redirigir('exito_estado', 'index.php');

        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $msg = in_array($e->getMessage(), ['accion_no_permitida', 'error_estado'])
                ? $e->getMessage()
                : 'error_estado';
            $this->redirigir($msg, 'index.php');
        }
    }
}