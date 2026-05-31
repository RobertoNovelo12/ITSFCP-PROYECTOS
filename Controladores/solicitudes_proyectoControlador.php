<?php
// Controladores/solicitudes_proyectoControlador.php
// Controlador del módulo Solicitudes de Proyecto.
// Gestiona solicitudes de creación y cierre de proyectos.
// Solo accesible por el rol 'supervisor' (listado/aprobación) e
// 'investigador'/'profesor' (consulta de sus propias solicitudes).

require_once __DIR__ . '/../Modelos/solicitudes_proyecto.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';
include __DIR__ . '/../publico/incluido/_botones.php';

class SolicitudesProyectoControlador extends BaseControlador
{

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
        include __DIR__ . '../../publico/incluido/_iconos.php';

        // Botón "Ver detalle" siempre presente
        $botones = Botones::botonIcono(
            'detalles.php?id_proyectos=' . $id_proyecto,
            'primary',
            $iconos['tabla']['ver'],
            'Ver detalle de solicitud'
        );

        if ($rol !== 'supervisor') {
            return $botones;
        }

        // Creación pendiente de aprobación
        if ($tipo_solicitud === 'creacion' && $estado_proyecto === 'Por aprobar') {

            $botones .= Botones::botonConfirmacion(
                'index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Activos',
                'success',
                $iconos['tabla']['aprobar'],
                'Aprobar proyecto',
                '¿Aprobar este proyecto?'
            );

            $botones .= Botones::botonIcono(
                'comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=creacion_rechazada&desde=solicitudes',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Rechazar proyecto'
            );
        }

        // Cierre pendiente de aprobación
        if ($tipo_solicitud === 'cierre' && $estado_proyecto === 'Por cerrar') {

            $botones .= Botones::botonConfirmacion(
                'index.php?action=actualizarestado&id_proyectos=' . $id_proyecto . '&tipo=Cierre',
                'success',
                $iconos['tabla']['aprobar'],
                'Aprobar cierre',
                '¿Aprobar el cierre de este proyecto?'
            );

            $botones .= Botones::botonIcono(
                'comentarios.php?id_proyectos=' . $id_proyecto . '&motivo=cierre_rechazado&desde=solicitudes',
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Rechazar cierre'
            );
        }

        return $botones;
    }


    // 
    // RESUMEN (tarjetas del dashboard)
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
    // LISTADO PAGINADO
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
    // DETALLE DE SOLICITUD
    // 

    public function datosproyecto(int $id_proyecto): ?array
    {
        global $conn;
        return (new SolicitudesProyecto($conn))->obtenerProyecto($id_proyecto);
    }

    public function datosinvestigador(int $id_proyecto): array
    {
        global $conn;
        $modelo     = new SolicitudesProyecto($conn);
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
    // Acción de formulario POST → redirige con msg.
    // El rechazo puede originarse desde solicitudes o desde proyectos,
    // por eso se conserva el parámetro 'desde' para elegir el destino.
    // 

    public function actualizarestadoRechazo(array $data, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            if (empty($data['comentario']) || empty($data['id_proyectos'])) {
                throw new Exception('error_rechazo');
            }

            (new SolicitudesProyecto($conn))->actualizarEstadoProyectoRechazo(
                $id_usuario,
                (int)$data['id_proyectos'],
                $data['tipo'],
                $data['comentario']
            );

            $desde = $data['desde'] ?? 'solicitudes';
            if ($desde === 'solicitudes') {
                $this->redirigir('exito_rechazo');
            } else {
                $this->redirigir('exito_rechazo', '../Proyectos/index.php');
            }

        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_rechazo';
            $this->redirigir($msg, 'comentarios.php', "&id_proyectos=" . ($data['id_proyectos'] ?? '') . "&motivo=" . ($data['tipo'] ?? ''));
        }
    }

    // 
    // ACTUALIZAR ESTADO — aprobación por enlace GET (desde index.php)
    // 

    public function actualizarestado(int $id_proyecto, string $rol, string $tipo): void
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

            $this->redirigir('exito_estado');

        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_estado';
            $this->redirigir($msg);
        }
    }

    // 
    // CONVERSIÓN action → id_estadoP
    // 

    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Total'                   => 0,
            'Cierre'                  => 1,
            'Activos', 'Activo'       => 2,
            'PorAprobar'              => 3,
            'Rechazados'              => 4,
            'PorCerrar'               => 5,
            'Vencido'                 => 6,
            'Cierrerechazado',
            'CierreRechazado'         => 7,
            default                   => 0,
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