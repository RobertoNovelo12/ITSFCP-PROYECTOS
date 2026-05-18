<?php
// Controladores/solicitudes_carta_terminacionControlador.php
// Controlador para el módulo de Solicitudes de Carta de Terminación (supervisor)

require_once __DIR__ . '/../Modelos/solicitudes_carta_terminacion.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class solicitudes_carta_terminacionControlador
{
    // 
    // VALIDACIONES INTERNAS
    // 

    private function validarAcceso($rol, array $permitidos)
    {
        if (!in_array($rol, $permitidos)) {
            throw new Exception("No tienes permisos para realizar esta acción.");
        }
    }

    private function validarMetodo($metodo)
    {
        if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
            throw new Exception("Método no permitido.");
        }
    }

    // 
    // CATÁLOGOS
    // 

    public function obtenerTodosPeriodos()
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->obtenerTodosPeriodos();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    // DASHBOARD — resumen de tarjetas
    // 

    public function resumenCartas($id_periodo = 0)
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->resumenCartas($id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['total' => 0, 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0];
        }
    }

    // 
    // LISTADO PAGINADO
    // 

    public function listarCartas($tipo_filtro = 'Todas', $buscar = '', $pagina = 1, $id_periodo = 0)
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->listarCartas($tipo_filtro, $buscar, $pagina, $id_periodo);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['solicitudes' => [], 'paginacion' => []];
        }
    }

    // 
    // DETALLE
    // 

    public function detalleCarta($id_cierre_est)
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->detalleCarta((int)$id_cierre_est);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function historialProceso($id_proyectos, $id_usuarios)
    {
        global $conn;
        try {
            return (new solicitudes_carta_terminacion($conn))->historialProceso((int)$id_proyectos, (int)$id_usuarios);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    // 
    // APROBAR CARTA
    // 

    public function aprobarCarta($id_cierre_est, $id_supervisor, $rol)
    {
        global $conn;
        try {
            $this->validarAcceso($rol, ['supervisor']);
            $resultado = (new solicitudes_carta_terminacion($conn))->aprobarCarta((int)$id_cierre_est, (int)$id_supervisor);

            if ($resultado['success']) {
                header("Location: index.php?mensaje=aprobado");
            } else {
                header("Location: detalles.php?id=" . intval($id_cierre_est) . "&error=" . urlencode($resultado['msg']));
            }
            exit;
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    }

    // 
    // RECHAZAR CARTA — recibe POST con comentario
    // 

    public function rechazarCarta($data, $id_supervisor, $rol)
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->validarAcceso($rol, ['supervisor']);

            $id_cierre_est = intval($data['id_cierre_est'] ?? 0);
            $comentario    = trim($data['comentario']    ?? '');

            if (!$id_cierre_est || empty($comentario)) {
                throw new Exception("Datos incompletos. El comentario es obligatorio.");
            }

            $resultado = (new solicitudes_carta_terminacion($conn))->rechazarCarta($id_cierre_est, (int)$id_supervisor, $comentario);

            if ($resultado['success']) {
                header("Location: index.php?mensaje=rechazado");
            } else {
                header("Location: motivo_rechazo.php?id=" . $id_cierre_est . "&error=" . urlencode($resultado['msg']));
            }
            exit;
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    }

    // 
    // HELPERS DE PRESENTACIÓN (reutilizables en vistas)
    // 

    public function estiloCarta($estado)
    {
        return match ($estado) {
            'aprobado'  => 'success',
            'rechazado' => 'danger',
            'pendiente' => 'warning',
            default     => 'secondary',
        };
    }

    public function etiquetaCarta($estado)
    {
        return match ($estado) {
            'aprobado'  => 'Aprobada',
            'rechazado' => 'Rechazada',
            'pendiente' => 'Pendiente',
            default     => ucfirst($estado),
        };
    }

    public function estiloEstadoProceso($estado)
    {
        return match ($estado) {
            'concluido'           => 'success',
            'liberado_supervisor' => 'info',
            'carta_subida'        => 'primary',
            default               => 'secondary',
        };
    }

    public function etiquetaEstadoProceso($estado)
    {
        return match ($estado) {
            'concluido'           => 'Concluido',
            'liberado_supervisor' => 'Liberado por supervisor',
            'carta_subida'        => 'Carta subida',
            default               => ucfirst(str_replace('_', ' ', $estado)),
        };
    }

    /**
     * Tamaño legible en KB / MB
     */
    public function formatoTamano($bytes)
    {
        if (!$bytes) return '—';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1024, 1) . ' KB';
    }
}