-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql305.infinityfree.com
-- Generation Time: Sep 22, 2026 at 02:26 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42701768_db_parkirpertamina`
--

-- --------------------------------------------------------

--
-- Table structure for table `area`
--

CREATE TABLE `area` (
  `id` int(11) NOT NULL,
  `lantai_id` int(11) NOT NULL,
  `kode_prefix` varchar(5) NOT NULL,
  `nama_lantai` varchar(100) NOT NULL,
  `kapasitas` int(11) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `area`
--

INSERT INTO `area` (`id`, `lantai_id`, `kode_prefix`, `nama_lantai`, `kapasitas`, `keterangan`, `status`, `created_at`) VALUES
(1, 1, 'A', 'Section A - Lantai 1', 12, NULL, 'Aktif', '2026-09-07 19:41:55'),
(2, 1, 'B', 'Section B - Lantai 1', 12, NULL, 'Aktif', '2026-09-07 19:41:55'),
(3, 2, 'C', 'Section C - Lantai 2', 12, NULL, 'Aktif', '2026-09-07 19:41:55'),
(4, 2, 'D', 'Section D - Lantai 2', 12, NULL, 'Aktif', '2026-09-07 19:41:55'),
(5, 3, 'E', 'Section E - Area Terbuka', 12, NULL, 'Aktif', '2026-09-07 19:41:55'),
(6, 0, '', 'Lantai 4', 145, 'VIP', 'Aktif', '2026-09-07 19:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `kode_booking` varchar(10) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `plat_nomor` varchar(20) NOT NULL,
  `nama_pemesan` varchar(60) DEFAULT NULL,
  `waktu_booking` datetime NOT NULL,
  `status` enum('aktif','selesai','dibatalkan','kedaluwarsa') NOT NULL DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`id`, `slot_id`, `kode_booking`, `user_id`, `plat_nomor`, `nama_pemesan`, `waktu_booking`, `status`, `created_at`, `updated_at`) VALUES
(1, 37, NULL, 1240, 'AD 1234 BA', 'muhamadsyafixxx', '2026-09-18 14:07:00', 'selesai', '2026-09-17 00:07:38', '2026-09-17 00:48:38'),
(2, 47, NULL, 1240, 'AD 1234 BA', 'muhamadsyafixxx', '2026-09-17 14:19:00', 'selesai', '2026-09-17 00:17:40', '2026-09-17 00:48:32'),
(3, 46, NULL, 1241, 'AA 4537 H', 'wahyu', '2026-09-18 21:10:00', 'dibatalkan', '2026-09-17 07:10:23', '2026-09-17 07:10:40'),
(4, 46, NULL, 1241, 'AA 4537 H', 'wahyu', '2026-09-18 21:10:00', 'selesai', '2026-09-17 07:11:01', '2026-09-17 07:11:40'),
(5, 24, NULL, 1240, 'AD 1234 BA', 'muhamadsyafixxx', '2026-09-19 08:00:00', 'selesai', '2026-09-17 18:56:22', '2026-09-17 19:03:28'),
(6, 21, '93JT6A', 1240, 'AD 1234 BA', 'muhamadsyafixxx', '2026-09-19 10:20:00', 'selesai', '2026-09-17 19:17:04', '2026-09-17 19:17:47'),
(7, 18, '96QTDV', 1240, 'AD 1234 BA', 'muhamadsyafixxx', '2026-09-18 11:37:00', 'selesai', '2026-09-17 20:36:37', '2026-09-17 20:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `kendaraan`
--

CREATE TABLE `kendaraan` (
  `id` int(11) NOT NULL,
  `kode_kendaraan` varchar(20) NOT NULL,
  `plat_nomor` varchar(20) NOT NULL,
  `no_hp` varchar(20) NOT NULL DEFAULT '',
  `tipe` enum('Mobil','Motor','Bus/Truk','Lainnya') NOT NULL,
  `warna` varchar(30) DEFAULT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `terdaftar_oleh_user_id` int(11) DEFAULT NULL,
  `sumber_registrasi` enum('Admin','Sistem Online') NOT NULL DEFAULT 'Admin',
  `status_verifikasi` enum('Terverifikasi','Menunggu Verifikasi','Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
  `alasan_penolakan` varchar(255) DEFAULT NULL,
  `izin_berlaku_sampai` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kendaraan`
--

INSERT INTO `kendaraan` (`id`, `kode_kendaraan`, `plat_nomor`, `no_hp`, `tipe`, `warna`, `nama_pemilik`, `terdaftar_oleh_user_id`, `sumber_registrasi`, `status_verifikasi`, `alasan_penolakan`, `izin_berlaku_sampai`, `created_at`, `updated_at`) VALUES
(1, '', 'AB 4433 JG', '876252555', 'Mobil', NULL, 'Arti', NULL, '', 'Terverifikasi', NULL, NULL, '2026-09-06 23:43:38', '2026-09-14 18:25:12'),
(2, 'VHC-2023-002', 'B 5678 XYZ', '0', 'Motor', 'Silver', 'Siti Rahma', NULL, 'Sistem Online', 'Terverifikasi', NULL, '2026-11-15', '2026-08-11 08:45:46', '2026-09-14 18:25:12'),
(3, 'VHC-2023-003', 'F 1122 GH', '0', 'Mobil', 'Putih', 'Budi Santoso', 10, 'Admin', 'Terverifikasi', NULL, '2026-09-20', '2026-08-11 08:45:46', '2026-09-14 18:25:12'),
(4, 'VHC-2023-004', 'B 9901 OK', '0', 'Lainnya', 'Biru', 'Logistik Div.', 10, 'Admin', 'Terverifikasi', NULL, '2026-08-30', '2026-08-11 08:45:46', '2026-09-14 18:25:12'),
(5, 'VHC-2023-005', 'D 4455 MK', '0', 'Motor', 'Merah', 'Rina Kartika', NULL, 'Sistem Online', 'Menunggu Verifikasi', NULL, '2026-08-12', '2026-08-11 08:45:46', '2026-09-14 18:25:12'),
(6, 'VHC-2026-006', 'ab123', '0', 'Mobil', 'kuning', 'yusuf', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-12 21:05:06', '2026-09-14 18:25:12'),
(7, 'VHC-2026-007', 'ab 1234 bc', '0', 'Bus/Truk', 'hitam', 'wanto', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-12 21:27:44', '2026-09-14 18:25:12'),
(8, 'VHC-2026-008', 'ad 145 gc', '0', 'Bus/Truk', 'putih', 'syafix', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-12 21:52:06', '2026-09-14 18:25:12'),
(9, 'VHC-2026-009', 'aa 545 bc', '0', 'Mobil', 'biru', 'yanti', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-12 22:05:44', '2026-09-14 18:25:12'),
(10, 'VHC-2026-010', 'B 543 GV', '0', 'Mobil', 'PINK', 'aura', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-13 07:12:46', '2026-09-14 18:25:12'),
(11, 'VHC-2026-011', 'sd 3456 gf', '0', 'Mobil', 'putih', 'rendra', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-13 13:21:00', '2026-09-14 18:25:12'),
(12, 'VHC-2026-012', 'SD 3458 FD', '0', 'Motor', 'PUTIH', 'AXEL', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-08-13 13:23:40', '2026-09-14 18:25:12'),
(14, 'VHC-2023-001', 'B 1234 ABC', '822860803', 'Mobil', 'Hitam', 'Andi Wijaya', 10, 'Admin', 'Terverifikasi', NULL, '2026-12-01', '2026-08-11 08:45:46', '2026-09-14 18:25:12'),
(20, 'VHC-2026-013', 'AB 6771 FV', '2147483647', 'Mobil', NULL, 'Siti', NULL, '', 'Terverifikasi', NULL, NULL, '2026-09-07 00:38:33', '2026-09-14 18:25:12'),
(21, 'VHC-2026-014', 'AG 1234 BG', '1234567', 'Motor', NULL, 'wahyu', NULL, '', 'Terverifikasi', NULL, NULL, '2026-09-07 19:25:44', '2026-09-14 18:25:12'),
(22, 'VHC-2026-016', 'AD 5466 GR', '0', 'Motor', 'PUTIH', 'AXEL', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-07 23:53:42', '2026-09-14 18:25:12'),
(23, 'VHC-2026-017', 'AD 7868 GD', '1234567890', 'Motor', NULL, 'Arti', NULL, '', 'Terverifikasi', NULL, NULL, '2026-09-07 23:56:23', '2026-09-14 18:25:12'),
(28, 'VHC-2026-018', 'gb 7776 gg', '', 'Motor', 'biru', 'waluyo', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-14 18:31:24', '2026-09-14 18:31:24'),
(29, 'VHC-2026-019', 'ab 4555 ac', '', 'Motor', 'biru', 'rendra', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 18:27:22', '2026-09-15 18:27:22'),
(30, 'VHC-2026-020', 'ds 4334 re', '', 'Motor', 'putih', 'evan', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 18:47:58', '2026-09-15 18:47:58'),
(31, 'VHC-2026-021', 'd 5255 kk', '', 'Motor', 'biru', 'hhh', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 19:00:56', '2026-09-15 19:00:56'),
(32, 'VHC-2026-022', 'd 1555 gf', '', 'Motor', 'biru', 'AXEL', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 19:14:10', '2026-09-15 19:14:10'),
(33, 'VHC-2026-023', 'we 3333 rt', '', 'Mobil', 'putih', 'sumadi', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 20:24:03', '2026-09-15 20:24:03'),
(34, 'VHC-2026-024', 're 4444 bv', '', 'Bus/Truk', '', 'arti', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 20:27:16', '2026-09-15 20:27:16'),
(35, 'VHC-2026-025', 'ad 5555 h', '', 'Motor', 'PINK', 'rendra', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 20:56:41', '2026-09-15 20:56:41'),
(36, 'VHC-2026-026', '5555', '', 'Bus/Truk', 'putih', 'yt', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 21:15:20', '2026-09-15 21:15:20'),
(37, 'VHC-2026-027', 'ab 2323 kl', '', 'Motor', 'biru', 'evan', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-15 23:33:50', '2026-09-15 23:33:50'),
(38, 'VHC-2026-028', 'we 1111 sd', '', 'Mobil', 'putih', 'evan', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-16 07:03:26', '2026-09-16 07:03:26'),
(39, 'VHC-2026-029', 'AD 1234 BA', '088228653320', 'Motor', NULL, 'muhamadsyafixxx', 1240, 'Sistem Online', 'Terverifikasi', NULL, NULL, '2026-09-16 18:22:32', '2026-09-16 18:41:58'),
(40, 'VHC-2026-030', 'ad 34 feu', '', 'Bus/Truk', 'dongker', 'sumadi', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-17 00:37:58', '2026-09-17 07:03:12'),
(41, 'VHC-2026-031', 'as 44 tr', '', 'Bus/Truk', 'putih', 'aku', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-17 01:54:19', '2026-09-17 07:02:54'),
(42, 'VHC-2026-032', 'sd 445 df', '', 'Motor', 'pink', 'po', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-17 01:56:54', '2026-09-17 07:02:37'),
(44, 'VHC-2026-033', 'hh 667 hg', '', 'Bus/Truk', 'putih', 'gg', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-17 01:58:05', '2026-09-17 07:02:22'),
(45, 'VHC-2026-034', 'aa 54 ds', '', 'Lainnya', 'putih', 'evan', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-17 05:03:54', '2026-09-17 07:01:54'),
(46, 'VHC-2026-035', 'AA 4537 H', '088228653320', 'Mobil', NULL, 'wahyu', 1241, 'Sistem Online', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 07:08:45', '2026-09-17 07:08:45'),
(47, 'VHC-2026-036', 'rt 223 u', '', 'Bus/Truk', 'PINK', 'Kurniawan', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 19:39:46', '2026-09-17 19:39:46'),
(48, 'VHC-2026-037', 'b 7889 ab', '', 'Motor', 'PINK', 'Smajiem', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 19:46:32', '2026-09-17 19:46:32'),
(49, 'VHC-2026-038', 's 8288 kk', '', 'Motor', 'hitam', 'mmm', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 20:00:21', '2026-09-17 20:00:21'),
(50, 'VHC-2026-039', 'tt 7777 yy', '', 'Motor', 'coklat', 'laila', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 20:26:57', '2026-09-17 20:26:57'),
(51, 'VHC-2026-040', 's 4700 nn', '', 'Mobil', 'biru', 'santo', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-17 20:35:18', '2026-09-17 20:35:18'),
(52, 'VHC-2026-041', 'AB 2008 HG', '088228653320', 'Mobil', NULL, 'jesslyn senja ayuning tyas', 1242, 'Sistem Online', 'Menunggu Verifikasi', NULL, NULL, '2026-09-20 05:16:56', '2026-09-20 05:16:56'),
(53, 'VHC-2026-042', 's 4543 h', '', 'Motor', 'putih', 'evan', 2, 'Admin', 'Menunggu Verifikasi', NULL, NULL, '2026-09-20 18:38:38', '2026-09-20 18:38:38'),
(54, 'VHC-2026-043', 's 6544 g', '', 'Bus/Truk', 'putih', 'yanto', 2, 'Admin', 'Terverifikasi', NULL, NULL, '2026-09-21 17:17:09', '2026-09-21 17:33:02'),
(55, 'VHC-2026-044', 'A 0 S', '9999999999', 'Motor', NULL, 'hhh', 1243, 'Sistem Online', 'Menunggu Verifikasi', NULL, NULL, '2026-09-21 23:21:51', '2026-09-21 23:21:51');

-- --------------------------------------------------------

--
-- Table structure for table `komentar`
--

CREATE TABLE `komentar` (
  `id` int(11) NOT NULL,
  `nama` varchar(80) NOT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `isi` text NOT NULL,
  `status` enum('Publik','Disembunyikan') NOT NULL DEFAULT 'Publik',
  `ip_address` varchar(45) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `komentar`
--

INSERT INTO `komentar` (`id`, `nama`, `rating`, `isi`, `status`, `ip_address`, `dibuat_pada`) VALUES
(1, 'Syafix', 5, 'sangat bagus', 'Publik', '5.255.118.82', '2026-09-17 06:56:10'),
(2, 'Nafian', 5, 'karena yang buat ganteng', 'Publik', '5.255.118.82', '2026-09-17 06:56:54'),
(3, 'MOKO', 5, 'ini UKK terbaik menurut saya', 'Publik', '5.255.118.82', '2026-09-17 06:57:21'),
(4, 'Evan fajar', 5, 'Parkirannya sangat bersih dan petugasnya baik', 'Publik', '89.105.200.65', '2026-09-17 18:45:23'),
(5, 'jesslyn', 5, 'bagussss banget keren', 'Publik', '89.105.200.65', '2026-09-20 05:11:37');

-- --------------------------------------------------------

--
-- Table structure for table `lantai`
--

CREATE TABLE `lantai` (
  `id` int(11) NOT NULL,
  `nama_lantai` varchar(50) NOT NULL,
  `gedung` varchar(100) NOT NULL DEFAULT 'Gedung Pusat Pertamina',
  `kapasitas` int(11) NOT NULL DEFAULT 0,
  `keterangan` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lantai`
--

INSERT INTO `lantai` (`id`, `nama_lantai`, `gedung`, `kapasitas`, `keterangan`, `status`, `created_at`) VALUES
(1, 'Lantai 1', 'Gedung Pusat Pertamina, Blok Utama', 120, 'Area VIP & Umum', 'Aktif', '2026-09-07 20:19:26'),
(2, 'Lantai 2', 'Gedung Pusat Pertamina, Blok Utama', 100, 'Area Umum', 'Aktif', '2026-09-07 20:19:26'),
(3, 'Lantai 3 / Area Terbuka', 'Gedung Pusat Pertamina, Blok Utama', 80, 'Area terbuka roda dua & empat', 'Aktif', '2026-09-07 20:19:26'),
(4, 'Under Ground', 'Gedung Pusat Pertamina', 50, 'Bawah Tanah Anti BOm', 'Aktif', '2026-09-07 20:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `kategori` enum('Login/Logout','Manajemen Kendaraan','Perubahan Tarif','Laporan','Lainnya') NOT NULL DEFAULT 'Lainnya',
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `user_id`, `aktivitas`, `kategori`, `ip_address`, `created_at`) VALUES
(5, 6, 'Update Tarif Parkir VIP', 'Perubahan Tarif', '182.253.112.42', '2023-10-24 14:25:01'),
(4, 7, 'Login Berhasil', 'Login/Logout', '110.137.89.201', '2023-10-24 13:10:55'),
(3, 8, 'Menghapus Data Kendaraan B 1234 ABC', 'Manajemen Kendaraan', '10.20.14.55', '2023-10-24 11:45:12'),
(2, 9, 'Download Laporan Bulanan (Sep 2023)', 'Laporan', '182.253.114.10', '2023-10-24 09:30:22'),
(1, 6, 'Menambahkan Lantai 4 Section B', 'Manajemen Kendaraan', '182.253.112.42', '2023-10-23 16:50:41'),
(6, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 09:34:34'),
(7, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 09:37:51'),
(8, 7, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-11 09:39:52'),
(9, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 09:39:59'),
(10, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 09:43:40'),
(11, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 09:48:58'),
(12, 7, 'Logout', 'Login/Logout', '::1', '2026-08-11 10:36:03'),
(13, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 10:36:27'),
(14, 1, 'Logout', 'Login/Logout', '::1', '2026-08-11 10:37:30'),
(15, 2, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-11 10:38:42'),
(16, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 10:39:13'),
(17, 2, 'Logout', 'Login/Logout', '::1', '2026-08-11 11:02:21'),
(18, 7, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-11 11:03:02'),
(19, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:03:13'),
(20, 7, 'Logout', 'Login/Logout', '::1', '2026-08-11 11:03:17'),
(21, 7, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-11 11:03:40'),
(22, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:03:46'),
(23, 7, 'Logout', 'Login/Logout', '::1', '2026-08-11 11:05:06'),
(24, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:06:39'),
(25, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:07:40'),
(26, 5, 'Logout', 'Login/Logout', '::1', '2026-08-11 11:08:07'),
(27, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:08:38'),
(28, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 11:23:37'),
(29, 1, 'Logout', 'Login/Logout', '::1', '2026-08-11 12:00:35'),
(30, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 12:04:31'),
(31, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 12:11:00'),
(32, 5, 'Logout', 'Login/Logout', '::1', '2026-08-11 12:11:35'),
(33, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 12:12:12'),
(34, 1, 'Logout', 'Login/Logout', '::1', '2026-08-11 12:12:56'),
(35, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 13:41:56'),
(36, 7, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-11 19:27:12'),
(37, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-11 19:27:33'),
(38, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 08:09:28'),
(39, 1, 'Logout', 'Login/Logout', '::1', '2026-08-12 08:09:47'),
(40, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 08:13:56'),
(41, 5, 'Logout', 'Login/Logout', '::1', '2026-08-12 08:15:07'),
(42, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 09:37:49'),
(43, 7, 'Logout', 'Login/Logout', '::1', '2026-08-12 09:37:55'),
(44, 13, 'Registrasi Akun Baru (syafiximanuar) - Menunggu Aktivasi Admin', 'Lainnya', '::1', '2026-08-12 12:34:50'),
(45, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:39:47'),
(46, 1, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:40:42'),
(47, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:41:21'),
(48, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:46:20'),
(49, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:46:33'),
(50, 5, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:47:27'),
(51, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:47:55'),
(52, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:53:49'),
(53, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:54:06'),
(54, 5, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:54:15'),
(55, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:58:32'),
(56, 5, 'Logout', 'Login/Logout', '::1', '2026-08-12 12:59:32'),
(57, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 12:59:48'),
(58, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 13:11:21'),
(59, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 14:03:30'),
(60, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 14:04:17'),
(61, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 14:07:51'),
(62, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 14:15:57'),
(63, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 21:02:23'),
(64, 2, 'Menambahkan Kendaraan ab123', 'Manajemen Kendaraan', '::1', '2026-08-12 21:05:06'),
(65, 2, 'Catat Kendaraan Masuk ab123 (PRK-2026-00005)', 'Manajemen Kendaraan', '::1', '2026-08-12 21:05:06'),
(66, 2, 'Menambahkan Kendaraan ab 1234 bc', 'Manajemen Kendaraan', '::1', '2026-08-12 21:27:44'),
(67, 2, 'Catat Kendaraan Masuk ab 1234 bc (PRK-2026-00006)', 'Manajemen Kendaraan', '::1', '2026-08-12 21:27:44'),
(68, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 21:32:53'),
(69, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 21:33:14'),
(70, 2, 'Menambahkan Kendaraan ad 145 gc', 'Manajemen Kendaraan', '::1', '2026-08-12 21:52:06'),
(71, 2, 'Catat Kendaraan Masuk ad 145 gc (PRK-2026-00007)', 'Manajemen Kendaraan', '::1', '2026-08-12 21:52:06'),
(72, 2, 'Menambahkan Kendaraan aa 545 bc', 'Manajemen Kendaraan', '::1', '2026-08-12 22:05:44'),
(73, 2, 'Catat Kendaraan Masuk aa 545 bc (PRK-2026-00008)', 'Manajemen Kendaraan', '::1', '2026-08-12 22:05:44'),
(74, 2, 'Logout', 'Login/Logout', '::1', '2026-08-12 22:06:39'),
(75, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-12 22:06:57'),
(76, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 07:11:44'),
(77, 2, 'Menambahkan Kendaraan B 543 GV', 'Manajemen Kendaraan', '::1', '2026-08-13 07:12:46'),
(78, 2, 'Catat Kendaraan Masuk B 543 GV (PRK-2026-00009)', 'Manajemen Kendaraan', '::1', '2026-08-13 07:12:46'),
(79, 2, 'Logout', 'Login/Logout', '::1', '2026-08-13 07:22:50'),
(80, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 07:23:10'),
(81, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 08:16:53'),
(82, 1, 'Logout', 'Login/Logout', '::1', '2026-08-13 09:43:14'),
(83, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 09:43:30'),
(84, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 11:16:48'),
(85, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 11:17:04'),
(86, 2, 'Logout', 'Login/Logout', '::1', '2026-08-13 11:17:25'),
(87, 1, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 11:17:42'),
(88, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 11:19:38'),
(89, 1, 'Logout', 'Login/Logout', '::1', '2026-08-13 11:22:14'),
(90, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 11:22:33'),
(91, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 11:55:17'),
(92, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 11:59:37'),
(93, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 12:00:14'),
(94, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 12:08:45'),
(95, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 12:08:49'),
(96, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 12:09:07'),
(97, 2, 'Logout', 'Login/Logout', '::1', '2026-08-13 12:15:31'),
(98, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 12:15:53'),
(99, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 12:16:03'),
(100, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 13:19:50'),
(101, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 13:20:07'),
(102, 2, 'Menambahkan Kendaraan sd 3456 gf', 'Manajemen Kendaraan', '::1', '2026-08-13 13:21:00'),
(103, 2, 'Catat Kendaraan Masuk sd 3456 gf (PRK-2026-00010)', 'Manajemen Kendaraan', '::1', '2026-08-13 13:21:00'),
(104, 2, 'Catat Kendaraan Keluar sd 3456 gf (PRK-2026-00010) - Bayar QRIS Rp 7.000', 'Manajemen Kendaraan', '::1', '2026-08-13 13:21:23'),
(105, 2, 'Menambahkan Kendaraan SD 3458 FD', 'Manajemen Kendaraan', '::1', '2026-08-13 13:23:40'),
(106, 2, 'Catat Kendaraan Masuk SD 3458 FD (PRK-2026-00011)', 'Manajemen Kendaraan', '::1', '2026-08-13 13:23:40'),
(107, 2, 'Catat Kendaraan Keluar SD 3458 FD (PRK-2026-00011) - Bayar Tunai Rp 3.000', 'Manajemen Kendaraan', '::1', '2026-08-13 13:23:44'),
(108, 2, 'Logout', 'Login/Logout', '::1', '2026-08-13 13:24:09'),
(109, 1, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 13:24:28'),
(110, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 20:59:41'),
(111, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 20:59:51'),
(112, 5, 'Logout', 'Login/Logout', '::1', '2026-08-13 21:40:55'),
(113, 1234, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 21:44:30'),
(114, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 21:45:06'),
(115, 7, 'Logout', 'Login/Logout', '::1', '2026-08-13 21:47:38'),
(116, 1234, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 21:47:51'),
(117, 1234, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-13 21:48:34'),
(118, 7, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-13 21:48:44'),
(119, 1234, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-14 13:23:31'),
(120, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-14 13:30:54'),
(121, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-18 10:02:01'),
(122, 5, 'Logout', 'Login/Logout', '::1', '2026-08-18 10:07:04'),
(123, 1234, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '::1', '2026-08-18 10:07:27'),
(124, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-18 10:43:08'),
(129, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-20 07:16:06'),
(130, 5, 'Logout', 'Login/Logout', '::1', '2026-08-20 07:38:38'),
(131, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-20 07:59:16'),
(132, 2, 'Logout', 'Login/Logout', '::1', '2026-08-20 07:59:49'),
(133, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-20 08:00:07'),
(134, 5, 'Logout', 'Login/Logout', '::1', '2026-08-20 08:05:06'),
(135, 2, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-20 08:07:32'),
(136, 2, 'Menambahkan Kendaraan sd 123', 'Manajemen Kendaraan', '::1', '2026-08-20 08:07:57'),
(137, 2, 'Catat Kendaraan Masuk sd 123 (PRK-2026-00012)', 'Manajemen Kendaraan', '::1', '2026-08-20 08:07:57'),
(138, 2, 'Logout', 'Login/Logout', '::1', '2026-08-20 08:48:51'),
(139, 5, 'Login Berhasil', 'Login/Logout', '::1', '2026-08-20 08:49:12'),
(125, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-08-19 23:48:06'),
(126, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-08-20 00:00:06'),
(127, 5, 'Login Berhasil', 'Login/Logout', '103.210.35.3', '2026-08-20 00:07:13'),
(128, 5, 'Logout', 'Login/Logout', '103.210.35.3', '2026-08-20 00:08:44'),
(140, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-08-20 22:54:56'),
(141, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:11'),
(142, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:16'),
(143, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:22'),
(144, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:24'),
(145, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:27'),
(146, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.65.214.246', '2026-08-25 01:49:29'),
(147, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.210.35.3', '2026-09-01 21:36:34'),
(148, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.210.35.3', '2026-09-01 21:36:40'),
(149, 5, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '103.210.35.3', '2026-09-01 21:36:46'),
(150, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-02 18:26:44'),
(151, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-02 18:39:12'),
(152, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-02 20:47:50'),
(153, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-02 20:51:49'),
(154, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-06 23:15:07'),
(155, 2, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-07 23:52:42'),
(156, 2, 'Menambahkan Kendaraan AD 5466 GR', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-07 23:53:42'),
(157, 2, 'Catat Kendaraan Masuk AD 5466 GR (PRK-2026-00013)', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-07 23:53:43'),
(158, 2, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-07 23:54:12'),
(159, 1, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-07 23:54:55'),
(160, 1, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-07 23:56:38'),
(161, 1, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-07 23:58:00'),
(162, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-13 21:58:08'),
(163, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-13 22:00:26'),
(164, 2, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-13 22:00:34'),
(165, 2, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-13 23:45:24'),
(166, 2, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-13 23:45:31'),
(0, 2, 'Menambahkan Kendaraan gb 7776 gg', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-14 18:31:24'),
(0, 2, 'Catat Kendaraan Masuk gb 7776 gg (PRK-2026-00014)', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-14 18:31:25'),
(0, 2, 'Catat Kendaraan Keluar gb 7776 gg (PRK-2026-00014) - Bayar Tunai Rp 42.000', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-14 18:31:51'),
(0, 2, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:32:30'),
(0, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 18:34:07'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-14 18:36:10'),
(0, 9, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 18:36:32'),
(0, 9, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:37:22'),
(0, 2, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-14 18:37:46'),
(0, 2, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:37:59'),
(0, 5, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-14 18:38:06'),
(0, 5, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:38:21'),
(0, 1, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-14 18:38:31'),
(0, 1, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:39:29'),
(0, 5, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-14 18:39:41'),
(0, 5, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 18:40:18'),
(0, 1, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-14 18:40:25'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-14 18:50:42'),
(0, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 18:50:50'),
(0, 5, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-14 18:51:01'),
(0, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 18:55:07'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-14 18:58:41'),
(0, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 18:58:59'),
(0, 5, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-14 19:26:56'),
(0, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-14 19:38:50'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-14 19:49:38'),
(0, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-15 17:35:13'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-15 18:25:43'),
(0, 2, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-15 18:25:52'),
(0, 2, 'Menambahkan Kendaraan ab 4555 ac', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 18:27:22'),
(0, 2, 'Catat Kendaraan Masuk ab 4555 ac (PRK-2026-00015)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 18:27:22'),
(0, 2, 'Menambahkan Kendaraan ds 4334 re', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 18:47:58'),
(0, 2, 'Catat Kendaraan Masuk ds 4334 re (PRK-2026-00016)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 18:47:58'),
(0, 2, 'Menambahkan Kendaraan d 5255 kk', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 19:00:56'),
(0, 2, 'Catat Kendaraan Masuk d 5255 kk (PRK-2026-00017)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 19:00:56'),
(0, 2, 'Catat Kendaraan Keluar d 5255 kk (PRK-2026-00017) - Bayar Tunai Rp 45.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 19:02:09'),
(0, 2, 'Menambahkan Kendaraan d 1555 gf', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 19:14:10'),
(0, 2, 'Catat Kendaraan Masuk d 1555 gf (PRK-2026-00018)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 19:14:10'),
(0, 2, 'Catat Kendaraan Keluar d 1555 gf (PRK-2026-00018) - Bayar Tunai Rp 45.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-15 19:15:18'),
(0, 2, 'Menambahkan Kendaraan we 3333 rt', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:24:03'),
(0, 2, 'Catat Kendaraan Masuk we 3333 rt (PRK-2026-00019)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:24:04'),
(0, 2, 'Catat Kendaraan Keluar we 3333 rt (PRK-2026-00019) - Bayar Tunai Rp 98.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:24:33'),
(0, 2, 'Menambahkan Kendaraan re 4444 bv', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:27:16'),
(0, 2, 'Catat Kendaraan Masuk re 4444 bv (PRK-2026-00020)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:27:16'),
(0, 2, 'Catat Kendaraan Keluar re 4444 bv (PRK-2026-00020) - Bayar Tunai Rp 350.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:27:46'),
(0, 2, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-15 20:28:53'),
(0, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-15 20:29:02'),
(0, 5, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-15 20:50:09'),
(0, 1, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-15 20:50:18'),
(0, 1, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-15 20:50:42'),
(0, 2, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-15 20:50:50'),
(0, 2, 'Menambahkan Kendaraan ad 5555 h', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:56:41'),
(0, 2, 'Catat Kendaraan Masuk ad 5555 h (PRK-2026-00021)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 20:56:41'),
(0, 2, 'Menambahkan Kendaraan 5555', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:15:20'),
(0, 2, 'Catat Kendaraan Masuk 5555 (PRK-2026-00022)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:15:21'),
(0, 2, 'Catat Kendaraan Keluar AD 5466 GR (PRK-2026-00013) - Bayar Tunai Rp 612.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:27:55'),
(0, 2, 'Catat Kendaraan Keluar ds 4334 re (PRK-2026-00016) - Bayar Tunai Rp 51.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:28:28'),
(0, 2, 'Catat Kendaraan Keluar 5555 (PRK-2026-00022) - Bayar Tunai Rp 375.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:28:49'),
(0, 2, 'Catat Kendaraan Keluar ad 5555 h (PRK-2026-00021) - Bayar Tunai Rp 45.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-15 21:29:11'),
(0, 2, 'Menambahkan Kendaraan ab 2323 kl', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-15 23:33:50'),
(0, 2, 'Catat Kendaraan Masuk ab 2323 kl (PRK-2026-00023)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-15 23:33:50'),
(0, 2, 'Catat Kendaraan Keluar ab 2323 kl (PRK-2026-00023) - Bayar Tunai Rp 42.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-15 23:34:39'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 06:38:13'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 07:02:32'),
(0, 2, 'Menambahkan Kendaraan we 1111 sd', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-16 07:03:26'),
(0, 2, 'Catat Kendaraan Masuk we 1111 sd (PRK-2026-00024)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-16 07:03:26'),
(0, 2, 'Catat Kendaraan Keluar we 1111 sd (PRK-2026-00024) - Bayar QRIS Rp 98.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-16 07:03:42'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 17:25:49'),
(0, 1238, 'Registrasi Pelanggan Baru (syafix)', 'Lainnya', '89.105.200.65', '2026-09-16 18:07:14');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `aktivitas`, `kategori`, `ip_address`, `created_at`) VALUES
(0, 1239, 'Registrasi Pelanggan Baru (syafixx)', 'Lainnya', '89.105.200.65', '2026-09-16 18:12:12'),
(0, 1240, 'Registrasi Pelanggan Baru (syafixxx)', 'Lainnya', '89.105.200.65', '2026-09-16 18:22:32'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:23:16'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:31:23'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:31:26'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:33:03'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:34:30'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:34:43'),
(0, 1239, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '89.105.200.65', '2026-09-16 18:35:15'),
(0, 1239, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '89.105.200.65', '2026-09-16 18:35:16'),
(0, 1239, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '89.105.200.65', '2026-09-16 18:35:53'),
(0, 1239, 'Login Gagal - Kata Sandi Salah', 'Login/Logout', '89.105.200.65', '2026-09-16 18:35:55'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:35:59'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:36:00'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:38:00'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:38:02'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:40:07'),
(0, 1240, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 18:40:25'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:40:32'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 18:40:40'),
(0, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:40:52'),
(0, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 18:42:08'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 18:49:50'),
(0, 1240, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-16 19:05:58'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 19:06:35'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-16 23:58:53'),
(0, 1240, 'Booking slot #37 berhasil dibuat', '', '89.105.200.65', '2026-09-17 00:07:38'),
(0, 1240, 'Booking slot #47 berhasil dibuat', '', '89.105.200.65', '2026-09-17 00:17:40'),
(0, 1240, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 00:17:57'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 00:18:05'),
(0, 2, 'Menambahkan Kendaraan ad 34 feu', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 00:37:58'),
(0, 2, 'Catat Kendaraan Masuk ad 34 feu (PRK-2026-00025)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 00:37:58'),
(0, 2, 'Booking #2 ditandai sudah datang oleh petugas', '', '5.255.118.82', '2026-09-17 00:48:32'),
(0, 2, 'Booking #1 ditandai sudah datang oleh petugas', '', '5.255.118.82', '2026-09-17 00:48:38'),
(0, 2, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-17 01:22:41'),
(0, 2, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-17 01:22:46'),
(0, 2, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-17 01:38:28'),
(0, 2, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-17 01:38:34'),
(0, 2, 'Menambahkan Kendaraan as 44 tr', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:54:19'),
(0, 2, 'Catat Kendaraan Masuk as 44 tr (PRK-2026-00026)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:54:19'),
(0, 2, 'Menambahkan Kendaraan sd 445 df', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:56:54'),
(0, 2, 'Catat Kendaraan Masuk sd 445 df (PRK-2026-00027)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:56:55'),
(0, 2, 'Menambahkan Kendaraan hh 667 hg', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:58:05'),
(0, 2, 'Catat Kendaraan Masuk hh 667 hg (PRK-2026-00028)', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:58:05'),
(0, 2, 'Catat Kendaraan Keluar hh 667 hg (PRK-2026-00028) - Bayar Tunai Rp 0', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:58:10'),
(0, 2, 'Catat Kendaraan Keluar sd 445 df (PRK-2026-00027) - Bayar Tunai Rp 0', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:58:58'),
(0, 2, 'Catat Kendaraan Keluar ad 34 feu (PRK-2026-00025) - Bayar Tunai Rp 0', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 01:59:38'),
(0, 2, 'Menambahkan Kendaraan aa 54 ds', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 05:03:54'),
(0, 2, 'Catat Kendaraan Masuk aa 54 ds (PRK-2026-00029)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 05:03:54'),
(0, 2, 'Catat Kendaraan Keluar aa 54 ds (PRK-2026-00029) - Bayar Tunai Rp 0', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 05:05:00'),
(0, 2, 'Logout', 'Login/Logout', '5.255.118.82', '2026-09-17 05:44:54'),
(0, 5, 'Login Berhasil', 'Login/Logout', '5.255.118.82', '2026-09-17 06:58:20'),
(0, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 07:06:34'),
(0, 1241, 'Registrasi Pelanggan Baru (wahyu)', 'Lainnya', '89.105.200.65', '2026-09-17 07:08:45'),
(0, 1241, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 07:09:11'),
(0, 1241, 'Booking slot #46 berhasil dibuat', '', '5.255.118.82', '2026-09-17 07:10:23'),
(0, 1241, 'Booking #3 dibatalkan', '', '89.105.200.65', '2026-09-17 07:10:40'),
(0, 1241, 'Booking slot #46 berhasil dibuat', '', '89.105.200.65', '2026-09-17 07:11:01'),
(0, 1241, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 07:11:19'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 07:11:29'),
(0, 2, 'Booking #4 ditandai sudah datang oleh petugas', '', '89.105.200.65', '2026-09-17 07:11:40'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 18:55:28'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 18:55:47'),
(0, 1240, 'Booking slot #24 berhasil dibuat', '', '89.105.200.65', '2026-09-17 18:56:22'),
(0, 1240, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 18:56:32'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 18:57:54'),
(0, 2, 'Booking #5 ditandai sudah datang, transaksi #30 dibuat', '', '89.105.200.65', '2026-09-17 19:03:28'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 19:15:59'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 19:16:21'),
(0, 1240, 'Booking slot #21 berhasil dibuat (kode: 93JT6A)', '', '89.105.200.65', '2026-09-17 19:17:04'),
(0, 1240, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 19:17:26'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 19:17:30'),
(0, 2, 'Booking #6 ditandai sudah datang, transaksi #31 dibuat', '', '89.105.200.65', '2026-09-17 19:17:47'),
(0, 2, 'Menambahkan Kendaraan rt 223 u', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 19:39:46'),
(0, 2, 'Catat Kendaraan Masuk rt 223 u (PRK-2026-00032)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 19:39:47'),
(0, 2, 'Menambahkan Kendaraan b 7889 ab', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 19:46:32'),
(0, 2, 'Catat Kendaraan Masuk b 7889 ab (PRK-2026-00033)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 19:46:32'),
(0, 2, 'Catat Kendaraan Keluar b 7889 ab (PRK-2026-00033) - Bayar Tunai Rp 45.000', 'Manajemen Kendaraan', '5.255.118.82', '2026-09-17 19:59:46'),
(0, 2, 'Menambahkan Kendaraan s 8288 kk', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:00:21'),
(0, 2, 'Catat Kendaraan Masuk s 8288 kk (PRK-2026-00034)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:00:22'),
(0, 2, 'Catat Kendaraan Keluar s 8288 kk (PRK-2026-00034) - Bayar Tunai Rp 42.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:00:42'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 20:01:50'),
(0, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 20:01:56'),
(0, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-17 20:25:58'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-17 20:26:06'),
(0, 2, 'Menambahkan Kendaraan tt 7777 yy', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:26:57'),
(0, 2, 'Catat Kendaraan Masuk tt 7777 yy (PRK-2026-00035)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:26:57'),
(0, 2, 'Catat Kendaraan Keluar tt 7777 yy (PRK-2026-00035) - Bayar Tunai Rp 42.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 20:27:22'),
(0, 2, 'Menambahkan Kendaraan s 4700 nn', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-17 20:35:18'),
(0, 2, 'Catat Kendaraan Masuk s 4700 nn (PRK-2026-00036)', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-17 20:35:19'),
(0, 2, 'Catat Kendaraan Keluar s 4700 nn (PRK-2026-00036) - Bayar Tunai Rp 7.000', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-17 20:35:30'),
(0, 2, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-17 20:35:47'),
(0, 1240, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-17 20:36:08'),
(0, 1240, 'Booking slot #18 berhasil dibuat (kode: 96QTDV)', '', '203.25.108.72', '2026-09-17 20:36:37'),
(0, 1240, 'Logout', 'Login/Logout', '203.25.108.72', '2026-09-17 20:36:52'),
(0, 2, 'Login Berhasil', 'Login/Logout', '203.25.108.72', '2026-09-17 20:36:57'),
(0, 2, 'Booking #7 ditandai sudah datang, transaksi #37 dibuat', '', '203.25.108.72', '2026-09-17 20:37:16'),
(0, 2, 'Catat Kendaraan Keluar AD 1234 BA () - Bayar Tunai Rp 3.000', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-17 20:38:21'),
(0, 2, 'Catat Kendaraan Keluar ad 145 gc (PRK-2026-00007) - Bayar QRIS Rp 21.600.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 21:01:53'),
(0, 2, 'Catat Kendaraan Keluar ab 1234 bc (PRK-2026-00006) - Bayar Tunai Rp 21.600.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-17 21:04:46'),
(0, 1242, 'Registrasi Pelanggan Baru (senja)', 'Lainnya', '89.105.200.65', '2026-09-20 05:16:56'),
(0, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-20 05:18:29'),
(0, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-20 05:19:24'),
(0, 2, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-20 18:36:45'),
(0, 2, 'Catat Kendaraan Keluar rt 223 u (PRK-2026-00032) - Bayar Tunai Rp 1.775.000', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-20 18:37:40'),
(0, 2, 'Menambahkan Kendaraan s 4543 h', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-20 18:38:38'),
(0, 2, 'Catat Kendaraan Masuk s 4543 h (PRK-2026-00038)', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-20 18:38:38'),
(0, 2, 'Catat Kendaraan Keluar s 4543 h (PRK-2026-00038) - Bayar QRIS Rp 3.000', 'Manajemen Kendaraan', '203.25.108.72', '2026-09-20 18:39:16'),
(0, 2, 'Menambahkan Kendaraan s 6544 g', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-21 17:17:09'),
(0, 2, 'Catat Kendaraan Masuk s 6544 g (PRK-2026-00039)', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-21 17:17:10'),
(0, 2, 'Catat Kendaraan Keluar s 6544 g (PRK-2026-00039) - Bayar Tunai Rp 25.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-21 17:18:03'),
(0, 2, 'Catat Kendaraan Keluar aa 545 bc (PRK-2026-00008) - Bayar Tunai Rp 6.692.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-21 17:18:47'),
(0, 2, 'Catat Kendaraan Keluar AB 4433 JG (PRK-2023-001) - Bayar Tunai Rp 178.647.000', 'Manajemen Kendaraan', '89.105.200.65', '2026-09-21 17:19:23'),
(0, 2, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-21 17:19:48'),
(0, 5, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-21 17:25:29'),
(0, 5, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-21 17:34:02'),
(0, 1, 'Login Berhasil', 'Login/Logout', '89.105.200.65', '2026-09-21 17:34:10'),
(0, 1, 'Logout', 'Login/Logout', '89.105.200.65', '2026-09-21 17:35:48'),
(0, 1243, 'Registrasi Pelanggan Baru (hhhh)', 'Lainnya', '89.105.200.65', '2026-09-21 23:21:51');

-- --------------------------------------------------------

--
-- Table structure for table `okupansi_per_jam`
--

CREATE TABLE `okupansi_per_jam` (
  `id` int(11) NOT NULL,
  `jam_label` varchar(5) NOT NULL,
  `persentase` decimal(5,2) NOT NULL,
  `tanggal` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `okupansi_per_jam`
--

INSERT INTO `okupansi_per_jam` (`id`, `jam_label`, `persentase`, `tanggal`) VALUES
(1, '00:00', '20.00', '2026-08-11'),
(2, '03:00', '15.00', '2026-08-11'),
(3, '06:00', '45.00', '2026-08-11'),
(4, '09:00', '95.00', '2026-08-11'),
(5, '12:00', '88.00', '2026-08-11'),
(6, '15:00', '92.00', '2026-08-11'),
(7, '18:00', '70.00', '2026-08-11'),
(8, '21:00', '35.00', '2026-08-11');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `deskripsi` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nama_role`, `deskripsi`) VALUES
(1, 'Super Admin', 'Akses penuh ke semua modul, pengaturan sistem, dan laporan keuangan.'),
(2, 'Owner', 'Akses penuh ke semua modul, pengaturan sistem, dan laporan keuangan.'),
(3, 'Admin', 'Manajemen data kendaraan, lantai, area parkir, dan tarif.'),
(4, 'Officer', 'Akses operasional harian seperti input kendaraan dan validasi transaksi.'),
(5, 'Security', 'Akses gerbang masuk/keluar dan verifikasi kendaraan.'),
(6, 'User', 'User'),
(7, 'Pelanggan', '');

-- --------------------------------------------------------

--
-- Table structure for table `slot_parkir`
--

CREATE TABLE `slot_parkir` (
  `id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `kode_slot` varchar(10) NOT NULL,
  `status` enum('Tersedia','Terisi','Dipesan') NOT NULL DEFAULT 'Tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `slot_parkir`
--

INSERT INTO `slot_parkir` (`id`, `area_id`, `kode_slot`, `status`) VALUES
(1, 1, 'A-01', 'Tersedia'),
(2, 1, 'A-02', 'Terisi'),
(3, 1, 'A-03', 'Dipesan'),
(4, 1, 'A-04', 'Terisi'),
(5, 1, 'A-05', 'Tersedia'),
(6, 1, 'A-06', 'Terisi'),
(7, 1, 'A-07', 'Tersedia'),
(8, 1, 'A-08', 'Tersedia'),
(9, 1, 'A-09', 'Terisi'),
(10, 1, 'A-10', 'Terisi'),
(11, 1, 'A-11', 'Terisi'),
(12, 1, 'A-12', 'Terisi'),
(13, 2, 'B-01', 'Terisi'),
(14, 2, 'B-02', 'Tersedia'),
(15, 2, 'B-03', 'Terisi'),
(16, 2, 'B-04', 'Terisi'),
(17, 2, 'B-05', 'Tersedia'),
(18, 2, 'B-06', 'Tersedia'),
(19, 2, 'B-07', 'Terisi'),
(20, 2, 'B-08', 'Tersedia'),
(21, 2, 'B-09', 'Terisi'),
(22, 2, 'B-10', 'Tersedia'),
(23, 2, 'B-11', 'Tersedia'),
(24, 2, 'B-12', 'Terisi'),
(25, 3, 'C-01', 'Tersedia'),
(26, 3, 'C-02', 'Terisi'),
(27, 3, 'C-03', 'Tersedia'),
(28, 3, 'C-04', 'Tersedia'),
(29, 3, 'C-05', 'Tersedia'),
(30, 3, 'C-06', 'Tersedia'),
(31, 3, 'C-07', 'Tersedia'),
(32, 3, 'C-08', 'Tersedia'),
(33, 3, 'C-09', 'Terisi'),
(34, 3, 'C-10', 'Tersedia'),
(35, 3, 'C-11', 'Terisi'),
(36, 3, 'C-12', 'Tersedia'),
(37, 4, 'D-01', 'Terisi'),
(38, 4, 'D-02', 'Terisi'),
(39, 4, 'D-03', 'Terisi'),
(40, 4, 'D-04', 'Terisi'),
(41, 4, 'D-05', 'Dipesan'),
(42, 4, 'D-06', 'Terisi'),
(43, 4, 'D-07', 'Terisi'),
(44, 4, 'D-08', 'Terisi'),
(45, 4, 'D-09', 'Terisi'),
(46, 4, 'D-10', 'Terisi'),
(47, 4, 'D-11', 'Terisi'),
(48, 4, 'D-12', 'Terisi'),
(49, 5, 'E-01', 'Terisi'),
(50, 5, 'E-02', 'Tersedia'),
(51, 5, 'E-03', 'Tersedia'),
(52, 5, 'E-04', 'Terisi'),
(53, 5, 'E-05', 'Tersedia'),
(54, 5, 'E-06', 'Tersedia'),
(55, 5, 'E-07', 'Tersedia'),
(56, 5, 'E-08', 'Dipesan'),
(57, 5, 'E-09', 'Tersedia'),
(58, 5, 'E-10', 'Tersedia'),
(59, 5, 'E-11', 'Terisi'),
(60, 5, 'E-12', 'Tersedia');

-- --------------------------------------------------------

--
-- Table structure for table `tarif`
--

CREATE TABLE `tarif` (
  `id` int(11) NOT NULL,
  `tipe_kendaraan` varchar(50) NOT NULL,
  `deskripsi` varchar(150) DEFAULT NULL,
  `tarif_per_jam` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tarif_maks_harian` int(11) NOT NULL DEFAULT 0,
  `status` enum('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
  `updated_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tarif`
--

INSERT INTO `tarif` (`id`, `tipe_kendaraan`, `deskripsi`, `tarif_per_jam`, `tarif_maks_harian`, `status`, `updated_by_user_id`, `created_at`) VALUES
(1, 'Motor', 'Motor Roda Dua Standard', '3000.00', 0, 'Aktif', 6, '2026-08-11 08:45:46'),
(2, 'Mobil', 'Mobil Pribadi / Sedan / SUV', '7000.00', 0, 'Aktif', 6, '2026-08-11 08:45:46'),
(3, 'Bus/Truk', 'Kendaraan Besar & Logistik', '25000.00', 0, 'Aktif', 6, '2026-08-11 08:45:46'),
(7, 'Sepeda', 'Sepeda', '2000.00', 0, 'Aktif', NULL, '2026-09-15 20:30:15');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `kode_parkir` varchar(20) NOT NULL,
  `kendaraan_id` int(11) NOT NULL,
  `slot_id` int(11) DEFAULT NULL,
  `waktu_masuk` datetime NOT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `biaya` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Masuk','Keluar') NOT NULL DEFAULT 'Masuk',
  `petugas_id` int(11) DEFAULT NULL,
  `metode_bayar` varchar(30) DEFAULT 'Tunai',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_parkir`, `kendaraan_id`, `slot_id`, `waktu_masuk`, `waktu_keluar`, `biaya`, `status`, `petugas_id`, `metode_bayar`, `created_at`) VALUES
(1, 'PRK-2023-001', 1, 1, '2023-10-24 08:30:00', '2026-09-21 17:19:23', '178647000.00', 'Keluar', 11, 'Tunai', '2026-08-11 08:45:46'),
(2, 'PRK-2023-002', 2, NULL, '2023-10-24 07:15:00', '2023-10-24 10:20:00', '8000.00', 'Keluar', 12, 'Tunai', '2026-08-11 08:45:46'),
(3, 'PRK-2023-003', 3, 25, '2023-10-24 09:00:00', NULL, '10000.00', 'Masuk', 11, 'Tunai', '2026-08-11 08:45:46'),
(4, 'PRK-2023-004', 4, NULL, '2023-10-24 06:45:00', '2023-10-24 08:50:00', '0.00', 'Keluar', 12, 'Tunai', '2026-08-11 08:45:46'),
(5, 'PRK-2026-00005', 6, 2, '2026-08-12 21:05:06', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-08-12 21:05:06'),
(6, 'PRK-2026-00006', 7, 5, '2026-08-12 21:27:44', '2026-09-17 21:04:46', '21600000.00', 'Keluar', 2, 'Tunai', '2026-08-12 21:27:44'),
(7, 'PRK-2026-00007', 8, 7, '2026-08-12 21:52:06', '2026-09-17 21:01:53', '21600000.00', 'Keluar', 2, 'QRIS', '2026-08-12 21:52:06'),
(8, 'PRK-2026-00008', 9, 8, '2026-08-12 22:05:44', '2026-09-21 17:18:47', '6692000.00', 'Keluar', 2, 'Tunai', '2026-08-12 22:05:44'),
(9, 'PRK-2026-00009', 10, 11, '2026-08-13 07:12:46', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-08-13 07:12:46'),
(10, 'PRK-2026-00010', 11, 12, '2026-08-13 13:21:00', '2026-08-13 13:21:23', '7000.00', 'Keluar', 2, 'QRIS', '2026-08-13 13:21:00'),
(11, 'PRK-2026-00011', 12, 12, '2026-08-13 13:23:40', '2026-08-13 13:23:44', '3000.00', 'Keluar', 2, 'Tunai', '2026-08-13 13:23:40'),
(12, 'PRK-2026-00012', 13, 12, '2026-08-20 08:07:57', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-08-20 08:07:57'),
(13, 'PRK-2026-00013', 22, 14, '2026-09-07 23:53:43', '2026-09-15 21:27:55', '612000.00', 'Keluar', 2, 'Tunai', '2026-09-07 23:53:43'),
(14, 'PRK-2026-00014', 28, 15, '2026-09-14 18:31:25', '2026-09-14 18:31:51', '42000.00', 'Keluar', 2, 'Tunai', '2026-09-14 18:31:25'),
(15, 'PRK-2026-00015', 29, 15, '2026-09-15 18:27:22', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-09-15 18:27:22'),
(16, 'PRK-2026-00016', 30, 16, '2026-09-15 18:47:58', '2026-09-15 21:28:28', '51000.00', 'Keluar', 2, 'Tunai', '2026-09-15 18:47:58'),
(17, 'PRK-2026-00017', 31, 17, '2026-09-15 19:00:56', '2026-09-15 19:02:09', '45000.00', 'Keluar', 2, 'Tunai', '2026-09-15 19:00:56'),
(18, 'PRK-2026-00018', 32, 17, '2026-09-15 19:14:10', '2026-09-15 19:15:18', '45000.00', 'Keluar', 2, 'Tunai', '2026-09-15 19:14:10'),
(19, 'PRK-2026-00019', 33, 17, '2026-09-15 20:24:04', '2026-09-15 20:24:33', '98000.00', 'Keluar', 2, 'Tunai', '2026-09-15 20:24:04'),
(20, 'PRK-2026-00020', 34, 17, '2026-09-15 20:27:16', '2026-09-15 20:27:46', '350000.00', 'Keluar', 2, 'Tunai', '2026-09-15 20:27:16'),
(21, 'PRK-2026-00021', 35, 17, '2026-09-15 20:56:41', '2026-09-15 21:29:11', '45000.00', 'Keluar', 2, 'Tunai', '2026-09-15 20:56:41'),
(22, 'PRK-2026-00022', 36, 18, '2026-09-15 21:15:21', '2026-09-15 21:28:49', '375000.00', 'Keluar', 2, 'Tunai', '2026-09-15 21:15:21'),
(23, 'PRK-2026-00023', 37, 14, '2026-09-15 23:33:50', '2026-09-15 23:34:39', '42000.00', 'Keluar', 2, 'Tunai', '2026-09-15 23:33:50'),
(24, 'PRK-2026-00024', 38, 14, '2026-09-16 07:03:26', '2026-09-16 07:03:42', '98000.00', 'Keluar', 2, 'QRIS', '2026-09-16 07:03:26'),
(25, 'PRK-2026-00025', 40, 14, '2026-09-17 00:37:58', '2026-09-17 01:59:38', '0.00', 'Keluar', 2, 'Tunai', '2026-09-17 00:37:58'),
(26, 'PRK-2026-00026', 41, 16, '2026-09-17 01:54:19', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-09-17 01:54:19'),
(27, 'PRK-2026-00027', 42, 17, '2026-09-17 01:56:55', '2026-09-17 01:58:58', '0.00', 'Keluar', 2, 'Tunai', '2026-09-17 01:56:55'),
(28, 'PRK-2026-00028', 44, 18, '2026-09-17 01:58:05', '2026-09-17 01:58:10', '0.00', 'Keluar', 2, 'Tunai', '2026-09-17 01:58:05'),
(29, 'PRK-2026-00029', 45, 14, '2026-09-17 05:03:54', '2026-09-17 05:05:00', '0.00', 'Keluar', 2, 'Tunai', '2026-09-17 05:03:54'),
(30, '', 39, 24, '2026-09-17 19:03:28', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-09-17 19:03:28'),
(31, '', 39, 21, '2026-09-17 19:17:47', NULL, '0.00', 'Masuk', 2, 'Tunai', '2026-09-17 19:17:47'),
(32, 'PRK-2026-00032', 47, 14, '2026-09-17 19:39:47', '2026-09-20 18:37:40', '1775000.00', 'Keluar', 2, 'Tunai', '2026-09-17 19:39:47'),
(33, 'PRK-2026-00033', 48, 17, '2026-09-17 19:46:32', '2026-09-17 19:59:46', '45000.00', 'Keluar', 2, 'Tunai', '2026-09-17 19:46:32'),
(34, 'PRK-2026-00034', 49, 17, '2026-09-17 20:00:22', '2026-09-17 20:00:42', '42000.00', 'Keluar', 2, 'Tunai', '2026-09-17 20:00:22'),
(35, 'PRK-2026-00035', 50, 17, '2026-09-17 20:26:57', '2026-09-17 20:27:22', '42000.00', 'Keluar', 2, 'Tunai', '2026-09-17 20:26:57'),
(36, 'PRK-2026-00036', 51, 17, '2026-09-17 20:35:19', '2026-09-17 20:35:30', '7000.00', 'Keluar', 2, 'Tunai', '2026-09-17 20:35:19'),
(37, '', 39, 18, '2026-09-17 20:37:16', '2026-09-17 20:38:21', '3000.00', 'Keluar', 2, 'Tunai', '2026-09-17 20:37:16'),
(38, 'PRK-2026-00038', 53, 5, '2026-09-20 18:38:38', '2026-09-20 18:39:16', '3000.00', 'Keluar', 2, 'QRIS', '2026-09-20 18:38:38'),
(39, 'PRK-2026-00039', 54, 5, '2026-09-21 17:17:10', '2026-09-21 17:18:03', '25000.00', 'Keluar', 2, 'Tunai', '2026-09-21 17:17:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `email`, `no_hp`, `password_hash`, `role_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'wahyu', 'owner', 'wahyu@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 2, 'Aktif', '2026-08-11 08:45:46', '2026-08-12 10:37:20'),
(2, 'muhammad', 'petugas', 'muhammad@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 4, 'Aktif', '2026-08-11 08:45:46', '2026-08-12 22:12:35'),
(3, 'Dedi Kusuma', 'dedi_admin', 'dedi.k@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Non-Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(4, 'Farah Nadya', 'farah_n', 'farah.n@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 4, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(5, 'imanuar', 'admin', 'imanuar@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif', '2026-08-11 08:45:46', '2026-08-12 22:16:15'),
(6, 'Andri Setiawan', 'andri.s', 'andri.s@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(7, 'Budi Kusuma', 'budi.k', 'budi.k@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 6, 'Aktif', '2026-08-11 08:45:46', '2026-08-13 21:44:57'),
(8, 'Rina Marlina', 'rina.m', 'rina.m@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(9, 'Dedi Hermawan', 'dedi.h', 'dedi.h@pertamina.com', '0', '$2y$10$JPf2zNmBd3xHNhcuzKWciu4kVYBTYA8l3xPBhGew8rJ/DgYHfbVqC', 1, 'Aktif', '2026-08-11 08:45:46', '2026-09-14 18:35:45'),
(10, 'Aditya Pratama', 'aditya_admin', 'aditya.p@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 3, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(11, 'Ahmad S.', 'ahmad.s', 'ahmad.s@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 5, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(12, 'Siska D.', 'siska.d', 'siska.d@pertamina.com', '0', '$2y$10$szBRKWcWd9GpHO41Zvarju5dfUzHxg1mAHkwEnVWlCTSBa6HLWUxO', 5, 'Aktif', '2026-08-11 08:45:46', '2026-08-11 08:45:46'),
(13, 'syafix', 'syafiximanuar', 'syafix@pertamina.com', '0', '$2y$10$FtFxnO3zB9iTPUxa4CodJeKj2GytXX/buMOwY/u3/AEBqteHHv0XC', 4, 'Non-Aktif', '2026-08-12 12:34:50', '2026-08-12 12:34:50'),
(1234, 'User', 'user', 'user@pertamina.com', '0', 'password123', 6, 'Aktif', '2026-08-13 21:44:04', '2026-08-13 21:44:04'),
(1235, 'Budhi', 'ytta', 'ytta@gmail.com', '0', '$2y$10$SDYqr.Jtm8HZ.0JcR06R0OCLCHtAV5eEpMaWBKmTvTEzVQ62abZu6', 2, 'Aktif', '2026-09-07 20:36:24', '2026-09-07 20:36:24'),
(1236, 'Budhiq', 'ytta3', 'ytta3@gmail.com', '000299928', '$2y$10$dh/prNBAR9ocRjS67ILYtuw7g4w3DYeZbfagJRVdc/EG0XEgZlJlS', 5, 'Aktif', '2026-09-07 20:46:20', '2026-09-07 20:46:20'),
(1237, 'Artii', 'ytta4', 'ytta4@gmail.com', '123456789', '$2y$10$S7zqopnQI309XkIOylDlNO5pSBlRK6q8vpsK3EodjrzTgp04AZyZ.', 1, 'Aktif', '2026-09-13 22:00:11', '2026-09-14 18:35:19'),
(1238, 'muhamadsyafix', 'syafix', 'syafix@gmail.com', '', '$2y$10$pDTcIAKo2Fr4SIym1BENnuwwv7YW8PQTaNM6h377PdZ4CcqQ9PWU.', 7, 'Aktif', '2026-09-16 18:07:14', '2026-09-16 18:07:14'),
(1239, 'muhamadsyafixx', 'syafixx', 'syafixx@gmail.com', '', '$2y$10$Y8kCqJk6xMq1BEtEXUUfB.R6tq2rCZE4Npi4MSjlweCKyLpp6BgNm', 7, 'Aktif', '2026-09-16 18:12:12', '2026-09-16 18:12:12'),
(1240, 'muhamadsyafixxx', 'syafixxx', 'syafixxx@gmail.com', '', '$2y$10$kiyAhF2xiyqH9TnEQ/AVgOs8quDjopGZH7b7NlHUY3LlfRgd8WFqi', 7, 'Aktif', '2026-09-16 18:22:32', '2026-09-16 18:22:32'),
(1241, 'wahyu', 'wahyu', 'wahyu@gmail.com', '', '$2y$10$Dhg89tvBchSFX7bBlV2cI.6Zo3cPRpDrbzGO4sx7haz8IHtpnCpf6', 7, 'Aktif', '2026-09-17 07:08:44', '2026-09-17 07:08:44'),
(1242, 'jesslyn senja ayuning tyas', 'senja', 'nipapinipa@gmail.com', '', '$2y$10$WRHtArpLmmoPYVb4ChgAX.F5n1ZTYybmPmWx575juH8fOsPVy5CE.', 7, 'Aktif', '2026-09-20 05:16:56', '2026-09-20 05:16:56'),
(1243, 'hhh', 'hhhh', 'h@gmail.com', '', '$2y$10$.rha8kHXeZiLpc/B4yTI1OoII2IlN9Pw/8hYNJIK1.lWdumczW.Dq', 7, 'Aktif', '2026-09-21 23:21:51', '2026-09-21 23:21:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `area`
--
ALTER TABLE `area`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lantai_id` (`lantai_id`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_booking` (`kode_booking`),
  ADD KEY `fk_booking_slot` (`slot_id`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `idx_booking_user` (`user_id`);

--
-- Indexes for table `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_kendaraan` (`kode_kendaraan`),
  ADD UNIQUE KEY `plat_nomor` (`plat_nomor`),
  ADD KEY `terdaftar_oleh_user_id` (`terdaftar_oleh_user_id`),
  ADD KEY `idx_kendaraan_plat` (`plat_nomor`);

--
-- Indexes for table `komentar`
--
ALTER TABLE `komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_id` (`status`,`id`);

--
-- Indexes for table `lantai`
--
ALTER TABLE `lantai`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `okupansi_per_jam`
--
ALTER TABLE `okupansi_per_jam`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `slot_parkir`
--
ALTER TABLE `slot_parkir`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tarif`
--
ALTER TABLE `tarif`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `area`
--
ALTER TABLE `area`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `komentar`
--
ALTER TABLE `komentar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lantai`
--
ALTER TABLE `lantai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `okupansi_per_jam`
--
ALTER TABLE `okupansi_per_jam`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `slot_parkir`
--
ALTER TABLE `slot_parkir`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `tarif`
--
ALTER TABLE `tarif`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1244;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `fk_booking_slot` FOREIGN KEY (`slot_id`) REFERENCES `slot_parkir` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
