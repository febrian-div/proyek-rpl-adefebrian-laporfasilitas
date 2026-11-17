<?php
require_once __DIR__ . '/bootstrap.php';

use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    // Try connecting to MySQL server directly to create the database if not exists
    $pdo = new PDO("mysql:host=" . env('DB_HOST', '127.0.0.1') . ";port=" . env('DB_PORT', '3306'), env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''));
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `db_laporfasilitas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $db = Database::getConnection();
}

if (!$db) {
    die("Database connection failed.\n");
}

echo "Running schema.sql...\n";
$schemaSql = file_get_contents(ROOT_PATH . '/database/schema.sql');
$db->exec($schemaSql);
echo "Schema executed successfully!\n";

echo "Running seeds.sql...\n";
$seedsSql = file_get_contents(ROOT_PATH . '/database/seeds.sql');
$db->exec($seedsSql);
echo "Seeds executed successfully!\n";
