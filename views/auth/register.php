<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex justify-center" style="min-height: 70vh; align-items: center;">
    <div class="card" style="width: 100%; max-width: 480px;">
        <div class="card-header text-center" style="display: block;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem;">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2 class="text-xl font-bold">Daftar Akun Masyarakat</h2>
            <p class="text-xs text-muted">Mulai laporkan fasilitas umum yang membutuhkan perbaikan</p>
        </div>

        <form action="<?= base_url('/register') ?>" method="POST">
            <?= Security::csrfField() ?>


            <div class="form-group">
                <label class="form-label" for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: Siti Rahmawati" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Alamat Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Nomor WhatsApp / HP</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="081234567890">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Ulangi Kata Sandi</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Ulangi sandi" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                <i class="fa-solid fa-user-check"></i> Buat Akun Sekarang
            </button>
        </form>

        <div class="mt-3 text-center text-xs text-muted" style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
            Sudah punya akun? <a href="<?= base_url('/login') ?>" class="font-semibold">Masuk ke Sini</a>
        </div>
    </div>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
