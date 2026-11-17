<?php
/**
 * Database Backup Script for LaporFasilitas
 * Exports all schema and data to database/backups/
 */
require_once __DIR__ . '/bootstrap.php';
use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    die("Database connection failed.\n");
}

$backupDir = ROOT_PATH . '/database/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');
$filename = "backup_db_laporfasilitas_{$timestamp}.sql";
$filepath = "{$backupDir}/{$filename}";

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$sqlDump = "-- ==========================================================\n";
$sqlDump .= "-- Database Backup: db_laporfasilitas\n";
$sqlDump .= "-- Created at: " . date('Y-m-d H:i:s') . "\n";
$sqlDump .= "-- MySQL/MariaDB Version: " . $db->query("SELECT VERSION()")->fetchColumn() . "\n";
$sqlDump .= "-- ==========================================================\n\n";
$sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sqlDump .= "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    $sqlDump .= "-- ----------------------------------------------------------\n";
    $sqlDump .= "-- Table structure and data for table `{$table}`\n";
    $sqlDump .= "-- ----------------------------------------------------------\n";
    $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
    
    $createTable = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    $sqlDump .= $createTable['Create Table'] . ";\n\n";
    
    $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $escapedCols = array_map(fn($c) => "`{$c}`", $cols);
        $colList = implode(', ', $escapedCols);
        
        $sqlDump .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
        $valStrings = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = "NULL";
                } else {
                    $vals[] = $db->quote($val);
                }
            }
            $valStrings[] = "(" . implode(', ', $vals) . ")";
        }
        $sqlDump .= implode(",\n", $valStrings) . ";\n\n";
    }
}

$sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$bytes = file_put_contents($filepath, $sqlDump);

if ($bytes === false || $bytes === 0) {
    die("ERROR: Failed to write database backup to {$filepath}\n");
}

echo "Database backup successfully created:\n";
echo "- Path: {$filepath}\n";
echo "- Size: {$bytes} bytes\n";
echo "- Tables backed up: " . implode(', ', $tables) . "\n";
