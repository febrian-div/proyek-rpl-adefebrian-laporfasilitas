-- ==========================================================
-- Database Backup: db_laporfasilitas
-- Created at: 2026-10-28 23:27:18
-- MySQL/MariaDB Version: 10.4.32-MariaDB
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;

-- ----------------------------------------------------------
-- Table structure and data for table `assignments`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL,
  `officer_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Ditugaskan','Diterima','Sedang Dikerjakan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Ditugaskan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assignments_report` (`report_id`),
  KEY `idx_assignments_officer` (`officer_id`),
  KEY `idx_assignments_status` (`status`),
  KEY `idx_assignments_officer_id` (`officer_id`),
  CONSTRAINT `fk_assignments_officer` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `assignments` (`id`, `report_id`, `officer_id`, `assigned_at`, `status`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '2026-08-31 08:06:58', 'Selesai', '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('2', '2', '2', '2026-09-03 08:06:58', 'Sedang Dikerjakan', '2026-09-04 08:06:58', '2026-09-04 08:06:58');

-- ----------------------------------------------------------
-- Table structure and data for table `notifications`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_id_is_read` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure and data for table `officers`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `officers`;
CREATE TABLE `officers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `phone` varchar(20) NOT NULL,
  `service_area` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_officers_area` (`service_area`),
  CONSTRAINT `fk_officers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `officers` (`id`, `user_id`, `phone`, `service_area`, `created_at`, `updated_at`) VALUES
('1', '2', '081298765431', 'Jakarta Pusat & Thamrin', '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('2', '3', '081298765432', 'Jakarta Selatan & Sudirman', '2026-09-04 08:06:58', '2026-09-04 08:06:58');

-- ----------------------------------------------------------
-- Table structure and data for table `repair_proofs`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `repair_proofs`;
CREATE TABLE `repair_proofs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL,
  `photo` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_repair_proofs_report` (`report_id`),
  CONSTRAINT `fk_repair_proofs_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `repair_proofs` (`id`, `report_id`, `photo`, `notes`, `created_at`) VALUES
('1', '1', '/uploads/repairs/sample_jalan_selesai.jpg', 'Penambalan aspal hotmix telah selesai dilakukan dengan ketebalan 8cm dan permukaan jalan telah diratakan kembali. Lalu lintas lancar dan aman.', '2026-09-01 08:06:58');

-- ----------------------------------------------------------
-- Table structure and data for table `report_timelines`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `report_timelines`;
CREATE TABLE `report_timelines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL,
  `status` varchar(100) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `report_timelines_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure and data for table `reports`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `photo` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `address` text NOT NULL,
  `facility_category` varchar(100) DEFAULT NULL,
  `damage_type` varchar(150) DEFAULT NULL,
  `priority` enum('Rendah','Sedang','Tinggi','Kritis') DEFAULT NULL,
  `ai_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_result`)),
  `status` enum('Menunggu Verifikasi','Diverifikasi','Ditugaskan','Sedang Diperbaiki','Selesai','Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reports_user` (`user_id`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_priority` (`priority`),
  KEY `idx_reports_category` (`facility_category`),
  KEY `idx_reports_created` (`created_at`),
  KEY `idx_reports_user_id` (`user_id`),
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reports` (`id`, `user_id`, `title`, `description`, `photo`, `latitude`, `longitude`, `address`, `facility_category`, `damage_type`, `priority`, `ai_result`, `status`, `created_at`, `updated_at`) VALUES
('1', '4', 'Lubang Aspal Menganga di Sekitar Bundaran HI', 'Terdapat lubang jalan berdiameter sekitar 60cm dan kedalaman 10cm yang sangat membahayakan pengendara motor saat malam hari.', '/uploads/reports/sample_jalan_rusak.jpg', '-6.19500000', '106.82300000', 'Jl. M.H. Thamrin No. 1, Menteng, Jakarta Pusat', 'Jalan & Trotoar', 'Lubang aspal dalam berpotensi kecelakaan', 'Kritis', '{\"detected_category\": \"Jalan & Trotoar\", \"damage_type\": \"Lubang aspal dalam berpotensi kecelakaan\", \"estimated_severity\": \"Kritis\", \"confidence_score\": 0.96, \"suggested_action\": \"Lakukan penambalan aspal darurat dan pasang rambu peringatan.\"}', 'Selesai', '2026-08-30 08:06:58', '2026-09-04 08:06:58'),
('2', '4', 'Lampu Penerangan Jalan Padam Sepanjang 100 Meter', 'Sebanyak 3 tiang lampu PJU mati total sehingga ruas jalan sangat gelap dan rawan kriminalitas.', '/uploads/reports/sample_lampu_mati.jpg', '-6.21500000', '106.81600000', 'Jl. Jenderal Sudirman Kav. 25, Karet Semanggi, Jakarta Selatan', 'Penerangan Jalan Umum (PJU)', 'Bohlam lampu padam & kendala kabel daya', 'Tinggi', '{\"detected_category\": \"Penerangan Jalan Umum (PJU)\", \"damage_type\": \"Bohlam lampu padam & kendala kabel daya\", \"estimated_severity\": \"Tinggi\", \"confidence_score\": 0.92, \"suggested_action\": \"Periksa gardu distribusi PJU dan lakukan penggantian armatur LED.\"}', 'Sedang Diperbaiki', '2026-09-02 08:06:58', '2026-09-04 08:06:58'),
('3', '5', 'Saluran Drainase Tersumbat Sampah dan Meluap ke Badan Jalan', 'Saluran air got tertutup sedimentasi pasir dan sampah plastik tebal sehingga air meluber saat hujan deras.', '/uploads/reports/sample_drainase_banjir.jpg', '-6.24200000', '106.80000000', 'Jl. Radio Dalam Raya No. 12, Gandaria Utara, Kebayoran Baru', 'Drainase & Saluran Air', 'Penyumbatan saluran air parah', 'Sedang', '{\"detected_category\": \"Drainase & Saluran Air\", \"damage_type\": \"Penyumbatan saluran air parah\", \"estimated_severity\": \"Sedang\", \"confidence_score\": 0.88, \"suggested_action\": \"Pengerukan sedimen dan pembersihan saringan air inlet got.\"}', 'Diverifikasi', '2026-09-03 08:06:58', '2026-09-04 08:06:58'),
('4', '5', 'Paving Block Trotoar Amblas Dekat Halte Busway', 'Trotoar pejalan kaki rusak dan amblas sehingga menyulitkan akses bagi lansia dan penyandang disabilitas.', '/uploads/reports/sample_trotoar_amblas.jpg', '-6.22500000', '106.80500000', 'Jl. Gatot Subroto Depan Halte Komdak, Jakarta Selatan', 'Jalan & Trotoar', 'Paving block trotoar lepas dan amblas', 'Tinggi', '{\"detected_category\": \"Jalan & Trotoar\", \"damage_type\": \"Paving block trotoar lepas dan amblas\", \"estimated_severity\": \"Tinggi\", \"confidence_score\": 0.91, \"suggested_action\": \"Pemasangan kembali dan pemadatan pondasi paving trotoar.\"}', 'Menunggu Verifikasi', '2026-09-04 08:06:58', '2026-09-04 08:06:58');

-- ----------------------------------------------------------
-- Table structure and data for table `telegram_deliveries`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `telegram_deliveries`;
CREATE TABLE `telegram_deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `report_id` int(10) unsigned DEFAULT NULL,
  `recipient_type` enum('admin','masyarakat','petugas') NOT NULL,
  `chat_id` varchar(50) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `message_payload` text NOT NULL,
  `parse_mode` varchar(10) NOT NULL DEFAULT 'HTML',
  `idempotency_key` varchar(64) NOT NULL,
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `worker_id` varchar(64) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `fk_tel_deliv_notif` (`notification_id`),
  KEY `fk_tel_deliv_user` (`user_id`),
  KEY `idx_tel_deliv_queue` (`status`,`next_retry_at`,`created_at`),
  KEY `idx_tel_deliv_chat_event` (`chat_id`,`event_type`,`status`),
  KEY `idx_tel_deliv_report` (`report_id`),
  KEY `idx_tel_deliv_worker_lock` (`status`,`locked_at`),
  CONSTRAINT `fk_tel_deliv_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tel_deliv_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tel_deliv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure and data for table `telegram_pairings`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `telegram_pairings`;
CREATE TABLE `telegram_pairings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pairing_lookup` (`token_hash`,`is_used`,`expires_at`),
  KEY `idx_pairing_user` (`user_id`),
  CONSTRAINT `fk_pairings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure and data for table `users`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('masyarakat','admin','petugas') NOT NULL DEFAULT 'masyarakat',
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_telegram_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `telegram_chat_id`, `created_at`, `updated_at`) VALUES
('1', 'Administrator Utama', 'admin@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'admin', NULL, '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('2', 'Budi Pratama (Teknisi PU)', 'petugas1@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'petugas', NULL, '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('3', 'Agus Setiawan (Teknisi Listrik/PJU)', 'petugas2@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'petugas', NULL, '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('4', 'Siti Rahmawati', 'warga@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'masyarakat', NULL, '2026-09-04 08:06:58', '2026-10-26 18:37:10'),
('5', 'Bambang Kusuma', 'budi@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'masyarakat', NULL, '2026-09-04 08:06:58', '2026-09-04 08:06:58'),
('10', 'asep', 'warga@laporfasilitas.com', '$2y$10$EJcfoaSpNF0EAGCDqY8cOuHeQtkakPoFTKqEZwtPjyT5jPpVg0BaG', 'masyarakat', NULL, '2026-10-01 08:42:13', '2026-10-01 08:42:13'),
('28', 'Ade Febrian', 'ade.febrian0705@gmail.com', '$2y$10$JhB8bmwa/ZHzPI6Z1h7FwOS.pdySaG78iwVzgQev.c5qfxVBrsdvC', 'masyarakat', NULL, '2026-10-16 08:55:52', '2026-10-16 08:55:52');

SET FOREIGN_KEY_CHECKS = 1;
