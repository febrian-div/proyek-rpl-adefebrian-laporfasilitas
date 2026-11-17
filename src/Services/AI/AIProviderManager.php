<?php
namespace App\Services\AI;

use App\Models\AIConfiguration;

class AIProviderManager {

    /** @var array<string, AIProviderInterface> */
    private static array $adapters = [];

    /**
     * Inisialisasi daftar adapter default.
     */
    public static function registerDefaults(): void {
        if (empty(self::$adapters)) {
            self::register(new NvidiaProviderAdapter());
            self::register(new GeminiProviderAdapter());
        }
    }

    /**
     * Mendaftarkan adapter provider baru (Extensible).
     */
    public static function register(AIProviderInterface $adapter): void {
        self::$adapters[$adapter->getName()] = $adapter;
    }

    /**
     * Dapatkan adapter berdasarkan nama provider.
     */
    public static function getAdapter(string $provider): ?AIProviderInterface {
        self::registerDefaults();
        return self::$adapters[strtolower(trim($provider))] ?? null;
    }

    /**
     * Dapatkan seluruh adapter yang terdaftar.
     * @return array<string, AIProviderInterface>
     */
    public static function getAllAdapters(): array {
        self::registerDefaults();
        return self::$adapters;
    }

    /**
     * Dapatkan konfigurasi provider yang sedang aktif dari database (atau fallback env).
     */
    public static function getActiveConfig(): ?array {
        $dbConfig = AIConfiguration::getActive();
        if ($dbConfig) {
            return $dbConfig;
        }

        // Fallback jika database belum ada atau kosong
        $envProvider = strtolower((string)env('AI_PROVIDER', 'gemini'));
        $adapter = self::getAdapter($envProvider) ?: self::getAdapter('gemini');
        if (!$adapter) return null;

        $pName = $adapter->getName();
        return [
            'provider'          => $pName,
            'display_name'      => $adapter->getDisplayName(),
            'model'             => (string)env('AI_MODEL', $adapter->getDefaultModel()),
            'decrypted_api_key' => (string)($pName === 'nvidia' ? env('NVIDIA_API_KEY') : env('GEMINI_API_KEY')),
            'base_url'          => (string)env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
            'temperature'       => 0.40,
            'max_tokens'        => 600,
            'is_active'         => 1,
        ];
    }

    /**
     * Dapatkan adapter untuk provider yang sedang aktif.
     */
    public static function getActiveAdapter(): ?AIProviderInterface {
        $activeConfig = self::getActiveConfig();
        if (!$activeConfig) return null;

        return self::getAdapter($activeConfig['provider'] ?? '');
    }

    /**
     * Eksekusi chatText menggunakan provider yang sedang aktif.
     */
    public static function chatText(string $systemPrompt, string $userMessage, string $contextSummary = '', array $history = []): ?string {
        $activeConfig = self::getActiveConfig();
        if (!$activeConfig) {
            return null;
        }

        $adapter = self::getAdapter($activeConfig['provider'] ?? '');
        if (!$adapter) {
            return null;
        }

        $config = [
            'api_key'     => $activeConfig['decrypted_api_key'] ?? '',
            'model'       => $activeConfig['model'] ?? $adapter->getDefaultModel(),
            'base_url'    => $activeConfig['base_url'] ?? null,
            'temperature' => (float)($activeConfig['temperature'] ?? 0.40),
            'max_tokens'  => (int)($activeConfig['max_tokens'] ?? 600),
        ];

        return $adapter->chatText($systemPrompt, $userMessage, $contextSummary, $history, $config);
    }

    /**
     * Uji koneksi ke provider tertentu menggunakan konfigurasi tersimpan atau input baru.
     */
    public static function testConnection(string $provider, array $overrideConfig = []): array {
        $adapter = self::getAdapter($provider);
        if (!$adapter) {
            return [
                'success' => false,
                'message' => "Provider '{$provider}' tidak dikenal dalam sistem.",
            ];
        }

        $savedConfig = AIConfiguration::findByProvider($provider);

        // Gabungkan config: utamakan override input admin, jika kosong pakai yang tersimpan
        $apiKey = trim((string)($overrideConfig['api_key'] ?? ''));
        if ($apiKey === '' && $savedConfig) {
            $apiKey = (string)($savedConfig['decrypted_api_key'] ?? '');
        }

        $model = trim((string)($overrideConfig['model'] ?? ''));
        if ($model === '' && $savedConfig) {
            $model = (string)($savedConfig['model'] ?? '');
        }
        if ($model === '') {
            $model = $adapter->getDefaultModel();
        }

        $baseUrl = array_key_exists('base_url', $overrideConfig) ? $overrideConfig['base_url'] : ($savedConfig['base_url'] ?? null);

        $mergedConfig = [
            'api_key'     => $apiKey,
            'model'       => $model,
            'base_url'    => $baseUrl,
            'temperature' => $overrideConfig['temperature'] ?? ($savedConfig['temperature'] ?? 0.40),
            'max_tokens'  => $overrideConfig['max_tokens'] ?? ($savedConfig['max_tokens'] ?? 600),
        ];

        return $adapter->testConnection($mergedConfig);
    }

    /**
     * Ambil daftar model dari API provider jika didukung.
     */
    public static function fetchModels(string $provider, array $overrideConfig = []): array {
        $adapter = self::getAdapter($provider);
        if (!$adapter) return [];

        $savedConfig = AIConfiguration::findByProvider($provider);
        $apiKey = trim((string)($overrideConfig['api_key'] ?? ''));
        if ($apiKey === '' && $savedConfig) {
            $apiKey = (string)($savedConfig['decrypted_api_key'] ?? '');
        }

        $baseUrl = array_key_exists('base_url', $overrideConfig) ? $overrideConfig['base_url'] : ($savedConfig['base_url'] ?? null);

        return $adapter->fetchModels([
            'api_key'  => $apiKey,
            'base_url' => $baseUrl,
        ]);
    }
}
