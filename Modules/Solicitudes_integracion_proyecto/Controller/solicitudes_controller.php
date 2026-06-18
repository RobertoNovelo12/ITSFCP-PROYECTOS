<?php
// Controladores/solicitudesControlador.php
//
//  SECCIÓN A  │  Módulo del INVESTIGADOR
//  SECCIÓN B  │  Módulo del ESTUDIANTE
//
// Patrón PRG: todos los métodos de acción redirigen con ?msg= al terminar.

require_once __DIR__ . '/../Model/solicitudes_model.php';
require_once __DIR__ . '/../../../public/config/conexion.php';
require_once __DIR__ . '/../../../public/incluido/BaseControlador.php';
include __DIR__ . '/../../../public/incluido/_botones.php';

class solicitudesControlador extends BaseControlador
{

    // ─
    // GUARDAS DE ROL
    // ─

    private function soloInvestigador(string $rol): void
    {
        $this->validarAcceso($rol, ['investigador', 'profesor']);
    }

    private function soloEstudiante(string $rol): void
    {
        $this->validarAcceso($rol, ['estudiante']);
    }


    // ─
    // HELPERS DE ARCHIVOS
    // ─

    /**
     * Procesa un archivo adjunto de comentario del investigador.
     * Tipo: 'recurso', visibilidad: 'privado'.
     * Devuelve id_documento o null si no hay archivo o es inválido.
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

        $mimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
            'image/jpeg',
        ];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes, true)) return null;

        $nombreFinal = 'sol_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
        $base        = "/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . $base;

        if (!is_dir($dirFisico)) mkdir($dirFisico, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dirFisico . $nombreFinal)) {
            throw new Exception("Error al guardar el archivo en disco.");
        }

        global $conn;
        return (new Solicitud($conn))->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $base . $nombreFinal,
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
     * Acepta: pdf, docx, png. Máx. 10 MB.
     */
    private function procesarCartaCompromiso(
        string $campo_file,
        int    $id_proyectos,
        int    $id_estudiante,
        int    $id_seguimiento,
        int    $id_plantilla
    ): ?int {
        if (empty($_FILES[$campo_file]) || $_FILES[$campo_file]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$campo_file];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $exts_ok  = ['pdf', 'docx', 'png'];
        $mimes_ok = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
        ];

        if (!in_array($ext, $exts_ok, true)) {
            throw new Exception("Tipo de archivo no permitido. Use PDF, DOCX o PNG.");
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception("El archivo supera el máximo de 10 MB.");
        }

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes_ok, true)) {
            throw new Exception("El contenido del archivo no coincide con su extensión.");
        }

        $nombreFinal = "est{$id_estudiante}_td1_" . date('YmdHis') . '.' . $ext;
        $dirRelativo = "storage/etapas/proyecto_{$id_proyectos}/";
        $dirFisico   = realpath($_SERVER['DOCUMENT_ROOT'] . '/') . '/' . $dirRelativo;
        $rutaBD      = $dirRelativo . $nombreFinal;

        $storageBase = realpath($_SERVER['DOCUMENT_ROOT'] . '/storage');
        if (!$storageBase) throw new Exception("Storage no disponible.");

        if (!is_dir($dirFisico) && !mkdir($dirFisico, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de almacenamiento.");
        }

        $dirFisicoReal = realpath($dirFisico);
        if (!$dirFisicoReal || strpos($dirFisicoReal, $storageBase) !== 0) {
            throw new Exception("Ruta de destino inválida.");
        }

        if (!move_uploaded_file($file['tmp_name'], $dirFisico . $nombreFinal)) {
            throw new Exception("Error al guardar la carta compromiso en disco.");
        }

        global $conn;
        return (new Solicitud($conn))->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $rutaBD,
            tipo_mime: $mime_real,
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'etapa',
            visibilidad: 'privado',
            id_usuario: $id_estudiante,
            id_proyectos: $id_proyectos,
            id_etapa: 1,
            version: 1,
            id_plantilla: $id_plantilla,
            id_seguimiento: $id_seguimiento
        );
    }

    /**
     * Procesa el CV / constancias del estudiante (opcional).
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

        $mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_real, $mimes, true)) return null;

        $nombreFinal = 'cv_' . $id_solicitud . '_' . uniqid() . '.' . $ext;
        $base        = "/storage/recursos/solicitudes/solicitud_{$id_solicitud}/";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . $base;

        if (!is_dir($dirFisico)) mkdir($dirFisico, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dirFisico . $nombreFinal)) return null;

        global $conn;
        return (new Solicitud($conn))->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $base . $nombreFinal,
            tipo_mime: $mime_real,
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'recurso',
            visibilidad: 'privado',
            id_usuario: $id_usuario
        );
    }


    // ─
    // SECCIÓN A — INVESTIGADOR
    // ─

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
        global $conn;
        try {
            $this->soloInvestigador($rol);

            $S = new Solicitud($conn);
            $this->vencido($id_usuario, $rol);

            $S->marcarSolicitudesProyectosVencidos();

            $por_pagina = 6;
            $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
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

            $id_periodo  = !empty($filtros['periodo']) ? (int)$filtros['periodo'] : null;
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
                    'total_paginas' => max(1, (int)ceil($total / $por_pagina)),
                ],
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['solicitudes' => [], 'resumen' => [], 'proyectos' => [], 'periodos' => [], 'filtros' => [], 'paginacion' => []];
        }
    }

    //  aceptar 

    public function aceptar(int $id_solicitud, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloInvestigador($rol);

            if (!$id_solicitud) {
                $this->redirigir('error_datos', "detalles_solicitud.php?id={$id_solicitud}");
            }

            $S = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
                $this->redirigir('sin_permiso', "detalles_solicitud.php?id={$id_solicitud}");
            }

            $conn->begin_transaction();
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

            $this->redirigir('exito_aceptar', "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_solicitud}");
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
            $this->redirigir('error_aceptar', "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_solicitud}");
        }
    }

    //  pedirCorrecciones 

    public function pedirCorrecciones(int $id_solicitud, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloInvestigador($rol);

            $comentario = trim($_POST['comentario'] ?? '');
            if (!$id_solicitud || $comentario === '') {
                $this->redirigir('error_datos', "/Modules/Solicitudes_integracion_proyecto/Views/accion_solicitud.php?id={$id_solicitud}&tipo=correcciones");
            }

            $S = new Solicitud($conn);
            if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
                $this->redirigir('sin_permiso', "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_solicitud}");
            }

            $id_doc = $this->procesarArchivoComentario($id_solicitud, $id_usuario);
            $ok     = $S->pedirCorrecciones($id_solicitud, $id_usuario, $comentario, $id_doc);

            $msg     = $ok ? 'exito_correcciones' : 'error_correcciones';
            $destino = $ok
                ? "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_solicitud}"
                : "/Modules/Solicitudes_integracion_proyecto/Views/accion_solicitud.php?id={$id_solicitud}&tipo=correcciones";

            $this->redirigir($msg, $destino);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_correcciones', "/Modules/Solicitudes_integracion_proyecto/Views/accion_solicitud.php?id={$id_solicitud}&tipo=correcciones");
        }
    }

    //  rechazar 

    public function rechazar(int $id_solicitud, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloInvestigador($rol);

            $comentario = trim($_POST['comentario'] ?? '');
            if (!$id_solicitud || $comentario === '') {
                $this->redirigir('error_datos', "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar");
            }

            $S = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
                $this->redirigir('sin_permiso', "detalles_solicitud.php?id={$id_solicitud}");
            }

            $id_doc = $this->procesarArchivoComentario($id_solicitud, $id_usuario);
            $ok     = $S->rechazar($id_solicitud, $id_usuario, $comentario, $id_doc);

            $msg     = $ok ? 'exito_rechazo' : 'error_rechazo';
            $destino = $ok
                ? "detalles_solicitud.php?id={$id_solicitud}"
                : "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar";

            $this->redirigir($msg, $destino);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_rechazo', "accion_solicitud.php?id={$id_solicitud}&tipo=rechazar");
        }
    }

    //  responderCierre (etapa 3) 

    public function responderCierre(int $id_seg, int $id_sol, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloInvestigador($rol);

            $estado          = $_POST['estado']          ?? '';
            $comentario      = trim($_POST['comentario'] ?? '');
            $estados_validos = ['completado', 'correcciones', 'rechazado'];

            if (!$id_seg || !in_array($estado, $estados_validos, true)) {
                $this->redirigir('error_datos', "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_sol}");
            }

            if (in_array($estado, ['correcciones', 'rechazado'], true) && $comentario === '') {
                $this->redirigir('error_comentario_requerido', "/Modules/Solicitudes_integracion_proyecto/Views/accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}");
            }

            $S  = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            $ok = $S->actualizarEstadoCierre($id_seg, $estado, $id_usuario, $comentario);

            $msgs_exito = [
                'completado'   => 'exito_cierre_aprobado',
                'correcciones' => 'exito_cierre_correcciones',
                'rechazado'    => 'exito_cierre_rechazado',
            ];

            $msg     = $ok ? ($msgs_exito[$estado] ?? 'exito_cierre_aprobado') : 'error_cierre';
            $destino = $ok
                ? "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_sol}"
                : "/Modules/Solicitudes_integracion_proyecto/Views/accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}";

            $this->redirigir($msg, $destino);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cierre', "/Modules/Solicitudes_integracion_proyecto/Views/accion_cierre.php?id_seg={$id_seg}&id_sol={$id_sol}&estado={$estado}");
        }
    }

    //  vencido 

    public function vencido(int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->soloInvestigador($rol);

            $S = new Solicitud($conn);
            $conn->begin_transaction();

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
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log($e->getMessage());
        }
    }

    //  detallePagina 

    public function detallePagina(int $id_solicitud, int $id_usuario, string $rol)
    {
        global $conn;
        try {
            $this->soloInvestigador($rol);

            if (!$id_solicitud) {
                $this->redirigir('error_datos');
            }

            $S = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            if (!$S->verificarPermiso($id_solicitud, $id_usuario)) {
                $this->redirigir('sin_permiso');
            }

            $S->marcarEnRevision($id_solicitud);
            $solicitud   = $S->obtenerDetalle($id_solicitud);
            $comentarios = $S->obtenerComentarios($id_solicitud);

            if (!$solicitud) {
                $this->redirigir('error_datos', '/Modules/Solicitudes_integracion_proyecto/Views/index.php');
            }

            return [
                'solicitud'   => $solicitud,
                'comentarios' => $comentarios,
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar', '/Modules/Solicitudes_integracion_proyecto/Views/index.php');
        }
    }

    public function DatosSeguimientoEstudiante(int $id_proyectos, int $id_estudiante, int $id_usuario): array
    {
        global $conn;
        return (new Solicitud($conn))->DatosSeguimientoEstudiante($id_proyectos, $id_estudiante, $id_usuario);
    }

    public function botonesAccion(int $id_solicitud, string $estado, int $id_proyectos, array $filtros = []): string
    {
        include __DIR__ . '/../../../public/incluido/_iconos.php';

        $estado = strtolower(trim($estado));

        // Botón "Ver solicitud" siempre presente
        $btns = Botones::botonIcono(
            'detalles_solicitud.php?id=' . $id_solicitud,
            'primary',
            $iconos['detalles']['subinformacion'],
            'Ver solicitud'
        );

        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {

            // Aceptar — formulario POST con confirm
            $btns .= '
            <form method="POST" action="index.php" style="display:inline"
                  onsubmit="return confirm(\'¿Aceptar esta solicitud? El estudiante quedará integrado al proyecto.\')">
                <input type="hidden" name="accion"       value="aceptar">
                <input type="hidden" name="id_solicitud" value="' . $id_solicitud . '">
                <button type="submit"
                        class="btn btn-success btn-sm"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-bs-custom-class="custom-tooltip"
                        data-bs-title="Aceptar solicitud">
                    <i class="' . $iconos['tabla']['aprobar'] . '"></i>
                </button>
            </form>';

            $btns .= Botones::botonIcono(
                'accion_solicitud.php?id=' . $id_solicitud . '&tipo=correcciones',
                'warning',
                $iconos['tabla']['editar'],
                'Pedir correcciones'
            );

            $btns .= Botones::botonIcono(
                'accion_solicitud.php?id=' . $id_solicitud . '&tipo=rechazar',
                'danger',
                $iconos['tabla']['rechazar'],
                'Rechazar solicitud'
            );
        }

        return $btns;
    }


    public function periodoactualSolicitud()
    {
        global $conn;
        try {
            return (new Solicitud($conn))->periodoactualSolicitud();
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cargar', '/Modules/Principal/Views/index.php');
        }
    }

    public function obtenerDatosFormulario(int $id_proyectos, int $id_usuario): array
    {
        global $conn;
        $modelo = new Solicitud($conn);
        return [
            'proyecto'   => $modelo->obtenerProyecto($id_proyectos),
            'estudiante' => $modelo->obtenerEstudiante($id_usuario),
            'carreras'   => $modelo->obtenerCarreras(),
            'plantilla'  => $modelo->obtenerPlantillaCartaCompromiso(),
            'puede_solicitar' => $modelo->puedeSolicitarIntegracion($id_proyectos, $id_usuario),
        ];
    }

    public function badgeModalidad(string $modalidad): string
    {
        return match ($modalidad) {
            'virtual' => 'badge-modal-virtual',
            'fisico'  => 'badge-modal-fisico',
            'mixto'   => 'badge-modal-mixto',
            default   => '',
        };
    }


    // ─
    // SECCIÓN B — ESTUDIANTE
    // ─

    public function cancelarSolicitud(int $id_solicitud, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloEstudiante($rol);

            $ok  = (new Solicitud($conn))->cancelarSolicitud($id_solicitud, $id_usuario);
            $msg = $ok ? 'exito_cancelar' : 'error_cancelar';
            $this->redirigir($msg, "/Modules/Solicitudes_integracion_proyecto/Views/index.php");
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->redirigir('error_cancelar', "/Modules/Solicitudes_integracion_proyecto/Views/index.php");
        }
    }

    public function enviarCorrecciones(int $id_solicitud, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->soloEstudiante($rol);

            $S          = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            $comentario = trim($_POST['comentario'] ?? '');
            $datos      = $S->obtenerDatosSolicitud($id_solicitud);

            if (!$datos) {
                $this->redirigir('error_datos', "/Modules/Solicitudes_integracion_proyecto/Views/index.php?id={$id_solicitud}");
            }

            $conn->begin_transaction();
            $id_doc = null;

            if (!empty($_FILES['carta_compromiso']) && $_FILES['carta_compromiso']['error'] === UPLOAD_ERR_OK) {
                $plantilla    = $S->obtenerPlantillaCartaCompromiso();
                $id_plantilla = $plantilla ? (int)$plantilla['id_plantilla'] : 0;

                $seguimiento = $S->obtenerSolicitudEstudiante($datos['id_proyectos'], $id_usuario);
                $id_seg      = (int)($seguimiento['seg_id'] ?? 0);

                if (!$id_seg) {
                    $id_seg = $S->crearSeguimientoCartaCompromiso($datos['id_proyectos'], $id_usuario, 'proceso', $id_plantilla);
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

            $this->redirigir('exito_correcciones_enviadas', "/Modules/Solicitudes_integracion_proyecto/Views/index.php?id={$id_solicitud}");
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log("[enviarCorrecciones estudiante] " . $e->getMessage());
            $this->redirigir('error_correcciones', "/Modules/Solicitudes_integracion_proyecto/Views/index.php?id={$id_solicitud}");
        }
    }

    public function obtenerEstadoSolicitud(int $id_proyectos, int $id_usuario, string $rol): array
    {
        global $conn;
        try {
            $this->soloEstudiante($rol);
            return (new Solicitud($conn))->obtenerSolicitudEstudiante($id_proyectos, $id_usuario) ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function descargarPlantilla(int $id_plantilla): void
    {
        if (empty($id_plantilla)) {
            http_response_code(400);
            exit('Plantilla inválida.');
        }

        global $conn;
        $file = (new Solicitud($conn))->obtenerPlantillaPorId($id_plantilla);

        if (!$file || !$file['plantilla_activa'] || !$file['archivo_activo']) {
            http_response_code(404);
            exit('Plantilla no disponible.');
        }

        $storageBase = realpath(__DIR__ . '/../../../../storage');
        if (!$storageBase) {
            http_response_code(500);
            exit('Storage no disponible.');
        }

        $rutaRelativa = ltrim($file['ruta'], '/');
        $rutaRelativa = preg_replace('#^#', '', $rutaRelativa);
        $rutaCompleta = realpath(__DIR__ . '/../../../../' . $rutaRelativa);

        if (!$rutaCompleta || !file_exists($rutaCompleta)) {
            http_response_code(404);
            exit('Archivo inexistente.');
        }

        if (strpos($rutaCompleta, $storageBase) !== 0) {
            http_response_code(403);
            exit('Ruta inválida.');
        }

        if (ob_get_length()) ob_end_clean();

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

    //  enviarSolicitud 

    public function enviarSolicitud(int $id_proyectos, int $id_usuario, string $rol): void
    {
        global $conn;
        try {
            $this->validarMetodo('POST');
            $this->soloEstudiante($rol);

            $S       = new Solicitud($conn);

            $S->marcarSolicitudesProyectosVencidos();

            $periodo = $S->obtenerPeriodoActivoParaProyecto($id_proyectos);

            if (!$periodo) {
                $this->redirigir(
                    'error_ventana_cerrada',
                    "/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos={$id_proyectos}"
                );
            }
            $id_periodo = (int)$periodo['id_periodos'];

            $promedio    = isset($_POST['promedio'])  && $_POST['promedio']  !== '' ? (float)$_POST['promedio']  : null;
            $motivacion  = trim($_POST['motivacion']  ?? '');
            $experiencia = trim($_POST['experiencia'] ?? '');
            $semestre    = isset($_POST['semestre'])  && $_POST['semestre']  !== '' ? (int)$_POST['semestre']   : null;

            if ($motivacion === '' || $experiencia === '') {
                $this->redirigir(
                    'error_datos',
                    "/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos={$id_proyectos}"
                );
            }

            if (empty($_FILES['carta_compromiso']) || $_FILES['carta_compromiso']['error'] !== UPLOAD_ERR_OK) {
                $this->redirigir(
                    'error_carta_requerida',
                    "/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos={$id_proyectos}"
                );
            }

            $plantilla    = $S->obtenerPlantillaCartaCompromiso();
            $id_plantilla = $plantilla ? (int)$plantilla['id_plantilla'] : 0;

            $conn->begin_transaction();

            $id_solicitud = $S->crearSolicitud(
                id_proyectos: $id_proyectos,
                id_estudiante: $id_usuario,
                id_periodo: $id_periodo,
                promedio: $promedio,
                motivacion: $motivacion,
                experiencia: $experiencia,
                semestre: $semestre
            );

            $id_doc_cv = $this->procesarCvEstudiante($id_solicitud, $id_usuario);
            if ($id_doc_cv) {
                $S->actualizarDocumentoSolicitud($id_solicitud, $id_doc_cv);
            }


            $id_seguimiento = $S->crearSeguimientoCartaCompromiso(
                id_proyectos: $id_proyectos,
                id_estudiante: $id_usuario,
                estado: 'proceso'
            );



            $id_doc_carta = $this->procesarCartaCompromiso(
                campo_file: 'carta_compromiso',
                id_proyectos: $id_proyectos,
                id_estudiante: $id_usuario,
                id_seguimiento: $id_seguimiento,
                id_plantilla: $id_plantilla
            );



            if (!$id_doc_carta) {
                throw new Exception("No se pudo guardar la carta compromiso.");
            }


            $S->marcarSeguimientoEnProceso($id_seguimiento);

            $titulo_proyecto = $S->obtenerTituloProyecto($id_proyectos);
            $enlace          = "/Vistas/Proyectos/detalles.php?id_proyectos={$id_proyectos}";



            $S->insertarNotificacion(
                id_usuario: $id_usuario,
                titulo: 'Solicitud enviada',
                contenido: "Has enviado una solicitud para el proyecto: <b>" . htmlspecialchars($titulo_proyecto) . "</b>. En espera de revisión.",
                enlace: "/Modules/Proyectos/Views/detalles.php?id_proyectos={$id_proyectos}"
            );


            foreach ($S->obtenerSupervisoresActivos() as $sup) {
                $S->insertarNotificacion(
                    id_usuario: (int)$sup['id_usuarios'],
                    titulo: 'Nueva solicitud de integración',
                    contenido: "Un estudiante ha enviado una solicitud para el proyecto: <b>" . htmlspecialchars($titulo_proyecto) . "</b>.",
                    enlace: "/Modules/Solicitudes_integracion_proyecto/Views/detalles_solicitud.php?id={$id_solicitud}"
                );
            }

            $conn->commit();

            $this->redirigir(
                'exito_solicitud_enviada',
                "/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos={$id_proyectos}"
            );
        } catch (Exception $e) {
            if (isset($conn) && $conn->errno === 0) $conn->rollback();
            error_log("[enviarSolicitud] " . $e->getMessage());
            $this->redirigir(
                'error_solicitud',
                "/Modules/Solicitudes_integracion_proyecto/Views/solicitud_integracion.php?id_proyectos={$id_proyectos}"
            );
        }
    }
}
