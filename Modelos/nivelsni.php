<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class NivelSNI
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
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
                    COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
                FROM niveles_sni";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $resultado;
    }

    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        if ($filtro == 0) $where[] = "estado = 0";
        if ($filtro == 1) $where[] = "estado = 1";
        elseif ($filtro == 2) $where[] = "estado IN (0,1)";

        if (!empty($buscar)) {
            $where[] = "(nombre LIKE ? OR fecha_creacion LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "ss";
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

        $total = $this->obtenerCantidadNivelSNI($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_nivel,
                    nombre,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM niveles_sni";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_nivel ASC LIMIT ?, ?";

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
            "niveles_sni" => $data,
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    /**
     * Obtiene total de registros con filtros
     */
    public function obtenerCantidadNivelSNI($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM niveles_sni";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidadNivelSNI): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidadNivelSNI): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_nivel): array
    {
        $sql = "SELECT 
                    id_nivel, 
                    nombre,                    
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM niveles_sni
                WHERE id_nivel = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);

        $stmt->bind_param("i", $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Nivel SNI no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerDetalles($id_nivel): array
    {
        $sql = "SELECT 
                    id_nivel, 
                    nombre, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM niveles_sni
                WHERE id_nivel = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);

        $stmt->bind_param("i", $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Nivel SNI no encontrado");

        return $registro;
    }

    /**
     * Registra un nuevo Nivel SNI.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarNivelSNI(string $nombre): int
    {
        $validacion = $this->verificarNivelSNI($nombre);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Nivel SNI activo con ese nombre.");
        }

        $sql = "INSERT INTO niveles_sni 
            (nombre, estado, fecha_creacion) 
            VALUES (?, 1, NOW())";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrarNivelSNI): " . $this->con->error);

        $stmt->bind_param("s", $nombre);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrarNivelSNI): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Edita un Nivel SNI existente.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @param int $id_nivel
     * @return int ID editado
     * @throws Exception
     */
    public function editarNivelSNI(string $nombre, int $id_nivel): int
    {
        $sql = "UPDATE niveles_sni SET nombre = ?, fecha_modificacion = NOW() WHERE id_nivel = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (editarNivelSNI): " . $this->con->error);

        $stmt->bind_param("si", $nombre, $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (editarNivelSNI): " . $stmt->error);

        $stmt->close();
        return $id_nivel;
    }

    /**
     * Reactiva un Nivel SNI previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_nivel
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_nivel): void
    {
        $registro = $this->obtenerPorId($id_nivel, true);
        if (!$registro) throw new Exception("Nivel SNI no encontrado.");

        $sqlDatos = "SELECT nombre FROM niveles_sni WHERE id_nivel = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);
        if (!$stmtDatos) throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);

        $stmtDatos->bind_param("i", $id_nivel);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) throw new Exception("No se pudieron obtener datos de Nivel SNI.");

        $validacion = $this->verificarNivelSNI($datos['nombre']);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Nivel SNI activo con el mismo nombre.");
        }

        $sql = "UPDATE niveles_sni 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_nivel = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (reactivarNivelSNI): " . $this->con->error);

        $stmt->bind_param("i", $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (reactivarNivelSNI): " . $stmt->error);

        if ($stmt->affected_rows === 0) {
            throw new Exception("El registro ya estaba activo o no se pudo actualizar.");
        }

        $stmt->close();
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
        $sql = "SELECT id_nivel FROM niveles_sni WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un Nivel SNI.
     *
     * @param int $id_nivel
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_niveles_sni(int $id_nivel): int
    {
        $sql = "UPDATE niveles_sni 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_nivel = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (eliminar_niveles_sni): " . $this->con->error);

        $stmt->bind_param("i", $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (eliminar_niveles_sni): " . $stmt->error);

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    /**
     * Verifica duplicidad de Nivel SNI por nombre.
     *
     * @param string $nombre
     * @return array
     * @throws Exception
     */
    public function verificarNivelSNI(string $nombre): array
    {
        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM niveles_sni
                    WHERE estado = 1 AND nombre = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM niveles_sni
                    WHERE estado = 0 AND nombre = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (verificarNivelSNI): " . $this->con->error);

        $stmt->bind_param("ss", $nombre, $nombre);
        if (!$stmt->execute()) throw new Exception("Error en execute (verificarNivelSNI): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene un Nivel SNI por ID.
     *
     * @param int $id_nivel
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_nivel, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM niveles_sni WHERE id_nivel = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);

        $stmt->bind_param("i", $id_nivel);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Verifica si existe otro Nivel SNI con el mismo nombre, excluyendo el ID actual.
     *
     * @param int $id_nivel
     * @param string $nombre
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_nivel, $nombre): ?array
    {
        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM niveles_sni
        WHERE estado = 1 AND nombre = ? AND id_nivel != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM niveles_sni
        WHERE estado = 0 AND nombre = ? AND id_nivel != ?
    ) AS desactivado
                FROM niveles_sni 
                WHERE id_nivel != ? AND nombre = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorIdDiferente): " . $this->con->error);

        $stmt->bind_param(
            "sisiis",
            $nombre,
            $id_nivel,
            $nombre,
            $id_nivel,
            $id_nivel,
            $nombre
        );

        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorIdDiferente): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }
}
