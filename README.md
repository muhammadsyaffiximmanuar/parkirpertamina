# Pertamina Parking - Dashboard Management (PHP)

Konversi dari 3 file HTML statis (`dasbordutama-.html`, `aktivitas-1.html`, `laporan-1.html`)
menjadi aplikasi PHP dinamis yang saling terhubung dan mengambil data dari database MySQL.

## Struktur Proyek

```
pertamina-parking/
├── config.php              # Koneksi database (PDO MySQL)
├── database.sql            # Skema tabel + data contoh (WAJIB diimport)
├── index.php                # Halaman Dashboard / Beranda
├── aktivitas.php            # Halaman Log Aktivitas Sistem (dengan filter & pagination)
├── laporan.php               # Halaman Laporan & Analitik (chart dari Chart.js)
├── includes/
│   ├── head.php             # <head> + konfigurasi Tailwind bersama
│   ├── sidebar.php          # Sidebar navigasi bersama (menu aktif otomatis)
│   ├── topbar.php           # Header atas bersama (jam, tanggal, profil admin)
│   └── footer.php           # Footer bersama
└── README.md
```

Ketiga halaman kini saling terhubung lewat menu **Beranda / Log Aktivitas / Laporan**
di sidebar — tidak lagi berupa file HTML terpisah dengan link `#`.

## Cara Menjalankan (XAMPP / Laragon)

1. **Salin folder** `pertamina-parking` ke dalam folder web server Anda:
   - XAMPP: `C:\xampp\htdocs\pertamina-parking`
   - Laragon: `C:\laragon\www\pertamina-parking`

2. **Buat database & import data**
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`)
   - Buat database baru bernama `pertamina_parking` (atau langsung import,
     karena `database.sql` sudah menyertakan perintah `CREATE DATABASE`)
   - Klik tab **Import**, pilih file `database.sql`, lalu jalankan.

3. **Sesuaikan koneksi database** (jika perlu) di `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'pertamina_parking');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Jalankan Apache & MySQL** dari Control Panel XAMPP/Laragon.

5. **Buka di browser:**
   ```
   http://localhost/pertamina-parking/index.php
   ```

### Menjalankan tanpa XAMPP (PHP built-in server)

Jika sudah punya PHP terinstall di komputer:
```bash
cd pertamina-parking
php -S localhost:8000
```
Lalu buka `http://localhost:8000/index.php`.

## Catatan

- Jika database belum diimport / koneksi gagal, aplikasi **tetap bisa dibuka**
  dan akan menampilkan data contoh (fallback) beserta pesan peringatan kuning
  di bagian atas halaman, supaya tampilan tidak rusak/error.
- Semua angka pada dashboard (total area, kendaraan terdaftar, okupansi,
  pendapatan, dsb.) dihitung otomatis dari isi tabel di `database.sql`.
  Silakan ubah/tambah data di tabel `area_parkir`, `kendaraan`,
  `aktivitas_kendaraan`, `log_aktivitas`, `pendapatan_harian`, dan
  `okupansi_per_jam` sesuai kebutuhan nyata Anda.
- Halaman Log Aktivitas mendukung **filter** (kategori, role, pencarian)
  dan **pagination**, semuanya diproses lewat query GET dan query SQL
  langsung ke database (bukan lagi data statis).
- Menu sidebar lain (Kendaraan, Transaksi, Lantai & Area, Tarif, Pengguna)
  belum dibuatkan halamannya — masih berupa placeholder `#`, karena file
  HTML sumber yang diberikan hanya mencakup 3 halaman ini. Anda bisa
  menambahkannya dengan mengikuti pola yang sama seperti `aktivitas.php`.
