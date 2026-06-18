<?php
// Modelos/MisSolicitudes.php

require_once __DIR__ . '/../Repository/mis_solicitudes_repositorio.php';

/**
 * MisSolicitudes (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo "Mis Solicitudes".
 * Delega toda ejecución SQL a MisSolicitudesRepositorio.
 *
 * La construcción del WHERE dinámico permanece aquí como lógica de dominio;
 * el repositorio recibe el WHERE ya construido como parámetro string.
 */
class MisSolicitudes
{
    private MisSolicitudesRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new MisSolicitudesRepositorio($conn);
    }


    // 
    // CATÁLOGO
    // 

    public function periodosDelEstudiante(int $id_estudiante): array
    {
        return $this->repo->listarPeriodosEstudiante($id_estudiante);
    }


    // 
    // RESUMEN
    // 

    public function resumen(int $id_estudiante, ?int $id_periodo = null): array
    {
        $wherePeriodo = $id_periodo ? 'AND p.id_periodos = ?' : '';
        $types        = $id_periodo ? 'ii' : 'i';
        $params       = $id_periodo ? [$id_estudiante, $id_periodo] : [$id_estudiante];

        return $this->repo->resumen($id_estudiante, $wherePeriodo, $params, $types);
    }

    //Mantenimiento automático de estados: marcar como vencidos las solicitudes a proyectos que hayan pasado su fecha límite
    public function marcarVencidos(): void
    {
        $this->repo->marcarSolicitudesProyectosVencidos();
    }


    // 
    // LISTADO PAGINADO
    // 

    public function contarSolicitudes(int $id_estudiante, array $filtros): int
    {
        [$where, $params, $types] = $this->_construirWhere($id_estudiante, $filtros);

        return $this->repo->contarSolicitudes($where, $params, $types);
    }

    public function obtenerSolicitudes(int $id_estudiante, array $filtros, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->_construirWhere($id_estudiante, $filtros);

        return $this->repo->listarSolicitudes($where, $params, $types, $desde, $limite);
    }


    // 
    // DETALLE
    // 

    public function obtenerDetalle(int $id_solicitud, int $id_estudiante): ?array
    {
        return $this->repo->buscarDetalle($id_solicitud, $id_estudiante);
    }


    // 
    // HILO DE COMENTARIOS
    // 

    public function obtenerHilo(int $id_solicitud, int $id_estudiante): array
    {
        if (!$this->repo->verificarPertenencia($id_solicitud, $id_estudiante)) {
            return [];
        }

        return $this->repo->listarHilo($id_solicitud);
    }


    // 
    // VALIDACIÓN PREVIA A GUARDAR RESPUESTA
    // 

    public function obtenerSolicitud(int $id_solicitud, int $id_estudiante): ?array
    {
        return $this->repo->buscarSolicitud($id_solicitud, $id_estudiante);
    }


    // 
    // GUARDAR RESPUESTA
    // 

    public function guardarRespuesta(
        int    $id_solicitud,
        int    $id_estudiante,
        int    $id_proyecto,
        string $comentario,
        ?array $archivo
    ): bool {
        return $this->repo->guardarRespuesta(
            $id_solicitud,
            $id_estudiante,
            $id_proyecto,
            $comentario,
            $archivo
        );
    }


    // 
    // CANCELAR
    // 

    public function cancelarSolicitud(int $id_solicitud, int $id_estudiante): bool
    {
        return $this->repo->cancelarSolicitud($id_solicitud, $id_estudiante);
    }


    // 
    // WHERE DINÁMICO (lógica de dominio)
    // 

    private function _construirWhere(int $id_estudiante, array $f): array
    {
        $cond   = ['sp.id_estudiante = ?'];
        $params = [$id_estudiante];
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
            $cond[]   = "(p.titulo LIKE ? OR CONCAT(u.nombre,' ',u.apellido_paterno) LIKE ?)";
            $b        = '%' . $f['buscar'] . '%';
            $params[] = $b;
            $params[] = $b;
            $types   .= 'ss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}
