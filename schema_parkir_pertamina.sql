-- =====================================================================
-- SISTEM MANAJEMEN PARKIR GEDUNG PERTAMINA
-- Database Schema (MySQL / MariaDB 8+)
-- Dibuat berdasarkan struktur halaman: Dashboard, Kendaraan, Transaksi,
-- Lantai & Area, Tarif, Pengguna, Log Aktivitas, Laporan
-- =====================================================================

DROP DATABASE IF EXISTS parkir_pertamina;
CREATE DATABASE parkir_pertamina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parkir_pertamina;

-- ---------------------------------------------------------------------
-- 1. ROLES  (Owner, Admin, Officer, Security, Super Admin, ...)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nama_role       VARCHAR(50) NOT NULL UNIQUE,
    deskripsi       VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO roles (nama_role, deskripsi) VALUES
('Super Admin', 'Akses penuh ke semua modul, pengaturan sistem, dan laporan keuangan.'),
('Owner',       'Akses penuh ke semua modul, pengaturan sistem, dan laporan keuangan.'),
('Admin',       'Manajemen data kendaraan, lantai, area parkir, dan tarif.'),
('Officer',     'Akses operasional harian seperti input kendaraan dan validasi transaksi.'),
('Security',    'Akses gerbang masuk/keluar dan verifikasi kendaraan.');

-- ---------------------------------------------------------------------
-- 2. USERS (Pengguna Sistem)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap    VARCHAR(100) NOT NULL,
    username        VARCHAR(50) NOT NULL UNIQUE,
    email           VARCHAR(100) UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role_id         INT NOT NULL,
    status          ENUM('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- password default untuk SEMUA akun contoh di bawah ini: "password123"
-- (hash bcrypt asli, hasil dari password_hash('password123', PASSWORD_BCRYPT) - GANTI di produksi)
INSERT INTO users (nama_lengkap, username, email, password_hash, role_id, status) VALUES
('Bambang Susilo',  'bambang_s',   'bambang.s@pertamina.com', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 2, 'Aktif'),
('Rina Amalia',     'rina_officer','rina.a@pertamina.com',    '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 4, 'Aktif'),
('Dedi Kusuma',     'dedi_admin',  'dedi.k@pertamina.com',    '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Non-Aktif'),
('Farah Nadya',     'farah_n',     'farah.n@pertamina.com',   '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 4, 'Aktif'),
('M. Wahyu',        'wahyu_admin', 'wahyu@pertamina.com',     '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif'),
('Andri Setiawan',  'andri.s',     'andri.s@pertamina.com',   '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif'),
('Budi Kusuma',     'budi.k',      'budi.k@pertamina.com',    '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 4, 'Aktif'),
('Rina Marlina',    'rina.m',      'rina.m@pertamina.com',    '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif'),
('Dedi Hermawan',   'dedi.h',      'dedi.h@pertamina.com',    '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 1, 'Aktif'),
('Aditya Pratama',  'aditya_admin','aditya.p@pertamina.com',  '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif'),
('Ahmad S.',        'ahmad.s',     'ahmad.s@pertamina.com',   '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 5, 'Aktif'),
('Siska D.',        'siska.d',     'siska.d@pertamina.com',   '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 5, 'Aktif');

-- ---------------------------------------------------------------------
-- 3. LANTAI (Floors)
-- ---------------------------------------------------------------------
CREATE TABLE lantai (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nama_lantai     VARCHAR(50) NOT NULL,
    gedung          VARCHAR(100) NOT NULL DEFAULT 'Gedung Pusat Pertamina',
    kapasitas       INT NOT NULL DEFAULT 0,
    keterangan      VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO lantai (nama_lantai, gedung, kapasitas, keterangan) VALUES
('Lantai 1', 'Gedung Pusat Pertamina, Blok Utama', 120, 'Area VIP & Umum'),
('Lantai 2', 'Gedung Pusat Pertamina, Blok Utama', 100, 'Area Umum'),
('Lantai 3 / Area Terbuka', 'Gedung Pusat Pertamina, Blok Utama', 80, 'Area terbuka roda dua & empat');

-- ---------------------------------------------------------------------
-- 4. AREA (Section di dalam sebuah lantai, mis. Section A, B, VIP)
-- ---------------------------------------------------------------------
CREATE TABLE area (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    lantai_id       INT NOT NULL,
    kode_prefix     VARCHAR(5) NOT NULL,      -- 'A','B','VIP', dst -> dipakai sbg awalan kode slot
    nama_area       VARCHAR(100) NOT NULL,
    kapasitas       INT NOT NULL DEFAULT 0,
    FOREIGN KEY (lantai_id) REFERENCES lantai(id)
) ENGINE=InnoDB;

INSERT INTO area (lantai_id, kode_prefix, nama_area, kapasitas) VALUES
(1, 'A', 'Section A - Lantai 1', 12),
(1, 'B', 'Section B - Lantai 1', 12),
(2, 'C', 'Section C - Lantai 2', 12),
(2, 'D', 'Section D - Lantai 2', 12),
(3, 'E', 'Section E - Area Terbuka', 12);

-- ---------------------------------------------------------------------
-- 5. SLOT PARKIR (Visualisasi grid slot per area)
-- ---------------------------------------------------------------------
CREATE TABLE slot_parkir (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    area_id         INT NOT NULL,
    kode_slot       VARCHAR(10) NOT NULL UNIQUE,   -- 'A-01', 'B-12', dst
    status          ENUM('Tersedia','Terisi','Dipesan') NOT NULL DEFAULT 'Tersedia',
    FOREIGN KEY (area_id) REFERENCES area(id)
) ENGINE=InnoDB;

INSERT INTO slot_parkir (kode_slot, area_id, status) VALUES
('A-01', 1, 'Terisi'), ('A-02', 1, 'Tersedia'), ('A-03', 1, 'Dipesan'), ('A-04', 1, 'Terisi'),
('A-05', 1, 'Tersedia'), ('A-06', 1, 'Terisi'), ('A-07', 1, 'Tersedia'), ('A-08', 1, 'Tersedia'),
('A-09', 1, 'Terisi'), ('A-10', 1, 'Terisi'), ('A-11', 1, 'Tersedia'), ('A-12', 1, 'Tersedia'),
('B-01', 2, 'Terisi'), ('B-02', 2, 'Tersedia'), ('B-03', 2, 'Tersedia'), ('B-04', 2, 'Tersedia'),
('B-05', 2, 'Tersedia'), ('B-06', 2, 'Tersedia'), ('B-07', 2, 'Terisi'), ('B-08', 2, 'Tersedia'),
('B-09', 2, 'Tersedia'), ('B-10', 2, 'Tersedia'), ('B-11', 2, 'Tersedia'), ('B-12', 2, 'Tersedia'),
('C-01', 3, 'Tersedia'), ('C-02', 3, 'Terisi'), ('C-03', 3, 'Tersedia'), ('C-04', 3, 'Tersedia'),
('C-05', 3, 'Tersedia'), ('C-06', 3, 'Tersedia'), ('C-07', 3, 'Tersedia'), ('C-08', 3, 'Tersedia'),
('C-09', 3, 'Terisi'), ('C-10', 3, 'Tersedia'), ('C-11', 3, 'Terisi'), ('C-12', 3, 'Tersedia'),
('D-01', 4, 'Tersedia'), ('D-02', 4, 'Terisi'), ('D-03', 4, 'Terisi'), ('D-04', 4, 'Terisi'),
('D-05', 4, 'Dipesan'), ('D-06', 4, 'Terisi'), ('D-07', 4, 'Terisi'), ('D-08', 4, 'Terisi'),
('D-09', 4, 'Terisi'), ('D-10', 4, 'Tersedia'), ('D-11', 4, 'Tersedia'), ('D-12', 4, 'Terisi'),
('E-01', 5, 'Terisi'), ('E-02', 5, 'Tersedia'), ('E-03', 5, 'Tersedia'), ('E-04', 5, 'Terisi'),
('E-05', 5, 'Tersedia'), ('E-06', 5, 'Tersedia'), ('E-07', 5, 'Tersedia'), ('E-08', 5, 'Dipesan'),
('E-09', 5, 'Tersedia'), ('E-10', 5, 'Tersedia'), ('E-11', 5, 'Terisi'), ('E-12', 5, 'Tersedia');

-- ---------------------------------------------------------------------
-- 6. KENDARAAN (Vehicles)
-- ---------------------------------------------------------------------
CREATE TABLE kendaraan (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    kode_kendaraan          VARCHAR(20) NOT NULL UNIQUE,     -- VHC-2023-001
    plat_nomor              VARCHAR(20) NOT NULL UNIQUE,
    tipe                    ENUM('Mobil','Motor','Bus/Truk','Lainnya') NOT NULL,
    warna                   VARCHAR(30),
    nama_pemilik            VARCHAR(100) NOT NULL,
    terdaftar_oleh_user_id  INT NULL,                        -- NULL jika registrasi mandiri online
    sumber_registrasi       ENUM('Admin','Sistem Online') NOT NULL DEFAULT 'Admin',
    status_verifikasi       ENUM('Terverifikasi','Menunggu Verifikasi') NOT NULL DEFAULT 'Menunggu Verifikasi',
    izin_berlaku_sampai     DATE,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (terdaftar_oleh_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO kendaraan (kode_kendaraan, plat_nomor, tipe, warna, nama_pemilik, terdaftar_oleh_user_id, sumber_registrasi, status_verifikasi, izin_berlaku_sampai) VALUES
('VHC-2023-001', 'B 1234 ABC', 'Mobil', 'Hitam', 'Andi Wijaya',   10, 'Admin',          'Terverifikasi', '2026-12-01'),
('VHC-2023-002', 'B 5678 XYZ', 'Motor', 'Silver', 'Siti Rahma',   NULL, 'Sistem Online', 'Terverifikasi', '2026-11-15'),
('VHC-2023-003', 'F 1122 GH',  'Mobil', 'Putih', 'Budi Santoso',  10, 'Admin',          'Terverifikasi', '2026-09-20'),
('VHC-2023-004', 'B 9901 OK',  'Lainnya', 'Biru', 'Logistik Div.',10, 'Admin',          'Terverifikasi', '2026-08-30'),
('VHC-2023-005', 'D 4455 MK',  'Motor', 'Merah', 'Rina Kartika',  NULL, 'Sistem Online', 'Menunggu Verifikasi', '2026-08-12');

-- ---------------------------------------------------------------------
-- 7. TARIF (Rate configuration per vehicle type)
-- ---------------------------------------------------------------------
CREATE TABLE tarif (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tipe_kendaraan      VARCHAR(50) NOT NULL,
    deskripsi           VARCHAR(150),
    tarif_per_jam       DECIMAL(12,2) NOT NULL DEFAULT 0,
    status              ENUM('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
    updated_by_user_id  INT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO tarif (tipe_kendaraan, deskripsi, tarif_per_jam, status, updated_by_user_id) VALUES
('Motor',      'Motor Roda Dua Standard',                 3000.00, 'Aktif',     6),
('Mobil',      'Mobil Pribadi / Sedan / SUV',              7000.00, 'Aktif',     6),
('Bus/Truk',   'Kendaraan Besar & Logistik',               25000.00, 'Aktif',    6),
('Lainnya',    'Kendaraan Khusus / VIP (Flat)',            0.00,    'Non-Aktif', 6);

-- ---------------------------------------------------------------------
-- 8. TRANSAKSI (Parking transactions / ticketing)
-- ---------------------------------------------------------------------
CREATE TABLE transaksi (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kode_parkir     VARCHAR(20) NOT NULL UNIQUE,        -- PRK-2023-001
    kendaraan_id    INT NOT NULL,
    slot_id         INT NULL,
    waktu_masuk     DATETIME NOT NULL,
    waktu_keluar    DATETIME NULL,
    biaya           DECIMAL(12,2) NOT NULL DEFAULT 0,
    status          ENUM('Masuk','Keluar') NOT NULL DEFAULT 'Masuk',
    petugas_id      INT NULL,
    metode_bayar    VARCHAR(30) DEFAULT 'Tunai',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id),
    FOREIGN KEY (slot_id) REFERENCES slot_parkir(id),
    FOREIGN KEY (petugas_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO transaksi (kode_parkir, kendaraan_id, slot_id, waktu_masuk, waktu_keluar, biaya, status, petugas_id) VALUES
('PRK-2023-001', 1, 1,  '2023-10-24 08:30:00', NULL,                  15000.00, 'Masuk',  11),
('PRK-2023-002', 2, NULL, '2023-10-24 07:15:00', '2023-10-24 10:20:00', 8000.00,  'Keluar', 12),
('PRK-2023-003', 3, 25, '2023-10-24 09:00:00', NULL,                  10000.00, 'Masuk',  11),
('PRK-2023-004', 4, NULL, '2023-10-24 06:45:00', '2023-10-24 08:50:00', 0.00,     'Keluar', 12);

-- ---------------------------------------------------------------------
-- 9. LOG AKTIVITAS (Audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE log_aktivitas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    aktivitas       VARCHAR(255) NOT NULL,
    kategori        ENUM('Login/Logout','Manajemen Kendaraan','Perubahan Tarif','Laporan','Lainnya') NOT NULL DEFAULT 'Lainnya',
    ip_address      VARCHAR(45) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO log_aktivitas (user_id, aktivitas, kategori, ip_address, created_at) VALUES
(6,  'Update Tarif Parkir VIP',                    'Perubahan Tarif',      '182.253.112.42', '2023-10-24 14:25:01'),
(7,  'Login Berhasil',                             'Login/Logout',         '110.137.89.201', '2023-10-24 13:10:55'),
(8,  'Menghapus Data Kendaraan B 1234 ABC',        'Manajemen Kendaraan',  '10.20.14.55',    '2023-10-24 11:45:12'),
(9,  'Download Laporan Bulanan (Sep 2023)',        'Laporan',              '182.253.114.10', '2023-10-24 09:30:22'),
(6,  'Menambahkan Lantai 4 Section B',             'Manajemen Kendaraan',  '182.253.112.42', '2023-10-23 16:50:41');

-- =====================================================================
-- VIEWS untuk kebutuhan Dashboard & Laporan
-- =====================================================================

-- Ringkasan kartu statistik di halaman Dashboard
CREATE OR REPLACE VIEW view_dashboard_stats AS
SELECT
    (SELECT COUNT(*) FROM lantai) AS total_area_parkir,
    (SELECT COUNT(*) FROM kendaraan) AS kendaraan_terdaftar,
    (SELECT COUNT(*) FROM slot_parkir WHERE status = 'Terisi') AS terparkir_saat_ini,
    (SELECT COUNT(*) FROM slot_parkir WHERE status = 'Tersedia') AS slot_tersedia,
    (SELECT IFNULL(SUM(biaya),0) FROM transaksi WHERE DATE(waktu_masuk) = CURDATE()) AS pendapatan_hari_ini,
    (SELECT COUNT(*) FROM users WHERE status = 'Aktif') AS pengguna_aktif;

-- Okupansi per lantai (dipakai di Dashboard & Laporan)
CREATE OR REPLACE VIEW view_okupansi_lantai AS
SELECT
    l.id AS lantai_id,
    l.nama_lantai,
    l.kapasitas,
    COUNT(sp.id) AS total_slot_terdata,
    SUM(sp.status = 'Terisi') AS slot_terisi,
    ROUND(SUM(sp.status = 'Terisi') / NULLIF(COUNT(sp.id),0) * 100, 1) AS okupansi_persen
FROM lantai l
LEFT JOIN area a ON a.lantai_id = l.id
LEFT JOIN slot_parkir sp ON sp.area_id = a.id
GROUP BY l.id, l.nama_lantai, l.kapasitas;

-- Transaksi lengkap dengan info kendaraan & petugas (untuk halaman Transaksi)
CREATE OR REPLACE VIEW view_transaksi_detail AS
SELECT
    t.id, t.kode_parkir, k.plat_nomor, k.tipe AS tipe_kendaraan,
    sp.kode_slot, l.nama_lantai,
    t.waktu_masuk, t.waktu_keluar,
    TIMESTAMPDIFF(MINUTE, t.waktu_masuk, IFNULL(t.waktu_keluar, NOW())) AS durasi_menit,
    t.biaya, t.status, u.nama_lengkap AS petugas
FROM transaksi t
JOIN kendaraan k ON k.id = t.kendaraan_id
LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
LEFT JOIN area a ON a.id = sp.area_id
LEFT JOIN lantai l ON l.id = a.lantai_id
LEFT JOIN users u ON u.id = t.petugas_id;

-- Log aktivitas lengkap dengan nama & email pengguna (untuk halaman Log Aktivitas)
CREATE OR REPLACE VIEW view_log_aktivitas_detail AS
SELECT la.id, u.nama_lengkap, u.email, la.aktivitas, la.kategori, la.ip_address, la.created_at
FROM log_aktivitas la
JOIN users u ON u.id = la.user_id
ORDER BY la.created_at DESC;

-- =====================================================================
-- INDEXES tambahan untuk performa pencarian & filter yang sering dipakai
-- =====================================================================
CREATE INDEX idx_kendaraan_plat ON kendaraan(plat_nomor);
CREATE INDEX idx_transaksi_status ON transaksi(status);
CREATE INDEX idx_transaksi_waktu_masuk ON transaksi(waktu_masuk);
CREATE INDEX idx_log_kategori ON log_aktivitas(kategori);
CREATE INDEX idx_log_created_at ON log_aktivitas(created_at);
CREATE INDEX idx_slot_status ON slot_parkir(status);

-- ---------------------------------------------------------------------
-- 10. SNAPSHOT OKUPANSI PER JAM (untuk grafik tren di halaman Laporan)
-- ---------------------------------------------------------------------
CREATE TABLE okupansi_per_jam (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    jam_label   VARCHAR(5) NOT NULL,          -- '00:00', '03:00', dst
    persentase  DECIMAL(5,2) NOT NULL,
    tanggal     DATE NOT NULL DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB;

INSERT INTO okupansi_per_jam (jam_label, persentase) VALUES
('00:00', 20), ('03:00', 15), ('06:00', 45), ('09:00', 95),
('12:00', 88), ('15:00', 92), ('18:00', 70), ('21:00', 35);

-- =====================================================================
-- VIEWS tambahan (dibutuhkan oleh index.php & laporan.php)
-- =====================================================================

-- Ringkasan per area untuk tabel "Laporan Per Area" (laporan.php)
CREATE OR REPLACE VIEW view_area_ringkasan AS
SELECT
    a.id AS area_id,
    CONCAT(l.nama_lantai, ' - ', a.nama_area) AS nama_area,
    COUNT(sp.id) AS total_slot,
    SUM(sp.status = 'Terisi') AS slot_terisi,
    CASE
        WHEN COUNT(sp.id) = 0 THEN 'Normal'
        WHEN SUM(sp.status = 'Terisi') / COUNT(sp.id) >= 0.85 THEN 'Padat'
        WHEN SUM(sp.status = 'Terisi') / COUNT(sp.id) <= 0.30 THEN 'Lengang'
        ELSE 'Normal'
    END AS status,
    IFNULL((
        SELECT SUM(t.biaya) FROM transaksi t
        JOIN slot_parkir sp2 ON sp2.id = t.slot_id
        WHERE sp2.area_id = a.id
    ), 0) AS pendapatan
FROM area a
JOIN lantai l ON l.id = a.lantai_id
LEFT JOIN slot_parkir sp ON sp.area_id = a.id
GROUP BY a.id, l.nama_lantai, a.nama_area;

-- Pendapatan harian 7 hari terakhir (grafik index.php)
CREATE OR REPLACE VIEW view_pendapatan_harian AS
SELECT
    DATE(waktu_masuk) AS tanggal,
    ELT(WEEKDAY(waktu_masuk) + 1, 'Sen','Sel','Rab','Kam','Jum','Sab','Min') AS hari_label,
    SUM(biaya) AS jumlah
FROM transaksi
GROUP BY DATE(waktu_masuk);

-- Kendaraan yang baru masuk/keluar terbaru (aktivitas terbaru di index.php)
CREATE OR REPLACE VIEW view_aktivitas_kendaraan AS
SELECT
    k.plat_nomor, k.tipe AS jenis_kendaraan,
    CASE WHEN t.status = 'Masuk' THEN CONCAT('masuk ke ', IFNULL(l.nama_lantai,'-'))
         ELSE CONCAT('keluar dari ', IFNULL(l.nama_lantai,'-')) END AS keterangan,
    CASE WHEN t.status = 'Masuk' THEN 'Masuk' ELSE 'Selesai' END AS tipe_aktivitas,
    t.biaya AS nominal,
    IFNULL(t.waktu_keluar, t.waktu_masuk) AS waktu
FROM transaksi t
JOIN kendaraan k ON k.id = t.kendaraan_id
LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
LEFT JOIN area a ON a.id = sp.area_id
LEFT JOIN lantai l ON l.id = a.lantai_id
ORDER BY waktu DESC;
