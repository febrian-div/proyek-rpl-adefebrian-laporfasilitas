<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="flex justify-between items-center mb-3">
    <div>
        <h1 class="text-2xl font-bold">Dashboard Administrator</h1>
        <p class="text-sm text-muted">Ringkasan statistik pelaporan fasilitas umum</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('/admin/semua-laporan') ?>" class="btn btn-primary"><i class="fa-solid fa-list"></i> Semua Laporan</a>
        <a href="<?= base_url('/admin/petugas') ?>" class="btn btn-secondary"><i class="fa-solid fa-users"></i> Manajemen Petugas</a>
        <a href="<?= base_url('/admin/konfigurasi-ai') ?>" class="btn btn-secondary"><i class="fa-solid fa-robot"></i> Konfigurasi AI</a>
    </div>
</div>

<!-- Quick Metric Cards -->
<div class="grid grid-4 mb-4 gap-4">
    <div class="card" style="border-left: 4px solid var(--primary-color);">
        <span class="text-xs font-semibold text-muted">TOTAL LAPORAN</span>
        <h2 class="text-2xl font-extrabold mt-1"><?= count($reports ?? []) ?></h2>
    </div>
    <div class="card" style="border-left: 4px solid var(--status-pending);">
        <span class="text-xs font-semibold text-muted">MENUNGGU VERIFIKASI</span>
        <h2 class="text-2xl font-extrabold mt-1 text-pending">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Menunggu Verifikasi')) ?>
        </h2>
    </div>
    <div class="card" style="border-left: 4px solid #3b82f6;"> <!-- blue -->
        <span class="text-xs font-semibold text-muted">DIVERIFIKASI</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: #3b82f6;">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Diverifikasi')) ?>
        </h2>
    </div>
    <div class="card" style="border-left: 4px solid #8b5cf6;"> <!-- purple -->
        <span class="text-xs font-semibold text-muted">DITUGASKAN</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: #8b5cf6;">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Ditugaskan')) ?>
        </h2>
    </div>
    <div class="card" style="border-left: 4px solid var(--status-process);">
        <span class="text-xs font-semibold text-muted">SEDANG DIPERBAIKI</span>
        <h2 class="text-2xl font-extrabold mt-1">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Sedang Diperbaiki')) ?>
        </h2>
    </div>
    <div class="card" style="border-left: 4px solid var(--status-done);">
        <span class="text-xs font-semibold text-muted">SELESAI</span>
        <h2 class="text-2xl font-extrabold mt-1">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Selesai')) ?>
        </h2>
    </div>
    <div class="card" style="border-left: 4px solid var(--status-rejected);">
        <span class="text-xs font-semibold text-muted">DITOLAK</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: var(--status-rejected);">
            <?= count(array_filter($reports ?? [], fn($r) => $r['status'] === 'Ditolak')) ?>
        </h2>
    </div>
</div>

<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="font-bold text-lg">Laporan Terbaru</h3>
        <a href="<?= base_url('/admin/semua-laporan') ?>" class="text-primary text-sm hover:underline">Lihat Semua</a>
    </div>

    <?php 
    $recentReports = array_slice($reports ?? [], 0, 5);
    if (!empty($recentReports)): 
    ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 0.5rem;">Tiket</th>
                        <th style="padding: 0.75rem 0.5rem;">Judul & Kategori</th>
                        <th style="padding: 0.75rem 0.5rem;">Status</th>
                        <th style="padding: 0.75rem 0.5rem;">Tanggal</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReports as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 0.5rem; font-weight: 600;">#LPF-<?= str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <div class="font-semibold"><?= htmlspecialchars($r['title']) ?></div>
                                <span class="text-xs text-muted"><?= htmlspecialchars($r['facility_category'] ?? 'Belum Dikategorikan') ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span class="badge badge-verified"><?= htmlspecialchars($r['status']) ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <a href="<?= base_url('/admin/laporan/' . $r['id']) ?>" class="btn btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center" style="padding: 2.5rem 1rem;">
            <p class="text-muted text-sm">Belum ada data laporan masuk.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
