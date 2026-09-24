<?php
/**
 * aksi_kelola_area.php
 * Endpoint AJAX untuk aksi Kelola Area: tambah, ubah, hapus.
 * Menggunakan tabel `lantai` (bukan `area_parkir` yang tidak ada).
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
            $nama       = trim($input['nama_lantai'] ?? '');
            $kapasitas  = (int) ($input['kapasitas'] ?? 0);
            $keterangan = trim($input['keterangan'] ?? '');
            $status     = in_array($input['status'] ?? '', ['Aktif', 'Nonaktif'], true) ? $input['status'] : 'Aktif';
            if ($nama === '' || $kapasitas <= 0) {
                throw new InvalidArgumentException('Nama area dan kapasitas (harus lebih dari 0) wajib diisi.');
            }
            $duplikat = db_fetch_one(
                "SELECT COUNT(*) AS jml FROM lantai WHERE nama_lantai = ?",
                [$nama], ['jml' => 0]
            );
            if ((int) ($duplikat['jml'] ?? 0) > 0) {
                throw new InvalidArgumentException('Nama area/lantai tersebut sudah terdaftar.');
            }
            db_execute(
                "INSERT INTO lantai (nama_lantai, kapasitas, keterangan, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$nama, $kapasitas, $keterangan, $status]
            );
            echo json_encode(['success' => true, 'message' => 'Area berhasil ditambahkan.']);
            break;
        case 'ubah':
            $id         = (int) ($input['id'] ?? 0);
            $nama       = trim($input['nama_lantai'] ?? '');
            $kapasitas  = (int) ($input['kapasitas'] ?? 0);
            $keterangan = trim($input['keterangan'] ?? '');
            $status     = in_array($input['status'] ?? '', ['Aktif', 'Nonaktif'], true) ? $input['status'] : 'Aktif';
            if ($id <= 0 || $nama === '' || $kapasitas <= 0) {
                throw new InvalidArgumentException('Data area tidak lengkap.');
            }
            db_execute(
                "UPDATE lantai SET nama_lantai = ?, kapasitas = ?, keterangan = ?, status = ? WHERE id = ?",
                [$nama, $kapasitas, $keterangan, $status, $id]
            );
            echo json_encode(['success' => true, 'message' => 'Data area berhasil diperbarui.']);
            break;
        case 'hapus':
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Area tidak ditemukan.');
            }
            db_execute("DELETE FROM lantai WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Area berhasil dihapus.']);
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
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
}