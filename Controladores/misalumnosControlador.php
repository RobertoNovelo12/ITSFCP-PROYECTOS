<?php

/**
 * MisAlumnosControlador.php
 * Controlador para el módulo "Mis Alumnos" del investigador.
 * Solo lectura — sin acciones de baja/reactivación.
 * Ruta sugerida: /Controladores/MisAlumnosControlador.php
 */

require_once __DIR__ . '/../Modelos/misAlumnos.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class misalumnosControlador extends BaseControlador
{
    private misalumnos $modelo;

    public function __construct()
    {
        global $conn;
        $this->modelo = new misalumnos($conn);
    }

    // 
    //  GUARDIA
    // 

    private function soloInvestigador(): void
    {
        $rol = strtolower($_SESSION['rol'] ?? '');
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            header('Location: /index.php');
            exit;
        }
    }

    // 
    //  FILTROS GET
    // 

    private function filtrosGET(): array
    {
        return [
            'periodo'        => isset($_GET['periodo'])        ? (int)$_GET['periodo']            : 0,
            'id_proyecto'    => isset($_GET['id_proyecto'])    ? (int)$_GET['id_proyecto']         : 0,
            'carrera'        => isset($_GET['carrera'])        ? (int)$_GET['carrera']             : 0,
            'estado'         => isset($_GET['estado'])         ? trim($_GET['estado'])             : '',
            'estado_proceso' => isset($_GET['estado_proceso']) ? trim($_GET['estado_proceso'])     : '',
            'buscar'         => isset($_GET['buscar'])         ? trim($_GET['buscar'])             : '',
            'pagina'         => isset($_GET['pagina'])         ? max(1, (int)$_GET['pagina'])      : 1,
        ];
    }

    // 
    //  ACCIÓN PRINCIPAL
    // 

    public function index(): array
    {
        $this->soloInvestigador();

        $id_investigador = (int)$_SESSION['id_usuario'];
        $filtros         = $this->filtrosGET();
        $por_pagina      = 6;
        $desde           = ($filtros['pagina'] - 1) * $por_pagina;

        $periodos  = $this->modelo->obtenerPeriodos();
        $proyectos = $this->modelo->obtenerProyectosInvestigador($id_investigador, $filtros['periodo']);
        $carreras  = $this->modelo->obtenerCarreras();
        $resumen   = $this->modelo->resumenAlumnos($id_investigador, $filtros);
        $total     = $this->modelo->contarAlumnos($id_investigador, $filtros);
        $alumnos   = $this->modelo->obtenerAlumnos($id_investigador, $filtros, $desde, $por_pagina);

        $paginacion = [
            'total'         => $total,
            'por_pagina'    => $por_pagina,
            'pagina'        => $filtros['pagina'],
            'total_paginas' => max(1, (int)ceil($total / $por_pagina)),
        ];

        return compact(
            'filtros', 'periodos', 'proyectos', 'carreras',
            'resumen', 'alumnos', 'paginacion'
        );
    }

    // 
    //  HELPERS DE PRESENTACIÓN
    // 

    public function badgeEstadoParticipacion(string $estado): string
    {
        return match ($estado) {
            'activo'    => "<span class='badge bg-success'>Activo</span>",
            'concluido' => "<span class='badge bg-primary'>Concluido</span>",
            'baja'      => "<span class='badge bg-danger'>Baja</span>",
            'cancelado' => "<span class='badge bg-secondary'>Cancelado</span>",
            default     => "<span class='badge bg-light text-dark'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function badgeEstadoProceso(string $estado): string
    {
        return match ($estado) {
            'en_proceso'          => "<span class='badge bg-info text-dark'>En proceso</span>",
            'carta_subida'        => "<span class='badge bg-warning text-dark'>Carta subida</span>",
            'en_correccion'       => "<span class='badge bg-orange text-dark' style='background-color:#fd7e14;color:#fff'>En corrección</span>",
            'liberado_supervisor' => "<span class='badge bg-success'>Liberado por supervisor</span>",
            'concluido'           => "<span class='badge bg-primary'>Concluido</span>",
            default               => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function barraAvance(int $aprobadas, int $total): string
    {
        if ($total <= 0) {
            return '<span class="text-muted small">Sin tareas</span>';
        }
        $pct   = min(100, round(($aprobadas / $total) * 100));
        $color = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
        return "
            <div class='progress mb-1' style='height:7px;min-width:90px;'>
                <div class='progress-bar bg-{$color}' style='width:{$pct}%' role='progressbar'
                     aria-valuenow='{$pct}' aria-valuemin='0' aria-valuemax='100'></div>
            </div>
            <span class='small text-muted'>{$aprobadas}/{$total} ({$pct}%)</span>
        ";
    }
}