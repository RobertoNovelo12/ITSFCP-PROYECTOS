<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Periodo
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    public function obtenerPeriodoDatosFiltro($rol)
    {

        switch ($rol) {
            case 'supervisor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  COALESCE(SUM(CASE WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 1 ELSE 0 END), 0) AS Activo,
  COALESCE(SUM(CASE WHEN CURDATE() > fecha_final THEN 1 ELSE 0 END), 0) AS Terminado
FROM periodos 
WHERE estado = 1;";
                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // TABLA PRINCIPAL
    public function obtenerPeriodoTablaFiltro($buscar, $filtro)
    {
        $total = $this->obtenerCantidadPeriodo($buscar, $filtro);

        $por_pagina = 6;
        $pagina = empty($_GET['pagina']) ? 1 : intval($_GET['pagina']);
        $desde = ($pagina - 1) * $por_pagina;
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $params = [];
        $types = "";
        $where = [];

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

        // Siempre excluir eliminados (soft delete)
        $where[] = "estado = 1";

        // Filtro por estado lógico (fecha)
        switch ($filtro) {
            case 0: // Terminados
                $where[] = "CURDATE() > fecha_final";
                break;

            case 1: // Activo
                $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
                break;

            case 2: // Todos
                // No se agrega nada
                break;
        }

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "sss";
        }

        // Construcción correcta del WHERE
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // Orden + paginación
        $sql .= " ORDER BY id_periodos ASC LIMIT $desde, $por_pagina";

        // Preparar
        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error . "<br>SQL: $sql");
        }

        // 🔗 Bind dinámico
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        // Ejecutar
        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        // Resultado
        return [
            "periodo" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    public function obtenerCantidadPeriodo($buscar = null, $filtro = 2)
    {
        $sql = "SELECT COUNT(*) AS total FROM periodos";
        $params = [];
        $types = "";
        $where = [];

        // Siempre excluir eliminados (soft delete)
        $where[] = "estado = 1";

        // Filtros
        switch ($filtro) {
            case 0: // Terminado
                $where[] = "CURDATE() > fecha_final";
                break;
            case 1: // Activo
                $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
                break;
            case 2: // Total
                // No filtro
                break;
        }

        // Búsqueda
        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "sss";
        }

        // Construcción del WHERE
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->con->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();

        return $resultado['total'];
    }


    // EDICIÓN
    public function obtenerPeriodoEditar($id_periodos)
    {
        $sql = "SELECT id_periodos, periodo AS nombre, fecha_inicio AS inicio, fecha_final AS fin,
        CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() > fecha_final THEN 'Terminado'
    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_periodos);
        $stmt->execute();

        $periodo = $stmt->get_result()->fetch_assoc();

        if (!$periodo) {
            throw new Exception("Periodo no encontrada");
        }

        return $periodo;
    }

    //Obtener datos para detalles
    public function obtenerPeriodoDetalles($id_periodos)
    {
        $sql = "SELECT id_periodos, periodo, fecha_inicio, fecha_final, fecha_creacion, fecha_modificacion,
        CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() > fecha_final THEN 'Terminado'
    END AS estado
                FROM periodos
                WHERE id_periodos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_periodos);
        $stmt->execute();

        $periodo = $stmt->get_result()->fetch_assoc();

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
    public function desactivarActivos()
    {
        $sql = "UPDATE periodos 
            SET estado = 0, fecha_modificacion = NOW() 
            WHERE estado = 1";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
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
            "activo" => ($res['activo'] || $res['activo_nombre']) ? 1 : 0,
            "desactivado" => ($res['desactivado'] || $res['desactivado_nombre']) ? 1 : 0
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
