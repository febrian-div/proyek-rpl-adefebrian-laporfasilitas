<?php
/**
 * Automated Test Suite for Authentication & Role-Based Access Control (RBAC)
 * Run via: C:\xampp\php\php.exe tests/AuthAndRBACTest.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Officer;
use App\Models\Report;
use App\Models\Assignment;
use App\Middleware\AuthMiddleware;
use App\Helpers\Security;

class AuthAndRBACTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo " 🧪 RUNNING AUTHENTICATION & RBAC TEST SUITE\n";
        echo "======================================================\n\n";

        $this->testPasswordHashing();
        $this->testLoginSuccess();
        $this->testLoginFailed();
        $this->testRegisterMasyarakat();
        $this->testGuestProtection();
        $this->testMasyarakatAccessControl();
        $this->testAdminAccessControl();
        $this->testPetugasAccessControl();
        $this->testGranularReportAccessControl();

        echo "\n======================================================\n";
        echo " 📊 TEST RESULTS: {$this->passed} PASSED, {$this->failed} FAILED\n";
        echo "======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(string $testName, bool $condition, string $detail = ''): void {
        if ($condition) {
            $this->passed++;
            echo "  ✅ [PASS] {$testName}\n";
        } else {
            $this->failed++;
            echo "  ❌ [FAIL] {$testName} - Detail: {$detail}\n";
        }
    }

    /**
     * 1. Test Password Hashing Security
     */
    private function testPasswordHashing(): void {
        echo "[1] Testing Password Hashing & Encryption...\n";
        
        $user = User::findByEmail('admin@laporfasilitas.id');
        $this->assert('User password in DB is hashed', $user && str_starts_with($user['password'], '$2y$'));
        $this->assert('Password is not stored in plaintext', $user['password'] !== 'password123');
        $this->assert('password_verify validates correct password', password_verify('password123', $user['password']));
        $this->assert('password_verify rejects wrong password', !password_verify('wrongpassword', $user['password']));
    }

    /**
     * 2. Test Login Success for all 3 Roles
     */
    private function testLoginSuccess(): void {
        echo "\n[2] Testing Login Success (Admin, Petugas, Masyarakat)...\n";

        // Admin
        $admin = User::findByEmail('admin@laporfasilitas.id');
        $this->assert('Admin user exists and role is admin', $admin && $admin['role'] === 'admin');

        // Petugas
        $petugas = User::findByEmail('petugas1@laporfasilitas.id');
        $this->assert('Petugas user exists and role is petugas', $petugas && $petugas['role'] === 'petugas');

        // Masyarakat
        $warga = User::findByEmail('warga@laporfasilitas.id');
        $this->assert('Masyarakat user exists and role is masyarakat', $warga && $warga['role'] === 'masyarakat');
    }

    /**
     * 3. Test Login Failures
     */
    private function testLoginFailed(): void {
        echo "\n[3] Testing Login Failure Cases...\n";

        $nonExistent = User::findByEmail('nonexistent@user.com');
        $this->assert('Non-existent email returns null', $nonExistent === null);

        $admin = User::findByEmail('admin@laporfasilitas.id');
        $isWrongPasswordValid = password_verify('invalid_pass_123', $admin['password']);
        $this->assert('Incorrect password verification returns false', $isWrongPasswordValid === false);
    }

    /**
     * 4. Test Public Registration (Enforced as Masyarakat)
     */
    private function testRegisterMasyarakat(): void {
        echo "\n[4] Testing Public Registration & Role Enforcement...\n";

        $testEmail = 'newuser_' . time() . '@test.com';
        $newUserId = User::create([
            'name'     => 'Tester Warga Baru',
            'email'    => $testEmail,
            'password' => 'rahasia123',
            'role'     => 'masyarakat',
        ]);

        $this->assert('New user registered successfully', $newUserId > 0);

        $createdUser = User::findByEmail($testEmail);
        $this->assert('Registered user role is strictly masyarakat', $createdUser && $createdUser['role'] === 'masyarakat');
        $this->assert('Registered user password is encrypted with bcrypt', $createdUser && str_starts_with($createdUser['password'] ?? '', '$2y$'));
        $this->assert('Registered user password can be verified', password_verify('rahasia123', $createdUser['password'] ?? ''));

        // Clean up test user
        User::delete($newUserId);
        $this->assert('Cleaned up temporary test user', User::findById($newUserId) === null);
    }

    /**
     * 5. Test Guest & Unauthorized Protection
     */
    private function testGuestProtection(): void {
        echo "\n[5] Testing Session State & Guest Protection...\n";

        // Simulate guest session (empty)
        $_SESSION['user'] = null;
        $this->assert('AuthMiddleware::check() returns false when session is empty', AuthMiddleware::check() === false);
        $this->assert('AuthMiddleware::user() returns null for guest', AuthMiddleware::user() === null);
        $this->assert('AuthMiddleware::role() returns null for guest', AuthMiddleware::role() === null);
    }

    /**
     * 6. Test Masyarakat RBAC
     */
    private function testMasyarakatAccessControl(): void {
        echo "\n[6] Testing Masyarakat RBAC Rules...\n";

        $warga = User::findByEmail('warga@laporfasilitas.id');
        $_SESSION['user'] = [
            'id'    => $warga['id'],
            'name'  => $warga['name'],
            'email' => $warga['email'],
            'role'  => 'masyarakat',
        ];

        $this->assert('AuthMiddleware::check() is true for logged-in masyarakat', AuthMiddleware::check() === true);
        $this->assert('Role is recognized as masyarakat', AuthMiddleware::role() === 'masyarakat');

        // Check reports belonging to warga (user_id = 4 in seeds)
        $ownReports = Report::getByUser((int)$warga['id']);
        $this->assert('Masyarakat can fetch own reports', count($ownReports) > 0);

        // Ensure all returned reports belong to this user
        $allBelongToUser = true;
        foreach ($ownReports as $r) {
            if ((int)$r['user_id'] !== (int)$warga['id']) {
                $allBelongToUser = false;
            }
        }
        $this->assert('All fetched reports belong strictly to current citizen', $allBelongToUser);
    }

    /**
     * 7. Test Admin RBAC
     */
    private function testAdminAccessControl(): void {
        echo "\n[7] Testing Admin RBAC Rules...\n";

        $admin = User::findByEmail('admin@laporfasilitas.id');
        $_SESSION['user'] = [
            'id'    => $admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => 'admin',
        ];

        $this->assert('AuthMiddleware::role() is admin', AuthMiddleware::role() === 'admin');

        $allReports = Report::getAll();
        $this->assert('Admin can view all reports across all users', count($allReports) >= 4);

        $allOfficers = Officer::all();
        $this->assert('Admin can view and manage all officers', count($allOfficers) >= 2);
    }

    /**
     * 8. Test Petugas RBAC
     */
    private function testPetugasAccessControl(): void {
        echo "\n[8] Testing Petugas RBAC Rules...\n";

        $petugas = User::findByEmail('petugas1@laporfasilitas.id');
        $officerProfile = Officer::findByUserId((int)$petugas['id']);

        $_SESSION['user'] = [
            'id'    => $petugas['id'],
            'name'  => $petugas['name'],
            'email' => $petugas['email'],
            'role'  => 'petugas',
        ];

        $this->assert('Petugas role is recognized', AuthMiddleware::role() === 'petugas');
        $this->assert('Petugas has officer profile', $officerProfile !== null);

        $assignedTasks = Officer::assignedReports((int)$officerProfile['id']);
        $this->assert('Petugas can view reports assigned to them', count($assignedTasks) > 0);
    }

    /**
     * 9. Test Granular Report Access Control (canAccessReport)
     */
    private function testGranularReportAccessControl(): void {
        echo "\n[9] Testing Granular Report Access Control...\n";

        // Report 1: user_id = 4 (warga), assigned to officer 1 (petugas1)
        // Report 3: user_id = 5 (budi), no assignment

        // A. Admin can access any report
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $this->assert('Admin can access Report #1', AuthMiddleware::canAccessReport(1));
        $this->assert('Admin can access Report #3', AuthMiddleware::canAccessReport(3));

        // B. Warga (id=4) can access Report #1 (theirs), but cannot access Report #3 (Budi's)
        $_SESSION['user'] = ['id' => 4, 'role' => 'masyarakat'];
        $this->assert('Warga can access own Report #1', AuthMiddleware::canAccessReport(1));
        $this->assert('Warga CANNOT access another user Report #3 (Forbidden)', !AuthMiddleware::canAccessReport(3));

        // C. Petugas 1 (user_id=2) can access Report #1 (assigned to them), but cannot access unassigned Report #3
        $_SESSION['user'] = ['id' => 2, 'role' => 'petugas'];
        $this->assert('Petugas 1 can access assigned Report #1', AuthMiddleware::canAccessReport(1));
        $this->assert('Petugas 1 CANNOT access unassigned Report #3 (Forbidden)', !AuthMiddleware::canAccessReport(3));
    }
}

// Run test suite
$suite = new AuthAndRBACTest();
$suite->run();
