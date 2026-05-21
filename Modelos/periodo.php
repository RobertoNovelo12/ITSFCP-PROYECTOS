<?php
// Modelos/periodo.php

require_once __DIR__ . '/../publico/config/conexion.php';

class Periodo
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    //  FILTROS / CONTEOS
    // 

// 
// obtenerPeriodoDatosFiltro() —
// Se agrega comentario explicativo de por qué usa estado=1.
// Si los conteos no coincidían era por este mismo malentendido.
// 

    /**
     * Obtiene datos para filtros (totales, activos, terminados).
     * Solo cuenta periodos con estado = 1 (activos lógicamente).
     * Los desactivados administrativamente (estado=0) no se cuentan.
     * Se añade el conteo de Desactivados (estado = 0).
     */
    public function obtenerPeriodoDatosFiltro($rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        $sql = "SELECT
                -- Total de periodos activos lógicamente
                SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS Total,
 
                -- Activos: estado=1 y hoy está dentro del rango
                COALESCE(SUM(CASE
                    WHEN estado = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 1
                    ELSE 0
                END), 0) AS Activo,
 
                -- Terminados: estado=1 y el rango ya pasó
                COALESCE(SUM(CASE
                    WHEN estado = 1 AND CURDATE() > fecha_final THEN 1
                    ELSE 0
                END), 0) AS Terminado,
 
                -- Desactivados administrativamente: estado=0
                COALESCE(SUM(CASE
                    WHEN estado = 0 THEN 1
                    ELSE 0
                END), 0) AS Desactivado
 
            FROM periodos";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoDatosFiltro): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoDatosFiltro): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $resultado;
    }
    /**
     * Método base para construir WHERE dinámico.
     *
     * Filtros:
     *   2 = Total    → todos con estado = 1 (activos lógicos: tanto en curso como terminados)
     *   1 = Activo   → estado = 1 Y CURDATE() BETWEEN fecha_inicio AND fecha_final
     *   0 = Terminado→ estado = 1 Y CURDATE() > fecha_final
     *
     * Los registros con estado = 0 son "desactivados administrativamente"
     * y NUNCA aparecen en la tabla principal.

     * NUEVO filtro: $filtro === 3 → Desactivados administrativamente
     *   (estado = 0, independientemente de las fechas)
     * El resto de filtros siguen usando estado = 1.
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        if ($filtro === 3) {
            // Desactivados administrativamente: estado = 0
            $where = ["estado = 0"];
        } else {
            // Todos los demás filtros operan sobre estado = 1
            $where = ["estado = 1"];

            if ($filtro === 0) {
                $where[] = "CURDATE() > fecha_final";
            } elseif ($filtro === 1) {
                $where[] = "CURDATE() BETWEEN fecha_inicio AND fecha_final";
            }
            // filtro === 2 → Total (estado=1 sin restricción de fecha)
        }

        if (!empty($buscar)) {
            $where[] = "(fecha_inicio LIKE ? OR fecha_final LIKE ? OR periodo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types   .= "sss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    // 
    //  TABLA PRINCIPAL CON PAGINACIÓN
    // 

    /**
     * Obtiene tabla principal con paginación y las nuevas fechas.
     */
    // El CASE de estados ahora incluye la rama para estado = 0.
    // También se expone si el desactivado es "vigente" (puede
    // reactivarse) o "pasado" (no puede), para que la vista
    // pueda decidir qué botón mostrar.
    public function obtenerPeriodoTablaFiltro($buscar, $filtro): array
    {
        $pagina     = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde      = ($pagina - 1) * $por_pagina;

        $params = [];
        $types  = "";

        $total        = $this->obtenerCantidadPeriodo($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT
                id_periodos,
                periodo,
                fecha_inicio                AS inicio,
                fecha_final                 AS final,
                fecha_inicio_proyectos,
                fecha_fin_proyectos,
                fecha_inicio_solicitud,
                fecha_fin_solicitud,
                fecha_creacion              AS crear,
                fecha_modificacion,
                estado,
                CASE
                    WHEN estado = 0 THEN 'Desactivado'
                    WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                    WHEN CURDATE() > fecha_final THEN 'Terminado'
                    ELSE 'Desconocido'
                END AS estados,
                -- ¿Puede reactivarse? Solo si está desactivado y el semestre aún no terminó
                CASE
                    WHEN estado = 0 AND fecha_final >= CURDATE() THEN 1
                    ELSE 0
                END AS puede_reactivar
            FROM periodos";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_periodos DESC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPeriodoTablaFiltro): " . $this->con->error);
        }

        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPeriodoTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "periodo"    => $data,
            "paginacion" => [
                "total"         => $total,
                "por_pagina"    => $por_pagina,
                "pagina"        => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    /**
     * Total de registros con filtros.
     */
    public function obtenerCantidadPeriodo($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types  = "";

        $sql  = "SELECT COUNT(*) AS total FROM periodos";
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

    // 
    //  EDITAR / DETALLES
    // 

    /**
     * Obtiene datos para edición (incluye nuevas fechas).
     */
    public function obtenerPeriodoEditar($id_periodos): array
    {
        $sql = "SELECT 
                    id_periodos,
                    periodo                     AS nombre,
                    fecha_inicio                AS inicio,
                    fecha_final                 AS fin,
                    fecha_inicio_proyectos,
                    fecha_fin_proyectos,
                    fecha_inicio_solicitud,
                    fecha_fin_solicitud,
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
     * Obtiene datos para vista de detalles (incluye nuevas fechas).
     */
    public function obtenerPeriodoDetalles($id_periodos): array
    {
        $sql = "SELECT 
                    id_periodos,
                    periodo,
                    fecha_inicio,
                    fecha_final,
                    fecha_inicio_proyectos,
                    fecha_fin_proyectos,
                    fecha_inicio_solicitud,
                    fecha_fin_solicitud,
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

    // 
    //  CREAR / REACTIVAR
    // 

    /**
     * Registra un nuevo periodo con las fechas de proyectos e integración.
     *
     * IMPORTANTE: Ejecutar dentro de una transacción desde el controlador.
     *
     * @param string      $periodo
     * @param string      $fecha_inicio
     * @param string      $fecha_final
     * @param string|null $fecha_inicio_proyectos
     * @param string|null $fecha_fin_proyectos
     * @param string|null $fecha_inicio_solicitud
     * @param string|null $fecha_fin_solicitud
     * @return int ID insertado
     * @throws Exception
     */
    public function registrarPeriodo(
        string  $periodo,
        string  $fecha_inicio,
        string  $fecha_final,
        ?string $fecha_inicio_proyectos    = null,
        ?string $fecha_fin_proyectos       = null,
        ?string $fecha_inicio_solicitud  = null,
        ?string $fecha_fin_solicitud     = null
    ): int {

        $validacion = $this->verificarPeriodo($periodo, $fecha_inicio, $fecha_final);

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con ese nombre o fechas.");
        }

        $sql = "INSERT INTO periodos 
                    (periodo, fecha_inicio, fecha_final,
                     fecha_inicio_proyectos, fecha_fin_proyectos,
                     fecha_inicio_solicitud, fecha_fin_solicitud,
                     estado, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (registrarPeriodo): " . $this->con->error);
        }

        $stmt->bind_param(
            "sssssss",
            $periodo,
            $fecha_inicio,
            $fecha_final,
            $fecha_inicio_proyectos,
            $fecha_fin_proyectos,
            $fecha_inicio_solicitud,
            $fecha_fin_solicitud
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (registrarPeriodo): " . $stmt->error);
        }

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Actualiza únicamente las fechas de proyectos e integración de un periodo.
     *
     * IMPORTANTE: Ejecutar dentro de una transacción desde el controlador.
     *
     * @param int         $id_periodos
     * @param string|null $fecha_inicio_proyectos
     * @param string|null $fecha_fin_proyectos
     * @param string|null $fecha_inicio_solicitud
     * @param string|null $fecha_fin_solicitud
     * @return int Filas afectadas
     * @throws Exception
     */
    public function actualizarFechasSubperiodos(
        int     $id_periodos,
        ?string $fecha_inicio_proyectos,
        ?string $fecha_fin_proyectos,
        ?string $fecha_inicio_solicitud,
        ?string $fecha_fin_solicitud
    ): int {

        $sql = "UPDATE periodos
                SET fecha_inicio_proyectos   = ?,
                    fecha_fin_proyectos       = ?,
                    fecha_inicio_solicitud  = ?,
                    fecha_fin_solicitud     = ?,
                    fecha_modificacion        = NOW()
                WHERE id_periodos = ?
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (actualizarFechasSubperiodos): " . $this->con->error);
        }

        $stmt->bind_param(
            "ssssi",
            $fecha_inicio_proyectos,
            $fecha_fin_proyectos,
            $fecha_inicio_solicitud,
            $fecha_fin_solicitud,
            $id_periodos
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (actualizarFechasSubperiodos): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    // 
    //  REACTIVAR
    // 

    /**
     * Reactiva un periodo previamente desactivado.
     * Ejecutar dentro de transacción.
     */
    public function reactivarPeriodo(int $id): void
    {
        $periodo = $this->obtenerPorId($id, true);

        if (!$periodo) {
            throw new Exception("Periodo no encontrado.");
        }

        $sqlDatos = "SELECT periodo, fecha_inicio, fecha_final FROM periodos WHERE id_periodos = ?";
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

        $validacion = $this->verificarPeriodo(
            $datos['periodo'],
            $datos['fecha_inicio'],
            $datos['fecha_final']
        );

        if ($validacion['activo']) {
            throw new Exception("Conflicto: ya existe un periodo activo con mismo nombre o fechas.");
        }

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

    // 
    //  AUXILIARES
    // 

    public function obtenerPorNombre(string $nombre): ?array
    {
        $sql = "SELECT id_periodos FROM periodos WHERE periodo = ? LIMIT 1";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorNombre): " . $this->con->error);
        }

        $stmt->bind_param("s", $nombre);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorNombre): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $resultado ?: null;
    }

    public function bloquear_tabla(): void
    {
        $sql = "SELECT id_periodos FROM periodos WHERE estado = 1 FOR UPDATE";

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
        $stmt->close();

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


    /**
     * Verifica duplicidad de periodos por solapamiento de fechas y nombre.
     *
     * Retorna:
     *   activo            → existe un periodo con estado=1 que solapa o tiene el mismo nombre
     *   desactivado       → existe un periodo con estado=0 que solapa o tiene el mismo nombre
     *                       Y cuyo semestre NO ha terminado aún (fecha_final >= CURDATE())
     *   desactivado_pasado→ existe un periodo con estado=0 que solapa o tiene el mismo nombre
     *                       PERO ya terminó (fecha_final < CURDATE()) — no se puede reactivar
     */
    public function verificarPeriodo(string $nombre, string $fecha_inicio, string $fecha_fin): array
    {
        $sql = "SELECT
                -- ¿Existe periodo activo que solapa fechas?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                ) AS activo_fecha,
 
                -- ¿Existe periodo activo con el mismo nombre?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 1 AND periodo = ?
                ) AS activo_nombre,
 
                -- ¿Existe periodo desactivado que solapa fechas y aún NO terminó?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                      AND fecha_final >= CURDATE()
                ) AS desactivado_vigente_fecha,
 
                -- ¿Existe periodo desactivado con el mismo nombre y aún NO terminó?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0 AND periodo = ?
                      AND fecha_final >= CURDATE()
                ) AS desactivado_vigente_nombre,
 
                -- ¿Existe periodo desactivado que solapa fechas PERO ya terminó?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0
                      AND (? <= fecha_final AND ? >= fecha_inicio)
                      AND fecha_final < CURDATE()
                ) AS desactivado_pasado_fecha,
 
                -- ¿Existe periodo desactivado con el mismo nombre PERO ya terminó?
                EXISTS(
                    SELECT 1 FROM periodos
                    WHERE estado = 0 AND periodo = ?
                      AND fecha_final < CURDATE()
                ) AS desactivado_pasado_nombre";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarPeriodo): " . $this->con->error);
        }

        $stmt->bind_param(
            "sssssssss",
            $fecha_inicio,   // activo_fecha   (<=fecha_final)
            $fecha_fin,      // activo_fecha   (>=fecha_inicio)
            $nombre,         // activo_nombre
            $fecha_inicio,   // desactivado_vigente_fecha
            $fecha_fin,      // desactivado_vigente_fecha
            $nombre,         // desactivado_vigente_nombre
            $fecha_inicio,   // desactivado_pasado_fecha
            $fecha_fin,      // desactivado_pasado_fecha
            $nombre          // desactivado_pasado_nombre
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarPeriodo): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            // Hay un periodo activo (por fecha o nombre) → bloquear creación
            "activo"             => (int)(($res['activo_fecha'] ?? 0) || ($res['activo_nombre'] ?? 0)),
            // Hay un periodo desactivado vigente (semestre actual) → ofrecer reactivar
            "desactivado"        => (int)(($res['desactivado_vigente_fecha'] ?? 0) || ($res['desactivado_vigente_nombre'] ?? 0)),
            // Hay un periodo desactivado pero ya pasó → NO ofrecer reactivar, permitir crear
            "desactivado_pasado" => (int)(($res['desactivado_pasado_fecha'] ?? 0) || ($res['desactivado_pasado_nombre'] ?? 0)),
        ];
    }

    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado FROM periodos WHERE id_periodos = ?";

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
        $stmt->close();

        return $res ?: null;
    }
}
