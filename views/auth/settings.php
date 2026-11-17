<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex justify-center" style="min-height: 70vh; align-items: center;">
    <div class="card" style="width: 100%; max-width: 560px;">
        <div class="card-header text-center" style="display: block;">
            <div class="brand-icon" style="margin: 0 auto 0.75rem;">
                <i class="fa-solid fa-paper-plane" style="color: var(--primary);"></i>
            </div>
            <h2 class="text-xl font-bold">Pengaturan Notifikasi</h2>
            <p class="text-xs text-muted">Atur pemberitahuan status laporan melalui website dan Telegram</p>
        </div>

        <div class="card-body" style="padding: 1.5rem;">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible" style="margin-bottom: 1rem;">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i>
                    <div><?= htmlspecialchars($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <!-- Telegram Connection Status -->
            <div class="form-group mb-4">
                <label class="form-label">Status Telegram</label>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <?php if ($telegramConnected): ?>
                        <span class="badge badge-verified">
                            <i class="fa-solid fa-check-circle"></i> Terhubung
                        </span>
                    <?php else: ?>
                        <span class="badge badge-rejected" style="background: var(--status-pending-bg); color: #92400e; border-color: var(--status-pending);">
                            <i class="fa-solid fa-circle-notch"></i> Belum Terhubung
                        </span>
                    <?php endif; ?>
                    <span class="text-xs text-muted"><?= $telegramConnected ? 'Akun Telegram Anda sudah terpasang.' : 'Hubungkan akun Telegram untuk menerima notifikasi pribadi.' ?></span>
                </div>
            </div>

            <?php if (!$telegramEnabled): ?>
                <div class="alert alert-warning alert-dismissible" style="margin-bottom: 1rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>Integrasi Telegram belum diaktifkan di server (konfigurasi API belum lengkap). Notifikasi website tetap berfungsi normal.</div>
                </div>
            <?php endif; ?>

            <?php if ($telegramEnabled && !$telegramConnected): ?>
                <form id="pairingForm" method="POST" onsubmit="return generatePairing(event)">
                    <?= Security::csrfField() ?>
                    <div class="form-group mb-3">
                        <label class="form-label">Langkah 1: Klik tombol di bawah untuk membuat tautan pairing</label>
                        <button type="submit" id="pairingBtn" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="fa-solid fa-link"></i> Buat Tautan Pairing Telegram
                        </button>
                        <p class="text-xs text-muted mt-2">Tautan akan berlaku selama 15 menit dan hanya dapat digunakan sekali.</p>
                    </div>
                    <div id="pairingResult" style="margin-top: 1rem; display: none;"></div>
                </form>

                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <p class="text-xs text-muted">Atau buka bot Telegram dan ketik:</p>
                    <div class="font-mono" style="background: var(--bg-main); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px dashed var(--border-color); word-break: break-all;">
                        /start [kode_pairing]
                    </div>
                </div>
            <?php elseif ($telegramConnected): ?>
                <form method="POST" onsubmit="return disconnectTelegram(event)">
                    <?= Security::csrfField() ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-unlink"></i> Putuskan Koneksi Telegram
                    </button>
                    <p class="text-xs text-muted mt-2">Setelah diputuskan, Anda tidak akan menerima notifikasi pribadi via Telegram.</p>
                </form>
            <?php endif; ?>
        </div>

        <div class="card-footer text-center" style="padding: 1rem;">
            <a href="<?= base_url('/') ?>" class="text-sm text-muted">&larr; Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
async function generatePairing(e) {
    e.preventDefault();
    const btn = document.getElementById('pairingBtn');
    const result = document.getElementById('pairingResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';

    try {
        const res = await fetch('<?= base_url('/telegram/pairing') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        });
        const data = await res.json();

        if (data.success) {
            result.style.display = 'block';
            result.innerHTML = `
                <div class="alert alert-success alert-dismissible">
                    <i class="fa-solid fa-circle-check"></i>
                    <div><strong>Tautan pairing berhasil dibuat!</strong></div>
                    <p class="text-xs text-muted mt-1">Buka tautan berikut di aplikasi Telegram Anda:</p>
                    <div class="font-mono" style="background: var(--bg-main); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--primary); word-break: break-all; margin-top: 0.5rem;">
                        ${data.deep_link}
                    </div>
                    <p class="text-xs text-muted mt-1">Berlaku hingga: ${data.expires_at}</p>
                </div>
            `;
            btn.innerHTML = '<i class="fa-solid fa-link"></i> Buat Tautan Baru';
        } else {
            result.style.display = 'block';
            result.innerHTML = `
                <div class="alert alert-error alert-dismissible">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>${data.message}</div>
                </div>
            `;
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-link"></i> Buat Tautan Pairing Telegram';
        }
    } catch (err) {
        result.style.display = 'block';
        result.innerHTML = `<div class="alert alert-error alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i><div>Terjadi kesalahan jaringan.</div></div>`;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-link"></i> Buat Tautan Pairing Telegram';
    }
}

async function disconnectTelegram(e) {
    e.preventDefault();
    if (!confirm('Yakin ingin memutuskan koneksi Telegram?')) return;

    const btn = document.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';

    try {
        const res = await fetch('<?= base_url('/telegram/disconnect') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        });
        const data = await res.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal memutuskan koneksi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-unlink"></i> Putuskan Koneksi Telegram';
        }
    } catch (err) {
        alert('Terjadi kesalahan jaringan.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-unlink"></i> Putuskan Koneksi Telegram';
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
