<?php
/**
 * dashboard_user.php
 * Dashboard untuk role: User (Pengguna/Member)
 * Fokus: self-service — kendaraan pribadi, status parkir saat ini,
 * riwayat transaksi, dan ringkasan pengeluaran pribadi.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_USER]);

$activePage = 'beranda';
$pageTitle  = 'Dashboard Saya';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';
$userId     = $_SESSION['user_id'] ?? 0;

/* ============ RINGKASAN KENDARAAN SAYA ============ */
$totalKendaraanSaya = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM kendaraan WHERE terdaftar_oleh_user_id = :uid",
    ['uid' => $userId], ['jml' => 2]
)['jml'];

$kendaraanMenungguSaya = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM kendaraan WHERE terdaftar_oleh_user_id = :uid AND status_verifikasi = 'Menunggu Verifikasi'",
    ['uid' => $userId], ['jml' => 0]
)['jml'];

$sedangParkirSaya = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM view_transaksi_detail t
     JOIN kendaraan k ON k.plat_nomor = t.plat_nomor
     WHERE k.terdaftar_oleh_user_id = :uid AND t.status = 'Masuk'",
    ['uid' => $userId], ['jml' => 1]
)['jml'];

/* ============ RINGKASAN PENGELUARAN BULAN INI ============ */
$pengeluaranBulanIni = db_fetch_one(
    "SELECT IFNULL(SUM(t.biaya),0) AS jumlah FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     WHERE k.terdaftar_oleh_user_id = :uid AND MONTH(t.waktu_masuk) = MONTH(CURDATE()) AND YEAR(t.waktu_masuk) = YEAR(CURDATE())",
    ['uid' => $userId], ['jumlah' => 45000]
)['jumlah'];

$kunjunganBulanIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     WHERE k.terdaftar_oleh_user_id = :uid AND MONTH(t.waktu_masuk) = MONTH(CURDATE()) AND YEAR(t.waktu_masuk) = YEAR(CURDATE())",
    ['uid' => $userId], ['jml' => 6]
)['jml'];

/* ============ DAFTAR KENDARAAN SAYA ============ */
$daftarKendaraanSaya = db_fetch_all(
    "SELECT id, kode_kendaraan, plat_nomor, tipe, warna, status_verifikasi, created_at
     FROM kendaraan WHERE terdaftar_oleh_user_id = :uid ORDER BY created_at DESC",
    ['uid' => $userId],
    [
        ['id' => 1, 'kode_kendaraan' => 'VHC-2023-001', 'plat_nomor' => 'B 1234 ABC', 'tipe' => 'Mobil', 'warna' => 'Hitam', 'status_verifikasi' => 'Terverifikasi', 'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))],
        ['id' => 2, 'kode_kendaraan' => 'VHC-2023-002', 'plat_nomor' => 'B 5678 XYZ', 'tipe' => 'Motor', 'warna' => 'Silver', 'status_verifikasi' => 'Menunggu Verifikasi', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))],
    ]
);

/* ============ KENDARAAN SEDANG PARKIR (milik saya) ============ */
$parkirAktifSaya = db_fetch_all(
    "SELECT t.id, t.plat_nomor, t.tipe_kendaraan, t.kode_slot, t.nama_lantai, t.waktu_masuk, t.durasi_menit
     FROM view_transaksi_detail t
     JOIN kendaraan k ON k.plat_nomor = t.plat_nomor
     WHERE k.terdaftar_oleh_user_id = :uid AND t.status = 'Masuk'
     ORDER BY t.waktu_masuk DESC",
    ['uid' => $userId],
    [
        ['id' => 101, 'plat_nomor' => 'B 1234 ABC', 'tipe_kendaraan' => 'Mobil', 'kode_slot' => 'A-01', 'nama_lantai' => 'Lantai 1', 'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-55 minutes')), 'durasi_menit' => 55],
    ]
);

/* ============ RIWAYAT PARKIR TERBARU ============ */
$riwayatTerbaru = db_fetch_all(
    "SELECT t.id, t.plat_nomor, t.tipe_kendaraan, t.waktu_masuk, t.waktu_keluar, t.durasi_menit, t.biaya, t.status
     FROM view_transaksi_detail t
     JOIN kendaraan k ON k.plat_nomor = t.plat_nomor
     WHERE k.terdaftar_oleh_user_id = :uid
     ORDER BY t.waktu_masuk DESC LIMIT 6",
    ['uid' => $userId],
    [
        ['id' => 98, 'plat_nomor' => 'B 1234 ABC', 'tipe_kendaraan' => 'Mobil', 'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-1 days -3 hours')), 'waktu_keluar' => date('Y-m-d H:i:s', strtotime('-1 days -1 hours')), 'durasi_menit' => 120, 'biaya' => 14000, 'status' => 'Keluar'],
        ['id' => 95, 'plat_nomor' => 'B 5678 XYZ', 'tipe_kendaraan' => 'Motor', 'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-3 days -2 hours')), 'waktu_keluar' => date('Y-m-d H:i:s', strtotime('-3 days -1 hours')), 'durasi_menit' => 60, 'biaya' => 3000, 'status' => 'Keluar'],
    ]
);

function waktu_lalu_user($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}

function durasi_singkat_user($menit)
{
    $menit = (int) $menit;
    if ($menit < 60) return $menit . ' mnt';
    $jam = floor($menit / 60);
    $sisa = $menit % 60;
    return $jam . ' jam' . ($sisa > 0 ? ' ' . $sisa . ' mnt' : '');
}

$statusVerifBadge = [
    'Terverifikasi'         => 'bg-tertiary-fixed text-on-tertiary-fixed',
    'Menunggu Verifikasi'   => 'bg-secondary-fixed text-on-secondary-fixed-variant',
    'Ditolak'               => 'bg-error-container text-on-error-container',
];

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width pt-16 min-h-screen p-lg">
<div class="max-w-container-max mx-auto space-y-lg">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Halo, <?= htmlspecialchars($namaUser) ?></h2>
            <p class="text-body-lg text-on-surface-variant">Pantau kendaraan dan aktivitas parkir Anda di sini.</p>
        </div>
        <a href="daftar_kendaraan.php" class="flex items-center gap-2 px-lg py-3 rounded-xl bg-primary text-on-primary font-bold shadow-md hover:bg-primary/90 transition-all">
            <span class="material-symbols-outlined">add_circle</span> Daftarkan Kendaraan
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">directions_car</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kendaraan Terdaftar</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $totalKendaraanSaya ?></h3>
            <?php if ($kendaraanMenungguSaya > 0): ?>
            <p class="text-label-md text-primary mt-1"><?= (int) $kendaraanMenungguSaya ?> menunggu verifikasi</p>
            <?php endif; ?>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">local_parking</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Sedang Parkir</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $sedangParkirSaya ?> Kendaraan</h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">event_repeat</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kunjungan Bulan Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $kunjunganBulanIni ?> Kali</h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">payments</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Pengeluaran Bulan Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($pengeluaranBulanIni) ?></h3>
        </div>
    </div>

    <!-- Sedang parkir saat ini -->
    <?php if (!empty($parkirAktifSaya)): ?>
    <div class="bg-primary/5 border border-primary/20 rounded-xl p-lg">
        <h2 class="font-title-md text-title-md font-bold text-on-surface mb-md flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">directions_car_filled</span> Kendaraan Anda Sedang Parkir
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
            <?php foreach ($parkirAktifSaya as $p): ?>
            <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/10 flex justify-between items-center gap-md">
                <div>
                    <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($p['plat_nomor']) ?></p>
                    <p class="text-label-md text-on-surface-variant">
                        <?= htmlspecialchars($p['tipe_kendaraan']) ?> &bull; Slot <?= htmlspecialchars($p['kode_slot'] ?? '-') ?> &bull; <?= htmlspecialchars($p['nama_lantai'] ?? '-') ?>
                    </p>
                    <p class="text-label-md text-on-surface-variant mt-1">Masuk <?= waktu_lalu_user($p['waktu_masuk']) ?> &bull; Durasi <?= durasi_singkat_user($p['durasi_menit']) ?></p>
                </div>
                <a href="struk.php?id=<?= (int) $p['id'] ?>" target="_blank" class="shrink-0 inline-flex items-center gap-1 px-sm py-2 rounded-lg bg-primary/10 text-primary font-bold text-label-md hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span> Struk
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kendaraan saya + Riwayat parkir -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Kendaraan Saya</h2>
                <a href="daftar_kendaraan.php" class="text-primary font-bold text-body-md hover:underline">+ Tambah</a>
            </div>
            <?php if (empty($daftarKendaraanSaya)): ?>
                <p class="px-lg py-lg text-body-md text-on-surface-variant">Anda belum mendaftarkan kendaraan.</p>
            <?php else: ?>
            <div class="divide-y divide-outline-variant">
                <?php foreach ($daftarKendaraanSaya as $k): ?>
                <div class="px-lg py-md flex justify-between items-center gap-md">
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($k['plat_nomor']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($k['tipe']) ?> &bull; <?= htmlspecialchars($k['warna'] ?? '-') ?> &bull; <?= htmlspecialchars($k['kode_kendaraan'] ?? '-') ?></p>
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-label-md shrink-0 <?= $statusVerifBadge[$k['status_verifikasi']] ?? 'bg-surface-container text-on-surface' ?>">
                        <?= htmlspecialchars($k['status_verifikasi']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
            <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
                <h2 class="font-title-md text-title-md font-bold text-on-surface">Riwayat Parkir Terbaru</h2>
                <a href="riwayat.php" class="text-primary font-bold text-body-md hover:underline">Lihat Semua</a>
            </div>
            <?php if (empty($riwayatTerbaru)): ?>
                <p class="px-lg py-lg text-body-md text-on-surface-variant">Belum ada riwayat parkir.</p>
            <?php else: ?>
            <div class="divide-y divide-outline-variant">
                <?php foreach ($riwayatTerbaru as $r): ?>
                <div class="px-lg py-md flex justify-between items-start gap-md">
                    <div>
                        <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($r['plat_nomor']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($r['tipe_kendaraan']) ?> &bull; <?= durasi_singkat_user($r['durasi_menit']) ?></p>
                        <p class="text-label-md text-on-surface-variant"><?= waktu_lalu_user($r['waktu_masuk']) ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-body-md font-bold"><?= format_rupiah($r['biaya']) ?></p>
                        <a href="struk.php?id=<?= (int) $r['id'] ?>" target="_blank" class="text-label-md text-primary hover:underline">Lihat Struk</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>
<?php include 'includes/footer.php'; ?>