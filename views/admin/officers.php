<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-2xl font-bold">Manajemen Petugas</h1>
        <p class="text-sm text-muted">Daftar petugas lapangan dan status beban kerja mereka</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('/admin') ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="card">
    <?php if (!empty($officers)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 0.5rem;">Nama & Kontak</th>
                        <th style="padding: 0.75rem 0.5rem;">Area Layanan</th>
                        <th style="padding: 0.75rem 0.5rem;">Tugas Aktif</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($officers as $off): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 0.5rem;">
                                <div class="font-semibold text-base"><?= htmlspecialchars($off['name']) ?></div>
                                <div class="text-xs text-muted"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($off['email']) ?></div>
                                <div class="text-xs text-muted"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($off['phone']) ?></div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span class="badge badge-verified"><i class="fa-solid fa-map-location-dot"></i> <?= htmlspecialchars($off['service_area']) ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <?php if ($off['active_tasks'] > 0): ?>
                                    <span class="badge" style="background-color: var(--status-process); color: white;">
                                        <?= $off['active_tasks'] ?> Laporan
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-verified" style="background-color: var(--status-done);">Tersedia</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <button class="btn btn-secondary btn-sm" onclick="alert('Fitur edit petugas akan segera hadir')">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center" style="padding: 2.5rem 1rem;">
            <p class="text-muted">Belum ada data petugas yang terdaftar.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
