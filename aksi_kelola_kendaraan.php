<?php
/**
 * aksi_kelola_kendaraan.php
 * Endpoint AJAX untuk aksi Kelola Kendaraan: tambah, ubah, hapus.
 * Untuk aksi verifikasi/tolak, gunakan aksi_verifikasi_kendaraan.php yang sudah ada.
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

function validasi_plat_nomor(string $plat): bool
{
    return (bool) preg_match('/^[A-Z0-9\s]{4,12}$/', $plat);
}

try {
    switch ($aksi) {
        case 'tambah':
            $plat  = strtoupper(trim($input['plat_nomor'] ?? ''));
            $tipe  = trim($input['tipe'] ?? '');
            $nama  = trim($input['nama_pemilik'] ?? '');
            $hp    = trim($input['no_hp'] ?? '');

            if ($plat === '' || $nama === '' || !validasi_plat_nomor($plat)) {
                throw new InvalidArgumentException('Plat nomor dan nama pemilik wajib diisi dengan format yang benar.');
            }

            $duplikat = db_fetch_one(
                "SELECT COUNT(*) AS jml FROM kendaraan WHERE plat_nomor = ?",
                [$plat], ['jml' => 0]
            );
            if ((int) ($duplikat['jml'] ?? 0) > 0) {
                throw new InvalidArgumentException('Plat nomor tersebut sudah terdaftar.');
            }

            $kodeKendaraan = generate_kode_kendaraan();

            db_execute(
                "INSERT INTO kendaraan (kode_kendaraan, plat_nomor, tipe, nama_pemilik, no_hp, sumber_registrasi, status_verifikasi, created_at)
                 VALUES (?, ?, ?, ?, ?, 'Input Admin', 'Terverifikasi', NOW())",
                [$kodeKendaraan, $plat, $tipe, $nama, $hp]
            );
            echo json_encode(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan.']);
            break;

        case 'ubah':
            $id    = (int) ($input['id'] ?? 0);
            $plat  = strtoupper(trim($input['plat_nomor'] ?? ''));
            $tipe  = trim($input['tipe'] ?? '');
            $nama  = trim($input['nama_pemilik'] ?? '');
            $hp    = trim($input['no_hp'] ?? '');

            if ($id <= 0 || $plat === '' || $nama === '' || !validasi_plat_nomor($plat)) {
                throw new InvalidArgumentException('Data kendaraan tidak lengkap atau tidak valid.');
            }

            db_execute(
                "UPDATE kendaraan SET plat_nomor = ?, tipe = ?, nama_pemilik = ?, no_hp = ? WHERE id = ?",
                [$plat, $tipe, $nama, $hp, $id]
            );
            echo json_encode(['success' => true, 'message' => 'Data kendaraan berhasil diperbarui.']);
            break;

        case 'hapus':
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('Kendaraan tidak ditemukan.');
            }

            $sedangParkir = db_fetch_one(
                "SELECT COUNT(*) AS jml FROM kendaraan_terparkir WHERE kendaraan_id = ?",
                [$id], ['jml' => 0]
            );
            if ((int) ($sedangParkir['jml'] ?? 0) > 0) {
                throw new InvalidArgumentException('Kendaraan sedang terparkir, tidak dapat dihapus.');
            }

            db_execute("DELETE FROM kendaraan WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Kendaraan berhasil dihapus.']);
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