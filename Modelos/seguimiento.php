<?php

/**
 * Modelos/seguimiento.php
 *
 * Modelo de seguimiento de documentación.
 *
 * Etapa 1 — Carta Compromiso (tipo_documento id=1)
 *   El estudiante descarga la plantilla y la sube. El investigador aprueba
 *   desde detalles_solicitud. La etapa se muestra SIEMPRE como completada
 *   al llegar a esta vista (el alumno ya fue aceptado), y el estudiante
 *   solo puede DESCARGAR su documento subido, no volver a subirlo.
 *
 * Etapa 2 — Desarrollo del documento
 *   Estado calculado desde tareas. Sin subida de archivo.
 *
 * Etapa 3 — Carta de Terminación (cierres_estudiante)
 *   Solo accesible cuando Etapa 2 está completa.
 *   Estado 'finalizacion_pendiente' en cierres_estudiante cuando el alumno
 *   ya terminó actividades, subió la carta y el supervisor no ha respondido.
 *
 *   - getEtapasPorProyecto: Etapa 1 siempre 'completado'; expone documento
 *     subido para descarga exclusiva (no re-subida).
 *   - getCierreEstudiante: mapea 'finalizacion_pendiente' correctamente.
 *   - getComentariosCierre: obtiene el hilo de comentarios/correcciones
 *     para la vista correcciones_carta.php.
 *   - agregarComentarioCierre: estudiante responde correcciones del supervisor.
 *   - aprobarCarta / rechazarCarta: alineados con solicitudes_carta_terminacion.php.
 */

class SeguimientoModelo
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // 
    //  PROYECTO
    // 

    /**
     * Datos del proyecto visibles para el estudiante (debe estar activa/concluida).
     * Incluye estado_proceso, id_integrante y datos de solicitud de integración.
     */
    public function getProyectoPorId(int $id_usuario, int $id_proyecto): ?array
    {
        $sql = "
        SELECT
            p.*,
            ep_proy.nombre              AS estado_nombre,
            sp.estado                   AS estado_integracion,
            sp.id_solicitud_proyecto    AS id_solicitud,
            ep_proc.estado              AS estado_proceso,
            pu.id_integrante,
            pu.estado                   AS estado_integrante,
            pu.motivo_baja,
            pu.fecha_baja
        FROM proyectos p
        JOIN proyectos_usuarios pu
               ON pu.id_proyectos = p.id_proyectos
              AND pu.id_usuarios  = ?
        JOIN estados_proyectos ep_proy
               ON ep_proy.id_estadoP = p.id_estadoP
        JOIN estados_proceso ep_proc
               ON ep_proc.id_estados_proceso = pu.id_estados_proceso
        LEFT JOIN solicitud_proyecto sp
               ON sp.id_proyectos  = p.id_proyectos
              AND sp.id_estudiante = ?
        WHERE p.id_proyectos = ?
          AND pu.estado IN ('activo', 'concluido', 'baja', 'cancelado')
        ORDER BY sp.id_solicitud_proyecto DESC
        LIMIT 1
    ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // 
    //  ETAPAS — vista del estudiante
    // 

    /**
     * Construye las 3 etapas con su estado real y, para Etapa 1,
     * el documento subido por el estudiante (carta compromiso firmada).
     *
     * Etapa 1: siempre 'completado' al llegar aquí (el alumno ya fue aceptado).
     *          Se expone el documento subido para que el estudiante lo descargue.
     *          NO se permite volver a subir.
     *
     * Etapa 2: calculado desde tareas aprobadas.
     *
     * Etapa 3: derivado de cierres_estudiante.
     *          'finalizacion_pendiente' cuando carta subida y supervisor no responde.
     *          'rechazado' cuando supervisor rechazó → estudiante puede corregir.
     */
    /**
     * Construye las etapas en modo "baja/cancelado".
     * Muestra hasta dónde llegó el estudiante con estado visual de cierre,
     * y en cada etapa no completada indica que ya no puede continuar.
     */

    public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        // ── Verificar si el estudiante está dado de baja en este proyecto ──────
        $sqlEstado = "
        SELECT pu.estado, pu.motivo_baja, pu.fecha_baja,
               ep.nombre AS estado_proyecto
        FROM proyectos_usuarios pu
        JOIN proyectos p    ON p.id_proyectos  = pu.id_proyectos
        JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
        WHERE pu.id_proyectos = ?
          AND pu.id_usuarios  = ?
        LIMIT 1
    ";
        $stmt = $this->con->prepare($sqlEstado);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $estadoIntegrante = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Si está de baja o cancelado, construir etapas en modo "cerrado"
        // mostrando hasta dónde llegó y el motivo.
        if ($estadoIntegrante && in_array($estadoIntegrante['estado'], ['baja', 'cancelado'])) {
            return $this->getEtapasBaja($id_proyecto, $id_usuario, $estadoIntegrante);
        }

        // ── Flujo normal ───────────────────────────────────────────────────────
        $sql = "
        SELECT
            e.id_etapa,
            e.nombre,
            e.descripcion,
            e.orden,
            e.requiere_subida,
            e.plantilla_descargable     AS plantilla,
            td.id_tipo_documento,
            td.categoria                AS tipo_categoria,
            pd.id_plantilla,
            s.id_seguimiento,
            s.estado                    AS seg_estado,
            s.comentario_supervisor,
            s.observaciones
        FROM etapas_documento e
        LEFT JOIN tipo_documento td
               ON td.orden = e.orden AND td.estado = 1
        LEFT JOIN seguimiento_documento s
               ON s.id_tipo_documento = td.id_tipo_documento
              AND s.id_proyectos      = ?
              AND s.id_usuarios       = ?
        LEFT JOIN plantillas_documentos pd
               ON pd.id_tipo_documento = td.id_tipo_documento
              AND pd.activo = 1
        WHERE e.estado = 1
        ORDER BY e.orden ASC
    ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$etapa) {
            $orden = (int)$etapa['orden'];

            if ($orden === 1) {
                $etapa['estado']           = 'completado';
                $etapa['documento_subido'] = $this->getDocumentoEtapa1($id_proyecto, $id_usuario);
            } elseif ($orden === 2) {
                $total     = $this->contarTareasTotales($id_proyecto, $id_usuario);
                $aprobadas = $this->contarTareasAprobadas($id_proyecto, $id_usuario);

                $etapa['estado']           = ($total === 0 || $aprobadas < $total)
                    ? 'proceso'
                    : 'completado';
                $etapa['tareas_total']     = $total;
                $etapa['tareas_aprobadas'] = $aprobadas;
                $etapa['id_seguimiento']   = null;
                $etapa['id_plantilla']     = null;
                $etapa['documento_subido'] = null;
            } elseif ($orden === 3) {
                $cierre                    = $this->getCierreEstudiante($id_proyecto, $id_usuario);
                $etapa['documento_subido'] = null;
                $etapa['cierre']           = $cierre;

                if (!$cierre) {
                    $etapa2_completa    = $this->todasSeccionesAprobadas($id_proyecto, $id_usuario);
                    $proyecto_en_cierre = $this->proyectoPermiteCierreEstudiante($id_proyecto);

                    if (!$etapa2_completa) {
                        $etapa['estado']         = 'bloqueado';
                        $etapa['motivo_bloqueo'] = 'Debes completar todas tus actividades primero.';
                    } elseif (!$proyecto_en_cierre) {
                        $etapa['estado']         = 'esperando_cierre';
                        $etapa['motivo_bloqueo'] = 'Tus actividades están completas. En espera de que el investigador inicie el cierre del proyecto.';
                    } else {
                        $etapa['estado'] = 'pendiente';
                    }
                } else {
                    $etapa['estado'] = match ($cierre['estado']) {
                        'pendiente'              => 'finalizacion_pendiente',
                        'finalizacion_pendiente' => 'finalizacion_pendiente',
                        'aprobado'               => 'completado',
                        'rechazado'              => 'rechazado',
                        default                  => 'pendiente',
                    };
                    $etapa['comentario_supervisor'] = $cierre['comentarios'] ?? null;
                    if (!empty($cierre['id_documento'])) {
                        $etapa['documento_subido'] = $this->getDocumentoPorId((int)$cierre['id_documento']);
                    }
                }
            }
        }
        unset($etapa);

        return $rows;
    }
    /**
     * Construye las etapas en modo "baja/cancelado".
     * Muestra hasta dónde llegó el estudiante con estado visual de cierre,
     * y en cada etapa no completada indica que ya no puede continuar.
     */
    private function getEtapasBaja(int $id_proyecto, int $id_usuario, array $estadoIntegrante): array
    {
        $sql = "
        SELECT
            e.id_etapa, e.nombre, e.descripcion, e.orden,
            e.requiere_subida, e.plantilla_descargable AS plantilla,
            td.id_tipo_documento, pd.id_plantilla,
            s.id_seguimiento, s.estado AS seg_estado
        FROM etapas_documento e
        LEFT JOIN tipo_documento td
               ON td.orden = e.orden AND td.estado = 1
        LEFT JOIN seguimiento_documento s
               ON s.id_tipo_documento = td.id_tipo_documento
              AND s.id_proyectos      = ?
              AND s.id_usuarios       = ?
        LEFT JOIN plantillas_documentos pd
               ON pd.id_tipo_documento = td.id_tipo_documento
              AND pd.activo = 1
        WHERE e.estado = 1
        ORDER BY e.orden ASC
    ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $motivo     = $estadoIntegrante['motivo_baja']     ?? 'Participación finalizada';
        $fecha_baja = $estadoIntegrante['fecha_baja']      ?? null;
        $es_vencido = str_contains(strtolower($motivo), 'vencido');

        $total_t     = $this->contarTareasTotales($id_proyecto, $id_usuario);
        $aprobadas_t = $this->contarTareasAprobadas($id_proyecto, $id_usuario);
        $etapa2_ok   = $total_t > 0 && $aprobadas_t >= $total_t;

        foreach ($rows as &$etapa) {
            $orden = (int)$etapa['orden'];

            // Datos comunes de baja
            $etapa['estado_baja']  = $estadoIntegrante['estado'];  // 'baja' | 'cancelado'
            $etapa['motivo_baja']  = $motivo;
            $etapa['fecha_baja']   = $fecha_baja;
            $etapa['es_vencido']   = $es_vencido;
            $etapa['documento_subido'] = null;
            $etapa['cierre']           = null;

            if ($orden === 1) {
                // Etapa 1 siempre completada (fue aceptado en el proyecto)
                $etapa['estado']           = 'completado';
                $etapa['documento_subido'] = $this->getDocumentoEtapa1($id_proyecto, $id_usuario);
            } elseif ($orden === 2) {
                $etapa['tareas_total']     = $total_t;
                $etapa['tareas_aprobadas'] = $aprobadas_t;
                $etapa['id_seguimiento']   = null;
                $etapa['id_plantilla']     = null;

                // Si completó Etapa 2 antes de vencer → mostrar como completada
                $etapa['estado'] = $etapa2_ok ? 'completado' : 'baja_incompleta';
            } elseif ($orden === 3) {
                // Etapa 3 nunca alcanzada si está de baja por vencimiento sin Etapa 2
                $etapa['estado'] = 'baja_incompleta';
            }
        }
        unset($etapa);

        return $rows;
    }

    /**
     * Verifica si el proyecto está en un estado que permite
     * al estudiante subir su Carta de Terminación.
     * Estados válidos: 'Por cerrar' (id=5) y 'Cierre' (id=1).
     */
    public function proyectoPermiteCierreEstudiante(int $id_proyecto): bool
    {
        $sql = "
        SELECT COUNT(*) AS total
        FROM proyectos p
        JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
        WHERE p.id_proyectos = ?
          AND ep.nombre IN ('Por cerrar', 'Cierre')
    ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    // 
    //  DOCUMENTOS
    // 

    /**
     * Documento activo de Etapa 1 (carta compromiso firmada) del estudiante.
     * Busca en documentos_subidos vinculado al seguimiento_documento de tipo 1.
     */
    public function getDocumentoEtapa1(int $id_proyecto, int $id_usuario): ?array
    {
        $sql = "
            SELECT
                ds.id_documento,
                ds.nombre,
                ds.nombre_archivo,
                ds.ruta,
                ds.extension,
                ds.tipo_mime,
                ds.tamano_bytes,
                ds.fecha_subida
            FROM documentos_subidos ds
            JOIN seguimiento_documento sd
                   ON sd.id_seguimiento = ds.id_seguimiento
            WHERE sd.id_proyectos      = ?
              AND sd.id_usuarios       = ?
              AND sd.id_tipo_documento = 1
              AND ds.activo            = 1
              AND ds.tipo              = 'etapa'
            ORDER BY ds.fecha_subida DESC
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Devuelve un documento por su id_documento.
     */
    public function getDocumentoPorId(int $id_documento): ?array
    {
        $sql  = "SELECT * FROM documentos_subidos WHERE id_documento = ? AND activo = 1 LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_documento);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Documentos activos de tipo 'etapa' del estudiante en un proyecto.
     */
    public function getDocumentosEtapaEstudiante(int $id_proyecto, int $id_usuario): array
    {
        $sql = "
            SELECT
                ds.id_documento,
                ds.nombre,
                ds.nombre_archivo,
                ds.ruta,
                ds.extension,
                ds.id_etapa,
                ds.fecha_subida,
                td.nombre AS tipo_nombre
            FROM documentos_subidos ds
            LEFT JOIN seguimiento_documento seg ON seg.id_seguimiento = ds.id_seguimiento
            LEFT JOIN tipo_documento td         ON td.id_tipo_documento = seg.id_tipo_documento
            WHERE ds.id_proyectos = ?
              AND ds.id_usuarios  = ?
              AND ds.tipo         = 'etapa'
              AND ds.activo       = 1
            ORDER BY ds.id_etapa ASC, ds.fecha_subida DESC
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Registra documento en documentos_subidos (Etapa 1 / otros).
     */
    public function registrarDocumentoCentralizado(
        int     $id_seguimiento,
        ?int    $id_plantilla,
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        int     $id_usuario,
        int     $id_proyecto,
        ?int    $id_etapa
    ): bool {
        $sql = "
            INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa,
                 version, activo, id_seguimiento, id_plantilla)
            VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1, ?, ?)
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param(
            "sssssiiiiii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $id_usuario,
            $id_proyecto,
            $id_etapa,
            $id_seguimiento,
            $id_plantilla
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Registra Carta de Terminación en documentos_subidos (Etapa 3).
     * Devuelve id_documento generado.
     */
    public function registrarDocumentoCarta(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        int     $id_usuario,
        int     $id_proyecto,
        int     $id_etapa
    ): int {
        $sql = "
            INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa, version, activo)
            VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1)
        ";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param(
            "sssssiiiii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $id_usuario,
            $id_proyecto,
            $id_etapa
        );
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Desactiva el documento previo de carta de terminación al reenviar.
     */
    public function desactivarDocumentoCarta(int $id_documento): bool
    {
        $stmt = $this->con->prepare(
            "UPDATE documentos_subidos SET activo = 0 WHERE id_documento = ?"
        );
        $stmt->bind_param("i", $id_documento);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // 
    //  COMENTARIOS DE CORRECCIONES — Etapa 3 (hilo estudiante ↔ supervisor)
    // 

    /**
     * Obtiene el hilo de comentarios de correcciones de la carta de terminación.
     * Se usa en correcciones_carta.php igual que solicitud_comentarios en correcciones.php.
     *
     * NOTA: usa la tabla solicitud_comentarios reutilizada para el hilo,
     * con id_referencia = id_cierre_est y tipo_referencia = 'cierre'.
     */
    public function getComentariosCierre(int $id_cierre_est): array
    {
        $sql = "
            SELECT
                sc.id_comentario,
                sc.comentario,
                sc.tipo,
                sc.fecha,
                sc.archivo_nombre,
                sc.archivo_ruta,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) AS autor_nombre
            FROM solicitud_comentarios sc
            JOIN usuarios u ON u.id_usuarios = sc.id_usuarios
            WHERE sc.id_referencia    = ?
              AND sc.tipo_referencia  = 'cierre'
            ORDER BY sc.fecha ASC
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_cierre_est);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Agrega un comentario de corrección del estudiante en el hilo del cierre.
     * Actualiza el estado del cierre a 'finalizacion_pendiente' para re-revisión.
     */
    public function agregarComentarioCierre(
        int     $id_cierre_est,
        int     $id_usuario,
        string  $comentario,
        ?string $archivo_nombre = null,
        ?string $archivo_ruta   = null
    ): bool {
        $this->con->begin_transaction();
        try {
            // 1. Insertar comentario en el hilo
            $sql = "
                INSERT INTO solicitud_comentarios
                    (id_referencia, tipo_referencia, id_usuarios, comentario, tipo, archivo_nombre, archivo_ruta)
                VALUES (?, 'cierre', ?, ?, 'estudiante', ?, ?)
            ";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("iisss", $id_cierre_est, $id_usuario, $comentario, $archivo_nombre, $archivo_ruta);
            $stmt->execute();
            $stmt->close();

            // 2. Cambiar estado del cierre a 'finalizacion_pendiente' para que el supervisor revise
            $stmt2 = $this->con->prepare(
                "UPDATE cierres_estudiante
                 SET estado = 'finalizacion_pendiente', fecha_respuesta = NULL, comentarios = NULL
                 WHERE id_cierre_est = ?"
            );
            $stmt2->bind_param("i", $id_cierre_est);
            $stmt2->execute();
            $stmt2->close();

            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollback();
            error_log('agregarComentarioCierre: ' . $e->getMessage());
            return false;
        }
    }

    // 
    //  CIERRES_ESTUDIANTE (Etapa 3)
    // 

    /**
     * Registro de cierres_estudiante del estudiante en el proyecto.
     */
    public function getCierreEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        $sql = "
            SELECT ce.*
            FROM cierres_estudiante ce
            JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
            WHERE pu.id_proyectos = ?
              AND pu.id_usuarios  = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Obtiene un cierre por su id (para la vista de correcciones).
     */
    public function getCierrePorId(int $id_cierre_est): ?array
    {
        $sql = "
            SELECT
                ce.*,
                pu.id_proyectos,
                pu.id_usuarios,
                p.titulo        AS titulo_proyecto,
                ds.nombre       AS nombre_documento,
                ds.ruta         AS ruta_documento
            FROM cierres_estudiante ce
            JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
            JOIN proyectos p           ON p.id_proyectos   = pu.id_proyectos
            JOIN documentos_subidos ds ON ds.id_documento  = ce.id_documento
            WHERE ce.id_cierre_est = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_cierre_est);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** id_integrante del estudiante en el proyecto. */
    public function getIdIntegrante(int $id_proyecto, int $id_usuario): ?int
    {
        $sql  = "SELECT id_integrante FROM proyectos_usuarios
                 WHERE id_proyectos = ? AND id_usuarios = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id_integrante'] : null;
    }

    /** Primer supervisor activo asignado al proyecto. */
    public function getIdSupervisorDelProyecto(int $id_proyecto): ?int
    {
        $sql  = "SELECT sv.id_usuarios
                 FROM supervisores sv
                 INNER JOIN tbl_cierres tc ON tc.id_supervisor = sv.id_usuarios
                 WHERE tc.id_proyectos = ?
                 LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return (int)$row['id_usuarios'];

        $stmt2 = $this->con->prepare("SELECT id_usuarios FROM supervisores LIMIT 1");
        $stmt2->execute();
        $row2  = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        return $row2 ? (int)$row2['id_usuarios'] : null;
    }

    /**
     * Crea el registro en cierres_estudiante.
     * Estado inicial: 'finalizacion_pendiente' — carta subida, esperando supervisor.
     */
    public function crearCierreEstudiante(
        int $id_integrante,
        int $id_documento,
        int $id_supervisor
    ): int {
        $sql  = "
            INSERT INTO cierres_estudiante
                (id_integrante, id_documento, id_supervisor, estado, fecha_solicitud)
            VALUES (?, ?, ?, 'finalizacion_pendiente', NOW())
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_integrante, $id_documento, $id_supervisor);
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Reenvío de carta corregida: actualiza documento y vuelve a
     * 'finalizacion_pendiente'.
     */
    public function reenviarCierreEstudiante(int $id_cierre_est, int $id_documento): bool
    {
        $sql  = "
            UPDATE cierres_estudiante
            SET id_documento    = ?,
                estado          = 'finalizacion_pendiente',
                comentarios     = NULL,
                fecha_solicitud = NOW(),
                fecha_respuesta = NULL
            WHERE id_cierre_est = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_documento, $id_cierre_est);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Actualiza proyectos_usuarios.id_estados_proceso a 'carta_subida'.
     */
    public function actualizarEstadoProcesoCarta(int $id_integrante): bool
    {
        $sql  = "
            UPDATE proyectos_usuarios pu
            JOIN estados_proceso ep ON ep.estado = 'carta_subida'
            SET pu.id_estados_proceso = ep.id_estados_proceso
            WHERE pu.id_integrante = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_integrante);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // 
    //  SEGUIMIENTO_DOCUMENTO
    // 

    /** Reporte Final (tipo_documento nombre='Reporte Final'). */
    public function getSegimientoReporteFinal(int $id_proyecto, int $id_usuario): ?array
    {
        $sql = "
            SELECT s.*
            FROM seguimiento_documento s
            JOIN tipo_documento td ON td.id_tipo_documento = s.id_tipo_documento
            WHERE s.id_proyectos = ?
              AND s.id_usuarios  = ?
              AND td.nombre      = 'Reporte Final'
              AND td.estado      = 1
            ORDER BY s.id_seguimiento DESC
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Seguimiento por id. */
    public function getSegimientoPorId(int $id_seguimiento): ?array
    {
        $sql  = "SELECT * FROM seguimiento_documento WHERE id_seguimiento = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_seguimiento);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Crea seguimiento_documento en estado 'proceso'. */
    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        $sql  = "
            INSERT INTO seguimiento_documento
                (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
            VALUES (?, ?, ?, 'proceso', NOW())
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_proyecto, $id_tipo_documento, $id_usuario);
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /** Actualiza estado de seguimiento (acción del estudiante). */
    public function actualizarEstadoEstudiante(int $id, string $estado): bool
    {
        $stmt = $this->con->prepare(
            "UPDATE seguimiento_documento SET estado = ? WHERE id_seguimiento = ?"
        );
        $stmt->bind_param("si", $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Actualiza estado + comentario + revisor (acción del investigador). */
    public function actualizarEstadoSeguimiento(
        int    $id_seg,
        string $estado,
        string $comentario,
        int    $id_rev
    ): bool {
        $sql  = "
            UPDATE seguimiento_documento
            SET estado                = ?,
                comentario_supervisor = ?,
                revisado_por          = ?,
                fecha_revision        = NOW()
            WHERE id_seguimiento      = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssii", $estado, $comentario, $id_rev, $id_seg);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Verifica que un seguimiento pertenezca a proyecto del investigador. */
    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        $sql  = "
            SELECT COUNT(*) AS total
            FROM seguimiento_documento s
            JOIN proyectos p ON p.id_proyectos = s.id_proyectos
            WHERE s.id_seguimiento  = ?
              AND p.id_investigador = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_seg, $id_inv);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    // 
    //  SOLICITUDES DE INTEGRACIÓN
    // 

    /** Solicitud más reciente del estudiante en el proyecto. */
    public function getSolicitudPorEstudianteProyecto(int $id_estudiante, int $id_proyecto): ?array
    {
        $sql = "
            SELECT id_solicitud_proyecto, estado, fecha_envio, comentarios
            FROM solicitud_proyecto
            WHERE id_estudiante = ?
              AND id_proyectos  = ?
            ORDER BY id_solicitud_proyecto DESC
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_estudiante, $id_proyecto);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // 
    //  TIPO_DOCUMENTO — utilidades
    // 

    /** id_etapa (FK a etapas_documento) correspondiente al tipo_documento. */
    public function getIdEtapaPorTipoDocumento(int $id_tipo_documento): ?int
    {
        $sql  = "
            SELECT e.id_etapa
            FROM tipo_documento td
            JOIN etapas_documento e ON e.orden = td.orden
            WHERE td.id_tipo_documento = ?
            LIMIT 1
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tipo_documento);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id_etapa'] : null;
    }

    // 
    //  TAREAS — Etapa 2
    // 

    /** Total de tareas asignadas al estudiante en el proyecto. */
    public function contarTareasTotales(int $id_proyecto, int $id_estudiante): int
    {
        $sql  = "
            SELECT COUNT(*) AS total
            FROM tareas_usuarios tu
            JOIN tareas t           ON t.id_tarea    = tu.id_tarea
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            WHERE ts.id_proyectos = ?
              AND tu.id_usuarios  = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'];
    }

    /** Tareas con id_estadoT = 5 (aprobadas) del estudiante en el proyecto. */
    public function contarTareasAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        $sql  = "
            SELECT COUNT(*) AS total
            FROM tareas_usuarios tu
            JOIN tareas t           ON t.id_tarea    = tu.id_tarea
            JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
            WHERE tu.id_estadoT   = 5
              AND ts.id_proyectos = ?
              AND tu.id_usuarios  = ?
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'];
    }

    /** True si todas las tareas están aprobadas. */
    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        $total     = $this->contarTareasTotales($id_proyecto, $id_estudiante);
        $aprobadas = $this->contarTareasAprobadas($id_proyecto, $id_estudiante);
        return $total > 0 && $aprobadas >= $total;
    }

    // 
    //  PROYECTOS_USUARIOS + HISTORIAL
    // 

    /** Verifica que el estudiante pertenezca al proyecto (activo o concluido). */
    public function verificarProyectoUsuario(int $id_proyecto, int $id_usuario): bool
    {
        $sql  = "
            SELECT COUNT(*) AS total
            FROM proyectos_usuarios
            WHERE id_proyectos = ?
              AND id_usuarios  = ?
              AND estado IN ('activo','concluido')
        ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    /**
     * Marca al estudiante como 'concluido' en proyectos_usuarios.
     */
    public function marcarProyectoUsuarioConcluido(
        int    $id_proyecto,
        int    $id_estudiante,
        int    $realizado_por,
        string $motivo = 'Proyecto concluido — cierre aprobado por supervisor'
    ): bool {
        $this->con->begin_transaction();
        try {
            $stmt = $this->con->prepare(
                "SELECT id_estados_proceso FROM estados_proceso
                 WHERE estado = 'liberado_supervisor' LIMIT 1"
            );
            $stmt->execute();
            $ep   = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_ep = $ep ? (int)$ep['id_estados_proceso'] : 5;

            $stmt2 = $this->con->prepare(
                "UPDATE proyectos_usuarios
                 SET estado             = 'concluido',
                     fecha_terminacion  = CURDATE(),
                     id_estados_proceso = ?
                 WHERE id_proyectos = ?
                   AND id_usuarios  = ?"
            );
            $stmt2->bind_param("iii", $id_ep, $id_proyecto, $id_estudiante);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $this->con->prepare(
                "INSERT INTO historial_proyectos_usuarios
                     (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                 VALUES (?, ?, 'concluido', ?, ?)"
            );
            $stmt3->bind_param("iisi", $id_proyecto, $id_estudiante, $motivo, $realizado_por);
            $stmt3->execute();
            $stmt3->close();

            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollback();
            error_log('marcarProyectoUsuarioConcluido: ' . $e->getMessage());
            return false;
        }
    }

    /** Registra carta rechazada en historial. */
    public function registrarHistorialCartaRechazada(
        int    $id_proyecto,
        int    $id_estudiante,
        string $motivo,
        int    $realizado_por
    ): bool {
        $stmt = $this->con->prepare(
            "INSERT INTO historial_proyectos_usuarios
                 (id_proyectos, id_estudiante, accion, motivo, realizado_por)
             VALUES (?, ?, 'carta_rechazada', ?, ?)"
        );
        $stmt->bind_param("iisi", $id_proyecto, $id_estudiante, $motivo, $realizado_por);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // 
    //  NOTIFICACIONES
    // 

    /** Inserta una notificación para el usuario indicado. */
    public function notificar(int $id_usuario, string $titulo, string $contenido, string $enlace = ''): void
    {
        $stmt = $this->con->prepare(
            "INSERT INTO notificaciones (id_usuarios, titulo, contenido, enlace)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $id_usuario, $titulo, $contenido, $enlace);
        $stmt->execute();
        $stmt->close();
    }
}
