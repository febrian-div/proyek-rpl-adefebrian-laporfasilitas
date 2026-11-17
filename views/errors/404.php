<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="card text-center" style="max-width: 500px; margin: 4rem auto; padding: 3rem 1.5rem;">
    <div style="font-size: 4rem; color: var(--primary); margin-bottom: 1rem;">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h1 class="text-2xl font-bold mb-1">404 - Halaman Tidak Ditemukan</h1>
    <p class="text-sm text-muted mb-4">Halaman yang Anda cari mungkin telah dipindahkan atau URL salah diketikkan.</p>
    <a href="<?= base_url('/') ?>" class="btn btn-primary">
        <i class="fa-solid fa-house"></i> Kembali ke Beranda
    </a>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
