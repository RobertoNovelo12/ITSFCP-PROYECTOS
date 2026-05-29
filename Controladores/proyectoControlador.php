<?php
// Controladores/proyectoControlador.php

require_once __DIR__ . '/../Modelos/proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';


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

            if ($tipo === 'filtro') {
                return $modelo->obtenerProyectosDatosFiltro($id, $rol);
            }

            // tipo === 'tabla'
            $resultado = $modelo->obtenerProyectosTablaFiltro($id, $filtro, $rol, $buscar);
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
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

    public function filtros(int $id, string $rol): array
    {
        return $this->obtenerDatos($id, $rol, null, null, 'filtro');
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

    public function opcionesProyectos(string $rol, array $filtros): array
    {
        if (empty($filtros)) return [];

        $base = [
            'Total'   => "Total ({$filtros[0]['Total']})",
            'Activos' => "Activos ({$filtros[0]['Activos']})",
        ];

        if ($rol === 'estudiante') {
            return $base + [
                'Cierre'    => "Cierre ({$filtros[0]['Cierre']})",
                'PorCerrar' => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Vencido'   => "Vencidos ({$filtros[0]['Vencido']})",
            ];
        }

        if (in_array($rol, ['investigador', 'profesor'], true)) {
            return $base + [
                'Rechazados' => "Rechazados ({$filtros[0]['Rechazados']})",
                'Cierre'     => "Cierre ({$filtros[0]['Cierre']})",
                'PorCerrar'  => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Vencido'    => "Vencidos ({$filtros[0]['Vencido']})",
            ];
        }

        if ($rol === 'supervisor') {
            return $base + [
                'PorCerrar' => "Por Cerrar ({$filtros[0]['PorCerrar']})",
                'Cierre'    => "Cierre ({$filtros[0]['Cierre']})",
                'Vencido'   => "Vencidos ({$filtros[0]['Vencido']})",
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
        switch ($tipo) {

            case 'Detalles':
                return '<a href="detalles.php?id_proyectos=' . $id_proyecto . '" class="btn btn-primary btn-sm"
                    data-bs-toggle="tooltip" data-bs-title="Ver detalles del proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg></a>';

            case 'Tareas':
                return '<a href="../Tareas/index.php?id_proyectos=' . $id_proyecto . '" class="btn btn-info btn-sm"
                    data-bs-toggle="tooltip" data-bs-title="Tareas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-list-task" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                    </svg></a>';

            case 'Ver Tareas Alumnos':
                return '<a href="../Tareas/tareas_estudiante.php?id_usuario=' . $id_usuario . '&id_proyectos=' . $id_proyecto . '"
                    class="btn btn-info btn-sm" data-bs-toggle="tooltip" data-bs-title="Ver Tareas">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-list-task" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"/>
                        <path d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"/>
                        <path fill-rule="evenodd" d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"/>
                    </svg></a>';

            case 'Solicitar cerrar':
                return '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"
                    class="btn btn-warning btn-sm" data-bs-toggle="tooltip" data-bs-title="Solicitar cierre del proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg></a>';

            case 'Volver a enviar cierre':
                return '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorCerrar"
                    class="btn btn-warning btn-sm" data-bs-toggle="tooltip" data-bs-title="Volver a enviar cierre">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
                    </svg></a>';

            case 'Volver a enviar proyecto':
                return '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=PorAprobar"
                    class="btn btn-warning btn-sm" data-bs-toggle="tooltip" data-bs-title="Volver a enviar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5 5 0 0 0 8 3M3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9z"/>
                    </svg></a>';

            case 'Editar':
                return '<a href="editar.php?id_proyectos=' . $id_proyecto . '"
                    class="btn btn-warning btn-sm" data-bs-toggle="tooltip" data-bs-title="Editar proyecto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-pencil-square" viewBox="0 0 16 16">
                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                    </svg></a>';

            case 'Comentarios':
                return '<a href="ver_comentarios.php?id_proyectos=' . $id_proyecto . '"
                    class="btn btn-info btn-sm" data-bs-toggle="tooltip" data-bs-title="Ver comentarios">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                         class="bi bi-chat-dots-fill" viewBox="0 0 16 16">
                        <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
                    </svg></a>';

            case 'Dar de baja':
                return '<button class="btn btn-danger btn-sm" data-accion="baja" data-id="' . $id_usuario . '">Dar de baja</button>';

            case 'Reactivar':
                return '<button class="btn btn-success btn-sm" data-accion="reactivar" data-id="' . $id_usuario . '">Reactivar</button>';

            default:
                return '';
        }
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

            $subtematicas = $datos['subtematicas'] ?? [];
            if (empty($subtematicas) || empty($datos['NombreProyecto'])) {
                throw new Exception("Datos incompletos");
            }

            $periodo   = $this->obtenerperiodo();
            $instituto = $this->obtenerInstituto()[0] ?? null;
            if (!$instituto) {
                throw new Exception("No se encontró instituto");
            }

            $modelo = new Proyectos($conn);
            $modelo->actualizarProyectosVencidos();

            $proyectoId = $modelo->registrarProyecto(
                $id,
                3, // id_estadoP = 'Por aprobar'
                (int)$instituto['id_instituto'],
                (int)$periodo[0]['id_periodos'],
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
                $modelo->vincularSubtematica($proyectoId, (int)$idSub);
            }

            $this->redirigir('exito_crear');

        } catch (Exception $e) {
            error_log($e->getMessage());
            // Si el error viene de validarAcceso usamos su clave directamente,
            // en cualquier otro caso mostramos el mensaje genérico de creación.
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_crear';
            $this->redirigir($msg);
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