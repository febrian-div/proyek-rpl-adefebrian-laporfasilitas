<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-2xl font-bold">Semua Laporan</h1>
        <p class="text-sm text-muted">Manajemen seluruh laporan masyarakat (Total: <?= $totalReports ?>)</p>
    </div>
    <a href="<?= base_url('/admin') ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
</div>

<!-- Filter & Search Bar -->
<div class="card mb-4" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
    <form action="<?= base_url('/admin/semua-laporan') ?>" method="GET" class="grid" style="grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label text-xs">Pencarian</label>
            <input type="text" name="search" class="form-control" placeholder="Judul, Lokasi..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label text-xs">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <?php 
                    $statuses = ['Menunggu Verifikasi', 'Diverifikasi', 'Ditolak', 'Ditugaskan', 'Sedang Diperbaiki', 'Selesai'];
                    foreach ($statuses as $st):
                ?>
                    <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label text-xs">Kategori</label>
            <select name="kategori" class="form-control">
                <option value="">Semua Kategori</option>
                <?php 
                    $categories = ['Jalan', 'Lampu Jalan', 'Trotoar', 'Taman', 'Tempat Sampah', 'Saluran Air', 'Fasilitas Umum Lainnya'];
                    foreach ($categories as $cat):
                ?>
                    <option value="<?= $cat ?>" <?= ($filters['facility_category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label text-xs">Prioritas</label>
            <select name="prioritas" class="form-control">
                <option value="">Semua Prioritas</option>
                <?php 
                    $priorities = ['Rendah', 'Sedang', 'Tinggi', 'Kritis'];
                    foreach ($priorities as $pr):
                ?>
                    <option value="<?= $pr ?>" <?= ($filters['priority'] ?? '') === $pr ? 'selected' : '' ?>><?= $pr ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label text-xs">Urutkan Tanggal</label>
            <select name="dir" class="form-control">
                <option value="DESC" <?= ($filters['direction'] ?? '') === 'DESC' ? 'selected' : '' ?>>Terbaru (DESC)</option>
                <option value="ASC" <?= ($filters['direction'] ?? '') === 'ASC' ? 'selected' : '' ?>>Terlama (ASC)</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <button type="submit" class="btn btn-primary h-full">Filter</button>
            <?php if (array_filter($filters, fn($k) => in_array($k, ['search', 'status', 'facility_category', 'priority']) && !empty($filters[$k]), ARRAY_FILTER_USE_KEY)): ?>
                <a href="<?= base_url('/admin/semua-laporan') ?>" class="btn btn-secondary h-full ml-1" title="Reset Filter"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <?php if (!empty($reports)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 0.5rem;">Tiket</th>
                        <th style="padding: 0.75rem 0.5rem;">Pelapor</th>
                        <th style="padding: 0.75rem 0.5rem;">Judul & Kategori</th>
                        <th style="padding: 0.75rem 0.5rem;">Status & Prioritas</th>
                        <th style="padding: 0.75rem 0.5rem;">Petugas</th>
                        <th style="padding: 0.75rem 0.5rem;">Tanggal</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 0.5rem; font-weight: 600;">#LPF-<?= str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($r['reporter_name']) ?></td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <div class="font-semibold"><?= htmlspecialchars($r['title']) ?></div>
                                <span class="text-xs text-muted"><?= htmlspecialchars($r['facility_category'] ?? '-') ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <div class="mb-1"><span class="badge badge-verified"><?= htmlspecialchars($r['status']) ?></span></div>
                                <?php if ($r['priority']): ?>
                                    <span class="badge badge-severity-<?= strtolower($r['priority']) ?>"><?= htmlspecialchars($r['priority']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;"><?= htmlspecialchars($r['officer_name'] ?? '-') ?></td>
                            <td style="padding: 0.75rem 0.5rem;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <a href="<?= base_url('/admin/laporan/' . $r['id']) ?>" class="btn btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <div class="flex gap-1">
                    <?php
                        // Build query string for pagination links
                        $qsData = $_GET;
                        unset($qsData['page']);
                        $qs = http_build_query($qsData);
                        $qs = $qs ? '&' . $qs : '';
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $qs ?>" class="btn btn-secondary btn-sm">&laquo; Prev</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $qs ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $qs ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center" style="padding: 2.5rem 1rem;">
            <p class="text-muted">Tidak ada laporan yang sesuai dengan kriteria.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
