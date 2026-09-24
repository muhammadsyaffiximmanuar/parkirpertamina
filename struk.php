<?php
/**
 * struk.php
 * Halaman cetak struk transaksi parkir.
 *
 * - Kalau transaksi masih status 'Masuk' (belum keluar/belum bayar), halaman ini
 *   menampilkan tombol "Proses Pembayaran & Catat Keluar" yang memanggil
 *   transaksi.php (PUT) untuk menutup transaksi, lalu me-reload struk sebagai LUNAS.
 * - Kalau sudah 'Keluar' (lunas), halaman ini hanya menampilkan struk final untuk dicetak.
 *
 * Akses: ?id=<id_transaksi>  atau  ?kode=<kode_parkir>
 */
session_start();
require_once 'config.php';
require_login_page();

$id   = (int) ($_GET['id'] ?? 0);
$kode = trim($_GET['kode'] ?? '');

if ($id <= 0 && $kode === '') {
    die('Parameter id atau kode struk tidak ditemukan.');
}

if ($id > 0) {
    $trx = db_fetch_one(
        'SELECT t.*, k.plat_nomor, k.tipe AS tipe_kendaraan, k.nama_pemilik,
                sp.kode_slot, l.nama_lantai, u.nama_lengkap AS petugas
         FROM transaksi t
         JOIN kendaraan k ON k.id = t.kendaraan_id
         LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
         LEFT JOIN area a ON a.id = sp.area_id
         LEFT JOIN lantai l ON l.id = a.lantai_id
         LEFT JOIN users u ON u.id = t.petugas_id
         WHERE t.id = :id LIMIT 1',
        ['id' => $id]
    );
} else {
    $trx = db_fetch_one(
        'SELECT t.*, k.plat_nomor, k.tipe AS tipe_kendaraan, k.nama_pemilik,
                sp.kode_slot, l.nama_lantai, u.nama_lengkap AS petugas
         FROM transaksi t
         JOIN kendaraan k ON k.id = t.kendaraan_id
         LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
         LEFT JOIN area a ON a.id = sp.area_id
         LEFT JOIN lantai l ON l.id = a.lantai_id
         LEFT JOIN users u ON u.id = t.petugas_id
         WHERE t.kode_parkir = :kode LIMIT 1',
        ['kode' => $kode]
    );
}

if (!$trx) {
    die('Transaksi tidak ditemukan.');
}

$sudahKeluar = $trx['status'] === 'Keluar' && !empty($trx['waktu_keluar']);

// Dihitung via MySQL (bukan PHP strtotime), supaya tidak ada selisih
// zona waktu antara PHP (Asia/Jakarta) dan server MySQL (biasanya UTC).
$durasiRow = db_fetch_one(
    $sudahKeluar
        ? 'SELECT TIMESTAMPDIFF(MINUTE, waktu_masuk, waktu_keluar) AS menit FROM transaksi WHERE id = :id'
        : 'SELECT TIMESTAMPDIFF(MINUTE, waktu_masuk, NOW()) AS menit FROM transaksi WHERE id = :id',
    ['id' => $trx['id']],
    ['menit' => 0]
);
$durasiMenit = max(0, (int) $durasiRow['menit']);
$jam  = intdiv($durasiMenit, 60);
$menit = $durasiMenit % 60;
$durasiText = ($jam > 0 ? $jam . ' jam ' : '') . $menit . ' menit';

$metodeTersedia = metode_pembayaran_tersedia();
$labelMetode = $metodeTersedia[$trx['metode_bayar']] ?? ($trx['metode_bayar'] ?: '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Struk Parkir - <?= htmlspecialchars($trx['kode_parkir']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Inter', sans-serif;
    background: #eee;
    margin: 0;
    padding: 24px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .struk {
    background: #fff;
    width: 320px;
    max-width: 100%;
    padding: 20px 18px;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.12);
  }
  .struk-center { text-align: center; }
  .logo-circle {
    width: 44px; height: 44px; border-radius: 999px;
    background: #b5000b; color: #fff; display: flex;
    align-items: center; justify-content: center;
    font-weight: 800; font-size: 18px; margin: 0 auto 8px;
  }
  h1 { font-size: 14px; margin: 0; letter-spacing: .02em; }
  .subtitle { font-size: 11px; color: #666; margin: 2px 0 0; }
  .divider {
    border: none; border-top: 1px dashed #ccc; margin: 14px 0;
  }
  .baris {
    display: flex; justify-content: space-between; gap: 8px;
    font-size: 12.5px; margin-bottom: 6px; color: #222;
  }
  .baris span:first-child { color: #777; }
  .baris .mono { font-family: 'Roboto Mono', monospace; }
  .kode-parkir {
    text-align: center; font-family: 'Roboto Mono', monospace;
    font-size: 18px; font-weight: 700; letter-spacing: 1px;
    margin: 4px 0 2px; color: #b5000b;
  }
  .qr-wrap {
    display: flex; justify-content: center; margin: 10px 0 8px;
  }
  .qr-wrap canvas, .qr-wrap img {
    border: 6px solid #fff; box-shadow: 0 0 0 1px #eee; border-radius: 6px;
  }
  .status-badge {
    display: inline-block; margin: 0 auto 10px; padding: 3px 10px;
    border-radius: 999px; font-size: 10.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
  }
  .status-lunas { background: #e6f4d7; color: #3e6300; }
  .status-belum { background: #ffdad6; color: #93000a; }
  .total-box {
    background: #fff5f3; border: 1px solid #f3c9c4; border-radius: 8px;
    padding: 12px 14px; margin: 12px 0;
  }
  .total-box .baris { font-size: 13px; margin-bottom: 4px; }
  .total-box .grand {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e5b6b1;
  }
  .total-box .grand span:first-child { font-size: 12px; font-weight: 700; color: #333; }
  .total-box .grand span:last-child { font-size: 20px; font-weight: 800; color: #b5000b; }
  .footer-note { text-align: center; font-size: 11px; color: #888; margin-top: 14px; line-height: 1.6; }

  /* ==== Panel proses pembayaran (hanya muncul saat status Belum Dibayar) ==== */
  .bayar-panel {
    width: 320px; max-width: 100%; background: #fff; border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.12); padding: 16px 18px; margin-top: 16px;
  }
  .bayar-panel h2 { font-size: 13px; margin: 0 0 10px; color: #333; }
  .metode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
  .metode-opt {
    display: flex; align-items: center; gap: 6px; font-size: 12.5px;
    border: 1px solid #ddd; border-radius: 6px; padding: 8px 10px; cursor: pointer;
  }
  .metode-opt:has(input:checked) { border-color: #b5000b; background: #fff5f3; }
  .btn-bayar {
    width: 100%; background: #b5000b; color: #fff; border: none; border-radius: 8px;
    padding: 10px 12px; font-size: 13px; font-weight: 700; cursor: pointer;
  }
  .btn-bayar:disabled { opacity: .6; cursor: not-allowed; }
  .msg-box {
    font-size: 12px; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; display: none;
  }
  .msg-error { background: #ffdad6; color: #93000a; }
  .msg-sukses { background: #e6f4d7; color: #3e6300; }

  .actions {
    width: 320px; max-width: 100%; display: flex; gap: 10px; margin-top: 16px;
  }
  .btn {
    flex: 1; text-align: center; padding: 10px 12px; border-radius: 8px;
    font-size: 13px; font-weight: 700; cursor: pointer; border: none;
    text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  }
  .btn-print { background: #b5000b; color: #fff; }
  .btn-back { background: #fff; color: #444; border: 1px solid #ccc; }

  @media print {
    body { background: #fff; padding: 0; }
    .actions, .bayar-panel { display: none; }
    .struk { box-shadow: none; width: 100%; }
  }
</style>
</head>
<body>

  <div class="struk">
    <div class="struk-center">
      <div class="logo-circle">P</div>
      <h1>PARKIR GEDUNG PERTAMINA</h1>
      <p class="subtitle">Jl. Medan Merdeka Timur, Jakarta Pusat</p>
    </div>

    <hr class="divider">

    <div class="struk-center">
      <p class="kode-parkir"><?= htmlspecialchars($trx['kode_parkir']) ?></p>
      <div id="qrCodeStruk" class="qr-wrap"></div>
      <span class="status-badge <?= $sudahKeluar ? 'status-lunas' : 'status-belum' ?>" id="statusBadge">
        <?= $sudahKeluar ? 'Lunas' : 'Belum Dibayar' ?>
      </span>
    </div>

    <hr class="divider">

    <div class="baris"><span>Plat Nomor</span><span class="mono"><?= htmlspecialchars($trx['plat_nomor']) ?></span></div>
    <div class="baris"><span>Tipe Kendaraan</span><span><?= htmlspecialchars($trx['tipe_kendaraan']) ?></span></div>
    <div class="baris"><span>Slot</span><span><?= htmlspecialchars($trx['kode_slot'] ?? '-') ?> <?= $trx['nama_lantai'] ? '('.htmlspecialchars($trx['nama_lantai']).')' : '' ?></span></div>
    <div class="baris"><span>Waktu Masuk</span><span><?= date('d/m/Y H:i', strtotime($trx['waktu_masuk'])) ?></span></div>
    <div class="baris"><span>Waktu Keluar</span><span id="waktuKeluarText"><?= $sudahKeluar ? date('d/m/Y H:i', strtotime($trx['waktu_keluar'])) : '-' ?></span></div>
    <div class="baris"><span>Durasi</span><span id="durasiText"><?= $durasiText ?></span></div>
    <div class="baris"><span>Petugas</span><span><?= htmlspecialchars($trx['petugas'] ?? '-') ?></span></div>

    <div class="total-box">
      <div class="baris"><span>Metode Pembayaran</span><span id="metodeText"><?= htmlspecialchars($labelMetode) ?></span></div>
      <div class="grand">
        <span>TOTAL BAYAR</span>
        <span id="totalBayarText"><?= format_rupiah($trx['biaya']) ?></span>
      </div>
    </div>

    <p class="footer-note">
      Simpan struk ini sebagai bukti pembayaran.<br>
      Terima kasih telah menggunakan layanan parkir kami.
    </p>
  </div>

  <?php if (!$sudahKeluar): ?>
  <!-- Panel proses pembayaran & catat keluar, hanya muncul saat status masih Belum Dibayar -->
  <div class="bayar-panel" id="bayarPanel">
    <h2>Proses Pembayaran &amp; Catat Keluar</h2>

    <p id="bayarError" class="msg-box msg-error"></p>
    <p id="bayarSukses" class="msg-box msg-sukses"></p>

    <div class="metode-grid" id="metodeGrid">
      <?php foreach ($metodeTersedia as $kode => $label): ?>
      <label class="metode-opt">
        <input type="radio" name="metode_bayar_struk" value="<?= htmlspecialchars($kode) ?>" <?= $kode === 'Tunai' ? 'checked' : '' ?>>
        <span><?= htmlspecialchars($label) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <button class="btn-bayar" id="btnProsesKeluar" onclick="prosesKeluar()">💳 Proses Pembayaran &amp; Catat Keluar</button>
  </div>
  <?php endif; ?>

  <div class="actions">
    <button class="btn btn-print" onclick="window.print()">🖨️ Cetak Struk</button>
    <a class="btn btn-back" href="riwayat.php">Kembali</a>
  </div>

<?php if (!$sudahKeluar): ?>
<script>
async function prosesKeluar() {
    const errBox = document.getElementById('bayarError');
    const sukBox = document.getElementById('bayarSukses');
    const btn = document.getElementById('btnProsesKeluar');
    errBox.style.display = 'none';
    sukBox.style.display = 'none';

    const metodeBayar = document.querySelector('input[name="metode_bayar_struk"]:checked')?.value;

    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
        const res = await fetch('transaksi.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                id: <?= (int) $trx['id'] ?>,
                metode_bayar: metodeBayar
            })
        });
        const data = await res.json();

        if (data.success) {
            sukBox.textContent = 'Pembayaran berhasil diproses. Struk diperbarui...';
            sukBox.style.display = 'block';
            setTimeout(() => window.location.reload(), 800);
        } else {
            errBox.textContent = data.message || 'Gagal memproses pembayaran.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = '💳 Proses Pembayaran & Catat Keluar';
        }
    } catch (err) {
        errBox.textContent = 'Tidak dapat terhubung ke server.';
        errBox.style.display = 'block';
        btn.disabled = false;
        btn.textContent = '💳 Proses Pembayaran & Catat Keluar';
    }
}
</script>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrCodeStruk'), {
    text: <?= json_encode($trx['kode_parkir']) ?>,
    width: 120,
    height: 120,
    correctLevel: QRCode.CorrectLevel.M
});
</script>

</body>
</html>