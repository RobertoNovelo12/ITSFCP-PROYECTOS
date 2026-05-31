<?php
// Modelos/SupervisorModelo.php

require_once __DIR__ . '/../Repositorios/SupervisorRepositorio.php';

/**
 * SupervisorModelo
 *
 * Responsabilidad exclusiva: lógica de negocio del panel de supervisor
 * (dashboard de solo lectura). Delega toda ejecución SQL a SupervisorRepositorio.
 *
 * No extiende BaseModelo porque no ejecuta SQL directamente.
 */
class SupervisorModelo
{
    private SupervisorRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new SupervisorRepositorio($conn);
    }


    // 
    // AUXILIARES PARA FILTROS (selects)
    // 

    public function obtenerPeriodos(): array
    {
        return $this->repo->obtenerPeriodos();
    }

    public function obtenerInvestigadores(): array
    {
        return $this->repo->obtenerInvestigadores();
    }

    public function obtenerEstadosProyecto(): array
    {
        return $this->repo->obtenerEstadosProyecto();
    }

    public function obtenerCarreras(): array
    {
        return $this->repo->obtenerCarreras();
    }


    // 
    // RESUMEN GLOBAL — tarjetas principales del dashboard
    // 

    /**
     * Devuelve los cuatro bloques de resumen global del dashboard
     * (proyectos, estudiantes, solicitudes y tareas), filtrados por periodo.
     *
     * @return array  Claves: 'proyectos', 'estudiantes', 'solicitudes', 'tareas'.
     */
    public function resumenGlobal(array $f): array
    {
        [$whereP, $paramsP, $typesP] = $this->filtroPeriodo($f);

        $sqlPeriodoJoin = !empty($f['periodo'])
            ? "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos AND p.id_periodos = ?"
            : "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos";

        $proyectos   = $this->repo->resumenProyectos($whereP, $paramsP, $typesP);
        $estudiantes = $this->repo->resumenEstudiantes($whereP, $paramsP, $typesP);
        $solicitudes = $this->repo->resumenSolicitudes($whereP, $paramsP, $typesP);
        $tareas      = $this->repo->resumenTareas($sqlPeriodoJoin, $paramsP, $typesP);

        return compact('proyectos', 'estudiantes', 'solicitudes', 'tareas');
    }


    // 
    // PROYECTOS — tabla paginada con filtros
    // 

    /**
     * Cuenta proyectos que cumplen los filtros dados.
     *
     * @return int
     */
    public function contarProyectos(array $f): int
    {
        [$where, $params, $types] = $this->wheresProyectos($f);
        return $this->repo->contarProyectos($where, $params, $types);
    }

    /**
     * Lista paginada de proyectos.
     *
     * @return array[]
     */
    public function obtenerProyectos(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresProyectos($f);
        return $this->repo->listarProyectos($where, $params, $types, $desde, $limite);
    }


    // 
    // DETALLE PROYECTO
    // 

    /**
     * Devuelve el detalle completo del proyecto para la vista del supervisor.
     *
     * @return array  Claves: 'proyecto', 'estudiantes', 'solicitudes', 'tareas', 'historial'.
     */
    public function detalleProyecto(int $id_proyecto): array
    {
        $proyecto    = $this->repo->detalleProyectoCabecera($id_proyecto);
        $estudiantes = $this->repo->detalleEstudiantesDeProyecto($id_proyecto);
        $solicitudes = $this->repo->detalleSolicitudesDeProyecto($id_proyecto);
        $tareas      = $this->repo->detalleTareasDeProyecto($id_proyecto);
        $historial   = $this->repo->detalleHistorialDeProyecto($id_proyecto);

        return compact('proyecto', 'estudiantes', 'solicitudes', 'tareas', 'historial');
    }


    // 
    // SOLICITUDES — tabla paginada con filtros
    // 

    /**
     * Cuenta solicitudes que cumplen los filtros dados.
     *
     * @return int
     */
    public function contarSolicitudes(array $f): int
    {
        [$where, $params, $types] = $this->wheresSolicitudes($f);
        return $this->repo->contarSolicitudes($where, $params, $types);
    }

    /**
     * Lista paginada de solicitudes.
     *
     * @return array[]
     */
    public function obtenerSolicitudes(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresSolicitudes($f);
        return $this->repo->listarSolicitudes($where, $params, $types, $desde, $limite);
    }


    // 
    // ETAPAS — resumen de alumnos por etapa
    // 

    /**
     * Resumen de etapas y secciones del documento, filtrado por periodo.
     *
     * @return array  Claves: 'etapas', 'secciones'.
     */
    public function resumenEtapas(array $f): array
    {
        [$whereP, $paramsP, $typesP] = $this->filtroPeriodo($f);

        $sqlPeriodoJoin = !empty($f['periodo'])
            ? "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos AND p.id_periodos = ?"
            : "JOIN proyectos p ON p.id_proyectos = ts.id_proyectos";

        $etapas    = $this->repo->resumenEtapas($whereP, $paramsP, $typesP);
        $secciones = $this->repo->resumenSecciones($sqlPeriodoJoin, $paramsP, $typesP);

        return ['etapas' => $etapas, 'secciones' => $secciones];
    }


    // 
    // ESTUDIANTES — tabla paginada con filtros
    // 

    /**
     * Cuenta estudiantes que cumplen los filtros dados.
     *
     * @return int
     */
    public function contarEstudiantes(array $f): int
    {
        [$where, $params, $types] = $this->wheresEstudiantes($f);
        return $this->repo->contarEstudiantes($where, $params, $types);
    }

    /**
     * Lista paginada de estudiantes.
     *
     * @return array[]
     */
    public function obtenerEstudiantes(array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->wheresEstudiantes($f);
        return $this->repo->listarEstudiantes($where, $params, $types, $desde, $limite);
    }


    // 
    // DETALLE ESTUDIANTE
    // 

    /**
     * Devuelve el detalle completo del estudiante para la vista del supervisor.
     *
     * @return array  Claves: 'usuario', 'proyectos', 'tareas', 'solicitudes'.
     */
    public function detalleEstudiante(int $id_usuario): array
    {
        $usuario     = $this->repo->detalleUsuario($id_usuario);
        $proyectos   = $this->repo->detalleProyectosDeEstudiante($id_usuario);
        $tareas      = $this->repo->detalleTareasDeEstudiante($id_usuario);
        $solicitudes = $this->repo->detalleSolicitudesDeEstudiante($id_usuario);

        return compact('usuario', 'proyectos', 'tareas', 'solicitudes');
    }


    // 
    // RESUMEN POR INVESTIGADOR
    // 

    /**
     * Lista de investigadores con sus conteos de proyectos.
     *
     * @return array[]
     */
    public function resumenInvestigadores(array $f): array
    {
        [$whereP, $paramsP, $typesP] = $this->filtroPeriodo($f);
        return $this->repo->resumenInvestigadores($whereP, $paramsP, $typesP);
    }


    // 
    // HELPERS PRIVADOS: construcción de filtros WHERE
    // 

    /**
     * Genera el fragmento WHERE y parámetros solo para el filtro de periodo.
     * Compartido entre resumenGlobal(), resumenEtapas() y resumenInvestigadores().
     *
     * @return array{0: string, 1: array, 2: string}
     */
    private function filtroPeriodo(array $f): array
    {
        if (!empty($f['periodo'])) {
            return ['AND p.id_periodos = ?', [(int)$f['periodo']], 'i'];
        }
        return ['', [], ''];
    }

    /**
     * Construye el WHERE, parámetros y tipos para filtros de proyectos.
     *
     * @return array{0: string, 1: array, 2: string}
     */
    private function wheresProyectos(array $f): array
    {
        $cond   = ['1=1'];
        $params = [];
        $types  = '';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }
        if (!empty($f['estado_proyecto'])) {
            $cond[]   = 'ep.nombre = ?';
            $params[] = $f['estado_proyecto'];
            $types   .= 's';
        }
        if (!empty($f['investigador'])) {
            $cond[]   = 'p.id_investigador = ?';
            $params[] = (int)$f['investigador'];
            $types   .= 'i';
        }
        if (!empty($f['modalidad'])) {
            $cond[]   = 'p.modalidad = ?';
            $params[] = $f['modalidad'];
            $types   .= 's';
        }
        if (!empty($f['buscar_proy'])) {
            $b        = '%' . $f['buscar_proy'] . '%';
            $cond[]   = '(p.titulo LIKE ? OR ui.nombre LIKE ? OR ui.apellido_paterno LIKE ?)';
            array_push($params, $b, $b, $b);
            $types   .= 'sss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }

    /**
     * Construye el WHERE, parámetros y tipos para filtros de solicitudes.
     *
     * @return array{0: string, 1: array, 2: string}
     */
    private function wheresSolicitudes(array $f): array
    {
        $cond   = ['1=1'];
        $params = [];
        $types  = '';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }
        if (!empty($f['estado_sol'])) {
            $cond[]   = 'sp.estado = ?';
            $params[] = $f['estado_sol'];
            $types   .= 's';
        }
        if (!empty($f['investigador'])) {
            $cond[]   = 'p.id_investigador = ?';
            $params[] = (int)$f['investigador'];
            $types   .= 'i';
        }
        if (!empty($f['carrera'])) {
            $cond[]   = 'e.id_carrera = ?';
            $params[] = (int)$f['carrera'];
            $types   .= 'i';
        }
        if (!empty($f['buscar_sol'])) {
            $b        = '%' . $f['buscar_sol'] . '%';
            $cond[]   = '(u.nombre LIKE ? OR e.matricula LIKE ? OR p.titulo LIKE ?)';
            array_push($params, $b, $b, $b);
            $types   .= 'sss';
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

    /**
     * Construye el WHERE, parámetros y tipos para filtros de estudiantes.
     *
     * @return array{0: string, 1: array, 2: string}
     */
    private function wheresEstudiantes(array $f): array
    {
        $cond   = ['1=1'];
        $params = [];
        $types  = '';

        if (!empty($f['carrera'])) {
            $cond[]   = 'e.id_carrera = ?';
            $params[] = (int)$f['carrera'];
            $types   .= 'i';
        }
        if (!empty($f['estado_usuario'])) {
            $cond[]   = 'u.estado_usuario = ?';
            $params[] = $f['estado_usuario'];
            $types   .= 's';
        }
        if (!empty($f['buscar_usr'])) {
            $b        = '%' . $f['buscar_usr'] . '%';
            $cond[]   = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR e.matricula LIKE ? OR u.correo_institucional LIKE ?)';
            array_push($params, $b, $b, $b, $b);
            $types   .= 'ssss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}