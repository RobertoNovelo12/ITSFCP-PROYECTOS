<?php
// Modelos/Solicitud.php

require_once __DIR__ . '/../Repositorios/SolicitudRepositorio.php';

/**
 * Solicitud (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo de solicitudes
 * de integración a proyecto (secciones investigador y estudiante).
 * Delega toda ejecución SQL a SolicitudRepositorio.
 *
 * La construcción del WHERE dinámico permanece aquí como lógica de dominio.
 */
class Solicitud
{
    private SolicitudRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SolicitudRepositorio($conn);
    }


    // 
    // SECCIÓN A — INVESTIGADOR
    // 

    public function registrarDocumento(
        string  $nombre,
        string  $nombre_archivo,
        string  $ruta,
        string  $tipo_mime,
        string  $extension,
        int     $tamano_bytes,
        string  $tipo,
        string  $visibilidad,
        int     $id_usuario,
        int     $id_proyectos    = 0,
        ?int    $id_etapa        = null,
        int     $version         = 1,
        ?int    $id_plantilla    = null,
        ?int    $id_seguimiento  = null
    ): int {
        $visibilidad = strtolower(trim($visibilidad));
        if (!in_array($visibilidad, ['publico', 'privado'], true)) {
            throw new Exception('Visibilidad inválida');
        }

        $id_proyecto_val = $id_proyectos ?: null;

        $id = $this->repo->insertarDocumento(
            $nombre,
            $nombre_archivo,
            $ruta,
            $tipo_mime,
            $extension,
            $tamano_bytes,
            $tipo,
            $visibilidad,
            $id_usuario,
            $id_proyecto_val,
            $id_etapa,
            $version,
            $id_plantilla,
            $id_seguimiento
        );

        if ($id === 0) {
            throw new Exception('No se pudo registrar el documento en documentos_subidos');
        }

        return $id;
    }

    public function periodosDelInvestigador(int $id): array
    {
        return $this->repo->listarPeriodosInvestigador($id);
    }

    public function resumen(int $id, ?int $id_periodo = null): array
    {
        $where  = 'WHERE p.id_investigador = ?';
        $params = [$id];
        $types  = 'i';

        if ($id_periodo) {
            $where   .= ' AND p.id_periodos = ?';
            $params[] = $id_periodo;
            $types   .= 'i';
        }

        return $this->repo->resumen($id, $where, $params, $types);
    }

    public function contarSolicitudes(int $id, array $f): int
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);

        return $this->repo->contarSolicitudes($where, $params, $types);
    }

    public function obtenerSolicitudes(int $id, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->construirWhere($id, $f);

        return $this->repo->listarSolicitudes($where, $params, $types, $desde, $limite);
    }

    public function obtenerDetalle(int $id): ?array
    {
        return $this->repo->buscarDetalle($id);
    }

    public function verificarPermiso(int $id, int $inv): bool
    {
        return $this->repo->verificarPermiso($id, $inv);
    }

    public function marcarEnRevision(int $id): void
    {
        $this->repo->marcarEnRevision($id);
    }

    public function aceptar(int $id): bool
    {
        $this->repo->aceptar($id);

        return true;
    }

    public function pedirCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->repo->pedirCorrecciones($id, $id_usuario, $comentario, $id_documento);

        return true;
    }

    public function rechazar(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->repo->rechazar($id, $id_usuario, $comentario, $id_documento);

        return true;
    }

    public function obtenervencido(): array
    {
        return $this->repo->obtenerVencidos();
    }

    public function vencido(int $id): void
    {
        $this->repo->marcarVencido($id);
    }

    public function obtenerComentarios(int $id): array
    {
        return $this->repo->listarComentarios($id);
    }

    public function obtenerDatosSolicitud(int $id_solicitud): ?array
    {
        return $this->repo->buscarDatosSolicitud($id_solicitud);
    }

    public function vincularTareasAlNuevoEstudiante(int $id_proyectos, int $id_usuario): void
    {
        $this->repo->vincularTareasEstudiante($id_proyectos, $id_usuario);
    }

    public function vincularEstudianteProyecto(int $id_proyectos, int $id_usuario): void
    {
        $this->repo->vincularEstudianteProyecto($id_proyectos, $id_usuario);
    }

    public function registrarHistorialUsuario(
        int    $id_proyectos,
        int    $id_estudiante,
        string $accion,
        string $motivo,
        int    $realizado_por
    ): void {
        $this->repo->registrarHistorialUsuario($id_proyectos, $id_estudiante, $accion, $motivo, $realizado_por);
    }

    public function actualizarEstadoCierre(
        int    $id_seguimiento,
        string $estado,
        int    $id_usuario,
        string $comentario = ''
    ): bool {
        return $this->repo->actualizarEstadoCierre($id_seguimiento, $estado, $id_usuario, $comentario);
    }

    public function DatosSeguimientoEstudiante(int $id_proyectos, int $id_estudiante, int $id_investigador): array
    {
        $sol       = $this->repo->buscarEstadoSolicitud($id_proyectos, $id_estudiante);
        $e1_estado = $sol['estado'] ?? 'pendiente';

        $act           = $this->repo->buscarActividadesEstudiante($id_proyectos, $id_estudiante);
        $total_act     = (int)($act['total']     ?? 0);
        $aprobadas_act = (int)($act['aprobadas'] ?? 0);
        $fase2_ok      = $total_act > 0 && $aprobadas_act >= 11;
        $e2_estado     = $fase2_ok ? 'completado' : ($aprobadas_act > 0 ? 'proceso' : 'pendiente');

        $cierre        = $this->repo->buscarSeguimientoCierre($id_proyectos, $id_estudiante);
        $e3_estado     = $cierre['estado']         ?? 'pendiente';
        $id_seg_cierre = $cierre['id_seguimiento']  ?? null;

        $documentos = $this->repo->listarDocumentosEstudiante($id_estudiante, $id_proyectos);

        return [
            'e1_estado'             => $e1_estado,
            'e2_estado'             => $e2_estado,
            'e3_estado'             => $e3_estado,
            'fase2_ok'              => $fase2_ok,
            'id_seguimiento_cierre' => $id_seg_cierre,
            'documentos'            => $documentos,
            'actividades_total'     => $total_act,
            'actividades_aprobadas' => $aprobadas_act,
        ];
    }

    public function enviarCorrecciones(int $id, int $id_usuario, string $comentario, ?int $id_documento = null): bool
    {
        $this->repo->enviarCorrecciones($id, $id_usuario, $comentario, $id_documento);

        return true;
    }

    public function proyectosDelInvestigador(int $id): array
    {
        return $this->repo->listarProyectosInvestigador($id);
    }

    public function getEtapasPorProyecto(int $id_proyectos, int $id_usuario): array
    {
        return $this->repo->listarEtapasPorProyecto($id_proyectos, $id_usuario);
    }


    // 
    // SECCIÓN B — ESTUDIANTE
    // 

    public function obtenerPlantillaCartaCompromiso(): ?array
    {
        return $this->repo->buscarPlantillaCartaCompromiso();
    }

    public function obtenerPlantillaPorId(int $id_plantilla): ?array
    {
        return $this->repo->buscarPlantillaPorId($id_plantilla);
    }

    public function crearSolicitud(
        int     $id_proyectos,
        int     $id_estudiante,
        int     $id_periodo,
        ?float  $promedio,
        string  $motivacion,
        string  $experiencia,
        ?int    $semestre,
        ?int    $id_doc_cv = null
    ): int {
        return $this->repo->crearSolicitud(
            $id_proyectos,
            $id_estudiante,
            $id_periodo,
            $promedio,
            $motivacion,
            $experiencia,
            $semestre,
            $id_doc_cv
        );
    }

    public function crearSeguimientoCartaCompromiso(int $id_proyectos, int $id_estudiante, string $estado = 'pendiente'): int
    {
        return $this->repo->crearSeguimientoCartaCompromiso($id_proyectos, $id_estudiante, $estado);
    }

    public function vincularDocumentoSeguimiento(int $id_documento, int $id_seguimiento): void
    {
        $this->repo->vincularDocumentoSeguimiento($id_documento, $id_seguimiento);
    }

    public function marcarSeguimientoEnProceso(int $id_seguimiento): void
    {
        $this->repo->marcarSeguimientoEnProceso($id_seguimiento);
    }

    public function actualizarDocumentoSolicitud(int $id_solicitud, int $id_documento): void
    {
        $this->repo->actualizarDocumentoSolicitud($id_solicitud, $id_documento);
    }

    public function cancelarSolicitud(int $id_solicitud, int $id_estudiante): bool
    {
        return $this->repo->cancelarSolicitud($id_solicitud, $id_estudiante);
    }

    public function obtenerSolicitudEstudiante(int $id_proyectos, int $id_estudiante): ?array
    {
        return $this->repo->buscarSolicitudEstudiante($id_proyectos, $id_estudiante);
    }

    public function obtenerPeriodoActivoParaProyecto(int $id_proyecto): ?array
    {
        return $this->repo->buscarPeriodoActivoParaProyecto($id_proyecto);
    }

    public function obtenerProyecto(int $id_proyecto): ?array
    {
        return $this->repo->buscarProyecto($id_proyecto);
    }
    public function obtenerEstudiante(int $id_usuario): ?array
    {
        return $this->repo->buscarEstudiante($id_usuario);
    }
    public function obtenerCarreras(): array
    {
        return $this->repo->listarCarreras();
    }
    public function obtenerTituloProyecto(int $id_proyecto): string
    {
        return $this->repo->buscarTituloProyecto($id_proyecto);
    }
    public function obtenerSupervisoresActivos(): array
    {
        return $this->repo->listarSupervisoresActivos();
    }
    public function periodoactualSolicitud(): ?array
    {
        return $this->repo->buscarPeriodoActualSolicitud();
    }

    public function insertarNotificacion(int $id_usuario, string $titulo, string $contenido, string $enlace = ''): void
    {
        $this->repo->insertarNotificacion($id_usuario, $titulo, $contenido, $enlace);
    }


    // 
    // WHERE DINÁMICO (lógica de dominio)
    // 

    private function construirWhere(int $id, array $f): array
    {
        $cond   = ['p.id_investigador = ?'];
        $params = [$id];
        $types  = 'i';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }
        if (!empty($f['estado'])) {
            $cond[]   = 'sp.estado = ?';
            $params[] = $f['estado'];
            $types   .= 's';
        }
        if (!empty($f['buscar'])) {
            $cond[]   = '(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)';
            $b        = '%' . $f['buscar'] . '%';
            array_push($params, $b, $b, $b);
            $types   .= 'sss';
        }
        if (!empty($f['proyecto'])) {
            $cond[]   = 'sp.id_proyectos = ?';
            $params[] = (int)$f['proyecto'];
            $types   .= 'i';
        }
        if (!empty($f['semestre'])) {
            $cond[]   = 'e.semestre = ?';
            $params[] = (int)$f['semestre'];
            $types   .= 'i';
        }
        if (!empty($f['fecha_desde'])) {
            $cond[]   = 'sp.fecha_envio >= ?';
            $params[] = $f['fecha_desde'];
            $types   .= 's';
        }
        if (!empty($f['fecha_hasta'])) {
            $cond[]   = 'sp.fecha_envio <= ?';
            $params[] = $f['fecha_hasta'];
            $types   .= 's';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}
