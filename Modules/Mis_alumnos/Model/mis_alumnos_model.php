<?php
// Modelos/MisAlumnos.php

require_once __DIR__ . '/../Repository/mis_alumnos_repository.php';

/**
 * MisAlumnos (Modelo)
 *
 * Responsabilidad exclusiva: lógica de negocio del módulo "Mis Alumnos".
 * Delega toda ejecución SQL a MisAlumnosRepositorio.
 *
 * La construcción del WHERE dinámico permanece aquí como lógica de negocio,
 * ya que decide qué filtros aplicar según las reglas del dominio.
 * El repositorio recibe el WHERE ya construido como parámetro.
 */
class MisAlumnos
{
    private MisAlumnosRepositorio $repo;

    public function __construct(mysqli $conn)
    {
        $this->repo = new MisAlumnosRepositorio($conn);
    }


    // 
    // CATÁLOGOS
    // 

    public function obtenerPeriodos(): array
    {
        return $this->repo->obtenerPeriodos();
    }

    public function obtenerProyectosInvestigador(int $id_investigador, int $id_periodo = 0): array
    {
        return $this->repo->obtenerProyectosInvestigador($id_investigador, $id_periodo);
    }

    public function obtenerCarreras(): array
    {
        return $this->repo->obtenerCarreras();
    }


    // 
    // RESUMEN
    // 

    public function resumenAlumnos(int $id_investigador, array $f): array
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        return $this->repo->resumenAlumnos($id_investigador, $where, $params, $types);
    }


    // 
    // LISTADO PAGINADO
    // 

    public function contarAlumnos(int $id_investigador, array $f): int
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        return $this->repo->contarAlumnos($where, $params, $types);
    }

    public function obtenerAlumnos(int $id_investigador, array $f, int $desde, int $limite): array
    {
        [$where, $params, $types] = $this->buildWheres($id_investigador, $f);

        return $this->repo->listarAlumnos($where, $params, $types, $desde, $limite);
    }


    // 
    // WHERE DINÁMICO (lógica de dominio)
    // 

    private function buildWheres(int $id_investigador, array $f): array
    {
        $cond   = ['p.id_investigador = ?'];
        $params = [$id_investigador];
        $types  = 'i';

        if (!empty($f['periodo'])) {
            $cond[]   = 'p.id_periodos = ?';
            $params[] = (int)$f['periodo'];
            $types   .= 'i';
        }

        if (!empty($f['id_proyecto'])) {
            $cond[]   = 'pu.id_proyectos = ?';
            $params[] = (int)$f['id_proyecto'];
            $types   .= 'i';
        }

        if (!empty($f['estado'])) {
            $permitidos = ['activo', 'concluido', 'baja', 'cancelado'];
            if (in_array($f['estado'], $permitidos, true)) {
                $cond[]   = 'pu.estado = ?';
                $params[] = $f['estado'];
                $types   .= 's';
            }
        }

        if (!empty($f['estado_proceso'])) {
            $cond[]   = 'ep.estado = ?';
            $params[] = $f['estado_proceso'];
            $types   .= 's';
        }

        if (!empty($f['carrera'])) {
            $cond[]   = 'e.id_carrera = ?';
            $params[] = (int)$f['carrera'];
            $types   .= 'i';
        }

        if (!empty($f['buscar'])) {
            $like     = '%' . $f['buscar'] . '%';
            $cond[]   = '(u.nombre LIKE ? OR u.apellido_paterno LIKE ? OR e.matricula LIKE ? OR u.correo_institucional LIKE ?)';
            array_push($params, $like, $like, $like, $like);
            $types   .= 'ssss';
        }

        return ['WHERE ' . implode(' AND ', $cond), $params, $types];
    }
}
