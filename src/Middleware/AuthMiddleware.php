<?php
namespace App\Middleware;

use App\Helpers\Response;
use App\Models\Officer;
use App\Models\Report;
use App\Models\Assignment;
use App\Models\User;

class AuthMiddleware {
    /**
     * Start session safely if not started
     */
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure cookie configuration
            if (!headers_sent()) {
                ini_set('session.cookie_httponly', '1');
                ini_set('session.use_only_cookies', '1');
            }
            session_start();
        }
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        self::initSession();
        return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    /**
     * Get current logged-in user object
     */
    public static function user(): ?array {
        self::initSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get current user ID
     */
    public static function id(): ?int {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Get current user role
     */
    public static function role(): ?string {
        $user = self::user();
        return $user['role'] ?? null;
    }

    /**
     * Abort request with HTTP 403 Forbidden
     */
    public static function abort403(string $message = 'Anda tidak memiliki hak akses untuk halaman atau sumber daya ini.'): void {
        http_response_code(403);
        
        // If request expects JSON (API request)
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            Response::json(['error' => 'Forbidden', 'message' => $message], 403);
        }

        view('errors.403', [
            'title' => '403 Akses Ditolak - LaporFasilitas',
            'errorMessage' => $message,
        ]);
        exit;
    }

    /**
     * Protect route for authenticated users only
     */
    public static function requireAuth(string $redirectPath = '/login'): void {
        if (!self::check()) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                Response::json(['error' => 'Unauthorized', 'message' => 'Silakan login terlebih dahulu.'], 401);
            }
            Response::setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
            Response::redirect($redirectPath);
            return;
        }

        // Validasi status aktif akun dari database untuk mencabut session pengguna nonaktif
        $currentUser = self::user();
        if ($currentUser && isset($currentUser['id'])) {
            $dbUser = User::findById((int)$currentUser['id']);
            if (!$dbUser || (isset($dbUser['is_active']) && !(bool)$dbUser['is_active'])) {
                self::initSession();
                $_SESSION = [];
                if (ini_get("session.use_cookies") && !headers_sent()) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
                if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                    session_start();
                }
                Response::setFlash('error', 'Akun Anda telah dinonaktifkan. Hubungi administrator.');
                Response::redirect($redirectPath);
                return;
            }
        }
    }

    /**
     * Protect route by role(s)
     * @param string|array $roles Allowed role or array of roles
     */
    public static function requireRole(string|array $roles): void {
        self::requireAuth();
        $user = self::user();
        $allowedRoles = is_array($roles) ? $roles : [$roles];

        if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
            self::abort403("Peran Anda ({$user['role']}) tidak memiliki akses ke halaman ini.");
        }
    }

    /**
     * Protect route for guests only (e.g. login/register pages)
     */
    public static function requireGuest(): void {
        if (self::check()) {
            $user = self::user();
            $target = match ($user['role'] ?? '') {
                'admin' => '/admin',
                'petugas' => '/petugas',
                default => '/laporan',
            };
            Response::redirect($target);
        }
    }

    /**
     * Verify if current user can view or manage a specific report
     * - Admin: Can access all reports
     * - Petugas: Can access reports assigned to them
     * - Masyarakat: Can access only reports they created
     */
    public static function canAccessReport(int $reportId): bool {
        self::requireAuth();
        $user = self::user();
        $userId = (int)$user['id'];
        $role = $user['role'] ?? '';

        if ($role === 'admin') {
            return true;
        }

        $report = Report::findById($reportId);
        if (!$report) {
            return false;
        }

        if ($role === 'masyarakat') {
            return (int)$report['user_id'] === $userId;
        }

        if ($role === 'petugas') {
            $officer = Officer::findByUserId($userId);
            if (!$officer) {
                return false;
            }
            $assignments = Assignment::getByReport($reportId);
            foreach ($assignments as $a) {
                if ((int)$a['officer_id'] === (int)$officer['id']) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Enforce report access guard, abort 403 if unauthorized
     */
    public static function requireReportAccess(int $reportId): array {
        self::requireAuth();
        $report = Report::findById($reportId);

        if (!$report) {
            http_response_code(404);
            view('errors.404', ['title' => 'Laporan Tidak Ditemukan']);
            exit;
        }

        if (!self::canAccessReport($reportId)) {
            self::abort403('Anda tidak memiliki izin untuk melihat atau mengelola laporan ini.');
        }

        return $report;
    }
}
