<?php
// Repositorios/PlantillaDocumentoRepositorio.php

require_once __DIR__ . '/../Modelos/BaseModelo.php';

/**
 * PlantillaDocumentoRepositorio
 *
 * Responsabilidad exclusiva: ejecutar consultas SQL sobre las tablas
 * `plantillas_documentos`, `documentos_subidos` e `historial_plantillas`.
 * No contiene lógica de negocio.
 */
class PlantillaDocumentoRepositorio extends BaseModelo
{
    public function __construct(mysqli $conn)
    {
        parent::__construct($conn);
    }


    // 
    // CONSULTAS PRINCIPALES
    // 

    public function contarPlantillas(?string $buscar, int $filtro): int
    {
        $params = [];
        $types  = '';
        $where  = $this->construirWhere($params, $types, $buscar, $filtro);

        $total = $this->ejecutar(
            "SELECT COUNT(*) AS total FROM plantillas_documentos pd" . $where,
            $types,
            $params,
            false
        );

        return (int)($total['total'] ?? 0);
    }

    public function listarPlantillas(?string $buscar, int $filtro, int $desde, int $porPagina): array
    {
        $params = [];
        $types  = '';
        $where  = $this->construirWhere($params, $types, $buscar, $filtro);

        $params[] = $desde;
        $params[] = $porPagina;
        $types   .= 'ii';

        return $this->ejecutar(
            "SELECT
                pd.id_plantilla,
                pd.id_tipo_documento,
                pd.nombre,
                pd.version,
                pd.fecha_creacion     AS crear,
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
             LIMIT ?, ?",
            $types,
            $params
        );
    }

    public function listarTiposDocumentos(): array
    {
        return $this->ejecutar(
            'SELECT id_tipo_documento, nombre, categoria
             FROM tipo_documento
             WHERE estado = 1
             ORDER BY orden ASC'
        );
    }

    public function obtenerInfoTipo(int $id_tipo_documento): ?array
    {
        $fila = $this->ejecutar(
            'SELECT
                t.nombre,
                MAX(p.version) AS ultima_version
             FROM tipo_documento t
             LEFT JOIN plantillas_documentos p
                 ON t.id_tipo_documento = p.id_tipo_documento
             WHERE t.id_tipo_documento = ?
             GROUP BY t.id_tipo_documento',
            'i',
            [$id_tipo_documento],
            false
        );

        return $fila ?: null;
    }

    public function obtenerInfoPlantilla(int $id_plantilla): ?array
    {
        $fila = $this->ejecutar(
            'SELECT
                p.id_plantilla,
                p.version,
                p.activo,
                p.id_tipo_documento,
                t.nombre
             FROM plantillas_documentos p
             INNER JOIN tipo_documento t
                 ON p.id_tipo_documento = t.id_tipo_documento
             WHERE p.id_plantilla = ?',
            'i',
            [$id_plantilla],
            false
        );

        return $fila ?: null;
    }

    public function buscarPorId(int $id_plantilla): ?array
    {
        $fila = $this->ejecutar(
            'SELECT activo FROM plantillas_documentos WHERE id_plantilla = ?',
            'i',
            [$id_plantilla],
            false
        );

        return $fila ?: null;
    }

    public function buscarArchivoPlantilla(int $id_plantilla): ?array
    {
        $fila = $this->ejecutar(
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
        );

        return $fila ?: null;
    }

    public function obtenerSiguienteVersion(int $id_tipo_documento): int
    {
        $fila = $this->ejecutar(
            'SELECT COALESCE(MAX(version), 0) + 1 AS version
             FROM plantillas_documentos
             WHERE id_tipo_documento = ?',
            'i',
            [$id_tipo_documento],
            false
        );

        return (int)($fila['version'] ?? 1);
    }


    // 
    // OPERACIONES DE ESCRITURA
    // 

    public function insertarDocumento(
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
        $this->ejecutar(
            'INSERT INTO documentos_subidos
                (nombre, nombre_archivo, ruta, tipo_mime, extension,
                 tamano_bytes, tipo, visibilidad, id_usuarios, version, activo, fecha_subida)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            'sssssissii',
            [$nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
             $tamano_bytes, $tipo, $visibilidad, $id_usuario, $version]
        );

        return (int)$this->conn->insert_id;
    }

    public function insertarPlantilla(
        int    $id_tipo_documento,
        string $nombre,
        int    $version,
        int    $id_documento
    ): int {
        $this->ejecutar(
            'INSERT INTO plantillas_documentos
                (id_tipo_documento, nombre, version, id_documento, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, 1, NOW())',
            'isii',
            [$id_tipo_documento, $nombre, $version, $id_documento]
        );

        return (int)$this->conn->insert_id;
    }

    public function desactivarPorTipo(int $id_tipo_documento): int
    {
        $this->ejecutar(
            'UPDATE plantillas_documentos
             SET activo = 0, fecha_modificacion = NOW()
             WHERE id_tipo_documento = ? AND activo = 1',
            'i',
            [$id_tipo_documento]
        );

        return $this->conn->affected_rows;
    }

    /**
     * Reactiva una versión específica desactivando el resto del mismo tipo.
     * Debe ejecutarse dentro de una transacción.
     *
     * @throws Exception
     */
    public function activarVersion(int $id_plantilla): void
    {
        $row = $this->ejecutar(
            'SELECT id_tipo_documento FROM plantillas_documentos WHERE id_plantilla = ? FOR UPDATE',
            'i',
            [$id_plantilla],
            false
        );

        if (!$row) {
            throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
        }

        $id_tipo = (int)$row['id_tipo_documento'];

        $this->ejecutar(
            'SELECT id_plantilla FROM plantillas_documentos WHERE id_tipo_documento = ? FOR UPDATE',
            'i',
            [$id_tipo]
        );

        $this->ejecutar(
            'UPDATE plantillas_documentos SET activo = 0 WHERE id_tipo_documento = ?',
            'i',
            [$id_tipo]
        );

        $this->ejecutar(
            'UPDATE plantillas_documentos SET activo = 1, fecha_modificacion = NOW() WHERE id_plantilla = ?',
            'i',
            [$id_plantilla]
        );

        if ($this->conn->affected_rows === 0) {
            throw new Exception("No se pudo activar la plantilla (ID: {$id_plantilla})");
        }
    }

    public function bloquearTabla(int $id_tipo_documento): void
    {
        $this->ejecutar(
            'SELECT id_plantilla FROM plantillas_documentos WHERE id_tipo_documento = ? FOR UPDATE',
            'i',
            [$id_tipo_documento]
        );
    }


    // 
    // HISTORIAL
    // 

    public function insertarHistorial(
        int    $id_plantilla,
        int    $id_usuario,
        string $accion,
        string $descripcion
    ): void {
        $this->ejecutar(
            'INSERT INTO historial_plantillas (id_plantilla, id_usuarios, accion, descripcion, fecha)
             VALUES (?, ?, ?, ?, NOW())',
            'iiss',
            [$id_plantilla, $id_usuario, $accion, $descripcion]
        );
    }

    public function contarHistorial(int $id_tipo_documento): int
    {
        $fila = $this->ejecutar(
            'SELECT COUNT(*) AS total
             FROM historial_plantillas h
             INNER JOIN plantillas_documentos p ON h.id_plantilla = p.id_plantilla
             WHERE p.id_tipo_documento = ?',
            'i',
            [$id_tipo_documento],
            false
        );

        return (int)($fila['total'] ?? 0);
    }

    public function listarHistorial(int $id_tipo_documento, int $desde, int $porPagina): array
    {
        return $this->ejecutar(
            'SELECT
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
             LIMIT ?, ?',
            'iii',
            [$id_tipo_documento, $desde, $porPagina]
        );
    }


    // 
    // HELPER PRIVADO: WHERE
    // 

    private function construirWhere(array &$params, string &$types, ?string $buscar, int $filtro): string
    {
        $where = [];

        if ($filtro === 0) {
            $where[] = 'pd.activo = 0';
        } elseif ($filtro === 1) {
            $where[] = 'pd.activo = 1';
        }

        if (!empty($buscar)) {
            $where[]  = 'pd.nombre LIKE ?';
            $params[] = "%{$buscar}%";
            $types   .= 's';
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }
}