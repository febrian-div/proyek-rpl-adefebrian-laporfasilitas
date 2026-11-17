<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex justify-center" style="min-height: 70vh; align-items: center;">
    <div class="card" style="width: 100%; max-width: 440px;">
        <div class="card-header text-center" style="display: block;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="text-xl font-bold">Masuk ke Akun Anda</h2>
            <p class="text-xs text-muted">Akses dashboard masyarakat, admin, atau petugas</p>
            <p class="text-xs text-muted">Hak akses otomatis mengikuti peran yang terdaftar pada akun Anda.</p>
        </div>

        <form action="<?= base_url('/login') ?>" method="POST">
            <?= Security::csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Alamat Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk
            </button>
        </form>

        <div class="mt-3 text-center text-xs text-muted" style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
            Belum memiliki akun? <a href="<?= base_url('/register') ?>" class="font-semibold">Daftar Akun Baru</a>
        </div>

        <div class="mt-3 card" style="background: var(--bg-main); padding: 0.75rem; font-size: 0.75rem;">
            <p class="font-semibold mb-1"><i class="fa-solid fa-circle-info"></i> Akun Uji Coba (Seed):</p>
            <ul style="padding-left: 1rem; color: var(--text-muted);">
                <li><strong>Admin:</strong> admin@laporfasilitas.id / password123</li>
                <li><strong>Petugas:</strong> petugas1@laporfasilitas.id / password123</li>
                <li><strong>Warga:</strong> warga@laporfasilitas.id / password123</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
