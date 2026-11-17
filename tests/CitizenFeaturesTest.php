<?php
/**
 * Automated Test Suite for Citizen (Masyarakat) Features & Authorization
 * Run via: C:\xampp\php\php.exe tests/CitizenFeaturesTest.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Models\User;
use App\Models\Report;
use App\Models\Assignment;
use App\Models\RepairProof;
use App\Middleware\AuthMiddleware;

class CitizenFeaturesTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo " 🏙️ RUNNING CITIZEN (MASYARAKAT) FEATURES TEST SUITE\n";
        echo "======================================================\n\n";

        $this->testCitizenMetricsCalculation();
        $this->testCitizenReportCreation();
        $this->testCitizenOwnReportsIsolation();
        $this->testCitizenReportDetailAndTimeline();
        $this->testCitizenAccessOtherUserReportForbidden();
        $this->testFilterAndSearch();

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
     * 1. Test Citizen Metric Calculation
     */
    private function testCitizenMetricsCalculation(): void {
        echo "[1] Testing Dashboard Metrics Calculation for Citizen...\n";

        $warga = User::findByEmail('warga@laporfasilitas.id');
        $this->assert('User warga exists (user_id = 4)', $warga !== null);

        $reports = Report::getByUser((int)$warga['id']);
        $stats = [
            'total'    => count($reports),
            'menunggu' => 0,
            'diproses' => 0,
            'selesai'  => 0,
            'ditolak'  => 0,
        ];

        foreach ($reports as $r) {
            match ($r['status']) {
                'Menunggu Verifikasi'                         => $stats['menunggu']++,
                'Diverifikasi', 'Ditugaskan', 'Sedang Diperbaiki' => $stats['diproses']++,
                'Selesai'                                     => $stats['selesai']++,
                'Ditolak'                                     => $stats['ditolak']++,
                default                                       => null,
            };
        }

        $this->assert('Metrics total count is accurate', $stats['total'] === count($reports));
        $this->assert('Metrics finished (selesai) count > 0', $stats['selesai'] >= 1);
        $this->assert('Metrics in-progress (diproses) count > 0', $stats['diproses'] >= 1);
    }

    /**
     * 2. Test Report Creation by Citizen
     */
    private function testCitizenReportCreation(): void {
        echo "\n[2] Testing Report Creation by Citizen...\n";

        $warga = User::findByEmail('warga@laporfasilitas.id');

        $newReportId = Report::create([
            'user_id'           => (int)$warga['id'],
            'title'             => 'Test Laporan Jalan Rusak di Dekat Pasar',
            'description'       => 'Aspal amblas sedalam 15cm di depan pintu masuk pasar tradisional.',
            'photo'             => '/uploads/reports/test_jalan.jpg',
            'latitude'          => -6.21000000,
            'longitude'         => 106.84000000,
            'address'           => 'Jl. Pasar Minggu No. 10, Jakarta Selatan',
            'facility_category' => 'Jalan & Trotoar',
            'status'            => 'Menunggu Verifikasi',
        ]);

        $this->assert('Report created successfully with ID', $newReportId > 0);

        $createdReport = Report::findById($newReportId);
        $this->assert('Created report initial status is Menunggu Verifikasi', $createdReport && $createdReport['status'] === 'Menunggu Verifikasi');
        $this->assert('Created report has correct facility category', $createdReport['facility_category'] === 'Jalan & Trotoar');
        $this->assert('Created report has coordinates', (float)$createdReport['latitude'] === -6.21);

        // Clean up test report
        Report::delete($newReportId);
        $this->assert('Cleaned up test report', Report::findById($newReportId) === null);
    }

    /**
     * 3. Test Isolation of Citizen Reports
     */
    private function testCitizenOwnReportsIsolation(): void {
        echo "\n[3] Testing Isolation: Citizen only sees own reports...\n";

        $warga = User::findByEmail('warga@laporfasilitas.id'); // ID = 4
        $budi = User::findByEmail('budi@laporfasilitas.id');   // ID = 5

        $wargaReports = Report::getByUser((int)$warga['id']);
        $budiReports = Report::getByUser((int)$budi['id']);

        $this->assert('Warga has reports', count($wargaReports) > 0);
        $this->assert('Budi has reports', count($budiReports) > 0);

        $wargaReportIds = array_column($wargaReports, 'id');
        $budiReportIds = array_column($budiReports, 'id');

        // Ensure no overlap
        $overlap = array_intersect($wargaReportIds, $budiReportIds);
        $this->assert('No report overlap between different citizens', empty($overlap));
    }

    /**
     * 4. Test Report Detail, Assignments & Timeline
     */
    private function testCitizenReportDetailAndTimeline(): void {
        echo "\n[4] Testing Report Detail, Assignments, and Timeline...\n";

        // Report 1 is Selesai, user_id = 4
        $report1 = Report::findById(1);
        $this->assert('Report #1 exists', $report1 !== null);

        $assignments = Assignment::getByReport(1);
        $this->assert('Report #1 has assignments', count($assignments) > 0);

        $repairProofs = RepairProof::getByReport(1);
        $this->assert('Report #1 has repair proofs documentation', count($repairProofs) > 0);
        $this->assert('Repair proof has photo and notes', !empty($repairProofs[0]['photo']) && !empty($repairProofs[0]['notes']));
    }

    /**
     * 5. Test Access Control: Citizen cannot access another user's report
     */
    private function testCitizenAccessOtherUserReportForbidden(): void {
        echo "\n[5] Testing RBAC: Citizen cannot access other user's report...\n";

        // Log in as Warga (id=4)
        $_SESSION['user'] = [
            'id'    => 4,
            'name'  => 'Siti Rahmawati',
            'email' => 'warga@laporfasilitas.id',
            'role'  => 'masyarakat',
        ];

        // Report 1 belongs to Warga (id=4) -> ALLOWED
        $this->assert('Citizen can access own report #1', AuthMiddleware::canAccessReport(1) === true);

        // Report 3 belongs to Budi (id=5) -> FORBIDDEN
        $this->assert('Citizen CANNOT access other citizen report #3 (Forbidden)', AuthMiddleware::canAccessReport(3) === false);
    }

    /**
     * 6. Test Filtering and Searching
     */
    private function testFilterAndSearch(): void {
        echo "\n[6] Testing Report Filtering & Searching...\n";

        $allReports = Report::getAll();
        
        $selesaiReports = Report::getAll(['status' => 'Selesai']);
        $this->assert('Filter by status Selesai returns completed reports', count($selesaiReports) > 0);
        foreach ($selesaiReports as $r) {
            $this->assert('Report status is Selesai', $r['status'] === 'Selesai');
            break;
        }

        $searchResult = Report::getAll(['search' => 'Bundaran HI']);
        $this->assert('Search by keyword Bundaran HI returns matching report', count($searchResult) >= 1);
        $this->assert('Search result contains keyword in title or address', str_contains($searchResult[0]['title'], 'Bundaran HI') || str_contains($searchResult[0]['address'], 'Bundaran HI'));
    }
}

// Run test suite
$suite = new CitizenFeaturesTest();
$suite->run();
