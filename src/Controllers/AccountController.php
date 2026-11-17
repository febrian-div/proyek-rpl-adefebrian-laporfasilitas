<?php
namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Security;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\TelegramService;
use PDO;

class AccountController {
    private NotificationService $notificationService;
    private TelegramService $telegramService;

    public function __construct(
        ?NotificationService $notificationService = null,
        ?TelegramService $telegramService = null
    ) {
        $this->telegramService = $telegramService ?? new TelegramService();
        $this->notificationService = $notificationService ?? new NotificationService($this->telegramService);
    }

    /**
     * Show Account Notification Settings Page
     * GET /pengaturan/notifikasi
     */
    public function notifications(): void {
        AuthMiddleware::requireAuth();
        $sessionUser = AuthMiddleware::user();
        $userId = (int)$sessionUser['id'];

        $db = Database::getConnection();
        if (!$db) {
            http_response_code(500);
            echo "Database connection unavailable.";
            exit;
        }

        // Fetch fresh user record
        $stmt = $db->prepare("SELECT id, name, email, role, telegram_chat_id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if there is an active pairing session
        $pairingInfo = $_SESSION['active_pairing'] ?? null;
        if ($pairingInfo && strtotime($pairingInfo['expires_at'] ?? '2000-01-01') <= time()) {
            unset($_SESSION['active_pairing']);
            $pairingInfo = null;
        }

        // Count rate limit usage in the last 1 hour
        $rlStmt = $db->prepare("
            SELECT COUNT(*) FROM telegram_pairings 
            WHERE user_id = :uid AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $rlStmt->execute(['uid' => $userId]);
        $attemptsLastHour = (int)$rlStmt->fetchColumn();
        $rateLimitExceeded = $attemptsLastHour >= 5;

        view('account.notifications', [
            'title'             => 'Pengaturan Notifikasi - LaporFasilitas',
            'user'              => $user,
            'isTelegramEnabled' => $this->telegramService->isEnabled(),
            'botUsername'       => $this->telegramService->getBotUsername(),
            'pairingInfo'       => $pairingInfo,
            'rateLimitExceeded' => $rateLimitExceeded,
            'attemptsLastHour'  => $attemptsLastHour,
        ]);
    }

    /**
     * Request a new pairing token
     * POST /pengaturan/telegram/connect
     */
    public function connectTelegram(): void {
        AuthMiddleware::requireAuth();
        $sessionUser = AuthMiddleware::user();
        $userId = (int)$sessionUser['id'];

        // 1. Verify CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($csrfToken)) {
            Response::setFlash('error', 'Validasi sesi kedaluwarsa (CSRF tidak valid). Silakan coba lagi.');
            Response::redirect('/pengaturan/notifikasi');
            return;
        }

        // 2. Check Rate Limit (max 5 requests per hour)
        $db = Database::getConnection();
        $rlStmt = $db->prepare("
            SELECT COUNT(*) FROM telegram_pairings 
            WHERE user_id = :uid AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $rlStmt->execute(['uid' => $userId]);
        $attemptsLastHour = (int)$rlStmt->fetchColumn();

        if ($attemptsLastHour >= 5) {
            Response::setFlash('error', 'Batas permintaan token tercapai (maksimal 5 kali per jam). Harap tunggu beberapa saat.');
            Response::redirect('/pengaturan/notifikasi');
            return;
        }

        // 3. Generate token
        $tokenInfo = $this->notificationService->generatePairingToken($userId);

        if (!$tokenInfo['success']) {
            Response::setFlash('error', 'Gagal membuat kode pairing. Silakan coba kembali.');
            Response::redirect('/pengaturan/notifikasi');
            return;
        }

        $_SESSION['active_pairing'] = $tokenInfo;
        Response::setFlash('success', 'Kode pairing berhasil dibuat! Silakan buka bot Telegram dan tekan Start untuk menghubungkan akun.');
        Response::redirect('/pengaturan/notifikasi');
    }

    /**
     * Disconnect Telegram connection
     * POST /pengaturan/telegram/disconnect
     */
    public function disconnectTelegram(): void {
        AuthMiddleware::requireAuth();
        $sessionUser = AuthMiddleware::user();
        $userId = (int)$sessionUser['id'];

        // 1. Verify CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($csrfToken)) {
            Response::setFlash('error', 'Validasi sesi kedaluwarsa (CSRF tidak valid). Silakan coba lagi.');
            Response::redirect('/pengaturan/notifikasi');
            return;
        }

        // 2. Disconnect
        $this->notificationService->disconnectTelegram($userId);
        unset($_SESSION['active_pairing']);

        Response::setFlash('success', 'Koneksi Telegram berhasil diputuskan. Notifikasi website Anda tetap aktif normal.');
        Response::redirect('/pengaturan/notifikasi');
    }
}
