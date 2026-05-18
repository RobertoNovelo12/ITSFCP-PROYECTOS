<?php
// Controladores/misSolicitudesControlador.php
require_once __DIR__ . '/../Modelos/misSolicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class MisSolicitudesControlador
{
    private const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png'];
    private const TAMANO_MAXIMO_BYTES    = 5 * 1024 * 1024; // 5 MB
    private const RUTA_STORAGE           = __DIR__ . '/../storage/solicitudes/';
    private const POR_PAGINA             = 8;
    private const BASE_URL               = '/ITSFCP-PROYECTOS/Vistas/Mis_solicitudes';

    // 
    //  INDEX: todo lo que necesita la tabla + filtros + paginación + resumen
    // 
    public function index(int $id_estudiante): array
    {
        global $conn;
        $modelo = new MisSolicitudes($conn);

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
    //  DETALLE: datos para detalles_mi_solicitud.php
    // 
    public function detallePagina(int $id_solicitud, int $id_estudiante): array
    {
        global $conn;
        $modelo    = new MisSolicitudes($conn);
        $solicitud = $modelo->obtenerDetalle($id_solicitud, $id_estudiante);
        $hilo      = $solicitud ? $modelo->obtenerHilo($id_solicitud, $id_estudiante) : [];

        return [
            'solicitud' => $solicitud,
            'hilo'      => $hilo,
        ];
    }

    // 
    //  PROCESAR RESPUESTA a correcciones (POST desde detalles_mi_solicitud.php)
    // 
    public function procesarRespuesta(array $post, array $files, int $id_estudiante): array
    {
        global $conn;

        $id_solicitud = (int)($post['id_solicitud'] ?? 0);
        $comentario   = trim($post['comentario']    ?? '');

        if ($id_solicitud <= 0 || $comentario === '') {
            return ['ok' => false, 'mensaje' => 'El comentario no puede estar vacío.'];
        }

        $modelo    = new MisSolicitudes($conn);
        $solicitud = $modelo->obtenerSolicitud($id_solicitud, $id_estudiante);

        if ($solicitud === null) {
            return ['ok' => false, 'mensaje' => 'Solicitud no encontrada.'];
        }
        if ($solicitud['estado'] !== 'correcciones') {
            return ['ok' => false, 'mensaje' => 'Solo puedes responder cuando la solicitud está en correcciones.'];
        }

        $archivo = null;
        if (!empty($files['adjunto']['name'])) {
            $resultado = $this->_procesarArchivo($files['adjunto'], $id_estudiante);
            if (!$resultado['ok']) return $resultado;
            $archivo = $resultado['datos'];
        }

        $ok = $modelo->guardarRespuesta(
            $id_solicitud, $id_estudiante,
            (int)$solicitud['id_proyectos'],
            $comentario, $archivo
        );

        return $ok
            ? ['ok' => true,  'mensaje' => 'Tu respuesta fue enviada. El investigador la revisará.']
            : ['ok' => false, 'mensaje' => 'Ocurrió un error al guardar. Intenta de nuevo.'];
    }

    // 
    //  CANCELAR solicitud (POST desde tabla o detalle)
    // 
    public function cancelar(int $id_solicitud, int $id_estudiante): array
    {
        global $conn;
        $modelo = new MisSolicitudes($conn);
        $ok     = $modelo->cancelarSolicitud($id_solicitud, $id_estudiante);

        return $ok
            ? ['ok' => true,  'mensaje' => 'Solicitud cancelada correctamente.']
            : ['ok' => false, 'mensaje' => 'No se pudo cancelar. Solo puedes cancelar solicitudes pendientes o en revisión.'];
    }

    // 
    //  ENCABEZADOS para la tabla
    // 
    public function encabezados(): array
    {
        return ['#', 'Proyecto', 'Investigador', 'Periodo', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }

    // 
    //  BADGE HTML de estado
    // 
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

    // 
    //  Etiqueta legible del estado
    // 
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

    // 
    //  BOTONES DE ACCIÓN para cada fila de la tabla
    // 
    public function botonesAccion(int $id_solicitud, string $estado): string
    {
        $base = self::BASE_URL;

        // Ver detalle (siempre visible)
        $btns = "<a href='{$base}/detalles_mi_solicitud.php?id={$id_solicitud}'
                    class='ms-btn-accion btn btn-sm btn-primary'
                    title='Ver detalle'>
                    <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='currentColor' class='bi bi-eye-fill' viewBox='0 0 16 16'>
                        <path d='M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0'/><path d='M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7'/>
                    </svg>
                 </a>";

        // Responder correcciones
        if ($estado === 'correcciones') {
            $btns .= " <a href='{$base}/detalles_mi_solicitud.php?id={$id_solicitud}#form-responder'
                          class='ms-btn-accion btn-sm ms-btn-resp'
                          title='Responder correcciones'>
                          <i class='bi bi-reply-fill'></i> Responder
                       </a>";
        }

        // Cancelar
        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            $btns .= " <button type='button'
                               class='ms-btn-accion btn-sm ms-btn-cancel'
                               title='Cancelar solicitud'
                               onclick=\"abrirModalCancelar({$id_solicitud}, 'esta solicitud')\">
                           <i class='bi bi-x-circle-fill'></i> Cancelar
                       </button>";
        }

        return $btns;
    }

    // 
    //  Leer mensaje flash del QueryString
    // 
    public function leerMensaje(): ?array
    {
        if (!isset($_GET['msg'])) return null;

        return match ($_GET['msg']) {
            'enviado'   => ['tipo' => 'exito',   'texto' => 'Tu respuesta fue enviada al investigador.'],
            'cancelado' => ['tipo' => '',        'texto' => 'Solicitud cancelada correctamente.'],
            'error'     => ['tipo' => 'peligro', 'texto' => $_GET['detalle'] ?? 'Ocurrió un error. Intenta de nuevo.'],
            default     => null,
        };
    }

    // 
    //  Helper privado: validar y mover archivo adjunto
    // 
    private function _procesarArchivo(array $file, int $id_estudiante): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'mensaje' => 'Error al subir el archivo.'];
        }
        if ($file['size'] > self::TAMANO_MAXIMO_BYTES) {
            return ['ok' => false, 'mensaje' => 'El archivo supera el límite de 5 MB.'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS)) {
            return ['ok' => false, 'mensaje' => 'Tipo de archivo no permitido. Usa PDF, DOCX o imágenes.'];
        }

        $nombre_display = pathinfo($file['name'], PATHINFO_FILENAME);
        $nombre_fisico  = 'est' . $id_estudiante . '_sol_' . date('YmdHis') . '.' . $extension;
        $directorio     = self::RUTA_STORAGE . 'estudiante_' . $id_estudiante . '/';
        $ruta_relativa  = 'storage/solicitudes/estudiante_' . $id_estudiante . '/' . $nombre_fisico;

        if (!is_dir($directorio)) mkdir($directorio, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $directorio . $nombre_fisico)) {
            return ['ok' => false, 'mensaje' => 'No se pudo guardar el archivo en el servidor.'];
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