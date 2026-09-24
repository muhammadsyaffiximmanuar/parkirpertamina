<?php
/**
 * aksi_verifikasi_kendaraan.php
 * Endpoint AJAX (dipanggil via fetch dari dashboard_admin.php) untuk
 * menyetujui atau menolak kendaraan yang berstatus "Menunggu Verifikasi".
 *
 * CATATAN PENTING:
 * File ini ditulis mengikuti pola yang sudah dipakai di dashboard_admin.php
 * (db_fetch_one / db_fetch_all). Karena isi config.php tidak tersedia saat
 * file ini dibuat, bagian eksekusi query (ditandai TODO) perlu disesuaikan
 * dengan fungsi/driver database yang sebenarnya dipakai di config.php Anda
 * (mis. PDO langsung, mysqli, atau helper db_execute()/db_query() jika ada).
 */

session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_ADMIN]);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$id     = $input['id']     ?? null;
$aksi   = $input['aksi']   ?? null; // 'setujui' | 'tolak'
$alasan = $input['alasan'] ?? null;
$token  = $input['csrf_token'] ?? '';

// Validasi CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid, muat ulang halaman.']);
    exit;
}

// Validasi input dasar
if (!$id || !in_array($aksi, ['setujui', 'tolak'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
    exit;
}

if ($aksi === 'tolak' && empty(trim((string) $alasan))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Alasan penolakan wajib diisi.']);
    exit;
}

/**
 * Menjalankan query UPDATE dengan mendeteksi otomatis koneksi database
 * yang sudah dibuat oleh config.php (mendukung pola paling umum: PDO
 * ($pdo/$db/$conn) dan mysqli ($mysqli/$conn/$db/mysqli_connect global)).
 * Kalau config.php sudah punya helper db_execute()/db_query(), itu yang
 * dipakai duluan.
 *
 * @return int Jumlah baris yang ter-update.
 */
function jalankan_update_verifikasi(string $sql, array $paramsPositional, string $sqlNamed, array $paramsNamed): int
{
    // 1) Jika config.php sudah menyediakan helper sendiri, pakai itu.
    foreach (['db_execute', 'db_query', 'execute_query'] as $fn) {
        if (function_exists($fn)) {
            $result = $fn($sql, $paramsPositional);
            if (is_int($result)) {
                return $result;
            }
            if ($result instanceof PDOStatement) {
                return $result->rowCount();
            }
            // Anggap berhasil kalau helper tidak melempar exception dan tidak null/false
            return $result === false ? 0 : 1;
        }
    }

    // 2) Cari koneksi PDO yang sudah dibuat config.php (nama variabel umum).
    foreach (['pdo', 'db', 'conn', 'pdo_conn'] as $varName) {
        if (isset($GLOBALS[$varName]) && $GLOBALS[$varName] instanceof PDO) {
            $stmt = $GLOBALS[$varName]->prepare($sqlNamed);
            $stmt->execute($paramsNamed);
            return $stmt->rowCount();
        }
    }

    // 3) Cari koneksi mysqli yang sudah dibuat config.php.
    foreach (['mysqli', 'conn', 'db', 'link'] as $varName) {
        if (isset($GLOBALS[$varName]) && $GLOBALS[$varName] instanceof mysqli) {
            $mysqli = $GLOBALS[$varName];
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new Exception('Gagal menyiapkan query: ' . $mysqli->error);
            }
            // Parameter terakhir selalu id (integer), sisanya string.
            $count = count($paramsPositional);
            $types = str_repeat('s', $count - 1) . 'i';
            $stmt->bind_param($types, ...$paramsPositional);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
    }

    throw new Exception(
        'Koneksi database tidak ditemukan. Endpoint ini mencari variabel global ' .
        '$pdo/$db/$conn (PDO) atau $mysqli/$conn/$db/$link (mysqli) dari config.php, ' .
        'atau fungsi db_execute()/db_query(). Sesuaikan nama variabel koneksi Anda ' .
        'di fungsi jalankan_update_verifikasi() pada file ini jika berbeda.'
    );
}

try {
    $statusBaru = $aksi === 'setujui' ? 'Terverifikasi' : 'Ditolak';
    $alasanFinal = $aksi === 'tolak' ? $alasan : null;

    try {
        $affected = jalankan_update_verifikasi(
            "UPDATE kendaraan SET status_verifikasi = ?, alasan_penolakan = ?, updated_at = NOW() WHERE id = ? AND status_verifikasi = 'Menunggu Verifikasi'",
            [$statusBaru, $alasanFinal, $id],
            "UPDATE kendaraan SET status_verifikasi = :status, alasan_penolakan = :alasan, updated_at = NOW() WHERE id = :id AND status_verifikasi = 'Menunggu Verifikasi'",
            [':status' => $statusBaru, ':alasan' => $alasanFinal, ':id' => $id]
        );
    } catch (Throwable $e) {
        // Jika kolom alasan_penolakan belum ada di tabel, coba lagi tanpa kolom itu
        // (alasan tetap tersimpan lewat catat_log_aktivitas di bawah, jika tersedia).
        if (stripos($e->getMessage(), 'alasan_penolakan') !== false || stripos($e->getMessage(), 'unknown column') !== false) {
            $affected = jalankan_update_verifikasi(
                "UPDATE kendaraan SET status_verifikasi = ?, updated_at = NOW() WHERE id = ? AND status_verifikasi = 'Menunggu Verifikasi'",
                [$statusBaru, $id],
                "UPDATE kendaraan SET status_verifikasi = :status, updated_at = NOW() WHERE id = :id AND status_verifikasi = 'Menunggu Verifikasi'",
                [':status' => $statusBaru, ':id' => $id]
            );
        } else {
            throw $e;
        }
    }

    if ($affected === 0) {
        throw new Exception('Kendaraan tidak ditemukan atau sudah diproses sebelumnya.');
    }

    // Opsional: catat ke log aktivitas jika ada tabel/fungsi log
    if (function_exists('catat_log_aktivitas')) {
        catat_log_aktivitas(
            $_SESSION['id_user'] ?? null,
            $aksi === 'setujui' ? "Memverifikasi kendaraan #$id" : "Menolak kendaraan #$id ($alasan)",
            'Kendaraan'
        );
    }

    echo json_encode(['success' => true, 'message' => 'Berhasil diproses.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}