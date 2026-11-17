<?php
require_once __DIR__ . '/bootstrap.php';
use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    die("Database connection failed.\n");
}

$sql = "
CREATE TABLE IF NOT EXISTS `report_timelines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT UNSIGNED NOT NULL,
  `status` varchar(100) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "report_timelines table created successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
