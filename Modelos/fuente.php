<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Fuente
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
                FROM fuente";

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
            $where[] = "(fuente LIKE ? OR fecha_creacion LIKE ?)";
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

        $total = $this->obtenerCantidadFuente($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_fuente,
                    fuente,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM fuente";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_fuente ASC LIMIT ?, ?";

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
            "fuente" => $data,
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
    public function obtenerCantidadFuente($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM fuente";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidadFuente): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidadFuente): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_fuente): array
    {
        $sql = "SELECT 
                    id_fuente, 
                    fuente,                    
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM fuente
                WHERE id_fuente = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);

        $stmt->bind_param("i", $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Fuente no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerDetalles($id_fuente): array
    {
        $sql = "SELECT 
                    id_fuente, 
                    fuente, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM fuente
                WHERE id_fuente = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);

        $stmt->bind_param("i", $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Fuente no encontrado");

        return $registro;
    }

    /**
     * Registra un nuevo Fuente.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $fuente
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarFuente(string $fuente): int
    {
        $validacion = $this->verificarFuente($fuente);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Fuente activo con ese nombre.");
        }

        $sql = "INSERT INTO fuente 
            (fuente, estado, fecha_creacion) 
            VALUES (?, 1, NOW())";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrarFuente): " . $this->con->error);

        $stmt->bind_param("s", $fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrarFuente): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Edita un Fuente existente.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $fuente
     * @param int $id_fuente
     * @return int ID editado
     * @throws Exception
     */
    public function editarFuente(string $fuente, int $id_fuente): int
    {
        $sql = "UPDATE fuente SET fuente = ?, fecha_modificacion = NOW() WHERE id_fuente = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (editarFuente): " . $this->con->error);

        $stmt->bind_param("si", $fuente, $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (editarFuente): " . $stmt->error);

        $stmt->close();
        return $id_fuente;
    }

    /**
     * Reactiva un Fuente previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_fuente
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_fuente): void
    {
        $registro = $this->obtenerPorId($id_fuente, true);
        if (!$registro) throw new Exception("Fuente no encontrado.");

        $sqlDatos = "SELECT fuente FROM fuente WHERE id_fuente = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);
        if (!$stmtDatos) throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);

        $stmtDatos->bind_param("i", $id_fuente);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) throw new Exception("No se pudieron obtener datos de Fuente.");

        $validacion = $this->verificarFuente($datos['fuente']);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Fuente activo con el mismo nombre.");
        }

        $sql = "UPDATE fuente 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_fuente = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (reactivarFuente): " . $this->con->error);

        $stmt->bind_param("i", $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (reactivarFuente): " . $stmt->error);

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
        $sql = "SELECT id_fuente FROM fuente WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un Fuente.
     *
     * @param int $id_fuente
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_fuente(int $id_fuente): int
    {
        $sql = "UPDATE fuente 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_fuente = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (eliminar_fuente): " . $this->con->error);

        $stmt->bind_param("i", $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (eliminar_fuente): " . $stmt->error);

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    /**
     * Verifica duplicidad de Fuente por nombre.
     *
     * @param string $fuente
     * @return array
     * @throws Exception
     */
    public function verificarFuente(string $fuente): array
    {
        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM fuente
                    WHERE estado = 1 AND fuente = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM fuente
                    WHERE estado = 0 AND fuente = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (verificarFuente): " . $this->con->error);

        $stmt->bind_param("ss", $fuente, $fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (verificarFuente): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene un Fuente por ID.
     *
     * @param int $id_fuente
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_fuente, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM fuente WHERE id_fuente = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);

        $stmt->bind_param("i", $id_fuente);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Verifica si existe otro Fuente con el mismo nombre, excluyendo el ID actual.
     *
     * @param int $id_fuente
     * @param string $fuente
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_fuente, $fuente): ?array
    {
        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM fuente
        WHERE estado = 1 AND fuente = ? AND id_fuente != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM fuente
        WHERE estado = 0 AND fuente = ? AND id_fuente != ?
    ) AS desactivado
                FROM fuente 
                WHERE id_fuente != ? AND fuente = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorIdDiferente): " . $this->con->error);

        $stmt->bind_param(
            "sisiis",
            $fuente,
            $id_fuente,
            $fuente,
            $id_fuente,
            $id_fuente,
            $fuente
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
