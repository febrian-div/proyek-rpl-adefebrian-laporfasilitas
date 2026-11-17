<?php
/**
 * Migration Script: Telegram Integration System
 * Adds telegram_chat_id to users, creates telegram_pairings and telegram_deliveries (Outbox)
 * Non-destructive and idempotent.
 */
require_once __DIR__ . '/bootstrap.php';
use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    die("Database connection failed.\n");
}

echo "======================================================\n";
echo "Starting Migration: Telegram System & Outbox Queue\n";
echo "======================================================\n\n";

try {
    // 1. Add telegram_chat_id to users table if not exists
    $colCheck = $db->query("SHOW COLUMNS FROM `users` LIKE 'telegram_chat_id'")->fetch();
    if (!$colCheck) {
        $db->exec("
            ALTER TABLE `users` 
            ADD COLUMN `telegram_chat_id` VARCHAR(50) NULL DEFAULT NULL AFTER `role`,
            ADD INDEX `idx_users_telegram_chat_id` (`telegram_chat_id`)
        ");
        echo "[1/3] Added `telegram_chat_id` column and index to `users` table.\n";
    } else {
        echo "[1/3] Column `telegram_chat_id` already exists in `users` table. Skipped.\n";
    }

    // 2. Create telegram_pairings table
    $sqlPairings = "
    CREATE TABLE IF NOT EXISTS `telegram_pairings` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `token_hash` VARCHAR(64) NOT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `is_used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_pairings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        INDEX `idx_pairing_lookup` (`token_hash`, `is_used`, `expires_at`),
        INDEX `idx_pairing_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlPairings);
    echo "[2/3] Verified/Created `telegram_pairings` table.\n";

    // 3. Create telegram_deliveries table (Transactional Outbox)
    $sqlDeliveries = "
    CREATE TABLE IF NOT EXISTS `telegram_deliveries` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `notification_id` INT UNSIGNED NULL,
        `user_id` INT UNSIGNED NULL,
        `report_id` INT UNSIGNED NULL,
        `recipient_type` ENUM('admin', 'masyarakat', 'petugas') NOT NULL,
        `chat_id` VARCHAR(50) NOT NULL,
        `event_type` VARCHAR(50) NOT NULL,
        `message_payload` TEXT NOT NULL,
        `parse_mode` VARCHAR(10) NOT NULL DEFAULT 'HTML',
        `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
        `status` ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
        `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
        `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
        `last_attempt_at` TIMESTAMP NULL DEFAULT NULL,
        `next_retry_at` TIMESTAMP NULL DEFAULT NULL,
        `sent_at` TIMESTAMP NULL DEFAULT NULL,
        `error_message` TEXT NULL DEFAULT NULL,
        `worker_id` VARCHAR(64) NULL DEFAULT NULL,
        `locked_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_tel_deliv_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_tel_deliv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_tel_deliv_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL,
        INDEX `idx_tel_deliv_queue` (`status`, `next_retry_at`, `created_at`),
        INDEX `idx_tel_deliv_chat_event` (`chat_id`, `event_type`, `status`),
        INDEX `idx_tel_deliv_report` (`report_id`),
        INDEX `idx_tel_deliv_worker_lock` (`status`, `locked_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlDeliveries);
    echo "[3/3] Verified/Created `telegram_deliveries` table.\n\n";

    echo "======================================================\n";
    echo "Migration completed successfully without errors.\n";
    echo "======================================================\n";
} catch (PDOException $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
