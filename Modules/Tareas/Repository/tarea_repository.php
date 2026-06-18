<?php
// Repositorios/TareaRepositorio.php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * TareaRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL del módulo de tareas.
 * No contiene lógica de negocio.
 *
 * Nota: los métodos actualizarTareasVencidos() y actualizarTareasConcluidas()
 * requieren múltiples prepared statements iterados sobre loops. Para estos casos
 * se accede directamente a $this->conn (heredado de BaseModelo) manteniendo
 * la misma seguridad de prepared statements.
 */
class TareaRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // ─
    // MANTENIMIENTO AUTOMÁTICO
    // ─

    public function obtenerAsignacionesVencidas(string $hoy): array
    {
        return $this->ejecutar(
            "SELECT taus.id_asignacion
         FROM tareas_usuarios taus
         JOIN tareas tare ON taus.id_tarea = tare.id_tarea
         WHERE taus.id_estadoT IN (1,3,8)
           AND tare.fecha_entrega < ?",
            's',
            [$hoy]
        );
    }

    public function marcarVencidas(string $hoy): void
    {
        $this->ejecutar(
            "UPDATE tareas_usuarios taus
         JOIN tareas tare ON taus.id_tarea = tare.id_tarea
         SET taus.id_estadoT = 6
         WHERE taus.id_estadoT IN (1,3,8)
           AND tare.fecha_entrega < ?",
            's',
            [$hoy]
        );
    }

    public function insertarHistorialVencido(array $asignaciones): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             VALUES (?, 6, 1, 'Tarea marcada como vencida automáticamente', 'estado')"
        );
        if (!$stmt) return;
        foreach ($asignaciones as $v) {
            $stmt->bind_param('i', $v['id_asignacion']);
            $stmt->execute();
        }
        $stmt->close();
    }

    // REACTIVAR TODAS LAS ASIGNACIONES VENCIDAS DE UNA TAREA
    public function reactivarAsignacionesVencidas(int $id_tarea): void
    {
        $this->ejecutar(
            'UPDATE tareas_usuarios
         SET id_estadoT = 1
         WHERE id_tarea = ?
           AND id_estadoT = 6',
            'i',
            [$id_tarea]
        );
    }

    public function obtenerAsignacionesVencidasDeTarea(int $id_tarea): array
    {
        return $this->ejecutar(
            'SELECT id_asignacion
         FROM tareas_usuarios
         WHERE id_tarea = ?
           AND id_estadoT = 6',
            'i',
            [$id_tarea]
        );
    }

    public function insertarHistorialReactivacion(
        array $asignaciones,
        int $id_usuario
    ): void {
        $stmt = $this->conn->prepare(
            "INSERT INTO tareas_historial
            (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
         VALUES (?, 1, ?, 'Tarea reactivada por el investigador', 'estado')"
        );

        if (!$stmt) {
            return;
        }

        foreach ($asignaciones as $v) {
            $stmt->bind_param(
                'ii',
                $v['id_asignacion'],
                $id_usuario
            );
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * Devuelve tareas en estados activos (1, 2, 3), opcionalmente filtradas por id_tarea.
     */
    public function obtenerTareasActivas(?int $id_tarea): array
    {
        if ($id_tarea !== null) {
            return $this->ejecutar(
                "SELECT t.id_tarea, tbse.id_proyectos
                 FROM tareas t
                 JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
                 WHERE t.id_tarea = ? AND t.id_estadoT IN (1, 2, 3)",
                'i',
                [$id_tarea]
            );
        }
        return $this->ejecutar(
            "SELECT t.id_tarea, tbse.id_proyectos
             FROM tareas t
             JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
             WHERE t.id_estadoT IN (1, 2, 3)"
        );
    }

    /**
     * Batch: procesa cada tarea del array para marcarla como concluida
     * si todos los alumnos activos del proyecto la tienen aprobada.
     */
    public function procesarConclusiones(array $tareas): void
    {
        $stmtTotal = $this->conn->prepare(
            "SELECT COUNT(*) AS total
             FROM tareas_usuarios tu
             JOIN proyectos_usuarios pu
                  ON pu.id_proyectos = ? AND pu.id_usuarios = tu.id_usuarios AND pu.estado = 'activo'
             WHERE tu.id_tarea = ?"
        );
        $stmtAprobados = $this->conn->prepare(
            "SELECT COUNT(*) AS aprobados
             FROM tareas_usuarios tu
             JOIN proyectos_usuarios pu
                  ON pu.id_proyectos = ? AND pu.id_usuarios = tu.id_usuarios AND pu.estado = 'activo'
             WHERE tu.id_tarea = ? AND tu.id_estadoT = 5"
        );
        $stmtConcluir = $this->conn->prepare(
            "UPDATE tareas SET id_estadoT = 9
             WHERE id_tarea = ? AND id_estadoT IN (1, 2, 3)"
        );
        $stmtHist = $this->conn->prepare(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             SELECT tu.id_asignacion, 9, 1,
                    'Tarea marcada como concluida automáticamente', 'estado'
             FROM tareas_usuarios tu
             JOIN proyectos_usuarios pu
                  ON pu.id_proyectos = ? AND pu.id_usuarios = tu.id_usuarios AND pu.estado = 'activo'
             WHERE tu.id_tarea = ?"
        );

        if (!$stmtTotal || !$stmtAprobados || !$stmtConcluir || !$stmtHist) return;

        foreach ($tareas as $tarea) {
            $idT = (int)$tarea['id_tarea'];
            $idP = (int)$tarea['id_proyectos'];

            $stmtTotal->bind_param('ii', $idP, $idT);
            $stmtTotal->execute();
            $total = (int)$stmtTotal->get_result()->fetch_assoc()['total'];
            if ($total === 0) continue;

            $stmtAprobados->bind_param('ii', $idP, $idT);
            $stmtAprobados->execute();
            $aprobados = (int)$stmtAprobados->get_result()->fetch_assoc()['aprobados'];

            if ($aprobados === $total) {
                $stmtConcluir->bind_param('i', $idT);
                $stmtConcluir->execute();
                if ($stmtConcluir->affected_rows > 0) {
                    $stmtHist->bind_param('ii', $idP, $idT);
                    $stmtHist->execute();
                }
            }
        }

        $stmtTotal->close();
        $stmtAprobados->close();
        $stmtConcluir->close();
        $stmtHist->close();
    }


    // ─
    // OBTENER TAREAS (tabla principal)
    // ─

    public function obtenerTareasEstudiante(int $id_usuario, int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                t.id_tarea, taus.id_asignacion,
                tt.descripcion_tipo          AS tipo,
                ds_rec.nombre               AS archivo_nombre,
                ds_rec.ruta                 AS archivo_ruta,
                ds_rec.tipo_mime            AS archivo_tipo,
                ds_rec.extension            AS archivo_extension,
                t.fecha_entrega,
                est.nombre                  AS estado_plantilla,
                ds_ent.nombre               AS archivo_entregado_nombre,
                ds_ent.ruta                 AS archivo_entregado_ruta,
                esu.nombre                  AS estado_entrega,
                t.fecha_modificacion
             FROM tareas t
             INNER JOIN tbl_seguimiento s    ON t.id_avances    = s.id_avances
             LEFT  JOIN tareas_usuarios taus ON taus.id_tarea   = t.id_tarea
             INNER JOIN tipo_tarea tt        ON t.id_tareatipo  = tt.id_tareatipo
             LEFT  JOIN estados_tarea est    ON t.id_estadoT    = est.id_estadoT
             LEFT  JOIN estados_tarea esu    ON taus.id_estadoT = esu.id_estadoT
             LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
             LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = taus.id_documento_entrega
             WHERE s.id_proyectos = ? AND taus.id_usuarios = ?
             ORDER BY t.id_tarea ASC",
            'ii',
            [$id_proyecto, $id_usuario]
        );
    }

    public function obtenerTareasInvestigador(int $id_proyecto, int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT
            t.id_tarea,
            tt.descripcion_tipo                         AS tipo,
            t.descripcion,
            t.fecha_entrega,
            t.fecha_modificacion,
            est.nombre                                  AS estado_plantilla,
            ds_rec.nombre                               AS archivo_nombre,
            ds_rec.ruta                                 AS archivo_ruta,

            COUNT(tu.id_asignacion)                     AS total_asignados,
            SUM(tu.id_estadoT = 1)                      AS total_pendientes,
            SUM(tu.id_estadoT = 2)                      AS total_por_revisar,
            SUM(tu.id_estadoT = 3)                      AS total_corregir,
            SUM(tu.id_estadoT = 5)                      AS total_aprobados,
            SUM(tu.id_estadoT = 6)                      AS total_vencidos,
            SUM(tu.id_estadoT = 7)                      AS total_entregados,
            SUM(tu.id_estadoT IN (2, 7))                AS total_requieren_revision

        FROM tareas t
        INNER JOIN tbl_seguimiento s     ON t.id_avances      = s.id_avances
        INNER JOIN proyectos proy        ON proy.id_proyectos  = s.id_proyectos
        INNER JOIN tipo_tarea tt         ON t.id_tareatipo     = tt.id_tareatipo
        LEFT  JOIN estados_tarea est     ON t.id_estadoT       = est.id_estadoT
        LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
        LEFT  JOIN tareas_usuarios tu    ON tu.id_tarea        = t.id_tarea
        WHERE s.id_proyectos     = ?
          AND proy.id_investigador = ?
        GROUP BY
            t.id_tarea, tt.descripcion_tipo, t.descripcion,
            t.fecha_entrega, t.fecha_modificacion,
            est.nombre, ds_rec.nombre, ds_rec.ruta
        ORDER BY t.id_tarea ASC",
            'ii',
            [$id_proyecto, $id_usuario]
        );
    }

    public function obtenerTareasSupervisor(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
            t.id_tarea,
            tt.descripcion_tipo                         AS tipo,
            t.descripcion,
            t.fecha_entrega,
            t.fecha_modificacion,
            est.nombre                                  AS estado_plantilla,
            ds_rec.nombre                               AS archivo_nombre,
            ds_rec.ruta                                 AS archivo_ruta,

            COUNT(tu.id_asignacion)                     AS total_asignados,
            SUM(tu.id_estadoT = 1)                      AS total_pendientes,
            SUM(tu.id_estadoT = 2)                      AS total_por_revisar,
            SUM(tu.id_estadoT = 3)                      AS total_corregir,
            SUM(tu.id_estadoT = 5)                      AS total_aprobados,
            SUM(tu.id_estadoT = 6)                      AS total_vencidos,
            SUM(tu.id_estadoT = 7)                      AS total_entregados,
            SUM(tu.id_estadoT IN (2, 7))                AS total_requieren_revision

        FROM tareas t
        INNER JOIN tbl_seguimiento s     ON t.id_avances   = s.id_avances
        INNER JOIN tipo_tarea tt         ON t.id_tareatipo = tt.id_tareatipo
        LEFT  JOIN estados_tarea est     ON t.id_estadoT   = est.id_estadoT
        LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
        LEFT  JOIN tareas_usuarios tu    ON tu.id_tarea    = t.id_tarea
        WHERE s.id_proyectos = ?
        GROUP BY
            t.id_tarea, tt.descripcion_tipo, t.descripcion,
            t.fecha_entrega, t.fecha_modificacion,
            est.nombre, ds_rec.nombre, ds_rec.ruta
        ORDER BY t.id_tarea ASC",
            'i',
            [$id_proyecto]
        );
    }


    // ─
    // LISTA DE ASIGNACIONES (por id_tarea)
    // ─

    public function obtenerListaInvestigador(int $id_tarea, int $id_usuario): array
    {
        $subFechas = $this->subFechasSQL();
        return $this->ejecutar(
            "SELECT
                tu.id_asignacion, tita.descripcion_tipo AS tipo,
                u.id_usuarios,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante,
                et.nombre AS estados_tarea,
                ds_ent.nombre AS archivo_nombre, ds_ent.ruta AS archivo_ruta,
                ta.id_tarea, {$subFechas}
             FROM tareas_usuarios tu
             INNER JOIN usuarios u       ON tu.id_usuarios  = u.id_usuarios
             INNER JOIN estados_tarea et ON tu.id_estadoT   = et.id_estadoT
             INNER JOIN tareas ta        ON ta.id_tarea     = tu.id_tarea
             INNER JOIN tipo_tarea tita  ON ta.id_tareatipo = tita.id_tareatipo
             INNER JOIN tbl_seguimiento s ON s.id_avances   = ta.id_avances
             INNER JOIN proyectos proy   ON proy.id_proyectos = s.id_proyectos
             LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = tu.id_documento_entrega
             WHERE tu.id_tarea = ? AND proy.id_investigador = ?
             ORDER BY estudiante ASC",
            'ii',
            [$id_tarea, $id_usuario]
        );
    }

    public function obtenerListaSupervisor(int $id_tarea): array
    {
        $subFechas = $this->subFechasSQL();
        return $this->ejecutar(
            "SELECT
                tu.id_asignacion, tita.descripcion_tipo AS tipo,
                u.id_usuarios,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante,
                et.nombre AS estados_tarea,
                ds_ent.nombre AS archivo_nombre, ds_ent.ruta AS archivo_ruta,
                ta.id_tarea, {$subFechas}
             FROM tareas_usuarios tu
             INNER JOIN usuarios u       ON tu.id_usuarios  = u.id_usuarios
             INNER JOIN estados_tarea et ON tu.id_estadoT   = et.id_estadoT
             INNER JOIN tareas ta        ON ta.id_tarea     = tu.id_tarea
             INNER JOIN tipo_tarea tita  ON ta.id_tareatipo = tita.id_tareatipo
             LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = tu.id_documento_entrega
             WHERE tu.id_tarea = ?
             ORDER BY estudiante ASC",
            'i',
            [$id_tarea]
        );
    }


    // ─
    // TAREAS POR ESTUDIANTE (vista estudiante)
    // ─

    public function obtenerTareasListaEstudiante(int $id_usuario, int $id_proyectos): array
    {
        return $this->ejecutar(
            "SELECT
                tu.id_asignacion, tu.id_estadoT, tu.id_tarea, ts.id_proyectos,
                t.fecha_entrega, t.descripcion, t.instrucciones, t.fecha_modificacion,
                CASE tu.id_estadoT
                    WHEN 1 THEN 'Pendiente' WHEN 2 THEN 'En revisión' WHEN 3 THEN 'Corregir'
                    WHEN 5 THEN 'Aprobado'  WHEN 6 THEN 'Vencido'    WHEN 7 THEN 'Entregado'
                    WHEN 8 THEN 'Borrador'  ELSE 'Desconocido'
                END AS estado_texto,
                tita.descripcion_tipo AS tipo
             FROM tareas_usuarios tu
             INNER JOIN tareas t           ON t.id_tarea     = tu.id_tarea
             INNER JOIN tipo_tarea tita    ON t.id_tareatipo = tita.id_tareatipo
             INNER JOIN tbl_seguimiento ts ON ts.id_avances  = t.id_avances
             WHERE tu.id_usuarios = ? AND ts.id_proyectos = ?
             ORDER BY tu.id_asignacion DESC",
            'ii',
            [$id_usuario, $id_proyectos]
        );
    }


    // ─
    // DOCUMENTOS
    // ─

    public function registrarDocumento(
        string  $nombre,
        string  $nombre_archivo,
        string $ruta,
        string  $tipo_mime,
        string $extension,
        int $tamano_bytes,
        string  $tipo,
        string $visibilidad,
        int $id_usuario,
        ?int    $id_proyecto,
        ?int $etapa,
        int $version
    ): int {
        $this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuario, id_proyecto, etapa, version, activo, fecha_subida)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            'sssssiissiii',
            [
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
            ]
        );
        return (int)$this->conn->insert_id;
    }


    // ─
    // EDITAR TAREA GENERAL (investigador)
    // ─

    public function obtenerDatosTareaActual(int $id_tarea): ?array
    {
        return $this->ejecutar(
            'SELECT descripcion, instrucciones, fecha_entrega FROM tareas WHERE id_tarea = ?',
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }

    public function obtenerPrimeraAsignacion(int $id_tarea): ?array
    {
        return $this->ejecutar(
            'SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? LIMIT 1',
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }

    public function actualizarTareaGeneral(
        int $id_tarea,
        string $descripcion,
        string $instrucciones,
        string $fecha_entrega,
        ?int $id_documento_recurso
    ): void {
        if ($id_documento_recurso === null) {
            $this->ejecutar(
                "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?, fecha_modificacion=NOW() WHERE id_tarea=?",
                'sssi',
                [$descripcion, $instrucciones, $fecha_entrega, $id_tarea]
            );
        } else {
            $this->ejecutar(
                "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?, id_documento_recurso=?, fecha_modificacion=NOW() WHERE id_tarea=?",
                'sssii',
                [$descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_tarea]
            );
        }
    }

    public function obtenerTodasAsignaciones(int $id_tarea): array
    {
        return $this->ejecutar('SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?', 'i', [$id_tarea]);
    }

    /**
     * Inserta filas de historial de edición para un lote de asignaciones y cambios.
     */
    public function insertarHistorialEdicion(array $asignaciones, array $cambios, int $id_usuario): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio, campo_modificado, valor_anterior, valor_nuevo)
             VALUES (?, 1, ?, 'Tarea editada por el investigador', 'edicion', ?, ?, ?)"
        );
        if (!$stmt) throw new \Exception('Error prepare (historial edicion): ' . $this->conn->error);

        foreach ($asignaciones as $asig) {
            foreach ($cambios as $c) {
                $stmt->bind_param(
                    'iisss',
                    $asig['id_asignacion'],
                    $id_usuario,
                    $c['campo_modificado'],
                    $c['valor_anterior'],
                    $c['valor_nuevo']
                );
                $stmt->execute();
            }
        }
        $stmt->close();
    }


    // ─
    // EDITAR TAREA ESTUDIANTE
    // ─

    public function actualizarAsignacion(
        int $id_asignacion,
        int $id_tarea,
        string $contenido,
        ?int $id_documento_entrega
    ): void {
        $this->ejecutar(
            "UPDATE tareas_usuarios
             SET contenido            = ?,
                 id_documento_entrega = COALESCE(?, id_documento_entrega)
             WHERE id_asignacion = ? AND id_tarea = ?",
            'siii',
            [$contenido, $id_documento_entrega, $id_asignacion, $id_tarea]
        );
    }


    // ─
    // GUARDAR BORRADOR - ALUMNO
    // ─

    /*public function guardarBorrador(
        int $id_tarea,
        int $id_asignacion,
        int $id_usuarios,
        string $contenido,
        ?int $id_documento_entrega
    ): void {
        if (!$id_asignacion) return;

        $this->ejecutar(
            "UPDATE tareas_usuarios
             SET id_estadoT           = 8,
                 contenido            = ?,
                 id_documento_entrega = COALESCE(?, id_documento_entrega)
             WHERE id_asignacion = ? AND id_tarea = ?",
            'siii',
            [$contenido, $id_documento_entrega, $id_asignacion, $id_tarea]
        );
        $this->ejecutar(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             VALUES (?, 8, ?, 'Borrador guardado por el estudiante', 'estado')",
            'ii',
            [$id_asignacion, $id_usuarios]
        );
    }*/

    // ─
    // GUARDAR BORRADOR - INVESTIGADOR
    // ─

    public function guardarBorradorInvestigador(
        int $id_tarea,
        int $id_avances,
        string $instrucciones,
        string $descripcion,
        string $fecha_entrega,
        ?int $id_documento_recurso
    ): void {
        if (!$id_tarea) return;

        $this->ejecutar(
            "UPDATE tareas
         SET id_estadoT           = 8,
             descripcion          = ?,
             instrucciones        = ?,
             fecha_entrega        = ?,
             fecha_modificacion   = NOW(),
             id_documento_recurso = COALESCE(?, id_documento_recurso)
         WHERE id_tarea = ? AND id_avances = ?",
            'sssiii',
            [$descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_tarea, $id_avances]
        );
        // Sin historial: la tarea aún no tiene id_asignacion (estado Sin activar)
        // fecha_modificacion sirve como traza suficiente en esta etapa
    }


    // ─
    // ACTUALIZAR ESTADO DE TAREA / ASIGNACIÓN
    // ─

    public function actualizarEstadoTarea(int $id_tarea, int $estado): void
    {
        $this->ejecutar('UPDATE tareas SET id_estadoT = ? WHERE id_tarea = ?', 'ii', [$estado, $id_tarea]);
    }

    public function obtenerProyectoDeTarea(int $id_tarea): ?array
    {
        return $this->ejecutar(
            "SELECT tbse.id_proyectos, tare.id_tarea
             FROM tareas tare
             JOIN tbl_seguimiento tbse ON tbse.id_avances = tare.id_avances
             WHERE tare.id_tarea = ?",
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }

    public function obtenerAlumnosActivosProyecto(int $id_proyectos): array
    {
        return $this->ejecutar(
            "SELECT id_usuarios FROM proyectos_usuarios WHERE id_proyectos = ? AND estado = 'activo'",
            'i',
            [$id_proyectos]
        );
    }

    /**
     * Inserta asignación si no existe, luego obtiene su id y registra historial.
     */
    public function activarTareaParaAlumnos(int $id_tarea, array $alumnos, int $id_usuario): void
    {
        $stmtIns = $this->conn->prepare(
            "INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
             SELECT ?, ?, 1
             WHERE NOT EXISTS (
                 SELECT 1 FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?
             )"
        );
        $stmtGet = $this->conn->prepare(
            'SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? AND id_usuarios = ?'
        );
        $stmtHist = $this->conn->prepare(
            "INSERT INTO tareas_historial (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             VALUES (?, 1, ?, 'Tarea activada', 'estado')"
        );

        foreach ($alumnos as $al) {
            $stmtIns->bind_param('iiii', $id_tarea, $al['id_usuarios'], $id_tarea, $al['id_usuarios']);
            $stmtIns->execute();

            $stmtGet->bind_param('ii', $id_tarea, $al['id_usuarios']);
            $stmtGet->execute();
            $asigRow = $stmtGet->get_result()->fetch_assoc();

            if ($asigRow && $id_usuario) {
                $id_asig = $asigRow['id_asignacion'];
                $stmtHist->bind_param('ii', $id_asig, $id_usuario);
                $stmtHist->execute();
            }
        }

        $stmtIns->close();
        $stmtGet->close();
        $stmtHist->close();
    }

    public function actualizarEstadoAsignacion(int $id_asignacion, int $estado): void
    {
        $this->ejecutar(
            'UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?',
            'ii',
            [$estado, $id_asignacion]
        );
    }

    public function actualizarEstadoAsignacionesDeTarea(int $id_tarea, int $estado): void
    {
        $this->ejecutar('UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_tarea = ?', 'ii', [$estado, $id_tarea]);
    }

    public function insertarHistorialEstado(
        int $id_asignacion,
        int $estado,
        int $id_usuario,
        string $comentario
    ): void {
        $this->ejecutar(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             VALUES (?, ?, ?, ?, 'estado')",
            'iiis',
            [$id_asignacion, $estado, $id_usuario, $comentario]
        );
    }


    // ─
    // DETALLE DE TAREA (obtener por asignación / rol)
    // ─

    private function sqlDetalleAsignacion(): string
    {
        return "SELECT
                a.id_asignacion, a.id_tarea, tbse.id_proyectos,
                ds_ent.nombre AS archivo_nombre, ds_ent.ruta AS archivo_ruta,
                ds_ent.tipo_mime AS archivo_tipo, ds_ent.extension AS archivo_extension,
                esta.nombre AS estado, a.id_estadoT,
                t.descripcion, t.instrucciones, t.fecha_entrega, t.fecha_modificacion,
                tt.descripcion_tipo AS tipo_tarea, a.contenido,
                ds_rec.nombre AS guia_nombre, ds_rec.ruta AS guia_ruta
             FROM tareas_usuarios a
             INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
             INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             INNER JOIN tipo_tarea tt        ON tt.id_tareatipo  = t.id_tareatipo
             INNER JOIN estados_tarea esta   ON esta.id_estadoT  = a.id_estadoT
             LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = a.id_documento_entrega
             LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso";
    }

    public function obtenerTareaAlumnoEstudiante(int $id_asignacion, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            $this->sqlDetalleAsignacion() . "
             WHERE a.id_asignacion = ? AND a.id_usuarios = ? AND proy.id_proyectos = ? LIMIT 1",
            'iii',
            [$id_asignacion, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }

    public function obtenerTareaAlumnoInvestigador(int $id_asignacion, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            $this->sqlDetalleAsignacion() . "
             WHERE a.id_asignacion = ? AND proy.id_investigador = ? AND proy.id_proyectos = ? LIMIT 1",
            'iii',
            [$id_asignacion, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }

    public function obtenerTareaAlumnoSupervisor(int $id_asignacion, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            $this->sqlDetalleAsignacion() . "
             WHERE a.id_asignacion = ? AND proy.id_proyectos = ? LIMIT 1",
            'ii',
            [$id_asignacion, $id_proyecto],
            false
        ) ?: null;
    }

    public function verificarTareaEstudiante(int $id_asignacion, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT 1 FROM tareas_usuarios a
             INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
             INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             WHERE a.id_asignacion = ? AND a.id_usuarios = ? AND proy.id_proyectos = ? LIMIT 1",
            'iii',
            [$id_asignacion, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }

    public function verificarTareaInvestigador(int $id_asignacion, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT 1 FROM tareas_usuarios a
             INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
             INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             WHERE a.id_asignacion = ? AND proy.id_investigador = ? AND proy.id_proyectos = ? LIMIT 1",
            'iii',
            [$id_asignacion, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }

    public function verificarTareaGeneralInvestigador(int $id_tarea, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT 1 FROM tareas t
             INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             WHERE t.id_tarea = ? AND proy.id_investigador = ? AND proy.id_proyectos = ? LIMIT 1",
            'iii',
            [$id_tarea, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }


    // ─
    // DETALLE GENERAL DE TAREA (editar/detalles)
    // ─

    private function sqlTareaGeneral(): string
    {
        return "SELECT
                    tare.id_tarea, tita.descripcion_tipo AS tipo, tita.id_tareatipo, tare.id_avances,
                    tare.descripcion, tare.instrucciones, tare.fecha_entrega,
                    tare.fecha_modificacion, tita.descripcion_tipo AS titulo_tarea,
                    esta.nombre AS estado, tare.id_estadoT,
                    ds_rec.nombre AS archivo_nombre, ds_rec.ruta AS archivo_ruta,
                    ds_rec.tipo_mime AS archivo_tipo, ds_rec.extension AS archivo_extension
                FROM tareas tare
                INNER JOIN tipo_tarea tita    ON tare.id_tareatipo = tita.id_tareatipo
                INNER JOIN estados_tarea esta ON esta.id_estadoT   = tare.id_estadoT
                LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = tare.id_documento_recurso";
    }

    public function obtenerTareaGeneralEstudiante(int $id_tarea, int $id_usuario): ?array
    {
        return $this->ejecutar(
            $this->sqlTareaGeneral() . "
             INNER JOIN tareas_usuarios taus ON taus.id_tarea = tare.id_tarea
             WHERE tare.id_tarea = ? AND taus.id_usuarios = ?",
            'ii',
            [$id_tarea, $id_usuario],
            false
        ) ?: null;
    }

    public function obtenerTareaGeneralInvestigador(int $id_tarea, int $id_usuario): ?array
    {
        return $this->ejecutar(
            $this->sqlTareaGeneral() . "
             INNER JOIN tbl_seguimiento tbse ON tbse.id_avances   = tare.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             WHERE tare.id_tarea = ? AND proy.id_investigador = ?",
            'ii',
            [$id_tarea, $id_usuario],
            false
        ) ?: null;
    }

    public function obtenerTareaGeneralSupervisor(int $id_tarea): ?array
    {
        return $this->ejecutar(
            $this->sqlTareaGeneral() . " WHERE tare.id_tarea = ?",
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }


    // ─
    // DESCARGAS
    // ─

    public function obtenerDocumentoEntrega(int $id_asignacion): ?array
    {
        return $this->ejecutar(
            "SELECT ds.id_documento, ds.nombre_archivo, ds.nombre, ds.ruta,
                    ds.tipo_mime, ds.extension, ds.activo
             FROM tareas_usuarios t
             JOIN documentos_subidos ds ON ds.id_documento = t.id_documento_entrega
             WHERE t.id_asignacion = ? AND ds.tipo = 'entrega' AND ds.activo = 1
             LIMIT 1",
            'i',
            [$id_asignacion],
            false
        ) ?: null;
    }

    public function obtenerDocumentoGuia(int $id_tarea): ?array
    {
        return $this->ejecutar(
            "SELECT ds.id_documento, ds.nombre_archivo, ds.nombre, ds.ruta,
                    ds.tipo_mime, ds.extension, ds.activo
             FROM tareas t
             JOIN documentos_subidos ds ON ds.id_documento = t.id_documento_recurso
             WHERE t.id_tarea = ? AND ds.tipo = 'recurso' AND ds.activo = 1
             LIMIT 1",
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }

    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->ejecutar(
            "SELECT pd.id_plantilla, pd.activo AS plantilla_activa,
                    ds.nombre_archivo, ds.ruta, ds.activo AS archivo_activo,
                    ds.tipo_mime, ds.extension
             FROM plantillas_documentos pd
             JOIN documentos_subidos ds ON ds.id_documento = pd.id_documento
             WHERE pd.id_plantilla = ? LIMIT 1",
            'i',
            [$id_plantilla],
            false
        ) ?: null;
    }


    // ─
    // HISTORIAL Y LÍNEA DE TIEMPO
    // ─

    public function obtenerEdicionesRecientes(int $id_tarea, int $limite = 5): array
    {
        return $this->ejecutar(
            "SELECT th.campo_modificado, th.valor_anterior, th.valor_nuevo, th.fecha, u.nombre AS editor
             FROM tareas_historial th
             LEFT JOIN usuarios u ON u.id_usuarios = th.id_usuarios
             WHERE th.tipo_cambio = 'edicion'
               AND th.id_asignacion IN (SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?)
             ORDER BY th.fecha DESC
             LIMIT ?",
            'ii',
            [$id_tarea, $limite]
        );
    }

    public function contarHistorialEstado(int $id_asignacion): int
    {
        return (int)($this->ejecutar(
            "SELECT COUNT(*) AS total FROM tareas_historial WHERE id_asignacion = ? AND tipo_cambio = 'estado'",
            'i',
            [$id_asignacion],
            false
        )['total'] ?? 0);
    }

    public function obtenerHistorialEstado(int $id_asignacion, int $desde, int $por_pagina): array
    {
        return $this->ejecutar(
            "SELECT tahi.id_tareas_historial, tahi.id_estadoT,
                    et.nombre AS estado,
                    CASE WHEN e.id_usuarios IS NOT NULL THEN 1 ELSE 0 END AS esEstudiante,
                    tahi.comentario, tahi.fecha, u.nombre AS usuario
             FROM tareas_historial tahi
             LEFT JOIN estados_tarea et ON et.id_estadoT = tahi.id_estadoT
             LEFT JOIN estudiantes e    ON e.id_usuarios = tahi.id_usuarios
             LEFT JOIN usuarios u       ON u.id_usuarios = tahi.id_usuarios
             WHERE tahi.id_asignacion = ? AND tahi.tipo_cambio = 'estado'
             ORDER BY tahi.fecha DESC
             LIMIT ?, ?",
            'iii',
            [$id_asignacion, $desde, $por_pagina]
        );
    }


    // ─
    // HELPER PRIVADO
    // ─

    private function subFechasSQL(): string
    {
        return "(SELECT MAX(th.fecha) FROM tareas_historial th
                 WHERE th.id_asignacion = tu.id_asignacion AND th.tipo_cambio = 'estado' AND th.id_estadoT = 2) AS fecha_revision,
                (SELECT MAX(th.fecha) FROM tareas_historial th
                 WHERE th.id_asignacion = tu.id_asignacion AND th.tipo_cambio = 'estado' AND th.id_estadoT = 3) AS fecha_correccion,
                (SELECT MAX(th.fecha) FROM tareas_historial th
                 WHERE th.id_asignacion = tu.id_asignacion AND th.tipo_cambio = 'estado' AND th.id_estadoT = 5) AS fecha_aprobacion";
    }

    public function listarPeriodoActual(): array
    {
        return $this->ejecutar(
            "SELECT id_periodos, periodo,
                    fecha_inicio AS FechaInicio, fecha_final AS FechaFinal,
                    CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado, fecha_inicio_proyectos, fecha_fin_proyectos
             FROM periodos ORDER BY periodo DESC LIMIT 1"
        );
    }

    public function obtenerProyectoPorTarea(int $id_tarea)
    {
        return $this->ejecutar(
            "SELECT fecha_inicio, fecha_fin
             FROM tareas AS ta
             JOIN tbl_seguimiento AS tbse ON ta.id_avances = tbse.id_avances
             JOIN proyectos AS proy ON tbse.id_proyectos = proy.id_proyectos
             WHERE ta.id_tarea = ?",
            'i',
            [$id_tarea],
            false
        );
    }
}
