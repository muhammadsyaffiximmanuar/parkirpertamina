<?php
/**
 * landing.php
 * Halaman publik (tanpa login) untuk Sistem Manajemen Parkir Gedung Pertamina.
 * Menampilkan ringkasan ketersediaan, okupansi, dan info umum yang diambil
 * dari database (via helper db_fetch_one/db_fetch_all di config.php),
 * dengan data contoh sebagai fallback jika database belum tersedia.
 *
 * FITUR KOMENTAR:
 * - Pengunjung bisa menulis ulasan sendiri (nama, rating 1-5, isi ulasan).
 * - Disimpan ke tabel `komentar` (lihat SQL pembuatan tabel di bawah).
 * - Dilindungi token CSRF, honeypot anti-bot, prepared statement, dan escaping XSS.
 *
 * ---------------------------------------------------------------------------
 * JALANKAN SQL INI SEKALI DI DATABASE SEBELUM MEMAKAI FITUR KOMENTAR:
 *
 * CREATE TABLE komentar (
 *   id           INT AUTO_INCREMENT PRIMARY KEY,
 *   nama         VARCHAR(80)  NOT NULL,
 *   rating       TINYINT      NOT NULL DEFAULT 5,
 *   isi          TEXT         NOT NULL,
 *   status       ENUM('Publik','Disembunyikan') NOT NULL DEFAULT 'Publik',
 *   ip_address   VARCHAR(45)  DEFAULT NULL,
 *   dibuat_pada  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_status_id (status, id)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * ---------------------------------------------------------------------------
 */
session_start();
require_once 'config.php';

/* URL halaman ini sendiri — aman meski file di-rename atau dipindah ke subfolder */
$selfUrl = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');

/* ============================================================
   PEMROSESAN FORM KOMENTAR
   Harus berada di atas semua output HTML karena memakai header()
   (pola Post/Redirect/Get: refresh halaman tidak mengirim ulang komentar).
   ============================================================ */
if (empty($_SESSION['csrf_komentar'])) {
    $_SESSION['csrf_komentar'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'komentar') {
    $nama   = trim($_POST['nama'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 5);

    if (!hash_equals($_SESSION['csrf_komentar'], $_POST['csrf'] ?? '')) {
        $_SESSION['komentar_error'] = 'Sesi tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } elseif (!empty($_POST['website'])) {            // honeypot: hanya terisi oleh bot
        $_SESSION['komentar_error'] = 'Komentar tidak dapat diproses.';
    } elseif ($nama === '' || mb_strlen($nama) > 80) {
        $_SESSION['komentar_error'] = 'Nama wajib diisi (maksimal 80 karakter).';
    } elseif (mb_strlen($isi) < 10 || mb_strlen($isi) > 1000) {
        $_SESSION['komentar_error'] = 'Ulasan minimal 10 karakter dan maksimal 1000 karakter.';
    } elseif ($rating < 1 || $rating > 5) {
        $_SESSION['komentar_error'] = 'Rating harus antara 1 sampai 5 bintang.';
    } elseif (!$pdo) {
        $_SESSION['komentar_error'] = 'Database sedang tidak tersedia, komentar belum bisa disimpan.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO komentar (nama, rating, isi, ip_address) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$nama, $rating, $isi, $_SERVER['REMOTE_ADDR'] ?? null]);
            $_SESSION['komentar_sukses'] = 'Terima kasih! Ulasan Anda sudah tersimpan.';
        } catch (PDOException $e) {
            $_SESSION['komentar_error'] = 'Gagal menyimpan ulasan. Silakan coba beberapa saat lagi.';
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '#ulasan');
    exit;
}

/* Ambil pesan notifikasi sekali pakai (flash message) */
$komentarError  = $_SESSION['komentar_error']  ?? '';
$komentarSukses = $_SESSION['komentar_sukses'] ?? '';
unset($_SESSION['komentar_error'], $_SESSION['komentar_sukses']);

/* Jika sudah login, tombol "Masuk" diarahkan langsung ke dashboard sesuai role */
$sudahLogin  = !empty($_SESSION['user_id']);
$loginTarget = $sudahLogin ? dashboard_url_for_role($_SESSION['role'] ?? '') : 'login.html';
$loginLabel  = $sudahLogin ? 'Ke Dashboard' : 'Masuk';

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
$persenTersedia = $slotTotal > 0 ? round(($slotTersedia / $slotTotal) * 100, 1) : 0;

/* ============ KENDARAAN TERLAYANI BULAN INI (analog "Sold") ============ */
$kendaraanTerlayani = db_fetch_one(
    "SELECT COUNT(*) AS jml FROM transaksi
     WHERE MONTH(waktu_masuk) = MONTH(CURDATE()) AND YEAR(waktu_masuk) = YEAR(CURDATE())",
    [], ['jml' => 12482]
)['jml'];

/* ============ OKUPANSI PER LANTAI ============ */
$okupansiLantai = db_fetch_all(
    "SELECT nama_lantai, kapasitas, total_slot_terdata, slot_terisi, okupansi_persen FROM view_okupansi_lantai",
    [],
    [
        ['nama_lantai' => 'Lantai 1', 'kapasitas' => 120, 'total_slot_terdata' => 24, 'slot_terisi' => 15, 'okupansi_persen' => 62.5],
        ['nama_lantai' => 'Lantai 2', 'kapasitas' => 100, 'total_slot_terdata' => 24, 'slot_terisi' => 14, 'okupansi_persen' => 58.3],
        ['nama_lantai' => 'Lantai 3 / Area Terbuka', 'kapasitas' => 80, 'total_slot_terdata' => 12, 'slot_terisi' => 5, 'okupansi_persen' => 41.7],
    ]
);
$okupansiBadge = function ($persen) {
    if ($persen >= 85) return ['label' => 'Padat',   'class' => 'bg-error-container text-on-error-container'];
    if ($persen <= 30) return ['label' => 'Lengang',  'class' => 'bg-secondary-fixed text-on-secondary-fixed-variant'];
    return ['label' => 'Normal', 'class' => 'bg-tertiary-fixed text-on-tertiary-fixed-variant'];
};

/* ============ GRAFIK: TREN OKUPANSI PER JAM ============ */
$okupansiJam = db_fetch_all(
    "SELECT jam_label, persentase FROM okupansi_per_jam WHERE tanggal = CURDATE() ORDER BY id ASC",
    [],
    [
        ['jam_label' => '00:00', 'persentase' => 20], ['jam_label' => '03:00', 'persentase' => 15],
        ['jam_label' => '06:00', 'persentase' => 45], ['jam_label' => '09:00', 'persentase' => 95],
        ['jam_label' => '12:00', 'persentase' => 88], ['jam_label' => '15:00', 'persentase' => 92],
        ['jam_label' => '18:00', 'persentase' => 70], ['jam_label' => '21:00', 'persentase' => 35],
    ]
);
$labelJam    = array_column($okupansiJam, 'jam_label');
$dataJam     = array_map('floatval', array_column($okupansiJam, 'persentase'));
$jamTersibuk = !empty($okupansiJam) ? $okupansiJam[array_search(max($dataJam), $dataJam)]['jam_label'] : '09:00';
$okupansiPuncak  = !empty($dataJam) ? max($dataJam) : 95;
$okupansiRataJam = !empty($dataJam) ? round(array_sum($dataJam) / count($dataJam), 1) : 62.3;

/* ============ KATEGORI KENDARAAN (jumlah slot per tipe area, jika ada) ============ */
$kategoriKendaraan = db_fetch_all(
    "SELECT tipe, COUNT(*) AS jml FROM kendaraan GROUP BY tipe",
    [],
    [['tipe' => 'Mobil', 'jml' => 620], ['tipe' => 'Motor', 'jml' => 800], ['tipe' => 'Bus/Truk', 'jml' => 30]]
);

/* ============ DAFTAR & STATISTIK KOMENTAR ============ */
$daftarKomentar = db_fetch_all(
    "SELECT nama, rating, isi, dibuat_pada FROM komentar
     WHERE status = 'Publik' ORDER BY id DESC LIMIT 6",
    [],
    [
        ['nama' => 'Andra R.',  'rating' => 5, 'isi' => 'Slot VIP selalu tersedia sesuai jadwal meeting, notifikasi masuk juga cepat sampai ke satpam.', 'dibuat_pada' => date('Y-m-d H:i:s')],
        ['nama' => 'Dewi P.',   'rating' => 5, 'isi' => 'Verifikasi kendaraan tamu jadi lebih cepat sejak pakai sistem ini. Petugas loket juga sigap.',   'dibuat_pada' => date('Y-m-d H:i:s')],
        ['nama' => 'Hendra S.', 'rating' => 4, 'isi' => 'Sudah cukup baik, hanya saja Lantai 1 sering padat jam 9 pagi. Semoga bisa ditambah slotnya.',   'dibuat_pada' => date('Y-m-d H:i:s')],
    ]
);
$statKomentar = db_fetch_one(
    "SELECT COUNT(*) AS jml, ROUND(AVG(rating), 1) AS rata FROM komentar WHERE status = 'Publik'",
    [], ['jml' => 1860, 'rata' => 4.8]
);
$jumlahUlasan = (int) ($statKomentar['jml'] ?: 0);
$rataRating   = (float) ($statKomentar['rata'] ?: 0);

/* Helper tampilan bintang & inisial avatar */
$bintang = function ($n) {
    $n = max(0, min(5, (int) round($n)));
    return str_repeat('&#9733;', $n) . str_repeat('&#9734;', 5 - $n);
};
$inisial = function ($nama) {
    $p = preg_split('/\s+/', trim($nama));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (isset($p[1]) ? mb_substr($p[1], 0, 1) : ''));
};
$warnaAvatar = ['bg-primary text-on-primary', 'bg-secondary text-on-secondary', 'bg-tertiary text-on-tertiary'];

/* ============ VIDEO TUR AREA PARKIR ============ */
/* TODO: ganti $videoTurUrl dengan URL/path video tur parkir kantor Pertamina yang asli
   (misal: 'assets/video/tur-parkir-pertamina.mp4' atau link YouTube/embed lain).
   Untuk sekarang dipakai video contoh supaya tombol play sudah berfungsi. */
$videoTurUrl = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';

/* ============ TARIF AKTIF (ringkasan untuk landing page) ============ */
$tarifRingkas = db_fetch_all(
    "SELECT tipe_kendaraan, tarif_per_jam FROM tarif WHERE status = 'Aktif' ORDER BY tarif_per_jam ASC",
    [],
    [
        ['tipe_kendaraan' => 'Motor', 'tarif_per_jam' => 3000],
        ['tipe_kendaraan' => 'Mobil', 'tarif_per_jam' => 7000],
        ['tipe_kendaraan' => 'Bus/Truk', 'tarif_per_jam' => 25000],
    ]
);
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistem Manajemen Parkir Gedung Pertamina</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script id="tailwind-config">try{
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        "colors": {
          "outline-variant": "#e9bcb6", "secondary": "#3a5f94", "on-background": "#1a1c1c",
          "inverse-primary": "#ffb4aa", "secondary-container": "#9fc2fe", "secondary-fixed-dim": "#a7c8ff",
          "surface-container-highest": "#e2e2e2", "on-secondary-fixed": "#001b3c", "on-primary-fixed-variant": "#930007",
          "background": "#f9f9f9", "on-surface": "#1a1c1c", "on-tertiary-fixed": "#112000",
          "surface-bright": "#f9f9f9", "surface-tint": "#c0000c", "primary-fixed-dim": "#ffb4aa",
          "error-container": "#ffdad6", "on-secondary-container": "#294f83", "on-tertiary": "#ffffff",
          "outline": "#936e69", "on-secondary": "#ffffff", "on-tertiary-fixed-variant": "#304f00",
          "on-secondary-fixed-variant": "#1f477b", "on-surface-variant": "#5e3f3b", "on-primary-container": "#fff5f3",
          "surface-container": "#eeeeee", "surface-dim": "#dadada", "primary-fixed": "#ffdad5",
          "surface": "#f9f9f9", "tertiary": "#3e6300", "on-error-container": "#93000a",
          "tertiary-fixed-dim": "#97d93a", "inverse-surface": "#2f3131", "surface-variant": "#e2e2e2",
          "tertiary-fixed": "#b2f655", "surface-container-low": "#f3f3f3", "primary-container": "#e30613",
          "on-error": "#ffffff", "secondary-fixed": "#d5e3ff", "surface-container-high": "#e8e8e8",
          "inverse-on-surface": "#f1f1f1", "error": "#ba1a1a", "primary": "#b5000b",
          "surface-container-lowest": "#ffffff", "tertiary-container": "#507e00", "on-primary": "#ffffff",
          "on-tertiary-container": "#eaffc8", "on-primary-fixed": "#410001"
        },
        "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
        "spacing": { "md": "16px", "container-max": "1440px", "xs": "4px", "lg": "24px", "sm": "12px", "base": "8px", "xl": "32px", "sidebar-width": "260px" },
        "fontFamily": {
          "display-lg": ["Inter"], "body-md": ["Inter"], "headline-lg": ["Inter"],
          "title-md": ["Inter"], "headline-lg-mobile": ["Inter"], "body-lg": ["Inter"], "label-md": ["Inter"]
        },
        "fontSize": {
          "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
          "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
          "headline-lg": ["32px", {"lineHeight": "40px", "fontWeight": "600"}],
          "title-md": ["18px", {"lineHeight": "24px", "fontWeight": "600"}],
          "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
          "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
          "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.5px", "fontWeight": "500"}]
        }
      }
    }
  }
}catch(_e){}</script>
<style>
  .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
  html{scroll-behavior:smooth;}
  .bg-grid{
    background-image: linear-gradient(rgba(181,0,11,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(181,0,11,.05) 1px, transparent 1px);
    background-size: 36px 36px;
  }
  .weave-thread{width:100%;height:20px;display:block;}
</style>
</head>
<body class="bg-background text-on-background">

<?php if (!$pdo): ?>
<div class="bg-error-container text-on-error-container text-center text-label-md py-2">
  Koneksi database tidak tersedia &mdash; halaman ini menampilkan data contoh (fallback).
</div>
<?php endif; ?>

<!-- ============ HEADER ============ -->
<header class="sticky top-0 z-50 bg-surface-container-lowest/95 backdrop-blur border-b border-outline-variant/40">
  <div class="max-w-container-max mx-auto px-lg py-3 flex items-center justify-between gap-lg">
    <a href="<?= $selfUrl ?>" class="flex items-center gap-3 shrink-0">
      <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCOG5IdYEQA-m7Pd_C7l_qHDHG5WAULIExzPanS0779guNt_2DaM74EUsFRfV2N4SO-fYdihO3SpXyoSOhWwYafXX0f4oiiODNsYyYu25eyO9IrvgvrSRuWz1C43EEluwSftubiARd1HBl5G2WJSKgIkUMVGEdgr8Ra6aN6GJds4ZDw0eVC7P2lPwX2yBkWPhX5O8w6MBb6f5Y6fP-PYcbMGsfi_ioRxStkEN86sQcHxURjpIRanMI-eRqeYExGtxh38t72JeG06qM" alt="Logo Pertamina" class="h-9 w-auto object-contain">
      <div class="leading-tight hidden sm:block">
        <p class="font-title-md text-title-md font-bold text-on-background">Parkir Gedung Pertamina</p>
        <p class="text-label-md text-on-surface-variant">Sistem Manajemen Infrastruktur Parkir</p>
      </div>
    </a>
    <nav class="hidden lg:flex items-center gap-lg text-body-md font-medium text-on-surface-variant">
      <a href="#fasilitas" class="hover:text-primary transition-colors">Fasilitas</a>
      <a href="#ketersediaan" class="hover:text-primary transition-colors">Ketersediaan</a>
      <a href="ketersediaan.php" class="hover:text-primary transition-colors">Cek Slot &amp; Tarif</a>
      <a href="#tarif" class="hover:text-primary transition-colors">Tren Okupansi</a>
      <a href="#ulasan" class="hover:text-primary transition-colors">Ulasan</a>
      <a href="#bantuan" class="hover:text-primary transition-colors">Bantuan</a>
    </nav>
    <div class="flex items-center gap-sm shrink-0">
      <?php if ($sudahLogin): ?>
        <span class="hidden md:inline text-label-md text-on-surface-variant mr-1">Halo, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?></span>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($loginTarget) ?>" class="flex items-center gap-2 px-lg py-2.5 rounded-lg bg-primary text-on-primary font-bold text-body-md shadow-sm hover:bg-primary/90 active:scale-[0.98] transition-all">
        <span class="material-symbols-outlined text-[18px]"><?= $sudahLogin ? 'dashboard' : 'login' ?></span>
        <?= htmlspecialchars($loginLabel) ?>
      </a>
    </div>
  </div>
</header>

<!-- ============ HERO: Text + Gambar ============ -->
<section class="relative overflow-hidden bg-grid">
  <img src="pertamina-hq-bg.png" alt="" aria-hidden="true"
       class="absolute inset-0 w-full h-full object-cover object-center opacity-[9] pointer-events-none select-none">
  <div class="absolute inset-0 bg-gradient-to-b from-background/60 via-background/85 to-background pointer-events-none"></div>
  <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
  <div class="absolute -bottom-[10%] -right-[10%] w-[50%] h-[50%] bg-secondary/5 rounded-full blur-[150px] pointer-events-none"></div>

  <div class="relative max-w-container-max mx-auto px-lg py-xl md:py-[80px] grid grid-cols-1 items-center">
    <div class="max-w-[640px]">
      <span class="inline-flex items-center gap-2 px-md py-1.5 rounded-full border border-primary/25 bg-primary/5 text-primary text-label-md font-bold uppercase tracking-wider mb-lg">
        <span class="w-2 h-2 rounded-full bg-primary"></span> Live &middot; Termonitor 24 Jam
      </span>
      <h1 class="font-headline-lg text-[40px] md:text-display-lg leading-[1.05] font-bold text-on-background mb-lg">
        Parkir tertata,<br><span class="text-primary">terpantau real-time.</span>
      </h1>
      <p class="text-body-lg text-on-surface-variant max-w-[460px] mb-xl">
        Sistem Manajemen Parkir Gedung Pertamina mengatur ketersediaan slot, tarif, dan keamanan area parkir HQ secara terpusat &mdash; untuk karyawan, tamu, dan petugas lapangan.
      </p>
      <div class="flex flex-wrap items-center gap-md">
        <a href="<?= htmlspecialchars($loginTarget) ?>" class="flex items-center gap-2 px-xl py-3 rounded-xl bg-primary text-on-primary font-bold text-body-lg shadow-lg shadow-primary/20 hover:bg-primary/90 active:scale-[0.98] transition-all">
          <span class="material-symbols-outlined">badge</span> <?= $sudahLogin ? 'Ke Dashboard Saya' : 'Masuk sebagai Staf/Admin' ?>
        </a>
        <a href="#ketersediaan" class="flex items-center gap-2 px-xl py-3 rounded-xl border-2 border-outline/30 text-on-background font-bold text-body-lg hover:border-primary hover:text-primary transition-all">
          <span class="material-symbols-outlined">event_available</span> Cek Ketersediaan
        </a>
      </div>
      <div class="flex flex-wrap gap-xl mt-xl pt-lg border-t border-outline-variant/60">
        <div><p class="font-title-md text-title-md font-bold text-on-background"><?= $totalArea ?> Area</p><p class="text-label-md text-on-surface-variant uppercase">Terintegrasi</p></div>
        <div><p class="font-title-md text-title-md font-bold text-on-background"><?= number_format($totalKendaraan, 0, ',', '.') ?></p><p class="text-label-md text-on-surface-variant uppercase">Kendaraan Terdaftar</p></div>
        <div><p class="font-title-md text-title-md font-bold text-on-background"><?= number_format($rataRating, 1, ',', '.') ?>/5</p><p class="text-label-md text-on-surface-variant uppercase">Kepuasan Pengguna</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ Foto Area Parkir (di bawah hero) ============ -->
<section class="max-w-container-max mx-auto px-lg py-lg md:py-xl">
  <div class="relative">
    <div class="absolute -inset-4 bg-gradient-to-br from-primary/20 via-primary/5 to-secondary/15 rounded-[2rem] blur-2xl -z-10"></div>
    <div class="relative rounded-2xl overflow-hidden shadow-2xl h-[300px] sm:h-[420px] lg:h-[560px]">
      <img src="parkir-kantor-pertamina.png" alt="Area parkir kantor Pertamina" class="w-full h-full object-cover">
      <div class="absolute inset-0 bg-gradient-to-t from-primary-fixed/40 via-transparent to-black/5"></div>
      <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/15"></div>
      <div class="absolute top-lg left-lg inline-flex items-center gap-1.5 bg-black/45 backdrop-blur-sm rounded-full px-sm py-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-error animate-pulse"></span>
        <span class="text-label-md font-bold text-white uppercase tracking-wider">Live CCTV</span>
      </div>
    </div>
    <!-- Informasi: Availability Transaksi (live, dari database) -->
    <div class="absolute -bottom-8 left-6 md:left-lg bg-surface-container-lowest rounded-xl shadow-xl border border-outline-variant/30 p-lg w-[260px]">
      <div class="flex items-center gap-2 mb-sm">
        <span class="w-2 h-2 rounded-full bg-tertiary animate-pulse"></span>
        <p class="text-label-md font-bold text-tertiary uppercase tracking-wider">Tersedia Sekarang</p>
      </div>
      <p class="font-headline-lg text-[36px] font-bold text-on-background leading-none"><?= number_format($slotTersedia, 0, ',', '.') ?></p>
      <p class="text-label-md text-on-surface-variant mt-1">slot dari <?= number_format($slotTotal, 0, ',', '.') ?> total kapasitas</p>
      <div class="w-full h-1.5 bg-surface-container-high rounded-full overflow-hidden mt-sm">
        <div class="h-full bg-tertiary" style="width: <?= min($persenTersedia, 100) ?>%"></div>
      </div>
    </div>
    <!-- Badge/Bintang -->
    <div class="absolute -top-5 right-6 md:right-lg bg-surface-container-lowest rounded-xl shadow-lg border border-outline-variant/30 px-md py-sm flex items-center gap-2">
      <span class="material-symbols-outlined text-primary text-[20px]" style="font-variation-settings:'FILL' 1">verified_user</span>
      <span class="text-label-md font-bold text-on-background">Keamanan Terverifikasi HQ</span>
    </div>
  </div>
</section>

<!-- signature thread divider -->
<div class="max-w-container-max mx-auto px-lg">
  <svg class="weave-thread" viewBox="0 0 1200 20" preserveAspectRatio="none">
    <path d="M0 10 Q30 2 60 10 T120 10 T180 10 T240 10 T300 10 T360 10 T420 10 T480 10 T540 10 T600 10 T660 10 T720 10 T780 10 T840 10 T900 10 T960 10 T1020 10 T1080 10 T1140 10 T1200 10" fill="none" stroke="#b5000b" stroke-width="1.3" stroke-opacity="0.25"/>
  </svg>
</div>

<!-- ============ Icon strip: Kategori Kendaraan ============ -->
<section id="fasilitas" class="max-w-container-max mx-auto px-lg py-lg">
  <div class="flex flex-wrap gap-md">
    <?php
    $ikonTipe = ['Mobil' => ['directions_car', 'text-secondary'], 'Motor' => ['two_wheeler', 'text-tertiary'], 'Bus/Truk' => ['local_shipping', 'text-secondary']];
    foreach ($kategoriKendaraan as $k):
        [$ikon, $warna] = $ikonTipe[$k['tipe']] ?? ['directions_car', 'text-secondary'];
    ?>
    <div class="flex items-center gap-2 px-lg py-3 rounded-full bg-surface-container-lowest border border-outline-variant/40 shadow-sm text-body-md font-bold">
      <span class="material-symbols-outlined <?= $warna ?>"><?= $ikon ?></span> <?= htmlspecialchars($k['tipe']) ?> &mdash; <?= number_format($k['jml'], 0, ',', '.') ?> unit terdaftar
    </div>
    <?php endforeach; ?>
    <div class="flex items-center gap-2 px-lg py-3 rounded-full bg-surface-container-lowest border border-outline-variant/40 shadow-sm text-body-md font-bold">
      <span class="material-symbols-outlined text-primary">workspace_premium</span> VIP / Direksi
    </div>
    <div class="flex items-center gap-2 px-lg py-3 rounded-full bg-surface-container-lowest border border-outline-variant/40 shadow-sm text-body-md font-bold">
      <span class="material-symbols-outlined text-tertiary">ev_station</span> Slot Kendaraan Listrik
    </div>
  </div>
</section>

<!-- ============ Sidebar + Informasi (Availability, Kualitas, Sold) ============ -->
<section id="ketersediaan" class="max-w-container-max mx-auto px-lg py-xl grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-xl">
  <!-- Sidebar -->
  <aside class="space-y-lg">
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <h3 class="text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-md">Jam Operasional</h3>
      <div class="space-y-2 text-body-md">
        <div class="flex justify-between"><span class="text-on-surface-variant">Senin&ndash;Jumat</span><span class="font-bold">06.00&ndash;22.00</span></div>
        <div class="flex justify-between"><span class="text-on-surface-variant">Sabtu</span><span class="font-bold">07.00&ndash;18.00</span></div>
        <div class="flex justify-between"><span class="text-on-surface-variant">Minggu</span><span class="font-bold text-error">Tutup</span></div>
      </div>
    </div>
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <h3 class="text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-md">Lokasi</h3>
      <p class="text-body-md text-on-surface-variant leading-relaxed">Gedung Utama Kantor Pusat Pertamina, Jl. Medan Merdeka Timur, Jakarta Pusat.</p>
      <a href="#" class="inline-flex items-center gap-1 text-primary font-bold text-label-md mt-sm hover:underline">
        <span class="material-symbols-outlined text-[16px]">location_on</span> Lihat Peta
      </a>
    </div>
    <!-- Help card -->
    <div id="bantuan" class="bg-primary rounded-xl p-lg text-on-primary">
      <h4 class="font-title-md text-title-md font-bold mb-2">Butuh Bantuan?</h4>
      <p class="text-body-md opacity-90 mb-lg">Petugas loket &amp; keamanan siap membantu kendala akses atau verifikasi kendaraan.</p>
      <div class="space-y-sm">
        <a href="https://wa.me/6288228653320?text=Halo%2C%20saya%20butuh%20bantuan%20terkait%20Sistem%20Parkir%20Gedung%20Pertamina" target="_blank" rel="noopener"
           class="flex items-center gap-2 bg-on-primary/15 px-md py-2 rounded-lg text-label-md font-bold hover:bg-on-primary/25 transition-colors">
          <span class="material-symbols-outlined text-[16px]">chat</span> WhatsApp: 0882-2865-3320
        </a>
        <a href="tel:088228653320"
           class="flex items-center gap-2 bg-on-primary/15 px-md py-2 rounded-lg text-label-md font-bold hover:bg-on-primary/25 transition-colors">
          <span class="material-symbols-outlined text-[16px]">call</span> Telepon Petugas
        </a>
        <a href="mailto:parkir@pertamina.com"
           class="flex items-center gap-2 bg-on-primary/15 px-md py-2 rounded-lg text-label-md font-bold hover:bg-on-primary/25 transition-colors">
          <span class="material-symbols-outlined text-[16px]">mail</span> parkir@pertamina.com
        </a>
      </div>
    </div>
  </aside>

  <!-- Info cards -->
  <div>
    <div class="mb-lg">
      <h2 class="font-headline-lg text-headline-lg text-on-background">Informasi Terkini</h2>
      <p class="text-body-lg text-on-surface-variant">Ketersediaan, kualitas layanan, dan jumlah transaksi diperbarui otomatis dari sistem.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-lg">
      <!-- Availability Transaksi -->
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
        <div class="p-2 bg-tertiary/10 rounded-lg text-tertiary w-fit mb-md"><span class="material-symbols-outlined">event_available</span></div>
        <p class="text-label-md text-on-surface-variant uppercase tracking-wider mb-1">Ketersediaan Transaksi</p>
        <p class="font-headline-lg text-[28px] font-bold text-on-background"><?= number_format($slotTersedia, 0, ',', '.') ?> Slot</p>
        <span class="inline-flex items-center gap-1 mt-sm px-2 py-1 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant text-label-md font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> <?= $persenTersedia >= 15 ? 'Normal' : 'Terbatas' ?>
        </span>
      </div>
      <!-- Kualitas Layanan (diambil dari rata-rata rating komentar) -->
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
        <div class="p-2 bg-primary/10 rounded-lg text-primary w-fit mb-md"><span class="material-symbols-outlined">star</span></div>
        <p class="text-label-md text-on-surface-variant uppercase tracking-wider mb-1">Kualitas Layanan</p>
        <p class="font-headline-lg text-[28px] font-bold text-on-background"><?= number_format($rataRating, 1, ',', '.') ?> / 5</p>
        <div class="flex text-primary mt-sm text-[14px]"><?= $bintang($rataRating) ?><a href="#ulasan" class="text-on-surface-variant text-label-md ml-2 self-center hover:underline"><?= number_format($jumlahUlasan, 0, ',', '.') ?> ulasan</a></div>
      </div>
      <!-- Sold / Terlayani -->
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
        <div class="p-2 bg-secondary/10 rounded-lg text-secondary w-fit mb-md"><span class="material-symbols-outlined">local_parking</span></div>
        <p class="text-label-md text-on-surface-variant uppercase tracking-wider mb-1">Kendaraan Terlayani</p>
        <p class="font-headline-lg text-[28px] font-bold text-on-background"><?= number_format((int) $kendaraanTerlayani, 0, ',', '.') ?></p>
        <p class="text-label-md text-on-surface-variant mt-sm">bulan berjalan</p>
      </div>
    </div>

    <!-- Okupansi per lantai -->
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm mt-lg overflow-hidden">
      <div class="px-lg py-md border-b border-outline-variant/60">
        <h3 class="font-title-md text-title-md font-bold">Okupansi per Lantai</h3>
      </div>
      <div class="divide-y divide-outline-variant/50">
        <?php foreach ($okupansiLantai as $l):
            $persen = (float) $l['okupansi_persen'];
            $badge  = $okupansiBadge($persen);
        ?>
        <div class="px-lg py-md">
          <div class="flex justify-between items-center mb-1">
            <p class="text-body-md font-medium"><?= htmlspecialchars($l['nama_lantai']) ?></p>
            <span class="px-2 py-0.5 rounded-full text-label-md <?= $badge['class'] ?>"><?= $badge['label'] ?> (<?= $persen ?>%)</span>
          </div>
          <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
            <div class="h-full bg-primary" style="width: <?= min($persen, 100) ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ Mau Parkir? Cek Slot & Tarif ============ -->
<section class="max-w-container-max mx-auto px-lg py-xl">
  <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-xl">
    <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-md mb-lg">
      <div>
        <h2 class="font-headline-lg text-headline-lg text-on-background">Mau Parkir? Cek Slot & Tarif Dulu</h2>
        <p class="text-body-lg text-on-surface-variant mt-1">Slot kosong per area diperbarui langsung dari sistem. Bayar di gerbang keluar, tunai maupun non-tunai.</p>
      </div>
      <a href="ketersediaan.php" class="shrink-0 inline-flex items-center gap-2 px-lg py-3 rounded-xl bg-primary text-on-primary font-bold shadow-md hover:bg-primary/90 transition-all whitespace-nowrap">
        <span class="material-symbols-outlined">grid_view</span> Lihat Detail Slot & Tarif
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
      <!-- Ringkasan slot per lantai -->
      <div>
        <h3 class="text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-sm">Slot per Lantai</h3>
        <div class="space-y-sm">
          <?php foreach ($okupansiLantai as $l):
              $persen = (float) $l['okupansi_persen'];
              $badge  = $okupansiBadge($persen);
              $sisaSlot = max((int) $l['kapasitas'] - (int) $l['slot_terisi'], 0);
          ?>
          <div class="flex justify-between items-center px-md py-sm rounded-lg bg-surface-container-low">
            <span class="text-body-md font-medium"><?= htmlspecialchars($l['nama_lantai']) ?></span>
            <span class="flex items-center gap-2">
              <span class="text-body-md font-bold text-tertiary"><?= $sisaSlot ?> kosong</span>
              <span class="px-2 py-0.5 rounded-full text-label-md <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Ringkasan tarif -->
      <div>
        <h3 class="text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-sm">Tarif per Jam</h3>
        <div class="space-y-sm">
          <?php foreach ($tarifRingkas as $t): ?>
          <div class="flex justify-between items-center px-md py-sm rounded-lg bg-surface-container-low">
            <span class="text-body-md font-medium"><?= htmlspecialchars($t['tipe_kendaraan']) ?></span>
            <span class="text-body-md font-bold"><?= format_rupiah($t['tarif_per_jam']) ?> / jam</span>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-label-md text-on-surface-variant mt-sm">* Dibulatkan ke atas per jam. Metode bayar: Tunai, QRIS, Debit, Kredit, E-Wallet.</p>
      </div>
    </div>
  </div>
</section>

<section id="tarif" class="bg-surface-container-low border-y border-outline-variant/40">
  <div class="max-w-container-max mx-auto px-lg py-xl grid grid-cols-1 lg:grid-cols-3 gap-xl items-center">
    <div class="lg:col-span-1">
      <span class="inline-flex items-center gap-2 px-md py-1 rounded-full border border-primary/25 bg-primary/5 text-primary text-label-md font-bold uppercase tracking-wider mb-md">Grafik</span>
      <h2 class="font-headline-lg text-headline-lg text-on-background mb-md">Tren Okupansi Per Jam</h2>
      <p class="text-body-lg text-on-surface-variant mb-lg">Kepadatan area parkir cenderung memuncak pukul <?= htmlspecialchars($jamTersibuk) ?>, mengikuti jam kerja gedung.</p>
      <div class="space-y-2 text-body-md">
        <div class="flex justify-between border-b border-outline-variant/50 pb-2"><span class="text-on-surface-variant">Jam tersibuk</span><span class="font-bold"><?= htmlspecialchars($jamTersibuk) ?></span></div>
        <div class="flex justify-between border-b border-outline-variant/50 pb-2"><span class="text-on-surface-variant">Okupansi puncak</span><span class="font-bold"><?= $okupansiPuncak ?>%</span></div>
        <div class="flex justify-between"><span class="text-on-surface-variant">Okupansi rata-rata</span><span class="font-bold"><?= $okupansiRataJam ?>%</span></div>
      </div>
    </div>
    <div class="lg:col-span-2 bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <div class="flex items-center justify-between mb-md">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
          <span class="text-label-md font-bold text-on-surface-variant uppercase tracking-wider">Okupansi per Jam &mdash; Hari Ini</span>
        </div>
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-error-container text-on-error-container text-label-md font-bold">
          <span class="material-symbols-outlined text-[14px]">trending_up</span> Puncak <?= $okupansiPuncak ?>% &middot; <?= htmlspecialchars($jamTersibuk) ?>
        </span>
      </div>
      <div style="height:320px"><canvas id="occupancyChart"></canvas></div>
    </div>
  </div>
</section>

<!-- ============ Multimedia: Video + Audio ============ -->
<section class="max-w-container-max mx-auto px-lg py-xl grid grid-cols-1 lg:grid-cols-2 gap-xl items-center">
  <div id="videoTurWrapper" class="relative rounded-xl overflow-hidden aspect-video shadow-lg cursor-pointer group" onclick="putarVideoTur()">
    <img src="https://images.unsplash.com/photo-1590674899484-d5640e854abe?w=1000&q=80" alt="Tur video area parkir" class="w-full h-full object-cover opacity-90">
    <div class="absolute inset-0 bg-on-background/25 flex items-center justify-center">
      <button type="button" class="w-16 h-16 rounded-full bg-surface-container-lowest/95 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform">
        <span class="material-symbols-outlined text-primary text-[32px]" style="font-variation-settings:'FILL' 1">play_arrow</span>
      </button>
    </div>
    <span class="absolute bottom-3 left-3 bg-on-background/70 text-surface-container-lowest text-label-md px-2 py-1 rounded-md">Tur Area Parkir &middot; 1:48</span>
  </div>
  <div>
    <h2 class="font-headline-lg text-headline-lg text-on-background mb-md">Pantau dari mana saja</h2>
    <p class="text-body-lg text-on-surface-variant mb-lg">Petugas menerima notifikasi bersuara setiap ada kendaraan masuk/keluar, dan setiap area dilengkapi dokumentasi video singkat untuk panduan pengguna baru.</p>
    <div class="flex items-center gap-md bg-surface-container-lowest border border-outline-variant/40 rounded-xl p-md mb-md">
      <button class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined">volume_up</span>
      </button>
      <div class="flex items-end gap-[3px] h-6">
        <span class="w-[3px] bg-primary/70 rounded" style="height:8px"></span><span class="w-[3px] bg-primary/70 rounded" style="height:16px"></span>
        <span class="w-[3px] bg-primary/70 rounded" style="height:10px"></span><span class="w-[3px] bg-primary/70 rounded" style="height:20px"></span>
        <span class="w-[3px] bg-primary/70 rounded" style="height:12px"></span><span class="w-[3px] bg-primary/70 rounded" style="height:18px"></span>
        <span class="w-[3px] bg-primary/70 rounded" style="height:9px"></span>
      </div>
      <div class="min-w-0">
        <p class="text-body-md font-bold truncate">Notifikasi Kendaraan Masuk</p>
        <p class="text-label-md text-on-surface-variant">0:03 &middot; diputar otomatis di dashboard petugas</p>
      </div>
    </div>
    <ul class="space-y-3 text-body-md">
      <li class="flex items-start gap-3"><span class="material-symbols-outlined text-tertiary">check_circle</span> Rekaman CCTV tersimpan 30 hari untuk audit keamanan.</li>
      <li class="flex items-start gap-3"><span class="material-symbols-outlined text-tertiary">check_circle</span> Panduan video untuk setiap gerbang masuk &amp; keluar.</li>
    </ul>
  </div>
</section>

<!-- ============ Komentar / Ulasan (pengunjung bisa menulis sendiri) ============ -->
<section id="ulasan" class="bg-surface-container-low border-y border-outline-variant/40">
  <div class="max-w-container-max mx-auto px-lg py-xl">
    <div class="flex flex-col md:flex-row justify-between md:items-end gap-md mb-xl">
      <div>
        <h2 class="font-headline-lg text-headline-lg text-on-background">Apa Kata Pengguna</h2>
        <p class="text-body-lg text-on-surface-variant">
          <?= number_format($rataRating, 1, ',', '.') ?> dari 5 &mdash; berdasarkan
          <?= number_format($jumlahUlasan, 0, ',', '.') ?> ulasan karyawan &amp; tamu
        </p>
      </div>
      <div class="flex items-center gap-1 text-primary text-[20px]"><?= $bintang($rataRating) ?></div>
    </div>

    <!-- Notifikasi hasil pengiriman komentar -->
    <?php if ($komentarSukses): ?>
      <div class="flex items-center gap-2 mb-lg px-lg py-3 rounded-xl bg-tertiary-fixed text-on-tertiary-fixed-variant text-body-md font-medium">
        <span class="material-symbols-outlined text-[20px]">check_circle</span><?= htmlspecialchars($komentarSukses) ?>
      </div>
    <?php endif; ?>
    <?php if ($komentarError): ?>
      <div class="flex items-center gap-2 mb-lg px-lg py-3 rounded-xl bg-error-container text-on-error-container text-body-md font-medium">
        <span class="material-symbols-outlined text-[20px]">error</span><?= htmlspecialchars($komentarError) ?>
      </div>
    <?php endif; ?>

    <!-- Daftar komentar terbaru -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg">
      <?php foreach ($daftarKomentar as $i => $k): ?>
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
        <div class="flex items-center gap-3 mb-md">
          <div class="w-10 h-10 rounded-full <?= $warnaAvatar[$i % 3] ?> flex items-center justify-center font-bold shrink-0">
            <?= htmlspecialchars($inisial($k['nama'])) ?>
          </div>
          <div class="min-w-0">
            <p class="font-bold text-body-md truncate"><?= htmlspecialchars($k['nama']) ?></p>
            <div class="text-primary text-[13px]"><?= $bintang($k['rating']) ?></div>
          </div>
          <span class="ml-auto text-label-md text-on-surface-variant shrink-0">
            <?= date('d/m/Y', strtotime($k['dibuat_pada'])) ?>
          </span>
        </div>
        <p class="text-body-md text-on-surface-variant"><?= nl2br(htmlspecialchars($k['isi'])) ?></p>
      </div>
      <?php endforeach; ?>
      <?php if (empty($daftarKomentar)): ?>
      <p class="text-body-md text-on-surface-variant md:col-span-3">Belum ada ulasan. Jadilah yang pertama memberi masukan!</p>
      <?php endif; ?>
    </div>

    <!-- Form tulis komentar -->
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-xl mt-xl max-w-[760px]">
      <h3 class="font-title-md text-title-md font-bold mb-1">Tulis Ulasan Anda</h3>
      <p class="text-body-md text-on-surface-variant mb-lg">Masukan Anda membantu kami memperbaiki layanan parkir gedung.</p>

      <form method="post" action="<?= $selfUrl ?>#ulasan" class="space-y-md">
        <input type="hidden" name="form" value="komentar">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_komentar']) ?>">
        <!-- honeypot: disembunyikan dari pengguna, hanya terisi oleh bot -->
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
          <div>
            <label for="k-nama" class="block text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-2">Nama</label>
            <input id="k-nama" name="nama" type="text" maxlength="80" required
                   placeholder="Nama Anda"
                   class="w-full rounded-lg border-outline-variant bg-surface-container-low text-body-md px-md py-2.5 focus:border-primary focus:ring-primary">
          </div>
          <div>
            <label for="k-rating" class="block text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-2">Rating</label>
            <select id="k-rating" name="rating" required
                    class="w-full rounded-lg border-outline-variant bg-surface-container-low text-body-md px-md py-2.5 focus:border-primary focus:ring-primary">
              <option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; &mdash; Sangat Puas</option>
              <option value="4">&#9733;&#9733;&#9733;&#9733;&#9734; &mdash; Puas</option>
              <option value="3">&#9733;&#9733;&#9733;&#9734;&#9734; &mdash; Cukup</option>
              <option value="2">&#9733;&#9733;&#9734;&#9734;&#9734; &mdash; Kurang</option>
              <option value="1">&#9733;&#9734;&#9734;&#9734;&#9734; &mdash; Buruk</option>
            </select>
          </div>
        </div>

        <div>
          <label for="k-isi" class="block text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-2">Ulasan</label>
          <textarea id="k-isi" name="isi" rows="4" minlength="10" maxlength="1000" required
                    placeholder="Ceritakan pengalaman Anda memakai area parkir gedung ini&hellip;"
                    class="w-full rounded-lg border-outline-variant bg-surface-container-low text-body-md px-md py-2.5 focus:border-primary focus:ring-primary"></textarea>
        </div>

        <div class="flex items-center justify-between gap-md flex-wrap">
          <p class="text-label-md text-on-surface-variant">Ulasan akan tampil publik di halaman ini.</p>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-xl py-3 rounded-xl bg-primary text-on-primary font-bold text-body-md shadow-md hover:bg-primary/90 active:scale-[0.98] transition-all">
            <span class="material-symbols-outlined text-[18px]">send</span> Kirim Ulasan
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="bg-on-background text-surface-container-lowest">
  <div class="max-w-container-max mx-auto px-lg py-xl grid grid-cols-1 md:grid-cols-4 gap-xl border-b border-white/10 pb-xl">
    <div>
      <div class="flex items-center gap-2 mb-md">
        <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCOG5IdYEQA-m7Pd_C7l_qHDHG5WAULIExzPanS0779guNt_2DaM74EUsFRfV2N4SO-fYdihO3SpXyoSOhWwYafXX0f4oiiODNsYyYu25eyO9IrvgvrSRuWz1C43EEluwSftubiARd1HBl5G2WJSKgIkUMVGEdgr8Ra6aN6GJds4ZDw0eVC7P2lPwX2yBkWPhX5O8w6MBb6f5Y6fP-PYcbMGsfi_ioRxStkEN86sQcHxURjpIRanMI-eRqeYExGtxh38t72JeG06qM" class="h-7 w-auto object-contain bg-white rounded p-1" alt="Logo Pertamina">
        <span class="font-title-md text-title-md font-bold">Parkir Pertamina</span>
      </div>
      <p class="text-body-md text-white/60">Sistem Manajemen Parkir Gedung Kantor Pusat PT Pertamina (Persero).</p>
    </div>
    <div>
      <h5 class="text-label-md uppercase tracking-wider text-white/50 font-bold mb-md">Navigasi</h5>
      <ul class="space-y-2 text-body-md text-white/75">
        <li><a href="#fasilitas" class="hover:text-white">Fasilitas</a></li>
        <li><a href="#ketersediaan" class="hover:text-white">Ketersediaan</a></li>
        <li><a href="#ulasan" class="hover:text-white">Ulasan</a></li>
      </ul>
    </div>
    <div>
      <h5 class="text-label-md uppercase tracking-wider text-white/50 font-bold mb-md">Akses</h5>
      <ul class="space-y-2 text-body-md text-white/75">
        <li><a href="<?= htmlspecialchars($loginTarget) ?>" class="hover:text-white"><?= htmlspecialchars($loginLabel) ?></a></li>
        <li><a href="#bantuan" class="hover:text-white">Pusat Bantuan</a></li>
      </ul>
    </div>
    <div>
      <h5 class="text-label-md uppercase tracking-wider text-white/50 font-bold mb-md">Kontak</h5>
      <ul class="space-y-2 text-body-md text-white/75">
        <li>Jl. Medan Merdeka Timur, Jakarta Pusat</li>
        <li>parkir@pertamina.com</li>
        <li><a href="https://wa.me/6288228653320" target="_blank" rel="noopener" class="hover:text-white">WA: 0882-2865-3320</a></li>
      </ul>
    </div>
  </div>
  <div class="max-w-container-max mx-auto px-lg py-md flex flex-col sm:flex-row justify-between items-center gap-2">
    <div class="flex items-center gap-2 text-white/60">
      <span class="material-symbols-outlined text-[16px]" style="font-variation-settings:'FILL' 1">verified_user</span>
      <span class="text-label-md tracking-wider uppercase">Sistem Keamanan Terverifikasi &mdash; HQ Management</span>
    </div>
    <p class="text-[12px] text-white/50">&copy; <?= date('Y') ?> PT Pertamina (Persero). Seluruh Hak Cipta Dilindungi.</p>
  </div>
</footer>

<script>
  // Ganti thumbnail video dengan elemen <video> asli saat tombol play diklik.
  function putarVideoTur() {
    const wrapper = document.getElementById('videoTurWrapper');
    if (!wrapper) return;
    wrapper.outerHTML = `
      <div class="relative rounded-xl overflow-hidden aspect-video shadow-lg bg-black">
        <video class="w-full h-full object-cover" controls autoplay playsinline>
          <source src="<?= htmlspecialchars($videoTurUrl) ?>" type="video/mp4">
          Browser Anda tidak mendukung pemutaran video HTML5.
        </video>
      </div>`;
  }

  const ctx = document.getElementById('occupancyChart').getContext('2d');
  const gradienOkupansi = ctx.createLinearGradient(0, 0, 0, 320);
  gradienOkupansi.addColorStop(0, 'rgba(181,0,11,0.35)');
  gradienOkupansi.addColorStop(0.65, 'rgba(181,0,11,0.06)');
  gradienOkupansi.addColorStop(1, 'rgba(181,0,11,0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($labelJam) ?>,
      datasets: [{
        label: 'Okupansi %',
        data: <?= json_encode($dataJam) ?>,
        borderColor: '#b5000b',
        backgroundColor: gradienOkupansi,
        fill: true,
        tension: 0.42,
        cubicInterpolationMode: 'monotone',
        borderWidth: 3,
        pointRadius: 0,
        pointHitRadius: 16,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#b5000b',
        pointHoverBorderColor: '#ffffff',
        pointHoverBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1a1c1c',
          titleColor: '#ffffff',
          titleFont: { weight: 'bold' },
          bodyColor: '#ffffff',
          padding: 10,
          cornerRadius: 8,
          displayColors: false,
          callbacks: { label: (item) => `Okupansi: ${item.formattedValue}%` }
        }
      },
      scales: {
        y: {
          beginAtZero: true, max: 100,
          grid: { color: '#f0f0f0' },
          border: { display: false },
          ticks: { callback: v => v + '%', color: '#8a8a8a', font: { size: 11 } }
        },
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: '#8a8a8a', font: { size: 11 } }
        }
      }
    }
  });
</script>

<!-- Tombol WhatsApp Melayang -->
<a href="https://wa.me/6288228653320?text=Halo%2C%20saya%20butuh%20bantuan%20terkait%20Sistem%20Parkir%20Gedung%20Pertamina"
   target="_blank" rel="noopener"
   class="fixed bottom-6 right-6 z-50 flex items-center gap-2 bg-tertiary text-on-tertiary px-lg py-3 rounded-full shadow-lg hover:shadow-xl active:scale-95 transition-all"
   title="Hubungi kami via WhatsApp">
  <span class="material-symbols-outlined">chat</span>
  <span class="hidden sm:inline font-bold text-body-md">Bantuan WA</span>
</a>
</body>
</html>