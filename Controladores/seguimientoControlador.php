<?php

/**
 * SeguimientoControlador.php
 *
 * Rutas via ?action= :
 *   GET  index              → Estudiante: timeline de sus 3 etapas
 *   POST responderCierre    → Investigador: aprueba/rechaza/corrige Etapa 3 (AJAX JSON)
 *   POST actualizarEstado   → Investigador: actualiza estado genérico (AJAX JSON)
 *   POST subirDocumento     → Estudiante: sube archivo de una etapa (AJAX JSON)
 *
 * Método público adicional:
 *   getDatosSeguimientoEstudiante() → usado en detalles_solicitud.php
 */

require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/../Modelos/seguimiento.php';

class SeguimientoControlador
{
    private SeguimientoModelo $modelo;
    private mysqli $conn;

    public function __construct()
    {
        global $conn;
        $this->conn   = $conn;
        $this->modelo = new SeguimientoModelo($conn);
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    private function esEstudiante(string $rol): bool
    {
        return $rol === 'estudiante';
    }

    private function esInvestigador(string $rol): bool
    {
        return in_array($rol, ['investigador', 'profesor'], true);
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

    // ──────────────────────────────────────────────────────────────
    // INDEX — ESTUDIANTE
    // ──────────────────────────────────────────────────────────────

    public function index(int $id_usuario, string $rol, int $id_proyecto): array
    {
        if (!$this->esEstudiante($rol)) {
            return [
                'etapas'  => [], 'proyecto' => null,
                'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
                'mensaje'  => 'Acceso no permitido.',
            ];
        }

        if (!$id_proyecto) {
            return [
                'etapas'  => [], 'proyecto' => null,
                'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
                'mensaje'  => 'Proyecto no especificado.',
            ];
        }

        $proyecto = $this->modelo->getProyectoPorId($id_usuario, $id_proyecto);

        if (!$proyecto) {
            return [
                'etapas'  => [], 'proyecto' => null,
                'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
                'mensaje'  => 'No tienes acceso a este proyecto.',
            ];
        }

        $etapas      = $this->modelo->getEtapasPorProyecto($id_proyecto, $id_usuario);
        $completadas = array_filter($etapas, fn($e) => $e['estado'] === 'completado');
        $total       = count($etapas);

        return [
            'etapas'   => $etapas,
            'proyecto' => $proyecto,
            'progreso' => [
                'completadas' => count($completadas),
                'total'       => $total,
                'pct'         => $total > 0 ? round((count($completadas) / $total) * 100) : 0,
            ],
            'mensaje'  => null,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // getDatosSeguimientoEstudiante
    // Usado por detalles_solicitud.php
    // ──────────────────────────────────────────────────────────────

    public function getDatosSeguimientoEstudiante(
        int $id_proyecto,
        int $id_estudiante,
        int $id_investigador
    ): array {
        // Etapa 1: estado de la solicitud de integración
        $solicitud = $this->modelo->getSolicitudPorEstudianteProyecto($id_estudiante, $id_proyecto);
        $e1_estado = $solicitud ? $solicitud['estado'] : 'pendiente';

        // Etapa 2: desarrollo automático por tareas aprobadas
        $tareasOk  = $this->modelo->contarTareasAprobadas($id_proyecto, $id_estudiante);
        $fase2_ok  = $tareasOk >= 11;
        $e2_estado = $fase2_ok ? 'completado' : ($tareasOk > 0 ? 'proceso' : 'pendiente');

        // Etapa 3: cierre (seguimiento_documento del Reporte Final)
        $segCierre = $this->modelo->getSegimientoCierre($id_proyecto, $id_estudiante);
        $e3_estado = $segCierre ? $segCierre['estado'] : 'pendiente';
        $id_seg_c  = $segCierre ? $segCierre['id_seguimiento'] : null;

        // Documentos subidos por el estudiante en etapas
        $documentos = $this->modelo->getDocumentosEtapaEstudiante($id_proyecto, $id_estudiante);

        return [
            'e1_estado'             => $e1_estado,
            'e2_estado'             => $e2_estado,
            'e3_estado'             => $e3_estado,
            'fase2_ok'              => $fase2_ok,
            'id_seguimiento_cierre' => $id_seg_c,
            'documentos'            => $documentos,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // RESPONDER CIERRE — Etapa 3 (AJAX POST)
    // Acepta: completado | correcciones | rechazado
    // ──────────────────────────────────────────────────────────────

    public function responderCierre(): void
    {
        $id_usuario = $this->idUsuario();
        $rol        = $this->rol();

        if (!$this->esInvestigador($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_seguimiento = intval($_POST['id_seguimiento'] ?? 0);
        $estado         = trim($_POST['estado'] ?? '');
        $comentario     = trim($_POST['comentario'] ?? '');

        if (!$id_seguimiento || !in_array($estado, ['completado', 'correcciones', 'rechazado'], true)) {
            $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
        }

        if (!$this->modelo->verificarPermisoInvestigador($id_seguimiento, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No tienes permiso sobre este seguimiento.'], 403);
        }

        $ok = $this->modelo->actualizarEstadoSeguimiento(
            $id_seguimiento, $estado, $comentario, $id_usuario
        );

        // Cierre aprobado → marcar concluido en proyectos_usuarios + historial
        if ($ok && $estado === 'completado') {
            $seg = $this->modelo->getSegimientoPorId($id_seguimiento);
            if ($seg) {
                $this->modelo->marcarProyectoUsuarioConcluido(
                    (int)$seg['id_proyectos'],
                    (int)$seg['id_usuarios'],
                    $id_usuario
                );
            }
        }

        $msgs = [
            'completado'  => 'Cierre aprobado. El estudiante ha concluido el proyecto.',
            'correcciones'=> 'Correcciones solicitadas al estudiante.',
            'rechazado'   => 'Cierre rechazado.',
        ];

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? ($msgs[$estado] ?? 'Actualizado.') : 'Error al actualizar.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // ACTUALIZAR ESTADO GENÉRICO (AJAX POST)
    // ──────────────────────────────────────────────────────────────

    public function actualizarEstado(): void
    {
        $id_usuario = $this->idUsuario();
        $rol        = $this->rol();

        if (!$this->esInvestigador($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_seguimiento = intval($_POST['id_seguimiento'] ?? 0);
        $estado         = trim($_POST['estado'] ?? '');
        $comentario     = trim($_POST['comentario'] ?? '');

        if (!$id_seguimiento || !in_array($estado, ['completado', 'rechazado', 'correcciones'], true)) {
            $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
        }

        if (!$this->modelo->verificarPermisoInvestigador($id_seguimiento, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No tienes permiso.'], 403);
        }

        $ok = $this->modelo->actualizarEstadoSeguimiento(
            $id_seguimiento, $estado, $comentario, $id_usuario
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Estado actualizado correctamente.' : 'Error al actualizar.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // SUBIR DOCUMENTO — Estudiante (AJAX POST)
    // Ruta: /storage/etapas/proyecto_{id}/
    // ──────────────────────────────────────────────────────────────

    public function subirDocumento(): void
    {
        $id_usuario = $this->idUsuario();
        $rol        = $this->rol();

        if (!$this->esEstudiante($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_proyecto       = intval($_POST['id_proyecto'] ?? 0);
        $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
        $id_plantilla      = intval($_POST['id_plantilla'] ?? 0) ?: null;
        $id_seg_previo     = intval($_POST['id_seguimiento'] ?? 0);

        if (!$id_proyecto || !$id_tipo_documento) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'msg' => 'Archivo inválido o no recibido.'], 422);
        }

        $archivo = $_FILES['documento'];

        // Validar MIME real (no confiar en el tipo enviado por el cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        $mimesValidos = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        if (!isset($mimesValidos[$mime])) {
            $this->json(['ok' => false, 'msg' => 'Solo se aceptan archivos PDF o DOCX.'], 422);
        }

        if ($archivo['size'] > 10 * 1024 * 1024) {
            $this->json(['ok' => false, 'msg' => 'El archivo supera el máximo de 10 MB.'], 422);
        }

        if (!$this->modelo->verificarProyectoUsuario($id_proyecto, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No estás autorizado para este proyecto.'], 403);
        }

        $ext   = $mimesValidos[$mime];
        $etapa = $this->modelo->getOrdenTipoDocumento($id_tipo_documento);

        // Ruta física: /storage/etapas/proyecto_{id}/
        $dirRelativo = "storage/etapas/proyecto_{$id_proyecto}";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/' . $dirRelativo . '/';

        if (!is_dir($dirFisico)) {
            mkdir($dirFisico, 0755, true);
        }

        $nombreArchivo = "est{$id_usuario}_td{$id_tipo_documento}_" . date('YmdHis') . ".{$ext}";
        $nombreDisplay = basename($archivo['name']);
        $rutaFisica    = $dirFisico . $nombreArchivo;
        $rutaBD        = $dirRelativo . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
            $this->json(['ok' => false, 'msg' => 'Error al guardar el archivo en disco.'], 500);
        }

        // Crear o actualizar seguimiento_documento
        if (!$id_seg_previo) {
            $id_seguimiento = $this->modelo->crearSeguimiento(
                $id_proyecto, $id_tipo_documento, $id_usuario
            );
        } else {
            $id_seguimiento = $id_seg_previo;
            $this->modelo->actualizarEstadoEstudiante($id_seguimiento, 'proceso');
        }

        // Registrar en documentos_subidos (tabla centralizada)
        $ok = $this->modelo->registrarDocumentoCentralizado(
            $id_seguimiento,
            $id_plantilla,
            $nombreDisplay,
            $nombreArchivo,
            $rutaBD,
            $mime,
            $ext,
            $archivo['size'],
            $id_usuario,
            $id_proyecto,
            $etapa
        );

        $this->json([
            'ok'            => $ok,
            'id_seguimiento'=> $id_seguimiento,
            'msg'           => $ok ? 'Documento subido correctamente.' : 'Error al registrar el documento.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ──────────────────────────────────────────────────────────────

    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        return $this->modelo->contarTareasAprobadas($id_proyecto, $id_estudiante) >= 11;
    }

    public function badgeEstado(string $estado): string
    {
        $map = [
            'pendiente'    => ['secondary',        'Pendiente'],
            'en_revision'  => ['info text-dark',   'En revisión'],
            'correcciones' => ['warning text-dark', 'Correcciones'],
            'aceptado'     => ['success',           'Aceptado'],
            'rechazado'    => ['danger',            'Rechazado'],
            'proceso'      => ['primary',           'En proceso'],
            'completado'   => ['success',           'Completado'],
        ];
        [$color, $texto] = $map[$estado] ?? ['secondary', $estado];
        return "<span class='badge bg-{$color}'>{$texto}</span>";
    }

    // Stubs requeridos por otras vistas
    public function filtros(int $id_usuario, string $rol): array     { return []; }
    public function encabezados(string $rol): array                   { return []; }
    public function datosopciones(string $rol, array $filtros): array { return []; }
}
