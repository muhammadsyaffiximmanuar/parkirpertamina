<?php
/**
 * kelola_area.php
 * Dashboard Admin — Kelola Area
 * Fokus: manajemen data area/lantai parkir beserta kapasitasnya.
 * Menggunakan tabel `lantai` (bukan `area_parkir` yang tidak ada di DB).
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OWNER, ROLE_SUPER_ADMIN, ROLE_ADMIN]);

expire_booking_lewat_waktu(); // bebaskan slot dari booking pelanggan yang sudah lewat waktu & tidak check-in

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$activePage = 'kelola_area';
$pageTitle  = 'Kelola Area';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ DAFTAR AREA / LANTAI ============ */
// "Slot Terisi" dihitung dari tabel `slot_parkir` (id, area_id, kode_slot,
// status ENUM('Tersedia','Terisi','Dipesan')): jumlah slot di area ini
// yang statusnya bukan 'Tersedia'.
$daftarArea = db_fetch_all(
    "SELECT l.id, l.nama_lantai, l.gedung, l.kapasitas, l.keterangan, l.status,
            (SELECT COUNT(*) FROM slot_parkir sp
             WHERE sp.area_id = l.id AND sp.status != 'Tersedia') AS slot_terisi
     FROM lantai l ORDER BY l.nama_lantai ASC",
    [],
    [
        ['id' => 1, 'nama_lantai' => 'Lantai 1', 'gedung' => '', 'kapasitas' => 120, 'keterangan' => 'Area utama dekat lobi', 'status' => 'Aktif', 'slot_terisi' => 0],
        ['id' => 2, 'nama_lantai' => 'Lantai 2', 'gedung' => '', 'kapasitas' => 100, 'keterangan' => 'Area indoor', 'status' => 'Aktif', 'slot_terisi' => 0],
        ['id' => 3, 'nama_lantai' => 'Lantai 3 / Area Terbuka', 'gedung' => '', 'kapasitas' => 80, 'keterangan' => 'Area terbuka, rawan hujan', 'status' => 'Aktif', 'slot_terisi' => 0],
    ]
);

$totalArea      = count($daftarArea);
$totalKapasitas = array_sum(array_column($daftarArea, 'kapasitas'));
$totalTerisi    = array_sum(array_column($daftarArea, 'slot_terisi'));
$totalNonaktif  = count(array_filter($daftarArea, fn($a) => $a['status'] !== 'Aktif'));

$okupansiBadge = function ($persen) {
    if ($persen >= 85) return ['label' => 'Padat', 'class' => 'bg-error-container text-on-error-container'];
    if ($persen <= 30) return ['label' => 'Lengang', 'class' => 'bg-secondary-fixed text-on-secondary-fixed-variant'];
    return ['label' => 'Normal', 'class' => 'bg-tertiary-fixed text-on-tertiary-fixed'];
};

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-sm">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Kelola Area</h2>
            <p class="text-body-lg text-on-surface-variant">Kelola data area/lantai parkir dan kapasitasnya.</p>
        </div>
        <button type="button" id="btnTambahArea" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">add_location_alt</span> Tambah Area
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">layers</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Area</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalArea ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">local_parking</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Kapasitas</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($totalKapasitas, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">directions_car</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Slot Terisi</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($totalTerisi, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-error/10 rounded-lg text-error w-fit mb-base"><span class="material-symbols-outlined">block</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Nonaktif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalNonaktif ?></h3>
        </div>
    </div>

    <!-- Daftar Area -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar Area / Lantai</h2>
        </div>
        <?php if (empty($daftarArea)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Belum ada area parkir yang terdaftar.</p>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-md p-lg" id="daftarArea">
            <?php foreach ($daftarArea as $a):
                $persen = $a['kapasitas'] > 0 ? round(($a['slot_terisi'] / $a['kapasitas']) * 100, 1) : 0;
                $badge = $okupansiBadge($persen);
            ?>
            <div class="rounded-xl border border-outline-variant/30 p-md" data-area-row data-id="<?= (int) $a['id'] ?>">
                <div class="flex justify-between items-start gap-sm mb-sm">
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($a['nama_lantai']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($a['keterangan'] ?: '-') ?></p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="px-2 py-0.5 rounded-full text-label-md <?= $a['status'] === 'Aktif' ? 'bg-tertiary-fixed text-on-tertiary-fixed-variant' : 'bg-error-container text-on-error-container' ?>"><?= htmlspecialchars($a['status']) ?></span>
                        <button type="button" class="btn-edit-area p-1.5 rounded-lg hover:bg-surface-container-high transition-colors" title="Ubah"
                                data-id="<?= (int) $a['id'] ?>" data-nama="<?= htmlspecialchars($a['nama_lantai']) ?>"
                                data-kapasitas="<?= (int) $a['kapasitas'] ?>" data-keterangan="<?= htmlspecialchars($a['keterangan']) ?>" data-status="<?= htmlspecialchars($a['status']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">edit</span>
                        </button>
                        <button type="button" class="btn-hapus-area p-1.5 rounded-lg hover:bg-error-container/60 transition-colors" title="Hapus"
                                data-id="<?= (int) $a['id'] ?>" data-nama="<?= htmlspecialchars($a['nama_lantai']) ?>">
                            <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                        </button>
                    </div>
                </div>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-label-md text-on-surface-variant"><?= (int) $a['slot_terisi'] ?> / <?= (int) $a['kapasitas'] ?> slot terisi</span>
                    <span class="px-2 py-0.5 rounded-full text-label-md <?= $badge['class'] ?>"><?= $badge['label'] ?> (<?= $persen ?>%)</span>
                </div>
                <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                    <div class="h-full bg-primary" style="width: <?= min($persen, 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- Modal Tambah / Ubah Area -->
<div id="modalFormArea" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md" id="modalFormAreaTitle">Tambah Area</h3>
        <form id="formArea" class="space-y-sm">
            <input type="hidden" id="areaId" value="">
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="areaNama">Nama Lantai / Area</label>
                <input type="text" id="areaNama" required placeholder="Contoh: Lantai 4" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="areaKapasitas">Kapasitas (jumlah slot)</label>
                <input type="number" id="areaKapasitas" min="1" required class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="areaKeterangan">Keterangan</label>
                <textarea id="areaKeterangan" rows="2" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary" placeholder="Contoh: Area indoor, dekat lift"></textarea>
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="areaStatus">Status</label>
                <select id="areaStatus" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>
        </form>
        <div class="flex justify-end gap-sm mt-md">
            <button type="button" id="modalFormAreaBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalFormAreaSimpan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="modalHapusArea" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-1">Hapus Area</h3>
        <p class="text-body-md text-on-surface-variant mb-md" id="modalHapusAreaDesc"></p>
        <div class="flex justify-end gap-sm">
            <button type="button" id="modalHapusAreaBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalHapusAreaYa" class="px-md py-2 rounded-lg text-body-md font-bold bg-error text-on-error hover:opacity-90 transition-opacity">Ya, Hapus</button>
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

    /* ---------- Modal form tambah/ubah ---------- */
    const modalForm = document.getElementById('modalFormArea');
    const modalFormTitle = document.getElementById('modalFormAreaTitle');
    const inputId = document.getElementById('areaId');
    const inputNama = document.getElementById('areaNama');
    const inputKapasitas = document.getElementById('areaKapasitas');
    const inputKeterangan = document.getElementById('areaKeterangan');
    const inputStatus = document.getElementById('areaStatus');

    function bukaModalForm(mode, data = {}) {
        inputId.value = data.id || '';
        inputNama.value = data.nama || '';
        inputKapasitas.value = data.kapasitas || '';
        inputKeterangan.value = data.keterangan || '';
        inputStatus.value = data.status || 'Aktif';
        modalFormTitle.textContent = mode === 'tambah' ? 'Tambah Area' : 'Ubah Area';
        modalForm.classList.remove('hidden');
        modalForm.classList.add('flex');
    }

    function tutupModalForm() {
        modalForm.classList.add('hidden');
        modalForm.classList.remove('flex');
    }

    document.getElementById('btnTambahArea').addEventListener('click', () => bukaModalForm('tambah'));
    document.getElementById('modalFormAreaBatal').addEventListener('click', tutupModalForm);
    modalForm.addEventListener('click', (e) => { if (e.target === modalForm) tutupModalForm(); });

    document.querySelectorAll('.btn-edit-area').forEach(btn => {
        btn.addEventListener('click', () => bukaModalForm('ubah', {
            id: btn.dataset.id, nama: btn.dataset.nama, kapasitas: btn.dataset.kapasitas,
            keterangan: btn.dataset.keterangan, status: btn.dataset.status,
        }));
    });

    document.getElementById('modalFormAreaSimpan').addEventListener('click', async () => {
        if (!inputNama.value.trim() || !inputKapasitas.value) {
            showToast('Nama area dan kapasitas wajib diisi.', 'error');
            return;
        }
        const aksi = inputId.value ? 'ubah' : 'tambah';
        const btn = document.getElementById('modalFormAreaSimpan');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        try {
            const res = await fetch('aksi_kelola_area.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    aksi, id: inputId.value || null,
                    nama_lantai: inputNama.value.trim(), kapasitas: parseInt(inputKapasitas.value, 10),
                    keterangan: inputKeterangan.value.trim(), status: inputStatus.value, csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan data area.');
            showToast(aksi === 'tambah' ? 'Area baru berhasil ditambahkan.' : 'Data area berhasil diperbarui.');
            tutupModalForm();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    });

    /* ---------- Hapus area ---------- */
    const modalHapus = document.getElementById('modalHapusArea');
    const modalHapusDesc = document.getElementById('modalHapusAreaDesc');
    let idHapus = null;

    document.querySelectorAll('.btn-hapus-area').forEach(btn => {
        btn.addEventListener('click', () => {
            idHapus = btn.dataset.id;
            modalHapusDesc.textContent = `Hapus area "${btn.dataset.nama}"? Slot dan riwayat terkait area ini bisa terpengaruh.`;
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        });
    });

    function tutupModalHapus() {
        modalHapus.classList.add('hidden');
        modalHapus.classList.remove('flex');
        idHapus = null;
    }

    document.getElementById('modalHapusAreaBatal').addEventListener('click', tutupModalHapus);
    modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) tutupModalHapus(); });

    document.getElementById('modalHapusAreaYa').addEventListener('click', async () => {
        if (!idHapus) return;
        const btn = document.getElementById('modalHapusAreaYa');
        btn.disabled = true;
        btn.textContent = 'Menghapus...';
        try {
            const res = await fetch('aksi_kelola_area.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'hapus', id: idHapus, csrf_token: csrfToken }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menghapus area.');
            showToast('Area berhasil dihapus.');
            tutupModalHapus();
            setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Ya, Hapus';
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>