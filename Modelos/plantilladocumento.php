<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class plantilladocumento
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        if ($filtro == 0) $where[] = "activo = 0";
        if ($filtro == 1) $where[] = "activo = 1";
        elseif ($filtro == 2) $where[] = "activo IN (0,1)";

        if (!empty($buscar)) {
            $where[] = "(nombre LIKE ?)";
            $params[] = "%$buscar%";
            $types .= "s";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    /**
     * Obtiene tabla principal con paginación
     */
    public function obtenerTablaFiltro($buscar, $filtro): array
    {
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde = ($pagina - 1) * $por_pagina;

        $params = [];
        $types = "";

        $total = $this->obtenerCantidad($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    d.id_plantilla,
                    d.id_tipo_documento,
                    d.nombre,
                    d.version,
                    d.nombre_archivo,
                    d.fecha_modificacion AS modificar,
                    d.fecha_creacion AS crear,
                    CASE 
                        WHEN d.activo = 1 THEN 'Activo'        
                        WHEN d.activo = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS activo
                FROM plantillas_documentos AS d";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_plantilla ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "plantillas" => $data,
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    /**
     * Obtiene datos para filtros (totales, activos, desactivados)
     */
    public function obtenerDatosFiltro($rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
                FROM plantillas_documentos";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $resultado;
    }

    /**
     * Obtiene total de registros con filtros
     */
    public function obtenerCantidad($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM plantillas_documentos";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidad): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidad): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }


    /**
     * Obtiene datos para vista de detalles de tipos de documentos
     */
    public function obtenerTipos_documentos(): array
    {
        $sql = "SELECT 
                    id_tipo_documento, 
                    nombre,
                    categoria
                FROM tipo_documento
                WHERE estado = 1";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerTipos_documentos): " . $this->con->error);

        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTipos_documentos): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$registro) throw new Exception("Tipo de documento no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerInfoTipos($id_tipo_documento): array
    {
        $sql = "SELECT 
    MAX(p.version) AS ultima_version,
    t.nombre
FROM tipo_documento t
LEFT JOIN plantillas_documentos p 
    ON t.id_tipo_documento = p.id_tipo_documento
WHERE t.id_tipo_documento = ?
GROUP BY t.id_tipo_documento;";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerInfoTipos): " . $this->con->error);

        $stmt->bind_param("i", $id_tipo_documento);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerInfoTipos): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Plantillas de documentos no encontrado");

        return $registro;
    }

    public function obtenerInfoPlantilla($id_plantilla)
    {
        $sql = "SELECT p.version, t.nombre, t.id_tipo_documento
            FROM plantillas_documentos p
            INNER JOIN tipo_documento t 
                ON p.id_tipo_documento = t.id_tipo_documento
            WHERE p.id_plantilla = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare: " . $this->con->error);

        $stmt->bind_param("i", $id_plantilla);
        if (!$stmt->execute()) throw new Exception("Error en execute: " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Plantilla no encontrada");

        return $registro;
    }
    /**
     * Inserta el archivo físico en documentos_subidos.
     * Devuelve el id_documento generado.
     */
    public function registrarDocumento(
        string $nombre,
        string $nombre_archivo,
        string $ruta,
        string $tipo_mime,
        string $extension,
        int    $tamano_bytes,
        string $tipo,          // 'plantilla'
        string $visibilidad,   // 'privado' | 'publico'
        int    $id_usuario,
        int    $version
    ): int {
        $sql = "
        INSERT INTO documentos_subidos
            (nombre, nombre_archivo, ruta, tipo_mime, extension, tamano_bytes,
             tipo, visibilidad, id_usuario, version, activo, fecha_subida)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (registrarDocumento): " . $this->con->error);

        $stmt->bind_param(
            "sssssissii",
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $tipo,
            $visibilidad,
            $id_usuario,
            $version
        );

        if (!$stmt->execute()) throw new Exception("Error execute (registrarDocumento): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Registra la plantilla con referencia al documento centralizado.
     * nombre_archivo y ruta ya NO van aquí.
     */
    public function registrar(
        int    $id_tipo_documento,
        string $nombre,
        int    $version,
        int    $id_documento
    ): int {
        $sql = "
        INSERT INTO plantillas_documentos
            (id_tipo_documento, nombre, version, id_documento, activo, fecha_creacion)
        VALUES (?, ?, ?, ?, 1, NOW())
    ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (registrar): " . $this->con->error);

        $stmt->bind_param("isii", $id_tipo_documento, $nombre, $version, $id_documento);
        if (!$stmt->execute()) throw new Exception("Error execute (registrar): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Sin cambios de firma — solo se mantiene igual.
     */
    public function registrarHistorial(
        int    $id_plantilla,
        int    $id_usuarios,
        string $accion,
        string $descripcion
    ): bool {
        $sql = "
        INSERT INTO historial_plantillas
            (id_plantilla, id_usuarios, accion, descripcion, fecha)
        VALUES (?, ?, ?, ?, NOW())
    ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare (registrarHistorial): " . $this->con->error);

        $stmt->bind_param("iiss", $id_plantilla, $id_usuarios, $accion, $descripcion);
        if (!$stmt->execute()) throw new Exception("Error execute (registrarHistorial): " . $stmt->error);

        $stmt->close();
        return true;
    }



    /**
     * Bloquea registros.
     * IMPORTANTE: Debe ejecutarse dentro de una transacción.
     *
     * @return void
     * @throws Exception
     */
    public function bloquear_tabla($id_tipo_documento): void
    {
        $sql = "SELECT id_plantilla FROM plantillas_documentos WHERE id_tipo_documento = ? FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        $stmt->bind_param("i", $id_tipo_documento);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->close();
    }

    /**
     * Obtiene una Plantilla de documentos por ID.
     *
     * @param int $id_grado
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_plantilla, bool $forUpdate = false): ?array
    {
        $sql = "SELECT activo FROM plantillas_documentos WHERE id_plantilla = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);

        $stmt->bind_param("i", $id_plantilla);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Eliminación lógica (soft delete) de plantilla de documentos.
     *
     * @param int $id_grado
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function desactivarPorTipo(int $id_tipo_documento): int
    {
        $sql = "UPDATE plantillas_documentos SET activo = 0, fecha_modificacion = NOW() WHERE id_tipo_documento = ? AND activo = 1";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (desactivarPorTipo): " . $this->con->error);

        $stmt->bind_param("i", $id_tipo_documento);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (desactivarPorTipo): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    /**
     * Reactiva una Plantilla de documento previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_plantilla
     * @return void
     * @throws Exception
     */
    public function activarVersion(int $id_plantilla): void
    {
        // 1. Obtener tipo
        $sql = "SELECT id_tipo_documento 
            FROM plantillas_documentos 
            WHERE id_plantilla = ? FOR UPDATE";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error prepare");

        $stmt->bind_param("i", $id_plantilla);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) throw new Exception("No existe");

        $id_tipo = $res['id_tipo_documento'];

        // 2. Bloquear registros del tipo
        $sql = "SELECT id_plantilla 
            FROM plantillas_documentos 
            WHERE id_tipo_documento = ? 
            FOR UPDATE";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tipo);
        $stmt->execute();
        $stmt->close();

        // 3. Desactivar todas
        $sql = "UPDATE plantillas_documentos 
            SET activo = 0 
            WHERE id_tipo_documento = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_tipo);
        $stmt->execute();
        $stmt->close();

        // 4. Activar seleccionada
        $sql = "UPDATE plantillas_documentos 
            SET activo = 1, fecha_modificacion = NOW()
            WHERE id_plantilla = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_plantilla);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("No se pudo activar");
        }

        $stmt->close();
    }

    public function obtenerSiguienteVersion($id_tipo_documento): int
    {
        $sql = "SELECT COALESCE(MAX(version), 0) + 1 AS version 
            FROM plantillas_documentos 
            WHERE id_tipo_documento = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare: " . $this->con->error);

        $stmt->bind_param("i", $id_tipo_documento);
        if (!$stmt->execute()) throw new Exception("Error en execute: " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)$resultado['version']; // Devueve el número
    }

    //OBTENR INFORMACIÓN DEL HISTORIAL DE PLANTILLAS DE DOCUMENTOS PARA EL TIMELINE
    public function linea_tiempo($id_tipo_documento, $pagina = 1, $por_pagina = 5)
    {
        $pagina = max(1, (int)$pagina);
        $desde = ($pagina - 1) * $por_pagina;

        // TOTAL
        $sqlTotal = "SELECT COUNT(*) as total
                 FROM historial_plantillas h
                 INNER JOIN plantillas_documentos p 
                 ON h.id_plantilla = p.id_plantilla
                 WHERE p.id_tipo_documento = ?";

        $stmt = $this->con->prepare($sqlTotal);
        $stmt->bind_param("i", $id_tipo_documento);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $total_paginas = ceil($total / $por_pagina);

        // DATOS
        $sql = "SELECT 
                h.id_plantilla,
                p.version,
                p.nombre_archivo,
                h.accion AS tipo_evento,
                h.descripcion,
                h.fecha,
                u.nombre AS usuario
            FROM historial_plantillas h
            INNER JOIN plantillas_documentos p 
                ON h.id_plantilla = p.id_plantilla
            LEFT JOIN usuarios u 
                ON h.id_usuarios = u.id_usuarios
            WHERE p.id_tipo_documento = ?
            ORDER BY p.version DESC, h.fecha DESC
            LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_tipo_documento, $desde, $por_pagina);
        $stmt->execute();

        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // AGRUPAR POR VERSION
        $agrupado = [];
        foreach ($historial as $item) {
            $version = "Versión " . $item['version'];
            $agrupado[$version][] = $item;
        }

        return [
            "datos" => $agrupado,
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }
}
