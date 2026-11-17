<?php
/**
 * Application Configuration & Environment Loader
 */

// Load .env if exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

function env(string $key, mixed $default = null): mixed {
    $value = getenv($key);
    if ($value === false) {
        return $_ENV[$key] ?? $default;
    }
    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'empty', '(empty)' => '',
        'null', '(null)' => null,
        default => $value,
    };
}

// Set timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));

return [
    'name' => env('APP_NAME', 'LaporFasilitas'),
    'env' => env('APP_ENV', 'development'),
    'url' => env('APP_URL', 'http://localhost/projekfasilitas/public'),
    
    // Status Flow Laporan
    'report_statuses' => [
        'MENUNGGU_VERIFIKASI' => 'Menunggu Verifikasi',
        'DIVERIFIKASI'        => 'Diverifikasi',
        'DITUGASKAN'          => 'Ditugaskan',
        'SEDANG_DIPERBAIKI'   => 'Sedang Diperbaiki',
        'SELESAI'             => 'Selesai',
        'DITOLAK'             => 'Ditolak',
    ],

    // Prioritas Laporan
    'priorities' => [
        'RENDAH'  => 'Rendah',
        'SEDANG'  => 'Sedang',
        'TINGGI'  => 'Tinggi',
        'KRITIS'  => 'Kritis',
    ],

    // Role Pengguna
    'roles' => [
        'MASYARAKAT' => 'masyarakat',
        'ADMIN'      => 'admin',
        'PETUGAS'    => 'petugas',
    ],

    // AI Provider Configuration (dibaca server-side saja via env(), tidak ke frontend)
    'ai' => [
        'provider' => env('AI_PROVIDER', 'mock'), // mock, gemini, openai, nvidia
        'gemini_key' => env('GEMINI_API_KEY', ''),
        'openai_key' => env('OPENAI_API_KEY', ''),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini') ?: 'gpt-4o-mini',
        'nvidia_key' => env('NVIDIA_API_KEY', ''),
        'nvidia_model' => env('NVIDIA_MODEL', 'meta/llama-3.1-8b-instruct') ?: 'meta/llama-3.1-8b-instruct',
        'nvidia_base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1') ?: 'https://integrate.api.nvidia.com/v1',
        'model' => env('AI_MODEL', env('GEMINI_MODEL', env('OPENAI_MODEL', 'gemini-1.5-flash'))),
    ],
];
