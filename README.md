# parkirpertamina
# Sistem Manajemen Parkir Gedung Pertamina

Sistem manajemen infrastruktur parkir untuk Gedung Pertamina — memantau ketersediaan slot, mengelola tarif, memverifikasi kendaraan, dan melihat tren okupansi secara real-time. Terdiri dari **landing page publik** dan **dashboard admin**.

**Demo:** https://parkirpertamina.infinityfreeapp.com/parkirpertamina/landingpage.php

---

## ✨ Fitur

### Landing Page (Publik)
- Status ketersediaan slot parkir secara live (jumlah slot tersedia dari total kapasitas)
- Cek slot & tarif per tipe kendaraan
- Grafik tren okupansi per jam (area chart, menampilkan jam paling sibuk)
- Info fasilitas, ulasan pengguna, dan bantuan (termasuk kontak WhatsApp)
- Tombol masuk untuk staf/admin

### Dashboard Admin
- **Kelola Tarif** — tambah, ubah, dan hapus tarif per tipe kendaraan (endpoint AJAX `aksi_kelola_tarif.php`)
- **Verifikasi Kendaraan** — approval/verifikasi kendaraan masuk-keluar
- **Log Aktivitas** — riwayat aktivitas dengan filter kategori, ekspor CSV, dan cetak
- **Grafik Tren Okupansi** — visualisasi okupansi harian
- Kontrol akses berbasis role: `ROLE_OWNER`, `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`
- Proteksi sesi (`session_start`) dan CSRF token pada setiap aksi mutasi data

---

## 🛠️ Teknologi

| Layer      | Teknologi |
|------------|-----------|
| Backend    | PHP (native, tanpa framework) |
| Database   | MySQL / MariaDB |
| Frontend   | Tailwind CSS (utility classes), Material Symbols |
| Grafik     | Chart.js |
| Hosting    | InfinityFree |

---

## 📁 Struktur Proyek

```
parkirpertamina/
├── landingpage.php          # Halaman publik (hero, ketersediaan, tarif, tren okupansi)
├── dashboard_admin.php      # Dashboard admin (kelola tarif, verifikasi, log aktivitas)
├── aksi_kelola_tarif.php    # Endpoint AJAX: tambah / ubah / hapus tarif
├── config.php               # Koneksi database & fungsi helper (db_execute, require_role, dsb.)
├── parkir-kantor-pertamina.png   # Foto area parkir (ditampilkan di landing page)
├── pertamina-hq-bg.png      # Foto gedung HQ, dipakai sebagai background samar di hero
└── README.md
```

> Struktur di atas berdasarkan file yang sudah ada di proyek ini. Sesuaikan jika ada file/folder lain (misal endpoint AJAX untuk verifikasi kendaraan atau log aktivitas) yang belum tercantum.

---

## 🚀 Instalasi & Deployment

Proyek ini di-hosting di **InfinityFree**, jadi tidak perlu server sendiri — cukup upload lewat File Manager atau FTP.

1. **Siapkan database**
   - Buat database MySQL lewat panel InfinityFree.
   - Import skema tabel yang dibutuhkan (minimal tabel `tarif`, tabel kendaraan/transaksi, tabel user dengan kolom role).

2. **Konfigurasi koneksi**
   - Isi kredensial database (host, nama db, user, password) di `config.php`.
   - Pastikan `config.php` juga mendefinisikan konstanta role (`ROLE_OWNER`, `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`) dan fungsi `db_execute()` serta `require_role()`.

3. **Upload file**
   - Upload seluruh file PHP beserta aset gambar (`parkir-kantor-pertamina.png`, `pertamina-hq-bg.png`) ke folder `htdocs/parkirpertamina/` (atau folder subdomain yang sesuai) via FTP/File Manager InfinityFree.

4. **Akses**
   - Landing page: `https://parkirpertamina.infinityfreeapp.com/parkirpertamina/landingpage.php`
   - Dashboard admin: `https://parkirpertamina.infinityfreeapp.com/parkirpertamina/dashboard_admin.php` (perlu login dengan role admin/owner/super admin)

---

## 🔒 Keamanan

- Setiap request ke `aksi_kelola_tarif.php` divalidasi sesi (`$_SESSION['user_id']`) dan role pengguna.
- CSRF token dicocokkan dengan `hash_equals()` sebelum aksi tambah/ubah/hapus dieksekusi.
- Response API selalu dalam format JSON dengan kode status HTTP yang sesuai (401, 403, 422, 500).

---

## 📄 Lisensi

Proyek internal — sesuaikan bagian ini dengan lisensi yang berlaku untuk organisasi Anda.
