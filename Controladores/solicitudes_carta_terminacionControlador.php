<?php
// Controladores/solicitudesCartaTerminacionControlador.php

require_once __DIR__ . '/../Modelos/solicitudes_carta_terminacion.php';
require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/BaseControlador.php';

class solicitudes_carta_terminacionControlador extends BaseControlador
{
    // ─
    //  CATÁLOGOS
    // ─

    public function obtenerTodosPeriodos(): array
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->obtenerTodosPeriodos();
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::obtenerTodosPeriodos() — ' . $e->getMessage());
            return [];
        }
    }

    // ─
    //  DASHBOARD
    // ─

    public function resumenCartas(int $id_periodo = 0): array
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->resumenCartas($id_periodo);
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::resumenCartas() — ' . $e->getMessage());
            return ['total' => 0, 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0];
        }
    }

    // ─
    //  LISTADO PAGINADO
    // ─

    public function listarCartas(
        string $tipo_filtro = 'Todas',
        string $buscar = '',
        int    $pagina = 1,
        int    $id_periodo = 0
    ): array {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->listarCartas($tipo_filtro, $buscar, $pagina, $id_periodo);
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::listarCartas() — ' . $e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    // ─
    //  DETALLE
    // ─

    public function detalleCarta(int $id_cierre_est): ?array
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->detalleCarta($id_cierre_est);
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::detalleCarta() — ' . $e->getMessage());
            return null;
        }
    }

    public function historialProceso(int $id_proyectos, int $id_usuarios): array
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->historialProceso($id_proyectos, $id_usuarios);
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::historialProceso() — ' . $e->getMessage());
            return [];
        }
    }

    // ─
    //  APROBAR (GET desde botón tabla)
    // ─

    /**
     * Aprueba una carta de terminación y redirige con msg al index.
     */
    public function aprobarCarta(int $id_cierre_est, int $id_supervisor, string $rol): void
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);

            $resultado = (new solicitudes_carta_terminacion($conn))->aprobarCarta($id_cierre_est, $id_supervisor);

            if ($resultado['success']) {
                $this->redirigir('exito_aprobar');
            } elseif($resultado['msg'] === 'Esta solicitud ya fue procesada.') {
                $this->redirigir('error_procesada', 'index.php', "&id={$id_cierre_est}");
            }
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::aprobarCarta() — ' . $e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_aprobar';
            $this->redirigir($msg);
        }
    }

    // ─
    //  RECHAZAR (POST con comentario)
    // ─

    /**
     * Rechaza una carta con motivo y redirige con msg al index.
     */
    public function rechazarCarta(array $data, int $id_supervisor, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_cierre_est = (int)($data['id_cierre_est'] ?? 0);
            $comentario    = trim($data['comentario']    ?? '');

            if (!$id_cierre_est || empty($comentario)) {
                throw new \Exception('Datos incompletos. El comentario es obligatorio.');
            }

            $resultado = (new solicitudes_carta_terminacion($conn))->rechazarCarta(
                $id_cierre_est,
                $id_supervisor,
                $comentario
            );


            if ($resultado['success']) {
                $this->redirigir('exito_rechazar');
            } else {
                $this->redirigir('error_rechazar', 'motivo_rechazo.php', "&id={$id_cierre_est}");
            }
        } catch (\Exception $e) {
            error_log('CartaTerminacionControlador::rechazarCarta() — ' . $e->getMessage());
            $msg = ($e->getMessage() === 'accion_no_permitida') ? 'accion_no_permitida' : 'error_rechazar';
            $this->redirigir($msg);
        }
    }

    // ─
    //  HELPERS DE PRESENTACIÓN
    // ─

    public function estiloCarta(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'success',
            'rechazado' => 'danger',
            'pendiente' => 'warning',
            default     => 'secondary',
        };
    }

    public function etiquetaCarta(string $estado): string
    {
        return match ($estado) {
            'aprobado'  => 'Aprobada',
            'rechazado' => 'Rechazada',
            'pendiente' => 'Pendiente',
            default     => ucfirst($estado),
        };
    }

    public function estiloEstadoProceso(string $estado): string
    {
        return match ($estado) {
            'concluido'           => 'success',
            'liberado_supervisor' => 'info',
            'carta_subida'        => 'primary',
            default               => 'secondary',
        };
    }

    public function etiquetaEstadoProceso(string $estado): string
    {
        return match ($estado) {
            'concluido'           => 'Concluido',
            'liberado_supervisor' => 'Liberado por supervisor',
            'carta_subida'        => 'Carta subida',
            default               => ucfirst(str_replace('_', ' ', $estado)),
        };
    }

    public function formatoTamano(?int $bytes): string
    {
        if (!$bytes) return '—';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1024, 1) . ' KB';
    }
}