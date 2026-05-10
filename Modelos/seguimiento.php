<?php

/**
 * Modelos/seguimiento.php
 *
 * Modelo de seguimiento de documentación — versión actualizada.
 *
 * Cambios respecto a la versión anterior:
 *  - Etapa 1: vinculada a solicitud_proyecto (estado 'aceptado') Y a
 *             seguimiento_documento con tipo_documento id=1 (Carta Compromiso).
 *  - Etapa 2: sin cambio de lógica (≥ todas las tareas aprobadas en tbl_seguimiento).
 *  - Etapa 3: vinculada a cierres_estudiante (tabla nueva) en lugar de
 *             seguimiento_documento con categoria='final'.
 *             El estudiante sube la Carta de Terminación → va a cierres_estudiante.
 *             El investigador primero valida (seguimiento_documento Reporte Final),
 *             luego el supervisor aprueba en cierres_estudiante.
 *  - subirCartaTerminacion(): método nuevo para la subida de la etapa 3.
 *  - registrarDocumentoCentralizado(): corregido — usa columna id_etapa (int)
 *    en lugar de la columna 'etapa' que no existe en la BD real.
 *  - marcarProyectoUsuarioConcluido(): actualiza id_estados_proceso
 *    usando el catálogo estados_proceso en lugar de un valor hardcodeado.
 *
 * Tablas involucradas:
 *   proyectos, proyectos_usuarios, estados_proceso,
 *   solicitud_proyecto, etapas_documento, tipo_documento,
 *   seguimiento_documento, documentos_subidos, plantillas_documentos,
 *   cierres_estudiante, supervisores,
 *   tareas_usuarios, tareas, tbl_seguimiento,
 *   historial_proyectos_usuarios, notificaciones
 */

class SeguimientoModelo
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ══════════════════════════════════════════════════════════════
    // PROYECTOS
    // ══════════════════════════════════════════════════════════════

    /**
     * Proyecto visible para un estudiante (debe estar en proyectos_usuarios activo/concluido).
     * Incluye:
     *   - estado_integracion → solicitud_proyecto.estado (para bloqueo Etapa 1)
     *   - estado_proceso     → estados_proceso.estado    (carta_subida, liberado_supervisor…)
     *   - id_integrante      → FK a cierres_estudiante
     */
    public function getProyectoPorId(int $id_usuario, int $id_proyecto): ?array
    {
        $sql = "SELECT
                    p.*,
                    ep_proy.nombre              AS estado_nombre,
                    sp.estado                   AS estado_integracion,
                    sp.id_solicitud_proyecto    AS id_solicitud,
                    ep_proc.estado              AS estado_proceso,
                    pu.id_integrante,
                    pu.estado                   AS estado_integrante
                FROM proyectos p
                JOIN proyectos_usuarios pu
                       ON pu.id_proyectos = p.id_proyectos
                      AND pu.id_usuarios  = ?
                JOIN estados_proyectos ep_proy
                       ON ep_proy.id_estadoP   = p.id_estadoP
                JOIN estados_proceso ep_proc
                       ON ep_proc.id_estados_proceso = pu.id_estados_proceso
                LEFT JOIN solicitud_proyecto sp
                       ON sp.id_proyectos  = p.id_proyectos
                      AND sp.id_estudiante = ?
                WHERE p.id_proyectos = ?
                  AND pu.estado IN ('activo','concluido')
                ORDER BY sp.id_solicitud_proyecto DESC
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_usuario, $id_usuario, $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    // ETAPAS — vista del estudiante
    // ══════════════════════════════════════════════════════════════

    /**
     * Construye las 3 etapas de seguimiento para el estudiante con su estado real.
     *
     * Etapa 1 (orden=1, tipo_documento id=1 Carta Compromiso):
     *   Estado viene de seguimiento_documento si existe, si no 'pendiente'.
     *
     * Etapa 2 (orden=2, sin subida de archivo):
     *   Estado calculado a partir de tareas aprobadas (no tiene seguimiento_documento).
     *
     * Etapa 3 (orden=3, tipo_documento id=4 Carta de Terminación):
     *   Estado derivado de cierres_estudiante (tabla nueva).
     *   Mientras no haya registro en cierres_estudiante, el estado es
     *   'pendiente' o 'proceso' (si hay seguimiento_documento del Reporte Final aprobado).
     */
    public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        // Obtener definición de etapas con su tipo_documento y plantilla activa
        $sql = "SELECT
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
                ORDER BY e.orden ASC";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Enriquecer cada etapa con su estado real
        foreach ($rows as &$etapa) {
            $orden = (int)$etapa['orden'];

            if ($orden === 1) {
                // Etapa 1: estado viene de seguimiento_documento (Carta Compromiso)
                $etapa['estado'] = $etapa['seg_estado'] ?? 'pendiente';

            } elseif ($orden === 2) {
                // Etapa 2: calculado desde tareas
                $total     = $this->contarTareasTotales($id_proyecto, $id_usuario);
                $aprobadas = $this->contarTareasAprobadas($id_proyecto, $id_usuario);

                if ($total === 0) {
                    $etapa['estado'] = 'pendiente';
                } elseif ($aprobadas >= $total && $total > 0) {
                    $etapa['estado'] = 'completado';
                } elseif ($aprobadas > 0) {
                    $etapa['estado'] = 'proceso';
                } else {
                    $etapa['estado'] = 'pendiente';
                }

                // Etapa 2 no tiene seguimiento_documento ni plantilla
                $etapa['id_seguimiento'] = null;
                $etapa['id_plantilla']   = null;

            } elseif ($orden === 3) {
                // Etapa 3: estado derivado de cierres_estudiante
                $cierre = $this->getCierreEstudiante($id_proyecto, $id_usuario);
                if (!$cierre) {
                    // Aún no ha subido carta — verificar si el Reporte Final está aprobado
                    $reporteFinal = $this->getSegimientoReporteFinal($id_proyecto, $id_usuario);
                    if ($reporteFinal && $reporteFinal['estado'] === 'completado') {
                        $etapa['estado'] = 'proceso'; // Listo para subir carta de terminación
                    } else {
                        $etapa['estado'] = 'pendiente';
                    }
                } else {
                    $etapa['estado'] = match($cierre['estado']) {
                        'pendiente' => 'proceso',   // Carta subida, esperando validación
                        'aprobado'  => 'completado',
                        'rechazado' => 'rechazado',
                        default     => 'pendiente',
                    };
                    $etapa['comentario_supervisor'] = $cierre['comentarios'] ?? null;
                }
            }
        }
        unset($etapa);

        return $rows;
    }

    // ══════════════════════════════════════════════════════════════
    // CIERRES_ESTUDIANTE (Etapa 3)
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene el registro de cierres_estudiante de un estudiante en un proyecto.
     * Solo puede existir uno por la restricción UNIQUE en id_integrante.
     */
    public function getCierreEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        $sql = "SELECT ce.*
                FROM cierres_estudiante ce
                JOIN proyectos_usuarios pu
                       ON pu.id_integrante = ce.id_integrante
                WHERE pu.id_proyectos = ?
                  AND pu.id_usuarios  = ?
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Obtiene el id_integrante del estudiante en el proyecto
     * (necesario para insertar en cierres_estudiante).
     */
    public function getIdIntegrante(int $id_proyecto, int $id_usuario): ?int
    {
        $sql  = "SELECT id_integrante
                 FROM proyectos_usuarios
                 WHERE id_proyectos = ? AND id_usuarios = ?
                 LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id_integrante'] : null;
    }

    /**
     * Obtiene el id_supervisor activo asignado al proyecto.
     * Se usa para crear el registro en cierres_estudiante.
     */
    public function getIdSupervisorDelProyecto(int $id_proyecto): ?int
    {
        // Buscar en tbl_cierres el supervisor que revisó el proyecto,
        // o en supervisores directamente. Aquí tomamos el primer supervisor activo.
        $sql = "SELECT sv.id_usuarios
                FROM supervisores sv
                INNER JOIN tbl_cierres tc ON tc.id_supervisor = sv.id_usuarios
                WHERE tc.id_proyectos = ?
                LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) return (int)$row['id_usuarios'];

        // Fallback: cualquier supervisor registrado en el sistema
        $stmt2 = $this->con->prepare("SELECT id_usuarios FROM supervisores LIMIT 1");
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        return $row2 ? (int)$row2['id_usuarios'] : null;
    }

    /**
     * Crea el registro en cierres_estudiante cuando el estudiante sube
     * su Carta de Terminación firmada.
     *
     * @return int  id_cierre_est generado
     */
    public function crearCierreEstudiante(
        int $id_integrante,
        int $id_documento,
        int $id_supervisor
    ): int {
        $sql  = "INSERT INTO cierres_estudiante
                     (id_integrante, id_documento, id_supervisor, estado, fecha_solicitud)
                 VALUES (?, ?, ?, 'pendiente', NOW())";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_integrante, $id_documento, $id_supervisor);
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Actualiza el documento adjunto a un cierre cuando el estudiante
     * reenvía la carta corregida (estado pasa de 'rechazado' a 'pendiente').
     */
    public function reenviarCierreEstudiante(int $id_cierre_est, int $id_documento): bool
    {
        $sql  = "UPDATE cierres_estudiante
                 SET id_documento     = ?,
                     estado           = 'pendiente',
                     comentarios      = NULL,
                     fecha_solicitud  = NOW(),
                     fecha_respuesta  = NULL
                 WHERE id_cierre_est  = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_documento, $id_cierre_est);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Actualiza proyectos_usuarios.id_estados_proceso a 'carta_subida'
     * cuando el estudiante sube la carta de terminación.
     */
    public function actualizarEstadoProcesoCarta(int $id_integrante): bool
    {
        $sql  = "UPDATE proyectos_usuarios pu
                 JOIN estados_proceso ep ON ep.estado = 'carta_subida'
                 SET pu.id_estados_proceso = ep.id_estados_proceso
                 WHERE pu.id_integrante = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_integrante);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ══════════════════════════════════════════════════════════════
    // SEGUIMIENTO_DOCUMENTO — Reporte Final (necesario antes de Etapa 3)
    // ══════════════════════════════════════════════════════════════

    /**
     * Reporte Final del estudiante en el proyecto.
     * tipo_documento id=3 (Reporte Final, categoria='final').
     */
    public function getSegimientoReporteFinal(int $id_proyecto, int $id_usuario): ?array
    {
        $sql = "SELECT s.*
                FROM seguimiento_documento s
                JOIN tipo_documento td ON td.id_tipo_documento = s.id_tipo_documento
                WHERE s.id_proyectos = ?
                  AND s.id_usuarios  = ?
                  AND td.nombre      = 'Reporte Final'
                  AND td.estado      = 1
                ORDER BY s.id_seguimiento DESC
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Obtiene un seguimiento_documento por id.
     */
    public function getSegimientoPorId(int $id_seguimiento): ?array
    {
        $sql  = "SELECT * FROM seguimiento_documento WHERE id_seguimiento = ? LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_seguimiento);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    // SOLICITUDES DE INTEGRACIÓN
    // ══════════════════════════════════════════════════════════════

    /**
     * Solicitud más reciente del estudiante en el proyecto.
     */
    public function getSolicitudPorEstudianteProyecto(int $id_estudiante, int $id_proyecto): ?array
    {
        $sql = "SELECT id_solicitud_proyecto, estado, fecha_envio, comentarios
                FROM solicitud_proyecto
                WHERE id_estudiante = ?
                  AND id_proyectos  = ?
                ORDER BY id_solicitud_proyecto DESC
                LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_estudiante, $id_proyecto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    // SEGUIMIENTO_DOCUMENTO — operaciones
    // ══════════════════════════════════════════════════════════════

    /**
     * Crea un nuevo seguimiento_documento con estado 'proceso'.
     */
    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        $sql  = "INSERT INTO seguimiento_documento
                     (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
                 VALUES (?, ?, ?, 'proceso', NOW())";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_proyecto, $id_tipo_documento, $id_usuario);
        $stmt->execute();
        $id = $this->con->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Actualiza el estado de un seguimiento (acción del estudiante).
     */
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

    /**
     * Actualiza estado + comentario supervisor + fecha revisión (acción del investigador).
     * Estados válidos: completado | rechazado | correcciones | proceso
     */
    public function actualizarEstadoSeguimiento(
        int    $id_seg,
        string $estado,
        string $comentario,
        int    $id_rev
    ): bool {
        $sql  = "UPDATE seguimiento_documento
                 SET estado                = ?,
                     comentario_supervisor = ?,
                     revisado_por          = ?,
                     fecha_revision        = NOW()
                 WHERE id_seguimiento      = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssii", $estado, $comentario, $id_rev, $id_seg);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Verifica que un seguimiento_documento pertenezca a un proyecto del investigador.
     */
    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM seguimiento_documento s
                 JOIN proyectos p ON p.id_proyectos = s.id_proyectos
                 WHERE s.id_seguimiento  = ?
                   AND p.id_investigador = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_seg, $id_inv);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    // ══════════════════════════════════════════════════════════════
    // DOCUMENTOS_SUBIDOS
    // ══════════════════════════════════════════════════════════════

    /**
     * Registra un documento en la tabla centralizada documentos_subidos.
     *
     * CORRECCIÓN: la BD usa la columna `id_etapa` (INT, FK a etapas_documento.id_etapa),
     * no una columna llamada 'etapa'. Se recibe el id_etapa directamente.
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
        ?int    $id_etapa          // FK a etapas_documento.id_etapa
    ): bool {
        $sql = "INSERT INTO documentos_subidos
                    (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                     tipo, visibilidad, id_usuarios, id_proyectos, id_etapa,
                     version, activo, id_seguimiento, id_plantilla)
                VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1, ?, ?)";

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
     * Registra la Carta de Terminación como documento en documentos_subidos.
     * Tipo 'etapa', retorna el id_documento generado.
     * Se usa al crear el registro en cierres_estudiante.
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
        int     $id_etapa         // id_etapa = 3 (Etapa 3 en etapas_documento)
    ): int {
        $sql = "INSERT INTO documentos_subidos
                    (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                     tipo, visibilidad, id_usuarios, id_proyectos, id_etapa, version, activo)
                VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1)";

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
     * Desactiva el documento anterior de carta de terminación (cuando se reenvía).
     * Actualiza activo=0 al documento previo para mantener solo la versión vigente.
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

    /**
     * Documentos activos de tipo 'etapa' del estudiante en un proyecto.
     */
    public function getDocumentosEtapaEstudiante(int $id_proyecto, int $id_usuario): array
    {
        $sql = "SELECT
                    ds.id_documento,
                    ds.nombre,
                    ds.nombre_archivo,
                    ds.ruta,
                    ds.extension,
                    ds.id_etapa,
                    ds.fecha_subida,
                    td.nombre AS tipo_nombre
                FROM documentos_subidos ds
                LEFT JOIN seguimiento_documento seg
                       ON seg.id_seguimiento = ds.id_seguimiento
                LEFT JOIN tipo_documento td
                       ON td.id_tipo_documento = seg.id_tipo_documento
                WHERE ds.id_proyectos = ?
                  AND ds.id_usuarios  = ?
                  AND ds.tipo         = 'etapa'
                  AND ds.activo       = 1
                ORDER BY ds.id_etapa ASC, ds.fecha_subida DESC";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ══════════════════════════════════════════════════════════════
    // TIPO_DOCUMENTO — utilidades
    // ══════════════════════════════════════════════════════════════

    /**
     * Devuelve el id_etapa (FK a etapas_documento) según el tipo_documento.
     * Equivale al orden del tipo_documento, que coincide con el orden de la etapa.
     */
    public function getIdEtapaPorTipoDocumento(int $id_tipo_documento): ?int
    {
        $sql  = "SELECT e.id_etapa
                 FROM tipo_documento td
                 JOIN etapas_documento e ON e.orden = td.orden
                 WHERE td.id_tipo_documento = ?
                 LIMIT 1";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tipo_documento);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id_etapa'] : null;
    }

    // ══════════════════════════════════════════════════════════════
    // TAREAS — para determinar si Etapa 2 está completa
    // ══════════════════════════════════════════════════════════════

    /**
     * Total de tareas asignadas al estudiante en el proyecto.
     */
    public function contarTareasTotales(int $id_proyecto, int $id_estudiante): int
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM tareas_usuarios tu
                 JOIN tareas t           ON t.id_tarea    = tu.id_tarea
                 JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
                 WHERE ts.id_proyectos = ?
                   AND tu.id_usuarios  = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'];
    }

    /**
     * Tareas con id_estadoT = 5 (aprobado) del estudiante en el proyecto.
     */
    public function contarTareasAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM tareas_usuarios tu
                 JOIN tareas t           ON t.id_tarea    = tu.id_tarea
                 JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
                 WHERE tu.id_estadoT   = 5
                   AND ts.id_proyectos = ?
                   AND tu.id_usuarios  = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'];
    }

    /**
     * Alias público para compatibilidad.
     * Devuelve true si todas las tareas están aprobadas.
     */
    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        $total     = $this->contarTareasTotales($id_proyecto, $id_estudiante);
        $aprobadas = $this->contarTareasAprobadas($id_proyecto, $id_estudiante);
        return $total > 0 && $aprobadas >= $total;
    }

    // ══════════════════════════════════════════════════════════════
    // PROYECTOS_USUARIOS + HISTORIAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Verifica que el estudiante pertenezca al proyecto (activo o concluido).
     */
    public function verificarProyectoUsuario(int $id_proyecto, int $id_usuario): bool
    {
        $sql  = "SELECT COUNT(*) AS total
                 FROM proyectos_usuarios
                 WHERE id_proyectos = ?
                   AND id_usuarios  = ?
                   AND estado IN ('activo','concluido')";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['total'] > 0;
    }

    /**
     * Marca al estudiante como 'concluido' en proyectos_usuarios y registra
     * el evento en historial_proyectos_usuarios.
     * Se llama al aprobar el Cierre (Etapa 3) desde el controlador de cartas
     * de terminación — también puede llamarse desde aquí si el investigador
     * aprueba el seguimiento_documento del Reporte Final.
     *
     * CORRECCIÓN: usa el catálogo estados_proceso para obtener el id correcto
     * en lugar de hardcodear un valor.
     */
    public function marcarProyectoUsuarioConcluido(
        int $id_proyecto,
        int $id_estudiante,
        int $realizado_por,
        string $motivo = 'Proyecto concluido — cierre aprobado por supervisor'
    ): bool {
        $this->con->begin_transaction();
        try {
            // 1. Obtener id_estados_proceso del estado 'liberado_supervisor'
            //    (el supervisor libera → concluido lo maneja el modelo de cartas)
            $stmt = $this->con->prepare(
                "SELECT id_estados_proceso FROM estados_proceso
                 WHERE estado = 'liberado_supervisor' LIMIT 1"
            );
            $stmt->execute();
            $ep = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $id_ep = $ep ? (int)$ep['id_estados_proceso'] : 5; // fallback id=5

            // 2. Actualizar proyectos_usuarios
            $stmt2 = $this->con->prepare(
                "UPDATE proyectos_usuarios
                 SET estado              = 'concluido',
                     fecha_terminacion   = CURDATE(),
                     id_estados_proceso  = ?
                 WHERE id_proyectos = ?
                   AND id_usuarios  = ?"
            );
            $stmt2->bind_param("iii", $id_ep, $id_proyecto, $id_estudiante);
            $stmt2->execute();
            $stmt2->close();

            // 3. Registrar en historial_proyectos_usuarios
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

    /**
     * Registra en historial_proyectos_usuarios cuando se rechaza la carta.
     * Acción: 'carta_rechazada' (valor válido en el ENUM).
     */
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

    // ══════════════════════════════════════════════════════════════
    // NOTIFICACIONES
    // ══════════════════════════════════════════════════════════════

    /**
     * Inserta una notificación para el usuario indicado.
     */
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