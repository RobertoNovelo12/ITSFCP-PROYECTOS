<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Solicitud
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    // ===========================
    // RESUMEN
    // ===========================
    public function resumen(int $id): array
    {
        $sql = "
            SELECT
                COUNT(*) total,
                SUM(sp.estado='pendiente') pendientes,
                SUM(sp.estado='en_revision') en_revision,
                SUM(sp.estado='correcciones') correcciones,
                SUM(sp.estado='aceptado') aceptadas,
                SUM(sp.estado='rechazado') rechazadas
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE p.id_investigador = ?
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    // ===========================
    // CONTAR
    // ===========================
    public function contarSolicitudes(int $id, array $f): int
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);

        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            JOIN usuarios u ON u.id_usuarios = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios = sp.id_estudiante
            JOIN carreras c ON c.id_carrera = e.id_carrera
            $where
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        return $stmt->get_result()->fetch_row()[0] ?? 0;
    }

    // ===========================
    // LISTADO
    // ===========================
    public function obtenerSolicitudes(int $id, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);

        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera carrera,
                p.titulo proyecto_titulo
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            JOIN usuarios u ON u.id_usuarios = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios = sp.id_estudiante
            JOIN carreras c ON c.id_carrera = e.id_carrera
            $where
            ORDER BY sp.fecha_envio ASC
            LIMIT ?, ?
        ";

        $params[] = $desde;
        $params[] = $limite;
        $types   .= "ii";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function construirWhere(int $id, array $f): array
    {
        $cond = ["p.id_investigador = ?"];
        $params = [$id];
        $types = "i";

        if (!empty($f['estado'])) {
            $cond[] = "sp.estado = ?";
            $params[] = $f['estado'];
            $types .= "s";
        }

        if (!empty($f['buscar'])) {
            $cond[] = "(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)";
            $b = "%" . $f['buscar'] . "%";
            array_push($params, $b, $b, $b);
            $types .= "sss";
        }

        $where = "WHERE " . implode(" AND ", $cond);

        return [$where, $params, $types];
    }

    // ===========================
    // DETALLE
    // ===========================
    public function obtenerDetalle(int $id)
    {
        $sql = "
            SELECT
                sp.*,
                CONCAT(u.nombre,' ',u.apellido_paterno,' ',u.apellido_materno) estudiante_nombre,
                u.correo_institucional,
                e.matricula,
                c.nombre_carrera carrera,
                p.titulo proyecto_titulo,
                p.modalidad
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            JOIN usuarios u ON u.id_usuarios = sp.id_estudiante
            JOIN estudiantes e ON e.id_usuarios = sp.id_estudiante
            JOIN carreras c ON c.id_carrera = e.id_carrera
            WHERE sp.id_solicitud_proyecto = ?
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    // ===========================
    // PERMISO
    // ===========================
    public function verificarPermiso(int $id, int $inv): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM solicitud_proyecto sp
            JOIN proyectos p ON p.id_proyectos = sp.id_proyectos
            WHERE sp.id_solicitud_proyecto = ?
            AND p.id_investigador = ?
        ";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id, $inv);
        $stmt->execute();

        return $stmt->get_result()->fetch_row()[0] > 0;
    }

    // ===========================
    // CAMBIOS
    // ===========================
    public function marcarEnRevision(int $id): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado='en_revision'
            WHERE id_solicitud_proyecto=? AND estado='pendiente'
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function aceptar(int $id): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado='aceptado', fecha_respuesta=CURDATE()
            WHERE id_solicitud_proyecto=?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function pedirCorrecciones(int $id, int $user, string $com, $ruta=null, $nombre=null): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado='correcciones'
            WHERE id_solicitud_proyecto=?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function rechazar(int $id, int $user, string $com, $ruta=null, $nombre=null): bool
    {
        $stmt = $this->con->prepare("
            UPDATE solicitud_proyecto
            SET estado='rechazado', motivo_rechazo=?
            WHERE id_solicitud_proyecto=?
        ");
        $stmt->bind_param("si", $com, $id);
        return $stmt->execute();
    }

    public function obtenerComentarios(int $id): array
    {
        $stmt = $this->con->prepare("
            SELECT * FROM solicitud_comentarios
            WHERE id_solicitud=?
            ORDER BY fecha ASC
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

        // -------------------------------------------------------------
    // Enviar correcciones (estudiante)
    // -------------------------------------------------------------
    public function enviarCorrecciones($id, $usuario, $comentario, $x, $ruta, $nombre)
    {
        $sql = "INSERT INTO solicitud_comentarios
                (id_solicitud, id_usuario, comentario, archivo_ruta, nombre_archivo, fecha)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iisss", $id, $usuario, $comentario, $ruta, $nombre);

        return $stmt->execute();
    }

        public function proyectosDelInvestigador(int $id): array
    {
        $stmt = $this->con->prepare("SELECT id_proyectos, titulo FROM proyectos WHERE id_investigador=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

     public function getEtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        $sql = "SELECT 
    e.*,
    COALESCE(s.estado, 'pendiente') AS estado,
    s.id_seguimiento,
    s.observaciones,
    s.comentario_supervisor,
    td.id_tipo_documento,
    pd.id_plantilla
FROM etapas_documento e

LEFT JOIN tipo_documento td 
    ON td.orden = e.orden 
    AND td.estado = 1

LEFT JOIN seguimiento_documento s 
    ON s.id_tipo_documento = td.id_tipo_documento 
    AND s.id_proyectos = ? 
    AND s.id_usuarios = ?

LEFT JOIN plantillas_documentos pd 
    ON pd.id_tipo_documento = td.id_tipo_documento 
    AND pd.activo = 1

WHERE e.estado = 1
ORDER BY e.orden;";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}