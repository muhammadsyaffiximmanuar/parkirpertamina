<?php
/**
 * config.php
 * File koneksi database untuk aplikasi Pertamina Parking Management.
 * Versi ini dikonfigurasi untuk XAMPP (Apache + MySQL lokal).
 *
 * Sebelum menjalankan:
 * 1. Nyalakan Apache & MySQL di XAMPP Control Panel.
 * 2. Buka http://localhost/phpmyadmin
 * 3. Buat database baru bernama "db_parkirpertamina" (nama ini harus
 *    sama persis dengan DB_NAME di bawah).
 * 4. Import file db_parkir_pertamina.sql ke database tersebut lewat
 *    tab "Import" di phpMyAdmin. File ini berisi semua tabel yang
 *    dipakai project (area, booking, kendaraan, komentar, lantai,
 *    log_aktivitas, okupansi_per_jam, roles, slot_parkir, tarif,
 *    transaksi, users) lengkap dengan data yang sudah ada.
 * 5. Taruh folder project ini di dalam htdocs (Laragon: folder mana pun
 *    juga bisa via virtual host), lalu akses lewat
 *    http://localhost/nama-folder-project/
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

// ================== KONEKSI DATABASE (XAMPP LOKAL) ==================
define('DB_HOST', 'localhost');
define('DB_NAME', 'parkir_pertamina'); // <-- harus sama dengan nama database yang kamu buat di phpMyAdmin
define('DB_USER', 'root');
define('DB_PASS', '');                  // default XAMPP: kosong. Kalau MySQL kamu diberi password, isi di sini.
define('DB_CHARSET', 'utf8mb4');

/*
 * Kredensial hosting online (InfinityFree) yang lama — disimpan sebagai
 * referensi saja, TIDAK dipakai saat DB_HOST = 'localhost' di atas.
 * Kalau nanti mau deploy ulang ke InfinityFree, tinggal aktifkan lagi
 * 4 baris define() di atas dengan nilai-nilai ini:
 *
 * DB_HOST = 'sql305.infinityfree.com'
 * DB_NAME = 'if0_42701768_db_parkirpertamina'
 * DB_USER = 'if0_42701768'
 * DB_PASS = 'sapekgtg16'
 */

/**
 * ============================================================
 *  PENGATURAN REGISTRASI PRIVAT (khusus orang perusahaan)
 * ============================================================
 * EMAIL_DOMAIN_PERUSAHAAN : hanya email dengan domain ini yang
 *   boleh mendaftar lewat register.php.
 * KODE_REGISTRASI_PERUSAHAAN : kode rahasia yang HANYA dibagikan
 *   secara internal (WA/email HR) ke karyawan baru. Ganti kode ini
 *   secara berkala. JANGAN ditaruh di halaman publik mana pun.
 */
define('EMAIL_DOMAIN_PERUSAHAAN', '@pertamina.com');
define('KODE_REGISTRASI_PERUSAHAAN', 'PTM-2026-INTERNAL');

/**
 * $pdo akan bernilai objek PDO jika koneksi berhasil,
 * atau null jika database belum tersedia (mis. belum diimport).
 * Semua halaman memakai fallback data dummy bila $pdo null,
 * sehingga aplikasi tetap bisa dibuka meski database belum disiapkan.
 */
$pdo = null;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Database belum tersedia / belum diimport.
    // Simpan pesan supaya bisa ditampilkan sebagai notifikasi non-fatal.
    $GLOBALS['db_connection_error'] = $e->getMessage();
    $pdo = null;
}

/**
 * Helper: jalankan query SELECT dan kembalikan array hasil.
 * Jika koneksi database tidak tersedia atau query gagal,
 * kembalikan $fallback supaya tampilan tidak rusak.
 */
function db_fetch_all($sql, $params = [], $fallback = [])
{
    global $pdo;
    if (!$pdo) return $fallback;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return $fallback;
    }
}

/**
 * Helper: jalankan query yang mengembalikan satu baris.
 */
function db_fetch_one($sql, $params = [], $fallback = null)
{
    global $pdo;
    if (!$pdo) return $fallback;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: $fallback;
    } catch (PDOException $e) {
        return $fallback;
    }
}

/**
 * Helper: jalankan query INSERT / UPDATE / DELETE.
 * Dipakai untuk aksi tulis-data (mis. aksi_kelola_tarif.php).
 * Mengembalikan jumlah baris yang terpengaruh (rowCount).
 * Jika koneksi database tidak tersedia, melempar RuntimeException
 * supaya endpoint pemanggil tahu aksinya gagal (bukan diam-diam sukses).
 * PDOException dari query yang gagal (mis. constraint, kolom salah)
 * dibiarkan menjalar ke pemanggil, supaya tertangkap oleh
 * catch (Throwable $e) di endpoint dan tetap menghasilkan respons JSON.
 */
function db_execute($sql, $params = [])
{
    global $pdo;
    if (!$pdo) {
        throw new RuntimeException('Koneksi database tidak tersedia.');
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Helper: buat kode_kendaraan baru yang unik dengan format VHC-{tahun}-{urut}.
 * Contoh: VHC-2026-013. Nomor urut dihitung dari kode_kendaraan terbesar
 * yang sudah ada untuk tahun berjalan, lalu +1 (3 digit, padded dengan nol).
 * Dipakai oleh aksi_kelola_kendaraan.php saat menambah kendaraan baru,
 * supaya kolom kode_kendaraan (UNIQUE) tidak pernah dikirim kosong/duplikat.
 */
function generate_kode_kendaraan(): string
{
    $tahun  = date('Y');
    $prefix = "VHC-{$tahun}-";
    $row = db_fetch_one(
        "SELECT kode_kendaraan FROM kendaraan
         WHERE kode_kendaraan LIKE ?
         ORDER BY kode_kendaraan DESC LIMIT 1",
        [$prefix . '%'],
        null
    );
    $urut = 1;
    if ($row && !empty($row['kode_kendaraan'])) {
        $bagianUrut = substr($row['kode_kendaraan'], strlen($prefix));
        if (ctype_digit($bagianUrut)) {
            $urut = ((int) $bagianUrut) + 1;
        }
    }
    return $prefix . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
}

/**
 * Helper: format angka ke format Rupiah singkat, mis. 8200000 -> "Rp 8,2jt"
 */
function format_rupiah_singkat($angka)
{
    $angka = (float) $angka;
    if ($angka >= 1000000000) {
        return 'Rp ' . number_format($angka / 1000000000, 1, ',', '.') . 'M';
    }
    if ($angka >= 1000000) {
        return 'Rp ' . number_format($angka / 1000000, 1, ',', '.') . 'jt';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/**
 * Helper: kirim respons JSON dan hentikan eksekusi.
 * Menggantikan fungsi json_response() yang sebelumnya dipanggil
 * dari file '../includes/helpers.php' yang tidak pernah ada.
 */
function json_response(array $data, int $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Helper: pastikan pengguna sudah login sebelum endpoint API diakses.
 * Menggantikan require_login() yang sebelumnya dipanggil dari
 * '../config/database.php' / '../includes/helpers.php' yang tidak ada.
 * Mengembalikan data sesi (user_id, nama_lengkap, role) jika valid.
 */
function require_login(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Anda harus login terlebih dahulu.'], 401);
    }
    return [
        'user_id'      => $_SESSION['user_id'],
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
        'role'         => $_SESSION['role'] ?? '',
    ];
}

/**
 * Helper: pastikan pengguna sudah login untuk halaman HTML (bukan JSON).
 * Redirect ke login.html bila belum login. Dipakai di index.php,
 * aktivitas.php, laporan.php yang sebelumnya tidak punya proteksi sama sekali.
 */
function require_login_page(string $redirectTo = 'login.html'): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $redirectTo);
        exit;
    }
    return [
        'user_id'      => $_SESSION['user_id'],
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
        'role'         => $_SESSION['role'] ?? '',
    ];
}

/**
 * Helper: catat satu baris ke log_aktivitas (audit trail).
 */
function catat_log($userId, string $aktivitas, string $kategori = 'Lainnya')
{
    global $pdo;
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO log_aktivitas (user_id, aktivitas, kategori, ip_address)
             VALUES (:user_id, :aktivitas, :kategori, :ip)'
        );
        $stmt->execute([
            'user_id'   => $userId,
            'aktivitas' => $aktivitas,
            'kategori'  => $kategori,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } catch (PDOException $e) {
        // Diamkan: audit log gagal tidak boleh menggagalkan aksi utama.
    }
}

/**
 * ============================================================
 *  PEMBAYARAN & STRUK
 * ============================================================
 */

/**
 * Daftar metode pembayaran yang tersedia di loket.
 * Dipakai bersama oleh dashboard_petugas.php (form Catat Keluar),
 * transaksi.php (validasi backend), dan struk.php (tampilan struk).
 */
function metode_pembayaran_tersedia(): array
{
    return [
        'Tunai'      => 'Tunai / Cash',
        'QRIS'       => 'QRIS',
    ];
}

/**
 * Helper: hitung biaya parkir berdasarkan durasi & tarif per jam.
 * Aturan: dibulatkan ke atas per jam, minimal 1 jam.
 * (mis. 61 menit dihitung 2 jam)
 */
function hitung_biaya_parkir(int $durasiMenit, float $tarifPerJam): float
{
    $jam = (int) ceil($durasiMenit / 60);
    if ($jam < 1) $jam = 1;
    return $jam * $tarifPerJam;
}

/**
 * Helper: ambil tarif per jam aktif untuk sebuah tipe kendaraan.
 * Fallback ke 0 jika tidak ditemukan / tidak aktif.
 */
function ambil_tarif_per_jam(string $tipeKendaraan): float
{
    $row = db_fetch_one(
        "SELECT tarif_per_jam FROM tarif WHERE tipe_kendaraan = :t AND status = 'Aktif' LIMIT 1",
        ['t' => $tipeKendaraan],
        null
    );
    return $row ? (float) $row['tarif_per_jam'] : 0.0;
}

/**
 * ============================================================
 *  ROLE-BASED DASHBOARD ROUTING
 *  (nama_role persis seperti di tabel `roles`)
 * ============================================================
 */
define('ROLE_SUPER_ADMIN', 'Super Admin');
define('ROLE_OWNER', 'Owner');
define('ROLE_ADMIN', 'Admin');
define('ROLE_OFFICER', 'Officer');
define('ROLE_SECURITY', 'Security');

/**
 * ROLE_USER : karyawan pemilik kendaraan (self-service).
 * Berbeda dari role lain di atas — role ini BUKAN staf loket/manajemen,
 * melainkan karyawan biasa yang mendaftarkan kendaraan pribadi dan
 * memantau riwayat parkirnya sendiri lewat dashboard_user.php.
 * Pastikan nilai 'User' ini sama persis dengan isi kolom nama_role
 * di tabel `roles` pada database.
 */
define('ROLE_USER', 'User');

/**
 * ROLE_PELANGGAN : masyarakat umum / pelanggan non-karyawan Pertamina.
 * Berbeda dari ROLE_USER (karyawan): pendaftaran Pelanggan TIDAK dibatasi
 * domain email perusahaan dan TIDAK memerlukan kode registrasi internal
 * (lihat register_pelanggan.php, dibanding register.php yang privat
 * khusus karyawan). Akun Pelanggan otomatis 'Aktif' setelah daftar,
 * berbeda dengan karyawan yang wajib diaktifkan manual oleh Admin.
 * Pastikan nilai 'Pelanggan' ini sama persis dengan isi kolom nama_role
 * di tabel `roles` pada database.
 */
define('ROLE_PELANGGAN', 'Pelanggan');

/**
 * Helper: tentukan file dashboard yang sesuai untuk sebuah role.
 * - Owner & Super Admin  -> dashboard_owner.php     (ringkasan bisnis & finansial)
 * - Admin                -> dashboard_admin.php     (manajemen data & verifikasi)
 * - Officer & Security   -> dashboard_petugas.php   (operasional harian / shift)
 * - User                 -> dashboard_user.php      (self-service karyawan)
 * - Pelanggan            -> dashboard_pelanggan.php (self-service pelanggan umum)
 */
function dashboard_url_for_role(string $role): string
{
    switch ($role) {
        case ROLE_OWNER:
        case ROLE_SUPER_ADMIN:
            return 'dashboard_owner.php';
        case ROLE_ADMIN:
            return 'dashboard_admin.php';
        case ROLE_OFFICER:
        case ROLE_SECURITY:
            return 'dashboard_petugas.php';
        case ROLE_USER:
            return 'dashboard_user.php';
        case ROLE_PELANGGAN:
            return 'dashboard_pelanggan.php';
        default:
            // Role tidak dikenali: aman-kan ke dashboard paling terbatas.
            return 'dashboard_petugas.php';
    }
}

/**
 * Helper: proteksi halaman dashboard supaya hanya role tertentu yang
 * boleh mengaksesnya. Jika role pengguna tidak ada di $allowedRoles,
 * ia diarahkan ke dashboard yang memang sesuai role-nya (bukan error),
 * supaya tidak ada dead-end saat user salah buka link.
 *
 * Contoh pemakaian di atas file dashboard_admin.php:
 *   $session = require_login_page();
 *   require_role([ROLE_ADMIN]);
 */
function require_role(array $allowedRoles)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        header('Location: ' . dashboard_url_for_role($role));
        exit;
    }
}

/**
 * Helper: tema visual (warna aksen, label tampilan, ikon) untuk tiap role.
 * Satu sumber kebenaran dipakai oleh includes/sidebar.php & includes/topbar.php
 * supaya setiap role punya identitas visual berbeda di mana pun halaman itu
 * dibuka (dashboard, aktivitas.php, laporan.php, dst).
 *
 * 'color' harus salah satu dari warna yang sudah didefinisikan di
 * tailwind.config pada includes/head.php: 'primary' | 'secondary' | 'tertiary'.
 */
function role_theme(string $role): array
{
    switch ($role) {
        case ROLE_OWNER:
        case ROLE_SUPER_ADMIN:
            return ['color' => 'primary', 'label' => 'Owner', 'icon' => 'workspace_premium'];
        case ROLE_ADMIN:
            return ['color' => 'secondary', 'label' => 'Admin', 'icon' => 'admin_panel_settings'];
        case ROLE_OFFICER:
            return ['color' => 'tertiary', 'label' => 'Petugas Loket', 'icon' => 'badge'];
        case ROLE_SECURITY:
            return ['color' => 'tertiary', 'label' => 'Petugas Keamanan', 'icon' => 'shield_person'];
        case ROLE_USER:
            return ['color' => 'secondary', 'label' => 'Karyawan', 'icon' => 'directions_car'];
        case ROLE_PELANGGAN:
            return ['color' => 'secondary', 'label' => 'Pelanggan', 'icon' => 'person'];
        default:
            return ['color' => 'secondary', 'label' => ($role !== '' ? $role : 'Pengguna'), 'icon' => 'person'];
    }
}

/**
 * ============================================================
 *  TABEL roles (id <-> nama_role) — dipakai oleh Kelola User
 * ============================================================
 * Kolom `role_id` di tabel `users` adalah foreign key ke tabel `roles`.
 * Nama role di tabel `roles` (Super Admin, Owner, Admin, Officer,
 * Security, User) sama persis dengan konstanta ROLE_* di atas.
 */

/**
 * Helper: ambil id dari tabel roles berdasarkan nama role.
 * Mengembalikan null jika nama role tidak ditemukan.
 */
function ambil_role_id_by_nama(string $namaRole): ?int
{
    $row = db_fetch_one(
        "SELECT id FROM roles WHERE nama_role = ? LIMIT 1",
        [$namaRole],
        null
    );
    return $row ? (int) $row['id'] : null;
}

/**
 * Helper: buat username unik secara otomatis dari bagian sebelum '@' di
 * email (fallback ke nama lengkap jika email tidak bisa dipakai).
 * Kolom `username` di tabel users wajib diisi (NOT NULL) tapi form
 * Kelola User tidak selalu punya input untuk ini, jadi digenerate di sini.
 * Jika basis sudah dipakai, ditambah angka di belakangnya (mis. budi, budi2, budi3).
 */
function generate_username_unik(string $basis): string
{
    $basis = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $basis));
    $basis = trim($basis, '.');
    if ($basis === '') {
        $basis = 'user';
    }
    $basis = substr($basis, 0, 40); // sisakan ruang untuk angka suffix (kolom varchar(50))

    $username = $basis;
    $i = 1;
    while (true) {
        $row = db_fetch_one("SELECT id FROM users WHERE username = ?", [$username], null);
        if (!$row) {
            break;
        }
        $i++;
        $username = $basis . $i;
    }
    return $username;
}

/**
 * ============================================================
 *  BOOKING SLOT PARKIR (self-service Pelanggan/User)
 * ============================================================
 * Lihat skema_booking.sql untuk struktur tabel slot_parkir & booking.
 */

/** Tipe kendaraan yang valid untuk kendaraan & slot parkir. */
function tipe_kendaraan_tersedia(): array
{
    return ['Mobil', 'Motor'];
}

/**
 * Helper: ambil daftar kendaraan milik seorang user (role Pelanggan/User),
 * berdasarkan kolom `terdaftar_oleh_user_id` di tabel kendaraan.
 */
function ambil_kendaraan_milik_user(int $userId): array
{
    return db_fetch_all(
        "SELECT id, kode_kendaraan, plat_nomor, tipe, status_verifikasi
         FROM kendaraan WHERE terdaftar_oleh_user_id = ? ORDER BY id DESC",
        [$userId],
        []
    );
}

/**
 * Helper: buat kode booking unik 6 karakter (huruf besar + angka, tanpa
 * karakter yang gampang tertukar seperti 0/O dan 1/I), untuk verifikasi
 * kedatangan pelanggan booking oleh petugas.
 */
function generate_kode_booking(): string
{
    $karakter = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa 0, O, 1, I
    do {
        $kode = '';
        for ($i = 0; $i < 6; $i++) {
            $kode .= $karakter[random_int(0, strlen($karakter) - 1)];
        }
        $sudahAda = db_fetch_one("SELECT id FROM booking WHERE kode_booking = ?", [$kode], null);
    } while ($sudahAda);

    return $kode;
}
/**
 * Helper: ambil daftar area/lantai yang berstatus 'Aktif',
 * untuk dipilih pelanggan saat booking. Menggunakan tabel `lantai`
 * yang sama dengan yang dikelola Admin di kelola_area.php.
 */
function ambil_area_aktif(): array
{
    return db_fetch_all(
        "SELECT id, nama_lantai, gedung, keterangan
         FROM lantai WHERE status = 'Aktif' ORDER BY nama_lantai",
        [],
        []
    );
}

/**
 * Helper: ambil semua slot parkir berstatus 'Tersedia' beserta area_id-nya
 * (tabel slot_parkir asli: id, area_id, kode_slot, status).
 * Dipakai untuk dropdown slot yang difilter per area di sisi client (JS).
 */
function ambil_slot_tersedia(): array
{
    return db_fetch_all(
        "SELECT sp.id, sp.area_id, sp.kode_slot
         FROM slot_parkir sp
         JOIN lantai l ON l.id = sp.area_id
         WHERE sp.status = 'Tersedia' AND l.status = 'Aktif'
         ORDER BY sp.kode_slot",
        [],
        []
    );
}

/**
 * Helper: ambil okupansi tiap area SAAT INI, dihitung langsung dari
 * jumlah slot_parkir per area_id (bukan dari kolom kapasitas di tabel
 * lantai, supaya selalu akurat sesuai jumlah slot fisik yang terdaftar).
 */
function ambil_okupansi_area_sekarang(): array
{
    return db_fetch_all(
        "SELECT l.id, l.nama_lantai, l.gedung, l.keterangan,
                COUNT(sp.id) AS total_slot,
                SUM(CASE WHEN sp.status != 'Tersedia' THEN 1 ELSE 0 END) AS slot_terisi
         FROM lantai l
         LEFT JOIN slot_parkir sp ON sp.area_id = l.id
         WHERE l.status = 'Aktif'
         GROUP BY l.id, l.nama_lantai, l.gedung, l.keterangan
         ORDER BY l.nama_lantai",
        [],
        []
    );
}

/**
 * Helper: label & warna badge okupansi berdasarkan persentase terisi.
 * Sama seperti $okupansiBadge di kelola_area.php.
 */
function label_okupansi(float $persen): array
{
    if ($persen >= 85) return ['label' => 'Padat', 'class' => 'bg-error-container text-on-error-container'];
    if ($persen <= 30) return ['label' => 'Lengang', 'class' => 'bg-secondary-fixed text-on-secondary-fixed-variant'];
    return ['label' => 'Normal', 'class' => 'bg-tertiary-fixed text-on-tertiary-fixed'];
}

/**
 * Helper: ambil riwayat booking milik seorang user, terbaru dulu.
 * booking.status memakai huruf kecil: 'aktif' | 'selesai' | 'dibatalkan' | 'kedaluwarsa'.
 */
function ambil_booking_milik_user(int $userId): array
{
    return db_fetch_all(
        "SELECT b.id, b.kode_booking, b.waktu_booking, b.status, b.plat_nomor,
                s.kode_slot, l.nama_lantai
         FROM booking b
         JOIN slot_parkir s ON s.id = b.slot_id
         JOIN lantai l ON l.id = s.area_id
         WHERE b.user_id = ?
         ORDER BY b.waktu_booking DESC",
        [$userId],
        []
    );
}

/**
 * Helper: bebaskan otomatis slot dari booking yang sudah lewat waktu +
 * toleransi tertentu dan pelanggan belum "Catat Masuk" (booking masih
 * berstatus 'aktif'). Slot terkait dikembalikan ke 'Tersedia', booking
 * ditandai 'kedaluwarsa'.
 *
 * Panggil fungsi ini di awal halaman/endpoint mana pun yang membaca atau
 * mengubah data slot_parkir/booking (dashboard_pelanggan.php, kelola_area.php,
 * aksi_booking.php, halaman Catat Masuk), supaya datanya selalu segar —
 * di hosting gratis seperti ini tidak selalu ada akses cron job.
 */
function expire_booking_lewat_waktu(int $menitToleransi = 30): void
{
    global $pdo;
    if (!$pdo) return;
    try {
        $menitToleransi = max(0, $menitToleransi); // pastikan non-negatif, aman untuk SQL literal

        $kedaluwarsa = $pdo->prepare(
            "SELECT id, slot_id FROM booking
             WHERE status = 'aktif'
               AND waktu_booking < DATE_SUB(NOW(), INTERVAL {$menitToleransi} MINUTE)"
        );
        $kedaluwarsa->execute();
        $daftar = $kedaluwarsa->fetchAll();

        foreach ($daftar as $b) {
            $pdo->prepare("UPDATE booking SET status = 'kedaluwarsa' WHERE id = ?")->execute([$b['id']]);
            $pdo->prepare("UPDATE slot_parkir SET status = 'Tersedia' WHERE id = ? AND status = 'Dipesan'")->execute([$b['slot_id']]);
        }
    } catch (PDOException $e) {
        // Diamkan: kegagalan auto-expire tidak boleh menggagalkan halaman yang memanggilnya.
    }
}

/**
 * Helper: cari booking AKTIF milik sebuah plat nomor, dipakai saat Officer
 * melakukan "Catat Masuk" untuk mendeteksi apakah kendaraan ini sudah
 * booking sebelumnya. Mengambil yang waktu_booking-nya paling dekat.
 */
function cari_booking_aktif_by_plat(string $platNomor): ?array
{
    return db_fetch_one(
        "SELECT b.id, b.slot_id, b.waktu_booking, s.kode_slot, l.nama_lantai
         FROM booking b
         JOIN slot_parkir s ON s.id = b.slot_id
         JOIN lantai l ON l.id = s.area_id
         WHERE b.plat_nomor = ? AND b.status = 'aktif'
         ORDER BY ABS(TIMESTAMPDIFF(SECOND, b.waktu_booking, NOW())) ASC
         LIMIT 1",
        [$platNomor],
        null
    );
}

/**
 * Helper: tandai booking sebagai 'selesai' karena kendaraannya sudah
 * benar-benar datang (dipanggil dari alur Catat Masuk Officer).
 * Slot terkait diubah dari 'Dipesan' menjadi 'Terisi' (bukan 'Tersedia'
 * lagi, karena kendaraannya sekarang benar-benar terparkir di situ).
 */
function tandai_booking_selesai_kedatangan(int $bookingId): bool
{
    $booking = db_fetch_one(
        "SELECT id, slot_id FROM booking WHERE id = ? AND status = 'aktif'",
        [$bookingId],
        null
    );
    if (!$booking) {
        return false;
    }
    db_execute("UPDATE booking SET status = 'selesai' WHERE id = ?", [$booking['id']]);
    db_execute("UPDATE slot_parkir SET status = 'Terisi' WHERE id = ? AND status = 'Dipesan'", [$booking['slot_id']]);
    return true;
}

/**
 * Helper LENGKAP: proses kedatangan kendaraan dari sebuah booking sampai
 * tercatat resmi sebagai transaksi parkir aktif (status 'Masuk'), supaya
 * otomatis muncul di "Kendaraan Sedang Parkir" (dashboard_petugas.php)
 * dan bisa diproses "Catat Keluar" + pembayaran seperti kendaraan walk-in
 * biasa — TANPA petugas perlu input ulang manual.
 *
 * ASUMSI kolom tabel `transaksi` (sedang diverifikasi bertahap ke database
 * asli lewat percobaan): id, kendaraan_id, slot_id, petugas_id, waktu_masuk,
 * waktu_keluar, biaya, metode_bayar, status ENUM('Masuk','Keluar').
 * (kolom tarif_per_jam TIDAK ada di transaksi - sudah dikonfirmasi error).
 * Kalau nama kolom lain masih salah, INSERT ini akan gagal dengan pesan
 * "Unknown column ..." — laporkan pesan errornya untuk diperbaiki.
 *
 * Mengembalikan array ['success' => bool, 'message' => string,
 * 'transaksi_id' => int|null].
 */
function proses_kedatangan_booking(int $bookingId, string $kodeBookingInput, int $petugasId): array
{
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'message' => 'Koneksi database tidak tersedia.', 'transaksi_id' => null];
    }

    $booking = db_fetch_one(
        "SELECT id, slot_id, plat_nomor, kode_booking FROM booking WHERE id = ? AND status = 'aktif'",
        [$bookingId],
        null
    );
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking tidak ditemukan atau sudah tidak aktif (mungkin sudah ditandai sebelumnya).', 'transaksi_id' => null];
    }

    $kodeBookingInput = strtoupper(trim($kodeBookingInput));
    if ($kodeBookingInput === '' || !hash_equals((string) $booking['kode_booking'], $kodeBookingInput)) {
        return ['success' => false, 'message' => 'Kode booking tidak cocok. Minta pelanggan menunjukkan kode dari akunnya.', 'transaksi_id' => null];
    }

    // Cari kendaraan_id dari plat_nomor yang tercatat di booking.
    $kendaraan = db_fetch_one(
        "SELECT id, tipe FROM kendaraan WHERE plat_nomor = ? LIMIT 1",
        [$booking['plat_nomor']],
        null
    );
    if (!$kendaraan) {
        return ['success' => false, 'message' => "Data kendaraan dengan plat {$booking['plat_nomor']} tidak ditemukan.", 'transaksi_id' => null];
    }

    $tarifPerJam = ambil_tarif_per_jam($kendaraan['tipe']); // dipakai untuk info di pesan sukses, bukan disimpan ke transaksi

    try {
        $pdo->beginTransaction();

        db_execute("UPDATE booking SET status = 'selesai' WHERE id = ?", [$booking['id']]);
        db_execute("UPDATE slot_parkir SET status = 'Terisi' WHERE id = ? AND status = 'Dipesan'", [$booking['slot_id']]);

        $stmt = $pdo->prepare(
            'INSERT INTO transaksi (kendaraan_id, slot_id, petugas_id, waktu_masuk, status)
             VALUES (:kendaraan_id, :slot_id, :petugas_id, NOW(), :status)'
        );
        $stmt->execute([
            'kendaraan_id' => $kendaraan['id'],
            'slot_id'      => $booking['slot_id'],
            'petugas_id'   => $petugasId,
            'status'       => 'Masuk',
        ]);
        $transaksiId = (int) $pdo->lastInsertId();

        $pdo->commit();

        return ['success' => true, 'message' => 'Kendaraan berhasil ditandai sudah datang dan tercatat sebagai transaksi aktif.', 'transaksi_id' => $transaksiId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Gagal membuat transaksi: ' . $e->getMessage(), 'transaksi_id' => null];
    }
}