<?php
// Modelos/BaseModelo.php
abstract class BaseModelo
{
    protected mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Ejecuta un prepared statement y devuelve el resultado.
     * Lanza excepción si algo falla, para que el controlador la capture.
     */
    protected function ejecutar(string $sql, string $types = "", array $params = [], bool $fetchAll = true)
{
    $stmt = $this->conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en prepare(): " . $this->conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception("Error en execute(): " . $stmt->error);
    }
    if (stripos(trim($sql), "SELECT") === 0) {
        $result = $stmt->get_result();

        // get_result() falla si mysqlnd no está disponible
        if ($result === false) {
            throw new Exception("get_result() falló. ¿mysqlnd disponible?: " . $stmt->error);
        }

        if ($fetchAll) {
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        }

        return $result->fetch_assoc() ?? [];  // ← evita null en ejecutarConteo
    }
    return true;
}
}
