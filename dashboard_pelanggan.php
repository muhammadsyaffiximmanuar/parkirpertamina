<?php
/**
 * dashboard_pelanggan.php
 * Dashboard self-service untuk role Pelanggan: booking slot parkir
 * (tabel slot_parkir per area/lantai) dan kelola booking sendiri.
 */
require_once 'config.php';
$session = require_login_page();
require_role([ROLE_PELANGGAN]);

expire_booking_lewat_waktu(); // bebaskan slot dari booking yang sudah lewat waktu & tidak di-check-in

$kendaraanSaya = ambil_kendaraan_milik_user((int) $session['user_id']);
$areaAktif     = ambil_area_aktif();
$slotTersedia  = ambil_slot_tersedia();       // untuk dropdown slot, difilter per area di JS
$areaOkupansi  = ambil_okupansi_area_sekarang(); // untuk kartu status
$bookingSaya   = ambil_booking_milik_user((int) $session['user_id']);

$labelStatus = ['aktif' => 'Aktif', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan', 'kedaluwarsa' => 'Kedaluwarsa (tidak check-in)'];
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pelanggan - Sistem Manajemen Parkir Gedung Pertamina</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">try{
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        "colors": {
          "background": "#f9f9f9", "on-background": "#1a1c1c", "on-surface": "#1a1c1c",
          "on-surface-variant": "#5e3f3b", "surface": "#f9f9f9", "outline": "#936e69",
          "outline-variant": "#e9bcb6",
          "primary": "#b5000b", "on-primary": "#ffffff", "surface-container-lowest": "#ffffff",
          "surface-container-high": "#e8e8e8",
          "tertiary": "#3e6300", "on-tertiary": "#ffffff", "tertiary-container": "#eaffc8", "on-tertiary-container":"#112000",
          "tertiary-fixed": "#b2f655", "on-tertiary-fixed-variant": "#304f00",
          "error": "#ba1a1a", "on-error": "#ffffff", "error-container": "#ffdad6", "on-error-container": "#93000a",
          "secondary": "#3a5f94", "secondary-container": "#d5e3ff", "on-secondary-container": "#1f477b",
          "secondary-fixed": "#d5e3ff", "on-secondary-fixed-variant": "#1f477b"
        },
        "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
        "spacing": { "md": "16px", "xs": "4px", "lg": "24px", "sm": "12px", "base": "8px", "xl": "32px" },
        "fontFamily": { "headline-lg": ["Inter"], "title-md": ["Inter"], "body-lg": ["Inter"], "body-md": ["Inter"], "label-md": ["Inter"] },
        "fontSize": {
          "headline-lg": ["28px", {"lineHeight": "36px", "fontWeight": "600"}],
          "title-md": ["18px", {"lineHeight": "24px", "fontWeight": "600"}],
          "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
          "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
          "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.5px", "fontWeight": "500"}]
        }
      }
    }
  }
}catch(_e){}</script>
<style>.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="bg-background text-on-background min-h-screen p-md md:p-xl">
<main class="max-w-[960px] mx-auto space-y-xl pb-xl">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="font-headline-lg text-headline-lg">Halo, <?= htmlspecialchars($session['nama_lengkap']) ?></h1>
      <p class="text-body-md text-on-surface-variant">Dashboard Pelanggan &mdash; booking slot parkir</p>
    </div>
    <a href="logout.php" class="text-primary font-bold text-body-md hover:underline whitespace-nowrap">Keluar</a>
  </div>

  <!-- Pesan notifikasi hasil aksi -->
  <div id="notif" class="hidden rounded-lg px-md py-sm text-body-md"></div>

  <?php if (empty($kendaraanSaya)): ?>
    <div class="bg-error-container/40 border border-error/20 text-error rounded-lg px-md py-sm text-body-md">
      Anda belum memiliki kendaraan terdaftar, sehingga belum bisa membuat booking.
      Hubungi Admin untuk menambahkan kendaraan ke akun Anda.
    </div>
  <?php endif; ?>

  <!-- Form Booking Baru -->
  <section class="bg-white rounded-xl shadow-sm p-lg">
    <h2 class="font-title-md text-title-md mb-md">Buat Booking Baru</h2>
    <form id="form-booking" class="space-y-md">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="kendaraan_id">Kendaraan</label>
          <select id="kendaraan_id" name="kendaraan_id" class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg outline-none focus:ring-2 focus:ring-primary/20 text-body-lg" required <?= empty($kendaraanSaya) ? 'disabled' : '' ?>>
            <option value="">-- Pilih kendaraan --</option>
            <?php foreach ($kendaraanSaya as $k): ?>
              <option value="<?= (int) $k['id'] ?>">
                <?= htmlspecialchars($k['plat_nomor']) ?> (<?= htmlspecialchars($k['tipe']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="area_id">Area / Lantai</label>
          <select id="area_id" class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg outline-none focus:ring-2 focus:ring-primary/20 text-body-lg" <?= (empty($kendaraanSaya) || empty($areaAktif)) ? 'disabled' : '' ?>>
            <option value="">-- Pilih area --</option>
            <?php foreach ($areaAktif as $a): ?>
              <option value="<?= (int) $a['id'] ?>">
                <?= htmlspecialchars($a['nama_lantai']) ?><?= $a['gedung'] ? ' - ' . htmlspecialchars($a['gedung']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="slot_id">Slot Tersedia</label>
          <select id="slot_id" name="slot_id" class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg outline-none focus:ring-2 focus:ring-primary/20 text-body-lg" required <?= (empty($kendaraanSaya) || empty($areaAktif)) ? 'disabled' : '' ?>>
            <option value="">-- Pilih area dulu --</option>
          </select>
        </div>
        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="waktu_booking">Waktu Booking</label>
          <input type="datetime-local" id="waktu_booking" name="waktu_booking" class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg outline-none focus:ring-2 focus:ring-primary/20 text-body-lg" required <?= empty($kendaraanSaya) ? 'disabled' : '' ?>>
        </div>
      </div>
      <?php if (empty($areaAktif)): ?>
        <p class="text-body-md text-error">Belum ada area/lantai aktif. Hubungi Admin untuk menambahkannya di menu Kelola Area.</p>
      <?php endif; ?>
      <button type="submit" class="bg-primary text-on-primary font-title-md text-title-md px-lg py-sm rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all disabled:opacity-50" <?= (empty($kendaraanSaya) || empty($areaAktif)) ? 'disabled' : '' ?>>
        Booking Sekarang
      </button>
    </form>
  </section>

  <!-- Status Okupansi Area (gaya sama dengan Kelola Area di Admin) -->
  <section class="bg-white rounded-xl shadow-sm p-lg">
    <h2 class="font-title-md text-title-md mb-md">Status Area Parkir Saat Ini</h2>
    <?php if (empty($areaOkupansi)): ?>
      <p class="text-body-md text-on-surface-variant">Belum ada data area parkir. Hubungi Admin untuk menambahkan area.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
        <?php foreach ($areaOkupansi as $a):
          $total  = (int) $a['total_slot'];
          $terisi = (int) $a['slot_terisi'];
          $persen = $total > 0 ? round(($terisi / $total) * 100, 1) : 0;
          $badge  = label_okupansi($persen);
        ?>
          <div class="rounded-xl border border-outline-variant/30 p-md">
            <div class="flex justify-between items-start gap-sm mb-sm">
              <div>
                <p class="text-body-md font-bold text-on-surface"><?= htmlspecialchars($a['nama_lantai']) ?></p>
                <p class="text-label-md text-on-surface-variant"><?= htmlspecialchars($a['keterangan'] ?: '-') ?></p>
              </div>
              <span class="px-2 py-0.5 rounded-full text-label-md <?= $badge['class'] ?>"><?= $badge['label'] ?> (<?= $persen ?>%)</span>
            </div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-label-md text-on-surface-variant"><?= $terisi ?> / <?= $total ?> slot terisi</span>
            </div>
            <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
              <div class="h-full bg-primary" style="width: <?= min($persen, 100) ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Riwayat Booking Saya -->
  <section class="bg-white rounded-xl shadow-sm p-lg">
    <h2 class="font-title-md text-title-md mb-md">Booking Saya</h2>
    <?php if (empty($bookingSaya)): ?>
      <p class="text-body-md text-on-surface-variant">Anda belum pernah membuat booking.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-body-md">
          <thead>
            <tr class="border-b border-outline/20 text-on-surface-variant text-label-md uppercase">
              <th class="py-sm pr-sm">Kode</th>
              <th class="py-sm pr-sm">Area</th>
              <th class="py-sm pr-sm">Slot</th>
              <th class="py-sm pr-sm">Plat Nomor</th>
              <th class="py-sm pr-sm">Waktu</th>
              <th class="py-sm pr-sm">Status</th>
              <th class="py-sm pr-sm"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookingSaya as $b): ?>
              <tr class="border-b border-outline/10">
                <td class="py-sm pr-sm font-bold tracking-wider"><?= htmlspecialchars($b['kode_booking'] ?: '-') ?></td>
                <td class="py-sm pr-sm"><?= htmlspecialchars($b['nama_lantai']) ?></td>
                <td class="py-sm pr-sm"><?= htmlspecialchars($b['kode_slot']) ?></td>
                <td class="py-sm pr-sm"><?= htmlspecialchars($b['plat_nomor']) ?></td>
                <td class="py-sm pr-sm"><?= htmlspecialchars(date('d M Y H:i', strtotime($b['waktu_booking']))) ?></td>
                <td class="py-sm pr-sm"><?= htmlspecialchars($labelStatus[$b['status']] ?? $b['status']) ?></td>
                <td class="py-sm pr-sm text-right">
                  <?php if ($b['status'] === 'aktif'): ?>
                    <button data-booking-id="<?= (int) $b['id'] ?>" class="btn-batalkan text-error font-bold hover:underline text-body-md">Batalkan</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
// Semua slot Tersedia beserta area_id-nya, untuk difilter per area di client.
const SLOT_TERSEDIA = <?= json_encode($slotTersedia) ?>;

const areaSelect = document.getElementById('area_id');
const slotSelect = document.getElementById('slot_id');
const notif = document.getElementById('notif');

function tampilkanNotif(pesan, sukses) {
    notif.textContent = pesan;
    notif.classList.remove('hidden', 'bg-tertiary-container', 'text-on-tertiary-container', 'bg-error-container', 'text-error');
    notif.classList.add(sukses ? 'bg-tertiary-container' : 'bg-error-container', sukses ? 'text-on-tertiary-container' : 'text-error');
    notif.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function perbaruiPilihanSlot() {
    const areaId = areaSelect.value;
    slotSelect.innerHTML = '';

    if (!areaId) {
        slotSelect.innerHTML = '<option value="">-- Pilih area dulu --</option>';
        return;
    }

    const cocok = SLOT_TERSEDIA.filter(s => String(s.area_id) === String(areaId));
    if (cocok.length === 0) {
        slotSelect.innerHTML = '<option value="">Tidak ada slot tersedia di area ini</option>';
        return;
    }

    slotSelect.innerHTML = '<option value="">-- Pilih slot --</option>' +
        cocok.map(s => `<option value="${s.id}">${s.kode_slot}</option>`).join('');
}

if (areaSelect) {
    areaSelect.addEventListener('change', perbaruiPilihanSlot);
    perbaruiPilihanSlot();
}

const formBooking = document.getElementById('form-booking');
if (formBooking) {
    formBooking.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = formBooking.querySelector('button[type="submit"]');
        const asli = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Memproses...';

        try {
            const formData = new FormData(formBooking);
            formData.append('aksi', 'buat_booking');
            const res = await fetch('aksi_booking.php', { method: 'POST', body: formData });
            const hasil = await res.json();
            tampilkanNotif(hasil.message, hasil.success);
            if (hasil.success) {
                setTimeout(() => window.location.reload(), 800);
            } else {
                btn.disabled = false;
                btn.textContent = asli;
            }
        } catch (err) {
            tampilkanNotif('Tidak dapat terhubung ke server. Coba lagi.', false);
            btn.disabled = false;
            btn.textContent = asli;
        }
    });
}

document.querySelectorAll('.btn-batalkan').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Batalkan booking ini?')) return;
        btn.disabled = true;
        try {
            const formData = new FormData();
            formData.append('aksi', 'batalkan_booking');
            formData.append('booking_id', btn.dataset.bookingId);
            const res = await fetch('aksi_booking.php', { method: 'POST', body: formData });
            const hasil = await res.json();
            tampilkanNotif(hasil.message, hasil.success);
            if (hasil.success) {
                setTimeout(() => window.location.reload(), 800);
            } else {
                btn.disabled = false;
            }
        } catch (err) {
            tampilkanNotif('Tidak dapat terhubung ke server. Coba lagi.', false);
            btn.disabled = false;
        }
    });
});
</script>
</body>
</html>