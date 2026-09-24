<?php
session_start();
require_once 'config.php';
require_login_page();

$activePage = 'laporan';
$pageTitle  = 'Parking Management';
$searchPlaceholder = 'Cari data laporan...';

/* ============ PERIODE AKTIF ============
   Setiap tombol (Harian/Mingguan/Bulanan/Kustom) adalah link/form dengan
   query string ?periode=..., sehingga PHP menghitung ulang rentang tanggal
   dan menjalankan query yang benar-benar berbeda, bukan hanya mengganti
   tampilan tombol. */
$periodeValid = ['harian', 'mingguan', 'bulanan', 'kustom'];
$periode = $_GET['periode'] ?? 'bulanan';
if (!in_array($periode, $periodeValid, true)) {
    $periode = 'bulanan';
}

$hariIni = date('Y-m-d');

switch ($periode) {
    case 'harian':
        $tglMulai = $hariIni;
        $tglAkhir = $hariIni;
        $tglMulaiSebelum = date('Y-m-d', strtotime('-1 day'));
        $tglAkhirSebelum = date('Y-m-d', strtotime('-1 day'));
        $labelPembanding = 'dari kemarin';
        break;

    case 'mingguan':
        $tglMulai = date('Y-m-d', strtotime('-6 days'));
        $tglAkhir = $hariIni;
        $tglMulaiSebelum = date('Y-m-d', strtotime('-13 days'));
        $tglAkhirSebelum = date('Y-m-d', strtotime('-7 days'));
        $labelPembanding = 'dari minggu lalu';
        break;

    case 'kustom':
        $tglMulai = $_GET['mulai'] ?? date('Y-m-d', strtotime('-6 days'));
        $tglAkhir = $_GET['akhir'] ?? $hariIni;
        if (strtotime($tglMulai) === false) $tglMulai = date('Y-m-d', strtotime('-6 days'));
        if (strtotime($tglAkhir) === false) $tglAkhir = $hariIni;
        if (strtotime($tglMulai) > strtotime($tglAkhir)) {
            [$tglMulai, $tglAkhir] = [$tglAkhir, $tglMulai];
        }
        $rentangHari = (int) ((strtotime($tglAkhir) - strtotime($tglMulai)) / 86400) + 1;
        $tglMulaiSebelum = date('Y-m-d', strtotime($tglMulai . " -{$rentangHari} days"));
        $tglAkhirSebelum = date('Y-m-d', strtotime($tglMulai . ' -1 day'));
        $labelPembanding = 'dari periode sebelumnya';
        break;

    case 'bulanan':
    default:
        $tglMulai = date('Y-m-01');
        $tglAkhir = $hariIni;
        $tglMulaiSebelum = date('Y-m-01', strtotime('-1 month'));
        $tglAkhirSebelum = date('Y-m-t', strtotime('-1 month'));
        $labelPembanding = 'dari bulan lalu';
        break;
}
// Rentang 1 hari (Harian, atau Kustom dengan mulai == akhir) -> grafik per jam.
// Selain itu -> grafik per hari (rata-rata okupansi harian).
$rentangSatuHari = ($tglMulai === $tglAkhir);

function url_periode(string $periode, array $override = []): string
{
    return '?' . http_build_query(array_merge(['periode' => $periode], $override));
}

function hitung_perubahan_persen(float $sekarang, float $sebelum): ?float
{
    if ($sebelum == 0.0) {
        return $sekarang == 0.0 ? 0.0 : null;
    }
    return round((($sekarang - $sebelum) / $sebelum) * 100, 1);
}

function format_perubahan($nilai, string $labelPembanding, string $satuan = '%'): array
{
    if ($nilai === null) {
        return ['teks' => 'Data pembanding tidak tersedia', 'ikon' => 'trending_flat', 'warna' => 'text-outline'];
    }
    if ($nilai > 0) {
        return ['teks' => '+' . number_format($nilai, 1, ',', '.') . "{$satuan} {$labelPembanding}", 'ikon' => 'trending_up', 'warna' => 'text-tertiary'];
    }
    if ($nilai < 0) {
        return ['teks' => number_format($nilai, 1, ',', '.') . "{$satuan} {$labelPembanding}", 'ikon' => 'trending_down', 'warna' => 'text-error'];
    }
    return ['teks' => 'Tidak berubah ' . $labelPembanding, 'ikon' => 'trending_flat', 'warna' => 'text-outline'];
}

/* ============ DATA RINGKASAN (mengikuti rentang periode terpilih) ============
   Catatan: mengasumsikan tabel transaksi punya kolom tanggal/waktu bernama
   created_at (pola yang sama dipakai di dashboard_admin.php untuk tabel lain).
   Jika kolom tanggal transaksi Anda bernama lain (mis. waktu_keluar), ganti
   "created_at" di query totalTransaksi & totalPendapatan di bawah ini. */
$totalTransaksi = (int) db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE status = 'Keluar' AND DATE(created_at) BETWEEN ? AND ?",
    [$tglMulai, $tglAkhir], ['jml' => 12482]
)['jml'];

$totalTransaksiSebelum = (int) db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE status = 'Keluar' AND DATE(created_at) BETWEEN ? AND ?",
    [$tglMulaiSebelum, $tglAkhirSebelum], ['jml' => 11098]
)['jml'];

$totalPendapatan = (float) db_fetch_one(
    "SELECT COALESCE(SUM(biaya),0) AS jumlah FROM transaksi WHERE DATE(created_at) BETWEEN ? AND ?",
    [$tglMulai, $tglAkhir], ['jumlah' => 482500000]
)['jumlah'];

$totalPendapatanSebelum = (float) db_fetch_one(
    "SELECT COALESCE(SUM(biaya),0) AS jumlah FROM transaksi WHERE DATE(created_at) BETWEEN ? AND ?",
    [$tglMulaiSebelum, $tglAkhirSebelum], ['jumlah' => 445800000]
)['jumlah'];

// Okupansi rata-rata periode terpilih dihitung dari rata-rata data per-jam
// (tabel okupansi_per_jam), bukan snapshot slot_parkir saat ini — supaya
// benar-benar mencerminkan periode yang dipilih, bukan hanya "saat ini".
$okupansiRata = (float) db_fetch_one(
    "SELECT AVG(persentase) AS rata FROM okupansi_per_jam WHERE tanggal BETWEEN ? AND ?",
    [$tglMulai, $tglAkhir], ['rata' => 65.5]
)['rata'];

$okupansiRataSebelum = (float) db_fetch_one(
    "SELECT AVG(persentase) AS rata FROM okupansi_per_jam WHERE tanggal BETWEEN ? AND ?",
    [$tglMulaiSebelum, $tglAkhirSebelum], ['rata' => 67.0]
)['rata'];
$okupansiRata = round($okupansiRata, 1);

$perubahanTransaksi  = format_perubahan(hitung_perubahan_persen($totalTransaksi, $totalTransaksiSebelum), $labelPembanding);
$perubahanPendapatan = format_perubahan(hitung_perubahan_persen($totalPendapatan, $totalPendapatanSebelum), $labelPembanding);
$perubahanOkupansi   = format_perubahan(
    $okupansiRataSebelum == 0.0 ? null : round($okupansiRata - $okupansiRataSebelum, 1),
    $labelPembanding, ' poin'
);

/* ============ DATA CHART OKUPANSI (per jam untuk 1 hari, per hari untuk rentang lebih panjang) ============ */
if ($rentangSatuHari) {
    $okupansiJam = db_fetch_all(
        "SELECT jam_label, persentase FROM okupansi_per_jam WHERE tanggal = ? ORDER BY id ASC",
        [$tglMulai],
        [
            ['jam_label' => '00:00', 'persentase' => 20], ['jam_label' => '03:00', 'persentase' => 15],
            ['jam_label' => '06:00', 'persentase' => 45], ['jam_label' => '09:00', 'persentase' => 95],
            ['jam_label' => '12:00', 'persentase' => 88], ['jam_label' => '15:00', 'persentase' => 92],
            ['jam_label' => '18:00', 'persentase' => 70], ['jam_label' => '21:00', 'persentase' => 35],
        ]
    );
    $labelJam = array_column($okupansiJam, 'jam_label');
    $dataJam  = array_map('floatval', array_column($okupansiJam, 'persentase'));
    $chartOkupansiTitle = 'Tren Okupansi Per Jam';
    $chartOkupansiSub   = 'Kepadatan area parkir pada ' . date('d/m/Y', strtotime($tglMulai));
} else {
    $okupansiHarian = db_fetch_all(
        "SELECT tanggal, AVG(persentase) AS rata FROM okupansi_per_jam WHERE tanggal BETWEEN ? AND ? GROUP BY tanggal ORDER BY tanggal ASC",
        [$tglMulai, $tglAkhir],
        (function () use ($tglMulai, $tglAkhir) {
            $out = []; $cur = strtotime($tglMulai); $end = strtotime($tglAkhir); $i = 0;
            while ($cur <= $end) {
                $out[] = ['tanggal' => date('Y-m-d', $cur), 'rata' => round(40 + 35 * sin($i / 2), 1)];
                $cur = strtotime('+1 day', $cur); $i++;
            }
            return $out;
        })()
    );
    $labelJam = array_map(fn($r) => date('d/m', strtotime($r['tanggal'])), $okupansiHarian);
    $dataJam  = array_map(fn($r) => round((float) $r['rata'], 1), $okupansiHarian);
    $chartOkupansiTitle = 'Tren Okupansi Per Hari';
    $chartOkupansiSub   = 'Rata-rata kepadatan ' . date('d/m/Y', strtotime($tglMulai)) . ' – ' . date('d/m/Y', strtotime($tglAkhir));
}

/* ============ DATA CHART TIPE KENDARAAN ============
   Ini komposisi seluruh kendaraan terdaftar (tidak terikat periode),
   sama seperti perilaku aslinya — karena tidak ada kolom tanggal pada
   query GROUP BY tipe di file sebelumnya. */
$tipeKendaraan = db_fetch_all(
    "SELECT tipe, COUNT(*) AS jml FROM kendaraan GROUP BY tipe",
    [],
    [
        ['tipe' => 'Mobil', 'jml' => 65],
        ['tipe' => 'Motor', 'jml' => 28],
        ['tipe' => 'Bus/Truk', 'jml' => 7],
    ]
);
$totalTipe = array_sum(array_column($tipeKendaraan, 'jml')) ?: 1;
$persenTipe = [];
foreach ($tipeKendaraan as $t) {
    $persenTipe[$t['tipe']] = round(($t['jml'] / $totalTipe) * 100, 1);
}
$persenMobil = $persenTipe['Mobil'] ?? 65;
$persenMotor = $persenTipe['Motor'] ?? 28;
$persenBus   = $persenTipe['Bus/Truk'] ?? 7;

/* ============ TABEL RINGKASAN AREA (dengan paginasi) ============ */
$perHalaman = 3;
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$offset = ($halaman - 1) * $perHalaman;

$daftarArea = db_fetch_all(
    "SELECT nama_area, total_slot, slot_terisi, status, pendapatan FROM view_area_ringkasan ORDER BY area_id ASC LIMIT ? OFFSET ?",
    [$perHalaman, $offset],
    [
        ['nama_area' => 'Lantai B1 - Area A (Direksi)', 'total_slot' => 150, 'slot_terisi' => 138, 'status' => 'Padat', 'pendapatan' => 45200000],
        ['nama_area' => 'Lantai B2 - Area Umum', 'total_slot' => 350, 'slot_terisi' => 263, 'status' => 'Normal', 'pendapatan' => 82900000],
        ['nama_area' => 'Lantai P1 - Sepeda Motor', 'total_slot' => 800, 'slot_terisi' => 512, 'status' => 'Normal', 'pendapatan' => 12500000],
    ]
);
$totalAreaKeseluruhan = (int) db_fetch_one("SELECT COUNT(*) AS jml FROM area", [], ['jml' => 12])['jml'];
$totalHalaman = max(1, (int) ceil($totalAreaKeseluruhan / $perHalaman));
$halaman = min($halaman, $totalHalaman);

$statusBadge = [
    'Padat'   => 'bg-error-container text-on-error-container',
    'Normal'  => 'bg-tertiary-fixed text-on-tertiary-fixed',
    'Lengang' => 'bg-secondary-fixed text-on-secondary-fixed-variant',
];
$statusDot = ['Padat' => 'bg-error', 'Normal' => 'bg-tertiary', 'Lengang' => 'bg-secondary'];

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-24 px-lg pb-xl min-h-screen">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-xl gap-md" id="areaCetak">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Laporan &amp; Analitik</h2>
            <p class="text-body-lg text-on-surface-variant">Pantau performa operasional parkir secara real-time.</p>
        </div>
        <div class="flex items-center gap-sm bg-white p-1 rounded-xl shadow-sm border border-outline-variant no-print">
            <?php foreach (['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'] as $key => $label): ?>
                <a href="<?= url_periode($key) ?>"
                   class="px-md py-2 rounded-lg text-body-md font-medium transition-colors <?= $periode === $key ? 'bg-primary text-on-primary shadow-sm' : 'text-outline hover:bg-surface-container' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
            <button type="button" id="btnBukaKustom"
                    class="px-md py-2 rounded-lg text-body-md font-medium transition-colors flex items-center gap-1 <?= $periode === 'kustom' ? 'bg-primary text-on-primary shadow-sm' : 'text-outline hover:bg-surface-container' ?>">
                <span class="material-symbols-outlined text-[18px]">date_range</span> Kustom
            </button>
        </div>
    </div>

    <!-- Form tanggal kustom -->
    <form method="get" id="formKustom" class="<?= $periode === 'kustom' ? '' : 'hidden' ?> no-print bg-white p-md rounded-xl shadow-sm border border-outline-variant flex flex-wrap items-end gap-md mb-xl">
        <input type="hidden" name="periode" value="kustom">
        <div>
            <label class="text-label-md text-outline block mb-1" for="mulai">Tanggal Mulai</label>
            <input type="date" id="mulai" name="mulai" value="<?= htmlspecialchars($tglMulai) ?>" max="<?= $hariIni ?>"
                   class="px-sm py-2 rounded-lg border border-outline-variant text-body-md focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="text-label-md text-outline block mb-1" for="akhir">Tanggal Akhir</label>
            <input type="date" id="akhir" name="akhir" value="<?= htmlspecialchars($tglAkhir) ?>" max="<?= $hariIni ?>"
                   class="px-sm py-2 rounded-lg border border-outline-variant text-body-md focus:outline-none focus:border-primary">
        </div>
        <button type="submit" class="px-md py-2 rounded-xl bg-primary text-on-primary font-bold text-body-md hover:opacity-90 transition-opacity">Terapkan</button>
    </form>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <div class="bento-card p-xl border-l-4 border-primary relative overflow-hidden">
            <div class="flex justify-between items-start mb-sm">
                <div>
                    <p class="text-label-md uppercase tracking-wider text-outline font-bold">Total Transaksi</p>
                    <h3 class="font-display-lg text-display-lg mt-1"><?= number_format($totalTransaksi, 0, ',', '.') ?></h3>
                </div>
                <div class="bg-primary-fixed p-3 rounded-xl text-primary"><span class="material-symbols-outlined">receipt_long</span></div>
            </div>
            <div class="flex items-center gap-1 <?= $perubahanTransaksi['warna'] ?>">
                <span class="material-symbols-outlined text-[20px]"><?= $perubahanTransaksi['ikon'] ?></span>
                <span class="text-body-md font-medium"><?= htmlspecialchars($perubahanTransaksi['teks']) ?></span>
            </div>
        </div>
        <div class="bento-card p-xl border-l-4 border-secondary relative overflow-hidden">
            <div class="flex justify-between items-start mb-sm">
                <div>
                    <p class="text-label-md uppercase tracking-wider text-outline font-bold">Total Pendapatan</p>
                    <h3 class="font-display-lg text-display-lg mt-1"><?= format_rupiah_singkat($totalPendapatan) ?></h3>
                </div>
                <div class="bg-secondary-fixed p-3 rounded-xl text-secondary"><span class="material-symbols-outlined">payments</span></div>
            </div>
            <div class="flex items-center gap-1 <?= $perubahanPendapatan['warna'] ?>">
                <span class="material-symbols-outlined text-[20px]"><?= $perubahanPendapatan['ikon'] ?></span>
                <span class="text-body-md font-medium"><?= htmlspecialchars($perubahanPendapatan['teks']) ?></span>
            </div>
        </div>
        <div class="bento-card p-xl border-l-4 border-tertiary relative overflow-hidden">
            <div class="flex justify-between items-start mb-sm">
                <div>
                    <p class="text-label-md uppercase tracking-wider text-outline font-bold">Okupansi Rata-rata</p>
                    <h3 class="font-display-lg text-display-lg mt-1"><?= $okupansiRata ?>%</h3>
                </div>
                <div class="bg-tertiary-fixed p-3 rounded-xl text-tertiary"><span class="material-symbols-outlined">leaderboard</span></div>
            </div>
            <div class="flex items-center gap-1 <?= $perubahanOkupansi['warna'] ?>">
                <span class="material-symbols-outlined text-[20px]"><?= $perubahanOkupansi['ikon'] ?></span>
                <span class="text-body-md font-medium"><?= htmlspecialchars($perubahanOkupansi['teks']) ?></span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg mb-xl">
        <div class="lg:col-span-2 bento-card p-xl">
            <div class="flex justify-between items-center mb-xl">
                <div>
                    <h4 class="font-title-md text-title-md"><?= htmlspecialchars($chartOkupansiTitle) ?></h4>
                    <p class="text-body-md text-outline"><?= htmlspecialchars($chartOkupansiSub) ?></p>
                </div>
                <button type="button" id="btnRefreshChart" class="p-2 hover:bg-surface-container-high rounded-lg transition-all no-print" title="Refresh">
                    <span class="material-symbols-outlined text-outline">refresh</span>
                </button>
            </div>
            <?php if (empty($dataJam)): ?>
                <p class="text-body-md text-outline py-xl text-center">Belum ada data untuk periode ini.</p>
            <?php else: ?>
                <div class="chart-container"><canvas id="occupancyChart"></canvas></div>
            <?php endif; ?>
        </div>
        <div class="bento-card p-xl">
            <h4 class="font-title-md text-title-md mb-xl">Statistik Berdasarkan Tipe Kendaraan</h4>
            <div class="flex flex-col items-center">
                <div class="w-full h-64 mb-lg"><canvas id="vehicleChart"></canvas></div>
                <div class="w-full space-y-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-primary"></span><span class="text-body-md text-on-surface-variant">Mobil Pribadi</span></div>
                        <span class="text-body-md font-bold"><?= $persenMobil ?>%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-secondary"></span><span class="text-body-md text-on-surface-variant">Motor</span></div>
                        <span class="text-body-md font-bold"><?= $persenMotor ?>%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-tertiary"></span><span class="text-body-md text-on-surface-variant">Bus/Truk</span></div>
                        <span class="text-body-md font-bold"><?= $persenBus ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row justify-end items-center gap-md mb-xl no-print">
        <button type="button" id="btnEksporExcel" class="w-full sm:w-auto flex items-center justify-center gap-2 px-xl py-3 rounded-xl border-2 border-secondary text-secondary font-bold hover:bg-secondary/10 transition-all active:scale-95">
            <span class="material-symbols-outlined">description</span> Ekspor Excel
        </button>
        <button type="button" id="btnEksporPdf" class="w-full sm:w-auto flex items-center justify-center gap-2 px-xl py-3 rounded-xl bg-primary text-on-primary font-bold shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all active:scale-95">
            <span class="material-symbols-outlined">picture_as_pdf</span> Ekspor PDF
        </button>
    </div>

    <!-- Data Table -->
    <div class="bento-card overflow-hidden" id="tabelArea">
        <div class="px-xl py-lg border-b border-outline-variant flex justify-between items-center">
            <h4 class="font-title-md text-title-md">Ringkasan Laporan Per Area</h4>
            <a href="#" class="text-primary font-bold text-body-md hover:underline no-print">Lihat Detail</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low">
                        <th class="px-xl py-md text-label-md uppercase text-outline">Nama Area / Lantai</th>
                        <th class="px-xl py-md text-label-md uppercase text-outline">Total Kapasitas</th>
                        <th class="px-xl py-md text-label-md uppercase text-outline">Okupansi Rata-rata</th>
                        <th class="px-xl py-md text-label-md uppercase text-outline">Status</th>
                        <th class="px-xl py-md text-label-md uppercase text-outline">Pendapatan (IDR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant" id="tbodyArea">
                <?php if (empty($daftarArea)): ?>
                    <tr><td colspan="5" class="px-xl py-lg text-body-md text-outline text-center">Tidak ada data area.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftarArea as $a):
                    $okupansi = $a['total_slot'] > 0 ? round(($a['slot_terisi'] / $a['total_slot']) * 100) : 0;
                    $status = $a['status'];
                ?>
                    <tr class="hover:bg-surface-container-lowest transition-colors"
                        data-nama="<?= htmlspecialchars($a['nama_area']) ?>" data-slot="<?= (int) $a['total_slot'] ?>"
                        data-okupansi="<?= $okupansi ?>" data-status="<?= htmlspecialchars($status) ?>" data-pendapatan="<?= (int) $a['pendapatan'] ?>">
                        <td class="px-xl py-md text-body-md font-medium"><?= htmlspecialchars($a['nama_area']) ?></td>
                        <td class="px-xl py-md text-body-md"><?= number_format($a['total_slot'], 0, ',', '.') ?> Slot</td>
                        <td class="px-xl py-md text-body-md"><?= $okupansi ?>%</td>
                        <td class="px-xl py-md">
                            <span class="inline-flex items-center gap-1 <?= $statusBadge[$status] ?? 'bg-surface-container text-on-surface' ?> px-2 py-1 rounded-full text-label-md">
                                <span class="w-1.5 h-1.5 rounded-full <?= $statusDot[$status] ?? 'bg-outline' ?>"></span> <?= htmlspecialchars($status) ?>
                            </span>
                        </td>
                        <td class="px-xl py-md text-body-md font-bold"><?= number_format($a['pendapatan'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-xl py-md bg-surface-container-lowest flex justify-between items-center border-t border-outline-variant no-print">
            <p class="text-label-md text-outline">
                Menampilkan <?= count($daftarArea) ?> dari <?= $totalAreaKeseluruhan ?> area parkir
                &bull; Halaman <?= $halaman ?> / <?= $totalHalaman ?>
            </p>
            <div class="flex gap-2">
                <a href="<?= url_periode($periode, array_filter(['mulai' => $periode === 'kustom' ? $tglMulai : null, 'akhir' => $periode === 'kustom' ? $tglAkhir : null, 'halaman' => max(1, $halaman - 1)])) ?>"
                   class="p-1 rounded border border-outline-variant transition-colors <?= $halaman <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-surface-container' ?>">
                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                </a>
                <a href="<?= url_periode($periode, array_filter(['mulai' => $periode === 'kustom' ? $tglMulai : null, 'akhir' => $periode === 'kustom' ? $tglAkhir : null, 'halaman' => min($totalHalaman, $halaman + 1)])) ?>"
                   class="p-1 rounded border border-outline-variant transition-colors <?= $halaman >= $totalHalaman ? 'opacity-40 pointer-events-none' : 'hover:bg-surface-container' ?>">
                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                </a>
            </div>
        </div>
    </div>

<style>
@media print {
    .no-print { display: none !important; }
    aside, header, nav { display: none !important; }
    main { margin-left: 0 !important; padding-top: 0 !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Toggle form tanggal kustom ---------- */
    const btnKustom = document.getElementById('btnBukaKustom');
    const formKustom = document.getElementById('formKustom');
    btnKustom?.addEventListener('click', () => {
        formKustom.classList.toggle('hidden');
        if (!formKustom.classList.contains('hidden')) {
            formKustom.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    /* ---------- Refresh grafik ---------- */
    document.getElementById('btnRefreshChart')?.addEventListener('click', () => window.location.reload());

    /* ---------- Ekspor Excel (CSV dari tabel area yang tampil) ---------- */
    document.getElementById('btnEksporExcel')?.addEventListener('click', () => {
        const rows = [['Nama Area', 'Total Slot', 'Okupansi (%)', 'Status', 'Pendapatan (IDR)']];
        document.querySelectorAll('#tbodyArea tr[data-nama]').forEach(tr => {
            rows.push([tr.dataset.nama, tr.dataset.slot, tr.dataset.okupansi, tr.dataset.status, tr.dataset.pendapatan]);
        });
        const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'laporan_area_<?= $periode ?>_<?= $tglMulai ?>_sd_<?= $tglAkhir ?>.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });

    /* ---------- Ekspor PDF (pakai dialog cetak browser -> "Simpan sebagai PDF") ---------- */
    document.getElementById('btnEksporPdf')?.addEventListener('click', () => window.print());

    /* ---------- Grafik okupansi ---------- */
    const occCanvas = document.getElementById('occupancyChart');
    if (occCanvas) {
        new Chart(occCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labelJam) ?>,
                datasets: [{
                    label: 'Okupansi %',
                    data: <?= json_encode($dataJam) ?>,
                    borderColor: '#b5000b',
                    backgroundColor: 'rgba(181, 0, 11, 0.1)',
                    fill: true, tension: 0.4, borderWidth: 3,
                    pointBackgroundColor: '#b5000b', pointRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: '#eeeeee' }, ticks: { callback: v => v + '%' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const vehCtx = document.getElementById('vehicleChart').getContext('2d');
    new Chart(vehCtx, {
        type: 'doughnut',
        data: {
            labels: ['Mobil', 'Motor', 'Bus/Truk'],
            datasets: [{
                data: [<?= $persenMobil ?>, <?= $persenMotor ?>, <?= $persenBus ?>],
                backgroundColor: ['#b5000b', '#3a5f94', '#3e6300'],
                borderWidth: 0, hoverOffset: 15
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
    });
});
</script>
</main>
<?php include 'includes/footer.php'; ?>