<?php
// Controladores/BaseControlador.php
// Controladores/BaseControlador.php
abstract class BaseControlador
{
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirige al destino indicado con un parámetro ?msg= y termina.
     * Para acciones invocadas desde formularios POST o enlaces GET.
     *
     * Claves reconocidas por $_mapa en las vistas:
     *   exito_editar | exito_desactivar | exito_reactivar
     *   error_editar | error_desactivar | error_reactivar
     *   error_cargar | error_duplicado
     *   sin_permiso  | accion_no_permitida
     */
    protected function redirigir(string $msg, string $destino = 'index.php', string $extra = ''): void
    {
        $sep = str_contains($destino, '?') ? '&' : '?';
        header("Location: {$destino}{$sep}msg={$msg}{$extra}");
        exit;
    }

    protected function limpiar(?string $dato): ?string
    {
        return isset($dato) ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8') : null;
    }

    
    protected function esSupervisor(string $rol): bool
    {
        return $rol === 'supervisor';
    }


    // Genérico: recibe los roles permitidos como parámetro
    protected function validarAcceso(string $rol, array $permitidos): void
    {
        if (!in_array($rol, $permitidos, true)) {
            throw new Exception('accion_no_permitida');
        }
    }

    // Genérico: cualquier controlador puede necesitar validar el método HTTP
    protected function validarMetodo(string $metodo): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
            throw new Exception('metodo_no_permitido');
        }
    }
}
?>