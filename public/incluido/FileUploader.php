<?php

/**
 * FileUploader.php
 *
 * Servicio para manejar la subida de archivos de forma segura y centralizada.
 *
 * Responsabilidades:
 *  - Validar un archivo subido (error, tamaño, tipo MIME).
 *  - Generar un nombre de archivo único y seguro.
 *  - Mover el archivo a una ruta de almacenamiento predefinida.
 *  - Devolver la información del archivo para guardarla en la base de datos.
 */
class FileUploader
{
    private string $projectRoot;
    private string $storagePath;

    /**
     * @param string $storageSubDir Directorio base dentro de 'storage' para esta subida.
     *                                Ejemplo: 'storage/etapas' o 'storage/academico'.
     */
    public function __construct(string $storageSubDir)
    {
        $this->projectRoot = realpath(__DIR__ . '/../../');
        if (!$this->projectRoot) {
            throw new RuntimeException('La raíz del proyecto no está configurada correctamente.');
        }

        $this->storagePath = $this->projectRoot . DIRECTORY_SEPARATOR . trim($storageSubDir, '/\\');
    }

    /**
     * Procesa un archivo subido desde $_FILES.
     *
     * @param array $fileData La entrada de $_FILES (ej: $_FILES['documento']).
     * @param array $allowedMimes Un array de tipos MIME permitidos (ej: ['application/pdf']).
     * @param int $maxSize El tamaño máximo en bytes.
     * @param string $fileNamePrefix Un prefijo para el nombre del archivo generado.
     *
     * @return array{ruta_bd: string, nombre_archivo: string, nombre_display: string, mime: string, extension: string, tamano: int}
     * @throws RuntimeException Si la subida falla por cualquier motivo.
     */
    public function upload(array $fileData, array $allowedMimes, int $maxSize, string $fileNamePrefix): array
    {
        // 1. Validar error de subida
        if (empty($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getUploadErrorMessage($fileData['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        // 2. Validar tamaño
        if ($fileData['size'] > $maxSize) {
            throw new RuntimeException('El archivo supera el tamaño máximo permitido (' . ($maxSize / 1024 / 1024) . ' MB).');
        }

        // 3. Validar tipo MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Tipo de archivo no permitido. Se detectó: ' . $mime);
        }

        // 4. Generar nombres y rutas
        $displayName = basename($fileData['name']);
        $extension = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
        $safeFileName = $fileNamePrefix . '_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;

        // La ruta relativa que se guardará en la BD
        $relativePath = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $this->storagePath) . DIRECTORY_SEPARATOR . $safeFileName;
        $relativePath = str_replace('\\', '/', $relativePath); // Normalizar a slashes

        // La ruta física completa para mover el archivo
        $physicalPath = $this->storagePath . DIRECTORY_SEPARATOR . $safeFileName;

        // 5. Crear directorio y mover archivo
        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0755, true)) {
                throw new RuntimeException('No se pudo crear el directorio de almacenamiento.');
            }
        }

        if (!move_uploaded_file($fileData['tmp_name'], $physicalPath)) {
            throw new RuntimeException('Error al mover el archivo subido al destino final.');
        }

        return [
            'ruta_bd'        => $relativePath,
            'nombre_archivo' => $safeFileName,
            'nombre_display' => $displayName,
            'mime'           => $mime,
            'extension'      => $extension,
            'tamano'         => $fileData['size'],
        ];
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'El archivo excede la directiva upload_max_filesize en php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo excede la directiva MAX_FILE_SIZE especificada en el formulario HTML.',
            UPLOAD_ERR_PARTIAL    => 'El archivo fue solo parcialmente subido.',
            UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta una carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida del archivo.',
            default               => 'Error desconocido al subir el archivo.',
        };
    }
}