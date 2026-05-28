<?php
// Modelos/plantilladocumento.php

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseModelo.php';

class plantilladocumento extends BaseModelo
{

    // 
    //  WHERE DINÁMICO
    // 

    /**
     * Construye la cláusula WHERE según filtro (0=desactivado, 1=activo, 2=todos)
     * y término de búsqueda opcional.
     */
    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0) {
            $where[] = "pd.activo = 0";
        } elseif ($filtro === 1) {
            $where[] = "pd.activo = 1";
        }
        // filtro === 2 → sin restricción de activo

        if (!empty($buscar)) {
            $where[]  = "pd.nombre LIKE ?";
            $params[] = "%{$buscar}%";
            $types   .= 's';
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    // 
    //  CONSULTAS PRINCIPALES
    // 

    /**
     * Listado paginado de plantillas con datos del archivo asociado.
     */
    public function obtenerTablaFiltro(?string $buscar, int $filtro): array
    {
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 6;
        $desde     = ($pagina - 1) * $porPagina;

        $params = [];
        $types  = '';
        $where  = $this->construirWhere($params, $types, $buscar, $filtro);

        $total = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total FROM plantillas_documentos pd" . $where,
            $types,
            $params,
            false
        )['total'] ?? 0);

        $totalPaginas = $total > 0 ? (int)ceil($total / $porPagina) : 1;

        $params[] = $desde;
        $params[] = $porPagina;
        $types   .= 'ii';

        $sql = "
            SELECT
                pd.id_plantilla,
                pd.id_tipo_documento,
                pd.nombre,
                pd.version,
                pd.fecha_creacion    AS crear,
                pd.fecha_modificacion AS modificar,
                pd.activo,
                CASE
                    WHEN pd.activo = 1 THEN 'Activo'
                    WHEN pd.activo = 0 THEN 'Desactivado'
                    ELSE 'Desconocido'
                END AS estado_texto,
                ds.nombre_archivo,
                ds.ruta,
                ds.tipo_mime,
                ds.extension
            FROM plantillas_documentos pd
            LEFT JOIN documentos_subidos ds
                ON ds.id_documento = pd.id_documento
               AND ds.activo = 1
            {$where}
            ORDER BY pd.id_plantilla ASC
            LIMIT ?, ?
        ";

        return [
            'plantillas' => $this->ejecutar($sql, $types, $params),
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $porPagina,
                'pagina'        => $pagina,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }

    /**
     * Totales para los filtros de estado (Total / Activo / Desactivado).
     */
    public function obtenerDatosFiltro(): array
    {
        return $this->ejecutar(
            "SELECT
                COUNT(*)                     AS Total,
                COALESCE(SUM(activo = 1), 0) AS Activo,
                COALESCE(SUM(activo = 0), 0) AS Desactivado
             FROM plantillas_documentos",
            '',
            [],
            false
        ) ?? [];
    }

    /**
     * Tipos de documento activos para el select del formulario de creación.
     */
    public function obtenerTipos_documentos(): array
    {
        return $this->ejecutar(
            "SELECT id_tipo_documento, nombre, categoria
             FROM tipo_documento
             WHERE estado = 1
             ORDER BY orden ASC"
        );
    }

    /**
     * Versión máxima y nombre del tipo de documento para calcular la siguiente versión.
     */
    public function obtenerInfoTipos(int $id_tipo_documento): array
    {
        $resultado = $this->ejecutar(
            "SELECT
                t.nombre,
                MAX(p.version) AS ultima_version
             FROM tipo_documento t
             LEFT JOIN plantillas_documentos p
                 ON t.id_tipo_documento = p.id_tipo_documento
             WHERE t.id_tipo_documento = ?
             GROUP BY t.id_tipo_documento",
            'i',
            [$id_tipo_documento],
            false
        );

        if (!$resultado) {
            throw new Exception("Tipo de documento no encontrado (ID: {$id_tipo_documento})");
        }

        return $resultado;
    }

    /**
     * Datos de la plantilla + su tipo para operaciones de desactivar/reactivar.
     */
    public function obtenerInfoPlantilla(int $id_plantilla): array
    {
        $resultado = $this->ejecutar(
            "SELECT
                p.id_plantilla,
                p.version,
                p.activo,
                p.id_tipo_documento,
                t.nombre
             FROM plantillas_documentos p
             INNER JOIN tipo_documento t
                 ON p.id_tipo_documento = t.id_tipo_documento
             WHERE p.id_plantilla = ?",
            'i',
            [$id_plantilla],
            false
        );

        if (!$resultado) {
            throw new Exception("Plantilla no encontrada (ID: {$id_plantilla})");
        }

        return $resultado;
    }

    /**
     * Solo el campo activo de una plantilla (para validar antes de cambiar estado).
     */
    public function obtenerPorId(int $id_plantilla): ?array
    {
        return $this->ejecutar(
            "SELECT activo FROM plantillas_documentos WHERE id_plantilla = ?",
            'i',
            [$id_plantilla],
            false
        ) ?: null;
    }

    /**
     * Datos del archivo para descarga segura.
     */
    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->ejecutar(
            "SELECT
                ds.id_documento,
                ds.nombre_archivo,
                ds.nombre,
                ds.ruta,
                ds.tipo_mime,
                ds.extension,
                pd.activo AS plantilla_activa,
                ds.activo AS archivo_activo
             FROM plantillas_documentos pd
             INNER JOIN documentos_subidos ds
                 ON ds.id_documento = pd.id_documento
                AND ds.tipo = 'plantilla'
             WHERE pd.id_plantilla = ?
             LIMIT 1",
            'i',
            [$id_plantilla],
            false
        ) ?: null;
    }

    // 
    //  OPERACIONES DE ESCRITURA
    // 

    /**
     * Registra el archivo físico en documentos_subidos.
     * Devuelve el id_documento generado.
     */
    public function registrarDocumento(
        string $nombre,
        string $nombre_archivo,
        string $ruta,
        string $tipo_mime,
        string $extension,
        int    $tamano_bytes,
        string $tipo,
        string $visibilidad,
        int    $id_usuario,
        int    $version
    ): int {
        $id = (int)$this->ejecutar(
            "INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension,
                 tamano_bytes, tipo, visibilidad, id_usuarios, version, activo, fecha_subida)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            'sssssissii',
            [$nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
             $tamano_bytes, $tipo, $visibilidad, $id_usuario, $version]
        );

        if ($id === 0) {
            throw new Exception("No se pudo registrar el documento en documentos_subidos");
        }

        return $id;
    }

    /**
     * Registra la plantilla con referencia al documento centralizado.
     * Devuelve el id_plantilla generado.
     */
    public function registrar(
        int    $id_tipo_documento,
        string $nombre,
        int    $version,
        int    $id_documento
    ): int {
        $id = (int)$this->ejecutar(
            "INSERT INTO plantillas_documentos
                (id_tipo_documento, nombre, version, id_documento, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, 1, NOW())",
            'isii',
            [$id_tipo_documento, $nombre, $version, $id_documento]
        );

        if ($id === 0) {
            throw new Exception("No se pudo registrar la plantilla");
        }

        return $id;
    }

    /**
     * Desactiva (soft delete) todas las plantillas activas de un tipo.
     * Devuelve filas afectadas.
     */
    public function desactivarPorTipo(int $id_tipo_documento): int
    {
        return (int)$this->ejecutar(
            "UPDATE plantillas_documentos
             SET activo = 0, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ? AND activo = 1",
            'i',
            [$id_tipo_documento]
        );
    }

    /**
     * Reactiva una versión específica desactivando el resto del mismo tipo.
     * Debe ejecutarse dentro de una transacción.
     */
    public function activarVersion(int $id_plantilla): void
    {
        // 1. Obtener el tipo al que pertenece (con bloqueo pesimista)
        $row = $this->ejecutar(
            "SELECT id_tipo_documento FROM plantillas_documentos WHERE id_plantilla = ? FOR UPDATE",
            'i',
            [$id_plantilla],
            false
        );

        if (!$row) {
            throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
        }

        $id_tipo = (int)$row['id_tipo_documento'];

        // 2. Bloquear todos los del tipo
        $this->ejecutar(
            "SELECT id_plantilla FROM plantillas_documentos WHERE id_tipo_documento = ? FOR UPDATE",
            'i',
            [$id_tipo]
        );

        // 3. Desactivar todos del tipo
        $this->ejecutar(
            "UPDATE plantillas_documentos SET activo = 0 WHERE id_tipo_documento = ?",
            'i',
            [$id_tipo]
        );

        // 4. Activar la versión seleccionada
        $afectadas = (int)$this->ejecutar(
            "UPDATE plantillas_documentos SET activo = 1, fecha_modificacion = NOW() WHERE id_plantilla = ?",
            'i',
            [$id_plantilla]
        );

        if ($afectadas === 0) {
            throw new Exception("No se pudo activar la plantilla (ID: {$id_plantilla})");
        }
    }

    /**
     * Bloqueo pesimista de registros de un tipo (dentro de transacción).
     */
    public function bloquearTabla(int $id_tipo_documento): void
    {
        $this->ejecutar(
            "SELECT id_plantilla FROM plantillas_documentos WHERE id_tipo_documento = ? FOR UPDATE",
            'i',
            [$id_tipo_documento]
        );
    }

    /**
     * Calcula la siguiente versión para un tipo de documento.
     */
    public function obtenerSiguienteVersion(int $id_tipo_documento): int
    {
        $resultado = $this->ejecutar(
            "SELECT COALESCE(MAX(version), 0) + 1 AS version
             FROM plantillas_documentos
             WHERE id_tipo_documento = ?",
            'i',
            [$id_tipo_documento],
            false
        );

        return (int)($resultado['version'] ?? 1);
    }

    /**
     * Registra un evento en el historial de la plantilla.
     */
    public function registrarHistorial(
        int    $id_plantilla,
        int    $id_usuario,
        string $accion,
        string $descripcion
    ): void {
        $this->ejecutar(
            "INSERT INTO historial_plantillas (id_plantilla, id_usuarios, accion, descripcion, fecha)
             VALUES (?, ?, ?, ?, NOW())",
            'iiss',
            [$id_plantilla, $id_usuario, $accion, $descripcion]
        );
    }

    // 
    //  LÍNEA DE TIEMPO / HISTORIAL
    // 

    /**
     * Historial paginado de eventos de un tipo de documento, agrupado por versión.
     */
    public function linea_tiempo(int $id_tipo_documento, int $pagina = 1, int $porPagina = 5): array
    {
        $pagina = max(1, $pagina);
        $desde  = ($pagina - 1) * $porPagina;

        $total = (int)($this->ejecutar(
            "SELECT COUNT(*) AS total
             FROM historial_plantillas h
             INNER JOIN plantillas_documentos p ON h.id_plantilla = p.id_plantilla
             WHERE p.id_tipo_documento = ?",
            'i',
            [$id_tipo_documento],
            false
        )['total'] ?? 0);

        $totalPaginas = $total > 0 ? (int)ceil($total / $porPagina) : 1;

        $historial = $this->ejecutar(
            "SELECT
                h.id_plantilla,
                p.version,
                ds.nombre_archivo,
                h.accion     AS tipo_evento,
                h.descripcion,
                h.fecha,
                u.nombre     AS usuario
             FROM historial_plantillas h
             INNER JOIN plantillas_documentos p
                 ON h.id_plantilla = p.id_plantilla
             LEFT JOIN documentos_subidos ds
                 ON ds.id_documento = p.id_documento AND ds.activo = 1
             LEFT JOIN usuarios u
                 ON h.id_usuarios = u.id_usuarios
             WHERE p.id_tipo_documento = ?
             ORDER BY p.version DESC, h.fecha DESC
             LIMIT ?, ?",
            'iii',
            [$id_tipo_documento, $desde, $porPagina]
        );

        $agrupado = [];
        foreach ($historial as $item) {
            $agrupado['Versión ' . $item['version']][] = $item;
        }

        return [
            'datos'      => $agrupado,
            'paginacion' => [
                'total'         => $total,
                'por_pagina'    => $porPagina,
                'pagina'        => $pagina,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }
}
