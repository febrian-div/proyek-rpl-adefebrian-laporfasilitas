<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\TelegramService;

/**
 * TelegramPairingController — Pengaturan dan pairing akun Telegram.
 * Hanya untuk pengguna yang sudah login. Setiap pengguna hanya dapat
 * memasangkan akunnya sendiri. Tidak ada pengiriman pesan Telegram sungguhan
 * jika konfigurasi belum diisi.
 */
class TelegramPairingController {

    /**
     * Halaman pengaturan notifikasi & pairing Telegram
     */
    public function showSettings(): void {
        AuthMiddleware::requireAuth();

        $user = AuthMiddleware::user();
        $telegram = new TelegramService();
        $notif = new NotificationService($telegram);

        $isConnected = !empty($user['telegram_chat_id'] ?? '');
        $botUsername = $telegram->getBotUsername();
        $deepLink = '';

        // Hasilkan tautan pairing baru hanya saat belum terhubung dan Telegram aktif
        if (!$isConnected && $telegram->isEnabled()) {
            $result = $notif->generatePairingToken($user['id']);
            if ($result['success']) {
                $deepLink = $result['deep_link'];
            }
        }

        view('auth.settings', [
            'title' => 'Pengaturan Notifikasi - LaporFasilitas',
            'user' => $user,
            'telegramConnected' => $isConnected,
            'botUsername' => $botUsername,
            'telegramEnabled' => $telegram->isEnabled(),
            'deepLink' => $deepLink,
        ]);
    }

    /**
     * Generate token pairing untuk menghubungkan akun Telegram
     */
    public function generatePairing(): void {
        AuthMiddleware::requireAuth();

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Token CSRF tidak valid.'], 403);
        }

        $user = AuthMiddleware::user();
        $userId = (int)$user['id'];

        $telegram = new TelegramService();
        if (!$telegram->isEnabled()) {
            Response::json(['success' => false, 'message' => 'Integrasi Telegram belum diaktifkan di server.'], 400);
        }

        $notif = new NotificationService($telegram);
        $result = $notif->generatePairingToken($userId);

        if ($result['success']) {
            Response::json([
                'success' => true,
                'deep_link' => $result['deep_link'],
                'expires_at' => $result['expires_at'],
                'bot_username' => $result['bot_username'],
            ]);
        } else {
            Response::json(['success' => false, 'message' => $result['message']], 500);
        }
    }

    /**
     * Putuskan koneksi Telegram dari akun pengguna
     */
    public function disconnect(): void {
        AuthMiddleware::requireAuth();

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Token CSRF tidak valid.'], 403);
        }

        $user = AuthMiddleware::user();
        $userId = (int)$user['id'];

        $telegram = new TelegramService();
        $notif = new NotificationService($telegram);

        $notif->disconnectTelegram($userId);

        Response::json(['success' => true, 'message' => 'Koneksi Telegram telah diputus.']);
    }
}
