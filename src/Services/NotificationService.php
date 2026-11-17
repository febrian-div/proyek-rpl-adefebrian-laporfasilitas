<?php
namespace App\Services;

use App\Config\Database;
use App\Models\Notification;
use App\Models\User;
use App\Models\Report;
use PDO;

class NotificationService {
    private TelegramService $telegramService;

    public function __construct(?TelegramService $telegramService = null) {
        $this->telegramService = $telegramService ?? new TelegramService();
    }

    public function getTelegramService(): TelegramService {
        return $this->telegramService;
    }

    // =========================================================================
    // 1. TRANSACTIONAL OUTBOX DISPATCHER
    // =========================================================================

    /**
     * Enqueue a Telegram delivery job into the transactional outbox table
     * Returns true if queued or false if already exists (idempotency key match)
     */
    public function enqueueTelegramDelivery(
        string $chatId,
        string $recipientType,
        string $eventType,
        string $messagePayload,
        string $idempotencyKey,
        ?int $userId = null,
        ?int $reportId = null,
        ?int $notificationId = null,
        string $parseMode = 'HTML'
    ): bool {
        if (!$this->telegramService->isEnabled()) {
            return false;
        }

        $chatId = trim($chatId);
        if (empty($chatId)) {
            return false;
        }

        $db = Database::getConnection();
        if (!$db) return false;

        // Check if exact idempotency key already exists to prevent duplicate jobs
        $checkStmt = $db->prepare("SELECT id, status FROM telegram_deliveries WHERE idempotency_key = :key LIMIT 1");
        $checkStmt->execute(['key' => $idempotencyKey]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return false; // Already queued or sent
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO telegram_deliveries (
                    notification_id, user_id, report_id, recipient_type, chat_id,
                    event_type, message_payload, parse_mode, idempotency_key,
                    status, attempts, max_attempts, next_retry_at, created_at
                ) VALUES (
                    :notification_id, :user_id, :report_id, :recipient_type, :chat_id,
                    :event_type, :message_payload, :parse_mode, :idempotency_key,
                    'pending', 0, 3, NOW(), NOW()
                )
            ");

            return $stmt->execute([
                'notification_id' => $notificationId,
                'user_id'         => $userId,
                'report_id'       => $reportId,
                'recipient_type'  => $recipientType,
                'chat_id'         => $chatId,
                'event_type'      => $eventType,
                'message_payload' => $messagePayload,
                'parse_mode'      => $parseMode,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (\PDOException $e) {
            // Handle duplicate key error code 1062 gracefully
            if ($e->errorInfo[1] == 1062) {
                return false;
            }
            error_log("Failed to enqueue telegram delivery: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a single queued outbox job by ID (used by CLI Worker and test suite)
     */
    public function processOutboxJob(int $deliveryId, ?string $workerId = 'worker_default'): array {
        $db = Database::getConnection();
        if (!$db) {
            return ['success' => false, 'error' => 'Database connection unavailable'];
        }

        // 1. Claim job atomically using worker leasing
        $claimStmt = $db->prepare("
            UPDATE telegram_deliveries 
            SET status = 'processing',
                worker_id = :worker_id,
                locked_at = NOW(),
                attempts = attempts + 1,
                last_attempt_at = NOW()
            WHERE id = :id 
              AND status = 'pending'
              AND (next_retry_at IS NULL OR next_retry_at <= NOW())
              AND attempts < max_attempts
        ");
        $claimStmt->execute(['id' => $deliveryId, 'worker_id' => $workerId]);

        if ($claimStmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Job cannot be claimed (already processing, sent, or max attempts reached)'];
        }

        // 2. Fetch the claimed job payload
        $stmt = $db->prepare("SELECT * FROM telegram_deliveries WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $deliveryId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            return ['success' => false, 'error' => 'Job not found'];
        }

        // 3. Dispatch to Telegram API via TelegramService
        $sendResult = $this->telegramService->sendMessage(
            $job['chat_id'],
            $job['message_payload'],
            $job['parse_mode'] ?? 'HTML'
        );

        // 4. Update status based on result
        if ($sendResult['success']) {
            $upStmt = $db->prepare("
                UPDATE telegram_deliveries 
                SET status = 'sent',
                    sent_at = NOW(),
                    error_message = NULL,
                    worker_id = NULL,
                    locked_at = NULL
                WHERE id = :id
            ");
            $upStmt->execute(['id' => $deliveryId]);
            return ['success' => true, 'message_id' => $sendResult['message_id']];
        }

        // Handle failure
        $isPermanent = $sendResult['is_blocked'] || $sendResult['is_not_found'] || ($sendResult['http_code'] >= 400 && $sendResult['http_code'] < 429);
        $retryAfter = $sendResult['retry_after'];
        $currentAttempts = (int)$job['attempts'] + 1;
        $maxAttempts = (int)$job['max_attempts'];

        if ($isPermanent || $currentAttempts >= $maxAttempts) {
            // Permanent failure or max attempts reached
            $failStmt = $db->prepare("
                UPDATE telegram_deliveries 
                SET status = 'failed',
                    error_message = :err,
                    worker_id = NULL,
                    locked_at = NULL,
                    next_retry_at = NULL
                WHERE id = :id
            ");
            $failStmt->execute(['id' => $deliveryId, 'err' => $sendResult['error']]);
        } else {
            // Calculate next retry delay
            if ($retryAfter !== null && $retryAfter > 0) {
                $delaySeconds = $retryAfter + 1;
            } else {
                // Exponential backoff: attempt 1: 60s, attempt 2: 300s, attempt 3: 900s
                $delaySeconds = match ($currentAttempts) {
                    1 => 60,
                    2 => 300,
                    default => 900,
                };
            }

            $retryStmt = $db->prepare("
                UPDATE telegram_deliveries 
                SET status = 'pending',
                    error_message = :err,
                    worker_id = NULL,
                    locked_at = NULL,
                    next_retry_at = DATE_ADD(NOW(), INTERVAL :delay SECOND)
                WHERE id = :id
            ");
            $retryStmt->execute([
                'id'    => $deliveryId,
                'err'   => $sendResult['error'],
                'delay' => $delaySeconds,
            ]);
        }

        return ['success' => false, 'error' => $sendResult['error']];
    }

    // =========================================================================
    // 2. PAIRING TOKEN MANAGEMENT (SECURE HASH, 15 MIN TTL, ANTI-HIJACKING)
    // =========================================================================

    /**
     * Generate an unguessable pairing token for user (Single-Use, 15 min TTL, Hash Stored)
     */
    public function generatePairingToken(int $userId): array {
        $db = Database::getConnection();
        if (!$db) return ['success' => false, 'error' => 'Database connection error'];

        // Invalidate any older unused tokens for this user
        $db->prepare("UPDATE telegram_pairings SET is_used = 1 WHERE user_id = :uid AND is_used = 0")
           ->execute(['uid' => $userId]);

        // Generate 32-character random hex token (128-bit entropy)
        $rawToken = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes TTL

        $stmt = $db->prepare("
            INSERT INTO telegram_pairings (user_id, token_hash, expires_at, is_used, created_at)
            VALUES (:user_id, :token_hash, :expires_at, 0, NOW())
        ");
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        $deepLink = $this->telegramService->getDeepLink($rawToken);

        return [
            'success'      => true,
            'raw_token'    => $rawToken,
            'deep_link'    => $deepLink,
            'expires_at'   => $expiresAt,
            'bot_username' => $this->telegramService->getBotUsername(),
        ];
    }

    /**
     * Verify token from bot /start and bind Telegram Chat ID
     * Enforces Anti-Hijacking: NEVER automatically transfer if Chat ID is linked to another user!
     */
    public function verifyAndLinkTelegram(string $rawToken, string $chatId): array {
        $chatId = trim($chatId);
        $rawToken = trim($rawToken);

        if (empty($chatId) || empty($rawToken)) {
            return ['success' => false, 'error' => 'invalid_input', 'message' => 'Parameter tidak valid.'];
        }

        $db = Database::getConnection();
        if (!$db) return ['success' => false, 'error' => 'db_error', 'message' => 'Kesalahan koneksi database.'];

        $tokenHash = hash('sha256', $rawToken);

        $db->beginTransaction();

        try {
            // 1. Atomically consume the token first (Row lock & single consumer guarantee)
            $consumeStmt = $db->prepare("
                UPDATE telegram_pairings 
                SET is_used = 1 
                WHERE token_hash = :hash AND is_used = 0 AND expires_at > NOW()
            ");
            $consumeStmt->execute(['hash' => $tokenHash]);

            if ($consumeStmt->rowCount() === 0) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error'   => 'expired_or_invalid',
                    'message' => 'Tautan pairing tidak valid atau telah kedaluwarsa (berlaku 15 menit). Silakan buat tautan baru melalui menu Pengaturan Akun di website.',
                ];
            }

            // 2. Fetch the target user ID for this consumed token
            $fetchStmt = $db->prepare("SELECT user_id FROM telegram_pairings WHERE token_hash = :hash LIMIT 1");
            $fetchStmt->execute(['hash' => $tokenHash]);
            $targetUserId = (int)$fetchStmt->fetchColumn();

            // 3. Strict Anti-Hijacking Check: Is this Chat ID already linked to ANY OTHER account?
            $checkChat = $db->prepare("SELECT id, name FROM users WHERE telegram_chat_id = :chat_id AND id != :uid LIMIT 1");
            $checkChat->execute(['chat_id' => $chatId, 'uid' => $targetUserId]);
            $existingOwner = $checkChat->fetch(PDO::FETCH_ASSOC);

            if ($existingOwner) {
                // Token remains consumed to prevent replay, but user is NOT linked
                $db->commit();
                return [
                    'success' => false,
                    'error'   => 'chat_id_already_linked',
                    'message' => 'PERINGATAN KEAMANAN: Akun Telegram ini sudah terhubung dengan akun LaporFasilitas lain. Satu akun Telegram tidak dapat digunakan oleh dua akun berbeda. Silakan putuskan koneksi di akun lama terlebih dahulu.',
                ];
            }

            // 4. Link Telegram Chat ID to the target user
            $db->prepare("UPDATE users SET telegram_chat_id = :chat_id WHERE id = :uid")
               ->execute(['chat_id' => $chatId, 'uid' => $targetUserId]);

            $db->commit();

            return [
                'success' => true,
                'user_id' => $targetUserId,
                'message' => 'Akun LaporFasilitas Anda berhasil terhubung! Anda akan menerima notifikasi status laporan secara otomatis di sini.',
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Atomic pairing error: " . $e->getMessage());
            return ['success' => false, 'error' => 'server_error', 'message' => 'Terjadi kesalahan sistem saat menghubungkan akun.'];
        }
    }

    /**
     * Disconnect Telegram from user account
     */
    public function disconnectTelegram(int $userId): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = :uid");
        $stmt->execute(['uid' => $userId]);

        // Cancel all pending tokens for this user
        $db->prepare("UPDATE telegram_pairings SET is_used = 1 WHERE user_id = :uid AND is_used = 0")
           ->execute(['uid' => $userId]);

        return true;
    }

    // =========================================================================
    // 3. APPLICATION EVENT TRIGGERS
    // =========================================================================

    /**
     * Trigger 1: User Registered (Masyarakat Baru)
     */
    public function notifyUserRegistered(array $user): void {
        $userId = (int)$user['id'];
        $userName = $user['name'] ?? 'Warga';

        // 1. In-app website notification for the citizen
        $notifId = Notification::create([
            'user_id' => $userId,
            'title'   => 'Selamat Datang di LaporFasilitas',
            'message' => "Halo {$userName}, akun Anda berhasil dibuat. Anda dapat mulai melaporkan kerusakan fasilitas umum di sekitar Anda.",
        ]);

        // 2. Telegram notification to Admin Channel (if configured)
        $adminChatId = $this->telegramService->getAdminChatId();
        if ($adminChatId) {
            $msg = "<b>Pengguna Baru Terdaftar</b>\n\n";
            $msg .= "Nama: " . TelegramService::escape($user['name'] ?? '') . "\n";
            $msg .= "Email: " . TelegramService::escape($user['email'] ?? '') . "\n";
            $msg .= "Role: " . TelegramService::escape($user['role'] ?? 'masyarakat') . "\n";
            $msg .= "Waktu: " . date('d M Y, H:i') . " WIB\n";

            $idempotencyKey = hash('sha256', "tel:user_registered:uid_{$userId}:admin_{$adminChatId}");
            $this->enqueueTelegramDelivery(
                $adminChatId,
                'admin',
                'user_registered',
                $msg,
                $idempotencyKey,
                $userId,
                null,
                $notifId
            );
        }
    }

    /**
     * Trigger 2: Report Created by Citizen
     */
    public function notifyReportCreated(array $report, array $reporter): void {
        $reportId = (int)$report['id'];
        $formattedId = '#LPF-' . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT);
        $title = $report['title'] ?? 'Laporan Fasilitas';
        $category = $report['facility_category'] ?? 'Fasilitas Umum';
        $address = $report['address'] ?? '-';
        $reporterUserId = (int)$reporter['id'];

        // 1. In-app notification for the citizen reporter
        $citizenNotifId = Notification::create([
            'user_id' => $reporterUserId,
            'title'   => 'Laporan Berhasil Terkirim',
            'message' => "Laporan Anda \"{$title}\" ({$formattedId}) berhasil tersimpan dan sedang menunggu verifikasi oleh admin.",
        ]);

        // 2. Telegram to Citizen (if telegram_chat_id is connected)
        $citizenChatId = $this->getUserTelegramChatId($reporterUserId);
        if ($citizenChatId) {
            $msg = "<b>Laporan Berhasil Terkirim!</b>\n\n";
            $msg .= "Laporan Anda telah berhasil masuk ke sistem LaporFasilitas.\n\n";
            $msg .= "<b>ID Laporan:</b> {$formattedId}\n";
            $msg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
            $msg .= "<b>Kategori:</b> " . TelegramService::escape($category) . "\n";
            $msg .= "<b>Lokasi:</b> " . TelegramService::escape($address) . "\n";
            $msg .= "<b>Status:</b> Menunggu Verifikasi\n";
            $msg .= "<b>Waktu:</b> " . date('d M Y, H:i') . " WIB\n\n";
            $msg .= "Kami akan memberi tahu Anda jika ada perkembangan status pada laporan ini.";

            $idempotencyKey = hash('sha256', "tel:report_created_citizen:rep_{$reportId}:user_{$reporterUserId}");
            $this->enqueueTelegramDelivery(
                $citizenChatId,
                'masyarakat',
                'report_created_citizen',
                $msg,
                $idempotencyKey,
                $reporterUserId,
                $reportId,
                $citizenNotifId
            );
        }

        // 3. In-app notification for all Admins
        $admins = User::all('admin');
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => (int)$admin['id'],
                'title'   => 'Laporan Baru Menunggu Verifikasi',
                'message' => "Laporan baru masuk ({$formattedId}): \"{$title}\" di {$address}.",
            ]);
        }

        // 4. Telegram alert to Admin Channel (if configured)
        $adminChatId = $this->telegramService->getAdminChatId();
        if ($adminChatId) {
            $msg = "<b>Laporan Baru Menunggu Verifikasi</b>\n\n";
            $msg .= "<b>ID Laporan:</b> {$formattedId}\n";
            $msg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
            $msg .= "<b>Kategori:</b> " . TelegramService::escape($category) . "\n";
            $msg .= "<b>Pelapor:</b> " . TelegramService::escape($reporter['name'] ?? 'Warga') . "\n";
            $msg .= "<b>Lokasi:</b> " . TelegramService::escape($address) . "\n";
            $msg .= "<b>Waktu:</b> " . date('d M Y, H:i') . " WIB\n";

            $idempotencyKey = hash('sha256', "tel:report_created_admin:rep_{$reportId}:admin_{$adminChatId}");
            $this->enqueueTelegramDelivery(
                $adminChatId,
                'admin',
                'report_created_admin',
                $msg,
                $idempotencyKey,
                null,
                $reportId,
                null
            );
        }
    }

    /**
     * Trigger 3: Report Status Changed (Verifikasi, Penolakan dengan Alasan, Ditugaskan, Sedang Diperbaiki, Selesai)
     */
    public function notifyReportStatusChanged(
        int $reportId,
        string $newStatus,
        ?string $note = null,
        ?int $officerUserId = null
    ): void {
        $report = Report::findById($reportId);
        if (!$report) return;

        $formattedId = '#LPF-' . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT);
        $title = $report['title'] ?? 'Laporan Fasilitas';
        $citizenUserId = (int)$report['user_id'];
        $citizenChatId = $this->getUserTelegramChatId($citizenUserId);

        // Customize message and action by status
        switch ($newStatus) {
            case 'Ditolak':
                $reasonText = !empty($note) ? $note : 'Laporan belum memenuhi kriteria penanganan fasilitas publik.';
                $webMsg = "Laporan Anda \"{$title}\" ({$formattedId}) tidak dapat dilanjutkan. Alasan: {$reasonText}";
                $notifId = Notification::create([
                    'user_id' => $citizenUserId,
                    'title'   => 'Laporan Tidak Dapat Dilanjutkan',
                    'message' => $webMsg,
                ]);

                if ($citizenChatId) {
                    $tgMsg = "<b>Laporan Tidak Dapat Dilanjutkan</b>\n\n";
                    $tgMsg .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgMsg .= "<b>Status:</b> Ditolak\n";
                    $tgMsg .= "<b>Alasan Penolakan:</b> " . TelegramService::escape($reasonText) . "\n";
                    $tgMsg .= "<b>Waktu:</b> " . date('d M Y, H:i') . " WIB\n\n";
                    $tgMsg .= "Silakan periksa detail penolakan pada akun LaporFasilitas Anda.";

                    $key = hash('sha256', "tel:status_ditolak:rep_{$reportId}:user_{$citizenUserId}");
                    $this->enqueueTelegramDelivery($citizenChatId, 'masyarakat', 'status_ditolak', $tgMsg, $key, $citizenUserId, $reportId, $notifId);
                }
                break;

            case 'Diverifikasi':
                $webMsg = "Laporan Anda \"{$title}\" ({$formattedId}) telah diverifikasi oleh admin dan akan segera ditugaskan ke petugas lapangan.";
                $notifId = Notification::create([
                    'user_id' => $citizenUserId,
                    'title'   => 'Laporan Telah Diverifikasi',
                    'message' => $webMsg,
                ]);

                if ($citizenChatId) {
                    $tgMsg = "<b>Laporan Telah Diverifikasi</b>\n\n";
                    $tgMsg .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgMsg .= "<b>Status:</b> Diverifikasi\n";
                    $tgMsg .= "<b>Waktu:</b> " . date('d M Y, H:i') . " WIB\n\n";
                    $tgMsg .= "Laporan Anda telah diverifikasi oleh admin dan menunggu penugasan petugas lapangan.";

                    $key = hash('sha256', "tel:status_diverifikasi:rep_{$reportId}:user_{$citizenUserId}");
                    $this->enqueueTelegramDelivery($citizenChatId, 'masyarakat', 'status_diverifikasi', $tgMsg, $key, $citizenUserId, $reportId, $notifId);
                }
                break;

            case 'Ditugaskan':
                $webMsg = "Laporan Anda \"{$title}\" ({$formattedId}) telah ditugaskan kepada petugas lapangan untuk persiapan penanganan.";
                $notifId = Notification::create([
                    'user_id' => $citizenUserId,
                    'title'   => 'Laporan Telah Ditugaskan',
                    'message' => $webMsg,
                ]);

                if ($citizenChatId) {
                    $tgMsg = "<b>Laporan Telah Ditugaskan</b>\n\n";
                    $tgMsg .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgMsg .= "<b>Status:</b> Ditugaskan ke Petugas\n";
                    $tgMsg .= "<b>Waktu:</b> " . date('d M Y, H:i') . " WIB\n\n";
                    $tgMsg .= "Petugas lapangan telah menerima tugas untuk menindaklanjuti laporan Anda.";

                    $key = hash('sha256', "tel:status_ditugaskan_warga:rep_{$reportId}:user_{$citizenUserId}");
                    $this->enqueueTelegramDelivery($citizenChatId, 'masyarakat', 'status_ditugaskan_warga', $tgMsg, $key, $citizenUserId, $reportId, $notifId);
                }

                // Notify Assigned Officer
                if ($officerUserId) {
                    $offNotifId = Notification::create([
                        'user_id' => $officerUserId,
                        'title'   => 'Tugas Perbaikan Baru',
                        'message' => "Anda mendapat tugas perbaikan baru ({$formattedId}): \"{$title}\" di " . ($report['address'] ?? ''),
                    ]);

                    $officerChatId = $this->getUserTelegramChatId($officerUserId);
                    if ($officerChatId) {
                        $offMsg = "<b>Tugas Perbaikan Baru Ditugaskan</b>\n\n";
                        $offMsg .= "<b>ID Tugas:</b> {$formattedId}\n";
                        $offMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                        $offMsg .= "<b>Kategori:</b> " . TelegramService::escape($report['facility_category'] ?? '') . "\n";
                        $offMsg .= "<b>Lokasi:</b> " . TelegramService::escape($report['address'] ?? '') . "\n";
                        $offMsg .= "<b>Prioritas:</b> " . TelegramService::escape($report['priority'] ?? 'Sedang') . "\n";
                        $offMsg .= "<b>Waktu Penugasan:</b> " . date('d M Y, H:i') . " WIB\n\n";
                        $offMsg .= "Silakan buka lembar kerja petugas di website untuk memulai penanganan.";

                        $key = hash('sha256', "tel:status_ditugaskan_petugas:rep_{$reportId}:officer_{$officerUserId}");
                        $this->enqueueTelegramDelivery($officerChatId, 'petugas', 'status_ditugaskan_petugas', $offMsg, $key, $officerUserId, $reportId, $offNotifId);
                    }
                }
                break;

            case 'Sedang Diperbaiki':
                $webMsg = "Petugas telah berada di lokasi dan sedang melakukan perbaikan untuk laporan \"{$title}\" ({$formattedId}).";
                $notifId = Notification::create([
                    'user_id' => $citizenUserId,
                    'title'   => 'Perbaikan Sedang Berlangsung',
                    'message' => $webMsg,
                ]);

                if ($citizenChatId) {
                    $tgMsg = "<b>Perbaikan Sedang Berlangsung</b>\n\n";
                    $tgMsg .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgMsg .= "<b>Status:</b> Sedang Diperbaiki\n";
                    $tgMsg .= "<b>Waktu Mulai:</b> " . date('d M Y, H:i') . " WIB\n\n";
                    $tgMsg .= "Petugas lapangan sedang melakukan perbaikan fasilitas di lokasi.";

                    $key = hash('sha256', "tel:status_sedang_diperbaiki:rep_{$reportId}:user_{$citizenUserId}");
                    $this->enqueueTelegramDelivery($citizenChatId, 'masyarakat', 'status_sedang_diperbaiki', $tgMsg, $key, $citizenUserId, $reportId, $notifId);
                }
                break;

            case 'Selesai':
                $webMsg = "Fasilitas pada laporan Anda \"{$title}\" ({$formattedId}) telah selesai diperbaiki oleh petugas. Terima kasih atas partisipasi Anda!";
                $notifId = Notification::create([
                    'user_id' => $citizenUserId,
                    'title'   => 'Laporan Telah Selesai Ditangani',
                    'message' => $webMsg,
                ]);

                if ($citizenChatId) {
                    $tgMsg = "<b>Laporan Telah Selesai Ditangani</b>\n\n";
                    $tgMsg .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgMsg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgMsg .= "<b>Status:</b> Selesai\n";
                    $tgMsg .= "<b>Waktu Selesai:</b> " . date('d M Y, H:i') . " WIB\n\n";
                    $tgMsg .= "Terima kasih telah berpartisipasi melaporkan fasilitas umum yang membutuhkan perbaikan.";

                    $key = hash('sha256', "tel:status_selesai_warga:rep_{$reportId}:user_{$citizenUserId}");
                    $this->enqueueTelegramDelivery($citizenChatId, 'masyarakat', 'status_selesai_warga', $tgMsg, $key, $citizenUserId, $reportId, $notifId);
                }

                // Notify Admin Channel that repair is completed
                $adminChatId = $this->telegramService->getAdminChatId();
                if ($adminChatId) {
                    $tgAdmin = "<b>Laporan Selesai Diperbaiki</b>\n\n";
                    $tgAdmin .= "<b>ID Laporan:</b> {$formattedId}\n";
                    $tgAdmin .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
                    $tgAdmin .= "<b>Petugas:</b> " . TelegramService::escape($report['officer_name'] ?? 'Petugas') . "\n";
                    $tgAdmin .= "<b>Waktu Selesai:</b> " . date('d M Y, H:i') . " WIB\n";

                    $key = hash('sha256', "tel:status_selesai_admin:rep_{$reportId}:admin_{$adminChatId}");
                    $this->enqueueTelegramDelivery($adminChatId, 'admin', 'status_selesai_admin', $tgAdmin, $key, null, $reportId, null);
                }
                break;
        }
    }

    /**
     * Trigger 4: Task Reassigned to another officer
     */
    public function notifyTaskReassigned(int $reportId, int $oldOfficerUserId, int $newOfficerUserId): void {
        $report = Report::findById($reportId);
        if (!$report) return;

        $formattedId = '#LPF-' . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT);
        $title = $report['title'] ?? 'Laporan Fasilitas';

        // 1. Notify Old Officer (In-App + Telegram)
        Notification::create([
            'user_id' => $oldOfficerUserId,
            'title'   => 'Tugas Dialihkan',
            'message' => "Tugas perbaikan ({$formattedId}) \"{$title}\" telah dialihkan kepada petugas lain oleh admin.",
        ]);

        $oldChatId = $this->getUserTelegramChatId($oldOfficerUserId);
        if ($oldChatId) {
            $msg = "<b>Tugas Perbaikan Dialihkan</b>\n\n";
            $msg .= "Tugas perbaikan ({$formattedId}) \"{$title}\" telah dialihkan ke petugas lain. Anda tidak lagi bertugas untuk laporan ini.";
            $key = hash('sha256', "tel:task_reassigned_old:rep_{$reportId}:off_{$oldOfficerUserId}_to_{$newOfficerUserId}");
            $this->enqueueTelegramDelivery($oldChatId, 'petugas', 'task_reassigned_old', $msg, $key, $oldOfficerUserId, $reportId);
        }

        // 2. Notify New Officer (In-App + Telegram)
        Notification::create([
            'user_id' => $newOfficerUserId,
            'title'   => 'Tugas Baru (Pengalihan)',
            'message' => "Anda mendapat tugas perbaikan ({$formattedId}): \"{$title}\".",
        ]);

        $newChatId = $this->getUserTelegramChatId($newOfficerUserId);
        if ($newChatId) {
            $msg = "<b>Tugas Perbaikan Baru (Pengalihan)</b>\n\n";
            $msg .= "<b>ID Tugas:</b> {$formattedId}\n";
            $msg .= "<b>Judul:</b> " . TelegramService::escape($title) . "\n";
            $msg .= "<b>Lokasi:</b> " . TelegramService::escape($report['address'] ?? '') . "\n";
            $msg .= "Tugas ini telah ditugaskan kepada Anda. Silakan periksa detail di website.";
            $key = hash('sha256', "tel:task_reassigned_new:rep_{$reportId}:off_{$newOfficerUserId}");
            $this->enqueueTelegramDelivery($newChatId, 'petugas', 'task_reassigned_new', $msg, $key, $newOfficerUserId, $reportId);
        }
    }

    /**
     * Helper to retrieve a user's Telegram Chat ID directly from database
     */
    public function getUserTelegramChatId(int $userId): ?string {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT telegram_chat_id FROM users WHERE id = :uid LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $chatId = $stmt->fetchColumn();

        return !empty($chatId) ? (string)$chatId : null;
    }
}
