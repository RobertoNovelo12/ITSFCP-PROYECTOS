<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Periodo
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }
    /**
     * Obtiene datos para filtros (totales, activos, terminados)
     */
    public function obtenerPeriodoDatosFiltro($rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN CURDATE() > fecha_final THEN 1 ELSE 0 END), 0) AS Terminado
                FROM periodos 
                WHERE estado = 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoDatosFiltro): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoDatosFiltro): " . $stmt->error);
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
        $where = ["estado = 1"];

        // Filtro lógico
        if ($filtro === 0) {
            $where[] = "CURDATE() > fecha_final";
        } elseif ($filtro === 1) {
            $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
        }

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
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
    public function obtenerPeriodoTablaFiltro($buscar, $filtro): array
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
        $total = $this->obtenerCantidadPeriodo($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_periodos,
                    periodo,
                    fecha_inicio AS inicio,
                    fecha_final AS final,
                    fecha_creacion AS crear,
                    CASE 
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'        
                        WHEN CURDATE() > fecha_final THEN 'Terminado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM periodos";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        /**
         * - Evitar inyección (LIMIT seguro con enteros)
         */
        $sql .= " ORDER BY id_periodos ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoTablaFiltro): " . $this->con->error);
        }

        // Agregar paginación como enteros
        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close(); // liberar recurso

        return [
            "periodo" => $data,
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
    public function obtenerCantidadPeriodo($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types = "";

        $sql = "SELECT COUNT(*) AS total FROM periodos";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerCantidadPeriodo): " . $this->con->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerCantidadPeriodo): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    /**
     * Obtiene datos para edición
     */
    public function obtenerPeriodoEditar($id_periodos): array
    {
        $sql = "SELECT 
                    id_periodos, 
                    periodo AS nombre, 
                    fecha_inicio AS inicio, 
                    fecha_final AS fin,
                    CASE 
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() > fecha_final THEN 'Terminado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoEditar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_periodos);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoEditar): " . $stmt->error);
        }

        $periodo = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$periodo) {
            throw new Exception("Periodo no encontrado");
        }

        return $periodo;
    }

    /**
     * Obtiene datos para vista de detalles
     */
    public function obtenerPeriodoDetalles($id_periodos): array
    {
        $sql = "SELECT 
                    id_periodos, 
                    periodo, 
                    fecha_inicio, 
                    fecha_final, 
                    fecha_creacion, 
                    fecha_modificacion,
                    CASE 
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() > fecha_final THEN 'Terminado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoDetalles): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_periodos);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoDetalles): " . $stmt->error);
        }

        $periodo = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$periodo) {
            throw new Exception("Periodo no encontrado");
        }

        return $periodo;
    }

    //Crea Periodo
    /**
     * Registra un nuevo periodo.
     * 
     * REGLAS:
     * - Se crea siempre como activo
     * - No debe solaparse con otro activo
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción desde el controlador.
     *
     * @param string $periodo
     * @param string $fecha_inicio
     * @param string $fecha_final
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarPeriodo(string $periodo, string $fecha_inicio, string $fecha_final): int
    {

        $validacion = $this->verificarPeriodo($periodo, $fecha_inicio, $fecha_final);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con ese nombre o fechas.");
        }


        $sql = "INSERT INTO periodos 
            (periodo, fecha_inicio, fecha_final, estado, fecha_creacion) 
            VALUES (?, ?, ?, 1, NOW())";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (registrarPeriodo): " . $this->con->error);
        }

        $stmt->bind_param("sss", $periodo, $fecha_inicio, $fecha_final);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (registrarPeriodo): " . $stmt->error);
        }

        $id = $stmt->insert_id;

        $stmt->close(); // liberar recurso

        return $id;
    }


    /**
     * Reactiva un periodo previamente desactivado.
     * 
     * REGLAS:
     * - No debe existir otro periodo activo solapado
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Ejecutar dentro de transacción.
     *
     * @param int $id
     * @return void
     * @throws Exception
     */
    public function reactivarPeriodo(int $id): void
    {
        /**
         * 1. Obtener el periodo con bloqueo (evita concurrencia)
         */
        $periodo = $this->obtenerPorId($id, true);

        if (!$periodo) {
            throw new Exception("Periodo no encontrado.");
        }

        /**
         * 2. Obtener datos completos del periodo (necesarios para validar)
         */
        $sqlDatos = "SELECT periodo, fecha_inicio, fecha_final 
                 FROM periodos 
                 WHERE id_periodos = ?";

        $stmtDatos = $this->con->prepare($sqlDatos);

        if (!$stmtDatos) {
            throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);
        }

        $stmtDatos->bind_param("i", $id);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) {
            throw new Exception("No se pudieron obtener datos del periodo.");
        }

        /**
         * 3. Validar conflictos antes de reactivar
         */
        $validacion = $this->verificarPeriodo(
            $datos['periodo'],
            $datos['fecha_inicio'],
            $datos['fecha_final']
        );

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con mismo nombre o fechas.");
        }

        /**
         * 4. Reactivar
         * - Solo si está desactivado
         */
        $sql = "UPDATE periodos 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_periodos = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (reactivarPeriodo): " . $this->con->error);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (reactivarPeriodo): " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("El periodo ya estaba activo o no se pudo actualizar.");
        }

        $stmt->close();
    }
    /**
     * Obtiene un periodo por nombre (búsqueda exacta).
     * Previene duplicados lógicos.
     *
     * @param string $nombre
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorNombre(string $nombre): ?array
    {
        $sql = "SELECT id_periodos 
                FROM periodos 
                WHERE periodo = ? 
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
        $sql = "SELECT id_periodos 
                FROM periodos 
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
     * @param int $id_periodo
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function eliminar_periodo(int $id_periodo): int
    {

        $sql = "UPDATE periodos 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_periodos = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (eliminar_periodo): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_periodo);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (eliminar_periodo): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;

        $stmt->close(); // liberar recurso SIEMPRE

        return $filas;
    }
    public function desactivarActivos(): void
    {
        $sql = "UPDATE periodos 
            SET estado = 0, fecha_modificacion = NOW() 
            WHERE estado = 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (desactivarActivos): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (desactivarActivos): " . $stmt->error);
        }

        $stmt->close();
    }
    //Busca duplicidad de periodos
    /**
     * Verifica duplicidad de periodos por:
     * - Solapamiento de fechas
     * - Nombre duplicado
     *
     * IMPORTANTE:
     * Este método DEBE ejecutarse dentro de una transacción si se usa para inserción/actualización crítica.
     *
     * @param string $nombre
     * @param string $fecha_inicio (Y-m-d)
     * @param string $fecha_fin (Y-m-d)
     * @return array
     * @throws Exception
     */
    public function verificarPeriodo(string $nombre, string $fecha_inicio, string $fecha_fin): array
    {

        $sql = "SELECT
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                ) AS activo,

                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                ) AS desactivado,

                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1 AND periodo = ?
                ) AS activo_nombre,

                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0 AND periodo = ?
                ) AS desactivado_nombre
        ";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarPeriodo): " . $this->con->error);
        }

        $stmt->bind_param(
            "ssssss",
            $fecha_inicio,
            $fecha_fin,
            $fecha_inicio,
            $fecha_fin,
            $nombre,
            $nombre
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarPeriodo): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return [
            "activo" => (int)($res['activo'] || $res['activo_nombre']),
            "desactivado" => (int)($res['desactivado'] || $res['desactivado_nombre'])
        ];
    }

    /**
     * Obtiene un periodo por ID.
     * OPCIONAL: Permite bloqueo de fila para concurrencia.
     *
     * @param int $id
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {

        $sql = "SELECT estado 
                FROM periodos 
                WHERE id_periodos = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return $res ?: null;
    }
}
