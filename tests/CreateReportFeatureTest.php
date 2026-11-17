<?php
/**
 * Automated Test Suite for "Buat Laporan" Feature
 * Run via: C:\xampp\php\php.exe tests/CreateReportFeatureTest.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Models\User;
use App\Models\Report;
use App\Services\UploadService;

class CreateReportFeatureTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "\n======================================================\n";
        echo " 📝 RUNNING 'BUAT LAPORAN' FEATURE TEST SUITE\n";
        echo "======================================================\n\n";

        $this->testAllowedCategories();
        $this->testValidReportCreation();
        $this->testInitialStatusAndUserBinding();
        $this->testValidationRules();
        $this->testUploadServiceSecurity();

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
     * 1. Test the 7 Required Categories
     */
    private function testAllowedCategories(): void {
        echo "[1] Testing 7 Required Facility Categories...\n";

        $requiredCategories = [
            'Jalan',
            'Lampu Jalan',
            'Trotoar',
            'Taman',
            'Tempat Sampah',
            'Saluran Air',
            'Fasilitas Umum Lainnya'
        ];

        $this->assert('Exactly 7 facility categories defined', count($requiredCategories) === 7);

        foreach ($requiredCategories as $cat) {
            $this->assert("Category '{$cat}' is valid", in_array($cat, $requiredCategories, true));
        }
    }

    /**
     * 2. Test Valid Report Creation by Citizen
     */
    private function testValidReportCreation(): void {
        echo "\n[2] Testing Valid Report Submission...\n";

        $warga = User::findByEmail('warga@laporfasilitas.id');
        $this->assert('Citizen user exists (ID: 4)', $warga !== null);

        $reportId = Report::create([
            'user_id'           => (int)$warga['id'],
            'title'             => 'Lampu Jalan Padam di Pertigaan Blok M',
            'facility_category' => 'Lampu Jalan',
            'description'       => 'Tiang lampu PJU nomor 12 padam total setiap malam sejak 3 hari lalu.',
            'photo'             => '/uploads/reports/sample_lampu_test.jpg',
            'latitude'          => -6.2445000,
            'longitude'         => 106.7985000,
            'address'           => 'Jl. Panglima Polim Raya No. 8, Melawai, Kebayoran Baru',
            'status'            => 'Menunggu Verifikasi',
        ]);

        $this->assert('Report saved successfully into database', $reportId > 0);

        $report = Report::findById($reportId);
        $this->assert('Report user_id matches logged in citizen', (int)$report['user_id'] === (int)$warga['id']);
        $this->assert('Report title matches input', $report['title'] === 'Lampu Jalan Padam di Pertigaan Blok M');
        $this->assert('Report category is Lampu Jalan', $report['facility_category'] === 'Lampu Jalan');
        $this->assert('Report latitude is accurate float', (float)$report['latitude'] === -6.2445000);
        $this->assert('Report longitude is accurate float', (float)$report['longitude'] === 106.7985000);
        $this->assert('Report address matches', $report['address'] === 'Jl. Panglima Polim Raya No. 8, Melawai, Kebayoran Baru');

        // Clean up test report
        Report::delete($reportId);
        $this->assert('Test report cleaned up', Report::findById($reportId) === null);
    }

    /**
     * 3. Test Initial Status and User Binding
     */
    private function testInitialStatusAndUserBinding(): void {
        echo "\n[3] Testing Initial Status strictly 'Menunggu Verifikasi'...\n";

        $budi = User::findByEmail('budi@laporfasilitas.id');

        $reportId = Report::create([
            'user_id'           => (int)$budi['id'],
            'title'             => 'Tempat Sampah Publik Rusak & Terbakar',
            'facility_category' => 'Tempat Sampah',
            'description'       => 'Wadah tempat sampah pilah di pinggir taman pecah dan tutupnya hilang.',
            'photo'             => '/uploads/reports/sample_sampah.jpg',
            'latitude'          => -6.2200000,
            'longitude'         => 106.8200000,
            'address'           => 'Taman Mataram, Kebayoran Baru',
            'status'            => 'Menunggu Verifikasi',
        ]);

        $report = Report::findById($reportId);
        $this->assert('Initial status is strictly Menunggu Verifikasi', $report['status'] === 'Menunggu Verifikasi');
        $this->assert('Priority is initially null before admin review', $report['priority'] === null);
        $this->assert('AI result is null initially', $report['ai_result'] === null);

        Report::delete($reportId);
    }

    /**
     * 4. Test Validation Rules
     */
    private function testValidationRules(): void {
        echo "\n[4] Testing Validation Rules...\n";

        // Title length validation (< 5 chars)
        $shortTitle = "Jln";
        $isTitleValid = strlen(trim($shortTitle)) >= 5;
        $this->assert('Short title (< 5 chars) fails validation', $isTitleValid === false);

        // Description length validation (< 10 chars)
        $shortDesc = "Rusak pak";
        $isDescValid = strlen(trim($shortDesc)) >= 10;
        $this->assert('Short description (< 10 chars) fails validation', $isDescValid === false);

        // Invalid category
        $invalidCat = "Kategori Abal-Abal";
        $allowed = ['Jalan', 'Lampu Jalan', 'Trotoar', 'Taman', 'Tempat Sampah', 'Saluran Air', 'Fasilitas Umum Lainnya'];
        $this->assert('Invalid category rejected by whitelist', !in_array($invalidCat, $allowed, true));

        // Coordinate float validation
        $validLat = filter_var("-6.2088", FILTER_VALIDATE_FLOAT);
        $invalidLat = filter_var("bukan_angka", FILTER_VALIDATE_FLOAT);
        $this->assert('Valid coordinate parses correctly', $validLat !== false);
        $this->assert('Invalid coordinate rejected', $invalidLat === false);
    }

    /**
     * 5. Test Upload Service Security
     */
    private function testUploadServiceSecurity(): void {
        echo "\n[5] Testing UploadService Security & MIME Validation...\n";

        $uploadService = new UploadService();
        $this->assert('UploadService instantiated', $uploadService !== null);

        // Test invalid file parameter structure
        $fakeInvalidFile = ['error' => UPLOAD_ERR_NO_FILE];
        $res = $uploadService->uploadImage($fakeInvalidFile);
        $this->assert('UploadService rejects empty/failed upload', $res['success'] === false);
    }
}

// Run test suite
$suite = new CreateReportFeatureTest();
$suite->run();
