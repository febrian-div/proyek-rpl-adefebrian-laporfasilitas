<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="card text-center" style="max-width: 540px; margin: 4rem auto; padding: 3rem 1.5rem; border-top: 4px solid #ef4444;">
    <div style="font-size: 4rem; color: #ef4444; margin-bottom: 1rem;">
        <i class="fa-solid fa-shield-halved"></i>
    </div>
    <h1 class="text-2xl font-bold mb-1" style="color: var(--secondary);">403 - Akses Ditolak</h1>
    <p class="text-sm text-muted mb-4">
        <?= htmlspecialchars($errorMessage ?? 'Anda tidak memiliki hak akses atau izin untuk melihat halaman/sumber daya ini.') ?>
    </p>

    <div class="flex gap-2 justify-center">
        <?php if (\App\Middleware\AuthMiddleware::check()): ?>
            <?php
                $role = \App\Middleware\AuthMiddleware::user()['role'] ?? '';
                $homePath = match($role) {
                    'admin' => '/admin',
                    'petugas' => '/petugas',
                    default => '/laporan'
                };
            ?>
            <a href="<?= base_url($homePath) ?>" class="btn btn-primary">
                <i class="fa-solid fa-gauge"></i> Kembali ke Dashboard
            </a>
        <?php else: ?>
            <a href="<?= base_url('/login') ?>" class="btn btn-primary">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk Akun
            </a>
        <?php endif; ?>
        <a href="<?= base_url('/') ?>" class="btn btn-secondary">
            <i class="fa-solid fa-house"></i> Beranda
        </a>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
