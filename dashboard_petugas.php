<?php
/**
 * dashboard_petugas.php
 * Dashboard untuk role: Officer & Security
 * Fokus: operasional harian/shift — status slot live, kendaraan sedang parkir,
 * booking pelanggan yang menunggu kedatangan, dan ringkasan transaksi hari ini.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OFFICER, ROLE_SECURITY]);

expire_booking_lewat_waktu(); // bebaskan slot dari booking yang sudah lewat waktu & tidak check-in

$activePage = 'beranda';
$pageTitle  = 'Dashboard Petugas';
$namaUser   = $_SESSION['nama_lengkap'] ?? '';
$userId     = $_SESSION['user_id'] ?? 0;

/* ============ RINGKASAN SHIFT HARI INI ============ */
$masukHariIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE DATE(waktu_masuk) = CURDATE()",
    [], ['jml' => 18]
)['jml'];

$keluarHariIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi WHERE status = 'Keluar' AND DATE(waktu_keluar) = CURDATE()",
    [], ['jml' => 11]
)['jml'];

$slotTersediaSaatIni = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM slot_parkir WHERE status = 'Tersedia'",
    [], ['jml' => 21]
)['jml'];

$pendapatanSayaHariIni = db_fetch_one(
    "SELECT IFNULL(SUM(biaya),0) AS jumlah FROM transaksi WHERE petugas_id = :uid AND DATE(waktu_masuk) = CURDATE()",
    ['uid' => $userId], ['jumlah' => 0]
)['jumlah'];

/* ============ BOOKING PELANGGAN MENUNGGU KEDATANGAN ============ */
// Booking berstatus 'aktif' (belum kedaluwarsa, belum check-in), diurutkan
// dari waktu booking paling dekat. Petugas bisa langsung "Tandai Sudah Datang"
// dari sini -> memanggil aksi_tandai_kedatangan.php.
$bookingMenunggu = db_fetch_all(
    "SELECT b.id, b.plat_nomor, b.nama_pemesan, b.waktu_booking,
            s.kode_slot, l.nama_lantai
     FROM booking b
     JOIN slot_parkir s ON s.id = b.slot_id
     JOIN lantai l ON l.id = s.area_id
     WHERE b.status = 'aktif'
     ORDER BY b.waktu_booking ASC
     LIMIT 20",
    [],
    []
);

/* ============ KENDARAAN SEDANG PARKIR (status Masuk) ============ */
/* ============ KENDARAAN SEDANG PARKIR (status Masuk) ============ */
$sedangParkir = db_fetch_all(
    "SELECT t.id, k.plat_nomor, k.tipe AS tipe_kendaraan, sp.kode_slot, l.nama_lantai,
            t.waktu_masuk,
            TIMESTAMPDIFF(MINUTE, t.waktu_masuk, NOW()) AS durasi_menit
     FROM transaksi t
     JOIN kendaraan k ON k.id = t.kendaraan_id
     JOIN slot_parkir sp ON sp.id = t.slot_id
     JOIN lantai l ON l.id = sp.area_id
     WHERE t.status = 'Masuk'
     ORDER BY t.waktu_masuk DESC LIMIT 8",
    [],
    [
        ['id' => 1, 'plat_nomor' => 'B 1234 ABC', 'tipe_kendaraan' => 'Mobil', 'kode_slot' => 'A-01', 'nama_lantai' => 'Lantai 1', 'waktu_masuk' => date('Y-m-d H:i:s', strtotime('-40 minutes')), 'durasi_menit' => 40],
    ]
);

/* ============ STATUS SLOT PER AREA (live grid) ============ */
// Catatan: sebelumnya JOIN ke tabel `area` yang tidak ada di database
// (skema asli memakai tabel `lantai`). Sudah diperbaiki di sini.
$slotPerArea = db_fetch_all(
    "SELECT l.nama_lantai AS nama_area, sp.kode_slot, sp.status
     FROM slot_parkir sp JOIN lantai l ON l.id = sp.area_id
     ORDER BY l.id, sp.kode_slot",
    [],
    [
        ['nama_area' => 'Section A - Lantai 1', 'kode_slot' => 'A-01', 'status' => 'Terisi'],
        ['nama_area' => 'Section A - Lantai 1', 'kode_slot' => 'A-02', 'status' => 'Tersedia'],
    ]
);
$slotGrouped = [];
foreach ($slotPerArea as $s) {
    $slotGrouped[$s['nama_area']][] = $s;
}

$slotColor = [
    'Tersedia' => 'bg-tertiary/15 text-tertiary border-tertiary/30',
    'Terisi'   => 'bg-error-container text-on-error-container border-error/20',
    'Dipesan'  => 'bg-secondary/15 text-secondary border-secondary/30',
];

function durasi_singkat($menit)
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

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-background">Halo, <?= htmlspecialchars($namaUser) ?></h2>
            <p class="text-body-lg text-on-surface-variant">Ringkasan operasional shift Anda hari ini.</p>
        </div>
    </div>

    <!-- Notifikasi hasil aksi (tandai kedatangan) -->
    <div id="notif-booking" class="hidden rounded-lg px-md py-sm text-body-md"></div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">login</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kendaraan Masuk Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $masukHariIni ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-base"><span class="material-symbols-outlined">logout</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Kendaraan Keluar Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $keluarHariIni ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-base"><span class="material-symbols-outlined">event_available</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Slot Tersedia Saat Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= (int) $slotTersediaSaatIni ?></h3>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl shadow-soft border border-outline-variant/10">
            <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-base"><span class="material-symbols-outlined">payments</span></div>
            <p class="text-label-md text-on-surface-variant mb-xs">Transaksi Saya Hari Ini</p>
            <h3 class="font-title-md text-title-md font-bold"><?= format_rupiah($pendapatanSayaHariIni) ?></h3>
        </div>
    </div>

    <!-- Booking pelanggan menunggu kedatangan -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Booking Menunggu Kedatangan</h2>
            <span class="text-label-md text-on-surface-variant"><?= count($bookingMenunggu) ?> booking aktif</span>
        </div>
        <?php if (empty($bookingMenunggu)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Tidak ada booking pelanggan yang aktif saat ini.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low">
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Plat Nomor</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Pemesan</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Slot</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Lantai</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Waktu Booking</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Verifikasi Kode</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                <?php foreach ($bookingMenunggu as $b): ?>
                    <tr class="hover:bg-surface-container-lowest transition-colors" id="baris-booking-<?= (int) $b['id'] ?>">
                        <td class="px-lg py-sm text-body-md font-bold"><?= htmlspecialchars($b['plat_nomor']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($b['nama_pemesan'] ?: '-') ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($b['kode_slot']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($b['nama_lantai']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars(date('d M Y H:i', strtotime($b['waktu_booking']))) ?></td>
                        <td class="px-lg py-sm">
                            <input type="text" maxlength="6" placeholder="Kode dari pelanggan"
                                   class="input-kode-booking uppercase tracking-wider w-32 px-2 py-1.5 border border-outline-variant rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 outline-none">
                        </td>
                        <td class="px-lg py-sm">
                            <button data-booking-id="<?= (int) $b['id'] ?>" class="btn-tandai-datang inline-flex items-center gap-1 bg-tertiary text-on-tertiary font-bold text-label-md px-3 py-1.5 rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap">
                                <span class="material-symbols-outlined text-[16px]">check_circle</span> Tandai Sudah Datang
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Live slot grid -->
    <div class="bg-surface-container-lowest p-lg rounded-xl shadow-soft border border-outline-variant/10">
        <div class="flex justify-between items-center mb-lg">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Status Slot Parkir (Live)</h2>
            <div class="flex items-center gap-md text-label-md">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-tertiary"></span> Tersedia</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-error"></span> Terisi</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-secondary"></span> Dipesan</span>
            </div>
        </div>
        <?php foreach ($slotGrouped as $namaArea => $slots): ?>
        <div class="mb-lg last:mb-0">
            <p class="text-body-md font-bold text-on-surface-variant mb-sm"><?= htmlspecialchars($namaArea) ?></p>
            <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 gap-2">
                <?php foreach ($slots as $s): ?>
                <div class="aspect-square rounded-lg border flex items-center justify-center text-[11px] font-bold <?= $slotColor[$s['status']] ?? 'bg-surface-container text-on-surface border-outline-variant' ?>" title="<?= htmlspecialchars($s['status']) ?>">
                    <?= htmlspecialchars($s['kode_slot']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Kendaraan sedang parkir -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/10 overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center">
            <h2 class="font-title-md text-title-md font-bold text-on-surface">Kendaraan Sedang Parkir</h2>
            <a href="riwayat.php" class="text-primary font-bold text-body-md hover:underline">Lihat Semua Transaksi</a>
        </div>
        <?php if (empty($sedangParkir)): ?>
            <p class="px-lg py-lg text-body-md text-on-surface-variant">Tidak ada kendaraan yang sedang parkir saat ini.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low">
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Plat Nomor</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Tipe</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Slot</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Lantai</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Durasi</th>
                        <th class="px-lg py-sm text-label-md uppercase text-outline">Struk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                <?php foreach ($sedangParkir as $p): ?>
                    <tr class="hover:bg-surface-container-lowest transition-colors">
                        <td class="px-lg py-sm text-body-md font-bold"><?= htmlspecialchars($p['plat_nomor']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($p['tipe_kendaraan']) ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($p['kode_slot'] ?? '-') ?></td>
                        <td class="px-lg py-sm text-body-md"><?= htmlspecialchars($p['nama_lantai'] ?? '-') ?></td>
                        <td class="px-lg py-sm text-body-md"><?= durasi_singkat($p['durasi_menit']) ?></td>
                        <td class="px-lg py-sm">
                            <a href="struk.php?id=<?= (int) $p['id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-primary font-bold text-label-md hover:underline">
                                <span class="material-symbols-outlined text-[16px]">receipt_long</span> Cetak
                            </a>
                        </td>
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
const notifBooking = document.getElementById('notif-booking');

function tampilkanNotifBooking(pesan, sukses) {
    notifBooking.textContent = pesan;
    notifBooking.classList.remove('hidden', 'bg-tertiary-container', 'text-on-tertiary-container', 'bg-error-container', 'text-error');
    notifBooking.classList.add(sukses ? 'bg-tertiary-container' : 'bg-error-container', sukses ? 'text-on-tertiary-container' : 'text-error');
    notifBooking.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.querySelectorAll('.btn-tandai-datang').forEach(btn => {
    btn.addEventListener('click', async () => {
        const bookingId = btn.dataset.bookingId;
        const baris = document.getElementById('baris-booking-' + bookingId);
        const inputKode = baris ? baris.querySelector('.input-kode-booking') : null;
        const kodeBooking = inputKode ? inputKode.value.trim() : '';

        if (!kodeBooking) {
            tampilkanNotifBooking('Masukkan kode booking dari pelanggan terlebih dahulu.', false);
            if (inputKode) inputKode.focus();
            return;
        }
        if (!confirm('Tandai kendaraan ini sudah datang & masuk area parkir?')) return;
        btn.disabled = true;
        try {
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('kode_booking', kodeBooking);
            const res = await fetch('aksi_tandai_kedatangan.php', { method: 'POST', body: formData });
            const hasil = await res.json();
            tampilkanNotifBooking(hasil.message, hasil.success);
            if (hasil.success) {
                setTimeout(() => window.location.reload(), 900);
            } else {
                btn.disabled = false;
            }
        } catch (err) {
            tampilkanNotifBooking('Tidak dapat terhubung ke server. Coba lagi.', false);
            btn.disabled = false;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>