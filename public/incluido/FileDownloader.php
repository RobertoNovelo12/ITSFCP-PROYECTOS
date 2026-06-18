<?php

/**
 * FileDownloader.php
 *
 * Clase de ayuda para gestionar descargas de archivos de forma segura y centralizada.
 *
 * Responsabilidades:
 *  - Resolver la ruta física real de un archivo a partir de su ruta en la BD.
 *  - Validar que el archivo exista y esté dentro del directorio 'storage' (previene path traversal).
 *  - Validar el tipo MIME real del archivo contra una lista blanca.
 *  - Limpiar el buffer de salida y enviar las cabeceras HTTP correctas para forzar la descarga.
 *  - Transferir el contenido del archivo al cliente.
 *  - Manejar errores de forma consistente con códigos de estado HTTP.
 */
class FileDownloader
{
    private string $projectRoot;
    private string $storageBase;

    /**
     * Mapeo de extensiones a tipos MIME para una validación más estricta.
     * @var string[]
     */
    private const ALLOWED_MIMES = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function __construct()
    {
        // Asume que este archivo está en /public/incluido
        $this->projectRoot = realpath(__DIR__ . '/../../');
        if (!$this->projectRoot) {
            $this->fail(500, 'La raíz del proyecto no está configurada correctamente.');
        }

        $this->storageBase = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($this->storageBase)) {
            $this->fail(500, 'El directorio de almacenamiento no está disponible. Contacta al administrador.');
        }
    }

    /**
     * Procesa y envía un archivo para su descarga.
     *
     * @param string $pathFromDB La ruta del archivo tal como está en la base de datos.
     * @param string $displayName El nombre que se usará para la descarga del archivo.
     */
    public function download(string $pathFromDB, string $displayName): void
    {
        $physicalPath = $this->resolveAndValidatePath($pathFromDB);
        $this->validateMime($physicalPath);

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . mime_content_type($physicalPath));
        header('Content-Disposition: attachment; filename="' . basename($displayName) . '"');
        header('Content-Length: ' . filesize($physicalPath));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        readfile($physicalPath);
        exit;
    }

    /**
     * Normaliza y valida la ruta del archivo.
     *
     * @return string La ruta física absoluta y validada.
     */
    private function resolveAndValidatePath(string $pathFromDB): string
    {
        // Normaliza la ruta eliminando prefijos comunes y barras iniciales.
        $cleanPath = ltrim($pathFromDB, '/\\');
        $cleanPath = preg_replace('#^Proyecto/ITSFCP-PROYECTOS[\\/]#i', '', $cleanPath);
        $cleanPath = preg_replace('#^ITSFCP-PROYECTOS[\\/]#i', '', $cleanPath);

        $fullPath = realpath($this->projectRoot . DIRECTORY_SEPARATOR . $cleanPath);

        if (!$fullPath || !file_exists($fullPath)) {
            $this->fail(404, 'El archivo no existe en el servidor. Contacta al administrador.');
        }

        // Seguridad: Previene Path Traversal. La ruta resuelta DEBE estar dentro de /storage.
        if (strpos($fullPath, $this->storageBase) !== 0) {
            $this->fail(403, 'Acceso denegado: ruta fuera del área permitida.');
        }

        return $fullPath;
    }

    /**
     * Valida el tipo MIME real del archivo contra una lista blanca.
     */
    private function validateMime(string $physicalPath): void
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $physicalPath);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            $this->fail(415, 'Tipo de archivo no permitido.');
        }
    }

    /**
     * Termina la ejecución con un código de estado HTTP y un mensaje.
     */
    private function fail(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        exit($message);
    }
}