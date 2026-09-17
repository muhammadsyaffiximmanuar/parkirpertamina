# parkirpertamina

## Sistem Manajemen Parkir — Gedung Pusat Pertamina

Aplikasi manajemen parkir gedung perkantoran, dibangun dengan **PHP native + MySQL (PDO)** dan tampilan **Tailwind CSS (CDN)**, mengikuti alur kerja parkir dasar (catat masuk → catat keluar → struk) yang dilengkapi **6 role terpisah** dengan dashboard masing-masing, registrasi privat karyawan (domain email + kode internal), registrasi publik pelanggan, booking area parkir self-service, pencarian kendaraan lewat **scan QR struk**, serta laporan pendapatan & okupansi berbasis Chart.js.

**Halaman publik:** `landingpage.php` (profil & ringkasan okupansi) dan `ketersediaan.php` (peta slot live + tabel tarif)

**Login:** `login.html` → `login.php` (satu form untuk semua role, redirect otomatis sesuai role)

**Skema database:** `schema_parkir_pertamina.sql` (identik dengan `config/database.sql`)

---

## 1. Struktur Folder

```
parkirpertamina/
├── config/
│   ├── database.php                 -> koneksi PDO gaya OOP (class Database, baca .env) — jalur alternatif
│   └── database.sql                 -> salinan identik schema_parkir_pertamina.sql
├── includes/                        -> komponen bersama
│   ├── head.php                     (<head> + konfigurasi tailwind.config: warna, spacing, tipografi)
│   ├── sidebar.php                  (sidebar navigasi, menu & warna aksen difilter per role)
│   ├── topbar.php                   (header atas: jam, tanggal, identitas pengguna)
│   ├── footer.php
│   └── helpers.php                  (json_response & require_login versi lama)
│
├── config.php                       -> konfigurasi & helper utama (PDO, db_fetch_all/one, db_execute,
│                                       catat_log, require_login/require_role, dashboard_url_for_role,
│                                       role_theme, hitung_biaya_parkir, helper booking)
├── koneksi.php                      (DEPRECATED — hanya meneruskan ke config.php)
├── index.php                        -> ROUTER: cek sesi lalu redirect ke dashboard sesuai role
├── login.html                       -> halaman login (form dikirim via fetch ke login.php)
├── login.php                        (proses autentikasi, kembalikan JSON + URL redirect)
├── logout.php                       (hapus sesi; balas JSON bila AJAX, redirect bila klik biasa)
├── register.php                     -> registrasi PRIVAT karyawan (kode rahasia + domain email,
│                                       akun otomatis Non-Aktif sampai disetujui Admin)
├── register_pelanggan.php           -> registrasi PUBLIK pelanggan umum (langsung Aktif)
│
├── landingpage.php                  -> landing page publik (statistik, tarif, info layanan)
├── ketersediaan.php                 -> halaman publik: peta slot per area (live) + tabel tarif
│
├── dashboard_owner.php              -> dashboard Owner & Super Admin (ringkasan bisnis & finansial)
├── dashboard_admin.php              -> dashboard Admin (verifikasi kendaraan, tarif, area)
├── dashboard_petugas.php            -> dashboard Officer & Security (operasional shift, slot live)
├── dashboard_user.php               -> dashboard User/karyawan (kendaraan & riwayat pribadi)
├── dashboard_pelanggan.php          -> dashboard Pelanggan (booking area parkir)
├── dashboard_stats.php              (endpoint JSON: kartu statistik + okupansi + aktivitas terbaru)
│
├── catat_masuk.php                  -> Petugas: catat kendaraan masuk + pendaftaran cepat kendaraan
├── catat_keluar.php                 -> Petugas: catat keluar + pembayaran, cari via ketik plat
│                                       ATAU scan QR struk (html5-qrcode)
├── struk.php                        -> cetak struk (QR code tiket; tombol bayar bila masih 'Masuk')
├── transaksi.php                    (endpoint: GET daftar, POST masuk, PUT keluar + hitung biaya)
├── riwayat.php                      -> riwayat transaksi semua role internal (filter + pagination)
├── rekap_transaksi.php              -> rekap transaksi khusus Owner & Super Admin
├── laporan.php                      -> laporan & analitik (periode harian/mingguan/bulanan/kustom)
├── aktivitas.php                    -> log aktivitas sistem (filter kategori/role + pagination)
│
├── kelola_user.php                  (CRUD akun pengguna)      + aksi_kelola_user.php
├── kelola_area.php                  (CRUD lantai & kapasitas) + aksi_kelola_area.php
├── kelola_kendaraan.php             (CRUD kendaraan)          + aksi_kelola_kendaraan.php
├── kelola_tarif.php                 (CRUD tarif per jam)      + aksi_kelola_tarif.php
├── aksi_verifikasi_kendaraan.php    (setujui/tolak kendaraan "Menunggu Verifikasi")
├── aksi_booking.php                 (buat & batalkan booking area oleh Pelanggan/User)
├── aksi_kelola_tarif_debug.php      ⚠️ versi debug, bocorkan pesan error asli — lihat §7
├── kendaraan.php                    (endpoint JSON kendaraan: GET/POST/DELETE)
├── area.php                         (DEPRECATED — redirect ke aktivitas.php)
│
├── schema_parkir_pertamina.sql      -> skema + data contoh (WAJIB diimport)
├── dashbordutama-.html              (sisa mockup HTML statis sebelum dikonversi ke PHP)
├── parkir-kantor-pertamina.png / pertamina-hq-bg.png   (aset gambar landing page)
└── README.md
```

Seluruh halaman berada langsung di folder root project (tidak dipisah per subfolder role). Pembatasan akses dijaga lewat `require_login_page()` + `require_role([...])` dari `config.php` di baris awal setiap file. Jika role tidak sesuai, pengguna **tidak** mendapat halaman error, melainkan dilempar ke dashboard miliknya sendiri.

---

## 2. Akun & Pendaftaran

| Role | Cara mendapatkan akun | Keterangan |
| --- | --- | --- |
| **Pelanggan** (umum) | Daftar mandiri via `register_pelanggan.php` (ditautkan dari `login.html`) | Bebas domain email, tanpa kode rahasia, akun **langsung Aktif** dan bisa langsung booking |
| **User** (karyawan pemilik kendaraan) | Dibuatkan Admin lewat Kelola User | Self-service: lihat kendaraan pribadi, status parkir, riwayat & pengeluaran |
| **Officer / Security** (petugas) | Registrasi privat via `register.php` (tidak ditautkan dari halaman mana pun) | Wajib kode `PTM-2026-INTERNAL` **dan** email berdomain `@pertamina.com`; akun otomatis **Non-Aktif** sampai diaktifkan Admin |
| **Admin** | Dibuatkan/dinaikkan oleh Admin atau Super Admin lewat Kelola User | Role tidak bisa dipilih sendiri saat mendaftar — pendaftar baru selalu dapat role terendah (Officer) |
| **Owner / Super Admin** | Sama seperti Admin | Akses penuh ke ringkasan bisnis & keuangan |

Login memakai satu form yang sama untuk semua role (`login.html` → `login.php`). Sistem membaca kolom `role_id` (JOIN ke tabel `roles`) lalu mengarahkan otomatis lewat `dashboard_url_for_role()`; pengguna tidak memilih role secara manual. Akun berstatus `Non-Aktif` ditolak saat login dengan pesan khusus.

Keamanan sesi: `session_regenerate_id(true)` dipanggil setelah login berhasil (anti session fixation), password disimpan sebagai hash bcrypt (`password_hash`/`password_verify`), dan percobaan login gagal ikut tercatat ke log aktivitas.

Akun contoh di `schema_parkir_pertamina.sql` semuanya memakai password **`password123`** — wajib diganti sebelum dipakai nyata.

---

## 3. Hak Akses Fitur

| Fitur | Super Admin | Owner | Admin | Officer / Security | User | Pelanggan |
| --- | --- | --- | --- | --- | --- | --- |
| Login / Logout | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Lihat landing page & ketersediaan slot (publik) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Registrasi mandiri publik (`register_pelanggan.php`) | | | | | | ✔ |
| Registrasi privat berkode (`register.php`, perlu approval) | | | | ✔ | | |
| Dashboard ringkasan bisnis & pendapatan | ✔ | ✔ | | | | |
| Dashboard manajemen data & verifikasi | | | ✔ | | | |
| Dashboard operasional shift | | | | ✔ | | |
| CRUD Pengguna | ✔ | | ✔ | | | |
| CRUD Area / Lantai & kapasitas | ✔ | ✔ | ✔ | | | |
| CRUD Kendaraan | ✔ | | ✔ | | | |
| CRUD Tarif per jam | ✔ | | ✔ | | | |
| Verifikasi / tolak kendaraan pendaftaran online | | | ✔ | | | |
| Catat Kendaraan Masuk (+ daftar cepat kendaraan baru) | | | | ✔ | | |
| Catat Kendaraan Keluar — cari via ketik plat **atau scan QR struk** | | | | ✔ | | |
| Hitung biaya otomatis & pilih metode bayar | | | | ✔ | | |
| Cetak struk (dengan QR code tiket) | ✔ | ✔ | ✔ | ✔ | ✔ | |
| Riwayat transaksi (filter + pagination) | ✔ | ✔ | ✔ | ✔ | | |
| Rekap transaksi | ✔ | ✔ | | | | |
| Laporan & analitik pendapatan | ✔ | ✔ | ✔ | | | |
| Log aktivitas seluruh pengguna | ✔ | | ✔ | | | |
| Booking area parkir (self-service) | | | | | ✔ | ✔ |
| Lihat kendaraan & riwayat parkir sendiri | | | | | ✔ | ✔ |

---

## 4. Skema Database

Database: `parkir_pertamina` (MySQL/MariaDB 8+, `utf8mb4_unicode_ci`).

| Tabel | Fungsi |
| --- | --- |
| `roles` | Daftar role beserta deskripsinya; direferensikan `users.role_id` |
| `users` | Akun pengguna: `nama_lengkap`, `username` (unik), `email`, `password_hash` (bcrypt), `role_id`, `status` (`Aktif`/`Non-Aktif`) |
| `lantai` | Lantai/area besar beserta `gedung` dan `kapasitas` — dipakai Kelola Area & sebagai unit yang dibooking |
| `area` | Section di dalam sebuah lantai (`kode_prefix` A/B/VIP dipakai sebagai awalan kode slot) |
| `slot_parkir` | Slot individual (`kode_slot` unik, status `Tersedia`/`Terisi`/`Dipesan`) untuk visualisasi grid |
| `kendaraan` | Data kendaraan: `kode_kendaraan` (`VHC-{tahun}-{urut}`, digenerate otomatis), `plat_nomor` unik, `tipe`, `sumber_registrasi` (`Admin`/`Sistem Online`), `status_verifikasi`, `izin_berlaku_sampai`, `terdaftar_oleh_user_id` |
| `tarif` | Tarif per tipe kendaraan: `tarif_per_jam`, `status`, `updated_by_user_id` |
| `transaksi` | Transaksi parkir: `kode_parkir` unik, waktu masuk/keluar, `biaya`, `status` (`Masuk`/`Keluar`), `slot_id`, `petugas_id`, `metode_bayar` |
| `log_aktivitas` | Audit trail seluruh pengguna (kategori: Login/Logout, Manajemen Kendaraan, Perubahan Tarif, Laporan, Lainnya) beserta `ip_address` |
| `okupansi_per_jam` | Snapshot persentase okupansi per jam untuk grafik tren di halaman Laporan |

Relasi antar tabel dijaga dengan **FOREIGN KEY** (`users` ↔ `roles`, `area` ↔ `lantai`, `slot_parkir` ↔ `area`, `transaksi` ↔ `kendaraan`/`slot_parkir`/`users`, `log_aktivitas` ↔ `users`), ditambah index pencarian pada plat nomor, status & waktu transaksi, kategori & tanggal log, dan status slot.

**VIEW** yang dipakai halaman-halaman utama:

| View | Dipakai untuk |
| --- | --- |
| `view_dashboard_stats` | Kartu statistik (total area, kendaraan terdaftar, terparkir, slot tersedia, pendapatan hari ini, pengguna aktif) |
| `view_okupansi_lantai` | Okupansi & persentase terisi per lantai |
| `view_transaksi_detail` | Daftar transaksi lengkap + durasi menit + nama petugas |
| `view_log_aktivitas_detail` | Log aktivitas + nama & email pengguna |
| `view_area_ringkasan` | Tabel "Laporan Per Area" |
| `view_pendapatan_harian` | Grafik pendapatan harian |
| `view_aktivitas_kendaraan` | Statistik aktivitas kendaraan |

---

## 5. Alur Kerja Aplikasi

1. Pengunjung publik membuka `landingpage.php` (profil layanan + ringkasan statistik) atau `ketersediaan.php` untuk melihat **peta slot per area secara live**, tabel tarif lengkap, dan penjelasan bahwa pembayaran dilakukan di gerbang keluar saat check-out — bukan bayar online di muka.
2. Pelanggan umum mendaftar mandiri lewat `register_pelanggan.php` dan langsung bisa login. Karyawan internal didaftarkan lewat `register.php` dengan kode rahasia + domain email perusahaan, lalu menunggu Admin mengaktifkan akunnya.
3. Pelanggan/User login ke dashboard-nya, memilih kendaraan, lantai, serta waktu mulai & selesai, lalu membuat **booking** (`aksi_booking.php`). Sistem menolak booking bila jumlah booking aktif yang **tumpang tindih waktunya** sudah menyamai kapasitas lantai. Booking miliknya sendiri bisa dibatalkan kapan saja.
4. Saat kendaraan masuk, Petugas mencatatnya di `catat_masuk.php` — kendaraan yang belum terdaftar bisa didaftarkan cepat di form yang sama, slot dipilih otomatis, lalu struk masuk tercetak (`struk.php`) lengkap dengan **QR code tiket**.
5. Saat kendaraan keluar, Petugas membuka `catat_keluar.php` dan mencari kendaraan dengan **mengetik plat/kode parkir atau memindai QR code struk lewat kamera**. Biaya dihitung dari durasi: dibulatkan **ke atas per jam, minimal 1 jam** (61 menit = 2 jam), dikali `tarif_per_jam` tipe kendaraan yang berstatus Aktif.
6. Petugas memilih metode bayar — **Tunai, QRIS, Kartu Debit, Kartu Kredit, atau E-Wallet** — transaksi ditutup (`transaksi.php` PUT), slot dikembalikan ke `Tersedia`, dan struk final bertanda LUNAS bisa dicetak.
7. Admin mengelola master data (pengguna, kendaraan, tarif, area/lantai), **memverifikasi atau menolak kendaraan** yang didaftarkan lewat sistem online, dan memantau log aktivitas seluruh pengguna.
8. Owner & Super Admin memantau rekap transaksi (`rekap_transaksi.php`) serta laporan pendapatan, okupansi, dan performa per area (`laporan.php`) dengan pilihan periode harian, mingguan, bulanan, atau kustom — setiap periode menjalankan query berbeda lewat query string, bukan sekadar mengganti tampilan tombol.

---

## 6. Cara Menjalankan

**XAMPP / Laragon**

1. Salin folder `parkirpertamina` ke `C:\xampp\htdocs\` atau `C:\laragon\www\`.
2. Buka phpMyAdmin → tab **Import** → pilih `schema_parkir_pertamina.sql` → jalankan. File ini sudah berisi `CREATE DATABASE parkir_pertamina`, jadi tidak perlu membuat database manual.
3. Sesuaikan kredensial di `config.php` (lihat peringatan di §7):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'parkir_pertamina');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
4. Jalankan Apache & MySQL, lalu buka `http://localhost/parkirpertamina/landingpage.php`.

**Tanpa XAMPP (PHP built-in server)**

```bash
cd parkirpertamina
php -S localhost:8000
```

Lalu buka `http://localhost:8000/landingpage.php`. Login di `http://localhost:8000/login.html`.

Jika database belum diimport atau koneksi gagal, sebagian besar halaman **tetap bisa dibuka** dan menampilkan data contoh (fallback lewat `db_fetch_all`/`db_fetch_one`) sehingga tampilan tidak rusak.

---

## 7. Hal yang Perlu Diperhatikan

- **Kredensial produksi tertulis langsung di `config.php`** (host, nama database, user, dan password InfinityFree dalam bentuk plaintext). Kalau repo ini dipublikasikan, kredensial ikut terbaca siapa pun. Pindahkan ke file `.env` di luar version control — `config/database.php` sudah menyediakan mekanisme baca `.env`, tinggal dipakai — lalu **ganti password database** yang sudah terlanjur tertulis.
- **`config.php` masih mode debug** (`error_reporting(E_ALL)` + `display_errors` aktif). Detail error PDO termasuk host dan nama database bisa terlihat publik saat koneksi gagal. Matikan sebelum dipakai nyata.
- **Ada dua jalur koneksi database yang berbeda.** `config.php` memakai konstanta `DB_*` yang di-hardcode, sedangkan `config/database.php` memakai class `Database` + `.env` dengan default nama database yang sama tapi kredensial berbeda. Nyaris seluruh aplikasi memakai jalur pertama; pastikan tidak ada file baru yang tidak sengaja memakai jalur kedua.
- **Tabel `booking` belum ada di skema.** `aksi_booking.php` dan `dashboard_pelanggan.php` melakukan query ke tabel `booking`, tetapi `schema_parkir_pertamina.sql` tidak memuatnya, dan file `skema_booking.sql` yang dirujuk komentar di `config.php` tidak ada di project. Fitur booking akan gagal sampai tabel ini dibuat.
- **Role `User` dan `Pelanggan` belum ada di tabel `roles`.** Seed pada skema hanya mengisi Super Admin, Owner, Admin, Officer, dan Security. Akibatnya `register_pelanggan.php` tidak menemukan `role_id` yang cocok dan `dashboard_user.php`/`dashboard_pelanggan.php` tidak akan pernah terpakai. Tambahkan dua baris role tersebut dengan nama yang **persis** sama seperti konstanta `ROLE_USER` dan `ROLE_PELANGGAN`.
- **`aksi_kelola_tarif_debug.php` sebaiknya dihapus dari server.** File ini sengaja mengirim pesan error asli ke response untuk keperluan debug, sehingga bisa membocorkan struktur database ke pengguna.
- **Sidebar belum punya menu untuk role User & Pelanggan.** Daftar menu di `includes/sidebar.php` hanya memetakan role internal, jadi kedua role self-service melihat sidebar kosong meski halamannya berfungsi.
- **Hak akses halaman dan endpoint-nya belum konsisten.** Endpoint `aksi_kelola_kendaraan.php`, `aksi_kelola_tarif.php`, dan `aksi_kelola_user.php` mengizinkan Owner, sementara halaman `kelola_kendaraan.php`, `kelola_tarif.php`, dan `kelola_user.php` hanya mengizinkan Super Admin & Admin. Samakan daftar role di keduanya.
- **Proteksi CSRF belum merata.** Endpoint `aksi_*` sudah memvalidasi `csrf_token`, tetapi `transaksi.php`, `kendaraan.php`, `aksi_booking.php`, dan `login.php` belum.
- **Fitur scan QR butuh HTTPS** dan izin kamera browser — tidak berfungsi di `http://` biasa (kecuali `localhost`), dan sering gagal bila dibuka lewat in-app browser WhatsApp/Instagram alih-alih browser asli.
- **Ganti password akun contoh.** Semua akun seed memakai `password123` dengan hash bcrypt yang sama.
- Masih ada sisa file dari tahap konversi: `dashbordutama-.html` (mockup statis), `area.php` (redirect ke `aktivitas.php`), `koneksi.php` (shim), dan `includes/helpers.php` (duplikat fungsi yang sudah ada di `config.php`). Aman dihapus setelah dipastikan tidak ada tautan yang menunjuk ke sana.
