<?php

/**
 * seguimiento.php
 * Modelo para el seguimiento de documentación de proyectos de investigación.
 * Usa PDO exclusivamente.
 */

class SeguimientoModelo
{
    private mysqli $con;

    public function __construct(mysqli $conn)
    {
        $this->con = $conn;
    }

    public function getProyectoPorId(int $id_usuario, int $id_proyecto)
    {
        $sql = "SELECT p.*, ep.nombre AS estado_nombre
            FROM proyectos p
            JOIN proyectos_usuarios pu 
                ON pu.id_proyectos = p.id_proyectos
            JOIN estados_proyectos ep 
                ON ep.id_estadoP = p.id_estadoP
            WHERE pu.id_usuarios = ?
            AND p.id_proyectos = ?
            LIMIT 1";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_usuario, $id_proyecto);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
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

    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        $sql = "INSERT INTO seguimiento_documento
                (id_proyectos,id_tipo_documento,id_usuarios,estado,fecha_inicio)
                VALUES (?,?,?,'proceso',NOW())";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iii", $id_proyecto, $id_tipo_documento, $id_usuario);
        $stmt->execute();
        return $this->con->insert_id;
    }

    public function registrarDocumento(int $id_seg, int $id_plan, string $nombre, string $ruta): bool
    {
        $sql = "INSERT INTO documentos_subidos
                (id_seguimiento,id_plantilla,nombre_archivo,ruta)
                VALUES (?,?,?,?)";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iiss", $id_seg, $id_plan, $nombre, $ruta);
        return $stmt->execute();
    }

    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        $sql = "SELECT COUNT(*) total
                FROM seguimiento_documento s
                JOIN proyectos p ON p.id_proyectos=s.id_proyectos
                WHERE s.id_seguimiento=? AND p.id_investigador=?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_seg, $id_inv);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res['total'] > 0;
    }

    public function actualizarEstado(int $id_seg, string $estado, string $comentario, int $id_rev): bool
    {
        $sql = "UPDATE seguimiento_documento
                SET estado=?, comentario_supervisor=?, revisado_por=?, fecha_revision=NOW()
                WHERE id_seguimiento=?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ssii", $estado, $comentario, $id_rev, $id_seg);
        return $stmt->execute();
    }

    public function responderSolicitud(int $id_sol, string $estado, string $comentario, int $id_inv): bool
    {
        $this->con->begin_transaction();

        try {
            $sql = "UPDATE solicitud_proyecto
                    SET estado=?, comentarios=?, fecha_respuesta=CURDATE()
                    WHERE id_solicitud_proyecto=?";

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ssi", $estado, $comentario, $id_sol);
            $stmt->execute();

            if ($estado === 'aceptado') {
                $res = $this->con->query("SELECT id_proyectos,id_estudiante FROM solicitud_proyecto WHERE id_solicitud_proyecto=$id_sol")->fetch_assoc();

                $stmt2 = $this->con->prepare("INSERT IGNORE INTO proyectos_usuarios
                    (id_proyectos,id_usuarios,fecha_asignacion,estado)
                    VALUES (?, ?, CURDATE(),'activo')");
                $stmt2->bind_param("ii", $res['id_proyectos'], $res['id_estudiante']);
                $stmt2->execute();
            }

            $this->con->commit();
            return true;
        } catch (Exception $e) {
            $this->con->rollback();
            return false;
        }
    }

    public function getSolicitudesParaRevisar(int $id): array
    {
        $res = $this->con->query("SELECT * FROM solicitud_proyecto WHERE estado='pendiente'");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getAvanceEstudiantesPorInvestigador(int $id): array
    {
        $res = $this->con->query("SELECT * FROM seguimiento_documento");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getEstadisticasPeriodo(int $id): array
    {
        $res = $this->con->query("SELECT COUNT(*) proyectos FROM proyectos WHERE id_periodos=$id");
        return $res->fetch_assoc();
    }

    public function actualizarEstadoEstudiante(int $id, string $estado): bool
    {
        $stmt = $this->con->prepare("UPDATE seguimiento_documento SET estado=? WHERE id_seguimiento=?");
        $stmt->bind_param("si", $estado, $id);
        return $stmt->execute();
    }

    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        $sql = "SELECT COUNT(*) as total
            FROM tareas_usuarios as tu
            JOIN tareas AS t ON tu.id_tarea = t.id_tarea
            JOIN tbl_seguimiento AS ts ON ts.id_avances = t.id_avances
            WHERE tu.id_estadoT = 5 
            AND ts.id_proyectos = ? 
            AND tu.id_usuarios = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['total']; // ← ahora sí regresas el COUNT real
    }
}
