<?php
// Modelos/solicitudes_proyecto.php
// Modelo del módulo Solicitudes de Proyecto.
// Gestiona el listado, resumen, detalle y cambios de estado de solicitudes
// de creación y cierre. Incluye los métodos de actualización de estado que
// necesita el controlador de este módulo.

require_once __DIR__ . '/../publico/config/conexion.php';

class SolicitudesProyecto
{
    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // 
    // HELPER INTERNO
    // 

    private function ejecutar(string $sql, string $types = "", array $params = [], bool $fetchAll = true)
    {
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error en prepare(): " . $this->con->error);
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

    // 
    // MANTENIMIENTO AUTOMÁTICO DE ESTADOS
    // (requerido antes de cualquier cambio de estado)
    // 

    public function actualizarProyectosVencidos(): bool
    {
        return $this->ejecutar(
            "UPDATE proyectos
             SET id_estadoP = 6
             WHERE id_estadoP IN (2,3,4,5,7)
               AND fecha_fin < CURDATE()"
        );
    }

    // 
    // ACTUALIZAR ESTADO — aprobación (GET desde index/detalles)
    // 

    public function actualizarestado(int $id_proyectos, int $numeroEstado, ?float $porcentaje = null): void
    {
        $stmt = $this->con->prepare("UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?");
        if (!$stmt) throw new Exception("Error prepare: " . $this->con->error);
        $stmt->bind_param("ii", $numeroEstado, $id_proyectos);
        if (!$stmt->execute()) throw new Exception("Error execute: " . $stmt->error);
        $stmt->close();

        // Estado 2 → Activo: crear seguimiento y tareas
        if ($numeroEstado === 2) {

            $resPlantilla  = $this->con->query(
                "SELECT pd.id_plantilla, pd.id_documento
                 FROM plantillas_documentos pd
                 INNER JOIN tipo_documento td ON td.id_tipo_documento = pd.id_tipo_documento
                 WHERE pd.activo = 1 AND LOWER(td.nombre) LIKE 'reporte%' LIMIT 1"
            );
            $plantillaRep   = $resPlantilla ? $resPlantilla->fetch_assoc() : null;
            $id_doc_reporte = $plantillaRep['id_documento'] ?? null;

            $stmtSeg = $this->con->prepare(
                "INSERT INTO tbl_seguimiento (id_proyectos, fecha_activacion) VALUES (?, CURDATE())"
            );
            if (!$stmtSeg) throw new Exception("Error prepare tbl_seguimiento: " . $this->con->error);
            $stmtSeg->bind_param("i", $id_proyectos);
            $stmtSeg->execute();
            $id_avances = (int)$stmtSeg->insert_id;
            $stmtSeg->close();

            $result = $this->con->query("SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC");
            if (!$result) throw new Exception("Error al obtener tipos de tarea.");

            $estadoSinActivar = 4;
            $stmtTarea    = $this->con->prepare(
                "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT) VALUES (?, ?, ?)"
            );
            $stmtTareaDoc = $this->con->prepare(
                "INSERT INTO tareas (id_avances, id_tareatipo, id_estadoT, id_documento_recurso) VALUES (?, ?, ?, ?)"
            );

            while ($row = $result->fetch_assoc()) {
                $id_tipo = (int)$row['id_tareatipo'];
                if ($id_tipo === 12 && $id_doc_reporte !== null) {
                    $stmtTareaDoc->bind_param("iiii", $id_avances, $id_tipo, $estadoSinActivar, $id_doc_reporte);
                    $stmtTareaDoc->execute();
                } else {
                    $stmtTarea->bind_param("iii", $id_avances, $id_tipo, $estadoSinActivar);
                    $stmtTarea->execute();
                }
            }
            $stmtTarea->close();
            $stmtTareaDoc->close();

        // Estado 1 → Cierre aprobado
        } elseif ($numeroEstado === 1) {

            $stmtInv = $this->con->prepare("SELECT id_investigador FROM proyectos WHERE id_proyectos = ?");
            $stmtInv->bind_param("i", $id_proyectos);
            $stmtInv->execute();
            $row = $stmtInv->get_result()->fetch_assoc();
            $stmtInv->close();

            if ($row) {
                $estadoCierre = 'aprobado';
                $stmtC = $this->con->prepare(
                    "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = ? WHERE id_proyectos = ?"
                );
                $stmtC->bind_param("si", $estadoCierre, $id_proyectos);
                $stmtC->execute();
                $stmtC->close();

                $estadoUsuario = 'concluido';
                $stmtU = $this->con->prepare(
                    "UPDATE proyectos_usuarios SET fecha_terminacion = CURDATE(), estado = ? WHERE id_proyectos = ?"
                );
                $stmtU->bind_param("si", $estadoUsuario, $id_proyectos);
                $stmtU->execute();
                $stmtU->close();
            }
        }
    }

    // 
    // ACTUALIZAR ESTADO — rechazo con comentario (POST desde comentarios.php)
    // 

    public function actualizarEstadoProyectoRechazo(
        int $id_usuario, int $id_proyectos, string $tipo, string $comentario
    ): void {
        $num_motivo = ($tipo === 'cierre_rechazado') ? 7 : 4;

        $stmt = $this->con->prepare("UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?");
        $stmt->bind_param("ii", $num_motivo, $id_proyectos);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);

        $stmt = $this->con->prepare(
            "INSERT INTO proyectos_comentarios (id_proyectos, id_usuarios, tipo, comentario, fecha)
             VALUES (?, ?, ?, ?, CURDATE())"
        );
        $stmt->bind_param("iiss", $id_proyectos, $id_usuario, $tipo, $comentario);
        if (!$stmt->execute()) die("Error execute(): " . $stmt->error);

        if ($tipo === 'cierre_rechazado') {
            $estado = 'rechazado';
            $stmtC  = $this->con->prepare(
                "UPDATE tbl_cierres SET fecha_resultado = CURDATE(), estado = ? WHERE id_proyectos = ?"
            );
            $stmtC->bind_param("si", $estado, $id_proyectos);
            $stmtC->execute();
        }
    }

    // 
    // PORCENTAJE DE AVANCE
    // 

    private function valorPorEstado(int $estado): int
    {
        return match ($estado) {
            5       => 100,
            2, 3    => 50,
            default => 0,
        };
    }

    public function obtenerTareasAvance(int $id_proyecto): float
    {
        $stmt = $this->con->prepare(
            "SELECT taus.id_estadoT
             FROM tareas_usuarios AS taus
             JOIN tareas AS tare ON tare.id_tarea = taus.id_tarea
             JOIN tbl_seguimiento AS tbse ON tare.id_avances = tbse.id_avances
             WHERE tbse.id_proyectos = ? AND taus.id_estadoT = 5"
        );
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result      = $stmt->get_result();
        $totalTareas = 11;
        $suma        = 0;

        while ($row = $result->fetch_assoc()) {
            $suma += $this->valorPorEstado((int)$row['id_estadoT']);
        }

        return round(min(100, ($suma / $totalTareas) * 100), 2);
    }

    // 
    // RESUMEN (tarjetas del dashboard)
    // 

    public function resumenSolicitudes(string $rol, int $id_usuario, int $id_periodo = 0): array
    {
        $where_periodo = $id_periodo ? " AND proy.id_periodos = $id_periodo" : "";

        if ($rol === 'supervisor') {
            $sql  = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN espr.nombre = 'Por aprobar'             THEN 1 ELSE 0 END) AS pendientes_creacion,
                        SUM(CASE WHEN espr.nombre = 'Por cerrar'              THEN 1 ELSE 0 END) AS pendientes_cierre,
                        SUM(CASE WHEN espr.nombre IN ('Activo','Cierre')      THEN 1 ELSE 0 END) AS aprobadas
                     FROM proyectos proy
                     JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
                     WHERE proy.id_estadoP IN (3, 5, 2, 1)
                     $where_periodo";
            $stmt = $this->con->prepare($sql);
            $stmt->execute();
        } else {
            $sql  = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN espr.nombre = 'Por aprobar'             THEN 1 ELSE 0 END) AS pendientes_creacion,
                        SUM(CASE WHEN espr.nombre = 'Por cerrar'              THEN 1 ELSE 0 END) AS pendientes_cierre,
                        SUM(CASE WHEN espr.nombre IN ('Activo','Cierre')      THEN 1 ELSE 0 END) AS aprobadas
                     FROM proyectos proy
                     JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
                     WHERE proy.id_investigador = ?
                       AND proy.id_estadoP IN (3, 4, 5, 7, 2, 1)
                     $where_periodo";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
        }

        return $stmt->get_result()->fetch_assoc() ?? [
            'total'               => 0,
            'pendientes_creacion' => 0,
            'pendientes_cierre'   => 0,
            'aprobadas'           => 0,
        ];
    }

    // 
    // LISTADO PAGINADO
    // 

    public function listarSolicitudes(
        string $rol,
        int $id_usuario,
        string $tipo_filtro = 'Todas',
        string $buscar = '',
        int $pagina = 1,
        int $id_periodo = 0
    ): string {
        $por_pagina = 6;
        $pagina     = max(1, $pagina);
        $desde      = ($pagina - 1) * $por_pagina;

        $estados = match ($tipo_filtro) {
            'Creacion'   => [3, 4],
            'Cierre'     => [5, 7, 1],
            'Pendientes' => [3, 5],
            default      => [3, 4, 5, 7, 1, 2],
        };

        $in_estados = implode(',', $estados);
        $where_rol  = ($rol === 'supervisor') ? "" : " AND proy.id_investigador = ?";
        $base_where = "proy.id_estadoP IN ($in_estados) $where_rol";

        if ($id_periodo) {
            $base_where .= " AND proy.id_periodos = ?";
        }
        if (!empty($buscar)) {
            $base_where .= " AND proy.titulo LIKE ?";
        }

        // -- TOTAL --
        $stmt_total = $this->con->prepare(
            "SELECT COUNT(*) AS total FROM proyectos proy WHERE $base_where"
        );
        $bind_params = [];
        $bind_types  = "";

        if ($rol !== 'supervisor') {
            $bind_params[] = $id_usuario;
            $bind_types   .= "i";
        }
        if ($id_periodo) {
            $bind_params[] = $id_periodo;
            $bind_types   .= "i";
        }
        if (!empty($buscar)) {
            $bind_params[] = "%$buscar%";
            $bind_types   .= "s";
        }

        if (!empty($bind_params)) {
            $stmt_total->bind_param($bind_types, ...$bind_params);
        }
        $stmt_total->execute();
        $total         = (int)($stmt_total->get_result()->fetch_assoc()['total'] ?? 0);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        // -- DATOS --
        $stmt = $this->con->prepare(
            "SELECT
                proy.id_proyectos,
                proy.titulo,
                espr.nombre AS estado_proyecto,
                peri.periodo,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS investigador,
                proy.creado_en AS fecha_solicitud,
                CASE
                    WHEN proy.id_estadoP IN (3, 4) THEN 'creacion'
                    ELSE 'cierre'
                END AS tipo_solicitud
             FROM proyectos proy
             JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
             JOIN periodos peri ON proy.id_periodos = peri.id_periodos
             JOIN usuarios u ON u.id_usuarios = proy.id_investigador
             WHERE $base_where
             ORDER BY proy.id_proyectos DESC
             LIMIT ?, ?"
        );

        $params = $bind_params;
        $types  = $bind_types;
        $params[] = $desde;
        $params[] = $por_pagina;
        $types   .= "ii";

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return json_encode([
            "solicitudes" => $solicitudes,
            "paginacion"  => compact("total", "por_pagina", "pagina") + ["total_paginas" => $total_paginas],
        ]);
    }

    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos(): array
    {
        return $this->ejecutar(
            "SELECT id_periodos, periodo,
                    fecha_inicio AS FechaInicio, fecha_final AS FechaFinal,
                    CASE
                        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
                        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
                        ELSE 'Terminado'
                    END AS estado
             FROM periodos ORDER BY periodo DESC"
        );
    }

    // 
    // DETALLE DE SOLICITUD
    // 

    public function obtenerProyecto(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT
                proy.id_proyectos,
                espr.nombre AS estado_proyecto,
                tema.nombre_tematica AS tematica,
                peri.periodo,
                CASE
                    WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo'
                    WHEN CURDATE() < peri.fecha_inicio THEN 'Pendiente'
                    ELSE 'Terminado'
                END AS estado_periodo,
                proy.titulo, proy.descripcion, proy.objetivo,
                proy.fecha_inicio, proy.fecha_fin, proy.presupuesto,
                proy.creado_en, proy.requisitos, proy.pre_requisitos,
                proy.modalidad, proy.cantidad_estudiante
             FROM proyectos AS proy
             JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
             JOIN proyectos_subtematica AS proy_sub ON proy.id_proyectos = proy_sub.id_proyectos
             JOIN subtematica AS subt ON proy_sub.id_subtematica = subt.id_subtematica
             JOIN tematica AS tema ON tema.id_tematica = subt.id_tematica
             JOIN periodos peri ON proy.id_periodos = peri.id_periodos
             WHERE proy.id_proyectos = ?
             GROUP BY proy.id_proyectos, espr.nombre, tema.nombre_tematica
             ORDER BY proy.id_proyectos DESC",
            "i", [$id_proyecto], false
        );
    }

    public function obtenerProyectoInvestigador(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno,
                    nisn.nombre AS nivel_sni, grac.nombre AS grado_academico
             FROM investigadores AS inve
             JOIN usuarios AS usua ON usua.id_usuarios = inve.id_usuarios
             JOIN niveles_sni AS nisn ON nisn.id_nivel = inve.id_nivel_sni
             JOIN grados_academicos AS grac ON grac.id_grado = inve.id_grado
             JOIN proyectos AS proy ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i", [$id_proyecto], false
        );
    }

    public function obtenerUsuarioArea(?int $id_usuario): ?array
    {
        if (!$id_usuario) return null;
        return $this->ejecutar(
            "SELECT arco.nombre_area AS area_conocimiento, GROUP_CONCAT(subco.nombre_subarea) AS subarea
             FROM usuarios AS us
             JOIN usuarios_subareas AS ussu ON ussu.id_usuarios = us.id_usuarios
             JOIN subareas_conocimiento AS subco ON ussu.id_subarea = subco.id_subarea
             JOIN areas_conocimiento AS arco ON arco.id_area = subco.id_area
             WHERE us.id_usuarios = ?
             GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area",
            "i", [$id_usuario], false
        );
    }

    public function obtenerInvestigadorLinea(int $id_proyecto): ?array
    {
        return $this->ejecutar(
            "SELECT liin.nombre AS linea
             FROM investigadores AS inve
             JOIN investigador_lineas_investigacion AS inliin ON inliin.id_usuarios = inve.id_usuarios
             JOIN lineas_investigacion AS liin ON liin.id_linea = inliin.id_linea
             JOIN proyectos AS proy ON proy.id_investigador = inve.id_usuarios
             WHERE proy.id_proyectos = ?",
            "i", [$id_proyecto], false
        );
    }

    public function obtenersubtematicasProyecto(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre
             FROM proyectos_subtematica AS sub
             JOIN subtematica AS sub2 ON sub.id_subtematica = sub2.id_subtematica
             WHERE sub.id_proyectos = ? AND sub2.estado = 1",
            "i", [$id_proyecto]
        );
    }

    public function obtenerProyectoComentarios(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                CASE
                    WHEN prco.tipo = 'creacion_rechazada' THEN 'Creación rechazada'
                    WHEN prco.tipo = 'cierre_rechazado'   THEN 'Cierre rechazado'
                    ELSE 'Rechazo'
                END AS tipo,
                CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) AS nombre_completo,
                prco.comentario,
                prco.fecha
             FROM proyectos_comentarios AS prco
             JOIN proyectos AS proy ON proy.id_proyectos = prco.id_proyectos
             JOIN usuarios AS usua ON usua.id_usuarios = prco.id_usuarios
             WHERE proy.id_proyectos = ?
             ORDER BY fecha DESC",
            "i", [$id_proyecto]
        );
    }

    public function estudiantes(int $id_proyecto): array
    {
        return $this->ejecutar(
            "SELECT
                u.id_usuarios,
                u.nombre, u.apellido_paterno, u.apellido_materno,
                c.nombre_carrera AS carrera,
                pu.estado
             FROM proyectos_usuarios pu
             JOIN usuarios u ON u.id_usuarios = pu.id_usuarios
             JOIN estudiantes e ON e.id_usuarios = u.id_usuarios
             JOIN carreras c ON e.id_carrera = c.id_carrera
             WHERE pu.id_proyectos = ?",
            "i", [$id_proyecto]
        );
    }
}