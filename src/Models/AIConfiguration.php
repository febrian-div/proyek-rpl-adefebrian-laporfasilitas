<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class AIConfiguration {

    private const CIPHER = 'aes-256-cbc';

    /**
     * Dapatkan kunci enkripsi dari env atau fallback secret server.
     */
    private static function getSecretKey(): string {
        $secret = (string)env('APP_KEY', '');
        if ($secret === '') {
            $secret = (string)env('DB_PASS', 'laporfasilitas_secret_ai_key_2026');
        }
        return hash('sha256', 'laporfasilitas_aes_key_' . $secret, true);
    }

    /**
     * Enkripsi API Key secara aman menggunakan AES-256-CBC dengan IV acak.
     */
    public static function encryptApiKey(string $plainKey): string {
        $plain = trim($plainKey);
        if ($plain === '') return '';

        $key = self::getSecretKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $encrypted, $key, true);

        return base64_encode($iv . $hmac . $encrypted);
    }

    /**
     * Dekripsi API Key. Mengembalikan string plain text atau empty string jika gagal.
     */
    public static function decryptApiKey(?string $encryptedKey): string {
        if ($encryptedKey === null || trim($encryptedKey) === '') {
            return '';
        }

        $raw = base64_decode($encryptedKey, true);
        if ($raw === false) return '';

        $key = self::getSecretKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $hmacLength = 32;

        if (strlen($raw) < ($ivLength + $hmacLength)) {
            return '';
        }

        $iv = substr($raw, 0, $ivLength);
        $hmac = substr($raw, $ivLength, $hmacLength);
        $ciphertext = substr($raw, $ivLength + $hmacLength);

        $calcHmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        if (!hash_equals($hmac, $calcHmac)) {
            return ''; // Integritas data tidak valid
        }

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return is_string($decrypted) ? trim($decrypted) : '';
    }

    /**
     * Buat representasi disamarkan (masked) dari API Key agar aman ditampilkan ke antarmuka Admin.
     * Contoh: nvapi-••••••••••••••••••••3aB8
     */
    public static function maskApiKey(?string $plainKey): string {
        if ($plainKey === null || trim($plainKey) === '') {
            return '';
        }
        $key = trim($plainKey);
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('•', max(4, $len));
        }

        // Tampilkan 4 prefix karakter (atau hingga tanda hubung pertama jika ada) dan 4 suffix karakter
        $prefix = substr($key, 0, min(6, (int)($len * 0.2)));
        $suffix = substr($key, -4);
        $maskCount = max(6, $len - strlen($prefix) - strlen($suffix));

        return $prefix . str_repeat('•', min(24, $maskCount)) . $suffix;
    }

    /**
     * Ambil seluruh baris konfigurasi provider.
     */
    public static function getAll(): array {
        $db = Database::getConnection();
        if (!$db) return [];

        try {
            $stmt = $db->query("SELECT * FROM ai_configurations ORDER BY id ASC");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as &$r) {
                $plainKey = self::decryptApiKey($r['api_key'] ?? '');
                $r['has_key'] = ($plainKey !== '');
                $r['masked_key'] = $r['has_key'] ? self::maskApiKey($plainKey) : '';
                // Jangan pernah kirim plain key di getAll
                unset($r['api_key']);
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ambil data konfigurasi lengkap satu provider (termasuk plain API key didekripsi server-side).
     */
    public static function findByProvider(string $provider): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        try {
            $stmt = $db->prepare("SELECT * FROM ai_configurations WHERE provider = :provider LIMIT 1");
            $stmt->execute(['provider' => strtolower($provider)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $plainKey = self::decryptApiKey($row['api_key'] ?? '');
            $row['decrypted_api_key'] = $plainKey;
            $row['has_key'] = ($plainKey !== '');
            $row['masked_key'] = $row['has_key'] ? self::maskApiKey($plainKey) : '';

            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ambil konfigurasi provider yang sedang aktif saat ini.
     */
    public static function getActive(): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        try {
            $stmt = $db->query("SELECT * FROM ai_configurations WHERE is_active = 1 LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$row) return null;

            $plainKey = self::decryptApiKey($row['api_key'] ?? '');
            $row['decrypted_api_key'] = $plainKey;
            $row['has_key'] = ($plainKey !== '');
            $row['masked_key'] = $row['has_key'] ? self::maskApiKey($plainKey) : '';

            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Aktifkan satu provider sebagai provider utama chatbot.
     */
    public static function setActive(string $provider): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $target = strtolower(trim($provider));

        try {
            $db->beginTransaction();
            // Nonaktifkan semua provider
            $db->exec("UPDATE ai_configurations SET is_active = 0");
            // Aktifkan provider yang dipilih
            $stmt = $db->prepare("UPDATE ai_configurations SET is_active = 1, updated_at = NOW() WHERE provider = :provider");
            $stmt->execute(['provider' => $target]);
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Simpan / Perbarui konfigurasi provider.
     */
    public static function saveConfig(string $provider, array $data): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $target = strtolower(trim($provider));
        $existing = self::findByProvider($target);

        $fields = [];
        $params = ['provider' => $target];

        if (isset($data['display_name'])) {
            $fields[] = "display_name = :display_name";
            $params['display_name'] = trim((string)$data['display_name']);
        }

        if (isset($data['model'])) {
            $fields[] = "model = :model";
            $params['model'] = trim((string)$data['model']);
        }

        // Jika API Key dikirim baru (tidak kosong)
        if (isset($data['api_key']) && trim((string)$data['api_key']) !== '') {
            $fields[] = "api_key = :api_key";
            $params['api_key'] = self::encryptApiKey(trim((string)$data['api_key']));
        }

        if (array_key_exists('base_url', $data)) {
            $fields[] = "base_url = :base_url";
            $params['base_url'] = !empty($data['base_url']) ? rtrim(trim((string)$data['base_url']), '/') : null;
        }

        if (isset($data['temperature'])) {
            $fields[] = "temperature = :temperature";
            $params['temperature'] = max(0.0, min(2.0, (float)$data['temperature']));
        }

        if (isset($data['max_tokens'])) {
            $fields[] = "max_tokens = :max_tokens";
            $params['max_tokens'] = max(50, min(8192, (int)$data['max_tokens']));
        }

        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = (int)(bool)$data['is_active'];
        }

        try {
            if ($existing) {
                if (empty($fields)) return true;
                $sql = "UPDATE ai_configurations SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE provider = :provider";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insert baru jika belum ada
                $stmt = $db->prepare("
                    INSERT INTO ai_configurations (provider, display_name, model, api_key, base_url, temperature, max_tokens, is_active)
                    VALUES (:provider, :display_name, :model, :api_key, :base_url, :temperature, :max_tokens, :is_active)
                ");
                $stmt->execute([
                    'provider'     => $target,
                    'display_name' => $data['display_name'] ?? ucfirst($target),
                    'model'        => $data['model'] ?? 'default',
                    'api_key'      => !empty($data['api_key']) ? self::encryptApiKey(trim((string)$data['api_key'])) : null,
                    'base_url'     => !empty($data['base_url']) ? rtrim(trim((string)$data['base_url']), '/') : null,
                    'temperature'  => (float)($data['temperature'] ?? 0.40),
                    'max_tokens'   => (int)($data['max_tokens'] ?? 600),
                    'is_active'    => (int)(bool)($data['is_active'] ?? 0),
                ]);
            }

            // Jika is_active diset 1, pastikan provider lain jadi 0
            if (!empty($data['is_active'])) {
                self::setActive($target);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[AIConfiguration::saveConfig] ' . $e->getMessage());
            return false;
        }
    }
}
