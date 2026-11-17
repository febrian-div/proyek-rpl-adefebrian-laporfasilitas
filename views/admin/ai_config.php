<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';

$activeConfig = $activeConfig ?? null;
$activeProvider = $activeProvider ?? 'gemini';
$providers = $providers ?? [];
?>

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-2xl font-bold"><i class="fa-solid fa-robot"></i> Konfigurasi AI Assistant</h1>
        <p class="text-sm text-muted">Kelola provider AI, model, API Key terenkripsi, dan parameter kecerdasan Chatbot LaporFasilitas</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('/admin') ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<!-- Banner Status Provider Aktif Saat Ini -->
<div class="card mb-4" style="border-left: 5px solid #10b981; background: linear-gradient(to right, #f0fdf4, #ffffff);">
    <div class="flex justify-between items-center flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #10b981; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div>
                <span class="text-xs font-semibold text-muted text-uppercase">PROVIDER AKTIF CHATBOT</span>
                <div class="flex items-center gap-2 mt-1">
                    <h2 class="text-xl font-bold m-0" style="color: #065f46;">
                        <?= htmlspecialchars($providers[$activeProvider]['display_name'] ?? ucfirst($activeProvider)) ?>
                    </h2>
                    <span class="badge" style="background: #10b981; color: white; font-size: 0.75rem;">
                        <i class="fa-solid fa-circle-check"></i> Aktif Beroperasi
                    </span>
                </div>
                <div class="text-xs text-muted mt-1 flex flex-wrap gap-3">
                    <span><i class="fa-solid fa-microchip"></i> Model: <strong><?= htmlspecialchars($activeConfig['model'] ?? '-') ?></strong></span>
                    <span><i class="fa-solid fa-key"></i> Status API Key: 
                        <?php if (!empty($activeConfig['has_key'])): ?>
                            <span class="text-success font-semibold"><i class="fa-solid fa-check"></i> Tersimpan (<?= htmlspecialchars($activeConfig['masked_key'] ?? '') ?>)</span>
                        <?php else: ?>
                            <span class="text-danger font-semibold"><i class="fa-solid fa-triangle-exclamation"></i> Belum Dikonfigurasi</span>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($activeConfig['updated_at'])): ?>
                        <span><i class="fa-regular fa-clock"></i> Diperbarui: <?= date('d M Y H:i', strtotime($activeConfig['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <span class="badge badge-verified" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                <i class="fa-solid fa-shield-halved"></i> Konteks & Aturan Database Terproteksi
            </span>
        </div>
    </div>
</div>

<!-- Tabs Pilihan Provider -->
<div class="flex gap-2 mb-4 border-b pb-2">
    <?php foreach ($providers as $pName => $pData): 
        $isActiveTab = ($pName === $activeProvider);
    ?>
        <button type="button" 
            class="btn <?= $isActiveTab ? 'btn-primary' : 'btn-secondary' ?> provider-tab-btn" 
            id="tab-btn-<?= $pName ?>"
            onclick="switchProviderTab('<?= $pName ?>')">
            <?php if ($pName === 'nvidia'): ?>
                <i class="fa-solid fa-network-wired"></i>
            <?php else: ?>
                <i class="fa-brands fa-google"></i>
            <?php endif; ?>
            <?= htmlspecialchars($pData['display_name']) ?>
            <?php if ($pData['is_active']): ?>
                <span class="badge" style="background: #059669; color: white; margin-left: 0.25rem; font-size: 0.65rem;">Aktif</span>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Container Form Provider -->
<?php foreach ($providers as $pName => $pData): 
    $isActiveTab = ($pName === $activeProvider);
?>
<div class="provider-config-pane" id="pane-<?= $pName ?>" style="display: <?= $isActiveTab ? 'block' : 'none' ?>;">
    
    <div class="card mb-4" style="border-top: 4px solid <?= $pName === 'nvidia' ? '#76b900' : '#4285f4' ?>;">
        <div class="card-header flex justify-between items-center flex-wrap gap-2">
            <div>
                <h3 class="font-bold text-lg m-0">
                    <?php if ($pName === 'nvidia'): ?>
                        <i class="fa-solid fa-microchip" style="color: #76b900;"></i>
                    <?php else: ?>
                        <i class="fa-brands fa-google" style="color: #4285f4;"></i>
                    <?php endif; ?>
                    Pengaturan Provider <?= htmlspecialchars($pData['display_name']) ?>
                </h3>
                <p class="text-xs text-muted m-0 mt-1">Konfigurasi kredensial, model, dan parameter untuk provider ini</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($pData['is_active']): ?>
                    <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 0.8rem; padding: 0.4rem 0.75rem;">
                        <i class="fa-solid fa-circle-check"></i> Mesin Aktif Saat Ini
                    </span>
                <?php else: ?>
                    <form action="<?= base_url('/admin/konfigurasi-ai/aktifkan') ?>" method="POST" style="display: inline;">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="provider" value="<?= $pName ?>">
                        <button type="submit" class="btn btn-sm" style="background: #059669; color: white;" title="Gunakan provider ini untuk Chatbot AI">
                            <i class="fa-solid fa-power-off"></i> Jadikan Provider Aktif
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <form id="form-<?= $pName ?>" action="<?= base_url('/admin/konfigurasi-ai/simpan') ?>" method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="provider" value="<?= $pName ?>">

            <!-- Alert Hasil Uji Koneksi Real-time -->
            <div id="test-result-<?= $pName ?>" style="display: none; margin-bottom: 1.5rem;" class="p-3 rounded"></div>

            <div class="grid grid-2 gap-4">
                <!-- Kolom Kiri: Model & Kredensial -->
                <div>
                    <h4 class="font-semibold text-sm mb-3 pb-1 border-b"><i class="fa-solid fa-sliders"></i> Model & Kredensial API</h4>

                    <!-- Pilihan Model -->
                    <div class="form-group mb-3">
                        <label class="form-label font-semibold" for="model-select-<?= $pName ?>">
                            Pilihan Model AI
                        </label>
                        <div class="flex gap-2">
                            <select id="model-select-<?= $pName ?>" name="model" class="form-control" onchange="handleModelChange('<?= $pName ?>', this.value)">
                                <?php 
                                $isCustomSelected = true;
                                foreach ($pData['available_models'] as $mKey => $mLabel): 
                                    $selected = ($pData['current_model'] === $mKey);
                                    if ($selected) $isCustomSelected = false;
                                ?>
                                    <option value="<?= htmlspecialchars($mKey) ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mKey) ?> — <?= htmlspecialchars($mLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?= $isCustomSelected ? 'selected' : '' ?>>Input Model Kustom Manual...</option>
                            </select>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="fetchLiveModels('<?= $pName ?>')" title="Muat daftar model dari API">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                        <small class="text-xs text-muted">Model bawaan: <code><?= htmlspecialchars($pData['default_model']) ?></code></small>
                    </div>

                    <!-- Input Model Kustom (Jika dipilih) -->
                    <div class="form-group mb-3" id="custom-model-group-<?= $pName ?>" style="display: <?= $isCustomSelected ? 'block' : 'none' ?>;">
                        <label class="form-label font-semibold" for="custom-model-<?= $pName ?>">Nama Model Kustom</label>
                        <input type="text" id="custom-model-<?= $pName ?>" name="custom_model" class="form-control" 
                            placeholder="Contoh: <?= $pName === 'nvidia' ? 'meta/llama-3.3-70b-instruct' : 'gemini-1.5-pro' ?>" 
                            value="<?= $isCustomSelected ? htmlspecialchars($pData['current_model']) : '' ?>">
                        <small class="text-xs text-muted">Ketik nama model persis seperti yang tercantum di dokumentasi resmi.</small>
                    </div>

                    <!-- API Key -->
                    <div class="form-group mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <label class="form-label font-semibold m-0" for="api-key-<?= $pName ?>">
                                API Key
                            </label>
                            <?php if ($pData['has_key']): ?>
                                <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 0.7rem;">
                                    <i class="fa-solid fa-lock"></i> Tersimpan (<?= htmlspecialchars($pData['masked_key']) ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #fee2e2; color: #991b1b; font-size: 0.7rem;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Belum Ada Key
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <input type="password" id="api-key-<?= $pName ?>" name="api_key" class="form-control" 
                                placeholder="<?= $pData['has_key'] ? 'Biarkan kosong jika tidak ingin mengubah API Key tersimpan' : ($pName === 'nvidia' ? 'nvapi-...' : 'AIzaSy...') ?>" 
                                autocomplete="new-password">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="togglePasswordVisibility('api-key-<?= $pName ?>', this)" title="Tampilkan/Sembunyikan">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-xs text-muted">
                            <i class="fa-solid fa-shield-halved text-success"></i> 
                            Kunci dienkripsi dengan standar <strong>AES-256-CBC</strong> sebelum disimpan ke basis data.
                        </small>
                    </div>

                    <!-- Base URL / Endpoint (Khusus NVIDIA atau custom provider) -->
                    <?php if ($pName === 'nvidia'): ?>
                        <div class="form-group mb-3">
                            <label class="form-label font-semibold" for="base-url-<?= $pName ?>">Base URL / Endpoint API</label>
                            <input type="text" id="base-url-<?= $pName ?>" name="base_url" class="form-control" 
                                value="<?= htmlspecialchars($pData['base_url']) ?>" 
                                placeholder="https://integrate.api.nvidia.com/v1">
                            <small class="text-xs text-muted">Endpoint standar NVIDIA NIM: <code>https://integrate.api.nvidia.com/v1</code></small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kolom Kanan: Parameter Generasi AI -->
                <div>
                    <h4 class="font-semibold text-sm mb-3 pb-1 border-b"><i class="fa-solid fa-gauge-high"></i> Parameter Generasi AI</h4>

                    <!-- Temperature -->
                    <div class="form-group mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <label class="form-label font-semibold m-0" for="temp-<?= $pName ?>">
                                Temperature (Kreativitas)
                            </label>
                            <span class="badge" id="temp-val-<?= $pName ?>" style="background: var(--bg-secondary); color: var(--text-dark); font-weight: bold;">
                                <?= number_format($pData['temperature'], 2) ?>
                            </span>
                        </div>
                        <input type="range" id="temp-<?= $pName ?>" name="temperature" min="0.0" max="1.5" step="0.05" 
                            value="<?= $pData['temperature'] ?>" class="form-control" style="padding: 0;"
                            oninput="document.getElementById('temp-val-<?= $pName ?>').innerText = parseFloat(this.value).toFixed(2)">
                        <div class="flex justify-between text-muted text-xs mt-1">
                            <span>0.0 (Presisi & Faktual)</span>
                            <span>0.4 (Direkomendasikan)</span>
                            <span>1.0+ (Sangat Kreatif)</span>
                        </div>
                    </div>

                    <!-- Maximum Tokens -->
                    <div class="form-group mb-3">
                        <label class="form-label font-semibold" for="tokens-<?= $pName ?>">Maximum Output Tokens</label>
                        <input type="number" id="tokens-<?= $pName ?>" name="max_tokens" min="100" max="4096" step="50" 
                            class="form-control" value="<?= $pData['max_tokens'] ?>" required>
                        <small class="text-xs text-muted">Batas panjang jawaban bot. Nilai rekomendasi: <strong>600</strong> token.</small>
                    </div>

                    <!-- Opsi Langsung Aktifkan -->
                    <div class="p-3 rounded mb-3" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <label class="flex items-center gap-2 m-0 cursor-pointer">
                            <input type="checkbox" name="set_active" value="1" <?= $pData['is_active'] ? 'checked' : '' ?>>
                            <span class="font-semibold text-sm">Jadikan <?= htmlspecialchars($pData['display_name']) ?> sebagai provider aktif</span>
                        </label>
                        <p class="text-xs text-muted m-0 mt-1 pl-4">Chatbot akan langsung beralih memproses pesan menggunakan provider ini setelah disimpan.</p>
                    </div>

                    <!-- Kotak Informasi Proteksi Konteks -->
                    <div class="p-3 rounded text-xs" style="background: #eff6ff; border-left: 3px solid #3b82f6; color: #1e40af;">
                        <i class="fa-solid fa-circle-info"></i> <strong>Konteks Database Tetap Terjaga:</strong><br>
                        Pergantian provider tidak mempengaruhi system prompt, integrasi data laporan real-time, atau batasan privasi role masyarakat/petugas/admin.
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Bawah -->
            <div class="flex justify-between items-center mt-4 pt-3 border-t flex-wrap gap-2">
                <div>
                    <button type="button" class="btn btn-secondary" id="btn-test-<?= $pName ?>" onclick="runConnectionTest('<?= $pName ?>')">
                        <i class="fa-solid fa-plug-circle-check"></i> Uji Koneksi API
                    </button>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Konfigurasi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function switchProviderTab(provider) {
    // Hide all panes
    document.querySelectorAll('.provider-config-pane').forEach(el => el.style.display = 'none');
    // Remove active style from tabs
    document.querySelectorAll('.provider-tab-btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    });

    // Show target pane & set active tab
    const targetPane = document.getElementById('pane-' + provider);
    if (targetPane) targetPane.style.display = 'block';

    const targetTab = document.getElementById('tab-btn-' + provider);
    if (targetTab) {
        targetTab.classList.remove('btn-secondary');
        targetTab.classList.add('btn-primary');
    }
}

function handleModelChange(provider, value) {
    const customGroup = document.getElementById('custom-model-group- ' + provider) || document.getElementById('custom-model-group-' + provider);
    if (customGroup) {
        customGroup.style.display = (value === 'custom') ? 'block' : 'none';
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    }
}

function runConnectionTest(provider) {
    const btn = document.getElementById('btn-test-' + provider);
    const resultBox = document.getElementById('test-result-' + provider);
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menguji Koneksi...';

    resultBox.style.display = 'block';
    resultBox.style.background = '#f8fafc';
    resultBox.style.border = '1px solid #cbd5e1';
    resultBox.style.color = '#334155';
    resultBox.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Menghubungi API server ' + provider.toUpperCase() + '...';

    const form = document.getElementById('form-' + provider);
    const formData = new FormData(form);

    fetch('<?= base_url('/admin/konfigurasi-ai/uji-koneksi') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        if (data.success) {
            resultBox.style.background = '#d1fae5';
            resultBox.style.border = '1px solid #10b981';
            resultBox.style.color = '#065f46';
            resultBox.innerHTML = '<strong><i class="fa-solid fa-circle-check"></i> ' + escapeHtml(data.message) + '</strong>';
        } else {
            resultBox.style.background = '#fee2e2';
            resultBox.style.border = '1px solid #ef4444';
            resultBox.style.color = '#991b1b';
            resultBox.innerHTML = '<strong><i class="fa-solid fa-circle-xmark"></i> Uji Koneksi Gagal:</strong> ' + escapeHtml(data.message);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        resultBox.style.background = '#fee2e2';
        resultBox.style.border = '1px solid #ef4444';
        resultBox.style.color = '#991b1b';
        resultBox.innerHTML = '<strong><i class="fa-solid fa-triangle-exclamation"></i> Kesalahan Jaringan:</strong> Gagal mengirim permintaan ke server lokal.';
    });
}

function fetchLiveModels(provider) {
    const form = document.getElementById('form-' + provider);
    const formData = new FormData(form);

    const select = document.getElementById('model-select-' + provider);
    const currentVal = select.value;

    alert('Sedang memeriksa daftar model dari API server ' + provider.toUpperCase() + '...');

    fetch('<?= base_url('/admin/konfigurasi-ai/muat-model') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && Array.isArray(data.models) && data.models.length > 0) {
            select.innerHTML = '';
            data.models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                if (m === currentVal) opt.selected = true;
                select.appendChild(opt);
            });
            const customOpt = document.createElement('option');
            customOpt.value = 'custom';
            customOpt.textContent = 'Input Model Kustom Manual...';
            select.appendChild(customOpt);

            alert('Berhasil memuat ' + data.models.length + ' model dari ' + provider.toUpperCase() + '!');
        } else {
            alert('Tidak dapat memuat model secara otomatis. Gunakan daftar model default atau input manual.');
        }
    })
    .catch(err => {
        alert('Gagal memuat model dari API: ' + err.message);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
