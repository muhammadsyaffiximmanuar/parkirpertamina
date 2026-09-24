<?php
/**
 * area.php (DEPRECATED)
 *
 * File ini adalah versi awal/duplikat dari aktivitas.php: memakai
 * koneksi mysqli terpisah (koneksi.php) dan query ke tabel
 * `activity_logs` dengan kolom (nama_user, email_user, status,
 * created_at) yang TIDAK PERNAH ADA di skema database
 * (schema_parkir_pertamina.sql hanya punya tabel `log_aktivitas`).
 * Karena area.php memakai die() saat query gagal, mengakses file ini
 * akan menyebabkan fatal error / halaman putih di server manapun.
 *
 * aktivitas.php sudah mencakup seluruh fungsi halaman ini (dengan
 * skema yang benar, filter, dan pagination), jadi file ini sekarang
 * hanya redirect ke sana agar tidak ada tautan lama yang error.
 */
header('Location: aktivitas.php');
exit;
