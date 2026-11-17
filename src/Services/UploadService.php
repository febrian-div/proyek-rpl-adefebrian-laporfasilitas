<?php
namespace App\Services;

class UploadService {
    private string $uploadBaseDir;
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    private int $maxFileSize = 5242880; // 5 MB

    public function __construct() {
        $this->uploadBaseDir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($this->uploadBaseDir)) {
            mkdir($this->uploadBaseDir, 0755, true);
        }
        if (!is_dir($this->uploadBaseDir . '/reports')) {
            mkdir($this->uploadBaseDir . '/reports', 0755, true);
        }
        if (!is_dir($this->uploadBaseDir . '/repairs')) {
            mkdir($this->uploadBaseDir . '/repairs', 0755, true);
        }
    }

    /**
     * Handle single file upload
     *
     * @param array $file $_FILES['input_name']
     * @param string $folder Subfolder: 'reports' | 'repairs'
     * @return array ['success' => bool, 'path' => string, 'error' => string|null]
     */
    public function uploadImage(array $file, string $folder = 'reports'): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'path' => null, 'error' => 'Parameter upload tidak valid.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'error' => 'Gagal mengunggah file (Kode: ' . $file['error'] . ').'];
        }

        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'path' => null, 'error' => 'Ukuran file melebihi batas maksimal 5 MB.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $this->allowedMimes, true)) {
            return ['success' => false, 'path' => null, 'error' => 'Format file tidak didukung. Harap upload gambar JPG, PNG, atau WEBP.'];
        }

        $extension = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $safeFolder = in_array($folder, ['reports', 'repairs'], true) ? $folder : 'reports';
        $filename = sprintf('%s_%s.%s', uniqid('img_', true), time(), $extension);
        $targetDirectory = $this->uploadBaseDir . '/' . $safeFolder;
        $targetPath = $targetDirectory . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'path' => null, 'error' => 'Gagal menyimpan file ke direktori server.'];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'relative_url' => '/uploads/' . $safeFolder . '/' . $filename,
            'absolute_path' => $targetPath,
            'error' => null,
        ];
    }
}
