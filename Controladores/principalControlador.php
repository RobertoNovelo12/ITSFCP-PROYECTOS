<?php
// Controladores/principalControlador.php
require_once __DIR__ . '/../Modelos/principal.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class principalControlador
{
    private int $POR_PAGINA = 30; // 3 columnas × 10 filas

    // 
    //  Leer y sanitizar parámetros de filtro desde GET
    // 
    private function leerFiltros(): array
    {
        return [
            'buscar'          => trim(strip_tags($_GET['buscar']       ?? '')),
            'modalidad'       => in_array($_GET['modalidad'] ?? '', ['virtual', 'fisico', 'mixto'])
                ? $_GET['modalidad'] : '',
            'id_tematica'     => max(0, (int)($_GET['id_tematica']    ?? 0)),
            'id_subtematica'  => max(0, (int)($_GET['id_subtematica'] ?? 0)),
            'pagina'          => max(1, (int)($_GET['pagina']         ?? 1)),
        ];
    }

    // 
    //  Enrutador principal: devuelve todo lo que la vista necesita
    // 
    public function listarProyectos(string $rol, int $id_usuario): array
    {
        global $conn;
        try {
            $modelo  = new principal($conn);
            $filtros = $this->leerFiltros();
            $pagina  = $filtros['pagina'];

            // Catálogos para los <select> del filtro (siempre se cargan)
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
                        'proyectos'      => $proyectos,
                        'tematicas'      => $tematicas,
                        'subtematicas'   => $subtematicas,
                        'filtros'        => $filtros,
                        'ventana_abierta' => false,
                        'puede_crear'    => $modelo->ventanaCreacionAbierta(),
                        'paginacion'     => $this->_paginacion($total, $pagina),
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
                        'proyectos'      => $proyectos,
                        'tematicas'      => $tematicas,
                        'subtematicas'   => $subtematicas,
                        'filtros'        => $filtros,
                        'ventana_abierta' => false,
                        'puede_crear'    => false,
                        'paginacion'     => $this->_paginacion($total, $pagina),
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
                        'proyectos'      => $proyectos,
                        'tematicas'      => $tematicas,
                        'subtematicas'   => $subtematicas,
                        'filtros'        => $filtros,
                        'ventana_abierta' => $ventana,
                        'puede_crear'    => false,
                        'paginacion'     => $this->_paginacion($total, $pagina),
                    ];

                default:
                    return $this->_vacio($tematicas, $subtematicas);
            }
        } catch (Exception $e) {
            error_log("listarProyectos(): " . $e->getMessage());
            return $this->_vacio();
        }
    }

    // 
    //  AJAX: subtémáticas dependientes de la temática seleccionada
    //  Llamar con: ?action=subtematicas&id_tematica=N
    // 
    public function subtematicasPorTematica(): void
    {
        global $conn;
        $id_tematica = max(0, (int)($_GET['id_tematica'] ?? 0));
        $modelo      = new principal($conn);
        header('Content-Type: application/json');
        echo json_encode($modelo->obtenerSubtematicas($id_tematica));
        exit;
    }

    // 
    //  Helpers privados
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
            'proyectos'      => [],
            'tematicas'      => $tematicas,
            'subtematicas'   => $subtematicas,
            'filtros'        => ['buscar' => '', 'modalidad' => '', 'id_tematica' => 0, 'id_subtematica' => 0, 'pagina' => 1],
            'ventana_abierta' => false,
            'puede_crear'    => false,
            'paginacion'     => $this->_paginacion(0, 1),
        ];
    }

    // 
    //  Badge de color por estado del proyecto
    // 
    public function badgeEstado(int $id_estadoP): string
    {
        return match ($id_estadoP) {
            2 => 'badge-estado-activo',
            5 => 'badge-estado-porcerrar',
            3 => 'badge-estado-poraprobar',
            1 => 'badge-estado-cierre',
            6 => 'badge-estado-vencido',
            default => 'badge-estado-default',
        };
    }

    // 
    //  Badge de color para modalidad
    // 
    public function badgeModalidad(string $modalidad): string
    {
        return match ($modalidad) {
            'virtual' => 'badge-modal-virtual',
            'fisico'  => 'badge-modal-fisico',
            'mixto'   => 'badge-modal-mixto',
            default   => '',
        };
    }


    /*APARTADO DE detalles_proyecto.php */

    // 
    //  Punto de entrada principal: devuelve todo lo que la vista necesita
    //
    //  $id_proyecto  → int  obtenido del GET['id'] ya validado en la vista
    //  $id_usuario   → int  de $_SESSION['id_usuario']
    //  $rol          → string  de $_SESSION['rol'] (en minúsculas)
    // 
    public function obtenerDatos(int $id_proyecto, int $id_usuario, string $rol): array
    {
        global $conn;

        $modelo = new principal($conn);

        // 1. Datos del proyecto
        $proyecto = $modelo->obtenerDetalle($id_proyecto);
        if ($proyecto === null) {
            return ['proyecto' => null];
        }

        // 2. Estado de membresía (solo relevante para estudiante, pero se calcula
        //    para todos para no duplicar lógica en la vista)
        $es_integrante = ($rol === 'estudiante')
            ? $modelo->esIntegrante($id_proyecto, $id_usuario)
            : false;

        // 3. Última solicitud del estudiante
        $solicitud = null;
        if ($rol === 'estudiante') {
            $solicitud = $modelo->obtenerUltimaSolicitud($id_proyecto, $id_usuario);
        }

        // 4. ¿La ventana de solicitud está abierta para este proyecto?
        //    Solo importa al estudiante; investigador/supervisor/profesor nunca ven el botón.
        $ventana_abierta = false;
        if ($rol === 'estudiante') {
            $ventana_abierta = $modelo->ventanaSolicitudAbiertaParaProyecto($id_proyecto);
        }

        // 5. ¿Puede el estudiante solicitar en este momento?
        //    Condiciones:
        //      a) rol estudiante
        //      b) ventana abierta
        //      c) no es integrante activo de ESTE proyecto
        //      d) cupo disponible en ESTE proyecto
        //      e) sin solicitud previa para ESTE proyecto, o la última fue rechazada
        //      f) no supera el límite de 3 proyectos activos en total          
        //      g) no tiene 3 o más solicitudes vigentes (en espera) en total   
        $puede_solicitar  = false;
        $cupo_disponible  = (int)($proyecto['lugares_disponibles'] ?? 0) > 0;
        $limite_alcanzado = false;
        $carga            = ['activos' => 0, 'en_espera' => 0]; // siempre disponible para la vista

        if ($rol === 'estudiante' && $ventana_abierta && !$es_integrante && $cupo_disponible) {
            $estado_sol = $solicitud['estado'] ?? null;

            if ($estado_sol === null || $estado_sol === 'rechazado') {
                $carga            = $modelo->obtenerCargaProyectosEstudiante($id_usuario);
                $limite_alcanzado = ($carga['activos'] + $carga['en_espera']) >= 3;
                $puede_solicitar  = !$limite_alcanzado;
            }
        } elseif ($rol === 'estudiante') {
            // Cargar igualmente para mostrar el contador en la nota aunque no pueda solicitar aquí
            $carga = $modelo->obtenerCargaProyectosEstudiante($id_usuario);
        }
        // 6. ¿Puede cancelar su solicitud?
        $puede_cancelar = false;
        if ($rol === 'estudiante' && !$es_integrante && $solicitud !== null) {
            $puede_cancelar = in_array($solicitud['estado'], ['pendiente', 'en_revision', 'correcciones', 'aceptado']);
        }

        return [
            'proyecto'        => $proyecto,
            'es_integrante'   => $es_integrante,
            'solicitud'       => $solicitud,          // null | ['id_solicitud_proyecto', 'estado']
            'ventana_abierta' => $ventana_abierta,
            'puede_solicitar' => $puede_solicitar,
            'puede_cancelar'  => $puede_cancelar,
            'carga'           => $carga,   // ← expuesto para la nota

        ];
    }

    // 
    //  Leer y decodificar el código de mensaje de solicitud del QueryString
    //  (?solicitud=sent | pending | accepted | error)
    //  Devuelve ['title'=>..., 'body'=>...] o null si no hay parámetro
    // 
    public function leerMensajeSolicitud(): ?array
    {
        if (!isset($_GET['solicitud'])) {
            return null;
        }

        return match ($_GET['solicitud']) {
            'sent'     => [
                'title' => '¡Solicitud enviada!',
                'body'  => 'Tu solicitud ha sido enviada correctamente. Será revisada por el investigador.',
                'tipo'  => 'success',
            ],
            'pending'  => [
                'title' => 'Solicitud pendiente',
                'body'  => 'Ya tienes una solicitud pendiente para este proyecto.',
                'tipo'  => 'warning',
            ],
            'accepted' => [
                'title' => 'Solicitud aceptada',
                'body'  => 'Ya fuiste aceptado anteriormente en este proyecto.',
                'tipo'  => 'info',
            ],
            'cancelled' => [
                'title' => 'Solicitud cancelada',
                'body'  => 'Tu solicitud ha sido cancelada exitosamente.',
                'tipo'  => 'info',
            ],
            default    => [
                'title' => 'Atención',
                'body'  => 'Ocurrió un problema. Intenta más tarde.',
                'tipo'  => 'error',
            ],
        };
    }
}
