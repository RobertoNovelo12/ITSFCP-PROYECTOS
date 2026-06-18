<?php
// Controladores/tematicaControlador.php

require_once __DIR__ . '/../Model/tematica_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';
include __DIR__ . '/../../../public/incluido/_botones.php';

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
        include __DIR__ . '/../../../public/incluido/_iconos.php';

        return match ($tipo) {

            'Editar Tematica' => Botones::botonIcono(
                'editar.php?id_tematica=' . $id1,
                'warning',
                $iconos['tabla']['editar'],
                'Editar temática'
            ),

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_tematica=' . $id1,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles de la temática'
            ),

            'Desactivar' => Botones::botonIcono(
                'index.php?id_tematica=' . $id1 . '&action=desactivar_tematica',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar temática'
            ),

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

     // ─ Botones formulario editar ─

    private function obtenerbotonesEditar(string $tipo): string
    {
        include __DIR__ . '/../../../public/incluido/_iconos.php';

        return match ($tipo) {
            'Desactivar' => Botones::botonData(
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Desactivar Temática',
                ['accion' => 'Desactivar'],
                'sm',
                'Desactivar'
            ),
            'Reactivar' => Botones::botonData(
                'warning',
                $iconos['tabla']['reactivar'],
                'Reactivar Temática',
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
