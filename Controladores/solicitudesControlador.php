<?php
require_once __DIR__ . '/../Modelos/solicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';

/**
 * Controlador de Solicitudes de Integración.
 *
 * 
 *  SECCIÓN A  │  Módulo del INVESTIGADOR
 *             │  index, aceptar, pedirCorrecciones, rechazar,
 *             │  responderCierre, vencido, detallePagina,
 *             │  getDatosSeguimientoEstudiante, encabezados,
 *             │  botonesAccion, badgeEstado
 * 
 *  SECCIÓN B  │  Módulo del ESTUDIANTE
 *             │  enviarSolicitud, cancelarSolicitud,
 *             │  enviarCorrecciones, obtenerEstadoSolicitud,
 *             │  descargarPlantilla
 * 
 *
 * Todos los métodos de acción reciben sus datos como parámetros explícitos
 * (sin leer $_POST/$_SESSION directamente) y redirigen con mensajes GET
 * al terminar, siguiendo el patrón PRG (Post-Redirect-Get).
 */
class solicitudesControlador
{
    // 
    //  GUARDAS DE ROL
    // 

    private function soloInvestigador(string $rol): void
    {
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            die('Acceso denegado.');
        }
    }

    private function soloEstudiante(string $rol): void
    {
        if ($rol !== 'estudiante') {
            http_response_code(403);
            die('Acceso denegado.');
        }
    }

    // 
    //  HELPERS DE ARCHIVOS
    // 

    /**
     * Procesa un archivo adjunto (comentario del investigador) y devuelve id_documento.
     * Tipo: 'recurso', visibilidad: 'privado'.
     */
    private function procesarArchivoComentario(int $id_solicitud, int $id_usuario): ?int
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['archivo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx', 'png', 'jpg'], true)) return null;
        if ($file['size'] > 8 * 1024 * 1024) return null;

        $mimes_permitidos = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
            'image/jpeg',
        ];

        // Validar MIME real con finfo
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes_permitidos, true)) return null;

        $nombreFinal = 'sol_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
        $base        = "/ITSFCP-PROYECTOS/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . $base;
        $rutaFisica  = $dirFisico . $nombreFinal;
        $rutaBD      = $base . $nombreFinal;

        if (!is_dir($dirFisico)) {
            mkdir($dirFisico, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new Exception("Error al guardar el archivo en disco.");
        }

        global $conn;
        $modelo = new Solicitud($conn);
        return $modelo->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $rutaBD,
            tipo_mime: $mime_real,
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'recurso',
            visibilidad: 'privado',
            id_usuario: $id_usuario
        );
    }

    /**
     * Procesa la carta compromiso firmada del estudiante.
     * Acepta: pdf, docx, png.
     * Ruta física: storage/etapas/proyecto_{id}/
     * Tipo: 'etapa', visibilidad: 'privado'.
     *
     * Devuelve id_documento o null si no hay archivo.
     */
    private function procesarCartaCompromiso(
        string $campo_file,
        int    $id_proyecto,
        int    $id_estudiante,
        int    $id_seguimiento,
        int    $id_plantilla
    ): ?int {
        if (empty($_FILES[$campo_file]) || $_FILES[$campo_file]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$campo_file];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $exts_permitidas  = ['pdf', 'docx', 'png'];
        $mimes_permitidos = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
        ];

        if (!in_array($ext, $exts_permitidas, true)) {
            throw new Exception("Tipo de archivo no permitido para la carta compromiso. Use PDF, DOCX o PNG.");
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception("El archivo supera el tamaño máximo permitido (10 MB).");
        }

        // Validar MIME real
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes_permitidos, true)) {
            throw new Exception("El contenido del archivo no coincide con su extensión.");
        }

        // Nombre de archivo único: est{id_est}_td1_{timestamp}.{ext}
        $nombreFinal = "est{$id_estudiante}_td1_" . date('YmdHis') . '.' . $ext;
        $dirRelativo = "storage/etapas/proyecto_{$id_proyecto}/";
        $dirFisico   = realpath($_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS') . '/' . $dirRelativo;
        $rutaFisica  = $dirFisico . $nombreFinal;
        $rutaBD      = $dirRelativo . $nombreFinal;

        // Prevenir path traversal: confirmar que el directorio final queda dentro del storage
        $storageBase = realpath($_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/storage');
        if (!$storageBase) throw new Exception("Storage no disponible.");

        if (!is_dir($dirFisico)) {
            if (!mkdir($dirFisico, 0755, true)) {
                throw new Exception("No se pudo crear el directorio de almacenamiento.");
            }
        }

        $dirFisicoReal = realpath($dirFisico);
        if (!$dirFisicoReal || strpos($dirFisicoReal, $storageBase) !== 0) {
            throw new Exception("Ruta de destino inválida.");
        }

        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new Exception("Error al guardar la carta compromiso en disco.");
        }

        global $conn;
        $modelo = new Solicitud($conn);
        return $modelo->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $rutaBD,
            tipo_mime: $mime_real,
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'etapa',
            visibilidad: 'privado',
            id_usuario: $id_estudiante,
            id_proyecto: $id_proyecto,
            id_etapa: 1,
            version: 1,
            id_plantilla: $id_plantilla,
            id_seguimiento: $id_seguimiento
        );
    }

    /**
     * Procesa el CV / constancias del estudiante (opcional).
     * Tipo: 'recurso', visibilidad: 'privado'.
     */
    private function procesarCvEstudiante(int $id_solicitud, int $id_usuario): ?int
    {
        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['documento'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) return null;
        if ($file['size'] > 8 * 1024 * 1024) return null;

        $mimes_permitidos = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes_permitidos, true)) return null;

        $nombreFinal = 'cv_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
        $base        = "/ITSFCP-PROYECTOS/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . $base;
        $rutaFisica  = $dirFisico . $nombreFinal;
        $rutaBD      = $base . $nombreFinal;

        if (!is_dir($dirFisico)) {
            mkdir($dirFisico, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) return null;

        global $conn;
        $modelo = new Solicitud($conn);
        return $modelo->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $rutaBD,
            tipo_mime: $mime_real,
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'recurso',
            visibilidad: 'privado',
            id_usuario: $id_usuario
        );
    }

    // 
    //  SECCIÓN A — INVESTIGADOR
    // 

    //  Helpers de vista 

    public function encabezados(): array
    {
        return ['#', 'Estudiante', 'Matrícula', 'Carrera', 'Proyecto', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }



    public function badgeEstado(string $estado): string
    {
        return match (strtolower($estado)) {
            'pendiente'    => "<span class='badge bg-secondary'>Pendiente</span>",
            'en_revision'  => "<span class='badge bg-info text-dark'>En revisión</span>",
            'correcciones' => "<span class='badge bg-warning text-dark'>Correcciones</span>",
            'aceptado'     => "<span class='badge bg-success'>Aceptado</span>",
            'rechazado'    => "<span class='badge bg-danger'>Rechazado</span>",
            'vencido'      => "<span class='badge bg-dark'>Vencido</span>",
            'cancelado'    => "<span class='badge bg-secondary'>Cancelado</span>",
            default        => "<span class='badge bg-light text-dark'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    //  index 

    public function index(int $id_usuario, string $rol): array
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);
        $this->vencido($id_usuario, $rol);

        $por_pagina = 6;
        $pagina     = max(1, intval($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $filtros = [
            'periodo'     => $_GET['periodo']     ?? '',
            'estado'      => $_GET['estado']      ?? '',
            'buscar'      => $_GET['buscar']       ?? '',
            'proyecto'    => $_GET['proyecto']     ?? '',
            'semestre'    => $_GET['semestre']     ?? '',
            'fecha_desde' => $_GET['fecha_desde']  ?? '',
            'fecha_hasta' => $_GET['fecha_hasta']  ?? '',
        ];

        $id_periodo  = !empty($filtros['periodo']) ? intval($filtros['periodo']) : null;

        $total       = $S->contarSolicitudes($id_usuario, $filtros);
        $solicitudes = $S->obtenerSolicitudes($id_usuario, $filtros, $desde, $por_pagina);
        $resumen     = $S->resumen($id_usuario, $id_periodo);
        $proyectos   = $S->proyectosDelInvestigador($id_usuario);
        $periodos    = $S->periodosDelInvestigador($id_usuario);

        return [
            'solicitudes' => $solicitudes,
            'resumen'     => $resumen,
            'proyectos'   => $proyectos,
            'periodos'    => $periodos,
            'filtros'     => $filtros,
            'paginacion'  => [
                'total'         => $total,
                'por_pagina'    => $por_pagina,
                'pagina'        => $pagina,
                'total_paginas' => max(1, (int) ceil($total / $por_pagina)),
            ],
        ];
    }

    //  aceptar ─

    public function aceptar(int $id_solicitud, int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);

        if (!$id_solicitud) {
            $this->redirigir("detalles_solicitud.php?id={$id_solicitud}", null, 'ID de solicitud inválido.');
        }

        if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
            $this->redirigir("detalles_solicitud.php?id={$id_solicitud}", null, 'No tiene permiso sobre esta solicitud.');
        }

        $conn->begin_transaction();
        try {
            $datos = $S->obtenerDatosSolicitud($id_solicitud);
            if (!$datos) throw new Exception("Solicitud no encontrada.");

            $S->aceptar($id_solicitud);
            $S->vincularEstudianteProyecto($datos['id_proyectos'], $datos['id_usuarios']);
            $S->vincularTareasAlNuevoEstudiante($datos['id_proyectos'], $datos['id_usuarios']);
            $S->registrarHistorialUsuario(
                $datos['id_proyectos'],
                $datos['id_usuarios'],
                'reactivado',
                'Solicitud de integración aceptada por el investigador',
                $id_usuario
            );

            $conn->commit();
            $this->redirigir(
                "detalles_solicitud.php?id={$id_solicitud}",
                'Solicitud aceptada. El estudiante ha sido integrado al proyecto.'
            );
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $this->redirigir(
                "detalles_solicitud.php?id={$id_solicitud}",
                null,
                'Ocurrió un error al procesar la aceptación. Intente nuevamente.'
            );
        }
    }


    public function pedirCorrecciones(int $id_solicitud, int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id_solicitud || $comentario === '') {
            $this->redirigir(
                "accion_solicitud.php?id={$id_solicitud}&tipo=correcciones",
                null,
                'El comentario es obligatorio.'
            );
        }

        $S = new Solicitud($conn);

        if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
            $this->redirigir(
                "detalles_solicitud.php?id={$id_solicitud}",
                null,
                'No tiene permiso sobre esta solicitud.'
            );
        }

        try {
            $id_doc = $this->procesarArchivoComentario($id_solicitud, $id_usuario);
            $ok     = $S->pedirCorrecciones($id_solicitud, $id_usuario, $comentario, $id_doc);

            if ($ok) {
                $this->redirigir(
                    "detalles_solicitud.php?id={$id_solicitud}",
                    'Correcciones enviadas al estudiante correctamente.'
                );
            } else {
                $this->redirigir(
                    "accion_solicitud.php?id={$id_solicitud}&tipo=correcciones",
                    null,
                    'No se pudieron guardar las correcciones. Intente nuevamente.'
                );
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir(
                "accion_solicitud.php?id={$id_solicitud}&tipo=correcciones",
                null,
                'Error interno al procesar. Intente nuevamente.'
            );
        }
    }

    //  rechazar 

    public function rechazar(int $id_solicitud, int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id_solicitud || $comentario === '') {
            $this->redirigir(
                "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar",
                null,
                'El motivo de rechazo es obligatorio.'
            );
        }

        $S = new Solicitud($conn);

        if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
            $this->redirigir(
                "detalles_solicitud.php?id={$id_solicitud}",
                null,
                'No tiene permiso sobre esta solicitud.'
            );
        }

        try {
            $id_doc = $this->procesarArchivoComentario($id_solicitud, $id_usuario);
            $ok     = $S->rechazar($id_solicitud, $id_usuario, $comentario, $id_doc);

            if ($ok) {
                $this->redirigir(
                    "detalles_solicitud.php?id={$id_solicitud}",
                    'La solicitud ha sido rechazada. El estudiante fue notificado.'
                );
            } else {
                $this->redirigir(
                    "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar",
                    null,
                    'No se pudo procesar el rechazo. Intente nuevamente.'
                );
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir(
                "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar",
                null,
                'Error interno al procesar. Intente nuevamente.'
            );
        }
    }

    //  responderCierre (etapa 3) ─

    public function responderCierre(int $id_seg, int $id_sol, int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $estado     = $_POST['estado']     ?? '';
        $comentario = trim($_POST['comentario'] ?? '');
        $estados_validos = ['completado', 'correcciones', 'rechazado'];

        if (!$id_seg || !in_array($estado, $estados_validos, true)) {
            $this->redirigir("detalles_solicitud.php?id={$id_sol}", null, 'Datos de cierre inválidos.');
        }

        if (in_array($estado, ['correcciones', 'rechazado'], true) && $comentario === '') {
            $this->redirigir(
                "accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}",
                null,
                'El comentario es obligatorio para esta acción.'
            );
        }

        $S = new Solicitud($conn);

        try {
            $ok = $S->actualizarEstadoCierre($id_seg, $estado, $id_usuario, $comentario);

            if ($ok) {
                $msgs = [
                    'completado'   => 'Cierre aprobado. El proyecto ha sido finalizado.',
                    'correcciones' => 'Correcciones de cierre enviadas al estudiante.',
                    'rechazado'    => 'Cierre rechazado. El estudiante fue notificado.',
                ];
                $this->redirigir("detalles_solicitud.php?id={$id_sol}", $msgs[$estado] ?? 'Acción realizada.');
            } else {
                $this->redirigir(
                    "accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}",
                    null,
                    'No se pudo actualizar el cierre. Intente nuevamente.'
                );
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir(
                "accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}",
                null,
                'Error interno al procesar. Intente nuevamente.'
            );
        }
    }


    /**
     * Marca automáticamente como vencidas las solicitudes cuya ventana de
     * solicitud del periodo ya cerró (fecha_fin_solicitud < HOY).
     *
     * CORRECCIÓN: usa fecha_fin_solicitud del periodo, NO fecha_fin del proyecto.
     */
    public function vencido(int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);
        $conn->begin_transaction();
        try {
            $ids_vencidos = $S->obtenervencido();
            foreach ($ids_vencidos as $id_vencido) {
                $datos = $S->obtenerDatosSolicitud($id_vencido);
                if (!$datos) continue;

                $S->vencido($id_vencido);
                $S->registrarHistorialUsuario(
                    $datos['id_proyectos'],
                    $datos['id_usuarios'],
                    'vencido',
                    'Solicitud vencida: la ventana de solicitudes del periodo ya cerró',
                    $id_usuario
                );
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
        }
    }


    public function detallePagina(int $id_solicitud, int $id_usuario, string $rol): array
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);

        if (!$id_solicitud) die('ID inválido.');
        if (!$S->verificarPermiso($id_solicitud, $id_usuario)) die('Sin permiso.');

        $S->marcarEnRevision($id_solicitud);

        $solicitud   = $S->obtenerDetalle($id_solicitud);
        $comentarios = $S->obtenerComentarios($id_solicitud);

        if (!$solicitud) die('Solicitud no encontrada.');

        return [
            'solicitud'   => $solicitud,
            'comentarios' => $comentarios,
        ];
    }


    public function getDatosSeguimientoEstudiante(int $id_proyecto, int $id_estudiante, int $id_usuario): array
    {
        global $conn;
        $S = new Solicitud($conn);
        return $S->getDatosSeguimientoEstudiante($id_proyecto, $id_estudiante, $id_usuario);
    }

    // 
    //  SECCIÓN B — ESTUDIANTE
    // 



    /**
     * Cancela la solicitud del estudiante (PRG).
     */
    public function cancelarSolicitud(int $id_solicitud, int $id_usuario, string $rol): void
    {
        $this->soloEstudiante($rol);
        global $conn;

        $S  = new Solicitud($conn);
        $ok = $S->cancelarSolicitud($id_solicitud, $id_usuario);

        if ($ok) {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Mis_solicitudes/index.php"
            );
        } else {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Mis_solicitudes/index.php?id={$id_solicitud}",
                null,
                'No fue posible cancelar la solicitud. Verifica su estado actual.'
            );
        }
    }

    /**
     * El estudiante responde a las correcciones del investigador,
     * adjuntando una nueva versión de la carta si es necesario.
     */
    public function enviarCorrecciones(int $id_solicitud, int $id_usuario, string $rol): void
    {
        $this->soloEstudiante($rol);
        global $conn;

        $S          = new Solicitud($conn);
        $comentario = trim($_POST['comentario'] ?? '');

        // Obtener datos para saber el proyecto (necesario para guardar carta)
        $datos = $S->obtenerDatosSolicitud($id_solicitud);
        if (!$datos) {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Mis_solicitudes/index.php?id={$id_solicitud}",
                null,
                'Solicitud no encontrada.'
            );
        }

        $conn->begin_transaction();
        try {
            $id_doc = null;

            // Si sube una nueva carta compromiso
            if (!empty($_FILES['carta_compromiso']) && $_FILES['carta_compromiso']['error'] === UPLOAD_ERR_OK) {
                $plantilla    = $S->obtenerPlantillaCartaCompromiso();
                $id_plantilla = $plantilla ? (int)$plantilla['id_plantilla'] : 0;

                // Buscar seguimiento existente
                $seguimiento = $S->obtenerSolicitudEstudiante($datos['id_proyectos'], $id_usuario);
                $id_seg      = (int)($seguimiento['seg_id'] ?? 0);

                if (!$id_seg) {
                    $id_seg = $S->crearSeguimientoCartaCompromiso($datos['id_proyectos'], $id_usuario, 'proceso');
                }

                $id_doc = $this->procesarCartaCompromiso(
                    'carta_compromiso',
                    $datos['id_proyectos'],
                    $id_usuario,
                    $id_seg,
                    $id_plantilla
                );
            }

            $S->enviarCorrecciones($id_solicitud, $id_usuario, $comentario, $id_doc);
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("[enviarCorrecciones estudiante] " . $e->getMessage());
        }

        $this->redirigir(
            "/ITSFCP-PROYECTOS/Vistas/Mis_solicitudes/index.php?id={$id_solicitud}",
            'Respuesta enviada. El investigador revisará tu solicitud.'
        );
    }

    /**
     * Devuelve el estado de la solicitud + carta para mostrarlo en la vista del estudiante.
     */
    public function obtenerEstadoSolicitud(int $id_proyecto, int $id_usuario, string $rol): array
    {
        $this->soloEstudiante($rol);
        global $conn;
        $S = new Solicitud($conn);
        return $S->obtenerSolicitudEstudiante($id_proyecto, $id_usuario) ?? [];
    }

    /**
     * Descarga segura de una plantilla (carta compromiso u otras).
     * Puede ser usada por cualquier usuario autenticado.
     */
    public function descargarPlantilla(int $id_plantilla): void
    {
        if (empty($id_plantilla)) {
            http_response_code(400);
            exit('Plantilla inválida.');
        }

        global $conn;
        $S    = new Solicitud($conn);
        $file = $S->obtenerPlantillaPorId($id_plantilla);

        if (!$file || !$file['plantilla_activa'] || !$file['archivo_activo']) {
            http_response_code(404);
            exit('Plantilla no disponible.');
        }

        // Resolver ruta segura
        $storageBase = realpath($_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/storage');
        if (!$storageBase) {
            http_response_code(500);
            exit('Storage no disponible.');
        }

        // La ruta en BD puede ser absoluta (/ITSFCP-PROYECTOS/storage/...) o relativa
        $rutaRelativa = ltrim($file['ruta'], '/');
        $rutaRelativa = preg_replace('#^ITSFCP-PROYECTOS/#', '', $rutaRelativa);

        $rutaCompleta = realpath($_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/' . $rutaRelativa);

        if (!$rutaCompleta || !file_exists($rutaCompleta)) {
            http_response_code(404);
            exit('Archivo inexistente.');
        }

        // Prevenir path traversal
        if (strpos($rutaCompleta, $storageBase) !== 0) {
            http_response_code(403);
            exit('Ruta inválida.');
        }

        // Limpiar buffer
        if (ob_get_length()) ob_end_clean();

        // MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $rutaCompleta);
        finfo_close($finfo);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($file['nombre_archivo']) . '"');
        header('Content-Length: ' . filesize($rutaCompleta));
        header('Pragma: public');
        header('Cache-Control: must-revalidate');

        readfile($rutaCompleta);
        exit;
    }

    // 
    //  PRG REDIRECT
    // 

    private function redirigir(string $destino, ?string $ok = null, ?string $error = null): never
    {
        $sep = str_contains($destino, '?') ? '&' : '?';

        if ($ok !== null) {
            header("Location: {$destino}{$sep}ok=" . urlencode($ok));
        } elseif ($error !== null) {
            header("Location: {$destino}{$sep}error=" . urlencode($error));
        } else {
            header("Location: {$destino}");
        }
        exit;
    }


    /*Solicitud_integracion.php */

    // 
    //  Datos completos para el formulario de solicitud
    //  Devuelve null en 'proyecto' o 'estudiante' si no se encuentran,
    //  para que la vista pueda redirigir.
    // 
    public function obtenerDatosFormulario(int $id_proyecto, int $id_usuario): array
    {
        global $conn;
        $modelo = new Solicitud($conn);

        $proyecto   = $modelo->obtenerProyecto($id_proyecto);
        $estudiante = $modelo->obtenerEstudiante($id_usuario);
        $carreras   = $modelo->obtenerCarreras();
        $plantilla  = $modelo->obtenerPlantillaCartaCompromiso();

        return [
            'proyecto'   => $proyecto,
            'estudiante' => $estudiante,
            'carreras'   => $carreras,
            'plantilla'  => $plantilla,   // null si no existe plantilla activa
        ];
    }

    // 
    //  Badge de modalidad (reutilizado de principalControlador)
    // 
    public function badgeModalidad(string $modalidad): string
    {
        return match ($modalidad) {
            'virtual' => 'badge-modal-virtual',
            'fisico'  => 'badge-modal-fisico',
            'mixto'   => 'badge-modal-mixto',
            default   => '',
        };
    }

    /**
     * Procesa el envío del formulario de solicitud de integración.
     *
     * Flujo completo:
     *  1. Validar que la ventana de solicitud está activa para el proyecto
     *  2. Crear solicitud en BD
     *  3. Guardar CV (opcional)
     *  4. Crear seguimiento de carta compromiso (etapa 1)
     *  5. Guardar carta compromiso si se adjuntó
     *  6. Redirigir con PRG
     *
     * Lee datos de $_POST y $_FILES. Parámetros de identidad son explícitos.
     */

    public function enviarSolicitud(int $id_proyecto, int $id_usuario, string $rol): void
    {
        $this->soloEstudiante($rol);
        global $conn;

        $S = new Solicitud($conn);

        // 1. Validar ventana de solicitud del periodo activo
        $periodo = $S->obtenerPeriodoActivoParaProyecto($id_proyecto);
        if (!$periodo) {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Solicitudes_proyecto/solicitud_integracion.php?id_proyecto={$id_proyecto}",
                null,
                'La ventana de solicitudes no está activa para este proyecto.'
            );
        }
        $id_periodo = (int)$periodo['id_periodos'];

        // 2. Recoger y sanitizar datos del formulario
        $promedio    = isset($_POST['promedio'])    && $_POST['promedio']    !== '' ? (float)$_POST['promedio']   : null;
        $motivacion  = trim($_POST['motivacion']    ?? '');
        $experiencia = trim($_POST['experiencia']   ?? '');
        $semestre    = isset($_POST['semestre'])    && $_POST['semestre']    !== '' ? (int)$_POST['semestre']     : null;

        if ($motivacion === '' || $experiencia === '') {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Solicitudes_proyecto/solicitud_integracion.php?id_proyecto={$id_proyecto}",
                null,
                'Motivación y experiencia son campos obligatorios.'
            );
        }

        // 3. Carta compromiso obligatoria
        if (empty($_FILES['carta_compromiso']) || $_FILES['carta_compromiso']['error'] !== UPLOAD_ERR_OK) {
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Solicitudes_proyecto/solicitud_integracion.php?id_proyecto={$id_proyecto}",
                null,
                'Debes adjuntar la carta compromiso firmada para enviar la solicitud.'
            );
        }

        // 4. Plantilla vigente para vincular al documento
        $plantilla    = $S->obtenerPlantillaCartaCompromiso();
        $id_plantilla = $plantilla ? (int)$plantilla['id_plantilla'] : 0;

        $conn->begin_transaction();
        try {
            // 5. Crear la solicitud en BD
            $id_solicitud = $S->crearSolicitud(
                id_proyecto: $id_proyecto,
                id_estudiante: $id_usuario,
                id_periodo: $id_periodo,
                promedio: $promedio,
                motivacion: $motivacion,
                experiencia: $experiencia,
                semestre: $semestre
            );

            // 6. CV opcional
            $id_doc_cv = $this->procesarCvEstudiante($id_solicitud, $id_usuario);
            if ($id_doc_cv) {
                $S->actualizarDocumentoSolicitud($id_solicitud, $id_doc_cv);
            }

            // 7. Seguimiento etapa 1 (carta compromiso)
            $id_seguimiento = $S->crearSeguimientoCartaCompromiso(
                id_proyecto: $id_proyecto,
                id_estudiante: $id_usuario,
                estado: 'proceso'
            );

            // 8. Guardar carta compromiso firmada
            $id_doc_carta = $this->procesarCartaCompromiso(
                campo_file: 'carta_compromiso',
                id_proyecto: $id_proyecto,
                id_estudiante: $id_usuario,
                id_seguimiento: $id_seguimiento,
                id_plantilla: $id_plantilla
            );

            if (!$id_doc_carta) {
                throw new Exception("No se pudo guardar la carta compromiso.");
            }

            $S->marcarSeguimientoEnProceso($id_seguimiento);

            // 
            // Se ejecutan dentro de la transacción para que todo se revierta
            // si algo falla. Si tu tabla notificaciones está en otra BD o
            // prefieres que las notificaciones no bloqueen el proceso, muévelas
            // fuera del try (después del commit) y envuélvelas en su propio try.
            $titulo_proyecto = $S->obtenerTituloProyecto($id_proyecto);

            $enlace = "/ITSFCP-PROYECTOS/Vistas/Proyectos/detalles_proyecto.php?id={$id_proyecto}";

            // Notificación al estudiante
            $S->insertarNotificacion(
                id_usuario: $id_usuario,
                titulo: 'Solicitud enviada',
                contenido: "Has enviado una solicitud para el proyecto: <b>" . htmlspecialchars($titulo_proyecto) . "</b>. En espera de revisión.",
                enlace: $enlace
            );

            // Notificación a todos los supervisores activos
            $supervisores = $S->obtenerSupervisoresActivos();
            foreach ($supervisores as $sup) {
                $S->insertarNotificacion(
                    id_usuario: (int)$sup['id_usuarios'],
                    titulo: 'Nueva solicitud de integración',
                    contenido: "Un estudiante ha enviado una solicitud para el proyecto: <b>" . htmlspecialchars($titulo_proyecto) . "</b>.",
                    enlace: $enlace
                );
            }
            // 

            $conn->commit();

            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Solicitudes_proyecto/solicitud_integracion.php?id_proyecto={$id_proyecto}",
                'Tu solicitud ha sido enviada correctamente. El investigador la revisará pronto.'
            );
        } catch (Exception $e) {
            $conn->rollback();
            error_log("[enviarSolicitud] " . $e->getMessage());
            $this->redirigir(
                "/ITSFCP-PROYECTOS/Vistas/Solicitudes_proyecto/solicitud_integracion.php?id_proyecto={$id_proyecto}",
                null,
                'Error al procesar la solicitud: ' . htmlspecialchars($e->getMessage())
            );
        }
    }

    public function botonesAccion(int $id_solicitud, string $estado, int $id_proyecto, array $filtros = []): string
    {
        $estado = strtolower(trim($estado));

        $btns = "<a href='detalles_solicitud.php?id={$id_solicitud}' 
                    class='btn btn-primary btn-sm'
                    title='Ver solicitud'>
                    <i class='bi bi-file-text-fill'></i>
                 </a>";

        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            $btns .= "<form method='POST' action='index.php' style='display:inline'
                      onsubmit='return confirm(\"¿Aceptar esta solicitud? El estudiante quedará integrado al proyecto.\")'>
                    <input type='hidden' name='accion'       value='aceptar'>
                    <input type='hidden' name='id_solicitud' value='{$id_solicitud}'>
                    <button type='submit' class='btn btn-success btn-sm' title='Aceptar solicitud'>
                        <i class='bi bi-check-circle-fill'></i>
                    </button>
                </form>";

            $btns .= "<a href='accion_solicitud.php?id={$id_solicitud}&tipo=correcciones'
                         class='btn btn-warning btn-sm' title='Pedir correcciones'>
                         <i class='bi bi-pencil-fill'></i>
                      </a>";

            $btns .= "<a href='accion_solicitud.php?id={$id_solicitud}&tipo=rechazar'
                         class='btn btn-danger btn-sm' title='Rechazar solicitud'>
                         <i class='bi bi-ban'></i>
                      </a>";
        }

        return $btns;
    }

    //Periodo Actuar para mandar solicitud de integración a proyecto - Solicitudes estudiante -> sinvestigador

    public function periodoactualSolicitud(): ?array
    {
        global $conn;
        try {
            $S = new Solicitud($conn);

            return ($S->periodoactualSolicitud());
        } catch (Exception $e) {
            error_log($e->getMessage());
            header("Location: index.php?error=1");
            exit;
        }
    }
}
