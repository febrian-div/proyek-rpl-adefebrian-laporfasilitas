<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<!-- Header & CTA Banner -->
<div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff; border: none; padding: 2rem;">
    <div class="flex justify-between items-center" style="flex-wrap: wrap; gap: 1.25rem;">
        <div>
            <span class="brand-badge" style="background: rgba(255,255,255,0.15); color: #38bdf8; margin-bottom: 0.5rem; display: inline-block;">
                Portal Warga
            </span>
            <h1 class="text-2xl font-extrabold" style="color: #ffffff; line-height: 1.2;">
                Halo, <?= htmlspecialchars($user['name']) ?> 👋
            </h1>
            <p class="text-sm" style="color: #cbd5e1; margin-top: 0.25rem;">
                Pantau status perbaikan fasilitas umum yang Anda laporkan di lingkungan sekitar Anda.
            </p>
        </div>
        <a href="<?= base_url('/laporan/buat') ?>" class="btn btn-gradient btn-lg" style="box-shadow: 0 4px 20px rgba(6, 182, 212, 0.4);">
            <i class="fa-solid fa-plus"></i> Buat Laporan Baru
        </a>
    </div>
</div>

<!-- 4 Metrics Stat Cards -->
<div class="grid grid-4 mb-4">
    <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total'] ?></span>
            <span class="stat-label">Total Laporan</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['menunggu'] ?></span>
            <span class="stat-label">Menunggu</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #cffafe; color: #0891b2;">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['diproses'] ?></span>
            <span class="stat-label">Diproses</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['selesai'] ?></span>
            <span class="stat-label">Selesai</span>
        </div>
    </div>
</div>

<!-- Filter Bar & Search -->
<div class="card mb-4" style="padding: 1.25rem;">
    <div class="flex justify-between items-center" style="flex-wrap: wrap; gap: 1rem;">
        <div class="filter-bar">
            <a href="<?= base_url('/laporan') ?>" class="filter-pill <?= empty($currentFilter) ? 'active' : '' ?>">
                Semua <span class="count"><?= $stats['total'] ?></span>
            </a>
            <a href="<?= base_url('/laporan?status=menunggu+verifikasi') ?>" class="filter-pill <?= $currentFilter === 'menunggu verifikasi' ? 'active' : '' ?>">
                <i class="fa-solid fa-hourglass-start"></i> Menunggu <span class="count"><?= $stats['menunggu'] ?></span>
            </a>
            <a href="<?= base_url('/laporan?status=diproses') ?>" class="filter-pill <?= $currentFilter === 'diproses' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i> Diproses <span class="count"><?= $stats['diproses'] ?></span>
            </a>
            <a href="<?= base_url('/laporan?status=selesai') ?>" class="filter-pill <?= $currentFilter === 'selesai' ? 'active' : '' ?>">
                <i class="fa-solid fa-check"></i> Selesai <span class="count"><?= $stats['selesai'] ?></span>
            </a>
            <?php if ($stats['ditolak'] > 0): ?>
                <a href="<?= base_url('/laporan?status=ditolak') ?>" class="filter-pill <?= $currentFilter === 'ditolak' ? 'active' : '' ?>">
                    <i class="fa-solid fa-xmark"></i> Ditolak <span class="count"><?= $stats['ditolak'] ?></span>
                </a>
            <?php endif; ?>
        </div>

        <form action="<?= base_url('/laporan') ?>" method="GET" style="display: flex; gap: 0.5rem; width: 100%; max-width: 320px;">
            <?php if (!empty($currentFilter)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($currentFilter) ?>">
            <?php endif; ?>
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" class="form-control" placeholder="Cari judul atau lokasi..." value="<?= htmlspecialchars($currentSearch ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
        </form>
    </div>
</div>

<!-- Daftar Laporan Saya (Card Grid) -->
<div class="mb-4">
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-xl font-bold">Daftar Laporan Saya</h2>
        <span class="text-xs text-muted">Menampilkan <?= count($reports) ?> laporan</span>
    </div>

    <?php if (!empty($reports)): ?>
        <div class="grid grid-3">
            <?php foreach ($reports as $r): ?>
                <?php
                    $badgeClass = match($r['status']) {
                        'Menunggu Verifikasi' => 'badge-pending',
                        'Diverifikasi'        => 'badge-verified',
                        'Ditugaskan'          => 'badge-assigned',
                        'Sedang Diperbaiki'   => 'badge-process',
                        'Selesai'             => 'badge-done',
                        'Ditolak'             => 'badge-rejected',
                        default               => 'badge-pending'
                    };
                    $photoSrc = !empty($r['photo']) ? htmlspecialchars($r['photo']) : 'https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?w=500&auto=format&fit=crop&q=60';
                ?>
                <div class="report-item-card">
                    <img src="<?= $photoSrc ?>" alt="<?= htmlspecialchars($r['title']) ?>" class="report-thumb" onerror="this.src='https://images.unsplash.com/photo-1515162816999-a0c47dc192f7?w=500&auto=format&fit=crop&q=60'">
                    
                    <div class="report-item-body">
                        <div class="report-item-meta">
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span>
                            <?php if (!empty($r['priority'])): ?>
                                <span class="badge badge-severity-<?= strtolower($r['priority']) ?>">
                                    <?= htmlspecialchars($r['priority']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="report-item-title">
                            <a href="<?= base_url('/laporan/' . $r['id']) ?>"><?= htmlspecialchars($r['title']) ?></a>
                        </h3>

                        <div class="text-xs text-muted mb-2">
                            <i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($r['facility_category'] ?? 'Fasilitas Umum') ?>
                        </div>

                        <p class="report-item-desc">
                            <?= htmlspecialchars(mb_substr($r['description'], 0, 95)) ?>...
                        </p>

                        <div class="report-item-footer">
                            <span title="<?= htmlspecialchars($r['address']) ?>" style="max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($r['address']) ?>
                            </span>
                            <span>
                                <i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($r['created_at'])) ?>
                            </span>
                        </div>

                        <a href="<?= base_url('/laporan/' . $r['id']) ?>" class="btn btn-secondary btn-sm mt-2" style="width: 100%;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Detail & Timeline
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card text-center" style="padding: 3.5rem 1.5rem;">
            <div style="font-size: 3.5rem; color: var(--text-light); margin-bottom: 1rem;">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
            <h3 class="text-lg font-bold mb-1">Tidak Ada Laporan Ditemukan</h3>
            <p class="text-sm text-muted mb-3">
                <?php if (!empty($currentFilter) || !empty($currentSearch)): ?>
                    Tidak ada laporan yang sesuai dengan filter atau pencarian Anda.
                <?php else: ?>
                    Anda belum pernah membuat laporan kerusakan fasilitas umum.
                <?php endif; ?>
            </p>
            <div class="flex gap-2 justify-center">
                <?php if (!empty($currentFilter) || !empty($currentSearch)): ?>
                    <a href="<?= base_url('/laporan') ?>" class="btn btn-secondary btn-sm">Reset Filter</a>
                <?php endif; ?>
                <a href="<?= base_url('/laporan/buat') ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Buat Laporan Pertama Anda
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
