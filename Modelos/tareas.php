<?php
require_once __DIR__ . '/../publico/config/conexion.php';

class Tarea
{

    private $con;

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    //ACTUALIZAR A VENCIDO LOS PROYECTOS
    public function actualizarTareasVencidos()
    {
        $hoy = date("Y-m-d");

        $sql = "UPDATE tareas_usuarios 
            SET id_estadoT = 6
            WHERE id_estadoT IN (1, 2, 3)
              AND fecha_entrega < ?";

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
    }

    //DATOS PRINCIPAL
    public function obtenerTareas($id_proyecto, $id_usuario, $rol)
    {
        switch ($rol) {
            case 'estudiante':
                $sql = "SELECT 
    s.id_avances,
    t.id_tarea,
    tt.descripcion_tipo AS tipo,
    
    -- Datos de la tarea (base)
    t.archivo_guia,
    t.archivo_guia_nombre,
    t.archivo_guia_tipo,
    t.fecha_activacion,

    -- Datos de la entrega del estudiante
    taus.fecha_entrega,
    
    es.nombre AS estado

FROM tbl_seguimiento s
INNER JOIN tareas t ON s.id_avances = t.id_avances
LEFT JOIN tareas_usuarios taus 
    ON taus.id_tarea = t.id_tarea
    AND taus.id_usuario = ?
INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo
LEFT JOIN estados_tarea es ON taus.id_estadoT = es.id_estadoT

WHERE s.id_proyectos = ?
ORDER BY t.id_tarea ASC";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("ii", $id_proyecto, $id_usuario);
                break;
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
    s.id_avances,
    t.id_tarea,
    tt.descripcion_tipo AS tipo,
    t.archivo_guia,
    t.archivo_guia_nombre,
    t.fecha_activacion,

    -- Conteo de entregas por tarea
    (
        SELECT COUNT(*)
        FROM tareas_usuarios tu
        WHERE tu.id_tarea = t.id_tarea
          AND tu.archivo IS NOT NULL
    ) AS entregado,

    -- Total de estudiantes del proyecto
    (
        SELECT COUNT(*)
        FROM proyectos_usuarios pu
        WHERE pu.id_proyectos = s.id_proyectos
    ) AS total_Estudiantes

FROM tbl_seguimiento s
INNER JOIN tareas t ON s.id_avances = t.id_avances
INNER JOIN tipo_tarea tt ON t.id_tipotarea = tt.id_tareatipo

WHERE s.id_proyectos = ?
ORDER BY t.id_tarea ASC";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_proyecto);
                break;
            default:
                break;
        }
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //DATOS LISTA PROYECTO
    public function obtenerTareasLista($id_tarea, $rol)
    {
        switch ($rol) {
            case 'profesor':
            case 'investigador':
            case 'supervisor':
                $sql = "SELECT 
                tu.id_asignacion,
                
                -- Tipo de tarea (nombre)
                tita.descripcion_tipo AS tipo,

                -- Datos del alumno
                u.id_usuarios,
                CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS estudiante,

                -- Estado actual de la entrega
                et.nombre AS estados_tarea,

                -- Fechas claves
                tu.fecha_revision,
                tu.fecha_correccion,
                tu.fecha_aprobacion,

                -- Datos del archivo entregado
                tu.archivo,
                tu.archivo_nombre,
                tu.archivo_tipo,

                -- Control: la tarea a la que pertenece
                tu.id_tarea

            FROM tareas_usuarios tu
            INNER JOIN usuarios u 
                ON tu.id_usuario = u.id_usuarios
            INNER JOIN estados_tarea et 
                ON tu.id_estadoT = et.id_estadoT
            INNER JOIN tareas ta 
                ON ta.id_tarea = tu.id_tarea
            INNER JOIN tipo_tarea tita
                ON ta.id_tipotarea = tita.id_tareatipo

            WHERE tu.id_tarea = ?
            ORDER BY estudiante ASC
";
                $stmt = $this->con->prepare($sql);
                $stmt->bind_param("i", $id_tarea);
                break;
            default:
                break;
        }
        $stmt->execute();


        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    //ACTUALIZAR TAREA
   public function editarTareaGeneral($id_tareas, $descripcion, $instrucciones, $fecha_entrega, $archivo_guia, $archivo_nombre, $archivo_tipo)
{
    // Normalizar fecha
    $fecha_entrega = ($fecha_entrega === "" ? null : $fecha_entrega);

    // 1. Obtener fecha anterior para saber si antes estaba NULL
    $sqlOld = "SELECT fecha_entrega, id_avances FROM tareas WHERE id_tarea = ?";
    $stmtOld = $this->con->prepare($sqlOld);
    $stmtOld->bind_param("i", $id_tareas);
    $stmtOld->execute();
    $old = $stmtOld->get_result()->fetch_assoc();

    $fecha_anterior = $old['fecha_entrega'];
    $id_avances     = $old['id_avances'];

    // 2. ACTUALIZAR TAREA
    $sqlTarea = "
        UPDATE tareas
        SET descripcion = ?, 
            instrucciones = ?, 
            fecha_entrega = ?,
            archivo_guia  = ?,
            archivo_nombre = ?,
            archivo_tipo   = ?
        WHERE id_tarea = ?
    ";

    $stmt = $this->con->prepare($sqlTarea);
    $stmt->bind_param(
        "ssssssi",
        $descripcion,
        $instrucciones,
        $fecha_entrega,
        $archivo_guia,
        $archivo_nombre,
        $archivo_tipo,
        $id_tareas
    );

    $stmt->execute();

    // 3. SI ANTES LA FECHA ERA NULL Y AHORA NO → crear registros en tareas_usuarios
    if ($fecha_anterior === null && $fecha_entrega !== null) {

        // Obtener proyecto desde id_avances
        $sqlProyecto = "
            SELECT id_proyectos 
            FROM tbl_seguimiento 
            WHERE id_avances = ?
        ";
        $stmtProyecto = $this->con->prepare($sqlProyecto);
        $stmtProyecto->bind_param("i", $id_avances);
        $stmtProyecto->execute();
        $proy = $stmtProyecto->get_result()->fetch_assoc();
        $id_proyecto = $proy['id_proyectos'];

        // Obtener alumnos del proyecto
        $sqlEstudiante = "
            SELECT id_usuarios
            FROM proyectos_usuarios as prus
            JOIN estudiantes as estu ON prus.id_usuarios = estu.id_usuario
            WHERE id_proyectos = ?
        ";
        $stmtAlumnos = $this->con->prepare($sqlEstudiante);
        $stmtAlumnos->bind_param("i", $id_proyecto);
        $stmtAlumnos->execute();
        $alumnos = $stmtAlumnos->get_result();

        // Insertar en tareas_usuarios
        $sqlInsertTU = "
            INSERT INTO tareas_usuarios (id_tarea, id_usuario, id_estadoT)
            VALUES (?, ?, 1)  -- estado 1 = pendiente
        ";
        $stmtInsert = $this->con->prepare($sqlInsertTU);

        while ($al = $alumnos->fetch_assoc()) {
            $stmtInsert->bind_param("ii", $id_tareas, $al['id_usuarios']);
            $stmtInsert->execute();
        }
    }

    header("Location: editar.php?msg=ok&id_tarea=" . $id_tareas);
    exit();
}

    //Estudiante
    public function editarTareaEstudiante($id_asignacion, $id_tarea, $id_estudiante, $contenidoJSON, $archivo = null)
    {
        $sql = "UPDATE tareas_usuarios 
            SET contenido = ?, 
                fecha_entrega = NOW(),
                id_estadoT = 2, /* 2 = Revisar */
                archivo = COALESCE(?, archivo),
                archivo_nombre = COALESCE(?, archivo_nombre),
                archivo_tipo = COALESCE(?, archivo_tipo)
            WHERE id_asignacion = ?
              AND id_tarea = ?
              AND id_estudiante = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $archivo_blob   = $archivo['data'] ?? null;
        $archivo_nombre = $archivo['name'] ?? null;
        $archivo_tipo   = $archivo['type'] ?? null;

        $stmt->bind_param(
            "bbssiii",
            $contenidoJSON,
            $archivo_blob,
            $archivo_nombre,
            $archivo_tipo,
            $id_asignacion,
            $id_tarea,
            $id_estudiante
        );

        // obligatorios para los parámetros tipo "b"
        if ($contenidoJSON !== null) {
            $stmt->send_long_data(0, $contenidoJSON);
        }

        if ($archivo_blob !== null) {
            $stmt->send_long_data(1, $archivo_blob);
        }

        return $stmt->execute();
    }
    //Investigador
    public function editarTareaRevisar($id_tarea, $id_estudiante, $comentarios, $nuevoEstado)
    {
        $sql = "UPDATE tareas_usuarios
            SET comentarios = ?,
                id_estadoT = ?,
                fecha_revision = NOW()
            WHERE id_tarea = ?
              AND id_estudiante = ?";

        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            die("Error al preparar: " . $this->con->error);
        }

        $stmt->bind_param("siii", $comentarios, $nuevoEstado, $id_tarea, $id_estudiante);

        $stmt->execute();
        header("Location: editar.php?msg=mensaje");
        exit();
    }


    public function actualizarestado($id_asignacion, $numeroEstado)
    {
        if ($numeroEstado == 1) { //Pendiente
            $sql1 = "";
            $sql2 = "UPDATE tareas_usuarios SET id_estadoT = ? WHERE id_asignacion = ?";
        } else if ($numeroEstado == 2) { //Revisar
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_revision = CURDATE() WHERE id_asignacion = ?";
        } else if ($numeroEstado == 3) { //Corregir
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_correccion= CURDATE() WHERE id_asignacion = ?";
        } else if ($numeroEstado == 5) { //Aprobar
            $sql = "UPDATE tareas_usuarios SET id_estadoT = ?, fecha_aprobacion= CURDATE() WHERE id_asignacion = ?";
        }

        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("ii", $numeroEstado, $id_asignacion);
        if (!$stmt) {
            die("Error en prepare(): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            die("Error en execute(): " . $stmt->error);
        }
        header("Location: lista_tareas.php?msg=mensaje");
        exit;
    }

    // DETALLES DE LA TAREA (Estudiante)
    function obtenerTareaAlumno($id_asignacion)
    {
        $sql = "SELECT 
            a.id_tarea,
            a.archivo,
            a.archivo_nombre,
            a.archivo_tipo,

            t.descripcion,
            t.instrucciones,
            
            tt.descripcion_tipo AS tipo_tarea,

            a.contenido,
            a.comentarios
        FROM tbl_seguimiento s
        INNER JOIN tareas t          ON t.id_tarea = s.id_tarea
        INNER JOIN tipo_tarea tt     ON tt.id_tareatipo = t.id_tipotarea
        INNER JOIN tareas_usuarios a 
                                     ON a.id_tarea = s.id_tarea 
                                     AND a.id_asignacion = ?     LIMIT 1";


        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            die("Error al preparar consulta: " . $this->con->error);
        }

        $stmt->bind_param("i", $id_asignacion);
        $stmt->execute();

        $tarea = $stmt->get_result()->fetch_assoc();

        // Convertir a array
        $tarea = json_decode(json_encode($tarea), true);

        // Si contenido es null → inicializar
        if (empty($tarea['contenido'])) {
            $tarea['contenido'] = [];
        }

        // Si comentarios es null → inicializar
        if (empty($tarea['comentarios'])) {
            $tarea['comentarios'] = "";
        }

        // Empaquetar JSON
        return json_encode($tarea);
    }

    //Obtener información de tarea con seguimiento para modificar los datos
    function obtenerTareaGeneral($id_tarea)
    {
        // 1) OBTENER TAREA
        $sqlTarea = "SELECT 
                    tare.id_tarea,
                    tita.descripcion_tipo AS tipo,
                    tare.descripcion,
                    tare.instrucciones,
                    tare.fecha_entrega,
                    tare.archivo_guia,
                    tare.archivo_nombre,
                    tare.archivo_tipo
                 FROM tareas AS tare
                 JOIN tipo_tarea AS tita ON tare.id_tipotarea = tita.id_tareatipo
                 WHERE tare.id_tarea = ?";

        $stmt1 = $this->con->prepare($sqlTarea);
        $stmt1->bind_param("i", $id_tarea);
        $stmt1->execute();
        $tarea = $stmt1->get_result()->fetch_assoc();

        // Empaquetar JSON
        return $tarea;
    }
}
