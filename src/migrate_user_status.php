<?php
/**
 * Migration: Tambah kolom is_active ke tabel users
 * Jalankan: php src/migrate_user_status.php
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

$db = \App\Config\Database::getConnection();
if (!$db) {
    echo "ERROR: Koneksi database gagal.\n";
    exit(1);
}

// Cek apakah kolom is_active sudah ada
$check = $db->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetch();
if ($check) {
    echo "INFO: Kolom is_active sudah ada di tabel users. Tidak ada perubahan.\n";
    exit(0);
}

// Tambahkan kolom is_active (default 1 = aktif)
$db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER telegram_chat_id");

// Pastikan semua user existing dianggap aktif
$db->exec("UPDATE users SET is_active = 1 WHERE is_active IS NULL");

echo "SUCCESS: Kolom is_active berhasil ditambahkan ke tabel users.\n";
echo "INFO: Semua pengguna existing telah diset is_active = 1 (aktif).\n";

