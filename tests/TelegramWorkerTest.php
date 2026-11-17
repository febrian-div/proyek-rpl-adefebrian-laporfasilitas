<?php
/**
 * Automated Test Suite for TelegramWorkerCommand (CLI Outbox Worker)
 * Run via: C:\xampp\php\php.exe tests/TelegramWorkerTest.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Notification;
use App\Services\TelegramService;
use App\Services\NotificationService;
use App\Commands\TelegramWorkerCommand;

class TelegramWorkerTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo " ⚙️ RUNNING TELEGRAM WORKER CLI TEST SUITE\n";
        echo "======================================================\n\n";

        $this->testConcurrentWorkersNoDoubleClaim();
        $this->testWorkerProcessSuccessSent();
        $this->testWorkerProcessTemporaryErrorBackoff();
        $this->testWorkerProcessRateLimit429();
        $this->testWorkerProcessPermanentFailure403();
        $this->testWorkerRecoverExpiredLease();
        $this->testWorkerIgnoresSentAndFailedJobs();
        $this->testWorkerZeroImpactOnWebsiteNotifications();

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

    /**
     * 1. Test Concurrent Workers (Atomic Claiming / Race Condition Prevention)
     */
    private function testConcurrentWorkersNoDoubleClaim(): void {
        echo "[1] Testing Concurrent Workers Atomic Claiming (No Double Claim)...\n";
        $db = Database::getConnection();

        // Insert 2 distinct pending jobs
        $key1 = 'concurrent_test_1_' . time();
        $key2 = 'concurrent_test_2_' . time();

        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES ('chat_c1', 'masyarakat', 'test_event', 'Payload 1', :k1, 'pending', 0, NOW(), NOW()),
                   ('chat_c2', 'masyarakat', 'test_event', 'Payload 2', :k2, 'pending', 0, NOW(), NOW())
        ")->execute(['k1' => $key1, 'k2' => $key2]);

        $workerA = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $workerA->setWorkerId('worker_alpha_' . time());

        $workerB = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $workerB->setWorkerId('worker_beta_' . time());

        // Worker A claims 1 job
        $batchA = $workerA->claimBatch(1);
        // Worker B immediately claims 1 job
        $batchB = $workerB->claimBatch(1);

        $this->assert('Worker A claimed exactly 1 job', count($batchA) === 1);
        $this->assert('Worker B claimed exactly 1 job', count($batchB) === 1);

        $jobAId = $batchA[0]['id'] ?? 0;
        $jobBId = $batchB[0]['id'] ?? 0;

        $this->assert('Worker A and Worker B claimed completely distinct jobs', $jobAId > 0 && $jobBId > 0 && $jobAId !== $jobBId);
        $this->assert('Worker A job has worker_id=alpha', $batchA[0]['worker_id'] === $workerA->getWorkerId());
        $this->assert('Worker B job has worker_id=beta', $batchB[0]['worker_id'] === $workerB->getWorkerId());

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key IN (:k1, :k2)")->execute(['k1' => $key1, 'k2' => $key2]);
    }

    /**
     * 2. Test Worker Success -> Sent status
     */
    private function testWorkerProcessSuccessSent(): void {
        echo "\n[2] Testing Worker Processing Success (HTTP 200 -> Sent)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 200,
                'body' => json_encode(['ok' => true, 'result' => ['message_id' => 9911]])
            ];
        });

        $db = Database::getConnection();
        $key = 'worker_succ_' . time();
        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES ('chat_succ', 'admin', 'test_event', 'Payload Succ', :k, 'pending', 0, NOW(), NOW())
        ")->execute(['k' => $key]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $metrics = $worker->runOnce(1);

        $this->assert('Worker processed 1 job', $metrics['processed'] === 1);
        $this->assert('Worker sent count is 1', $metrics['sent'] === 1);

        $stmt = $db->prepare("SELECT status, sent_at, worker_id, locked_at FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Job status updated to sent', $row['status'] === 'sent');
        $this->assert('sent_at timestamp is populated', !empty($row['sent_at']));
        $this->assert('worker_id and locked_at are released (null)', empty($row['worker_id']) && empty($row['locked_at']));

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
        TelegramService::setMockHttpHandler(null);
    }

    /**
     * 3. Test Worker Temporary Error -> Exponential Backoff
     */
    private function testWorkerProcessTemporaryErrorBackoff(): void {
        echo "\n[3] Testing Worker Temporary Error Backoff (HTTP 500 -> Retry)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 500,
                'body' => json_encode(['ok' => false, 'error_code' => 500, 'description' => 'Internal Server Error'])
            ];
        });

        $db = Database::getConnection();
        $key = 'worker_retry_' . time();
        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES ('chat_retry', 'masyarakat', 'test_event', 'Payload Retry', :k, 'pending', 0, NOW(), NOW())
        ")->execute(['k' => $key]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $metrics = $worker->runOnce(1);

        $this->assert('Worker processed 1 job', $metrics['processed'] === 1);
        $this->assert('Worker retried count is 1', $metrics['retried'] === 1);

        $stmt = $db->prepare("SELECT status, attempts, next_retry_at, error_message FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Job status remains pending for next retry', $row['status'] === 'pending');
        $this->assert('Attempts count is 1', (int)$row['attempts'] === 1);
        $this->assert('next_retry_at is scheduled in future', strtotime($row['next_retry_at']) > time());
        $this->assert('error_message contains sanitized HTTP 500', str_contains($row['error_message'], 'HTTP 500'));

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
        TelegramService::setMockHttpHandler(null);
    }

    /**
     * 4. Test Worker HTTP 429 Rate Limit Handling
     */
    private function testWorkerProcessRateLimit429(): void {
        echo "\n[4] Testing Worker HTTP 429 Rate Limit (Parameters retry_after)...\n";
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 429,
                'body' => json_encode([
                    'ok' => false,
                    'error_code' => 429,
                    'description' => 'Too Many Requests: retry after 12',
                    'parameters' => ['retry_after' => 12]
                ])
            ];
        });

        $db = Database::getConnection();
        $key = 'worker_429_' . time();
        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES ('chat_429', 'admin', 'test_event', 'Payload 429', :k, 'pending', 0, NOW(), NOW())
        ")->execute(['k' => $key]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $metrics = $worker->runOnce(1);

        $this->assert('Job retried due to rate limit', $metrics['retried'] === 1);

        $stmt = $db->prepare("SELECT status, next_retry_at FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Status is pending', $row['status'] === 'pending');
        $diffSeconds = strtotime($row['next_retry_at']) - time();
        $this->assert('next_retry_at set approximately 12-13 seconds in future', $diffSeconds >= 11 && $diffSeconds <= 15);

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
        TelegramService::setMockHttpHandler(null);
    }

    /**
     * 5. Test Worker Permanent Failure (HTTP 403 Bot Blocked)
     */
    private function testWorkerProcessPermanentFailure403(): void {
        echo "\n[5] Testing Worker Permanent Failure (HTTP 403 Bot Blocked -> Failed)...\n";
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

        $db = Database::getConnection();
        $key = 'worker_perm_' . time();
        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES ('chat_blocked', 'masyarakat', 'test_event', 'Payload Blocked', :k, 'pending', 0, NOW(), NOW())
        ")->execute(['k' => $key]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $metrics = $worker->runOnce(1);

        $this->assert('Job marked as failed', $metrics['failed'] === 1);

        $stmt = $db->prepare("SELECT status, next_retry_at, error_message FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Status is permanently failed', $row['status'] === 'failed');
        $this->assert('next_retry_at is null (no retry)', $row['next_retry_at'] === null);

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
        TelegramService::setMockHttpHandler(null);
    }

    /**
     * 6. Test Worker Recover Expired Lease (Crashed Worker Recovery)
     */
    private function testWorkerRecoverExpiredLease(): void {
        echo "\n[6] Testing Worker Expired Lease Recovery (Abandoned Processing Jobs)...\n";
        $db = Database::getConnection();

        $key = 'worker_lease_' . time();
        // Insert a job stuck in 'processing' with locked_at 10 minutes ago
        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, worker_id, locked_at, attempts, next_retry_at, created_at)
            VALUES ('chat_lease', 'admin', 'test_event', 'Payload Lease', :k, 'processing', 'crashed_worker_pid', DATE_SUB(NOW(), INTERVAL 10 MINUTE), 1, NOW(), DATE_SUB(NOW(), INTERVAL 10 MINUTE))
        ")->execute(['k' => $key]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true), 10, 300); // 300s lease
        $recoveredCount = $worker->recoverExpiredLeases();

        $this->assert('Worker recovered at least 1 expired lease', $recoveredCount >= 1);

        $stmt = $db->prepare("SELECT status, worker_id, locked_at, error_message FROM telegram_deliveries WHERE idempotency_key = :k");
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assert('Stuck job recovered to status=pending', $row['status'] === 'pending');
        $this->assert('worker_id and locked_at released to null', empty($row['worker_id']) && empty($row['locked_at']));
        $this->assert('error_message records lease recovery', str_contains($row['error_message'], 'Lease expired and recovered'));

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
    }

    /**
     * 7. Test Worker Ignores Sent and Failed Jobs
     */
    private function testWorkerIgnoresSentAndFailedJobs(): void {
        echo "\n[7] Testing Worker Ignores Already Sent and Failed Jobs...\n";
        $db = Database::getConnection();

        $keySent = 'worker_ignore_sent_' . time();
        $keyFailed = 'worker_ignore_failed_' . time();

        $db->prepare("
            INSERT INTO telegram_deliveries (chat_id, recipient_type, event_type, message_payload, idempotency_key, status, sent_at, created_at)
            VALUES ('chat_ign', 'admin', 'test_event', 'Already Sent', :ks, 'sent', NOW(), NOW()),
                   ('chat_ign', 'admin', 'test_event', 'Already Failed', :kf, 'failed', NULL, NOW())
        ")->execute(['ks' => $keySent, 'kf' => $keyFailed]);

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $claimed = $worker->claimBatch(10);

        $claimedIds = array_column($claimed, 'idempotency_key');

        $this->assert('Sent job is not claimed', !in_array($keySent, $claimedIds, true));
        $this->assert('Failed job is not claimed', !in_array($keyFailed, $claimedIds, true));

        // Clean up
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key IN (:ks, :kf)")->execute(['ks' => $keySent, 'kf' => $keyFailed]);
    }

    /**
     * 8. Test Zero Impact on Website Notifications
     */
    private function testWorkerZeroImpactOnWebsiteNotifications(): void {
        echo "\n[8] Testing Worker Failure Has Zero Impact on Website Notifications...\n";
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $userId = (int)$warga['id'];

        // Create an in-app website notification
        $notifId = Notification::create([
            'user_id' => $userId,
            'title'   => 'Notifikasi Website Penting',
            'message' => 'Pesan pengujian integrasi website',
        ]);

        $this->assert('Website notification created successfully', $notifId > 0);

        // Queue a Telegram delivery associated with this notification
        $db = Database::getConnection();
        $key = 'zero_impact_' . time();
        $db->prepare("
            INSERT INTO telegram_deliveries (notification_id, user_id, chat_id, recipient_type, event_type, message_payload, idempotency_key, status, attempts, next_retry_at, created_at)
            VALUES (:nid, :uid, 'chat_fail', 'masyarakat', 'test_event', 'Payload', :k, 'pending', 0, NOW(), NOW())
        ")->execute(['nid' => $notifId, 'uid' => $userId, 'k' => $key]);

        // Simulate Telegram failure
        TelegramService::setMockHttpHandler(function($method, $payload) {
            return [
                'code' => 500,
                'body' => json_encode(['ok' => false, 'error_code' => 500, 'description' => 'Telegram Down'])
            ];
        });

        $worker = new TelegramWorkerCommand(new TelegramService('fake', 'bot', '123', true));
        $worker->runOnce(1);

        // Verify website notification is completely intact
        $inAppCheck = Notification::getUnreadByUser($userId);
        $found = false;
        foreach ($inAppCheck as $n) {
            if ((int)$n['id'] === $notifId) {
                $found = true;
                break;
            }
        }

        $this->assert('Website notification remains 100% intact despite Telegram failure', $found === true);

        // Clean up
        $db->exec("DELETE FROM notifications WHERE id = {$notifId}");
        $db->prepare("DELETE FROM telegram_deliveries WHERE idempotency_key = :k")->execute(['k' => $key]);
        TelegramService::setMockHttpHandler(null);
    }
}

// Run the worker test suite
$test = new TelegramWorkerTest();
$test->run();
