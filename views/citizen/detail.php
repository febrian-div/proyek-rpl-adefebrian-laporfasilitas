<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<?php
$ticketCode = 'LPF-' . str_pad((string)$report['id'], 5, '0', STR_PAD_LEFT);
$status = $report['status'];

// Compute step index for timeline progress bar
// 1: Menunggu Verifikasi, 2: Diverifikasi, 3: Ditugaskan, 4: Sedang Diperbaiki, 5: Selesai
$stepIndex = match ($status) {
    'Menunggu Verifikasi' => 1,
    'Diverifikasi'        => 2,
    'Ditugaskan'          => 3,
    'Sedang Diperbaiki'   => 4,
    'Selesai'             => 5,
    'Ditolak'             => -1,
    default               => 1,
};

$badgeClass = match($status) {
    'Menunggu Verifikasi' => 'badge-pending',
    'Diverifikasi'        => 'badge-verified',
    'Ditugaskan'          => 'badge-assigned',
    'Sedang Diperbaiki'   => 'badge-process',
    'Selesai'             => 'badge-done',
    'Ditolak'             => 'badge-rejected',
    default               => 'badge-pending'
};
?>

<!-- Breadcrumb -->
<div class="mb-3 flex items-center justify-between" style="flex-wrap: wrap; gap: 0.5rem;">
    <a href="<?= base_url('/laporan') ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat Laporan
    </a>
    <span class="text-xs font-semibold text-muted">ID Tiket: <strong>#<?= $ticketCode ?></strong></span>
</div>

<!-- Header Card -->
<div class="card mb-4">
    <div class="flex justify-between items-center" style="flex-wrap: wrap; gap: 1rem;">
        <div>
            <div class="flex items-center gap-1 mb-1" style="flex-wrap: wrap;">
                <span class="badge <?= $badgeClass ?>" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;">
                    <i class="fa-solid <?= $status === 'Selesai' ? 'fa-check-circle' : ($status === 'Ditolak' ? 'fa-circle-xmark' : 'fa-clock') ?>"></i>
                    <?= htmlspecialchars($status) ?>
                </span>
                <?php if (!empty($report['priority'])): ?>
                    <span class="badge badge-severity-<?= strtolower($report['priority']) ?>">
                        Prioritas: <?= htmlspecialchars($report['priority']) ?>
                    </span>
                <?php endif; ?>
                <span class="text-xs text-muted">
                    <i class="fa-regular fa-clock"></i> Dilaporkan pada <?= date('d M Y, H:i', strtotime($report['created_at'])) ?> WIB
                </span>
            </div>
            <h1 class="text-2xl font-bold" style="color: var(--secondary);"><?= htmlspecialchars($report['title']) ?></h1>
        </div>
    </div>

    <!-- Step Progress Bar -->
    <?php if ($status !== 'Ditolak'): ?>
        <div class="timeline-steps-bar mt-4 mb-2">
            <div class="timeline-step-node">
                <div class="step-bullet <?= $stepIndex >= 1 ? ($stepIndex > 1 ? 'completed' : 'active') : '' ?>">
                    <?= $stepIndex > 1 ? '<i class="fa-solid fa-check"></i>' : '1' ?>
                </div>
                <span class="step-name <?= $stepIndex === 1 ? 'active' : '' ?>">Menunggu Verifikasi</span>
            </div>

            <div class="timeline-step-node">
                <div class="step-bullet <?= $stepIndex >= 2 ? ($stepIndex > 2 ? 'completed' : 'active') : '' ?>">
                    <?= $stepIndex > 2 ? '<i class="fa-solid fa-check"></i>' : '2' ?>
                </div>
                <span class="step-name <?= $stepIndex === 2 ? 'active' : '' ?>">Diverifikasi</span>
            </div>

            <div class="timeline-step-node">
                <div class="step-bullet <?= $stepIndex >= 3 ? ($stepIndex > 3 ? 'completed' : 'active') : '' ?>">
                    <?= $stepIndex > 3 ? '<i class="fa-solid fa-check"></i>' : '3' ?>
                </div>
                <span class="step-name <?= $stepIndex === 3 ? 'active' : '' ?>">Ditugaskan</span>
            </div>

            <div class="timeline-step-node">
                <div class="step-bullet <?= $stepIndex >= 4 ? ($stepIndex > 4 ? 'completed' : 'active') : '' ?>">
                    <?= $stepIndex > 4 ? '<i class="fa-solid fa-check"></i>' : '4' ?>
                </div>
                <span class="step-name <?= $stepIndex === 4 ? 'active' : '' ?>">Sedang Diperbaiki</span>
            </div>

            <div class="timeline-step-node">
                <div class="step-bullet <?= $stepIndex >= 5 ? 'completed' : '' ?>">
                    <?= $stepIndex >= 5 ? '<i class="fa-solid fa-check"></i>' : '5' ?>
                </div>
                <span class="step-name <?= $stepIndex === 5 ? 'active' : '' ?>">Selesai</span>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-error mt-3 mb-1">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Laporan Ditolak:</strong> Laporan ini tidak dapat diproses lebih lanjut oleh admin.
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-2" style="align-items: start;">
    <!-- Left Column: Detail Kerusakan, Foto & Peta Lokasi -->
    <div>
        <!-- Foto Kerusakan -->
        <div class="card mb-4">
            <h3 class="font-bold text-lg mb-2"><i class="fa-solid fa-camera"></i> Foto Fasilitas Rusak</h3>
            <?php
                $photoUrl = !empty($report['photo']) ? htmlspecialchars($report['photo']) : 'https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?w=800&auto=format&fit=crop&q=80';
            ?>
            <img src="<?= $photoUrl ?>" alt="<?= htmlspecialchars($report['title']) ?>" style="width: 100%; max-height: 380px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);" onerror="this.src='https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?w=800&auto=format&fit=crop&q=80'">

            <div class="mt-3">
                <div class="flex justify-between items-center mb-2" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <span class="text-xs text-muted font-semibold">Kategori Fasilitas:</span>
                    <span class="font-bold text-sm" style="color: var(--primary);">
                        <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($report['facility_category'] ?? 'Fasilitas Umum') ?>
                    </span>
                </div>
                
                <?php if (!empty($report['damage_type'])): ?>
                    <div class="flex justify-between items-center mb-2" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                        <span class="text-xs text-muted font-semibold">Jenis Kerusakan:</span>
                        <span class="font-semibold text-sm"><?= htmlspecialchars($report['damage_type']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="mt-2">
                    <span class="text-xs text-muted font-semibold block mb-1">Deskripsi Lengkap:</span>
                    <p class="text-sm" style="line-height: 1.6; color: var(--text-main); background: var(--bg-main); padding: 0.85rem; border-radius: var(--radius-sm);">
                        <?= nl2br(htmlspecialchars($report['description'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Peta Lokasi -->
        <div class="card mb-4">
            <h3 class="font-bold text-lg mb-2"><i class="fa-solid fa-map-location-dot"></i> Titik Lokasi Fasilitas</h3>
            <p class="text-xs text-muted mb-2">
                <i class="fa-solid fa-location-dot"></i> <strong>Alamat:</strong> <?= htmlspecialchars($report['address']) ?>
            </p>
            <div id="map-viewer" class="map-viewer-container"></div>
            <div class="flex justify-between text-xs text-muted mt-2">
                <span>Latitude: <?= $report['latitude'] ?></span>
                <span>Longitude: <?= $report['longitude'] ?></span>
            </div>
        </div>
    </div>

    <!-- Right Column: Timeline Riwayat Proses & Bukti Perbaikan -->
    <div>
        <!-- Timeline Proses Laporan -->
        <div class="card mb-4">
            <h3 class="font-bold text-lg mb-1"><i class="fa-solid fa-clock-rotate-left"></i> Timeline Proses Laporan</h3>
            <p class="text-xs text-muted mb-3">Histori penanganan laporan dari awal verifikasi hingga penyelesaian</p>

            <div class="timeline-list">
                <!-- Step 1: Laporan Masuk (Selalu ada dari created_at report) -->
                <div class="timeline-entry">
                    <div class="timeline-marker" style="background: #2563eb;"></div>
                    <div class="timeline-content">
                        <div class="timeline-time"><?= date('d M Y, H:i', strtotime($report['created_at'])) ?> WIB</div>
                        <h4 class="font-bold text-sm">Laporan Diterima Sistem</h4>
                        <p class="text-xs text-muted mt-1">Laporan berhasil didaftarkan oleh pelapor dan masuk ke antrean verifikasi admin.</p>
                    </div>
                </div>

                <!-- Dynamic Timeline from Database -->
                <?php if (!empty($timeline)): ?>
                    <?php foreach ($timeline as $t): 
                        $tlColor = match($t['status']) {
                            'Diverifikasi' => '#3b82f6',
                            'Ditugaskan' => '#8b5cf6',
                            'Sedang Diperbaiki' => '#0891b2',
                            'Selesai' => '#10b981',
                            'Ditolak' => '#ef4444',
                            default => '#6b7280'
                        };
                    ?>
                        <div class="timeline-entry">
                            <div class="timeline-marker" style="background: <?= $tlColor ?>;"></div>
                            <div class="timeline-content" <?= in_array($t['status'], ['Selesai', 'Ditolak']) ? 'style="border-left: 3px solid '.$tlColor.';"' : '' ?>>
                                <div class="timeline-time"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?> WIB</div>
                                <h4 class="font-bold text-sm" <?= in_array($t['status'], ['Selesai', 'Ditolak']) ? 'style="color: '.$tlColor.';"' : '' ?>>
                                    <?php if ($t['status'] === 'Selesai'): ?>
                                        <i class="fa-solid fa-circle-check"></i> 
                                    <?php endif; ?>
                                    Status: <?= htmlspecialchars($t['status']) ?>
                                </h4>
                                <?php if (!empty($t['note'])): ?>
                                    <p class="text-xs text-muted mt-1"><?= nl2br(htmlspecialchars($t['note'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bukti Foto Hasil Perbaikan (Jika Ada) -->
        <?php if (!empty($repairProofs)): ?>
            <div class="card mb-4" style="border-left: 4px solid #10b981;">
                <h3 class="font-bold text-lg mb-2" style="color: #065f46;">
                    <i class="fa-solid fa-certificate"></i> Bukti Hasil Perbaikan Selesai
                </h3>
                <p class="text-xs text-muted mb-3">Foto dokumentasi hasil pengerjaan perbaikan oleh teknisi lapangan</p>

                <?php foreach ($repairProofs as $proof): ?>
                    <div class="mb-3">
                        <?php
                            $proofImg = !empty($proof['photo']) ? htmlspecialchars($proof['photo']) : 'https://images.unsplash.com/photo-1541888946425-d0fbb180c5f5?w=800&auto=format&fit=crop&q=80';
                        ?>
                        <img src="<?= $proofImg ?>" alt="Bukti Perbaikan" style="width: 100%; max-height: 240px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);" onerror="this.src='https://images.unsplash.com/photo-1541888946425-d0fbb180c5f5?w=800&auto=format&fit=crop&q=80'">
                        <?php if (!empty($proof['notes'])): ?>
                            <p class="text-xs mt-2" style="background: var(--bg-main); padding: 0.75rem; border-radius: 6px; color: var(--secondary);">
                                <strong>Catatan Teknisi:</strong> <?= htmlspecialchars($proof['notes']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Contact Support Card -->
        <div class="card" style="background: var(--bg-main);">
            <h4 class="font-bold text-sm mb-1"><i class="fa-solid fa-headset"></i> Butuh Bantuan?</h4>
            <p class="text-xs text-muted mb-2">Jika terdapat pembaruan mendesak terkait laporan ini, hubungi pusat layanan pengaduan publik.</p>
            <span class="text-xs font-semibold" style="color: var(--primary);">
                <i class="fa-brands fa-whatsapp"></i> Call Center: 0800-1-LAPOR (Bebas Pulsa)
            </span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inisialisasi peta Leaflet Viewer
    if (window.LaporMap) {
        window.LaporMap.initViewer(
            'map-viewer', 
            <?= (float)$report['latitude'] ?>, 
            <?= (float)$report['longitude'] ?>, 
            '<?= addslashes(htmlspecialchars($report['title'])) ?>'
        );
    }
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
