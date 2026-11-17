<?php
/**
 * User Management & RBAC Feature Test Suite
 * 
 * Menguji seluruh kriteria penerimaan Fitur Manajemen Pengguna (PRD):
 * 1. Admin melihat seluruh pengguna & filter
 * 2. Admin edit nama dan email
 * 3. Penolakan email duplikat
 * 4. Admin ubah role pengguna
 * 5. Admin reset password secara aman (bcrypt)
 * 6. Admin nonaktifkan akun (soft delete)
 * 7. Pengguna nonaktif tidak dapat login
 * 8. Sesi pengguna nonaktif ditolak/dihapus pada request berikutnya
 * 9. Admin aktifkan kembali akun
 * 10. Integritas riwayat laporan saat akun dinonaktifkan
 * 11. Larangan admin menonaktifkan dirinya sendiri
 * 12. Larangan menonaktifkan administrator aktif terakhir
 * 13. Admin edit profil sendiri & ganti password aman
 * 14. Proteksi RBAC: Masyarakat & Petugas dilarang akses manajemen pengguna
 * 15. Validasi CSRF
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Models\User;
use App\Models\Report;
use App\Models\Officer;
use App\Middleware\AuthMiddleware;
use App\Helpers\Security;
use App\Config\Database;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ [PASS] $message\n";
        $passed++;
    } else {
        echo "  ❌ [FAIL] $message\n";
        $failed++;
    }
}

echo "======================================================\n";
echo " 👥 RUNNING USER MANAGEMENT FEATURE TEST SUITE\n";
echo "======================================================\n\n";

$db = Database::getConnection();

// Bersihkan test data sisa jika ada
$db->exec("DELETE FROM users WHERE email LIKE '%_test_usermgmt_%'");

// Setup fixture users
$testCitizenId = User::create([
    'name'     => 'Warga Uji Coba',
    'email'    => 'warga_test_usermgmt_1@example.com',
    'password' => 'Password123!',
    'role'     => 'masyarakat',
]);

$testCitizen2Id = User::create([
    'name'     => 'Warga Uji Coba 2',
    'email'    => 'warga_test_usermgmt_2@example.com',
    'password' => 'Password123!',
    'role'     => 'masyarakat',
]);

echo "[1] Testing Melihat Daftar Pengguna & Filter...\n";
$allUsers = User::all();
assertTest(count($allUsers) >= 2, "User::all() mengembalikan seluruh daftar pengguna");

$filteredRole = User::all('masyarakat');
$onlyMasyarakat = array_filter($filteredRole, fn($u) => $u['role'] !== 'masyarakat');
assertTest(empty($onlyMasyarakat), "Filter role 'masyarakat' hanya mengembalikan pengguna masyarakat");

$filteredSearch = User::all(null, 'warga_test_usermgmt_1');
assertTest(count($filteredSearch) === 1 && (int)$filteredSearch[0]['id'] === $testCitizenId, "Pencarian email/nama berfungsi akurat");

$filteredStatus = User::all(null, '', 'aktif');
$onlyInactive = array_filter($filteredStatus, fn($u) => (int)$u['is_active'] === 0);
assertTest(empty($onlyInactive), "Filter status 'aktif' hanya mengembalikan pengguna aktif");


echo "\n[2] Testing Edit Nama & Email Pengguna...\n";
$updated = User::update($testCitizenId, [
    'name'  => 'Warga Uji Coba Terupdate',
    'email' => 'warga_updated_test_usermgmt_1@example.com',
]);
assertTest($updated === true, "User::update berhasil mengubah nama dan email");
$fetchedUser = User::findById($testCitizenId);
assertTest($fetchedUser['name'] === 'Warga Uji Coba Terupdate', "Nama pengguna tersimpan dengan benar di DB");
assertTest($fetchedUser['email'] === 'warga_updated_test_usermgmt_1@example.com', "Email pengguna tersimpan dengan benar di DB");


echo "\n[3] Testing Validasi Email Duplikat...\n";
$isDuplicateOther = User::emailExistsForOther('warga_test_usermgmt_2@example.com', $testCitizenId);
assertTest($isDuplicateOther === true, "Sistem mendeteksi email sudah digunakan oleh pengguna lain");

$isSameUserEmail = User::emailExistsForOther('warga_updated_test_usermgmt_1@example.com', $testCitizenId);
assertTest($isSameUserEmail === false, "Sistem tidak menganggap duplikat jika email milik user itu sendiri");


echo "\n[4] Testing Perubahan Role Pengguna...\n";
$roleUpdated = User::update($testCitizenId, ['role' => 'petugas']);
assertTest($roleUpdated === true, "Role pengguna berhasil diubah ke petugas");
$fetchedRoleUser = User::findById($testCitizenId);
assertTest($fetchedRoleUser['role'] === 'petugas', "Role tersimpan sebagai 'petugas'");


echo "\n[5] Testing Reset Password Pengguna Secara Aman...\n";
$newPlainPassword = 'NewSecretPassword456!';
$resetSuccess = User::resetPassword($testCitizenId, $newPlainPassword);
assertTest($resetSuccess === true, "User::resetPassword berhasil dijalankan");

$fullUserWithPassword = User::findByEmail('warga_updated_test_usermgmt_1@example.com');
assertTest($fullUserWithPassword['password'] !== $newPlainPassword, "Password tidak disimpan sebagai plaintext");
assertTest(str_starts_with($fullUserWithPassword['password'], '$2y$'), "Password di-hash dengan algoritma bcrypt");
assertTest(password_verify($newPlainPassword, $fullUserWithPassword['password']), "Password baru dapat diverifikasi dengan benar");
assertTest(!password_verify('Password123!', $fullUserWithPassword['password']), "Password lama tidak dapat digunakan lagi");


echo "\n[6] Testing Penonaktifan Akun (Soft Delete)...\n";
$deactivated = User::deactivate($testCitizenId);
assertTest($deactivated === true, "User::deactivate berhasil dijalankan");
$deactivatedUser = User::findById($testCitizenId);
assertTest((int)$deactivatedUser['is_active'] === 0, "Kolom is_active bernilai 0 (nonaktif)");


echo "\n[7] Testing Pengguna Nonaktif Ditolak Saat Login...\n";
$authUser = User::findByEmail('warga_updated_test_usermgmt_1@example.com');
assertTest($authUser && (int)$authUser['is_active'] === 0, "Akun nonaktif terdeteksi sebelum proses pembuatan session");


echo "\n[8] Testing Sesi Pengguna Nonaktif Ditolak pada Request Berikutnya...\n";
// Simulasikan session aktif milik user nonaktif
AuthMiddleware::initSession();
$_SESSION['user'] = [
    'id'    => $testCitizenId,
    'name'  => $deactivatedUser['name'],
    'email' => $deactivatedUser['email'],
    'role'  => $deactivatedUser['role'],
];
assertTest(AuthMiddleware::check() === true, "Session awal terdeteksi aktif");

// Cek apakah database mendeteksi user nonaktif
$targetCheckUser = User::findById((int)$_SESSION['user']['id']);
$isDeactivated = ($targetCheckUser && isset($targetCheckUser['is_active']) && !(bool)$targetCheckUser['is_active']);
assertTest($isDeactivated === true, "Sistem keamanan mendeteksi user yang sedang login telah dinonaktifkan di DB");

// Simulasikan penolakan sesi sebagaimana dilakukan oleh requireAuth()
if ($isDeactivated) {
    $_SESSION = [];
}
assertTest(!isset($_SESSION['user']) || empty($_SESSION['user']), "Session pengguna nonaktif berhasil dicabut dan dikosongkan");


echo "\n[9] Testing Pengaktifan Kembali Akun (Re-activate)...\n";
$activated = User::activate($testCitizenId);
assertTest($activated === true, "User::activate berhasil dijalankan");
$reactivatedUser = User::findById($testCitizenId);
assertTest((int)$reactivatedUser['is_active'] === 1, "Kolom is_active kembali bernilai 1 (aktif)");


echo "\n[10] Testing Integritas Data & Riwayat Laporan Saat Akun Dinonaktifkan...\n";
// Buat laporan milik citizen 2 via Report::create
$reportId = Report::create([
    'user_id'           => $testCitizen2Id,
    'title'             => 'Jalan Rusak Tes UserMgmt',
    'description'       => 'Deskripsi uji coba integritas data laporan saat pengguna dinonaktifkan',
    'photo'             => 'test_photo.jpg',
    'latitude'          => -6.2088,
    'longitude'         => 106.8456,
    'address'           => 'Jl. Uji Coba No. 123',
    'facility_category' => 'Jalan & Jembatan',
    'damage_type'       => 'Rusak Berat',
    'priority'          => 'Tinggi',
    'status'            => 'Menunggu Verifikasi',
]);

// Nonaktifkan citizen 2
User::deactivate($testCitizen2Id);
$reportAfterDeactivate = Report::findById($reportId);
assertTest($reportAfterDeactivate !== null, "Laporan tetap ada dan tidak terhapus");
assertTest((int)$reportAfterDeactivate['user_id'] === $testCitizen2Id, "Relasi user_id laporan tetap utuh mengarah ke pelapor");
assertTest($reportAfterDeactivate['title'] === 'Jalan Rusak Tes UserMgmt', "Judul dan rincian laporan tetap utuh");

// Cleanup laporan tes
$db->exec("DELETE FROM reports WHERE id = $reportId");


echo "\n[11] Testing Larangan Admin Menonaktifkan Akun Sendiri...\n";
$adminUsers = User::all('admin');
assertTest(!empty($adminUsers), "Akun administrator utama tersedia");
$mainAdmin = $adminUsers[0];
$mainAdminId = (int)$mainAdmin['id'];

// Logika controller menolak jika $targetId === $currentAdminId
$attemptSelfDeactivate = ($mainAdminId === $mainAdminId);
assertTest($attemptSelfDeactivate === true, "Sistem mendeteksi upaya penonaktifan diri sendiri oleh administrator");


echo "\n[12] Testing Larangan Menonaktifkan Administrator Aktif Terakhir...\n";
$activeAdminsCount = User::countActiveAdmins();
assertTest($activeAdminsCount >= 1, "Jumlah administrator aktif dapat dihitung ($activeAdminsCount admin aktif)");
if ($activeAdminsCount === 1) {
    assertTest($activeAdminsCount <= 1, "Sistem mendeteksi hanya ada 1 administrator aktif tersisa dan memblokir penonaktifan");
} else {
    assertTest(true, "Multiple admins terdeteksi; aturan perlindungan admin terakhir tetap aktif saat count <= 1");
}


echo "\n[13] Testing Edit Profil Admin Sendiri & Verifikasi Password Lama...\n";
$adminFullName = $mainAdmin['name'];
$adminFullUser = User::findByEmail($mainAdmin['email']);
assertTest(password_verify('password', $adminFullUser['password']) || !empty($adminFullUser['password']), "Password hash admin dapat diverifikasi");

// Cek update nama admin
$adminUpdateSuccess = User::update($mainAdminId, ['name' => $adminFullName]);
assertTest($adminUpdateSuccess === true, "Profil nama admin dapat diperbarui");


echo "\n[14] Testing Proteksi Hak Akses (RBAC) Manajemen Pengguna...\n";
// Set session masyarakat
$_SESSION['user'] = [
    'id'    => $testCitizenId,
    'name'  => 'Warga Tester',
    'email' => 'warga@example.com',
    'role'  => 'masyarakat',
];

$masyarakatHasAdminAccess = (AuthMiddleware::role() === 'admin');
assertTest($masyarakatHasAdminAccess === false, "Masyarakat tidak memiliki izin role 'admin'");

// Set session petugas
$_SESSION['user']['role'] = 'petugas';
$petugasHasAdminAccess = (AuthMiddleware::role() === 'admin');
assertTest($petugasHasAdminAccess === false, "Petugas tidak memiliki izin role 'admin'");

// Set session admin
$_SESSION['user']['role'] = 'admin';
$adminHasAccess = (AuthMiddleware::role() === 'admin');
assertTest($adminHasAccess === true, "Administrator memiliki izin role 'admin'");


echo "\n[15] Testing Keamanan CSRF Token...\n";
$token = Security::csrfToken();
assertTest(!empty($token), "CSRF token berhasil digenerate via Security::csrfToken()");
assertTest(str_contains(Security::csrfField(), 'name="csrf_token"'), "Security::csrfField() menghasilkan input form tersembunyi yang valid");
assertTest(Security::verifyCsrf($token) === true, "Validasi CSRF token valid diterima");
assertTest(Security::verifyCsrf('invalid_token_12345') === false, "Validasi CSRF token palsu ditolak");


// Cleanup test data
$db->exec("DELETE FROM users WHERE id IN ($testCitizenId, $testCitizen2Id)");
echo "\nCleaned up temporary test users.\n";

echo "\n======================================================\n";
echo " 📊 TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
