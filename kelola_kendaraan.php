<?php
/**
 * kelola_kendaraan.php
 * Dashboard Admin — Kelola Kendaraan
 * Fokus: manajemen seluruh data kendaraan terdaftar (tambah, ubah, hapus, verifikasi/tolak).
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_SUPER_ADMIN, ROLE_ADMIN]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$activePage = 'kelola_kendaraan';
$pageTitle  = 'Kelola Kendaraan';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ DAFTAR KENDARAAN ============ */
$daftarKendaraan = db_fetch_all(
    "SELECT id, plat_nomor, tipe, nama_pemilik, no_hp, sumber_registrasi, status_verifikasi, created_at
     FROM kendaraan ORDER BY created_at DESC",
    [],
    [
        ['id' => 1, 'plat_nomor' => 'D 4455 MK', 'tipe' => 'Motor', 'nama_pemilik' => 'Rina Kartika', 'no_hp' => '081311122233', 'sumber_registrasi' => 'Sistem Online', 'status_verifikasi' => 'Menunggu Verifikasi', 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'plat_nomor' => 'B 1234 XYZ', 'tipe' => 'Mobil', 'nama_pemilik' => 'Ahmad Fauzi', 'no_hp' => '081211122233', 'sumber_registrasi' => 'Loket', 'status_verifikasi' => 'Terverifikasi', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => 3, 'plat_nomor' => 'B 9988 QQ', 'tipe' => 'Motor', 'nama_pemilik' => 'Dewi Lestari', 'no_hp' => '081322233344', 'sumber_registrasi' => 'Sistem Online', 'status_verifikasi' => 'Ditolak', 'created_at' => date('Y-m-d H:i:s', strtotime('-6 days'))],
    ]
);

$totalKendaraan  = count($daftarKendaraan);
$totalMenunggu   = count(array_filter($daftarKendaraan, fn($k) => $k['status_verifikasi'] === 'Menunggu Verifikasi'));
$totalTerverif   = count(array_filter($daftarKendaraan, fn($k) => $k['status_verifikasi'] === 'Terverifikasi'));
$totalDitolak    = count(array_filter($daftarKendaraan, fn($k) => $k['status_verifikasi'] === 'Ditolak'));

$statusBadgeClass = function ($status) {
    return match ($status) {
        'Terverifikasi'        => 'bg-tertiary-fixed text-on-tertiary-fixed-variant',
        'Menunggu Verifikasi'  => 'bg-secondary-fixed text-on-secondary-fixed-variant',
        'Ditolak'               => 'bg-error-container text-on-error-container',
        default                  => 'bg-surface-container-high text-on-surface-variant',
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
            <h2 class="font-headline-lg text-headline-lg text-on-background">Kelola Kendaraan</h2>
            <p class="text-body-lg text-on-surface-variant">Kelola, verifikasi, dan tinjau seluruh kendaraan terdaftar.</p>
        </div>
        <div class="flex items-center gap-sm">
            <button type="button" id="btnExportKendaraan" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant border border-outline-variant/40 hover:bg-surface-container-high transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">download</span> Ekspor
            </button>
            <button type="button" id="btnTambahKendaraan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">directions_car_filled</span> Tambah Kendaraan
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">directions_car</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Kendaraan</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalKendaraan ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">pending_actions</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Menunggu Verifikasi</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalMenunggu ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">verified</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Terverifikasi</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalTerverif ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-error/10 rounded-lg text-error w-fit mb-base"><span class="material-symbols-outlined">cancel</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Ditolak</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalDitolak ?></h3>
        </div>
    </div>

    <!-- Tabel Kendaraan -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant flex flex-col md:flex-row md:items-center md:justify-between gap-sm">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar Kendaraan</h2>
            <div class="flex items-center gap-sm flex-wrap">
                <div class="flex flex-wrap gap-1" id="filterStatusKendaraan">
                    <button type="button" class="chip-status px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary" data-status="semua">Semua</button>
                    <button type="button" class="chip-status px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant" data-status="Menunggu Verifikasi">Menunggu</button>
                    <button type="button" class="chip-status px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant" data-status="Terverifikasi">Terverifikasi</button>
                    <button type="button" class="chip-status px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant" data-status="Ditolak">Ditolak</button>
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                    <input type="text" id="cariKendaraanKelola" placeholder="Cari plat nomor atau nama pemilik..."
                           class="pl-8 pr-3 py-2 text-body-md rounded-lg border border-outline-variant/40 bg-surface-container-lowest focus:outline-none focus:border-primary transition-colors">
                </div>
            </div>
        </div>

        <?php if (empty($daftarKendaraan)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Belum ada kendaraan yang terdaftar.</p>
        <?php else: ?>
        <div class="divide-y divide-outline-variant" id="daftarKendaraanKelola">
            <?php foreach ($daftarKendaraan as $k): ?>
            <div class="px-lg py-md flex flex-col md:flex-row md:items-center justify-between gap-sm" data-kendaraan-row data-id="<?= (int) $k['id'] ?>"
                 data-status="<?= htmlspecialchars($k['status_verifikasi']) ?>"
                 data-search="<?= htmlspecialchars(strtolower($k['plat_nomor'] . ' ' . $k['nama_pemilik'])) ?>">
                <div>
                    <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($k['plat_nomor']) ?></p>
                    <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($k['tipe']) ?> &bull; <?= htmlspecialchars($k['nama_pemilik']) ?> &bull; <?= htmlspecialchars($k['no_hp']) ?> &bull; <?= htmlspecialchars($k['sumber_registrasi']) ?></p>
                </div>
                <div class="flex items-center gap-sm flex-wrap">
                    <span class="px-2 py-0.5 rounded-full text-label-md font-bold <?= $statusBadgeClass($k['status_verifikasi']) ?>"><?= htmlspecialchars($k['status_verifikasi']) ?></span>
                    <div class="flex items-center gap-1 shrink-0">
                        <?php if ($k['status_verifikasi'] === 'Menunggu Verifikasi'): ?>
                        <button type="button" class="btn-verifikasi px-sm py-1 rounded-lg bg-tertiary/10 text-tertiary font-bold text-label-md hover:bg-tertiary/20 transition-colors"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>" data-aksi="setujui">Verifikasi</button>
                        <button type="button" class="btn-verifikasi px-sm py-1 rounded-lg bg-error-container/60 text-on-error-container font-bold text-label-md hover:bg-error-container transition-colors"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>" data-aksi="tolak">Tolak</button>
                        <?php endif; ?>
                        <button type="button" class="btn-edit-kendaraan p-1.5 rounded-lg hover:bg-surface-container-high transition-colors" title="Ubah"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>" data-tipe="<?= htmlspecialchars($k['tipe']) ?>"
                                data-nama="<?= htmlspecialchars($k['nama_pemilik']) ?>" data-hp="<?= htmlspecialchars($k['no_hp']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">edit</span>
                        </button>
                        <button type="button" class="btn-hapus-kendaraan p-1.5 rounded-lg hover:bg-error-container/60 transition-colors" title="Hapus"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="px-lg py-lg text-body-md text-on-surface-variant hidden" id="kendaraanKelolaNoResult">Tidak ada hasil yang cocok.</p>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Modal Tambah / Ubah Kendaraan -->
<div id="modalFormKendaraan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md" id="modalFormKendaraanTitle">Tambah Kendaraan</h3>
        <form id="formKendaraan" class="space-y-sm">
            <input type="hidden" id="kendaraanId" value="">
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="kendaraanPlat">Plat Nomor</label>
                <input type="text" id="kendaraanPlat" required placeholder="Contoh: B 1234 XYZ" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary uppercase">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="kendaraanTipe">Tipe Kendaraan</label>
                <select id="kendaraanTipe" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <option value="Motor">Motor</option>
                    <option value="Mobil">Mobil</option>
                </select>
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="kendaraanNama">Nama Pemilik</label>
                <input type="text" id="kendaraanNama" required class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="kendaraanHp">No. HP Pemilik</label>
                <input type="text" id="kendaraanHp" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
        </form>
        <div class="flex justify-end gap-sm mt-md">
            <button type="button" id="modalFormKendaraanBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalFormKendaraanSimpan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<!-- Modal Verifikasi / Tolak -->
<div id="modalVerifikasi" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-1" id="modalVerifikasiTitle">Konfirmasi</h3>
        <p class="text-body-md text-on-surface-variant mb-md" id="modalVerifikasiDesc"></p>
        <div id="modalTolakAlasanWrap" class="hidden mb-md">
            <label class="text-label-md text-on-surface-variant mb-1 block" for="modalTolakAlasan">Alasan penolakan</label>
            <textarea id="modalTolakAlasan" rows="3" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary" placeholder="Contoh: STNK tidak terbaca / data tidak sesuai"></textarea>
        </div>
        <div class="flex justify-end gap-sm">
            <button type="button" id="modalBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalKonfirmasi" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Konfirmasi</button>
        </div>
    </div>
</div>

<!-- Modal Hapus Kendaraan -->
<div id="modalHapusKendaraan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-1">Hapus Kendaraan</h3>
        <p class="text-body-md text-on-surface-variant mb-md" id="modalHapusKendaraanDesc"></p>
        <div class="flex justify-end gap-sm">
            <button type="button" id="modalHapusKendaraanBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalHapusKendaraanYa" class="px-md py-2 rounded-lg text-body-md font-bold bg-error text-on-error hover:opacity-90 transition-opacity">Ya, Hapus</button>
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

    /* ---------- Filter status + pencarian ---------- */
    const inputCari = document.getElementById('cariKendaraanKelola');
    const chipsStatus = document.querySelectorAll('.chip-status');
    let statusAktif = 'semua';

    function terapkanFilter() {
        const q = (inputCari?.value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('[data-kendaraan-row]');
        let visibleCount = 0;
        rows.forEach(row => {
            const cocokStatus = statusAktif === 'semua' || row.dataset.status === statusAktif;
            const cocokCari = row.dataset.search.includes(q);
            const tampil = cocokStatus && cocokCari;
            row.classList.toggle('hidden', !tampil);
            if (tampil) visibleCount++;
        });
        document.getElementById('kendaraanKelolaNoResult')?.classList.toggle('hidden', visibleCount !== 0);
    }

    inputCari?.addEventListener('input', terapkanFilter);
    chipsStatus.forEach(chip => {
        chip.addEventListener('click', () => {
            chipsStatus.forEach(c => c.className = 'chip-status px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant');
            chip.className = 'chip-status px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary';
            statusAktif = chip.dataset.status;
            terapkanFilter();
        });
    });

    /* ---------- Modal form tambah/ubah kendaraan ---------- */
    const modalForm = document.getElementById('modalFormKendaraan');
    const modalFormTitle = document.getElementById('modalFormKendaraanTitle');
    const inputId = document.getElementById('kendaraanId');
    const inputPlat = document.getElementById('kendaraanPlat');
    const inputTipe = document.getElementById('kendaraanTipe');
    const inputNama = document.getElementById('kendaraanNama');
    const inputHp = document.getElementById('kendaraanHp');

    function bukaModalForm(mode, data = {}) {
        inputId.value = data.id || '';
        inputPlat.value = data.plat || '';
        inputTipe.value = data.tipe || 'Motor';
        inputNama.value = data.nama || '';
        inputHp.value = data.hp || '';
        modalFormTitle.textContent = mode === 'tambah' ? 'Tambah Kendaraan' : 'Ubah Kendaraan';
        modalForm.classList.remove('hidden');
        modalForm.classList.add('flex');
    }

    function tutupModalForm() {
        modalForm.classList.add('hidden');
        modalForm.classList.remove('flex');
    }

    document.getElementById('btnTambahKendaraan').addEventListener('click', () => bukaModalForm('tambah'));
    document.getElementById('modalFormKendaraanBatal').addEventListener('click', tutupModalForm);
    modalForm.addEventListener('click', (e) => { if (e.target === modalForm) tutupModalForm(); });

    document.querySelectorAll('.btn-edit-kendaraan').forEach(btn => {
        btn.addEventListener('click', () => bukaModalForm('ubah', {
            id: btn.dataset.id, plat: btn.dataset.plat, tipe: btn.dataset.tipe, nama: btn.dataset.nama, hp: btn.dataset.hp,
        }));
    });

    document.getElementById('modalFormKendaraanSimpan').addEventListener('click', async () => {
        if (!inputPlat.value.trim() || !inputNama.value.trim()) {
            showToast('Plat nomor dan nama pemilik wajib diisi.', 'error');
            return;
        }
        const aksi = inputId.value ? 'ubah' : 'tambah';
        const btn = document.getElementById('modalFormKendaraanSimpan');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        try {
            const res = await fetch('aksi_kelola_kendaraan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    aksi, id: inputId.value || null,
                    plat_nomor: inputPlat.value.trim().toUpperCase(), tipe: inputTipe.value,
                    nama_pemilik: inputNama.value.trim(), no_hp: inputHp.value.trim(), csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan data kendaraan.');
            showToast(aksi === 'tambah' ? 'Kendaraan baru berhasil ditambahkan.' : 'Data kendaraan berhasil diperbarui.');
            tutupModalForm();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    });

    /* ---------- Verifikasi / Tolak kendaraan ---------- */
    const modal = document.getElementById('modalVerifikasi');
    const modalTitle = document.getElementById('modalVerifikasiTitle');
    const modalDesc = document.getElementById('modalVerifikasiDesc');
    const modalAlasanWrap = document.getElementById('modalTolakAlasanWrap');
    const modalAlasan = document.getElementById('modalTolakAlasan');
    const modalKonfirmasi = document.getElementById('modalKonfirmasi');
    let pendingAction = null;

    function openModal({ id, plat, aksi }) {
        pendingAction = { id, plat, aksi };
        modalAlasan.value = '';
        if (aksi === 'setujui') {
            modalTitle.textContent = 'Verifikasi Kendaraan';
            modalDesc.textContent = `Setujui pendaftaran kendaraan dengan plat nomor ${plat}?`;
            modalAlasanWrap.classList.add('hidden');
            modalKonfirmasi.className = 'px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity';
        } else {
            modalTitle.textContent = 'Tolak Pendaftaran';
            modalDesc.textContent = `Tolak pendaftaran kendaraan dengan plat nomor ${plat}?`;
            modalAlasanWrap.classList.remove('hidden');
            modalKonfirmasi.className = 'px-md py-2 rounded-lg text-body-md font-bold bg-error text-on-error hover:opacity-90 transition-opacity';
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        pendingAction = null;
    }

    document.getElementById('modalBatal').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    document.querySelectorAll('.btn-verifikasi').forEach(btn => {
        btn.addEventListener('click', () => openModal({
            id: btn.dataset.id, plat: btn.dataset.plat, aksi: btn.dataset.aksi,
        }));
    });

    modalKonfirmasi.addEventListener('click', async () => {
        if (!pendingAction) return;
        const { id, aksi } = pendingAction;
        modalKonfirmasi.disabled = true;
        modalKonfirmasi.textContent = 'Memproses...';
        try {
            const res = await fetch('aksi_verifikasi_kendaraan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, aksi, alasan: modalAlasan.value || null, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memproses permintaan.');
            showToast(aksi === 'setujui' ? 'Kendaraan berhasil diverifikasi.' : 'Pendaftaran kendaraan ditolak.');
            closeModal();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            modalKonfirmasi.disabled = false;
            modalKonfirmasi.textContent = 'Konfirmasi';
        }
    });

    /* ---------- Hapus kendaraan ---------- */
    const modalHapus = document.getElementById('modalHapusKendaraan');
    const modalHapusDesc = document.getElementById('modalHapusKendaraanDesc');
    let idHapus = null;

    document.querySelectorAll('.btn-hapus-kendaraan').forEach(btn => {
        btn.addEventListener('click', () => {
            idHapus = btn.dataset.id;
            modalHapusDesc.textContent = `Hapus data kendaraan dengan plat nomor "${btn.dataset.plat}"? Tindakan ini tidak dapat dibatalkan.`;
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        });
    });

    function tutupModalHapus() {
        modalHapus.classList.add('hidden');
        modalHapus.classList.remove('flex');
        idHapus = null;
    }

    document.getElementById('modalHapusKendaraanBatal').addEventListener('click', tutupModalHapus);
    modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) tutupModalHapus(); });

    document.getElementById('modalHapusKendaraanYa').addEventListener('click', async () => {
        if (!idHapus) return;
        const btn = document.getElementById('modalHapusKendaraanYa');
        btn.disabled = true;
        btn.textContent = 'Menghapus...';
        try {
            const res = await fetch('aksi_kelola_kendaraan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'hapus', id: idHapus, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menghapus kendaraan.');
            showToast('Kendaraan berhasil dihapus.');
            tutupModalHapus();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Ya, Hapus';
        }
    });

    /* ---------- Ekspor CSV ---------- */
    function downloadCsv(filename, rows) {
        const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    document.getElementById('btnExportKendaraan')?.addEventListener('click', () => {
        const rows = [['Plat Nomor', 'Status']];
        document.querySelectorAll('[data-kendaraan-row]').forEach(row => {
            const plat = row.querySelector('p.font-bold')?.textContent.trim() ?? '';
            rows.push([plat, row.dataset.status]);
        });
        downloadCsv('daftar_kendaraan.csv', rows);
        showToast('Data kendaraan berhasil diekspor.');
    });
})();
</script>

<?php include 'includes/footer.php'; ?>