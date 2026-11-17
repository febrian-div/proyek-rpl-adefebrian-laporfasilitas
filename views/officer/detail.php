<?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

<div class="mb-3">
    <a href="<?= base_url('/petugas/tugas') ?>" class="text-primary text-sm font-semibold hover:underline">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Tugas
    </a>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Left: Detail Kerusakan & Map -->
    <div class="card">
        <div class="card-header flex justify-between items-center border-b pb-3 mb-3" style="flex-wrap: wrap; gap: 1rem;">
            <h2 class="text-xl font-bold">Laporan #LPF-<?= str_pad((string)$report['id'], 5, '0', STR_PAD_LEFT) ?></h2>
            <span class="badge badge-<?= $assignment['status'] === 'Selesai' ? 'done' : ($assignment['status'] === 'Sedang Dikerjakan' ? 'process' : 'assigned') ?>">
                <?= htmlspecialchars($assignment['status']) ?>
            </span>
        </div>
        
        <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars($report['title']) ?></h3>
        
        <?php if (!empty($report['photo'])): ?>
            <img src="<?= htmlspecialchars($report['photo']) ?>" alt="Foto Fasilitas" style="width: 100%; max-height: 400px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 1rem; border: 1px solid var(--border-color);">
        <?php endif; ?>

        <div class="grid grid-2 gap-4 mb-4" style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-sm);">
            <div>
                <span class="text-xs text-muted font-semibold uppercase">Kategori Fasilitas</span>
                <p class="font-bold"><i class="fa-solid fa-layer-group text-primary"></i> <?= htmlspecialchars($report['facility_category'] ?? '-') ?></p>
            </div>
            <div>
                <span class="text-xs text-muted font-semibold uppercase">Jenis Kerusakan</span>
                <p class="font-bold"><?= htmlspecialchars($report['damage_type'] ?? '-') ?></p>
            </div>
            <div>
                <span class="text-xs text-muted font-semibold uppercase">Prioritas Penanganan</span>
                <p>
                    <?php if (!empty($report['priority'])): ?>
                        <span class="badge badge-severity-<?= strtolower($report['priority']) ?> text-xs"><?= htmlspecialchars($report['priority']) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="mb-4">
            <h4 class="font-bold mb-1">Deskripsi Laporan</h4>
            <p class="text-sm" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
        </div>

        <div class="mb-4">
            <h4 class="font-bold mb-2"><i class="fa-solid fa-map-location-dot"></i> Peta & Lokasi</h4>
            <p class="text-sm mb-2"><i class="fa-solid fa-location-dot text-primary"></i> <?= htmlspecialchars($report['address']) ?></p>
            <div id="map-viewer" class="map-viewer-container" style="height: 300px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); z-index: 1;"></div>
        </div>
    </div>

    <!-- Right: Actions Panel -->
    <div>
        <!-- Aksi Petugas -->
        <div class="card mb-4" style="border-top: 4px solid var(--primary-color);">
            <h3 class="font-bold text-lg mb-3 border-b pb-2">Tindakan Petugas</h3>
            
            <?php if ($assignment['status'] === 'Ditugaskan'): ?>
                <div class="alert alert-info mb-3 text-sm">
                    <strong>Tugas Baru:</strong> Harap segera menuju lokasi dan mulai pekerjaan perbaikan.
                </div>
                <form action="<?= base_url('/petugas/tugas/' . $report['id'] . '/proses') ?>" method="POST">
                    <?= \App\Helpers\Security::csrfField() ?>
                    <button type="submit" class="btn btn-primary w-full" style="padding: 1rem; font-size: 1.1rem;">
                        <i class="fa-solid fa-person-digging"></i> Mulai Dikerjakan
                    </button>
                </form>
            
            <?php elseif ($assignment['status'] === 'Sedang Dikerjakan'): ?>
                <div class="alert alert-warning mb-3 text-sm">
                    <strong>Pekerjaan Berlangsung:</strong> Setelah perbaikan selesai, unggah foto bukti hasil pekerjaan.
                </div>
                
                <form action="<?= base_url('/petugas/tugas/' . $report['id'] . '/selesai') ?>" method="POST" enctype="multipart/form-data">
                    <?= \App\Helpers\Security::csrfField() ?>
                    
                    <div class="form-group">
                        <label class="form-label">Foto Bukti Perbaikan <span class="text-danger">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="photo" id="photo" class="form-control" accept="image/*" required onchange="previewImage(this, 'proofPreview')">
                        </div>
                        <img id="proofPreview" src="#" alt="Preview" style="display:none; width:100%; margin-top:0.5rem; border-radius: var(--radius-sm);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Catatan Teknisi (Opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Material aspal telah ditambal dengan baik..."></textarea>
                    </div>

                    <button type="submit" class="btn w-full" style="background: #10b981; color: white; padding: 1rem; font-size: 1.1rem; border: none; cursor: pointer; border-radius: var(--radius-sm); font-weight: bold;">
                        <i class="fa-solid fa-check-double"></i> Selesaikan Pekerjaan
                    </button>
                </form>
            
            <?php elseif ($assignment['status'] === 'Selesai'): ?>
                <div class="alert alert-success text-sm">
                    <i class="fa-solid fa-check-circle"></i> Pekerjaan ini telah selesai dilaksanakan.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Info Penugasan -->
        <div class="card" style="background: var(--bg-secondary);">
            <h4 class="font-bold text-sm mb-2"><i class="fa-solid fa-circle-info"></i> Info Penugasan</h4>
            <div class="text-xs text-muted space-y-1">
                <p><strong>Diberikan pada:</strong> <?= date('d M Y, H:i', strtotime($assignment['assigned_at'])) ?> WIB</p>
                <p><strong>Admin Verifikator:</strong> Sistem LaporFasilitas</p>
                <p><strong>Batas Waktu:</strong> Sesuai prioritas</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.LaporMap) {
        window.LaporMap.initViewer(
            'map-viewer', 
            <?= (float)$report['latitude'] ?>, 
            <?= (float)$report['longitude'] ?>, 
            '<?= addslashes(htmlspecialchars($report['title'])) ?>'
        );
    }
});

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
