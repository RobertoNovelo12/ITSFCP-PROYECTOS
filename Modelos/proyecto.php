<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Proyectos
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    private function ejecutar($sql, $types = "", $params = [], $fetchAll = true)
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

        if (stripos($sql, "SELECT") === 0) {
            $result = $stmt->get_result();
            return $fetchAll ? $result->fetch_all(MYSQLI_ASSOC) : $result->fetch_assoc();
        }

        return true;
    }

    //ACTUALIZAR A VENCIDO LOS PROYECTOS
    public function actualizarProyectosVencidos()
    {
        $sql = "
        UPDATE proyectos 
        SET id_estadoP = 6
        WHERE id_estadoP IN (2,3,4,5,7)
        AND fecha_fin < CURDATE()
    ";

        return $this->ejecutar($sql, "", []);
    }


    private function construirBaseQuery($rol, &$params, &$types, $id = null)
    {
        $sql = "
    FROM proyectos proy
    JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
    JOIN periodos peri ON proy.id_periodos = peri.id_periodos
    ";

        switch ($rol) {

            case 'estudiante':
                $sql .= "
            JOIN proyectos_usuarios prus ON proy.id_proyectos = prus.id_proyectos
            JOIN estudiantes estu ON estu.id_usuarios = prus.id_usuarios
            ";
                $sql .= " WHERE estu.id_usuarios = ? ";
                $params[] = $id;
                $types .= "i";
                break;

            case 'investigador':
            case 'profesor':
                $sql .= "
            JOIN investigadores inv ON inv.id_usuarios = proy.id_investigador
            ";
                $sql .= " WHERE proy.id_investigador = ? ";
                $params[] = $id;
                $types .= "i";
                break;

            case 'supervisor':
                $sql .= " WHERE 1 ";
                break;
        }

        return $sql;
    }

    //DATOS DEL FILTRO
    public function obtenerProyectosDatosFiltro($id, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
  COUNT(*) AS Total,
  COALESCE(SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END),0) AS Activos,
  COALESCE(SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END),0) AS PorAprobar,
  COALESCE(SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END),0) AS Cierre,
  COALESCE(SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END),0) AS PorCerrar,
  COALESCE(SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END),0) AS Vencido
FROM gestion_proyectos.proyectos AS proy
JOIN proyectos_usuarios AS prus ON proy.id_proyectos = prus.id_proyectos
JOIN estudiantes AS estu ON prus.id_usuarios = estu.id_usuarios
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
WHERE estu.id_usuarios = ?;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'investigador':
            case 'profesor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  COALESCE(SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END),0) AS Activos,
  COALESCE(SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END),0) AS PorAprobar,
  COALESCE(SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END),0) AS Cierre,
  COALESCE(SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END),0) AS PorCerrar,
  COALESCE(SUM(CASE WHEN espr.nombre='Rechazado' THEN 1 ELSE 0 END),0) AS Rechazados,
  COALESCE(SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END),0) AS Vencido,
  COALESCE(SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END),0) AS Cierrerechazado
FROM gestion_proyectos.proyectos AS proy
JOIN investigadores AS inv ON inv.id_usuarios = proy.id_investigador
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP
WHERE proy.id_investigador = ?;";

                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id);
                break;
            case 'supervisor':
                $sql = "SELECT 
  COUNT(*) AS Total,
  SUM(CASE WHEN espr.nombre='Activo' THEN 1 ELSE 0 END) AS Activos,
  SUM(CASE WHEN espr.nombre='Por aprobar' THEN 1 ELSE 0 END) AS PorAprobar,
  SUM(CASE WHEN espr.nombre='Cierre' THEN 1 ELSE 0 END) AS Cierre,
  SUM(CASE WHEN espr.nombre='Por cerrar' THEN 1 ELSE 0 END) AS PorCerrar,
  SUM(CASE WHEN espr.nombre='Rechazado' THEN 1 ELSE 0 END) AS Rechazados, 
  SUM(CASE WHEN espr.nombre='Vencido' THEN 1 ELSE 0 END) AS Vencido,
  SUM(CASE WHEN espr.nombre='Cierre rechazado' THEN 1 ELSE 0 END) AS Cierrerechazado
FROM gestion_proyectos.proyectos AS proy
JOIN estados_proyectos AS espr ON proy.id_estadoP = espr.id_estadoP;";

                $stmt = $this->con->prepare($sql);
                break;
            default:
                return []; // Retorna un array vacío si el rol no es válido
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return $res;
    }

    //DATOS FILTRADOS SEGUN SELECCION
    public function obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar)
    {
        $rol = strtolower($rol);

        // --- PAGINACIÓN ---
        $por_pagina = 6;
        $pagina = isset($_GET['pagina']) && $_GET['pagina'] > 0 ? intval($_GET['pagina']) : 1;
        $desde = ($pagina - 1) * $por_pagina;

        $total_proyectos = $this->obtenerCantidadProyectos($id, $filtro, $rol, $buscar);
        $total_paginas = ($total_proyectos > 0) ? ceil($total_proyectos / $por_pagina) : 1;

        $params = [];
        $types = "";

        // --- SELECT ---
        $sql = "SELECT 
        proy.id_proyectos,
        proy.titulo,
        proy.fecha_inicio,
        proy.fecha_fin,
        espr.nombre AS estado_proyecto,
        peri.periodo,
        COALESCE(tareas_resumen.total, 0) AS total";

        if ($rol == 'estudiante') {
            $sql .= ", pu.estado AS estado_estudiante";
        } else {
            $sql .= ", NULL AS estado_estudiante";
        }

        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= ",
        CASE 
            WHEN COALESCE(pa.total_alumnos,0) > 0
            AND COALESCE(tt.total_tareas,0) >= 11
            AND COALESCE(tc.tareas_completadas,0) = 
                (COALESCE(tt.total_tareas,0) * COALESCE(pa.total_alumnos,0))
            THEN 1 ELSE 0 
        END AS puede_cerrar";
        }

        // --- FROM ---
        $sql .= "
    FROM proyectos proy
    JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
    JOIN periodos peri ON proy.id_periodos = peri.id_periodos";

        // --- JOIN POR ROL ---
        if ($rol === 'estudiante') {
            $sql .= " 
        LEFT JOIN proyectos_usuarios pu 
            ON pu.id_proyectos = proy.id_proyectos 
            AND pu.id_usuarios = ?";
            $params[] = $id;
            $types .= "i";
        }

        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= " 
        JOIN investigadores inv 
            ON inv.id_usuarios = proy.id_investigador";
        }

        // --- SUBQUERY ---
        $sql .= "
    LEFT JOIN (
        SELECT ts.id_proyectos,
               COUNT(CASE WHEN tu.id_estadoT = 2 THEN 1 END) AS total
        FROM tbl_seguimiento ts
        JOIN tareas t ON t.id_avances = ts.id_avances
        LEFT JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
        GROUP BY ts.id_proyectos
    ) tareas_resumen ON tareas_resumen.id_proyectos = proy.id_proyectos";

        // --- EXTRA JOINS ---
        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= "
        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(DISTINCT t.id_tarea) total_tareas
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            WHERE t.id_estadoT = 1
            GROUP BY ts.id_proyectos
        ) tt ON tt.id_proyectos = proy.id_proyectos

        LEFT JOIN (
            SELECT pu.id_proyectos, COUNT(*) total_alumnos
            FROM proyectos_usuarios pu
            WHERE pu.estado = 'activo'
            GROUP BY pu.id_proyectos
        ) pa ON pa.id_proyectos = proy.id_proyectos

        LEFT JOIN (
            SELECT ts.id_proyectos, COUNT(*) tareas_completadas
            FROM tbl_seguimiento ts
            JOIN tareas t ON t.id_avances = ts.id_avances
            JOIN tareas_usuarios tu ON tu.id_tarea = t.id_tarea
            JOIN proyectos_usuarios pu ON pu.id_usuarios = tu.id_usuarios
            WHERE t.id_estadoT = 1
              AND tu.id_estadoT = 5
              AND pu.estado = 'activo'
            GROUP BY ts.id_proyectos
        ) tc ON tc.id_proyectos = proy.id_proyectos";
        }

        // --- WHERE ---
        $sql .= " WHERE 1";

        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= " AND proy.id_investigador = ?";
            $params[] = $id;
            $types .= "i";
        }

        if ($filtro != 0) {
            $sql .= " AND proy.id_estadoP = ?";
            $params[] = $filtro;
            $types .= "i";
        }

        if (!empty($buscar)) {
            $sql .= " AND proy.titulo LIKE ?";
            $params[] = "%$buscar%";
            $types .= "s";
        }

        // --- FINAL ---
        $sql .= " GROUP BY proy.id_proyectos, pu.id_integrante 
              ORDER BY proy.id_proyectos DESC 
              LIMIT ?, ?";

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en SQL: " . $this->con->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return json_encode([
            "proyectos" => $data,
            "paginacion" => [
                "total_proyectos" => $total_proyectos,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ]);
    }

    //OBTENER LA CANTIDAD DE PROYECTOS
    public function obtenerCantidadProyectos($id, $filtro, $rol, $buscar)
    {
        $rol = strtolower($rol);

        $params = [];
        $types = "";

        $sql = "SELECT COUNT(DISTINCT proy.id_proyectos) AS total
            FROM proyectos proy
            JOIN estados_proyectos espr ON proy.id_estadoP = espr.id_estadoP
            JOIN periodos peri ON proy.id_periodos = peri.id_periodos ";

        // --- JOIN POR ROL ---
        if ($rol === 'estudiante') {
            $sql .= " JOIN proyectos_usuarios pu ON pu.id_proyectos = proy.id_proyectos ";
        }

        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= " JOIN investigadores inv ON inv.id_usuarios = proy.id_investigador ";
        }

        // --- WHERE ---
        $sql .= " WHERE 1 ";

        if ($rol === 'estudiante') {
            $sql .= " AND pu.id_usuarios = ? ";
            $params[] = $id;
            $types .= "i";
        }

        if ($rol === 'investigador' || $rol === 'profesor') {
            $sql .= " AND proy.id_investigador = ? ";
            $params[] = $id;
            $types .= "i";
        }

        if ($filtro != 0) {
            $sql .= " AND proy.id_estadoP = ? ";
            $params[] = $filtro;
            $types .= "i";
        }

        if (!empty($buscar)) {
            $sql .= " AND proy.titulo LIKE ? ";
            $params[] = "%$buscar%";
            $types .= "s";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en SQL (COUNT): " . $this->con->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        return $res['total'] ?? 0;
    }

    //ACTUALIZAR ESTADO DE ESTUDIANTES A BAJA SI PROYECTO VENCIDO
    public function actualizarEstadoEstudiantesVencidos()
    {
        // 1. BAJA POR PROYECTO VENCIDO

        $sql_historial_baja = "
    INSERT INTO historial_proyectos_usuarios 
    (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
    SELECT 
        pu.id_proyectos,
        pu.id_usuarios,
        'baja',
        'Proyecto vencido',
        0,
        NOW()
    FROM proyectos_usuarios pu
    JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
    WHERE 
        p.fecha_fin < CURDATE()
        AND p.id_estadoP != 1
        AND pu.estado = 'activo'
        AND NOT EXISTS (
            SELECT 1 
            FROM historial_proyectos_usuarios h
            WHERE 
                h.id_proyectos = pu.id_proyectos
                AND h.id_estudiante = pu.id_usuarios
                AND h.accion = 'baja'
                AND h.motivo = 'Proyecto vencido'
        )
    ";

        $this->ejecutar($sql_historial_baja);

        $sql_update_baja = "
    UPDATE proyectos_usuarios pu
    JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
    SET 
        pu.estado = 'baja',
        pu.fecha_baja = NOW(),
        pu.motivo_baja = 'Proyecto vencido'
    WHERE 
        p.fecha_fin < CURDATE()
        AND p.id_estadoP != 1
        AND pu.estado = 'activo'
    ";

        $this->ejecutar($sql_update_baja);

        // 2. CONCLUIR ESTUDIANTES SI PROYECTO ESTÁ EN CIERRE

        $sql_historial_concluido = "
    INSERT INTO historial_proyectos_usuarios 
    (id_proyectos, id_estudiante, accion, motivo, realizado_por, fecha)
    SELECT 
        pu.id_proyectos,
        pu.id_usuarios,
        'concluido',
        'Proyecto finalizado',
        0,
        NOW()
    FROM proyectos_usuarios pu
    JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
    WHERE 
        p.id_estadoP = 1
        AND pu.estado = 'activo'
        AND NOT EXISTS (
            SELECT 1 
            FROM historial_proyectos_usuarios h
            WHERE 
                h.id_proyectos = pu.id_proyectos
                AND h.id_estudiante = pu.id_usuarios
                AND h.accion = 'concluido'
        )
    ";

        $this->ejecutar($sql_historial_concluido);

        $sql_update_concluido = "
    UPDATE proyectos_usuarios pu
    JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
    SET 
        pu.estado = 'concluido'
    WHERE 
        p.id_estadoP = 1
        AND pu.estado = 'activo'
    ";

        return $this->ejecutar($sql_update_concluido);
    }

    public function tematica()
    {
        $sql = "SELECT id_tematica, nombre_tematica FROM gestion_proyectos.tematica;";

        $stmt = $this->con->prepare($sql);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenersubtematica($id_tematica)
    {
        $sql = "SELECT sub.id_subtematica, sub.nombre_subtematica FROM gestion_proyectos.subtematica as sub
JOIN tematica as te ON sub.id_tematica = te.id_tematica
WHERE te.id_tematica = ? AND sub.estado = 1";

        $params = [$id_tematica];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    //AÑADIR FUNCIÓN EN PERIODOS QUE NO PUEDE CREAR UN PERIODO QUE YA VENCIÓ LA FECHA Y CUANDO UN PERIODO ESTE ACTIVO

    public function obtenerperiodo()
    {
        $sql = "SELECT 
        id_periodos,
        periodo,
        fecha_inicio AS FechaInicio,
        fecha_final AS FechaFinal,
    CASE 
        WHEN CURDATE() BETWEEN fecha_inicio AND fecha_final THEN 'Activo'
        WHEN CURDATE() < fecha_inicio THEN 'Pendiente'
        ELSE 'Terminado'
    END AS estado
FROM periodos ORDER BY periodo DESC
LIMIT 1;";

        $stmt = $this->con->prepare($sql);

        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function obtenerinstituto()
    {
        $sql = "SELECT id_instituto FROM gestion_proyectos.instituto ORDER BY id_instituto DESC LIMIT 1;";

        $stmt = $this->con->prepare($sql);

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //INSERCION DE PROYECTOS
    public function registrarProyecto($id_investigador, $id_estadoP, $id_instituto, $id_periodos, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {

        $sql = "INSERT INTO proyectos 
(id_investigador, id_estadoP, id_instituto, id_periodos, titulo, descripcion, objetivo, fecha_inicio, fecha_fin, presupuesto, actualizado_en, requisitos, pre_requisitos, modalidad, cantidad_estudiante)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }
        $stmt->bind_param(
            "iiiisssssssssi",
            $id_investigador,
            $id_estadoP,
            $id_instituto,
            $id_periodos,
            $titulo,
            $descripcion,
            $objetivo,
            $fecha_inicio,
            $fecha_final,
            $presupuesto,
            $requisitos,
            $Pre_requisitos,
            $modalidad,
            $AlumnosCantidad,
        );

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        return $this->con->insert_id;
    }

    //ACTUALIZAR PROYECTO
    public function editarProyecto($id_proyecto, $id_investigador, $titulo, $descripcion, $objetivo, $fecha_inicio, $fecha_final, $presupuesto, $requisitos, $Pre_requisitos, $modalidad, $AlumnosCantidad)
    {
        $sql = "UPDATE proyectos SET 
                titulo = ?,
                descripcion = ?,
                objetivo = ?,
                pre_requisitos = ?,
                requisitos = ?,
                cantidad_estudiante = ?,
                modalidad = ?,
                actualizado_en = NOW(),
                presupuesto = ?,
                fecha_inicio = ?,
                fecha_fin = ?
            WHERE id_proyectos = ? AND id_investigador = ?";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }
        $stmt->bind_param(
            "sssssiisissi",
            $titulo,
            $descripcion,
            $objetivo,
            $Pre_requisitos,
            $requisitos,
            $AlumnosCantidad,
            $modalidad,
            $presupuesto,
            $fecha_inicio,
            $fecha_final,
            $id_proyecto,
            $id_investigador
        );

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
    }


    //ACCIÓN DE RECHAZO DE CIERRE
    public function actualizarEstadoProyectoRechazo($id_usuario, $id_proyectos, $tipo, $comentario)
    {
        //Actualizar estado
        if ($tipo == "cierre_rechazado") {
            $num_motivo = 7;
        } else if ($tipo == "creacion_rechazada") {
            $num_motivo = 4;
        }

        $sql = "UPDATE proyectos SET id_estadoP = ?, actualizado_en = NOW() WHERE id_proyectos = ?";
        $stmt = $this->con->prepare($sql);

        $stmt->bind_param("ii", $num_motivo, $id_proyectos);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        // Insertar comentario
        $sql = "INSERT INTO proyectos_comentarios 
            (id_proyectos, id_usuarios, tipo, comentario, fecha)
            VALUES (?, ?, ?, ?, CURDATE())";

        $stmt = $this->con->prepare($sql);

        $stmt->bind_param("iiss", $id_proyectos, $id_usuario, $tipo, $comentario);


        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }

        //Actualizar estado de cirrer (si esta es rechazo cierre)
        if ($tipo == "cierre_rechazado") {
            // Actualizar tbl_cierres
            $sql = "UPDATE tbl_cierres
            SET fecha_resultado = CURDATE(), estado = ? 
            WHERE id_proyectos = ?";
            $estado = "rechazado";
            $stmtSeg = $this->con->prepare($sql);
            $stmtSeg->bind_param("si", $estado, $id_proyectos);
            $stmtSeg->execute();
        }
    }

    public function actualizarestado($id_proyectos, $numeroEstado, $porcentaje = null)
    {

        // 1. Actualizar estado
        $sql = "UPDATE proyectos 
            SET id_estadoP = ?, actualizado_en = NOW() 
            WHERE id_proyectos = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        $stmt->bind_param("ii", $numeroEstado, $id_proyectos);

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        // 2. Si estado = 2, crear tareas
        $estado = "";
        if ($numeroEstado == 2) {

            // Obtener tipos reales de tareas
            $sqlTipos = "SELECT id_tareatipo FROM tipo_tarea ORDER BY id_tareatipo ASC";
            $result = $this->con->query($sqlTipos);

            // INSERT tbl_seguimiento
            $sqlSeg = "INSERT INTO tbl_seguimiento 
                    (id_proyectos, fecha_activacion) 
                    VALUES (?,  CURDATE())";

            $stmtSeg = $this->con->prepare($sqlSeg);
            $stmtSeg->bind_param("i", $id_proyectos);
            $stmtSeg->execute();

            $id_avances = $stmtSeg->insert_id;

            if ($result->num_rows > 0) {

                while ($row = $result->fetch_assoc()) {
                    $id_tipo = $row['id_tareatipo'];

                    // INSERT tareas
                    $sqlTarea = "INSERT INTO tareas 
                    (id_avances, id_tareatipo, id_estadoT)
                    VALUES (?, ?, ?)";
                    $estado = 4; // Estado : Sin activar
                    $stmtTarea = $this->con->prepare($sqlTarea);
                    if (!$stmtTarea) {
                        die("Error en prepare(): " . $this->con->error);
                    }
                    $stmtTarea->bind_param("iii", $id_avances, $id_tipo, $estado);
                    $stmtTarea->execute();
                }
            }
            //Por cerrar, insertar datos a tbl_cierres
        } else if ($numeroEstado == 5) {
            $sql_investigador = "SELECT id_investigador FROM proyectos WHERE id_proyectos = ?";
            $stmtInvestigador = $this->con->prepare($sql_investigador);
            $stmtInvestigador->bind_param("i", $id_proyectos);
            $stmtInvestigador->execute();
            $result = $stmtInvestigador->get_result();
            $estado = "espera";

            if ($row = $result->fetch_assoc()) {

                // INSERT tbl_seguimiento
                $sqlSeg = "INSERT INTO tbl_cierres 
                    (id_proyectos, id_supervisor, fecha_solicitud, porcentaje, estado) 
                    VALUES (?, ?, CURDATE(), ?, ?)";

                $stmtSeg = $this->con->prepare($sqlSeg);
                $stmtSeg->bind_param("iiis", $id_proyectos, $row['id_investigador'], $porcentaje,  $estado);
                $stmtSeg->execute();
            }
        } else if ($numeroEstado == 1) { //Cierre
            $sql_investigador = "SELECT id_investigador FROM proyectos WHERE id_proyectos = ?";
            $stmtInvestigador = $this->con->prepare($sql_investigador);
            $stmtInvestigador->bind_param("i", $id_proyectos);
            $result = $this->con->query($stmtInvestigador);

            if ($row = $result->fetch_assoc()) {

                // Actualizar tbl_cierres
                $sql = "UPDATE tbl_cierres
            SET fecha_resultado = CURDATE(), estado = ? 
            WHERE id_proyectos = ?";
                $estado = "aprobado";
                $stmtSeg = $this->con->prepare($sql);
                $stmtSeg->bind_param("si", $estado, $id_proyectos);
                $stmtSeg->execute();

                // Actualizar proyectos_usuarios
                $sql = "UPDATE proyectos_usuarios
            SET fecha_terminacion = CURDATE(), estado = ? 
            WHERE id_proyectos = ?";
                $estado = "concluido";
                $stmtSeg = $this->con->prepare($sql);
                $stmtSeg->bind_param("si", $estado, $id_proyectos);
                $stmtSeg->execute();
            }
        }
    }

    //Para la operación de porcentaje de avance
    function valorPorEstado($estado)
    {
        switch ($estado) {
            case 5:
                return 100;
            case 2:
                return 50;
            case 3:
                return 50;
            case 1:
            case 4:
            case 6:
                return 0;
            default:
                return 0;
        }
    }

    //Obtener información de las tareas para calcular el porcentaje
    public function obtenerTareasAvance($id_proyecto)
    {
        $sql = "SELECT taus.id_estadoT FROM tareas_usuarios as taus
        JOIN tareas as tare ON tare.id_tarea = taus.id_tarea
        JOIN tbl_seguimiento as tbse ON tare.id_avances = tbse.id_avances
        WHERE tbse.id_proyectos = ? AND taus.id_estadoT=5"; //5 es aprobado
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalTareas = 11; // número total de tareas
        $suma = 0;

        while ($row = $result->fetch_assoc()) {
            $suma += $this->valorPorEstado($row['id_estadoT']);
        }

        $porcentaje = min(100, ($suma / $totalTareas) * 100);
        $porcentaje = round($porcentaje, 2);
        return $porcentaje;
    }


    //DETALLES DEL PROYECTO
    function obtenerProyecto($id_proyecto)
    {
        $sql = "SELECT 
proy.id_proyectos,
espr.nombre AS estado_proyecto,
tema.nombre_tematica AS tematica,
peri.periodo,

CASE 
WHEN CURDATE() BETWEEN peri.fecha_inicio AND peri.fecha_final THEN 'Activo'
WHEN CURDATE() < peri.fecha_inicio THEN 'Pendiente'
ELSE 'Terminado'
END AS estado_periodo,

proy.titulo,
proy.descripcion,
proy.objetivo,
proy.fecha_inicio,
proy.fecha_fin,
proy.presupuesto,
proy.creado_en,
proy.requisitos,
proy.pre_requisitos,
proy.modalidad,
proy.cantidad_estudiante

FROM proyectos AS proy

JOIN estados_proyectos AS espr 
ON proy.id_estadoP = espr.id_estadoP

JOIN proyectos_subtematica AS proy_sub 
ON proy.id_proyectos = proy_sub.id_proyectos

JOIN subtematica AS subt 
ON proy_sub.id_subtematica = subt.id_subtematica

JOIN tematica AS tema 
ON tema.id_tematica = subt.id_tematica

JOIN periodos AS peri 
ON peri.id_periodos = proy.id_periodos

WHERE proy.id_proyectos = ?
GROUP BY 
proy.id_proyectos,
espr.nombre,
tema.nombre_tematica
ORDER BY proy.id_proyectos
DESC;";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare: " . $this->con->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function obtenerProyectoInvestigador($id_proyecto)
    {
        $sql = "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno, nisn.nombre as nivel_sni, grac.nombre as grado_academico FROM gestion_proyectos.investigadores as inve
JOIN usuarios as usua ON usua.id_usuarios = inve.id_usuarios
JOIN niveles_sni as nisn ON nisn.id_nivel = inve.id_nivel_sni
JOIN grados_academicos as grac ON grac.id_grado = inve.id_grado
JOIN proyectos as proy ON proy.id_investigador = inve.id_usuarios
WHERE proy.id_proyectos = ?";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function obtenerUsuarioArea($id_usuario)
    {
        $sql = "SELECT arco.nombre_area as area_conocimiento, group_concat(subco.nombre_subarea) as subarea  FROM usuarios as us 
JOIN usuarios_subareas as ussu ON ussu.id_usuarios = us.id_usuarios
JOIN subareas_conocimiento as subco ON ussu.id_subarea = subco.id_subarea
JOIN areas_conocimiento as arco ON arco.id_area = subco.id_area
WHERE us.id_usuarios = ?
GROUP BY us.id_usuarios, subco.id_subarea, arco.id_area;";

        $params = [$id_usuario];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }


    function obtenerInvestigadorLinea($id_proyecto)
    {
        $sql = "SELECT liin.nombre as linea FROM gestion_proyectos.investigadores as inve
JOIN investigador_lineas_investigacion as inliin ON inliin.id_usuarios = inve.id_usuarios 
JOIN lineas_investigacion as liin ON liin.id_linea = inliin.id_linea
JOIN proyectos as proy ON proy.id_investigador = inve.id_usuarios
WHERE proy.id_proyectos = ?";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function obtenersubtematicasProyecto($id_proyecto)
    {
        $sql = "SELECT sub.id_subtematica, sub2.nombre_subtematica AS nombre FROM proyectos_subtematica as sub JOIN subtematica as sub2 ON sub.id_subtematica = sub2.id_subtematica WHERE sub.id_proyectos = ? AND sub2.estado = 1";
        $params = [$id_proyecto];
        $types = "i";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    function obtenerProyectoEstudiante($id_proyecto)
    {
        $sql = "SELECT usua.id_usuarios, usua.nombre, usua.apellido_paterno, usua.apellido_materno, carr.nombre_carrera as carrera FROM gestion_proyectos.estudiantes as estu 
JOIN usuarios AS usua ON usua.id_usuarios = estu.id_usuarios
JOIN carreras as carr ON carr.id_carrera = estu.id_carrera
JOIN proyectos_usuarios as prus ON prus.id_usuarios = estu.id_usuarios
JOIN proyectos as proy ON proy.id_proyectos = prus.id_proyectos
WHERE proy.id_proyectos = ?";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerProyectoComentarios($id_proyecto)
    {
        $sql = "SELECT CASE 
        WHEN prco.tipo = 'creacion_rechazada' THEN 'Creación rechazada'
        WHEN prco.tipo = 'cierre_rechazado' THEN 'Cierre rechazada'
        ELSE 'Rechazo'
    END AS tipo, CONCAT(usua.nombre, ' ', usua.apellido_paterno, ' ', usua.apellido_materno) as nombre_completo, prco.comentario, prco.fecha FROM gestion_proyectos.proyectos_comentarios as prco
JOIN proyectos as proy ON proy.id_proyectos = prco.id_proyectos
JOIN usuarios as usua ON usua.id_usuarios = prco.id_usuarios
Where proy.id_proyectos = ? ORDER BY fecha DESC;";

        $params = [$id_proyecto];
        $types  = "i";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    //Vincular las subtematicas elegidas al momento de crear un proyecto
    public function vincularSubtematica($id_proyecto, $id_subtematica)
    {
        $sql = "INSERT INTO proyectos_subtematica (id_proyectos, id_subtematica) VALUES (?, ?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }
        $stmt->bind_param("ii", $id_proyecto, $id_subtematica);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
    }

    public function ActualizarvincularSubtematica($id_proyecto, $id_subtematica)
    {
        $sqleliminar = "DELETE FROM proyectos_subtematica WHERE id_proyectos = ?";
        $stmtE = $this->con->prepare($sqleliminar);
        if (!$stmtE) {
            die("Error en prepare(): " . $this->con->error);
        }
        $stmtE->bind_param("i", $id_proyecto);

        if (!$stmtE) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmtE->execute()) {
            die("Error en execute(): " . $stmtE->error);
        }
        $sql = "INSERT INTO  proyectos_subtematica (id_subtematica, id_proyectos) VALUES (?,?)";
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }
        $stmt->bind_param("ii", $id_subtematica, $id_proyecto);

        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
    }

    //Función para dar de baja a estudiante y agregar el historial. 
    public function bajaEstudiante($id_proyecto, $id_estudiante, $motivo, $usuario)
    {
        $this->con->begin_transaction();

        try {

            // Validar que esté activo
            $check = "SELECT estado FROM proyectos_usuarios 
                  WHERE id_proyectos = ? AND id_usuarios = ?";
            $stmt = $this->con->prepare($check);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();
            $estado = $stmt->get_result()->fetch_assoc()['estado'] ?? null;

            if ($estado !== 'activo') {
                throw new Exception("El estudiante no está activo");
            }

            // Update
            $sql = "UPDATE proyectos_usuarios 
                SET estado = 'baja', fecha_baja = NOW(), motivo_baja = ?, reincorporacion = 0
                WHERE id_proyectos = ? AND id_usuarios = ?";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("sii", $motivo, $id_proyecto, $id_estudiante);
            $stmt->execute();

            // Historial
            $sql = "INSERT INTO historial_proyectos_usuarios 
                (id_proyectos, id_estudiante, accion, motivo, realizado_por)
                VALUES (?, ?, 'baja', ?, ?)";
            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("iisi", $id_proyecto, $id_estudiante, $motivo, $usuario);
            $stmt->execute();

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }
    //Función para reactivar estudiante
    public function reactivarEstudiante($id_proyecto, $id_estudiante, $usuario)
    {
        $this->con->begin_transaction();

        try {

            // Validar estado actual
            $check = "SELECT pu.estado, p.fecha_fin 
                  FROM proyectos_usuarios pu
                  JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
                  WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?";

            $stmt = $this->con->prepare($check);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if (!$data) {
                throw new Exception("Registro no encontrado");
            }

            // Validaciones importantes
            if ($data['estado'] !== 'baja') {
                throw new Exception("Solo se puede reactivar si está en baja");
            }

            if ($data['fecha_fin'] < date('Y-m-d')) {
                throw new Exception("El proyecto está vencido, requiere prórroga");
            }

            // Update
            $sql = "UPDATE proyectos_usuarios 
                SET estado = 'activo', fecha_baja = NULL, motivo_baja = NULL, reincorporacion = 1
                WHERE id_proyectos = ? AND id_usuarios = ?";

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
            $stmt->execute();

            // Historial
            $sql = "INSERT INTO historial_proyectos_usuarios 
                (id_proyectos, id_estudiante, accion, realizado_por)
                VALUES (?, ?, 'reactivado', ?)";

            $stmt = $this->con->prepare($sql);
            $stmt->bind_param("iii", $id_proyecto, $id_estudiante, $usuario);
            $stmt->execute();

            $this->con->commit();
            return ["success" => true];
        } catch (Throwable $e) {
            $this->con->rollback();
            return ["success" => false, "msg" => $e->getMessage()];
        }
    }
    //Para ver los estudiantes en editar proyecto
    public function estudiantes($id_proyecto)
    {
        $sql = "SELECT 
                u.id_usuarios,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                c.nombre_carrera AS carrera,
                pu.estado,

                hpu.motivo -- Motivo de baja

            FROM proyectos_usuarios pu

            JOIN usuarios u 
                ON u.id_usuarios = pu.id_usuarios

            JOIN estudiantes e 
                ON e.id_usuarios = u.id_usuarios

            JOIN carreras c 
                ON e.id_carrera = c.id_carrera

            LEFT JOIN (
                SELECT h1.id_proyectos, h1.id_estudiante, h1.motivo
                FROM historial_proyectos_usuarios h1
                INNER JOIN (
                    SELECT id_proyectos, id_estudiante, MAX(id_historial) AS max_id
                    FROM historial_proyectos_usuarios
                    WHERE accion = 'baja'
                    GROUP BY id_proyectos, id_estudiante
                ) h2 
                ON h1.id_historial = h2.max_id
            ) hpu 
                ON hpu.id_proyectos = pu.id_proyectos 
                AND hpu.id_estudiante = pu.id_usuarios

            WHERE pu.id_proyectos = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function obtenerEstudianteProyecto($id_proyecto, $id_estudiante)
    {
        $sql = "SELECT u.nombre, u.apellido_paterno, u.apellido_materno, p.titulo
            FROM usuarios u
            JOIN proyectos_usuarios pu ON pu.id_usuarios = u.id_usuarios
            JOIN proyectos p ON p.id_proyectos = pu.id_proyectos
            WHERE pu.id_proyectos = ? AND pu.id_usuarios = ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $id_proyecto, $id_estudiante);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    //Historial de proyectos con estudiantes
    public function lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina = 1, $por_pagina = 5)
    {
        $pagina = max(1, (int)$pagina);
        $desde = ($pagina - 1) * $por_pagina;

        // TOTAL
        $sqlTotal = "SELECT COUNT(*) as total
                 FROM historial_proyectos_usuarios
                 WHERE id_proyectos = ? AND id_estudiante = ?";

        $stmt = $this->con->prepare($sqlTotal);

        $stmt->bind_param("ii", $id_proyecto, $id_usuario);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $total_paginas = ceil($total / $por_pagina);

        // DATOS
        $sql = "SELECT 
                h.accion AS tipo_evento,
                h.motivo AS descripcion,
                h.fecha,
                u.nombre AS usuario
            FROM historial_proyectos_usuarios h
            LEFT JOIN usuarios u 
                ON h.id_estudiante = u.id_usuarios
            WHERE h.id_proyectos = ? 
              AND h.id_estudiante = ?
            ORDER BY h.fecha DESC
            LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("iiii", $id_proyecto, $id_usuario, $desde, $por_pagina);
        $stmt->execute();

        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // AGRUPADO (tipo timeline simple)
        $agrupado = [];
        foreach ($historial as $item) {
            $fecha = date("d/m/Y", strtotime($item['fecha']));
            $agrupado[$fecha][] = $item;
        }

        return [
            "datos" => $agrupado,
            "paginacion" => [
                "total" => $total,
                "por_pagina" => $por_pagina,
                "pagina" => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }
}
