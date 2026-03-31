<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Director
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
                    COALESCE(SUM(CASE WHEN d.estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN d.estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
                FROM director d";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $resultado;
    }

    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     * Director usa JOIN con grados_academicos para mostrar el nombre del grado
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        if ($filtro == 0) $where[] = "d.estado = 0";
        if ($filtro == 1) $where[] = "d.estado = 1";
        elseif ($filtro == 2) $where[] = "d.estado IN (0,1)";

        // Búsqueda por nombre, apellido, correo y fecha_creacion
        if (!empty($buscar)) {
            $where[] = "(d.nombre LIKE ? OR d.apellido LIKE ? OR d.correo LIKE ? OR d.fecha_creacion LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "ssss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    /**
     * Obtiene tabla principal con paginación
     * Incluye JOIN con grados_academicos para mostrar el nombre del grado
     */
    public function obtenerTablaFiltro($buscar, $filtro): array
    {
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde = ($pagina - 1) * $por_pagina;

        $params = [];
        $types = "";

        $total = $this->obtenerCantidadDirector($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    d.id_director,
                    d.nombre,
                    d.apellido,
                    d.correo,
                    d.telefono,
                    g.nombre AS nombre_grado,
                    d.fecha_creacion AS crear,
                    d.fecha_inicio AS inicio,
                    d.fecha_final AS fin,
                    CASE 
                        WHEN d.estado = 1 THEN 'Activo'        
                        WHEN d.estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM director d
                INNER JOIN grados_academicos g ON d.id_grado = g.id_grado";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY d.id_director ASC LIMIT ?, ?";

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
            "director" => $data,
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
    public function obtenerCantidadDirector($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        // Nota: necesitamos alias d para el construirWhere
        $sql = "SELECT COUNT(*) AS total FROM director d";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerCantidadDirector): " . $this->con->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerCantidadDirector): " . $stmt->error);

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición (incluye id_grado para el select)
     */
    public function obtenerEditar($id_director): array
    {
        $sql = "SELECT 
                    id_director,
                    id_grado, 
                    nombre,
                    apellido,
                    correo,
                    telefono,
                    fecha_inicio AS inicio,
                    fecha_final AS fin,
                    motivo_fin,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM director
                WHERE id_director = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);

        $stmt->bind_param("i", $id_director);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Director no encontrado");

        return $registro;
    }

    /**
     * Obtiene datos para vista de detalles (incluye JOIN con grados)
     */
    public function obtenerDetalles($id_director): array
    {
        $sql = "SELECT 
                    d.id_director,
                    d.nombre,
                    d.apellido,
                    d.correo,
                    d.telefono,
                    g.nombre AS nombre_grado,
                    d.fecha_inicio AS inicio,
                    d.fecha_final AS fin,
                    d.motivo_fin,
                    d.fecha_creacion, 
                    d.fecha_modificacion,
                    CASE 
                        WHEN d.estado = 1 THEN 'Activo'
                        WHEN d.estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM director d
                INNER JOIN grados_academicos g ON d.id_grado = g.id_grado
                WHERE d.id_director = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);

        $stmt->bind_param("i", $id_director);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) throw new Exception("Director no encontrado");

        return $registro;
    }

    /**
     * Obtiene todos los grados académicos activos para el select del formulario.
     *
     * @return array
     * @throws Exception
     */
    public function obtenerGradosActivos(): array
    {
        $sql = "SELECT id_grado, nombre FROM grados_academicos WHERE estado = 1 ORDER BY nombre ASC";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerGradosActivos): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerGradosActivos): " . $stmt->error);

        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result;
    }

    /**
     * Registra un nuevo director.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param int $id_grado
     * @param string $nombre
     * @param string $apellido
     * @param string|null $correo
     * @param string|null $telefono
     * @param string|null $fecha_inicio
     * @param string|null $fecha_final
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarDirector(int $id_grado, string $nombre, string $apellido, ?string $correo, ?string $telefono, ?string $fecha_inicio, ?string $fecha_final): int
    {
        $validacion = $this->verificarDirector($correo);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un director activo con ese correo.");
        }

        $sql = "INSERT INTO director 
            (id_grado, nombre, apellido, correo, telefono, estado, fecha_creacion, fecha_inicio, fecha_final) 
            VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?)";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (registrarDirector): " . $this->con->error);

        $stmt->bind_param("issssss", $id_grado, $nombre, $apellido, $correo, $telefono, $fecha_inicio, $fecha_final);
        if (!$stmt->execute()) throw new Exception("Error en execute (registrarDirector): " . $stmt->error);

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Edita un director existente.
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param int $id_grado
     * @param string $nombre
     * @param string $apellido
     * @param string|null $correo
     * @param string|null $telefono
     * @param int $id_director
     * @param string|null $fecha_inicio
     * @param string|null $fecha_final
     * @return int ID editado
     * @throws Exception
     */
    public function editarDirector(int $id_grado, string $nombre, string $apellido, ?string $correo, ?string $telefono, int $id_director, ?string $fecha_inicio, ?string $fecha_final, $motivo_fin): int
    {
        $sql = "UPDATE director 
                SET id_grado = ?, nombre = ?, apellido = ?, correo = ?, telefono = ?, fecha_modificacion = NOW(), fecha_inicio = ?, fecha_final = ?, motivo_fin = ?
                WHERE id_director = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (editarDirector): " . $this->con->error);

        $stmt->bind_param("issssisss", $id_grado, $nombre, $apellido, $correo, $telefono, $id_director, $fecha_inicio, $fecha_final, $motivo_fin);
        if (!$stmt->execute()) throw new Exception("Error en execute (editarDirector): " . $stmt->error);

        $stmt->close();
        return $id_director;
    }

    /**
     * Reactiva un director previamente desactivado.
     * IMPORTANTE: Ejecutar dentro de transacción.
     *
     * @param int $id_director
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_director): void
    {
        $registro = $this->obtenerPorId($id_director, true);
        if (!$registro) throw new Exception("Director no encontrado.");

        $sqlDatos = "SELECT correo FROM director WHERE id_director = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);
        if (!$stmtDatos) throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);

        $stmtDatos->bind_param("i", $id_director);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) throw new Exception("No se pudieron obtener datos del director.");

        // Solo verificar correo si existe (correo puede ser NULL)
        if (!empty($datos['correo'])) {
            $validacion = $this->verificarDirector($datos['correo']);
            if ($validacion['activo']) {
                throw new Exception("Conflicto: ya existe un director activo con el mismo correo.");
            }
        }

        $sql = "UPDATE director 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_director = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (reactivarDirector): " . $this->con->error);

        $stmt->bind_param("i", $id_director);
        if (!$stmt->execute()) throw new Exception("Error en execute (reactivarDirector): " . $stmt->error);

        if ($stmt->affected_rows === 0) {
            throw new Exception("El director ya estaba activo o no se pudo actualizar.");
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
        $sql = "SELECT id_director FROM director WHERE estado = 1 FOR UPDATE";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        if (!$stmt->execute()) throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un director.
     *
     * @param int $id_director
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_director(int $id_director): int
    {
        $sql = "UPDATE director 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_director = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (eliminar_director): " . $this->con->error);

        $stmt->bind_param("i", $id_director);
        if (!$stmt->execute()) throw new Exception("Error en execute (eliminar_director): " . $stmt->error);

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    /**
     * Verifica duplicidad de director por correo.
     * El correo es el campo único de la tabla director.
     *
     * @param string|null $correo
     * @return array
     * @throws Exception
     */
    public function verificarDirector(?string $correo): array
    {
        // Si el correo es nulo o vacío, no hay duplicidad posible
        if (empty($correo)) {
            return ["activo" => 0, "desactivado" => 0];
        }

        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM director
                    WHERE estado = 1 AND correo = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM director
                    WHERE estado = 0 AND correo = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (verificarDirector): " . $this->con->error);

        $stmt->bind_param("ss", $correo, $correo);
        if (!$stmt->execute()) throw new Exception("Error en execute (verificarDirector): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene un director por ID.
     *
     * @param int $id_director
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_director, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM director WHERE id_director = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);

        $stmt->bind_param("i", $id_director);
        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Verifica si existe otro director con el mismo correo, excluyendo el ID actual.
     *
     * @param int $id_director
     * @param string|null $correo
     * @return array
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_director, ?string $correo): array
    {
        if (empty($correo)) {
            return ["activo" => 0, "desactivado" => 0];
        }

        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM director
        WHERE estado = 1 AND correo = ? AND id_director != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM director
        WHERE estado = 0 AND correo = ? AND id_director != ?
    ) AS desactivado
                FROM director 
                WHERE id_director != ? AND correo = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) throw new Exception("Error en prepare (obtenerPorIdDiferente): " . $this->con->error);

        $stmt->bind_param(
            "sisiis",
            $correo,
            $id_director,
            $correo,
            $id_director,
            $id_director,
            $correo
        );

        if (!$stmt->execute()) throw new Exception("Error en execute (obtenerPorIdDiferente): " . $stmt->error);

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

/**
 * Desactiva directores cuyo periodo ya venció.
 *
 * @return int Número de registros afectados
 * @throws Exception
 */
public function desactivarDirectoresVencidos(): int
{
    $sql = "UPDATE director 
            SET estado = 0 
            WHERE estado = 1 
            AND fecha_final IS NOT NULL 
            AND CURDATE() > fecha_final";

    $stmt = $this->con->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en prepare: " . $this->con->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error en execute: " . $stmt->error);
    }

    $filasAfectadas = $stmt->affected_rows;

    $stmt->close();

    return $filasAfectadas;
}
}
