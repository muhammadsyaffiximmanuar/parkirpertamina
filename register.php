<?php
/**
 * register.php (PRIVAT — khusus orang perusahaan)
 *
 * Halaman ini SENGAJA tidak ditautkan dari login.html / landingpage.php.
 * Hanya boleh diakses oleh orang yang tahu URL-nya secara langsung
 * (dibagikan HR/Admin ke karyawan baru), dan tetap dijaga oleh 3 lapis:
 *
 *   1. Kode Registrasi Perusahaan — rahasia, lihat KODE_REGISTRASI_PERUSAHAAN
 *      di config.php. Ganti kode ini secara berkala.
 *   2. Domain email wajib @pertamina.com (lihat EMAIL_DOMAIN_PERUSAHAAN).
 *   3. Akun baru otomatis berstatus 'Non-Aktif' -> WAJIB diaktifkan
 *      manual oleh Admin/Super Admin (menu Pengguna) sebelum bisa login.
 *      Jadi walau seseorang lolos kode + domain, dia tetap tidak bisa
 *      masuk ke sistem sampai disetujui.
 *
 * Role yang diberikan ke pendaftar baru selalu role paling rendah
 * (Officer) — role lain (Admin/Owner/dst) hanya bisa diubah manual
 * oleh Admin lewat database/menu pengguna, TIDAK bisa dipilih sendiri
 * lewat form ini.
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
    'nama_lengkap' => '',
    'username'     => '',
    'email'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $namaLengkap    = trim($_POST['nama_lengkap'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';
    $kodeRegistrasi = trim($_POST['kode_registrasi'] ?? '');

    $old['nama_lengkap'] = $namaLengkap;
    $old['username']     = $username;
    $old['email']        = $email;

    // 1. Validasi field wajib
    if ($namaLengkap === '' || $username === '' || $email === '' || $password === '') {
        $errors[] = 'Semua field wajib diisi.';
    }

    // 2. Validasi kode registrasi perusahaan (lapis privasi utama)
    if ($kodeRegistrasi === '' || !hash_equals(KODE_REGISTRASI_PERUSAHAAN, $kodeRegistrasi)) {
        $errors[] = 'Kode registrasi perusahaan salah atau kosong. Hubungi HR/Admin untuk mendapatkan kode ini.';
    }

    // 3. Validasi domain email perusahaan
    if ($email !== '' && !str_ends_with(strtolower($email), strtolower(EMAIL_DOMAIN_PERUSAHAAN))) {
        $errors[] = 'Email harus menggunakan domain perusahaan (' . EMAIL_DOMAIN_PERUSAHAAN . ').';
    }

    // 4. Validasi username (huruf/angka/underscore/titik saja)
    if ($username !== '' && !preg_match('/^[a-zA-Z0-9._]{4,50}$/', $username)) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, dan underscore (minimal 4 karakter).';
    }

    // 5. Validasi password
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }
    if ($password !== $konfirmasi) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok.';
    }

    // 6. Cek koneksi database
    if (empty($errors) && !$pdo) {
        $errors[] = 'Koneksi database tidak tersedia. Coba lagi nanti.';
    }

    // 7. Cek username/email sudah dipakai
    if (empty($errors)) {
        $cek = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $cek->execute(['u' => $username, 'e' => $email]);
        if ($cek->fetch()) {
            $errors[] = 'Username atau email sudah terdaftar.';
        }
    }

    // 8. Simpan pendaftaran baru (role terendah, status Non-Aktif menunggu approval)
    if (empty($errors)) {
        $roleOfficer = $pdo->prepare('SELECT id FROM roles WHERE nama_role = :r LIMIT 1');
        $roleOfficer->execute(['r' => ROLE_OFFICER]);
        $roleRow = $roleOfficer->fetch();

        if (!$roleRow) {
            $errors[] = 'Role default tidak ditemukan di sistem. Hubungi Admin.';
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
                'status'   => 'Non-Aktif', // wajib diaktifkan manual oleh Admin
            ]);

            $newUserId = $pdo->lastInsertId();
            catat_log($newUserId, "Registrasi Akun Baru ($username) - Menunggu Aktivasi Admin", 'Lainnya');

            $success = true;
            $old = ['nama_lengkap' => '', 'username' => '', 'email' => ''];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi Akun - Sistem Manajemen Parkir Gedung Pertamina</title>
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
      <span class="material-symbols-outlined text-primary text-[32px]">badge</span>
    </div>
    <h1 class="font-headline-lg text-headline-lg text-on-background px-md">Registrasi Akun Internal</h1>
    <p class="font-body-md text-body-md text-on-surface-variant mt-sm">
      Khusus karyawan PT Pertamina (Persero) &mdash; wajib kode registrasi dari HR/Admin
    </p>
  </div>

  <div class="bg-white/90 rounded-xl shadow-lg p-xl">

    <?php if ($success): ?>
      <div class="text-center space-y-md">
        <span class="material-symbols-outlined text-tertiary text-[48px]">check_circle</span>
        <h2 class="font-title-md text-title-md">Pendaftaran Berhasil Dikirim</h2>
        <p class="text-body-md text-on-surface-variant">
          Akun Anda telah dibuat dengan status <strong>Non-Aktif</strong>. Admin akan
          memverifikasi dan mengaktifkan akun Anda sebelum bisa digunakan untuk login.
          Silakan hubungi Admin/HR jika ingin mempercepat proses aktivasi.
        </p>
        <a href="login.html" class="inline-block mt-md text-primary font-bold hover:underline">Kembali ke halaman Masuk</a>
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
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="email">Email Perusahaan</label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="email" name="email" type="email" required placeholder="nama<?= htmlspecialchars(EMAIL_DOMAIN_PERUSAHAAN) ?>" value="<?= htmlspecialchars($old['email']) ?>">
          <p class="text-label-md text-on-surface-variant">Wajib domain <?= htmlspecialchars(EMAIL_DOMAIN_PERUSAHAAN) ?></p>
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

        <div class="space-y-xs pt-sm border-t border-outline-variant/40">
          <label class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider block" for="kode_registrasi">
            Kode Registrasi Perusahaan
          </label>
          <input class="w-full px-md py-sm bg-surface-container-lowest border border-outline/20 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-lg"
                 id="kode_registrasi" name="kode_registrasi" type="password" required placeholder="Dapatkan dari HR/Admin">
          <p class="text-label-md text-on-surface-variant">Kode ini bersifat rahasia, jangan dibagikan ke pihak luar perusahaan.</p>
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
    &copy; <?= date('Y') ?> PT Pertamina (Persero). Halaman internal &mdash; jangan disebarluaskan ke luar perusahaan.
  </p>
</main>
</body>
</html>