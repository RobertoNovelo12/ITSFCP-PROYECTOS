<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Carrera
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
                FROM carreras";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return $resultado;
    }

    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     * Nota: 'carreras' usa 'nombre_carrera' como campo principal (en lugar de 'nombre')
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        // Filtro lógico
        if ($filtro == 0) {
            $where[] = "estado = 0";
        }
        if ($filtro == 1) {
            $where[] = "estado = 1";
        } elseif ($filtro == 2) {
            $where[] = "estado IN (0,1)";
        }

        // Búsqueda por nombre_carrera y fecha_creacion
        if (!empty($buscar)) {
            $where[] = "(nombre_carrera LIKE ? OR fecha_creacion LIKE ?)";
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

        $total = $this->obtenerCantidadCarrera($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_carrera,
                    nombre_carrera,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM carreras";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $sql .= " ORDER BY id_carrera ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);
        }

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return [
            "carrera" => $data,
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
    public function obtenerCantidadCarrera($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM carreras";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerCantidadCarrera): " . $this->con->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerCantidadCarrera): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_carrera): array
    {
        $sql = "SELECT 
                    id_carrera, 
                    nombre_carrera,                    
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM carreras
                WHERE id_carrera = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);
        }

        $carrera = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$carrera) {
            throw new Exception("Carrera no encontrada");
        }

        return $carrera;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerDetalles($id_carrera): array
    {
        $sql = "SELECT 
                    id_carrera, 
                    nombre_carrera, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM carreras
                WHERE id_carrera = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);
        }

        $carrera = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$carrera) {
            throw new Exception("Carrera no encontrada");
        }

        return $carrera;
    }

    /**
     * Registra una nueva carrera.
     * 
     * REGLAS:
     * - Se crea siempre como activo
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción desde el controlador.
     *
     * @param string $nombre_carrera
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarCarrera(string $nombre_carrera): int
    {
        $validacion = $this->verificarCarrera($nombre_carrera);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe una carrera activa con ese nombre.");
        }

        $sql = "INSERT INTO carreras 
            (nombre_carrera, estado, fecha_creacion) 
            VALUES (?, 1, NOW())";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (registrarCarrera): " . $this->con->error);
        }

        $stmt->bind_param("s", $nombre_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (registrarCarrera): " . $stmt->error);
        }

        $id = $stmt->insert_id;

        $stmt->close();

        return $id;
    }

    /**
     * Edita una carrera existente.
     * 
     * REGLAS:
     * - No debe duplicar nombre activo con otro id
     * 
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción desde el controlador.
     *
     * @param string $nombre_carrera
     * @param int $id_carrera
     * @return int ID editado
     * @throws Exception
     */
    public function editarCarrera(string $nombre_carrera, int $id_carrera): int
    {
        $sql = "UPDATE carreras SET nombre_carrera = ?, fecha_modificacion = NOW() WHERE id_carrera = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (editarCarrera): " . $this->con->error);
        }

        $stmt->bind_param("si", $nombre_carrera, $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (editarCarrera): " . $stmt->error);
        }

        $stmt->close();

        return $id_carrera;
    }

    /**
     * Reactiva una carrera previamente desactivada.
     * 
     * IMPORTANTE:
     * Ejecutar dentro de transacción.
     *
     * @param int $id_carrera
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_carrera): void
    {
        $carrera = $this->obtenerPorId($id_carrera, true);

        if (!$carrera) {
            throw new Exception("Carrera no encontrada.");
        }

        $sqlDatos = "SELECT nombre_carrera 
                 FROM carreras
                 WHERE id_carrera = ?";

        $stmtDatos = $this->con->prepare($sqlDatos);

        if (!$stmtDatos) {
            throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);
        }

        $stmtDatos->bind_param("i", $id_carrera);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) {
            throw new Exception("No se pudieron obtener datos de la carrera.");
        }

        $validacion = $this->verificarCarrera($datos['nombre_carrera']);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe una carrera activa con el mismo nombre.");
        }

        $sql = "UPDATE carreras 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_carrera = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (reactivarCarrera): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (reactivarCarrera): " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("La carrera ya estaba activa o no se pudo actualizar.");
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
        $sql = "SELECT id_carrera 
                FROM carreras
                WHERE estado = 1 
                FOR UPDATE";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        }

        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de una carrera.
     *
     * @param int $id_carrera
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_carrera(int $id_carrera): int
    {
        $sql = "UPDATE carreras 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_carrera = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (eliminar_carrera): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (eliminar_carrera): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;

        $stmt->close();

        return $filas;
    }

    /**
     * Verifica duplicidad de carreras por nombre.
     *
     * @param string $nombre_carrera
     * @return array
     * @throws Exception
     */
    public function verificarCarrera(string $nombre_carrera): array
    {
        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM carreras
                    WHERE estado = 1 AND nombre_carrera = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM carreras
                    WHERE estado = 0 AND nombre_carrera = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarCarrera): " . $this->con->error);
        }

        $stmt->bind_param("ss", $nombre_carrera, $nombre_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarCarrera): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene una carrera por ID.
     *
     * @param int $id_carrera
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_carrera, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado 
                FROM carreras 
                WHERE id_carrera = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_carrera);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return $res ?: null;
    }

    /**
     * Verifica si existe otra carrera con el mismo nombre, excluyendo el ID actual.
     *
     * @param int $id_carrera
     * @param string $nombre_carrera
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_carrera, $nombre_carrera): ?array
    {
        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM carreras
        WHERE estado = 1 AND nombre_carrera = ? AND id_carrera != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM carreras
        WHERE estado = 0 AND nombre_carrera = ? AND id_carrera != ?
    ) AS desactivado
                FROM carreras 
                WHERE id_carrera != ? AND nombre_carrera = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorIdDiferente): " . $this->con->error);
        }

        $stmt->bind_param(
            "sisiis",
            $nombre_carrera,
            $id_carrera,
            $nombre_carrera,
            $id_carrera,
            $id_carrera,
            $nombre_carrera
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorIdDiferente): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }
}
