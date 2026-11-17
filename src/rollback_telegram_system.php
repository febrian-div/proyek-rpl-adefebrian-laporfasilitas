<?php
/**
 * Rollback Script: Telegram Integration System
 * NOTE: DO NOT RUN THIS SCRIPT UNLESS EXPLICITLY INSTRUCTED.
 * Safely removes telegram_deliveries, telegram_pairings, and users.telegram_chat_id.
 */
require_once __DIR__ . '/bootstrap.php';
use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    die("Database connection failed.\n");
}

echo "Starting Rollback of Telegram System...\n";

try {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("DROP TABLE IF EXISTS `telegram_deliveries`;");
    echo "- Dropped `telegram_deliveries`\n";

    $db->exec("DROP TABLE IF EXISTS `telegram_pairings`;");
    echo "- Dropped `telegram_pairings`\n";

    $colCheck = $db->query("SHOW COLUMNS FROM `users` LIKE 'telegram_chat_id'")->fetch();
    if ($colCheck) {
        $db->exec("ALTER TABLE `users` DROP COLUMN `telegram_chat_id`;");
        echo "- Dropped column `telegram_chat_id` from `users`\n";
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Rollback completed.\n";
} catch (PDOException $e) {
    echo "Rollback error: " . $e->getMessage() . "\n";
    exit(1);
}
