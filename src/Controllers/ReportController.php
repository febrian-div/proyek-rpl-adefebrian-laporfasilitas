<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Middleware\AuthMiddleware;
use App\Models\Report;
use App\Models\Assignment;
use App\Models\RepairProof;
use App\Models\Notification;
use App\Models\User;
use App\Services\UploadService;
use App\Services\AIService;
use App\Services\NotificationService;
use App\Services\TelegramService;

class ReportController {
    /**
     * Citizen Dashboard & Report History
     */
    public function index(): void {
        AuthMiddleware::requireRole(['masyarakat', 'admin']);
        $user = AuthMiddleware::user();
        $userId = (int)$user['id'];
        
        // Fetch only reports belonging to the authenticated citizen
        $allUserReports = Report::getByUser($userId);

        // Calculate dashboard summary metrics
        $stats = [
            'total'     => count($allUserReports),
            'menunggu'  => 0,
            'diproses'  => 0,
            'selesai'   => 0,
            'ditolak'   => 0,
        ];

        foreach ($allUserReports as $r) {
            match ($r['status']) {
                'Menunggu Verifikasi'                         => $stats['menunggu']++,
                'Diverifikasi', 'Ditugaskan', 'Sedang Diperbaiki' => $stats['diproses']++,
                'Selesai'                                     => $stats['selesai']++,
                'Ditolak'                                     => $stats['ditolak']++,
                default                                       => null,
            };
        }

        // Apply filters if present
        $filteredReports = $allUserReports;
        $statusFilter = trim($_GET['status'] ?? '');
        $searchQuery = strtolower(trim($_GET['q'] ?? ''));

        if (!empty($statusFilter)) {
            $filteredReports = array_filter($filteredReports, function ($r) use ($statusFilter) {
                if ($statusFilter === 'diproses') {
                    return in_array($r['status'], ['Diverifikasi', 'Ditugaskan', 'Sedang Diperbaiki'], true);
                }
                return strtolower($r['status']) === strtolower($statusFilter);
            });
        }

        if (!empty($searchQuery)) {
            $filteredReports = array_filter($filteredReports, function ($r) use ($searchQuery) {
                return str_contains(strtolower($r['title']), $searchQuery)
                    || str_contains(strtolower($r['address']), $searchQuery)
                    || str_contains(strtolower($r['facility_category'] ?? ''), $searchQuery);
            });
        }

        view('citizen.index', [
            'title'           => 'Dashboard & Riwayat Laporan - LaporFasilitas',
            'user'            => $user,
            'stats'           => $stats,
            'reports'         => array_values($filteredReports),
            'currentFilter'   => $statusFilter,
            'currentSearch'   => $searchQuery,
        ]);
    }

    /**
     * Show create report form for citizen
     */
    public function create(): void {
        AuthMiddleware::requireRole(['masyarakat', 'admin']);
        $user = AuthMiddleware::user();

        view('citizen.create', [
            'title' => 'Buat Laporan Kerusakan Baru - LaporFasilitas',
            'user'  => $user,
        ]);
    }

    /**
     * Store new report submitted by citizen
     */
    public function store(): void {
        AuthMiddleware::requireRole(['masyarakat', 'admin']);
        $user = AuthMiddleware::user();

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            Response::redirect('/laporan/buat');
        }

        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['facility_category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $address = trim($_POST['address'] ?? '');

        // Validation
        if (empty($title) || strlen($title) < 5) {
            Response::setFlash('error', 'Judul laporan wajib diisi minimal 5 karakter.');
            Response::redirect('/laporan/buat');
        }

        $allowedCategories = [
            'Jalan',
            'Lampu Jalan',
            'Trotoar',
            'Taman',
            'Tempat Sampah',
            'Saluran Air',
            'Fasilitas Umum Lainnya'
        ];

        if (empty($category) || !in_array($category, $allowedCategories, true)) {
            Response::setFlash('error', 'Harap pilih salah satu kategori fasilitas umum yang valid.');
            Response::redirect('/laporan/buat');
        }

        if (empty($description) || strlen($description) < 10) {
            Response::setFlash('error', 'Deskripsi kerusakan wajib diisi minimal 10 karakter.');
            Response::redirect('/laporan/buat');
        }

        if ($latitude === false || $longitude === false) {
            Response::setFlash('error', 'Titik koordinat lokasi peta tidak valid.');
            Response::redirect('/laporan/buat');
        }

        if (empty($address) || strlen($address) < 5) {
            Response::setFlash('error', 'Detail alamat / patokan lokasi wajib diisi.');
            Response::redirect('/laporan/buat');
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            Response::setFlash('error', 'Harap unggah foto bukti kerusakan fasilitas.');
            Response::redirect('/laporan/buat');
        }

        // Upload photo securely
        $uploadService = new UploadService();
        $uploadResult = $uploadService->uploadImage($_FILES['photo'], 'reports');

        if (!$uploadResult['success']) {
            Response::setFlash('error', $uploadResult['error'] ?? 'Gagal mengunggah gambar.');
            Response::redirect('/laporan/buat');
        }

        // Jalankan AI Analysis secara sinkron (dengan fallback)
        $aiService = new AIService();
        // Path absolut file foto yang baru diupload
        $imageAbsPath = ROOT_PATH . '/public' . $uploadResult['relative_url'];
        $aiJsonResult = $aiService->analyzeReport($imageAbsPath, $description, $title);

        // Insert report
        $reportId = Report::create([
            'user_id'           => (int)$user['id'],
            'title'             => $title,
            'facility_category' => $category, // Tetap menggunakan input user, AI hanya untuk rekomendasi (atau akan tersimpan di ai_result)
            'description'       => $description,
            'photo'             => $uploadResult['relative_url'],
            'latitude'          => $latitude,
            'longitude'         => $longitude,
            'address'           => $address,
            'status'            => 'Menunggu Verifikasi',
            'priority'          => null,
            'ai_result'         => $aiJsonResult,
        ]);

        if (!$reportId) {
            Response::setFlash('error', 'Terjadi kesalahan sistem saat menyimpan laporan.');
            Response::redirect('/laporan/buat');
        }

        // Notifikasi laporan baru ke pelapor dan admin (website + Telegram jika dikonfigurasi)
        // Dibungkus try-catch agar kegagalan notifikasi tidak membatalkan penyimpanan laporan.
        $newReport = Report::findById($reportId);
        if ($newReport) {
            try {
                $notifService = new NotificationService(new TelegramService());
                $notifService->notifyReportCreated($newReport, $user);
            } catch (\Throwable $e) {
                error_log('[ReportController] Notification error after report created (report_id=' . $reportId . '): ' . $e->getMessage());
            }
        }

        Response::setFlash('success', 'Laporan berhasil dibuat! Laporan Anda sedang menunggu verifikasi admin.');
        Response::redirect('/laporan/' . $reportId);
    }

    /**
     * View single report detail with ownership guard & process timeline
     */
    public function show(int|string $id): void {
        $reportId = (int)$id;

        // Enforce strict access control:
        // Citizen can ONLY view their own report, officer assigned only, admin all
        $report = AuthMiddleware::requireReportAccess($reportId);
        $user = AuthMiddleware::user();

        // Retrieve assignments, repair proofs, and timeline
        $assignments = Assignment::getByReport($reportId);
        $repairProofs = RepairProof::getByReport($reportId);
        $timeline = \App\Models\ReportTimeline::getByReport($reportId);

        view('citizen.detail', [
            'title'        => "Detail Laporan #LPF-" . str_pad((string)$reportId, 5, '0', STR_PAD_LEFT) . " - LaporFasilitas",
            'user'         => $user,
            'report'       => $report,
            'assignments'  => $assignments,
            'repairProofs' => $repairProofs,
            'timeline'     => $timeline,
        ]);
    }
}
