<?php
/**
 * Clase para manejo de carga de archivos
 *
 * @package GADBE\Utils
 */

namespace GADBE\Utils;

class FileUploader {
    private array $allowedExtensions;
    private int $maxSize;
    private string $uploadPath;
    private array $errors = [];

    /**
     * Constructor
     *
     * @param array|null $allowedExtensions Extensiones permitidas
     * @param int|null $maxSize Tamaño máximo en bytes
     * @param string|null $uploadPath Ruta de subida
     */
    public function __construct(?array $allowedExtensions = null, ?int $maxSize = null, ?string $uploadPath = null) {
        $this->allowedExtensions = $allowedExtensions ?? ALLOWED_EXTENSIONS ?? ['pdf', 'jpg', 'jpeg', 'png'];
        $this->maxSize = $maxSize ?? MAX_UPLOAD_SIZE ?? 5242880; // 5MB default
        $this->uploadPath = $uploadPath ?? UPLOAD_PATH ?? __DIR__ . '/../../uploads/documentos';

        // Crear directorio si no existe
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Valida un archivo subido
     *
     * @param array $file Información del archivo de $_FILES
     * @return bool True si es válido
     */
    public function validate(array $file): bool {
        $this->errors = [];

        // Verificar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }

        // Verificar tamaño
        if ($file['size'] > $this->maxSize) {
            $maxSizeMB = round($this->maxSize / 1048576, 2);
            $this->errors[] = "El archivo excede el tamaño máximo permitido de {$maxSizeMB}MB";
            return false;
        }

        // Verificar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $allowed = implode(', ', $this->allowedExtensions);
            $this->errors[] = "Formato no permitido. Formatos permitidos: $allowed";
            return false;
        }

        // Verificar tipo MIME
        if (!$this->validateMimeType($file['tmp_name'], $extension)) {
            $this->errors[] = "El tipo de archivo no coincide con su extensión";
            return false;
        }

        // Verificar que el archivo no esté corrupto
        if (!$this->isFileValid($file['tmp_name'], $extension)) {
            $this->errors[] = "El archivo está corrupto o no es válido";
            return false;
        }

        return true;
    }

    /**
     * Sube un archivo validado
     *
     * @param array $file Información del archivo de $_FILES
     * @param string|null $customName Nombre personalizado (sin extensión)
     * @return string|false Ruta del archivo subido o false
     */
    public function upload(array $file, ?string $customName = null) {
        if (!$this->validate($file)) {
            return false;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = ($customName ?? $this->generateUniqueFilename()) . '.' . $extension;
        $destination = $this->uploadPath . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->errors[] = "Error al mover el archivo al destino";
            return false;
        }

        // Establecer permisos seguros
        chmod($destination, 0644);

        return $destination;
    }

    /**
     * Sube múltiples archivos
     *
     * @param array $files Array de archivos de $_FILES
     * @return array Array con rutas de archivos subidos y errores
     */
    public function uploadMultiple(array $files): array {
        $uploaded = [];
        $errors = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                // Normalizar array de archivos múltiples
                $fileCount = count($file['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    $singleFile = [
                        'name' => $file['name'][$i],
                        'type' => $file['type'][$i],
                        'tmp_name' => $file['tmp_name'][$i],
                        'error' => $file['error'][$i],
                        'size' => $file['size'][$i]
                    ];

                    $result = $this->upload($singleFile);
                    if ($result) {
                        $uploaded[] = $result;
                    } else {
                        $errors[$file['name'][$i]] = $this->getErrors();
                    }
                }
            } else {
                $result = $this->upload($file);
                if ($result) {
                    $uploaded[] = $result;
                } else {
                    $errors[$file['name']] = $this->getErrors();
                }
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors
        ];
    }

    /**
     * Elimina un archivo
     *
     * @param string $filepath Ruta del archivo
     * @return bool
     */
    public function delete(string $filepath): bool {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Obtiene los errores de validación
     *
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Genera un nombre único para el archivo
     *
     * @return string
     */
    private function generateUniqueFilename(): string {
        return uniqid('file_', true) . '_' . time();
    }

    /**
     * Obtiene mensaje de error de subida
     *
     * @param int $errorCode Código de error de PHP
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
        ];

        return $errors[$errorCode] ?? 'Error desconocido al subir archivo';
    }

    /**
     * Valida el tipo MIME del archivo
     *
     * @param string $filepath Ruta del archivo
     * @param string $extension Extensión del archivo
     * @return bool
     */
    private function validateMimeType(string $filepath, string $extension): bool {
        $validMimeTypes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png' => ['image/png']
        ];

        if (!isset($validMimeTypes[$extension])) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        return in_array($mimeType, $validMimeTypes[$extension]);
    }

    /**
     * Verifica que el archivo no esté corrupto
     *
     * @param string $filepath Ruta del archivo
     * @param string $extension Extensión del archivo
     * @return bool
     */
    private function isFileValid(string $filepath, string $extension): bool {
        switch ($extension) {
            case 'pdf':
                // Verificar que sea un PDF válido (empieza con %PDF)
                $handle = fopen($filepath, 'r');
                $header = fread($handle, 4);
                fclose($handle);
                return $header === '%PDF';

            case 'jpg':
            case 'jpeg':
            case 'png':
                // Verificar que sea una imagen válida
                return @getimagesize($filepath) !== false;

            default:
                return true;
        }
    }
}
