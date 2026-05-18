<?php

/**
 * MisAlumnosControlador.php
 * Controlador para el módulo "Mis Alumnos" del investigador.
 * Solo lectura — sin acciones de baja/reactivación.
 * Ruta sugerida: /ITSFCP-PROYECTOS/Controladores/MisAlumnosControlador.php
 */

require_once __DIR__ . '/../Modelos/misalumnos.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class misalumnosControlador
{
    private misalumnos $modelo;

    public function __construct()
    {
        global $conn;
        $this->modelo = new misalumnos($conn);
    }

    // 
    // GUARDIA DE ACCESO
    // 

    /**
     * Verifica que el usuario en sesión tenga rol de investigador o profesor.
     * Si no, redirige a la página principal.
     */
    private function soloInvestigador(): void
    {
        $rol = strtolower($_SESSION['rol'] ?? '');
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            header('Location: /ITSFCP-PROYECTOS/index.php');
            exit;
        }
    }

    // 
    // LECTURA SEGURA DE FILTROS GET
    // 

    /**
     * Lee y sanea todos los filtros del querystring.
     * Nunca confía en valores crudos de $_GET.
     */
    private function filtrosGET(): array
    {
        return [
            // Periodo es independiente: afecta a los demás selects
            'periodo'        => isset($_GET['periodo'])        ? (int)$_GET['periodo']            : 0,
            'id_proyecto'    => isset($_GET['id_proyecto'])    ? (int)$_GET['id_proyecto']         : 0,
            'carrera'        => isset($_GET['carrera'])        ? (int)$_GET['carrera']             : 0,
            // Cadenas: se recortan y escapan en las vistas con htmlspecialchars
            'estado'         => isset($_GET['estado'])         ? trim($_GET['estado'])             : '',
            'estado_proceso' => isset($_GET['estado_proceso']) ? trim($_GET['estado_proceso'])     : '',
            'buscar'         => isset($_GET['buscar'])         ? trim($_GET['buscar'])             : '',
            // Paginación
            'pagina'         => isset($_GET['pagina'])         ? max(1, (int)$_GET['pagina'])      : 1,
        ];
    }

    // 
    // ACCIÓN PRINCIPAL — index()
    // 

    /**
     * Reúne todos los datos necesarios para la vista index.php.
     * Devuelve un array que la vista extrae con extract().
     */
    public function index(): array
    {
        $this->soloInvestigador();

        $id_investigador = (int)$_SESSION['id_usuario'];
        $filtros         = $this->filtrosGET();
        $por_pagina      = 10;
        $desde           = ($filtros['pagina'] - 1) * $por_pagina;

        // ── Catálogos para los selects ────────────────────────────
        $periodos   = $this->modelo->obtenerPeriodos();
        // El select de proyectos se restringe por periodo activo
        $proyectos  = $this->modelo->obtenerProyectosInvestigador($id_investigador, $filtros['periodo']);
        $carreras   = $this->modelo->obtenerCarreras();

        // ── Tarjetas resumen ──────────────────────────────────────
        $resumen = $this->modelo->resumenAlumnos($id_investigador, $filtros);

        // ── Listado paginado ──────────────────────────────────────
        $total   = $this->modelo->contarAlumnos($id_investigador, $filtros);
        $alumnos = $this->modelo->obtenerAlumnos($id_investigador, $filtros, $desde, $por_pagina);

        $paginacion = [
            'total'         => $total,
            'por_pagina'    => $por_pagina,
            'pagina'        => $filtros['pagina'],
            'total_paginas' => max(1, (int)ceil($total / $por_pagina)),
        ];

        return compact(
            'filtros',
            'periodos',
            'proyectos',
            'carreras',
            'resumen',
            'alumnos',
            'paginacion'
        );
    }

    // 
    // HELPERS DE PRESENTACIÓN (usados directamente en la vista)
    // 

    /**
     * Badge Bootstrap para el estado de participación en el proyecto.
     */
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

    /**
     * Badge Bootstrap para el estado del proceso (etapa del alumno).
     * Basado en los valores de la tabla estados_proceso.
     */
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

    /**
     * Genera el HTML de una barra de progreso de tareas.
     */
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

    /**
     * Genera el HTML de paginación reutilizando los filtros activos.
     */
    public function htmlPaginacion(array $pag, array $filtros): string
    {
        if ($pag['total_paginas'] <= 1) {
            return '';
        }

        // Reconstruye los parámetros sin 'pagina' para no duplicarlo
        $base = array_filter([
            'periodo'        => $filtros['periodo']        ?: '',
            'id_proyecto'    => $filtros['id_proyecto']    ?: '',
            'carrera'        => $filtros['carrera']        ?: '',
            'estado'         => $filtros['estado']         ?: '',
            'estado_proceso' => $filtros['estado_proceso'] ?: '',
            'buscar'         => $filtros['buscar']         ?: '',
        ]);
        $q  = http_build_query($base);
        $p  = $pag['pagina'];
        $tp = $pag['total_paginas'];

        $html  = '<nav class="mt-3" aria-label="Paginación alumnos">';
        $html .= '<ul class="pagination justify-content-center pagination-sm flex-wrap">';

        // Anterior
        $html .= '<li class="page-item ' . ($p <= 1 ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="?' . $q . '&pagina=' . ($p - 1) . '">&laquo;</a></li>';

        // Páginas con ventana ±2
        $ini = max(1, $p - 2);
        $fin = min($tp, $p + 2);
        if ($ini > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="?' . $q . '&pagina=1">1</a></li>';
            if ($ini > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($i = $ini; $i <= $fin; $i++) {
            $html .= '<li class="page-item ' . ($i === $p ? 'active' : '') . '">';
            $html .= '<a class="page-link" href="?' . $q . '&pagina=' . $i . '">' . $i . '</a></li>';
        }
        if ($fin < $tp) {
            if ($fin < $tp - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="?' . $q . '&pagina=' . $tp . '">' . $tp . '</a></li>';
        }

        // Siguiente
        $html .= '<li class="page-item ' . ($p >= $tp ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="?' . $q . '&pagina=' . ($p + 1) . '">&raquo;</a></li>';

        $html .= '</ul>';

        // Contador de registros
        $desde = ($p - 1) * $pag['por_pagina'] + 1;
        $hasta = min($p * $pag['por_pagina'], $pag['total']);
        $html .= '<p class="text-center text-muted small mb-0">';
        $html .= "Mostrando {$desde}–{$hasta} de {$pag['total']} participaciones</p>";
        $html .= '</nav>';

        return $html;
    }
}