<?php

/**
 * SeguimientoControlador.php
 * Controlador para el seguimiento de documentación de proyectos de investigación.
 *
 * Rutas que maneja (via ?action=):
 *   GET  index          → Vista principal (estudiante: timeline | investigador: solicitudes)
 *   POST actualizarEstado → Investigador aprueba/rechaza una etapa (AJAX JSON)
 *   POST responderSolicitud → Investigador acepta/rechaza integración (AJAX JSON)
 *   POST subirDocumento  → Estudiante sube archivo de una etapa (AJAX JSON)
 */


require_once __DIR__ . '/../publico/config/conexion.php';
require_once __DIR__ . '/../Modelos/seguimiento.php';

class SeguimientoControlador
{
    private SeguimientoModelo $modelo;
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn   = $conn;
        $this->modelo = new SeguimientoModelo($conn);
    }

    private function esEstudiante(string $rol): bool
    {
        return $rol === 'estudiante';
    }

    private function esInvestigador(string $rol): bool
    {
        return in_array($rol, ['investigador', 'profesor'], true);
    }

    private function esSupervisor(string $rol): bool
    {
        return $rol === 'supervisor';
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

    public function index(int $id_usuario, string $rol): array
    {
        if ($this->esEstudiante($rol)) {

            $proyecto = $this->modelo->getProyectoActivo($id_usuario);

            if (!$proyecto) {
                return [
                    'etapas'   => [],
                    'proyecto' => null,
                    'progreso' => ['completadas' => 0, 'total' => 0, 'pct' => 0],
                    'mensaje'  => 'No tienes ningún proyecto activo en este momento.',
                ];
            }

            $etapas = $this->modelo->getEtapasPorProyecto(
                $proyecto['id_proyectos'],
                $id_usuario
            );

            $completadas = array_filter($etapas, fn($e) => $e['estado'] === 'completado');
            $total = count($etapas);

            return [
                'etapas'   => $etapas,
                'proyecto' => $proyecto,
                'progreso' => [
                    'completadas' => count($completadas),
                    'total'       => $total,
                    'pct'         => $total > 0
                        ? round((count($completadas) / $total) * 100)
                        : 0,
                ],
                'mensaje'  => null,
            ];
        }

        if ($this->esInvestigador($rol)) {
            return [
                'etapas'      => [],
                'solicitudes' => $this->modelo->getSolicitudesParaRevisar($id_usuario),
                'avance'      => $this->modelo->getAvanceEstudiantesPorInvestigador($id_usuario),
            ];
        }

        if ($this->esSupervisor($rol)) {
            $id_periodo = intval($_GET['periodo'] ?? 0);
            return [
                'etapas'       => [],
                'estadisticas' => $id_periodo
                    ? $this->modelo->getEstadisticasPeriodo($id_periodo)
                    : [],
            ];
        }

        return ['etapas' => []];
    }

    public function actualizarEstado(): void
    {
        $id_usuario = $this->idUsuario();
        $rol = strtolower($_SESSION['rol'] ?? '');

        if (!$this->esInvestigador($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_seguimiento = intval($_POST['id_seguimiento'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id_seguimiento || !in_array($estado, ['completado', 'rechazado'], true)) {
            $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
        }

        if (!$this->modelo->verificarPermisoInvestigador($id_seguimiento, $id_usuario)) {
            $this->json(['ok' => false, 'msg' => 'No tienes permiso.'], 403);
        }

        $ok = $this->modelo->actualizarEstado(
            $id_seguimiento,
            $estado,
            $comentario,
            $id_usuario
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok ? 'Estado actualizado correctamente.' : 'Error al actualizar.',
        ]);
    }

    public function responderSolicitud(): void
    {
        $id_usuario = $this->idUsuario();
        $rol = strtolower($_SESSION['rol'] ?? '');

        if (!$this->esInvestigador($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_solicitud = intval($_POST['id_solicitud'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id_solicitud || !in_array($estado, ['aceptado', 'rechazado'], true)) {
            $this->json(['ok' => false, 'msg' => 'Datos inválidos.'], 422);
        }

        $ok = $this->modelo->responderSolicitud(
            $id_solicitud,
            $estado,
            $comentario,
            $id_usuario
        );

        $this->json([
            'ok'  => $ok,
            'msg' => $ok
                ? ($estado === 'aceptado' ? 'Estudiante integrado.' : 'Solicitud rechazada.')
                : 'Error.',
        ]);
    }

    public function subirDocumento(): void
    {
        $id_usuario = $this->idUsuario();
        $rol = strtolower($_SESSION['rol'] ?? '');

        if (!$this->esEstudiante($rol)) {
            $this->json(['ok' => false, 'msg' => 'Sin permiso.'], 403);
        }

        $id_proyecto = intval($_POST['id_proyecto'] ?? 0);
        $id_tipo_documento = intval($_POST['id_tipo_documento'] ?? 0);
        $id_plantilla = intval($_POST['id_plantilla'] ?? 0);
        $id_seguimiento_previo = intval($_POST['id_seguimiento'] ?? 0);

        if (!$id_proyecto || !$id_tipo_documento) {
            $this->json(['ok' => false, 'msg' => 'Datos incompletos.'], 422);
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'msg' => 'Archivo inválido.'], 422);
        }

        $archivo = $_FILES['documento'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, ['pdf', 'docx'], true)) {
            $this->json(['ok' => false, 'msg' => 'Solo PDF o DOCX.'], 422);
        }

        if ($archivo['size'] > 10 * 1024 * 1024) {
            $this->json(['ok' => false, 'msg' => 'Máx 10MB.'], 422);
        }

        $dir = __DIR__ . '/../publico/uploads/seguimiento/' . $id_proyecto . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = "{$id_proyecto}_{$id_usuario}_{$id_tipo_documento}_" . date('YmdHis') . ".$extension";
        $rutaFisica = $dir . $nombre;
        $rutaDB = "uploads/seguimiento/$id_proyecto/$nombre";

        if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
            $this->json(['ok' => false, 'msg' => 'Error al guardar.'], 500);
        }

        $id_seguimiento = $id_seguimiento_previo;

        if (!$id_seguimiento) {
            $id_seguimiento = $this->modelo->crearSeguimiento(
                $id_proyecto,
                $id_tipo_documento,
                $id_usuario
            );
        } else {
            $this->modelo->actualizarEstadoEstudiante($id_seguimiento, 'proceso');
        }

        $ok = $this->modelo->registrarDocumento(
            $id_seguimiento,
            $id_plantilla,
            $nombre,
            $rutaDB
        );

        $this->json([
            'ok' => $ok,
            'id_seguimiento' => $id_seguimiento,
            'msg' => $ok ? 'Documento subido.' : 'Error.',
        ]);
    }

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
    public function todasSeccionesAprobadas(int $id_proyecto, int $id_estudiante): bool
    {
        $total = $this->modelo->todasSeccionesAprobadas($id_proyecto, $id_estudiante);

        return ($total >= 11); // Son 12 en total pero por si no acepto los anexos serían 11 activitades contando el reprote final
    }
}
