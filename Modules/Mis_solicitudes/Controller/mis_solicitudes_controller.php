<?php
// Controladores/misSolicitudesControlador.php

require_once __DIR__ . '/../Model/mis_solicitudes_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';
include __DIR__ . '/../../../public/incluido/_botones.php';

class MisSolicitudesControlador extends BaseControlador
{
    private const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];
    private const TAMANO_MAXIMO_BYTES    = 5 * 1024 * 1024; // 5 MB
    private const RUTA_STORAGE           = __DIR__ . '/../../../storage/solicitudes/';
    private const POR_PAGINA             = 8;
    private const BASE_URL               = '/Modules/Mis_solicitudes/Views';

    // 
    //  INDEX
    // 

    public function index(int $id_estudiante): array
    {
        global $conn;
        $modelo = new MisSolicitudes($conn);

        $modelo->marcarVencidos(); // Mantenimiento automático de estados

        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'periodo' => $_GET['periodo'] ?? '',
            'estado'  => $_GET['estado']  ?? '',
            'buscar'  => trim($_GET['buscar'] ?? ''),
        ];

        $id_periodo = !empty($filtros['periodo']) ? (int)$filtros['periodo'] : null;
        $desde      = ($pagina - 1) * self::POR_PAGINA;

        $total       = $modelo->contarSolicitudes($id_estudiante, $filtros);
        $solicitudes = $modelo->obtenerSolicitudes($id_estudiante, $filtros, $desde, self::POR_PAGINA);
        $resumen     = $modelo->resumen($id_estudiante, $id_periodo);
        $periodos    = $modelo->periodosDelEstudiante($id_estudiante);

        return [
            'solicitudes' => $solicitudes,
            'resumen'     => $resumen,
            'periodos'    => $periodos,
            'filtros'     => $filtros,
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => self::POR_PAGINA,
                'pagina'        => $pagina,
                'total_paginas' => max(1, (int)ceil($total / self::POR_PAGINA)),
            ],
        ];
    }

    // 
    //  DETALLE
    // 

    public function detallePagina(int $id_solicitud, int $id_estudiante): array
    {
        global $conn;
        $modelo    = new MisSolicitudes($conn);
        $solicitud = $modelo->obtenerDetalle($id_solicitud, $id_estudiante);
        $hilo      = $solicitud ? $modelo->obtenerHilo($id_solicitud, $id_estudiante) : [];

        return ['solicitud' => $solicitud, 'hilo' => $hilo];
    }

    // 
    //  PROCESAR RESPUESTA A CORRECCIONES
    // 

    public function procesarRespuesta(array $post, array $files, int $id_estudiante): array
    {
        global $conn;

        $id_solicitud = (int)($post['id_solicitud'] ?? 0);
        $comentario   = trim($post['comentario']    ?? '');

        if ($id_solicitud <= 0 || $comentario === '') {
            return ['ok' => false, 'msg' => 'error_comentario_vacio'];
        }

        $modelo    = new MisSolicitudes($conn);
        $solicitud = $modelo->obtenerSolicitud($id_solicitud, $id_estudiante);

        if ($solicitud === null) {
            return ['ok' => false, 'msg' => 'error_no_encontrada'];
        }
        if ($solicitud['estado'] !== 'correcciones') {
            return ['ok' => false, 'msg' => 'error_estado_invalido'];
        }

        $archivo = null;
        if (!empty($files['adjunto']['name'])) {
            $resultado = $this->_procesarArchivo($files['adjunto'], $id_estudiante);
            if (!$resultado['ok']) return ['ok' => false, 'msg' => $resultado['msg']];
            $archivo = $resultado['datos'];
        }

        $ok = $modelo->guardarRespuesta(
            $id_solicitud,
            $id_estudiante,
            (int)$solicitud['id_proyectos'],
            $comentario,
            $archivo
        );

        return $ok
            ? ['ok' => true,  'msg' => 'exito_respuesta']
            : ['ok' => false, 'msg' => 'error_guardar'];
    }

    // 
    //  CANCELAR
    // 

    public function cancelar(int $id_solicitud, int $id_estudiante): void
    {
        global $conn;
        try {
            $this->validarMetodo('GET');

            $ok = (new MisSolicitudes($conn))->cancelarSolicitud($id_solicitud, $id_estudiante);

            $this->redirigir($ok ? 'exito_cancelar' : 'error_cancelar', self::BASE_URL . '/index.php');
        } catch (Exception $e) {
            error_log('MisSolicitudesControlador::cancelar() — ' . $e->getMessage());
            $this->redirigir('error_cancelar', self::BASE_URL . '/index.php');
        }
    }

    // 
    //  PRESENTACIÓN
    // 

    public function encabezados(): array
    {
        return ['#', 'Proyecto', 'Investigador', 'Periodo', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }

    public function badgeEstado(string $estado): string
    {
        $clase = match (strtolower($estado)) {
            'pendiente'    => 'badge-pendiente',
            'en_revision'  => 'badge-en_revision',
            'correcciones' => 'badge-correcciones',
            'aceptado'     => 'badge-aceptado',
            'rechazado'    => 'badge-rechazado',
            'vencido'      => 'badge-vencido',
            'cancelado'    => 'badge-cancelado',
            default        => 'badge-cancelado',
        };

        $icono = match (strtolower($estado)) {
            'pendiente'    => 'bi-hourglass-split',
            'en_revision'  => 'bi-eye-fill',
            'correcciones' => 'bi-pencil-fill',
            'aceptado'     => 'bi-check-circle-fill',
            'rechazado'    => 'bi-x-circle-fill',
            'vencido'      => 'bi-clock-history',
            'cancelado'    => 'bi-slash-circle-fill',
            default        => 'bi-circle',
        };

        $etiqueta = $this->etiquetaEstado($estado);
        return "<span class='badge {$clase}'><i class='bi {$icono}'></i> {$etiqueta}</span>";
    }

    public function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente'    => 'Pendiente',
            'en_revision'  => 'En revisión',
            'correcciones' => 'Correcciones',
            'aceptado'     => 'Aceptado',
            'rechazado'    => 'Rechazado',
            'vencido'      => 'Vencido',
            'cancelado'    => 'Cancelado',
            default        => ucfirst($estado),
        };
    }

    public function botonesAccion(int $id_solicitud, string $estado): string
    {
        include __DIR__ . '/../../../public/incluido/_iconos.php';

        $base = self::BASE_URL;

        $btns = Botones::botonIcono(
            $base . '/detalles_mi_solicitud.php?id=' . $id_solicitud,
            'primary',
            $iconos['tabla']['ver'],
            'Ver detalle'
        );

        if ($estado === 'correcciones') {
            $btns .= Botones::botonTexto(
                $base . '/detalles_mi_solicitud.php?id=' . $id_solicitud . '#form-responder',
                'secondary',
                $iconos['tabla']['responder'],
                'Responder'
            );
        }

        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            $btns .= Botones::botonConfirmacion(
                self::BASE_URL . '/index.php?accion=cancelar&id_solicitud=' . $id_solicitud,
                'danger',
                $iconos['tabla']['solicitar_cierre'],
                'Cancelar solicitud',
                '¿Estás seguro de que deseas cancelar esta solicitud? Esta acción no se puede deshacer.'
            );
        }

        return $btns;
    }

    // 
    //  HELPER PRIVADO: archivo adjunto
    // 

    private function _procesarArchivo(array $file, int $id_estudiante): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'error_subida_archivo'];
        }
        if ($file['size'] > self::TAMANO_MAXIMO_BYTES) {
            return ['ok' => false, 'msg' => 'error_archivo_grande'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return ['ok' => false, 'msg' => 'error_tipo_archivo'];
        }

        $nombre_display = pathinfo($file['name'], PATHINFO_FILENAME);
        $nombre_fisico  = 'est' . $id_estudiante . '_sol_' . date('YmdHis') . '.' . $extension;
        $directorio     = self::RUTA_STORAGE . 'estudiante_' . $id_estudiante . '/';
        $ruta_relativa  = 'storage/solicitudes/estudiante_' . $id_estudiante . '/' . $nombre_fisico;

        if (!is_dir($directorio)) mkdir($directorio, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $directorio . $nombre_fisico)) {
            return ['ok' => false, 'msg' => 'error_guardar_archivo'];
        }

        return [
            'ok'    => true,
            'datos' => [
                'nombre_display' => $nombre_display,
                'nombre_fisico'  => $nombre_fisico,
                'ruta'           => $ruta_relativa,
                'mime'           => $file['type'],
                'extension'      => $extension,
                'tamano'         => $file['size'],
            ],
        ];
    }
}
