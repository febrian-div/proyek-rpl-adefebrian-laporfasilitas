<?php
namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Helpers\Response;
use App\Helpers\Security;
use App\Models\Report;
use App\Models\Officer;
use App\Models\User;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\RepairProof;
use App\Services\NotificationService;
use App\Services\TelegramService;

class AdminController {
    public function dashboard(): void {
        AuthMiddleware::requireRole('admin');
        $user = AuthMiddleware::user();
        $reports = Report::getAll();

        view('admin.dashboard', [
            'title' => 'Admin Dashboard - LaporFasilitas',
            'user' => $user,
            'reports' => $reports, // You might want to consider fetching just the counts here for performance, but we use this for now
        ]);
    }

    public function reports(): void {
        AuthMiddleware::requireRole('admin');
        
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'facility_category' => $_GET['kategori'] ?? '',
            'priority' => $_GET['prioritas'] ?? '',
            'sort' => $_GET['sort'] ?? 'created_at',
            'direction' => $_GET['dir'] ?? 'DESC',
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10;
        $filters['limit'] = $limit;
        $filters['offset'] = ($page - 1) * $limit;

        $totalReports = Report::countAll($filters);
        $reports = Report::getAll($filters);
        $totalPages = ceil($totalReports / $limit);

        view('admin.reports', [
            'title' => 'Semua Laporan - Admin',
            'reports' => $reports,
            'filters' => $filters,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalReports' => $totalReports
        ]);
    }

    public function officers(): void {
        AuthMiddleware::requireRole('admin');
        
        $officers = Officer::all();
        foreach ($officers as &$officer) {
            $tasks = Officer::assignedReports($officer['id']);
            $officer['active_tasks'] = count(array_filter($tasks, fn($t) => in_array($t['assignment_status'], ['Ditugaskan', 'Sedang Dikerjakan'])));
        }

        view('admin.officers', [
            'title' => 'Manajemen Petugas - Admin',
            'officers' => $officers
        ]);
    }

    /**
     * User management page (admin only).
     * Staff accounts (petugas/admin) may ONLY be created here, never via public register.
     */
    public function users(): void {
        AuthMiddleware::requireRole('admin');

        $search = trim($_GET['search'] ?? '');
        $roleFilter = trim($_GET['role'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');

        $allUsers = User::all();
        $totalUsers = count($allUsers);
        $totalMasyarakat = count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'masyarakat'));
        $totalPetugas = count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'petugas'));
        $totalAdmin = count(array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'admin'));
        $totalActive = count(array_filter($allUsers, fn($u) => (int)($u['is_active'] ?? 1) === 1));
        $totalInactive = count(array_filter($allUsers, fn($u) => (int)($u['is_active'] ?? 1) === 0));

        $filteredUsers = User::all($roleFilter !== '' ? $roleFilter : null, $search, $statusFilter);

        view('admin.users', [
            'title' => 'Manajemen Pengguna - Admin',
            'users' => $filteredUsers,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
            ],
            'stats' => [
                'total' => $totalUsers,
                'masyarakat' => $totalMasyarakat,
                'petugas' => $totalPetugas,
                'admin' => $totalAdmin,
                'active' => $totalActive,
                'inactive' => $totalInactive,
            ],
            'currentAdminId' => AuthMiddleware::id(),
        ]);
    }

    /**
     * Store a new staff/user account created by admin.
     */
    public function storeUser(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/pengguna');
        }

        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $serviceArea = trim($_POST['service_area'] ?? '');

        $allowedRoles = ['masyarakat', 'petugas', 'admin'];
        if (empty($name) || strlen($name) < 3) {
            Response::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            Response::redirect('/admin/pengguna');
        }
        if (!$email) {
            Response::setFlash('error', 'Alamat email tidak valid.');
            Response::redirect('/admin/pengguna');
        }
        if (strlen($password) < 6) {
            Response::setFlash('error', 'Kata sandi minimal 6 karakter.');
            Response::redirect('/admin/pengguna');
        }
        if (!in_array($role, $allowedRoles, true)) {
            Response::setFlash('error', 'Role tidak valid. Pilih masyarakat, petugas, atau admin.');
            Response::redirect('/admin/pengguna');
        }
        if (User::findByEmail($email)) {
            Response::setFlash('error', 'Email ini sudah terdaftar.');
            Response::redirect('/admin/pengguna');
        }

        // Petugas wajib memiliki profil lapangan (telepon & area layanan)
        if ($role === 'petugas' && (empty($phone) || empty($serviceArea))) {
            Response::setFlash('error', 'Akun petugas wajib dilengkapi nomor telepon dan area layanan.');
            Response::redirect('/admin/pengguna');
        }

        $userId = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role'     => $role,
        ]);

        if (!$userId) {
            Response::setFlash('error', 'Gagal membuat akun. Silakan coba kembali.');
            Response::redirect('/admin/pengguna');
        }

        if ($role === 'petugas') {
            Officer::create([
                'user_id'      => $userId,
                'phone'        => $phone,
                'service_area' => $serviceArea,
            ]);
        }

        Response::setFlash('success', "Akun {$role} '{$name}' berhasil dibuat.");
        Response::redirect('/admin/pengguna');
    }

    /**
     * Edit user details (Admin only)
     */
    public function editUser(string|int $id): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/pengguna');
        }

        $userId = (int)$id;
        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::setFlash('error', 'Pengguna tidak ditemukan.');
            Response::redirect('/admin/pengguna');
        }

        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role = strtolower(trim($_POST['role'] ?? ''));

        $allowedRoles = ['masyarakat', 'petugas', 'admin'];
        if (empty($name) || strlen($name) < 3) {
            Response::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            Response::redirect('/admin/pengguna');
        }
        if (!$email) {
            Response::setFlash('error', 'Alamat email tidak valid.');
            Response::redirect('/admin/pengguna');
        }
        if (!in_array($role, $allowedRoles, true)) {
            Response::setFlash('error', 'Role tidak valid.');
            Response::redirect('/admin/pengguna');
        }

        // Cek email unik (tidak boleh duplikat dengan pengguna lain)
        if (User::emailExistsForOther($email, $userId)) {
            Response::setFlash('error', 'Alamat email sudah digunakan oleh pengguna lain.');
            Response::redirect('/admin/pengguna');
        }

        $currentAdminId = AuthMiddleware::id();

        // Validasi keamanan: jika admin mengedit dirinya sendiri dan mencoba mengubah role menjadi non-admin
        if ($userId === $currentAdminId && $role !== 'admin') {
            if (User::countActiveAdmins() <= 1) {
                Response::setFlash('error', 'Anda tidak dapat mengubah role Anda sendiri karena merupakan administrator aktif terakhir.');
                Response::redirect('/admin/pengguna');
            }
        }

        $updated = User::update($userId, [
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ]);

        if (!$updated) {
            Response::setFlash('error', 'Gagal memperbarui data pengguna.');
            Response::redirect('/admin/pengguna');
        }

        // Jika role diubah menjadi petugas, pastikan ada entri di tabel officers
        if ($role === 'petugas') {
            $existingOfficer = Officer::findByUserId($userId);
            if (!$existingOfficer) {
                Officer::create([
                    'user_id'      => $userId,
                    'phone'        => trim($_POST['phone'] ?? '-') ?: '-',
                    'service_area' => trim($_POST['service_area'] ?? '-') ?: '-',
                ]);
            }
        }

        // Jika mengedit akun sendiri, update sesi admin yang sedang login
        if ($userId === $currentAdminId) {
            AuthMiddleware::initSession();
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;
        }

        Response::setFlash('success', "Informasi pengguna '{$name}' berhasil diperbarui.");
        Response::redirect('/admin/pengguna');
    }

    /**
     * Reset user password (Admin only)
     */
    public function resetUserPassword(string|int $id): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/pengguna');
        }

        $userId = (int)$id;
        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::setFlash('error', 'Pengguna tidak ditemukan.');
            Response::redirect('/admin/pengguna');
        }

        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (strlen($newPassword) < 6) {
            Response::setFlash('error', 'Kata sandi baru minimal 6 karakter.');
            Response::redirect('/admin/pengguna');
        }

        if ($newPassword !== $newPasswordConfirm) {
            Response::setFlash('error', 'Konfirmasi kata sandi tidak cocok.');
            Response::redirect('/admin/pengguna');
        }

        $reset = User::resetPassword($userId, $newPassword);
        if (!$reset) {
            Response::setFlash('error', 'Gagal mereset kata sandi pengguna.');
            Response::redirect('/admin/pengguna');
        }

        Response::setFlash('success', "Kata sandi untuk pengguna '{$targetUser['name']}' berhasil direset.");
        Response::redirect('/admin/pengguna');
    }

    /**
     * Deactivate user account (soft delete)
     */
    public function deactivateUser(string|int $id): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/pengguna');
        }

        $userId = (int)$id;
        $currentAdminId = AuthMiddleware::id();

        // Admin tidak dapat menonaktifkan dirinya sendiri
        if ($userId === $currentAdminId) {
            Response::setFlash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            Response::redirect('/admin/pengguna');
        }

        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::setFlash('error', 'Pengguna tidak ditemukan.');
            Response::redirect('/admin/pengguna');
        }

        // Administrator aktif terakhir tidak dapat dinonaktifkan
        if ($targetUser['role'] === 'admin') {
            if (User::countActiveAdmins() <= 1) {
                Response::setFlash('error', 'Administrator aktif terakhir tidak dapat dinonaktifkan.');
                Response::redirect('/admin/pengguna');
            }
        }

        $deactivated = User::deactivate($userId);
        if (!$deactivated) {
            Response::setFlash('error', 'Gagal menonaktifkan akun pengguna.');
            Response::redirect('/admin/pengguna');
        }

        Response::setFlash('success', "Akun pengguna '{$targetUser['name']}' berhasil dinonaktifkan.");
        Response::redirect('/admin/pengguna');
    }

    /**
     * Re-activate user account
     */
    public function activateUser(string|int $id): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/pengguna');
        }

        $userId = (int)$id;
        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::setFlash('error', 'Pengguna tidak ditemukan.');
            Response::redirect('/admin/pengguna');
        }

        $activated = User::activate($userId);
        if (!$activated) {
            Response::setFlash('error', 'Gagal mengaktifkan akun pengguna.');
            Response::redirect('/admin/pengguna');
        }

        Response::setFlash('success', "Akun pengguna '{$targetUser['name']}' berhasil diaktifkan kembali.");
        Response::redirect('/admin/pengguna');
    }

    /**
     * Show admin self-profile page
     */
    public function editProfile(): void {
        AuthMiddleware::requireRole('admin');
        $currentUser = AuthMiddleware::user();
        $adminData = User::findById((int)$currentUser['id']);

        view('admin.profile', [
            'title' => 'Profil Administrator - LaporFasilitas',
            'user' => $adminData,
        ]);
    }

    /**
     * Update admin self-profile
     */
    public function updateProfile(): void {
        AuthMiddleware::requireRole('admin');

        $token = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($token)) {
            Response::setFlash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir.');
            Response::redirect('/admin/profil');
        }

        $adminId = AuthMiddleware::id();
        $dbAdmin = User::findById($adminId);
        if (!$dbAdmin) {
            Response::setFlash('error', 'Data pengguna tidak ditemukan.');
            Response::redirect('/admin/profil');
        }

        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($name) || strlen($name) < 3) {
            Response::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            Response::redirect('/admin/profil');
        }
        if (!$email) {
            Response::setFlash('error', 'Alamat email tidak valid.');
            Response::redirect('/admin/profil');
        }

        if (User::emailExistsForOther($email, $adminId)) {
            Response::setFlash('error', 'Alamat email sudah digunakan oleh akun lain.');
            Response::redirect('/admin/profil');
        }

        // Jika ingin ganti password
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                Response::setFlash('error', 'Masukkan kata sandi saat ini untuk mengubah kata sandi.');
                Response::redirect('/admin/profil');
            }

            // Ambil hash password pengguna saat ini
            $fullUser = User::findByEmail($dbAdmin['email']);
            if (!$fullUser || !password_verify($currentPassword, $fullUser['password'])) {
                Response::setFlash('error', 'Kata sandi saat ini tidak sesuai.');
                Response::redirect('/admin/profil');
            }

            if (strlen($newPassword) < 6) {
                Response::setFlash('error', 'Kata sandi baru minimal 6 karakter.');
                Response::redirect('/admin/profil');
            }

            if ($newPassword !== $newPasswordConfirm) {
                Response::setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
                Response::redirect('/admin/profil');
            }

            User::resetPassword($adminId, $newPassword);
        }

        // Update info profil (nama dan email)
        User::update($adminId, [
            'name'  => $name,
            'email' => $email,
        ]);

        // Perbarui data di session
        AuthMiddleware::initSession();
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;

        Response::setFlash('success', 'Profil administrator berhasil diperbarui.');
        Response::redirect('/admin/profil');
    }

    public function show(int $id): void {
        AuthMiddleware::requireRole('admin');
        $user = AuthMiddleware::user();
        
        $report = Report::findById($id);
        if (!$report) {
            http_response_code(404);
            echo "Laporan tidak ditemukan";
            exit;
        }

        $officers = Officer::all();
        $proofs = RepairProof::getByReport($id);

        view('admin.detail', [
            'title' => 'Detail Laporan - LaporFasilitas',
            'user' => $user,
            'report' => $report,
            'officers' => $officers,
            'proofs' => $proofs
        ]);
    }

    public function verify(int $id): void {
        AuthMiddleware::requireRole('admin');
        
        $report = Report::findById($id);
        if (!$report) {
            Response::redirect('/admin');
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrf($csrfToken)) {
            Response::setFlash('error', 'Validasi sesi kedaluwarsa. Silakan coba lagi.');
            Response::redirect("/admin/laporan/$id");
        }

        $status = $_POST['status'] ?? '';
        $priority = $_POST['priority'] ?? null;
        $facility_category = $_POST['facility_category'] ?? null;
        $damage_type = $_POST['damage_type'] ?? null;
        $officerId = $_POST['officer_id'] ?? '';
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');

        if (!in_array($status, ['Menunggu Verifikasi', 'Diverifikasi', 'Ditolak', 'Ditugaskan', 'Sedang Diperbaiki', 'Selesai'])) {
            $status = $report['status'];
        }

        if ($status === 'Diverifikasi' && !empty($officerId)) {
            $status = 'Ditugaskan';
        }

        $updateData = [];
        if (!empty($priority)) $updateData['priority'] = $priority;
        if (!empty($facility_category)) $updateData['facility_category'] = $facility_category;
        if (!empty($damage_type)) $updateData['damage_type'] = $damage_type;
        $updateData['status'] = $status;

        Report::update($id, $updateData);

        $statusChanged = ($report['status'] !== $status);
        $officerChanged = (!empty($officerId) && (string)($report['officer_id'] ?? '') !== (string)$officerId);
        $notifService = new NotificationService(new TelegramService());

        // Resolve officer user IDs
        $newOfficerObj = !empty($officerId) ? Officer::findById((int)$officerId) : null;
        $newOfficerUserId = $newOfficerObj ? (int)$newOfficerObj['user_id'] : null;
        $oldOfficerObj = !empty($report['officer_id']) ? Officer::findById((int)$report['officer_id']) : null;
        $oldOfficerUserId = $oldOfficerObj ? (int)$oldOfficerObj['user_id'] : null;

        // Record timeline if status changed
        if ($statusChanged) {
            $note = null;
            if ($status === 'Ditolak') {
                $note = empty($rejection_reason) ? "Laporan ditolak oleh admin." : $rejection_reason;
            } elseif ($status === 'Ditugaskan' && !empty($officerId)) {
                $note = "Laporan telah ditugaskan kepada petugas.";
            }

            \App\Models\ReportTimeline::create([
                'report_id' => $id,
                'status' => $status,
                'note' => $note
            ]);

            // If reassigned to a different officer, notifyTaskReassigned handles officer notifications.
            // Otherwise, pass $newOfficerUserId to notifyReportStatusChanged.
            $notifyOfficerUserId = ($status === 'Ditugaskan' && $officerChanged && $oldOfficerUserId) 
                ? null 
                : $newOfficerUserId;

            $notifService->notifyReportStatusChanged($id, $status, $rejection_reason, $notifyOfficerUserId);
        }

        // Handle assignment when assigned or officer changed
        if (!empty($officerId) && ($status === 'Ditugaskan' || $report['status'] === 'Ditugaskan')) {
            if ($officerChanged) {
                Assignment::create([
                    'report_id' => $id,
                    'officer_id' => $officerId
                ]);

                // Notifikasi jika dialihkan dari petugas lama ke petugas baru
                if ($oldOfficerUserId && $newOfficerUserId) {
                    $notifService->notifyTaskReassigned($id, $oldOfficerUserId, $newOfficerUserId);
                } elseif ($newOfficerUserId && !$statusChanged) {
                    // Jika status sudah Ditugaskan sebelumnya dan baru ditugaskan ke officer pertama kali
                    $notifService->notifyReportStatusChanged($id, 'Ditugaskan', null, $newOfficerUserId);
                }
            }
        }

        Response::setFlash('success', 'Perubahan laporan berhasil disimpan.');
        Response::redirect("/admin/laporan/$id");
    }
}
