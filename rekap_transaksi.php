<?php
/**
 * rekap_transaksi.php
 * Halaman untuk role: Owner & Super Admin
 * Rekap transaksi masuk/keluar — ringkasan + daftar transaksi dengan filter tanggal & status.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OWNER, ROLE_SUPER_ADMIN]);

$activePage = 'rekap_transaksi';
$pageTitle  = 'Rekap Transaksi';

/* ============ FILTER ============ */
$statusFilter = $_GET['status'] ?? 'semua';
if (!in_array($statusFilter, ['semua', 'Masuk', 'Keluar'], true)) {
    $statusFilter = 'semua';
}
$tanggalMulai = $_GET['dari'] ?? date('Y-m-d', strtotime('-6 days'));
$tanggalAkhir = $_GET['sampai'] ?? date('Y-m-d');

/* ============ RINGKASAN HARI INI ============ */
$rekapHariIni = db_fetch_one(
    "SELECT
        SUM(CASE WHEN DATE(waktu_masuk) = CURDATE() THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN status = 'Keluar' AND DATE(waktu_keluar) = CURDATE() THEN 1 ELSE 0 END) AS keluar,
        SUM(CASE WHEN status = 'Keluar' AND DATE(waktu_keluar) = CURDATE() THEN biaya ELSE 0 END) AS pendapatan
     FROM transaksi",
    [], ['masuk' => 18, 'keluar' => 14, 'pendapatan' => 420000]
);
$masukHariIni      = (int) ($rekapHariIni['masuk'] ?? 0);
$keluarHariIni      = (int) ($rekapHariIni['keluar'] ?? 0);
$pendapatanHariIni = (float) ($rekapHariIni['pendapatan'] ?? 0);

$sedangParkir = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE status = 'Masuk'",
    [], ['jml' => 12]
)['jml'];

$rataDurasi = db_fetch_one(
    "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, waktu_masuk, waktu_keluar))) AS menit
     FROM transaksi WHERE status = 'Keluar' AND DATE(waktu_keluar) = CURDATE()",
    [], ['menit' => 95]
)['menit'];

/* ============ DAFTAR TRANSAKSI (sesuai filter) ============ */
$paramsList = ['dari' => $tanggalMulai, 'sampai' => $tanggalAkhir . ' 23:59:59'];
$whereStatus = '';
if ($statusFilter !== 'semua') {
    $whereStatus = ' AND t.status = :status';
    $paramsList['status'] = $statusFilter;
}
$daftarTransaksi = db_fetch_all(
    "SELECT t.id, t.status, t.waktu_masuk, t.waktu_keluar, t.biaya, t.metode_bayar,
            k.plat_nomor, k.tipe AS tipe_kendaraan, k.nama_pemilik,
            sp.kode_slot, l.nama_lantai,
            u.nama_lengkap AS petugas
     FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
     LEFT JOIN area a ON a.id = sp.area_id
     LEFT JOIN lantai l ON l.id = a.lantai_id
     LEFT JOIN users u ON u.id = t.petugas_id
     WHERE t.waktu_masuk BETWEEN :dari AND :sampai" . $whereStatus . "
     ORDER BY t.waktu_masuk DESC
     LIMIT 200",
    $paramsList,
    [
        ['id' => 1, 'status' => 'Keluar', 'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-2 hours')),
         'waktu_keluar' => date('Y-m-d H:i:s', strtotime('-1 hours')), 'biaya' => 7000, 'metode_bayar' => 'Tunai',
         'plat_nomor' => 'D 1234 AB', 'tipe_kendaraan' => 'Mobil', 'nama_pemilik' => 'Contoh Pemilik',
         'kode_slot' => 'B2-014', 'nama_lantai' => 'Lantai B2', 'petugas' => 'Contoh Petugas'],
    ]
);

function waktu_singkat_rekap($datetime)
{
    if (!$datetime) return '-';
    return date('d M Y, H:i', strtotime($datetime));
}

function durasi_rekap($masuk, $keluar)
{
    if (!$keluar) return 'Masih parkir';
    $menit = round((strtotime($keluar) - strtotime($masuk)) / 60);
    $jam = intdiv($menit, 60);
    $sisaMenit = $menit % 60;
    return ($jam > 0 ? $jam . ' jam ' : '') . $sisaMenit . ' menit';
}

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Rekap Transaksi</h2>
            <p class="text-body-lg text-on-surface-variant">Ringkasan dan daftar transaksi masuk/keluar kendaraan.</p>
        </div>
        <a href="laporan.php" class="flex items-center gap-2 px-lg py-3 rounded-xl bg-primary text-on-primary font-bold shadow-md hover:bg-primary/90 transition-all">
            <span class="material-symbols-outlined">bar_chart</span> Lihat Laporan Lengkap
        </a>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">login</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Transaksi Masuk Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($masukHariIni, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">logout</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Transaksi Keluar Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format($keluarHariIni, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">directions_car</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Sedang Parkir Sekarang</p>
            <h3 class="font-title-md text-title-md font-bold"><?= number_format((int) $sedangParkir, 0, ',', '.') ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">payments</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Pendapatan dari Transaksi Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($pendapatanHariIni) ?></h3>
        </div>
    </div>

    <!-- Filter + Daftar Transaksi -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant flex flex-wrap items-center justify-between gap-sm">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Daftar Transaksi</h2>
            <form method="get" class="flex flex-wrap items-center gap-sm">
                <input type="date" name="dari" value="<?= htmlspecialchars($tanggalMulai) ?>"
                       class="px-sm py-1.5 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                <span class="text-on-surface-variant text-body-md">s/d</span>
                <input type="date" name="sampai" value="<?= htmlspecialchars($tanggalAkhir) ?>"
                       class="px-sm py-1.5 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                <select name="status" class="px-sm py-1.5 rounded-lg border border-outline-variant/40 text-body-md focus:outline-none focus:border-primary">
                    <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Masuk" <?= $statusFilter === 'Masuk' ? 'selected' : '' ?>>Masih Parkir</option>
                    <option value="Keluar" <?= $statusFilter === 'Keluar' ? 'selected' : '' ?>>Sudah Keluar</option>
                </select>
                <button type="submit" class="px-md py-1.5 rounded-lg bg-primary text-on-primary font-bold text-body-md hover:bg-primary/90 transition-colors">Terapkan</button>
                <button type="button" id="btnExportRekap" class="px-md py-1.5 rounded-lg border border-outline-variant/40 font-bold text-body-md text-on-surface-variant hover:bg-surface-container-high transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">download</span> Ekspor CSV
                </button>
            </form>
        </div>
        <?php if (empty($daftarTransaksi)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Tidak ada transaksi pada rentang tanggal/filter ini.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tabelRekapTransaksi">
                <thead>
                    <tr class="bg-surface-container-low">
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Plat Nomor</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Tipe</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Lokasi Slot</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Masuk</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Keluar</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Durasi</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Biaya</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                <?php foreach ($daftarTransaksi as $t): ?>
                    <tr class="hover:bg-surface-container-lowest transition-colors">
                        <td class="px-lg py-sm text-body-md font-bold"><?= htmlspecialchars($t['plat_nomor']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($t['tipe_kendaraan']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars(($t['nama_lantai'] ?? '-') . ' / ' . ($t['kode_slot'] ?? '-')) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= waktu_singkat_rekap($t['waktu_masuk']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= $t['waktu_keluar'] ? waktu_singkat_rekap($t['waktu_keluar']) : '<span class="text-tertiary font-bold">Masih parkir</span>' ?></td>
                        <td class="px-lg py-sm text-body-md"><?= durasi_rekap($t['waktu_masuk'], $t['waktu_keluar']) ?></td>
                        <td class="px-lg py-sm text-body-md font-bold"><?= $t['biaya'] ? format_rupiah($t['biaya']) : '-' ?></td>
                        <td class="px-lg py-sm text-body-md text-on-surface-variant"><?= htmlspecialchars($t['petugas'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>
<script>
document.getElementById('btnExportRekap')?.addEventListener('click', () => {
    const rows = [['Plat Nomor', 'Tipe', 'Lokasi Slot', 'Masuk', 'Keluar', 'Durasi', 'Biaya', 'Petugas']];
    document.querySelectorAll('#tabelRekapTransaksi tbody tr').forEach(tr => {
        rows.push(Array.from(tr.children).map(td => td.textContent.trim()));
    });
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'rekap_transaksi.csv';
    link.click();
    URL.revokeObjectURL(link.href);
});
</script>
<?php include 'includes/footer.php'; ?>