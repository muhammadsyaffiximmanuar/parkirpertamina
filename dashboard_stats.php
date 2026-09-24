<?php
/**
 * Endpoint: GET /dashboard_stats.php
 * Sumber data untuk kartu statistik & grafik okupansi di dasbordutama.html
 */
session_start();
require_once 'config.php';

require_login();
if (!$pdo) {
    json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
}

$stats     = $pdo->query('SELECT * FROM view_dashboard_stats')->fetch();
$okupansi  = $pdo->query('SELECT * FROM view_okupansi_lantai')->fetchAll();
$aktivitas = $pdo->query(
    'SELECT nama_lengkap, aktivitas, kategori, created_at
     FROM view_log_aktivitas_detail
     LIMIT 5'
)->fetchAll();

json_response([
    'success'           => true,
    'stats'             => $stats,
    'okupansi_lantai'   => $okupansi,
    'aktivitas_terbaru' => $aktivitas,
]);
