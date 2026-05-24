<?php
class AjustesModelo
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getUsuario(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM usuarios WHERE id_usuarios = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }

    public function getConfig(int $id): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM configuraciones WHERE id_usuarios = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    public function actualizarPerfil(int $id, array $campos): bool
    {
        $sets = [];
        $tipos = '';
        $valores = [];

        if (isset($campos['nombre'])) {
            $sets[] = "nombre = ?";
            $tipos .= 's';
            $valores[] = $campos['nombre'];
        }
        if (isset($campos['password'])) {
            $sets[] = "password = ?";
            $tipos .= 's';
            $valores[] = password_hash($campos['password'], PASSWORD_DEFAULT);
        }
        if (isset($campos['fecha_nacimiento'])) {
            $sets[] = "fecha_nacimiento = ?";
            $tipos .= 's';
            $valores[] = $campos['fecha_nacimiento'];
        }

        if (empty($sets)) return false;

        $valores[] = $id;
        $tipos .= 'i';

        $sql = "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id_usuarios = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);
        return $stmt->execute();
    }

    public function guardarConfig(int $id, array $datos): bool
    {
        // Verificar si ya existe config
        $stmt = $this->conn->prepare("SELECT id_usuarios FROM configuraciones WHERE id_usuarios = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;

        if ($existe) {
            $sql = "UPDATE configuraciones SET
                        localidad = ?,
                        institucion_academica = ?,
                        notif_todas = ?,
                        notif_tareas_nuevas = ?,
                        notif_tareas_atrasadas = ?,
                        notif_modificaciones_proyecto = ?,
                        notif_admin_proyecto = ?,
                        priv_ver_tareas = ?,
                        priv_ver_proyectos = ?,
                        priv_ver_datos = ?
                    WHERE id_usuarios = ?";
        } else {
            $sql = "INSERT INTO configuraciones (
                        localidad, institucion_academica,
                        notif_todas, notif_tareas_nuevas, notif_tareas_atrasadas,
                        notif_modificaciones_proyecto, notif_admin_proyecto,
                        priv_ver_tareas, priv_ver_proyectos, priv_ver_datos,
                        id_usuarios
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "ssiiiiiiiii",
            $datos['localidad'],
            $datos['institucion_academica'],
            $datos['notif_todas'],
            $datos['notif_tareas_nuevas'],
            $datos['notif_tareas_atrasadas'],
            $datos['notif_modificaciones_proyecto'],
            $datos['notif_admin_proyecto'],
            $datos['priv_ver_tareas'],
            $datos['priv_ver_proyectos'],
            $datos['priv_ver_datos'],
            $id
        );
        return $stmt->execute();
    }
}