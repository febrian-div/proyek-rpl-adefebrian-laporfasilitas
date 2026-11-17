-- ==========================================================
-- Seeds Data for LaporFasilitas
-- Default Users, Officers, Reports, Assignments, Repair Proofs
-- Password for all users: password123 ($2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu)
-- ==========================================================

USE `db_laporfasilitas`;

-- 1. Insert Users (Admin, Petugas, Masyarakat)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Administrator Utama', 'admin@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'admin'),
(2, 'Budi Pratama (Teknisi PU)', 'petugas1@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'petugas'),
(3, 'Agus Setiawan (Teknisi Listrik/PJU)', 'petugas2@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'petugas'),
(4, 'Siti Rahmawati', 'warga@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'masyarakat'),
(5, 'Bambang Kusuma', 'budi@laporfasilitas.id', '$2y$10$RSV2aYXTko7oMkCoD.HkRette6uoBAymAAiW3.Zd8Q2oZfyZPcXTu', 'masyarakat')
ON DUPLICATE KEY UPDATE `email`=`email`;

-- 2. Insert Officers Profile
INSERT INTO `officers` (`id`, `user_id`, `phone`, `service_area`) VALUES
(1, 2, '081298765431', 'Jakarta Pusat & Thamrin'),
(2, 3, '081298765432', 'Jakarta Selatan & Sudirman')
ON DUPLICATE KEY UPDATE `user_id`=`user_id`;

-- 3. Insert Sample Reports with AI Inference JSON
INSERT INTO `reports` (
    `id`, `user_id`, `title`, `description`, `photo`, `latitude`, `longitude`, 
    `address`, `facility_category`, `damage_type`, `priority`, `ai_result`, `status`, `created_at`
) VALUES
(
    1, 4, 
    'Lubang Aspal Menganga di Sekitar Bundaran HI', 
    'Terdapat lubang jalan berdiameter sekitar 60cm dan kedalaman 10cm yang sangat membahayakan pengendara motor saat malam hari.', 
    '/uploads/reports/sample_jalan_rusak.jpg', 
    -6.19500000, 106.82300000, 
    'Jl. M.H. Thamrin No. 1, Menteng, Jakarta Pusat', 
    'Jalan & Trotoar', 
    'Lubang aspal dalam berpotensi kecelakaan', 
    'Kritis', 
    '{"detected_category": "Jalan & Trotoar", "damage_type": "Lubang aspal dalam berpotensi kecelakaan", "estimated_severity": "Kritis", "confidence_score": 0.96, "suggested_action": "Lakukan penambalan aspal darurat dan pasang rambu peringatan."}', 
    'Selesai', 
    NOW() - INTERVAL 5 DAY
),
(
    2, 4, 
    'Lampu Penerangan Jalan Padam Sepanjang 100 Meter', 
    'Sebanyak 3 tiang lampu PJU mati total sehingga ruas jalan sangat gelap dan rawan kriminalitas.', 
    '/uploads/reports/sample_lampu_mati.jpg', 
    -6.21500000, 106.81600000, 
    'Jl. Jenderal Sudirman Kav. 25, Karet Semanggi, Jakarta Selatan', 
    'Penerangan Jalan Umum (PJU)', 
    'Bohlam lampu padam & kendala kabel daya', 
    'Tinggi', 
    '{"detected_category": "Penerangan Jalan Umum (PJU)", "damage_type": "Bohlam lampu padam & kendala kabel daya", "estimated_severity": "Tinggi", "confidence_score": 0.92, "suggested_action": "Periksa gardu distribusi PJU dan lakukan penggantian armatur LED."}', 
    'Sedang Diperbaiki', 
    NOW() - INTERVAL 2 DAY
),
(
    3, 5, 
    'Saluran Drainase Tersumbat Sampah dan Meluap ke Badan Jalan', 
    'Saluran air got tertutup sedimentasi pasir dan sampah plastik tebal sehingga air meluber saat hujan deras.', 
    '/uploads/reports/sample_drainase_banjir.jpg', 
    -6.24200000, 106.80000000, 
    'Jl. Radio Dalam Raya No. 12, Gandaria Utara, Kebayoran Baru', 
    'Drainase & Saluran Air', 
    'Penyumbatan saluran air parah', 
    'Sedang', 
    '{"detected_category": "Drainase & Saluran Air", "damage_type": "Penyumbatan saluran air parah", "estimated_severity": "Sedang", "confidence_score": 0.88, "suggested_action": "Pengerukan sedimen dan pembersihan saringan air inlet got."}', 
    'Diverifikasi', 
    NOW() - INTERVAL 1 DAY
),
(
    4, 5, 
    'Paving Block Trotoar Amblas Dekat Halte Busway', 
    'Trotoar pejalan kaki rusak dan amblas sehingga menyulitkan akses bagi lansia dan penyandang disabilitas.', 
    '/uploads/reports/sample_trotoar_amblas.jpg', 
    -6.22500000, 106.80500000, 
    'Jl. Gatot Subroto Depan Halte Komdak, Jakarta Selatan', 
    'Jalan & Trotoar', 
    'Paving block trotoar lepas dan amblas', 
    'Tinggi', 
    '{"detected_category": "Jalan & Trotoar", "damage_type": "Paving block trotoar lepas dan amblas", "estimated_severity": "Tinggi", "confidence_score": 0.91, "suggested_action": "Pemasangan kembali dan pemadatan pondasi paving trotoar."}', 
    'Menunggu Verifikasi', 
    NOW()
)
ON DUPLICATE KEY UPDATE `title`=`title`;

-- 4. Insert Assignments
INSERT INTO `assignments` (`id`, `report_id`, `officer_id`, `assigned_at`, `status`) VALUES
(1, 1, 1, NOW() - INTERVAL 4 DAY, 'Selesai'),
(2, 2, 2, NOW() - INTERVAL 1 DAY, 'Sedang Dikerjakan')
ON DUPLICATE KEY UPDATE `status`=`status`;

-- 5. Insert Repair Proofs
INSERT INTO `repair_proofs` (`id`, `report_id`, `photo`, `notes`, `created_at`) VALUES
(
    1, 1, 
    '/uploads/repairs/sample_jalan_selesai.jpg', 
    'Penambalan aspal hotmix telah selesai dilakukan dengan ketebalan 8cm dan permukaan jalan telah diratakan kembali. Lalu lintas lancar dan aman.', 
    NOW() - INTERVAL 3 DAY
)
ON DUPLICATE KEY UPDATE `photo`=`photo`;
