<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="mb-4">
    <h1 class="text-2xl font-bold">Dashboard Petugas Lapangan</h1>
    <p class="text-sm text-muted">Selamat datang kembali, <strong><?= htmlspecialchars($officer['name']) ?></strong> (Area: <?= htmlspecialchars($officer['service_area']) ?>)</p>
</div>

<!-- Statistik Panel -->
<div class="grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
    
    <div class="card" style="border-left: 4px solid var(--primary-color);">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-muted">Total Penugasan</h3>
            <div class="icon text-primary"><i class="fa-solid fa-clipboard-list text-xl"></i></div>
        </div>
        <div class="text-3xl font-bold"><?= $stats['total'] ?></div>
    </div>
    
    <div class="card" style="border-left: 4px solid #8b5cf6;">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-muted">Tugas Baru</h3>
            <div class="icon" style="color: #8b5cf6;"><i class="fa-solid fa-bell text-xl"></i></div>
        </div>
        <div class="text-3xl font-bold"><?= $stats['baru'] ?></div>
    </div>
    
    <div class="card" style="border-left: 4px solid #0891b2;">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-muted">Sedang Dikerjakan</h3>
            <div class="icon" style="color: #0891b2;"><i class="fa-solid fa-person-digging text-xl"></i></div>
        </div>
        <div class="text-3xl font-bold"><?= $stats['proses'] ?></div>
    </div>
    
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-muted">Tugas Selesai</h3>
            <div class="icon" style="color: #10b981;"><i class="fa-solid fa-check-double text-xl"></i></div>
        </div>
        <div class="text-3xl font-bold"><?= $stats['selesai'] ?></div>
    </div>

</div>

<!-- Quick Action & List -->
<div class="grid grid-2 gap-4" style="align-items: start;">
    <div class="card">
        <div class="card-header flex justify-between items-center mb-3 pb-2 border-b">
            <h3 class="font-bold text-lg"><i class="fa-solid fa-clock-rotate-left"></i> 5 Tugas Terbaru</h3>
            <a href="<?= base_url('/petugas/tugas') ?>" class="btn btn-sm btn-secondary">Lihat Semua</a>
        </div>
        
        <?php if (!empty($recentTasks)): ?>
            <div class="timeline-list">
                <?php foreach ($recentTasks as $t): 
                    $badgeClass = match($t['assignment_status']) {
                        'Ditugaskan' => 'badge-assigned',
                        'Sedang Dikerjakan' => 'badge-process',
                        'Selesai' => 'badge-done',
                        default => 'badge-pending'
                    };
                ?>
                <div class="timeline-entry" style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <div class="timeline-marker" style="background: var(--primary-color);"></div>
                    <div class="timeline-content">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-sm text-primary">#LPF-<?= str_pad((string)$t['id'], 5, '0', STR_PAD_LEFT) ?></h4>
                            <span class="badge <?= $badgeClass ?> text-xs"><?= htmlspecialchars($t['assignment_status']) ?></span>
                        </div>
                        <p class="font-semibold text-sm mb-1"><?= htmlspecialchars($t['title']) ?></p>
                        <p class="text-xs text-muted mb-2"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($t['address']) ?></p>
                        <a href="<?= base_url('/petugas/tugas/' . $t['id']) ?>" class="btn btn-sm btn-primary text-xs">Lihat Detail & Tindakan</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-sm text-center py-4">Belum ada tugas baru yang ditugaskan kepada Anda.</p>
        <?php endif; ?>
    </div>

    <div class="card" style="background: var(--bg-secondary);">
        <h3 class="font-bold text-lg mb-2"><i class="fa-solid fa-bullhorn"></i> Panduan Petugas</h3>
        <ul class="text-sm space-y-2 text-main" style="list-style: decimal; padding-left: 1.25rem;">
            <li>Periksa daftar <strong>Tugas Baru</strong> Anda setiap saat.</li>
            <li>Saat tiba di lokasi perbaikan, pastikan menekan tombol <strong>"Mulai Dikerjakan"</strong>.</li>
            <li>Gunakan alat pelindung diri (K3) sesuai SOP daerah setempat.</li>
            <li>Setelah pekerjaan fisik rampung, ambil <strong>Foto Bukti</strong> yang jelas dan unggah ke sistem.</li>
            <li>Tambahkan <strong>Catatan Perbaikan</strong> untuk Admin dan pelapor.</li>
            <li>Selesaikan tugas hanya apabila seluruh perbaikan benar-benar rampung.</li>
        </ul>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
