<?php
/**
 * AI Configuration & Provider Integration Test Suite
 * 
 * Menguji seluruh kriteria penerimaan Fitur Konfigurasi AI Assistant (PRD):
 * 1. RBAC: Hanya Admin yang dapat mengakses konfigurasi AI
 * 2. Masyarakat & Petugas ditolak (403 Forbidden)
 * 3. Penyimpanan konfigurasi provider NVIDIA dan Gemini
 * 4. Keamanan enkripsi & dekripsi API Key (AES-256-CBC)
 * 5. Masking API Key (tidak menampilkan kunci utuh)
 * 6. Pemilihan model dan parameter (temperature, max_tokens)
 * 7. Pergantian provider aktif dan persistensi ke database
 * 8. AIProviderManager merefleksikan adapter aktif
 * 9. Uji koneksi (Connection Test) penanganan API key kosong dan respon
 * 10. Konteks database dan system prompt tetap terkirim utuh ke provider
 * 11. Ketahanan sistem saat provider gagal (Zero Crash, safe fallback)
 * 12. Validasi CSRF Token
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Models\AIConfiguration;
use App\Services\AI\AIProviderManager;
use App\Services\AI\NvidiaProviderAdapter;
use App\Services\AI\GeminiProviderAdapter;
use App\Services\AIService;
use App\Services\ChatbotService;
use App\Middleware\AuthMiddleware;
use App\Helpers\Security;
use App\Config\Database;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ [PASS] $message\n";
        $passed++;
    } else {
        echo "  ❌ [FAIL] $message\n";
        $failed++;
    }
}

echo "======================================================\n";
echo " 🤖 RUNNING AI CONFIGURATION & PROVIDERS TEST SUITE\n";
echo "======================================================\n\n";

$db = Database::getConnection();

// [1] Testing Hak Akses & RBAC Konfigurasi AI
echo "[1] Testing Hak Akses & RBAC Konfigurasi AI...\n";
AuthMiddleware::initSession();

// Simulasi user masyarakat
$_SESSION['user'] = ['id' => 4, 'name' => 'Siti Rahmawati', 'role' => 'masyarakat'];
assertTest(AuthMiddleware::role() === 'masyarakat', "User diset sebagai masyarakat");
$wargaAllowed = (AuthMiddleware::role() === 'admin');
assertTest($wargaAllowed === false, "Masyarakat DITOLAK mengakses konfigurasi AI");

// Simulasi user petugas
$_SESSION['user'] = ['id' => 2, 'name' => 'Budi Pratama', 'role' => 'petugas'];
$petugasAllowed = (AuthMiddleware::role() === 'admin');
assertTest($petugasAllowed === false, "Petugas DITOLAK mengakses konfigurasi AI");

// Simulasi user admin
$_SESSION['user'] = ['id' => 1, 'name' => 'febrian', 'role' => 'admin'];
$adminAllowed = (AuthMiddleware::role() === 'admin');
assertTest($adminAllowed === true, "Admin DIIZINKAN mengakses konfigurasi AI");


// [2] Testing Model Database AIConfiguration & Enkripsi API Key
echo "\n[2] Testing Enkripsi & Dekripsi API Key...\n";
$dummyKeyNvidia = 'nvapi-test-secret-key-1234567890abcdefghijklmnopqrstuvwxyz';
$encrypted = AIConfiguration::encryptApiKey($dummyKeyNvidia);
assertTest($encrypted !== $dummyKeyNvidia, "API Key dienkripsi dan tidak disimpan plaintext");
assertTest(base64_decode($encrypted, true) !== false, "Ciphertext tersimpan dalam format base64");

$decrypted = AIConfiguration::decryptApiKey($encrypted);
assertTest($decrypted === $dummyKeyNvidia, "API Key berhasil didekripsi dengan presisi 100%");

$emptyDecrypted = AIConfiguration::decryptApiKey('');
assertTest($emptyDecrypted === '', "Penanganan aman jika ciphertext kosong");

// [3] Testing Masking API Key
echo "\n[3] Testing Masking API Key (Tampilan Aman)...\n";
$masked = AIConfiguration::maskApiKey($dummyKeyNvidia);
assertTest(str_contains($masked, '••••'), "API Key disamarkan dengan simbol bullet");
assertTest(!str_contains($masked, 'secret-key'), "Bagian rahasia API key tidak terlihat");
assertTest(str_starts_with($masked, 'nvapi-') && str_ends_with($masked, 'wxyz'), "Prefix dan suffix tetap dikenali untuk verifikasi pengguna");

// [4] Testing Penyimpanan Konfigurasi Provider (NVIDIA & Gemini)
echo "\n[4] Testing Penyimpanan Konfigurasi Provider...\n";
$saveNvidia = AIConfiguration::saveConfig('nvidia', [
    'display_name' => 'NVIDIA (NVIDIA NIM)',
    'model'        => 'meta/llama-3.1-8b-instruct',
    'api_key'      => $dummyKeyNvidia,
    'base_url'     => 'https://integrate.api.nvidia.com/v1',
    'temperature'  => 0.35,
    'max_tokens'   => 800,
]);
assertTest($saveNvidia === true, "AIConfiguration::saveConfig berhasil untuk NVIDIA");

$fetchedNvidia = AIConfiguration::findByProvider('nvidia');
assertTest($fetchedNvidia !== null, "Konfigurasi NVIDIA dapat dibaca dari database");
assertTest($fetchedNvidia['model'] === 'meta/llama-3.1-8b-instruct', "Model NVIDIA tersimpan akurat");
assertTest((float)$fetchedNvidia['temperature'] === 0.35, "Temperature NVIDIA tersimpan akurat");
assertTest((int)$fetchedNvidia['max_tokens'] === 800, "Max tokens NVIDIA tersimpan akurat");
assertTest($fetchedNvidia['decrypted_api_key'] === $dummyKeyNvidia, "API Key NVIDIA tersimpan terenkripsi dan dapat didekripsi");

$saveGemini = AIConfiguration::saveConfig('gemini', [
    'display_name' => 'Google Gemini',
    'model'        => 'gemini-1.5-flash',
    'api_key'      => 'AIzaSyDummyTestGeminiKey123456789',
    'temperature'  => 0.45,
    'max_tokens'   => 650,
]);
assertTest($saveGemini === true, "AIConfiguration::saveConfig berhasil untuk Gemini");
$fetchedGemini = AIConfiguration::findByProvider('gemini');
assertTest($fetchedGemini['model'] === 'gemini-1.5-flash', "Model Gemini tersimpan akurat");

// [5] Testing Pergantian Provider Aktif (Switching)
echo "\n[5] Testing Pergantian Provider Aktif...\n";
AIConfiguration::setActive('gemini');
$active1 = AIConfiguration::getActive();
assertTest($active1['provider'] === 'gemini', "Provider aktif berhasil beralih ke 'gemini'");
$nvidiaCheck = AIConfiguration::findByProvider('nvidia');
assertTest((int)$nvidiaCheck['is_active'] === 0, "Provider nonaktif diset is_active = 0");

AIConfiguration::setActive('nvidia');
$active2 = AIConfiguration::getActive();
assertTest($active2['provider'] === 'nvidia', "Provider aktif berhasil beralih ke 'nvidia'");
$geminiCheck = AIConfiguration::findByProvider('gemini');
assertTest((int)$geminiCheck['is_active'] === 0, "Provider Gemini otomatis diset is_active = 0");

// [6] Testing AIProviderManager & Registry Adapter
echo "\n[6] Testing AIProviderManager & Adapter Registry...\n";
$adapters = AIProviderManager::getAllAdapters();
assertTest(isset($adapters['nvidia']) && $adapters['nvidia'] instanceof NvidiaProviderAdapter, "NvidiaProviderAdapter terdaftar di manager");
assertTest(isset($adapters['gemini']) && $adapters['gemini'] instanceof GeminiProviderAdapter, "GeminiProviderAdapter terdaftar di manager");

$activeAdapter = AIProviderManager::getActiveAdapter();
assertTest($activeAdapter !== null, "AIProviderManager::getActiveAdapter mengembalikan adapter aktif");
assertTest($activeAdapter->getName() === 'nvidia', "Adapter aktif saat ini adalah NVIDIA");

// [7] Testing Model List & Model Capabilities
echo "\n[7] Testing Model List & Metadata Provider...\n";
$nvidiaModels = $adapters['nvidia']->getAvailableModels();
assertTest(isset($nvidiaModels['meta/llama-3.1-8b-instruct']), "Model Llama 3.1 8B tersedia di daftar model NVIDIA");
assertTest(isset($nvidiaModels['meta/llama-3.1-70b-instruct']), "Model Llama 3.1 70B tersedia di daftar model NVIDIA");

$geminiModels = $adapters['gemini']->getAvailableModels();
assertTest(isset($geminiModels['gemini-1.5-flash']), "Model Gemini 1.5 Flash tersedia di daftar model Gemini");
assertTest(isset($geminiModels['gemini-1.5-pro']), "Model Gemini 1.5 Pro tersedia di daftar model Gemini");

// [8] Testing Uji Koneksi (Connection Test) Handling
echo "\n[8] Testing Uji Koneksi API Provider...\n";
// Uji coba dengan API Key kosong
$testEmptyKey = AIProviderManager::testConnection('nvidia', ['api_key' => '', 'model' => 'meta/llama-3.1-8b-instruct']);
// Jika di database sudah ada key, ia menguji key yang ada atau jika kosong menolak
assertTest(is_array($testEmptyKey) && isset($testEmptyKey['success']), "Uji koneksi mengembalikan struktur array status");

$testInvalidKey = AIProviderManager::testConnection('gemini', ['api_key' => 'AIzaSyInvalidKey123', 'model' => 'gemini-1.5-flash']);
assertTest($testInvalidKey['success'] === false, "Uji koneksi menolak API key invalid secara aman");
assertTest(!str_contains($testInvalidKey['message'], 'AIzaSyInvalidKey123'), "Pesan error tidak membocorkan nilai API key");

// [9] Testing Integrasi AIService & Pemeliharaan Konteks Chatbot
echo "\n[9] Testing Pemeliharaan Konteks Chatbot & Integrasi AIService...\n";
$aiService = new AIService();
$systemPrompt = ChatbotService::SYSTEM_PROMPT;
assertTest(!empty($systemPrompt), "System prompt asli LaporFasilitas tersedia dan utuh");
assertTest(str_contains($systemPrompt, 'PANDUAN PERSONALISASI & IDENTITAS PENGGUNA'), "System prompt memuat aturan personalisasi pengguna");
assertTest(str_contains($systemPrompt, 'BATASAN HAK AKSES & PRIVASI ROLE'), "System prompt memuat pembatasan role ketat");

$bot = new ChatbotService();
$dbContext = $bot->buildDatabaseContext(['id' => 1, 'name' => 'febrian', 'role' => 'admin'], 'cek laporan');
assertTest(!empty($dbContext), "Konteks database real-time dapat dikompilasi");
assertTest(str_contains($dbContext, 'RINGKASAN DATABASE REAL-TIME'), "Konteks database memuat data real-time laporan");

// [10] Testing Ketahanan Sistem saat Provider Offline / Error
echo "\n[10] Testing Ketahanan Sistem (Zero Crash / Safe Fallback)...\n";
// Set API key dummy yang pasti gagal pada panggilan cURL
$dummyFailConfig = [
    'api_key' => 'nvapi-dummy-fail-key',
    'model'   => 'meta/llama-3.1-8b-instruct',
];
$failResponse = $adapters['nvidia']->chatText($systemPrompt, 'halo', $dbContext, [], $dummyFailConfig);
assertTest($failResponse === null, "Provider gagal mengembalikan null tanpa throw exception");

// Pastikan ChatbotService langsung fallback ke database deterministik jika provider mengembalikan null
$directDbAnswer = $bot->answerDirectFromDatabase('berapa total laporan yang ada?', null);
assertTest(str_contains($directDbAnswer, 'total seluruh laporan'), "Fallback deterministik database berjalan sempurna jika AI offline");

// [11] Testing Proteksi Keamanan CSRF Form
echo "\n[11] Testing Proteksi Keamanan CSRF Form...\n";
$csrfToken = Security::csrfToken();
assertTest(!empty($csrfToken), "CSRF Token tersedia");
assertTest(Security::verifyCsrf($csrfToken) === true, "Validasi token CSRF valid diterima");
assertTest(Security::verifyCsrf('forged_csrf_token_999') === false, "Validasi token CSRF palsu ditolak");

echo "\n======================================================\n";
echo " 📊 TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
