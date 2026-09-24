<?php
/**
 * register_pelanggan.php (PUBLIK — untuk pelanggan umum)
 *
 * Berbeda dari register.php (privat, khusus karyawan Pertamina):
 *   - TIDAK memerlukan kode registrasi perusahaan.
 *   - TIDAK dibatasi domain email @pertamina.com — email apa pun boleh.
 *   - Akun baru langsung berstatus 'Aktif' (tidak perlu approval Admin),
 *     karena ini pendaftaran publik untuk pelanggan, bukan akses internal.
 *   - Role yang diberikan selalu ROLE_PELANGGAN, tidak bisa dipilih sendiri.
 *
 * Halaman ini SENGAJA ditautkan dari login.html, karena memang untuk umum.
 */
session_start();
require_once 'config.php';

// Jika sudah login, tidak perlu daftar lagi.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . dashboard_url_for_role($_SESSION['role'] ?? ''));
    exit;
}

$errors  = [];
$success = false;

// Nilai form (dikembalikan lagi ke input jika gagal, kecuali password)
$old = [
    'nama_lengkap'   => '',
    'username'       => '',
    'email'          => '',
    'plat_nomor'     => '',
    'no_hp'          => '',
    'tipe_kendaraan' => 'Mobil',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $namaLengkap   = trim($_POST['nama_lengkap'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $konfirmasi    = $_POST['konfirmasi_password'] ?? '';
    $platNomor     = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $noHp          = trim($_POST['no_hp'] ?? '');
    $tipeKendaraan = trim($_POST['tipe_kendaraan'] ?? '');

    $old['nama_lengkap']   = $namaLengkap;
    $old['username']       = $username;
    $old['email']          = $email;
    $old['plat_nomor']     = $platNomor;
    $old['no_hp']          = $noHp;
    $old['tipe_kendaraan'] = $tipeKendaraan ?: 'Mobil';

    // 1. Validasi field wajib
    if ($namaLengkap === '' || $username === '' || $email === '' || $password === '' || $platNomor === '' || $noHp === '') {
        $errors[] = 'Semua field wajib diisi, termasuk plat nomor dan nomor HP kendaraan.';
    }

    // 1a. Validasi format no HP (longgar: angka, +, spasi, strip, 8-15 karakter)
    if ($noHp !== '' && !preg_match('/^[0-9+\s\-]{8,15}$/', $noHp)) {
        $errors[] = 'Format nomor HP tidak valid.';
    }

    // 1b. Validasi tipe kendaraan
    if (!in_array($tipeKendaraan, tipe_kendaraan_tersedia(), true)) {
        $errors[] = 'Tipe kendaraan tidak valid.';
    }

    // 1c. Validasi format plat nomor (longgar: huruf, angka, spasi/strip, 4-12 karakter)
    if ($platNomor !== '' && !preg_match('/^[A-Z0-9\s\-]{4,12}$/', $platNomor)) {
        $errors[] = 'Format plat nomor tidak valid.';
    }

    // 2. Validasi format email umum (bukan domain khusus)
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    // 3. Validasi username (huruf/angka/underscore/titik saja)
    if ($username !== '' && !preg_match('/^[a-zA-Z0-9._]{4,50}$/', $username)) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, dan underscore (minimal 4 karakter).';
    }

    // 4. Validasi password
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }
    if ($password !== $konfirmasi) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok.';
    }

    // 5. Cek koneksi database
    if (empty($errors) && !$pdo) {
        $errors[] = 'Koneksi database tidak tersedia. Coba lagi nanti.';
    }

    // 6. Cek username/email sudah dipakai
    if (empty($errors)) {
        $cek = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $cek->execute(['u' => $username, 'e' => $email]);
        if ($cek->fetch()) {
            $errors[] = 'Username atau email sudah terdaftar.';
        }
    }

    // 6b. Cek plat nomor sudah terdaftar
    if (empty($errors)) {
        $cekPlat = $pdo->prepare('SELECT id FROM kendaraan WHERE plat_nomor = :p LIMIT 1');
        $cekPlat->execute(['p' => $platNomor]);
        if ($cekPlat->fetch()) {
            $errors[] = 'Plat nomor ini sudah terdaftar. Hubungi Admin jika ini kesalahan.';
        }
    }

    // 7. Simpan pendaftaran baru (role Pelanggan, langsung Aktif)
    if (empty($errors)) {
        $rolePelanggan = $pdo->prepare('SELECT id FROM roles WHERE nama_role = :r LIMIT 1');
        $rolePelanggan->execute(['r' => ROLE_PELANGGAN]);
        $roleRow = $rolePelanggan->fetch();

        if (!$roleRow) {
            $errors[] = 'Role Pelanggan belum tersedia di sistem. Hubungi Admin untuk menambahkan role ini ke tabel roles.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (nama_lengkap, username, email, password_hash, role_id, status)
                 VALUES (:nama, :username, :email, :hash, :role_id, :status)'
            );
            $stmt->execute([
                'nama'     => $namaLengkap,
                'username' => $username,
                'email'    => $email,
                'hash'     => password_hash($password, PASSWORD_BCRYPT),
                'role_id'  => $roleRow['id'],
                'status'   => 'Aktif', // pendaftaran publik, langsung bisa login
            ]);

            $newUserId = $pdo->lastInsertId();
            catat_log($newUserId, "Registrasi Pelanggan Baru ($username)", 'Lainnya');

            // Daftarkan kendaraan pelanggan ini juga, ditautkan ke akunnya.
            // status_verifikasi dibiarkan default ('Menunggu Verifikasi') karena ini
            // pendaftaran mandiri via web, sama seperti pola sumber_registrasi lain
            // yang sudah ada di tabel kendaraan. Admin perlu memverifikasi dulu
            // sebelum kendaraan ini bisa dipakai untuk booking (lihat aksi_booking.php).
            $stmtKendaraan = $pdo->prepare(
                'INSERT INTO kendaraan (kode_kendaraan, plat_nomor, no_hp, tipe, nama_pemilik, terdaftar_oleh_user_id, sumber_registrasi)
                 VALUES (:kode, :plat, :no_hp, :tipe, :nama_pemilik, :user_id, :sumber)'
            );
            $stmtKendaraan->execute([
                'kode'         => generate_kode_kendaraan(),
                'plat'         => $platNomor,
                'no_hp'        => $noHp,
                'tipe'         => $tipeKendaraan,
                'nama_pemilik' => $namaLengkap,
                'user_id'      => $newUserId,
                'sumber'       => 'Sistem Online',
            ]);

            $success = true;
            $old = ['nama_lengkap' => '', 'username' => '', 'email' => '', 'plat_nomor' => '', 'no_hp' => '', 'tipe_kendaraan' => 'Mobil'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar sebagai Pelanggan - Sistem Manajemen Parkir Gedung Pertamina</title>
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
          "tertiary-fixed": "#b2f655", "on-tertiary-fixed": "#112000"
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
<body class="bg-background text-on-background min-h-screen flex items-center justify-center p-md">
<main class="w-full max-w-[480px] py-xl">

  <div class="text-center mb-xl">
    <div class="inline-flex items-center justify-center p-md bg-white rounded-xl shadow-sm mb-lg">
      <span class="material-symbols-outlined text-primary text-[32px]">person_add</span>
    </div>
    <h1 class="font-headline-lg text-headline-lg text-on-background px-md">Daftar sebagai Pelanggan</h1>
    <p class="font-body-md text-body-md text-on-surface-variant mt-sm">
      Untuk pengguna umum &mdash; buat akun untuk mengakses layanan parkir
    </p>
  </div>

  <div class="bg-white/90 rounded-xl shadow-lg p-xl">

    <?php if ($success): ?>
      <div class="text-center space-y-md">
        <span class="material-symbols-outlined text-tertiary text-[48px]">check_circle</span>
        <h2 class="font-title-md text-title-md">Pendaftaran Berhasil</h2>
        <p class="text-body-md text-on-surface-variant">
          Akun Anda sudah aktif dan siap digunakan. Silakan masuk menggunakan
          username dan kata sandi yang baru saja Anda daftarkan untuk mulai booking slot parkir.
        </p>
        <a href="login.html" class="inline-block mt-md text-primary font-bold hover:underline">Masuk sekarang</a>
      </div>
    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="mb-lg text-error text-body-md bg-error-container/40 border border-error/20 rounded-lg px-md py-sm space-y-1">
          <?php foreach ($errors as $e): ?>
            <p>&bull; <?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-lg">

        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="nama_lengkap">Nama Lengkap</label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="nama_lengkap" name="nama_lengkap" type="text" required value="<?= htmlspecialchars($old['nama_lengkap']) ?>">
        </div>

        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="username">Username</label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="username" name="username" type="text" required minlength="4" placeholder="cth: budi.k" value="<?= htmlspecialchars($old['username']) ?>">
        </div>

        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="email">Email</label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="email" name="email" type="email" required placeholder="nama@email.com" value="<?= htmlspecialchars($old['email']) ?>">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
          <div class="space-y-xs">
            <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="password">Kata Sandi</label>
            <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                   id="password" name="password" type="password" required minlength="8">
          </div>
          <div class="space-y-xs">
            <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="konfirmasi_password">Ulangi Kata Sandi</label>
            <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                   id="konfirmasi_password" name="konfirmasi_password" type="password" required minlength="8">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md pt-sm border-t border-outline-variant/40">
          <div class="space-y-xs">
            <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="plat_nomor">Plat Nomor Kendaraan</label>
            <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg uppercase"
                   id="plat_nomor" name="plat_nomor" type="text" required placeholder="cth: B 1234 ABC" value="<?= htmlspecialchars($old['plat_nomor']) ?>">
          </div>
          <div class="space-y-xs">
            <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="tipe_kendaraan">Tipe Kendaraan</label>
            <select class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                    id="tipe_kendaraan" name="tipe_kendaraan">
              <?php foreach (tipe_kendaraan_tersedia() as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $old['tipe_kendaraan'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="space-y-xs">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="no_hp">No. HP Pemilik Kendaraan</label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="no_hp" name="no_hp" type="tel" required placeholder="cth: 081234567890" value="<?= htmlspecialchars($old['no_hp']) ?>">
        </div>

        <button class="w-full bg-primary text-on-primary font-title-md text-title-md py-md rounded-lg shadow-md hover:shadow-lg active:scale-[0.98] transition-all" type="submit">
          Daftar
        </button>
      </form>

      <p class="text-center text-body-md text-on-surface-variant mt-lg">
        Sudah punya akun? <a href="login.html" class="text-primary font-bold hover:underline">Masuk di sini</a>
      </p>
    <?php endif; ?>
  </div>

  <p class="text-center text-label-md text-on-surface-variant mt-lg">
    &copy; <?= date('Y') ?> PT Pertamina (Persero). Layanan untuk pelanggan umum.
  </p>
</main>
</body>
</html>