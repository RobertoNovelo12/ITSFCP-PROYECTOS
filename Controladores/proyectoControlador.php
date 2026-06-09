<?php
// Controladores/proyectoControlador.php

require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class ProyectoControlador extends BaseControlador
{

    // 
    // VALIDACIÓN DE ROL
    // 

    private function rolValido(string $rol): bool
    {
        return in_array($rol, ['investigador', 'estudiante', 'supervisor', 'profesor'], true);
    }


    /**
     * MÉTODO BASE
     * Centraliza la obtención de datos para todos los roles.
     * Se añadió el parámetro $estado para poder filtrar por
     * estado del estudiante dentro de proyectos_usuarios
     * (ej. 'concluido').
     */
    private function obtenerDatos(
        int     $id,
        string  $rol,
        ?string $buscar,
        ?int    $filtro    = null,
        string  $tipo      = 'filtro',
        int     $id_periodo = 0,
        ?string $estado    = null   // <-- NUEVO parámetro
    ): array {
        global $conn;
        try {
            if (!$this->rolValido($rol)) return [];

            $modelo = new Proyectos($conn);
            $modelo->actualizarProyectosVencidos();
            $modelo->actualizarEstadoEstudiantesVencidos();

            // Se pasa $estado al modelo
            $resultado = $modelo->obtenerProyectosTablaFiltro(
                $id,
                $filtro,
                $rol,
                $buscar,
                $id_periodo,
                $estado
            );
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

    public function index(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 0, 'tabla', $id_periodo);
    }

    public function Total(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 0, 'tabla', $id_periodo);
    }

    public function Cierre(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 1, 'tabla', $id_periodo);
    }

    public function Activos(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 2, 'tabla', $id_periodo);
    }


    /**
     * Devuelve los proyectos donde el estudiante/usuario
     * tiene estado = 'concluido' en proyectos_usuarios.
     * $filtro = 2 acota además el estado del proyecto (Activo).
     * Puedes quitar $filtro si quieres ver concluidos sin importar
     * el estado del proyecto.
     */
    public function Concluidos(
        int     $id,
        string  $rol,
        ?string $buscar     = null,
        int     $id_periodo = 0
    ): array {
        // $filtro = null  → muestra proyectos de cualquier estado
        // $estado = 'concluido' → filtra por estado del usuario en proyectos_usuarios
        return $this->obtenerDatos($id, $rol, $buscar, null, 'tabla', $id_periodo, 'concluido');
    }


    // PorAprobar ya no existe en Módulo 1 para investigador,
    // pero se conserva el método por si el supervisor o futuras rutas lo necesitan.
    public function PorAprobar(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        // Investigador/profesor no deben ver este filtro en Módulo 1.
        if (in_array($rol, ['investigador', 'profesor'], true)) {
            return ['proyectos' => [], 'paginacion' => ['total' => 0, 'por_pagina' => 6, 'pagina' => 1, 'total_paginas' => 1]];
        }
        return $this->obtenerDatos($id, $rol, $buscar, 3, 'tabla', $id_periodo);
    }

    // Rechazados ya no es visible para investigador en este Módulo.
    public function Rechazados(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        if (in_array($rol, ['investigador', 'profesor'], true)) {
            return ['proyectos' => [], 'paginacion' => ['total' => 0, 'por_pagina' => 6, 'pagina' => 1, 'total_paginas' => 1]];
        }
        return $this->obtenerDatos($id, $rol, $buscar, 4, 'tabla', $id_periodo);
    }

    public function PorCerrar(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 5, 'tabla', $id_periodo);
    }

    public function Vencido(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        return $this->obtenerDatos($id, $rol, $buscar, 6, 'tabla', $id_periodo);
    }

    // Cierrerechazado ya no es visible para investigador en este Módulo.
    public function Cierrerechazado(int $id, string $rol, ?string $buscar = null, int $id_periodo = 0): array
    {
        if (in_array($rol, ['investigador', 'profesor'], true)) {
            return ['proyectos' => [], 'paginacion' => ['total' => 0, 'por_pagina' => 6, 'pagina' => 1, 'total_paginas' => 1]];
        }
        return $this->obtenerDatos($id, $rol, $buscar, 7, 'tabla', $id_periodo);
    }


    // 
    // CONVERSIÓN action -> id_estadoP
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
            'Total'   => 'Total',
            'Activos' => 'Activos',
        ];

        if ($rol === 'estudiante') {
            return $base + [
                'Cierre'    => 'Cierre',
                'PorCerrar' => 'Por Cerrar',
                'Vencido'   => 'Vencidos',
                'Concluidos' => 'Concluidos',
            ];
        }

        if (in_array($rol, ['investigador', 'profesor'], true)) {
            // Esos estados se gestionan exclusivamente en Módulo 2 (Solicitudes).
            return $base + [
                'Cierre'    => 'Cierre',
                'PorCerrar' => 'Por Cerrar',
                'Vencido'   => 'Vencidos',
                'Concluidos' => 'Concluidos',
            ];
        }

        if ($rol === 'supervisor') {
            return $base + [
                'PorCerrar' => 'Por Cerrar',
                'Cierre'    => 'Cierre',
                'Vencido'   => 'Vencidos',
                'Concluidos' => 'Concluidos',
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
        include __DIR__ . '/../publico/incluido/_iconos.php';

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

            // El investigador confirma/inicia el flujo de cierre desde Solicitudes.
            'Solicitar cerrar' => Botones::botonIcono(
                '../Solicitudes_proyecto/index.php?action=solicitarCierre&id_proyectos=' . $id_proyecto,
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Solicitar cierre del proyecto'
            ),

            // "Ver solicitud" para Cierre rechazado -> redirige a Módulo 2.
            'Ver solicitud cierre' => Botones::botonIcono(
                '../Solicitudes_proyecto/index.php?id_proyectos=' . $id_proyecto,
                'warning',
                $iconos['tabla']['ver'],
                'Ver solicitud de cierre en Módulo 2'
            ),

            'Comentarios' => Botones::botonIcono(
                'ver_comentarios.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['comentarios'],
                'Ver comentarios'
            ),

            // Los siguientes botones se conservan para usos en editar.php / detalles.php.
            'Volver a enviar cierre' => Botones::botonIcono(
                '../Solicitudes_proyecto/index.php?action=reenviarCierre&id_proyectos=' . $id_proyecto,
                'warning',
                $iconos['tabla']['volver_enviar'],
                'Reenviar cierre al supervisor'
            ),

            'Volver a enviar proyecto' => Botones::botonIcono(
                'editar.php?id_proyectos=' . $id_proyecto . '&desde=solicitudes',
                'warning',
                $iconos['tabla']['volver_enviar'],
                'Corregir y reenviar proyecto'
            ),

            'Editar' => Botones::botonIcono(
                'editar.php?id_proyectos=' . $id_proyecto,
                'info',
                $iconos['tabla']['editar'],
                'Editar proyecto'
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
        // $estado_completados_estudiantes == 1 -> puede solicitar cierre
        $solicitar = ($estado_completados_estudiantes == 1) ? 'Solicitar cerrar' : '';

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
                'Cierre rechazado'     => ['Detalles', 'Ver Tareas Alumnos'],
            ],

            //   • Rechazado y Cierre rechazado: NO aparecen en Módulo 1 (la query ya los excluye).
            //   • Activo con puede_cerrar=1: "Solicitar cerrar" redirige a Módulo 2.
            //   • Por cerrar: solo lectura — en revisión en Módulo 2.
            //   • Cierre rechazado: "Ver solicitud" redirige a Módulo 2 (redundante aquí,
            //     pero se define por si la query del supervisor los devuelve con ese rol).
            'investigador' => [
                'Activo'           => ['Detalles', 'Tareas', 'Comentarios', $solicitar],
                'Por cerrar'       => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Vencido'          => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            'profesor' => [
                'Activo'           => ['Detalles', 'Tareas', 'Comentarios', $solicitar],
                'Por cerrar'       => ['Detalles', 'Tareas', 'Comentarios'],
                'Cierre'           => ['Detalles', 'Tareas', 'Comentarios'],
                'Vencido'          => ['Detalles', 'Tareas', 'Comentarios'],
            ],

            // Supervisor: solo lectura en Módulo 1 (aprobación/rechazo en Módulo 2).
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

    /** Periodo activo para registrar proyecto. */
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

    /** Todos los periodos — usado por el supervisor para el filtro de periodo en Módulo 1. */
    public function obtenerTodosPeriodos(): array
    {
        global $conn;
        try {
            return (new Proyectos($conn))->obtenerTodosPeriodos() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }


    // 
    // CRUD DE PROYECTOS
    // 

    /**
     * Registra un proyecto nuevo con estado "Por aprobar".
     * Acción de formulario -> redirige con msg.
     */
    public function registrarProyecto(array $datos, int $id, string $rol): void
    {
        global $conn;

        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['investigador', 'profesor']);

            $periodo = $this->obtenerperiodo();

            if (empty($periodo)) {
                $this->redirigir('periodo_vencido');
            }

            $hoy            = new DateTime(date('Y-m-d'));
            $inicioRegistro = new DateTime($periodo['fecha_inicio_proyectos']);
            $finRegistro    = new DateTime($periodo['fecha_fin_proyectos']);

            if ($hoy < $inicioRegistro || $hoy > $finRegistro) {
                $this->redirigir('periodo_vencido');
            }

            $fechaInicio = $datos['FechaInicio'] ?? '';
            $fechaFinal  = $datos['FechaFinal']  ?? '';

            if (empty($fechaInicio) || empty($fechaFinal)) {
                $this->redirigir('error_crear');
            }

            $inicio        = new DateTime($fechaInicio);
            $fin           = new DateTime($fechaFinal);
            $inicioPeriodo = new DateTime($periodo['FechaInicio']);

            if ($fin < $inicio) {
                $this->redirigir('error_crear');
            }

            if ($inicio < $inicioPeriodo) {
                $this->redirigir('error_crear');
            }

            $limiteUnAnio = clone $inicio;
            $limiteUnAnio->modify('+1 year');

            if ($fin > $limiteUnAnio) {
                $this->redirigir('error_crear');
            }

            $subtematicas = $datos['subtematicas'] ?? [];

            if (
                empty($subtematicas) ||
                empty(trim($datos['NombreProyecto'] ?? ''))
            ) {
                $this->redirigir('error_crear');
            }

            if ($inicio < new DateTime($periodo['fecha_inicio'])) {
                $this->redirigir('error_crear');
            }

            $limiteMaximo = new DateTime($periodo['fecha_final']);
            $limiteMaximo->modify('+1 year');

            if ($fin > $limiteMaximo) {
                $this->redirigir('error_crear');
            }

            $instituto = $this->obtenerInstituto()[0] ?? null;

            if (!$instituto) {
                $this->redirigir('error_sin_registro');
            }

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
                $modelo->vincularSubtematica($proyectoId, (int)$idSub);
            }

            $conn->commit();

            // Tras crear, redirigir a Módulo de solicitudes donde verá el estado "Por aprobar".
            $this->redirigir('exito_crear', '../Solicitudes_proyectos/index.php');
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
     * Recibe parámetro opcional 'desde' para redirigir correctamente al terminar.
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
                throw new Exception('Datos incompletos');
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

            // Si la edición viene de Solicitudes (corrección de rechazo),
            // redirigir de vuelta al Módulo de solicitudes con el id del proyecto.
            $desde = $datos['desde'] ?? '';
            if ($desde === 'solicitudes') {
                $this->redirigir('exito_editar', '../Solicitudes_proyectos/index.php', "&id_proyectos={$id_proyecto}");
            } else {
                $this->redirigir('exito_editar', 'index.php', "&id_proyectos={$id_proyecto}");
            }
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
     * Cambia el estado de un proyecto vía enlace GET.
     *
     */
    public function actualizarestado(int $id_proyecto, string $rol, string $tipo): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor', 'investigador', 'profesor']);

            $modelo = new Proyectos($conn);
            $modelo->actualizarProyectosVencidos();

            $estado     = $this->numerofiltro($tipo);
            $porcentaje = $this->obtenerPorcentajeAvance($id_proyecto);

            $modelo->actualizarestado($id_proyecto, $estado, $porcentaje);

            $this->redirigir('exito_estado');
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_estado';
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
        $investigador = $modelo->obtenerProyectoInvestigador($id_proyecto);

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
            $resultado = $modelo->obtenersubtematicasProyecto($id_proyecto);

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
            return (new Proyectos($conn))->lineaTiempoProyectoUsuarios($id_proyecto, $id_usuario, $pagina, 5);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar', 'editar.php');
        }
    }


    // 
    // ACCIONES DE ESTUDIANTE (baja / reactivar) — Proyectos/detalles.php
    // 

    public function accionEstudiante(array $datos): void
    {
        global $conn;

        $action        = $datos['action']        ?? '';
        $id_proyecto   = (int)($datos['id_proyecto']   ?? 0);
        $id_estudiante = (int)($datos['id_estudiante'] ?? 0);
        $motivo        = $datos['motivo']         ?? null;

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
