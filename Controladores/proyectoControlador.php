<?php
// Controladores/proyectoControlador.php

require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '../../publico/incluido/_iconos.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class ProyectoControlador extends BaseControlador
{

    // Solo esto es específico de Proyectos
    private function rolValido(string $rol): bool
    {
        return in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor'], true);
    }

    // 
    // MÉTODO BASE
    // 

    private function obtenerDatos(int $id, string $rol, ?string $buscar, ?int $filtro = null, string $tipo = 'filtro'): array
    {
        global $conn;
        try {
            if (!$this->rolValido($rol)) return [];

            $modelo = new Proyectos($conn);
            $modelo->actualizarProyectosVencidos();
            $modelo->actualizarEstadoEstudiantesVencidos();


            // tipo === 'tabla'
            $resultado = $modelo->obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar);
            error_log("resultado tipo: " . gettype($resultado) . " | valor: " . var_export($resultado, true));

            $decoded = is_string($resultado) ? json_decode($resultado, true) : $resultado;
            return is_array($decoded) ? $decoded : [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // 
    // ACCIONES DE TABLA (index.php de Proyectos)
    // 

    public function index(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 0, 'tabla');
    }

    public function Total(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 0, 'tabla');
    }

    public function Cierre(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 1, 'tabla');
    }

    public function Activos(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 2, 'tabla');
    }

    public function PorAprobar(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 3, 'tabla');
    }

    public function Rechazados(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 4, 'tabla');
    }

    public function PorCerrar(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 5, 'tabla');
    }

    public function Vencido(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 6, 'tabla');
    }

    public function Cierrerechazado(int $id, string $rol, ?string $buscar = null): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 7, 'tabla');
    }


    // 
    // CONVERSIÓN action → id_estadoP
    // 

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Total'                         => 0,
            'Cierre'                        => 1,
            'Activos', 'Activo'             => 2,
            'PorAprobar'                    => 3,
            'Rechazados'                    => 4,
            'PorCerrar'                     => 5,
            'Vencido'                       => 6,
            'Cierrerechazado',
            'CierreRechazado'               => 7,
            default                         => 0,
        };
    }


    // 
    // ENCABEZADOS DE TABLA
    // 

    public function encabezadosProyectos(string $rol): array
    {
        if ($rol === 'estudiante') {
            return [
                'ID',
                'Título',
                'Inicio',
                'Fin',
                'Estado Proyecto',
                'Estado Estudiante',
                'Documentación',
                'Período',
                'Pendientes',
                'Acciones',
            ];
        }
        return ['ID', 'Título', 'Inicio', 'Fin', 'Estado', 'Período', 'Pendientes', 'Acciones'];
    }


    // 
    // OPCIONES DE FILTRO (select de la tabla de proyectos)
    // 

    public function opcionesProyectos(string $rol): array
    {

        $base = [
            'Total'   => "Total",
            'Activos' => "Activos",
        ];

        if ($rol === 'estudiante') {

            return $base + [
                'Cierre'    => "Cierre",
                'PorCerrar' => "Por Cerrar",
                'Vencido'   => "Vencidos",
            ];
        }

        if (in_array($rol, ['investigador', 'profesor'], true)) {

            return $base + [
                'Rechazados' => "Rechazados",
                'Cierre'     => "Cierre",
                'PorCerrar'  => "Por Cerrar",
                'Vencido'    => "Vencidos",
            ];
        }


        if ($rol === 'supervisor') {
            return $base + [
                'PorCerrar' => "Por Cerrar",
                'Cierre'    => "Cierre",
                'Vencido'   => "Vencidos",
            ];
        }

        return [];
    }


    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstado(string $estado): string
    {
        return match ($estado) {
            'Cierre rechazado', 'Rechazado', 'Terminado'                                    => 'danger',
            'Por cerrar', 'Por aprobar', 'Pendiente', 'en_proceso', 'en_correccion'         => 'warning',
            'Vencido'                                                                        => 'dark',
            'Activo', 'carta_subida', 'liberado_supervisor'                                 => 'success',
            'Cierre'                                                                         => 'secondary',
            default                                                                          => 'info',
        };
    }


    // 
    // BOTONES INDIVIDUALES
    // 

    public function obtenerbotones(string $tipo, int $id_proyecto, ?int $id_usuario = null): string
    {
        include __DIR__ . '../../publico/incluido/_iconos.php';

        return match ($tipo) {

            'Detalles' => Botones::botonIcono(
                'detalles.php?id_proyectos=' . $id_proyecto,
                'primary',
                $iconos['tabla']['ver'],
                'Ver detalles del proyecto'
            ),

            'Tareas' => Botones::botonIcono(
                '../Tareas/index.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['tareas_list'],
                'Tareas'
            ),

            'Ver Tareas Alumnos' => Botones::botonIcono(
                '../Tareas/tareas_estudiante.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['tareas_list'],
                'Ver Tareas'
            ),

            'Solicitar cerrar' => Botones::botonIcono(
                'index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Solicitar cerrar proyecto'
            ),

            'Volver a enviar cierre' => Botones::botonIcono(
                'index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar',
                'warning',
                $iconos['tabla']['volver_enviar'],
                'Volver a enviar cierre'
            ),

            'Volver a enviar proyecto' => Botones::botonIcono(
                'index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorAprobar',
                'warning',
                $iconos['tabla']['volver_enviar'],
                'Volver a enviar proyecto'
            ),

            'Editar' => Botones::botonIcono(
                'editar.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['editar'],
                'Editar proyecto'
            ),

            'Comentarios' => Botones::botonIcono(
                'ver_comentarios.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['comentarios'],
                'Ver comentarios'
            ),

            default => '',
        };
    }



    // 
    // BOTONES DE ACCIÓN — tabla de proyectos (Proyectos/index.php)
    // 

    public function botonesAccion(
        int $id,
        string $rol,
        ?string $estado = null,
        ?int $extra = null,
        int $estado_completados_estudiantes = 0,
        ?string $estado_estudiante = 'activo',
        bool $estado_editar = false
    ): string {
        $solicitar = ($estado_completados_estudiantes == 1) ? 'Solicitar cerrar' : '';
        $editar    = $estado_editar ? 'Editar' : '';
        // Recibe el valor de la vista donde calcula a partir del rango de fecha del que el
        // investigador puede registrar su proyecto y mandar correcciones.
        $volver_enviar_rechazo = $estado_editar ? 'Volver a enviar proyecto' : '';

        // Estudiante dado de baja: solo detalles
        if ($rol === 'estudiante' && strtolower($estado_estudiante ?? '') === 'baja') {
            return $this->obtenerbotones('Detalles', $id);
        }

        $acciones = [
            'estudiante' => [
                'Activo'     => ['Detalles', 'Ver Tareas Alumnos'],
                'Por cerrar' => ['Detalles', 'Ver Tareas Alumnos'],
                'Vencido'    => ['Detalles', 'Ver Tareas Alumnos'],
                'Cierre'     => ['Detalles', 'Ver Tareas Alumnos'],
            ],
            'investigador' => [
                'Activo'           => ['Detalles', 'Tareas', 'Comentarios', $solicitar],
                'Por cerrar'       => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', $editar, 'Tareas', 'Comentarios'],
                'Rechazado'        => [$volver_enviar_rechazo, 'Detalles', $editar, 'Comentarios'],
                'Vencido'          => ['Detalles', 'Tareas', 'Comentarios'],
            ],
            'profesor' => [
                'Activo'           => ['Detalles', 'Tareas', 'Comentarios', $solicitar],
                'Por cerrar'       => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Volver a enviar cierre', 'Detalles', $editar, 'Tareas', 'Comentarios'],
                'Rechazado'        => [$volver_enviar_rechazo, 'Detalles', $editar, 'Comentarios'],
                'Vencido'          => ['Detalles', 'Tareas', 'Comentarios'],
            ],
            'supervisor' => [
                'Activo'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Por cerrar'       => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre rechazado' => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Vencido'          => ['Detalles', 'Tareas', 'Comentarios'],
            ],
        ];

        if (!isset($acciones[$rol][$estado])) return '';

        $botones = '';
        foreach ($acciones[$rol][$estado] as $accion) {
            if (empty($accion)) continue;
            $botones .= ($accion === 'Ver Tareas Alumnos')
                ? $this->obtenerbotones($accion, $id, $extra)
                : $this->obtenerbotones($accion, $id);
        }
        return $botones;
    }


    // 
    // BOTONES EDITAR ESTUDIANTE (Proyectos/editar.php)
    // 

    public function botonesAccionEditarEstudiante(
        int $id_estudiante,
        string $rol,
        string $estado,
        int $id_proyecto,
        string $estado_proyecto
    ): string {
        if (!in_array($rol, ['investigador', 'profesor'], true)) return '';

        if (strtolower($estado_proyecto) === 'vencido') {
            return '<span class="text-muted">Sin acciones</span>';
        }

        $boton = '';
        if ($estado === 'activo') {
            $boton .= $this->obtenerbotones('Dar de baja', $id_proyecto, $id_estudiante);
        }
        if ($estado === 'baja') {
            $boton .= $this->obtenerbotones('Reactivar', $id_proyecto, $id_estudiante);
        }
        return $boton;
    }


    // 
    // PORCENTAJE DE AVANCE
    // 

    public function obtenerPorcentajeAvance(int $id_proyecto): float
    {
        global $conn;
        try {
            $valor = (new Proyectos($conn))->obtenerTareasAvance($id_proyecto);
            return ($valor === null || $valor === false) ? 0.0 : (float)$valor;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0.0;
        }
    }


    // 
    // CATÁLOGOS
    // 

    public function tematica(): array
    {
        global $conn;
        try {
            return (new Proyectos($conn))->tematica() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function subtematicas(int $id): array
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenersubtematica($id) ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function obtenerperiodo(): array
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerperiodo() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function obtenerInstituto(): array
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerinstituto() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    /** Periodo activo para registrar proyecto (Solicitudes investigador → Supervisor). */
    public function periodoactual()
    {
        global $conn;
        try {
            return (new Proyectos($conn))->periodoactual();
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar');
        }
    }


    // 
    // CRUD DE PROYECTOS
    // 

    /**
     * Registra un proyecto nuevo con estado "Por aprobar".
     * Acción de formulario → redirige con msg al index.
     */
    public function registrarProyecto(array $datos, int $id, string $rol): void
    {
        global $conn;

        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            // 
            // VALIDAR PERIODO DE CREACIÓN
            // 
            $periodo = $this->obtenerperiodo();

            if (empty($periodo) || empty($periodo[0])) {
                $this->redirigir('periodo_vencido');
            }

            $periodo = $periodo[0];

            $hoy = date('Y-m-d');

            if (
                $hoy < $periodo['fecha_inicio_proyectos'] ||
                $hoy > $periodo['fecha_fin_proyectos']
            ) {
                $this->redirigir('periodo_vencido');
            }

            // 
            // VALIDAR DATOS OBLIGATORIOS
            // 
            $subtematicas = $datos['subtematicas'] ?? [];

            if (
                empty($subtematicas) ||
                empty(trim($datos['NombreProyecto'] ?? ''))
            ) {
                $this->redirigir('error_crear');
            }

            // 
            // VALIDAR FECHAS DEL PROYECTO
            // 
            $fechaInicio = $datos['FechaInicio'] ?? '';
            $fechaFinal  = $datos['FechaFinal'] ?? '';

            if (empty($fechaInicio) ||  empty($fechaFinal)) {
                $this->redirigir('error_crear');
            }

            $inicio = new DateTime($fechaInicio);
            $fin    = new DateTime($fechaFinal);

            if ($fin < $inicio) {
                $this->redirigir('error_crear');
            }

            // Máximo 1 año
            $limite = clone $inicio;
            $limite->modify('+1 year');

            if ($fin > $limite) {
                $this->redirigir('error_crear');
            }

            //Evitar que capturen antes de la fecha de inicio
            if ($inicio < new DateTime($periodo['fecha_inicio'])) {
                $this->redirigir('error_crear');
            }

            //Evitar que capturen después de un año de la fecha final
            $limiteMaximo = new DateTime($periodo['fecha_final']);
            $limiteMaximo->modify('+1 year');

            if ($fin > $limiteMaximo) {
                $this->redirigir('error_crear');
            }

            // 
            // OBTENER INSTITUTO
            // 
            $instituto = $this->obtenerInstituto()[0] ?? null;

            if (!$instituto) {
                $this->redirigir('error_sin_registro');
            }

            // 
            // REGISTRAR PROYECTO
            // 
            $conn->begin_transaction();

            $modelo = new Proyectos($conn);

            $modelo->actualizarProyectosVencidos();

            $proyectoId = $modelo->registrarProyecto(
                $id,
                3, // Por aprobar
                (int)$instituto['id_instituto'],
                (int)$periodo['id_periodos'],
                $datos['NombreProyecto'],
                $datos['Descripcion'],
                $datos['Objetivos'],
                $fechaInicio,
                $fechaFinal,
                $datos['Presupuesto'],
                $datos['Requisitos'],
                $datos['Pre_requisitos'],
                $datos['Modalidad'],
                (int)$datos['AlumnosCantidad']
            );

            foreach ($subtematicas as $idSub) {
                $modelo->vincularSubtematica(
                    $proyectoId,
                    (int)$idSub
                );
            }

            $conn->commit();

            $this->redirigir('exito_crear');
        } catch (Exception $e) {

            if ($conn->errno === 0) {
                try {
                    $conn->rollback();
                } catch (Exception $ex) {
                }
            }

            error_log('ProyectoControlador::registrarProyecto() - ' . $e->getMessage());

            $mensaje = $e->getMessage();

            if (in_array($mensaje, [
                'accion_no_permitida',
                'periodo_vencido',
                'error_sin_registro'
            ], true)) {

                $this->redirigir($mensaje);
            }

            $this->redirigir('error_crear');
        }
    }

    /**
     * Edita un proyecto existente.
     * Acción de formulario → redirige con msg al index.
     */
    public function editarProyecto(array $datos, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $id_proyecto  = (int)($datos['id_proyectos'] ?? 0);
            $subtematicas = $datos['subtematicas'] ?? [];

            if (!$id_proyecto || empty($subtematicas)) {
                throw new Exception("Datos incompletos");
            }

            $modelo = new Proyectos($conn);
            $modelo->editarProyecto(
                $id_proyecto,
                $id_usuario,
                $datos['NombreProyecto'],
                $datos['Descripcion'],
                $datos['Objetivos'],
                $datos['FechaInicio'],
                $datos['FechaFinal'],
                $datos['Presupuesto'],
                $datos['Requisitos'],
                $datos['Pre_requisitos'],
                $datos['Modalidad'],
                (int)$datos['AlumnosCantidad']
            );

            foreach ($subtematicas as $idSub) {
                $modelo->ActualizarvincularSubtematica($id_proyecto, (int)$idSub);
            }

            $this->redirigir('exito_editar', 'index.php', "&id_proyectos={$id_proyecto}");
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_editar';
            $this->redirigir($msg);
        }
    }


    // 
    // ACTUALIZAR ESTADO
    // 

    /**
     * Cambia el estado de un proyecto vía enlace GET
     * (supervisor / investigador / profesor).
     *
     * No es una llamada AJAX, por lo que responde con redirect + msg.
     * En caso de error de permisos se usa 'accion_no_permitida' para que
     * el mapa de alertas de la vista muestre el mensaje adecuado.
     */
    public function actualizarestado(int $id_proyecto, string $rol, string $tipo): void
    {
        global $conn;
        try {

            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor', 'investigador', 'profesor']);

            $modelo     = new Proyectos($conn);
            $modelo->actualizarProyectosVencidos();

            $estado     = $this->numerofiltro($tipo);
            $porcentaje = $this->obtenerPorcentajeAvance($id_proyecto);

            $modelo->actualizarestado($id_proyecto, $estado, $porcentaje);

            $this->redirigir('exito_estado');
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_estado';
            // El error de estado conviene mostrarlo en la página de detalles
            // para que el usuario no pierda el contexto del proyecto.
            $this->redirigir($msg, 'detalles.php', "&id_proyectos={$id_proyecto}");
        }
    }


    // 
    // DATOS DE DETALLE
    // 

    public function datosproyecto(int $id_proyecto): ?array
    {
        $id_usuario = $_SESSION['id_usuario'];
        $rol        = strtolower($_SESSION['rol'] ?? '');
        global $conn;

        $resultado = (new Proyectos($conn))->obtenerProyecto($id_proyecto, $id_usuario, $rol);
        if (!$resultado) {
            $this->redirigir('sin_permiso');
        }
        return $resultado;
    }

    public function datosinvestigador(int $id_proyecto): array
    {
        $id_usuario = $_SESSION['id_usuario'];
        $rol        = strtolower($_SESSION['rol'] ?? '');
        global $conn;

        $modelo       = new Proyectos($conn);
        $investigador = $modelo->obtenerProyectoInvestigador($id_proyecto, $id_usuario, $rol);

        if (!$investigador) {
            $this->redirigir('sin_permiso');
        }

        return [
            'investigador' => $investigador,
            'area'         => $modelo->obtenerUsuarioArea($investigador['id_usuarios']),
            'lineas'       => $modelo->obtenerInvestigadorLinea($id_proyecto),
        ];
    }

    public function subtematicasProyecto(int $id_proyecto)
    {
        $id_usuario = $_SESSION['id_usuario'];
        $rol        = strtolower($_SESSION['rol'] ?? '');
        global $conn;

        try {
            $modelo    = new Proyectos($conn);
            $resultado = $modelo->obtenersubtematicasProyecto($id_proyecto, $id_usuario, $rol);

            if (!$resultado) {
                $this->redirigir('sin_permiso');
            }
            return $resultado;
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar', 'detalles.php', "&id_proyectos={$id_proyecto}");
        }
    }

    public function datosestudiantes(int $id_proyecto): array
    {
        global $conn;
        $modelo     = new Proyectos($conn);
        $estudiante = $modelo->obtenerProyectoEstudiante($id_proyecto);
        $id_usuario = $estudiante['id_usuario'] ?? null;
        return [
            'estudiante' => $estudiante,
            'area'       => $modelo->obtenerUsuarioArea($id_usuario),
        ];
    }

    public function comentarios(int $id_proyecto): array
    {
        global $conn;
        return (new Proyectos($conn))->obtenerProyectoComentarios($id_proyecto);
    }

    public function estudiantes(int $id_proyecto): array
    {
        global $conn;
        return (new Proyectos($conn))->estudiantes($id_proyecto);
    }

    public function obtenerEstudianteProyecto(int $id_proyecto, int $id_estudiante): ?array
    {
        global $conn;
        return (new Proyectos($conn))->obtenerEstudianteProyecto($id_proyecto, $id_estudiante);
    }

    public function historial_estudiante_proyecto(int $id_proyecto, int $id_usuario, int $id)
    {
        global $conn;
        try {
            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            return (new Proyectos($conn))->lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina, 5, $id);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar', 'editar.php');
        }
    }


    // 
    // ACCIONES DE ESTUDIANTE (baja / reactivar) — Proyectos/detalles.php
    //
    // Esta acción se invoca vía fetch() / AJAX desde la vista, por lo que
    // responde con JSON en lugar de redirect, igual que SeguimientoControlador.
    // 

    /**
     * Da de baja o reactiva a un estudiante dentro de un proyecto.
     *
     * Se invoca desde un <form method="POST"> en detalles.php,
     * por lo que responde siempre con redirect + ?msg=, igual que
     * las demás acciones POST del controlador.
     *
     * Espera POST con:
     *   action        => 'baja' | 'reactivar'
     *   id_proyecto   => int
     *   id_estudiante => int
     *   motivo        => string (solo para 'baja')

     */
    public function accionEstudiante(array $datos): void
    {
        global $conn;

        $action        = $datos['action']       ?? '';
        $id_proyecto   = (int)($datos['id_proyecto']   ?? 0);
        $id_estudiante = (int)($datos['id_estudiante'] ?? 0);
        $motivo        = $datos['motivo']        ?? null;

        if (!$id_proyecto || !$id_estudiante || !in_array($action, ['baja', 'reactivar'], true)) {
            $this->redirigir('error_operacion', 'detalles.php', "&id_proyectos={$id_proyecto}");
        }

        try {
            $modelo = new Proyectos($conn);

            match ($action) {
                'baja'      => $modelo->bajaEstudiante($id_proyecto, $id_estudiante, $motivo, (int)$_SESSION['id_usuario']),
                'reactivar' => $modelo->reactivarEstudiante($id_proyecto, $id_estudiante, (int)$_SESSION['id_usuario']),
            };

            $this->redirigir('exito_operacion', 'detalles.php', "&id_proyectos={$id_proyecto}");
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_operacion', 'detalles.php', "&id_proyectos={$id_proyecto}");
        }
    }
}
