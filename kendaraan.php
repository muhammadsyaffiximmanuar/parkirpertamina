<?php
/**
 * Endpoint: /kendaraan.php
 *   GET    -> daftar kendaraan (mendukung ?search=plat)
 *   POST   -> tambah kendaraan baru
 *   DELETE -> hapus kendaraan (?id=)
 * Dipakai oleh halaman manajemen-1.html (Manajemen Kendaraan)
 */
session_start();
require_once 'config.php';

$session = require_login();
if (!$pdo) {
    json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM kendaraan WHERE plat_nomor LIKE :s OR nama_pemilik LIKE :s ORDER BY id DESC'
            );
            $stmt->execute(['s' => "%$search%"]);
        } else {
            $stmt = $pdo->query('SELECT * FROM kendaraan ORDER BY id DESC');
        }
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $required = ['plat_nomor', 'tipe', 'nama_pemilik'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                json_response(['success' => false, 'message' => "Field '$field' wajib diisi."], 422);
            }
        }

        // Generate kode_kendaraan otomatis: VHC-2023-006, dst.
        $count = (int) $pdo->query('SELECT COUNT(*) c FROM kendaraan')->fetch()['c'];
        $kode = sprintf('VHC-%s-%03d', date('Y'), $count + 1);

        $stmt = $pdo->prepare(
            'INSERT INTO kendaraan
                (kode_kendaraan, plat_nomor, tipe, warna, nama_pemilik, terdaftar_oleh_user_id, sumber_registrasi, status_verifikasi)
             VALUES
                (:kode, :plat, :tipe, :warna, :pemilik, :user_id, :sumber, :verifikasi)'
        );
        $stmt->execute([
            'kode'       => $kode,
            'plat'       => $input['plat_nomor'],
            'tipe'       => $input['tipe'],
            'warna'      => $input['warna'] ?? null,
            'pemilik'    => $input['nama_pemilik'],
            'user_id'    => $session['user_id'],
            'sumber'     => 'Admin',
            'verifikasi' => 'Menunggu Verifikasi',
        ]);

        $newId = $pdo->lastInsertId();
        catat_log($session['user_id'], "Menambahkan Kendaraan {$input['plat_nomor']}", 'Manajemen Kendaraan');

        json_response(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan.', 'id' => $newId, 'kode_kendaraan' => $kode], 201);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'ID kendaraan tidak valid.'], 422);
        }

        $find = $pdo->prepare('SELECT plat_nomor FROM kendaraan WHERE id = :id');
        $find->execute(['id' => $id]);
        $vehicle = $find->fetch();
        if (!$vehicle) {
            json_response(['success' => false, 'message' => 'Kendaraan tidak ditemukan.'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM kendaraan WHERE id = :id');
        $stmt->execute(['id' => $id]);

        catat_log($session['user_id'], "Menghapus Data Kendaraan {$vehicle['plat_nomor']}", 'Manajemen Kendaraan');

        json_response(['success' => true, 'message' => 'Kendaraan berhasil dihapus.']);
        break;

    default:
        json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}
