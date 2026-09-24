<?php
/**
 * kelola_tarif.php
 * Dashboard Admin — Kelola Tarif
 * Fokus: manajemen data tarif parkir per tipe kendaraan.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_SUPER_ADMIN, ROLE_ADMIN]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$activePage = 'kelola_tarif';
$pageTitle  = 'Kelola Tarif';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ DAFTAR TARIF ============ */
$daftarTarif = db_fetch_all(
    "SELECT id, tipe_kendaraan, deskripsi, tarif_per_jam, tarif_maks_harian, status
     FROM tarif ORDER BY tipe_kendaraan ASC",
    [],
    [
        ['id' => 1, 'tipe_kendaraan' => 'Motor', 'deskripsi' => 'Motor Roda Dua Standard', 'tarif_per_jam' => 3000, 'tarif_maks_harian' => 20000, 'status' => 'Aktif'],
        ['id' => 2, 'tipe_kendaraan' => 'Mobil', 'deskripsi' => 'Mobil Pribadi / Sedan / SUV', 'tarif_per_jam' => 7000, 'tarif_maks_harian' => 50000, 'status' => 'Aktif'],
        ['id' => 3, 'tipe_kendaraan' => 'Bus / Truk', 'deskripsi' => 'Kendaraan Roda Besar', 'tarif_per_jam' => 15000, 'tarif_maks_harian' => 90000, 'status' => 'Nonaktif'],
    ]
);

$totalTarif    = count($daftarTarif);
$totalAktif    = count(array_filter($daftarTarif, fn($t) => $t['status'] === 'Aktif'));
$tarifTertinggi = $daftarTarif ? max(array_column($daftarTarif, 'tarif_per_jam')) : 0;
$tarifTerendah  = $daftarTarif ? min(array_column($daftarTarif, 'tarif_per_jam')) : 0;

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-sm">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Kelola Tarif</h2>
            <p class="text-body-lg text-on-surface-variant">Kelola tarif parkir untuk setiap tipe kendaraan.</p>
        </div>
        <div class="flex items-center gap-sm">
            <button type="button" id="btnExportTarifKelola" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant border border-outline-variant/40 hover:bg-surface-container-high transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">download</span> Ekspor
            </button>
            <button type="button" id="btnTambahTarif" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">add_circle</span> Tambah Tarif
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">sell</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Tarif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalTarif ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">toggle_on</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Tarif Aktif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalAktif ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">trending_up</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Tarif Tertinggi/Jam</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($tarifTertinggi) ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">trending_down</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Tarif Terendah/Jam</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($tarifTerendah) ?></h3>
        </div>
    </div>

    <!-- Tabel Tarif -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar Tarif</h2>
        </div>
        <?php if (empty($daftarTarif)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Belum ada tarif yang ditetapkan.</p>
        <?php else: ?>
        <div class="divide-y divide-outline-variant" id="tabelDaftarTarifKelola">
            <?php foreach ($daftarTarif as $t): ?>
            <div class="px-lg py-md flex flex-col md:flex-row md:items-center justify-between gap-sm" data-tarif-row data-id="<?= (int) $t['id'] ?>"
                 data-tipe="<?= htmlspecialchars($t['tipe_kendaraan']) ?>" data-tarif="<?= (int) $t['tarif_per_jam'] ?>" data-status="<?= htmlspecialchars($t['status']) ?>">
                <div>
                    <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($t['tipe_kendaraan']) ?></p>
                    <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($t['deskripsi']) ?></p>
                </div>
                <div class="flex items-center gap-sm flex-wrap">
                    <div class="text-right">
                        <p class="text-body-md font-bold"><?= format_rupiah($t['tarif_per_jam']) ?>/jam</p>
                        <p class="text-label-md text-on-surface-variant">Maks. <?= format_rupiah($t['tarif_maks_harian']) ?>/hari</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-label-md <?= $t['status'] === 'Aktif' ? 'bg-tertiary-fixed text-on-tertiary-fixed-variant' : 'bg-error-container text-on-error-container' ?>"><?= htmlspecialchars($t['status']) ?></span>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-edit-tarif p-1.5 rounded-lg hover:bg-surface-container-high transition-colors" title="Ubah"
                                data-id="<?= (int) $t['id'] ?>" data-tipe="<?= htmlspecialchars($t['tipe_kendaraan']) ?>" data-deskripsi="<?= htmlspecialchars($t['deskripsi']) ?>"
                                data-tarif="<?= (int) $t['tarif_per_jam'] ?>" data-maks="<?= (int) $t['tarif_maks_harian'] ?>" data-status="<?= htmlspecialchars($t['status']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">edit</span>
                        </button>
                        <button type="button" class="btn-hapus-tarif p-1.5 rounded-lg hover:bg-error-container/60 transition-colors" title="Hapus"
                                data-id="<?= (int) $t['id'] ?>" data-tipe="<?= htmlspecialchars($t['tipe_kendaraan']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Modal Tambah / Ubah Tarif -->
<div id="modalFormTarif" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md" id="modalFormTarifTitle">Tambah Tarif</h3>
        <form id="formTarif" class="space-y-sm">
            <input type="hidden" id="tarifId" value="">
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="tarifTipe">Tipe Kendaraan</label>
                <input type="text" id="tarifTipe" required placeholder="Contoh: Motor, Mobil, Bus/Truk" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="tarifDeskripsi">Deskripsi</label>
                <input type="text" id="tarifDeskripsi" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary" placeholder="Contoh: Motor Roda Dua Standard">
            </div>
            <div class="grid grid-cols-2 gap-sm">
                <div>
                    <label class="text-label-md text-on-surface-variant mb-1 block" for="tarifPerJam">Tarif per Jam (Rp)</label>
                    <input type="number" id="tarifPerJam" min="0" step="500" required class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="text-label-md text-on-surface-variant mb-1 block" for="tarifMaksHarian">Maks. per Hari (Rp)</label>
                    <input type="number" id="tarifMaksHarian" min="0" step="500" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                </div>
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="tarifStatus">Status</label>
                <select id="tarifStatus" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>
        </form>
        <div class="flex justify-end gap-sm mt-md">
            <button type="button" id="modalFormTarifBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalFormTarifSimpan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<!-- Modal Hapus Tarif -->
<div id="modalHapusTarif" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-1">Hapus Tarif</h3>
        <p class="text-body-md text-on-surface-variant mb-md" id="modalHapusTarifDesc"></p>
        <div class="flex justify-end gap-sm">
            <button type="button" id="modalHapusTarifBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalHapusTarifYa" class="px-md py-2 rounded-lg text-body-md font-bold bg-error text-on-error hover:opacity-90 transition-opacity">Ya, Hapus</button>
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

    /* ---------- Modal form tambah/ubah tarif ---------- */
    const modalForm = document.getElementById('modalFormTarif');
    const modalFormTitle = document.getElementById('modalFormTarifTitle');
    const inputId = document.getElementById('tarifId');
    const inputTipe = document.getElementById('tarifTipe');
    const inputDeskripsi = document.getElementById('tarifDeskripsi');
    const inputPerJam = document.getElementById('tarifPerJam');
    const inputMaks = document.getElementById('tarifMaksHarian');
    const inputStatus = document.getElementById('tarifStatus');

    function bukaModalForm(mode, data = {}) {
        inputId.value = data.id || '';
        inputTipe.value = data.tipe || '';
        inputDeskripsi.value = data.deskripsi || '';
        inputPerJam.value = data.tarif || '';
        inputMaks.value = data.maks || '';
        inputStatus.value = data.status || 'Aktif';
        modalFormTitle.textContent = mode === 'tambah' ? 'Tambah Tarif' : 'Ubah Tarif';
        modalForm.classList.remove('hidden');
        modalForm.classList.add('flex');
    }

    function tutupModalForm() {
        modalForm.classList.add('hidden');
        modalForm.classList.remove('flex');
    }

    document.getElementById('btnTambahTarif').addEventListener('click', () => bukaModalForm('tambah'));
    document.getElementById('modalFormTarifBatal').addEventListener('click', tutupModalForm);
    modalForm.addEventListener('click', (e) => { if (e.target === modalForm) tutupModalForm(); });

    document.querySelectorAll('.btn-edit-tarif').forEach(btn => {
        btn.addEventListener('click', () => bukaModalForm('ubah', {
            id: btn.dataset.id, tipe: btn.dataset.tipe, deskripsi: btn.dataset.deskripsi,
            tarif: btn.dataset.tarif, maks: btn.dataset.maks, status: btn.dataset.status,
        }));
    });

    document.getElementById('modalFormTarifSimpan').addEventListener('click', async () => {
        if (!inputTipe.value.trim() || !inputPerJam.value) {
            showToast('Tipe kendaraan dan tarif per jam wajib diisi.', 'error');
            return;
        }
        const aksi = inputId.value ? 'ubah' : 'tambah';
        const btn = document.getElementById('modalFormTarifSimpan');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        try {
              const res = await fetch('aksi_kelola_tarif.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    aksi, id: inputId.value || null,
                    tipe_kendaraan: inputTipe.value.trim(), deskripsi: inputDeskripsi.value.trim(),
                    tarif_per_jam: parseInt(inputPerJam.value, 10), tarif_maks_harian: inputMaks.value ? parseInt(inputMaks.value, 10) : 0,
                    status: inputStatus.value, csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan data tarif.');
            showToast(aksi === 'tambah' ? 'Tarif baru berhasil ditambahkan.' : 'Data tarif berhasil diperbarui.');
            tutupModalForm();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    });

    /* ---------- Hapus tarif ---------- */
    const modalHapus = document.getElementById('modalHapusTarif');
    const modalHapusDesc = document.getElementById('modalHapusTarifDesc');
    let idHapus = null;

    document.querySelectorAll('.btn-hapus-tarif').forEach(btn => {
        btn.addEventListener('click', () => {
            idHapus = btn.dataset.id;
            modalHapusDesc.textContent = `Hapus tarif untuk "${btn.dataset.tipe}"? Tindakan ini tidak dapat dibatalkan.`;
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        });
    });

    function tutupModalHapus() {
        modalHapus.classList.add('hidden');
        modalHapus.classList.remove('flex');
        idHapus = null;
    }

    document.getElementById('modalHapusTarifBatal').addEventListener('click', tutupModalHapus);
    modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) tutupModalHapus(); });

    document.getElementById('modalHapusTarifYa').addEventListener('click', async () => {
        if (!idHapus) return;
        const btn = document.getElementById('modalHapusTarifYa');
        btn.disabled = true;
        btn.textContent = 'Menghapus...';
        try {
            const res = await fetch('aksi_kelola_tarif.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'hapus', id: idHapus, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menghapus tarif.');
            showToast('Tarif berhasil dihapus.');
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

    document.getElementById('btnExportTarifKelola')?.addEventListener('click', () => {
        const rows = [['Tipe Kendaraan', 'Tarif per Jam', 'Status']];
        document.querySelectorAll('[data-tarif-row]').forEach(row => {
            rows.push([row.dataset.tipe, row.dataset.tarif, row.dataset.status]);
        });
        downloadCsv('daftar_tarif.csv', rows);
        showToast('Data tarif berhasil diekspor.');
    });
})();
</script>

<?php include 'includes/footer.php'; ?>