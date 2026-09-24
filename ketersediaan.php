<?php
/**
 * ketersediaan.php
 * Halaman PUBLIK (tanpa login) — untuk calon pengguna yang mau parkir.
 * Menampilkan peta slot per area (live) + tabel tarif lengkap + info
 * cara pembayaran (dibayar di gerbang keluar saat check-out, bukan
 * pembayaran online di muka).
 */
session_start();
require_once 'config.php';

$sudahLogin  = !empty($_SESSION['user_id']);
$loginTarget = $sudahLogin ? dashboard_url_for_role($_SESSION['role'] ?? '') : 'login.html';
$loginLabel  = $sudahLogin ? 'Ke Dashboard' : 'Masuk';

/* ============ SLOT PER AREA (live) ============ */
$slotPerArea = db_fetch_all(
    "SELECT a.nama_area, l.nama_lantai, sp.kode_slot, sp.status
     FROM slot_parkir sp
     JOIN area a ON a.id = sp.area_id
     JOIN lantai l ON l.id = a.lantai_id
     ORDER BY l.id, a.id, sp.kode_slot",
    [],
    [
        ['nama_area' => 'Section A - Lantai 1', 'nama_lantai' => 'Lantai 1', 'kode_slot' => 'A-01', 'status' => 'Terisi'],
        ['nama_area' => 'Section A - Lantai 1', 'nama_lantai' => 'Lantai 1', 'kode_slot' => 'A-02', 'status' => 'Tersedia'],
        ['nama_area' => 'Section B - Lantai 1', 'nama_lantai' => 'Lantai 1', 'kode_slot' => 'B-01', 'status' => 'Tersedia'],
    ]
);
$slotGrouped = [];
foreach ($slotPerArea as $s) {
    $key = $s['nama_lantai'] . '|' . $s['nama_area'];
    $slotGrouped[$key]['nama_area']   = $s['nama_area'];
    $slotGrouped[$key]['nama_lantai'] = $s['nama_lantai'];
    $slotGrouped[$key]['slots'][]     = $s;
}

$totalSemua    = count($slotPerArea);
$tersediaSemua = count(array_filter($slotPerArea, fn($s) => $s['status'] === 'Tersedia'));

$slotColor = [
    'Tersedia' => 'bg-tertiary/15 text-tertiary border-tertiary/30',
    'Terisi'   => 'bg-error-container text-on-error-container border-error/20',
    'Dipesan'  => 'bg-secondary/15 text-secondary border-secondary/30',
];

/* ============ TARIF AKTIF ============ */
$daftarTarif = db_fetch_all(
    "SELECT tipe_kendaraan, deskripsi, tarif_per_jam FROM tarif WHERE status = 'Aktif' ORDER BY tarif_per_jam ASC",
    [],
    [
        ['tipe_kendaraan' => 'Motor', 'deskripsi' => 'Motor Roda Dua Standard', 'tarif_per_jam' => 3000],
        ['tipe_kendaraan' => 'Mobil', 'deskripsi' => 'Mobil Pribadi / Sedan / SUV', 'tarif_per_jam' => 7000],
        ['tipe_kendaraan' => 'Bus/Truk', 'deskripsi' => 'Kendaraan Besar & Logistik', 'tarif_per_jam' => 25000],
    ]
);

$metodePembayaran = metode_pembayaran_tersedia();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ketersediaan Slot & Tarif - Sistem Manajemen Parkir Gedung Pertamina</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">try{
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        "colors": {
          "outline-variant": "#e9bcb6", "secondary": "#3a5f94", "on-background": "#1a1c1c",
          "background": "#f9f9f9", "on-surface": "#1a1c1c", "outline": "#936e69",
          "on-surface-variant": "#5e3f3b", "surface": "#f9f9f9", "tertiary": "#3e6300",
          "on-error-container": "#93000a", "error-container": "#ffdad6", "error": "#ba1a1a",
          "primary": "#b5000b", "surface-container-lowest": "#ffffff", "on-primary": "#ffffff",
          "surface-container-low": "#f3f3f3", "on-tertiary": "#ffffff"
        },
        "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
        "spacing": { "md": "16px", "container-max": "1200px", "xs": "4px", "lg": "24px", "sm": "12px", "base": "8px", "xl": "32px" },
        "fontFamily": { "headline-lg": ["Inter"], "title-md": ["Inter"], "body-lg": ["Inter"], "body-md": ["Inter"], "label-md": ["Inter"] },
        "fontSize": {
          "headline-lg": ["30px", {"lineHeight": "38px", "fontWeight": "600"}],
          "title-md": ["18px", {"lineHeight": "24px", "fontWeight": "600"}],
          "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
          "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
          "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.5px", "fontWeight": "500"}]
        }
      }
    }
  }
}catch(_e){}</script>
</head>
<body class="bg-background text-on-background min-h-screen">

<!-- Header -->
<header class="sticky top-0 z-40 bg-surface-container-lowest/95 backdrop-blur border-b border-outline-variant/40">
  <div class="max-w-container-max mx-auto px-lg py-md flex justify-between items-center">
    <a href="landingpage.php" class="flex items-center gap-2">
      <span class="material-symbols-outlined text-primary">local_parking</span>
      <span class="font-title-md text-title-md font-bold">Parkir Gedung Pertamina</span>
    </a>
    <div class="flex items-center gap-sm">
      <a href="landingpage.php" class="text-body-md text-on-surface-variant hover:text-primary">&larr; Beranda</a>
      <a href="<?= htmlspecialchars($loginTarget) ?>" class="px-md py-2 rounded-lg bg-primary text-on-primary font-bold text-body-md hover:bg-primary/90 transition-colors"><?= $loginLabel ?></a>
    </div>
  </div>
</header>

<main class="max-w-container-max mx-auto px-lg py-xl space-y-xl">

  <div>
    <h1 class="font-headline-lg text-headline-lg">Ketersediaan Slot & Tarif Parkir</h1>
    <p class="text-body-lg text-on-surface-variant mt-1">Data slot diperbarui langsung dari sistem. Pembayaran dilakukan di gerbang keluar oleh petugas.</p>
  </div>

  <!-- Ringkasan -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-md">
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <p class="text-label-md text-on-surface-variant uppercase tracking-wider mb-1">Total Slot</p>
      <p class="font-headline-lg text-[26px] font-bold"><?= number_format($totalSemua, 0, ',', '.') ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <p class="text-label-md text-on-surface-variant uppercase tracking-wider mb-1">Slot Tersedia Sekarang</p>
      <p class="font-headline-lg text-[26px] font-bold text-tertiary"><?= number_format($tersediaSemua, 0, ',', '.') ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
      <div class="flex items-center gap-md text-label-md">
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-tertiary"></span> Tersedia</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-error"></span> Terisi</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-secondary"></span> Dipesan</span>
      </div>
    </div>
  </div>

  <!-- Peta slot per area -->
  <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm p-lg">
    <h2 class="font-title-md text-title-md font-bold mb-lg">Peta Slot per Area</h2>
    <?php if (empty($slotGrouped)): ?>
      <p class="text-body-md text-on-surface-variant">Data slot belum tersedia.</p>
    <?php else: foreach ($slotGrouped as $grp): ?>
      <div class="mb-lg last:mb-0">
        <p class="text-body-md font-bold text-on-surface-variant mb-sm"><?= htmlspecialchars($grp['nama_area']) ?> &middot; <?= htmlspecialchars($grp['nama_lantai']) ?></p>
        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 gap-2">
          <?php foreach ($grp['slots'] as $s): ?>
          <div class="aspect-square rounded-lg border flex items-center justify-center text-[11px] font-bold <?= $slotColor[$s['status']] ?? 'bg-surface-container text-on-surface border-outline-variant' ?>" title="<?= htmlspecialchars($s['status']) ?>">
            <?= htmlspecialchars($s['kode_slot']) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Tarif -->
  <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-sm overflow-hidden">
    <div class="px-lg py-md border-b border-outline-variant/60">
      <h2 class="font-title-md text-title-md font-bold">Tarif Parkir</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="bg-surface-container-low">
            <th class="px-lg py-sm text-label-md uppercase text-outline">Tipe Kendaraan</th>
            <th class="px-lg py-sm text-label-md uppercase text-outline">Keterangan</th>
            <th class="px-lg py-sm text-label-md uppercase text-outline">Tarif per Jam</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
          <?php foreach ($daftarTarif as $t): ?>
          <tr>
            <td class="px-lg py-md text-body-md font-bold"><?= htmlspecialchars($t['tipe_kendaraan']) ?></td>
            <td class="px-lg py-md text-body-md text-on-surface-variant"><?= htmlspecialchars($t['deskripsi']) ?></td>
            <td class="px-lg py-md text-body-md font-bold"><?= format_rupiah($t['tarif_per_jam']) ?> / jam</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="px-lg py-md text-label-md text-on-surface-variant bg-surface-container-low border-t border-outline-variant/60">
      * Tarif dihitung per jam, dibulatkan ke atas (mis. 1 jam 10 menit tetap dihitung 2 jam).
    </p>
  </div>

  <!-- Cara pembayaran -->
  <div class="bg-primary/5 border border-primary/20 rounded-xl p-lg">
    <h2 class="font-title-md text-title-md font-bold mb-2">Cara Pembayaran</h2>
    <p class="text-body-md text-on-surface-variant mb-md">
      Pembayaran dilakukan <strong>di gerbang keluar</strong> saat kendaraan check-out, langsung ke petugas loket.
      Struk akan dicetak sebagai bukti setelah pembayaran selesai. Metode pembayaran yang tersedia:
    </p>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($metodePembayaran as $label): ?>
        <span class="inline-flex items-center gap-1 px-md py-2 rounded-lg bg-white border border-primary/20 text-body-md font-medium">
          <span class="material-symbols-outlined text-[18px] text-primary">payments</span> <?= htmlspecialchars($label) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

</main>

<footer class="border-t border-outline-variant/40 py-lg">
  <p class="text-center text-label-md text-on-surface-variant">&copy; <?= date('Y') ?> PT Pertamina (Persero). Seluruh Hak Cipta Dilindungi.</p>
</footer>

</body>
</html>