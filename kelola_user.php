<?php
/**
 * kelola_user.php
 * Dashboard Admin — Kelola User
 * Fokus: manajemen akun pengguna sistem (Admin, Petugas, dsb).
 *
 * CATATAN STRUKTUR (disesuaikan dari struktur asli tabel users & roles):
 * - Tabel `users` tidak punya kolom `role` teks, tapi `role_id` (FK ke tabel `roles`).
 *   Query di bawah JOIN ke `roles` supaya dapat nama role-nya (r.nama_role AS role).
 * - Nama role di database berbentuk "Admin", "Owner", "Super Admin", dst — sama
 *   persis dengan konstanta ROLE_* di config.php. SEMUA perbandingan/kunci array
 *   di file ini memakai konstanta ROLE_* itu (bukan string lowercase seperti
 *   'admin'/'officer' yang dipakai versi sebelumnya, karena itu tidak akan pernah
 *   cocok dengan data asli).
 * - Kolom `no_hp` ditambahkan lewat ALTER TABLE (lihat catatan terpisah).
 * - Kolom `status` bertipe ENUM('Aktif', 'Non-Aktif') — perhatikan tanda hubung.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_SUPER_ADMIN, ROLE_ADMIN]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$activePage = 'kelola_user';
$pageTitle  = 'Kelola User';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ DAFTAR ROLE ============ */
// Key HARUS sama persis dengan nama_role di tabel roles (dan konstanta ROLE_*).
$daftarRole = [
    ROLE_SUPER_ADMIN => 'Super Admin',
    ROLE_OWNER       => 'Owner',
    ROLE_ADMIN       => 'Admin',
    ROLE_OFFICER     => 'Petugas (Officer)',
    ROLE_SECURITY    => 'Security',
    ROLE_USER        => 'User',
];

/* ============ DAFTAR USER ============ */
$daftarUserRaw = db_fetch_all(
    "SELECT u.id, u.nama_lengkap, u.email, u.no_hp, r.nama_role AS role, u.status, u.created_at
     FROM users u
     JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC",
    [],
    [
        ['id' => 1, 'nama_lengkap' => 'Budi Santoso', 'email' => 'budi.admin@parkir.local', 'no_hp' => '081234567890', 'role' => ROLE_ADMIN, 'status' => 'Aktif', 'created_at' => date('Y-m-d H:i:s', strtotime('-40 days'))],
        ['id' => 2, 'nama_lengkap' => 'Siti Aminah', 'email' => 'siti.officer@parkir.local', 'no_hp' => '081298765432', 'role' => ROLE_OFFICER, 'status' => 'Aktif', 'created_at' => date('Y-m-d H:i:s', strtotime('-25 days'))],
        ['id' => 3, 'nama_lengkap' => 'Joko Purnomo', 'email' => 'joko.security@parkir.local', 'no_hp' => '081311122233', 'role' => ROLE_SECURITY, 'status' => 'Non-Aktif', 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ['id' => 4, 'nama_lengkap' => 'Andi Wijaya', 'email' => 'andi.owner@parkir.local', 'no_hp' => '081399988877', 'role' => ROLE_OWNER, 'status' => 'Aktif', 'created_at' => date('Y-m-d H:i:s', strtotime('-90 days'))],
    ]
);

$totalUser     = count($daftarUserRaw);
$totalAdmin    = count(array_filter($daftarUserRaw, fn($u) => in_array($u['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_OWNER], true)));
$totalPetugas  = count(array_filter($daftarUserRaw, fn($u) => in_array($u['role'], [ROLE_OFFICER, ROLE_SECURITY], true)));
$totalNonaktif = count(array_filter($daftarUserRaw, fn($u) => $u['status'] !== 'Aktif'));

$roleBadgeClass = function ($role) {
    return match ($role) {
        ROLE_OWNER, ROLE_SUPER_ADMIN => 'bg-primary/10 text-primary',
        ROLE_ADMIN                    => 'bg-secondary/10 text-secondary',
        ROLE_OFFICER, ROLE_SECURITY   => 'bg-tertiary/10 text-tertiary',
        ROLE_USER                     => 'bg-surface-container-high text-on-surface-variant',
        default                        => 'bg-secondary/10 text-secondary',
    };
};

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-sm">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Kelola User</h2>
            <p class="text-body-lg text-on-surface-variant">Kelola akun admin, petugas, dan pengguna sistem parkir.</p>
        </div>
        <button type="button" id="btnTambahUser" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">person_add</span> Tambah User
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">group</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total User</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalUser ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">admin_panel_settings</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Admin</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalAdmin ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">badge</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Petugas</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalPetugas ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-error/10 rounded-lg text-error w-fit mb-base"><span class="material-symbols-outlined">person_off</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Nonaktif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalNonaktif ?></h3>
        </div>
    </div>

    <!-- Tabel User -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant flex flex-col md:flex-row md:items-center md:justify-between gap-sm">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar User</h2>
            <div class="flex items-center gap-sm flex-wrap">
                <div class="flex flex-wrap gap-1" id="filterRoleUser">
                    <button type="button" class="chip-role px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary" data-role="semua">Semua</button>
                    <?php foreach ($daftarRole as $key => $label): ?>
                    <button type="button" class="chip-role px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant" data-role="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                    <input type="text" id="cariUser" placeholder="Cari nama atau email..."
                           class="pl-8 pr-3 py-2 text-body-md rounded-lg border border-outline-variant/40 bg-surface-container-lowest focus:outline-none focus:border-primary transition-colors">
                </div>
            </div>
        </div>

        <?php if (empty($daftarUserRaw)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant" id="userEmptyState">Belum ada user yang terdaftar.</p>
        <?php else: ?>
        <div class="divide-y divide-outline-variant" id="daftarUser">
            <?php foreach ($daftarUserRaw as $u): ?>
            <div class="px-lg py-md flex flex-col md:flex-row md:items-center justify-between gap-sm" data-user-row data-id="<?= (int) $u['id'] ?>"
                 data-role="<?= htmlspecialchars($u['role']) ?>"
                 data-search="<?= htmlspecialchars(strtolower($u['nama_lengkap'] . ' ' . $u['email'])) ?>">
                <div class="flex items-center gap-sm">
                    <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                        <?= htmlspecialchars(strtoupper(substr($u['nama_lengkap'], 0, 1))) ?>
                    </div>
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($u['nama_lengkap']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($u['email']) ?> &bull; <?= htmlspecialchars($u['no_hp'] ?? '-') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-sm flex-wrap">
                    <span class="px-2 py-0.5 rounded-full text-label-md font-bold <?= $roleBadgeClass($u['role']) ?>"><?= htmlspecialchars($daftarRole[$u['role']] ?? $u['role']) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-label-md <?= $u['status'] === 'Aktif' ? 'bg-tertiary-fixed text-on-tertiary-fixed-variant' : 'bg-error-container text-on-error-container' ?>"><?= htmlspecialchars($u['status']) ?></span>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-edit-user p-1.5 rounded-lg hover:bg-surface-container-high transition-colors" title="Ubah"
                                data-id="<?= (int) $u['id'] ?>" data-nama="<?= htmlspecialchars($u['nama_lengkap']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>"
                                data-hp="<?= htmlspecialchars($u['no_hp'] ?? '') ?>" data-role="<?= htmlspecialchars($u['role']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">edit</span>
                        </button>
                        <button type="button" class="btn-toggle-status p-1.5 rounded-lg hover:bg-surface-container-high transition-colors" title="<?= $u['status'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                data-id="<?= (int) $u['id'] ?>" data-status="<?= htmlspecialchars($u['status']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant"><?= $u['status'] === 'Aktif' ? 'toggle_on' : 'toggle_off' ?></span>
                        </button>
                        <button type="button" class="btn-hapus-user p-1.5 rounded-lg hover:bg-error-container/60 transition-colors" title="Hapus"
                                data-id="<?= (int) $u['id'] ?>" data-nama="<?= htmlspecialchars($u['nama_lengkap']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="px-lg py-lg text-body-md text-on-surface-variant hidden" id="userNoResult">Tidak ada hasil yang cocok dengan pencarian.</p>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Modal Tambah / Ubah User -->
<div id="modalFormUser" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md" id="modalFormUserTitle">Tambah User</h3>
        <form id="formUser" class="space-y-sm">
            <input type="hidden" id="userId" value="">
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="userNama">Nama Lengkap</label>
                <input type="text" id="userNama" required class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="userEmail">Email</label>
                <input type="email" id="userEmail" required class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="userHp">No. HP</label>
                <input type="text" id="userHp" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="userRole">Role</label>
                <select id="userRole" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <?php foreach ($daftarRole as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="userPassword">Password <span id="userPasswordHint" class="font-normal">(kosongkan jika tidak diubah)</span></label>
                <input type="password" id="userPassword" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary" placeholder="Minimal 8 karakter">
            </div>
        </form>
        <div class="flex justify-end gap-sm mt-md">
            <button type="button" id="modalFormUserBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalFormUserSimpan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus / Toggle Status -->
<div id="modalKonfirmasiUser" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-1" id="modalKonfirmasiUserTitle">Konfirmasi</h3>
        <p class="text-body-md text-on-surface-variant mb-md" id="modalKonfirmasiUserDesc"></p>
        <div class="flex justify-end gap-sm">
            <button type="button" id="modalKonfirmasiUserBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalKonfirmasiUserYa" class="px-md py-2 rounded-lg text-body-md font-bold bg-error text-on-error hover:opacity-90 transition-opacity">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;

    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const bg = type === 'success' ? 'bg-tertiary text-on-tertiary' : 'bg-error text-on-error';
        const el = document.createElement('div');
        el.className = `px-md py-sm rounded-lg shadow-soft text-body-md font-medium ${bg} animate-fade-in`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, 3000);
    }

    /* ---------- Filter role + pencarian ---------- */
    const inputCari = document.getElementById('cariUser');
    const chipsRole = document.querySelectorAll('.chip-role');
    let roleAktif = 'semua';

    function terapkanFilter() {
        const q = (inputCari?.value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('[data-user-row]');
        let visibleCount = 0;
        rows.forEach(row => {
            const cocokRole = roleAktif === 'semua' || row.dataset.role === roleAktif;
            const cocokCari = row.dataset.search.includes(q);
            const tampil = cocokRole && cocokCari;
            row.classList.toggle('hidden', !tampil);
            if (tampil) visibleCount++;
        });
        document.getElementById('userNoResult')?.classList.toggle('hidden', visibleCount !== 0);
    }

    inputCari?.addEventListener('input', terapkanFilter);
    chipsRole.forEach(chip => {
        chip.addEventListener('click', () => {
            chipsRole.forEach(c => c.className = 'chip-role px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant');
            chip.className = 'chip-role px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary';
            roleAktif = chip.dataset.role;
            terapkanFilter();
        });
    });

    /* ---------- Modal form tambah/ubah ---------- */
    const modalForm = document.getElementById('modalFormUser');
    const modalFormTitle = document.getElementById('modalFormUserTitle');
    const inputId = document.getElementById('userId');
    const inputNama = document.getElementById('userNama');
    const inputEmail = document.getElementById('userEmail');
    const inputHp = document.getElementById('userHp');
    const inputRole = document.getElementById('userRole');
    const inputPassword = document.getElementById('userPassword');
    const passwordHint = document.getElementById('userPasswordHint');

    function bukaModalForm(mode, data = {}) {
        inputId.value = data.id || '';
        inputNama.value = data.nama || '';
        inputEmail.value = data.email || '';
        inputHp.value = data.hp || '';
        inputRole.value = data.role || inputRole.options[0].value;
        inputPassword.value = '';
        if (mode === 'tambah') {
            modalFormTitle.textContent = 'Tambah User';
            passwordHint.textContent = '';
            inputPassword.required = true;
            inputPassword.value = 'password123';
        } else {
            modalFormTitle.textContent = 'Ubah User';
            passwordHint.textContent = '(kosongkan jika tidak diubah)';
            inputPassword.required = false;
        }
        modalForm.classList.remove('hidden');
        modalForm.classList.add('flex');
    }

    function tutupModalForm() {
        modalForm.classList.add('hidden');
        modalForm.classList.remove('flex');
    }

    document.getElementById('btnTambahUser').addEventListener('click', () => bukaModalForm('tambah'));
    document.getElementById('modalFormUserBatal').addEventListener('click', tutupModalForm);
    modalForm.addEventListener('click', (e) => { if (e.target === modalForm) tutupModalForm(); });

    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', () => bukaModalForm('ubah', {
            id: btn.dataset.id, nama: btn.dataset.nama, email: btn.dataset.email, hp: btn.dataset.hp, role: btn.dataset.role,
        }));
    });

    document.getElementById('modalFormUserSimpan').addEventListener('click', async () => {
        if (!inputNama.value.trim() || !inputEmail.value.trim()) {
            showToast('Nama dan email wajib diisi.', 'error');
            return;
        }
        const aksi = inputId.value ? 'ubah' : 'tambah';
        const btn = document.getElementById('modalFormUserSimpan');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        try {
            const res = await fetch('aksi_kelola_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    aksi, id: inputId.value || null,
                    nama_lengkap: inputNama.value.trim(), email: inputEmail.value.trim(),
                    no_hp: inputHp.value.trim(), role: inputRole.value,
                    password: inputPassword.value || null, csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan data user.');
            showToast(aksi === 'tambah' ? 'User baru berhasil ditambahkan.' : 'Data user berhasil diperbarui.');
            tutupModalForm();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    });

    /* ---------- Modal konfirmasi hapus / toggle status ---------- */
    const modalKonfirmasi = document.getElementById('modalKonfirmasiUser');
    const modalKonfirmasiTitle = document.getElementById('modalKonfirmasiUserTitle');
    const modalKonfirmasiDesc = document.getElementById('modalKonfirmasiUserDesc');
    let pendingAction = null;

    function bukaModalKonfirmasi({ id, aksi, judul, deskripsi }) {
        pendingAction = { id, aksi };
        modalKonfirmasiTitle.textContent = judul;
        modalKonfirmasiDesc.textContent = deskripsi;
        modalKonfirmasi.classList.remove('hidden');
        modalKonfirmasi.classList.add('flex');
    }

    function tutupModalKonfirmasi() {
        modalKonfirmasi.classList.add('hidden');
        modalKonfirmasi.classList.remove('flex');
        pendingAction = null;
    }

    document.getElementById('modalKonfirmasiUserBatal').addEventListener('click', tutupModalKonfirmasi);
    modalKonfirmasi.addEventListener('click', (e) => { if (e.target === modalKonfirmasi) tutupModalKonfirmasi(); });

    document.querySelectorAll('.btn-hapus-user').forEach(btn => {
        btn.addEventListener('click', () => bukaModalKonfirmasi({
            id: btn.dataset.id, aksi: 'hapus',
            judul: 'Hapus User',
            deskripsi: `Hapus user "${btn.dataset.nama}"? Tindakan ini tidak dapat dibatalkan.`,
        }));
    });

    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', () => {
            const aktifkan = btn.dataset.status !== 'Aktif';
            bukaModalKonfirmasi({
                id: btn.dataset.id, aksi: 'toggle_status',
                judul: aktifkan ? 'Aktifkan User' : 'Nonaktifkan User',
                deskripsi: aktifkan ? 'Aktifkan kembali akun user ini?' : 'Nonaktifkan akun user ini? User tidak akan bisa login.',
            });
        });
    });

    document.getElementById('modalKonfirmasiUserYa').addEventListener('click', async () => {
        if (!pendingAction) return;
        const { id, aksi } = pendingAction;
        const btn = document.getElementById('modalKonfirmasiUserYa');
        btn.disabled = true;
        btn.textContent = 'Memproses...';
        try {
            const res = await fetch('aksi_kelola_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi, id, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memproses permintaan.');
            showToast(aksi === 'hapus' ? 'User berhasil dihapus.' : 'Status user berhasil diperbarui.');
            tutupModalKonfirmasi();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Ya, Lanjutkan';
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>