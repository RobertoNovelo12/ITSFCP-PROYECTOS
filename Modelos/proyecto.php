<?php
// Modelos/proyecto.php
// Modelo del módulo Proyectos.
// Gestiona proyectos confirmados: listado, filtros, CRUD, estados, estudiantes e historial.
// NO incluye lógica de solicitudes de creación/cierre (ver solicitudes_proyecto.php).

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Proyectos extends BaseModelo
{
    // Constructor y $this->conn heredados de BaseModelo.
    // ejecutar() heredado de BaseModelo (protected).

    // 
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // 

    /**
     * Paso 1 — Marcar proyectos como Vencidos.
     *
     * Condición: fecha_fin < hoy Y estado NO es Cierre (1) ni ya Vencido (6).
     * NO se usa fecha del periodo; siempre se lee proyectos.fecha_fin.
     *
     * Estados que pueden vencer: Activo(2), Por aprobar(3), Rechazado(4),
     *                             Por cerrar(5), Cierre rechazado(7).
     */
    public function actualizarProyectosVencidos(): bool
    {
        return $this->ejecutar("
            UPDATE proyectos
            SET id_estadoP = 6
            WHERE id_estadoP IN (2, 3, 4, 5, 7)
              AND fecha_fin < CURDATE()
        ");
    }

    /**
     * Paso 2 — Actualizar estudiantes de proyectos vencidos.
     *
     * CASO 1 — Etapa 2 completa (todas las tareas aprobadas, id_estadoT = 5):
     *   → Permanece 'activo'. Solo se registra historial con acción 'vencido'.
     *
     * CASO 2 — Etapa 2 incompleta (tiene tareas sin aprobar O sin tareas):
     *   → Pasa a 'baja' con motivo 'Proyecto vencido sin concluir actividades'.
     */
    public function actualizarEstadoEstudiantesVencidos(): bool
    {
        $etapa2_completa = "
            EXISTS (
                SELECT 1
                FROM tareas_usuarios tu2
                JOIN tareas t2           ON t2.id_tarea    = tu2.id_tarea
                JOIN tbl_seguimiento ts2 ON ts2.id_avances = t2.id_avances
                WHERE tu2.id_usuarios   = pu.id_usuarios
                  AND ts2.id_proyectos  = pu.id_proyectos
            )
            AND NOT EXISTS (
                SELECT 1
                FROM tareas_usuarios tu3
                JOIN tareas t3           ON t3.id_tarea    = tu3.id_tarea
                JOIN tbl_seguimiento ts3 ON ts3.id_avances = t3.id_avances
                WHERE tu3.id_usuarios   = pu.id_usuarios
                  AND ts3.id_proyectos  = pu.id_proyectos
                  AND tu3.id_estadoT   <> 5
            )
        ";

        // CASO 1 — Historial 'vencido' para estudiantes que SÍ terminaron Etapa 2.
        $this->ejecutar("
            INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT
                pu.id_proyectos,
                pu.id_usuarios,
                'vencido',
                'Proyecto vencido — estudiante concluyó actividades, pendiente de carta de terminación',
                0,
                NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.fecha_fin    < CURDATE()
              AND p.id_estadoP   = 6
              AND pu.estado       = 'activo'
              AND {$etapa2_completa}
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos  = pu.id_proyectos
                    AND h.id_estudiante = pu.id_usuarios
                    AND h.accion        = 'vencido'
              )
        ");

        // CASO 2 — Historial 'baja' para estudiantes que NO terminaron Etapa 2.
        $this->ejecutar("
            INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
            SELECT
                pu.id_proyectos,
                pu.id_usuarios,
                'baja',
                'Proyecto vencido sin concluir actividades',
                0,
                NOW()
            FROM proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE p.fecha_fin    < CURDATE()
              AND p.id_estadoP   = 6
              AND pu.estado       = 'activo'
              AND NOT ({$etapa2_completa})
              AND NOT EXISTS (
                  SELECT 1 FROM historial_proyectos_usuarios h
                  WHERE h.id_proyectos  = pu.id_proyectos
                    AND h.id_estudiante = pu.id_usuarios
                    AND h.accion        = 'baja'
                    AND h.motivo        = 'Proyecto vencido sin concluir actividades'
              )
        ");

        // CASO 2 — Dar de baja en proyectos_usuarios.
        return $this->ejecutar("
            UPDATE proyectos_usuarios pu
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            SET
                pu.estado       = 'baja',
                pu.fecha_baja   = NOW(),
                pu.motivo_baja  = 'Proyecto vencido sin concluir actividades'
            WHERE p.fecha_fin   < CURDATE()
              AND p.id_estadoP  = 6
              AND pu.estado      = 'activo'
              AND NOT ({$etapa2_completa})
        ");
    }

    // 
    // TABLA DE PROYECTOS (centralizado)
    // 

    public function obtenerProyectosTablaFiltro(int $id, ?int $filtro, string $rol, ?string $buscar): string
    {
        return match (strtolower($rol)) {
            'estudiante'             => $this->obtenerProyectosTablaEstudiante($id, $filtro, $buscar),
            'investigador', 'profesor' => $this->obtenerProyectosTablaInvestigador($id, $filtro, $buscar),
            'supervisor'             => $this->obtenerProyectosTablaSupervisor($filtro, $buscar),
            default                  => json_encode([]),
        };
    }

    private function obtenerProyectosTablaEstudiante(int $id, ?int $filtro, ?string $buscar): string
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadEstudiante($id, $filtro, $buscar);
        $total_paginas = (int)ceil($total / $por_pagina);

        $sql    = "
            SELECT
                proy.id_proyectos,
                proy.titulo,
                proy.fecha_inicio,
                proy.fecha_fin,
                espr.nombre           AS estado_proyecto,
                peri.periodo,
                pu.estado             AS estado_estudiante,
                COALESCE(tr.total, 0) AS total
            FROM proyectos proy
            JOIN proyectos_usuarios pu
                ON  pu.id_proyectos = proy.id_proyectos
                AND pu.id_usuarios  = ?
                AND pu.estado       = 'activo'
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
        ";
        $params = [$id];
        $types  = "i";

        if ($filtro) {
            $sql     .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $sql     .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        $sql     .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        return json_encode([
            "proyectos"  => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ]);
    }

    private function obtenerProyectosTablaInvestigador(int $id, ?int $filtro, ?string $buscar): string
    {
        $por_pagina    = 6;
        $pagina        = max(1, (int)($_GET['pagina'] ?? 1));
        $desde         = ($pagina - 1) * $por_pagina;
        $total         = $this->obtenerCantidadInvestigador($id, $filtro, $buscar);
        $total_paginas = (int)ceil($total / $por_pagina);

        $sql    = "
            SELECT
                proy.id_proyectos,
                proy.titulo,
                proy.fecha_inicio,
                proy.fecha_fin,
                espr.nombre            AS estado_proyecto,
                peri.periodo,
                COALESCE(tr.total, 0)  AS total,
                CASE
                    WHEN COALESCE(total_est.total_estudiantes, 0) = 0           THEN 0
                    WHEN COALESCE(activos_bloqueados.total, 0)    > 0           THEN 0
                    WHEN COALESCE(activos_completos.total, 0)     > 0           THEN 1
                    WHEN COALESCE(total_activos.total, 0)         = 0           THEN 1
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
                  AND EXISTS (
                      SELECT 1 FROM tareas_usuarios tu2 WHERE tu2.id_usuarios = pu.id_usuarios
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM tareas_usuarios tu3
                      WHERE tu3.id_usuarios = pu.id_usuarios AND tu3.id_estadoT <> 5
                  )
                GROUP BY pu.id_proyectos
            ) activos_completos ON activos_completos.id_proyectos = proy.id_proyectos
            LEFT JOIN (
                SELECT pu.id_proyectos, COUNT(DISTINCT pu.id_usuarios) AS total
                FROM proyectos_usuarios pu
                WHERE pu.estado = 'activo'
                  AND (
                      NOT EXISTS (
                          SELECT 1 FROM tareas_usuarios tu4 WHERE tu4.id_usuarios = pu.id_usuarios
                      )
                      OR EXISTS (
                          SELECT 1 FROM tareas_usuarios tu5
                          WHERE tu5.id_usuarios = pu.id_usuarios AND tu5.id_estadoT <> 5
                      )
                  )
                GROUP BY pu.id_proyectos
            ) activos_bloqueados ON activos_bloqueados.id_proyectos = proy.id_proyectos
            WHERE proy.id_investigador = ?
              AND proy.id_estadoP     <> 3
        ";
        $params = [$id];
        $types  = "i";

        if ($filtro) {
            $sql     .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $sql     .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        $sql     .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        return json_encode([
            "proyectos"  => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ]);
    }

    private function obtenerProyectosTablaSupervisor(?int $filtro, ?string $buscar): string
    {
        $por_pagina = 6;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $where_base    = "WHERE 1 AND proy.id_estadoP NOT IN (3, 4)";
        $params_total  = [];
        $types_total   = "";
        $sql_total     = "SELECT COUNT(*) AS total FROM proyectos proy $where_base";

        if ($filtro) {
            $sql_total    .= " AND proy.id_estadoP = ?";
            $params_total[] = $filtro;
            $types_total   .= "i";
        }
        if (!empty($buscar)) {
            $sql_total    .= " AND proy.titulo LIKE ?";
            $params_total[] = "%$buscar%";
            $types_total   .= "s";
        }

        $total         = (int)($this->ejecutar($sql_total, $types_total, $params_total, false)['total'] ?? 0);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $sql    = "
            SELECT
                proy.id_proyectos,
                proy.titulo,
                proy.fecha_inicio,
                proy.fecha_fin,
                espr.nombre AS estado_proyecto,
                peri.periodo,
                COALESCE(tr.total, 0) AS total,
                CASE
                    WHEN COALESCE(pa.total_alumnos, 0) > 0
                     AND COALESCE(tt.total_tareas, 0) >= 11
                     AND COALESCE(tc.tareas_completadas, 0) = (COALESCE(tt.total_tareas, 0) * COALESCE(pa.total_alumnos, 0))
                    THEN 1 ELSE 0
                END AS puede_cerrar
            FROM proyectos proy
            JOIN estados_proyectos espr ON proy.id_estadoP  = espr.id_estadoP
            JOIN periodos peri           ON proy.id_periodos = peri.id_periodos
            LEFT JOIN (
                SELECT ts.id_proyectos, COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
                FROM tbl_seguimiento ts
                JOIN tareas t ON t.id_avances = ts.id_avances
                LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
                GROUP BY ts.id_proyectos
            ) tr ON tr.id_proyectos = proy.id_proyectos
            LEFT JOIN (
                SELECT id_proyectos, COUNT(*) total_alumnos
                FROM proyectos_usuarios WHERE estado = 'activo' GROUP BY id_proyectos
            ) pa ON pa.id_proyectos = proy.id_proyectos
            LEFT JOIN (
                SELECT ts.id_proyectos, COUNT(DISTINCT t.id_tarea) total_tareas
                FROM tbl_seguimiento ts JOIN tareas t ON t.id_avances = ts.id_avances
                GROUP BY ts.id_proyectos
            ) tt ON tt.id_proyectos = proy.id_proyectos
            LEFT JOIN (
                SELECT ts.id_proyectos, COUNT(*) tareas_completadas
                FROM tbl_seguimiento ts
                JOIN tareas t ON t.id_avances = ts.id_avances
                JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
                JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
                WHERE tu.id_estadoT = 5 AND pu.estado = 'activo'
                GROUP BY ts.id_proyectos
            ) tc ON tc.id_proyectos = proy.id_proyectos
            $where_base
        ";
        $params = [];
        $types  = "";

        if ($filtro) {
            $sql     .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $sql     .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        $sql     .= " ORDER BY proy.id_proyectos DESC LIMIT ?, ?";
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        return json_encode([
            "proyectos"  => $this->ejecutar($sql, $types, $params),
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ]);
    }

    // --- Contadores internos ---

    private function obtenerCantidadEstudiante(int $id, ?int $filtro, ?string $buscar): int
    {
        $sql    = "
            SELECT COUNT(*) AS total
            FROM proyectos proy
            JOIN proyectos_usuarios pu
                ON pu.id_proyectos = proy.id_proyectos
               AND pu.id_usuarios  = ?
               AND pu.estado       = 'activo'
            WHERE 1 = 1
        ";
        $params = [$id];
        $types  = "i";

        if ($filtro) {
            $sql     .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $sql     .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }

    private function obtenerCantidadInvestigador(int $id, ?int $filtro, ?string $buscar): int
    {
        $sql    = "
            SELECT COUNT(*) AS total
            FROM proyectos
            WHERE id_investigador = ?
              AND id_estadoP <> 3
        ";
        $params = [$id];
        $types  = "i";

        if ($filtro) {
            $sql     .= " AND id_estadoP = ?";
            $params[] = $filtro;
            $types   .= "i";
        }
        if (!empty($buscar)) {
            $sql     .= " AND titulo LIKE ?";
            $params[] = "%$buscar%";
            $types   .= "s";
        }

        return (int)($this->ejecutar($sql, $types, $params, false)['total'] ?? 0);
    }

    // 
    // CATÁLOGOS
    // 

    public function tematica(): array
    {
        return $this->ejecutar(
            "SELECT id_tematica, nombre_tematica FROM gestion_proyectos.tematica"
        );
    }

    public function obtenersubtematica(int $id_tematica): array
    {
        return $this->ejecutar(
            "SELECT sub.id_subtematica, sub.nombre_subtematica
             FROM gestion_proyectos.subtematica AS sub
             JOIN tematica AS te ON sub.id_tematica = te.id_tematica
             WHERE te.id_tematica = ? AND sub.estado = 1",
            "i",
            [$id_tematica]
        );
    }

    public function obtenerperiodo(): array
    {
        return $this->ejecutar(
            "SELECT id_periodos, periodo,
                    fecha_inicio AS FechaInicio, fecha_final AS FechaFinal,
                    CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado
             FROM periodos ORDER BY periodo DESC LIMIT 1"
        );
    }

    public function obtenerinstituto(): array
    {
        return $this->ejecutar(
            "SELECT id_instituto FROM gestion_proyectos.instituto ORDER BY id_instituto DESC LIMIT 1"
        );
    }

    public function periodoactual(): ?array
    {
        return $this->ejecutar(
            "SELECT fecha_inicio_proyectos, fecha_fin_proyectos
             FROM periodos ORDER BY id_periodos DESC LIMIT 1",
            "",
            [],
            false
        );
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
        $this->ejecutar(
            "INSERT INTO proyectos
                (id_investigador, id_estadoP, id_instituto, id_periodos, titulo, descripcion, objetivo,
                 fecha_inicio, fecha_fin, presupuesto, actualizado_en, requisitos, pre_requisitos, modalidad, cantidad_estudiante)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)",
            "iiiisssssssssi",
            [
                $id_investigador, $id_estadoP, $id_instituto, $id_periodos,
                $titulo, $descripcion, $objetivo,
                $fecha_inicio, $fecha_final, $presupuesto,
                $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad,
            ]
        );
        return (int)$this->conn->insert_id;
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
        $this->ejecutar(
            "UPDATE proyectos SET
                titulo = ?, descripcion = ?, objetivo = ?, pre_requisitos = ?, requisitos = ?,
                cantidad_estudiante = ?, modalidad = ?, actualizado_en = NOW(),
                presupuesto = ?, fecha_inicio = ?, fecha_fin = ?
             WHERE id_proyectos = ? AND id_investigador = ?",
            "sssssiisisii",
            [
                $titulo, $descripcion, $objetivo, $Pre_requisitos, $requisitos,
                $AlumnosCantidad, $modalidad, $presupuesto,
                $fecha_inicio, $fecha_final,
                $id_proyecto, $id_investigador,
            ]
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

        $this->ejecutar(
            "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?",
            "ii",
            [$num_motivo, $id_proyectos]
        );
        $this->ejecutar(
            "INSERT INTO proyectos_comentarios (id_proyectos, id_usuarios, tipo, comentario, fecha)
             VALUES (?, ?, ?, ?, CURDATE())",
            "iiss",
            [$id_proyectos, $id_usuario, $tipo, $comentario]
        );

        if ($tipo === 'cierre_rechazado') {
            $this->ejecutar(
                "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = 'rechazado' WHERE id_proyectos = ?",
                "i",
                [$id_proyectos]
            );
        }
    }

    public function actualizarestado(int $id_proyectos, int $numeroEstado, ?float $porcentaje = null): void
    {
        $this->ejecutar(
            "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?",
            "ii",
            [$numeroEstado, $id_proyectos]
        );

        // Estado 2 → Activo: crear seguimiento y tareas
        if ($numeroEstado === 2) {
            $plantillaRep = $this->ejecutar(
                "SELECT pd.id_plantilla, pd.id_documento
                 FROM plantillas_documentos pd
                 INNER JOIN tipo_documento td ON td.id_tipo_documento = pd.id_tipo_documento
                 WHERE pd.activo = 1 AND LOWER(td.nombre) LIKE 'reporte%' LIMIT 1",
                "",
                [],
                false
            );
            $id_doc_reporte = $plantillaRep['id_documento'] ?? null;

            $this->ejecutar(
                "INSERT INTO tbl_seguimiento (id_proyectos, fecha_activacion) VALUES (?, CURDATE())",
                "i",
                [$id_proyectos]
            );
            $id_avances = (int)$this->conn->insert_id;

            $tipos_tarea = $this->ejecutar(
                "SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC"
            );

            $estadoSinActivar = 4;
            foreach ($tipos_tarea as $row) {
                $id_tipo = (int)$row['id_tareatipo'];
                if ($id_tipo === 12 && $id_doc_reporte !== null) {
                    $this->ejecutar(
                        "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT, id_documento_recurso) VALUES (?, ?, ?, ?)",
                        "iiii",
                        [$id_avances, $id_tipo, $estadoSinActivar, $id_doc_reporte]
                    );
                } else {
                    $this->ejecutar(
                        "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT) VALUES (?, ?, ?)",
                        "iii",
                        [$id_avances, $id_tipo, $estadoSinActivar]
                    );
                }
            }

        // Estado 5 → Por cerrar: registrar solicitud de cierre
        } elseif ($numeroEstado === 5) {
            $row = $this->ejecutar(
                "SELECT id_investigador FROM proyectos WHERE id_proyectos = ?",
                "i",
                [$id_proyectos],
                false
            );
            if ($row) {
                $this->ejecutar(
                    "INSERT INTO tbl_cierres (id_proyectos, id_supervisor, fecha_solicitud, porcentaje, estado)
                     VALUES (?, ?, CURDATE(), ?, 'espera')",
                    "iiid",
                    [$id_proyectos, $row['id_investigador'], $porcentaje]
                );
            }

        // Estado 1 → Cierre: aprobar cierre
        } elseif ($numeroEstado === 1) {
            $row = $this->ejecutar(
                "SELECT id_investigador FROM proyectos WHERE id_proyectos = ?",
                "i",
                [$id_proyectos],
                false
            );
            if ($row) {
                $this->ejecutar(
                    "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = 'aprobado' WHERE id_proyectos = ?",
                    "i",
                    [$id_proyectos]
                );
                $this->ejecutar(
                    "UPDATE proyectos_usuarios SET fecha_terminacion = CURDATE(), estado = 'concluido' WHERE id_proyectos = ?",
                    "i",
                    [$id_proyectos]
                );
            }
        }
    }

    // 
    // PORCENTAJE DE AVANCE
    // 

    private function valorPorEstado(int $estado): int
    {
        return match ($estado) {
            5       => 100,
            2, 3    => 50,
            default => 0,
        };
    }

    public function obtenerTareasAvance(int $id_proyecto): float
    {
        $tareas = $this->ejecutar(
            "SELECT taus.id_estadoT
             FROM tareas_usuarios AS taus
             JOIN tareas AS tare ON tare.id_tarea = taus.id_tarea
             JOIN tbl_seguimiento AS tbse ON tare.id_avances = tbse.id_avances
             WHERE tbse.id_proyectos = ? AND taus.id_estadoT = 5",
            "i",
            [$id_proyecto]
        );

        $totalTareas = 11;
        $suma        = array_sum(array_map(
            fn($row) => $this->valorPorEstado((int)$row['id_estadoT']),
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

        return match ($rol) {
            'estudiante' => $this->ejecutar(
                "$base
                 JOIN proyectos_usuarios AS prous ON proy.id_proyectos = prous.id_proyectos
                 WHERE proy.id_proyectos = ? AND prous.id_usuarios = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
                 ORDER BY proy.id_proyectos DESC",
                "ii",
                [$id_proyecto, $id_usuario],
                false
            ),
            'investigador', 'profesor' => $this->ejecutar(
                "$base
                 WHERE proy.id_proyectos = ? AND proy.id_investigador = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
                 ORDER BY proy.id_proyectos DESC",
                "ii",
                [$id_proyecto, $id_usuario],
                false
            ),
            'supervisor' => $this->ejecutar(
                "$base
                 WHERE proy.id_proyectos = ?
                 GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
                 ORDER BY proy.id_proyectos DESC",
                "i",
                [$id_proyecto],
                false
            ),
            default => null,
        };
    }

    public function obtenerProyectoInvestigador(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                    nisn.nombre AS nivel_sni, grac.nombre AS grado_academico
             FROM investigadores AS inve
             JOIN usuarios AS usua          ON usua.id_usuarios = inve.id_usuarios
             JOIN niveles_sni AS nisn        ON nisn.id_nivel    = inve.id_nivel_sni
             JOIN grados_academicos AS grac  ON grac.id_grado    = inve.id_grado
             JOIN proyectos AS proy          ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i",
            [$id_proyecto],
            false
        );
    }

    public function obtenerUsuarioArea(?int $id_usuario): ?array
    {
        if (!$id_usuario) return null;
        return $this->ejecutar(
            "SELECT arco.nombre_area AS area_conocimiento, GROUP_CONCAT(subco.nombre_subarea) AS subarea
             FROM usuarios AS us
             JOIN usuarios_subareas AS ussu       ON ussu.id_usuarios = us.id_usuarios
             JOIN subareas_conocimiento AS subco   ON ussu.id_subarea  = subco.id_subarea
             JOIN areas_conocimiento AS arco       ON arco.id_area     = subco.id_area
             WHERE us.id_usuarios = ?
             GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area",
            "i",
            [$id_usuario],
            false
        );
    }

    public function obtenerInvestigadorLinea(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT liin.nombre AS linea
             FROM investigadores AS inve
             JOIN investigador_lineas_investigacion AS inliin ON inliin.id_usuarios = inve.id_usuarios
             JOIN lineas_investigacion AS liin                ON liin.id_linea      = inliin.id_linea
             JOIN proyectos AS proy                           ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i",
            [$id_proyecto],
            false
        );
    }

    public function obtenersubtematicasProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre
             FROM proyectos_subtematica AS sub
             JOIN subtematica AS sub2 ON sub.id_subtematica = sub2.id_subtematica
             WHERE sub.id_proyectos = ? AND sub2.estado = 1",
            "i",
            [$id_proyecto]
        );
    }

    public function obtenerProyectoEstudiante(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                    carr.nombre_carrera AS carrera
             FROM estudiantes AS estu
             JOIN usuarios AS usua         ON usua.id_usuarios  = estu.id_usuarios
             JOIN carreras AS carr          ON carr.id_carrera   = estu.id_carrera
             JOIN proyectos_usuarios AS prus ON prus.id_usuarios = estu.id_usuarios
             JOIN proyectos AS proy          ON proy.id_proyectos = prus.id_proyectos
             WHERE proy.id_proyectos = ?",
            "i",
            [$id_proyecto]
        );
    }

    public function obtenerProyectoComentarios(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                CASE
                    WHEN prco.tipo = 'creacion_rechazada' THEN 'Creación rechazada'
                    WHEN prco.tipo = 'cierre_rechazado'   THEN 'Cierre rechazado'
                    ELSE 'Rechazo'
                END AS tipo,
                CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) AS nombre_completo,
                prco.comentario,
                prco.fecha
             FROM proyectos_comentarios AS prco
             JOIN proyectos AS proy ON proy.id_proyectos = prco.id_proyectos
             JOIN usuarios AS usua  ON usua.id_usuarios  = prco.id_usuarios
             WHERE proy.id_proyectos = ?
             ORDER BY fecha DESC",
            "i",
            [$id_proyecto]
        );
    }

    // 
    // SUBTEMATICAS
    // 

    public function vincularSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->ejecutar(
            "INSERT INTO proyectos_subtematica (id_proyectos, id_subtematica) VALUES (?, ?)",
            "ii",
            [$id_proyecto, $id_subtematica]
        );
    }

    public function ActualizarvincularSubtematica(int $id_proyecto, int $id_subtematica): void
    {
        $this->ejecutar(
            "DELETE FROM proyectos_subtematica WHERE id_proyectos = ?",
            "i",
            [$id_proyecto]
        );
        $this->ejecutar(
            "INSERT INTO proyectos_subtematica (id_subtematica, id_proyectos) VALUES (?, ?)",
            "ii",
            [$id_subtematica, $id_proyecto]
        );
    }

    // 
    // ESTUDIANTES EN EL PROYECTO
    // 

    public function estudiantes(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                u.id_usuarios,
                u.nombre, u.apellido_paterno, u.apellido_materno,
                c.nombre_carrera AS carrera,
                pu.estado,
                ep.estado AS estado_proceso,
                hpu.motivo
             FROM proyectos_usuarios pu
             JOIN usuarios u    ON u.id_usuarios   = pu.id_usuarios
             JOIN estudiantes e ON e.id_usuarios   = u.id_usuarios
             JOIN carreras c    ON e.id_carrera     = c.id_carrera
             JOIN estados_proceso ep ON pu.id_estados_proceso = ep.id_estados_proceso
             LEFT JOIN (
                 SELECT h1.id_proyectos, h1.id_estudiante, h1.motivo
                 FROM historial_proyectos_usuarios h1
                 INNER JOIN (
                     SELECT id_proyectos, id_estudiante, MAX(id_historial) AS max_id
                     FROM historial_proyectos_usuarios
                     WHERE accion = 'baja'
                     GROUP BY id_proyectos, id_estudiante
                 ) h2 ON h1.id_historial = h2.max_id
             ) hpu ON hpu.id_proyectos = pu.id_proyectos AND hpu.id_estudiante = pu.id_usuarios
             WHERE pu.id_proyectos = ?",
            "i",
            [$id_proyecto]
        );
    }

    public function obtenerEstudianteProyecto(int $id_proyecto, int $id_estudiante): ?array
    {
        return $this->ejecutar(
            "SELECT u.nombre, u.apellido_paterno, u.apellido_materno, p.titulo
             FROM usuarios u
             JOIN proyectos_usuarios pu ON pu.id_usuarios   = u.id_usuarios
             JOIN proyectos p           ON p.id_proyectos   = pu.id_proyectos
             WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        );
    }

    public function bajaEstudiante(int $id_proyecto, int $id_estudiante, ?string $motivo, int $usuario): array
    {
        $this->conn->begin_transaction();
        try {
            $data = $this->ejecutar(
                "SELECT estado FROM proyectos_usuarios WHERE id_proyectos = ? AND id_usuarios = ?",
                "ii",
                [$id_proyecto, $id_estudiante],
                false
            );
            if (($data['estado'] ?? '') !== 'activo') {
                throw new Exception("El estudiante no está activo");
            }
            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET estado = 'baja', fecha_baja = NOW(), motivo_baja = ?, reincorporacion = 0
                 WHERE id_proyectos = ? AND id_usuarios = ?",
                "sii",
                [$motivo, $id_proyecto, $id_estudiante]
            );
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                 VALUES (?, ?, 'baja', ?, ?)",
                "iisi",
                [$id_proyecto, $id_estudiante, $motivo, $usuario]
            );
            $this->conn->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    public function reactivarEstudiante(int $id_proyecto, int $id_estudiante, int $usuario): array
    {
        $this->conn->begin_transaction();
        try {
            $data = $this->ejecutar(
                "SELECT pu.estado, p.fecha_fin
                 FROM proyectos_usuarios pu
                 JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                 WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?",
                "ii",
                [$id_proyecto, $id_estudiante],
                false
            );
            if (!$data)                             throw new Exception("Registro no encontrado");
            if ($data['estado'] !== 'baja')         throw new Exception("Solo se puede reactivar si está en baja");
            if ($data['fecha_fin'] < date('Y-m-d')) throw new Exception("El proyecto está vencido, requiere prórroga");

            $this->ejecutar(
                "UPDATE proyectos_usuarios
                 SET estado = 'activo', fecha_baja = NULL, motivo_baja = NULL, reincorporacion = 1
                 WHERE id_proyectos = ? AND id_usuarios = ?",
                "ii",
                [$id_proyecto, $id_estudiante]
            );
            $this->ejecutar(
                "INSERT INTO historial_proyectos_usuarios (id_proyectos, id_estudiante, accion, realizado_por)
                 VALUES (?, ?, 'reactivado', ?)",
                "iii",
                [$id_proyecto, $id_estudiante, $usuario]
            );
            $this->conn->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }

    // 
    // HISTORIAL DE ESTUDIANTE EN PROYECTO
    // 

    public function lineaTiempoProyectoUsuarios(
        int $id_proyecto,
        int $id_usuario,
        int $pagina = 1,
        int $por_pagina = 5    ): array {
        $pagina        = max(1, $pagina);
        $desde         = ($pagina - 1) * $por_pagina;

        $total = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM historial_proyectos_usuarios
             WHERE id_proyectos = ? AND id_estudiante = ?",
            "ii",
            [$id_proyecto, $id_usuario],
            false
        )['total'] ?? 0);

        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->ejecutar(
            "SELECT h.accion AS tipo_evento, h.motivo AS descripcion, h.fecha,
                    u.nombre AS usuario
             FROM historial_proyectos_usuarios h
             LEFT JOIN usuarios u ON h.id_estudiante = u.id_usuarios
             WHERE h.id_proyectos = ? AND h.id_estudiante = ?
             ORDER BY h.fecha DESC
             LIMIT ?, ?",
            "iiii",
            [$id_proyecto, $id_usuario, $desde, $por_pagina]
        );

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date("d/m/Y", strtotime($item['fecha']))][] = $item;
        }

        return [
            "datos"      => $agrupado,
            "paginacion" => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ];
    }
}