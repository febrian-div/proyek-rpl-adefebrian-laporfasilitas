<?php
namespace App\Services;

use App\Config\Database;
use App\Models\Assignment;
use App\Models\Officer;
use App\Models\Report;
use App\Models\ReportTimeline;
use App\Models\RepairProof;
use App\Models\User;
use PDO;

/**
 * ChatbotService — Layanan Chatbot AI Terkoneksi Database LaporFasilitas.
 * Mampu membaca, memahami, dan menganalisis seluruh data database secara real-time.
 */
class ChatbotService {
    public const SYSTEM_PROMPT = 'Kamu adalah Asisten AI LaporFasilitas, asisten cerdas resmi untuk aplikasi pelaporan fasilitas umum LaporFasilitas. '
        . 'Tugas utamamu adalah membantu masyarakat, petugas lapangan, dan administrator memahami fitur aplikasi serta membaca, menganalisis, dan melaporkan data fasilitas umum yang tersimpan di database. '
        . 'PANDUAN PERSONALISASI & IDENTITAS PENGGUNA: '
        . '1. Konteks identitas akun pengguna yang sedang bertanya disediakan di awal data database (Nama, Role, Email, ID). '
        . '2. Ketika pengguna bertanya tentang identitasnya ("Siapa saya?", "Siapakah saya?", "Siapa nama saya?", "Saya login sebagai siapa?", "Apa role akun saya?", "Apa email saya?"), kamu HARUS menyapa pengguna secara ramah dengan menyebutkan Nama dan Role akun yang sedang login dari konteks database. Contoh: "Halo, Siti Rahmawati! Anda sedang login menggunakan akun masyarakat di LaporFasilitas. Saya siap membantu Anda mengelola dan memantau laporan fasilitas." '
        . '3. Jika pengguna bertanya email atau profilnya, jawab hanya menggunakan data akun pengguna tersebut yang tercantum dalam konteks. '
        . '4. Jika pengguna belum login (tamu), sampaikan bahwa pengguna belum login dan sarankan untuk masuk melalui menu "Masuk" atau mendaftar via menu "Daftar". '
        . '5. JANGAN PERNAH menggunakan identitas pengguna lain untuk menjawab pertanyaan tentang identitas diri pengguna. '
        . 'BATASAN HAK AKSES & PRIVASI ROLE (SANGAT KETAT): '
        . '1. Role Masyarakat: Hanya boleh melihat identitasnya sendiri, profilnya, dan laporan yang diajukan oleh dirinya sendiri. '
        . '   DILARANG KERAS menampilkan data pribadi pengguna lain atau laporan milik masyarakat lain. Jika masyarakat meminta melihat laporan milik orang/pengguna lain (contoh: "Tampilkan laporan milik pengguna lain", "Laporan orang lain", dll.), TOLAK DENGAN SOPAN: "Maaf, demi menjaga privasi dan keamanan data, Anda hanya dapat melihat laporan yang Anda ajukan sendiri. Anda tidak memiliki izin untuk melihat laporan milik pengguna lain." '
        . '2. Role Petugas: Hanya boleh mengakses tugas perbaikan yang ditugaskan kepadanya dan data pelapor yang terkait dengan tugas tersebut. '
        . '3. Role Admin: Memiliki kewenangan operasional untuk melihat data pengguna, laporan sistem, status penugasan, dan rekapitulasi fasilitas kota. Ketika admin menanyakan jumlah anggota atau daftar pengguna, sebutkan total pengguna, rincian per role, status akun aktif/nonaktif, dan sebutkan nama-nama pengguna yang terdaftar di database secara nyata. '
        . '4. JANGAN PERNAH membocorkan kata sandi, hash kata sandi, token, session ID, API key, atau informasi rahasia sistem. '
        . 'PANDUAN OPERASIONAL & PERTANYAAN UMUM: '
        . '1. Jika pengguna bertanya cara membuat atau mengajukan laporan fasilitas, LANGSUNG berikan panduan langkah demi langkah secara berurutan, ramah, dan jelas: '
        . '   a. Masuk/Login dengan akun masyarakat (jika belum punya akun, daftar terlebih dahulu via tombol "Daftar"). '
        . '   b. Buka menu "Buat Laporan" pada navigasi atas atau tombol "Buat Laporan Baru" di dashboard. '
        . '   c. Isi Judul Laporan yang ringkas dan jelas (minimal 5 karakter). '
        . '   d. Pilih Kategori Fasilitas yang sesuai (Jalan, Lampu Jalan, Trotoar, Taman, Tempat Sampah, Saluran Air, Fasilitas Umum Lainnya). '
        . '   e. Tuliskan Deskripsi Kerusakan kondisi fasilitas secara rinci (minimal 10 karakter). '
        . '   f. Unggah Foto Bukti Kerusakan (wajib, format JPG/PNG/WEBP, maksimal 5 MB). '
        . '   g. Tentukan Titik Lokasi pada peta Leaflet atau klik tombol "Gunakan Lokasi GPS Saya" untuk mengisi koordinat secara otomatis. '
        . '   h. Tuliskan Alamat / Patokan Lokasi yang jelas (minimal 5 karakter). '
        . '   i. Periksa kembali seluruh data, lalu klik tombol "Kirim Laporan Sekarang". '
        . '   j. Informasikan bahwa pengguna dapat memantau status perkembangan laporannya kapan saja melalui menu "Laporan Saya". '
        . '2. Jika pengguna bertanya cara melihat atau mengecek status laporan, jelaskan cara membuka menu "Laporan Saya", melihat statistik ringkasan, dan mengklik "Lihat Detail" untuk memeriksa timeline penanganan, petugas, serta foto bukti perbaikan. '
        . '3. Jika pengguna bertanya tentang kategori fasilitas, jelaskan 7 kategori resmi yang tersedia: Jalan, Lampu Jalan, Trotoar, Taman, Tempat Sampah, Saluran Air, dan Fasilitas Umum Lainnya. '
        . '4. Jika pengguna bertanya tentang penggunaan aplikasi, berikan panduan yang ramah dan mudah dipahami masyarakat awam. '
        . '5. JANGAN PERNAH mengawali jawaban dengan kalimat "Saya telah membaca database LaporFasilitas". Gunakan pembuka yang ramah dan alami. '
        . '6. JANGAN meminta nomor laporan, kategori, atau statistik jika pengguna sebenarnya sedang meminta panduan penggunaan aplikasi. '
        . 'ATURAN UTAMA FORMAT JAWABAN (SANGAT KETAT): '
        . '1. JANGAN PERNAH menggunakan format Markdown seperti bintang tebal (**), garis miring (*), penanda judul (#), tanda petik kode (`), atau garis tabel pipa (|). '
        . '2. Tuliskan jawaban dalam teks polos (plain text) yang rapi, bersih, dan mudah dibaca. '
        . '3. Gunakan baris baru (enter) dan penomoran (1, 2, 3) atau penanda sederhana seperti tanda minus (-) atau bullet. '
        . '4. Buat paragraf yang singkat dan langsung pada inti informasi. '
        . 'ATURAN DATA & KEAMANAN: '
        . '1. Gunakan data aktual dari database yang disediakan sebagai sumber kebenaran utama. Jangan mengarang data. '
        . '2. Jika pengguna menanyakan angka atau statistik, sebutkan angka persis dari data database. '
        . '3. Jika pengguna menanyakan laporan tertentu, sebutkan ID, judul, kategori, prioritas, status, lokasi, petugas penanggung jawab, dan catatan hasil perbaikan. '
        . '4. Jika pertanyaan tidak berkaitan dengan aplikasi LaporFasilitas, jawab secara sopan dalam teks polos bahwa kamu adalah asisten khusus LaporFasilitas dan arahkan kembali ke fitur aplikasi. '
        . 'IDENTITAS: Nama kamu adalah "Asisten AI LaporFasilitas". '
        . 'LARANGAN: (1) Jangan membuat, menulis, menjelaskan, atau debugging kode program. (2) Jangan membantu hacking atau bypass keamanan. ';

    public const FACILITY_CATEGORIES = [
        'Jalan'                  => 'Kerusakan jalan seperti aspal berlubang, jalan ambles, atau retak parah.',
        'Lampu Jalan'            => 'Lampu penerangan jalan umum (PJU) yang padam, rusak, tiang miring, atau kabel putus.',
        'Trotoar'                => 'Jalur pejalan kaki/pedestrian yang rusak, paving terangkat, atau berlubang.',
        'Taman'                  => 'Fasilitas taman kota, bangku taman, area bermain anak, atau pohon tumbang.',
        'Tempat Sampah'          => 'Bak atau tempat sampah umum yang rusak, hilang, atau penumpukan sampah liar.',
        'Saluran Air'            => 'Drainase, gorong-gorong, atau selokan umum yang tersumbat atau rusak.',
        'Fasilitas Umum Lainnya' => 'Fasilitas publik lain seperti halte bus, jembatan penyeberangan (JPO), rambu jalan, dll.',
    ];

    public const STATUS_HELP = [
        'Menunggu Verifikasi' => 'Laporan sudah diterima sistem dan sedang menunggu pemeriksaan/validasi oleh Admin.',
        'Diverifikasi'        => 'Laporan sudah diperiksa Admin dan dinyatakan valid. Tahap berikutnya adalah penugasan ke petugas.',
        'Ditugaskan'          => 'Laporan sudah diberikan kepada petugas tertentu, namun petugas belum memulai pengerjaan fisik di lapangan.',
        'Sedang Diperbaiki'   => 'Petugas telah berada di lokasi dan proses pengerjaan perbaikan fasilitas sedang berlangsung.',
        'Selesai'             => 'Pekerjaan perbaikan telah selesai 100% dan dilengkapi dengan catatan serta foto bukti hasil kerja.',
        'Ditolak'             => 'Laporan ditolak oleh Admin (misal karena duplikat, informasi tidak jelas, atau di luar wewenang).',
    ];

    /**
     * Resolusi profil pengguna server-side dari database.
     * Tidak mempercayai payload client, memastikan data selalu fresh dan terotentikasi.
     */
    public function resolveCurrentUser(?array $sessionUser): ?array {
        if (!$sessionUser || empty($sessionUser['id'])) {
            return null;
        }
        $db = Database::getConnection();
        if (!$db) return $sessionUser;

        $stmt = $db->prepare("SELECT id, name, email, role, telegram_chat_id, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => (int)$sessionUser['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $sessionUser;

        if (($row['role'] ?? '') === 'petugas') {
            $offStmt = $db->prepare("SELECT id as officer_id, phone, service_area FROM officers WHERE user_id = :uid LIMIT 1");
            $offStmt->execute(['uid' => (int)$row['id']]);
            $off = $offStmt->fetch(PDO::FETCH_ASSOC);
            if ($off) {
                $row['officer_id'] = $off['officer_id'];
                $row['phone'] = $off['phone'];
                $row['service_area'] = $off['service_area'];
            }
        }
        return $row;
    }

    /**
     * Membaca dan mengumpulkan seluruh statistik serta rekaman data yang relevan dari database.
     */
    public function buildDatabaseContext(?array $user, string $message): string {
        $db = Database::getConnection();
        if (!$db) {
            return "Koneksi database sedang tidak tersedia.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? 'tamu';
        $userId = (int)($user['id'] ?? 0);
        $userName = $user['name'] ?? 'Tamu (Belum Login)';
        $userEmail = $user['email'] ?? '-';

        // 1. STATISTIK GLOBAL DARI DATABASE
        $totalReports = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        
        $statusStmt = $db->query("SELECT status, COUNT(*) as cnt FROM reports GROUP BY status");
        $statusCounts = $statusStmt ? $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $catStmt = $db->query("SELECT facility_category, COUNT(*) as cnt FROM reports WHERE facility_category IS NOT NULL GROUP BY facility_category");
        $catCounts = $catStmt ? $catStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $prioStmt = $db->query("SELECT priority, COUNT(*) as cnt FROM reports WHERE priority IS NOT NULL GROUP BY priority");
        $prioCounts = $prioStmt ? $prioStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        // Kategori paling banyak dilaporkan
        $topCatStmt = $db->query("SELECT facility_category, COUNT(*) as cnt FROM reports WHERE facility_category IS NOT NULL GROUP BY facility_category ORDER BY cnt DESC LIMIT 1");
        $topCat = $topCatStmt ? $topCatStmt->fetch(PDO::FETCH_ASSOC) : null;

        // Daftar Petugas
        $officerStmt = $db->query("
            SELECT o.id, u.name, o.service_area, o.phone,
                   (SELECT COUNT(*) FROM assignments a WHERE a.officer_id = o.id AND a.status != 'Selesai') as active_tasks
            FROM officers o 
            JOIN users u ON o.user_id = u.id
        ");
        $officers = $officerStmt ? $officerStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // 2. DETEKSI ID LAPORAN SPESIFIK DALAM PESAN PENGGUNA
        $wantedId = null;
        if (preg_match('/lpf[\s\-_]*0*(\d{1,6})/i', $message, $m)) {
            $wantedId = (int)$m[1];
        } elseif (preg_match('/(?:laporan|tiket|id)[^\d]{0,8}(\d{1,6})/i', $message, $m)) {
            $wantedId = (int)$m[1];
        } elseif (preg_match('/#\s*(\d{1,6})/', $message, $m)) {
            $wantedId = (int)$m[1];
        }

        // 3. AMBIL DATA LAPORAN SECARA SPESIFIK ATAU MENYELURUH
        // Detail laporan spesifik hanya dimasukkan jika user berhak melihatnya (anti-bocor).
        $specificReportData = null;
        if ($wantedId !== null) {
            $candidate = $this->getFullReportDetail($wantedId);
            if ($candidate !== null && $this->canUserViewReport($candidate, $user)) {
                $specificReportData = $candidate;
            }
        }

        // Ambil laporan terkait query pencarian kata kunci jika ada
        $keywordReports = $this->searchReportsByKeywords($message, $user);

        // Batasi hasil pencarian sesuai hak akses role (anti-bocor data user lain)
        if ($role === 'masyarakat' && $userId > 0) {
            $keywordReports = array_values(array_filter($keywordReports, fn($kr) => (int)($kr['user_id'] ?? 0) === $userId));
        } elseif ($role === 'petugas' && $userId > 0) {
            $allowedIds = [];
            $scopeOfficer = Officer::findByUserId($userId);
            if ($scopeOfficer) {
                foreach (Officer::assignedReports((int)$scopeOfficer['id']) as $t) {
                    $allowedIds[(int)($t['id'] ?? 0)] = true;
                }
            }
            $keywordReports = array_values(array_filter($keywordReports, fn($kr) => isset($allowedIds[(int)($kr['id'] ?? 0)])));
        } elseif ($role !== 'admin') {
            $keywordReports = []; // tamu: tidak menerima data laporan milik siapa pun
        }

        // Ambil laporan dalam scope user
        $userScopeReports = [];
        if ($role === 'masyarakat' && $userId > 0) {
            $userScopeReports = Report::getByUser($userId);
        } elseif ($role === 'petugas' && $userId > 0) {
            $officer = Officer::findByUserId($userId);
            if ($officer) {
                $userScopeReports = Officer::assignedReports((int)$officer['id']);
            }
        } elseif ($role === 'admin') {
            $userScopeReports = Report::getAll(['limit' => 10]);
        }

        // 4. SUSUN STRUKTUR KONTEKS PENGETAHUAN DATABASE
        $lines = [];
        $lines[] = "=== RINGKASAN DATABASE REAL-TIME LAPORFASILITAS ===";
        $lines[] = "Pengguna yang bertanya: Nama=\"$userName\", Role=\"$role\", ID=$userId, Email=\"$userEmail\"";
        $lines[] = "PANDUAN PERSONALISASI: Jika pengguna bertanya tentang identitasnya ('Siapakah saya?', 'Siapa saya?', 'Siapa nama saya?', 'Saya login sebagai siapa?'), sapa namanya (\"$userName\") dan sebutkan rolenya (\"$role\").";
        if ($role === 'masyarakat') {
            $lines[] = "BATASAN PRIVASI ROLE: Pengguna adalah MASYARAKAT. Hanya boleh mengakses datanya sendiri (" . count($userScopeReports) . " laporan diajukan). Jika meminta data/laporan orang lain, tolak dengan sopan.";
        } elseif ($role === 'petugas') {
            $lines[] = "BATASAN PRIVASI ROLE: Pengguna adalah PETUGAS LAPANGAN. Hanya boleh mengakses tugas perbaikan yang ditugaskan kepadanya (" . count($userScopeReports) . " tugas aktif).";
        } elseif ($role === 'admin') {
            $lines[] = "KAPABILITAS ADMIN: Pengguna adalah ADMINISTRATOR. Berwenang melihat statistik, rekapitulasi, data pengguna, dan seluruh laporan.";
        }
        $lines[] = "Metrik Utama Sistem:";
        $lines[] = "- Total Seluruh Laporan di Database: $totalReports";
        $lines[] = "- Distribusi Status: " . json_encode($statusCounts, JSON_UNESCAPED_UNICODE);
        $lines[] = "- Distribusi Kategori Fasilitas: " . json_encode($catCounts, JSON_UNESCAPED_UNICODE);
        $lines[] = "- Distribusi Tingkat Prioritas: " . json_encode($prioCounts, JSON_UNESCAPED_UNICODE);
        if ($topCat) {
            $lines[] = "- Kategori Paling Banyak Dilaporkan: " . $topCat['facility_category'] . " (" . $topCat['cnt'] . " laporan)";
        }
        $lines[] = "- Kategori Resmi Fasilitas: " . implode(', ', array_keys(self::FACILITY_CATEGORIES));
        $lines[] = "- Navigasi Menu Masyarakat: Beranda, Laporan Saya (/laporan), Buat Laporan (/laporan/buat), Notifikasi, Pengaturan";
        $lines[] = "- Kategori Resmi Fasilitas: " . implode(', ', array_keys(self::FACILITY_CATEGORIES));
        $lines[] = "- Navigasi Menu Masyarakat: Beranda, Laporan Saya (/laporan), Buat Laporan (/laporan/buat), Notifikasi, Pengaturan";
        
        // Daftar petugas: nomor kontak hanya untuk admin; petugas melihat profilnya sendiri.
        if ($role === 'admin') {
            $lines[] = "\nDaftar Petugas Lapangan Terdaftar (" . count($officers) . " Petugas):";
            foreach ($officers as $off) {
                $lines[] = sprintf("- ID #%d: %s | Area: %s | Tugas Aktif: %d | Kontak: %s",
                    $off['id'], $off['name'], $off['service_area'], (int)$off['active_tasks'], $off['phone']);
            }

            // Data Rekapitulasi & Daftar Seluruh Pengguna untuk Admin
            $userRows = $db->query("SELECT id, name, email, role, is_active FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $totalUsers = count($userRows);
            $activeCount = count(array_filter($userRows, fn($u) => (int)($u['is_active'] ?? 1) === 1));
            $inactiveCount = count(array_filter($userRows, fn($u) => (int)($u['is_active'] ?? 1) === 0));
            $adminCount = count(array_filter($userRows, fn($u) => $u['role'] === 'admin'));
            $petugasCount = count(array_filter($userRows, fn($u) => $u['role'] === 'petugas'));
            $masyarakatCount = count(array_filter($userRows, fn($u) => $u['role'] === 'masyarakat'));

            $lines[] = "\n=== DATA REKAPITULASI & DAFTAR PENGGUNA TERDAFTAR (ADMIN ONLY) ===";
            $lines[] = "- Total Pengguna Terdaftar: $totalUsers";
            $lines[] = "- Rincian Role: $masyarakatCount masyarakat, $adminCount admin, $petugasCount petugas";
            $lines[] = "- Status Akun: $activeCount aktif, $inactiveCount nonaktif";
            $lines[] = "Daftar Nama Pengguna Lengkap:";
            foreach ($userRows as $u) {
                $statusStr = (int)($u['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif';
                $lines[] = sprintf("- ID #%d: %s | Role: %s | Status: %s", $u['id'], $u['name'], ucfirst($u['role']), $statusStr);
            }
        } elseif ($role === 'petugas' && $userId > 0) {
            $mine = Officer::findByUserId($userId);
            if ($mine !== null) {
                $lines[] = "\nPetugas yang bertanya: " . $mine['name'] . " | Area: " . $mine['service_area'];
            }
        } else {
            $lines[] = "\nDaftar Petugas Lapangan Terdaftar (" . count($officers) . " Petugas). Rincian kontak hanya tersedia untuk admin.";
        }

        if ($specificReportData) {
            $lines[] = "\n=== DATA RINCI LAPORAN SPESIFIK YANG DITANYAKAN (#{$specificReportData['id']}) ===";
            $lines[] = "Kode Tiket: LPF-" . str_pad((string)$specificReportData['id'], 5, '0', STR_PAD_LEFT);
            $lines[] = "Judul: " . $specificReportData['title'];
            $lines[] = "Kategori: " . ($specificReportData['facility_category'] ?? '-');
            $lines[] = "Jenis Kerusakan: " . ($specificReportData['damage_type'] ?? '-');
            $lines[] = "Tingkat Prioritas: " . ($specificReportData['priority'] ?? 'Belum ditentukan');
            $lines[] = "Status Terkini: " . $specificReportData['status'];
            $lines[] = "Alamat / Lokasi: " . $specificReportData['address'];
            $lines[] = "Koordinat GPS: Latitude " . $specificReportData['latitude'] . ", Longitude " . $specificReportData['longitude'];
            $lines[] = "Deskripsi: " . $specificReportData['description'];
            $lines[] = "Pelapor: " . ($specificReportData['reporter_name'] ?? 'Warga');
            $lines[] = "Tanggal Lapor: " . $specificReportData['created_at'];
            if (!empty($specificReportData['officer_name'])) {
                $lines[] = "Petugas Ditugaskan: " . $specificReportData['officer_name'] . " (" . ($specificReportData['officer_service_area'] ?? '') . ")";
            }
            if (!empty($specificReportData['timelines'])) {
                $lines[] = "Riwayat Timeline:";
                foreach ($specificReportData['timelines'] as $tl) {
                    $lines[] = sprintf("  * [%s] (%s): %s", $tl['status'], $tl['created_at'], $tl['note'] ?? '-');
                }
            }
            if (!empty($specificReportData['proofs'])) {
                $lines[] = "Bukti Penyelesaian Perbaikan:";
                foreach ($specificReportData['proofs'] as $pr) {
                    $lines[] = "  * Catatan Petugas: " . ($pr['notes'] ?? 'Perbaikan telah selesai.') . " (Waktu: " . ($pr['created_at'] ?? '-') . ")";
                }
            }
        }

        if (!empty($keywordReports)) {
            $lines[] = "\n=== HASIL PENCARIAN REKAMAN LAPORAN RELEVAN DARI DATABASE ===";
            foreach (array_slice($keywordReports, 0, 8) as $kr) {
                $lines[] = sprintf(
                    "- [ID #%d | LPF-%05d] Status: %s | Prioritas: %s | Kategori: %s | Judul: \"%s\" | Lokasi: %s | Petugas: %s",
                    $kr['id'],
                    $kr['id'],
                    $kr['status'],
                    $kr['priority'] ?? '-',
                    $kr['facility_category'] ?? '-',
                    $kr['title'],
                    $kr['address'],
                    $kr['officer_name'] ?? 'Belum ditugaskan'
                );
            }
        } elseif (!empty($userScopeReports)) {
            $lines[] = "\n=== REKAMAN LAPORAN DALAM LINGKUP PENGGUNA INI ===";
            foreach (array_slice($userScopeReports, 0, 8) as $sr) {
                $rid = $sr['id'] ?? $sr['report_id'] ?? 0;
                $lines[] = sprintf(
                    "- [ID #%d | LPF-%05d] Status: %s | Prioritas: %s | Kategori: %s | Judul: \"%s\" | Alamat: %s",
                    $rid,
                    $rid,
                    $sr['status'] ?? $sr['report_status'] ?? '-',
                    $sr['priority'] ?? $sr['report_priority'] ?? '-',
                    $sr['facility_category'] ?? '-',
                    $sr['title'] ?? $sr['report_title'] ?? '-',
                    $sr['address'] ?? '-'
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Mengambil detail lengkap 1 laporan termasuk timeline dan bukti foto.
     */
    public function getFullReportDetail(int $reportId): ?array {
        $report = Report::findById($reportId);
        if (!$report) return null;

        $report['timelines'] = ReportTimeline::getByReport($reportId);
        $report['proofs'] = RepairProof::getByReport($reportId);
        return $report;
    }

    /**
     * Mencari rekaman laporan di database berdasarkan kata kunci pertanyaan pengguna.
     */
    private function searchReportsByKeywords(string $message, ?array $user): array {
        $msg = mb_strtolower($message);
        $filters = [];

        // Deteksi status yang disebut
        $statusTerms = [
            'selesai' => 'Selesai',
            'sedang diperbaiki' => 'Sedang Diperbaiki',
            'perbaikan' => 'Sedang Diperbaiki',
            'ditugaskan' => 'Ditugaskan',
            'diverifikasi' => 'Diverifikasi',
            'menunggu' => 'Menunggu Verifikasi',
            'ditolak' => 'Ditolak'
        ];
        foreach ($statusTerms as $term => $st) {
            if (str_contains($msg, $term)) {
                $filters['status'] = $st;
                break;
            }
        }

        // Deteksi prioritas
        $priorityTerms = ['kritis' => 'Kritis', 'tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah'];
        foreach ($priorityTerms as $term => $prio) {
            if (str_contains($msg, $term) && !isset($filters['status'])) {
                $filters['priority'] = $prio;
                break;
            }
        }

        // Deteksi kategori
        $catTerms = [
            'jalan' => 'Jalan',
            'lampu' => 'Lampu Jalan',
            'pju' => 'Lampu Jalan',
            'trotoar' => 'Trotoar',
            'taman' => 'Taman',
            'sampah' => 'Tempat Sampah',
            'saluran' => 'Saluran Air',
            'drainase' => 'Saluran Air'
        ];
        foreach ($catTerms as $term => $cat) {
            if (str_contains($msg, $term)) {
                $filters['facility_category'] = $cat;
                break;
            }
        }

        // Deteksi lokasi terkenal
        $locTerms = ['thamrin', 'sudirman', 'menteng', 'kebayoran', 'gatot subroto', 'komdak', 'radio dalam', 'jakarta pusat', 'jakarta selatan'];
        foreach ($locTerms as $loc) {
            if (str_contains($msg, $loc)) {
                $filters['search'] = $loc;
                break;
            }
        }

        // Jika tidak ada filter khusus, ambil kata bermakna (>= 4 karakter)
        if (empty($filters)) {
            $words = array_filter(preg_split('/\s+/', $msg), fn($w) => mb_strlen($w) >= 4 && !in_array($w, ['berapa', 'apakah', 'adakah', 'bagaimana', 'laporan', 'tolong', 'siapa']));
            if (!empty($words)) {
                $filters['search'] = reset($words);
            }
        }

        if (empty($filters)) {
            return [];
        }

        $filters['limit'] = 8;
        return Report::getAll($filters);
    }

    /**
     * Menjawab pertanyaan FAQ / Bantuan operasional langsung dalam plain text.
     */
    public function answerFAQ(string $message, ?array $user = null): ?string {
        $msg = mb_strtolower(trim($message));
        $user = $this->resolveCurrentUser($user);

        // 1. Larangan membuat/menjelaskan kode program (Strict Rule)
        if ($this->isCodingRequest($msg)) {
            return "Maaf, saya adalah asisten AI khusus LaporFasilitas. Saya tidak dapat membantu membuat atau menjelaskan kode program. Saya hanya dapat membantu mengenai penggunaan dan fitur aplikasi LaporFasilitas.";
        }

        // 2. Permintaan melihat laporan orang lain / pengguna lain (Strict Privacy)
        if ($this->isOtherUsersReportsRequest($msg)) {
            return $this->getOtherUsersReportsRefusalResponse();
        }

        // 3. Pertanyaan Identitas Pengguna ("Siapa saya?", "Siapakah saya?", "Siapa nama saya?", "Apa role akun saya?", "Apa email saya?")
        if ($this->isUserIdentityQuestion($msg)) {
            return $this->getUserIdentityResponse($user, $msg);
        }

        // 3b. Pertanyaan Manajemen Pengguna / Anggota (Jumlah Anggota, Daftar Anggota, Masyarakat, Admin)
        if ($this->isUserManagementInquiry($msg)) {
            return $this->getUserManagementInquiryResponse($user, $msg);
        }

        // 4. Pertanyaan Jumlah Laporan Pengguna ("Berapa laporan yang sudah saya buat?")
        if ($this->isUserReportCountQuestion($msg)) {
            return $this->getUserReportCountResponse($user, $msg);
        }

        // 5. Pertanyaan Siapa Petugas yang Menangani Laporan Saya
        if ($this->isOfficerHandlingMyReportQuestion($msg)) {
            return $this->getOfficerHandlingMyReportResponse($user);
        }

        // 6. Pertanyaan Petugas ("Apa saja tugas saya?", "Siapa pelapor laporan yang saya tangani?")
        if ($this->isOfficerTasksQuestion($msg)) {
            return $this->getOfficerTasksResponse($user);
        }
        if ($this->isOfficerReporterInfoQuestion($msg)) {
            return $this->getOfficerReporterInfoResponse($user);
        }

        // 7. Pertanyaan Kapan Laporan Saya Terakhir Diperbarui
        if ($this->isLastUpdatedQuestion($msg)) {
            return $this->getLastUpdatedReportResponse($user);
        }

        // 8. Pertanyaan Kategori Fasilitas yang Paling Banyak Dilaporkan
        if ($this->isTopCategoryQuestion($msg)) {
            return $this->getTopCategoryResponse();
        }

        // 9. Pertanyaan Jumlah Laporan Berdasarkan Kategori
        if ($this->isCategoryBreakdownQuestion($msg)) {
            return $this->getCategoryBreakdownResponse();
        }

        // 10. Pertanyaan Jumlah Laporan Status Spesifik ("Berapa laporan yang menunggu verifikasi?", "Berapa laporan yang sudah selesai?")
        $matchedStatus = null;
        if ($this->isStatusCountQuestion($msg, $matchedStatus)) {
            return $this->getStatusCountResponse($matchedStatus);
        }

        // 11. Pertanyaan Total Seluruh Laporan di Sistem
        if ($this->isSystemReportCountQuestion($msg)) {
            return $this->getSystemReportCountResponse();
        }

        // 12. Pertanyaan status laporan pribadi pengguna ("bagaimana status laporan saya", "laporan saya", "daftar laporan saya")
        if ($this->isMyReportsInquiry($msg) || $this->isUserReportStatusQuestion($msg)) {
            return $this->getUserReportsSummary($user);
        }

        // 13. Jika ada nomor/ID laporan spesifik (misal: LPF-00001, #1, #84, status laporan 1)
        if ($this->hasSpecificReportId($message)) {
            return $this->getSpecificReportResponse($message, $user);
        }

        // 14. Panduan Cara Cek / Melihat Status Laporan (didahulukan sebelum pembuatan laporan)
        if ($this->isCheckStatusQuestion($msg)) {
            return $this->getCheckStatusGuide();
        }

        // 15. Panduan Umum Penggunaan & Fitur Aplikasi
        if ($this->isAppUsageQuestion($msg)) {
            return $this->getAppUsageGuide();
        }

        // 16. Panduan Cara Membuat / Mengajukan Laporan
        if ($this->isCreateReportQuestion($msg)) {
            return $this->getCreateReportGuide($user);
        }

        // 17. Kategori Fasilitas Umum yang Tersedia
        if ($this->isCategoriesQuestion($msg)) {
            return $this->getCategoriesGuide();
        }

        // 18. Penjelasan / Arti Status Alur Laporan
        if ($this->isStatusHelpQuestion($msg)) {
            return $this->getStatusExplanationGuide();
        }

        // 19. Panduan Pendaftaran Akun Baru
        if ($this->isRegisterQuestion($msg)) {
            return $this->getRegisterGuide();
        }

        // 20. Pertanyaan Identitas Chatbot ("siapa kamu", dll.)
        if ($this->isIdentityQuestion($msg)) {
            return $this->getIdentityResponse();
        }

        // 21. Pertanyaan Daftar Petugas
        if ($this->isOfficersListInquiry($msg)) {
            return $this->getOfficersListResponse($user);
        }

        // 22. Pertanyaan di luar cakupan aplikasi LaporFasilitas
        if ($this->isOutOfScopeQuestion($msg)) {
            return $this->getOutOfScopeResponse();
        }

        return null;
    }

    /**
     * Fallback deterministik: Membaca database secara langsung jika AI API tidak merespons.
     */
    public function answerDirectFromDatabase(string $message, ?array $user): string {
        $db = Database::getConnection();
        if (!$db) {
            return "Maaf, sistem database sedang tidak dapat diakses. Silakan coba beberapa saat lagi.";
        }

        $user = $this->resolveCurrentUser($user);
        $msg = mb_strtolower(trim($message));

        // 1. Cek jika pertanyaan sebenarnya adalah panduan operasional/FAQ
        $faqAnswer = $this->answerFAQ($message, $user);
        if ($faqAnswer !== null) {
            return $faqAnswer;
        }

        // 2. Cek jika tanya ID spesifik
        if ($this->hasSpecificReportId($message)) {
            return $this->getSpecificReportResponse($message, $user);
        }

        // 3. Cek jika tanya pengguna / anggota / role spesifik
        if ($this->isUserManagementInquiry($msg)) {
            return $this->getUserManagementInquiryResponse($user, $msg);
        }

        // 4. Cek jika tanya total / statistik laporan
        if ($this->matches($msg, ['berapa', 'total', 'jumlah', 'banyak', 'statistik', 'rekap'])) {
            return $this->getSystemReportCountResponse();
        }

        // 5. Cek jika tanya daftar petugas
        if ($this->matches($msg, ['petugas', 'teknisi'])) {
            return $this->getOfficersListResponse($user);
        }

        // 6. Cek jika masyarakat menanyakan laporan miliknya sendiri ("laporan saya")
        if ($this->isMyReportsInquiry($msg) || $this->isUserReportStatusQuestion($msg)) {
            return $this->getUserReportsSummary($user);
        }

        // 7. Fallback ramah dan informatif (tanpa awalan kaku "Saya telah membaca database...")
        return "Halo! Ada yang bisa saya bantu mengenai aplikasi LaporFasilitas?\n\n"
            . "Anda dapat menanyakan hal-hal berikut:\n"
            . "- Panduan cara membuat laporan fasilitas umum\n"
            . "- Daftar kategori fasilitas yang dapat dilaporkan\n"
            . "- Cara mengecek perkembangan status laporan di menu Laporan Saya\n"
            . "- Informasi nomor laporan tertentu (contoh: status laporan #1)\n"
            . "- Rekapitulasi atau statistik laporan di kota";
    }

    /**
     * Penolakan sopan untuk permintaan melihat laporan orang lain (Pembatasan Role Masyarakat).
     */
    public function getOtherUsersReportsRefusalResponse(): string {
        return "Maaf, demi menjaga privasi dan keamanan data, Anda hanya dapat melihat laporan yang Anda ajukan sendiri. Anda tidak memiliki izin untuk melihat laporan milik pengguna lain.";
    }

    /**
     * Respon personalisasi identitas pengguna yang sedang login.
     */
    public function getUserIdentityResponse(?array $user, string $msg): string {
        if ($user === null) {
            return "Anda saat ini belum login ke dalam sistem LaporFasilitas (bertindak sebagai Tamu). Untuk membuat laporan fasilitas atau memantau laporan Anda, silakan masuk melalui menu Masuk atau daftar akun baru via menu Daftar.";
        }

        $user = $this->resolveCurrentUser($user);
        $name = $user['name'] ?? 'Pengguna';
        $role = $user['role'] ?? 'masyarakat';
        $email = $user['email'] ?? '-';

        // Jika secara spesifik menanyakan email
        if (preg_match('/(?:apa\s+)?(?:alamat\s+)?email/i', $msg)) {
            return "Alamat email akun Anda yang terdaftar di LaporFasilitas adalah " . $email . ".";
        }

        // Jika secara spesifik menanyakan role/peran
        if (preg_match('/(?:apa\s+)?(?:role|peran)/i', $msg)) {
            $desc = match ($role) {
                'admin'     => 'Sebagai Administrator, Anda memiliki kewenangan penuh untuk mengelola pengguna, memvalidasi laporan masuk, menugaskan petugas lapangan, dan memantau statistik fasilitas kota.',
                'petugas'   => 'Sebagai Petugas Lapangan, Anda memiliki hak akses untuk menerima tugas perbaikan fasilitas, memperbarui status pengerjaan, dan mengunggah foto bukti penyelesaian.',
                default     => 'Sebagai Masyarakat, Anda memiliki hak akses untuk membuat laporan fasilitas umum, memantau perkembangan penanganan laporan Anda di menu Laporan Saya, dan menerima notifikasi perbaikan.'
            };
            return "Role akun Anda adalah " . ucfirst($role) . ".\n\n" . $desc;
        }

        // Respon personalisasi identitas utama
        if ($role === 'petugas') {
            $area = !empty($user['service_area']) ? " (Area: " . $user['service_area'] . ")" : "";
            return "Halo, " . $name . "! Anda sedang login sebagai Petugas Lapangan di LaporFasilitas" . $area . ". Saya siap membantu Anda memantau tugas perbaikan fasilitas.";
        }

        if ($role === 'admin') {
            return "Halo, " . $name . "! Anda sedang login sebagai Administrator di LaporFasilitas. Saya siap membantu Anda mengelola data laporan, penugasan petugas, dan rekapitulasi fasilitas umum.";
        }

        // Masyarakat
        return "Halo, " . $name . "! Anda sedang login menggunakan akun masyarakat di LaporFasilitas. Saya siap membantu Anda mengelola dan memantau laporan fasilitas.";
    }

    /**
     * Respon perhitungan jumlah laporan milik pengguna yang sedang login.
     */
    public function getUserReportCountResponse(?array $user, string $msg): string {
        if ($user === null) {
            return "Silakan login menggunakan akun masyarakat untuk melihat jumlah laporan Anda.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? 'masyarakat';
        $userId = (int)($user['id'] ?? 0);
        $db = Database::getConnection();

        if ($role === 'admin') {
            $total = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
            return "Sebagai administrator, Anda dapat mengelola seluruh laporan di sistem. Total seluruh laporan yang tercatat dalam sistem LaporFasilitas saat ini adalah " . $total . " laporan.";
        }

        if ($role === 'petugas') {
            $officer = Officer::findByUserId($userId);
            $taskCount = 0;
            if ($officer) {
                $taskCount = count(Officer::assignedReports((int)$officer['id']));
            }
            return "Sebagai petugas lapangan, Anda tidak membuat laporan masyarakat melainkan menangani tugas perbaikan fasilitas. Saat ini terdapat " . $taskCount . " tugas perbaikan yang ditugaskan kepada Anda.";
        }

        // Role Masyarakat
        $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        $total = (int)$stmt->fetchColumn();

        if ($total === 0) {
            return "Anda belum membuat laporan fasilitas. Untuk membuat laporan baru, silakan buka menu Buat Laporan pada navigasi atas.";
        }

        $stmt2 = $db->prepare("SELECT status, COUNT(*) as cnt FROM reports WHERE user_id = :uid GROUP BY status");
        $stmt2->execute(['uid' => $userId]);
        $breakdown = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);

        $breakdownLines = [];
        $order = ['Selesai', 'Sedang Diperbaiki', 'Ditugaskan', 'Diverifikasi', 'Menunggu Verifikasi', 'Ditolak'];
        foreach ($order as $st) {
            if (isset($breakdown[$st])) {
                $breakdownLines[] = "- " . $st . ": " . $breakdown[$st] . " laporan";
            }
        }
        foreach ($breakdown as $st => $c) {
            if (!in_array($st, $order)) {
                $breakdownLines[] = "- " . $st . ": " . $c . " laporan";
            }
        }

        return "Anda telah membuat " . $total . " laporan fasilitas di LaporFasilitas.\n\n"
            . "Rincian status laporan Anda:\n"
            . implode("\n", $breakdownLines) . "\n\n"
            . "Anda dapat melihat detail masing-masing laporan melalui menu Laporan Saya.";
    }

    /**
     * Respon informasi petugas yang menangani laporan milik masyarakat.
     */
    public function getOfficerHandlingMyReportResponse(?array $user): string {
        if ($user === null) {
            return "Silakan login menggunakan akun masyarakat untuk melihat informasi petugas penanggung jawab laporan Anda.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? 'masyarakat';
        $userId = (int)($user['id'] ?? 0);
        $db = Database::getConnection();

        if ($role === 'petugas') {
            return "Anda adalah petugas lapangan yang bertugas menangani perbaikan fasilitas umum. Untuk melihat tugas yang menjadi tanggung jawab Anda, silakan tanyakan 'Apa saja tugas saya?' atau buka menu Tugas Perbaikan.";
        }

        if ($role === 'admin') {
            return "Sebagai administrator, Anda dapat memantau dan mengatur penugasan petugas untuk setiap laporan melalui halaman Verifikasi & Penugasan di Dashboard Admin.";
        }

        // Masyarakat
        $sql = "
            SELECT r.id, r.title, r.status, ou.name AS officer_name, o.service_area
            FROM reports r
            LEFT JOIN (
                SELECT a1.report_id, a1.officer_id
                FROM assignments a1
                JOIN (SELECT report_id, MAX(id) AS max_id FROM assignments GROUP BY report_id) a2 ON a1.id = a2.max_id
            ) a ON a.report_id = r.id
            LEFT JOIN officers o ON a.officer_id = o.id
            LEFT JOIN users ou ON o.user_id = ou.id
            WHERE r.user_id = :uid
            ORDER BY r.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($reports)) {
            return "Anda belum memiliki laporan fasilitas yang diajukan. Untuk membuat laporan baru, silakan buka menu Buat Laporan.";
        }

        $out = "Berikut adalah informasi petugas yang menangani laporan Anda:\n\n";
        foreach ($reports as $r) {
            $formattedId = 'LPF-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT);
            if (!empty($r['officer_name'])) {
                $area = !empty($r['service_area']) ? " (" . $r['service_area'] . ")" : "";
                $out .= "- [" . $formattedId . "] \"" . $r['title'] . "\": Ditangani oleh " . $r['officer_name'] . $area . "\n";
            } else {
                $out .= "- [" . $formattedId . "] \"" . $r['title'] . "\": Belum ditugaskan (status saat ini: " . $r['status'] . ")\n";
            }
        }
        $out .= "\nAnda dapat memantau foto bukti pengerjaan dan perkembangan lebih lanjut melalui menu Laporan Saya.";
        return trim($out);
    }

    /**
     * Respon daftar tugas yang ditugaskan kepada petugas lapangan yang sedang login.
     */
    public function getOfficerTasksResponse(?array $user): string {
        if ($user === null) {
            return "Silakan login menggunakan akun petugas lapangan untuk mengakses daftar tugas.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? '';
        $userId = (int)($user['id'] ?? 0);

        if ($role !== 'petugas') {
            return "Informasi penugasan tugas perbaikan hanya dapat diakses oleh petugas lapangan dan administrator.";
        }

        $officer = Officer::findByUserId($userId);
        if (!$officer) {
            return "Profil petugas lapangan Anda belum terdaftar di sistem.";
        }

        $tasks = Officer::assignedReports((int)$officer['id']);
        if (empty($tasks)) {
            return "Anda belum memiliki tugas perbaikan yang ditugaskan saat ini.";
        }

        $out = "Daftar tugas perbaikan yang ditugaskan kepada Anda:\n\n";
        $i = 1;
        foreach ($tasks as $t) {
            $rid = $t['id'] ?? $t['report_id'] ?? 0;
            $formattedId = 'LPF-' . str_pad((string)$rid, 5, '0', STR_PAD_LEFT);
            $st = $t['status'] ?? $t['report_status'] ?? '-';
            $prio = $t['priority'] ?? $t['report_priority'] ?? 'Sedang';
            $addr = $t['address'] ?? $t['report_address'] ?? '-';
            $out .= $i . ". [" . $formattedId . "] \"" . ($t['title'] ?? $t['report_title'] ?? '-') . "\"\n"
                . "   Status: " . $st . " | Prioritas: " . $prio . "\n"
                . "   Lokasi: " . $addr . "\n\n";
            $i++;
        }
        $out .= "Silakan buka lembar kerja Petugas di website untuk memulai atau menyelesaikan perbaikan.";
        return trim($out);
    }

    /**
     * Respon informasi pelapor untuk tugas yang ditangani oleh petugas lapangan.
     */
    public function getOfficerReporterInfoResponse(?array $user): string {
        if ($user === null) {
            return "Silakan login menggunakan akun petugas lapangan untuk mengakses informasi pelapor tugas.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? '';
        $userId = (int)($user['id'] ?? 0);
        $db = Database::getConnection();

        if ($role !== 'petugas' && $role !== 'admin') {
            return "Informasi pelapor hanya dapat diakses oleh petugas lapangan yang ditugaskan atau administrator.";
        }

        $officer = Officer::findByUserId($userId);
        if (!$officer && $role === 'petugas') {
            return "Profil petugas Anda belum terdaftar di sistem.";
        }

        $sql = "
            SELECT r.id, r.title, r.address, u.name as reporter_name
            FROM assignments a
            JOIN reports r ON a.report_id = r.id
            JOIN users u ON r.user_id = u.id
            WHERE " . ($role === 'petugas' ? "a.officer_id = :offId AND " : "") . "a.status != 'Dibatalkan'
            ORDER BY a.id DESC
        ";
        $stmt = $db->prepare($sql);
        if ($role === 'petugas') {
            $stmt->execute(['offId' => (int)$officer['id']]);
        } else {
            $stmt->execute();
        }
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tasks)) {
            return "Belum ada laporan aktif yang ditugaskan kepada Anda saat ini.";
        }

        $out = "Berikut adalah informasi pelapor untuk tugas perbaikan yang ditugaskan kepada Anda:\n\n";
        foreach ($tasks as $t) {
            $formattedId = 'LPF-' . str_pad((string)$t['id'], 5, '0', STR_PAD_LEFT);
            $out .= "- [" . $formattedId . "] \"" . $t['title'] . "\": Dilaporkan oleh " . $t['reporter_name'] . " (Lokasi: " . $t['address'] . ")\n";
        }
        $out .= "\nInformasi ini dapat Anda gunakan untuk koordinasi penanganan fasilitas di lapangan.";
        return trim($out);
    }

    /**
     * Respon waktu pembaruan terakhir laporan milik pengguna.
     */
    public function getLastUpdatedReportResponse(?array $user): string {
        if ($user === null) {
            return "Silakan login menggunakan akun masyarakat untuk melihat pembaruan laporan Anda.";
        }

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? 'masyarakat';
        $userId = (int)($user['id'] ?? 0);
        $db = Database::getConnection();

        if ($role === 'masyarakat') {
            $stmt = $db->prepare("SELECT id, title, status, updated_at FROM reports WHERE user_id = :uid ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute(['uid' => $userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                return "Anda belum memiliki laporan fasilitas yang diajukan.";
            }
            $formattedId = 'LPF-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT);
            $time = date('d M Y, H:i', strtotime($r['updated_at'])) . " WIB";
            return "Laporan Anda yang terakhir diperbarui adalah [" . $formattedId . "] \"" . $r['title'] . "\" pada " . $time . " dengan status '" . $r['status'] . "'.";
        }

        $r = $db->query("SELECT id, title, status, updated_at FROM reports ORDER BY updated_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$r) return "Belum ada laporan dalam sistem.";
        $formattedId = 'LPF-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT);
        $time = date('d M Y, H:i', strtotime($r['updated_at'])) . " WIB";
        return "Laporan yang terakhir diperbarui dalam sistem adalah [" . $formattedId . "] \"" . $r['title'] . "\" pada " . $time . " dengan status '" . $r['status'] . "'.";
    }

    /**
     * Respon kategori fasilitas yang paling banyak dilaporkan.
     */
    public function getTopCategoryResponse(): string {
        $db = Database::getConnection();
        if (!$db) return "Koneksi database sedang tidak tersedia.";

        $total = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        $stmt = $db->query("SELECT facility_category, COUNT(*) as cnt FROM reports WHERE facility_category IS NOT NULL GROUP BY facility_category ORDER BY cnt DESC LIMIT 1");
        $top = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$top) {
            return "Belum ada data kategori laporan yang tercatat dalam sistem database.";
        }

        return "Berdasarkan data database LaporFasilitas, kategori fasilitas yang paling banyak dilaporkan adalah " . $top['facility_category'] . " dengan total " . $top['cnt'] . " laporan (dari total " . $total . " laporan di sistem).";
    }

    /**
     * Respon rincian jumlah laporan berdasarkan kategori.
     */
    public function getCategoryBreakdownResponse(): string {
        $db = Database::getConnection();
        if (!$db) return "Koneksi database sedang tidak tersedia.";

        $stmt = $db->query("SELECT facility_category, COUNT(*) as cnt FROM reports WHERE facility_category IS NOT NULL GROUP BY facility_category ORDER BY cnt DESC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (empty($rows)) {
            return "Belum ada data kategori laporan yang tercatat di database.";
        }

        $total = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        $out = "Jumlah laporan fasilitas berdasarkan kategori di LaporFasilitas (Total: " . $total . " laporan):\n\n";
        foreach ($rows as $r) {
            $out .= "- " . $r['facility_category'] . ": " . $r['cnt'] . " laporan\n";
        }
        return trim($out);
    }

    /**
     * Respon jumlah laporan untuk status spesifik.
     */
    public function getStatusCountResponse(string $status): string {
        $db = Database::getConnection();
        if (!$db) return "Koneksi database sedang tidak tersedia.";

        $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE status = :st");
        $stmt->execute(['st' => $status]);
        $count = (int)$stmt->fetchColumn();

        return "Berdasarkan data database LaporFasilitas, jumlah laporan dengan status '" . $status . "' saat ini adalah " . $count . " laporan.";
    }

    /**
     * Respon total seluruh laporan di sistem.
     */
    public function getSystemReportCountResponse(): string {
        $db = Database::getConnection();
        if (!$db) return "Koneksi database sedang tidak tersedia.";

        $total = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        $statusCounts = $db->query("SELECT status, COUNT(*) FROM reports GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $parts = [];
        foreach ($statusCounts as $st => $c) {
            $parts[] = $st . ": " . $c;
        }

        return "Berdasarkan data real-time database LaporFasilitas, total seluruh laporan yang masuk saat ini adalah " . $total . " laporan.\n\n"
            . "Rincian laporan:\n"
            . "- Status: " . implode(', ', $parts);
    }

    /**
     * Respon daftar petugas lapangan (disesuaikan dengan role).
     */
    public function getOfficersListResponse(?array $user): string {
        $db = Database::getConnection();
        if (!$db) return "Koneksi database sedang tidak tersedia.";

        $user = $this->resolveCurrentUser($user);
        $role = $user['role'] ?? 'tamu';

        if ($role === 'admin') {
            $officers = $db->query("
                SELECT u.name, u.role, u.is_active, o.service_area, o.phone,
                       (SELECT COUNT(*) FROM assignments a WHERE a.officer_id = o.id AND a.status != 'Selesai') as active_tasks
                FROM officers o 
                JOIN users u ON o.user_id = u.id
                ORDER BY u.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (empty($officers)) {
                return "Belum ada petugas lapangan yang terdaftar di database.";
            }

            $activeCount = count(array_filter($officers, fn($o) => (int)($o['is_active'] ?? 1) === 1));
            $inactiveCount = count(array_filter($officers, fn($o) => (int)($o['is_active'] ?? 1) === 0));
            $statusNote = $inactiveCount > 0 ? " ($activeCount Aktif, $inactiveCount Nonaktif)" : " ($activeCount Aktif)";

            $out = "Daftar petugas lapangan terdaftar di database (" . count($officers) . " petugas)" . $statusNote . ":\n\n";
            foreach ($officers as $off) {
                $statusLabel = (int)($off['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif';
                $out .= "- " . $off['name'] . ": Area " . $off['service_area'] . " (Kontak: " . $off['phone'] . " | Role: Petugas | Status: " . $statusLabel . ")\n";
            }
            return trim($out);
        }

        if ($role === 'petugas') {
            $mine = Officer::findByUserId((int)($user['id'] ?? 0));
            if ($mine) {
                return "Profil petugas Anda:\n\n- " . ($user['name'] ?? '-') . ": Area " . $mine['service_area'] . " (Kontak: " . $mine['phone'] . ")";
            }
        }

        if ($user === null) {
            return "Silakan login untuk melihat informasi laporan pribadi Anda. Setelah login, buka menu Laporan Saya atau tanyakan lagi di sini.";
        }

        return "Informasi daftar petugas hanya dapat diakses oleh admin. Nama petugas yang menangani laporan milik Anda tercantum pada detail laporan tersebut.";
    }

    /**
     * Respon query manajemen pengguna / anggota untuk role Admin.
     * Mengambil nama sebenarnya, role, dan status akun langsung dari database.
     */
    public function getUserManagementInquiryResponse(?array $user, string $msg): string {
        $db = Database::getConnection();
        if (!$db) {
            return "Maaf, sistem database sedang tidak dapat diakses. Silakan coba beberapa saat lagi.";
        }

        $user = $this->resolveCurrentUser($user);
        if ($user === null) {
            return "Silakan login terlebih dahulu untuk mengakses informasi tersebut.";
        }
        if (($user['role'] ?? '') !== 'admin') {
            return "Maaf, informasi pengguna aplikasi hanya dapat diakses oleh admin demi menjaga privasi dan keamanan data anggota.";
        }

        $stmt = $db->query("SELECT id, name, email, role, is_active FROM users ORDER BY id ASC");
        $allUsers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $totalUsers = count($allUsers);
        $activeUsers = array_filter($allUsers, fn($u) => (int)($u['is_active'] ?? 1) === 1);
        $inactiveUsers = array_filter($allUsers, fn($u) => (int)($u['is_active'] ?? 1) === 0);
        $activeCount = count($activeUsers);
        $inactiveCount = count($inactiveUsers);

        $masyarakatList = array_values(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'masyarakat'));
        $petugasList = array_values(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'petugas'));
        $adminList = array_values(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'admin'));

        $masyarakatCount = count($masyarakatList);
        $petugasCount = count($petugasList);
        $adminCount = count($adminList);

        // 1. Cabang Admin Spesifik ("Siapa administrator aplikasi?", "Siapa admin aplikasi?", dll.)
        if ($this->isAdminRoleInquiry($msg)) {
            $activeAdmin = count(array_filter($adminList, fn($u) => (int)($u['is_active'] ?? 1) === 1));
            $inactiveAdmin = count(array_filter($adminList, fn($u) => (int)($u['is_active'] ?? 1) === 0));
            $statusNote = $inactiveAdmin > 0 ? " ($activeAdmin Aktif, $inactiveAdmin Nonaktif)" : " ($activeAdmin Aktif)";

            $out = "Berdasarkan data database LaporFasilitas, terdapat " . $adminCount . " pengguna dengan role Administrator" . $statusNote . ":\n\n";
            $out .= "Daftar administrator aplikasi:\n";
            $no = 1;
            foreach ($adminList as $adm) {
                $st = (int)($adm['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif';
                $out .= $no++ . ". " . $adm['name'] . " (Role: Admin | Status: " . $st . ")\n";
            }
            return trim($out);
        }

        // 2. Cabang Masyarakat Spesifik ("Siapa saja masyarakat yang terdaftar?", "Siapa saja pengguna dengan role masyarakat?", dll.)
        if ($this->isMasyarakatRoleInquiry($msg)) {
            $activeMasyarakat = count(array_filter($masyarakatList, fn($u) => (int)($u['is_active'] ?? 1) === 1));
            $inactiveMasyarakat = count(array_filter($masyarakatList, fn($u) => (int)($u['is_active'] ?? 1) === 0));
            $statusNote = $inactiveMasyarakat > 0 ? " ($activeMasyarakat Aktif, $inactiveMasyarakat Nonaktif)" : " ($activeMasyarakat Aktif)";

            $out = "Berdasarkan data database LaporFasilitas, terdapat " . $masyarakatCount . " pengguna dengan role Masyarakat" . $statusNote . ":\n\n";
            $out .= "Daftar nama masyarakat terdaftar:\n";
            $no = 1;
            $maxShow = 25;
            foreach (array_slice($masyarakatList, 0, $maxShow) as $m) {
                $st = (int)($m['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif';
                $out .= $no++ . ". " . $m['name'] . " (Role: Masyarakat | Status: " . $st . ")\n";
            }
            if ($masyarakatCount > $maxShow) {
                $out .= "\n... dan " . ($masyarakatCount - $maxShow) . " masyarakat lainnya. Daftar lengkap dapat dilihat di menu Manajemen Pengguna (/admin/pengguna).\n";
            }
            return trim($out);
        }

        // 3. Cabang Keseluruhan (Jumlah Anggota / Total Pengguna / Daftar Semua Anggota)
        $out = "Berdasarkan data real-time database LaporFasilitas, total pengguna yang terdaftar di aplikasi ini adalah " . $totalUsers . " pengguna.\n\n";
        $out .= "Rincian per role: " . $masyarakatCount . " masyarakat, " . $adminCount . " admin, " . $petugasCount . " petugas.\n\n";
        $out .= "Status akun:\n";
        $out .= "- Akun Aktif: " . $activeCount . " pengguna\n";
        $out .= "- Akun Nonaktif: " . $inactiveCount . " pengguna\n";
        if ($inactiveCount > 0) {
            $inactiveNames = array_map(fn($u) => $u['name'], $inactiveUsers);
            $out .= "  (Pengguna nonaktif: " . implode(', ', $inactiveNames) . ")\n";
        }
        $out .= "\nDaftar nama pengguna:\n";
        $no = 1;
        $maxShow = 25;
        foreach (array_slice($allUsers, 0, $maxShow) as $u) {
            $roleLabel = ucfirst($u['role']);
            $statusLabel = (int)($u['is_active'] ?? 1) === 1 ? 'Aktif' : 'Nonaktif';
            $out .= $no++ . ". " . $u['name'] . " (Role: " . $roleLabel . " | Status: " . $statusLabel . ")\n";
        }
        if ($totalUsers > $maxShow) {
            $out .= "\n... dan " . ($totalUsers - $maxShow) . " pengguna lainnya. Daftar lengkap dapat dilihat di menu Manajemen Pengguna (/admin/pengguna).\n";
        }
        return trim($out);
    }

    /**
     * Respon detail 1 laporan spesifik dengan validasi ketat hak akses role.
     */
    public function getSpecificReportResponse(string $message, ?array $user): string {
        if (!preg_match('/(?:lpf[\s\-_]*|#\s*)0*(\d{1,6})/i', $message, $m) && !preg_match('/(?:laporan|tiket|id)[^\d]{0,8}(\d{1,6})/i', $message, $m)) {
            return "Format nomor laporan tidak valid.";
        }
        $id = (int)$m[1];
        if ($user === null) {
            return "Silakan login untuk melihat informasi laporan pribadi Anda. Setelah login, buka menu Laporan Saya atau tanyakan lagi di sini.";
        }

        $user = $this->resolveCurrentUser($user);
        $r = $this->getFullReportDetail($id);
        if (!$r) {
            return "Laporan dengan ID " . $id . " tidak ditemukan dalam database.";
        }

        // Cek hak akses role
        if (!$this->canUserViewReport($r, $user)) {
            $role = $user['role'] ?? 'masyarakat';
            if ($role === 'masyarakat') {
                return "Maaf, laporan #" . $id . " tidak ditemukan atau Anda tidak memiliki izin untuk melihat laporan tersebut karena bukan milik akun Anda. Demi menjaga privasi dan keamanan data, masyarakat hanya dapat mengakses laporan yang diajukan oleh diri sendiri.";
            } elseif ($role === 'petugas') {
                return "Maaf, laporan #" . $id . " tidak ditemukan atau tidak ditugaskan kepada Anda.";
            }
            return "Maaf, Anda tidak memiliki izin untuk mengakses laporan #" . $id . ".";
        }

        // Cek jika pertanyaan spesifik menanyakan siapa petugas laporan tersebut
        $msgLower = mb_strtolower($message);
        if (str_contains($msgLower, 'petugas') || str_contains($msgLower, 'teknisi')) {
            $formattedCode = 'LPF-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            if (!empty($r['officer_name'])) {
                return "Laporan " . $formattedCode . " (\"" . $r['title'] . "\") ditangani oleh petugas " . $r['officer_name'] . " (" . ($r['officer_service_area'] ?? '') . ").";
            }
            return "Laporan " . $formattedCode . " (\"" . $r['title'] . "\") saat ini berstatus '" . $r['status'] . "' dan belum ditugaskan kepada petugas lapangan.";
        }

        // Tampilkan detail laporan
        $resp = "Informasi Laporan LPF-" . str_pad((string)$id, 5, '0', STR_PAD_LEFT) . ":\n\n"
            . "- Judul: " . ($r['title'] ?? '-') . "\n"
            . "- Kategori: " . ($r['facility_category'] ?? '-') . "\n"
            . "- Status: " . ($r['status'] ?? '-') . "\n"
            . "- Prioritas: " . ($r['priority'] ?? '-') . "\n"
            . "- Lokasi: " . ($r['address'] ?? '-') . "\n";

        if (!empty($r['officer_name'])) {
            $resp .= "- Petugas: " . $r['officer_name'] . " (" . ($r['officer_service_area'] ?? '') . ")\n";
        }
        if (!empty($r['proofs'])) {
            $resp .= "- Catatan Hasil Perbaikan: " . ($r['proofs'][0]['notes'] ?? 'Selesai dikerjakan.') . "\n";
        }
        return trim($resp);
    }

    /**
     * Panduan Lengkap Cara Membuat Laporan
     */
    public function getCreateReportGuide(?array $user = null): string {
        $isLoggedIn = !empty($user);
        $role = $user['role'] ?? '';
        
        $step1 = ($isLoggedIn && $role === 'masyarakat')
            ? "1. Masuk ke Akun: Anda saat ini sudah masuk sebagai " . htmlspecialchars($user['name'] ?? 'Masyarakat') . " (akun masyarakat aktif)."
            : "1. Masuk ke Akun: Pastikan Anda telah login menggunakan akun masyarakat. Jika belum memiliki akun, silakan klik menu \"Daftar\" pada navigasi atas.";

        return "Panduan langkah-langkah membuat laporan kerusakan fasilitas umum:\n\n"
            . $step1 . "\n"
            . "2. Buka Formulir: Klik menu \"Buat Laporan\" pada navigasi atas atau tombol \"Buat Laporan Baru\" pada dashboard Anda.\n"
            . "3. Isi Judul Laporan: Tuliskan judul yang ringkas dan menjelaskan permasalahan secara jelas (minimal 5 karakter).\n"
            . "4. Pilih Kategori Fasilitas: Pilih salah satu kategori yang sesuai (Jalan, Lampu Jalan, Trotoar, Taman, Tempat Sampah, Saluran Air, atau Fasilitas Umum Lainnya).\n"
            . "5. Jelaskan Kerusakan: Tuliskan deskripsi kerusakan fasilitas secara rinci pada kolom Deskripsi Kerusakan (minimal 10 karakter).\n"
            . "6. Unggah Foto Bukti: Unggah foto bukti kerusakan fasilitas yang jelas (wajib, mendukung format JPG, PNG, atau WEBP, maksimal 5 MB).\n"
            . "7. Tentukan Titik Lokasi: Tandai lokasi kerusakan pada peta Leaflet atau klik tombol \"Gunakan Lokasi GPS Saya\" untuk mengisi titik koordinat secara otomatis.\n"
            . "8. Isi Alamat / Patokan: Tuliskan detail nama jalan, nomor bangunan, atau patokan di sekitar lokasi kejadian (minimal 5 karakter).\n"
            . "9. Periksa Kembali: Pastikan seluruh data dan foto yang dimasukkan sudah benar dan lengkap.\n"
            . "10. Kirim Laporan: Klik tombol \"Kirim Laporan Sekarang\".\n\n"
            . "Setelah laporan berhasil dikirim, Anda dapat memantau proses verifikasi dan perbaikannya secara berkala melalui menu \"Laporan Saya\".";
    }

    /**
     * Panduan Cara Melihat / Mengecek Status Laporan
     */
    public function getCheckStatusGuide(): string {
        return "Panduan cara melihat dan memantau status laporan Anda:\n\n"
            . "1. Pastikan Anda sudah login menggunakan akun masyarakat.\n"
            . "2. Buka menu \"Laporan Saya\" pada navigasi atas.\n"
            . "3. Di halaman tersebut, Anda akan melihat ringkasan statistik laporan (Total Laporan, Menunggu, Diproses, dan Selesai) serta daftar seluruh laporan yang pernah Anda buat.\n"
            . "4. Anda dapat menggunakan kolom pencarian atau filter status untuk mempermudah menemukan laporan tertentu.\n"
            . "5. Klik tombol \"Lihat Detail\" pada salah satu laporan untuk melihat:\n"
            . "   - Status terkini penanganan laporan.\n"
            . "   - Riwayat alur proses (timeline verifikasi, penugasan, dan pengerjaan).\n"
            . "   - Petugas lapangan yang ditugaskan menangani perbaikan.\n"
            . "   - Foto bukti hasil pengerjaan perbaikan dari petugas jika perbaikan telah selesai.";
    }

    /**
     * Daftar Kategori Fasilitas Resmi
     */
    public function getCategoriesGuide(): string {
        $out = "Kategori fasilitas umum yang dapat Anda laporkan di LaporFasilitas:\n\n";
        $i = 1;
        foreach (self::FACILITY_CATEGORIES as $cat => $desc) {
            $out .= $i . ". " . $cat . ": " . $desc . "\n";
            $i++;
        }
        $out .= "\nSilakan pilih kategori yang paling sesuai saat mengisi formulir di menu Buat Laporan.";
        return $out;
    }

    /**
     * Penjelasan Arti Status Laporan
     */
    public function getStatusExplanationGuide(): string {
        $out = "Arti status alur penanganan laporan di LaporFasilitas:\n\n";
        foreach (self::STATUS_HELP as $st => $desc) {
            $out .= "- " . $st . ": " . $desc . "\n";
        }
        $out .= "\nAlur perjalanan laporan: Menunggu Verifikasi -> Diverifikasi -> Ditugaskan -> Sedang Diperbaiki -> Selesai.";
        return $out;
    }

    /**
     * Panduan Ringkas Penggunaan Aplikasi
     */
    public function getAppUsageGuide(): string {
        return "Panduan penggunaan aplikasi LaporFasilitas untuk masyarakat:\n\n"
            . "LaporFasilitas adalah platform pelaporan kerusakan fasilitas umum kota yang terintegrasi langsung dengan petugas perbaikan dan administrator.\n\n"
            . "Fitur dan alur penggunaan utama:\n"
            . "1. Pendaftaran & Masuk: Buat akun masyarakat via menu \"Daftar\", lalu login via menu \"Masuk\".\n"
            . "2. Ajukan Laporan: Gunakan menu \"Buat Laporan\" untuk melaporkan kerusakan fasilitas lengkap dengan foto bukti dan titik lokasi peta GPS.\n"
            . "3. Pantau Status: Pantau tindak lanjut perbaikan kapan saja melalui menu \"Laporan Saya\".\n"
            . "4. Notifikasi Otomatis: Dapatkan pemberitahuan status melalui menu lonceng notifikasi website dan notifikasi Telegram di menu Pengaturan Akun.\n"
            . "5. Asisten AI: Gunakan jendela obrolan ini kapan saja jika Anda membutuhkan panduan, informasi status laporan, maupun data statistik fasilitas.";
    }

    /**
     * Panduan Pendaftaran Akun Baru
     */
    public function getRegisterGuide(): string {
        return "Panduan pendaftaran akun masyarakat di LaporFasilitas:\n\n"
            . "1. Buka halaman utama LaporFasilitas, lalu klik tombol \"Daftar\" pada navigasi atas kanan.\n"
            . "2. Masukkan Nama Lengkap Anda (minimal 3 karakter).\n"
            . "3. Masukkan Alamat Email aktif Anda.\n"
            . "4. Masukkan Kata Sandi (minimal 6 karakter) dan ulangi pada kolom Konfirmasi Kata Sandi.\n"
            . "5. Klik tombol \"Daftar Akun Sekarang\".\n"
            . "6. Setelah berhasil, silakan masuk melalui menu \"Masuk\" menggunakan email dan kata sandi yang telah didaftarkan.";
    }

    /**
     * Identitas Chatbot
     */
    public function getIdentityResponse(): string {
        return "Halo! Saya adalah Asisten AI LaporFasilitas. Saya adalah asisten cerdas resmi yang membantu penggunaan aplikasi pelaporan fasilitas umum LaporFasilitas.\n\n"
            . "Saya dapat membantu Anda:\n"
            . "- Memberikan panduan cara membuat laporan dan cara mengecek status perbaikan.\n"
            . "- Menjelaskan kategori fasilitas publik yang dapat dilaporkan.\n"
            . "- Memeriksa status dan riwayat laporan fasilitas umum Anda secara real-time.\n"
            . "- Menyajikan data statistik rekapitulasi laporan kerusakan di kota.\n"
            . "- Menjelaskan fitur dan alur penanganan laporan pada aplikasi LaporFasilitas.";
    }

    /**
     * Respons di Luar Cakupan Aplikasi
     */
    public function getOutOfScopeResponse(): string {
        return "Maaf, saya adalah asisten khusus LaporFasilitas. Saya hanya dapat membantu seputar aplikasi LaporFasilitas, seperti panduan cara membuat laporan, kategori fasilitas, pemantauan status perbaikan, dan fitur aplikasi.";
    }

    /**
     * Menampilkan Rekapitulasi Laporan Milik Pengguna yang Sedang Login
     */
    public function getUserReportsSummary(?array $user): string {
        if ($user === null) {
            return "Silakan login menggunakan akun masyarakat untuk melihat riwayat dan status laporan Anda. Setelah login, buka menu Laporan Saya atau tanyakan kembali di sini.";
        }
        $role = $user['role'] ?? '';
        $userId = (int)($user['id'] ?? 0);

        if ($role === 'admin') {
            $reports = Report::getAll(['limit' => 5]);
            if (empty($reports)) {
                return "Belum ada laporan yang tercatat dalam sistem database.";
            }
            $out = "Daftar 5 laporan terbaru dalam sistem LaporFasilitas:\n\n";
            foreach ($reports as $r) {
                $out .= sprintf("- [LPF-%05d] \"%s\" | Status: %s | Kategori: %s\n",
                    $r['id'], $r['title'], $r['status'], $r['facility_category'] ?? '-');
            }
            $out .= "\nDetail seluruh laporan dapat Anda kelola melalui Dashboard Admin.";
            return $out;
        }

        if ($role === 'petugas') {
            $officer = Officer::findByUserId($userId);
            if (!$officer) return "Data profil petugas Anda belum terdaftar.";
            $tasks = Officer::assignedReports((int)$officer['id']);
            if (empty($tasks)) {
                return "Anda belum memiliki tugas perbaikan yang ditugaskan saat ini.";
            }
            $out = "Daftar tugas perbaikan Anda:\n\n";
            foreach (array_slice($tasks, 0, 5) as $t) {
                $rid = $t['id'] ?? $t['report_id'] ?? 0;
                $st = $t['status'] ?? $t['report_status'] ?? '-';
                $out .= sprintf("- [LPF-%05d] \"%s\" | Status: %s\n", $rid, $t['title'] ?? '-', $st);
            }
            $out .= "\nSilakan buka lembar kerja Tugas Perbaikan untuk memulai pengerjaan.";
            return $out;
        }

        // Masyarakat
        $reports = Report::getByUser($userId);
        if (empty($reports)) {
            return "Anda belum memiliki laporan fasilitas yang diajukan. Untuk membuat laporan baru, silakan buka menu Buat Laporan.";
        }

        $out = "Berikut adalah daftar laporan fasilitas yang pernah Anda ajukan:\n\n";
        foreach (array_slice($reports, 0, 5) as $r) {
            $out .= sprintf("- [LPF-%05d] \"%s\"\n  Status: %s | Kategori: %s | Lokasi: %s\n",
                $r['id'], $r['title'], $r['status'], $r['facility_category'] ?? '-', $r['address'] ?? '-');
        }
        $out .= "\nUntuk melihat timeline lengkap atau bukti foto perbaikan, silakan buka menu Laporan Saya.";
        return $out;
    }

    // =========================================================================
    // INTENT DETECTION HELPER METHODS
    // =========================================================================

    public function isCodingRequest(string $msg): bool {
        return (bool)(preg_match('/(?:buatkan|buat|tulis|tuliskan)?\s*(?:source\s*)?kode(?:\s*program|\s*php|\s*javascript|\s*python)?/i', $msg)
            || preg_match('/(?:coding|programming|debugging|bikin\s*program|source\s*code)/i', $msg));
    }

    public function hasSpecificReportId(string $msg): bool {
        return (bool)(preg_match('/(?:lpf[\s\-_]*|#\s*)0*(\d{1,6})/i', $msg)
            || preg_match('/(?:laporan|tiket|id)[^\d]{0,8}(\d{1,6})/i', $msg));
    }

    public function isCreateReportQuestion(string $msg): bool {
        if ($this->hasSpecificReportId($msg)) return false;
        if ($this->isCheckStatusQuestion($msg)) return false;
        if (preg_match('/(?:status|riwayat|perkembangan|daftar)\s+laporan/i', $msg)) return false;
        if (preg_match('/laporan\s+saya/i', $msg)) return false;

        if (preg_match('/(?:bagaimana|gimana|tata\s+cara|cara(?:nya)?)\s*(?:saya\s+)?(?:untuk\s+)?(?:mem?buat|meng?ajukan|bikin|mengirim|melakukan|mendaftarkan|lapor|melapor|pengaduan)\s*(?:laporan|pengaduan|keluhan|kerusakan|fasilitas)?/i', $msg)) {
            return true;
        }
        if (preg_match('/(?:langkah|panduan|alur|prosedur|tahap|petunjuk|syarat)\s*(?:dalam\s+|untuk\s+)?(?:mem?buat|meng?ajukan|bikin|melapor|lapor|pengajuan|pelaporan)/i', $msg)) {
            return true;
        }
        if (preg_match('/(?:mau|ingin|bisa)\s+(?:mem?buat|meng?ajukan|melaporkan|lapor|mengadu)/i', $msg)) {
            return true;
        }
        if (preg_match('/^cara\s+(?:lapor|melapor|buat\s+laporan|bikin\s+laporan)/i', $msg)) {
            return true;
        }
        return false;
    }

    public function isCheckStatusQuestion(string $msg): bool {
        if (preg_match('/(?:apa\s+)?(?:arti|makna|maksud|penjelasan)\s+(?:dari\s+)?status/i', $msg)) return false;
        
        if (preg_match('/(?:bagaimana|gimana|cara|langkah|panduan|dimana|bisa)\s+(?:cara\s+)?(?:saya\s+)?(?:untuk\s+)?(?:cek|mengecek|melihat|lihat|pantau|memantau|lacak|melacak|mengetahui)\s+(?:status|riwayat|perkembangan|progres|laporan)/i', $msg)
            || preg_match('/(?:cara|bagaimana)\s+(?:cek|lacak|pantau)\s+laporan/i', $msg)
            || preg_match('/(?:cara|langkah|panduan)\s+(?:melihat|cek)\s+status/i', $msg)) {
            return true;
        }
        return false;
    }

    public function isCategoriesQuestion(string $msg): bool {
        return (bool)(preg_match('/(?:apa\s+saja|daftar|macam|jenis|pilihan|ragam)?\s*kategori\s*(?:fasilitas|laporan)?/i', $msg)
            || preg_match('/fasilitas\s+(?:apa\s+saja|yang\s+bisa\s+dilaporkan|yang\s+dapat\s+dilaporkan)/i', $msg)
            || preg_match('/(?:apa\s+saja\s+yang\s+bisa\s+dilaporkan|bisa\s+lapor\s+apa\s+saja)/i', $msg));
    }

    public function isStatusHelpQuestion(string $msg): bool {
        return (bool)(preg_match('/(?:apa\s+)?(?:arti|makna|maksud|penjelasan)\s+(?:dari\s+)?status/i', $msg)
            || preg_match('/status\s+(?:laporan\s+)?(?:artinya\s+apa|maksudnya\s+apa)/i', $msg));
    }

    public function isAppUsageQuestion(string $msg): bool {
        return (bool)(preg_match('/(?:panduan|cara\s+menggunakan|cara\s+pakai|cara\s+kerja|fungsi|fitur)\s+(?:.*?\s+)?(?:aplikasi|sistem|laporfasilitas)/i', $msg)
            || preg_match('/bagaimana\s+cara\s+kerja\s+aplikasi/i', $msg));
    }

    public function isRegisterQuestion(string $msg): bool {
        return (bool)(preg_match('/(?:bagaimana\s+)?cara\s+(?:mendaftar|daftar|registrasi|buat\s+akun|bikin\s+akun)/i', $msg));
    }

    public function isIdentityQuestion(string $msg): bool {
        return (bool)(preg_match('/(?:siapa\s+kamu|kamu\s+siapa|siapakah\s+anda|anda\s+siapa|apa\s+itu\s+chatbot|chatbot\s+ini\s+untuk\s+apa)/i', $msg));
    }

    public function isMyReportsInquiry(string $msg): bool {
        if ($this->hasSpecificReportId($msg)) return false;
        if ($this->isCheckStatusQuestion($msg)) return false;
        if ($this->isCreateReportQuestion($msg)) return false;

        return (bool)(preg_match('/(?:tampilkan|lihat|daftar|ada\s+apa\s+saja|apakah|bagaimana)\s+(?:daftar\s+)?laporan\s+saya/i', $msg)
            || preg_match('/^laporan\s+saya(?:\s+apa\s+saja|\s+sudah\s+sampai\s+mana|\s+gimana)?$/i', $msg)
            || preg_match('/status\s+laporan\s+saya/i', $msg));
    }

    public function isOtherUsersReportsRequest(string $msg): bool {
        return (bool)(
            preg_match('/(?:tampilkan|lihat|cek|buka|daftar|ada\s+apa\s+saja)?\s*laporan\s+(?:milik\s+)?(?:pengguna|user|orang|warga|masyarakat)\s+lain/i', $msg)
            || preg_match('/laporan\s+(?:milik\s+)?(?:orang|user|pengguna|warga)\s+lain/i', $msg)
            || preg_match('/laporan\s+lain\s+milik\s+orang/i', $msg)
            || preg_match('/(?:tampilkan|lihat|cek)\s+laporan\s+(?:orang|user|pengguna)\s+lain/i', $msg)
        );
    }

    public function isUserIdentityQuestion(string $msg): bool {
        if ($this->isIdentityQuestion($msg)) {
            return false;
        }

        // Siapa saya / siapakah saya / siapa nama saya / saya siapa
        if (preg_match('/(?:siapakah|siapa)\s+(?:sebenarnya\s+)?saya/i', $msg)
            || preg_match('/^saya\s+siapa/i', $msg)
            || preg_match('/(?:siapa|apakah)\s+nama\s+saya/i', $msg)
            || preg_match('/nama\s+saya\s*(?:\?|siapa|apa)?$/i', $msg)
            || preg_match('/nama\s+saya\s+(?:siapa|apa)/i', $msg)
            || preg_match('/siapa\s+nama\s+akun\s+saya/i', $msg)
            || preg_match('/nama\s+akun\s+saya/i', $msg)
            || preg_match('/(?:saya\s+)?(?:sedang\s+)?(?:login|masuk)\s+sebagai\s+siapa/i', $msg)
            || preg_match('/(?:akun|identitas|profil)\s+saya(?:\s+apa)?/i', $msg)) {
            return true;
        }

        // Role / peran saya
        if (preg_match('/(?:apa\s+)?(?:role|peran)\s+(?:akun\s+)?saya/i', $msg)
            || preg_match('/(?:role|peran)\s+(?:akun\s+)?saya\s+(?:apa|sebagai\s+apa)/i', $msg)) {
            return true;
        }

        // Email saya
        if (preg_match('/(?:apa\s+)?(?:alamat\s+)?email\s+(?:akun\s+)?saya/i', $msg)
            || preg_match('/email\s+(?:akun\s+)?saya\s+(?:apa)/i', $msg)) {
            return true;
        }

        // Kontak / telepon saya (khusus petugas)
        if (preg_match('/(?:nomor\s+telepon|no\s+hp|kontak|area\s+tugas|wilayah\s+tugas)\s+saya/i', $msg)) {
            return true;
        }

        return false;
    }

    public function isUserReportCountQuestion(string $msg): bool {
        if (preg_match('/(?:total|seluruh)\s+laporan\s+(?:di\s+sistem|di\s+database|semua)/i', $msg)) {
            return false;
        }

        return (bool)(
            preg_match('/berapa\s+(?:jumlah\s+)?(?:banyak\s+)?laporan\s+(?:yang\s+)?(?:sudah\s+|telah\s+|pernah\s+)?(?:saya\s+)?(?:buat|ajukan|laporkan|kirim)/i', $msg)
            || preg_match('/berapa\s+(?:jumlah\s+)?laporan\s+saya/i', $msg)
            || preg_match('/jumlah\s+laporan\s+saya/i', $msg)
            || preg_match('/total\s+laporan\s+saya/i', $msg)
            || preg_match('/berapa\s+banyak\s+laporan\s+saya/i', $msg)
        );
    }

    public function isUserReportStatusQuestion(string $msg): bool {
        if ($this->hasSpecificReportId($msg)) return false;
        if ($this->isCheckStatusQuestion($msg)) return false;
        if ($this->isUserReportCountQuestion($msg)) return false;

        return (bool)(
            preg_match('/(?:bagaimana|gimana|apa\s+saja|tampilkan|daftar)\s+(?:status\s+)?laporan\s+(?:milik\s+)?saya/i', $msg)
            || preg_match('/^status\s+laporan\s+saya/i', $msg)
            || preg_match('/^laporan\s+saya(?:\s+apa\s+saja|\s+sudah\s+sampai\s+mana|\s+gimana)?$/i', $msg)
            || preg_match('/(?:apa\s+saja\s+)?laporan\s+milik\s+saya/i', $msg)
            || preg_match('/daftar\s+laporan\s+saya/i', $msg)
        );
    }

    public function isOfficerHandlingMyReportQuestion(string $msg): bool {
        if ($this->hasSpecificReportId($msg)) return false;
        return (bool)(
            preg_match('/(?:siapa|siapakah)\s+(?:nama\s+)?petugas\s+(?:yang\s+)?(?:menangani|tangani|perbaiki)?\s*laporan\s+saya/i', $msg)
            || preg_match('/petugas\s+(?:yang\s+)?menangani\s+laporan\s+saya/i', $msg)
            || preg_match('/siapa\s+yang\s+menangani\s+laporan\s+saya/i', $msg)
            || preg_match('/siapa\s+petugas\s+laporan\s+saya/i', $msg)
        );
    }

    public function isOfficerTasksQuestion(string $msg): bool {
        return (bool)(
            preg_match('/(?:apa\s+saja|daftar|tampilkan)?\s*tugas\s+(?:perbaikan\s+)?saya/i', $msg)
            || preg_match('/tugas\s+saya(?:\s+apa\s+saja)?/i', $msg)
            || preg_match('/pekerjaan\s+saya/i', $msg)
        );
    }

    public function isOfficerReporterInfoQuestion(string $msg): bool {
        return (bool)(
            preg_match('/(?:siapa|siapakah)\s+pelapor\s+(?:laporan\s+|tugas\s+)?(?:yang\s+saya\s+tangani|saya|milik\s+saya)/i', $msg)
            || preg_match('/siapa\s+pelapor\s+tugas\s+saya/i', $msg)
            || preg_match('/siapa\s+yang\s+(?:lapor|melaporkan)\s+(?:tugas|laporan)\s+saya/i', $msg)
            || preg_match('/informasi\s+pelapor\s+(?:tugas|laporan)\s+saya/i', $msg)
        );
    }

    public function isLastUpdatedQuestion(string $msg): bool {
        return (bool)(
            preg_match('/kapan\s+laporan\s+(?:saya\s+)?terakhir\s+(?:diperbarui|diupdate)/i', $msg)
            || preg_match('/kapan\s+update\s+terakhir\s+laporan(?:\s+saya)?/i', $msg)
            || preg_match('/kapan\s+laporan\s+saya\s+diperbarui/i', $msg)
        );
    }

    public function isTopCategoryQuestion(string $msg): bool {
        return (bool)(
            preg_match('/kategori\s+(?:fasilitas\s+)?(?:apa\s+yang\s+)?paling\s+banyak\s+dilaporkan/i', $msg)
            || preg_match('/kategori\s+(?:paling\s+)?terbanyak/i', $msg)
            || preg_match('/kategori\s+(?:paling\s+)?populer/i', $msg)
            || preg_match('/kategori\s+yang\s+paling\s+sering\s+dilaporkan/i', $msg)
        );
    }

    public function isCategoryBreakdownQuestion(string $msg): bool {
        return (bool)(
            preg_match('/berapa\s+(?:jumlah\s+)?laporan\s+berdasarkan\s+kategori/i', $msg)
            || preg_match('/jumlah\s+laporan\s+per\s+kategori/i', $msg)
            || preg_match('/laporan\s+berdasarkan\s+kategori/i', $msg)
            || preg_match('/statistik\s+(?:laporan\s+)?kategori/i', $msg)
        );
    }

    public function isStatusCountQuestion(string $msg, ?string &$matchedStatus = null): bool {
        if (preg_match('/laporan\s+saya/i', $msg)) return false;

        $statuses = [
            'Menunggu Verifikasi' => ['menunggu verifikasi', 'menunggu diverifikasi', 'belum diverifikasi', 'menunggu diperiksa'],
            'Diverifikasi'        => ['diverifikasi', 'sudah diverifikasi', 'telah diverifikasi'],
            'Ditugaskan'          => ['ditugaskan', 'sudah ditugaskan', 'telah ditugaskan'],
            'Sedang Diperbaiki'   => ['sedang diperbaiki', 'dalam perbaikan', 'proses perbaikan', 'sedang dikerjakan'],
            'Selesai'             => ['sudah selesai', 'telah selesai', 'yang selesai', 'laporan selesai'],
            'Ditolak'             => ['ditolak', 'yang ditolak', 'laporan ditolak'],
        ];

        if (!preg_match('/(?:berapa|jumlah|total|hitung)\s+laporan/i', $msg)) {
            return false;
        }

        foreach ($statuses as $st => $terms) {
            foreach ($terms as $t) {
                if (str_contains($msg, $t)) {
                    $matchedStatus = $st;
                    return true;
                }
            }
        }
        return false;
    }

    public function isSystemReportCountQuestion(string $msg): bool {
        return (bool)(
            preg_match('/(?:berapa\s+)?(?:total|seluruh|jumlah)\s+laporan\s+(?:di\s+|pada\s+)?(?:sistem|aplikasi|database|kota)/i', $msg)
            || preg_match('/berapa\s+total\s+laporan(?:\s+yang\s+ada|\s+masuk)?\??$/i', $msg)
            || preg_match('/berapa\s+seluruh\s+laporan/i', $msg)
        );
    }

    public function isOfficersListInquiry(string $msg): bool {
        return (bool)(
            preg_match('/(?:siapa\s+saja|daftar|ada\s+siapa\s+saja|tampilkan)?\s*petugas\s*(?:yang\s+tersedia|lapangan|terdaftar|di\s+aplikasi|aplikasi)?/i', $msg)
            && !preg_match('/petugas\s+(?:yang\s+)?menangani\s+laporan\s+saya/i', $msg)
        );
    }

    public function isOutOfScopeQuestion(string $msg): bool {
        $outOfScopeTerms = ['cuaca', 'resep', 'masakan', 'presiden', 'pemilu', 'politik', 'pacar', 'jodoh', 'zodiak', 'ramalan', 'sepak bola', 'lagu', 'chord', 'lirik', 'film', 'sinopsis'];
        foreach ($outOfScopeTerms as $term) {
            if (preg_match('/\b' . $term . '\b/i', $msg)) {
                return true;
            }
        }
        return false;
    }

    public function isUserManagementInquiry(string $msg): bool {
        if ($this->isAdminRoleInquiry($msg)) return true;
        if ($this->isMasyarakatRoleInquiry($msg)) return true;
        if ($this->isAllUsersListInquiry($msg)) return true;
        if ($this->isUserCountInquiry($msg)) return true;
        return false;
    }

    public function isAdminRoleInquiry(string $msg): bool {
        if (preg_match('/(?:apa\s+)?(?:role|peran)\s+saya/i', $msg)) {
            return false;
        }
        return (bool)(
            preg_match('/(?:siapa|daftar|tampilkan|sebutkan|ada\s+siapa)\s+(?:saja\s+)?(?:administrator|admin)(?:\s+aplikasi|\s+sistem|\s+laporfasilitas|\s+yang\s+ada)?/i', $msg)
            || (preg_match('/(?:administrator|admin)\s+(?:aplikasi|sistem|laporfasilitas)/i', $msg) && preg_match('/siapa/i', $msg))
        );
    }

    public function isMasyarakatRoleInquiry(string $msg): bool {
        if (preg_match('/(?:apa\s+)?(?:role|peran)\s+saya/i', $msg)) {
            return false;
        }
        return (bool)(
            preg_match('/(?:siapa|daftar|tampilkan|sebutkan)\s+(?:saja\s+)?(?:pengguna\s+(?:dengan\s+role\s+)?)?(?:masyarakat|warga)(?:\s+yang\s+terdaftar|\s+di\s+aplikasi|\s+sistem)?/i', $msg)
            || (preg_match('/(?:pengguna|user)\s+(?:dengan\s+)?role\s+masyarakat/i', $msg) && preg_match('/(?:siapa|daftar|tampilkan)/i', $msg))
        );
    }

    public function isAllUsersListInquiry(string $msg): bool {
        return (bool)(
            preg_match('/(?:siapa\s+saja\s+anggota|tampilkan\s+(?:semua\s+)?(?:nama\s+)?pengguna|daftar\s+(?:semua\s+)?(?:nama\s+)?pengguna|daftar\s+anggota|siapa\s+saja\s+pengguna(?:\s+yang\s+terdaftar)?)/i', $msg)
            || (preg_match('/(?:anggota|pengguna)\s+(?:yang\s+)?terdaftar/i', $msg) && preg_match('/(?:siapa|daftar|tampilkan|sebutkan)/i', $msg))
            || (preg_match('/anggota\s+laporfasilitas/i', $msg) && preg_match('/(?:siapa|daftar|tampilkan|sebutkan)/i', $msg))
        );
    }

    public function isUserCountInquiry(string $msg): bool {
        $isUserQ = $this->matches($msg, ['pengguna', 'pendaftar', 'anggota', 'masyarakat', 'petugas', 'teknisi', 'admin', 'akun', 'user']);
        $isCountQ = $this->matches($msg, ['berapa', 'total', 'jumlah', 'banyak', 'statistik', 'hitung', 'rekap']);
        $isReportQ = $this->matches($msg, ['lapor', 'tiket', 'fasilitas', 'kerusakan', 'perbaikan', 'verifikasi']);
        return ($isUserQ && $isCountQ && !$isReportQ);
    }

    /**
     * Membersihkan teks dari sintaks Markdown agar selalu tampil teks polos (plain text) yang rapi.
     */
    public static function cleanPlainText(string $text): string {
        // Hapus kode blok ``` atau `
        $text = preg_replace('/```[a-zA-Z]*\n?/', '', $text);
        $text = str_replace(['```', '`'], '', $text);

        // Hapus header markdown (### Header -> Header)
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $text);

        // Hapus bold & italic (**text** -> text, *text* -> text)
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/(?<=\s|^)\*([^\*\n]+)\*(?=\s|$|[.,!?])/', '$1', $text);
        $text = str_replace(['**', '*'], '', $text);

        // Konversi markdown table (| Kolom | Detail |) menjadi format poin sederhana
        if (str_contains($text, '|')) {
            $lines = explode("\n", $text);
            $newLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^\|?\s*[-:]+[-|\s:]*$/', $trimmed)) {
                    continue;
                }
                if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                    $cols = array_map('trim', explode('|', trim($trimmed, '|')));
                    $cols = array_values(array_filter($cols, fn($c) => $c !== ''));
                    if (count($cols) >= 2) {
                        $newLines[] = '- ' . implode(': ', $cols);
                    } else if (count($cols) === 1) {
                        $newLines[] = '- ' . reset($cols);
                    }
                } else {
                    $newLines[] = $line;
                }
            }
            $text = implode("\n", $newLines);
        }

        // Rapikan spasi dan baris baru
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function matches(string $text, array $keywords): bool {
        foreach ($keywords as $k) {
            if (str_contains($text, $k)) return true;
        }
        return false;
    }

    /**
     * Hak akses laporan per role (mirror AuthMiddleware::canAccessReport, aman untuk API):
     * admin = semua; masyarakat = miliknya sendiri; petugas = yang ditugaskan kepadanya.
     */
    private function canUserViewReport(array $report, ?array $user): bool {
        if ($user === null) return false;
        $role = $user['role'] ?? '';
        if ($role === 'admin') return true;
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0) return false;
        if ($role === 'masyarakat') {
            return (int)($report['user_id'] ?? 0) === $uid;
        }
        if ($role === 'petugas') {
            $officer = Officer::findByUserId($uid);
            if ($officer === null) return false;
            foreach (Assignment::getByReport((int)($report['id'] ?? 0)) as $a) {
                if ((int)($a['officer_id'] ?? 0) === (int)$officer['id']) return true;
            }
            return false;
        }
        return false;
    }
}
