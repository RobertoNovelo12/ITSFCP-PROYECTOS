<?php

/**
 * Rutas vía ?action= :
 *   GET  index                   → Estudiante: timeline de sus 3 etapas
 *   POST subirDocumento          → Estudiante: sube Carta Compromiso (Etapa 1)
 *   POST subirCartaTerminacion   → Estudiante: sube Carta de Terminación firmada (Etapa 3)
 *   POST enviarCorreccionesCarta → Estudiante: reenvía correcciones al supervisor
 *   POST actualizarEstado        → Investigador: aprueba/rechaza/corrige seguimiento_documento (AJAX JSON)
 *
 * Mensajes $_mapa reconocidos en la vista:
 *   exito_documento_subido       | error_archivo_invalido    | error_datos_incompletos
 *   exito_carta_enviada          | error_actividades_pendientes
 *   exito_correcciones_enviadas  | error_correcciones
 *   error_sin_permiso            | error_interno             | error_no_supervisor
 *   accion_no_permitida
 */

require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../Model/seguimiento_model.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';
require_once __DIR__ . '/../../../public/incluido/FileUploader.php';

class SeguimientoControlador extends BaseControlador
{
    private SeguimientoModelo $modelo;
    private mysqli $conn;

    public function __construct()
    {
        global $conn;
        $this->conn   = $conn;
        $this->modelo = new SeguimientoModelo($conn);
    }


    // 
    // HELPERS PRIVADOS DE ROL
    // 

    private function esEstudiante(string $rol): bool
    {
        return $rol === 'estudiante';
    }

    private function esInvestigador(string $rol): bool
    {
        return in_array($rol, ['investigador', 'profesor'], true);
    }

    private function idUsuario(): int
    {
        return intval($_SESSION['id_usuario'] ?? 0);
    }

    private function rol(): string
    {
        return strtolower($_SESSION['rol'] ?? '');
    }

    /**
     * URL de retorno a la vista de seguimiento del proyecto.
     */
    private function urlSeguimiento(int $id_proyecto): string
    {
        return '/Modules/Seguimiento/Views/index.php?id_proyectos=' . $id_proyecto;
    }


    // 
    // RESPUESTA JSON SEGURA (solo para actualizarEstado — acción del investigador)
    // 

    protected function json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }


    // 
    // INDEX — ESTUDIANTE (GET)
    // 

    /**
     * Carga los datos para la vista de seguimiento del estudiante.
     *
     * Bloqueos de etapas:
     *   Etapa 1 → siempre accesible (el alumno ya fue aceptado si llegó aquí).
     *   Etapa 2 → visible si Etapa 1 está en estado 'completado'.
     *   Etapa 3 → visible si Etapa 2 está completada (todas las tareas aprobadas).
     */
    public function index(int $id_usuario, string $rol, int $id_proyecto): array
    {
        $vacio = [
            'etapas'   => [],
            'proyecto' => null,
            'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
            'mensaje'  => null,
        ];

        if (!$this->esEstudiante($rol)) {
            return array_merge($vacio, ['mensaje' => 'Acceso no permitido.']);
        }

        if (!$id_proyecto) {
            return array_merge($vacio, ['mensaje' => 'Proyecto no especificado.']);
        }

        $proyecto = $this->modelo->ProyectoPorId($id_usuario, $id_proyecto);

        if (!$proyecto) {
            return array_merge($vacio, ['mensaje' => 'No tienes acceso a este proyecto.']);
        }

        $etapas      = $this->modelo->EtapasPorProyecto($id_proyecto, $id_usuario);
        $completadas = array_filter($etapas, fn($e) => $e['estado'] === 'completado');
        $total       = count($etapas);

        return [
            'etapas'   => $etapas,
            'proyecto' => $proyecto,
            'progreso' => [
                'completadas' => count($completadas),
                'total'       => $total,
                'pct'         => $total > 0 ? round((count($completadas) / $total) * 100) : 0,
            ],
            'mensaje'  => null,
        ];
    }


    // 
    // MÉTODOS DEL CONTROLADOR (extracto — reemplaza los tres métodos existentes)
    // 


    // 
    //  SUBIR DOCUMENTO — Etapa 1: Carta Compromiso (POST → redirigir)
    //  Ruta física: /storage/etapas/proyecto_{id}/
    // 

    public function subirDocumento(): void
    {
        try {
            $this->validarMetodo('POST');

            $id_usuario = $this->idUsuario();
            $rol        = $this->rol();

            if (!$this->esEstudiante($rol)) {
                $this->redirigir('sin_permiso', '/Modules/Principal/Views/index.php');
            }

            $id_proyecto       = intval($_POST['id_proyecto']       ?? 0);
            $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
            $id_plantilla      = intval($_POST['id_plantilla']      ?? 0) ?: null;
            $id_seg_previo     = intval($_POST['id_seguimiento']    ?? 0);

            $destino = $this->urlSeguimiento($id_proyecto);

            if (!$id_proyecto || !$id_tipo_documento) {
                $this->redirigir('error_datos_incompletos', $destino);
            }

            if (!$this->modelo->verificarProyectoUsuario($id_proyecto, $id_usuario)) {
                $this->redirigir('sin_permiso', $destino);
            }

            $uploader = new FileUploader("storage/etapas/proyecto_{$id_proyecto}");
            $allowedMimes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $maxSize = 10 * 1024 * 1024; // 10 MB
            $prefix = "est{$id_usuario}_td{$id_tipo_documento}";

            try {
                $fileInfo = $uploader->upload($_FILES['documento'], $allowedMimes, $maxSize, $prefix);
            } catch (RuntimeException $e) {
                error_log('[subirDocumento] FileUpload Error: ' . $e->getMessage());
                $this->redirigir('error_archivo_invalido', $destino);
            }


            if (!$id_seg_previo) {
                $id_seguimiento = $this->modelo->crearSeguimiento($id_proyecto, $id_tipo_documento, $id_usuario);
            } else {
                $id_seguimiento = $id_seg_previo;
                $this->modelo->actualizarEstadoEstudiante($id_seguimiento, 'proceso');
            }

            $id_etapa = $this->modelo->IdEtapaPorTipoDocumento($id_tipo_documento);

            $ok = $this->modelo->registrarDocumentoCentralizado(
                $id_seguimiento,
                $id_plantilla,
                $fileInfo['nombre_display'],
                $fileInfo['nombre_archivo'],
                $fileInfo['ruta_bd'],
                $fileInfo['mime'],
                $fileInfo['extension'],
                $fileInfo['tamano'],
                $id_usuario,
                $id_proyecto,
                $id_etapa
            );

            if (!$ok) {
                $this->redirigir('error_interno', $destino);
            }

            $this->redirigir('exito_documento_subido', $destino);
        } catch (Throwable $e) {
            error_log('[subirDocumento] ' . $e->getMessage());
            $id_proyecto = intval($_POST['id_proyecto'] ?? 0);
            $this->redirigir('error_interno', $this->urlSeguimiento($id_proyecto));
        }
    }


    // 
    //  SUBIR CARTA DE TERMINACIÓN — Etapa 3 (POST → redirigir)
    //  Crea registro en cierres_estudiante con estado 'finalizacion_pendiente'.
    // 

    public function subirCartaTerminacion(): void
    {
        try {
            $this->validarMetodo('POST');

            $id_usuario = $this->idUsuario();
            $rol        = $this->rol();

            if (!$this->esEstudiante($rol)) {
                $this->redirigir('sin_permiso', '/Modules/Principal/Views/index.php');
            }

            $id_proyecto = intval($_POST['id_proyecto'] ?? 0);
            $destino     = $this->urlSeguimiento($id_proyecto);

            if (!$id_proyecto) {
                $this->redirigir('error_datos_incompletos', '/Modules/Principal/Views/index.php');
            }

            if (!$this->modelo->verificarProyectoUsuario($id_proyecto, $id_usuario)) {
                $this->redirigir('sin_permiso', $destino);
            }

            if (!$this->modelo->todasSeccionesAprobadas($id_proyecto, $id_usuario)) {
                $this->redirigir('error_actividades_pendientes', $destino);
            }

            $uploader = new FileUploader("storage/etapas/proyecto_{$id_proyecto}");
            $allowedMimes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $maxSize = 10 * 1024 * 1024; // 10 MB
            $prefix = "carta_est{$id_usuario}";

            try {
                $fileInfo = $uploader->upload($_FILES['documento'], $allowedMimes, $maxSize, $prefix);
            } catch (RuntimeException $e) {
                error_log('[subirCartaTerminacion] FileUpload Error: ' . $e->getMessage());
                $this->redirigir('error_archivo_invalido', $destino);
            }


            $id_etapa = 3;

            $id_integrante = $this->modelo->getIdIntegrante($id_proyecto, $id_usuario);
            if (!$id_integrante) {
                $this->redirigir('error_interno', $destino);
            }

            $id_supervisor = $this->modelo->getIdSupervisorDelProyecto($id_proyecto);
            if (!$id_supervisor) {
                $this->redirigir('error_no_supervisor', $destino);
            }

            $cierre_previo = $this->modelo->CierreEstudiante($id_proyecto, $id_usuario);

            $id_documento = $this->modelo->registrarDocumentoCarta(
                $fileInfo['nombre_display'],
                $fileInfo['nombre_archivo'],
                $fileInfo['ruta_bd'],
                $fileInfo['mime'],
                $fileInfo['extension'],
                $fileInfo['tamano'],
                $id_usuario,
                $id_proyecto,
                $id_etapa
            );

            if (!$id_documento) {
                $this->redirigir('error_interno', $destino);
            }

            if (!$cierre_previo) {
                $id_cierre = $this->modelo->crearCierreEstudiante($id_integrante, $id_documento, $id_supervisor);
                $ok        = (bool)$id_cierre;
            } else {
                $this->modelo->desactivarDocumentoCarta((int)$cierre_previo['id_documento']);
                $ok = $this->modelo->reenviarCierreEstudiante((int)$cierre_previo['id_cierre_est'], $id_documento);
            }

            if (!$ok) {
                $this->redirigir('error_interno', $destino);
            }

            $this->modelo->actualizarEstadoProcesoCarta($id_integrante);

            $this->redirigir('exito_carta_enviada', $destino);
        } catch (Throwable $e) {
            error_log('[subirCartaTerminacion] ' . $e->getMessage());
            $id_proyecto = intval($_POST['id_proyecto'] ?? 0);
            $this->redirigir('error_interno', $this->urlSeguimiento($id_proyecto));
        }
    }


    // 
    //  ENVIAR CORRECCIONES CARTA — Etapa 3 (POST → redirigir)
    //  Solo envía comentario + archivo de apoyo opcional.
    //  No reemplaza la carta de terminación.
    // 

    public function enviarCorreccionesCarta(): void
    {
        try {
            $this->validarMetodo('POST');

            $id_usuario = $this->idUsuario();
            $rol        = $this->rol();

            if (!$this->esEstudiante($rol)) {
                $this->redirigir('sin_permiso', '/Modules/Principal/Views/index.php');
            }

            $id_cierre_est = intval($_POST['id_cierre_est'] ?? 0);
            $comentario    = trim($_POST['comentario']      ?? '');
            $id_proyecto   = intval($_POST['id_proyecto']   ?? 0);
            $destino       = $this->urlSeguimiento($id_proyecto);

            if (!$id_cierre_est || empty($comentario)) {
                $this->redirigir('error_correcciones', $destino);
            }

            $cierre = $this->modelo->CierrePorId($id_cierre_est);
            if (!$cierre || (int)$cierre['id_usuarios'] !== $id_usuario) {
                $this->redirigir('sin_permiso', $destino);
            }

            if (!in_array($cierre['estado'], ['rechazado', 'finalizacion_pendiente'], true)) {
                $this->redirigir('accion_no_permitida', $destino);
            }

            // Archivo adjunto opcional
            $id_documento_adjunto = null;

            if (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $id_proyecto_cierre = (int)$cierre['id_proyectos'];

                $uploader = new FileUploader("storage/etapas/proyecto_{$id_proyecto_cierre}");
                $allowedMimes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
                $maxSize = 10 * 1024 * 1024; // 10 MB
                $prefix = "corr_est{$id_usuario}";

                try {
                    $fileInfo = $uploader->upload($_FILES['archivo'], $allowedMimes, $maxSize, $prefix);
                } catch (RuntimeException $e) {
                    error_log('[enviarCorreccionesCarta] FileUpload Error: ' . $e->getMessage());
                    $this->redirigir('error_archivo_invalido', $destino);
                }

                $id_documento_adjunto = $this->modelo->registrarDocumentoCarta(
                    $fileInfo['nombre_display'],
                    $fileInfo['nombre_archivo'],
                    $fileInfo['ruta_bd'],
                    $fileInfo['mime'],
                    $fileInfo['extension'],
                    $fileInfo['tamano'],
                    $id_usuario,
                    $id_proyecto_cierre,
                    3
                );

                if (!$id_documento_adjunto) {
                    $this->redirigir('error_interno', $destino);
                }
            }

            $ok = $this->modelo->agregarComentarioCierre(
                $id_cierre_est,
                $id_usuario,
                $comentario,
                $id_documento_adjunto
            );

            if (!$ok) {
                $this->redirigir('error_correcciones', $destino);
            }

            $this->redirigir('exito_correcciones_enviadas', $destino);
        } catch (Throwable $e) {
            error_log('[enviarCorreccionesCarta] ' . $e->getMessage());
            $id_proyecto = intval($_POST['id_proyecto'] ?? 0);
            $this->redirigir('error_interno', $this->urlSeguimiento($id_proyecto));
        }
    }


    // 
    // ACTUALIZAR ESTADO — Investigador (AJAX POST → JSON)
    // Se mantiene como JSON porque es consumida por fetch() en la vista.
    // 

    public function actualizarEstado(): void
    {
        try {
            $this->validarMetodo('POST');

            $id_usuario = $this->idUsuario();
            $rol        = $this->rol();

            if (!$this->esInvestigador($rol)) {
                $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
            }

            $id_seguimiento = intval($_POST['id_seguimiento'] ?? 0);
            $estado         = trim($_POST['estado']           ?? '');
            $comentario     = trim($_POST['comentario']       ?? '');

            if (!$id_seguimiento || !in_array($estado, ['completado', 'rechazado', 'correcciones'], true)) {
                $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
            }

            if (!$this->modelo->verificarPermisoInvestigador($id_seguimiento, $id_usuario)) {
                $this->json(['ok' => false, 'msg' => 'No tienes permiso sobre este seguimiento.'], 403);
            }

            $ok = $this->modelo->actualizarEstadoSeguimiento($id_seguimiento, $estado, $comentario, $id_usuario);

            if ($ok && $estado === 'completado') {
                $seg = $this->modelo->getSegimientoPorId($id_seguimiento);
                if ($seg) {
                    $this->modelo->notificar(
                        (int)$seg['id_usuarios'],
                        'Reporte Final aprobado',
                        'El investigador aprobó tu Reporte Final. Ya puedes descargar y subir tu Carta de Terminación.',
                        '/Modules/Seguimiento/Views/index.php?id_proyectos=' . $seg['id_proyectos']
                    );
                }
            }

            $msgs = [
                'completado'   => 'Documento aprobado correctamente.',
                'correcciones' => 'Correcciones solicitadas al estudiante.',
                'rechazado'    => 'Documento rechazado.',
            ];

            $this->json([
                'ok'  => $ok,
                'msg' => $ok ? ($msgs[$estado] ?? 'Actualizado.') : 'Error al actualizar.',
            ]);
        } catch (Throwable $e) {
            error_log('[actualizarEstado] ' . $e->getMessage());
            $this->json(['ok' => false, 'msg' => 'Error interno al actualizar el estado.'], 500);
        }
    }


    // 
    // HELPERS PÚBLICOS PARA VISTAS EXTERNAS
    // 

    public function contarTareasTotales($id_proyecto, $id_estudiante): int
    {
        return $this->modelo->contarTareasTotales($id_proyecto, $id_estudiante);
    }

    public function getDatosSeguimientoEstudiante(
        int $id_proyecto,
        int $id_estudiante,
        int $id_investigador
    ): array {
        $solicitud = $this->modelo->getSolicitudPorEstudianteProyecto($id_estudiante, $id_proyecto);
        $e1_estado = $solicitud ? $solicitud['estado'] : 'pendiente';

        $total     = $this->modelo->contarTareasTotales($id_proyecto, $id_estudiante);
        $aprobadas = $this->modelo->contarTareasAprobadas($id_proyecto, $id_estudiante);
        $fase2_ok  = $total > 0 && $aprobadas >= $total;

        if ($total === 0)        $e2_estado = 'pendiente';
        elseif ($fase2_ok)       $e2_estado = 'completado';
        elseif ($aprobadas > 0)  $e2_estado = 'proceso';
        else                     $e2_estado = 'pendiente';

        $cierre    = $this->modelo->CierreEstudiante($id_proyecto, $id_estudiante);
        $e3_estado = !$cierre ? 'pendiente' : match ($cierre['estado']) {
            'pendiente'              => 'finalizacion_pendiente',
            'finalizacion_pendiente' => 'finalizacion_pendiente',
            'aprobado'               => 'completado',
            'rechazado'              => 'rechazado',
            default                  => 'pendiente',
        };

        $documentos = $this->modelo->getDocumentosEtapaEstudiante($id_proyecto, $id_estudiante);

        return [
            'e1_estado'  => $e1_estado,
            'e2_estado'  => $e2_estado,
            'e3_estado'  => $e3_estado,
            'fase2_ok'   => $fase2_ok,
            'cierre'     => $cierre,
            'documentos' => $documentos,
        ];
    }

    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        return $this->modelo->todasSeccionesAprobadas($id_proyecto, $id_estudiante);
    }



    // 
    // STUBS requeridos por otras vistas
    // 

    public function filtros(int $id_usuario, string $rol): array
    {
        return [];
    }
    public function encabezados(string $rol): array
    {
        return [];
    }
    public function datosopciones(string $rol, array $filtros): array
    {
        return [];
    }
}
