-- ==========================================================
-- Database Schema for LaporFasilitas
-- Sistem Pelaporan Fasilitas Umum Berbasis AI
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `db_laporfasilitas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_laporfasilitas`;

-- Drop existing tables if needed (in reverse dependency order)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `repair_proofs`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `officers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. Table: users (Masyarakat, Admin, Petugas)
-- ----------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('masyarakat', 'admin', 'petugas') NOT NULL DEFAULT 'masyarakat',
    `telegram_chat_id` VARCHAR(50) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_telegram_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Table: officers (Data profil khusus petugas lapangan)
-- ----------------------------------------------------------
CREATE TABLE `officers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `service_area` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_officers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_officers_area` (`service_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Table: reports (Laporan kerusakan fasilitas)
-- ----------------------------------------------------------
CREATE TABLE `reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `photo` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `address` TEXT NOT NULL,
    `facility_category` VARCHAR(100) DEFAULT NULL,
    `damage_type` VARCHAR(150) DEFAULT NULL,
    `priority` ENUM('Rendah', 'Sedang', 'Tinggi', 'Kritis') DEFAULT NULL,
    `ai_result` JSON DEFAULT NULL,
    `status` ENUM('Menunggu Verifikasi', 'Diverifikasi', 'Ditugaskan', 'Sedang Diperbaiki', 'Selesai', 'Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_reports_user` (`user_id`),
    INDEX `idx_reports_status` (`status`),
    INDEX `idx_reports_priority` (`priority`),
    INDEX `idx_reports_category` (`facility_category`),
    INDEX `idx_reports_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Table: assignments (Penugasan laporan ke petugas)
-- ----------------------------------------------------------
CREATE TABLE `assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `officer_id` INT UNSIGNED NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Ditugaskan', 'Diterima', 'Sedang Dikerjakan', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Ditugaskan',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_assignments_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assignments_officer` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`id`) ON DELETE CASCADE,
    INDEX `idx_assignments_report` (`report_id`),
    INDEX `idx_assignments_officer` (`officer_id`),
    INDEX `idx_assignments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Table: repair_proofs (Bukti foto dan catatan perbaikan)
-- ----------------------------------------------------------
CREATE TABLE `repair_proofs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `photo` VARCHAR(255) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_repair_proofs_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
    INDEX `idx_repair_proofs_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Table: notifications (Notifikasi dalam website)
-- ----------------------------------------------------------
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Table: telegram_pairings (Pairing akun Telegram)
-- ----------------------------------------------------------
CREATE TABLE `telegram_pairings` (
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

-- ----------------------------------------------------------
-- 8. Table: telegram_deliveries (Transaksi Outbox Telegram)
-- ----------------------------------------------------------
CREATE TABLE `telegram_deliveries` (
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
