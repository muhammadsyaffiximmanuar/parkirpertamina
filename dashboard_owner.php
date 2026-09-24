<?php
/**
 * dashboard_owner.php
 * Dashboard untuk role: Owner & Super Admin
 * Fokus: ringkasan bisnis, pendapatan, performa area, dan audit aktivitas staf.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OWNER, ROLE_SUPER_ADMIN]);

$activePage = 'beranda';
$pageTitle  = 'Dashboard Owner';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';

/* ============ KARTU STATISTIK UTAMA ============ */
$statsRow = db_fetch_one(
    "SELECT * FROM view_dashboard_stats",
    [],
    ['total_area_parkir' => 12, 'kendaraan_terdaftar' => 1240, 'terparkir_saat_ini' => 856,
     'slot_tersedia' => 144, 'pendapatan_hari_ini' => 8200000, 'pengguna_aktif' => 42]
);
$totalArea      = (int) $statsRow['total_area_parkir'];
$totalKendaraan = (int) $statsRow['kendaraan_terdaftar'];
$slotTerisi     = (int) $statsRow['terparkir_saat_ini'];
$slotTersedia   = (int) $statsRow['slot_tersedia'];
$slotTotal      = $slotTerisi + $slotTersedia;
$persenOkupansi = $slotTotal > 0 ? round(($slotTerisi / $slotTotal) * 100) : 0;
$pendapatanHariIni = (float) $statsRow['pendapatan_hari_ini'];
$penggunaAktif     = (int) $statsRow['pengguna_aktif'];

$totalPendapatanKeseluruhan = db_fetch_one(
    "SELECT IFNULL(SUM(biaya),0) AS jumlah FROM transaksi",
    [], ['jumlah' => 482500000]
)['jumlah'];

/* ============ TREN PENDAPATAN MINGGUAN ============ */
$chartMingguan = db_fetch_all(
    "SELECT hari_label, jumlah FROM view_pendapatan_harian
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     ORDER BY tanggal ASC LIMIT 7",
    [],
    [
        ['hari_label' => 'Sen', 'jumlah' => 3800000], ['hari_label' => 'Sel', 'jumlah' => 5600000],
        ['hari_label' => 'Rab', 'jumlah' => 5100000], ['hari_label' => 'Kam', 'jumlah' => 7900000],
        ['hari_label' => 'Jum', 'jumlah' => 8200000], ['hari_label' => 'Sab', 'jumlah' => 2800000],
        ['hari_label' => 'Min', 'jumlah' => 2300000],
    ]
);
$maxChart = !empty($chartMingguan) ? max(array_column($chartMingguan, 'jumlah')) : 1;
if ($maxChart <= 0) $maxChart = 1;

/* ============ REKAP TRANSAKSI HARI INI ============ */
$rekapTransaksiHariIni = db_fetch_one(
    "SELECT
        SUM(CASE WHEN DATE(waktu_masuk) = CURDATE() THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN status = 'Keluar' AND DATE(waktu_keluar) = CURDATE() THEN 1 ELSE 0 END) AS keluar
     FROM transaksi",
    [], ['masuk' => 18, 'keluar' => 14]
);
$transaksiMasukHariIni  = (int) ($rekapTransaksiHariIni['masuk'] ?? 0);
$transaksiKeluarHariIni = (int) ($rekapTransaksiHariIni['keluar'] ?? 0);

/* ============ TREN KENDARAAN MASUK MINGGUAN (grafik & dasar hitung hari paling ramai) ============ */
$chartKendaraanMingguan = db_fetch_all(
    "SELECT DATE(waktu_masuk) AS tanggal,
            ELT(WEEKDAY(waktu_masuk) + 1, 'Sen','Sel','Rab','Kam','Jum','Sab','Min') AS hari_label,
            COUNT(*) AS jumlah
     FROM transaksi
     WHERE waktu_masuk >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY DATE(waktu_masuk)
     ORDER BY tanggal ASC",
    [],
    [
        ['tanggal' => date('Y-m-d', strtotime('-6 days')), 'hari_label' => 'Sen', 'jumlah' => 32],
        ['tanggal' => date('Y-m-d', strtotime('-5 days')), 'hari_label' => 'Sel', 'jumlah' => 41],
        ['tanggal' => date('Y-m-d', strtotime('-4 days')), 'hari_label' => 'Rab', 'jumlah' => 38],
        ['tanggal' => date('Y-m-d', strtotime('-3 days')), 'hari_label' => 'Kam', 'jumlah' => 55],
        ['tanggal' => date('Y-m-d', strtotime('-2 days')), 'hari_label' => 'Jum', 'jumlah' => 63],
        ['tanggal' => date('Y-m-d', strtotime('-1 days')), 'hari_label' => 'Sab', 'jumlah' => 24],
        ['tanggal' => date('Y-m-d'),                        'hari_label' => 'Min', 'jumlah' => 19],
    ]
);
$maxChartKendaraan = !empty($chartKendaraanMingguan) ? max(array_column($chartKendaraanMingguan, 'jumlah')) : 1;
if ($maxChartKendaraan <= 0) $maxChartKendaraan = 1;

$hariRamai = null;
foreach ($chartKendaraanMingguan as $ck) {
    if ($hariRamai === null || $ck['jumlah'] > $hariRamai['jumlah']) {
        $hariRamai = $ck;
    }
}

/* ============ PERFORMA PER AREA (untuk keputusan bisnis) ============ */
$areaRingkasan = db_fetch_all(
    "SELECT nama_area, total_slot, slot_terisi, status, pendapatan FROM view_area_ringkasan ORDER BY pendapatan DESC LIMIT 5",
    [],
    [
        ['nama_area' => 'Lantai B2 - Area Umum', 'total_slot' => 350, 'slot_terisi' => 263, 'status' => 'Normal', 'pendapatan' => 82900000],
        ['nama_area' => 'Lantai B1 - Area A (Direksi)', 'total_slot' => 150, 'slot_terisi' => 138, 'status' => 'Padat', 'pendapatan' => 45200000],
        ['nama_area' => 'Lantai P1 - Sepeda Motor', 'total_slot' => 800, 'slot_terisi' => 512, 'status' => 'Normal', 'pendapatan' => 12500000],
    ]
);

/* ============ OKUPANSI PER LANTAI ============ */
$okupansiLantai = db_fetch_all(
    "SELECT nama_lantai, kapasitas, total_slot_terdata, slot_terisi, okupansi_persen FROM view_okupansi_lantai",
    [],
    [
        ['nama_lantai' => 'Lantai 1', 'kapasitas' => 120, 'total_slot_terdata' => 24, 'slot_terisi' => 15, 'okupansi_persen' => 62.5],
        ['nama_lantai' => 'Lantai 2', 'kapasitas' => 100, 'total_slot_terdata' => 24, 'slot_terisi' => 14, 'okupansi_persen' => 58.3],
    ]
);

/* ============ DISTRIBUSI PENGGUNA PER ROLE ============ */
$penggunaPerRole = db_fetch_all(
    "SELECT r.nama_role, COUNT(*) AS jumlah
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.status = 'Aktif'
     GROUP BY r.nama_role
     ORDER BY jumlah DESC",
    [],
    [
        ['nama_role' => 'Admin', 'jumlah' => 4], ['nama_role' => 'Officer', 'jumlah' => 3],
        ['nama_role' => 'Security', 'jumlah' => 2], ['nama_role' => 'Owner', 'jumlah' => 1],
    ]
);

/* ============ AUDIT: AKTIVITAS STAF TERBARU ============ */
$logAktivitasTerbaru = db_fetch_all(
    "SELECT nama_lengkap, aktivitas, kategori, created_at FROM view_log_aktivitas_detail LIMIT 6",
    [],
    [
        ['nama_lengkap' => 'Dedi Kusuma', 'aktivitas' => 'Update Tarif Parkir VIP', 'kategori' => 'Perubahan Tarif', 'created_at' => date('Y-m-d H:i:s')],
    ]
);

function waktu_lalu_owner($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}

$kategoriBadge = [
    'Login/Logout'         => 'bg-secondary/10 text-secondary',
    'Manajemen Kendaraan'  => 'bg-primary/10 text-primary',
    'Perubahan Tarif'      => 'bg-tertiary/10 text-tertiary',
    'Laporan'               => 'bg-secondary/10 text-secondary',
    'Lainnya'               => 'bg-outline/10 text-outline',
];
$statusBadgeArea = [
    'Padat'   => 'bg-error-container text-on-error-container',
    'Normal'  => 'bg-tertiary-fixed text-on-tertiary-fixed',
    'Lengang' => 'bg-secondary-fixed text-on-secondary-fixed-variant',
];

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Selamat datang, <?= htmlspecialchars($namaUser) ?></h2>
            <p class="text-body-lg text-on-surface-variant">Ringkasan bisnis dan performa operasional secara keseluruhan.</p>
        </div>
        <a href="laporan.php" class="flex items-center gap-2 px-lg py-3 rounded-xl bg-primary text-on-primary font-bold shadow-md hover:bg-primary/90 transition-all">
            <span class="material-symbols-outlined">bar_chart</span> Lihat Laporan Lengkap
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">account_balance_wallet</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Pendapatan (Semua Waktu)</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah_singkat($totalPendapatanKeseluruhan) ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">payments</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Pendapatan Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah_singkat($pendapatanHariIni) ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">app_registration</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kendaraan Terdaftar</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($totalKendaraan, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">directions_car</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Okupansi Rata-rata</p>
            <h3 class="font-title-md text-title-md font-bold"><?= $persenOkupansi ?>%</h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">map</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Total Area Parkir</p>
            <h3 class="font-title-md text-title-md font-bold"><?= $totalArea ?> Area</h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">group</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Pengguna Aktif</p>
            <h3 class="font-title-md text-title-md font-bold"><?= $penggunaAktif ?></h3>
        </div>
    </div>

    <!-- Rekap Transaksi & Insight -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10 flex items-center gap-md">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary shrink-0"><span class="material-symbols-outlined">login</span></div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-xs">Transaksi Masuk Hari Ini</p>
                <h3 class="font-title-md text-title-md font-bold"><?= number_format($transaksiMasukHariIni, 0, ',', '.') ?></h3>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10 flex items-center gap-md">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary shrink-0"><span class="material-symbols-outlined">logout</span></div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-xs">Transaksi Keluar Hari Ini</p>
                <h3 class="font-title-md text-title-md font-bold"><?= number_format($transaksiKeluarHariIni, 0, ',', '.') ?></h3>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10 flex items-center gap-md">
            <div class="p-2 bg-primary/10 rounded-lg text-primary shrink-0"><span class="material-symbols-outlined">local_fire_department</span></div>
            <div>
                <p class="text-label-md text-on-surface-variant mb-xs">Hari Paling Ramai (7 Hari Terakhir)</p>
                <h3 class="font-title-md text-title-md font-bold"><?= $hariRamai ? htmlspecialchars($hariRamai['hari_label']) . ' · ' . number_format($hariRamai['jumlah'], 0, ',', '.') . ' kendaraan' : '-' ?></h3>
            </div>
        </div>
    </div>

    <!-- Revenue chart + Area performance -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
        <div class="lg:col-span-2 bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
            <h2 class="font-title-md text-title-md font-bold text-on-surface mb-lg">Tren Pendapatan Mingguan</h2>
            <div class="h-64 flex items-end justify-between gap-md relative">
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-10">
                    <div class="border-b border-on-surface"></div><div class="border-b border-on-surface"></div>
                    <div class="border-b border-on-surface"></div><div class="border-b border-on-surface"></div>
                </div>
                <?php foreach ($chartMingguan as $c):
                    $tinggi = max(round(($c['jumlah'] / $maxChart) * 100), 4);
                    $isTertinggi = $c['jumlah'] == $maxChart;
                ?>
                <div class="flex-1 flex flex-col justify-end items-center gap-xs h-full">
                    <div class="w-full <?= $isTertinggi ? 'bg-primary' : 'bg-primary/20' ?> rounded-t-lg transition-all duration-500" style="height: <?= $tinggi ?>%" title="<?= format_rupiah($c['jumlah']) ?>"></div>
                    <span class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($c['hari_label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
            <h2 class="font-title-md text-title-md font-bold text-on-surface mb-lg">Distribusi Staf Aktif</h2>
            <div class="space-y-md">
                <?php foreach ($penggunaPerRole as $r): ?>
                <div class="flex justify-between items-center text-body-md">
                    <span class="text-on-surface-variant"><?= htmlspecialchars($r['nama_role']) ?></span>
                    <span class="font-bold"><?= (int) $r['jumlah'] ?> orang</span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="rekap_transaksi.php" class="mt-lg block text-center text-primary font-bold text-body-md hover:underline">Lihat Rekap Transaksi</a>
        </div>
    </div>

    <!-- Tren Kendaraan Masuk Mingguan -->
    <div class="bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
        <div class="flex flex-wrap items-center justify-between gap-sm mb-lg">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Tren Kendaraan Masuk Mingguan</h2>
            <?php if ($hariRamai): ?>
            <span class="px-sm py-1 rounded-full text-label-md bg-tertiary/10 text-tertiary font-bold">Paling ramai: <?= htmlspecialchars($hariRamai['hari_label']) ?> (<?= number_format($hariRamai['jumlah'], 0, ',', '.') ?> kendaraan)</span>
            <?php endif; ?>
        </div>
        <div class="h-56 flex items-end justify-between gap-md relative">
            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-10">
                <div class="border-b border-on-surface"></div><div class="border-b border-on-surface"></div>
                <div class="border-b border-on-surface"></div><div class="border-b border-on-surface"></div>
            </div>
            <?php foreach ($chartKendaraanMingguan as $ck):
                $tinggiKendaraan = max(round(($ck['jumlah'] / $maxChartKendaraan) * 100), 4);
                $isTertinggiKendaraan = $ck['jumlah'] == $maxChartKendaraan;
            ?>
            <div class="flex-1 flex flex-col justify-end items-center gap-xs h-full">
                <div class="w-full <?= $isTertinggiKendaraan ? 'bg-tertiary' : 'bg-tertiary/20' ?> rounded-t-lg transition-all duration-500" style="height: <?= $tinggiKendaraan ?>%" title="<?= number_format($ck['jumlah'], 0, ',', '.') ?> kendaraan"></div>
                <span class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($ck['hari_label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Area performance table + Audit log -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Performa Area Teratas</h2>
                <a href="laporan.php" class="text-primary font-bold text-body-md hover:underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low">
                            <th class="px-lg py-sm text-label-md uppercase text-outline">Area</th>
                            <th class="px-lg py-sm text-label-md uppercase text-outline">Status</th>
                            <th class="px-lg py-sm text-label-md uppercase text-outline">Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                    <?php foreach ($areaRingkasan as $a): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-sm text-body-md font-medium"><?= htmlspecialchars($a['nama_area']) ?></td>
                            <td class="px-lg py-sm">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-label-md <?= $statusBadgeArea[$a['status']] ?? 'bg-surface-container text-on-surface' ?>"><?= htmlspecialchars($a['status']) ?></span>
                            </td>
                            <td class="px-lg py-sm text-body-md font-bold"><?= format_rupiah($a['pendapatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Aktivitas Staf Terbaru</h2>
                <a href="aktivitas.php" class="text-primary font-bold text-body-md hover:underline">Lihat Semua</a>
            </div>
            <div class="divide-y divide-outline-variant">
                <?php foreach ($logAktivitasTerbaru as $log): ?>
                <div class="px-lg py-md flex justify-between items-start gap-md">
                    <div>
                        <p class="text-body-md font-medium text-on-surface"><?= htmlspecialchars($log['nama_lengkap']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($log['aktivitas']) ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="inline-block px-2 py-1 rounded-full text-label-md <?= $kategoriBadge[$log['kategori']] ?? 'bg-outline/10 text-outline' ?>"><?= htmlspecialchars($log['kategori']) ?></span>
                        <p class="text-label-md text-on-surface-variant mt-1"><?= waktu_lalu_owner($log['created_at']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>
</main>
<?php include 'includes/footer.php'; ?>