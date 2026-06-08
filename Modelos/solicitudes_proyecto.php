<?php
// Modelos/SolicitudesProyecto.php

require_once __DIR__ . '/../Repositorios/SolicitudesProyectoRepositorio.php';

/**
 * SolicitudesProyecto (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de
 * solicitudes de creación/cierre de proyectos.
 * Delega toda ejecución SQL a SolicitudesProyectoRepositorio.
 *
 */
class SolicitudesProyecto
{
    private SolicitudesProyectoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudesProyectoRepositorio($conn);
    }


    // 
    // MANTENIMIENTO AUTOMÁTICO
    // 

    public function actualizarProyectosVencidos(): bool
    {
        return $this->repo->actualizarProyectosVencidos();
    }


    // 
    // CAMBIOS DE ESTADO
    // 

    /**
     *   2 → Activo:          crea seguimiento + tareas
     *   5 → Por cerrar:      inserta registro en tbl_cierres
     *   1 → Cierre aprobado: aprueba cierre y concluye estudiantes
     *
 
     */
    public function actualizarestado(
        int    $id_proyectos,
        int    $numeroEstado,
        ?float $porcentaje     = null,
        ?int   $id_investigador = null
    ): void {
        $this->repo->actualizarEstado($id_proyectos, $numeroEstado);

        if ($numeroEstado === 2) {
            $plantillaRep   = $this->repo->obtenerPlantillaReporte();
            $id_doc_reporte = $plantillaRep['id_documento'] ?? null;

            $id_avances       = $this->repo->insertarSeguimiento($id_proyectos);
            $tipos_tarea      = $this->repo->obtenerTiposTarea();
            $estadoSinActivar = 4;

            foreach ($tipos_tarea as $row) {
                $id_tipo = (int)$row['id_tareatipo'];
                if ($id_tipo === 12 && $id_doc_reporte !== null) {
                    $this->repo->insertarTareaConDocumento($id_avances, $id_tipo, $estadoSinActivar, $id_doc_reporte);
                } else {
                    $this->repo->insertarTarea($id_avances, $id_tipo, $estadoSinActivar);
                }
            }

        } elseif ($numeroEstado === 5) {
            // Insertar registro de cierre. Se usa el id_investigador recibido;
            // si no viene, se busca en la tabla de proyectos (compatibilidad).
            if ($id_investigador === null) {
                $row = $this->repo->obtenerInvestigadorDeProyecto($id_proyectos);
                $id_investigador = $row['id_investigador'] ?? null;
            }
            if ($id_investigador !== null) {
                $this->repo->insertarCierre($id_proyectos, $id_investigador, $porcentaje);
            }

        } elseif ($numeroEstado === 1) {
            $row = $this->repo->obtenerInvestigadorDeProyecto($id_proyectos);
            if ($row) {
                $this->repo->aprobarCierre($id_proyectos);
            }
        }
    }

    /**
     * Rechaza un proyecto (creación o cierre) e inserta el comentario de rechazo.
     */
    public function actualizarEstadoProyectoRechazo(
        int    $id_usuario,
        int    $id_proyectos,
        string $tipo,
        string $comentario
    ): void {
        $this->repo->rechazarConComentario($id_usuario, $id_proyectos, $tipo, $comentario);
    }

    /**
     * Reenvía un cierre rechazado (estado 7 → 5).
     * Solo el investigador dueño del proyecto puede ejecutarla.
     * Retorna true si el cambio se realizó, false si no procede.
     */
    public function reenviarCierre(int $id_proyectos, int $id_investigador): bool
    {
        return $this->repo->reenviarCierre($id_proyectos, $id_investigador);
    }


    // 
    // PORCENTAJE DE AVANCE
    // 

    public function obtenerTareasAvance(int $id_proyecto): float
    {
        $tareas      = $this->repo->obtenerTareasAprobadas($id_proyecto);
        $totalTareas = 11;
        $suma        = array_sum(array_map(
            fn($row) => match ((int)$row['id_estadoT']) {
                5       => 100,
                2, 3    => 50,
                default => 0,
            },
            $tareas
        ));
        return round(min(100, ($suma / $totalTareas) * 100), 2);
    }


    // 
    // RESUMEN (dashboard)
    // 

    public function resumenSolicitudes(string $rol, int $id_usuario, int $id_periodo = 0): array
    {
        $periodo = $id_periodo ?: null;

        if ($rol === 'supervisor') {
            return $this->repo->resumenSolicitudesSupervisor($periodo);
        }

        // investigador / profesor
        return $this->repo->resumenSolicitudesInvestigador($id_usuario, $periodo);
    }


    // 
    // LISTADO PAGINADO
    // 

    /**
     * Lista solicitudes paginadas según rol y filtros.
     *
     * Filtros para investigador/profesor:
     *   Todas           → estados (3, 4, 5, 7, 1, 2)
     *   Creacion        → estados (3, 4)
     *   Cierre          → estados (5, 7, 1)
     *   Pendientes      → estados (3, 5)
     *   RequierenAccion → estados (4, 7)   ← nuevo
     *
     * Para supervisor los mismos filtros sin RequierenAccion.
     */
    public function listarSolicitudes(
        string $rol,
        int    $id_usuario,
        string $tipo_filtro = 'Todas',
        string $buscar      = '',
        int    $pagina      = 1,
        int    $id_periodo  = 0
    ): array {
        $por_pagina = 6;
        $pagina     = max(1, $pagina);
        $desde      = ($pagina - 1) * $por_pagina;

        $estados = match ($tipo_filtro) {
            'Creacion'        => [3, 4],
            'Cierre'          => [5, 7, 1],
            'Pendientes'      => [3, 5],
            'RequierenAccion' => [4, 7],
            default           => [3, 4, 5, 7, 1, 2],
        };

        $in_estados = implode(',', $estados);
        $where_rol  = in_array($rol, ['investigador', 'profesor'], true)
            ? ' AND proy.id_investigador = ?'
            : '';
        $base_where = "proy.id_estadoP IN ($in_estados) $where_rol";

        $bind_params = [];
        $bind_types  = '';

        if (in_array($rol, ['investigador', 'profesor'], true)) {
            $bind_params[] = $id_usuario;
            $bind_types   .= 'i';
        }
        if ($id_periodo) {
            $base_where   .= ' AND proy.id_periodos = ?';
            $bind_params[] = $id_periodo;
            $bind_types   .= 'i';
        }
        if (!empty($buscar)) {
            $base_where   .= ' AND proy.titulo LIKE ?';
            $bind_params[] = "%$buscar%";
            $bind_types   .= 's';
        }

        $total         = $this->repo->contarSolicitudes($in_estados, $base_where, $bind_types, $bind_params);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $params = array_merge($bind_params, [$desde, $por_pagina]);
        $types  = $bind_types . 'ii';

        $solicitudes = $this->repo->listarSolicitudes($base_where, $types, $params);

        return [
            'solicitudes' => $solicitudes,
            'paginacion'  => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }


    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos(): array
    {
        return $this->repo->obtenerTodosPeriodos();
    }


    // 
    // DETALLE DE SOLICITUD
    // 

    public function obtenerProyecto(int $id_proyecto): ?array
    {
        return $this->repo->obtenerProyecto($id_proyecto);
    }

    public function obtenerProyectoInvestigador(int $id_proyecto): ?array
    {
        return $this->repo->obtenerProyectoInvestigador($id_proyecto);
    }

    public function obtenerUsuarioArea(?int $id_usuario): ?array
    {
        return $this->repo->obtenerUsuarioArea($id_usuario);
    }

    public function obtenerInvestigadorLinea(int $id_proyecto): ?array
    {
        return $this->repo->obtenerInvestigadorLinea($id_proyecto);
    }

    public function obtenersubtematicasProyecto(int $id_proyecto): array
    {
        return $this->repo->obtenerSubtematicasProyecto($id_proyecto);
    }

    public function obtenerProyectoComentarios(int $id_proyecto): array
    {
        return $this->repo->obtenerProyectoComentarios($id_proyecto);
    }

    public function estudiantes(int $id_proyecto): array
    {
        return $this->repo->obtenerEstudiantes($id_proyecto);
    }
}
