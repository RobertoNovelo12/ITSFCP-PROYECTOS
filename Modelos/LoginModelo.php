<?php
class LoginModelo
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function buscarPorCorreo(string $correo): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT u.id_usuarios, u.nombre, u.password, r.nombre AS rol
            FROM usuarios u
            INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuarios  -- ← era ur.id_usuario
            INNER JOIN roles r ON ur.id_rol = r.id_roles
            WHERE u.correo_institucional = ?
        ");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
}