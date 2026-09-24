<?php
/**
 * dashboard_admin.php
 * Dashboard untuk role: Admin
 * Fokus: manajemen data — verifikasi kendaraan, tarif, dan area/lantai.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_ADMIN]);

// Token CSRF untuk aksi AJAX (verifikasi/tolak kendaraan, dsb)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$activePage = 'beranda';
$pageTitle  = 'Dashboard Admin';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ KARTU STATISTIK ============ */
$statsRow = db_fetch_one(
    "SELECT * FROM view_dashboard_stats",
    [],
    ['total_area_parkir' => 12, 'kendaraan_terdaftar' => 1240, 'terparkir_saat_ini' => 856, 'slot_tersedia' => 144]
);
$totalKendaraan = (int) $statsRow['kendaraan_terdaftar'];
$slotTersedia   = (int) $statsRow['slot_tersedia'];
$totalArea      = (int) $statsRow['total_area_parkir'];

$jumlahMenunggu = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM kendaraan WHERE status_verifikasi = 'Menunggu Verifikasi'",
    [], ['jml' => 3]
)['jml'];

$jumlahTarifAktif = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM tarif WHERE status = 'Aktif'",
    [], ['jml' => 3]
)['jml'];

/* ============ PENDAPATAN HARI INI ============ */
$pendapatanHariIni = db_fetch_one(
    "SELECT COALESCE(SUM(biaya), 0) AS total FROM transaksi WHERE DATE(created_at) = CURDATE()",
    [], ['total' => 1250000]
)['total'];

/* ============ TREN OKUPANSI 7 HARI TERAKHIR (untuk grafik) ============
 * CATATAN: view_tren_okupansi_mingguan tidak ada di database, dan tidak
 * ada tabel yang mencatat histori okupansi harian. Sebagai gantinya,
 * dipakai proksi: jumlah kendaraan masuk per hari dibagi total slot
 * parkir yang ada, sebagai perkiraan tingkat keramaian harian.
 */
$totalSlotUntukTren = (int) db_fetch_one("SELECT COUNT(*) AS jml FROM slot_parkir", [], ['jml' => 60])['jml'];
$trenOkupansi = db_fetch_all(
    "SELECT DATE(waktu_masuk) AS tanggal,
            ROUND(COUNT(*) / ? * 100, 1) AS rata_okupansi
     FROM transaksi
     WHERE waktu_masuk >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY DATE(waktu_masuk)
     ORDER BY tanggal ASC",
    [$totalSlotUntukTren > 0 ? $totalSlotUntukTren : 1],
    [
        ['tanggal' => date('Y-m-d', strtotime('-6 days')), 'rata_okupansi' => 48.2],
        ['tanggal' => date('Y-m-d', strtotime('-5 days')), 'rata_okupansi' => 52.6],
        ['tanggal' => date('Y-m-d', strtotime('-4 days')), 'rata_okupansi' => 61.0],
        ['tanggal' => date('Y-m-d', strtotime('-3 days')), 'rata_okupansi' => 55.4],
        ['tanggal' => date('Y-m-d', strtotime('-2 days')), 'rata_okupansi' => 58.9],
        ['tanggal' => date('Y-m-d', strtotime('-1 days')), 'rata_okupansi' => 63.1],
        ['tanggal' => date('Y-m-d'), 'rata_okupansi' => 54.2],
    ]
);

/* ============ KENDARAAN MENUNGGU VERIFIKASI ============ */
$kendaraanMenunggu = db_fetch_all(
    "SELECT id, plat_nomor, tipe, nama_pemilik, sumber_registrasi, created_at
     FROM kendaraan WHERE status_verifikasi = 'Menunggu Verifikasi'
     ORDER BY created_at DESC LIMIT 6",
    [],
    [
        ['id' => 1, 'plat_nomor' => 'D 4455 MK', 'tipe' => 'Motor', 'nama_pemilik' => 'Rina Kartika', 'sumber_registrasi' => 'Sistem Online', 'created_at' => date('Y-m-d H:i:s')],
    ]
);

/* ============ DAFTAR TARIF ============ */
$daftarTarif = db_fetch_all(
    "SELECT tipe_kendaraan, deskripsi, tarif_per_jam, status FROM tarif ORDER BY tipe_kendaraan",
    [],
    [
        ['tipe_kendaraan' => 'Motor', 'deskripsi' => 'Motor Roda Dua Standard', 'tarif_per_jam' => 3000, 'status' => 'Aktif'],
        ['tipe_kendaraan' => 'Mobil', 'deskripsi' => 'Mobil Pribadi / Sedan / SUV', 'tarif_per_jam' => 7000, 'status' => 'Aktif'],
    ]
);

/* ============ OKUPANSI PER LANTAI (untuk manajemen area) ============ */
$okupansiLantai = db_fetch_all(
    "SELECT nama_lantai, kapasitas, total_slot_terdata, slot_terisi, okupansi_persen FROM view_okupansi_lantai",
    [],
    [
        ['nama_lantai' => 'Lantai 1', 'kapasitas' => 120, 'total_slot_terdata' => 24, 'slot_terisi' => 15, 'okupansi_persen' => 62.5],
        ['nama_lantai' => 'Lantai 2', 'kapasitas' => 100, 'total_slot_terdata' => 24, 'slot_terisi' => 14, 'okupansi_persen' => 58.3],
        ['nama_lantai' => 'Lantai 3 / Area Terbuka', 'kapasitas' => 80, 'total_slot_terdata' => 12, 'slot_terisi' => 5, 'okupansi_persen' => 41.7],
    ]
);

/* ============ LOG AKTIVITAS TERBARU (audit ringan) ============ */
$logAktivitas = db_fetch_all(
    "SELECT nama_lengkap, aktivitas, kategori, created_at FROM view_log_aktivitas_detail LIMIT 5",
    [], []
);

function waktu_lalu_admin($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}

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
            <h2 class="font-headline-lg text-headline-lg text-on-background">Selamat datang, <?= htmlspecialchars($namaUser) ?></h2>
            <p class="text-body-lg text-on-surface-variant">Kelola data kendaraan, tarif, dan area parkir dari sini.</p>
        </div>
        <div class="flex items-center gap-sm text-label-md text-on-surface-variant">
            <span id="lastUpdatedLabel">Terakhir diperbarui: <span id="lastUpdatedTime"><?= date('H:i:s') ?></span></span>
            <button id="btnRefreshDashboard" type="button" title="Muat ulang data" class="p-1.5 rounded-lg border border-outline-variant/30 hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[18px] align-middle">refresh</span>
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">app_registration</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kendaraan Terdaftar</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($totalKendaraan, 0, ',', '.') ?></h3>
        </div>
        <a href="#kendaraanMenunggu" class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10 hover:border-primary transition-colors">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">pending_actions</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Menunggu Verifikasi</p>
            <h3 class="font-title-md text-title-md font-bold text-primary" id="statMenunggu"><?= (int) $jumlahMenunggu ?></h3>
        </a>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">event_available</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Slot Tersedia</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($slotTersedia, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">sell</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Tarif Aktif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $jumlahTarifAktif ?> / <?= count($daftarTarif) ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">payments</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Pendapatan Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($pendapatanHariIni) ?></h3>
        </div>
    </div>

    <!-- Verifikasi kendaraan + Tarif -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        <div id="kendaraanMenunggu" class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Kendaraan Menunggu Verifikasi</h2>
                <a href="#" class="text-primary font-bold text-body-md hover:underline">Kelola</a>
            </div>
            <?php if (!empty($kendaraanMenunggu)): ?>
            <div class="px-lg pt-md">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                    <input type="text" id="cariKendaraan" placeholder="Cari plat nomor atau nama pemilik..."
                           class="w-full pl-8 pr-3 py-2 text-body-md rounded-lg border border-outline-variant/40 bg-surface-container-lowest focus:outline-none focus:border-primary transition-colors">
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($kendaraanMenunggu)): ?>
                <p class="px-lg py-lg text-body-md text-on-surface-variant" id="kendaraanEmptyState">Tidak ada kendaraan yang menunggu verifikasi saat ini.</p>
            <?php else: ?>
            <div class="divide-y divide-outline-variant" id="daftarKendaraanMenunggu">
                <?php foreach ($kendaraanMenunggu as $k): ?>
                <div class="px-lg py-md flex justify-between items-center gap-md" data-kendaraan-row data-id="<?= (int) $k['id'] ?>"
                     data-search="<?= htmlspecialchars(strtolower($k['plat_nomor'] . ' ' . $k['nama_pemilik'])) ?>">
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($k['plat_nomor']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($k['tipe']) ?> &bull; <?= htmlspecialchars($k['nama_pemilik']) ?> &bull; <?= htmlspecialchars($k['sumber_registrasi']) ?></p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="btn-verifikasi px-sm py-1 rounded-lg bg-tertiary/10 text-tertiary font-bold text-label-md hover:bg-tertiary/20 transition-colors"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>" data-aksi="setujui">Verifikasi</button>
                        <button type="button" class="btn-verifikasi px-sm py-1 rounded-lg bg-error-container/60 text-on-error-container font-bold text-label-md hover:bg-error-container transition-colors"
                                data-id="<?= (int) $k['id'] ?>" data-plat="<?= htmlspecialchars($k['plat_nomor']) ?>" data-aksi="tolak">Tolak</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="px-lg py-lg text-body-md text-on-surface-variant hidden" id="kendaraanNoResult">Tidak ada hasil yang cocok dengan pencarian.</p>
            <?php endif; ?>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center gap-sm">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar Tarif</h2>
                <div class="flex items-center gap-md shrink-0">
                    <button type="button" id="btnTambahTarif" class="text-primary font-bold text-body-md hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">add</span> Tambah Tarif
                    </button>
                    <button type="button" id="btnExportTarif" class="text-on-surface-variant font-bold text-body-md hover:text-on-surface flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">download</span> Ekspor
                    </button>
                </div>
            </div>
            <?php if (empty($daftarTarif)): ?>
                <p class="px-lg py-lg text-body-md text-on-surface-variant" id="tarifEmptyState">Belum ada data tarif.</p>
            <?php endif; ?>
            <div class="divide-y divide-outline-variant" id="tabelDaftarTarif">
                <?php foreach ($daftarTarif as $t): ?>
                <div class="px-lg py-md flex justify-between items-center gap-md"
                     data-tipe="<?= htmlspecialchars($t['tipe_kendaraan']) ?>" data-tarif="<?= (int) $t['tarif_per_jam'] ?>" data-status="<?= htmlspecialchars($t['status']) ?>">
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($t['tipe_kendaraan']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($t['deskripsi']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-body-md font-bold"><?= format_rupiah($t['tarif_per_jam']) ?>/jam</p>
                        <span class="text-label-md <?= $t['status'] === 'Aktif' ? 'text-tertiary' : 'text-outline' ?>"><?= htmlspecialchars($t['status']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Okupansi lantai + Log aktivitas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Okupansi per Lantai</h2>
            </div>
            <div class="px-lg pt-md">
                <p class="text-label-md text-on-surface-variant mb-1">Tren rata-rata okupansi 7 hari terakhir</p>
                <canvas id="chartTrenOkupansi" height="90"
                        data-tren='<?= htmlspecialchars(json_encode($trenOkupansi), ENT_QUOTES) ?>'></canvas>
            </div>
            <div class="divide-y divide-outline-variant">
                <?php foreach ($okupansiLantai as $l):
                    $persen = (float) $l['okupansi_persen'];
                    $badge = $okupansiBadge($persen);
                ?>
                <div class="px-lg py-md">
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-body-md font-medium"><?= htmlspecialchars($l['nama_lantai']) ?></p>
                        <span class="px-2 py-0.5 rounded-full text-label-md <?= $badge['class'] ?>"><?= $badge['label'] ?> (<?= $persen ?>%)</span>
                    </div>
                    <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                        <div class="h-full bg-primary" style="width: <?= min($persen, 100) ?>%"></div>
                    </div>
                    <p class="text-label-md text-on-surface-variant mt-1"><?= (int) $l['slot_terisi'] ?> / <?= (int) $l['total_slot_terdata'] ?> slot terisi</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center gap-sm">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Aktivitas Terbaru</h2>
                <div class="flex items-center gap-md shrink-0">
                    <?php if (!empty($logAktivitas)): ?>
                    <button type="button" id="btnPrintLog" class="text-on-surface-variant font-bold text-body-md hover:text-on-surface flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">print</span> Cetak Laporan
                    </button>
                    <button type="button" id="btnExportLog" class="text-on-surface-variant font-bold text-body-md hover:text-on-surface flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">download</span> Ekspor CSV
                    </button>
                    <?php endif; ?>
                    <a href="aktivitas.php" class="text-primary font-bold text-body-md hover:underline">Lihat Semua</a>
                </div>
            </div>
            <?php if (empty($logAktivitas)): ?>
                <p class="px-lg py-lg text-body-md text-on-surface-variant">Belum ada aktivitas tercatat.</p>
            <?php else: ?>
            <?php $kategoriUnik = array_values(array_unique(array_map(fn($l) => $l['kategori'] ?? 'Lainnya', $logAktivitas))); ?>
            <div class="px-lg pt-md flex flex-wrap gap-1" id="filterKategoriLog">
                <button type="button" class="chip-kategori px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary" data-kategori="semua">Semua</button>
                <?php foreach ($kategoriUnik as $kat): ?>
                <button type="button" class="chip-kategori px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant" data-kategori="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="divide-y divide-outline-variant" id="daftarLogAktivitas">
                <?php foreach ($logAktivitas as $log): ?>
                <div class="px-lg py-md" data-kategori-item="<?= htmlspecialchars($log['kategori'] ?? 'Lainnya') ?>">
                    <p class="text-body-md font-medium"><?= htmlspecialchars($log['nama_lengkap']) ?> <span class="font-normal text-on-surface-variant">&mdash; <?= htmlspecialchars($log['aktivitas']) ?></span></p>
                    <p class="text-label-md text-on-surface-variant"><?= waktu_lalu_admin($log['created_at']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<!-- Modal konfirmasi verifikasi / tolak kendaraan -->
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

<!-- Modal Tambah Tarif -->
<div id="modalTambahTarif" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-soft w-full max-w-md p-lg">
        <h3 class="font-title-md text-title-md font-bold text-on-surface mb-md">Tambah Tarif</h3>
        <form id="formTambahTarif" class="space-y-sm">
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="inputTipeKendaraan">Tipe Kendaraan</label>
                <input type="text" id="inputTipeKendaraan" required maxlength="50" placeholder="Contoh: Motor, Mobil, Truk"
                       class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="inputDeskripsiTarif">Deskripsi</label>
                <input type="text" id="inputDeskripsiTarif" maxlength="150" placeholder="Contoh: Motor Roda Dua Standard"
                       class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
            </div>
            <div class="grid grid-cols-2 gap-sm">
                <div>
                    <label class="text-label-md text-on-surface-variant mb-1 block" for="inputTarifPerJam">Tarif per Jam (Rp)</label>
                    <input type="number" id="inputTarifPerJam" required min="1" step="1" placeholder="3000"
                           class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="text-label-md text-on-surface-variant mb-1 block" for="inputTarifMaksHarian">Tarif Maks Harian (Rp)</label>
                    <input type="number" id="inputTarifMaksHarian" min="0" step="1" placeholder="30000"
                           class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                </div>
            </div>
            <div>
                <label class="text-label-md text-on-surface-variant mb-1 block" for="inputStatusTarif">Status</label>
                <select id="inputStatusTarif" class="w-full px-sm py-2 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <option value="Aktif" selected>Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>
            <p id="tambahTarifError" class="text-label-md text-error hidden"></p>
        </form>
        <div class="flex justify-end gap-sm mt-md">
            <button type="button" id="modalTambahTarifBatal" class="px-md py-2 rounded-lg text-body-md font-bold text-on-surface-variant hover:bg-surface-container-high transition-colors">Batal</button>
            <button type="button" id="modalTambahTarifSimpan" class="px-md py-2 rounded-lg text-body-md font-bold bg-primary text-on-primary hover:opacity-90 transition-opacity">Simpan</button>
        </div>
    </div>
</div>

<!-- Toast notifikasi -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;

    /* ---------- Toast ---------- */
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

    /* ---------- Refresh timestamp ---------- */
    document.getElementById('btnRefreshDashboard')?.addEventListener('click', () => {
        document.getElementById('btnRefreshDashboard').querySelector('.material-symbols-outlined').classList.add('animate-spin');
        window.location.reload();
    });

    /* ---------- Pencarian kendaraan menunggu verifikasi ---------- */
    const inputCari = document.getElementById('cariKendaraan');
    if (inputCari) {
        inputCari.addEventListener('input', () => {
            const q = inputCari.value.trim().toLowerCase();
            const rows = document.querySelectorAll('[data-kendaraan-row]');
            let visibleCount = 0;
            rows.forEach(row => {
                const match = row.dataset.search.includes(q);
                row.classList.toggle('hidden', !match);
                if (match) visibleCount++;
            });
            document.getElementById('kendaraanNoResult')?.classList.toggle('hidden', visibleCount !== 0);
        });
    }

    /* ---------- Verifikasi / Tolak kendaraan (AJAX) ---------- */
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
                body: JSON.stringify({
                    id, aksi, alasan: modalAlasan.value || null, csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memproses permintaan.');

            const row = document.querySelector(`[data-kendaraan-row][data-id="${id}"]`);
            row?.remove();
            const statEl = document.getElementById('statMenunggu');
            if (statEl) statEl.textContent = Math.max(0, (parseInt(statEl.textContent, 10) || 1) - 1);
            if (!document.querySelectorAll('[data-kendaraan-row]').length) {
                document.getElementById('kendaraanEmptyState')?.classList.remove('hidden');
                document.getElementById('daftarKendaraanMenunggu')?.remove();
            }
            showToast(aksi === 'setujui' ? 'Kendaraan berhasil diverifikasi.' : 'Pendaftaran kendaraan ditolak.', 'success');
            closeModal();
        } catch (err) {
            showToast(err.message || 'Terjadi kesalahan, coba lagi.', 'error');
        } finally {
            modalKonfirmasi.disabled = false;
            modalKonfirmasi.textContent = 'Konfirmasi';
        }
    });

    /* ---------- Tambah Tarif (AJAX) ---------- */
    const modalTambahTarif = document.getElementById('modalTambahTarif');
    const formTambahTarif = document.getElementById('formTambahTarif');
    const tambahTarifError = document.getElementById('tambahTarifError');
    const modalTambahTarifSimpan = document.getElementById('modalTambahTarifSimpan');

    function openModalTambahTarif() {
        formTambahTarif.reset();
        tambahTarifError.classList.add('hidden');
        tambahTarifError.textContent = '';
        modalTambahTarif.classList.remove('hidden');
        modalTambahTarif.classList.add('flex');
    }

    function closeModalTambahTarif() {
        modalTambahTarif.classList.add('hidden');
        modalTambahTarif.classList.remove('flex');
    }

    document.getElementById('btnTambahTarif')?.addEventListener('click', openModalTambahTarif);
    document.getElementById('modalTambahTarifBatal').addEventListener('click', closeModalTambahTarif);
    modalTambahTarif.addEventListener('click', (e) => { if (e.target === modalTambahTarif) closeModalTambahTarif(); });

    function formatRupiahJs(angka) {
        return 'Rp' + Number(angka).toLocaleString('id-ID');
    }

    async function simpanTarifBaru() {
        const tipe = document.getElementById('inputTipeKendaraan').value.trim();
        const deskripsi = document.getElementById('inputDeskripsiTarif').value.trim();
        const perJam = parseInt(document.getElementById('inputTarifPerJam').value, 10) || 0;
        const maksHarian = parseInt(document.getElementById('inputTarifMaksHarian').value, 10) || 0;
        const status = document.getElementById('inputStatusTarif').value;

        tambahTarifError.classList.add('hidden');

        if (!tipe || perJam <= 0) {
            tambahTarifError.textContent = 'Tipe kendaraan dan tarif per jam (harus lebih dari 0) wajib diisi.';
            tambahTarifError.classList.remove('hidden');
            return;
        }

        modalTambahTarifSimpan.disabled = true;
        modalTambahTarifSimpan.textContent = 'Menyimpan...';
        try {
            const res = await fetch('aksi_kelola_tarif.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    aksi: 'tambah',
                    tipe_kendaraan: tipe,
                    deskripsi: deskripsi,
                    tarif_per_jam: perJam,
                    tarif_maks_harian: maksHarian,
                    status: status,
                    csrf_token: csrfToken,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menambahkan tarif.');

            document.getElementById('tarifEmptyState')?.remove();
            const tabel = document.getElementById('tabelDaftarTarif');
            const row = document.createElement('div');
            row.className = 'px-lg py-md flex justify-between items-center gap-md';
            row.dataset.tipe = tipe;
            row.dataset.tarif = perJam;
            row.dataset.status = status;
            row.innerHTML = `
                <div>
                    <p class="text-body-md font-bold text-on-surface"></p>
                    <p class="text-label-md text-on-surface-variant"></p>
                </div>
                <div class="text-right">
                    <p class="text-body-md font-bold">${formatRupiahJs(perJam)}/jam</p>
                    <span class="text-label-md ${status === 'Aktif' ? 'text-tertiary' : 'text-outline'}"></span>
                </div>`;
            row.querySelector('.text-on-surface').textContent = tipe;
            row.querySelector('.text-on-surface-variant').textContent = deskripsi;
            row.querySelector('span').textContent = status;
            tabel.appendChild(row);

            showToast(data.message || 'Tarif berhasil ditambahkan.', 'success');
            closeModalTambahTarif();
        } catch (err) {
            tambahTarifError.textContent = err.message || 'Terjadi kesalahan, coba lagi.';
            tambahTarifError.classList.remove('hidden');
        } finally {
            modalTambahTarifSimpan.disabled = false;
            modalTambahTarifSimpan.textContent = 'Simpan';
        }
    }

    modalTambahTarifSimpan.addEventListener('click', simpanTarifBaru);
    formTambahTarif.addEventListener('submit', (e) => { e.preventDefault(); simpanTarifBaru(); });

    /* ---------- Filter kategori log aktivitas ---------- */
    document.querySelectorAll('.chip-kategori').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.chip-kategori').forEach(c => c.className = 'chip-kategori px-sm py-0.5 rounded-full text-label-md bg-surface-container-high text-on-surface-variant');
            chip.className = 'chip-kategori px-sm py-0.5 rounded-full text-label-md bg-primary text-on-primary';
            const kategori = chip.dataset.kategori;
            document.querySelectorAll('[data-kategori-item]').forEach(item => {
                item.classList.toggle('hidden', kategori !== 'semua' && item.dataset.kategoriItem !== kategori);
            });
        });
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

    document.getElementById('btnExportTarif')?.addEventListener('click', () => {
        const rows = [['Tipe Kendaraan', 'Tarif per Jam', 'Status']];
        document.querySelectorAll('#tabelDaftarTarif > div').forEach(div => {
            rows.push([div.dataset.tipe, div.dataset.tarif, div.dataset.status]);
        });
        downloadCsv('daftar_tarif.csv', rows);
        showToast('Data tarif berhasil diekspor.');
    });

    document.getElementById('btnExportLog')?.addEventListener('click', () => {
        const rows = [['Nama', 'Aktivitas', 'Kategori']];
        document.querySelectorAll('#daftarLogAktivitas > div').forEach(div => {
            const nama = div.querySelector('p.font-medium')?.childNodes[0]?.textContent.trim() ?? '';
            const aktivitas = div.querySelector('span')?.textContent.replace('—', '').trim() ?? '';
            rows.push([nama, aktivitas, div.dataset.kategoriItem]);
        });
        downloadCsv('log_aktivitas.csv', rows);
        showToast('Log aktivitas berhasil diekspor.');
    });

    /* ---------- Cetak Laporan (log aktivitas) ---------- */
    document.getElementById('btnPrintLog')?.addEventListener('click', () => {
        const baris = [];
        document.querySelectorAll('#daftarLogAktivitas > div').forEach(div => {
            const nama = div.querySelector('p.font-medium')?.childNodes[0]?.textContent.trim() ?? '';
            const aktivitas = div.querySelector('span')?.textContent.replace('—', '').trim() ?? '';
            const waktu = div.querySelector('p.text-label-md')?.textContent.trim() ?? '';
            baris.push({ nama, aktivitas, waktu, kategori: div.dataset.kategoriItem || '-' });
        });

        if (!baris.length) {
            showToast('Tidak ada data aktivitas untuk dicetak.', 'error');
            return;
        }

        const tanggalCetak = new Date().toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' });
        const isiTabel = baris.map(r => `
            <tr>
                <td>${r.nama}</td>
                <td>${r.aktivitas}</td>
                <td>${r.kategori}</td>
                <td>${r.waktu}</td>
            </tr>`).join('');

        const jendelaCetak = window.open('', '_blank', 'width=900,height=700');
        if (!jendelaCetak) {
            showToast('Popup diblokir browser. Izinkan popup untuk mencetak laporan.', 'error');
            return;
        }

        jendelaCetak.document.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Laporan Aktivitas - Parkir Gedung Pertamina</title>
                <style>
                    * { box-sizing: border-box; }
                    body { font-family: Arial, Helvetica, sans-serif; padding: 32px; color: #1a1a1a; }
                    h1 { font-size: 18px; margin: 0 0 4px; }
                    p.subtitle { color: #666; margin: 0 0 24px; font-size: 13px; }
                    table { width: 100%; border-collapse: collapse; font-size: 13px; }
                    th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
                    th { background: #f2f2f2; }
                    tr:nth-child(even) { background: #fafafa; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <h1>Laporan Aktivitas &mdash; Parkir Gedung Pertamina</h1>
                <p class="subtitle">Dicetak pada ${tanggalCetak}</p>
                <table>
                    <thead><tr><th>Nama</th><th>Aktivitas</th><th>Kategori</th><th>Waktu</th></tr></thead>
                    <tbody>${isiTabel}</tbody>
                </table>
            </body>
            </html>
        `);
        jendelaCetak.document.close();
        jendelaCetak.focus();
        jendelaCetak.onload = () => jendelaCetak.print();
    });

    /* ---------- Grafik tren okupansi mingguan ---------- */
    const canvas = document.getElementById('chartTrenOkupansi');
    if (canvas && window.Chart) {
        const tren = JSON.parse(canvas.dataset.tren || '[]');
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: tren.map(t => new Date(t.tanggal).toLocaleDateString('id-ID', { weekday: 'short' })),
                datasets: [{
                    label: 'Rata-rata Okupansi (%)',
                    data: tren.map(t => t.rata_okupansi),
                    borderColor: '#6750A4',
                    backgroundColor: 'rgba(103,80,164,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
                maintainAspectRatio: false,
            },
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>