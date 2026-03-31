<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Linea
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }
    /**
     * Obtiene datos para filtros (totales, activos, terminados)
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
                FROM lineas_investigacion";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close(); // liberar recurso

        return $resultado;
    }

    /**
     * Método base para construir WHERE dinámico (REUTILIZABLE)
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        //$where = ["estado = 1"];

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

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(nombre LIKE ? OR descripcion LIKE ? OR fecha_creacion LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "sss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    /**
     * Obtiene tabla principal con paginación
     */
    public function obtenerTablaFiltro($buscar, $filtro): array
    {
        /**
         * Evitamos dependencia directa de $_GET
         */
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde = ($pagina - 1) * $por_pagina;

        $params = [];
        $types = "";

        // Total optimizado
        $total = $this->obtenerCantidadLinea($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_linea,
                    nombre,
                    descripcion,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM lineas_investigacion";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        /**
         * - Evitar inyección (LIMIT seguro con enteros)
         */
        $sql .= " ORDER BY id_linea ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);
        }

        // Agregar paginación como enteros
        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close(); // liberar recurso

        return [
            "linea" => $data,
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
    public function obtenerCantidadLinea($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM lineas_investigacion";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerCantidadLinea): " . $this->con->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerCantidadLinea): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_linea): array
    {
        $sql = "SELECT 
                    id_linea, 
                    nombre,
                    descripcion,                    
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM lineas_investigacion
                WHERE id_linea = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_linea);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);
        }

        $linea = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$linea) {
            throw new Exception("Línea de investigación no encontrada");
        }

        return $linea;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerDetalles($id_periodos): array
    {
        $sql = "SELECT 
                    id_linea, 
                    nombre,
                    descripcion, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM lineas_investigacion
                WHERE id_linea = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_periodos);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);
        }

        $periodo = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$periodo) {
            throw new Exception("Línea de investigación no encontrada");
        }

        return $periodo;
    }

    //Crea Línea de investigación
    /**
     * Registra una nueva línea de investigación.
     * 
     * REGLAS:
     * - Se crea siempre como activo
     * - No debe solaparse con otro activo
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción desde el controlador.
     *
     * @param string $nombre
     * @param string $descripcion
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarLinea(string $nombre, string $descripcion): int
    {

        $validacion = $this->verificarLinea($nombre);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe una línea de investigación activa con ese nombre.");
        }


        $sql = "INSERT INTO lineas_investigacion 
            (nombre, descripcion, estado, fecha_creacion) 
            VALUES (?, ?, 1, NOW())";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (registrarLinea): " . $this->con->error);
        }

        $stmt->bind_param("ss", $nombre, $descripcion);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (registrarLinea): " . $stmt->error);
        }

        $id = $stmt->insert_id;

        $stmt->close(); // liberar recurso

        return $id;
    }

        //Editar Línea de investigación
    /**
     * Editar una nueva línea de investigación.
     * 
     * REGLAS:
     * - Se edita siempre como activo
     * - No debe solaparse con otro activo
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción desde el controlador.
     *
     * @param string $nombre
     * @param string $descripcion
     * @return int ID insertado
     * @throws Exception
     */
    public function editarLinea(string $nombre, string $descripcion, int $id_linea): int
    {

        $sql = "UPDATE lineas_investigacion SET nombre = ?, descripcion = ?, fecha_modificacion = NOW() WHERE id_linea = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (editarLinea): " . $this->con->error);
        }

        $stmt->bind_param("ssi", $nombre, $descripcion, $id_linea);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (editarLinea): " . $stmt->error);
        }

        $stmt->close(); // liberar recurso

        return $id_linea;
    }


    /**
     * Reactiva una línea de investigación previamente desactivado.
     * 
     * REGLAS:
     * - No debe existir otra línea de investigación activa solapado
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Ejecutar dentro de transacción.
     *
     * @param int $id
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_linea): void
    {
        /**
         * 1. Obtener el periodo con bloqueo (evita concurrencia)
         */
        $periodo = $this->obtenerPorId($id_linea, true);

        if (!$periodo) {
            throw new Exception("Periodo no encontrado.");
        }

        /**
         * 2. Obtener datos completos del periodo (necesarios para validar)
         */
        $sqlDatos = "SELECT nombre, descripcion 
                 FROM lineas_investigacion
                 WHERE id_linea = ?";

        $stmtDatos = $this->con->prepare($sqlDatos);

        if (!$stmtDatos) {
            throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);
        }

        $stmtDatos->bind_param("i", $id_linea);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) {
            throw new Exception("No se pudieron obtener datos de la línea de investigación.");
        }

        /**
         * 3. Validar conflictos antes de reactivar
         */
        $validacion = $this->verificarLinea(
            $datos['nombre']
        );

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe una línea de investigación activa con mismo nombre");
        }

        /**
         * 4. Reactivar
         * - Solo si está desactivado
         */
        $sql = "UPDATE lineas_investigacion 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_linea = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (reactivarLinea): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_linea);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (reactivarLinea): " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("La línea de investigación ya estaba activa o no se pudo actualizar.");
        }

        $stmt->close();
    }
    /**
     * Obtiene una línea de investigación por nombre (búsqueda exacta).
     * Previene duplicados lógicos.
     *
     * @param string $nombre
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorNombre(string $nombre): ?array
    {
        $sql = "SELECT id_linea 
                FROM lineas_investigacion
                WHERE nombre = ? 
                LIMIT 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorNombre): " . $this->con->error);
        }

        $stmt->bind_param("s", $nombre);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorNombre): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return $resultado ?: null;
    }

    /**
     * Bloquea únicamente los registros activos.
     * IMPORTANTE: Debe ejecutarse dentro de una transacción.
     *
     * REQUIERE:
     * - Motor InnoDB
     * - Transacción activa
     * @return void
     * @throws Exception
     */

    public function bloquear_tabla(): void
    {
        $sql = "SELECT id_linea 
                FROM lineas_investigacion
                WHERE estado = 1 
                FOR UPDATE";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        }

        // No necesitamos el resultado → solo provocar el bloqueo
        $stmt->free_result();
        $stmt->close();
    }

    /**
     * Eliminación lógica (soft delete) de un periodo.
     *
     * @param int $id_linea
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_linea(int $id_linea): int
    {

        $sql = "UPDATE lineas_investigacion 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_linea = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (eliminar_linea): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_linea);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (eliminar_linea): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;

        $stmt->close(); // liberar recurso SIEMPRE

        return $filas;
    }

    //Busca duplicidad de líneas de investigación
    /**
     * Verifica duplicidad de líneas de investigación por:
     * - Nombre duplicado
     *
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción si se usa para inserción/actualización crítica.
     *
     * @param string $nombre
     * @return array
     * @throws Exception
     */
    public function verificarLinea(string $nombre): array
    {

        $sql = "SELECT

                EXISTS(
                    SELECT 1 FROM lineas_investigacion
                    WHERE estado = 1 AND nombre = ?
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM lineas_investigacion
                    WHERE estado = 0 AND nombre = ?
                ) AS desactivado
        ";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarLinea): " . $this->con->error);
        }

        $stmt->bind_param(
            "ss",
            $nombre,
            $nombre
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarLinea): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene una línea de investigación por ID.
     * OPCIONAL: Permite bloqueo de fila para concurrencia.
     *
     * @param int $id
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_linea, bool $forUpdate = false): ?array
    {

        $sql = "SELECT estado 
                FROM lineas_investigacion 
                WHERE id_linea = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_linea);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return $res ?: null;
    }

    /**
     * Verificar el nombre una línea de investigación por ID diferente.
     * OPCIONAL: Permite bloqueo de fila para concurrencia.
     *
     * @param int $id
     * @param string $nombre
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorIdDiferente(int $id_linea, $nombre): ?array
    {

        $sql = "SELECT
    EXISTS(
        SELECT 1 FROM lineas_investigacion
        WHERE estado = 1 AND nombre = ? AND id_linea != ?
    ) AS activo,

    EXISTS(
        SELECT 1 FROM lineas_investigacion
        WHERE estado = 0 AND nombre = ? AND id_linea != ?
    ) AS desactivado
                FROM lineas_investigacion 
                WHERE id_linea != ? AND nombre = ?";


        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarLinea): " . $this->con->error);
        }

        $stmt->bind_param(
            "sisiis",
            $nombre,
            $id_linea,
            $nombre,
            $id_linea,
            $id_linea,
            $nombre
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarLinea): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return [
            "activo" => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }
}
