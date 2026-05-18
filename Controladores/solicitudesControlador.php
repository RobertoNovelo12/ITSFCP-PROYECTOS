<?php
require_once __DIR__ . '/../Modelos/solicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';

/**
 * Controlador de Solicitudes de Integración.
 *
 * Todos los métodos de acción reciben sus datos como parámetros explícitos
 * (sin leer $_POST/$_SESSION directamente) y redirigen con mensajes GET
 * al terminar, siguiendo el patrón PRG (Post-Redirect-Get).
 */
class solicitudesControlador
{
    //  Guardas de rol ─

    private function soloInvestigador(string $rol): void
    {
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            die('Acceso denegado.');
        }
    }

    //  Helpers de archivos 

    /**
     * Procesa el archivo adjunto (si existe) y devuelve el id_documento
     * registrado en documentos_subidos, o null si no hay archivo.
     */
    private function procesarArchivo(int $id_solicitud, int $id_usuario): ?int
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['archivo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['pdf', 'docx', 'png', 'jpg'], true)) return null;
        if ($file['size'] > 8 * 1024 * 1024) return null;

        $mimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
            'image/jpeg',
        ];
        if (!in_array($file['type'], $mimes, true)) return null;

        $nombreFinal = 'sol_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
        $base        = "/ITSFCP-PROYECTOS/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
        $rutaFisica  = $_SERVER['DOCUMENT_ROOT'] . $base . $nombreFinal;
        $rutaBD      = $base . $nombreFinal;

        if (!is_dir(dirname($rutaFisica))) {
            mkdir(dirname($rutaFisica), 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new Exception("Error al guardar el archivo en disco.");
        }

        global $conn;
        $modelo = new Solicitud($conn);
        return $modelo->registrarDocumento(
            nombre:         basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta:           $rutaBD,
            tipo_mime:      $file['type'],
            extension:      $ext,
            tamano_bytes:   $file['size'],
            tipo:           'recurso',
            visibilidad:    'privado',
            id_usuario:     $id_usuario
        );
    }

    //  Helpers de vista ─

    public function encabezados(): array
    {
        return ['#', 'Estudiante', 'Matrícula', 'Carrera', 'Proyecto', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }

    /**
     * Genera los botones de acción para la fila de la tabla.
     * Aceptar va como formulario inline en la vista; correcciones y rechazo
     * enlazan a la página dedicada accion_solicitud.php.
     *
     * @param array $filtros Filtros actuales de la URL (para conservar en la vuelta).
     */
    public function botonesAccion(int $id_solicitud, string $estado, int $id_proyecto, array $filtros = []): string
    {
        $estado = strtolower(trim($estado));

        // Enlace al detalle
        $btns = "<a href='detalles_solicitud.php?id={$id_solicitud}'
                    class='btn btn-primary btn-sm'
                    title='Ver solicitud'>
                    <i class='bi bi-file-text-fill'></i>
                 </a>";

        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            // Aceptar: mini-form POST en la misma tabla
            $btns .= "
                <form method='POST' action='tabla.php' style='display:inline'
                      onsubmit='return confirm(\"¿Aceptar esta solicitud? El estudiante quedará integrado al proyecto.\")'>
                    <input type='hidden' name='accion'       value='aceptar'>
                    <input type='hidden' name='id_solicitud' value='{$id_solicitud}'>
                    <button type='submit' class='btn btn-success btn-sm' title='Aceptar solicitud'>
                        <i class='bi bi-check-circle-fill'></i>
                    </button>
                </form>";

            // Correcciones y rechazo: enlace a página dedicada
            $btns .= "<a href='accion_solicitud.php?id={$id_solicitud}&tipo=correcciones'
                         class='btn btn-warning btn-sm'
                         title='Pedir correcciones'>
                         <i class='bi bi-pencil-fill'></i>
                      </a>";

            $btns .= "<a href='accion_solicitud.php?id={$id_solicitud}&tipo=rechazar'
                         class='btn btn-danger btn-sm'
                         title='Rechazar solicitud'>
                         <i class='bi bi-ban'></i>
                      </a>";
        }

        return $btns;
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

        $por_pagina = 8;
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

    //  aceptar 

    /**
     * Acepta la solicitud y redirige con mensaje de resultado.
     * Llamado desde tabla.php o detalles_solicitud.php vía POST.
     */
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

    //  pedirCorrecciones 

    /**
     * Guarda correcciones y redirige.
     * Lee comentario y archivo de $_POST/$_FILES.
     */
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
            $id_doc = $this->procesarArchivo($id_solicitud, $id_usuario);
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

    //  rechazar ─

    /**
     * Rechaza la solicitud y redirige.
     * Lee comentario y archivo de $_POST/$_FILES.
     */
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
            $id_doc = $this->procesarArchivo($id_solicitud, $id_usuario);
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

    //  responderCierre (etapa 3) 

    /**
     * Aprueba, pide correcciones o rechaza el cierre del proyecto (etapa 3).
     * Lee id_seguimiento, estado y comentario de $_POST.
     */
    public function responderCierre(int $id_seg, int $id_sol, int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $estado     = $_POST['estado']     ?? '';
        $comentario = trim($_POST['comentario'] ?? '');

        $estados_validos = ['completado', 'correcciones', 'rechazado'];

        if (!$id_seg || !in_array($estado, $estados_validos, true)) {
            $this->redirigir(
                "detalles_solicitud.php?id={$id_sol}",
                null,
                'Datos de cierre inválidos.'
            );
        }

        // Comentario obligatorio al rechazar o pedir correcciones
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
                $this->redirigir(
                    "detalles_solicitud.php?id={$id_sol}",
                    $msgs[$estado] ?? 'Acción realizada.'
                );
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

    //  vencido 

    /**
     * Marca automáticamente como vencidas las solicitudes de proyectos
     * cuya fecha de fin ya pasó y aún no tienen respuesta definitiva.
     */
    public function vencido(int $id_usuario, string $rol): void
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);
        $conn->begin_transaction();
        try {
            $id_vencidos = $S->obtenervencido();
            foreach ($id_vencidos as $id_vencido) {
                $datos = $S->obtenerDatosSolicitud($id_vencido);
                if (!$datos) continue;

                $S->vencido($id_vencido);
                $S->registrarHistorialUsuario(
                    $datos['id_proyectos'],
                    $datos['id_usuarios'],
                    'vencido',
                    'Solicitud de integración vencida al no responder el investigador',
                    $id_usuario
                );
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
        }
    }

    //  enviarCorrecciones (estudiante) 

    public function enviarCorrecciones(int $id_solicitud, int $id_usuario): void
    {
        if (strtolower($_SESSION['rol'] ?? '') !== 'estudiante') {
            http_response_code(403);
            die('Acceso denegado.');
        }

        global $conn;
        $S          = new Solicitud($conn);
        $comentario = trim($_POST['comentario'] ?? '');

        try {
            $id_doc = $this->procesarArchivo($id_solicitud, $id_usuario);
            $S->enviarCorrecciones($id_solicitud, $id_usuario, $comentario, $id_doc);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        // Redirige de vuelta a la vista del estudiante
        header("Location: /ITSFCP-PROYECTOS/Vistas/estudiante/solicitud.php?id={$id_solicitud}&ok=Respuesta+enviada");
        exit;
    }

    //  detallePagina 

    public function detallePagina(int $id_solicitud, int $id_usuario, string $rol): array
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S = new Solicitud($conn);

        if (!$id_solicitud) die('ID inválido.');
        if (!$S->verificarPermiso($id_solicitud, $id_usuario)) die('Sin permiso.');

        // Marcar en revisión si sigue pendiente
        $S->marcarEnRevision($id_solicitud);

        $solicitud   = $S->obtenerDetalle($id_solicitud);
        $comentarios = $S->obtenerComentarios($id_solicitud);

        if (!$solicitud) die('Solicitud no encontrada.');

        return [
            'solicitud'   => $solicitud,
            'comentarios' => $comentarios,
        ];
    }

    //  getDatosSeguimientoEstudiante 

    public function getDatosSeguimientoEstudiante(int $id_proyecto, int $id_estudiante, int $id_usuario): array
    {
        global $conn;
        $S = new Solicitud($conn);
        return $S->getDatosSeguimientoEstudiante($id_proyecto, $id_estudiante, $id_usuario);
    }

    //  Utilidad: PRG redirect ─

    /**
     * Redirige con mensaje de éxito (ok=…) o error (error=…) en la URL.
     */
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
}