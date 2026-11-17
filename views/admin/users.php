<?php
use App\Helpers\Security;
require_once VIEWS_PATH . '/layouts/header.php';

$currentAdminId = $currentAdminId ?? 0;
$filters = $filters ?? ['search' => '', 'role' => '', 'status' => ''];
$stats = $stats ?? [
    'total' => count($users ?? []),
    'masyarakat' => 0,
    'petugas' => 0,
    'admin' => 0,
    'active' => 0,
    'inactive' => 0
];
?>

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="text-2xl font-bold">Manajemen Pengguna</h1>
        <p class="text-sm text-muted">Kelola seluruh akun masyarakat, petugas, dan administrator LaporFasilitas</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= base_url('/admin/profil') ?>" class="btn btn-secondary">
            <i class="fa-solid fa-user-gear"></i> Profil Saya
        </a>
        <button type="button" class="btn btn-primary" onclick="toggleAddUserForm()">
            <i class="fa-solid fa-user-plus"></i> <span id="toggleAddText">Tambah Pengguna</span>
        </button>
        <a href="<?= base_url('/admin') ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Metrik Pengguna -->
<div class="grid grid-4 mb-4 gap-3">
    <div class="card" style="border-left: 4px solid var(--primary-color);">
        <span class="text-xs font-semibold text-muted">TOTAL PENGGUNA</span>
        <h2 class="text-2xl font-extrabold mt-1"><?= (int)$stats['total'] ?></h2>
    </div>
    <div class="card" style="border-left: 4px solid #3b82f6;">
        <span class="text-xs font-semibold text-muted">MASYARAKAT</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: #3b82f6;"><?= (int)$stats['masyarakat'] ?></h2>
    </div>
    <div class="card" style="border-left: 4px solid #8b5cf6;">
        <span class="text-xs font-semibold text-muted">PETUGAS</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: #8b5cf6;"><?= (int)$stats['petugas'] ?></h2>
    </div>
    <div class="card" style="border-left: 4px solid var(--secondary);">
        <span class="text-xs font-semibold text-muted">ADMINISTRATOR</span>
        <h2 class="text-2xl font-extrabold mt-1" style="color: var(--secondary);"><?= (int)$stats['admin'] ?></h2>
    </div>
</div>

<div class="grid grid-2 mb-4 gap-3">
    <div class="card" style="border-left: 4px solid #10b981; padding: 1rem;">
        <div class="flex justify-between items-center">
            <span class="text-xs font-semibold text-muted">AKUN AKTIF</span>
            <span class="badge" style="background-color: #d1fae5; color: #065f46; font-size: 0.75rem;">Aktif</span>
        </div>
        <h3 class="text-xl font-bold mt-1" style="color: #059669;"><?= (int)$stats['active'] ?> Akun</h3>
    </div>
    <div class="card" style="border-left: 4px solid #ef4444; padding: 1rem;">
        <div class="flex justify-between items-center">
            <span class="text-xs font-semibold text-muted">AKUN NONAKTIF</span>
            <span class="badge" style="background-color: #fee2e2; color: #991b1b; font-size: 0.75rem;">Nonaktif</span>
        </div>
        <h3 class="text-xl font-bold mt-1" style="color: #dc2626;"><?= (int)$stats['inactive'] ?> Akun</h3>
    </div>
</div>

<!-- Form Tambah Pengguna (Collapsible) -->
<div id="addUserCard" class="card mb-4" style="display: none; border-top: 3px solid var(--primary-color);">
    <div class="card-header flex justify-between items-center">
        <h3 class="font-semibold"><i class="fa-solid fa-user-plus"></i> Tambah Pengguna Baru</h3>
        <button type="button" class="text-muted border-none bg-transparent cursor-pointer" onclick="toggleAddUserForm()">
            <i class="fa-solid fa-xmark fa-lg"></i>
        </button>
    </div>
    <form action="<?= base_url('/admin/pengguna/tambah') ?>" method="POST">
        <?= Security::csrfField() ?>
        <div class="grid grid-2 gap-3">
            <div class="form-group">
                <label class="form-label font-semibold" for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Nama pengguna" required minlength="3">
            </div>
            <div class="form-group">
                <label class="form-label font-semibold" for="email">Alamat Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required>
            </div>
            <div class="form-group">
                <label class="form-label font-semibold" for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 karakter" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label font-semibold" for="role">Role</label>
                <select id="role" name="role" class="form-control" required onchange="handleRoleChange(this.value)">
                    <option value="petugas">Petugas</option>
                    <option value="admin">Admin</option>
                    <option value="masyarakat">Masyarakat</option>
                </select>
            </div>
            <div class="form-group" id="group-phone">
                <label class="form-label font-semibold" for="phone">Telepon (Wajib untuk Petugas)</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="081234567890">
            </div>
            <div class="form-group" id="group-service-area">
                <label class="form-label font-semibold" for="service_area">Area Layanan (Wajib untuk Petugas)</label>
                <input type="text" id="service_area" name="service_area" class="form-control" placeholder="Contoh: Jakarta Pusat">
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-3">
            <button type="button" class="btn btn-secondary" onclick="toggleAddUserForm()">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Simpan Akun Baru</button>
        </div>
    </form>
</div>

<!-- Filter dan Pencarian -->
<div class="card mb-4" style="padding: 1rem 1.25rem;">
    <form action="<?= base_url('/admin/pengguna') ?>" method="GET" class="flex flex-wrap items-center gap-3">
        <div class="form-group mb-0" style="flex: 1 1 240px;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama atau email..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="form-group mb-0" style="flex: 0 1 170px;">
            <select name="role" class="form-control">
                <option value="">Semua Role</option>
                <option value="masyarakat" <?= ($filters['role'] ?? '') === 'masyarakat' ? 'selected' : '' ?>>Masyarakat</option>
                <option value="petugas" <?= ($filters['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group mb-0" style="flex: 0 1 170px;">
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="aktif" <?= ($filters['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= ($filters['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <?php if (!empty($filters['search']) || !empty($filters['role']) || !empty($filters['status'])): ?>
                <a href="<?= base_url('/admin/pengguna') ?>" class="btn btn-secondary" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabel Daftar Pengguna -->
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="font-semibold"><i class="fa-solid fa-users"></i> Daftar Pengguna (<?= count($users ?? []) ?>)</h3>
        <?php if (!empty($filters['search']) || !empty($filters['role']) || !empty($filters['status'])): ?>
            <span class="text-xs text-muted">Menampilkan hasil pencarian / filter</span>
        <?php endif; ?>
    </div>

    <?php if (!empty($users)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted); background: var(--bg-secondary);">
                        <th style="padding: 0.75rem 1rem; width: 40px;">No</th>
                        <th style="padding: 0.75rem 1rem;">Nama & Email</th>
                        <th style="padding: 0.75rem 1rem;">Role</th>
                        <th style="padding: 0.75rem 1rem;">Status Akun</th>
                        <th style="padding: 0.75rem 1rem;">Terdaftar</th>
                        <th style="padding: 0.75rem 1rem; text-align: center; width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($users as $u): 
                        $isSelf = ((int)$u['id'] === (int)$currentAdminId);
                        $isActive = (int)($u['is_active'] ?? 1) === 1;
                    ?>
                        <tr style="border-bottom: 1px solid var(--border-color); <?= !$isActive ? 'background: #fffafa;' : '' ?>">
                            <td style="padding: 0.75rem 1rem; color: var(--text-muted); font-size: 0.8rem;"><?= $no++ ?></td>
                            <td style="padding: 0.75rem 1rem;">
                                <div class="font-semibold text-base flex items-center gap-2">
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-size: 0.65rem;">Akun Anda</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-muted"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge" style="background-color: #f3e8ff; color: #6b21a8; font-weight: 600;">Admin</span>
                                <?php elseif ($u['role'] === 'petugas'): ?>
                                    <span class="badge" style="background-color: #dbeafe; color: #1e40af; font-weight: 600;">Petugas</span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #f1f5f9; color: #475569; font-weight: 600;">Masyarakat</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem;">
                                <?php if ($isActive): ?>
                                    <span class="badge" style="background-color: #d1fae5; color: #065f46; font-size: 0.75rem;">
                                        <i class="fa-solid fa-circle-check"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #fee2e2; color: #991b1b; font-size: 0.75rem;">
                                        <i class="fa-solid fa-circle-xmark"></i> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem;" class="text-xs text-muted">
                                <?= !empty($u['created_at']) ? date('d M Y H:i', strtotime($u['created_at'])) : '-' ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <div class="flex justify-center items-center gap-1">
                                    <!-- Tombol Edit -->
                                    <button type="button" class="btn btn-secondary btn-sm" title="Edit Pengguna"
                                        onclick="openEditModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['email'])) ?>', '<?= htmlspecialchars($u['role']) ?>', <?= $isSelf ? 'true' : 'false' ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Tombol Reset Password -->
                                    <button type="button" class="btn btn-secondary btn-sm" title="Reset Password"
                                        onclick="openResetPasswordModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                        <i class="fa-solid fa-key"></i>
                                    </button>

                                    <!-- Tombol Nonaktifkan / Aktifkan -->
                                    <?php if ($isActive): ?>
                                        <?php if ($isSelf): ?>
                                            <button type="button" class="btn btn-sm" style="opacity: 0.4; cursor: not-allowed; background: #e2e8f0; color: #64748b;" title="Anda tidak dapat menonaktifkan akun sendiri" disabled>
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm" style="background: #fee2e2; color: #dc2626;" title="Nonaktifkan Akun"
                                                onclick="openDeactivateModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars($u['role']) ?>')">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm" style="background: #d1fae5; color: #059669;" title="Aktifkan Kembali Akun"
                                            onclick="openActivateModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center" style="padding: 2.5rem 1rem;">
            <p class="text-muted"><i class="fa-solid fa-user-slash fa-2x mb-2"></i><br>Tidak ada data pengguna yang sesuai kriteria pencarian.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Modal Edit Pengguna -->
<div id="editModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; margin: 1rem; position: relative;">
        <div class="card-header flex justify-between items-center">
            <h3 class="font-semibold"><i class="fa-solid fa-pen-to-square"></i> Edit Pengguna</h3>
            <button type="button" class="text-muted border-none bg-transparent cursor-pointer" onclick="closeModal('editModal')">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>
        <form id="editUserForm" method="POST" action="">
            <?= Security::csrfField() ?>
            <div class="form-group mb-3">
                <label class="form-label font-semibold" for="edit_name">Nama Lengkap</label>
                <input type="text" id="edit_name" name="name" class="form-control" required minlength="3">
            </div>
            <div class="form-group mb-3">
                <label class="form-label font-semibold" for="edit_email">Alamat Email</label>
                <input type="email" id="edit_email" name="email" class="form-control" required>
            </div>
            <div class="form-group mb-4">
                <label class="form-label font-semibold" for="edit_role">Role Pengguna</label>
                <select id="edit_role" name="role" class="form-control" required>
                    <option value="masyarakat">Masyarakat</option>
                    <option value="petugas">Petugas</option>
                    <option value="admin">Admin</option>
                </select>
                <small id="roleWarningText" class="text-muted text-xs" style="display: none; color: #dc2626;">Perhatian: Mengubah role akun Anda sendiri menjadi non-admin akan membatalkan hak akses administratif Anda.</small>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Password -->
<div id="resetPasswordModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 480px; margin: 1rem; position: relative;">
        <div class="card-header flex justify-between items-center">
            <h3 class="font-semibold"><i class="fa-solid fa-key"></i> Reset Password Pengguna</h3>
            <button type="button" class="text-muted border-none bg-transparent cursor-pointer" onclick="closeModal('resetPasswordModal')">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>
        <form id="resetPasswordForm" method="POST" action="">
            <?= Security::csrfField() ?>
            <p class="text-sm mb-3 text-muted">
                Atur kata sandi baru untuk pengguna: <strong id="resetTargetName" class="text-dark"></strong>
            </p>
            <div class="form-group mb-3">
                <label class="form-label font-semibold" for="new_password">Kata Sandi Baru</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 karakter" required minlength="6">
            </div>
            <div class="form-group mb-4">
                <label class="form-label font-semibold" for="new_password_confirm">Konfirmasi Kata Sandi Baru</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" placeholder="Ulangi kata sandi baru" required minlength="6">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Simpan Password Baru</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Nonaktifkan -->
<div id="deactivateModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 480px; margin: 1rem; position: relative; border-top: 4px solid #dc2626;">
        <div class="card-header flex justify-between items-center">
            <h3 class="font-semibold text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Nonaktifkan Akun</h3>
            <button type="button" class="text-muted border-none bg-transparent cursor-pointer" onclick="closeModal('deactivateModal')">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>
        <form id="deactivateForm" method="POST" action="">
            <?= Security::csrfField() ?>
            <p class="text-sm mb-3">
                Apakah Anda yakin ingin menonaktifkan akun <strong id="deactivateTargetName"></strong>?
            </p>
            <div class="p-3 mb-4 rounded" style="background: #fef2f2; border-left: 3px solid #ef4444; font-size: 0.8rem; color: #991b1b;">
                <ul style="margin: 0; padding-left: 1.25rem;">
                    <li>Pengguna tidak akan dapat masuk ke sistem.</li>
                    <li>Sesi login aktif akan langsung dibatalkan.</li>
                    <li><strong>Riwayat laporan dan data aktivitas sebelumnya tetap utuh.</strong></li>
                    <li>Akun ini dapat diaktifkan kembali kapan saja oleh administrator.</li>
                </ul>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deactivateModal')">Batal</button>
                <button type="submit" class="btn" style="background: #dc2626; color: white;"><i class="fa-solid fa-ban"></i> Ya, Nonaktifkan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Aktifkan -->
<div id="activateModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 480px; margin: 1rem; position: relative; border-top: 4px solid #059669;">
        <div class="card-header flex justify-between items-center">
            <h3 class="font-semibold" style="color: #059669;"><i class="fa-solid fa-circle-check"></i> Konfirmasi Aktifkan Akun</h3>
            <button type="button" class="text-muted border-none bg-transparent cursor-pointer" onclick="closeModal('activateModal')">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>
        <form id="activateForm" method="POST" action="">
            <?= Security::csrfField() ?>
            <p class="text-sm mb-3">
                Apakah Anda ingin mengaktifkan kembali akun <strong id="activateTargetName"></strong>?
            </p>
            <p class="text-xs text-muted mb-4">Pengguna akan dapat login kembali menggunakan email dan kata sandi yang sudah ada.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('activateModal')">Batal</button>
                <button type="submit" class="btn" style="background: #059669; color: white;"><i class="fa-solid fa-rotate-left"></i> Ya, Aktifkan Kembali</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAddUserForm() {
    const card = document.getElementById('addUserCard');
    const text = document.getElementById('toggleAddText');
    if (card.style.display === 'none' || card.style.display === '') {
        card.style.display = 'block';
        text.innerText = 'Tutup Form';
    } else {
        card.style.display = 'none';
        text.innerText = 'Tambah Pengguna';
    }
}

function handleRoleChange(val) {
    const phoneGroup = document.getElementById('group-phone');
    const areaGroup = document.getElementById('group-service-area');
    if (val === 'petugas') {
        phoneGroup.style.display = 'block';
        areaGroup.style.display = 'block';
    } else {
        phoneGroup.style.display = 'none';
        areaGroup.style.display = 'none';
    }
}

function openEditModal(id, name, email, role, isSelf) {
    const form = document.getElementById('editUserForm');
    form.action = '<?= base_url('/admin/pengguna/') ?>' + id + '/edit';
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;

    const warn = document.getElementById('roleWarningText');
    if (isSelf) {
        warn.style.display = 'block';
    } else {
        warn.style.display = 'none';
    }

    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
}

function openResetPasswordModal(id, name) {
    const form = document.getElementById('resetPasswordForm');
    form.action = '<?= base_url('/admin/pengguna/') ?>' + id + '/reset-password';
    document.getElementById('resetTargetName').innerText = name;
    document.getElementById('new_password').value = '';
    document.getElementById('new_password_confirm').value = '';

    const modal = document.getElementById('resetPasswordModal');
    modal.style.display = 'flex';
}

function openDeactivateModal(id, name, role) {
    const form = document.getElementById('deactivateForm');
    form.action = '<?= base_url('/admin/pengguna/') ?>' + id + '/nonaktifkan';
    document.getElementById('deactivateTargetName').innerText = name + ' (' + role + ')';

    const modal = document.getElementById('deactivateModal');
    modal.style.display = 'flex';
}

function openActivateModal(id, name) {
    const form = document.getElementById('activateForm');
    form.action = '<?= base_url('/admin/pengguna/') ?>' + id + '/aktifkan';
    document.getElementById('activateTargetName').innerText = name;

    const modal = document.getElementById('activateModal');
    modal.style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    ['editModal', 'resetPasswordModal', 'deactivateModal', 'activateModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
};
</script>

<?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>
