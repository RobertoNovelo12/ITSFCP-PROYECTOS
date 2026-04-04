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
        if (!$stmt) throw new Exception("Error en prepare (obtenerTipos): " . $this->con->error);

        $stmt->bind_param("i", $id_tipo_documento);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerTipos): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Plantillas de documentos no encontrado");

        return $registro;
    }

    /**
     * Registra una nueva Plantilla de documento.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @param int $version
     * @throws Exception
     */
    public function registrar(int $id_tipo_documento, string $nombre, int $version, string $nombre_archivo, string $ruta_archivo)
    {

        $sql = "
        INSERT INTO plantillas_documentos 
            (id_tipo_documento, nombre, version, ruta, activo, nombre_archivo, fecha_creacion) 
            VALUES (?, ?, ?, ?, 1, ?, NOW())";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrar): " . $this->con->error);

        $stmt->bind_param("isiss", $id_tipo_documento, $nombre, $version, $ruta_archivo,$nombre_archivo);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrar): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Bloquea únicamente los registros activos.
     * IMPORTANTE: Debe ejecutarse dentro de una transacción.
     *
     * @return void
     * @throws Exception
     */
    public function bloquear_tabla(): void
    {
        $sql = "SELECT id_plantilla FROM plantillas_documentos WHERE activo = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
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
}
