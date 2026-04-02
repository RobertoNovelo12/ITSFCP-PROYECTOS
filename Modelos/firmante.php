<?php
require_once __DIR__ . '/../publico/config/conexion.php';

/**
 * CLASE: Firmante
 * Modelo de acceso a datos para la tabla `firmantes`.
 * Gestiona operaciones CRUD de firmantes digitales,
 * incluyendo carga, encriptación y descarga de imagen de firma.
 *
 * TABLA: firmantes
 *   - id_firmantes (PK)
 *   - id_instituto (FK)
 *   - nombre       VARCHAR(150)
 *   - cargo        VARCHAR(150)
 *   - firma_digital VARCHAR(255) — ruta relativa de la imagen PNG encriptada
 *   - estado        TINYINT(1)
 */
class Firmante
{
    private $con;

    /**
     * Clave de encriptación AES-256-CBC para proteger las imágenes de firma.
     * IMPORTANTE: Mueve esta clave a una variable de entorno (.env) en producción.
     * Ejemplo: $_ENV['FIRMA_KEY'] o getenv('FIRMA_KEY')
     */

    private function getEncryptKey(): string
    {
        $key = $_ENV['FIRMA_KEY'] ?? getenv('FIRMA_KEY');

        if (empty($key)) {
            throw new Exception("La clave de encriptación FIRMA_KEY no está configurada en .env");
        }

        if (strlen($key) !== 32) {
            throw new Exception("FIRMA_KEY debe tener exactamente 32 caracteres para AES-256.");
        }

        return $key;
    }

    private const ENCRYPT_CIPHER = 'AES-256-CBC';

    /**
     * Ruta base donde se almacenan las imágenes de firma.
     * Relativa a la raíz del proyecto: publico/img/firmas/
     */
    private const RUTA_FIRMAS = __DIR__ . '/../publico/img/firmas/';

    /**
     * Tamaño recomendado para la imagen de firma en reportes:
     *  - Ancho: 300 px  (suficiente para impresión clara en reportes A4)
     *  - Alto:  100 px  (proporción estándar firma/cargo)
     *  - Formato: PNG con fondo transparente
     *  - Peso máximo original antes de procesar: 2 MB
     */
    private const FIRMA_ANCHO   = 300;
    private const FIRMA_ALTO    = 100;
    private const FIRMA_MAX_KB  = 2048; // 2 MB

    public function __construct($conn)
    {
        $this->con = $conn;
    }

    // Encriptación / Desencriptación de imagen de firma

    /**
     * Encripta el contenido binario de una imagen de firma (PNG)
     * usando AES-256-CBC y lo guarda como archivo .enc en disco.
     *
     * FLUJO:
     *   1. Lee los bytes de la imagen temporal subida.
     *   2. Genera un IV aleatorio de 16 bytes (seguro).
     *   3. Encripta con openssl_encrypt en modo CBC.
     *   4. Guarda [IV(16 bytes) + datos_encriptados] en el archivo .enc.
     *
     * @param string $rutaTemporal   Ruta del archivo temporal ($_FILES[...]['tmp_name'])
     * @param string $nombreArchivo  Nombre de destino (sin extensión), p.ej. firma_20240315_143022
     * @return string                Nombre del archivo .enc guardado
     * @throws Exception             Si falla la lectura, encriptación o escritura
     */
    public function encriptarYGuardarFirma(string $rutaTemporal, string $nombreArchivo): string
    {
        // 1. Leer bytes originales de la imagen
        $bytesOriginales = file_get_contents($rutaTemporal);
        if ($bytesOriginales === false) {
            throw new Exception("No se pudo leer la imagen de firma temporal.");
        }

        // 2. Generar IV aleatorio de 16 bytes
        $iv = random_bytes(16);

        // 3. Encriptar con AES-256-CBC
        $datosEncriptados = openssl_encrypt(
            $bytesOriginales,
            self::ENCRYPT_CIPHER,
            $this->getEncryptKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($datosEncriptados === false) {
            throw new Exception("Error al encriptar la imagen de firma.");
        }

        // 4. Guardar IV + datos encriptados en archivo .enc
        $nombreFinal = $nombreArchivo . '.enc';
        $rutaFinal   = self::RUTA_FIRMAS . $nombreFinal;

        if (!is_dir(self::RUTA_FIRMAS)) {
            mkdir(self::RUTA_FIRMAS, 0755, true);
        }

        if (file_put_contents($rutaFinal, $iv . $datosEncriptados) === false) {
            throw new Exception("No se pudo guardar la imagen de firma encriptada en disco.");
        }

        return $nombreFinal;
    }

    /**
     * Desencripta una imagen de firma almacenada como .enc
     * y devuelve los bytes originales del PNG para visualización o descarga.
     *
     * FLUJO:
     *   1. Lee el archivo .enc desde disco.
     *   2. Extrae los primeros 16 bytes como IV.
     *   3. Desencripta el resto con AES-256-CBC.
     *   4. Devuelve los bytes PNG originales.
     *
     * @param string $nombreArchivo  Nombre del archivo .enc (solo nombre, sin ruta)
     * @return string                Bytes PNG desencriptados
     * @throws Exception             Si el archivo no existe o la desencriptación falla
     */
    public function desencriptarFirma(string $nombreArchivo): string
    {
        $rutaArchivo = self::RUTA_FIRMAS . $nombreArchivo;

        if (!file_exists($rutaArchivo)) {
            throw new Exception("El archivo de firma no existe: " . htmlspecialchars($nombreArchivo));
        }

        $contenido = file_get_contents($rutaArchivo);
        if ($contenido === false || strlen($contenido) <= 16) {
            throw new Exception("El archivo de firma está dañado o vacío.");
        }

        // Extraer IV (primeros 16 bytes) y datos encriptados
        $iv              = substr($contenido, 0, 16);
        $datosEncriptados = substr($contenido, 16);

        $bytesOriginales = openssl_decrypt(
            $datosEncriptados,
            self::ENCRYPT_CIPHER,
            $this->getEncryptKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($bytesOriginales === false) {
            throw new Exception("Error al desencriptar la imagen de firma. Clave incorrecta o archivo dañado.");
        }

        return $bytesOriginales;
    }

    /**
     * Procesa y redimensiona la imagen de firma al tamaño estándar para reportes.
     * - Ancho: 300 px | Alto: 100 px
     * - Mantiene transparencia PNG (fondo transparente)
     * - Valida formato PNG antes de procesar
     *
     * @param string $rutaTemporal  Ruta del archivo temporal subido
     * @return string               Ruta del archivo PNG redimensionado listo para encriptar
     * @throws Exception            Si el formato no es PNG o el procesamiento falla
     */
    public function redimensionarFirma(string $rutaTemporal): string
    {
        // Verificar que sea realmente PNG por MIME (no solo extensión)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $rutaTemporal);
        finfo_close($finfo);

        if ($mimeType !== 'image/png') {
            throw new Exception("La firma digital debe ser una imagen PNG. Formato recibido: " . $mimeType);
        }

        // Verificar tamaño máximo
        if (filesize($rutaTemporal) > self::FIRMA_MAX_KB * 1024) {
            throw new Exception("La imagen de firma supera el tamaño máximo permitido (" . self::FIRMA_MAX_KB . " KB).");
        }

        // Cargar imagen original
        $imagenOriginal = imagecreatefrompng($rutaTemporal);
        if (!$imagenOriginal) {
            throw new Exception("No se pudo cargar la imagen PNG de la firma.");
        }

        // Crear canvas con tamaño estándar para reportes (transparente)
        $imagenRedim = imagecreatetruecolor(self::FIRMA_ANCHO, self::FIRMA_ALTO);
        imagealphablending($imagenRedim, false);
        imagesavealpha($imagenRedim, true);
        $transparente = imagecolorallocatealpha($imagenRedim, 0, 0, 0, 127);
        imagefill($imagenRedim, 0, 0, $transparente);
        imagealphablending($imagenRedim, true);

        // Redimensionar manteniendo proporciones
        imagecopyresampled(
            $imagenRedim,
            $imagenOriginal,
            0,
            0,
            0,
            0,
            self::FIRMA_ANCHO,
            self::FIRMA_ALTO,
            imagesx($imagenOriginal),
            imagesy($imagenOriginal)
        );

        // Guardar imagen redimensionada en archivo temporal
        $rutaRedim = sys_get_temp_dir() . '/firma_redim_' . uniqid() . '.png';
        imagesavealpha($imagenRedim, true);
        imagepng($imagenRedim, $rutaRedim);

        imagedestroy($imagenOriginal);
        imagedestroy($imagenRedim);

        return $rutaRedim;
    }

    /**
     * Genera un nombre único para el archivo de firma usando fecha y hora.
     * Formato: firma_YYYYMMDD_HHMMSS_[microsegundos]
     *
     * @return string  Nombre único sin extensión
     */
    public static function generarNombreFirma(): string
    {
        $ahora = new DateTime();
        return 'firma_' . $ahora->format('Ymd_His') . '_' . substr((string)microtime(false), 2, 6);
    }

    /**
     * Elimina el archivo de firma encriptado del disco (al actualizar o desactivar).
     *
     * @param string|null $nombreArchivo  Nombre del archivo .enc
     * @return void
     */
    public function eliminarArchiveFirma(?string $nombreArchivo): void
    {
        if (empty($nombreArchivo)) return;

        $ruta = self::RUTA_FIRMAS . $nombreArchivo;
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }

    // Filtros y estadísticas

    /**
     * Obtiene conteos para el panel de filtros (Total, Activos, Desactivados).
     *
     * @param string $rol
     * @return array
     * @throws Exception
     */
    public function obtenerDatosFiltro($rol): array
    {
        if ($rol !== 'supervisor') {
            return [];
        }

        $sql = "SELECT 
                    COUNT(*) AS Total,
                    COALESCE(SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END), 0) AS Activo,
                    COALESCE(SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END), 0) AS Desactivado
                FROM firmantes";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDatosFiltro): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDatosFiltro): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $resultado;
    }

    // Construcción dinámica de WHERE

    /**
     * Construye la cláusula WHERE dinámica según filtros de estado y búsqueda.
     * Reutilizable por todos los métodos de consulta.
     *
     * @param array  $params   Parámetros bind (por referencia)
     * @param string $types    Tipos de bind (por referencia)
     * @param string $buscar   Texto de búsqueda
     * @param int    $filtro   0=Desactivado, 1=Activo, 2=Todos
     * @return string          Cláusula WHERE completa
     */
    private function construirWhere(&$params, &$types, $buscar, $filtro): string
    {
        $where = [];

        // Filtro por estado
        if ($filtro == 0) {
            $where[] = "estado = 0";
        }
        if ($filtro == 1) {
            $where[] = "estado = 1";
        } elseif ($filtro == 2) {
            $where[] = "estado IN (0,1)";
        }

        // Búsqueda por nombre o cargo del firmante
        if (!empty($buscar)) {
            $where[] = "(nombre LIKE ? OR cargo LIKE ?)";
            $params[] = "%$buscar%";
            $params[] = "%$buscar%";
            $types .= "ss";
        }

        return " WHERE " . implode(" AND ", $where);
    }

    // Listado con paginación

    /**
     * Obtiene la lista paginada de firmantes con filtros aplicados.
     *
     * @param string|null $buscar   Texto de búsqueda
     * @param int         $filtro   0=Desactivado, 1=Activo, 2=Todos
     * @return array                ['firmante' => [...], 'paginacion' => [...]]
     * @throws Exception
     */
    public function obtenerTablaFiltro($buscar, $filtro): array
    {
        $pagina    = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $por_pagina = 6;
        $desde     = ($pagina - 1) * $por_pagina;

        $params = [];
        $types  = "";

        $total         = $this->obtenerCantidadFirmante($buscar, $filtro);
        $total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

        $sql = "SELECT 
                    id_firmantes,
                    id_instituto,
                    nombre,
                    cargo,
                    firma_digital,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'        
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estados
                FROM firmantes";

        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);
        $sql .= " ORDER BY id_firmantes ASC LIMIT ?, ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerTablaFiltro): " . $this->con->error);
        }

        $params[] = $desde;
        $params[] = $por_pagina;
        $types .= "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerTablaFiltro): " . $stmt->error);
        }

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            "firmante"   => $data,
            "paginacion" => [
                "total"         => $total,
                "por_pagina"    => $por_pagina,
                "pagina"        => $pagina,
                "total_paginas" => $total_paginas
            ]
        ];
    }

    /**
     * Cuenta el total de firmantes según filtros (para paginación).
     *
     * @param string|null $buscar
     * @param int         $filtro
     * @return int
     * @throws Exception
     */
    public function obtenerCantidadFirmante($buscar = null, $filtro = 2): int
    {
        $params = [];
        $types  = "";

        $sql  = "SELECT COUNT(*) AS total FROM firmantes";
        $sql .= $this->construirWhere($params, $types, $buscar, $filtro);

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerCantidadFirmante): " . $this->con->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerCantidadFirmante): " . $stmt->error);
        }

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($resultado['total'] ?? 0);
    }

    // Obtener datos individuales

    /**
     * Obtiene los datos de un firmante para el formulario de edición.
     *
     * @param int $id_firmantes
     * @return array
     * @throws Exception
     */
    public function obtenerEditar($id_firmantes): array
    {
        $sql = "SELECT 
                    id_firmantes,
                    id_instituto,
                    nombre,
                    cargo,
                    firma_digital,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM firmantes
                WHERE id_firmantes = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerEditar): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_firmantes);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerEditar): " . $stmt->error);
        }

        $firmante = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$firmante) {
            throw new Exception("Firmante no encontrado.");
        }

        return $firmante;
    }

    /**
     * Obtiene los datos completos de un firmante para la vista de detalles.
     *
     * @param int $id_firmantes
     * @return array
     * @throws Exception
     */
    public function obtenerDetalles($id_firmantes): array
    {
        $sql = "SELECT 
                    id_firmantes,
                    id_instituto,
                    nombre,
                    cargo,
                    firma_digital,
                    CASE 
                        WHEN estado = 1 THEN 'Activo'
                        WHEN estado = 0 THEN 'Desactivado'
                        ELSE 'Desconocido'
                    END AS estado
                FROM firmantes
                WHERE id_firmantes = ?";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerDetalles): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_firmantes);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerDetalles): " . $stmt->error);
        }

        $firmante = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$firmante) {
            throw new Exception("Firmante no encontrado.");
        }

        return $firmante;
    }

    // Crear firmante

    /**
     * Registra un nuevo firmante en la base de datos.
     *
     * REGLAS:
     * - Se crea siempre con estado Activo (1).
     * - No puede duplicar nombre+cargo activo en el mismo instituto.
     * - La firma_digital almacena el nombre del archivo .enc (relativo a publico/img/firmas/).
     *
     * IMPORTANTE: Ejecutar dentro de una transacción desde el controlador.
     *
     * @param int    $id_instituto
     * @param string $nombre
     * @param string $cargo
     * @param string $firma_digital  Nombre del archivo .enc generado
     * @return int                   ID del nuevo firmante
     * @throws Exception
     */
    public function registrarFirmante(int $id_instituto, string $nombre, string $cargo, string $firma_digital): int
    {
        // Validar duplicado activo
        $validacion = $this->verificarFirmante($nombre, $cargo, $id_instituto);

        if ($validacion['activo']) {
            throw new Exception("Ya existe un firmante activo con ese nombre y cargo en el instituto.");
        }

        $sql = "INSERT INTO firmantes 
                    (id_instituto, nombre, cargo, firma_digital, estado) 
                VALUES (?, ?, ?, ?, 1)";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (registrarFirmante): " . $this->con->error);
        }

        $stmt->bind_param("isss", $id_instituto, $nombre, $cargo, $firma_digital);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (registrarFirmante): " . $stmt->error);
        }

        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    // SECCIÓN: Editar firmante

    /**
     * Actualiza los datos de un firmante existente.
     * Si se sube una nueva imagen de firma, actualiza también firma_digital.
     *
     * IMPORTANTE: Ejecutar dentro de una transacción desde el controlador.
     *
     * @param int         $id_firmantes
     * @param int         $id_instituto
     * @param string      $nombre
     * @param string      $cargo
     * @param string|null $firma_digital  null = no cambiar la firma existente
     * @return int                        ID del firmante editado
     * @throws Exception
     */
    public function editarFirmante(int $id_firmantes, int $id_instituto, string $nombre, string $cargo, ?string $firma_digital): int
    {
        if ($firma_digital !== null) {
            // Actualizar todos los campos incluyendo la nueva firma
            $sql = "UPDATE firmantes 
                    SET id_instituto = ?, nombre = ?, cargo = ?, firma_digital = ?
                    WHERE id_firmantes = ?";
            $stmt = $this->con->prepare($sql);

            if (!$stmt) {
                throw new Exception("Error en prepare (editarFirmante con firma): " . $this->con->error);
            }

            $stmt->bind_param("isssi", $id_instituto, $nombre, $cargo, $firma_digital, $id_firmantes);
        } else {
            // Actualizar solo nombre, cargo e instituto (sin tocar la firma)
            $sql = "UPDATE firmantes 
                    SET id_instituto = ?, nombre = ?, cargo = ?
                    WHERE id_firmantes = ?";
            $stmt = $this->con->prepare($sql);

            if (!$stmt) {
                throw new Exception("Error en prepare (editarFirmante sin firma): " . $this->con->error);
            }

            $stmt->bind_param("issi", $id_instituto, $nombre, $cargo, $id_firmantes);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (editarFirmante): " . $stmt->error);
        }

        $stmt->close();

        return $id_firmantes;
    }

    // Cambios de estado (Reactivar / Desactivar)

    /**
     * Reactiva un firmante previamente desactivado.
     *
     * IMPORTANTE: Ejecutar dentro de una transacción.
     *
     * @param int $id_firmantes
     * @return void
     * @throws Exception
     */
    public function reactivar(int $id_firmantes): void
    {
        // 1. Obtener datos para validar conflictos
        $sqlDatos = "SELECT nombre, cargo, id_instituto FROM firmantes WHERE id_firmantes = ?";
        $stmtDatos = $this->con->prepare($sqlDatos);

        if (!$stmtDatos) {
            throw new Exception("Error en prepare (reactivar datos): " . $this->con->error);
        }

        $stmtDatos->bind_param("i", $id_firmantes);
        $stmtDatos->execute();
        $datos = $stmtDatos->get_result()->fetch_assoc();
        $stmtDatos->close();

        if (!$datos) {
            throw new Exception("No se pudieron obtener datos del firmante para reactivar.");
        }

        // 2. Validar que no exista ya un firmante activo con mismo nombre+cargo en el instituto
        $validacion = $this->verificarFirmante($datos['nombre'], $datos['cargo'], $datos['id_instituto']);

        if ($validacion['activo']) {
            throw new Exception("Ya existe un firmante activo con el mismo nombre y cargo en ese instituto.");
        }

        // 3. Reactivar (solo si estaba desactivado)
        $sql = "UPDATE firmantes 
                SET estado = 1
                WHERE id_firmantes = ? 
                  AND estado = 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (reactivarFirmante): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_firmantes);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (reactivarFirmante): " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("El firmante ya estaba activo o no se pudo actualizar.");
        }

        $stmt->close();
    }

    /**
     * Desactiva lógicamente un firmante (soft delete, estado = 0).
     *
     * @param int $id_firmantes
     * @return int  Filas afectadas
     * @throws Exception
     */
    public function eliminar_firmante(int $id_firmantes): int
    {
        $sql = "UPDATE firmantes 
                SET estado = 0
                WHERE id_firmantes = ? 
                  AND estado <> 0";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (eliminar_firmante): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_firmantes);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (eliminar_firmante): " . $stmt->error);
        }

        $filas = $stmt->affected_rows;
        $stmt->close();

        return $filas;
    }

    // Validaciones y búsquedas auxiliares

    /**
     * Verifica si existe duplicidad de firmante (nombre + cargo + instituto).
     *
     * @param string $nombre
     * @param string $cargo
     * @param int    $id_instituto
     * @return array  ['activo' => int, 'desactivado' => int]
     * @throws Exception
     */
    public function verificarFirmante(string $nombre, string $cargo, int $id_instituto): array
    {
        $sql = "SELECT
                    EXISTS(
                        SELECT 1 FROM firmantes
                        WHERE estado = 1 AND nombre = ? AND cargo = ? AND id_instituto = ?
                    ) AS activo,
                    EXISTS(
                        SELECT 1 FROM firmantes
                        WHERE estado = 0 AND nombre = ? AND cargo = ? AND id_instituto = ?
                    ) AS desactivado";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarFirmante): " . $this->con->error);
        }

        $stmt->bind_param("ssissi", $nombre, $cargo, $id_instituto, $nombre, $cargo, $id_instituto);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarFirmante): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo"      => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Verifica duplicidad de firmante excluyendo el propio ID (para edición).
     *
     * @param int    $id_firmantes  ID del firmante que se está editando
     * @param string $nombre
     * @param string $cargo
     * @param int    $id_instituto
     * @return array  ['activo' => int, 'desactivado' => int]
     * @throws Exception
     */
    public function verificarFirmanteExcluyendo(int $id_firmantes, string $nombre, string $cargo, int $id_instituto): array
    {
        $sql = "SELECT
                    EXISTS(
                        SELECT 1 FROM firmantes
                        WHERE estado = 1 AND nombre = ? AND cargo = ? AND id_instituto = ? AND id_firmantes != ?
                    ) AS activo,
                    EXISTS(
                        SELECT 1 FROM firmantes
                        WHERE estado = 0 AND nombre = ? AND cargo = ? AND id_instituto = ? AND id_firmantes != ?
                    ) AS desactivado";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (verificarFirmanteExcluyendo): " . $this->con->error);
        }

        $stmt->bind_param(
            "ssiissii",
            $nombre,
            $cargo,
            $id_instituto,
            $id_firmantes,
            $nombre,
            $cargo,
            $id_instituto,
            $id_firmantes
        );

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (verificarFirmanteExcluyendo): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            "activo"      => (int)($res['activo']),
            "desactivado" => (int)($res['desactivado'])
        ];
    }

    /**
     * Obtiene el estado actual de un firmante por ID.
     * Permite bloqueo de fila (FOR UPDATE) para control de concurrencia.
     *
     * @param int  $id_firmantes
     * @param bool $forUpdate
     * @return array|null
     * @throws Exception
     */
    public function obtenerPorId(int $id_firmantes, bool $forUpdate = false): ?array
    {
        $sql = "SELECT estado, firma_digital 
                FROM firmantes 
                WHERE id_firmantes = ?";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (obtenerPorId): " . $this->con->error);
        }

        $stmt->bind_param("i", $id_firmantes);

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (obtenerPorId): " . $stmt->error);
        }

        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res ?: null;
    }

    /**
     * Bloquea los registros activos de firmantes para control de concurrencia.
     * REQUIERE: InnoDB + transacción activa.
     *
     * @return void
     * @throws Exception
     */
    public function bloquear_tabla(): void
    {
        $sql = "SELECT id_firmantes 
                FROM firmantes
                WHERE estado = 1 
                FOR UPDATE";

        $stmt = $this->con->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error en prepare (bloquear_tabla): " . $this->con->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Error en execute (bloquear_tabla): " . $stmt->error);
        }

        $stmt->free_result();
        $stmt->close();
    }
}
