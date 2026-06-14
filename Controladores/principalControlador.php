<?php
// Controladores/principalControlador.php

require_once __DIR__ . '/../Modelos/principal.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class principalControlador extends BaseControlador
{
    private int $POR_PAGINA = 30; // 3 columnas × 10 filas

    // 
    //  LEER Y SANITIZAR FILTROS DESDE GET
    // 

    private function leerFiltros(): array
    {
        return [
            'buscar'         => trim(strip_tags($_GET['buscar']       ?? '')),
            'modalidad'      => in_array($_GET['modalidad'] ?? '', ['virtual', 'fisico', 'mixto'])
                ? $_GET['modalidad'] : '',
            'id_tematica'    => max(0, (int)($_GET['id_tematica']    ?? 0)),
            'id_subtematica' => max(0, (int)($_GET['id_subtematica'] ?? 0)),
            'pagina'         => max(1, (int)($_GET['pagina']         ?? 1)),
        ];
    }

    // 
    //  ENRUTADOR PRINCIPAL
    // 

    public function listarProyectos(string $rol, int $id_usuario): array
    {
        global $conn;
        try {
            $modelo  = new principal($conn);

            $modelo->marcarSolicitudesProyectosVencidos(); // Mantenimiento automático de estados basado en fechas
            $filtros = $this->leerFiltros();
            $pagina  = $filtros['pagina'];

            $tematicas    = $modelo->obtenerTematicas();
            $subtematicas = $modelo->obtenerSubtematicas($filtros['id_tematica']);

            switch ($rol) {

                case 'investigador':
                case 'profesor':
                    $total = $modelo->contarProyectosInvestigador(
                        $id_usuario,
                        $rol,
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica']
                    );
                    $proyectos = $modelo->obtenerProyectosInvestigador(
                        $id_usuario,
                        $rol,
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica'],
                        $pagina,
                        $this->POR_PAGINA
                    );
                    return [
                        'proyectos'       => $proyectos,
                        'tematicas'       => $tematicas,
                        'subtematicas'    => $subtematicas,
                        'filtros'         => $filtros,
                        'ventana_abierta' => false,
                        'puede_crear'     => $modelo->ventanaCreacionAbierta(),
                        'paginacion'      => $this->_paginacion($total, $pagina),
                    ];

                case 'supervisor':
                    $total = $modelo->contarProyectosInvestigador(
                        0,
                        'supervisor',
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica']
                    );
                    $proyectos = $modelo->obtenerProyectosInvestigador(
                        0,
                        'supervisor',
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica'],
                        $pagina,
                        $this->POR_PAGINA
                    );
                    return [
                        'proyectos'       => $proyectos,
                        'tematicas'       => $tematicas,
                        'subtematicas'    => $subtematicas,
                        'filtros'         => $filtros,
                        'ventana_abierta' => false,
                        'puede_crear'     => false,
                        'paginacion'      => $this->_paginacion($total, $pagina),
                    ];

                case 'estudiante':
                    $ventana = $modelo->ventanaSolicitudAbierta();
                    $total   = $modelo->contarProyectosEstudiante(
                        $id_usuario,
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica']
                    );
                    $proyectos = $modelo->obtenerProyectosEstudiante(
                        $id_usuario,
                        $filtros['buscar'],
                        $filtros['modalidad'],
                        $filtros['id_tematica'],
                        $filtros['id_subtematica'],
                        $pagina,
                        $this->POR_PAGINA
                    );
                    return [
                        'proyectos'       => $proyectos,
                        'tematicas'       => $tematicas,
                        'subtematicas'    => $subtematicas,
                        'filtros'         => $filtros,
                        'ventana_abierta' => $ventana,
                        'puede_crear'     => false,
                        'paginacion'      => $this->_paginacion($total, $pagina),
                    ];

                default:
                    return $this->_vacio($tematicas, $subtematicas);
            }
        } catch (Exception $e) {
            error_log("listarProyectos(): " . $e->getMessage());
            return $this->_vacio();
        }
    }


    /**
     * Cancela una solicitud.
     */
    public function cancelar(int $id_usuario, int $id_proyectos): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');

            (new principal($conn))->cancelarSolicitud($id_usuario, $id_proyectos);

            $this->redirigir('exito_cancelar', 'detalles_proyecto.php', "&id_proyectos={$id_proyectos}");
        } catch (Exception $e) {
            error_log('principalControlador::cancelar() — ' . $e->getMessage());
            $this->redirigir('error_cancelar', 'detalles_proyecto.php', "&id_proyectos={$id_proyectos}");
        }
    }

    // 
    //  AJAX: subtémáticas dependientes
    // 

    public function subtematicasPorTematica(): void
    {
        global $conn;
        $id_tematica = max(0, (int)($_GET['id_tematica'] ?? 0));
        $this->json((new principal($conn))->obtenerSubtematicas($id_tematica));
    }

    // 
    //  DATOS PARA DETALLES_PROYECTO
    // 

    public function obtenerDatos(int $id_proyecto, int $id_usuario, string $rol): array
    {
        global $conn;

        $modelo  = new principal($conn);
        $proyecto = $modelo->obtenerDetalle($id_proyecto);

        if ($proyecto === null) {
            $this->redirigir('error_cargar');
        }

        $es_integrante = ($rol === 'estudiante')
            ? $modelo->esIntegrante($id_proyecto, $id_usuario)
            : false;

        $solicitud = null;
        if ($rol === 'estudiante') {
            $solicitud = $modelo->obtenerUltimaSolicitud($id_proyecto, $id_usuario);
        }

        $ventana_abierta = false;
        if ($rol === 'estudiante') {
            $ventana_abierta = $modelo->ventanaSolicitudAbiertaParaProyecto($id_proyecto);
        }

        $puede_solicitar     = false;
        $solicitud_bloqueante = false;   // ← nueva variable
        $cupo_disponible     = (int)($proyecto['lugares_disponibles'] ?? 0) > 0;
        $limite_alcanzado    = false;
        $carga               = ['activos' => 0, 'en_espera' => 0];

        if ($rol === 'estudiante' && $ventana_abierta && !$es_integrante && $cupo_disponible) {
            $estado_sol = $solicitud['estado'] ?? null;

            if ($estado_sol === null || $estado_sol === 'cancelado') {
                // Verificar si existe una solicitud bloqueante (rechazado / vencido)
                $solicitud_bloqueante = $modelo->tieneSolicitudBloqueante($id_usuario, $id_proyecto);

                if (!$solicitud_bloqueante) {
                    $carga            = $modelo->obtenerCargaProyectosEstudiante($id_usuario);
                    $limite_alcanzado = ($carga['activos'] + $carga['en_espera']) >= 3;
                    $puede_solicitar  = !$limite_alcanzado;
                }
            } elseif ($estado_sol === 'rechazado' || $estado_sol === 'vencido') {
                // La última solicitud ya indica el estado bloqueante directamente
                $solicitud_bloqueante = true;
            }
        } elseif ($rol === 'estudiante') {
            $carga = $modelo->obtenerCargaProyectosEstudiante($id_usuario);
        }

        $puede_cancelar = false;
        if ($rol === 'estudiante' && !$es_integrante && $solicitud !== null) {
            $puede_cancelar = in_array(
                $solicitud['estado'],
                ['pendiente', 'en_revision', 'correcciones', 'aceptado']
            );
        }

        return [
            'proyecto'             => $proyecto,
            'es_integrante'        => $es_integrante,
            'solicitud'            => $solicitud,
            'ventana_abierta'      => $ventana_abierta,
            'puede_solicitar'      => $puede_solicitar,
            'puede_cancelar'       => $puede_cancelar,
            'solicitud_bloqueante' => $solicitud_bloqueante,  // ← nueva clave
            'carga'                => $carga,
        ];
    }


    // 
    //  MENSAJE SOLICITUD (desde QueryString)
    // 

    public function leerMensajeSolicitud(): ?array
    {
        if (!isset($_GET['solicitud'])) return null;

        return match ($_GET['solicitud']) {
            'sent'      => ['title' => '¡Solicitud enviada!',    'body' => 'Tu solicitud ha sido enviada correctamente. Será revisada por el investigador.', 'tipo' => 'success'],
            'pending'   => ['title' => 'Solicitud pendiente',    'body' => 'Ya tienes una solicitud pendiente para este proyecto.',                          'tipo' => 'warning'],
            'accepted'  => ['title' => 'Solicitud aceptada',     'body' => 'Ya fuiste aceptado anteriormente en este proyecto.',                             'tipo' => 'info'],
            'cancelled' => ['title' => 'Solicitud cancelada',    'body' => 'Tu solicitud ha sido cancelada exitosamente.',                                   'tipo' => 'info'],
            default     => ['title' => 'Atención',               'body' => 'Ocurrió un problema. Intenta más tarde.',                                        'tipo' => 'error'],
        };
    }

    // 
    //  PRESENTACIÓN
    // 

    public function badgeEstado(int $id_estadoP): string
    {
        return match ($id_estadoP) {
            2       => 'badge-estado-activo',
            5       => 'badge-estado-porcerrar',
            3       => 'badge-estado-poraprobar',
            1       => 'badge-estado-cierre',
            6       => 'badge-estado-vencido',
            default => 'badge-estado-default',
        };
    }

    public function badgeModalidad(string $modalidad): string
    {
        return match ($modalidad) {
            'virtual' => 'badge-modal-virtual',
            'fisico'  => 'badge-modal-fisico',
            'mixto'   => 'badge-modal-mixto',
            default   => '',
        };
    }

    // 
    //  HELPERS PRIVADOS
    // 

    private function _paginacion(int $total, int $pagina): array
    {
        $total_paginas = max(1, (int)ceil($total / $this->POR_PAGINA));
        return [
            'total'         => $total,
            'por_pagina'    => $this->POR_PAGINA,
            'pagina'        => $pagina,
            'total_paginas' => $total_paginas,
        ];
    }

    private function _vacio(array $tematicas = [], array $subtematicas = []): array
    {
        return [
            'proyectos'       => [],
            'tematicas'       => $tematicas,
            'subtematicas'    => $subtematicas,
            'filtros'         => ['buscar' => '', 'modalidad' => '', 'id_tematica' => 0, 'id_subtematica' => 0, 'pagina' => 1],
            'ventana_abierta' => false,
            'puede_crear'     => false,
            'paginacion'      => $this->_paginacion(0, 1),
        ];
    }
}
