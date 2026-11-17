<?php
namespace App\Commands;

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Config\Database;
use App\Services\TelegramService;
use App\Services\NotificationService;
use PDO;

class TelegramWorkerCommand {
    private TelegramService $telegramService;
    private string $workerId;
    private int $batchSize;
    private int $leaseTimeoutSeconds;
    private bool $shouldStop = false;

    public function __construct(
        ?TelegramService $telegramService = null,
        int $batchSize = 10,
        int $leaseTimeoutSeconds = 300 // 5 minutes default lease
    ) {
        $this->telegramService = $telegramService ?? new TelegramService();
        $this->batchSize = max(1, $batchSize);
        $this->leaseTimeoutSeconds = max(30, $leaseTimeoutSeconds);
        $this->workerId = gethostname() . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }

    public function getWorkerId(): string {
        return $this->workerId;
    }

    public function setWorkerId(string $id): void {
        $this->workerId = $id;
    }

    public function stop(): void {
        $this->shouldStop = true;
    }

    /**
     * Recover jobs that were left in 'processing' status due to worker crash or network timeout
     * Ensures any job with expired lease returns to 'pending' for retry
     */
    public function recoverExpiredLeases(): int {
        $db = Database::getConnection();
        if (!$db) return 0;

        $stmt = $db->prepare("
            UPDATE telegram_deliveries
            SET status = 'pending',
                worker_id = NULL,
                locked_at = NULL,
                next_retry_at = NOW(),
                error_message = CONCAT('Lease expired and recovered: ', COALESCE(error_message, 'Worker timed out'))
            WHERE status = 'processing'
              AND locked_at IS NOT NULL
              AND locked_at <= DATE_SUB(NOW(), INTERVAL :leaseSeconds SECOND)
        ");

        $stmt->bindValue(':leaseSeconds', $this->leaseTimeoutSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Atomically claim a batch of pending jobs safe for MariaDB 10.4
     * Prevents race conditions and guarantees no two workers claim the same job
     */
    public function claimBatch(int $limit): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $limit = max(1, $limit);

        // Atomic claiming query supported in MariaDB 10.4
        $claimSql = "
            UPDATE telegram_deliveries
            SET status = 'processing',
                worker_id = :worker_id,
                locked_at = NOW(),
                attempts = attempts + 1,
                last_attempt_at = NOW()
            WHERE status = 'pending'
              AND (next_retry_at IS NULL OR next_retry_at <= NOW())
              AND attempts < max_attempts
            ORDER BY created_at ASC
            LIMIT {$limit}
        ";

        $claimStmt = $db->prepare($claimSql);
        $claimStmt->execute(['worker_id' => $this->workerId]);

        if ($claimStmt->rowCount() === 0) {
            return [];
        }

        // Fetch jobs claimed exclusively by this worker
        $fetchStmt = $db->prepare("
            SELECT * FROM telegram_deliveries
            WHERE worker_id = :worker_id
              AND status = 'processing'
            ORDER BY created_at ASC
        ");
        $fetchStmt->execute(['worker_id' => $this->workerId]);

        return $fetchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Process a single claimed job
     */
    public function processJob(array $job): array {
        $db = Database::getConnection();
        if (!$db) {
            return ['success' => false, 'error' => 'Database connection unavailable'];
        }

        $id = (int)$job['id'];
        $chatId = $job['chat_id'];
        $message = $job['message_payload'];
        $parseMode = $job['parse_mode'] ?? 'HTML';
        $currentAttempts = (int)$job['attempts'];
        $maxAttempts = (int)$job['max_attempts'];

        // Send through TelegramService
        $sendResult = $this->telegramService->sendMessage($chatId, $message, $parseMode);

        if ($sendResult['success']) {
            // Success: update to sent
            $upStmt = $db->prepare("
                UPDATE telegram_deliveries
                SET status = 'sent',
                    sent_at = NOW(),
                    error_message = NULL,
                    worker_id = NULL,
                    locked_at = NULL
                WHERE id = :id
            ");
            $upStmt->execute(['id' => $id]);

            return [
                'success'    => true,
                'status'     => 'sent',
                'message_id' => $sendResult['message_id'],
            ];
        }

        // Handle failure
        $isPermanent = $sendResult['is_blocked'] 
                    || $sendResult['is_not_found'] 
                    || ($sendResult['http_code'] >= 400 && $sendResult['http_code'] < 429);
        $retryAfter = $sendResult['retry_after'];
        $errorMsg = $sendResult['error'] ?? 'Unknown dispatch error';

        if ($isPermanent || $currentAttempts >= $maxAttempts) {
            // Permanent failure or retry exhausted
            $finalErr = $isPermanent ? $errorMsg : "Max attempts ({$maxAttempts}) reached: {$errorMsg}";
            $failStmt = $db->prepare("
                UPDATE telegram_deliveries
                SET status = 'failed',
                    error_message = :err,
                    worker_id = NULL,
                    locked_at = NULL,
                    next_retry_at = NULL
                WHERE id = :id
            ");
            $failStmt->execute(['id' => $id, 'err' => $finalErr]);

            return [
                'success' => false,
                'status'  => 'failed',
                'error'   => $finalErr,
            ];
        }

        // Calculate next retry delay
        if ($retryAfter !== null && $retryAfter > 0) {
            // HTTP 429 Rate Limit
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
            'id'    => $id,
            'err'   => $errorMsg,
            'delay' => $delaySeconds,
        ]);

        return [
            'success'       => false,
            'status'        => 'pending',
            'next_retry_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'error'         => $errorMsg,
        ];
    }

    /**
     * Run a single pass: recovers expired leases, claims ready jobs, and processes them
     * Returns execution metrics
     */
    public function runOnce(?int $limit = null): array {
        $limit = $limit ?? $this->batchSize;

        // 1. Recover any expired leases first
        $recovered = $this->recoverExpiredLeases();

        // 2. Claim pending jobs atomically
        $jobs = $this->claimBatch($limit);

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $retried = 0;

        foreach ($jobs as $job) {
            if ($this->shouldStop) {
                break;
            }

            $res = $this->processJob($job);
            $processed++;

            if ($res['success']) {
                $sent++;
            } elseif (($res['status'] ?? '') === 'failed') {
                $failed++;
            } else {
                $retried++;
            }
        }

        return [
            'recovered_leases' => $recovered,
            'claimed'          => count($jobs),
            'processed'        => $processed,
            'sent'             => $sent,
            'failed'           => $failed,
            'retried'          => $retried,
        ];
    }

    /**
     * Run worker in daemon mode (continuous loop)
     */
    public function runDaemon(int $sleepSeconds = 2, int $maxCycles = 0): void {
        echo "======================================================\n";
        echo "Telegram Outbox Worker started (Daemon Mode)\n";
        echo "Worker ID: {$this->workerId}\n";
        echo "Batch Size: {$this->batchSize} | Lease Timeout: {$this->leaseTimeoutSeconds}s\n";
        echo "Press Ctrl+C to terminate gracefully.\n";
        echo "======================================================\n\n";

        // Register signal handler if supported
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() {
                echo "\nReceived shutdown signal. Stopping worker gracefully...\n";
                $this->stop();
            });
            pcntl_signal(SIGTERM, function() {
                echo "\nReceived termination signal. Stopping worker gracefully...\n";
                $this->stop();
            });
        }

        $cycles = 0;

        while (!$this->shouldStop) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $metrics = $this->runOnce();

            if ($metrics['processed'] > 0 || $metrics['recovered_leases'] > 0) {
                $time = date('Y-m-d H:i:s');
                echo "[{$time}] Processed: {$metrics['processed']} (Sent: {$metrics['sent']}, Retried: {$metrics['retried']}, Failed: {$metrics['failed']}) | Recovered Leases: {$metrics['recovered_leases']}\n";
            }

            $cycles++;
            if ($maxCycles > 0 && $cycles >= $maxCycles) {
                echo "Max cycles ({$maxCycles}) reached. Stopping.\n";
                break;
            }

            // Sleep when queue is empty or after batch
            sleep($sleepSeconds);
        }

        echo "Worker {$this->workerId} has shut down cleanly.\n";
    }
}

// CLI Entry Point
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $options = getopt('', ['once', 'daemon', 'batch-size:', 'lease-timeout:', 'help']);

    if (isset($options['help'])) {
        echo "Usage: php TelegramWorkerCommand.php [options]\n";
        echo "Options:\n";
        echo "  --once             Process ready jobs in queue once and exit (default)\n";
        echo "  --daemon           Run continuously as a background daemon process\n";
        echo "  --batch-size=N     Number of jobs to claim per batch (default: 10)\n";
        echo "  --lease-timeout=S  Seconds before locked job lease expires (default: 300)\n";
        echo "  --help             Show this help screen\n";
        exit(0);
    }

    $batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : 10;
    $leaseTimeout = isset($options['lease-timeout']) ? (int)$options['lease-timeout'] : 300;

    $worker = new TelegramWorkerCommand(null, $batchSize, $leaseTimeout);

    if (isset($options['daemon'])) {
        $worker->runDaemon(2);
    } else {
        $metrics = $worker->runOnce();
        echo "Worker executed single pass:\n";
        echo "- Recovered Leases: {$metrics['recovered_leases']}\n";
        echo "- Claimed Jobs:    {$metrics['claimed']}\n";
        echo "- Sent:            {$metrics['sent']}\n";
        echo "- Retried:         {$metrics['retried']}\n";
        echo "- Failed:          {$metrics['failed']}\n";
    }
}
