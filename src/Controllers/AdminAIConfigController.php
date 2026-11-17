<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Helpers\Response;
use App\Helpers\Security;
use App\Models\AIConfiguration;
use App\Services\AI\AIProviderManager;

class AdminAIConfigController {

    /**
     * Halaman utama Konfigurasi AI Assistant (Admin Only).
     */
    public function index(): void {
        AuthMiddleware::requireRole('admin');

        $adapters = AIProviderManager::getAllAdapters();
        $dbConfigs = AIConfiguration::getAll();

        // Buat map konfigurasi per provider
        $configMap = [];
        foreach ($dbConfigs as $cfg) {
            $configMap[$cfg['provider']] = $cfg;
        }

        $activeConfig = AIConfiguration::getActive();
        $activeProvider = $activeConfig ? $activeConfig['provider'] : 'gemini';

        // Siapkan data provider lengkap untuk antarmuka
        $providersData = [];
        foreach ($adapters as $name => $adapter) {
            $saved = $configMap[$name] ?? null;
            $providersData[$name] = [
                'name'             => $name,
                'display_name'     => $adapter->getDisplayName(),
                'default_model'    => $adapter->getDefaultModel(),
                'available_models' => $adapter->getAvailableModels(),
                'current_model'    => $saved['model'] ?? $adapter->getDefaultModel(),
                'base_url'         => $saved['base_url'] ?? ($name === 'nvidia' ? 'https://integrate.api.nvidia.com/v1' : ''),
                'temperature'      => (float)($saved['temperature'] ?? 0.40),
                'max_tokens'       => (int)($saved['max_tokens'] ?? 600),
                'has_key'          => !empty($saved['has_key']),
                'masked_key'       => $saved['masked_key'] ?? '',
                'is_active'        => ($activeProvider === $name),
                'updated_at'       => $saved['updated_at'] ?? null,
            ];
        }

        view('admin.ai_config', [
            'title'          => 'Konfigurasi AI Assistant - Admin LaporFasilitas',
            'providers'      => $providersData,
            'activeProvider' => $activeProvider,
            'activeConfig'   => $activeConfig,
        ]);
    }

    /**
     * Simpan konfigurasi provider AI (Model, API Key, Parameter).
     */
    public function saveConfig(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $adapter = AIProviderManager::getAdapter($provider);
        if (!$adapter) {
            Response::setFlash('error', "Provider AI '{$provider}' tidak valid atau tidak didukung.");
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $model = trim($_POST['model'] ?? '');
        $customModel = trim($_POST['custom_model'] ?? '');
        if ($model === 'custom' && $customModel !== '') {
            $model = $customModel;
        }
        if ($model === '') {
            $model = $adapter->getDefaultModel();
        }

        $apiKey = trim($_POST['api_key'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $temperature = (float)($_POST['temperature'] ?? 0.40);
        $maxTokens = (int)($_POST['max_tokens'] ?? 600);
        $setActive = !empty($_POST['set_active']);

        $dataToSave = [
            'display_name' => $adapter->getDisplayName(),
            'model'        => $model,
            'base_url'     => $baseUrl !== '' ? $baseUrl : null,
            'temperature'  => max(0.0, min(2.0, $temperature)),
            'max_tokens'   => max(50, min(8192, $maxTokens)),
        ];

        // Hanya perbarui API key jika admin mengetikkan nilai baru
        if ($apiKey !== '') {
            $dataToSave['api_key'] = $apiKey;
        }

        if ($setActive) {
            $dataToSave['is_active'] = 1;
        }

        $saved = AIConfiguration::saveConfig($provider, $dataToSave);
        if ($saved) {
            $msg = "Konfigurasi provider {$adapter->getDisplayName()} berhasil disimpan.";
            if ($setActive) {
                $msg .= " Provider ini sekarang berstatus AKTIF sebagai mesin utama Chatbot AI.";
            }
            Response::setFlash('success', $msg);
        } else {
            Response::setFlash('error', "Gagal menyimpan konfigurasi provider {$adapter->getDisplayName()}.");
        }

        Response::redirect('/admin/konfigurasi-ai');
    }

    /**
     * Aktifkan salah satu provider sebagai provider utama chatbot.
     */
    public function setActive(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $adapter = AIProviderManager::getAdapter($provider);
        if (!$adapter) {
            Response::setFlash('error', 'Provider tidak valid.');
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $config = AIConfiguration::findByProvider($provider);
        if (!$config || empty($config['has_key'])) {
            Response::setFlash('warning', "Peringatan: Provider {$adapter->getDisplayName()} diaktifkan, namun API Key belum tersimpan. Pastikan mengisi API Key agar AI dapat merespons.");
        }

        AIConfiguration::setActive($provider);
        Response::setFlash('success', "Provider {$adapter->getDisplayName()} berhasil diaktifkan sebagai mesin utama Chatbot AI.");
        Response::redirect('/admin/konfigurasi-ai');
    }

    /**
     * Uji koneksi API ke provider secara aktual.
     * Mendukung pemanggilan via AJAX (JSON) atau form submit reguler.
     */
    public function testConnection(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            $err = ['success' => false, 'message' => 'Token CSRF tidak valid atau sesi kedaluwarsa.'];
            if ($this->isAjaxRequest()) {
                Response::json($err, 403);
            }
            Response::setFlash('error', $err['message']);
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $adapter = AIProviderManager::getAdapter($provider);
        if (!$adapter) {
            $err = ['success' => false, 'message' => "Provider '{$provider}' tidak didukung."];
            if ($this->isAjaxRequest()) {
                Response::json($err, 422);
            }
            Response::setFlash('error', $err['message']);
            Response::redirect('/admin/konfigurasi-ai');
            return;
        }

        $model = trim($_POST['model'] ?? '');
        $customModel = trim($_POST['custom_model'] ?? '');
        if ($model === 'custom' && $customModel !== '') {
            $model = $customModel;
        }

        $override = [
            'api_key'     => trim($_POST['api_key'] ?? ''),
            'model'       => $model,
            'base_url'    => trim($_POST['base_url'] ?? ''),
            'temperature' => (float)($_POST['temperature'] ?? 0.40),
            'max_tokens'  => (int)($_POST['max_tokens'] ?? 600),
        ];

        $result = AIProviderManager::testConnection($provider, $override);

        if ($this->isAjaxRequest()) {
            Response::json($result, $result['success'] ? 200 : 400);
            return;
        }

        if ($result['success']) {
            Response::setFlash('success', $result['message']);
        } else {
            Response::setFlash('error', $result['message']);
        }

        Response::redirect('/admin/konfigurasi-ai');
    }

    /**
     * Ambil daftar model terbaru langsung dari API provider (AJAX endpoint).
     */
    public function fetchModels(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Token CSRF tidak valid.'], 403);
            return;
        }

        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $adapter = AIProviderManager::getAdapter($provider);
        if (!$adapter) {
            Response::json(['success' => false, 'message' => 'Provider tidak valid.'], 422);
            return;
        }

        $override = [
            'api_key'  => trim($_POST['api_key'] ?? ''),
            'base_url' => trim($_POST['base_url'] ?? ''),
        ];

        $models = AIProviderManager::fetchModels($provider, $override);
        Response::json([
            'success' => true,
            'models'  => $models,
        ]);
    }

    private function isAjaxRequest(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }
}
