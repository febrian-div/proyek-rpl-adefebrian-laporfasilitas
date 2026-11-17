<?php
/**
 * LaporFasilitas - Test Runner
 * Run via: c:\xampp\php\php.exe tests/run_all_tests.php
 */

$testFiles = glob(__DIR__ . '/*Test.php');

echo "=========================================\n";
echo "LaporFasilitas - Automated Test Suite\n";
echo "=========================================\n\n";

if (empty($testFiles)) {
    echo "Tidak ditemukan file test.\n";
    exit;
}

foreach ($testFiles as $file) {
    echo "Running " . basename($file) . "...\n";
    echo "-----------------------------------------\n";
    
    // Using shell_exec to isolate test execution
    $output = shell_exec("c:\\xampp\\php\\php.exe " . escapeshellarg($file));
    echo $output;
    echo "\n\n";
}

echo "=========================================\n";
echo "All test suites completed.\n";
echo "=========================================\n";
