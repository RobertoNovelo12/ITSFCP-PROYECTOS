<?php
// Modelos/tareas.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class Tarea extends BaseModelo
{

    // 
    // ACTUALIZAR VENCIDOS
    // 

    public function actualizarTareasVencidos(): void
    {
        $hoy = date('Y-m-d');

        $vencidos = $this->ejecutar(
            "SELECT taus.id_asignacion
             FROM tareas_usuarios taus
             JOIN tareas tare ON taus.id_tarea = tare.id_tarea
             WHERE taus.id_estadoT IN (1, 2, 3, 8)
               AND tare.fecha_entrega < ?",
            's',
            [$hoy]
        );

        if (empty($vencidos)) return;

        $this->ejecutar(
            "UPDATE tareas_usuarios taus
             JOIN tareas tare ON taus.id_tarea = tare.id_tarea
             SET taus.id_estadoT = 6
             WHERE taus.id_estadoT IN (1, 2, 3, 8)
               AND tare.fecha_entrega < ?",
            's',
            [$hoy]
        );

        $stmt = $this->conn->prepare(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
             VALUES (?, 6, 1, 'Tarea marcada como vencida automáticamente', 'estado')"
        );
        if (!$stmt) return;
        foreach ($vencidos as $v) {
            $stmt->bind_param('i', $v['id_asignacion']);
            $stmt->execute();
        }
        $stmt->close();
    }

    // 
    // ACTUALIZAR CONCLUIDAS
    //
    // Una tarea pasa a estado 9 (Concluido) cuando TODOS los estudiantes
    // ACTIVOS del proyecto (proyectos_usuarios.estado = 'activo') que tienen
    // asignación tienen id_estadoT = 5 (Aprobado).
    // 

    public function actualizarTareasConcluidas(?int $id_tarea = null): void
    {
        if ($id_tarea !== null) {
            $tareas = $this->ejecutar(
                "SELECT t.id_tarea, tbse.id_proyectos
                 FROM tareas t
                 JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
                 WHERE t.id_tarea = ? AND t.id_estadoT IN (1, 2, 3)",
                'i',
                [$id_tarea]
            );
        } else {
            $tareas = $this->ejecutar(
                "SELECT t.id_tarea, tbse.id_proyectos
                 FROM tareas t
                 JOIN tbl_seguimiento tbse ON tbse.id_avances = t.id_avances
                 WHERE t.id_estadoT IN (1, 2, 3)"
            );
        }

        if (empty($tareas)) return;

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

    // 
    // OBTENER TAREAS (tabla principal index)
    // 

    public function obtenerTareas(int $id_proyecto, int $id_usuario, string $rol): array
    {
        switch ($rol) {

            case 'estudiante':
                return $this->ejecutar(
                    "SELECT
                        t.id_tarea,
                        taus.id_asignacion,
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

            case 'investigador':
            case 'profesor':
                return $this->ejecutar(
                    "SELECT
                        t.id_tarea,
                        tt.descripcion_tipo          AS tipo,
                        ds_rec.nombre               AS archivo_nombre,
                        ds_rec.ruta                 AS archivo_ruta,
                        t.fecha_entrega,
                        t.fecha_modificacion,
                        est.nombre                  AS estado_plantilla,
                        (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea) AS total_asignados,
                        (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea
                         AND tu.id_documento_entrega IS NOT NULL) AS total_entregados
                     FROM tareas t
                     INNER JOIN tbl_seguimiento s ON t.id_avances      = s.id_avances
                     INNER JOIN proyectos proy    ON proy.id_proyectos  = s.id_proyectos
                     INNER JOIN tipo_tarea tt     ON t.id_tareatipo     = tt.id_tareatipo
                     LEFT  JOIN estados_tarea est ON t.id_estadoT       = est.id_estadoT
                     LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
                     WHERE s.id_proyectos = ? AND proy.id_investigador = ?
                     ORDER BY t.id_tarea ASC",
                    'ii',
                    [$id_proyecto, $id_usuario]
                );

            case 'supervisor':
                return $this->ejecutar(
                    "SELECT
                        t.id_tarea,
                        tt.descripcion_tipo          AS tipo,
                        ds_rec.nombre               AS archivo_nombre,
                        ds_rec.ruta                 AS archivo_ruta,
                        t.fecha_entrega,
                        t.fecha_modificacion,
                        est.nombre                  AS estado_plantilla,
                        (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea) AS total_asignados,
                        (SELECT COUNT(*) FROM tareas_usuarios tu WHERE tu.id_tarea = t.id_tarea
                         AND tu.id_documento_entrega IS NOT NULL) AS total_entregados
                     FROM tareas t
                     INNER JOIN tbl_seguimiento s ON t.id_avances    = s.id_avances
                     INNER JOIN tipo_tarea tt     ON t.id_tareatipo  = tt.id_tareatipo
                     LEFT  JOIN estados_tarea est ON t.id_estadoT    = est.id_estadoT
                     LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
                     WHERE s.id_proyectos = ?
                     ORDER BY t.id_tarea ASC",
                    'i',
                    [$id_proyecto]
                );

            default:
                return [];
        }
    }

    // 
    // OBTENER TAREAS LISTA (por id_tarea)
    // 

    public function obtenerTareasLista(int $id_tarea, string $rol, int $id_usuario): array
    {
        $subFechas = "
            (SELECT MAX(th.fecha)
             FROM tareas_historial th
             WHERE th.id_asignacion = tu.id_asignacion
               AND th.tipo_cambio = 'estado'
               AND th.id_estadoT = 2) AS fecha_revision,
            (SELECT MAX(th.fecha)
             FROM tareas_historial th
             WHERE th.id_asignacion = tu.id_asignacion
               AND th.tipo_cambio = 'estado'
               AND th.id_estadoT = 3) AS fecha_correccion,
            (SELECT MAX(th.fecha)
             FROM tareas_historial th
             WHERE th.id_asignacion = tu.id_asignacion
               AND th.tipo_cambio = 'estado'
               AND th.id_estadoT = 5) AS fecha_aprobacion";

        switch ($rol) {
            case 'investigador':
            case 'profesor':
                return $this->ejecutar(
                    "SELECT
                        tu.id_asignacion,
                        tita.descripcion_tipo        AS tipo,
                        u.id_usuarios,
                        CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante,
                        et.nombre                   AS estados_tarea,
                        ds_ent.nombre               AS archivo_nombre,
                        ds_ent.ruta                 AS archivo_ruta,
                        ta.id_tarea,
                        {$subFechas}
                     FROM tareas_usuarios tu
                     INNER JOIN usuarios u       ON tu.id_usuarios   = u.id_usuarios
                     INNER JOIN estados_tarea et ON tu.id_estadoT    = et.id_estadoT
                     INNER JOIN tareas ta        ON ta.id_tarea      = tu.id_tarea
                     INNER JOIN tipo_tarea tita  ON ta.id_tareatipo  = tita.id_tareatipo
                     INNER JOIN tbl_seguimiento s ON s.id_avances    = ta.id_avances
                     INNER JOIN proyectos proy   ON proy.id_proyectos = s.id_proyectos
                     LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = tu.id_documento_entrega
                     WHERE tu.id_tarea = ? AND proy.id_investigador = ?
                     ORDER BY estudiante ASC",
                    'ii',
                    [$id_tarea, $id_usuario]
                );

            case 'supervisor':
                return $this->ejecutar(
                    "SELECT
                        tu.id_asignacion,
                        tita.descripcion_tipo        AS tipo,
                        u.id_usuarios,
                        CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante,
                        et.nombre                   AS estados_tarea,
                        ds_ent.nombre               AS archivo_nombre,
                        ds_ent.ruta                 AS archivo_ruta,
                        ta.id_tarea,
                        {$subFechas}
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

            default:
                return [];
        }
    }

    // 
    // OBTENER TAREAS ESTUDIANTE
    // 

    public function obtenerTareasEstudiante(int $id_usuario, int $id_proyectos): array
    {
        return $this->ejecutar(
            "SELECT
                tu.id_asignacion,
                tu.id_estadoT,
                tu.id_tarea,
                ts.id_proyectos,
                t.fecha_entrega,
                t.descripcion,
                t.instrucciones,
                t.fecha_modificacion,
                CASE tu.id_estadoT
                    WHEN 1 THEN 'Pendiente'
                    WHEN 2 THEN 'En revisión'
                    WHEN 3 THEN 'Corregir'
                    WHEN 5 THEN 'Aprobado'
                    WHEN 6 THEN 'Vencido'
                    WHEN 7 THEN 'Entregado'
                    WHEN 8 THEN 'Borrador'
                    ELSE 'Desconocido'
                END AS estado_texto,
                tita.descripcion_tipo AS tipo
             FROM tareas_usuarios tu
             INNER JOIN tareas t             ON t.id_tarea     = tu.id_tarea
             INNER JOIN tipo_tarea tita      ON t.id_tareatipo = tita.id_tareatipo
             INNER JOIN tbl_seguimiento ts   ON ts.id_avances  = t.id_avances
             WHERE tu.id_usuarios = ? AND ts.id_proyectos = ?
             ORDER BY tu.id_asignacion DESC",
            'ii',
            [$id_usuario, $id_proyectos]
        );
    }

    // 
    // REGISTRAR DOCUMENTO
    //
    // String de tipos corregido: 'sssssiissiii' (12 posiciones, 's' para tipo y visibilidad)
    // 

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

        $this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuario, id_proyecto, etapa, version, activo, fecha_subida)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            'sssssiissiii',
            [
                $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
                $tamano_bytes, $tipo, $visibilidad, $id_usuario,
                $id_proyecto, $etapa, $version,
            ]
        );

        return (int)$this->conn->insert_id;
    }

    // 
    // EDITAR TAREA GENERAL (investigador)
    // 

    public function editarTareaGeneral(
        int     $id_tarea,
        string  $descripcion,
        string  $instrucciones,
        string  $fecha_entrega,
        ?int    $id_documento_recurso,
        int     $id_usuario
    ): void {
        $actual = $this->ejecutar(
            'SELECT descripcion, instrucciones, fecha_entrega FROM tareas WHERE id_tarea = ?',
            'i',
            [$id_tarea],
            false
        );

        $primeraAsig = $this->ejecutar(
            'SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ? LIMIT 1',
            'i',
            [$id_tarea],
            false
        );

        if ($id_documento_recurso === null) {
            $this->ejecutar(
                "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?,
                 fecha_modificacion=NOW() WHERE id_tarea=?",
                'sssi',
                [$descripcion, $instrucciones, $fecha_entrega, $id_tarea]
            );
        } else {
            $this->ejecutar(
                "UPDATE tareas SET descripcion=?, instrucciones=?, fecha_entrega=?,
                 id_documento_recurso=?, fecha_modificacion=NOW() WHERE id_tarea=?",
                'sssii',
                [$descripcion, $instrucciones, $fecha_entrega, $id_documento_recurso, $id_tarea]
            );
        }

        if (!$primeraAsig) return;

        $todasAsig = $this->ejecutar(
            'SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?',
            'i',
            [$id_tarea]
        );

        $cambios = [];
        if ($actual['descripcion']   !== $descripcion)   $cambios[] = ['campo_modificado' => 'descripcion',   'valor_anterior' => $actual['descripcion'],   'valor_nuevo' => $descripcion];
        if ($actual['instrucciones'] !== $instrucciones) $cambios[] = ['campo_modificado' => 'instrucciones', 'valor_anterior' => $actual['instrucciones'], 'valor_nuevo' => $instrucciones];
        if ($actual['fecha_entrega'] !== $fecha_entrega) $cambios[] = ['campo_modificado' => 'fecha_entrega', 'valor_anterior' => $actual['fecha_entrega'], 'valor_nuevo' => $fecha_entrega];
        if ($id_documento_recurso !== null)               $cambios[] = ['campo_modificado' => 'archivo_guia',  'valor_anterior' => null,                    'valor_nuevo' => 'Nuevo archivo subido'];

        if (empty($cambios)) return;

        $stmtH = $this->conn->prepare(
            "INSERT INTO tareas_historial
                (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio, campo_modificado, valor_anterior, valor_nuevo)
             VALUES (?, 1, ?, 'Tarea editada por el investigador', 'edicion', ?, ?, ?)"
        );
        if (!$stmtH) throw new \Exception('Error prepare (historial edicion): ' . $this->conn->error);

        foreach ($todasAsig as $asig) {
            foreach ($cambios as $c) {
                $stmtH->bind_param(
                    'iisss',
                    $asig['id_asignacion'],
                    $id_usuario,
                    $c['campo_modificado'],
                    $c['valor_anterior'],
                    $c['valor_nuevo']
                );
                $stmtH->execute();
            }
        }
        $stmtH->close();
    }

    // 
    // EDITAR TAREA ESTUDIANTE
    // 

    public function editarTareaEstudiante(
        int    $id_asignacion,
        int    $id_tarea,
        string $contenido,
        string $comentarios,
        ?int   $id_documento_entrega
    ): bool {
        $this->ejecutar(
            "UPDATE tareas_usuarios
             SET contenido            = ?,
                 id_documento_entrega = COALESCE(?, id_documento_entrega)
             WHERE id_asignacion = ? AND id_tarea = ?",
            'siii',
            [$contenido, $id_documento_entrega, $id_asignacion, $id_tarea]
        );
        return true;
    }

    // 
    // GUARDAR BORRADOR (estado 8)
    // 

    public function guardar_borrador(
        int    $id_tarea,
        int    $id_asignacion,
        int    $id_usuarios,
        string $contenido,
        string $comentarios          = '',
        ?int   $id_documento_entrega = null
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
    }

    // 
    // ACTUALIZAR ESTADO
    //
    // CORRECCIÓN estado 1 (Activar):
    //   El original filtraba 'SELECT id_usuarios FROM proyectos_usuarios'
    //   sin el filtro estado = 'activo', asignando la tarea también a alumnos
    //   dados de baja. Ahora solo incluye alumnos activos.
    // 

    public function actualizarestado(
        int    $id_tarea,
        int    $numeroEstado,
        int    $id_proyectos,
        int    $id_asignacion,
        int    $id_usuarios,
        string $comentario
    ): void {
        if ($numeroEstado === 0) {
            error_log("actualizarestado (modelo): numeroEstado=0, se ignora.");
            return;
        }

        // ── ESTADO 1 — ACTIVAR TAREA ──
        if ($numeroEstado === 1) {

            $this->ejecutar(
                'UPDATE tareas SET id_estadoT = ? WHERE id_tarea = ?',
                'ii',
                [$numeroEstado, $id_tarea]
            );

            $proy = $this->ejecutar(
                "SELECT tbse.id_proyectos, tare.id_tarea
                 FROM tareas tare
                 JOIN tbl_seguimiento tbse ON tbse.id_avances = tare.id_avances
                 WHERE tare.id_tarea = ?",
                'i',
                [$id_tarea],
                false
            );
            if (!$proy) {
                error_log("actualizarestado: no se encontró proyecto para id_tarea={$id_tarea}");
                return;
            }

            $id_proyectos = (int)$proy['id_proyectos'];
            $id_tarea     = (int)$proy['id_tarea'];

            // CORRECCIÓN: solo alumnos con estado = 'activo' en el proyecto
            $alumnos = $this->ejecutar(
                "SELECT id_usuarios
                 FROM proyectos_usuarios
                 WHERE id_proyectos = ? AND estado = 'activo'",
                'i',
                [$id_proyectos]
            );

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

                if ($asigRow && $id_usuarios) {
                    $id_asig_nuevo = $asigRow['id_asignacion'];
                    $stmtHist->bind_param('ii', $id_asig_nuevo, $id_usuarios);
                    $stmtHist->execute();
                }
            }

            $stmtIns->close();
            $stmtGet->close();
            $stmtHist->close();
            return;
        }

        // ── ESTADOS 2 (Revisar), 3 (Corregir), 5 (Aprobado) ──
        if (!in_array($numeroEstado, [2, 3, 5], true)) {
            error_log("actualizarestado (modelo): estado no válido recibido: {$numeroEstado}");
            return;
        }

        if ($id_asignacion !== 0) {
            $this->ejecutar(
                'UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?',
                'ii',
                [$numeroEstado, $id_asignacion]
            );
            $this->ejecutar(
                "INSERT INTO tareas_historial
                    (id_asignacion, id_estadoT, id_usuarios, comentario, tipo_cambio)
                 VALUES (?, ?, ?, ?, 'estado')",
                'iiis',
                [$id_asignacion, $numeroEstado, $id_usuarios, $comentario]
            );
        } else {
            $this->ejecutar(
                'UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_tarea = ?',
                'ii',
                [$numeroEstado, $id_tarea]
            );
        }
    }

    // 
    // OBTENER TAREA ALUMNO
    // 

    public function obtenerTareaAlumno(int $id_asignacion, int $id_usuario, int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT
                a.id_asignacion,
                a.id_tarea,
                tbse.id_proyectos,
                ds_ent.nombre               AS archivo_nombre,
                ds_ent.ruta                 AS archivo_ruta,
                ds_ent.tipo_mime            AS archivo_tipo,
                ds_ent.extension            AS archivo_extension,
                esta.nombre                 AS estado,
                a.id_estadoT,
                t.descripcion,
                t.instrucciones,
                t.fecha_entrega,
                t.fecha_modificacion,
                tt.descripcion_tipo         AS tipo_tarea,
                a.contenido,
                ds_rec.nombre               AS guia_nombre,
                ds_rec.ruta                 AS guia_ruta
             FROM tareas_usuarios a
             INNER JOIN tareas t             ON t.id_tarea       = a.id_tarea
             INNER JOIN tbl_seguimiento tbse ON t.id_avances     = tbse.id_avances
             INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
             INNER JOIN tipo_tarea tt        ON tt.id_tareatipo  = t.id_tareatipo
             INNER JOIN estados_tarea esta   ON esta.id_estadoT  = a.id_estadoT
             LEFT  JOIN documentos_subidos ds_ent ON ds_ent.id_documento = a.id_documento_entrega
             LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = t.id_documento_recurso
             WHERE a.id_asignacion = ? AND proy.id_investigador = ? AND proy.id_proyectos = ?
             LIMIT 1",
            'iii',
            [$id_asignacion, $id_usuario, $id_proyecto],
            false
        ) ?: null;
    }

    // 
    // OBTENER TAREA GENERAL (editar / detalles)
    // 

    public function obtenerTareaGeneral(int $id_tarea, string $rol, int $id_usuario): ?array
    {
        $camposBase = "
            tare.id_tarea,
            tita.descripcion_tipo       AS tipo,
            tita.id_tareatipo,
            tare.descripcion,
            tare.instrucciones,
            tare.fecha_entrega,
            tare.fecha_modificacion,
            tita.descripcion_tipo       AS titulo_tarea,
            esta.nombre                 AS estado,
            tare.id_estadoT,
            ds_rec.nombre               AS archivo_nombre,
            ds_rec.ruta                 AS archivo_ruta,
            ds_rec.tipo_mime            AS archivo_tipo,
            ds_rec.extension            AS archivo_extension";

        $joinBase = "
            INNER JOIN tipo_tarea tita      ON tare.id_tareatipo = tita.id_tareatipo
            INNER JOIN estados_tarea esta   ON esta.id_estadoT   = tare.id_estadoT
            LEFT  JOIN documentos_subidos ds_rec ON ds_rec.id_documento = tare.id_documento_recurso";

        switch ($rol) {
            case 'estudiante':
                return $this->ejecutar(
                    "SELECT {$camposBase}
                     FROM tareas tare
                     {$joinBase}
                     INNER JOIN tareas_usuarios taus ON taus.id_tarea = tare.id_tarea
                     WHERE tare.id_tarea = ? AND taus.id_usuarios = ?",
                    'ii',
                    [$id_tarea, $id_usuario],
                    false
                ) ?: null;

            case 'investigador':
            case 'profesor':
                return $this->ejecutar(
                    "SELECT {$camposBase}
                     FROM tareas tare
                     {$joinBase}
                     INNER JOIN tbl_seguimiento tbse ON tbse.id_avances   = tare.id_avances
                     INNER JOIN proyectos proy       ON proy.id_proyectos = tbse.id_proyectos
                     WHERE tare.id_tarea = ? AND proy.id_investigador = ?",
                    'ii',
                    [$id_tarea, $id_usuario],
                    false
                ) ?: null;

            case 'supervisor':
                return $this->ejecutar(
                    "SELECT {$camposBase}
                     FROM tareas tare
                     {$joinBase}
                     WHERE tare.id_tarea = ?",
                    'i',
                    [$id_tarea],
                    false
                ) ?: null;

            default:
                return null;
        }
    }

    // 
    // OBTENER TAREA POR ASIGNACIÓN (descarga entrega)
    // 

    public function obtenerTareaPorId(int $id_asignacion): ?array
    {
        return $this->ejecutar(
            "SELECT
                ds.id_documento,
                ds.nombre_archivo,
                ds.nombre,
                ds.ruta,
                ds.tipo_mime,
                ds.extension,
                ds.activo
             FROM tareas_usuarios t
             JOIN documentos_subidos ds ON ds.id_documento = t.id_documento_entrega
             WHERE t.id_asignacion = ? AND ds.tipo = 'entrega' AND ds.activo = 1
             LIMIT 1",
            'i',
            [$id_asignacion],
            false
        ) ?: null;
    }

    // 
    // OBTENER GUÍA DE TAREA (descarga recurso)
    //
    // Devuelve null si no hay guía; el controlador/vista decide qué hacer.
    // 

    public function obtenerTareaGuiaPorId(int $id_tarea): ?array
    {
        return $this->ejecutar(
            "SELECT
                ds.id_documento,
                ds.nombre_archivo,
                ds.nombre,
                ds.ruta,
                ds.tipo_mime,
                ds.extension,
                ds.activo
             FROM tareas t
             JOIN documentos_subidos ds ON ds.id_documento = t.id_documento_recurso
             WHERE t.id_tarea = ? AND ds.tipo = 'recurso' AND ds.activo = 1
             LIMIT 1",
            'i',
            [$id_tarea],
            false
        ) ?: null;
    }

    // 
    // EDICIONES RECIENTES
    // 

    public function obtenerEdicionesRecientes(int $id_tarea, int $limite = 5): array
    {
        return $this->ejecutar(
            "SELECT
                th.campo_modificado,
                th.valor_anterior,
                th.valor_nuevo,
                th.fecha,
                u.nombre AS editor
             FROM tareas_historial th
             LEFT JOIN usuarios u ON u.id_usuarios = th.id_usuarios
             WHERE th.tipo_cambio = 'edicion'
               AND th.id_asignacion IN (
                   SELECT id_asignacion FROM tareas_usuarios WHERE id_tarea = ?
               )
             ORDER BY th.fecha DESC
             LIMIT ?",
            'ii',
            [$id_tarea, $limite]
        );
    }

    // 
    // LÍNEA DE TIEMPO
    // 

    public function linea_tiempo_tarea(int $id_asignacion, int $pagina = 1, int $por_pagina = 6): array
    {
        $pagina = max(1, $pagina);
        $desde  = ($pagina - 1) * $por_pagina;

        $total = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM tareas_historial
             WHERE id_asignacion = ? AND tipo_cambio = 'estado'",
            'i',
            [$id_asignacion],
            false
        )['total'] ?? 0);

        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $historial = $this->ejecutar(
            "SELECT
                tahi.id_tareas_historial,
                tahi.id_estadoT,
                et.nombre                   AS estado,
                CASE WHEN e.id_usuarios IS NOT NULL THEN 1 ELSE 0 END AS esEstudiante,
                tahi.comentario,
                tahi.fecha,
                u.nombre AS usuario
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

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado[date('d/m/Y', strtotime($item['fecha']))][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => compact('total', 'por_pagina', 'pagina', 'total_paginas'),
        ];
    }

    // 
    // OBTENER PLANTILLA POR ID (descarga)
    // 

    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->ejecutar(
            "SELECT
                pd.id_plantilla,
                pd.activo          AS plantilla_activa,
                ds.nombre_archivo,
                ds.ruta,
                ds.activo          AS archivo_activo,
                ds.tipo_mime,
                ds.extension
             FROM plantillas_documentos pd
             JOIN documentos_subidos ds ON ds.id_documento = pd.id_documento
             WHERE pd.id_plantilla = ?
             LIMIT 1",
            'i',
            [$id_plantilla],
            false
        ) ?: null;
    }
}