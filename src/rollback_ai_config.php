<?php
/**
 * Rollback: Menghapus tabel ai_configurations
 * Jalankan: php src/rollback_ai_config.php
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;

$db = Database::getConnection();
if (!$db) {
    echo "ERROR: Koneksi database gagal.\n";
    exit(1);
}

$db->exec("DROP TABLE IF EXISTS `ai_configurations`;");
echo "SUCCESS: Tabel ai_configurations berhasil dihapus (rollback selesai).\n";
