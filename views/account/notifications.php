<?php
require VIEWS_PATH . '/layouts/header.php';
$csrfToken = \App\Helpers\Security::generateCsrf();
$isConnected = !empty($user['telegram_chat_id']);
?>

<div class="row" style="max-width: 900px; margin: 0 auto;">
    <div class="col-12 mb-3">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="h3 font-bold m-0" style="color: var(--primary);">
                    <i class="fa-solid fa-bell-concierge"></i> Pengaturan Notifikasi
                </h1>
                <p class="text-muted text-sm m-0">Kelola preferensi kanal notifikasi website dan integrasi Telegram.</p>
            </div>
            <div>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= base_url('/admin') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
                <?php elseif ($user['role'] === 'petugas'): ?>
                    <a href="<?= base_url('/petugas') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
                <?php else: ?>
                    <a href="<?= base_url('/laporan') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali ke Laporan</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kanal 1: Website Notifications (Always Active) -->
    <div class="col-12 mb-4">
        <div class="card p-4" style="background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div style="width: 44px; height: 44px; background: rgba(30, 64, 175, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                        <i class="fa-solid fa-bell fa-lg"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-base m-0">Notifikasi Dalam Website</h4>
                        <p class="text-xs text-muted m-0">Pemberitahuan resmi pada ikon lonceng di navigasi atas.</p>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background: #10B981; color: white; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> Selalu Aktif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kanal 2: Telegram Bot Integration -->
    <div class="col-12 mb-4">
        <div class="card p-4" style="background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-center gap-3">
                    <div style="width: 44px; height: 44px; background: rgba(34, 158, 217, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #229ED9;">
                        <i class="fa-brands fa-telegram fa-2x"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-base m-0">Telegram Pribadi</h4>
                        <p class="text-xs text-muted m-0">Dapatkan notifikasi perubahan status laporan langsung ke akun Telegram pribadi Anda.</p>
                    </div>
                </div>
                <div>
                    <?php if ($isConnected): ?>
                        <span class="badge" style="background: #10B981; color: white; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                            <i class="fa-solid fa-link"></i> Terhubung
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: #6B7280; color: white; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                            <i class="fa-solid fa-link-slash"></i> Belum Terhubung
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1rem 0;">

            <?php if ($isConnected): ?>
                <!-- Connected State -->
                <div class="alert alert-success mb-3" style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fa-solid fa-circle-check fa-lg"></i>
                    <div>
                        Akun LaporFasilitas Anda telah terhubung dengan Telegram. Notifikasi otomatis akan dikirim ke akun Telegram Anda setiap kali ada pembaruan laporan.
                    </div>
                </div>

                <form method="POST" action="<?= base_url('/pengaturan/telegram/disconnect') ?>" onsubmit="return confirm('Apakah Anda yakin ingin memutuskan sambungan Telegram? Notifikasi website Anda tetap akan aktif.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-link-slash"></i> Putuskan Sambungan Telegram
                    </button>
                </form>

            <?php else: ?>
                <!-- Not Connected State -->
                <?php if (!empty($pairingInfo)): ?>
                    <!-- Active Pairing Token Generated -->
                    <div style="background: rgba(34, 158, 217, 0.05); border: 1px solid rgba(34, 158, 217, 0.2); border-radius: 8px; padding: 1.25rem;" class="mb-3">
                        <h5 class="font-bold text-sm mb-2" style="color: #229ED9;">
                            <i class="fa-solid fa-qrcode"></i> Langkah-langkah Menghubungkan Telegram:
                        </h5>
                        
                        <ol class="text-xs text-muted mb-3" style="padding-left: 1.25rem; line-height: 1.8;">
                            <li>Klik tombol <strong>Buka Bot Telegram</strong> di bawah ini (atau cari bot <code>@<?= htmlspecialchars($botUsername) ?></code> di aplikasi Telegram).</li>
                            <li>Tekan tombol <strong>Start</strong> pada percakapan bot.</li>
                            <li>Akun Anda akan terverifikasi dan terhubung secara otomatis seketika.</li>
                        </ol>

                        <div class="flex items-center gap-3 mb-3">
                            <a href="<?= htmlspecialchars($pairingInfo['deep_link']) ?>" target="_blank" class="btn btn-primary" style="background: #229ED9; border-color: #229ED9;">
                                <i class="fa-brands fa-telegram"></i> Buka Bot Telegram
                            </a>
                        </div>

                        <div style="background: var(--bg-card); padding: 0.75rem; border-radius: 6px; border: 1px dashed var(--border-color);" class="text-xs">
                            <span class="text-muted">Kode Pairing Manual:</span>
                            <strong style="letter-spacing: 1px; font-family: monospace; font-size: 0.9rem;"><?= htmlspecialchars($pairingInfo['raw_token']) ?></strong>
                            <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                Berlaku selama 15 menit (hingga <?= date('H:i', strtotime($pairingInfo['expires_at'])) ?> WIB).
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-muted mb-3">
                        Telegram bersifat opsional. Jika Anda tidak menghubungkan Telegram, Anda tetap dapat memantau status laporan melalui pusat notifikasi website kapan saja.
                    </p>

                    <form method="POST" action="<?= base_url('/pengaturan/telegram/connect') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-primary" <?= $rateLimitExceeded ? 'disabled' : '' ?>>
                            <i class="fa-brands fa-telegram"></i> Hubungkan Telegram
                        </button>
                        <?php if ($rateLimitExceeded): ?>
                            <small class="text-danger text-xs ml-2">Batas pembuatan token tercapai (maks 5x/jam). Silakan tunggu sebentar.</small>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require VIEWS_PATH . '/layouts/footer.php'; ?>
