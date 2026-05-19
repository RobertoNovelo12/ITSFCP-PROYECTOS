<?php
// Controladores/solicitudes_proyectoControlador.php
// Controlador del módulo Solicitudes de Proyecto.
// Gestiona solicitudes de creación y cierre de proyectos.
// Solo accesible por el rol 'supervisor' (listado/aprobación) e
// 'investigador'/'profesor' (consulta de sus propias solicitudes).

require_once __DIR__ . '/../Modelos/solicitudes_proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class SolicitudesProyectoControlador
{

    // 
    // VALIDACIONES INTERNAS
    // 

    private function rolValido(string $rol): bool
    {
        return in_array($rol, ['investigador', 'supervisor', 'profesor'], true);
    }

    private function validarAcceso(string $rol, array $permitidos): void
    {
        if (!in_array($rol, $permitidos, true)) {
            throw new Exception("No tienes permisos para realizar esta acción");
        }
    }

    private function validarMetodo(string $metodo): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
            throw new Exception("Método no permitido");
        }
    }

    // 
    // ESTILO DE ESTADO (badge Bootstrap)
    // 

    public function EstiloEstado(string $estado): string
    {
        return match ($estado) {
            'Cierre rechazado', 'Rechazado', 'Terminado'                            => 'danger',
            'Por cerrar', 'Por aprobar', 'Pendiente', 'en_proceso', 'en_correccion' => 'warning',
            'Vencido'                                                                => 'dark',
            'Activo', 'carta_subida', 'liberado_supervisor'                         => 'success',
            'Cierre'                                                                 => 'secondary',
            default                                                                  => 'info',
        };
    }

    // 
    // BOTONES DE ACCIÓN — tabla de Solicitudes_proyecto/index.php
    // 

    public function botonesAccionSolicitud(
        int $id_proyecto,
        string $rol,
        string $tipo_solicitud,
        string $estado_proyecto
    ): string {
        // Botón "Ver detalles" siempre presente
        $botones = '<a href="detalles.php?id_proyectos=' . $id_proyecto . '"
            class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-title="Ver detalle de solicitud">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                 class="bi bi-eye-fill" viewBox="0 0 16 16">
                <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
            </svg></a>';

        if ($rol !== 'supervisor') {
            return $botones;
        }

        if ($tipo_solicitud === 'creacion' && $estado_proyecto === 'Por aprobar') {
            // Aprobar creación — &tipo=Activos → numerofiltro() = 2
            $botones .= '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos"
                class="btn btn-success btn-sm" data-bs-toggle="tooltip" data-bs-title="Aprobar proyecto"
                onclick="return confirm(\'¿Aprobar este proyecto?\')">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                     class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg></a>';

            // Rechazar creación
            $botones .= '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=creacion_rechazada&desde=solicitudes"
                class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-title="Rechazar proyecto">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                     class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                </svg></a>';
        }

        if ($tipo_solicitud === 'cierre' && $estado_proyecto === 'Por cerrar') {
            // Aprobar cierre — &tipo=Cierre → numerofiltro() = 1
            $botones .= '<a href="index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre"
                class="btn btn-success btn-sm" data-bs-toggle="tooltip" data-bs-title="Aprobar cierre"
                onclick="return confirm(\'¿Aprobar el cierre de este proyecto?\')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                     class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg></a>';

            // Rechazar cierre
            $botones .= '<a href="comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=cierre_rechazado&desde=solicitudes"
                class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-title="Rechazar cierre">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                     class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                </svg></a>';
        }

        return $botones;
    }

    // 
    // RESUMEN (tarjetas del dashboard de Solicitudes_proyecto/index.php)
    // 

    public function resumenSolicitudes(string $rol, int $id_usuario, int $id_periodo = 0): array
    {
        global $conn;
        try {
            return (new SolicitudesProyecto($conn))->resumenSolicitudes($rol, $id_usuario, $id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['total' => 0, 'pendientes_creacion' => 0, 'pendientes_cierre' => 0, 'aprobadas' => 0];
        }
    }

    // 
    // LISTADO PAGINADO (Solicitudes_proyecto/index.php)
    // 

    public function listarSolicitudes(
        string $rol,
        int $id_usuario,
        string $tipo_filtro = 'Todas',
        string $buscar = '',
        int $pagina = 1,
        int $id_periodo = 0
    ): array {
        global $conn;
        try {
            $resultado = (new SolicitudesProyecto($conn))->listarSolicitudes(
                $rol, $id_usuario, $tipo_filtro, $buscar, $pagina, $id_periodo
            );
            return is_string($resultado) ? json_decode($resultado, true) : $resultado;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos(): array
    {
        global $conn;
        try {
            return (new SolicitudesProyecto($conn))->obtenerTodosPeriodos() ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    // DETALLE DE SOLICITUD (Solicitudes_proyecto/detalles.php)
    // 

    public function datosproyecto(int $id_proyecto): ?array
    {
        global $conn;
        return (new SolicitudesProyecto($conn))->obtenerProyecto($id_proyecto);
    }

    public function datosinvestigador(int $id_proyecto): array
    {
        global $conn;
        $modelo       = new SolicitudesProyecto($conn);
        $investigador = $modelo->obtenerProyectoInvestigador($id_proyecto);
        $id_usuario   = $investigador['id_usuarios'] ?? null;
        return [
            'investigador' => $investigador,
            'area'         => $modelo->obtenerUsuarioArea($id_usuario),
            'lineas'       => $modelo->obtenerInvestigadorLinea($id_proyecto),
        ];
    }

    public function subtematicasProyecto(int $id_proyecto): array
    {
        global $conn;
        try {
            return (new SolicitudesProyecto($conn))->obtenersubtematicasProyecto($id_proyecto);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function comentarios(int $id_proyecto): array
    {
        global $conn;
        return (new SolicitudesProyecto($conn))->obtenerProyectoComentarios($id_proyecto);
    }

    public function estudiantes(int $id_proyecto): array
    {
        global $conn;
        return (new SolicitudesProyecto($conn))->estudiantes($id_proyecto);
    }

    // 
    // ACTUALIZAR ESTADO — rechazo con comentario (POST desde comentarios.php)
    // 

    public function actualizarestadoRechazo(array $data, int $id_usuario, string $rol): ?string
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (empty($data['comentario']) || empty($data['id_proyectos'])) {
                throw new Exception("Datos incompletos");
            }

            (new SolicitudesProyecto($conn))->actualizarEstadoProyectoRechazo(
                $id_usuario,
                (int)$data['id_proyectos'],
                $data['tipo'],
                $data['comentario']
            );

            header("Location: index.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // 
    // ACTUALIZAR ESTADO — aprobación por enlace GET (desde index.php / detalles.php)
    // 

    public function actualizarestado(int $id_proyecto, string $rol, string $tipo): ?string
    {
        global $conn;
        try {
            $this->validarMetodo('GET');
            $this->validarAcceso($rol, ['supervisor']);

            $modelo = new SolicitudesProyecto($conn);
            $modelo->actualizarProyectosVencidos();

            $estado     = $this->numerofiltro($tipo);
            $porcentaje = $this->obtenerPorcentajeAvance($id_proyecto);

            $modelo->actualizarestado($id_proyecto, $estado, $porcentaje);

            header("Location: index.php?mensaje=1");
            exit();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // 
    // CONVERSIÓN action → id_estadoP
    // 

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Total'                     => 0,
            'Cierre'                    => 1,
            'Activos', 'Activo'         => 2,
            'PorAprobar'                => 3,
            'Rechazados'                => 4,
            'PorCerrar'                 => 5,
            'Vencido'                   => 6,
            'Cierrerechazado',
            'CierreRechazado'           => 7,
            default                     => 0,
        };
    }

    // 
    // PORCENTAJE DE AVANCE
    // 

    public function obtenerPorcentajeAvance(int $id_proyecto): float
    {
        global $conn;
        try {
            $valor = (new SolicitudesProyecto($conn))->obtenerTareasAvance($id_proyecto);
            return ($valor === null || $valor === false) ? 0.0 : (float)$valor;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0.0;
        }
    }
}