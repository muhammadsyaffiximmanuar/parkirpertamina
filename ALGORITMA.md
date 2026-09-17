# ALGORITMA — Sistem Manajemen Parkir Gedung Pertamina

Pseudocode setiap proses inti aplikasi, ditulis mengikuti alur kode yang benar-benar berjalan
(bukan rancangan ideal). Nama fungsi dan tabel sengaja dipertahankan sama persis dengan
kode agar mudah ditelusuri.

**Daftar isi**

1. [Autentikasi & Routing Role](#1-autentikasi--routing-role)
2. [Proteksi Akses Halaman](#2-proteksi-akses-halaman)
3. [Registrasi Karyawan (Privat)](#3-registrasi-karyawan-privat)
4. [Registrasi Pelanggan (Publik)](#4-registrasi-pelanggan-publik)
5. [Catat Kendaraan Masuk](#5-catat-kendaraan-masuk)
6. [Perhitungan Biaya Parkir](#6-perhitungan-biaya-parkir)
7. [Catat Kendaraan Keluar](#7-catat-kendaraan-keluar)
8. [Pemindaian QR Struk](#8-pemindaian-qr-struk)
9. [Cetak Struk](#9-cetak-struk)
10. [Booking Area Parkir](#10-booking-area-parkir)
11. [Verifikasi Kendaraan oleh Admin](#11-verifikasi-kendaraan-oleh-admin)
12. [Generator Kode Otomatis](#12-generator-kode-otomatis)
13. [Filter & Pagination Log Aktivitas](#13-filter--pagination-log-aktivitas)
14. [Laporan per Periode & Pembanding](#14-laporan-per-periode--pembanding)
15. [Audit Trail](#15-audit-trail)
16. [Fallback Koneksi Database](#16-fallback-koneksi-database)
17. [Catatan Penyimpangan Implementasi](#17-catatan-penyimpangan-implementasi)

---

## 1. Autentikasi & Routing Role

File: `login.html` → `login.php` → `index.php` (`dashboard_url_for_role`)

```
PROSES login(username, password)
  JIKA metode request BUKAN POST MAKA
      balas JSON error, kode 405
      SELESAI

  JIKA username kosong ATAU password kosong MAKA
      balas JSON error, kode 422
      SELESAI

  user <- SELECT id, nama_lengkap, username, password_hash, status, r.nama_role
          FROM users JOIN roles ON roles.id = users.role_id
          WHERE username = <username> LIMIT 1

  JIKA user tidak ditemukan ATAU password_verify(password, user.password_hash) = SALAH MAKA
      JIKA user ditemukan MAKA
          catat_log(user.id, "Login Gagal - Kata Sandi Salah", "Login/Logout")
      balas JSON error "Username atau kata sandi salah", kode 401
      SELESAI
      // catatan: pesan error sengaja sama untuk user tak ada & password salah
      //          supaya tidak membocorkan username mana yang terdaftar

  JIKA user.status BUKAN 'Aktif' MAKA
      balas JSON error "Akun Anda tidak aktif", kode 403
      SELESAI

  session_regenerate_id(true)          // cegah session fixation
  SESSION.user_id      <- user.id
  SESSION.nama_lengkap <- user.nama_lengkap
  SESSION.role         <- user.nama_role

  catat_log(user.id, "Login Berhasil", "Login/Logout")
  balas JSON sukses berisi redirect = dashboard_url_for_role(user.nama_role)
SELESAI
```

```
FUNGSI dashboard_url_for_role(role)
  PILIH role
      'Owner', 'Super Admin'  -> KEMBALIKAN 'dashboard_owner.php'
      'Admin'                 -> KEMBALIKAN 'dashboard_admin.php'
      'Officer', 'Security'   -> KEMBALIKAN 'dashboard_petugas.php'
      'User'                  -> KEMBALIKAN 'dashboard_user.php'
      'Pelanggan'             -> KEMBALIKAN 'dashboard_pelanggan.php'
      LAINNYA                 -> KEMBALIKAN 'dashboard_petugas.php'   // fallback paling terbatas
SELESAI
```

Sisi klien (`login.html`) mengirim form lewat `fetch('login.php')`, lalu:

```
respons <- fetch POST ke login.php
JIKA respons.success MAKA
    arahkan browser ke respons.redirect
LAINNYA
    tampilkan respons.message di bawah form
```

`index.php` sendiri tidak lagi berisi dashboard — hanya router:

```
PROSES index
  require_login_page()                       // belum login -> redirect login.html
  redirect ke dashboard_url_for_role(SESSION.role)
SELESAI
```

---

## 2. Proteksi Akses Halaman

File: `config.php` (`require_login_page`, `require_login`, `require_role`)

```
FUNGSI require_login_page(redirectTo = 'login.html')
  mulai sesi jika belum berjalan
  JIKA SESSION.user_id kosong MAKA
      redirect ke redirectTo
      hentikan eksekusi
  KEMBALIKAN data sesi (user_id, nama_lengkap, role)
SELESAI

FUNGSI require_login()                       // versi untuk endpoint JSON
  mulai sesi jika belum berjalan
  JIKA SESSION.user_id kosong MAKA
      balas JSON error kode 401
      hentikan eksekusi
  KEMBALIKAN data sesi
SELESAI

FUNGSI require_role(daftarRoleDiizinkan)
  JIKA SESSION.user_id kosong MAKA
      redirect ke 'login.html'; hentikan
  JIKA SESSION.role TIDAK ADA di daftarRoleDiizinkan MAKA
      redirect ke dashboard_url_for_role(SESSION.role)   // bukan halaman error
      hentikan
SELESAI
```

Prinsipnya: salah membuka halaman **tidak** menghasilkan dead-end, pengguna
dilempar kembali ke dashboard miliknya sendiri.

Sidebar memakai aturan yang sama untuk menyembunyikan menu:

```
menu <- FILTER fullMenu DENGAN item yang daftar 'roles'-nya memuat SESSION.role
tema <- role_theme(SESSION.role)             // warna aksen + label + ikon per role
```

---

## 3. Registrasi Karyawan (Privat)

File: `register.php` — halaman ini sengaja tidak ditautkan dari mana pun.

```
PROSES register_karyawan(form)
  JIKA sudah login MAKA
      redirect ke dashboard sesuai role; SELESAI

  JIKA metode request BUKAN POST MAKA tampilkan form; SELESAI

  errors <- daftar kosong

  // 1. Field wajib
  JIKA nama_lengkap / username / email / password ada yang kosong MAKA
      tambah error "Semua field wajib diisi"

  // 2. Lapis privasi utama: kode rahasia internal
  JIKA kode_registrasi kosong ATAU
       hash_equals(KODE_REGISTRASI_PERUSAHAAN, kode_registrasi) = SALAH MAKA
      tambah error "Kode registrasi perusahaan salah"
      // hash_equals dipakai agar perbandingan tahan timing attack

  // 3. Lapis kedua: domain email perusahaan
  JIKA email TIDAK berakhiran EMAIL_DOMAIN_PERUSAHAAN MAKA
      tambah error "Email harus memakai domain perusahaan"

  // 4. Format username
  JIKA username TIDAK cocok pola ^[a-zA-Z0-9._]{4,50}$ MAKA
      tambah error "Username hanya huruf, angka, titik, underscore, min 4 karakter"

  // 5. Password
  JIKA panjang(password) < 8 MAKA tambah error "Minimal 8 karakter"
  JIKA password <> konfirmasi_password MAKA tambah error "Konfirmasi tidak cocok"

  // 6-7. Cek database & duplikasi
  JIKA errors kosong DAN koneksi database tidak tersedia MAKA
      tambah error "Koneksi database tidak tersedia"
  JIKA errors kosong MAKA
      JIKA ADA users DENGAN username = <username> ATAU email = <email> MAKA
          tambah error "Username atau email sudah terdaftar"

  // 8. Simpan
  JIKA errors kosong MAKA
      roleId <- SELECT id FROM roles WHERE nama_role = 'Officer'
      JIKA roleId tidak ditemukan MAKA
          tambah error "Role default tidak ditemukan"
      LAINNYA
          INSERT INTO users (nama_lengkap, username, email, password_hash, role_id, status)
          VALUES (..., password_hash(password, BCRYPT), roleId, 'Non-Aktif')
          // lapis ketiga: akun TIDAK bisa login sampai Admin mengaktifkan

          catat_log(idUserBaru, "Registrasi Akun Baru - Menunggu Aktivasi Admin", "Lainnya")
          tampilkan pesan sukses + instruksi menunggu approval
SELESAI
```

Tiga lapis pengaman berlapis: **kode rahasia → domain email → status Non-Aktif**.
Role pendaftar selalu dipaksa ke `Officer`; tidak ada input role di form, sehingga
tidak seorang pun bisa mendaftar langsung sebagai Admin atau Owner.

---

## 4. Registrasi Pelanggan (Publik)

File: `register_pelanggan.php` — ditautkan dari `login.html`.

```
PROSES register_pelanggan(form)
  JIKA sudah login MAKA redirect ke dashboard; SELESAI

  validasi field wajib, format username, panjang password, konfirmasi password
  // PERBEDAAN dari register.php:
  //   - TIDAK ada pemeriksaan kode registrasi
  //   - TIDAK ada pembatasan domain email
  JIKA username ATAU email sudah dipakai MAKA tambah error

  JIKA errors kosong MAKA
      roleId <- SELECT id FROM roles WHERE nama_role = 'Pelanggan'
      INSERT INTO users (..., role_id = roleId, status = 'Aktif')
      // langsung aktif, tanpa approval Admin
      tampilkan pesan sukses + tautan ke halaman login
SELESAI
```

---

## 5. Catat Kendaraan Masuk

File: `catat_masuk.php` (UI) → `kendaraan.php` POST (opsional) → `transaksi.php` POST

Sisi klien, bila petugas mencentang "kendaraan belum terdaftar":

```
JIKA mode = daftar_cepat MAKA
    hasilDaftar <- fetch POST 'kendaraan.php' {plat_nomor, tipe, warna, nama_pemilik}
    JIKA hasilDaftar gagal MAKA tampilkan pesan; SELESAI

hasil <- fetch POST 'transaksi.php' {plat_nomor}
JIKA hasil.success MAKA
    buka tab baru 'struk.php?id=' + hasil.id
```

Sisi server (`transaksi.php`, metode POST):

```
PROSES catat_masuk(plat_nomor, slot_id opsional)
  sesi <- require_login()
  JIKA plat_nomor kosong MAKA balas error 422; SELESAI

  kendaraan <- SELECT id, tipe FROM kendaraan WHERE plat_nomor = <plat_nomor>
  JIKA kendaraan tidak ditemukan MAKA
      balas error "Kendaraan belum terdaftar", kode 404; SELESAI

  // Cegah satu kendaraan tercatat masuk dua kali
  JIKA ADA transaksi DENGAN kendaraan_id = kendaraan.id DAN status = 'Masuk' MAKA
      balas error "Kendaraan sudah tercatat sedang parkir", kode 409; SELESAI

  // Pemilihan slot: manual jika dikirim, selain itu ambil slot kosong pertama
  JIKA slot_id kosong MAKA
      slot_id <- SELECT id FROM slot_parkir WHERE status = 'Tersedia' LIMIT 1
  JIKA slot_id masih kosong MAKA
      balas error "Tidak ada slot tersedia", kode 409; SELESAI

  jumlah     <- SELECT COUNT(*) FROM transaksi
  kodeParkir <- "PRK-" + tahun_sekarang + "-" + format 5 digit (jumlah + 1)

  MULAI TRANSAKSI DATABASE
      INSERT INTO transaksi (kode_parkir, kendaraan_id, slot_id, waktu_masuk,
                             status, petugas_id)
      VALUES (kodeParkir, kendaraan.id, slot_id, NOW(), 'Masuk', sesi.user_id)
      transaksiId <- id yang baru dibuat

      UPDATE slot_parkir SET status = 'Terisi' WHERE id = slot_id
  JIKA ada kegagalan MAKA
      BATALKAN TRANSAKSI DATABASE
      balas error "Gagal mencatat kendaraan masuk", kode 500; SELESAI
  SIMPAN TRANSAKSI DATABASE

  catat_log(sesi.user_id, "Catat Kendaraan Masuk <plat> (<kodeParkir>)",
            "Manajemen Kendaraan")
  balas JSON sukses {id, kode_parkir}, kode 201
SELESAI
```

Penulisan baris transaksi dan perubahan status slot dibungkus satu transaksi
database, sehingga tidak mungkin terjadi slot tertandai `Terisi` tanpa ada
transaksi yang mendasarinya.

---

## 6. Perhitungan Biaya Parkir

File: `config.php`

```
FUNGSI hitung_biaya_parkir(durasiMenit, tarifPerJam)
  jam <- PEMBULATAN KE ATAS (durasiMenit / 60)
  JIKA jam < 1 MAKA jam <- 1                 // minimal tetap dihitung 1 jam
  KEMBALIKAN jam * tarifPerJam
SELESAI
```

Contoh: 5 menit → 1 jam; 60 menit → 1 jam; 61 menit → 2 jam; 185 menit → 4 jam.

```
FUNGSI ambil_tarif_per_jam(tipeKendaraan)
  baris <- SELECT tarif_per_jam FROM tarif
           WHERE tipe_kendaraan = <tipeKendaraan> DAN status = 'Aktif' LIMIT 1
  JIKA baris ditemukan MAKA KEMBALIKAN baris.tarif_per_jam
  LAINNYA KEMBALIKAN 0                        // tarif non-aktif -> gratis
SELESAI
```

Tarif berstatus `Non-Aktif` (mis. kendaraan VIP) otomatis menghasilkan biaya 0.

---

## 7. Catat Kendaraan Keluar

File: `catat_keluar.php` (UI) → `transaksi.php` PUT

```
PROSES catat_keluar(id ATAU kode_parkir, metode_bayar)
  sesi <- require_login()

  JIKA id <= 0 DAN kode_parkir kosong MAKA balas error 422; SELESAI

  JIKA metode_bayar TIDAK ADA di metode_pembayaran_tersedia() MAKA
      balas error "Metode pembayaran tidak valid", kode 422; SELESAI
      // metode valid: Tunai, QRIS, Debit, Kredit, E-Wallet

  // Pencarian transaksi bertingkat
  JIKA id > 0 MAKA
      trx <- SELECT transaksi JOIN kendaraan WHERE transaksi.id = id
  LAINNYA
      // 1) coba cocokkan sebagai kode parkir (hasil scan QR / ketik manual)
      trx <- SELECT ... WHERE kode_parkir = <masukan>
      // 2) kalau gagal, perlakukan masukan sebagai plat nomor
      JIKA trx tidak ditemukan MAKA
          trx <- SELECT ... WHERE plat_nomor = <masukan> DAN status = 'Masuk'
                 URUT waktu_masuk MENURUN LIMIT 1

  JIKA trx tidak ditemukan MAKA balas error 404; SELESAI
  JIKA trx.status = 'Keluar' MAKA
      balas error "Transaksi sudah tercatat keluar", kode 409; SELESAI
      // idempoten: mencegah tagihan ganda bila tombol ditekan dua kali

  tarifPerJam <- ambil_tarif_per_jam(trx.tipe_kendaraan)
  durasiMenit <- SELISIH MENIT (sekarang, trx.waktu_masuk), dibulatkan ke bawah
  biaya       <- hitung_biaya_parkir(durasiMenit, tarifPerJam)

  MULAI TRANSAKSI DATABASE
      UPDATE transaksi
      SET waktu_keluar = NOW(), biaya = biaya, status = 'Keluar',
          metode_bayar = metode_bayar
      WHERE id = trx.id

      JIKA trx.slot_id terisi MAKA
          UPDATE slot_parkir SET status = 'Tersedia' WHERE id = trx.slot_id
  JIKA ada kegagalan MAKA
      BATALKAN TRANSAKSI DATABASE; balas error 500; SELESAI
  SIMPAN TRANSAKSI DATABASE

  catat_log(sesi.user_id,
            "Catat Kendaraan Keluar <plat> (<kode>) - Bayar <metode> <biaya>",
            "Manajemen Kendaraan")

  balas JSON {id, kode_parkir, biaya, metode_bayar, struk_url}
SELESAI
```

Seluruh blok dibungkus penangkap galat menyeluruh, supaya kegagalan tak terduga
tetap dibalas sebagai JSON yang bisa dibaca frontend, bukan halaman kosong.

---

## 8. Pemindaian QR Struk

File: `catat_keluar.php` (pustaka `html5-qrcode`)

```
PROSES mulai_scan()
  JIKA scanner sudah aktif MAKA SELESAI
  pemindai <- Html5Qrcode('qrReader')
  pemindai.start(
      kamera = { facingMode: 'environment' },      // utamakan kamera belakang
      konfigurasi = { fps, ukuran kotak pindai },
      SAAT_BERHASIL(teksHasil):
          isi field pencarian dengan teksHasil     // biasanya kode_parkir
          hentikan_scan()
          jalankan pencarian transaksi otomatis
  )
  scannerAktif <- BENAR
SELESAI

PROSES hentikan_scan()
  JIKA pemindai ada DAN scannerAktif MAKA
      pemindai.stop()  LALU  pemindai.clear()
      scannerAktif <- SALAH
SELESAI
```

Prasyarat: halaman harus diakses lewat HTTPS (atau `localhost`) dan izin kamera
browser diberikan; selain itu `start()` gagal dan petugas harus mengetik plat manual.

---

## 9. Cetak Struk

File: `struk.php`

```
PROSES tampilkan_struk(id ATAU kode)
  require_login_page()
  JIKA id kosong DAN kode kosong MAKA hentikan dengan pesan galat

  trx <- SELECT transaksi
         JOIN kendaraan
         LEFT JOIN slot_parkir, area, lantai, users AS petugas
         WHERE id = <id> ATAU kode_parkir = <kode>

  render kop struk, data kendaraan, waktu masuk/keluar, durasi, rincian biaya
  render QR code berisi kode_parkir ke elemen #qrCodeStruk

  JIKA trx.status = 'Masuk' MAKA
      // struk sementara: belum bayar
      tampilkan pilihan metode bayar + tombol "Proses Pembayaran & Catat Keluar"
      SAAT tombol ditekan:
          hasil <- fetch PUT 'transaksi.php' {id, metode_bayar}
          JIKA sukses MAKA muat ulang halaman -> struk tampil sebagai LUNAS
  LAINNYA
      // status 'Keluar'
      tampilkan struk final bertanda LUNAS + tombol cetak
SELESAI
```

---

## 10. Booking Area Parkir

File: `dashboard_pelanggan.php` → `aksi_booking.php`

Pengecekan kapasitas memakai **irisan rentang waktu**, bukan jumlah booking harian:

```
FUNGSI hitung_booking_bentrok(lantaiId, waktuMulai, waktuSelesai)
  KEMBALIKAN SELECT COUNT(*) FROM booking
             WHERE lantai_id = lantaiId
               DAN status = 'Aktif'
               DAN waktu_mulai   < waktuSelesai
               DAN waktu_selesai > waktuMulai
SELESAI
```

Dua rentang dianggap bentrok bila masing-masing mulai sebelum yang lain selesai —
booking yang berakhir tepat saat booking lain dimulai tidak dihitung bentrok.

```
PROSES buat_booking(kendaraan_id, lantai_id, waktu_mulai, waktu_selesai)
  sesi <- require_login()
  JIKA sesi.role BUKAN 'Pelanggan' DAN BUKAN 'User' MAKA
      balas error 403; SELESAI

  JIKA ada field kosong MAKA balas error 422; SELESAI

  JIKA waktu tidak bisa diurai ATAU waktu_selesai <= waktu_mulai MAKA
      balas error "Rentang waktu tidak valid", kode 422; SELESAI
  JIKA waktu_mulai < (sekarang - 60 detik) MAKA
      balas error "Waktu mulai tidak boleh di masa lalu", kode 422; SELESAI
      // toleransi 60 detik untuk selisih jam klien vs server

  // Kepemilikan: pengguna hanya boleh membooking kendaraannya sendiri
  kendaraan <- SELECT id FROM kendaraan
               WHERE id = kendaraan_id DAN terdaftar_oleh_user_id = sesi.user_id
  JIKA kendaraan tidak ditemukan MAKA balas error 404; SELESAI

  area <- SELECT id, nama_lantai, kapasitas FROM lantai
          WHERE id = lantai_id DAN status = 'Aktif'
  JIKA area tidak ditemukan MAKA balas error 404; SELESAI

  terpakai <- hitung_booking_bentrok(lantai_id, waktu_mulai, waktu_selesai)
  JIKA terpakai >= area.kapasitas MAKA
      balas error "Area penuh untuk rentang waktu tersebut", kode 409; SELESAI

  INSERT INTO booking (user_id, kendaraan_id, lantai_id,
                       waktu_mulai, waktu_selesai, status)
  VALUES (sesi.user_id, kendaraan_id, lantai_id, ..., 'Aktif')

  catat_log(sesi.user_id, "Booking area <nama_lantai> berhasil dibuat", "Booking")
  balas JSON sukses
SELESAI
```

```
PROSES batalkan_booking(booking_id)
  sesi <- require_login()
  booking <- SELECT id, status FROM booking
             WHERE id = booking_id DAN user_id = sesi.user_id
  JIKA booking tidak ditemukan MAKA balas error 404; SELESAI
  JIKA booking.status <> 'Aktif' MAKA
      balas error "Booking sudah tidak aktif", kode 409; SELESAI

  UPDATE booking SET status = 'Dibatalkan' WHERE id = booking.id
  catat_log(sesi.user_id, "Booking #<id> dibatalkan", "Booking")
  balas JSON sukses
SELESAI
```

Baik pembuatan maupun pembatalan selalu menyertakan `user_id` di klausa `WHERE`,
sehingga pengguna tidak bisa menyentuh booking milik orang lain sekalipun ia
menebak ID-nya.

---

## 11. Verifikasi Kendaraan oleh Admin

File: `dashboard_admin.php` → `aksi_verifikasi_kendaraan.php`

```
PROSES verifikasi_kendaraan(id, aksi, alasan, csrf_token)
  require_login_page()
  require_role(['Admin'])

  JIKA hash_equals(SESSION.csrf_token, csrf_token) = SALAH MAKA
      balas error "Token keamanan tidak valid", kode 403; SELESAI

  JIKA id kosong ATAU aksi BUKAN 'setujui' DAN BUKAN 'tolak' MAKA
      balas error 400; SELESAI

  JIKA aksi = 'tolak' DAN alasan kosong MAKA
      balas error "Alasan penolakan wajib diisi", kode 400; SELESAI

  JIKA aksi = 'setujui' MAKA
      UPDATE kendaraan SET status_verifikasi = 'Terverifikasi' WHERE id = <id>
      kategoriLog <- "Kendaraan <id> disetujui"
  LAINNYA
      tandai/hapus kendaraan sesuai kebijakan penolakan, simpan alasan
      kategoriLog <- "Kendaraan <id> ditolak: <alasan>"

  JIKA jumlah baris terpengaruh = 0 MAKA
      balas error "Data tidak ditemukan atau sudah diproses"; SELESAI

  catat_log(SESSION.user_id, kategoriLog, "Manajemen Kendaraan")
  balas JSON sukses
SELESAI
```

Kendaraan yang didaftarkan lewat `register`/booking masuk dengan
`sumber_registrasi = 'Sistem Online'` dan `status_verifikasi = 'Menunggu Verifikasi'`,
lalu menunggu langkah ini. Kendaraan yang diinput Admin langsung berstatus
`Terverifikasi`.

---

## 12. Generator Kode Otomatis

File: `config.php`

```
FUNGSI generate_kode_kendaraan()
  prefix <- "VHC-" + tahun_sekarang + "-"
  baris  <- SELECT kode_kendaraan FROM kendaraan
            WHERE kode_kendaraan SEPERTI prefix + '%'
            URUT kode_kendaraan MENURUN LIMIT 1
  urut <- 1
  JIKA baris ditemukan MAKA
      bagianAngka <- potongan kode setelah prefix
      JIKA bagianAngka seluruhnya digit MAKA urut <- bagianAngka + 1
  KEMBALIKAN prefix + format 3 digit(urut)          // contoh: VHC-2026-013
SELESAI
```

```
FUNGSI generate_username_unik(basis)
  basis <- huruf kecil, semua karakter non-alfanumerik diganti titik
  basis <- buang titik di awal/akhir
  JIKA basis kosong MAKA basis <- 'user'
  basis <- potong maksimal 40 karakter        // sisakan ruang untuk angka akhiran

  username <- basis
  i <- 1
  ULANGI SELAMANYA
      JIKA TIDAK ADA users DENGAN username = username MAKA KELUAR DARI ULANGAN
      i <- i + 1
      username <- basis + i                   // budi, budi2, budi3, ...
  KEMBALIKAN username
SELESAI
```

```
// kode_parkir dibuat inline di transaksi.php
kodeParkir <- "PRK-" + tahun + "-" + format 5 digit(COUNT(*) FROM transaksi + 1)
```

---

## 13. Filter & Pagination Log Aktivitas

File: `aktivitas.php`

```
PROSES tampilkan_log
  require_login_page()

  q         <- GET.q         atau ''
  kategori  <- GET.kategori  atau 'Semua Aktivitas'
  role      <- GET.role      atau 'Semua Role'
  halaman   <- maksimum(GET.halaman, 1)
  perHalaman <- 5
  offset    <- (halaman - 1) * perHalaman

  where  <- daftar kosong
  params <- daftar kosong

  JIKA q tidak kosong MAKA
      tambah kondisi "(nama_lengkap SEPERTI ? ATAU aktivitas SEPERTI ?)"
      tambah parameter '%q%' dua kali

  JIKA kategori ADA di kategoriMap MAKA
      // label UI ("Login / Logout") dipetakan ke nilai ENUM asli ("Login/Logout")
      tambah kondisi "kategori = ?" dengan kategoriMap[kategori]

  JIKA role <> 'Semua Role' MAKA
      tambah kondisi "nama_role = ?"

  klausaWhere <- JIKA where kosong MAKA '' LAINNYA 'WHERE ' + gabung(where, ' AND ')

  total      <- SELECT COUNT(*) ... klausaWhere
  totalHalaman <- pembulatan ke atas (total / perHalaman)

  baris <- SELECT log JOIN users ... klausaWhere
           URUT created_at MENURUN
           LIMIT perHalaman OFFSET offset

  render tabel + navigasi halaman, setiap tautan mempertahankan seluruh filter
SELESAI
```

Semua filter dikirim lewat query string dan diterjemahkan menjadi klausa `WHERE`
berparameter — bukan penyaringan di sisi tampilan — sehingga pagination tetap
konsisten dengan jumlah total hasil.

---

## 14. Laporan per Periode & Pembanding

File: `laporan.php`

```
PROSES tampilkan_laporan
  periode <- GET.periode, divalidasi terhadap ['harian','mingguan','bulanan','kustom']
             (nilai tidak dikenal dipaksa menjadi 'bulanan')

  PILIH periode
      'harian':
          tglMulai = tglAkhir = hari ini
          periodePembanding = kemarin
          label = "dari kemarin"
      'mingguan':
          tglMulai = hari ini - 6 hari ; tglAkhir = hari ini
          periodePembanding = (hari ini - 13 hari) s/d (hari ini - 7 hari)
          label = "dari minggu lalu"
      'bulanan' (default):
          tglMulai = tanggal 1 bulan ini ; tglAkhir = hari ini
          periodePembanding = seluruh bulan lalu
          label = "dari bulan lalu"
      'kustom':
          ambil GET.mulai & GET.akhir
          JIKA tanggal tidak valid MAKA pakai default 7 hari terakhir
          JIKA tglMulai > tglAkhir MAKA TUKAR keduanya
          rentangHari = selisih hari + 1
          periodePembanding = rentang sepanjang itu tepat sebelum tglMulai
          label = "dari periode sebelumnya"

  rentangSatuHari <- (tglMulai = tglAkhir)
  JIKA rentangSatuHari MAKA gambar grafik per jam
  LAINNYA gambar grafik per hari (rata-rata okupansi harian)

  hitung metrik periode berjalan dan periode pembanding
  untuk setiap metrik: tampilkan hitung_perubahan_persen(sekarang, sebelum)
SELESAI
```

```
FUNGSI hitung_perubahan_persen(sekarang, sebelum)
  JIKA sebelum = 0 MAKA
      KEMBALIKAN JIKA sekarang = 0 MAKA 0 LAINNYA TIDAK TERDEFINISI
      // hindari pembagian dengan nol; TIDAK TERDEFINISI ditampilkan
      // sebagai "Data pembanding tidak tersedia", bukan kenaikan tak hingga
  KEMBALIKAN pembulatan(((sekarang - sebelum) / sebelum) * 100, 1 desimal)
SELESAI

FUNGSI format_perubahan(nilai, label)
  JIKA nilai TIDAK TERDEFINISI MAKA KEMBALIKAN teks netral, ikon datar
  JIKA nilai > 0 MAKA KEMBALIKAN "+n% <label>", ikon naik, warna hijau
  JIKA nilai < 0 MAKA KEMBALIKAN "n% <label>",  ikon turun, warna merah
  KEMBALIKAN "Tidak berubah <label>", ikon datar
SELESAI
```

---

## 15. Audit Trail

File: `config.php`

```
FUNGSI catat_log(userId, aktivitas, kategori = 'Lainnya')
  JIKA koneksi database tidak tersedia MAKA KEMBALIKAN   // diam-diam dilewati
  COBA
      INSERT INTO log_aktivitas (user_id, aktivitas, kategori, ip_address)
      VALUES (userId, aktivitas, kategori, alamat IP pengunjung atau 'unknown')
  TANGKAP galat
      abaikan
      // disengaja: kegagalan menulis audit log tidak boleh
      //            menggagalkan aksi utama yang sedang berjalan
SELESAI
```

Kategori yang valid mengikuti ENUM kolom: `Login/Logout`, `Manajemen Kendaraan`,
`Perubahan Tarif`, `Laporan`, `Lainnya`.

---

## 16. Fallback Koneksi Database

File: `config.php`

```
COBA
    pdo <- koneksi PDO baru ke MySQL dengan mode galat exception
TANGKAP galat koneksi
    simpan pesan galat ke variabel global
    pdo <- KOSONG          // aplikasi tetap lanjut, tidak fatal

FUNGSI db_fetch_all(sql, params, fallback = daftar kosong)
    JIKA pdo KOSONG MAKA KEMBALIKAN fallback
    COBA jalankan kueri; KEMBALIKAN seluruh baris
    TANGKAP galat -> KEMBALIKAN fallback
SELESAI

FUNGSI db_fetch_one(sql, params, fallback = KOSONG)
    perilaku sama, hanya mengembalikan satu baris
SELESAI

FUNGSI db_execute(sql, params)                 // untuk INSERT/UPDATE/DELETE
    JIKA pdo KOSONG MAKA LEMPAR galat "Koneksi database tidak tersedia"
    // sengaja melempar, BUKAN mengembalikan fallback:
    // aksi tulis yang gagal tidak boleh terlihat seolah berhasil
    jalankan kueri; KEMBALIKAN jumlah baris terpengaruh
SELESAI
```

Aturannya: **operasi baca** punya data contoh sebagai cadangan supaya tampilan
tidak rusak; **operasi tulis** gagal secara terang-terangan.

---

## 17. Catatan Penyimpangan Implementasi

Beberapa titik di mana kode saat ini belum sesuai dengan algoritma di atas, dan
perlu diperbaiki sebelum sistem dipakai nyata:

| Proses | Masalah | Dampak |
| --- | --- | --- |
| §7 Catat keluar | `transaksi.php` (PUT) memanggil `ambil_tarif($tipe)` dan `hitung_biaya_parkir($durasi, $perJam, $maksHarian)` dengan tiga argumen, padahal `config.php` hanya mendefinisikan `ambil_tarif_per_jam($tipe)` dan `hitung_biaya_parkir($durasi, $perJam)` | Pemanggilan fungsi tak terdefinisi tertangkap blok penangkap galat dan dibalas sebagai pesan kesalahan — **seluruh proses catat keluar dan pembayaran gagal**. Samakan nama fungsi & jumlah parameternya |
| §10 Booking | Tabel `booking` tidak ada di `schema_parkir_pertamina.sql`, dan berkas `skema_booking.sql` yang dirujuk komentar tidak tersedia | Semua kueri booking gagal |
| §3, §4 Registrasi | Tabel `roles` hanya berisi Super Admin, Owner, Admin, Officer, Security | `register_pelanggan.php` tidak menemukan role `Pelanggan`, pendaftaran publik gagal |
| §5 Catat masuk | `kodeParkir` dibentuk dari `COUNT(*) + 1` | Setelah ada baris yang dihapus, nomor bisa terulang dan melanggar batasan UNIQUE. Gunakan `AUTO_INCREMENT` atau nomor urut terbesar + 1 |
| §5 Catat masuk | Slot kosong pertama dipilih tanpa penguncian baris | Dua petugas yang mencatat bersamaan bisa mendapat slot yang sama. Gunakan `SELECT ... FOR UPDATE` di dalam transaksi |
| §11 Verifikasi | `aksi_verifikasi_kendaraan.php` masih mendeteksi jenis koneksi secara dinamis (mencoba `db_execute`, lalu `$pdo`, `$db`, `$conn`, lalu mysqli) | Sisa kode adaptif yang tidak perlu; sederhanakan menjadi `db_execute()` saja |
| §7, §10 | `transaksi.php`, `kendaraan.php`, dan `aksi_booking.php` belum memvalidasi token CSRF (endpoint `aksi_kelola_*` sudah) | Rentan terhadap permintaan lintas situs |
