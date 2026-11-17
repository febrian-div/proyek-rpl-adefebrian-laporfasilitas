<?php
/**
 * Migration: Membuat tabel ai_configurations dan seed konfigurasi default.
 * Jalankan: php src/migrate_ai_config.php
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Models\AIConfiguration;

$db = Database::getConnection();
if (!$db) {
    echo "ERROR: Koneksi database gagal.\n";
    exit(1);
}

// 1. Buat tabel ai_configurations jika belum ada
$sql = "CREATE TABLE IF NOT EXISTS `ai_configurations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `api_key` TEXT NULL,
    `base_url` VARCHAR(255) NULL,
    `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.40,
    `max_tokens` INT NOT NULL DEFAULT 600,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->exec($sql);
echo "SUCCESS: Tabel ai_configurations siap.\n";

// 2. Cek apakah sudah ada data awal
$count = (int)$db->query("SELECT COUNT(*) FROM ai_configurations")->fetchColumn();
if ($count === 0) {
    $activeProvider = strtolower((string)env('AI_PROVIDER', 'gemini'));
    if (!in_array($activeProvider, ['nvidia', 'gemini'], true)) {
        $activeProvider = 'gemini';
    }

    $nvidiaKey = (string)env('NVIDIA_API_KEY', '');
    $nvidiaModel = (string)env('NVIDIA_MODEL', 'meta/llama-3.1-8b-instruct');
    $nvidiaBaseUrl = (string)env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1');

    $geminiKey = (string)env('GEMINI_API_KEY', '');
    $geminiModel = (string)env('GEMINI_MODEL', 'gemini-1.5-flash');

    // Seed NVIDIA
    $encNvidiaKey = $nvidiaKey !== '' ? AIConfiguration::encryptApiKey($nvidiaKey) : null;
    $stmt = $db->prepare("INSERT INTO ai_configurations (provider, display_name, model, api_key, base_url, temperature, max_tokens, is_active)
        VALUES (:provider, :display_name, :model, :api_key, :base_url, :temperature, :max_tokens, :is_active)");
    $stmt->execute([
        'provider'     => 'nvidia',
        'display_name' => 'NVIDIA (NVIDIA NIM)',
        'model'        => $nvidiaModel,
        'api_key'      => $encNvidiaKey,
        'base_url'     => $nvidiaBaseUrl,
        'temperature'  => 0.40,
        'max_tokens'   => 600,
        'is_active'    => ($activeProvider === 'nvidia') ? 1 : 0,
    ]);

    // Seed Gemini
    $encGeminiKey = $geminiKey !== '' ? AIConfiguration::encryptApiKey($geminiKey) : null;
    $stmt->execute([
        'provider'     => 'gemini',
        'display_name' => 'Google Gemini',
        'model'        => $geminiModel,
        'api_key'      => $encGeminiKey,
        'base_url'     => null,
        'temperature'  => 0.40,
        'max_tokens'   => 600,
        'is_active'    => ($activeProvider === 'gemini') ? 1 : 0,
    ]);

    echo "SUCCESS: Seed awal untuk provider NVIDIA dan Google Gemini berhasil ditambahkan.\n";
    echo "INFO: Provider aktif default: " . strtoupper($activeProvider) . "\n";
} else {
    echo "INFO: Tabel ai_configurations sudah memiliki data. Tidak ada perubahan seed.\n";
}
