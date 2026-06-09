<?php
// Modelos/Proyectos.php

require_once __DIR__ . '/../Repositorios/ProyectosRepositorio.php';

/**
 * Proyectos (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de proyectos.
 * Delega toda ejecución SQL a ProyectosRepositorio.
 *
 * La construcción de SQL dinámico por rol permanece aquí como lógica de dominio.
 */
class Proyectos
{
    private ProyectosRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new ProyectosRepositorio($conn);
    }


    // 
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // 

    public function actualizarProyectosVencidos(): bool
    {
        return $this->repo->marcarProyectosVencidos();
    }

    public function actualizarEstadoEstudiantesVencidos(): bool
    {
        $etapa2_completa = "
            EXISTS (
                SELECT 1
                FROM tareas_usuarios tu2
                JOIN tareas t2           ON t2.id_tarea    = tu2.id_tarea
                JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                WHERE tu2.id_usuarios  = pu.id_usuarios
                  AND ts2.id_proyectos = pu.id_proyectos
            )
            AND NOT EXISTS (
                SELECT 1
                FROM tareas_usuarios tu3
                JOIN tareas t3           ON t3.id_tarea    = tu3.id_tarea
                JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                WHERE tu3.id_usuarios  = pu.id_usuarios
                  AND ts3.id_proyectos = pu.id_proyectos
                  AND tu3.id_estadoT  <> 5
            )
        ";

        $this->repo->insertarHistorialVencidoCompleto($etapa2_completa);
        $this->repo->insertarHistorialBajaVencido($etapa2_completa);

        return $this->repo->darDeBajaEstudiantesVencidos($etapa2_completa);
    }



    /**
     * Punto de entrada público.
     * Se añadió $estado para pasarlo a cada método específico.
     */
    public function obtenerProyectosTablaFiltro(
        int     $id,
        ?int    $filtro,
        string  $rol,
        ?string $buscar,
        int     $id_periodo = 0,
        ?string $estado     = null   // Para cuando el estudinante terminó un proyecto y quiere ver su historial (estado = 'concluido')
    ): array {
        return match (strtolower($rol)) {
            'estudiante'               => $this->obtenerProyectosTablaEstudiante($id, $filtro, $buscar, $estado),
            'investigador', 'profesor' => $this->obtenerProyectosTablaInvestigador($id, $filtro, $buscar, $estado),
            'supervisor'               => $this->obtenerProyectosTablaSupervisor($filtro, $buscar, $id_periodo, $estado),
            default                    => [],
        };
    }

    /**
     * Listado de proyectos para el rol ESTUDIANTE.
     *
     * Cambios respecto a la versión original:
     *  - Se añadió el parámetro $estado.
     *  - Si $estado NO es nulo, se filtra pu.estado = $estado
     *    en lugar del valor fijo 'activo'.
     *  - Si $estado es nulo, se mantiene el comportamiento
     *    original (pu.estado = 'activo').
     */
    private function obtenerProyectosTablaEstudiante(
        int     $id,
        ?int    $filtro,
        ?string $buscar,
        ?string $estado = null   // <-- NUEVO parámetro
    ): array {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->contarEstudiante($id, $filtro, $buscar, $estado);
        $total_paginas = (int)ceil($total / $por_pagina);

        // Si viene un estado explícito (ej. 'concluido') se usa ese valor;
        // de lo contrario se usa 'activo' (comportamiento original).
        $estado_pu = $estado ?? 'activo';

        $sql = '
        SELECT
            proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin,
            espr.nombre AS estado_proyecto, peri.periodo,
            pu.estado   AS estado_estudiante,
            COALESCE(tr.total, 0) AS total
        FROM proyectos proy
        JOIN proyectos_usuarios pu
            ON pu.id_proyectos = proy.id_proyectos
           AND pu.id_usuarios  = ?
           AND pu.estado       = ?
        JOIN estados_proyectos espr ON proy.id_estadoP  = espr.id_estadoP
        JOIN periodos peri          ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos,
                   COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t                ON t.id_avances  = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea   = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        WHERE 1 = 1
    ';

        $params = [$id, $estado_pu];
        $types  = 'is';

        if ($filtro) {
            $sql     .= ' AND proy.id_estadoP = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }
        if (!empty($buscar)) {
            $sql     .= ' AND proy.titulo LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql     .= ' ORDER BY proy.id_proyectos DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return [
            'proyectos'  => $this->repo->ejecutarListado($sql, $types, $params),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }
 
// 

    /**
     * Listado de proyectos para el rol INVESTIGADOR / PROFESOR.
     *
     * Cambios respecto a la versión original:
     *  - Se añadió el parámetro $estado.
     *  - Si $estado es 'concluido', se añade un JOIN a
     *    proyectos_usuarios para filtrar proyectos en los que
     *    al menos un usuario tiene ese estado, y se eliminan
     *    las restricciones NOT IN (3,4,7) para que aparezcan
     *    proyectos en cualquier estado de proyecto.
     *  - Si $estado es nulo, comportamiento original intacto.
     */
    private function obtenerProyectosTablaInvestigador(
        int     $id,
        ?int    $filtro,
        ?string $buscar,
        ?string $estado = null   // <-- NUEVO parámetro
    ): array {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->contarInvestigador($id, $filtro, $buscar, $estado);
        $total_paginas = (int)ceil($total / $por_pagina);

        $modoHistorial = ($estado === 'concluido');

        $sql = "
        SELECT
            proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin,
            espr.nombre AS estado_proyecto, peri.periodo,
            COALESCE(tr.total, 0) AS total,
            CASE
                WHEN COALESCE(total_est.total_estudiantes, 0) = 0       THEN 0
                WHEN COALESCE(activos_bloqueados.total, 0)   > 0        THEN 0
                WHEN COALESCE(activos_completos.total, 0)    > 0        THEN 1
                WHEN COALESCE(total_activos.total, 0)        = 0        THEN 1
                ELSE 0
            END AS puede_cerrar
        FROM proyectos proy
        JOIN estados_proyectos espr ON proy.id_estadoP  = espr.id_estadoP
        JOIN periodos peri           ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos,
                   COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t                ON t.id_avances = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea  = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT id_proyectos, COUNT(*) AS total_estudiantes
            FROM proyectos_usuarios GROUP BY id_proyectos
        ) total_est ON total_est.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT id_proyectos, COUNT(*) AS total
            FROM proyectos_usuarios WHERE estado = 'activo' GROUP BY id_proyectos
        ) total_activos ON total_activos.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT pu.id_proyectos, COUNT(DISTINCT pu.id_usuarios) AS total
            FROM proyectos_usuarios pu
            WHERE pu.estado = 'activo'
              AND EXISTS     (SELECT 1 FROM tareas_usuarios tu2 WHERE tu2.id_usuarios = pu.id_usuarios)
              AND NOT EXISTS (SELECT 1 FROM tareas_usuarios tu3
                              WHERE tu3.id_usuarios = pu.id_usuarios AND tu3.id_estadoT <> 5)
            GROUP BY pu.id_proyectos
        ) activos_completos ON activos_completos.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT pu.id_proyectos, COUNT(DISTINCT pu.id_usuarios) AS total
            FROM proyectos_usuarios pu
            WHERE pu.estado = 'activo'
              AND (NOT EXISTS (SELECT 1 FROM tareas_usuarios tu4 WHERE tu4.id_usuarios = pu.id_usuarios)
                   OR  EXISTS (SELECT 1 FROM tareas_usuarios tu5
                               WHERE tu5.id_usuarios = pu.id_usuarios AND tu5.id_estadoT <> 5))
            GROUP BY pu.id_proyectos
        ) activos_bloqueados ON activos_bloqueados.id_proyectos = proy.id_proyectos
        WHERE proy.id_investigador = ?
    ";

        $params = [$id];
        $types  = 'i';

        if ($modoHistorial) {
            // Modo historial: proyectos que tienen al menos un usuario concluido.
            // No se excluyen estados del proyecto para mostrar el historial completo.
            $sql     .= ' AND EXISTS (
                          SELECT 1 FROM proyectos_usuarios pu_h
                          WHERE pu_h.id_proyectos = proy.id_proyectos
                            AND pu_h.estado       = ?
                      )';
            $params[] = $estado;   // 'concluido'
            $types   .= 's';
        } else {
            // Comportamiento original: excluir Por aprobar(3), Rechazado(4) y Cierre rechazado(7)
            $sql .= ' AND proy.id_estadoP NOT IN (3, 4, 7)';
        }

        if ($filtro) {
            $sql     .= ' AND proy.id_estadoP = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }
        if (!empty($buscar)) {
            $sql     .= ' AND proy.titulo LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql     .= ' ORDER BY proy.id_proyectos DESC LIMIT ?, ?';
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= 'ii';

        return [
            'proyectos'  => $this->repo->ejecutarListado($sql, $types, $params),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }
 
// 

    /**
     * Listado de proyectos para el rol SUPERVISOR.
     *
     * Cambios respecto a la versión original:
     *  - Se añadió el parámetro $estado.
     *  - Si $estado es 'concluido', se añade una condición EXISTS
     *    para mostrar proyectos con al menos un usuario concluido,
     *    y se elimina el NOT IN (3,4) para ver el historial completo.
     *  - Si $estado es nulo, comportamiento original intacto.
     */
    private function obtenerProyectosTablaSupervisor(
        ?int    $filtro,
        ?string $buscar,
        int     $id_periodo = 0,
        ?string $estado     = null   // <-- NUEVO parámetro
    ): array {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;

        $modoHistorial = ($estado === 'concluido');

        // ---- Cláusula WHERE base según modo ----
        if ($modoHistorial) {
            $where_base = 'WHERE EXISTS (
                           SELECT 1 FROM proyectos_usuarios pu_h
                           WHERE pu_h.id_proyectos = proy.id_proyectos
                             AND pu_h.estado       = \'concluido\'
                       )';
        } else {
            $where_base = 'WHERE 1 AND proy.id_estadoP NOT IN (3, 4)';
        }

        // ---- Conteo total ----
        $params_total = [];
        $types_total  = '';
        $sql_total    = "SELECT COUNT(*) AS total FROM proyectos proy $where_base";

        if ($id_periodo) {
            $sql_total      .= ' AND proy.id_periodos = ?';
            $params_total[]  = $id_periodo;
            $types_total    .= 'i';
        }
        if ($filtro) {
            $sql_total      .= ' AND proy.id_estadoP = ?';
            $params_total[]  = $filtro;
            $types_total    .= 'i';
        }
        if (!empty($buscar)) {
            $sql_total      .= ' AND proy.titulo LIKE ?';
            $params_total[]  = "%$buscar%";
            $types_total    .= 's';
        }

        $total         = $this->repo->ejecutarConteo($sql_total, $types_total, $params_total);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        // ---- Consulta principal ----
        $sql = '
        SELECT
            proy.id_proyectos, proy.titulo, proy.fecha_inicio, proy.fecha_fin,
            espr.nombre AS estado_proyecto, peri.periodo,
            COALESCE(tr.total, 0) AS total,
            CASE
                WHEN COALESCE(pa.total_alumnos, 0)       > 0
                 AND COALESCE(tt.total_tareas, 0)        >= 11
                 AND COALESCE(tc.tareas_completadas, 0)   = (COALESCE(tt.total_tareas, 0) * COALESCE(pa.total_alumnos, 0))
                THEN 1 ELSE 0
            END AS puede_cerrar
        FROM proyectos proy
        JOIN estados_proyectos espr ON proy.id_estadoP  = espr.id_estadoP
        JOIN periodos peri           ON proy.id_periodos = peri.id_periodos
        LEFT JOIN (
            SELECT ts.id_proyectos,
                   COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
            FROM tbl_seguimiento ts
            JOIN tareas t                ON t.id_avances = ts.id_avances
            LEFT JOIN tareas_usuarios tu ON tu.id_tarea  = t.id_tarea
            GROUP BY ts.id_proyectos
        ) tr ON tr.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT id_proyectos, COUNT(*) total_alumnos
            FROM proyectos_usuarios
            WHERE estado = \'activo\'
            GROUP BY id_proyectos
        ) pa ON pa.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(DISTINCT t.id_tarea) total_tareas
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            GROUP BY ts.id_proyectos
        ) tt ON tt.id_proyectos = proy.id_proyectos
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(*) tareas_completadas
            FROM tbl_seguimiento ts
            JOIN tareas t             ON t.id_avances     = ts.id_avances
            JOIN tareas_usuarios tu   ON tu.id_tarea       = t.id_tarea
            JOIN proyectos_usuarios pu ON pu.id_usuarios  = tu.id_usuarios
            WHERE tu.id_estadoT = 5 AND pu.estado = \'activo\'
            GROUP BY ts.id_proyectos
        ) tc ON tc.id_proyectos = proy.id_proyectos
    ' . $where_base;

        $params = [];
        $types  = '';

        if ($id_periodo) {
            $sql     .= ' AND proy.id_periodos = ?';
            $params[] = (int)$id_periodo;
            $types   .= 'i';
        }
        if ($filtro) {
            $sql     .= ' AND proy.id_estadoP = ?';
            $params[] = (int)$filtro;
            $types   .= 'i';
        }
        if (!empty($buscar)) {
            $sql     .= ' AND proy.titulo LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        $sql     .= ' ORDER BY proy.id_proyectos DESC LIMIT ?, ?';
        $params[] = (int)$desde;
        $params[] = (int)$por_pagina;
        $types   .= 'ii';

        return [
            'proyectos'  => $this->repo->ejecutarListado($sql, $types, $params),
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }

    private function contarEstudiante(
        int     $id,
        ?int    $filtro,
        ?string $buscar,
        ?string $estado = null   // <-- NUEVO parámetro
    ): int {
        $estado_pu = $estado ?? 'activo';

        $sql    = "SELECT COUNT(*) AS total
               FROM proyectos proy
               JOIN proyectos_usuarios pu
                   ON pu.id_proyectos = proy.id_proyectos
                  AND pu.id_usuarios  = ?
                  AND pu.estado       = ?
               WHERE 1 = 1";
        $params = [$id, $estado_pu];
        $types  = 'is';

        if ($filtro) {
            $sql     .= ' AND proy.id_estadoP = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }
        if (!empty($buscar)) {
            $sql     .= ' AND proy.titulo LIKE ?';
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        return $this->repo->ejecutarConteo($sql, $types, $params);
    }

    /**
     * Cuenta proyectos para el rol INVESTIGADOR / PROFESOR.
     *
     * Cambio: si $estado es 'concluido' (modoHistorial) se elimina
     * el NOT IN (3,4,7) y se añade un EXISTS sobre proyectos_usuarios
     * para contar solo proyectos con al menos un usuario concluido.
     * Sin $estado el comportamiento es idéntico al original.
     */
    private function contarInvestigador(
        int     $id,
        ?int    $filtro,
        ?string $buscar,
        ?string $estado = null   // <-- NUEVO parámetro
    ): int {
        $modoHistorial = ($estado === 'concluido');

        if ($modoHistorial) {
            $sql    = 'SELECT COUNT(*) AS total
                   FROM proyectos proy
                   WHERE proy.id_investigador = ?
                     AND EXISTS (
                         SELECT 1 FROM proyectos_usuarios pu_h
                         WHERE pu_h.id_proyectos = proy.id_proyectos
                           AND pu_h.estado       = ?
                     )';
            $params = [$id, $estado];   // $estado = 'concluido'
            $types  = 'is';
        } else {
            // Comportamiento original: excluir Por aprobar(3), Rechazado(4) y Cierre rechazado(7)
            $sql    = 'SELECT COUNT(*) AS total
                   FROM proyectos
                   WHERE id_investigador = ?
                     AND id_estadoP NOT IN (3, 4, 7)';
            $params = [$id];
            $types  = 'i';
        }

        if ($filtro) {
            $sql     .= ' AND id_estadoP = ?';
            $params[] = $filtro;
            $types   .= 'i';
        }
        if (!empty($buscar)) {
            // En modoHistorial la tabla tiene alias proy, fuera no
            $campo    = $modoHistorial ? 'proy.titulo' : 'titulo';
            $sql     .= " AND $campo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= 's';
        }

        return $this->repo->ejecutarConteo($sql, $types, $params);
    }


    // 
    // CATÁLOGOS
    // 

    public function tematica(): array
    {
        return $this->repo->listarTematicas();
    }
    public function obtenersubtematica(int $id): array
    {
        return $this->repo->listarSubtematicas($id);
    }
    public function obtenerperiodo(): array
    {
        return $this->repo->listarPeriodoActual();
    }
    public function obtenerTodosPeriodos(): array
    {
        return $this->repo->listarTodosPeriodos();
    }
    public function obtenerinstituto(): array
    {
        return $this->repo->listarInstituto();
    }
    public function periodoactual(): ?array
    {
        return $this->repo->buscarPeriodoActual();
    }


    // 
    // CRUD DE PROYECTOS
    // 

    public function registrarProyecto(
        int $id_investigador,
        int $id_estadoP,
        int $id_instituto,
        int $id_periodos,
        string $titulo,
        string $descripcion,
        string $objetivo,
        string $fecha_inicio,
        string $fecha_final,
        string $presupuesto,
        string $requisitos,
        string $Pre_requisitos,
        string $modalidad,
        int $AlumnosCantidad
    ): int {
        return $this->repo->insertarProyecto(
            $id_investigador,
            $id_estadoP,
            $id_instituto,
            $id_periodos,
            $titulo,
            $descripcion,
            $objetivo,
            $fecha_inicio,
            $fecha_final,
            $presupuesto,
            $requisitos,
            $Pre_requisitos,
            $modalidad,
            $AlumnosCantidad
        );
    }

    public function editarProyecto(
        int $id_proyecto,
        int $id_investigador,
        string $titulo,
        string $descripcion,
        string $objetivo,
        string $fecha_inicio,
        string $fecha_final,
        string $presupuesto,
        string $requisitos,
        string $Pre_requisitos,
        string $modalidad,
        int $AlumnosCantidad
    ): void {
        $this->repo->actualizarProyecto(
            $id_proyecto,
            $id_investigador,
            $titulo,
            $descripcion,
            $objetivo,
            $fecha_inicio,
            $fecha_final,
            $presupuesto,
            $requisitos,
            $Pre_requisitos,
            $modalidad,
            $AlumnosCantidad
        );
    }


    // 
    // ACTUALIZAR ESTADO
    // 

    public function actualizarEstadoProyectoRechazo(
        int $id_usuario,
        int $id_proyectos,
        string $tipo,
        string $comentario
    ): void {
        $num_motivo = ($tipo === 'cierre_rechazado') ? 7 : 4;
        $this->repo->actualizarEstadoRechazo($id_proyectos, $num_motivo);
        $this->repo->insertarComentarioProyecto($id_proyectos, $id_usuario, $tipo, $comentario);

        if ($tipo === 'cierre_rechazado') {
            $this->repo->actualizarCierreCancelado($id_proyectos);
        }
    }

    public function actualizarestado(int $id_proyectos, int $numeroEstado, ?float $porcentaje = null): void
    {
        $this->repo->actualizarEstado($id_proyectos, $numeroEstado);

        if ($numeroEstado === 2) { //Activo
            $plantillaRep   = $this->repo->buscarPlantillaReporte();
            $id_doc_reporte = $plantillaRep['id_documento'] ?? null;

            $id_avances  = $this->repo->insertarSeguimiento($id_proyectos);
            $tipos_tarea = $this->repo->listarTiposTarea();

            foreach ($tipos_tarea as $row) {
                $id_tipo = (int)$row['id_tareatipo'];
                $this->repo->insertarTarea(
                    $id_avances,
                    $id_tipo,
                    4,
                    ($id_tipo === 12 && $id_doc_reporte !== null) ? $id_doc_reporte : null
                );
            }
        }
        /*
        //Se pasa está lógica en el módulo de solicitudes_proyecto
        elseif ($numeroEstado === 5) { //Por cerrar
            $row = $this->repo->buscarInvestigadorDeProyecto($id_proyectos);
            if ($row) {
                $this->repo->insertarCierre($id_proyectos, $row['id_investigador'], $porcentaje);
            }
        } elseif ($numeroEstado === 1) { //Cierre
            $row = $this->repo->buscarInvestigadorDeProyecto($id_proyectos);
            if ($row) {
                $this->repo->actualizarCierreAprobado($id_proyectos);
                //$this->repo->concluirEstudiantesProyecto($id_proyectos);
                //Ahora se concluyen los estudiantes al aprobar la carta de terminación por el supervisor, no al cambiar el estado a cierre.
            }
        }*/
    }


    // 
    // PORCENTAJE DE AVANCE
    // 

    public function obtenerTareasAvance(int $id_proyecto): float
    {
        $tareas      = $this->repo->listarTareasAprobadas($id_proyecto);
        $totalTareas = 11;
        $suma        = array_sum(array_map(
            fn($row) => match ((int)$row['id_estadoT']) {
                5 => 100,
                2, 3 => 50,
                default => 0
            },
            $tareas
        ));

        return round(min(100, ($suma / $totalTareas) * 100), 2);
    }


    // 
    // DETALLES DEL PROYECTO
    // 

    private function sqlDetalleProyecto(): string
    {
        return "SELECT
                    proy.id_proyectos,
                    espr.nombre AS estado_proyecto,
                    tema.nombre_tematica AS tematica,
                    peri.periodo,
                    CASE
                        WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo'
                        WHEN CURDATE() < peri.fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado_periodo,
                    proy.titulo, proy.descripcion, proy.objetivo,
                    proy.fecha_inicio, proy.fecha_fin, proy.presupuesto,
                    proy.creado_en, proy.requisitos, proy.pre_requisitos,
                    proy.modalidad, proy.cantidad_estudiante
                FROM proyectos AS proy
                JOIN estados_proyectos AS espr     ON proy.id_estadoP  = espr.id_estadoP
                JOIN proyectos_subtematica AS proy_sub ON proy.id_proyectos = proy_sub.id_proyectos
                JOIN subtematica AS subt            ON proy_sub.id_subtematica = subt.id_subtematica
                JOIN tematica AS tema               ON tema.id_tematica = subt.id_tematica
                JOIN periodos peri                  ON proy.id_periodos = peri.id_periodos";
    }

    public function obtenerProyecto(int $id_proyecto, int $id_usuario, string $rol): ?array
    {
        $base = $this->sqlDetalleProyecto();

        [$sql, $tipos, $params] = match ($rol) {
            'estudiante' => [
                "$base JOIN proyectos_usuarios AS prous ON proy.id_proyectos = prous.id_proyectos
                 WHERE proy.id_proyectos = ? AND prous.id_usuarios = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica ORDER BY proy.id_proyectos DESC",
                'ii',
                [$id_proyecto, $id_usuario],
            ],
            'investigador', 'profesor' => [
                "$base WHERE proy.id_proyectos = ? AND proy.id_investigador = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica ORDER BY proy.id_proyectos DESC",
                'ii',
                [$id_proyecto, $id_usuario],
            ],
            'supervisor' => [
                "$base WHERE proy.id_proyectos = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica ORDER BY proy.id_proyectos DESC",
                'i',
                [$id_proyecto],
            ],
            default => [null, null, null],
        };

        if ($sql === null) return null;

        return $this->repo->buscarProyectoPorRol($sql, $tipos, $params);
    }

    public function obtenerProyectoInvestigador(int $id_proyecto): ?array
    {
        return $this->repo->buscarInvestigadorProyecto($id_proyecto);
    }
    public function obtenerUsuarioArea(?int $id_usuario): ?array
    {
        return $id_usuario ? $this->repo->buscarAreaUsuario($id_usuario) : null;
    }
    public function obtenerInvestigadorLinea(int $id_proyecto): ?array
    {
        return $this->repo->buscarLineaInvestigador($id_proyecto);
    }
    public function obtenersubtematicasProyecto(int $id_proyecto): array
    {
        return $this->repo->listarSubtematicasProyecto($id_proyecto);
    }
    public function obtenerProyectoEstudiante(int $id_proyecto): array
    {
        return $this->repo->listarEstudiantesProyecto($id_proyecto);
    }
    public function obtenerProyectoComentarios(int $id_proyecto): array
    {
        return $this->repo->listarComentariosProyecto($id_proyecto);
    }


    // 
    // SUBTEMATICAS
    // 

    public function vincularSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->repo->vincularSubtematica($id_proyecto, $id_subtematica);
    }
    public function ActualizarvincularSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->repo->actualizarSubtematica($id_proyecto, $id_subtematica);
    }


    // 
    // ESTUDIANTES EN EL PROYECTO
    // 

    public function estudiantes(int $id_proyecto): array
    {
        return $this->repo->listarIntegrantes($id_proyecto);
    }
    public function obtenerEstudianteProyecto(int $id_proyecto, int $id_estudiante): ?array
    {
        return $this->repo->buscarEstudianteEnProyecto($id_proyecto, $id_estudiante);
    }
    public function bajaEstudiante(int $id_proyecto, int $id_estudiante, ?string $motivo, int $usuario): array
    {
        return $this->repo->bajaEstudiante($id_proyecto, $id_estudiante, $motivo, $usuario);
    }
    public function reactivarEstudiante(int $id_proyecto, int $id_estudiante, int $usuario): array
    {
        return $this->repo->reactivarEstudiante($id_proyecto, $id_estudiante, $usuario);
    }


    // 
    // HISTORIAL DE ESTUDIANTE EN PROYECTO
    // 

    public function lineaTiempoProyectoUsuarios(
        int $id_proyecto,
        int $id_usuario,
        int $pagina = 1,
        int $por_pagina = 5
    ): array {
        $pagina        = max(1, $pagina);
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->repo->contarHistorial($id_proyecto, $id_usuario);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->repo->listarHistorial($id_proyecto, $id_usuario, $desde, $por_pagina);

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date('d/m/Y', strtotime($item['fecha']))][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => compact('total', 'por_pagina', 'pagina') + ['total_paginas' => $total_paginas],
        ];
    }
}
