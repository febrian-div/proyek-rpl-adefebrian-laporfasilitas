<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-2xl font-bold">Profil Administrator</h1>
        <p class="text-sm text-muted">Perbarui data nama, email, dan kata sandi akun administrator Anda</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('/admin/pengguna') ?>" class="btn btn-secondary"><i class="fa-solid fa-users"></i> Manajemen Pengguna</a>
        <a href="<?= base_url('/admin') ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
    </div>
</div>

<div class="card" style="max-width: 650px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="font-semibold"><i class="fa-solid fa-user-gear"></i> Informasi Akun</h3>
    </div>
    <form action="<?= base_url('/admin/profil') ?>" method="POST">
        <?= Security::csrfField() ?>

        <div class="form-group mb-3">
            <label class="form-label font-semibold" for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required minlength="3">
        </div>

        <div class="form-group mb-3">
            <label class="form-label font-semibold" for="email">Alamat Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            <small class="text-muted text-xs">Pastikan email aktif dan belum digunakan oleh akun lain.</small>
        </div>

        <div class="form-group mb-3">
            <label class="form-label font-semibold">Peran (Role)</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($user['role'] ?? 'Admin')) ?>" disabled style="background-color: var(--bg-secondary);">
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

        <h4 class="font-semibold text-base mb-2"><i class="fa-solid fa-key"></i> Ganti Kata Sandi (Opsional)</h4>
        <p class="text-xs text-muted mb-3">Kosongkan jika Anda tidak ingin mengubah kata sandi.</p>

        <div class="form-group mb-3">
            <label class="form-label font-semibold" for="current_password">Kata Sandi Saat Ini</label>
            <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Wajib diisi jika mengganti kata sandi">
        </div>

        <div class="grid grid-2 gap-3 mb-4">
            <div class="form-group">
                <label class="form-label font-semibold" for="new_password">Kata Sandi Baru</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 karakter">
            </div>
            <div class="form-group">
                <label class="form-label font-semibold" for="new_password_confirm">Konfirmasi Kata Sandi Baru</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" placeholder="Ulangi kata sandi baru">
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= base_url('/admin/pengguna') ?>" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>

