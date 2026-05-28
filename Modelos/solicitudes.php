<?php
// Modelos/solicitudes.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

/**
 * Modelo Solicitud de integración a proyecto
 *
 *  SECCIÓN A  │  Módulo del INVESTIGADOR
 *  SECCIÓN B  │  Módulo del ESTUDIANTE
 */
class Solicitud extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // UTILIDADES INTERNAS
    // 

    /**
     * Inserta un comentario en solicitud_comentarios.
     */
    private function insertarComentario(
        int    $id_solicitud,
        int    $id_usuario,
        string $tipo,
        string $comentario,
        ?int   $id_documento = null
    ): bool {
        $this->ejecutar(
            "INSERT INTO solicitud_comentarios
                (id_solicitud, id_usuario, tipo, comentario, id_documento_adjunto, fecha)
             VALUES (?, ?, ?, ?, ?, NOW())",
            "iissi",
            [$id_solicitud, $id_usuario, $tipo, $comentario, $id_documento]
        );
        return true;
    }


    // 
    // SECCIÓN A — INVESTIGADOR
    // 

    //  Archivos / documentos 

    /**
     * Registra un archivo en documentos_subidos y devuelve su id_documento.
     *
     * CORRECCIÓN: usa el parámetro $visibilidad recibido, no $_POST.
     */
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
        int     $id_proyecto    = 0,
        ?int    $id_etapa       = null,
        int     $version        = 1,
        ?int    $id_plantilla   = null,
        ?int    $id_seguimiento = null
    ): int {
        $id_proyecto_val = $id_proyecto ?: null;

        $visibilidad = strtolower(trim($visibilidad));
        if (!in_array($visibilidad, ['publico', 'privado'], true)) {
            throw new Exception("Visibilidad inválida");
        }

        $id = (int)$this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa, version,
                 activo, fecha_subida, id_plantilla, id_seguimiento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)",
            "sssssisisiiiii",
            [
                $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension, $tamano_bytes,
                $tipo, $visibilidad, $id_usuario, $id_proyecto_val,
                $id_etapa, $version, $id_plantilla, $id_seguimiento,
            ]
        );

        if ($id === 0) {
            throw new Exception("No se pudo registrar el documento en documentos_subidos");
        }

        return $id;
    }

    //  Periodos 

    public function periodosDelInvestigador(int $id): array
    {
        return $this->ejecutar(
            "SELECT DISTINCT
                pe.id_periodos,
                pe.periodo,
                pe.estado
             FROM solicitud_proyecto sp
             JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
             JOIN periodos  pe ON pe.id_periodos  = p.id_periodos
             WHERE p.id_investigador = ?
               AND pe.periodo IS NOT NULL
             ORDER BY pe.periodo DESC",
            "i",
            [$id]
        );
    }

    //  Resumen / listado 

    public function resumen(int $id, ?int $id_periodo = null): array
    {
        $sql    = "SELECT
                    COUNT(*)                          total,
                    SUM(sp.estado='pendiente')        pendientes,
                    SUM(sp.estado='en_revision')      en_revision,
                    SUM(sp.estado='correcciones')     correcciones,
                    SUM(sp.estado='aceptado')         aceptadas,
                    SUM(sp.estado='rechazado')        rechazadas
                FROM solicitud_proyecto sp
                JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
                WHERE p.id_investigador = ?";

        $params = [$id];
        $types  = "i";

        if ($id_periodo) {
            $sql    .= " AND p.id_periodos = ?";
            $params[] = $id_periodo;
            $types   .= "i";
        }

        return $this->ejecutar($sql, $types, $params, false) ?? [];
    }

    public function contarSolicitudes(int $id, array $f): int
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);
        $sql = "SELECT COUNT(*)
                FROM solicitud_proyecto sp
                JOIN proyectos p   ON p.id_proyectos  = sp.id_proyectos
                JOIN usuarios u    ON u.id_usuarios   = sp.id_estudiante
                JOIN estudiantes e ON e.id_usuarios   = sp.id_estudiante
                JOIN carreras c    ON c.id_carrera    = e.id_carrera
                $where";

        $fila = $this->ejecutar($sql, $types, $params, false);
        return (int)array_values($fila)[0];
    }

    public function obtenerSolicitudes(int $id, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);
        $sql = "SELECT
                    sp.*,
                    CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) estudiante_nombre,
                    u.correo_institucional,
                    e.matricula,
                    c.nombre_carrera carrera,
                    p.titulo proyecto_titulo
                FROM solicitud_proyecto sp
                JOIN proyectos p   ON p.id_proyectos  = sp.id_proyectos
                JOIN usuarios u    ON u.id_usuarios   = sp.id_estudiante
                JOIN estudiantes e ON e.id_usuarios   = sp.id_estudiante
                JOIN carreras c    ON c.id_carrera    = e.id_carrera
                $where
                ORDER BY sp.fecha_envio ASC
                LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";

        return $this->ejecutar($sql, $types, $params);
    }

    private function construirWhere(int $id, array $f): array
    {
        $cond   = ["p.id_investigador = ?"];
        $params = [$id];
        $types  = "i";

        if (!empty($f['periodo'])) {
            $cond[]   = "p.id_periodos = ?";
            $params[] = (int)$f['periodo'];
            $types   .= "i";
        }
        if (!empty($f['estado'])) {
            $cond[]   = "sp.estado = ?";
            $params[] = $f['estado'];
            $types   .= "s";
        }
        if (!empty($f['buscar'])) {
            $cond[]   = "(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)";
            $b        = "%" . $f['buscar'] . "%";
            array_push($params, $b, $b, $b);
            $types .= "sss";
        }
        if (!empty($f['proyecto'])) {
            $cond[]   = "sp.id_proyectos = ?";
            $params[] = (int)$f['proyecto'];
            $types   .= "i";
        }
        if (!empty($f['semestre'])) {
            $cond[]   = "e.semestre = ?";
            $params[] = (int)$f['semestre'];
            $types   .= "i";
        }
        if (!empty($f['fecha_desde'])) {
            $cond[]   = "sp.fecha_envio >= ?";
            $params[] = $f['fecha_desde'];
            $types   .= "s";
        }
        if (!empty($f['fecha_hasta'])) {
            $cond[]   = "sp.fecha_envio <= ?";
            $params[] = $f['fecha_hasta'];
            $types   .= "s";
        }

        return ["WHERE " . implode(" AND ", $cond), $params, $types];
    }

    //  Detalle para el investigador 

    public function obtenerDetalle(int $id): ?array
    {
        return $this->ejecutar(
            "SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera          AS carrera,
                p.titulo                  AS proyecto_titulo,
                p.modalidad,
                p.id_investigador,
                ds_cv.nombre              AS cv_nombre,
                ds_cv.ruta                AS cv_ruta,
                ds_cv.extension           AS cv_extension,
                sd.id_seguimiento         AS seg_id,
                sd.estado                 AS seg_estado,
                sd.fecha_revision         AS seg_fecha_revision,
                sd.comentario_supervisor  AS seg_comentario,
                ds_carta.id_documento     AS carta_id_documento,
                ds_carta.nombre           AS carta_nombre,
                ds_carta.ruta             AS carta_ruta,
                ds_carta.extension        AS carta_extension,
                ds_carta.fecha_subida     AS carta_fecha_subida,
                pd.id_plantilla           AS plantilla_id,
                pd.nombre                 AS plantilla_nombre,
                pd.version                AS plantilla_version,
                ds_plantilla.ruta         AS plantilla_ruta,
                ds_plantilla.nombre_archivo AS plantilla_archivo
             FROM solicitud_proyecto sp
             JOIN proyectos p           ON p.id_proyectos      = sp.id_proyectos
             JOIN usuarios u            ON u.id_usuarios       = sp.id_estudiante
             JOIN estudiantes e         ON e.id_usuarios       = sp.id_estudiante
             JOIN carreras c            ON c.id_carrera        = e.id_carrera
             LEFT JOIN documentos_subidos ds_cv
                    ON ds_cv.id_documento = sp.id_documento
             LEFT JOIN seguimiento_documento sd
                    ON sd.id_proyectos      = sp.id_proyectos
                   AND sd.id_usuarios       = sp.id_estudiante
                   AND sd.id_tipo_documento = 1
             LEFT JOIN documentos_subidos ds_carta
                    ON ds_carta.id_seguimiento = sd.id_seguimiento
                   AND ds_carta.activo         = 1
                   AND ds_carta.tipo           = 'etapa'
             LEFT JOIN plantillas_documentos pd
                    ON pd.id_tipo_documento = 1
                   AND pd.activo            = 1
             LEFT JOIN documentos_subidos ds_plantilla
                    ON ds_plantilla.id_documento = pd.id_documento
             WHERE sp.id_solicitud_proyecto = ?
             LIMIT 1",
            "i",
            [$id],
            false
        ) ?: null;
    }

    //  Permiso 

    public function verificarPermiso(int $id, int $inv): bool
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS cnt
             FROM solicitud_proyecto sp
             JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             WHERE sp.id_solicitud_proyecto = ? AND p.id_investigador = ?",
            "ii",
            [$id, $inv],
            false
        );
        return (int)($fila['cnt'] ?? 0) > 0;
    }

    //  Cambios de estado 

    public function marcarEnRevision(int $id): void
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'en_revision'
             WHERE id_solicitud_proyecto = ? AND estado = 'pendiente'",
            "i",
            [$id]
        );
    }

    public function aceptar(int $id): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'aceptado', fecha_respuesta = CURDATE()
             WHERE id_solicitud_proyecto = ?",
            "i",
            [$id]
        );

        // Marcar seguimiento etapa 1 como completado si existe y estaba en proceso
        $this->ejecutar(
            "UPDATE seguimiento_documento sd
             JOIN solicitud_proyecto sp
                 ON sp.id_proyectos  = sd.id_proyectos
                AND sp.id_estudiante = sd.id_usuarios
             SET sd.estado         = 'completado',
                 sd.fecha_fin      = NOW(),
                 sd.fecha_revision = NOW()
             WHERE sp.id_solicitud_proyecto = ?
               AND sd.id_tipo_documento     = 1
               AND sd.estado               IN ('pendiente','proceso')",
            "i",
            [$id]
        );

        return true;
    }

    public function pedirCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto SET estado = 'correcciones'
             WHERE id_solicitud_proyecto = ?",
            "i",
            [$id]
        );

        // Marcar seguimiento carta como 'pendiente' (en corrección) si existe
        $this->ejecutar(
            "UPDATE seguimiento_documento sd
             JOIN solicitud_proyecto sp
                 ON sp.id_proyectos  = sd.id_proyectos
                AND sp.id_estudiante = sd.id_usuarios
             SET sd.estado                = 'pendiente',
                 sd.comentario_supervisor = ?,
                 sd.fecha_revision        = NOW(),
                 sd.revisado_por          = ?
             WHERE sp.id_solicitud_proyecto = ?
               AND sd.id_tipo_documento     = 1",
            "sii",
            [$comentario, $id_usuario, $id]
        );

        $this->actualizarEstadoProceso($id, 3); // 3 = en_correccion

        return $this->insertarComentario($id, $id_usuario, 'investigador', $comentario, $id_documento);
    }

    public function rechazar(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'rechazado', motivo_rechazo = ?, fecha_respuesta = CURDATE()
             WHERE id_solicitud_proyecto = ?",
            "si",
            [$comentario, $id]
        );

        return $this->insertarComentario($id, $id_usuario, 'investigador', $comentario, $id_documento);
    }

    private function actualizarEstadoProceso(int $id_solicitud, int $id_estados_proceso): void
    {
        $this->ejecutar(
            "UPDATE proyectos_usuarios pu
             JOIN solicitud_proyecto sp
                 ON sp.id_proyectos  = pu.id_proyectos
                AND sp.id_estudiante = pu.id_usuarios
             SET pu.id_estados_proceso = ?
             WHERE sp.id_solicitud_proyecto = ?",
            "ii",
            [$id_estados_proceso, $id_solicitud]
        );
    }

    //  Vencido 

    public function obtenervencido(): array
    {
        $filas = $this->ejecutar(
            "SELECT sp.id_solicitud_proyecto
             FROM solicitud_proyecto sp
             JOIN proyectos p  ON p.id_proyectos = sp.id_proyectos
             JOIN periodos  pe ON pe.id_periodos  = p.id_periodos
             WHERE pe.fecha_fin_solicitud IS NOT NULL
               AND CURDATE() > pe.fecha_fin_solicitud
               AND sp.estado NOT IN ('vencido','rechazado','aceptado','cancelado')"
        );
        return array_column($filas, 'id_solicitud_proyecto');
    }

    public function vencido(int $id): void
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'vencido', fecha_respuesta = CURDATE()
             WHERE id_solicitud_proyecto = ?",
            "i",
            [$id]
        );
    }

    //  Comentarios (lectura) 

    public function obtenerComentarios(int $id): array
    {
        return $this->ejecutar(
            "SELECT
                sc.id_comentario,
                sc.id_usuario,
                sc.tipo,
                sc.comentario,
                sc.fecha,
                CONCAT(u.nombre,' ',u.apellido_paterno) AS autor_nombre,
                ds.nombre       AS archivo_nombre,
                ds.ruta         AS archivo_ruta,
                ds.extension    AS archivo_extension,
                ds.tipo_mime    AS archivo_mime
             FROM solicitud_comentarios sc
             JOIN usuarios u ON u.id_usuarios = sc.id_usuario
             LEFT JOIN documentos_subidos ds ON ds.id_documento = sc.id_documento_adjunto
             WHERE sc.id_solicitud = ?
             ORDER BY sc.fecha ASC",
            "i",
            [$id]
        );
    }

    //  Tareas / vinculación 

    public function obtenerDatosSolicitud(int $id_solicitud): ?array
    {
        return $this->ejecutar(
            "SELECT id_estudiante AS id_usuarios, id_proyectos
             FROM solicitud_proyecto
             WHERE id_solicitud_proyecto = ?",
            "i",
            [$id_solicitud],
            false
        ) ?: null;
    }

    public function vincularTareasAlNuevoEstudiante(int $id_proyecto, int $id_usuario): void
    {
        $this->ejecutar(
            "INSERT INTO tareas_usuarios (id_tarea, id_usuarios, id_estadoT)
             SELECT t.id_tarea, ?, 1
             FROM tareas t
             INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
             WHERE s.id_proyectos = ?
               AND NOT EXISTS (
                   SELECT 1 FROM tareas_usuarios tu
                   WHERE tu.id_tarea = t.id_tarea AND tu.id_usuarios = ?
               )",
            "iii",
            [$id_usuario, $id_proyecto, $id_usuario]
        );
    }

    public function vincularEstudianteProyecto(int $id_proyecto, int $id_usuario): void
    {
        $this->ejecutar(
            "INSERT INTO proyectos_usuarios
                (id_proyectos, id_usuarios, fecha_asignacion, estado, reincorporacion, id_estados_proceso)
             VALUES (?, ?, CURDATE(), 'activo', 0, 1)
             ON DUPLICATE KEY UPDATE
                estado             = 'activo',
                fecha_baja         = NULL,
                motivo_baja        = NULL,
                reincorporacion    = reincorporacion + 1,
                id_estados_proceso = 1",
            "ii",
            [$id_proyecto, $id_usuario]
        );
    }

    public function registrarHistorialUsuario(
        int    $id_proyecto,
        int    $id_estudiante,
        string $accion,
        string $motivo,
        int    $realizado_por
    ): void {
        $this->ejecutar(
            "INSERT INTO historial_proyectos_usuarios
                (id_proyectos, id_estudiante, accion, motivo, realizado_por)
             VALUES (?, ?, ?, ?, ?)",
            "iissi",
            [$id_proyecto, $id_estudiante, $accion, $motivo, $realizado_por]
        );
    }

    //  Cierre (etapa 3) 

    public function actualizarEstadoCierre(
        int    $id_seguimiento,
        string $estado,
        int    $id_usuario,
        string $comentario = ''
    ): bool {
        $this->ejecutar(
            "UPDATE seguimiento_documento
             SET estado                = ?,
                 fecha_revision        = NOW(),
                 comentario_supervisor = ?,
                 revisado_por          = ?
             WHERE id_seguimiento = ?",
            "ssii",
            [$estado, $comentario, $id_usuario, $id_seguimiento]
        );
        return $this->conn->affected_rows > 0;
    }

    //  Seguimiento etapas 

    public function DatosSeguimientoEstudiante(int $id_proyecto, int $id_estudiante, int $id_investigador): array
    {
        // Etapa 1: estado de la solicitud
        $sol = $this->ejecutar(
            "SELECT estado FROM solicitud_proyecto
             WHERE id_proyectos = ? AND id_estudiante = ?
             ORDER BY id_solicitud_proyecto DESC LIMIT 1",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        );
        $e1_estado = $sol['estado'] ?? 'pendiente';

        // Etapa 2: actividades aprobadas
        $act = $this->ejecutar(
            "SELECT
                COUNT(*)               AS total,
                SUM(tu.id_estadoT = 5) AS aprobadas
             FROM tareas_usuarios tu
             JOIN tareas t           ON t.id_tarea    = tu.id_tarea
             JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
             WHERE ts.id_proyectos = ? AND tu.id_usuarios = ?",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        );
        $total_act     = (int)($act['total']     ?? 0);
        $aprobadas_act = (int)($act['aprobadas'] ?? 0);
        $fase2_ok      = $total_act > 0 && $aprobadas_act >= 11;
        $e2_estado     = $fase2_ok ? 'completado' : ($aprobadas_act > 0 ? 'proceso' : 'pendiente');

        // Etapa 3: seguimiento Reporte Final
        $cierre = $this->ejecutar(
            "SELECT sd.id_seguimiento, sd.estado
             FROM seguimiento_documento sd
             WHERE sd.id_proyectos = ? AND sd.id_usuarios = ? AND sd.id_tipo_documento = 3
             ORDER BY sd.id_seguimiento DESC LIMIT 1",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        );
        $e3_estado     = $cierre['estado']        ?? 'pendiente';
        $id_seg_cierre = $cierre['id_seguimiento'] ?? null;

        // Documentos del estudiante
        $documentos = $this->ejecutar(
            "SELECT
                ds.id_documento,
                ds.nombre,
                ds.ruta,
                ds.extension,
                ds.fecha_subida,
                td.nombre AS tipo_nombre
             FROM documentos_subidos ds
             LEFT JOIN seguimiento_documento seg ON seg.id_seguimiento  = ds.id_seguimiento
             LEFT JOIN tipo_documento        td  ON td.id_tipo_documento = seg.id_tipo_documento
             WHERE ds.id_usuarios  = ?
               AND ds.id_proyectos = ?
               AND ds.activo       = 1
               AND ds.tipo         = 'etapa'
             ORDER BY ds.fecha_subida DESC",
            "ii",
            [$id_estudiante, $id_proyecto]
        );

        return [
            'e1_estado'             => $e1_estado,
            'e2_estado'             => $e2_estado,
            'e3_estado'             => $e3_estado,
            'fase2_ok'              => $fase2_ok,
            'id_seguimiento_cierre' => $id_seg_cierre,
            'documentos'            => $documentos,
            'actividades_total'     => $total_act,
            'actividades_aprobadas' => $aprobadas_act,
        ];
    }

    //  Enviar correcciones (respuesta del estudiante) 

    public function enviarCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'en_revision'
             WHERE id_solicitud_proyecto = ? AND estado = 'correcciones'",
            "i",
            [$id]
        );

        return $this->insertarComentario($id, $id_usuario, 'estudiante', $comentario, $id_documento);
    }

    //  Otros 

    public function proyectosDelInvestigador(int $id): array
    {
        return $this->ejecutar(
            "SELECT id_proyectos, titulo FROM proyectos WHERE id_investigador = ?",
            "i",
            [$id]
        );
    }

    public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT
                e.*,
                COALESCE(s.estado, 'pendiente') AS estado,
                s.id_seguimiento,
                s.observaciones,
                s.comentario_supervisor,
                td.id_tipo_documento,
                pd.id_plantilla
             FROM etapas_documento e
             LEFT JOIN tipo_documento td
                    ON td.orden = e.orden AND td.estado = 1
             LEFT JOIN seguimiento_documento s
                    ON s.id_tipo_documento = td.id_tipo_documento
                   AND s.id_proyectos      = ?
                   AND s.id_usuarios       = ?
             LEFT JOIN plantillas_documentos pd
                    ON pd.id_tipo_documento = td.id_tipo_documento AND pd.activo = 1
             WHERE e.estado = 1
             ORDER BY e.orden",
            "ii",
            [$id_proyecto, $id_usuario]
        );
    }


    // 
    // SECCIÓN B — ESTUDIANTE
    // 

    public function obtenerPlantillaCartaCompromiso(): ?array
    {
        return $this->ejecutar(
            "SELECT
                pd.id_plantilla,
                pd.nombre          AS plantilla_nombre,
                pd.version,
                ds.ruta            AS archivo_ruta,
                ds.nombre_archivo  AS archivo_nombre,
                ds.extension,
                ds.tipo_mime
             FROM plantillas_documentos pd
             JOIN documentos_subidos ds ON ds.id_documento = pd.id_documento
             WHERE pd.id_tipo_documento = 1
               AND pd.activo            = 1
               AND ds.activo            = 1
             ORDER BY pd.version DESC
             LIMIT 1",
            "",
            [],
            false
        ) ?: null;
    }

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
            "i",
            [$id_plantilla],
            false
        ) ?: null;
    }

    //  Envío de solicitud 

    public function crearSolicitud(
        int     $id_proyecto,
        int     $id_estudiante,
        int     $id_periodo,
        ?float  $promedio,
        string  $motivacion,
        string  $experiencia,
        ?int    $semestre,
        ?int    $id_doc_cv = null
    ): int {
        $this->ejecutar(
            "INSERT INTO solicitud_proyecto
                (id_proyectos, id_estudiante, id_periodos, id_documento,
                 promedio, motivacion, experiencia, semestre,
                 estado, fecha_envio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', CURDATE())",
            "iiiisssi",
            [
                $id_proyecto, $id_estudiante, $id_periodo, $id_doc_cv,
                $promedio, $motivacion, $experiencia, $semestre,
            ]
        );

        return (int)$this->conn->insert_id;
    }

    public function crearSeguimientoCartaCompromiso(
        int    $id_proyecto,
        int    $id_estudiante,
        string $estado = 'pendiente'
    ): int {
        // Verificar si ya existe
        $fila = $this->ejecutar(
            "SELECT id_seguimiento FROM seguimiento_documento
             WHERE id_proyectos = ? AND id_usuarios = ? AND id_tipo_documento = 1
             LIMIT 1",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        );
        if ($fila) return (int)$fila['id_seguimiento'];

        $this->ejecutar(
            "INSERT INTO seguimiento_documento
                (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
             VALUES (?, 1, ?, ?, NOW())",
            "iis",
            [$id_proyecto, $id_estudiante, $estado]
        );

        return (int)$this->conn->insert_id;
    }

    public function vincularDocumentoSeguimiento(int $id_documento, int $id_seguimiento): void
    {
        $this->ejecutar(
            "UPDATE documentos_subidos SET id_seguimiento = ? WHERE id_documento = ?",
            "ii",
            [$id_seguimiento, $id_documento]
        );
    }

    public function marcarSeguimientoEnProceso(int $id_seguimiento): void
    {
        $this->ejecutar(
            "UPDATE seguimiento_documento
             SET estado = 'proceso', fecha_inicio = NOW()
             WHERE id_seguimiento = ? AND estado = 'pendiente'",
            "i",
            [$id_seguimiento]
        );
    }

    public function actualizarDocumentoSolicitud(int $id_solicitud, int $id_documento): void
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto SET id_documento = ? WHERE id_solicitud_proyecto = ?",
            "ii",
            [$id_documento, $id_solicitud]
        );
    }

    public function cancelarSolicitud(int $id_solicitud, int $id_estudiante): bool
    {
        $this->ejecutar(
            "UPDATE solicitud_proyecto
             SET estado = 'cancelado', fecha_respuesta = CURDATE()
             WHERE id_solicitud_proyecto = ?
               AND id_estudiante         = ?
               AND estado IN ('pendiente','en_revision','correcciones')",
            "ii",
            [$id_solicitud, $id_estudiante]
        );
        return $this->conn->affected_rows > 0;
    }

    public function obtenerSolicitudEstudiante(int $id_proyecto, int $id_estudiante): ?array
    {
        return $this->ejecutar(
            "SELECT
                sp.id_solicitud_proyecto,
                sp.estado,
                sp.fecha_envio,
                sp.fecha_respuesta,
                sp.motivo_rechazo,
                sp.comentarios,
                p.titulo AS proyecto_titulo,
                sd.id_seguimiento         AS seg_id,
                sd.estado                 AS seg_estado,
                sd.comentario_supervisor  AS seg_comentario,
                ds_carta.id_documento     AS carta_id,
                ds_carta.nombre           AS carta_nombre,
                ds_carta.ruta             AS carta_ruta,
                ds_carta.extension        AS carta_extension,
                ds_carta.fecha_subida     AS carta_fecha_subida,
                pd.id_plantilla,
                pd.nombre                 AS plantilla_nombre
             FROM solicitud_proyecto sp
             JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
             LEFT JOIN seguimiento_documento sd
                    ON sd.id_proyectos      = sp.id_proyectos
                   AND sd.id_usuarios       = sp.id_estudiante
                   AND sd.id_tipo_documento = 1
             LEFT JOIN documentos_subidos ds_carta
                    ON ds_carta.id_seguimiento = sd.id_seguimiento
                   AND ds_carta.activo         = 1
                   AND ds_carta.tipo           = 'etapa'
             LEFT JOIN plantillas_documentos pd
                    ON pd.id_tipo_documento = 1
                   AND pd.activo            = 1
             WHERE sp.id_proyectos  = ?
               AND sp.id_estudiante = ?
             ORDER BY sp.id_solicitud_proyecto DESC
             LIMIT 1",
            "ii",
            [$id_proyecto, $id_estudiante],
            false
        ) ?: null;
    }

    public function obtenerPeriodoActivoParaProyecto(int $id_proyecto): ?array
    {
        $hoy = date('Y-m-d');
        return $this->ejecutar(
            "SELECT per.id_periodos, per.periodo
             FROM periodos per
             JOIN proyectos p ON p.id_periodos = per.id_periodos
             WHERE p.id_proyectos = ?
               AND per.estado = 1
               AND (per.fecha_inicio_solicitud IS NULL OR per.fecha_inicio_solicitud <= ?)
               AND (per.fecha_fin_solicitud    IS NULL OR per.fecha_fin_solicitud    >= ?)
             LIMIT 1",
            "iss",
            [$id_proyecto, $hoy, $hoy],
            false
        ) ?: null;
    }

    public function obtenerProyecto(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT
                p.id_proyectos,
                p.titulo,
                p.descripcion,
                p.modalidad,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS investigador
             FROM proyectos p
             LEFT JOIN usuarios u ON u.id_usuarios = p.id_investigador
             WHERE p.id_proyectos = ?
             LIMIT 1",
            "i",
            [$id_proyecto],
            false
        ) ?: null;
    }

    public function obtenerEstudiante(int $id_usuario): ?array
    {
        return $this->ejecutar(
            "SELECT
                u.id_usuarios,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.correo_institucional,
                e.matricula,
                e.id_carrera,
                c.nombre_carrera
             FROM usuarios u
             LEFT JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
             LEFT JOIN carreras c    ON c.id_carrera  = e.id_carrera
             WHERE u.id_usuarios = ?
             LIMIT 1",
            "i",
            [$id_usuario],
            false
        ) ?: null;
    }

    public function obtenerCarreras(): array
    {
        return $this->ejecutar(
            "SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC"
        );
    }

    public function obtenerTituloProyecto(int $id_proyecto): string
    {
        $fila = $this->ejecutar(
            "SELECT titulo FROM proyectos WHERE id_proyectos = ? LIMIT 1",
            "i",
            [$id_proyecto],
            false
        );
        return $fila['titulo'] ?? '';
    }

    public function obtenerSupervisoresActivos(): array
    {
        return $this->ejecutar(
            "SELECT u.id_usuarios
             FROM usuarios u
             INNER JOIN usuarios_roles r ON r.id_usuario = u.id_usuarios
             WHERE r.id_rol = 4
               AND u.estado_usuario = 'activo'"
        );
    }

    public function insertarNotificacion(
        int    $id_usuario,
        string $titulo,
        string $contenido,
        string $enlace = ''
    ): void {
        // Notificaciones no son críticas; si falla solo se registra en log
        try {
            $this->ejecutar(
                "INSERT INTO notificaciones (usuario_id, titulo, contenido, enlace, leido, creado_en)
                 VALUES (?, ?, ?, ?, 0, NOW())",
                "isss",
                [$id_usuario, $titulo, $contenido, $enlace]
            );
        } catch (Exception $e) {
            error_log("[insertarNotificacion] " . $e->getMessage());
        }
    }

    public function periodoactualSolicitud(): ?array
    {
        return $this->ejecutar(
            "SELECT fecha_inicio_solicitud, fecha_fin_solicitud
             FROM periodos ORDER BY id_periodos DESC LIMIT 1",
            "",
            [],
            false
        ) ?: null;
    }
}