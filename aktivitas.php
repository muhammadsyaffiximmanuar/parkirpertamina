<?php
session_start();
require_once 'config.php';
require_login_page();

$activePage = 'aktivitas';
$pageTitle  = 'Log Aktivitas Sistem';
$searchPlaceholder = 'Cari log atau pengguna...';

/* ============ FILTER (via GET) ============ */
$q         = trim($_GET['q'] ?? '');
$kategori  = trim($_GET['kategori'] ?? 'Semua Aktivitas');
$role      = trim($_GET['role'] ?? 'Semua Role');
$halaman   = max((int) ($_GET['halaman'] ?? 1), 1);
$perHalaman = 5;
$offset    = ($halaman - 1) * $perHalaman;

// Peta label filter (yang ditampilkan di UI) -> nilai ENUM asli di kolom log_aktivitas.kategori
$kategoriMap = [
    'Login / Logout'       => 'Login/Logout',
    'Manajemen Kendaraan'  => 'Manajemen Kendaraan',
    'Perubahan Tarif'      => 'Perubahan Tarif',
    'Laporan'               => 'Laporan',
];

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(u.nama_lengkap LIKE ? OR la.aktivitas LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($kategori !== '' && $kategori !== 'Semua Aktivitas' && isset($kategoriMap[$kategori])) {
    $where[] = "la.kategori = ?";
    $params[] = $kategoriMap[$kategori];
}
if ($role !== '' && $role !== 'Semua Role') {
    $where[] = "r.nama_role = ?";
    $params[] = $role;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalLog = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM log_aktivitas la
     JOIN users u ON u.id = la.user_id
     JOIN roles r ON r.id = u.role_id
     $whereSql",
    $params, ['jml' => 5]
)['jml'];
$totalHalaman = max((int) ceil($totalLog / $perHalaman), 1);

$fallbackLog = [
    ['nama' => 'Andri Setiawan', 'email' => 'andri.s@pertamina.com', 'inisial' => 'AS', 'aktivitas' => 'Update Tarif Parkir VIP', 'warna' => 'blue', 'tanggal' => '2023-10-24', 'waktu' => '14:25:01', 'ip_address' => '182.253.112.42'],
    ['nama' => 'Budi Kusuma', 'email' => 'budi.k@pertamina.com', 'inisial' => 'BK', 'aktivitas' => 'Login Berhasil', 'warna' => 'green', 'tanggal' => '2023-10-24', 'waktu' => '13:10:55', 'ip_address' => '110.137.89.201'],
    ['nama' => 'Rina Marlina', 'email' => 'rina.m@pertamina.com', 'inisial' => 'RM', 'aktivitas' => 'Menghapus Data Kendaraan B 1234 ABC', 'warna' => 'red', 'tanggal' => '2023-10-24', 'waktu' => '11:45:12', 'ip_address' => '10.20.14.55'],
    ['nama' => 'Dedi Hermawan', 'email' => 'dedi.h@pertamina.com', 'inisial' => 'DH', 'aktivitas' => 'Download Laporan Bulanan (Sep 2023)', 'warna' => 'yellow', 'tanggal' => '2023-10-24', 'waktu' => '09:30:22', 'ip_address' => '182.253.114.10'],
    ['nama' => 'Andri Setiawan', 'email' => 'andri.s@pertamina.com', 'inisial' => 'AS', 'aktivitas' => 'Menambahkan Lantai 4 Section B', 'warna' => 'blue', 'tanggal' => '2023-10-23', 'waktu' => '16:50:41', 'ip_address' => '182.253.112.42'],
];

$daftarLogRaw = db_fetch_all(
    "SELECT u.nama_lengkap AS nama, u.email, la.aktivitas, la.kategori, la.created_at, la.ip_address
     FROM log_aktivitas la
     JOIN users u ON u.id = la.user_id
     JOIN roles r ON r.id = u.role_id
     $whereSql
     ORDER BY la.created_at DESC
     LIMIT $perHalaman OFFSET $offset",
    $params, $fallbackLog
);

// Turunkan 'inisial', 'warna', 'tanggal', 'waktu' di PHP karena kolom-kolom
// tersebut tidak ada di skema database (users tidak punya 'inisial',
// log_aktivitas tidak punya 'status_warna'/'tanggal'/'waktu' terpisah).
function warna_dari_kategori(string $kategori, string $aktivitas): string
{
    if (stripos($aktivitas, 'gagal') !== false || stripos($aktivitas, 'hapus') !== false) return 'red';
    return match ($kategori) {
        'Login/Logout'        => 'green',
        'Perubahan Tarif'     => 'yellow',
        'Manajemen Kendaraan' => 'blue',
        default                => 'blue',
    };
}
function inisial_dari_nama(string $nama): string
{
    $bagian = preg_split('/\s+/', trim($nama));
    $inisial = strtoupper(substr($bagian[0] ?? '', 0, 1) . substr($bagian[count($bagian) - 1] ?? '', 0, 1));
    return $inisial ?: '?';
}

$daftarLog = array_map(function ($log) {
    $sudahDiproses = array_key_exists('inisial', $log); // fallback dummy sudah punya field ini
    return [
        'nama'       => $log['nama'],
        'email'      => $log['email'],
        'inisial'    => $sudahDiproses ? $log['inisial'] : inisial_dari_nama($log['nama']),
        'aktivitas'  => $log['aktivitas'],
        'warna'      => $sudahDiproses ? $log['warna'] : warna_dari_kategori($log['kategori'] ?? '', $log['aktivitas']),
        'tanggal'    => $sudahDiproses ? $log['tanggal'] : date('Y-m-d', strtotime($log['created_at'])),
        'waktu'      => $sudahDiproses ? $log['waktu'] : date('H:i:s', strtotime($log['created_at'])),
        'ip_address' => $log['ip_address'],
    ];
}, $daftarLogRaw);

$warnaDotClass = [
    'green'  => 'bg-green-500',
    'blue'   => 'bg-blue-500',
    'yellow' => 'bg-yellow-500',
    'red'    => 'bg-error',
];

$totalGagalLogin = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM log_aktivitas WHERE aktivitas LIKE '%Gagal%'",
    [], ['jml' => 12]
)['jml'];

$ipUnikHariIni = db_fetch_one(
    "SELECT COUNT(DISTINCT ip_address) AS jml FROM log_aktivitas WHERE DATE(created_at) = CURDATE()",
    [], ['jml' => 84]
)['jml'];

include 'includes/head.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<main class="ml-sidebar-width mt-16 p-lg min-h-[calc(100vh-64px)]">

    <div class="mb-xl flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <nav class="flex items-center gap-xs text-on-surface-variant text-label-md mb-xs">
                <span>Manajemen</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-primary font-bold">Log Aktivitas</span>
            </nav>
            <h2 class="font-headline-lg text-headline-lg text-on-surface">Log Aktivitas Sistem</h2>
            <p class="text-body-md text-on-surface-variant mt-xs">Riwayat jejak audit aktivitas seluruh pengguna di dalam sistem management parkir.</p>
        </div>
        <div class="flex gap-sm">
            <a href="#" class="flex items-center gap-xs bg-white border border-outline-variant px-md py-sm rounded-lg hover:bg-surface-container-low transition-colors font-label-md text-on-surface">
                <span class="material-symbols-outlined">download</span> Ekspor CSV
            </a>
            <a href="#" class="flex items-center gap-xs bg-primary text-white px-md py-sm rounded-lg hover:opacity-90 transition-opacity font-label-md shadow-sm">
                <span class="material-symbols-outlined">print</span> Cetak Laporan
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="get" class="bg-white rounded-xl shadow-sm p-md mb-lg border border-surface-variant">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-md items-end">
            <div class="space-y-xs">
                <label class="text-label-md font-bold text-on-surface-variant block">Cari Pengguna / Aktivitas</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                    <input class="w-full pl-10 pr-4 py-2 border border-outline-variant rounded-lg focus:ring-1 focus:ring-primary text-body-md" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik kata kunci...">
                </div>
            </div>
            <div class="space-y-xs">
                <label class="text-label-md font-bold text-on-surface-variant block">Kategori Aktivitas</label>
                <select name="kategori" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-1 focus:ring-primary text-body-md bg-white">
                    <?php foreach (['Semua Aktivitas', 'Login / Logout', 'Manajemen Kendaraan', 'Perubahan Tarif', 'Laporan'] as $opt): ?>
                        <option <?= $kategori === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-xs">
                <label class="text-label-md font-bold text-on-surface-variant block">Role Pengguna</label>
                <select name="role" class="w-full px-4 py-2 border border-outline-variant rounded-lg focus:ring-1 focus:ring-primary text-body-md bg-white">
                    <?php foreach (['Semua Role', 'Super Admin', 'Operator', 'Security'] as $opt): ?>
                        <option <?= $role === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-sm">
                <button type="submit" class="flex-grow bg-secondary text-white py-2 rounded-lg hover:opacity-90 transition-opacity font-label-md">Terapkan Filter</button>
                <a href="aktivitas.php" class="p-2 border border-outline-variant rounded-lg hover:bg-surface-container transition-colors text-on-surface-variant" title="Reset">
                    <span class="material-symbols-outlined">restart_alt</span>
                </a>
            </div>
        </div>
    </form>

    <!-- Activity Table -->
    <div class="bg-white rounded-xl shadow-sm border border-surface-variant overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-surface-variant">
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider">Pengguna</th>
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider">Aktivitas</th>
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider">Tanggal</th>
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider">Waktu</th>
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider">Alamat IP</th>
                        <th class="px-lg py-md font-title-md text-on-surface-variant text-label-md uppercase tracking-wider text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-variant">
                <?php if (empty($daftarLog)): ?>
                    <tr><td colspan="6" class="px-lg py-xl text-center text-on-surface-variant">Tidak ada data log yang cocok dengan filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftarLog as $log): ?>
                    <tr class="hover:bg-surface-container transition-colors group">
                        <td class="px-lg py-md">
                            <div class="flex items-center gap-sm">
                                <div class="w-8 h-8 rounded-full bg-surface-container-highest text-on-surface-variant font-bold flex items-center justify-center text-label-md"><?= htmlspecialchars($log['inisial']) ?></div>
                                <div>
                                    <p class="font-bold text-on-surface text-body-md"><?= htmlspecialchars($log['nama']) ?></p>
                                    <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($log['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-lg py-md">
                            <div class="flex items-center gap-xs">
                                <span class="w-2 h-2 rounded-full <?= $warnaDotClass[$log['warna']] ?? 'bg-secondary' ?>"></span>
                                <span class="font-body-md text-on-surface"><?= htmlspecialchars($log['aktivitas']) ?></span>
                            </div>
                        </td>
                        <td class="px-lg py-md text-body-md text-on-surface"><?= date('d M Y', strtotime($log['tanggal'])) ?></td>
                        <td class="px-lg py-md text-body-md text-on-surface"><?= htmlspecialchars($log['waktu']) ?></td>
                        <td class="px-lg py-md text-body-md font-mono text-on-surface-variant"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td class="px-lg py-md text-right">
                            <button class="p-1 hover:bg-white rounded-md transition-colors opacity-0 group-hover:opacity-100 text-primary">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-lg py-md bg-surface-container-low flex items-center justify-between">
            <p class="text-body-md text-on-surface-variant">
                Menampilkan <span class="font-bold"><?= $totalLog > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $perHalaman, $totalLog) ?></span>
                dari <span class="font-bold"><?= number_format($totalLog, 0, ',', '.') ?></span> entri
            </p>
            <div class="flex items-center gap-xs">
                <?php
                $qsBase = $_GET;
                function buildLink($qsBase, $h) { $qsBase['halaman'] = $h; return 'aktivitas.php?' . http_build_query($qsBase); }
                ?>
                <a href="<?= buildLink($qsBase, max($halaman - 1, 1)) ?>" class="p-2 hover:bg-surface-container rounded-lg text-on-surface-variant <?= $halaman <= 1 ? 'pointer-events-none opacity-30' : '' ?>">
                    <span class="material-symbols-outlined">chevron_left</span>
                </a>
                <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
                    <a href="<?= buildLink($qsBase, $i) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-label-md font-bold <?= $i === $halaman ? 'bg-primary text-white' : 'hover:bg-surface-container text-on-surface-variant' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="<?= buildLink($qsBase, min($halaman + 1, $totalHalaman)) ?>" class="p-2 hover:bg-surface-container rounded-lg text-on-surface-variant <?= $halaman >= $totalHalaman ? 'pointer-events-none opacity-30' : '' ?>">
                    <span class="material-symbols-outlined">chevron_right</span>
                </a>
            </div>
        </div>
    </div>

    <!-- System Stats Footer -->
    <div class="mt-xl grid grid-cols-1 sm:grid-cols-3 gap-lg">
        <div class="bg-white p-lg rounded-xl shadow-sm border border-surface-variant flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-primary-container text-white flex items-center justify-center">
                <span class="material-symbols-outlined">lock_reset</span>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant">Total Upaya Gagal</p>
                <h4 class="text-headline-lg font-bold text-primary leading-tight"><?= (int) $totalGagalLogin ?></h4>
            </div>
        </div>
        <div class="bg-white p-lg rounded-xl shadow-sm border border-surface-variant flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center">
                <span class="material-symbols-outlined">security</span>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant">IP Unik Hari Ini</p>
                <h4 class="text-headline-lg font-bold text-on-surface leading-tight"><?= (int) $ipUnikHariIni ?></h4>
            </div>
        </div>
        <div class="bg-white p-lg rounded-xl shadow-sm border border-surface-variant flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center">
                <span class="material-symbols-outlined">info</span>
            </div>
            <div>
                <p class="text-label-md text-on-surface-variant">Total Log Tercatat</p>
                <h4 class="text-headline-lg font-bold text-on-surface leading-tight"><?= number_format($totalLog, 0, ',', '.') ?></h4>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
