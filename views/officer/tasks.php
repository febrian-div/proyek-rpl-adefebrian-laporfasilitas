<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="flex justify-between items-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="text-2xl font-bold">Daftar Tugas Saya</h1>
        <p class="text-sm text-muted">Manajemen pekerjaan perbaikan fasilitas umum</p>
    </div>
    
    <div class="flex gap-2" style="flex-wrap: wrap;">
        <a href="<?= base_url('/petugas/tugas') ?>" class="btn btn-sm <?= empty($currentFilter) ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
        <a href="<?= base_url('/petugas/tugas?status=baru') ?>" class="btn btn-sm <?= $currentFilter === 'baru' ? 'btn-primary' : 'btn-secondary' ?>">Baru</a>
        <a href="<?= base_url('/petugas/tugas?status=proses') ?>" class="btn btn-sm <?= $currentFilter === 'proses' ? 'btn-primary' : 'btn-secondary' ?>">Diproses</a>
        <a href="<?= base_url('/petugas/tugas?status=selesai') ?>" class="btn btn-sm <?= $currentFilter === 'selesai' ? 'btn-primary' : 'btn-secondary' ?>">Selesai</a>
    </div>
</div>

<div class="card">
    <?php if (empty($tasks)): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-box-open text-4xl text-muted mb-3"></i>
            <h3 class="font-bold text-lg">Tidak Ada Tugas</h3>
            <p class="text-sm text-muted mt-1">Belum ada data tugas yang sesuai dengan pencarian atau filter Anda.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 0.5rem;">ID & Laporan</th>
                        <th style="padding: 0.75rem 0.5rem;">Kategori & Prioritas</th>
                        <th style="padding: 0.75rem 0.5rem;">Lokasi</th>
                        <th style="padding: 0.75rem 0.5rem;">Status Tugas</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t): 
                        $badgeClass = match($t['assignment_status']) {
                            'Ditugaskan' => 'badge-assigned',
                            'Sedang Dikerjakan' => 'badge-process',
                            'Selesai' => 'badge-done',
                            default => 'badge-pending'
                        };
                    ?>
                        <tr style="border-bottom: 1px solid var(--border-color); <?= $t['assignment_status'] === 'Ditugaskan' ? 'background-color: var(--bg-secondary);' : '' ?>">
                            <td style="padding: 0.75rem 0.5rem;">
                                <div class="font-bold text-primary mb-1">#LPF-<?= str_pad((string)$t['id'], 5, '0', STR_PAD_LEFT) ?></div>
                                <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                                <div class="text-xs text-muted"><?= date('d M Y', strtotime($t['assigned_at'])) ?></div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <div><i class="fa-solid fa-layer-group text-muted"></i> <?= htmlspecialchars($t['facility_category'] ?? '-') ?></div>
                                <?php if (!empty($t['priority'])): ?>
                                    <div class="mt-1">
                                        <span class="badge badge-severity-<?= strtolower($t['priority']) ?> text-xs" style="padding: 0.15rem 0.4rem;"><?= htmlspecialchars($t['priority']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <i class="fa-solid fa-map-location-dot text-muted"></i> <?= htmlspecialchars($t['address']) ?>
                                </div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($t['assignment_status']) ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <a href="<?= base_url('/petugas/tugas/' . $t['id']) ?>" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
