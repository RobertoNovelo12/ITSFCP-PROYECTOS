<?php
/**
 * Controlador: plantilladocumentoControlador.php
 *
 * Orquesta la lógica de negocio del módulo de Plantillas de Documentos.
 * El controlador no genera HTML; las vistas hacen eso.
 * Las operaciones de archivo (upload/descarga) se mantienen en las vistas
 * correspondientes para respetar la separación de capas.
 */

require_once __DIR__ . '/../Modelos/plantilladocumento.php';
require_once __DIR__ . '/../publico/config/conexion.php';

class plantilladocumentoControlador
{
    // 
    //  HELPERS PRIVADOS
    // 

    private function esSupervisor(string $rol): bool
    {
        return $rol === 'supervisor';
    }

    /** Sanitiza un string para evitar XSS al mostrarlo en HTML. */
    private function limpiar(?string $dato): ?string
    {
        return $dato !== null
            ? htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8')
            : null;
    }

    /** Instancia el modelo con la conexión global. */
    private function modelo(): plantilladocumento
    {
        global $conn;
        return new plantilladocumento($conn);
    }

    /** Redirige con un código de mensaje o error y termina la ejecución. */
    private function redirigir(string $url): never
    {
        header("Location: {$url}");
        exit;
    }

    // 
    //  LISTADOS / FILTROS
    // 

    /**
     * Listado con filtro de estado y búsqueda por nombre.
     * Devuelve ['plantillas' => [...], 'paginacion' => [...]]
     */
    public function index(string $rol, ?string $buscar = null): array
    {
        if (!$this->esSupervisor($rol)) return $this->vacio();
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    public function Total(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 2, $buscar);
    }

    public function Activo(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 1, $buscar);
    }

    public function Desactivado(string $rol, ?string $buscar = null): array
    {
        return $this->obtenerPorFiltro($rol, 0, $buscar);
    }

    /** Método base para evitar duplicación entre Total/Activo/Desactivado. */
    private function obtenerPorFiltro(string $rol, int $estado, ?string $buscar): array
    {
        if (!$this->esSupervisor($rol)) return $this->vacio();
        try {
            return $this->modelo()->obtenerTablaFiltro($this->limpiar($buscar), $estado);
        } catch (Throwable $e) {
            error_log("[plantilladocumento] obtenerPorFiltro(): " . $e->getMessage());
            return $this->vacio();
        }
    }

    /** Resultado vacío con estructura de paginación válida. */
    private function vacio(): array
    {
        return [
            'plantillas' => [],
            'paginacion' => [
                'total'        => 0,
                'por_pagina'   => 6,
                'pagina'       => 1,
                'total_paginas'=> 1,
            ],
        ];
    }

    /**
     * Tipos de documento activos para el formulario de creación.
     */
    public function indexCrear(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        try {
            return $this->modelo()->obtenerTipos_documentos();
        } catch (Throwable $e) {
            error_log("[plantilladocumento] indexCrear(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Datos de conteo para generar las opciones del select de filtro.
     */
    public function filtros(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        try {
            return $this->modelo()->obtenerDatosFiltro();
        } catch (Throwable $e) {
            error_log("[plantilladocumento] filtros(): " . $e->getMessage());
            return [];
        }
    }

    // 
    //  PRESENTACIÓN
    // 

    public function encabezadosPrincipal(string $rol): array
    {
        if (!$this->esSupervisor($rol)) return [];
        return ['Plantilla', 'Versión', 'Creación', 'Modificación', 'Archivo', 'Estado', 'Acciones'];
    }

    /**
     * Genera el array de opciones para el select de filtro con conteos.
     * $filtros es el resultado directo de filtros() (una sola fila asociativa).
     */
    public function opciones(string $rol, array $filtros): array
    {
        if (!$this->esSupervisor($rol) || empty($filtros)) return [];

        // filtros() devuelve un array asociativo con Total/Activo/Desactivado
        $d = $filtros;
        return [
            'Total'       => "Total ("       . ($d['Total']       ?? 0) . " en total)",
            'Activo'      => "Activos ("     . ($d['Activo']      ?? 0) . " en total)",
            'Desactivado' => "Desactivados (" . ($d['Desactivado'] ?? 0) . " en total)",
        ];
    }

    /**
     * Convierte la acción del GET al número de filtro esperado por el modelo.
     */
    public function numerofiltro(string $action): int
    {
        return match ($action) {
            'Activo'      => 1,
            'Desactivado' => 0,
            default       => 2,   // Total / index
        };
    }

    public function EstiloEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo'      => 'success',
            'desactivado' => 'danger',
            default       => 'info',
        };
    }

    public function EstiloTimeLine(string $evento): string
    {
        return match (trim($evento)) {
            'CREACION'      => 'success',
            'NUEVA_VERSION' => 'primary',
            'REACTIVACION'  => 'info',
            'DESACTIVACION' => 'danger',
            default         => 'secondary',
        };
    }

    // 
    //  BOTONES DE TABLA
    // 

    private function botonHistorial(int $id_tipo_documento): string
    {
        return '<a href="historial.php?id_tipo_documento=' . $id_tipo_documento . '"
                   class="btn btn-sm btn-primary"
                   data-bs-toggle="tooltip" data-bs-placement="top"
                   data-bs-title="Ver historial de plantilla">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-eye-fill" viewBox="0 0 16 16">
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                    </svg>
                </a>';
    }

    private function botonDesactivar(int $id_plantilla, int $id_tipo_documento): string
    {
        return '<a href="index.php?id_plantilla=' . $id_plantilla
             . '&id_tipo_documento=' . $id_tipo_documento
             . '&action=desactivar"
                   class="btn btn-sm btn-danger"
                   data-bs-toggle="tooltip" data-bs-placement="top"
                   data-bs-title="Desactivar plantilla"
                   onclick="return confirm(\'¿Desactivar esta plantilla?\')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647
                                 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707
                                 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293z"/>
                    </svg>
                </a>';
    }

    private function botonReactivar(int $id_plantilla, int $id_tipo_documento): string
    {
        return '<a href="index.php?id_plantilla=' . $id_plantilla
             . '&id_tipo_documento=' . $id_tipo_documento
             . '&action=reactivar"
                   class="btn btn-sm btn-success"
                   data-bs-toggle="tooltip" data-bs-placement="top"
                   data-bs-title="Reactivar plantilla">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16">
                        <path fill-rule="evenodd"
                              d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2z"/>
                        <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36
                                 1.966A.25.25 0 0 0 8 4.466"/>
                    </svg>
                </a>';
    }

    public function botonesAccionPrincipal(int $id_plantilla, string $rol, string $estado, int $id_tipo_documento): string
    {
        if (!$this->esSupervisor($rol)) return '';

        $botones = $this->botonHistorial($id_tipo_documento);

        if ($estado === 'Activo') {
            $botones .= $this->botonDesactivar($id_plantilla, $id_tipo_documento);
        } elseif ($estado === 'Desactivado') {
            $botones .= $this->botonReactivar($id_plantilla, $id_tipo_documento);
        }

        return '<div class="d-flex gap-1">' . $botones . '</div>';
    }

    // 
    //  DATOS PARA CREAR (Ajax)
    // 

    /**
     * Devuelve nombre y versión para rellenar el formulario vía Ajax.
     */
    public function obtenerPlantillas(int $id_tipo_documento): array
    {
        try {
            $obj     = $this->modelo();
            $info    = $obj->obtenerInfoTipos($id_tipo_documento);
            $version = ($info['ultima_version'] !== null)
                ? (int) $info['ultima_version'] + 1
                : 1;

            return [
                'nombre'  => $info['nombre'] . ' v' . $version,
                'version' => $version,
            ];
        } catch (Throwable $e) {
            error_log("[plantilladocumento] obtenerPlantillas(): " . $e->getMessage());
            return [];
        }
    }

    // 
    //  DETERMINAR SUBCARPETA SEGÚN TIPO DE DOCUMENTO
    // 

    /**
     * Devuelve la subcarpeta de storage según el nombre del tipo de documento.
     *
     * Prioriza la categoría (proceso/final) y el nombre para mayor precisión.
     * Si no encaja en ningún caso conocido, usa 'general'.
     */
    public function carpetaPorTipo(string $nombre, string $categoria = ''): string
    {
        $nombre_lc    = strtolower(trim($nombre));
        $categoria_lc = strtolower(trim($categoria));

        // Casos específicos por nombre
        $mapaNombre = [
            'carta compromiso'    => 'carta',
            'carta de terminacion'=> 'carta',
            'reporte final'       => 'reporte',
            'informe'             => 'informe',
        ];

        foreach ($mapaNombre as $clave => $carpeta) {
            if (str_contains($nombre_lc, $clave)) {
                return $carpeta;
            }
        }

        // Fallback por categoría
        if ($categoria_lc === 'final') return 'final';
        if ($categoria_lc === 'proceso') return 'proceso';

        return 'general';
    }

    // 
    //  OPERACIONES DE ESCRITURA (Registrar / Desactivar / Reactivar)
    // 

    /**
     * Registra una nueva plantilla (o nueva versión de una existente).
     *
     * @param string $rol
     * @param string $nombre          Nombre de display (ej: "Carta Compromiso v3")
     * @param string $nombre_archivo  Nombre único en disco
     * @param string $ruta            Ruta relativa/absoluta guardada en BD
     * @param string $extension       Sin punto: docx, doc
     * @param string $tipo_mime
     * @param int    $tamano_bytes
     * @param int    $id_tipo_documento
     * @param int    $id_usuario
     */
    public function registrar(
        string $rol,
        string $nombre,
        string $nombre_archivo,
        string $ruta,
        string $extension,
        string $tipo_mime,
        int    $tamano_bytes,
        int    $id_tipo_documento,
        int    $id_usuario
    ): never {
        if (!$this->esSupervisor($rol)) {
            $this->redirigir('index.php?error=sin_permiso');
        }

        global $conn;
        $conn->begin_transaction();

        try {
            $obj = $this->modelo();
            $obj->bloquear_tabla($id_tipo_documento);

            $info    = $obj->obtenerInfoTipos($id_tipo_documento);
            $obj->desactivarPorTipo($id_tipo_documento);
            $version = $obj->obtenerSiguienteVersion($id_tipo_documento);

            // 1. Registrar archivo en documentos_subidos
            $id_documento = $obj->registrarDocumento(
                nombre:        $nombre,
                nombre_archivo:$nombre_archivo,
                ruta:          $ruta,
                tipo_mime:     $tipo_mime,
                extension:     $extension,
                tamano_bytes:  $tamano_bytes,
                tipo:          'plantilla',
                visibilidad:   'privado',
                id_usuario:    $id_usuario,
                version:       $version
            );

            // 2. Registrar la plantilla
            $id_plantilla = $obj->registrar($id_tipo_documento, $nombre, $version, $id_documento);

            // 3. Historial
            $accion      = $info['ultima_version'] === null ? 'CREACION' : 'NUEVA_VERSION';
            $descripcion = $this->generarDescripcion($accion, $version, $info['nombre']);
            $obj->registrarHistorial($id_plantilla, $id_usuario, $accion, $descripcion);

            $conn->commit();
            $this->redirigir('index.php?mensaje=1');

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log("[plantilladocumento] registrar() SQL: " . $e->getMessage());
            $this->redirigir('index.php?error=' . ($e->getCode() === 1062 ? 'duplicado' : '2'));

        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[plantilladocumento] registrar(): " . $e->getMessage());
            $this->redirigir('index.php?error=3');
        }
    }

    public function desactivar(string $rol, int $id_plantilla, int $id_usuario): never
    {
        if (!$this->esSupervisor($rol)) {
            $this->redirigir('index.php?error=sin_permiso');
        }
        if ($id_plantilla <= 0) {
            $this->redirigir('index.php?error=id_invalido');
        }

        global $conn;
        $conn->begin_transaction();

        try {
            $obj      = $this->modelo();
            $registro = $obj->obtenerPorId($id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
            }
            if ((int) $registro['activo'] === 0) {
                throw new Exception("La plantilla ya está desactivada");
            }

            $datos = $obj->obtenerInfoPlantilla($id_plantilla);
            $obj->bloquear_tabla($datos['id_tipo_documento']);

            $filas = $obj->desactivarPorTipo($datos['id_tipo_documento']);
            if ($filas === 0) {
                throw new Exception("No había plantillas activas para desactivar");
            }

            $descripcion = $this->generarDescripcion('DESACTIVACION', null, $datos['nombre']);
            $obj->registrarHistorial($id_plantilla, $id_usuario, 'DESACTIVACION', $descripcion);

            $conn->commit();
            $this->redirigir('index.php?mensaje=1');

        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[plantilladocumento] desactivar(): " . $e->getMessage());
            $this->redirigir('index.php?error=10');
        }
    }

    public function reactivar(string $rol, int $id_plantilla, int $id_usuario): never
    {
        if (!$this->esSupervisor($rol)) {
            $this->redirigir('index.php?error=sin_permiso');
        }

        global $conn;
        $conn->begin_transaction();

        try {
            $obj      = $this->modelo();
            $registro = $obj->obtenerPorId($id_plantilla);

            if (!$registro) {
                throw new Exception("Plantilla no existe (ID: {$id_plantilla})");
            }
            if ((int) $registro['activo'] === 1) {
                throw new Exception("La plantilla ya está activa");
            }

            $obj->activarVersion($id_plantilla);

            $datos       = $obj->obtenerInfoPlantilla($id_plantilla);
            $descripcion = $this->generarDescripcion('REACTIVACION', $datos['version'], null);
            $obj->registrarHistorial($id_plantilla, $id_usuario, 'REACTIVACION', $descripcion);

            $conn->commit();
            $this->redirigir('index.php?mensaje=1');

        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[plantilladocumento] reactivar(): " . $e->getMessage());
            $this->redirigir('index.php?error=2');
        }
    }

    // 
    //  LÍNEA DE TIEMPO
    // 

    public function info_linea_tiempo(int $id_tipo_documento): array
    {
        try {
            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
            return $this->modelo()->linea_tiempo($id_tipo_documento, $pagina);
        } catch (Throwable $e) {
            error_log("[plantilladocumento] info_linea_tiempo(): " . $e->getMessage());
            $this->redirigir('index.php?error=1');
        }
    }

    // 
    //  HELPER INTERNO: DESCRIPCIONES DE HISTORIAL
    // 

    private function generarDescripcion(string $accion, ?int $version, ?string $nombre): string
    {
        return match ($accion) {
            'CREACION'      => "Se creó la plantilla versión {$version} para el tipo de documento \"{$nombre}\".",
            'NUEVA_VERSION' => "Se registró una nueva versión {$version}. La versión anterior fue desactivada automáticamente.",
            'REACTIVACION'  => "Se reactivó la versión {$version} y se desactivaron las demás versiones.",
            'DESACTIVACION' => "Se desactivaron las plantillas activas del tipo de documento \"{$nombre}\".",
            default         => "Acción desconocida: {$accion}.",
        };
    }
}