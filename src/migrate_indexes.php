<?php
require_once __DIR__ . '/bootstrap.php';
use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    die("Database connection failed.\n");
}

$queries = [
    "ALTER TABLE reports ADD INDEX idx_reports_status (status);",
    "ALTER TABLE reports ADD INDEX idx_reports_user_id (user_id);",
    "ALTER TABLE assignments ADD INDEX idx_assignments_officer_id (officer_id);",
    "ALTER TABLE notifications ADD INDEX idx_notifications_user_id_is_read (user_id, is_read);"
];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        echo "Successfully executed: $sql\n";
    } catch (PDOException $e) {
        // 1061 is Duplicate key name (index already exists)
        if ($e->errorInfo[1] == 1061) {
            echo "Index already exists, skipping: $sql\n";
        } else {
            echo "Error executing $sql: " . $e->getMessage() . "\n";
        }
    }
}
echo "Index migration completed!\n";
