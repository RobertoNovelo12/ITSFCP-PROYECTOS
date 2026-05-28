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
            return $fetchAll ? $result->fetch_all(MYSQLI_ASSOC) : $result->fetch_assoc();
        }
        return true;
    }
}
