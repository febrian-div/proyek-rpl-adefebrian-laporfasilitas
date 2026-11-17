<?php
/**
 * Automated Test Suite for NotificationService, TelegramService, and Transactional Outbox
 * Run via: C:\xampp\php\php.exe tests/NotificationAndTelegramTest.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Report;
use App\Models\Notification;
use App\Services\TelegramService;
use App\Services\NotificationService;

class NotificationAndTelegramTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo " 📡 RUNNING NOTIFICATION & TELEGRAM SERVICE TEST SUITE\n";
        echo "======================================================\n\n";

        $this->testTelegramServiceHtmlEscape();
        $this->testTelegramServiceDisabledState();
        $this->testTelegramServiceMockSuccess();
        $this->testTelegramServiceMockRateLimit429();
        $this->testTelegramServiceMockBlocked403();
        $this->testTelegramServiceTokenMasking();

        $this->testOutboxEnqueueAndIdempotency();
        $this->testOutboxJobProcessingSuccess();
        $this->testOutboxJobProcessingRetryBackoff();
        $this->testOutboxJobProcessingPermanentFailure();

        $this->testPairingTokenGenerationSecurity();
        $this->testPairingVerificationSuccess();
        $this->testPairingRejectionUsedToken();
        $this->testPairingRejectionExpiredToken();
        $this->testPairingAntiHijackingProtection();
        $this->testDisconnectTelegram();

        $this->testNotifyUserRegistered();
        $this->testNotifyReportCreatedCitizenAndAdmin();
        $this->testNotifyReportStatusChangedRejectionReason();

        echo "\n======================================================\n";
        echo " 📊 TEST RESULTS: {$this->passed} PASSED, {$this->failed} FAILED\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(string $testName, bool $condition, string $detail = ''): void {
        if ($condition) {
            $this->passed++;
            echo "  ✅ [PASS] {$testName}\n";
        } else {
            $this->failed++;
            echo "  ❌ [FAIL] {$testName} - Detail: {$detail}\n";
        }
    }

    // =========================================================================
    // 1. TELEGRAM SERVICE UNIT TESTS
    // =========================================================================

    private function testTelegramServiceHtmlEscape(): void {
        echo "[1] Testing Telegram HTML Sanitization...\n";
        $raw = '<script>alert("hack & test");</script>';
        $escaped = TelegramService::escape($raw);
        $this->assert('HTML tags are properly escaped', !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));
        $this->assert('Ampersand is properly escaped', str_contains($escaped, '&amp;'));
    }

    private function testTelegramServiceDisabledState(): void {
        echo "\n[2] Testing TelegramService Disabled State...\n";
        $service = new TelegramService('fake_token', 'test_bot', '12345', false);
        $res = $service->sendMessage('999999', 'Hello');
        $this->assert('Returns success=false when disabled', $res['success'] === false);
        $this->assert('Contains disabled error message', str_contains($res['error'], 'disabled'));
    }

    private function testTelegramServiceMockSuccess(): void {
        echo "\n[3] Testing TelegramService Mock HTTP 200 Success...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 200,
                'body' => json_encode([
                    'ok' => true,
                    'result' => [
                        'message_id' => 7777,
                        'chat' => ['id' => $payload['chat_id']]
                    ]
                ])
            ];
        });

        $service = new TelegramService('fake_token', 'test_bot', '12345', true);
        $res = $service->sendMessage('123456', '<b>Test Message</b>');

        $this->assert('Mock HTTP 200 returns success=true', $res['success'] === true);
        $this->assert('Message ID is parsed accurately (7777)', $res['message_id'] === 7777);

        // Reset mock
        TelegramService::setMockHttpHandler(null);
    }

    private function testTelegramServiceMockRateLimit429(): void {
        echo "\n[4] Testing TelegramService HTTP 429 Rate Limiting...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 429,
                'body' => json_encode([
                    'ok' => false,
                    'error_code' => 429,
                    'description' => 'Too Many Requests: retry after 8',
                    'parameters' => ['retry_after' => 8]
                ])
            ];
        });

        $service = new TelegramService('fake_token', 'test_bot', '12345', true);
        $res = $service->sendMessage('123456', 'Test Rate Limit');

        $this->assert('Returns success=false on 429', $res['success'] === false);
        $this->assert('Parses retry_after parameter (8 seconds)', $res['retry_after'] === 8);

        TelegramService::setMockHttpHandler(null);
    }

    private function testTelegramServiceMockBlocked403(): void {
        echo "\n[5] Testing TelegramService HTTP 403 Forbidden (Bot Blocked)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 403,
                'body' => json_encode([
                    'ok' => false,
                    'error_code' => 403,
                    'description' => 'Forbidden: bot was blocked by the user'
                ])
            ];
        });

        $service = new TelegramService('fake_token', 'test_bot', '12345', true);
        $res = $service->sendMessage('123456', 'Test Blocked');

        $this->assert('Detects is_blocked=true on 403', $res['is_blocked'] === true);

        TelegramService::setMockHttpHandler(null);
    }

    private function testTelegramServiceTokenMasking(): void {
        echo "\n[6] Testing Bot Token Scrubbing from Error Logs...\n";
        $secretToken = '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz123456789';
        $service = new TelegramService($secretToken, 'test_bot', '12345', true);

        TelegramService::setMockHttpHandler(function($method, $payload) use ($secretToken) {
            return [
                'code' => 0,
                'body' => '',
                'curl_error' => "Failed connect to https://api.telegram.org/bot{$secretToken}/sendMessage"
            ];
        });

        $res = $service->sendMessage('123456', 'Test Token Masking');
        $this->assert('Secret bot token is masked in error message', !str_contains($res['error'], $secretToken));
        $this->assert('Protected token placeholder is present', str_contains($res['error'], '[PROTECTED_TOKEN]'));

        TelegramService::setMockHttpHandler(null);
    }

    // =========================================================================
    // 2. TRANSACTIONAL OUTBOX & NOTIFICATION SERVICE TESTS
    // =========================================================================

    private function testOutboxEnqueueAndIdempotency(): void {
        echo "\n[7] Testing Transactional Outbox Enqueue & Idempotency...\n";
        $telegramService = new TelegramService('fake_token', 'test_bot', '12345', true);
        $notifService = new NotificationService($telegramService);

        $testKey = 'test_idempotency_' . time() . '_' . mt_rand(1000, 9999);

        // 1. First enqueue should succeed
        $queued1 = $notifService->enqueueTelegramDelivery(
            '987654321',
            'masyarakat',
            'test_event',
            'Message payload 1',
            $testKey
        );
        $this->assert('First enqueue with unique key succeeds', $queued1 === true);

        // 2. Second enqueue with identical key MUST be rejected (Idempotency)
        $queued2 = $notifService->enqueueTelegramDelivery(
            '987654321',
            'masyarakat',
            'test_event',
            'Message payload 2 duplicate',
            $testKey
        );
        $this->assert('Duplicate enqueue with identical key is rejected (Idempotent)', $queued2 === false);

        // Clean up test outbox record
        $db = Database::getConnection();
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $testKey]);
    }

    private function testOutboxJobProcessingSuccess(): void {
        echo "\n[8] Testing Outbox Job Processing with Mock HTTP 200 (Sent)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 200,
                'body' => json_encode(['ok' => true, 'result' => ['message_id' => 8888]])
            ];
        });

        $telegramService = new TelegramService('fake_token', 'test_bot', '12345', true);
        $notifService = new NotificationService($telegramService);

        $testKey = 'test_success_' . time() . '_' . mt_rand(1000, 9999);
        $notifService->enqueueTelegramDelivery('111222', 'masyarakat', 'test_success', 'Payload', $testKey);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, status FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $testKey]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Job is initially status=pending', $job && $job['status'] === 'pending');

        // Process job
        $res = $notifService->processOutboxJob((int)$job['id'], 'worker_test_1');
        $this->assert('Worker process returns success=true', $res['success'] === true);

        // Check updated job status in DB
        $stmt->execute(['k' => $testKey]);
        $updated = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assert('Job status updated to sent', $updated['status'] === 'sent');

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $testKey]);
        TelegramService::setMockHttpHandler(null);
    }

    private function testOutboxJobProcessingRetryBackoff(): void {
        echo "\n[9] Testing Outbox Job Processing with Retry Backoff on Temporary Error...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 503,
                'body' => json_encode(['ok' => false, 'error_code' => 503, 'description' => 'Service Temporarily Unavailable'])
            ];
        });

        $telegramService = new TelegramService('fake_token', 'test_bot', '12345', true);
        $notifService = new NotificationService($telegramService);

        $testKey = 'test_retry_' . time() . '_' . mt_rand(1000, 9999);
        $notifService->enqueueTelegramDelivery('111222', 'masyarakat', 'test_retry', 'Payload', $testKey);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $testKey]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Process job (fails with 503)
        $res = $notifService->processOutboxJob((int)$job['id'], 'worker_test_2');
        $this->assert('Worker process returns success=false on 503', $res['success'] === false);

        $stmt->execute(['k' => $testKey]);
        $updated = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Job status remains pending for retry', $updated['status'] === 'pending');
        $this->assert('Attempts incremented to 1', (int)$updated['attempts'] === 1);
        $this->assert('Next retry timestamp is set in future', !empty($updated['next_retry_at']));

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $testKey]);
        TelegramService::setMockHttpHandler(null);
    }

    private function testOutboxJobProcessingPermanentFailure(): void {
        echo "\n[10] Testing Outbox Job Permanent Failure (403 Forbidden)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 403,
                'body' => json_encode(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'])
            ];
        });

        $telegramService = new TelegramService('fake_token', 'test_bot', '12345', true);
        $notifService = new NotificationService($telegramService);

        $testKey = 'test_perm_fail_' . time() . '_' . mt_rand(1000, 9999);
        $notifService->enqueueTelegramDelivery('111222', 'masyarakat', 'test_fail', 'Payload', $testKey);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $testKey]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Process job
        $notifService->processOutboxJob((int)$job['id'], 'worker_test_3');

        $stmt = $db->prepare("SELECT status, next_retry_at, error_message FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $testKey]);
        $updated = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Permanent error marked as failed immediately', $updated['status'] === 'failed');
        $this->assert('Next retry is null on permanent failure', $updated['next_retry_at'] === null);

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $testKey]);
        TelegramService::setMockHttpHandler(null);
    }

    // =========================================================================
    // 3. PAIRING TOKEN MANAGEMENT & ANTI-HIJACKING TESTS
    // =========================================================================

    private function testPairingTokenGenerationSecurity(): void {
        echo "\n[11] Testing Pairing Token Generation Security...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $res = $notifService->generatePairingToken($userId);

        $this->assert('Token generated successfully', $res['success'] === true);
        $this->assert('Raw token is 32-character hex (128-bit)', strlen($res['raw_token']) === 32);
        $this->assert('Deep link format is valid', str_contains($res['deep_link'], 't.me/lapor_bot?start='));

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM telegram_pairings WHERE user_id = :uid AND is_used = 0");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Token is stored in database as SHA-256 hash', $row && $row['token_hash'] === hash('sha256', $res['raw_token']));
        $this->assert('Token is not stored in plaintext', $row && $row['token_hash'] !== $res['raw_token']);

        // Clean up
        $db->prepare("DELETE FROM telegram_pairings WHERE user_id = :uid")->execute(['uid' => $userId]);
    }

    private function testPairingVerificationSuccess(): void {
        echo "\n[12] Testing Pairing Verification Success...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $tokenInfo = $notifService->generatePairingToken($userId);

        $chatId = 'test_chat_' . time();
        $res = $notifService->verifyAndLinkTelegram($tokenInfo['raw_token'], $chatId);

        $this->assert('Pairing verification returns success=true', $res['success'] === true);
        $this->assert('User ID matches paired citizen', (int)$res['user_id'] === $userId);

        // Verify users table updated
        $updatedUser = User::findById($userId);
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT telegram_chat_id FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $savedChatId = $stmt->fetchColumn();

        $this->assert('users.telegram_chat_id is updated to chat ID', $savedChatId === $chatId);

        // Clean up: disconnect
        $notifService->disconnectTelegram($userId);
    }

    private function testPairingRejectionUsedToken(): void {
        echo "\n[13] Testing Rejection of Already Used Pairing Token...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $tokenInfo = $notifService->generatePairingToken($userId);

        // Use token once
        $notifService->verifyAndLinkTelegram($tokenInfo['raw_token'], 'chat_used_1');

        // Attempt to use identical token again
        $secondAttempt = $notifService->verifyAndLinkTelegram($tokenInfo['raw_token'], 'chat_used_2');
        $this->assert('Used token is rejected on second attempt', $secondAttempt['success'] === false);
        $this->assert('Error code indicates expired or invalid', $secondAttempt['error'] === 'expired_or_invalid');

        // Clean up
        $notifService->disconnectTelegram($userId);
    }

    private function testPairingRejectionExpiredToken(): void {
        echo "\n[14] Testing Rejection of Expired Pairing Token (TTL)...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $rawToken = 'expired_token_' . time();
        $hash = hash('sha256', $rawToken);

        $db = Database::getConnection();
        $db->prepare("
            INSERT INTO telegram_pairings (user_id, token_hash, expires_at, is_used, created_at)
            VALUES (:uid, :hash, DATE_SUB(NOW(), INTERVAL 5 MINUTE), 0, DATE_SUB(NOW(), INTERVAL 20 MINUTE))
        ")->execute(['uid' => $userId, 'hash' => $hash]);

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $res = $notifService->verifyAndLinkTelegram($rawToken, 'chat_exp_1');

        $this->assert('Expired pairing token is rejected', $res['success'] === false);

        // Clean up
        $db->prepare("DELETE FROM telegram_pairings WHERE token_hash = :h")->execute(['h' => $hash]);
    }

    private function testPairingAntiHijackingProtection(): void {
        echo "\n[15] Testing Anti-Hijacking (Refusal to Silently Transfer Existing Chat ID)...\n";
        $db = Database::getConnection();

        // 1. Setup User A (warga@laporfasilitas.id, ID: 4) bound to Chat ID 'chat_victim_999'
        $wargaA = User::findByEmail('warga@laporfasilitas.id');
        $userAId = (int)$wargaA['id'];
        $db->prepare("UPDATE users SET telegram_chat_id = 'chat_victim_999' WHERE id = :id")->execute(['id' => $userAId]);

        // 2. User B (budi@laporfasilitas.id, ID: 5) attempts to pair with Chat ID 'chat_victim_999'
        $wargaB = User::findByEmail('budi@laporfasilitas.id');
        $userBId = (int)$wargaB['id'];

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $tokenInfoB = $notifService->generatePairingToken($userBId);

        // Attempt pairing User B using User A's Chat ID
        $hijackAttempt = $notifService->verifyAndLinkTelegram($tokenInfoB['raw_token'], 'chat_victim_999');

        $this->assert('Anti-Hijacking: Pairing is strictly REJECTED', $hijackAttempt['success'] === false);
        $this->assert('Error code is chat_id_already_linked', $hijackAttempt['error'] === 'chat_id_already_linked');

        // 3. Verify User A's Chat ID is completely untouched
        $checkA = $db->prepare("SELECT telegram_chat_id FROM users WHERE id = :id");
        $checkA->execute(['id' => $userAId]);
        $this->assert('User A chat ID was NOT disconnected or transferred', $checkA->fetchColumn() === 'chat_victim_999');

        // 4. Verify User B was NOT bound to User A's chat ID
        $checkB = $db->prepare("SELECT telegram_chat_id FROM users WHERE id = :id");
        $checkB->execute(['id' => $userBId]);
        $this->assert('User B chat ID remains null', empty($checkB->fetchColumn()));

        // Clean up
        $db->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = :id")->execute(['id' => $userAId]);
        $db->prepare("DELETE FROM telegram_pairings WHERE user_id IN (:a, :b)")->execute(['a' => $userAId, 'b' => $userBId]);
    }

    private function testDisconnectTelegram(): void {
        echo "\n[16] Testing Disconnect Telegram Action...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $db = Database::getConnection();
        $db->prepare("UPDATE users SET telegram_chat_id = 'chat_to_remove' WHERE id = :id")->execute(['id' => $userId]);

        $notifService = new NotificationService(new TelegramService('fake_token', 'lapor_bot', '12345', true));
        $disconnected = $notifService->disconnectTelegram($userId);

        $this->assert('disconnectTelegram returns true', $disconnected === true);

        $stmt = $db->prepare("SELECT telegram_chat_id FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $this->assert('users.telegram_chat_id is null after disconnect', empty($stmt->fetchColumn()));
    }

    // =========================================================================
    // 4. BUSINESS EVENT TRIGGERS & REJECTION REASON TESTS
    // =========================================================================

    private function testNotifyUserRegistered(): void {
        echo "\n[17] Testing notifyUserRegistered Event...\n";
        $db = Database::getConnection();

        // Create a real temporary test citizen to satisfy foreign keys
        $tempUserId = User::create([
            'name'     => 'Warga Uji Notif',
            'email'    => 'wargatest_' . time() . '@test.com',
            'password' => 'secret123',
            'role'     => 'masyarakat',
        ]);

        $notifService = new NotificationService(new TelegramService('fake_token', 'test_bot', '12345_admin', true));

        $mockUser = ['id' => $tempUserId, 'name' => 'Warga Uji Notif', 'email' => 'wargatest@test.com', 'role' => 'masyarakat'];
        $notifService->notifyUserRegistered($mockUser);

        // Check in-app notification created
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $tempUserId]);
        $inApp = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('In-app welcome notification created for citizen', $inApp && str_contains($inApp['title'], 'Selamat Datang'));

        // Check outbox delivery queued for admin
        $stmtDeliv = $db->prepare("SELECT * FROM telegram_deliveries WHERE event_type = 'user_registered' AND user_id = :uid LIMIT 1");
        $stmtDeliv->execute(['uid' => $tempUserId]);
        $outbox = $stmtDeliv->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Outbox delivery job queued for admin', $outbox && $outbox['recipient_type'] === 'admin');

        // Clean up temporary user (will cascade delete notifications and outbox deliveries)
        User::delete($tempUserId);
    }

    private function testNotifyReportCreatedCitizenAndAdmin(): void {
        echo "\n[18] Testing notifyReportCreated Event...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        // Use existing report #1
        $report = Report::findById(1);
        $reportId = (int)$report['id'];

        $db = Database::getConnection();
        // Temporarily connect citizen to a mock chat ID
        $db->prepare("UPDATE users SET telegram_chat_id = 'citizen_chat_888' WHERE id = :id")->execute(['id' => $userId]);

        $notifService = new NotificationService(new TelegramService('fake_token', 'test_bot', 'admin_chat_999', true));

        $mockReport = [
            'id' => $reportId,
            'title' => $report['title'],
            'facility_category' => $report['facility_category'] ?? 'Jalan',
            'address' => $report['address']
        ];
        $reporter = ['id' => $userId, 'name' => 'Warga Penguji'];

        $notifService->notifyReportCreated($mockReport, $reporter);

        // 1. Citizen in-app confirmation
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND title = 'Laporan Berhasil Terkirim' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $citNotif = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assert('In-app confirmation created for citizen', $citNotif !== false);

        // 2. Citizen Telegram outbox queued
        $stmtCitTg = $db->prepare("SELECT * FROM telegram_deliveries WHERE report_id = :rid AND recipient_type = 'masyarakat' ORDER BY id DESC LIMIT 1");
        $stmtCitTg->execute(['rid' => $reportId]);
        $citTg = $stmtCitTg->fetch(\PDO::FETCH_ASSOC);
        $this->assert('Telegram outbox queued for citizen', $citTg && $citTg['chat_id'] === 'citizen_chat_888');

        // 3. Admin Telegram outbox queued
        $stmtAdmTg = $db->prepare("SELECT * FROM telegram_deliveries WHERE report_id = :rid AND recipient_type = 'admin' ORDER BY id DESC LIMIT 1");
        $stmtAdmTg->execute(['rid' => $reportId]);
        $admTg = $stmtAdmTg->fetch(\PDO::FETCH_ASSOC);
        $this->assert('Telegram outbox queued for admin channel', $admTg && $admTg['chat_id'] === 'admin_chat_999');

        // Clean up
        $db->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = :id")->execute(['id' => $userId]);
        if ($citNotif) {
            $db->exec("DELETE FROM notifications WHERE id = {$citNotif['id']}");
        }
        $db->exec("DELETE FROM notifications WHERE title = 'Laporan Baru Menunggu Verifikasi'");
        $db->exec("DELETE FROM telegram_deliveries WHERE report_id = {$reportId}");
    }

    private function testNotifyReportStatusChangedRejectionReason(): void {
        echo "\n[19] Testing notifyReportStatusChanged with Rejection Reason...\n";
        // Use existing Report #1 (owned by citizen user_id = 4)
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        $db = Database::getConnection();
        $db->prepare("UPDATE users SET telegram_chat_id = 'citizen_chat_reject' WHERE id = :id")->execute(['id' => $userId]);

        $notifService = new NotificationService(new TelegramService('fake_token', 'test_bot', 'admin_chat_999', true));

        $customReason = 'Foto bukti buram dan lokasi bukan fasilitas umum daerah DKI.';
        $notifService->notifyReportStatusChanged(1, 'Ditolak', $customReason);

        // 1. Verify in-app notification includes rejection reason
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND title = 'Laporan Tidak Dapat Dilanjutkan' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $inApp = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('In-app notification created for rejected report', $inApp !== false);
        $this->assert('In-app message includes the exact rejection reason', $inApp && str_contains($inApp['message'], $customReason));

        // 2. Verify Telegram outbox payload includes rejection reason
        $stmtTg = $db->prepare("SELECT * FROM telegram_deliveries WHERE report_id = 1 AND event_type = 'status_ditolak' ORDER BY id DESC LIMIT 1");
        $stmtTg->execute();
        $tgJob = $stmtTg->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Telegram outbox queued with event_type status_ditolak', $tgJob !== false);
        $this->assert('Telegram payload includes the exact rejection reason', $tgJob && str_contains($tgJob['message_payload'], $customReason));

        // Clean up
        $db->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = :id")->execute(['id' => $userId]);
        if ($inApp) {
            $db->exec("DELETE FROM notifications WHERE id = {$inApp['id']}");
        }
        if ($tgJob) {
            $db->exec("DELETE FROM telegram_deliveries WHERE id = {$tgJob['id']}");
        }
    }
}

// Run the test suite
$test = new NotificationAndTelegramTest();
$test->run();
