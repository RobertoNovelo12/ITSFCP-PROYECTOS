<?php
class DashboardModelo
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getProyectosPorRol(string $rol, int $id_usuario): array
    {
        if ($rol === 'supervisor') {
            $sql = "SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
                    FROM proyectos p
                    INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
                    ORDER BY p.creado_en DESC";
        } elseif ($rol === 'investigador' || $rol === 'profesor') {
            $sql = "SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
                    FROM proyectos p
                    INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
                    WHERE p.id_investigador = $id_usuario
                    ORDER BY p.creado_en DESC";
        } else {
            $sql = "SELECT p.id_proyectos, p.titulo, ep.nombre AS estado, p.descripcion
                    FROM proyectos p
                    INNER JOIN proyectos_usuarios pu ON p.id_proyectos = pu.id_proyectos
                    INNER JOIN estados_proyectos ep ON p.id_estadoP = ep.id_estadoP
                    WHERE pu.id_usuarios = $id_usuario
                    ORDER BY p.creado_en DESC";
        }

        $result = $this->conn->query($sql);
        $proyectos = [];
        while ($row = $result->fetch_assoc()) {
            $proyectos[] = $row;
        }
        return $proyectos;
    }

    public function getProgresoProyecto(int $id_proyecto): int
    {
        $total_q = $this->conn->query("
            SELECT COUNT(*) AS total
            FROM tareas_usuarios tu
            INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
            INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
            WHERE s.id_proyectos = $id_proyecto
        ");
        $total = $total_q->fetch_assoc()['total'] ?? 0;
        if ($total == 0) return 0;

        $done_q = $this->conn->query("
            SELECT COUNT(*) AS done
            FROM tareas_usuarios tu
            INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
            INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
            WHERE s.id_proyectos = $id_proyecto
            AND tu.id_estadoT = 4
        ");
        $done = $done_q->fetch_assoc()['done'] ?? 0;

        return round(($done / $total) * 100);
    }

    public function getTareasUsuario(int $id_usuario): array
    {
        $sql = "
            SELECT 
                tu.id_tarea,
                tt.descripcion_tipo AS nombre_tarea,
                t.descripcion,
                t.fecha_entrega,
                tu.id_estadoT,
                et.nombre AS estado
            FROM tareas_usuarios tu
            INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
            INNER JOIN tipo_tarea tt ON t.id_tareatipo = tt.id_tareatipo
            INNER JOIN estados_tarea et ON tu.id_estadoT = et.id_estadoT
            INNER JOIN tbl_seguimiento s ON t.id_avances = s.id_avances
            INNER JOIN proyectos p ON s.id_proyectos = p.id_proyectos
            WHERE 
                tu.id_usuarios = $id_usuario
                OR (p.id_investigador = $id_usuario AND tu.id_usuarios != $id_usuario)
            ORDER BY t.fecha_entrega DESC
        ";

        $result = $this->conn->query($sql);
        $tareas = [];
        while ($row = $result->fetch_assoc()) {
            $tareas[] = $row;
        }
        return $tareas;
    }

    public function getProyectosIds(int $id_usuario): array
    {
        $ids = [];
        $res = $this->conn->query("
            SELECT id_proyectos FROM proyectos_usuarios WHERE id_usuarios = $id_usuario
        ");
        while ($row = $res->fetch_assoc()) {
            $ids[] = $row['id_proyectos'];
        }
        return $ids;
    }

    public function getModificaciones(string $rol, array $proyectos_ids): array
    {
        if ($rol === 'supervisor') {
            $sql = "
                SELECT 
                    tu.contenido,
                    t.fecha_modificacion AS fecha,
                    u.nombre,
                    r.nombre AS rol
                FROM tareas_usuarios tu
                INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
                INNER JOIN usuarios u ON tu.id_usuarios = u.id_usuarios
                INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuarios
                INNER JOIN roles r ON ur.id_rol = r.id_roles
                WHERE t.fecha_modificacion IS NOT NULL
                ORDER BY t.fecha_modificacion DESC
                LIMIT 5
            ";
        } else {
            $ids_lista = empty($proyectos_ids) ? '0' : implode(',', $proyectos_ids);
            $sql = "
                SELECT 
                    tu.contenido,
                    t.fecha_modificacion AS fecha,
                    u.nombre,
                    r.nombre AS rol
                FROM tareas_usuarios tu
                INNER JOIN tareas t ON tu.id_tarea = t.id_tarea
                INNER JOIN usuarios u ON tu.id_usuarios = u.id_usuarios
                INNER JOIN usuarios_roles ur ON u.id_usuarios = ur.id_usuarios
                INNER JOIN roles r ON ur.id_rol = r.id_roles
                INNER JOIN proyectos_usuarios pu ON tu.id_usuarios = pu.id_usuarios
                WHERE pu.id_proyectos IN ($ids_lista)
                AND t.fecha_modificacion IS NOT NULL
                ORDER BY t.fecha_modificacion DESC
                LIMIT 5
            ";
        }

        $result = $this->conn->query($sql);
        $mods = [];
        while ($row = $result->fetch_assoc()) {
            $mods[] = $row;
        }
        return $mods;
    }
}