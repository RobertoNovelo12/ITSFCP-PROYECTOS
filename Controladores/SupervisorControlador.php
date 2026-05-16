<?php

/**
 * SupervisorControlador.php
 * Dashboard exclusivo del supervisor — solo lectura, con filtros y paginación.
 */

require_once __DIR__ . '/../Modelos/SupervisorModelo.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class SupervisorControlador
{
    private SupervisorModelo $modelo;

    public function __construct()
    {
        global $conn;
        $this->modelo = new SupervisorModelo($conn);
    }

    // ── Guardia de acceso ────────────────────────────────────────

    private function soloSupervisor(): void
    {
        $rol = strtolower($_SESSION['rol'] ?? '');
        if ($rol !== 'supervisor') {
            http_response_code(403);
            header('Location: /ITSFCP-PROYECTOS/index.php');
            exit;
        }
    }

    // ── Leer filtros comunes desde GET ───────────────────────────

    private function filtrosGET(): array
    {
        return [
            'periodo'         => $_GET['periodo']        ?? '',
            'estado_proyecto' => $_GET['estado_proyecto'] ?? '',
            'estado_sol'      => $_GET['estado_sol']     ?? '',
            'investigador'    => $_GET['investigador']   ?? '',
            'modalidad'       => $_GET['modalidad']      ?? '',
            'carrera'         => $_GET['carrera']        ?? '',
            'estado_usuario'  => $_GET['estado_usuario'] ?? '',
            'buscar_proy'     => trim($_GET['buscar_proy'] ?? ''),
            'buscar_sol'      => trim($_GET['buscar_sol']  ?? ''),
            'buscar_usr'      => trim($_GET['buscar_usr']  ?? ''),
            'fecha_desde'     => $_GET['fecha_desde']    ?? '',
            'fecha_hasta'     => $_GET['fecha_hasta']    ?? '',
            'tab'             => $_GET['tab']            ?? 'resumen',
        ];
    }

    // ================================================================
    // index — pantalla principal del dashboard
    // ================================================================

    public function index(): array
    {
        $this->soloSupervisor();

        $filtros = $this->filtrosGET();

        $periodos       = $this->modelo->obtenerPeriodos();
        $investigadores = $this->modelo->obtenerInvestigadores();
        $estadosP       = $this->modelo->obtenerEstadosProyecto();
        $carreras       = $this->modelo->obtenerCarreras();

        $resumen = $this->modelo->resumenGlobal($filtros);

        $pp_proy    = 8;
        $pag_proy   = max(1, (int)($_GET['pag_proy'] ?? 1));
        $desde_proy = ($pag_proy - 1) * $pp_proy;
        $total_proy = $this->modelo->contarProyectos($filtros);
        $proyectos  = $this->modelo->obtenerProyectos($filtros, $desde_proy, $pp_proy);

        $pp_sol    = 10;
        $pag_sol   = max(1, (int)($_GET['pag_sol'] ?? 1));
        $desde_sol = ($pag_sol - 1) * $pp_sol;
        $total_sol = $this->modelo->contarSolicitudes($filtros);
        $solicitudes = $this->modelo->obtenerSolicitudes($filtros, $desde_sol, $pp_sol);

        $etapas_data = $this->modelo->resumenEtapas($filtros);

        $pp_usr    = 10;
        $pag_usr   = max(1, (int)($_GET['pag_usr'] ?? 1));
        $desde_usr = ($pag_usr - 1) * $pp_usr;
        $total_usr = $this->modelo->contarEstudiantes($filtros);
        $estudiantes = $this->modelo->obtenerEstudiantes($filtros, $desde_usr, $pp_usr);

        $resumen_inv = $this->modelo->resumenInvestigadores($filtros);

        return [
            'filtros'        => $filtros,
            'periodos'       => $periodos,
            'investigadores' => $investigadores,
            'estados_p'      => $estadosP,
            'carreras'       => $carreras,
            'resumen'        => $resumen,
            'proyectos'      => $proyectos,
            'pag_proy'       => $this->paginacion($total_proy, $pp_proy, $pag_proy),
            'solicitudes'    => $solicitudes,
            'pag_sol'        => $this->paginacion($total_sol, $pp_sol, $pag_sol),
            'etapas_data'    => $etapas_data,
            'estudiantes'    => $estudiantes,
            'pag_usr'        => $this->paginacion($total_usr, $pp_usr, $pag_usr),
            'resumen_inv'    => $resumen_inv,
        ];
    }

    // ================================================================
    // detalleProyecto
    // ================================================================

    public function detalleProyecto(): array
    {
        $this->soloSupervisor();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            header('Location: panel_supervisor.php');
            exit;
        }
        return $this->modelo->detalleProyecto($id);
    }

    // ================================================================
    // detalleEstudiante
    // ================================================================

    public function detalleEstudiante(): array
    {
        $this->soloSupervisor();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            header('Location: panel_supervisor.php?tab=usuarios');
            exit;
        }
        return $this->modelo->detalleEstudiante($id);
    }

    // ================================================================
    // Helper paginación
    // ================================================================

    private function paginacion(int $total, int $pp, int $pagina): array
    {
        return [
            'total'         => $total,
            'por_pagina'    => $pp,
            'pagina'        => $pagina,
            'total_paginas' => max(1, (int)ceil($total / $pp)),
        ];
    }

    // ================================================================
    // Helpers de badge para vistas
    // ================================================================

    public function badgeEstadoSolicitud(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'pendiente'    => "<span class='badge bg-secondary'>Pendiente</span>",
            'en_revision'  => "<span class='badge bg-info text-dark'>En revisión</span>",
            'correcciones' => "<span class='badge bg-warning text-dark'>Correcciones</span>",
            'aceptado'     => "<span class='badge bg-success'>Aceptado</span>",
            'rechazado'    => "<span class='badge bg-danger'>Rechazado</span>",
            default        => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function badgeEstadoEtapa(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'en_proceso'             => "<span class='badge bg-info text-dark'>En proceso</span>",
            'en_correccion'          => "<span class='badge bg-warning text-dark'>En corrección</span>",
            'carta_subida'           => "<span class='badge bg-success'>Carta subida</span>",
            'liberado_supervisor'    => "<span class='badge bg-primary'>Liberado supervisor</span>",
            'pendiente'              => "<span class='badge bg-secondary'>Pendiente</span>",
            'completado'             => "<span class='badge bg-success'>Completado</span>",
            'rechazado'              => "<span class='badge bg-danger'>Rechazado</span>",
            default                  => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function badgeEstadoProyecto(string $estado): string
    {
        return match (trim($estado)) {
            'Activo'      => "<span class='badge bg-success'>Activo</span>",
            'Por aprobar' => "<span class='badge bg-warning text-dark'>Por aprobar</span>",
            'Por cerrar'  => "<span class='badge bg-info text-dark'>Por cerrar</span>",
            'Rechazado'   => "<span class='badge bg-danger'>Rechazado</span>",
            'Vencido'     => "<span class='badge bg-secondary'>Vencido</span>",
            'Cierre'      => "<span class='badge bg-dark'>Cerrado</span>",
            default       => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    /**
     * Estados de tarea coherentes con la BD:
     *  Sin activar → gris claro (no cuenta en totales)
     *  Pendiente   → gris
     *  Entregado   → azul (alumno envió, aún no revisada)
     *  Revisar     → celeste (investigador revisando)
     *  Corregir    → amarillo
     *  Aprobado    → verde
     *  Vencido     → rojo
     */
    public function badgeEstadoTarea(string $estado): string
    {
        return match (trim($estado)) {
            'Aprobado'    => "<span class='badge bg-success'>Aprobado</span>",
            'Pendiente'   => "<span class='badge bg-secondary'>Pendiente</span>",
            'Entregado'   => "<span class='badge bg-primary'>Entregado</span>",
            'Revisar'     => "<span class='badge bg-info text-dark'>Por revisar</span>",
            'Corregir'    => "<span class='badge bg-warning text-dark'>Correcciones</span>",
            'Vencido'     => "<span class='badge bg-danger'>Vencido</span>",
            'Sin activar' => "<span class='badge bg-light text-muted border'>Sin activar</span>",
            default       => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function badgeEstadoUsuario(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'    => "<span class='badge bg-success'>Activo</span>",
            'aprobado'  => "<span class='badge bg-info text-dark'>Aprobado</span>",
            'espera'    => "<span class='badge bg-warning text-dark'>En espera</span>",
            'cancelado' => "<span class='badge bg-danger'>Cancelado</span>",
            'inactivo'  => "<span class='badge bg-secondary'>Inactivo</span>",
            default     => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    public function badgeParticipacion(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'    => "<span class='badge bg-success'>Activo</span>",
            'concluido' => "<span class='badge bg-primary'>Concluido</span>",
            'baja'      => "<span class='badge bg-danger'>Baja</span>",
            'cancelado' => "<span class='badge bg-secondary'>Cancelado</span>",
            default     => "<span class='badge bg-light text-dark border'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    /** Genera HTML de paginación para las vistas */
    public function htmlPaginacion(array $pag, string $key, string $tab, array $filtros): string
    {
        if ($pag['total_paginas'] <= 1) return '';

        $base = array_filter([
            'tab'             => $tab,
            'periodo'         => $filtros['periodo']         ?? '',
            'investigador'    => $filtros['investigador']    ?? '',
            'modalidad'       => $filtros['modalidad']       ?? '',
            'carrera'         => $filtros['carrera']         ?? '',
            'estado_proyecto' => $filtros['estado_proyecto'] ?? '',
            'estado_sol'      => $filtros['estado_sol']      ?? '',
            'estado_usuario'  => $filtros['estado_usuario']  ?? '',
            'buscar_proy'     => $filtros['buscar_proy']     ?? '',
            'buscar_sol'      => $filtros['buscar_sol']      ?? '',
            'buscar_usr'      => $filtros['buscar_usr']      ?? '',
            'fecha_desde'     => $filtros['fecha_desde']     ?? '',
            'fecha_hasta'     => $filtros['fecha_hasta']     ?? '',
        ]);

        $q  = http_build_query($base);
        $p  = $pag['pagina'];
        $tp = $pag['total_paginas'];

        $html  = '<nav class="mt-3"><ul class="pagination justify-content-center pagination-sm">';
        $html .= '<li class="page-item ' . ($p <= 1 ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="?' . $q . '&' . $key . '=' . ($p - 1) . '">‹</a></li>';

        $inicio = max(1, $p - 2);
        $fin    = min($tp, $p + 2);
        if ($inicio > 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        for ($i = $inicio; $i <= $fin; $i++) {
            $html .= '<li class="page-item ' . ($i === $p ? 'active' : '') . '">';
            $html .= '<a class="page-link" href="?' . $q . '&' . $key . '=' . $i . '">' . $i . '</a></li>';
        }
        if ($fin < $tp) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';

        $html .= '<li class="page-item ' . ($p >= $tp ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="?' . $q . '&' . $key . '=' . ($p + 1) . '">›</a></li>';
        $html .= '</ul>';

        $desde = ($p - 1) * $pag['por_pagina'] + 1;
        $hasta = min($p * $pag['por_pagina'], $pag['total']);
        $html .= '<p class="text-center text-muted small mb-0">Mostrando ' . $desde . '–' . $hasta . ' de ' . $pag['total'] . ' registros</p>';
        $html .= '</nav>';
        return $html;
    }
}