<?php
/**
 * Endpoint: POST /aksi_tandai_kedatangan.php
 * Dipanggil dari dashboard_petugas.php — Officer/Security menandai bahwa
 * kendaraan dengan booking aktif sudah benar-benar datang & masuk area
 * parkir. Booking jadi 'selesai', slot_parkir terkait jadi 'Terisi'.
 *
 * Body params:
 *   booking_id
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$session = require_login();

if (!in_array($session['role'], [ROLE_OFFICER, ROLE_SECURITY], true)) {
    json_response(['success' => false, 'message' => 'Anda tidak memiliki akses untuk aksi ini.'], 403);
}

$bookingId   = (int) ($_POST['booking_id'] ?? 0);
$kodeBooking = trim($_POST['kode_booking'] ?? '');

if (!$bookingId) {
    json_response(['success' => false, 'message' => 'Booking tidak ditemukan.'], 422);
}
if ($kodeBooking === '') {
    json_response(['success' => false, 'message' => 'Kode booking wajib diisi untuk verifikasi.'], 422);
}

try {
    global $pdo;
    if (!$pdo) {
        json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
    }

    $hasil = proses_kedatangan_booking($bookingId, $kodeBooking, (int) $session['user_id']);

    if (!$hasil['success']) {
        json_response(['success' => false, 'message' => $hasil['message']], 409);
    }

    catat_log($session['user_id'], "Booking #{$bookingId} ditandai sudah datang, transaksi #{$hasil['transaksi_id']} dibuat", 'Booking');

    json_response(['success' => true, 'message' => $hasil['message'], 'transaksi_id' => $hasil['transaksi_id']]);

} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
}