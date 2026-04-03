<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class ajustesdocumentos
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
                    COUNT(*) AS Todos,
                    CASE WHEN categoria = 'proceso' THEN 'Proceso' END AS Proceso,
                    CASE WHEN categoria = 'final' THEN 'Final' END AS Final
                FROM tipo_documento  GROUP BY categoria";

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
     * Obtiene tabla principal 
     */
    public function obtenerTablaFiltro(array $filtros): array
    {
        $placeholders = implode(',', array_fill(0, count($filtros), '?'));

        $sql = "SELECT 
                    id_tipo_documento,
                    nombre,
                    descripcion,
                    categoria,
                    orden,
                    fecha_modificacion AS modificar,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM tipo_documento WHERE categoria IN ($placeholders)";

        $sql .= " ORDER BY categoria";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);
        }

        $types = str_repeat('s', count($filtros));
        $stmt->bind_param($types, ...$filtros);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close(); // liberar recurso

        return $data;
    }


    /**
     * Obtiene datos para edición
     */
    public function obtenerEditar($id_tipo_documento): array
    {
        $sql = "SELECT 
                    id_tipo_documento,
                    nombre,
                    descripcion,
                    categoria,
                    orden,
                    fecha_modificacion AS modificar,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM tipo_documento WHERE id_tipo_documento = ?";

        $sql .= " ORDER BY id_tipo_documento";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_tipo_documento);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return $data;
    }

        //Editar Tipo de documento
    /**
     * Editar una nueva Tipo de documento.
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
    public function editar(string $descripcion, int $orden, int $id_tipo_documento): int
    {

        $sql = "UPDATE tipo_documento SET descripcion = ?, orden = ?, fecha_modificacion = NOW() WHERE id_tipo_documento = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (editar): " . $this->con->error);
        }

        $stmt->bind_param("sii", $descripcion, $orden, $id_tipo_documento);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (editar): " . $stmt->error);
        }

        $stmt->close(); // liberar recurso

        return $id_tipo_documento;
    }


    /**
     * Reactiva una Tipo de documento previamente desactivado.
     * 
     * REGLAS:
     * - No debe existir otra Tipo de documento activa solapado
     * - No debe duplicar nombre activo
     * 
     * IMPORTANTE:
     * Ejecutar dentro de transacción.
     *
     * @param int $id
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_tipo_documento): void
    {
        /**
         * 1. Obtener el periodo con bloqueo (evita concurrencia)
         */
        $periodo = $this->obtenerPorId($id_tipo_documento, true);

        if (!$periodo) {
            throw new Exception("Periodo no encontrado.");
        }


        /**
         * 4. Reactivar
         * - Solo si está desactivado
         */
        $sql = "UPDATE tipo_documento 
            SET estado = 1, 
                fecha_modificacion = NOW() 
            WHERE id_tipo_documento = ? 
              AND estado = 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (reactivar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_tipo_documento);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (reactivar): " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("El Tipo de documento ya estaba activa o no se pudo actualizar.");
        }

        $stmt->close();
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
        $sql = "SELECT id_tipo_documento 
                FROM tipo_documento
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
     * @param int $id_tipo_documento
     * @return int Número de filas afectadas
     * @throws Exception
     */
    public function desactivar(int $id_tipo_documentos): int
    {

        $sql = "UPDATE tipo_documento 
                SET estado = 0, 
                    fecha_modificacion = NOW() 
                WHERE id_tipo_documento = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (desactivar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_tipo_documentos);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (desactivar): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;

        $stmt->close(); // liberar recurso SIEMPRE

        return $filas;
    }

    /**
     * Obtiene una Tipo de documento por ID.
     * OPCIONAL: Permite bloqueo de fila para concurrencia.
     *
     * @param int $id
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_tipo_documento, bool $forUpdate = false): ?array
    {

        $sql = "SELECT estado 
                FROM tipo_documento 
                WHERE id_tipo_documento = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_tipo_documento);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();

        $stmt->close(); // liberar recurso

        return $res ?: null;
    }
}
