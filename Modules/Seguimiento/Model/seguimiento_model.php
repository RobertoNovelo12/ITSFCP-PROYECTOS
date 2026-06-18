<?php
// Modelos/SeguimientoModelo.php

require_once __DIR__ . '/../Repository/seguimiento_repository.php';

/**
 * SeguimientoModelo
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de seguimiento
 * de documentación. Delega toda ejecución SQL a SeguimientoRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class SeguimientoModelo
{
    private SeguimientoRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SeguimientoRepositorio($conn);
    }


    // 
    // PROYECTO
    // 

    /**
     * Datos del proyecto visibles para el estudiante (debe estar activa/concluida).
     * Incluye estado_proceso, id_integrante y datos de solicitud de integración.
     *
     * @return array|null
     */
    public function ProyectoPorId(int $id_usuario, int $id_proyecto): ?array
    {
        return $this->repo->proyectoPorId($id_usuario, $id_proyecto);
    }


    // 
    // ETAPAS — vista del estudiante
    // 

    /**
     * Construye las 3 etapas con su estado real y, para Etapa 1,
     * el documento subido por el estudiante (carta compromiso firmada).
     *
     * Etapa 1: siempre 'completado' al llegar aquí (el alumno ya fue aceptado).
     *          Se expone el documento subido para descarga. NO se permite re-subir.
     * Etapa 2: calculado desde tareas aprobadas.
     * Etapa 3: derivado de cierres_estudiante.
     *          'finalizacion_pendiente' cuando carta subida y supervisor no responde.
     *          'rechazado' cuando supervisor rechazó → estudiante puede corregir.
     *
     * @return array[]
     */
    public function EtapasPorProyecto(int $id_proyecto, int $id_usuario): array
    {
        $estadoIntegrante = $this->repo->estadoIntegrante($id_proyecto, $id_usuario);

        if ($estadoIntegrante && in_array($estadoIntegrante['estado'], ['baja', 'cancelado'])) {
            return $this->getEtapasBaja($id_proyecto, $id_usuario, $estadoIntegrante);
        }

        $rows = $this->repo->etapasConSeguimiento($id_proyecto, $id_usuario);

        foreach ($rows as &$etapa) {
            $orden = (int)$etapa['orden'];

            if ($orden === 1) {
                $etapa['estado']           = 'completado';
                $etapa['documento_subido'] = $this->repo->datosSeguimientoEstudiante($id_proyecto, $id_usuario);

            } elseif ($orden === 2) {
                $total     = $this->repo->contarTareasTotales($id_proyecto, $id_usuario);
                $aprobadas = $this->repo->contarTareasAprobadas($id_proyecto, $id_usuario);

                $etapa['estado']           = ($total === 0 || $aprobadas < $total) ? 'proceso' : 'completado';
                $etapa['tareas_total']     = $total;
                $etapa['tareas_aprobadas'] = $aprobadas;
                $etapa['id_seguimiento']   = null;
                $etapa['id_plantilla']     = null;
                $etapa['documento_subido'] = null;

            } elseif ($orden === 3) {
                $cierre                    = $this->repo->cierreEstudiante($id_proyecto, $id_usuario);
                $etapa['documento_subido'] = null;
                $etapa['cierre']           = $cierre;

                if (!$cierre) {
                    $etapa2_completa    = $this->todasSeccionesAprobadas($id_proyecto, $id_usuario);
                    $proyecto_en_cierre = $this->repo->proyectoPermiteCierreEstudiante($id_proyecto);

                    if (!$etapa2_completa) {
                        $etapa['estado']         = 'bloqueado';
                        $etapa['motivo_bloqueo'] = 'Debes completar todas tus actividades primero.';
                    } elseif (!$proyecto_en_cierre) {
                        $etapa['estado']         = 'esperando_cierre';
                        $etapa['motivo_bloqueo'] = 'Tus actividades están completas. En espera de que el investigador inicie el cierre del proyecto.';
                    } else {
                        $etapa['estado'] = 'pendiente';
                    }
                } else {
                    $etapa['estado'] = match ($cierre['estado']) {
                        'pendiente'              => 'finalizacion_pendiente',
                        'finalizacion_pendiente' => 'finalizacion_pendiente',
                        'aprobado'               => 'completado',
                        'rechazado'              => 'rechazado',
                        default                  => 'pendiente',
                    };
                    $etapa['comentario_supervisor'] = $cierre['comentarios'] ?? null;
                    if (!empty($cierre['id_documento'])) {
                        $etapa['documento_subido'] = $this->repo->documentoPorId((int)$cierre['id_documento']);
                    }
                }
            }
        }
        unset($etapa);

        return $rows;
    }

    /**
     * Construye las etapas en modo "baja/cancelado".
     * Muestra hasta dónde llegó el estudiante con estado visual de cierre.
     *
     * @return array[]
     */
    private function getEtapasBaja(int $id_proyecto, int $id_usuario, array $estadoIntegrante): array
    {
        $rows = $this->repo->etapasConSeguimientoBaja($id_proyecto, $id_usuario);

        $motivo     = $estadoIntegrante['motivo_baja'] ?? 'Participación finalizada';
        $fecha_baja = $estadoIntegrante['fecha_baja']  ?? null;
        $es_vencido = str_contains(strtolower($motivo), 'vencido');

        $total_t     = $this->repo->contarTareasTotales($id_proyecto, $id_usuario);
        $aprobadas_t = $this->repo->contarTareasAprobadas($id_proyecto, $id_usuario);
        $etapa2_ok   = $total_t > 0 && $aprobadas_t >= $total_t;

        foreach ($rows as &$etapa) {
            $orden = (int)$etapa['orden'];

            $etapa['estado_baja']      = $estadoIntegrante['estado'];
            $etapa['motivo_baja']      = $motivo;
            $etapa['fecha_baja']       = $fecha_baja;
            $etapa['es_vencido']       = $es_vencido;
            $etapa['documento_subido'] = null;
            $etapa['cierre']           = null;

            if ($orden === 1) {
                $etapa['estado']           = 'completado';
                $etapa['documento_subido'] = $this->repo->datosSeguimientoEstudiante($id_proyecto, $id_usuario);
            } elseif ($orden === 2) {
                $etapa['tareas_total']     = $total_t;
                $etapa['tareas_aprobadas'] = $aprobadas_t;
                $etapa['id_seguimiento']   = null;
                $etapa['id_plantilla']     = null;
                $etapa['estado']           = $etapa2_ok ? 'completado' : 'baja_incompleta';
            } elseif ($orden === 3) {
                $etapa['estado'] = 'baja_incompleta';
            }
        }
        unset($etapa);

        return $rows;
    }

    /**
     * Verifica si el proyecto permite que el estudiante suba su Carta de Terminación.
     */
    public function proyectoPermiteCierreEstudiante(int $id_proyecto): bool
    {
        return $this->repo->proyectoPermiteCierreEstudiante($id_proyecto);
    }


    // 
    // DOCUMENTOS
    // 

    /** @return array|null */
    public function DatosSeguimientoEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        return $this->repo->datosSeguimientoEstudiante($id_proyecto, $id_usuario);
    }

    /** @return array|null */
    public function getDocumentoPorId(int $id_documento): ?array
    {
        return $this->repo->documentoPorId($id_documento);
    }

    /** @return array[] */
    public function getDocumentosEtapaEstudiante(int $id_proyecto, int $id_usuario): array
    {
        return $this->repo->documentosEtapaEstudiante($id_proyecto, $id_usuario);
    }

    /** @return bool */
    public function registrarDocumentoCentralizado(
        int     $id_seguimiento,
        ?int    $id_plantilla,
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        int     $id_usuario,
        int     $id_proyecto,
        ?int    $id_etapa
    ): bool {
        return $this->repo->registrarDocumentoCentralizado(
            $id_seguimiento, $id_plantilla, $nombre, $nombre_archivo,
            $ruta, $tipo_mime, $extension, $tamano_bytes,
            $id_usuario, $id_proyecto, $id_etapa
        );
    }

    /**
     * Registra Carta de Terminación. Devuelve id_documento generado (0 si falló).
     *
     * @return int
     */
    public function registrarDocumentoCarta(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        int     $id_usuario,
        int     $id_proyecto,
        int     $id_etapa
    ): int {
        return $this->repo->registrarDocumentoCarta(
            $nombre, $nombre_archivo, $ruta, $tipo_mime, $extension,
            $tamano_bytes, $id_usuario, $id_proyecto, $id_etapa
        );
    }

    /** @return bool */
    public function desactivarDocumentoCarta(int $id_documento): bool
    {
        return $this->repo->desactivarDocumentoCarta($id_documento);
    }


    // 
    // COMENTARIOS DE CORRECCIONES — Etapa 3
    // 

    /** @return array[] */
    public function ComentariosCierre(int $id_cierre_est): array
    {
        return $this->repo->comentariosCierre($id_cierre_est);
    }

    /**
     * Agrega un comentario del estudiante y reinicia el estado del cierre
     * a 'finalizacion_pendiente' para re-revisión del supervisor.
     *
     * @return bool
     */
    public function agregarComentarioCierre(
        int     $id_cierre_est,
        int     $id_usuario,
        string  $comentario,
        ?int $id_documento_adjunto = null
    ): bool {
        try {
            $this->repo->insertarComentarioCierre(
                $id_cierre_est, $id_usuario, $comentario,
                $id_documento_adjunto
            );
            $this->repo->reiniciarEstadoCierre($id_cierre_est);
            return true;
        } catch (Exception $e) {
            error_log('agregarComentarioCierre: ' . $e->getMessage());
            return false;
        }
    }


    // 
    // CIERRES_ESTUDIANTE (Etapa 3)
    // 

    /** @return array|null */
    public function CierreEstudiante(int $id_proyecto, int $id_usuario): ?array
    {
        return $this->repo->cierreEstudiante($id_proyecto, $id_usuario);
    }

    /** @return array|null */
    public function CierrePorId(int $id_cierre_est): ?array
    {
        return $this->repo->cierrePorId($id_cierre_est);
    }

    /** @return int|null */
    public function getIdIntegrante(int $id_proyecto, int $id_usuario): ?int
    {
        return $this->repo->idIntegrante($id_proyecto, $id_usuario);
    }

    /** @return int|null */
    public function getIdSupervisorDelProyecto(int $id_proyecto): ?int
    {
        return $this->repo->idSupervisorDelProyecto($id_proyecto);
    }

    /**
     * Crea el registro en cierres_estudiante.
     *
     * @return int  id_cierre_est generado.
     */
    public function crearCierreEstudiante(int $id_integrante, int $id_documento, int $id_supervisor): int
    {
        return $this->repo->crearCierreEstudiante($id_integrante, $id_documento, $id_supervisor);
    }

    /** @return bool */
    public function reenviarCierreEstudiante(int $id_cierre_est, int $id_documento): bool
    {
        return $this->repo->reenviarCierreEstudiante($id_cierre_est, $id_documento);
    }

    /** @return bool */
    public function actualizarEstadoProcesoCarta(int $id_integrante): bool
    {
        return $this->repo->actualizarEstadoProcesoCarta($id_integrante);
    }


    // 
    // SEGUIMIENTO_DOCUMENTO
    // 

    /** @return array|null */
    public function getSegimientoReporteFinal(int $id_proyecto, int $id_usuario): ?array
    {
        return $this->repo->seguimientoReporteFinal($id_proyecto, $id_usuario);
    }

    /** @return array|null */
    public function getSegimientoPorId(int $id_seguimiento): ?array
    {
        return $this->repo->seguimientoPorId($id_seguimiento);
    }

    /**
     * Crea seguimiento_documento en estado 'proceso'.
     *
     * @return int  id_seguimiento generado.
     */
    public function crearSeguimiento(int $id_proyecto, int $id_tipo_documento, int $id_usuario): int
    {
        return $this->repo->crearSeguimiento($id_proyecto, $id_tipo_documento, $id_usuario);
    }

    /** @return bool */
    public function actualizarEstadoEstudiante(int $id, string $estado): bool
    {
        return $this->repo->actualizarEstadoEstudiante($id, $estado);
    }

    /** @return bool */
    public function actualizarEstadoSeguimiento(
        int    $id_seg,
        string $estado,
        string $comentario,
        int    $id_rev
    ): bool {
        return $this->repo->actualizarEstadoSeguimiento($id_seg, $estado, $comentario, $id_rev);
    }

    /** @return bool */
    public function verificarPermisoInvestigador(int $id_seg, int $id_inv): bool
    {
        return $this->repo->verificarPermisoInvestigador($id_seg, $id_inv);
    }


    // 
    // SOLICITUDES DE INTEGRACIÓN
    // 

    /** @return array|null */
    public function getSolicitudPorEstudianteProyecto(int $id_estudiante, int $id_proyecto): ?array
    {
        return $this->repo->solicitudPorEstudianteProyecto($id_estudiante, $id_proyecto);
    }


    // 
    // TIPO_DOCUMENTO — utilidades
    // 

    /** @return int|null */
    public function IdEtapaPorTipoDocumento(int $id_tipo_documento): ?int
    {
        return $this->repo->idEtapaPorTipoDocumento($id_tipo_documento);
    }


    // 
    // TAREAS — Etapa 2
    // 

    /** @return int */
    public function contarTareasTotales(int $id_proyecto, int $id_estudiante): int
    {
        return $this->repo->contarTareasTotales($id_proyecto, $id_estudiante);
    }

    /** @return int */
    public function contarTareasAprobadas(int $id_proyecto, int $id_estudiante): int
    {
        return $this->repo->contarTareasAprobadas($id_proyecto, $id_estudiante);
    }

    /**
     * True si todas las tareas del estudiante en el proyecto están aprobadas.
     *
     * @return bool
     */
    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        $total     = $this->repo->contarTareasTotales($id_proyecto, $id_estudiante);
        $aprobadas = $this->repo->contarTareasAprobadas($id_proyecto, $id_estudiante);
        return $total > 0 && $aprobadas >= $total;
    }


    // 
    // PROYECTOS_USUARIOS + HISTORIAL
    // 

    /** @return bool */
    public function verificarProyectoUsuario(int $id_proyecto, int $id_usuario): bool
    {
        return $this->repo->verificarProyectoUsuario($id_proyecto, $id_usuario);
    }

    /**
     * Marca al estudiante como 'concluido' en proyectos_usuarios e inserta historial.
     *
     * @return bool
     */
    public function marcarProyectoUsuarioConcluido(
        int    $id_proyecto,
        int    $id_estudiante,
        int    $realizado_por,
        string $motivo = 'Proyecto concluido — cierre aprobado por supervisor'
    ): bool {
        $id_ep = $this->repo->idEstadoProcesoLiberado();
        $this->repo->marcarIntegranteConcluido($id_proyecto, $id_estudiante, $id_ep);
        $this->repo->insertarHistorial($id_proyecto, $id_estudiante, 'concluido', $motivo, $realizado_por);
        return true;
    }

    /** @return bool */
    public function registrarHistorialCartaRechazada(
        int    $id_proyecto,
        int    $id_estudiante,
        string $motivo,
        int    $realizado_por
    ): bool {
        return $this->repo->insertarHistorial(
            $id_proyecto, $id_estudiante, 'carta_rechazada', $motivo, $realizado_por
        );
    }


    // 
    // NOTIFICACIONES
    // 

    public function notificar(int $id_usuario, string $titulo, string $contenido, string $enlace = ''): void
    {
        $this->repo->notificar($id_usuario, $titulo, $contenido, $enlace);
    }
}