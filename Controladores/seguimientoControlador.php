<?php

/**
 * Controladores/seguimientoControlador.php
 *
 * Rutas vía ?action= :
 *   GET  index              → Estudiante: timeline de sus 3 etapas
 *   POST subirDocumento     → Estudiante: sube Carta Compromiso (Etapa 1)
 *   POST subirCartaTerminacion → Estudiante: sube Carta de Terminación firmada (Etapa 3)
 *   POST actualizarEstado   → Investigador: aprueba/rechaza/corrige seguimiento_documento (AJAX JSON)
 *
 * Lógica de negocio por etapa:
 *   Etapa 1 — Carta Compromiso
 *     • El estudiante descarga la plantilla, la firma y la sube.
 *     • Se crea/actualiza seguimiento_documento (tipo_documento=1, Carta Compromiso).
 *     • El investigador aprueba/rechaza desde detalles_solicitud / panel investigador.
 *
 *   Etapa 2 — Desarrollo del documento
 *     • Estado calculado automáticamente: tareas aprobadas / tareas totales.
 *     • No requiere acción del estudiante en esta vista.
 *
 *   Etapa 3 — Carta de Terminación
 *     • Solo disponible si Etapa 2 está completa (todas las tareas aprobadas).
 *     • El estudiante descarga la plantilla de carta de terminación,
 *       la firma externamente y la sube aquí → crea registro en cierres_estudiante.
 *     • Si fue rechazada, puede reenviarla desde esta misma vista.
 *     • El supervisor revisa en su propio módulo (cartas_terminacion/).
 *
 * Métodos públicos auxiliares:
 *   getDatosSeguimientoEstudiante() → usado en detalles_solicitud.php (investigador)
 *   todasSeccionesAprobadas()       → helper para vistas externas
 *   badgeEstado()                   → genera badge HTML
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
    // INDEX — ESTUDIANTE (GET)
    // ──────────────────────────────────────────────────────────────

    /**
     * Carga los datos para la vista de seguimiento del estudiante.
     *
     * Bloqueos de etapas:
     *   Etapa 1 → siempre accesible (el estudiante ya fue aceptado si llegó aquí).
     *   Etapa 2 → visible si Etapa 1 está en estado 'completado'.
     *   Etapa 3 → visible si Etapa 2 está completada (todas las tareas aprobadas).
     */
    public function index(int $id_usuario, string $rol, int $id_proyecto): array
    {
        $vacio = [
            'etapas'  => [],
            'proyecto' => null,
            'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
            'mensaje'  => null,
        ];

        if (!$this->esEstudiante($rol)) {
            return array_merge($vacio, ['mensaje' => 'Acceso no permitido.']);
        }

        if (!$id_proyecto) {
            return array_merge($vacio, ['mensaje' => 'Proyecto no especificado.']);
        }

        $proyecto = $this->modelo->getProyectoPorId($id_usuario, $id_proyecto);

        if (!$proyecto) {
            return array_merge($vacio, ['mensaje' => 'No tienes acceso a este proyecto.']);
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
    // SUBIR DOCUMENTO — Etapa 1: Carta Compromiso (POST)
    // Ruta física: /storage/etapas/proyecto_{id}/
    // ──────────────────────────────────────────────────────────────

    public function subirDocumento(): void
    {
        $id_usuario        = $this->idUsuario();
        $rol               = $this->rol();

        if (!$this->esEstudiante($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_proyecto       = intval($_POST['id_proyecto']       ?? 0);
        $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
        $id_plantilla      = intval($_POST['id_plantilla']      ?? 0) ?: null;
        $id_seg_previo     = intval($_POST['id_seguimiento']    ?? 0);

        if (!$id_proyecto || !$id_tipo_documento) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        }

        // Validar que el estudiante pertenece al proyecto
        if (!$this->modelo->verificarProyectoUsuario($id_proyecto, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No estás autorizado para este proyecto.'], 403);
        }

        // Validar archivo
        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'msg' => 'Archivo inválido o no recibido.'], 422);
        }

        [$archivo, $mime, $ext] = $this->validarArchivo($_FILES['documento']);

        // Ruta: /storage/etapas/proyecto_{id}/
        $dirRelativo = "storage/etapas/proyecto_{$id_proyecto}";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/' . $dirRelativo . '/';

        if (!is_dir($dirFisico)) mkdir($dirFisico, 0755, true);

        $nombreArchivo  = "est{$id_usuario}_td{$id_tipo_documento}_" . date('YmdHis') . ".{$ext}";
        $nombreDisplay  = basename($archivo['name']);
        $rutaBD         = $dirRelativo . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $dirFisico . $nombreArchivo)) {
            $this->json(['ok' => false, 'msg' => 'Error al guardar el archivo en disco.'], 500);
        }

        // Crear o actualizar seguimiento_documento
        if (!$id_seg_previo) {
            $id_seguimiento = $this->modelo->crearSeguimiento($id_proyecto, $id_tipo_documento, $id_usuario);
        } else {
            $id_seguimiento = $id_seg_previo;
            $this->modelo->actualizarEstadoEstudiante($id_seguimiento, 'proceso');
        }

        // Obtener id_etapa correcto (FK a etapas_documento)
        $id_etapa = $this->modelo->getIdEtapaPorTipoDocumento($id_tipo_documento);

        // Registrar en documentos_subidos
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
            $id_etapa
        );

        $this->json([
            'ok'             => $ok,
            'id_seguimiento' => $id_seguimiento,
            'msg'            => $ok
                ? 'Carta Compromiso subida correctamente. El investigador la revisará pronto.'
                : 'Error al registrar el documento.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // SUBIR CARTA DE TERMINACIÓN — Etapa 3 (POST)
    // Ruta física: /storage/etapas/proyecto_{id}/
    // Crea registro en cierres_estudiante (tabla nueva).
    // ──────────────────────────────────────────────────────────────

    public function subirCartaTerminacion(): void
    {
        $id_usuario  = $this->idUsuario();
        $rol         = $this->rol();

        if (!$this->esEstudiante($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_proyecto = intval($_POST['id_proyecto'] ?? 0);

        if (!$id_proyecto) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        }

        if (!$this->modelo->verificarProyectoUsuario($id_proyecto, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No estás autorizado para este proyecto.'], 403);
        }

        // Verificar que Etapa 2 esté completada
        if (!$this->modelo->todasSeccionesAprobadas($id_proyecto, $id_usuario)) {
            $this->json([
                'ok'  => false,
                'msg' => 'Debes completar todas tus actividades antes de enviar la carta de terminación.',
            ], 422);
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'msg' => 'Archivo inválido o no recibido.'], 422);
        }

        [$archivo, $mime, $ext] = $this->validarArchivo($_FILES['documento']);

        // Ruta: /storage/etapas/proyecto_{id}/
        $dirRelativo = "storage/etapas/proyecto_{$id_proyecto}";
        $dirFisico   = $_SERVER['DOCUMENT_ROOT'] . '/ITSFCP-PROYECTOS/' . $dirRelativo . '/';

        if (!is_dir($dirFisico)) mkdir($dirFisico, 0755, true);

        $nombreArchivo = "carta_est{$id_usuario}_" . date('YmdHis') . ".{$ext}";
        $nombreDisplay = basename($archivo['name']);
        $rutaBD        = $dirRelativo . '/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $dirFisico . $nombreArchivo)) {
            $this->json(['ok' => false, 'msg' => 'Error al guardar el archivo en disco.'], 500);
        }

        // id_etapa = 3 (Cierre del proyecto en etapas_documento)
        $id_etapa = 3;

        // Obtener datos necesarios
        $id_integrante = $this->modelo->getIdIntegrante($id_proyecto, $id_usuario);
        if (!$id_integrante) {
            $this->json(['ok' => false, 'msg' => 'No se encontró tu relación con el proyecto.'], 500);
        }

        $id_supervisor = $this->modelo->getIdSupervisorDelProyecto($id_proyecto);
        if (!$id_supervisor) {
            $this->json(['ok' => false, 'msg' => 'No hay supervisor asignado al proyecto. Contacta al administrador.'], 500);
        }

        // Verificar si ya existe un cierre previo (reenvío por correcciones)
        $cierre_previo = $this->modelo->getCierreEstudiante($id_proyecto, $id_usuario);

        // Registrar documento en documentos_subidos
        $id_documento = $this->modelo->registrarDocumentoCarta(
            $nombreDisplay,
            $nombreArchivo,
            $rutaBD,
            $mime,
            $ext,
            $archivo['size'],
            $id_usuario,
            $id_proyecto,
            $id_etapa
        );

        if (!$id_documento) {
            $this->json(['ok' => false, 'msg' => 'Error al registrar el documento.'], 500);
        }

        if (!$cierre_previo) {
            // Primera subida → crear registro en cierres_estudiante
            $id_cierre = $this->modelo->crearCierreEstudiante($id_integrante, $id_documento, $id_supervisor);
            $ok        = (bool)$id_cierre;
        } else {
            // Reenvío → desactivar documento anterior y actualizar registro
            $this->modelo->desactivarDocumentoCarta((int)$cierre_previo['id_documento']);
            $ok = $this->modelo->reenviarCierreEstudiante((int)$cierre_previo['id_cierre_est'], $id_documento);
        }

        if ($ok) {
            // Actualizar estado_proceso en proyectos_usuarios a 'carta_subida'
            $this->modelo->actualizarEstadoProcesoCarta($id_integrante);

            // Notificar al supervisor
            $this->modelo->notificar(
                $id_supervisor,
                'Nueva carta de terminación',
                'Un estudiante ha enviado su carta de terminación firmada y está esperando revisión.',
                '/ITSFCP-PROYECTOS/Vistas/cartas_terminacion/index.php'
            );
        }

        $this->json([
            'ok'  => $ok,
            'msg' => $ok
                ? 'Carta de terminación enviada correctamente. El supervisor la revisará pronto.'
                : 'Error al registrar el cierre.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // ACTUALIZAR ESTADO — Investigador (AJAX POST)
    // Para seguimiento_documento de Etapas 1 y (si aplica) Reporte Final
    // ──────────────────────────────────────────────────────────────

    public function actualizarEstado(): void
    {
        $id_usuario = $this->idUsuario();
        $rol        = $this->rol();

        if (!$this->esInvestigador($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_seguimiento = intval($_POST['id_seguimiento'] ?? 0);
        $estado         = trim($_POST['estado']           ?? '');
        $comentario     = trim($_POST['comentario']       ?? '');

        if (!$id_seguimiento || !in_array($estado, ['completado', 'rechazado', 'correcciones'], true)) {
            $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
        }

        if (!$this->modelo->verificarPermisoInvestigador($id_seguimiento, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No tienes permiso sobre este seguimiento.'], 403);
        }

        $ok = $this->modelo->actualizarEstadoSeguimiento($id_seguimiento, $estado, $comentario, $id_usuario);

        // Si el investigador aprueba el Reporte Final → notificar al estudiante
        // para que proceda a subir la carta de terminación
        if ($ok && $estado === 'completado') {
            $seg = $this->modelo->getSegimientoPorId($id_seguimiento);
            if ($seg) {
                $this->modelo->notificar(
                    (int)$seg['id_usuarios'],
                    'Reporte Final aprobado',
                    'El investigador aprobó tu Reporte Final. Ya puedes descargar y subir tu Carta de Terminación.',
                    '/ITSFCP-PROYECTOS/Vistas/Seguimiento/seguimiento.php?id_proyectos=' . $seg['id_proyectos']
                );
            }
        }

        $msgs = [
            'completado'   => 'Documento aprobado correctamente.',
            'correcciones' => 'Correcciones solicitadas al estudiante.',
            'rechazado'    => 'Documento rechazado.',
        ];

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? ($msgs[$estado] ?? 'Actualizado.') : 'Error al actualizar.',
        ]);
    }

    //Contar tareas totales
    public function contarTareasTotales($id_proyecto, $id_estudiante)
    {
        $total     = $this->modelo->contarTareasTotales($id_proyecto, $id_estudiante);
        return $total;
    }

    // ──────────────────────────────────────────────────────────────
    // getDatosSeguimientoEstudiante
    // Usado por detalles_solicitud.php del investigador
    // ──────────────────────────────────────────────────────────────

    public function getDatosSeguimientoEstudiante(
        int $id_proyecto,
        int $id_estudiante,
        int $id_investigador
    ): array {
        // Etapa 1: Carta Compromiso → seguimiento_documento
        $solicitud = $this->modelo->getSolicitudPorEstudianteProyecto($id_estudiante, $id_proyecto);
        $e1_estado = $solicitud ? $solicitud['estado'] : 'pendiente';

        // Etapa 2: tareas
        $total     = $this->modelo->contarTareasTotales($id_proyecto, $id_estudiante);
        $aprobadas = $this->modelo->contarTareasAprobadas($id_proyecto, $id_estudiante);
        $fase2_ok  = $total > 0 && $aprobadas >= $total;

        if ($total === 0)           $e2_estado = 'pendiente';
        elseif ($fase2_ok)          $e2_estado = 'completado';
        elseif ($aprobadas > 0)     $e2_estado = 'proceso';
        else                        $e2_estado = 'pendiente';

        // Etapa 3: cierres_estudiante
        $cierre = $this->modelo->getCierreEstudiante($id_proyecto, $id_estudiante);
        $e3_estado = !$cierre ? 'pendiente' : match ($cierre['estado']) {
            'pendiente' => 'proceso',
            'aprobado'  => 'completado',
            'rechazado' => 'rechazado',
            default     => 'pendiente',
        };

        $documentos = $this->modelo->getDocumentosEtapaEstudiante($id_proyecto, $id_estudiante);

        return [
            'e1_estado'      => $e1_estado,
            'e2_estado'      => $e2_estado,
            'e3_estado'      => $e3_estado,
            'fase2_ok'       => $fase2_ok,
            'cierre'         => $cierre,
            'documentos'     => $documentos,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ──────────────────────────────────────────────────────────────

    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        return $this->modelo->todasSeccionesAprobadas($id_proyecto, $id_estudiante);
    }

    public function badgeEstado(string $estado): string
    {
        $map = [
            'pendiente'    => ['secondary',         'Pendiente'],
            'proceso'      => ['primary',           'En proceso'],
            'completado'   => ['success',           'Completado'],
            'rechazado'    => ['danger',            'Rechazado'],
            'correcciones' => ['warning text-dark', 'Correcciones'],
            'en_revision'  => ['info text-dark',    'En revisión'],
            'aceptado'     => ['success',           'Aceptado'],
        ];
        [$color, $texto] = $map[$estado] ?? ['secondary', ucfirst($estado)];
        return "<span class='badge bg-{$color}'>{$texto}</span>";
    }

    // ──────────────────────────────────────────────────────────────
    // HELPER PRIVADO — validación de archivo subido
    // ──────────────────────────────────────────────────────────────

    /**
     * Valida MIME real y tamaño del archivo.
     * Solo acepta PDF y DOCX (Carta Compromiso y Carta de Terminación).
     *
     * @return array [$_FILES entry, $mime, $ext]
     */
    private function validarArchivo(array $archivo): array
    {
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

        return [$archivo, $mime, $mimesValidos[$mime]];
    }

    // Stubs requeridos por otras vistas
    public function filtros(int $id_usuario, string $rol): array
    {
        return [];
    }
    public function encabezados(string $rol): array
    {
        return [];
    }
    public function datosopciones(string $rol, array $filtros): array
    {
        return [];
    }
}
