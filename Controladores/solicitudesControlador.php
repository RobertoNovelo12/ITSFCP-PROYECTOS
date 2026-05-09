<?php
require_once __DIR__ . '/../Modelos/solicitudes.php';
require_once __DIR__ . '/../publico/config/conexion.php';
//CONTROLADOR DE SOLICITUDES DE INTEGRACIÓN
class solicitudesControlador
{
    private function soloInvestigador(string $rol): void
    {
        if (!in_array($rol, ['investigador', 'profesor'], true)) {
            http_response_code(403);
            die('Acceso denegado.');
        }
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function idUsuario(): int
    {
        return intval($_SESSION['id_usuario'] ?? 0);
    }

    private function rol(): string
    {
        return strtolower($_SESSION['rol'] ?? '');
    }

    //Solicitudes de integración
    private function procesarArchivo(int $id_solicitud): ?int
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

        if (!is_dir(dirname($rutaFisica))) mkdir(dirname($rutaFisica), 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
            throw new Exception("Error al guardar archivo en disco.");
        }

        global $conn;
        $modelo = new Solicitud($conn);
        return $modelo->registrarDocumento(
            nombre: basename($file['name']),
            nombre_archivo: $nombreFinal,
            ruta: $rutaBD,
            tipo_mime: $file['type'],
            extension: $ext,
            tamano_bytes: $file['size'],
            tipo: 'recurso',
            visibilidad: 'privado',
            id_usuario: $this->idUsuario()
        );
    }

    // ── Vista helpers ─────────────────────────────────────────────

    public function encabezados(): array
    {
        return ['#', 'Estudiante', 'Matrícula', 'Carrera', 'Proyecto', 'Semestre', 'Promedio', 'Fecha', 'Estado', 'Acciones'];
    }

    public function botonesAccion(int $id_solicitud, string $estado, int $id_proyecto): string
    {
        $estado = strtolower(trim($estado));
        $btn    = $this->boton('detalle', $id_solicitud);

        if (in_array($estado, ['pendiente', 'en_revision', 'correcciones'], true)) {
            $btn .= ' ' . $this->boton('aceptar',      $id_solicitud);
            $btn .= ' ' . $this->boton('correcciones', $id_solicitud);
            $btn .= ' ' . $this->boton('rechazar',     $id_solicitud);
        }
        return $btn;
    }

    private function boton(string $tipo, int $id_solicitud): string
    {
        return match ($tipo) {
            'detalle'      => "<a href='detalles_solicitud.php?id={$id_solicitud}' class='btn btn-primary btn-sm' title='Ver solicitud'><i class='bi bi-file-text-fill'></i></a>",
            'aceptar'      => "<button type='button' class='btn btn-success btn-sm' title='Aceptar solicitud' onclick='confirmarAceptar({$id_solicitud})'><i class='bi bi-check-circle-fill'></i></button>",
            'correcciones' => "<button type='button' class='btn btn-warning btn-sm' title='Pedir correcciones' onclick='abrirModalAccion({$id_solicitud},\"correcciones\")'><i class='bi bi-pencil-fill'></i></button>",
            'rechazar'     => "<button type='button' class='btn btn-danger btn-sm' title='Rechazar solicitud' onclick='abrirModalAccion({$id_solicitud},\"rechazar\")'><i class='bi bi-ban'></i></button>",
            default        => '',
        };
    }

    public function badgeEstado(string $estado): string
    {
        return match (strtolower($estado)) {
            'pendiente'    => "<span class='badge bg-secondary'>Pendiente</span>",
            'en_revision'  => "<span class='badge bg-info text-dark'>En revisión</span>",
            'correcciones' => "<span class='badge bg-warning text-dark'>Correcciones</span>",
            'aceptado'     => "<span class='badge bg-success'>Aceptado</span>",
            'rechazado'    => "<span class='badge bg-danger'>Rechazado</span>",
            'vencido'    => "<span class='badge bg-dark'>Vencido</span>",
            default        => "<span class='badge bg-light text-dark'>" . htmlspecialchars($estado) . "</span>",
        };
    }

    // ── index ─────────────────────────────────────────────────────

    public function index(int $id_usuario, string $rol): array
    {
        $this->soloInvestigador($rol);
        global $conn;

        $S          = new Solicitud($conn);
        $this->vencido();
        $por_pagina = 8;
        $pagina     = max(1, intval($_GET['pagina'] ?? 1));
        $desde      = ($pagina - 1) * $por_pagina;

        $filtros = [
            'periodo'     => $_GET['periodo']     ?? '',   // ← filtro global nuevo
            'estado'      => $_GET['estado']      ?? '',
            'buscar'      => $_GET['buscar']      ?? '',
            'proyecto'    => $_GET['proyecto']    ?? '',
            'semestre'    => $_GET['semestre']    ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
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

    // ── aceptar ──────────────────────────────────────────────────

    public function aceptar(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $S  = new Solicitud($conn);
        $id = intval($_POST['id_solicitud'] ?? 0);
        if (!$id) $this->json(['ok' => false, 'msg' => 'ID inválido.'], 422);

        if (!$S->verificarPermiso($id, $this->idUsuario())) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $conn->begin_transaction();
        try {
            $datos = $S->obtenerDatosSolicitud($id);
            if (!$datos) throw new Exception("Solicitud no encontrada.");

            $S->aceptar($id);
            $S->vincularEstudianteProyecto($datos['id_proyectos'], $datos['id_usuarios']);
            $S->vincularTareasAlNuevoEstudiante($datos['id_proyectos'], $datos['id_usuarios']);
            $S->registrarHistorialUsuario(
                $datos['id_proyectos'],
                $datos['id_usuarios'],
                'reactivado',
                'Solicitud de integración aceptada por el investigador',
                $this->idUsuario()
            );

            $conn->commit();
            $this->json(['ok' => true, 'msg' => 'Solicitud aceptada. Estudiante integrado al proyecto.']);
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $this->json(['ok' => false, 'msg' => 'Error en el proceso.']);
        }
    }

    // ── pedir correcciones ────────────────────────────────────────

    public function pedirCorrecciones(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $S          = new Solicitud($conn);
        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id || $comentario === '') $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        if (!$S->verificarPermiso($id, $this->idUsuario())) $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);

        try {
            $id_doc = $this->procesarArchivo($id);
            $ok     = $S->pedirCorrecciones($id, $this->idUsuario(), $comentario, $id_doc);
            $this->json(['ok' => $ok, 'msg' => $ok ? 'Correcciones solicitadas.' : 'Error.']);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->json(['ok' => false, 'msg' => 'Error interno.'], 500);
        }
    }

    // ── rechazar ─────────────────────────────────────────────────

    public function rechazar(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $S          = new Solicitud($conn);
        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id || $comentario === '') $this->json(['ok' => false, 'msg' => 'Motivo obligatorio.'], 422);
        if (!$S->verificarPermiso($id, $this->idUsuario())) $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);

        try {
            $id_doc = $this->procesarArchivo($id);
            $ok     = $S->rechazar($id, $this->idUsuario(), $comentario, $id_doc);
            $this->json(['ok' => $ok, 'msg' => $ok ? 'Rechazada.' : 'Error.']);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->json(['ok' => false, 'msg' => 'Error interno.'], 500);
        }
    }

    // ── vencido ─────────────────────────────────────────────────
    // Función que realiza lo siguiente:
    // 1- Obtiene todos los ids cuyos proyectos hayan vencido fecha_Actual > fecha_fin
    // 2- Se ejecuta la función vencido con la id de la solicitud
    // 3- Se guarda en un historial la información.
    public function vencido(): void
    {
        $this->soloInvestigador($this->rol());
        global $conn;

        $S  = new Solicitud($conn);
        $conn->begin_transaction();
        try {
            $id_vencidos = $S->obtenervencido();
            foreach ($id_vencidos as $id_vencido) {
                $datos = $S->obtenerDatosSolicitud($id_vencido);
                if (!$datos) throw new Exception("Solicitud no encontrada.");

                $S->vencido($id_vencido);
                $S->registrarHistorialUsuario(
                    $datos['id_proyectos'],
                    $datos['id_usuarios'],
                    'vencido',
                    'Solicitud de integración vencida al no responer el investigador',
                    $this->idUsuario()
                );
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
        }
    }

    // ── enviar correcciones (estudiante) ──────────────────────────

    public function enviarCorrecciones(): void
    {
        if ($this->rol() !== 'estudiante') $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);

        global $conn;
        $S          = new Solicitud($conn);
        $id         = intval($_POST['id_solicitud'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id) $this->json(['ok' => false, 'msg' => 'ID inválido.'], 422);

        try {
            $id_doc = $this->procesarArchivo($id);
            $ok     = $S->enviarCorrecciones($id, $this->idUsuario(), $comentario, $id_doc);
            $this->json(['ok' => $ok, 'msg' => $ok ? 'Enviado.' : 'Error.']);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $this->json(['ok' => false, 'msg' => 'Error interno.'], 500);
        }
    }

    // ── detallePagina (detalles_solicitud.php) ────────────────────

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

    /**
     * Datos de seguimiento del estudiante para detalles_solicitud.php.
     * Delega al modelo.
     */
    public function getDatosSeguimientoEstudiante(int $id_proyecto, int $id_estudiante, int $id_usuario): array
    {
        global $conn;
        $S = new Solicitud($conn);
        return $S->getDatosSeguimientoEstudiante($id_proyecto, $id_estudiante, $id_usuario);
    }
}
