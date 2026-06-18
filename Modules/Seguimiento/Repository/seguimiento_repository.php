<?php
// Repositorios/SeguimientoRepositorio.php

require_once __DIR__ . '/../../../public/incluido/BaseModelo.php';

/**
 * SeguimientoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre las tablas
 * relacionadas al seguimiento de documentación de estudiantes.
 * No contiene lógica de negocio.
 *
 * Es instanciado por SeguimientoModelo mediante inyección por constructor.
 */
class SeguimientoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    //  PROYECTO
    // 

    /**
     * Datos del proyecto visible para el estudiante (activo/concluido/baja/cancelado).
     * Incluye estado_proceso, id_integrante y datos de solicitud de integración.
     *
     * @return array|null
     */
    public function proyectoPorId(int $id_usuario, int $id_proyecto): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
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
            LIMIT 1",
            'iii',
            [$id_usuario, $id_usuario, $id_proyecto],
            false
        );
        return $fila ?: null;
    }


    // 
    //  ETAPAS
    // 

    /**
     * Estado del integrante dentro del proyecto (activo/concluido/baja/cancelado).
     *
     * @return array|null
     */
    public function estadoIntegrante(int $id_proyecto, int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT pu.estado, pu.motivo_baja, pu.fecha_baja,
                    ep.nombre AS estado_proyecto
             FROM proyectos_usuarios pu
             JOIN proyectos p           ON p.id_proyectos  = pu.id_proyectos
             JOIN estados_proyectos ep  ON ep.id_estadoP   = p.id_estadoP
             WHERE pu.id_proyectos = ?
               AND pu.id_usuarios  = ?
             LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return $fila ?: null;
    }

    /**
     * Filas base de etapas con seguimiento del estudiante (flujo normal y baja).
     * Incluye tipo_documento, seguimiento_documento y plantilla activa.
     *
     * @return array[]
     */
    public function etapasConSeguimiento(int $id_proyecto, int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT
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
            ORDER BY e.orden ASC",
            'ii',
            [$id_proyecto, $id_usuario]
        );
    }

    /**
     * Filas base de etapas para modo baja.
     *
     * @return array[]
     */
    public function etapasConSeguimientoBaja(int $id_proyecto, int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT
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
            ORDER BY e.orden ASC",
            'ii',
            [$id_proyecto, $id_usuario]
        );
    }

    /**
     * Verifica si el proyecto está en estado que permite subir la Carta de Terminación.
     * Estados válidos: 'Por cerrar' (id=5) y 'Cierre' (id=1).
     */
    public function proyectoPermiteCierreEstudiante(int $id_proyecto): bool
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM proyectos p
             JOIN estados_proyectos ep ON ep.id_estadoP = p.id_estadoP
             WHERE p.id_proyectos = ?
               AND ep.nombre IN ('Por cerrar', 'Cierre')",
            'i',
            [$id_proyecto],
            false
        );
        return (int)($fila['total'] ?? 0) > 0;
    }


    // 
    //  DOCUMENTOS
    // 

    /**
     * Documento activo de Etapa 1 (carta compromiso firmada) del estudiante.
     *
     * @return array|null
     */
    public function datosSeguimientoEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
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
            LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return $fila ?: null;
    }

    /**
     * Devuelve un documento activo por su id_documento.
     *
     * @return array|null
     */
    public function documentoPorId(int $id_documento): ?array
    {
        $fila = $this->ejecutar(
            "SELECT * FROM documentos_subidos WHERE id_documento = ? AND activo = 1 LIMIT 1",
            'i',
            [$id_documento],
            false
        );
        return $fila ?: null;
    }

    /**
     * Documentos activos de tipo 'etapa' del estudiante en un proyecto.
     *
     * @return array[]
     */
    public function documentosEtapaEstudiante(int $id_proyecto, int $id_usuario): array
    {
        return $this->ejecutar(
            "SELECT
                ds.id_documento,
                ds.nombre,
                ds.nombre_archivo,
                ds.ruta,
                ds.extension,
                ds.id_etapa,
                ds.fecha_subida,
                td.nombre AS tipo_nombre
            FROM documentos_subidos ds
            LEFT JOIN seguimiento_documento seg ON seg.id_seguimiento   = ds.id_seguimiento
            LEFT JOIN tipo_documento td         ON td.id_tipo_documento = seg.id_tipo_documento
            WHERE ds.id_proyectos = ?
              AND ds.id_usuarios  = ?
              AND ds.tipo         = 'etapa'
              AND ds.activo       = 1
            ORDER BY ds.id_etapa ASC, ds.fecha_subida DESC",
            'ii',
            [$id_proyecto, $id_usuario]
        );
    }

    /**
     * Registra un documento en documentos_subidos (Etapa 1 / otros).
     *
     * @return bool
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
        return $this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa,
                 version, activo, id_seguimiento, id_plantilla)
             VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1, ?, ?)",
            'ssssiiiiii',
            [
                $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
                $tamano_bytes, $id_usuario, $id_proyecto, $id_etapa,
                $id_seguimiento, $id_plantilla,
            ]
        ) === true;
    }

    /**
     * Registra Carta de Terminación en documentos_subidos (Etapa 3).
     * Devuelve el id_documento generado.
     *
     * @return int  0 si falló.
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
        $this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
                 tipo, visibilidad, id_usuarios, id_proyectos, id_etapa, version, activo)
             VALUES (?, ?, ?, ?, ?, ?, 'etapa', 'privado', ?, ?, ?, 1, 1)",
            'ssssiiiii',
            [
                $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
                $tamano_bytes, $id_usuario, $id_proyecto, $id_etapa,
            ]
        );
        return (int)$this->conn->insert_id;
    }

    /**
     * Desactiva el documento previo de carta de terminación al reenviar.
     *
     * @return bool
     */
    public function desactivarDocumentoCarta(int $id_documento): bool
    {
        $this->ejecutar(
            "UPDATE documentos_subidos SET activo = 0 WHERE id_documento = ?",
            'i',
            [$id_documento]
        );
        return $this->conn->affected_rows > 0;
    }


    // 
    //  COMENTARIOS DE CORRECCIONES — Etapa 3
    //
    //  Esquema real de solicitud_comentarios:
    //    id_comentario        INT  PK AI
    //    id_solicitud         INT  NOT NULL  (FK → solicitud_proyecto)
    //    id_usuario           BIGINT UNSIGNED NOT NULL
    //    tipo                 ENUM('investigador','estudiante')
    //    comentario           TEXT NOT NULL
    //    id_documento_adjunto INT NULL       (FK → documentos_subidos)
    //    fecha                DATETIME DEFAULT current_timestamp()
    // 

    /**
     * Hilo de comentarios asociados a una solicitud de cierre.
     *
     * La tabla solicitud_comentarios referencia solicitud_proyecto mediante
     * id_solicitud. Para el flujo de carta de terminación se usa el
     * id_solicitud_proyecto del estudiante como clave de agrupación.
     *
     * @return array[]
     */
    public function comentariosCierre(int $id_solicitud): array
    {
        return $this->ejecutar(
            "SELECT
                sc.id_comentario,
                sc.comentario,
                sc.tipo,
                sc.fecha,
                sc.id_documento_adjunto,
                ds.nombre       AS archivo_nombre,
                ds.ruta         AS archivo_ruta,
                ds.extension    AS archivo_extension,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS autor_nombre
            FROM solicitud_comentarios sc
            JOIN usuarios u
                   ON u.id_usuarios = sc.id_usuario
            LEFT JOIN documentos_subidos ds
                   ON ds.id_documento = sc.id_documento_adjunto
            WHERE sc.id_solicitud = ?
            ORDER BY sc.fecha ASC",
            'i',
            [$id_solicitud]
        );
    }

    /**
     * Inserta un comentario del estudiante en el hilo de la solicitud.
     *
     * El archivo adjunto, si existe, debe registrarse primero en
     * documentos_subidos y pasarse su id como $id_documento_adjunto.
     *
     * @return bool
     */
    public function insertarComentarioCierre(
        int  $id_solicitud,
        int  $id_usuario,
        string $comentario,
        ?int $id_documento_adjunto
    ): bool {
        $this->ejecutar(
            "INSERT INTO solicitud_comentarios
                (id_solicitud, id_usuario, comentario, tipo, id_documento_adjunto)
             VALUES (?, ?, ?, 'estudiante', ?)",
            'iisi',
            [$id_solicitud, $id_usuario, $comentario, $id_documento_adjunto]
        );
        return $this->conn->affected_rows > 0;
    }

    /**
     * Reinicia el estado del cierre a 'finalizacion_pendiente' tras corrección.
     *
     * @return bool
     */
    public function reiniciarEstadoCierre(int $id_cierre_est): bool
    {
        $this->ejecutar(
            "UPDATE cierres_estudiante
             SET estado = 'finalizacion_pendiente', fecha_respuesta = NULL, comentarios = NULL
             WHERE id_cierre_est = ?",
            'i',
            [$id_cierre_est]
        );
        return $this->conn->affected_rows > 0;
    }


    // 
    //  CIERRES_ESTUDIANTE (Etapa 3)
    // 

    /**
     * Registro de cierres_estudiante del estudiante en el proyecto.
     *
     * @return array|null
     */
    public function cierreEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT ce.*
             FROM cierres_estudiante ce
             JOIN proyectos_usuarios pu ON pu.id_integrante = ce.id_integrante
             WHERE pu.id_proyectos = ?
               AND pu.id_usuarios  = ?
             LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return $fila ?: null;
    }

    /**
     * Obtiene un cierre por su id (vista de correcciones).
     *
     * @return array|null
     */
    public function cierrePorId(int $id_cierre_est): ?array
    {
        $fila = $this->ejecutar(
            "SELECT
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
            LIMIT 1",
            'i',
            [$id_cierre_est],
            false
        );
        return $fila ?: null;
    }

    /**
     * id_integrante del estudiante en el proyecto.
     *
     * @return int|null
     */
    public function idIntegrante(int $id_proyecto, int $id_usuario): ?int
    {
        $fila = $this->ejecutar(
            "SELECT id_integrante FROM proyectos_usuarios
             WHERE id_proyectos = ? AND id_usuarios = ? LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return $fila ? (int)$fila['id_integrante'] : null;
    }

    /**
     * Primer supervisor activo asignado al proyecto.
     * Fallback: cualquier supervisor registrado.
     *
     * @return int|null
     */
    public function idSupervisorDelProyecto(int $id_proyecto): ?int
    {
        $fila = $this->ejecutar(
            "SELECT sv.id_usuarios
             FROM supervisores sv
             INNER JOIN tbl_cierres tc ON tc.id_supervisor = sv.id_usuarios
             WHERE tc.id_proyectos = ?
             LIMIT 1",
            'i',
            [$id_proyecto],
            false
        );
        if ($fila) return (int)$fila['id_usuarios'];

        $fila2 = $this->ejecutar(
            "SELECT id_usuarios FROM supervisores LIMIT 1",
            '',
            [],
            false
        );
        return $fila2 ? (int)$fila2['id_usuarios'] : null;
    }

    /**
     * Crea el registro en cierres_estudiante.
     * Estado inicial: 'finalizacion_pendiente'.
     *
     * @return int  id_cierre_est generado.
     */
    public function crearCierreEstudiante(
        int $id_integrante,
        int $id_documento,
        int $id_supervisor
    ): int {
        $this->ejecutar(
            "INSERT INTO cierres_estudiante
                (id_integrante, id_documento, id_supervisor, estado, fecha_solicitud)
             VALUES (?, ?, ?, 'finalizacion_pendiente', NOW())",
            'iii',
            [$id_integrante, $id_documento, $id_supervisor]
        );
        return (int)$this->conn->insert_id;
    }

    /**
     * Reenvío de carta corregida: actualiza documento y vuelve a
     * 'finalizacion_pendiente'.
     *
     * @return bool
     */
    public function reenviarCierreEstudiante(int $id_cierre_est, int $id_documento): bool
    {
        $this->ejecutar(
            "UPDATE cierres_estudiante
             SET id_documento    = ?,
                 estado          = 'finalizacion_pendiente',
                 comentarios     = NULL,
                 fecha_solicitud = NOW(),
                 fecha_respuesta = NULL
             WHERE id_cierre_est = ?",
            'ii',
            [$id_documento, $id_cierre_est]
        );
        return $this->conn->affected_rows > 0;
    }

    /**
     * Actualiza proyectos_usuarios.id_estados_proceso a 'carta_subida'.
     *
     * @return bool
     */
    public function actualizarEstadoProcesoCarta(int $id_integrante): bool
    {
        $this->ejecutar(
            "UPDATE proyectos_usuarios pu
             JOIN estados_proceso ep ON ep.estado = 'carta_subida'
             SET pu.id_estados_proceso = ep.id_estados_proceso
             WHERE pu.id_integrante = ?",
            'i',
            [$id_integrante]
        );
        return $this->conn->affected_rows > 0;
    }


    // 
    //  SEGUIMIENTO_DOCUMENTO
    // 

    /**
     * Reporte Final (tipo_documento nombre='Reporte Final').
     *
     * @return array|null
     */
    public function seguimientoReporteFinal(int $id_proyecto, int $id_usuario): ?array
    {
        $fila = $this->ejecutar(
            "SELECT s.*
             FROM seguimiento_documento s
             JOIN tipo_documento td ON td.id_tipo_documento = s.id_tipo_documento
             WHERE s.id_proyectos = ?
               AND s.id_usuarios  = ?
               AND td.nombre      = 'Reporte Final'
               AND td.estado      = 1
             ORDER BY s.id_seguimiento DESC
             LIMIT 1",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return $fila ?: null;
    }

    /**
     * Seguimiento por id.
     *
     * @return array|null
     */
    public function seguimientoPorId(int $id_seguimiento): ?array
    {
        $fila = $this->ejecutar(
            "SELECT * FROM seguimiento_documento WHERE id_seguimiento = ? LIMIT 1",
            'i',
            [$id_seguimiento],
            false
        );
        return $fila ?: null;
    }

    /**
     * Crea seguimiento_documento en estado 'proceso'.
     *
     * @return int  id_seguimiento generado.
     */
    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        $this->ejecutar(
            "INSERT INTO seguimiento_documento
                (id_proyectos, id_tipo_documento, id_usuarios, estado, fecha_inicio)
             VALUES (?, ?, ?, 'proceso', NOW())",
            'iii',
            [$id_proyecto, $id_tipo_documento, $id_usuario]
        );
        return (int)$this->conn->insert_id;
    }

    /**
     * Actualiza estado de seguimiento (acción del estudiante).
     *
     * @return bool
     */
    public function actualizarEstadoEstudiante(int $id, string $estado): bool
    {
        $this->ejecutar(
            "UPDATE seguimiento_documento SET estado = ? WHERE id_seguimiento = ?",
            'si',
            [$estado, $id]
        );
        return $this->conn->affected_rows > 0;
    }

    /**
     * Actualiza estado + comentario + revisor (acción del investigador).
     *
     * @return bool
     */
    public function actualizarEstadoSeguimiento(
        int    $id_seg,
        string $estado,
        string $comentario,
        int    $id_rev
    ): bool {
        $this->ejecutar(
            "UPDATE seguimiento_documento
             SET estado                = ?,
                 comentario_supervisor = ?,
                 revisado_por          = ?,
                 fecha_revision        = NOW()
             WHERE id_seguimiento      = ?",
            'ssii',
            [$estado, $comentario, $id_rev, $id_seg]
        );
        return $this->conn->affected_rows > 0;
    }

    /**
     * Verifica que un seguimiento pertenezca a proyecto del investigador.
     *
     * @return bool
     */
    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM seguimiento_documento s
             JOIN proyectos p ON p.id_proyectos = s.id_proyectos
             WHERE s.id_seguimiento  = ?
               AND p.id_investigador = ?",
            'ii',
            [$id_seg, $id_inv],
            false
        );
        return (int)($fila['total'] ?? 0) > 0;
    }


    // 
    //  SOLICITUDES DE INTEGRACIÓN
    // 

    /**
     * Solicitud más reciente del estudiante en el proyecto.
     *
     * @return array|null
     */
    public function solicitudPorEstudianteProyecto(int $id_estudiante, int $id_proyecto): ?array
    {
        $fila = $this->ejecutar(
            "SELECT id_solicitud_proyecto, estado, fecha_envio, comentarios
             FROM solicitud_proyecto
             WHERE id_estudiante = ?
               AND id_proyectos  = ?
             ORDER BY id_solicitud_proyecto DESC
             LIMIT 1",
            'ii',
            [$id_estudiante, $id_proyecto],
            false
        );
        return $fila ?: null;
    }


    // 
    //  TIPO_DOCUMENTO — utilidades
    // 

    /**
     * id_etapa (FK a etapas_documento) correspondiente al tipo_documento.
     *
     * @return int|null
     */
    public function idEtapaPorTipoDocumento(int $id_tipo_documento): ?int
    {
        $fila = $this->ejecutar(
            "SELECT e.id_etapa
             FROM tipo_documento td
             JOIN etapas_documento e ON e.orden = td.orden
             WHERE td.id_tipo_documento = ?
             LIMIT 1",
            'i',
            [$id_tipo_documento],
            false
        );
        return $fila ? (int)$fila['id_etapa'] : null;
    }


    // 
    //  TAREAS — Etapa 2
    // 

    /**
     * Total de tareas asignadas al estudiante en el proyecto.
     *
     * @return int
     */
    public function contarTareasTotales(int $id_proyecto, int $id_estudiante): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM tareas_usuarios tu
             JOIN tareas t           ON t.id_tarea    = tu.id_tarea
             JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
             WHERE ts.id_proyectos = ?
               AND tu.id_usuarios  = ?",
            'ii',
            [$id_proyecto, $id_estudiante],
            false
        );
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Tareas con id_estadoT = 5 (aprobadas) del estudiante en el proyecto.
     *
     * @return int
     */
    public function contarTareasAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM tareas_usuarios tu
             JOIN tareas t           ON t.id_tarea    = tu.id_tarea
             JOIN tbl_seguimiento ts ON ts.id_avances = t.id_avances
             WHERE tu.id_estadoT   = 5
               AND ts.id_proyectos = ?
               AND tu.id_usuarios  = ?",
            'ii',
            [$id_proyecto, $id_estudiante],
            false
        );
        return (int)($fila['total'] ?? 0);
    }


    // 
    //  PROYECTOS_USUARIOS + HISTORIAL
    // 

    /**
     * Verifica que el estudiante pertenezca al proyecto (activo o concluido).
     *
     * @return bool
     */
    public function verificarProyectoUsuario(int $id_proyecto, int $id_usuario): bool
    {
        $fila = $this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM proyectos_usuarios
             WHERE id_proyectos = ?
               AND id_usuarios  = ?
               AND estado IN ('activo','concluido')",
            'ii',
            [$id_proyecto, $id_usuario],
            false
        );
        return (int)($fila['total'] ?? 0) > 0;
    }

    /**
     * Obtiene el id_estados_proceso para el estado 'liberado_supervisor'.
     *
     * @return int  Fallback a 5 si no existe.
     */
    public function idEstadoProcesoLiberado(): int
    {
        $fila = $this->ejecutar(
            "SELECT id_estados_proceso FROM estados_proceso
             WHERE estado = 'liberado_supervisor' LIMIT 1",
            '',
            [],
            false
        );
        return $fila ? (int)$fila['id_estados_proceso'] : 5;
    }

    /**
     * Marca al estudiante como 'concluido' en proyectos_usuarios.
     *
     * @return bool
     */
    public function marcarIntegranteConcluido(
        int $id_proyecto,
        int $id_estudiante,
        int $id_ep
    ): bool {
        $this->ejecutar(
            "UPDATE proyectos_usuarios
             SET estado             = 'concluido',
                 fecha_terminacion  = CURDATE(),
                 id_estados_proceso = ?
             WHERE id_proyectos = ?
               AND id_usuarios  = ?",
            'iii',
            [$id_ep, $id_proyecto, $id_estudiante]
        );
        return $this->conn->affected_rows > 0;
    }

    /**
     * Inserta una fila en historial_proyectos_usuarios.
     *
     * @return bool
     */
    public function insertarHistorial(
        int    $id_proyecto,
        int    $id_estudiante,
        string $accion,
        string $motivo,
        int    $realizado_por
    ): bool {
        $this->ejecutar(
            "INSERT INTO historial_proyectos_usuarios
                 (id_proyectos, id_estudiante, accion, motivo, realizado_por)
             VALUES (?, ?, ?, ?, ?)",
            'iissi',
            [$id_proyecto, $id_estudiante, $accion, $motivo, $realizado_por]
        );
        return $this->conn->affected_rows > 0;
    }


    // 
    //  NOTIFICACIONES
    // 

    /**
     * Inserta una notificación para el usuario indicado.
     */
    public function notificar(int $id_usuario, string $titulo, string $contenido, string $enlace = ''): void
    {
        $this->ejecutar(
            "INSERT INTO notificaciones (id_usuarios, titulo, contenido, enlace)
             VALUES (?, ?, ?, ?)",
            'isss',
            [$id_usuario, $titulo, $contenido, $enlace]
        );
    }
}