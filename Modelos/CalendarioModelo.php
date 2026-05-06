<?php
class CalendarioModelo
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getEventosSupervisor(): array
    {
        $sql = "
            SELECT 
                t.id_tarea AS id_eventos,
                MIN(p.id_proyectos) AS id_proyectos,
                MIN(p.titulo) AS proyecto,
                tt.descripcion_tipo AS title,
                t.descripcion AS descripcion,
                NULL AS ubicacion,
                t.fecha_entrega AS start,
                t.fecha_entrega AS end,
                'tarea' AS tipo
            FROM tareas t
            INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
            WHERE pu.estado = 'activo'
            GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
        ";
        return $this->ejecutarQuery($sql);
    }

    public function getEventosInvestigador(int $id_usuario): array
    {
        $sql = "
            SELECT 
                t.id_tarea AS id_eventos,
                MIN(p.id_proyectos) AS id_proyectos,
                MIN(p.titulo) AS proyecto,
                tt.descripcion_tipo AS title,
                t.descripcion AS descripcion,
                NULL AS ubicacion,
                t.fecha_entrega AS start,
                t.fecha_entrega AS end,
                'tarea' AS tipo
            FROM tareas t
            INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
            WHERE p.id_investigador = ?
              AND pu.estado = 'activo'
            GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getEventosEstudiante(int $id_usuario): array
    {
        $sql = "
            SELECT 
                t.id_tarea AS id_eventos,
                MIN(p.id_proyectos) AS id_proyectos,
                MIN(p.titulo) AS proyecto,
                tt.descripcion_tipo AS title,
                t.descripcion AS descripcion,
                NULL AS ubicacion,
                t.fecha_entrega AS start,
                t.fecha_entrega AS end,
                'tarea' AS tipo
            FROM tareas t
            INNER JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            INNER JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            INNER JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            INNER JOIN tipo_tarea tt ON tt.id_tareatipo = t.id_tipotarea
            WHERE tu.id_usuarios = ?
              AND pu.estado = 'activo'
            GROUP BY t.id_tarea, tt.descripcion_tipo, t.descripcion, t.fecha_entrega
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getEventosPersonales(int $id_usuario): array
    {
        $sql = "
            SELECT 
                e.id_eventos,
                e.id_proyectos,
                COALESCE(p.titulo, 'Sin proyecto') AS proyecto,
                e.titulo AS title,
                e.descripcion,
                e.ubicacion,
                DATE(e.fecha_inicio) AS start,
                DATE(e.fecha_fin) AS end,
                'evento' AS tipo
            FROM eventos_calendario e
            LEFT JOIN proyectos p ON p.id_proyectos = e.id_proyectos
            INNER JOIN eventos_usuarios eu ON eu.id_eventos = e.id_eventos
            WHERE eu.id_usuarios = ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getProyectosSupervisor(): array
    {
        $sql = "SELECT id_proyectos AS id, titulo FROM proyectos WHERE id_estadoP = 2";
        return $this->ejecutarQuery($sql);
    }

    public function getProyectosUsuario(int $id_usuario): array
    {
        $sql = "
            SELECT p.id_proyectos AS id, p.titulo
            FROM proyectos p
            WHERE p.id_estadoP = 2
              AND (
                p.id_investigador = ?
                OR p.id_proyectos IN (
                    SELECT id_proyectos FROM proyectos_usuarios WHERE id_usuarios = ?
                )
              )
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $id_usuario, $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function ejecutarQuery(string $sql): array
    {
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}