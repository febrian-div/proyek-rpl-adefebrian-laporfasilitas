<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<!-- Hero Section -->
<section class="hero mb-4" style="margin-top: -2rem; margin-left: -1.5rem; margin-right: -1.5rem; padding-left: 2rem; padding-right: 2rem;">
    <div style="max-width: 800px;">
        <span class="brand-badge mb-2" style="display: inline-block;">Platform Kolaborasi Publik</span>
        <h1>Laporkan Fasilitas Rusak, <br><span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Dianalisis Cerdas Oleh AI</span></h1>
        <p>Bantu pemerintah daerah mendeteksi dan memperbaiki jalan berlubang, lampu padam, dan saluran mampet dengan cepat dan terukur.</p>
        
        <div class="flex gap-2" style="flex-wrap: wrap;">
            <a href="<?= base_url('/laporan/buat') ?>" class="btn btn-gradient btn-lg">
                <i class="fa-solid fa-camera"></i> Buat Laporan Sekarang
            </a>
            <a href="#kategori" class="btn btn-secondary btn-lg" style="color: var(--secondary); background: rgba(255,255,255,0.9);">
                <i class="fa-solid fa-layer-group"></i> Lihat Kategori
            </a>
        </div>
    </div>
</section>

<!-- Flow Steps Section -->
<div class="mb-4">
    <div class="text-center mb-3">
        <h2 class="text-2xl font-bold">Alur Kerja Sistem</h2>
        <p class="text-muted text-sm">Bagaimana laporan Anda diproses dari awal hingga tuntas</p>
    </div>

    <div class="grid grid-4">
        <div class="card text-center">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--primary-light);color:var(--primary);margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;">1</div>
            <h3 class="font-semibold text-lg mb-1">Ambil Foto & Lokasi</h3>
            <p class="text-xs text-muted">Foto kerusakan fasilitas dan tentukan titik koordinat di peta interaktif.</p>
        </div>
        <div class="card text-center">
            <div style="width:48px;height:48px;border-radius:50%;background:#fef3c7;color:#d97706;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;">2</div>
            <h3 class="font-semibold text-lg mb-1">Analisis Otomatis AI</h3>
            <p class="text-xs text-muted">AI menganalisis jenis kerusakan, kategori, dan estimasi tingkat keparahan.</p>
        </div>
        <div class="card text-center">
            <div style="width:48px;height:48px;border-radius:50%;background:#ede9fe;color:#7c3aed;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;">3</div>
            <h3 class="font-semibold text-lg mb-1">Verifikasi & Penugasan</h3>
            <p class="text-xs text-muted">Admin memvalidasi data dan langsung menugaskan petugas terkait.</p>
        </div>
        <div class="card text-center">
            <div style="width:48px;height:48px;border-radius:50%;background:#d1fae5;color:#059669;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;">4</div>
            <h3 class="font-semibold text-lg mb-1">Perbaikan & Selesai</h3>
            <p class="text-xs text-muted">Petugas menyelesaikan perbaikan dan mengunggah foto bukti hasil kerja.</p>
        </div>
    </div>
</div>

<!-- Categories Grid -->
<div id="kategori" class="mb-4">
    <div class="text-center mb-3">
        <h2 class="text-2xl font-bold">Kategori Fasilitas Publik</h2>
        <p class="text-muted text-sm">Pilih jenis fasilitas umum yang ingin Anda laporkan</p>
    </div>

    <div class="grid grid-3">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat): ?>
                <div class="card flex gap-2 items-center">
                    <div style="font-size: 1.75rem; color: var(--primary); width: 50px; text-align: center;">
                        <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-landmark') ?>"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-sm"><?= htmlspecialchars($cat['name']) ?></h4>
                        <p class="text-xs text-muted"><?= htmlspecialchars($cat['description']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card text-center" style="grid-column: 1 / -1;">
                <p class="text-muted">Database kategori akan dimuat setelah database disinkronkan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
