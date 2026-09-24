<?php
/**
 * riwayat.php
 * Halaman Riwayat Transaksi (masuk & keluar) — bisa diakses oleh
 * Admin, Owner/Super Admin, dan Officer/Security (semua role internal),
 * karena ketiganya butuh melihat riwayat kendaraan masuk/keluar.
 *
 * Mendukung filter: status (Masuk/Keluar), tanggal, pencarian plat nomor,
 * serta pagination sederhana.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_ADMIN, ROLE_OWNER, ROLE_SUPER_ADMIN, ROLE_OFFICER, ROLE_SECURITY]);

$activePage = 'riwayat';
$pageTitle  = 'Riwayat Transaksi';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';
$role       = $_SESSION['role'] ?? '';

/* ============ FILTER ============ */
$filterStatus = $_GET['status'] ?? '';
$filterCari   = trim($_GET['cari'] ?? '');
$filterDari   = $_GET['dari'] ?? '';
$filterSampai = $_GET['sampai'] ?? '';

$perHalaman = 15;
$halaman    = max((int) ($_GET['page'] ?? 1), 1);
$offset     = ($halaman - 1) * $perHalaman;

$where  = [];
$params = [];

if ($filterStatus === 'Masuk' || $filterStatus === 'Keluar') {
    $where[] = 't.status = :status';
    $params['status'] = $filterStatus;
}
if ($filterCari !== '') {
    $where[] = '(k.plat_nomor LIKE :cari OR t.kode_parkir LIKE :cari OR k.nama_pemilik LIKE :cari)';
    $params['cari'] = "%$filterCari%";
}
if ($filterDari !== '') {
    $where[] = 'DATE(t.waktu_masuk) >= :dari';
    $params['dari'] = $filterDari;
}
if ($filterSampai !== '') {
    $where[] = 'DATE(t.waktu_masuk) <= :sampai';
    $params['sampai'] = $filterSampai;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ============ TOTAL DATA (untuk pagination) ============ */
$totalData = (int) db_fetch_one(
    "SELECT COUNT(*) AS jml
     FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     $whereSql",
    $params,
    ['jml' => 0]
)['jml'];
$totalHalaman = max((int) ceil($totalData / $perHalaman), 1);

/* ============ DATA RIWAYAT ============ */
$sql = "SELECT t.id, t.kode_parkir, k.plat_nomor, k.tipe AS tipe_kendaraan, k.nama_pemilik,
               sp.kode_slot, l.nama_lantai,
               t.waktu_masuk, t.waktu_keluar,
               TIMESTAMPDIFF(MINUTE, t.waktu_masuk, IFNULL(t.waktu_keluar, NOW())) AS durasi_menit,
               t.biaya, t.status, t.metode_bayar, u.nama_lengkap AS petugas
        FROM transaksi t
        JOIN kendaraan k ON k.id = t.kendaraan_id
        LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
        LEFT JOIN area a ON a.id = sp.area_id
        LEFT JOIN lantai l ON l.id = a.lantai_id
        LEFT JOIN users u ON u.id = t.petugas_id
        $whereSql
        ORDER BY t.waktu_masuk DESC
        LIMIT $perHalaman OFFSET $offset";

$riwayat = db_fetch_all($sql, $params, [
    ['id' => 1, 'kode_parkir' => 'PRK-2026-00001', 'plat_nomor' => 'B 1234 ABC', 'tipe_kendaraan' => 'Mobil',
     'nama_pemilik' => 'Andi Wijaya', 'kode_slot' => 'A-01', 'nama_lantai' => 'Lantai 1',
     'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'waktu_keluar' => date('Y-m-d H:i:s', strtotime('-1 hours')),
     'durasi_menit' => 60, 'biaya' => 7000, 'status' => 'Keluar', 'metode_bayar' => 'QRIS', 'petugas' => 'Dedi Kusuma'],
]);

/* ============ RINGKASAN CEPAT (hari ini) ============ */
$ringkasanHariIni = db_fetch_one(
    "SELECT
        SUM(CASE WHEN DATE(waktu_masuk) = CURDATE() THEN 1 ELSE 0 END) AS masuk_hari_ini,
        SUM(CASE WHEN status = 'Keluar' AND DATE(waktu_keluar) = CURDATE() THEN 1 ELSE 0 END) AS keluar_hari_ini,
        SUM(CASE WHEN status = 'Keluar' AND DATE(waktu_keluar) = CURDATE() THEN biaya ELSE 0 END) AS pendapatan_hari_ini
     FROM transaksi",
    [], ['masuk_hari_ini' => 0, 'keluar_hari_ini' => 0, 'pendapatan_hari_ini' => 0]
);

$statusBadge = [
    'Masuk'  => 'bg-secondary/10 text-secondary',
    'Keluar' => 'bg-tertiary/10 text-tertiary',
];

function durasi_singkat_riwayat($menit)
{
    $menit = (int) $menit;
    if ($menit < 60) return $menit . ' mnt';
    $jam = floor($menit / 60);
    $sisa = $menit % 60;
    return $jam . ' jam' . ($sisa > 0 ? ' ' . $sisa . ' mnt' : '');
}

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div>
        <h2 class="font-headline-lg text-headline-lg text-on-background">Riwayat Transaksi</h2>
        <p class="text-body-lg text-on-surface-variant">Riwayat kendaraan masuk &amp; keluar beserta status pembayaran.</p>
    </div>

    <!-- Ringkasan cepat -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <p class="text-label-md text-on-surface-variant mb-xs">Masuk Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $ringkasanHariIni['masuk_hari_ini'] ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <p class="text-label-md text-on-surface-variant mb-xs">Keluar Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $ringkasanHariIni['keluar_hari_ini'] ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <p class="text-label-md text-on-surface-variant mb-xs">Pendapatan Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($ringkasanHariIni['pendapatan_hari_ini']) ?></h3>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 p-lg grid grid-cols-1 md:grid-cols-5 gap-md items-end">
        <div class="md:col-span-2 space-y-xs">
            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Cari (Plat / Kode / Pemilik)</label>
            <input type="text" name="cari" value="<?= htmlspecialchars($filterCari) ?>" placeholder="cth: B 1234 ABC"
                   class="w-full px-md py-2 bg-surface border border-outline/20 rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
        </div>
        <div class="space-y-xs">
            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Status</label>
            <select name="status" class="w-full px-md py-2 bg-surface border border-outline/20 rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                <option value="">Semua</option>
                <option value="Masuk" <?= $filterStatus === 'Masuk' ? 'selected' : '' ?>>Masuk (Sedang Parkir)</option>
                <option value="Keluar" <?= $filterStatus === 'Keluar' ? 'selected' : '' ?>>Keluar (Selesai)</option>
            </select>
        </div>
        <div class="space-y-xs">
            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Dari Tanggal</label>
            <input type="date" name="dari" value="<?= htmlspecialchars($filterDari) ?>"
                   class="w-full px-md py-2 bg-surface border border-outline/20 rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
        </div>
        <div class="space-y-xs">
            <label class="text-label-md text-on-surface-variant uppercase tracking-wider block">Sampai Tanggal</label>
            <input type="date" name="sampai" value="<?= htmlspecialchars($filterSampai) ?>"
                   class="w-full px-md py-2 bg-surface border border-outline/20 rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
        </div>
        <div class="md:col-span-5 flex justify-end gap-sm">
            <a href="riwayat.php" class="px-lg py-2 rounded-lg border border-outline/20 text-on-surface-variant font-bold text-body-md hover:bg-surface-container transition-colors">Reset</a>
            <button type="submit" class="px-lg py-2 rounded-lg bg-primary text-on-primary font-bold text-body-md hover:bg-primary/90 transition-colors">Terapkan Filter</button>
        </div>
    </form>

    <!-- Tabel riwayat -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <?php if (empty($riwayat)): ?>
            <p class="px-lg py-xl text-body-md text-on-surface-variant text-center">Tidak ada data riwayat yang cocok dengan filter.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low">
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Kode Parkir</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Plat Nomor</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Slot / Lantai</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Masuk</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Keluar</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Durasi</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Biaya</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Bayar</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Status</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Petugas</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Struk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                <?php foreach ($riwayat as $r): ?>
                    <tr class="hover:bg-surface-container-lowest transition-colors">
                        <td class="px-lg py-sm text-body-md font-mono"><?= htmlspecialchars($r['kode_parkir']) ?></td>
                        <td class="px-lg py-sm text-body-md font-bold"><?= htmlspecialchars($r['plat_nomor']) ?><br><span class="text-label-md text-on-surface-variant font-normal"><?= htmlspecialchars($r['tipe_kendaraan']) ?> &bull; <?= htmlspecialchars($r['nama_pemilik']) ?></span></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($r['kode_slot'] ?? '-') ?><br><span class="text-label-md text-on-surface-variant"><?= htmlspecialchars($r['nama_lantai'] ?? '-') ?></span></td>
                        <td class="px-lg py-sm text-body-md"><?= date('d/m/y H:i', strtotime($r['waktu_masuk'])) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= $r['waktu_keluar'] ? date('d/m/y H:i', strtotime($r['waktu_keluar'])) : '-' ?></td>
                        <td class="px-lg py-sm text-body-md"><?= durasi_singkat_riwayat($r['durasi_menit']) ?></td>
                        <td class="px-lg py-sm text-body-md font-bold"><?= $r['status'] === 'Keluar' ? format_rupiah($r['biaya']) : '-' ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($r['metode_bayar'] ?? '-') ?></td>
                        <td class="px-lg py-sm">
                            <span class="inline-block px-2 py-1 rounded-full text-label-md <?= $statusBadge[$r['status']] ?? 'bg-outline/10 text-outline' ?>"><?= htmlspecialchars($r['status']) ?></span>
                        </td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($r['petugas'] ?? '-') ?></td>
                        <td class="px-lg py-sm">
                            <?php if ($r['status'] === 'Keluar'): ?>
                                <a href="struk.php?id=<?= (int) $r['id'] ?>" target="_blank" class="text-primary font-bold text-label-md hover:underline">Cetak</a>
                            <?php else: ?>
                                <span class="text-label-md text-outline">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-lg py-md bg-surface-container-lowest flex justify-between items-center border-t border-outline-variant">
            <p class="text-label-md text-outline">Halaman <?= $halaman ?> dari <?= $totalHalaman ?> &middot; <?= number_format($totalData, 0, ',', '.') ?> total data</p>
            <div class="flex gap-2">
                <?php
                    $queryTanpaPage = $_GET;
                    unset($queryTanpaPage['page']);
                    $baseQuery = http_build_query($queryTanpaPage);
                    $sep = $baseQuery ? '&' : '';
                ?>
                <a href="?<?= $baseQuery . $sep ?>page=<?= max($halaman - 1, 1) ?>"
                   class="p-1 px-3 rounded border border-outline-variant text-body-md hover:bg-surface-container transition-colors <?= $halaman <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">&laquo; Sebelumnya</a>
                <a href="?<?= $baseQuery . $sep ?>page=<?= min($halaman + 1, $totalHalaman) ?>"
                   class="p-1 px-3 rounded border border-outline-variant text-body-md hover:bg-surface-container transition-colors <?= $halaman >= $totalHalaman ? 'opacity-40 pointer-events-none' : '' ?>">Berikutnya &raquo;</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>
<?php include 'includes/footer.php'; ?>