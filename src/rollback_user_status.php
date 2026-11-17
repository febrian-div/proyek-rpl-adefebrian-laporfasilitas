<?php
/**
 * Rollback: Hapus kolom is_active dari tabel users
 * Jalankan: php src/rollback_user_status.php
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

$db = \App\Config\Database::getConnection();
if (!$db) {
    echo "ERROR: Koneksi database gagal.\n";
    exit(1);
}

$check = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetch();
if (!$check) {
    echo "INFO: Kolom is_active tidak ada. Tidak ada yang di-rollback.\n";
    exit(0);
}

$db->exec("ALTER TABLE users DROP COLUMN is_active");
echo "SUCCESS: Kolom is_active berhasil dihapus dari tabel users.\n";

