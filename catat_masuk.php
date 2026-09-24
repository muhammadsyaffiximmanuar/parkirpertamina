<?php
/**
 * catat_masuk.php
 * Halaman untuk role: Officer & Security
 * Mencatat kendaraan yang masuk ke area parkir, termasuk pendaftaran cepat
 * untuk kendaraan yang belum terdaftar.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OFFICER, ROLE_SECURITY]);

$activePage = 'catat_masuk';
$pageTitle  = 'Catat Kendaraan Masuk';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';
$userId     = $_SESSION['user_id'] ?? 0;

/* ============ RINGKASAN SINGKAT UNTUK PANEL INFO ============ */
$masukHariIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE DATE(waktu_masuk) = CURDATE()",
    [], ['jml' => 18]
)['jml'];

$slotTersediaSaatIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM slot_parkir WHERE status = 'Tersedia'",
    [], ['jml' => 21]
)['jml'];

/* ============ KENDARAAN MASUK TERAKHIR (untuk konteks petugas) ============ */
/* ============ KENDARAAN MASUK TERAKHIR (untuk konteks petugas) ============ */
$masukTerakhir = db_fetch_all(
    "SELECT k.plat_nomor, k.tipe AS tipe_kendaraan, sp.kode_slot, t.waktu_masuk
     FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     JOIN slot_parkir sp ON sp.id = t.slot_id
     WHERE t.status = 'Masuk'
     ORDER BY t.waktu_masuk DESC LIMIT 5",
    [],
    []
);
include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div class="flex items-center gap-md">
            <div class="w-12 h-12 rounded-xl bg-tertiary/15 text-tertiary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">directions_car</span>
            </div>
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-background">Catat Kendaraan Masuk</h2>
                <p class="text-body-lg text-on-surface-variant">Rekam kendaraan yang memasuki area parkir dan cetak tiket.</p>
            </div>
        </div>
        <a href="catat_keluar.php" class="flex items-center gap-2 px-lg py-3 rounded-xl border-2 border-primary text-primary font-bold hover:bg-primary/10 transition-all active:scale-95 shrink-0">
            <span class="material-symbols-outlined">logout</span> Catat Keluar
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg items-start">

        <!-- Form utama -->
        <div class="lg:col-span-2 bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
            <form id="formMasuk" class="space-y-lg" onsubmit="return submitMasuk(event)">

                <div class="space-y-xs">
                    <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Plat Nomor</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-on-surface-variant">directions_car</span>
                        <input id="masukPlatNomor" name="plat_nomor" type="text" required autofocus placeholder="cth: B 1234 ABC"
                               class="w-full pl-[44px] pr-md py-sm bg-white border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-body-lg uppercase tracking-wide">
                    </div>
                    <p class="text-label-md text-on-surface-variant">Kendaraan harus sudah terdaftar di menu Kendaraan.</p>
                </div>

                <label class="flex items-center gap-2 cursor-pointer select-none bg-surface-container-low rounded-lg px-md py-sm border border-outline-variant/40 hover:bg-surface-container transition-colors">
                    <input type="checkbox" id="masukKendaraanBaru" onchange="toggleDaftarCepat()" class="accent-tertiary w-4 h-4">
                    <span class="text-body-md font-medium">Kendaraan baru, belum terdaftar (daftarkan cepat)</span>
                </label>

                <div id="masukDaftarCepat" class="hidden space-y-md bg-surface-container-low rounded-lg p-md border border-outline-variant/40">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                        <div class="space-y-xs">
                            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Tipe Kendaraan</label>
                            <select id="masukTipe" class="w-full px-md py-sm bg-white border border-outline/20 rounded-lg text-body-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                <option value="Mobil">Mobil</option>
                                <option value="Motor">Motor</option>
                                <option value="Bus/Truk">Bus/Truk</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="space-y-xs">
                            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Warna <span class="normal-case text-on-surface-variant">(opsional)</span></label>
                            <input id="masukWarna" type="text" placeholder="cth: Hitam"
                                   class="w-full px-md py-sm bg-white border border-outline/20 rounded-lg text-body-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        </div>
                    </div>
                    <div class="space-y-xs">
                        <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Nama Pemilik</label>
                        <input id="masukNamaPemilik" type="text" placeholder="Nama pemilik kendaraan"
                               class="w-full px-md py-sm bg-white border border-outline/20 rounded-lg text-body-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                    </div>
                    <p class="text-label-md text-on-surface-variant flex items-start gap-1">
                        <span class="material-symbols-outlined text-[16px] mt-[1px]">verified</span>
                        Kendaraan langsung berstatus Terverifikasi karena dicek langsung oleh petugas di lapangan.
                    </p>
                </div>

                <p id="masukError" class="hidden text-error text-body-md bg-error-container/40 border border-error/20 rounded-lg px-md py-sm"></p>
                <p id="masukSukses" class="hidden text-tertiary text-body-md bg-tertiary/10 border border-tertiary/30 rounded-lg px-md py-sm"></p>

                <button type="submit" id="masukSubmitBtn" class="w-full flex items-center justify-center gap-2 bg-tertiary text-on-tertiary font-bold py-md rounded-lg hover:bg-tertiary/90 transition-all active:scale-[0.98]">
                    <span class="material-symbols-outlined">receipt_long</span>
                    Simpan &amp; Cetak Tiket Masuk
                </button>
            </form>
        </div>

        <!-- Panel info -->
        <div class="space-y-lg">
            <div class="bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10 space-y-md">
                <h3 class="font-title-md text-title-md font-bold text-on-surface">Info Cepat</h3>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-on-surface-variant text-body-md">
                        <span class="material-symbols-outlined text-tertiary text-[20px]">login</span> Masuk hari ini
                    </div>
                    <span class="font-bold text-title-md"><?= (int) $masukHariIni ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-on-surface-variant text-body-md">
                        <span class="material-symbols-outlined text-secondary text-[20px]">event_available</span> Slot tersedia
                    </div>
                    <span class="font-bold text-title-md"><?= (int) $slotTersediaSaatIni ?></span>
                </div>
            </div>

            <div class="bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
                <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md">Masuk Terbaru</h3>
                <?php if (empty($masukTerakhir)): ?>
                    <p class="text-body-md text-on-surface-variant">Belum ada kendaraan masuk hari ini.</p>
                <?php else: ?>
                    <ul class="divide-y divide-outline-variant">
                        <?php foreach ($masukTerakhir as $m): ?>
                        <li class="py-sm flex items-center justify-between gap-sm">
                            <div>
                                <p class="font-bold text-body-md"><?= htmlspecialchars($m['plat_nomor']) ?></p>
                                <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($m['tipe_kendaraan']) ?> &middot; Slot <?= htmlspecialchars($m['kode_slot'] ?? '-') ?></p>
                            </div>
                            <span class="text-label-md text-on-surface-variant shrink-0"><?= date('H:i', strtotime($m['waktu_masuk'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main>

<script>
function toggleDaftarCepat() {
    const cek = document.getElementById('masukKendaraanBaru').checked;
    document.getElementById('masukDaftarCepat').classList.toggle('hidden', !cek);
}

async function submitMasuk(e) {
    e.preventDefault();
    const errBox = document.getElementById('masukError');
    const sukBox = document.getElementById('masukSukses');
    const btn = document.getElementById('masukSubmitBtn');
    errBox.classList.add('hidden');
    sukBox.classList.add('hidden');

    const platNomor = document.getElementById('masukPlatNomor').value.trim();
    const kendaraanBaru = document.getElementById('masukKendaraanBaru').checked;

    btn.disabled = true;
    btn.classList.add('opacity-60');

    try {
        // Langkah 1 (opsional): daftarkan kendaraan dulu kalau belum terdaftar
        if (kendaraanBaru) {
            const namaPemilik = document.getElementById('masukNamaPemilik').value.trim();
            if (namaPemilik === '') {
                errBox.textContent = 'Nama pemilik wajib diisi untuk kendaraan baru.';
                errBox.classList.remove('hidden');
                btn.disabled = false; btn.classList.remove('opacity-60');
                return false;
            }
            const resDaftar = await fetch('kendaraan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    plat_nomor: platNomor,
                    tipe: document.getElementById('masukTipe').value,
                    nama_pemilik: namaPemilik,
                    warna: document.getElementById('masukWarna').value.trim(),
                    verifikasi: 'Terverifikasi'
                })
            });
            const dataDaftar = await resDaftar.json();
            if (!dataDaftar.success) {
                errBox.textContent = dataDaftar.message || 'Gagal mendaftarkan kendaraan baru.';
                errBox.classList.remove('hidden');
                btn.disabled = false; btn.classList.remove('opacity-60');
                return false;
            }
        }

        // Langkah 2: catat kendaraan masuk
        const res = await fetch('transaksi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ plat_nomor: platNomor })
        });
        const data = await res.json();
        if (data.success) {
            sukBox.textContent = 'Kendaraan berhasil dicatat masuk. Membuka tiket untuk dicetak...';
            sukBox.classList.remove('hidden');
            window.open('struk.php?id=' + data.id, '_blank');
            setTimeout(() => window.location.reload(), 900);
        } else {
            errBox.textContent = data.message || 'Gagal mencatat kendaraan masuk.';
            errBox.classList.remove('hidden');
        }
    } catch (err) {
        errBox.textContent = 'Tidak dapat terhubung ke server.';
        errBox.classList.remove('hidden');
    }
    btn.disabled = false;
    btn.classList.remove('opacity-60');
    return false;
}
</script>

<?php include 'includes/footer.php'; ?>