<?php
/**
 * koneksi.php (DEPRECATED)
 *
 * File ini sebelumnya membuka koneksi mysqli terpisah, sementara
 * seluruh aplikasi lain memakai PDO via config.php. Dua jalur koneksi
 * berbeda menyebabkan inkonsistensi (lihat area.php lama). Sekarang
 * file ini hanya memuat config.php agar variabel $pdo tetap tersedia
 * bagi kode lama yang mungkin masih meng-include koneksi.php.
 *
 * Jangan pakai file ini untuk kode baru — gunakan:
 *   require_once 'config.php';
 * dan variabel global $pdo (PDO), atau db_fetch_all()/db_fetch_one().
 */
require_once __DIR__ . '/config.php';
