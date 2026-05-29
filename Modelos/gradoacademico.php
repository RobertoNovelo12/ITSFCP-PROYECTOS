<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class GradoAcademico
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

        $total = $this->obtenerCantidadGradoAcademico($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_grado,
                    nombre,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM grados_academicos";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_grado ASC LIMIT ?, ?";

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
            "grados_academicos" => $data,
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
    public function obtenerCantidadGradoAcademico($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM grados_academicos";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidadGradoAcademico): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidadGradoAcademico): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_grado): array
    {
        $sql = "SELECT 
                    id_grado, 
                    nombre,                    
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM grados_academicos
                WHERE id_grado = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Grado Académico no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerDetalles($id_grado): array
    {
        $sql = "SELECT 
                    id_grado, 
                    nombre, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM grados_academicos
                WHERE id_grado = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Grado Académico no encontrado");

        return $registro;
    }

    /**
     * Registra un nuevo Grado Académico.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarGradoAcademico(string $nombre): int
    {
        $validacion = $this->verificarGradoAcademico($nombre);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Grado Académico activo con ese nombre.");
        }

        $sql = "INSERT INTO grados_academicos 
            (nombre, estado, fecha_creacion) 
            VALUES (?, 1, NOW())";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("s", $nombre);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrarGradoAcademico): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Edita un Grado Académico existente.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param string $nombre
     * @param int $id_grado
     * @return int ID editado
     * @throws Exception
     */
    public function editarGradoAcademico(string $nombre, int $id_grado): int
    {
        $sql = "UPDATE grados_academicos SET nombre = ?, fecha_modificacion = NOW() WHERE id_grado = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (editarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("si", $nombre, $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (editarGradoAcademico): " . $stmt->error);

        $stmt->close();
        return $id_grado;
    }

    /**
     * Reactiva un Grado Académico previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_grado
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_grado): void
    {
        $registro = $this->obtenerPorId($id_grado, true);
        if (!$registro) throw new Exception("Grado Académico no encontrado.");

        $sqlDatos = "SELECT nombre FROM grados_academicos WHERE id_grado = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);
        if (!$stmtDatos) throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);

        $stmtDatos->bind_param("i", $id_grado);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) throw new Exception("No se pudieron obtener datos de Grado Académico.");

        $validacion = $this->verificarGradoAcademico($datos['nombre']);
        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un Grado Académico activo con el mismo nombre.");
        }

        $sql = "UPDATE grados_academicos 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_grado = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (reactivarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (reactivarGradoAcademico): " . $stmt->error);

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
        $sql = "SELECT id_grado FROM grados_academicos WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un Grado Académico.
     *
     * @param int $id_grado
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_grados_academicos(int $id_grado): int
    {
        $sql = "UPDATE grados_academicos 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_grado = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (eliminar_grados_academicos): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (eliminar_grados_academicos): " . $stmt->error);

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    /**
     * Verifica duplicidad de Grado Académico por nombre.
     *
     * @param string $nombre
     * @return array
     * @throws Exception
     */
    public function verificarGradoAcademico(string $nombre): array
    {
        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM grados_academicos
                    WHERE estado = 1 AND nombre = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM grados_academicos
                    WHERE estado = 0 AND nombre = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (verificarGradoAcademico): " . $this->con->error);

        $stmt->bind_param("ss", $nombre, $nombre);
        if (!$stmt->execute()) throw new Exception("Error en execute (verificarGradoAcademico): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene un Grado Académico por ID.
     *
     * @param int $id_grado
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_grado, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM grados_academicos WHERE id_grado = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);

        $stmt->bind_param("i", $id_grado);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Verifica si existe otro Grado Académico con el mismo nombre, excluyendo el ID actual.
     *
     * @param int $id_grado
     * @param string $nombre
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_grado, $nombre): ?array
    {
        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM grados_academicos
        WHERE estado = 1 AND nombre = ? AND id_grado != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM grados_academicos
        WHERE estado = 0 AND nombre = ? AND id_grado != ?
    ) AS desactivado
                FROM grados_academicos 
                WHERE id_grado != ? AND nombre = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorIdDiferente): " . $this->con->error);

        $stmt->bind_param(
            "sisiis",
            $nombre,
            $id_grado,
            $nombre,
            $id_grado,
            $id_grado,
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
