<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';
?>

<!-- Header Breadcrumb -->
<div class="mb-3">
    <a href="<?= base_url('/laporan') ?>" class="btn btn-secondary btn-sm mb-2">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat
    </a>
    <h1 class="text-2xl font-bold mt-1" style="color: var(--secondary);">Formulir Laporan Kerusakan Fasilitas</h1>
    <p class="text-sm text-muted">
        Laporkan kerusakan fasilitas umum di sekitar Anda agar segera ditindaklanjuti dan diperbaiki oleh pemerintah daerah.
    </p>
</div>

<div class="card" style="max-width: 820px; margin: 0 auto; box-shadow: var(--shadow-md);">
    <form id="form-create-report" action="<?= base_url('/laporan/simpan') ?>" method="POST" enctype="multipart/form-data">
        <?= Security::csrfField() ?>

        <!-- 1. Judul Laporan -->
        <div class="form-group">
            <label class="form-label" for="title">
                Judul Laporan <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" id="title" name="title" class="form-control" placeholder="Contoh: Lampu Penerangan Padam di Jl. Sudirman Depan Halte" minlength="5" maxlength="200" required autofocus>
            <small class="text-xs text-muted">Gunakan judul yang ringkas dan menjelaskan masalah secara spesifik.</small>
        </div>

        <!-- 2. Kategori Fasilitas -->
        <div class="form-group">
            <label class="form-label" for="facility_category">
                Kategori Fasilitas <span style="color: #ef4444;">*</span>
            </label>
            <select id="facility_category" name="facility_category" class="form-control" required>
                <option value="">-- Pilih Kategori Fasilitas --</option>
                <option value="Jalan">Jalan</option>
                <option value="Lampu Jalan">Lampu Jalan</option>
                <option value="Trotoar">Trotoar</option>
                <option value="Taman">Taman</option>
                <option value="Tempat Sampah">Tempat Sampah</option>
                <option value="Saluran Air">Saluran Air</option>
                <option value="Fasilitas Umum Lainnya">Fasilitas Umum Lainnya</option>
            </select>
        </div>

        <!-- 3. Deskripsi Kerusakan -->
        <div class="form-group">
            <label class="form-label" for="description">
                Deskripsi Kerusakan <span style="color: #ef4444;">*</span>
            </label>
            <textarea id="description" name="description" class="form-control" rows="4" placeholder="Jelaskan kondisi kerusakan, potensi bahaya, atau perkiraan durasi terjadinya kerusakan..." minlength="10" required></textarea>
            <small class="text-xs text-muted">Minimal 10 karakter.</small>
        </div>

        <!-- 4. Upload Foto Kerusakan -->
        <div class="form-group">
            <label class="form-label" for="photo">
                Foto Bukti Kerusakan <span style="color: #ef4444;">*</span>
            </label>
            <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-sm); padding: 1.5rem; text-align: center; background: var(--bg-main); transition: border-color 0.2s ease;" id="dropzone-box">
                <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp,image/jpg" style="display: none;" required>
                
                <div id="upload-placeholder">
                    <div style="font-size: 2.25rem; color: var(--primary); margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <p class="font-semibold text-sm mb-1">Klik untuk Memilih Foto atau Ambil Gambar</p>
                    <p class="text-xs text-muted mb-3">Mendukung format: JPG, PNG, WEBP (Maksimal 5 MB)</p>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('photo').click();">
                        <i class="fa-solid fa-folder-open"></i> Pilih Berkas Foto
                    </button>
                </div>

                <div id="photo-preview-container" style="display: none; position: relative; margin-top: 0.5rem;">
                    <img id="photo-preview-img" src="" alt="Preview Foto" style="max-height: 280px; max-width: 100%; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; margin: 0 auto;">
                    <div class="mt-2 flex gap-2 justify-center">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('photo').click();">
                            <i class="fa-solid fa-arrows-rotate"></i> Ganti Foto
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="btn-remove-photo">
                            <i class="fa-solid fa-trash"></i> Hapus Foto
                        </button>
                    </div>
                </div>
            </div>
            <div id="photo-error" class="text-xs mt-1" style="color: #ef4444; display: none;"></div>
        </div>

        <!-- 5. Lokasi & Peta Geolocation -->
        <div class="form-group">
            <div class="flex justify-between items-center mb-1" style="flex-wrap: wrap; gap: 0.5rem;">
                <label class="form-label" style="margin-bottom: 0;">
                    Titik Lokasi Fasilitas <span style="color: #ef4444;">*</span>
                </label>
                <button type="button" id="btn-get-location" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; padding: 0.3rem 0.75rem;">
                    <i class="fa-solid fa-location-crosshairs"></i> Gunakan Lokasi GPS Saya
                </button>
            </div>
            
            <div id="geo-status" class="text-xs mb-1"></div>
            
            <div id="map-picker" class="map-picker-container"></div>
            <small class="text-xs text-muted">
                <i class="fa-solid fa-circle-info"></i> Klik pada peta atau geser pin merah untuk menandai lokasi fasilitas yang rusak secara presisi.
            </small>
        </div>

        <!-- Koordinat (Latitude & Longitude) -->
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label" for="latitude">Latitude <span style="color: #ef4444;">*</span></label>
                <input type="text" id="latitude" name="latitude" class="form-control" placeholder="-6.2088..." readonly required style="background: var(--bg-main); font-family: monospace;">
            </div>
            <div class="form-group">
                <label class="form-label" for="longitude">Longitude <span style="color: #ef4444;">*</span></label>
                <input type="text" id="longitude" name="longitude" class="form-control" placeholder="106.8456..." readonly required style="background: var(--bg-main); font-family: monospace;">
            </div>
        </div>

        <!-- 6. Alamat / Patokan Lokasi Teks -->
        <div class="form-group">
            <label class="form-label" for="address">
                Alamat / Patokan Lokasi (Teks) <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" id="address" name="address" class="form-control" placeholder="Contoh: Jl. Sudirman No. 45, depan Ruko Blok A dekat halte" minlength="5" required>
            <small class="text-xs text-muted">Tuliskan nama jalan, patokan bangunan, atau nomor ruko di sekitar titik lokasi.</small>
        </div>

        <!-- Submit Button -->
        <div class="mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
            <button type="submit" id="btn-submit-report" class="btn btn-gradient btn-lg" style="width: 100%;">
                <i class="fa-solid fa-paper-plane"></i> Kirim Laporan Sekarang
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Inisialisasi Peta Leaflet & Geolocation
    if (window.LaporMap) {
        window.LaporMap.initPicker('map-picker', 'latitude', 'longitude', 'address');
    }

    // 2. Client-side Image Upload & Validation
    const photoInput = document.getElementById('photo');
    const placeholder = document.getElementById('upload-placeholder');
    const previewContainer = document.getElementById('photo-preview-container');
    const previewImg = document.getElementById('photo-preview-img');
    const errorMsg = document.getElementById('photo-error');
    const btnRemove = document.getElementById('btn-remove-photo');
    const maxFileSize = 5 * 1024 * 1024; // 5 MB

    photoInput.addEventListener('change', (e) => {
        errorMsg.style.display = 'none';
        errorMsg.innerText = '';

        const file = e.target.files[0];
        if (!file) return;

        // Validasi tipe file
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            errorMsg.innerText = 'Format berkas tidak didukung. Harap pilih gambar JPG, PNG, atau WEBP.';
            errorMsg.style.display = 'block';
            photoInput.value = '';
            return;
        }

        // Validasi ukuran file
        if (file.size > maxFileSize) {
            errorMsg.innerText = 'Ukuran gambar melebihi batas maksimal 5 MB (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB).';
            errorMsg.style.display = 'block';
            photoInput.value = '';
            return;
        }

        // Tampilkan Preview
        const reader = new FileReader();
        reader.onload = (event) => {
            previewImg.src = event.target.result;
            placeholder.style.display = 'none';
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    if (btnRemove) {
        btnRemove.addEventListener('click', () => {
            photoInput.value = '';
            previewImg.src = '';
            previewContainer.style.display = 'none';
            placeholder.style.display = 'block';
            errorMsg.style.display = 'none';
        });
    }

    // 3. Form Submit Validation
    const form = document.getElementById('form-create-report');
    const btnSubmit = document.getElementById('btn-submit-report');

    form.addEventListener('submit', (e) => {
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        const photo = photoInput.files[0];

        if (!lat || !lng) {
            e.preventDefault();
            alert('Harap tentukan titik lokasi fasilitas pada peta terlebih dahulu.');
            return;
        }

        if (!photo) {
            e.preventDefault();
            errorMsg.innerText = 'Foto bukti kerusakan wajib diunggah.';
            errorMsg.style.display = 'block';
            return;
        }

        // Set Loading state on button
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim Laporan...';
    });
});
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
