<?php
/**
 * Endpoint: POST /aksi_booking.php
 * Aksi booking slot parkir self-service untuk role Pelanggan/User.
 * Menggunakan skema asli: slot_parkir(id, area_id, kode_slot, status)
 * dan booking(id, slot_id, user_id, plat_nomor, nama_pemesan,
 * waktu_booking, status). Satu booking = satu slot + satu waktu.
 *
 * Body params:
 *   aksi = 'buat_booking'      -> kendaraan_id, slot_id, waktu_booking
 *   aksi = 'batalkan_booking'  -> booking_id
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$session = require_login();

// Hanya Pelanggan & User (self-service pemilik kendaraan) yang boleh booking sendiri.
if (!in_array($session['role'], [ROLE_PELANGGAN, ROLE_USER], true)) {
    json_response(['success' => false, 'message' => 'Anda tidak memiliki akses untuk melakukan booking.'], 403);
}

$aksi = $_POST['aksi'] ?? '';

try {
    global $pdo;
    if (!$pdo) {
        json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
    }

    if ($aksi === 'buat_booking') {

        expire_booking_lewat_waktu(); // pastikan slot yang kedaluwarsa sudah dibebaskan sebelum dicek

        $kendaraanId  = (int) ($_POST['kendaraan_id'] ?? 0);
        $slotId       = (int) ($_POST['slot_id'] ?? 0);
        $waktuBooking = trim($_POST['waktu_booking'] ?? '');

        if (!$kendaraanId || !$slotId || $waktuBooking === '') {
            json_response(['success' => false, 'message' => 'Semua field booking wajib diisi.'], 422);
        }

        $waktuTs = strtotime($waktuBooking);
        if (!$waktuTs) {
            json_response(['success' => false, 'message' => 'Waktu booking tidak valid.'], 422);
        }
        if ($waktuTs < time() - 60) {
            json_response(['success' => false, 'message' => 'Waktu booking tidak boleh di masa lalu.'], 422);
        }
        $waktuSql = date('Y-m-d H:i:s', $waktuTs);

        // Pastikan kendaraan ini benar milik user yang sedang login, ambil plat nomornya.
        $kendaraan = db_fetch_one(
            'SELECT id, plat_nomor FROM kendaraan WHERE id = ? AND terdaftar_oleh_user_id = ?',
            [$kendaraanId, $session['user_id']],
            null
        );
        if (!$kendaraan) {
            json_response(['success' => false, 'message' => 'Kendaraan tidak ditemukan atau bukan milik Anda.'], 404);
        }

        // Pastikan slot masih berstatus Tersedia.
        $slot = db_fetch_one(
            "SELECT id, status FROM slot_parkir WHERE id = ?",
            [$slotId],
            null
        );
        if (!$slot || $slot['status'] !== 'Tersedia') {
            json_response(['success' => false, 'message' => 'Slot yang dipilih sudah tidak tersedia. Silakan pilih slot lain.'], 409);
        }

        // Kunci slot ini dulu (cegah dua orang booking slot yang sama bersamaan).
        $terkunci = db_execute(
            "UPDATE slot_parkir SET status = 'Dipesan' WHERE id = ? AND status = 'Tersedia'",
            [$slotId]
        );
        if ($terkunci === 0) {
            json_response(['success' => false, 'message' => 'Slot baru saja dipesan orang lain. Silakan pilih slot lain.'], 409);
        }

        $kodeBooking = generate_kode_booking();

        db_execute(
            'INSERT INTO booking (slot_id, kode_booking, user_id, plat_nomor, nama_pemesan, waktu_booking, status)
             VALUES (:slot_id, :kode_booking, :user_id, :plat_nomor, :nama_pemesan, :waktu_booking, :status)',
            [
                'slot_id'       => $slotId,
                'kode_booking'  => $kodeBooking,
                'user_id'       => $session['user_id'],
                'plat_nomor'    => $kendaraan['plat_nomor'],
                'nama_pemesan'  => $session['nama_lengkap'],
                'waktu_booking' => $waktuSql,
                'status'        => 'aktif',
            ]
        );

        catat_log($session['user_id'], "Booking slot #{$slotId} berhasil dibuat (kode: {$kodeBooking})", 'Booking');

        json_response(['success' => true, 'message' => "Booking berhasil dibuat. Kode booking Anda: {$kodeBooking} — tunjukkan ke petugas saat tiba.", 'kode_booking' => $kodeBooking]);

    } elseif ($aksi === 'batalkan_booking') {

        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        if (!$bookingId) {
            json_response(['success' => false, 'message' => 'Booking tidak ditemukan.'], 422);
        }

        $booking = db_fetch_one(
            'SELECT id, slot_id, status FROM booking WHERE id = ? AND user_id = ?',
            [$bookingId, $session['user_id']],
            null
        );
        if (!$booking) {
            json_response(['success' => false, 'message' => 'Booking tidak ditemukan atau bukan milik Anda.'], 404);
        }
        if ($booking['status'] !== 'aktif') {
            json_response(['success' => false, 'message' => 'Booking ini sudah tidak aktif.'], 409);
        }

        db_execute("UPDATE booking SET status = 'dibatalkan' WHERE id = ?", [$booking['id']]);
        db_execute("UPDATE slot_parkir SET status = 'Tersedia' WHERE id = ? AND status = 'Dipesan'", [$booking['slot_id']]);

        catat_log($session['user_id'], "Booking #{$booking['id']} dibatalkan", 'Booking');

        json_response(['success' => true, 'message' => 'Booking berhasil dibatalkan.']);

    } else {
        json_response(['success' => false, 'message' => 'Aksi tidak dikenali.'], 400);
    }

} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
}