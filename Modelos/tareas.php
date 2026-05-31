<?php
// Modelos/Tarea.php

require_once __DIR__ . '/../Repositorios/TareaRepositorio.php';

/**
 * Tarea (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de tareas.
 * Delega toda ejecución SQL a TareaRepositorio.
 */
class Tarea
{
    private TareaRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new TareaRepositorio($conn);
    }


    // ─
    // MANTENIMIENTO AUTOMÁTICO
    // ─

    public function actualizarTareasVencidos(): void
    {
        $hoy      = date('Y-m-d');
        $vencidos = $this->repo->obtenerAsignacionesVencidas($hoy);
        if (empty($vencidos)) return;

        $this->repo->marcarVencidas($hoy);
        $this->repo->insertarHistorialVencido($vencidos);
    }

    /**
     * Marca tareas como concluidas (estado 9) cuando todos los alumnos
     * activos del proyecto tienen su asignación aprobada (estado 5).
     */
    public function actualizarTareasConcluidas(?int $id_tarea = null): void
    {
        $tareas = $this->repo->obtenerTareasActivas($id_tarea);
        if (empty($tareas)) return;
        $this->repo->procesarConclusiones($tareas);
    }


    // ─
    // OBTENER TAREAS (tabla principal index)
    // ─

    public function obtenerTareas(int $id_proyecto, int $id_usuario, string $rol): array
    {
        return match (strtolower($rol)) {
            'estudiante'             => $this->repo->obtenerTareasEstudiante($id_proyecto, $id_usuario),
            'investigador', 'profesor' => $this->repo->obtenerTareasInvestigador($id_proyecto, $id_usuario),
            'supervisor'             => $this->repo->obtenerTareasSupervisor($id_proyecto),
            default                  => [],
        };
    }


    // ─
    // LISTA DE ASIGNACIONES POR TAREA
    // ─

    public function obtenerTareasLista(int $id_tarea, string $rol, int $id_usuario): array
    {
        return match (strtolower($rol)) {
            'investigador', 'profesor' => $this->repo->obtenerListaInvestigador($id_tarea, $id_usuario),
            'supervisor'               => $this->repo->obtenerListaSupervisor($id_tarea),
            default                    => [],
        };
    }


    // ─
    // TAREAS DEL ESTUDIANTE (vista estudiante)
    // ─

    public function obtenerTareasEstudiante(int $id_usuario, int $id_proyectos): array
    {
        return $this->repo->obtenerTareasListaEstudiante($id_usuario, $id_proyectos);
    }


    // ─
    // REGISTRAR DOCUMENTO
    // ─

    public function registrarDocumento(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        string  $tipo,
        string  $visibilidad,
        int     $id_usuario,
        int     $id_proyecto  = 0,
        ?int    $etapa        = null,
        int     $version      = 1
    ): int {
        $id_proyecto = $id_proyecto ?: null;
        return $this->repo->registrarDocumento(
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $tipo,
            $visibilidad,
            $id_usuario,
            $id_proyecto,
            $etapa,
            $version
        );
    }


    // ─
    // EDITAR TAREA GENERAL (investigador)
    // ─

    public function editarTareaGeneral(
        int     $id_tarea,
        string  $descripcion,
        string  $instrucciones,
        string  $fecha_entrega,
        ?int    $id_documento_recurso,
        int     $id_usuario
    ): void {
        $actual       = $this->repo->obtenerDatosTareaActual($id_tarea);
        $primeraAsig  = $this->repo->obtenerPrimeraAsignacion($id_tarea);

        $this->repo->actualizarTareaGeneral($id_tarea, $descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso);

        if (!$primeraAsig) return;

        $cambios = [];

        $campos = [
            'descripcion'   => $descripcion,
            'instrucciones' => $instrucciones,
            'fecha_entrega' => $fecha_entrega
        ];

        foreach ($campos as $campo => $nuevoValor) {
            if ($actual[$campo] !== $nuevoValor) {
                $cambios[] = [
                    'campo_modificado' => $campo,
                    'valor_anterior'   => $actual[$campo],
                    'valor_nuevo'      => $nuevoValor
                ];
            }
        }

        if ($id_documento_recurso !== null) {
            $cambios[] = [
                'campo_modificado' => 'archivo_guia',
                'valor_anterior'   => null,
                'valor_nuevo'      => 'Nuevo archivo subido'
            ];
        }
        if (empty($cambios)) return;

        $todasAsig = $this->repo->obtenerTodasAsignaciones($id_tarea);
        $this->repo->insertarHistorialEdicion($todasAsig, $cambios, $id_usuario);
    }


    // ─
    // EDITAR TAREA ESTUDIANTE
    // ─

    public function editarTareaEstudiante(
        int    $id_asignacion,
        int    $id_tarea,
        string $contenido,
        string $comentarios,
        ?int   $id_documento_entrega
    ): bool {
        $this->repo->actualizarAsignacion($id_asignacion, $id_tarea, $contenido, $id_documento_entrega);
        return true;
    }


    // ─
    // GUARDAR BORRADOR
    // ─

    public function guardar_borrador(
        int    $id_tarea,
        int    $id_asignacion,
        int    $id_usuarios,
        string $contenido,
        string $comentarios          = '',
        ?int   $id_documento_entrega = null
    ): void {
        $this->repo->guardarBorrador($id_tarea, $id_asignacion, $id_usuarios, $contenido, $id_documento_entrega);
    }


    // ─
    // ACTUALIZAR ESTADO
    // ─

    public function actualizarestado(
        int    $id_tarea,
        int    $numeroEstado,
        int    $id_proyectos,
        int    $id_asignacion,
        int    $id_usuarios,
        string $comentario,
        int $estado_anterior = 0
    ): void {
        if ($numeroEstado === 0) {
            error_log("actualizarestado (modelo): numeroEstado=0, se ignora.");
            return;
        }

        // ESTADO 1 — ACTIVAR TAREA
        if ($numeroEstado === 1 && $estado_anterior === 6) {
            $this->repo->actualizarEstadoTarea($id_tarea, $numeroEstado);

            return;
        }
        if ($numeroEstado === 1) {
            $this->repo->actualizarEstadoTarea($id_tarea, $numeroEstado);

            $proy = $this->repo->obtenerProyectoDeTarea($id_tarea);
            if (!$proy) {
                error_log("actualizarestado: no se encontró proyecto para id_tarea={$id_tarea}");
                return;
            }

            $id_proyectos = (int)$proy['id_proyectos'];
            $id_tarea     = (int)$proy['id_tarea'];

            $alumnos = $this->repo->obtenerAlumnosActivosProyecto($id_proyectos);
            $this->repo->activarTareaParaAlumnos($id_tarea, $alumnos, $id_usuarios);
            return;
        }

        // ESTADOS 2 (Revisar), 3 (Corregir), 5 (Aprobado)
        if (!in_array($numeroEstado, [2, 3, 5], true)) {
            error_log("actualizarestado (modelo): estado no válido recibido: {$numeroEstado}");
            return;
        }

        if ($id_asignacion !== 0) {
            $this->repo->actualizarEstadoAsignacion($id_asignacion, $numeroEstado);
            $this->repo->insertarHistorialEstado($id_asignacion, $numeroEstado, $id_usuarios, $comentario);
        } else {
            $this->repo->actualizarEstadoAsignacionesDeTarea($id_tarea, $numeroEstado);
        }
    }


    // ─
    // OBTENER TAREA ALUMNO
    // ─

    public function obtenerTareaAlumno(int $id_asignacion, int $id_usuario, int $id_proyecto, string $rol): ?array
    {
        return match (strtolower($rol)) {
            'estudiante'               => $this->repo->obtenerTareaAlumnoEstudiante($id_asignacion, $id_usuario, $id_proyecto),
            'investigador', 'profesor' => $this->repo->obtenerTareaAlumnoInvestigador($id_asignacion, $id_usuario, $id_proyecto),
            'supervisor'               => $this->repo->obtenerTareaAlumnoSupervisor($id_asignacion, $id_proyecto),
            default                    => null,
        };
    }

    public function VerificarTareaProyecto(int $id_asignacion, int $id_usuario, int $id_proyecto, string $rol): ?array
    {
        return match (strtolower($rol)) {
            'estudiante'               => $this->repo->verificarTareaEstudiante($id_asignacion, $id_usuario, $id_proyecto),
            'investigador', 'profesor' => $this->repo->verificarTareaInvestigador($id_asignacion, $id_usuario, $id_proyecto),
            default                    => null,
        };
    }


    // ─
    // OBTENER TAREA GENERAL
    // ─

    public function obtenerTareaGeneral(int $id_tarea, string $rol, int $id_usuario): ?array
    {
        return match (strtolower($rol)) {
            'estudiante'               => $this->repo->obtenerTareaGeneralEstudiante($id_tarea, $id_usuario),
            'investigador', 'profesor' => $this->repo->obtenerTareaGeneralInvestigador($id_tarea, $id_usuario),
            'supervisor'               => $this->repo->obtenerTareaGeneralSupervisor($id_tarea),
            default                    => null,
        };
    }


    // ─
    // DESCARGAS
    // ─

    public function obtenerTareaPorId(int $id_asignacion): ?array
    {
        return $this->repo->obtenerDocumentoEntrega($id_asignacion);
    }

    public function obtenerTareaGuiaPorId(int $id_tarea): ?array
    {
        return $this->repo->obtenerDocumentoGuia($id_tarea);
    }

    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->repo->obtenerPlantillaPorId($id_plantilla);
    }


    // ─
    // HISTORIAL Y LÍNEA DE TIEMPO
    // ─

    public function obtenerEdicionesRecientes(int $id_tarea, int $limite = 5): array
    {
        return $this->repo->obtenerEdicionesRecientes($id_tarea, $limite);
    }

    public function linea_tiempo_tarea(int $id_asignacion, int $pagina = 1, int $por_pagina = 6): array
    {
        $pagina        = max(1, $pagina);
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarHistorialEstado($id_asignacion);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->repo->obtenerHistorialEstado($id_asignacion, $desde, $por_pagina);

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date('d/m/Y', strtotime($item['fecha']))][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => compact('total', 'por_pagina', 'pagina', 'total_paginas'),
        ];
    }
}
