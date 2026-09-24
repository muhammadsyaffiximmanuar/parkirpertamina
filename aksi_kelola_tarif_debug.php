<?php
/**
 * aksi_kelola_tarif.php (VERSI DEBUG SEMENTARA)
 * Bedanya dari versi asli: pesan error asli ($e->getMessage()) ikut
 * dikirim ke response supaya kelihatan penyebabnya. 
 * GANTI KEMBALI ke pesan generik setelah masalah ketemu — jangan
 * dipakai di production dalam waktu lama karena bisa membocorkan
 * detail struktur database ke pengguna.
 */
session_start();
require_once 'config.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid, silakan login ulang.']);
    exit;
}
require_role([ROLE_OWNER, ROLE_SUPER_ADMIN, ROLE_ADMIN]);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}
$aksi = $input['aksi'] ?? '';
try {
    switch ($aksi) {
        case 'tambah':
            $tipe       = trim($input['tipe_kendaraan'] ?? '');
            $deskripsi  = trim($input['deskripsi'] ?? '');
            $perJam     = (int) ($input['tarif_per_jam'] ?? 0);
            $maksHarian = (int) ($input['tarif_maks_harian'] ?? 0);
            $status     = in_array($input['status'] ?? '', ['Aktif', 'Nonaktif'], true) ? $input['status'] : 'Aktif';
            if ($tipe === '' || $perJam <= 0) {
                throw new InvalidArgumentException('Tipe kendaraan dan tarif per jam (harus lebih dari 0) wajib diisi.');
            }
            db_execute(
                "INSERT INTO tarif (tipe_kendaraan, deskripsi, tarif_per_jam, tarif_maks_harian, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$tipe, $deskripsi, $perJam, $maksHarian, $status]
            );
            echo json_encode(['success' => true, 'message' => 'Tarif berhasil ditambahkan.']);
            break;
        case 'ubah':
            $id         = (int) ($input['id'] ?? 0);
            $tipe       = trim($input['tipe_kendaraan'] ?? '');
            $deskripsi  = trim($input['deskripsi'] ?? '');
            $perJam     = (int) ($input['tarif_per_jam'] ?? 0);
            $maksHarian = (int) ($input['tarif_maks_harian'] ?? 0);
            $status     = in_array($input['status'] ?? '', ['Aktif', 'Nonaktif'], true) ? $input['status'] : 'Aktif';
            if ($id <= 0 || $tipe === '' || $perJam <= 0) {
                throw new InvalidArgumentException('Data tarif tidak lengkap.');
            }
            db_execute(
                "UPDATE tarif SET tipe_kendaraan = ?, deskripsi = ?, tarif_per_jam = ?, tarif_maks_harian = ?, status = ? WHERE id = ?",
                [$tipe, $deskripsi, $perJam, $maksHarian, $status, $id]
            );
            echo json_encode(['success' => true, 'message' => 'Tarif berhasil diperbarui.']);
            break;
        case 'hapus':
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Tarif tidak ditemukan.');
            }
            db_execute("DELETE FROM tarif WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Tarif berhasil dihapus.']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    // DEBUG: pesan error asli ditampilkan sementara.
    echo json_encode([
        'success' => false,
        'message' => 'DEBUG: ' . $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine(),
    ]);
}