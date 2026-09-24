<?php
/**
 * aksi_kelola_user.php
 * Endpoint AJAX untuk kelola_user.php — menangani aksi: tambah, ubah, hapus, toggle_status.
 *
 * CATATAN PENTING SOAL PASSWORD:
 * Password TIDAK PERNAH disimpan dalam bentuk asli (plaintext). Kita pakai password_hash()
 * bawaan PHP (algoritma bcrypt) sebelum disimpan ke database, dan password_verify() saat
 * proses login.
 *
 * CATATAN STRUKTUR TABEL users (disesuaikan dari struktur asli di database):
 * - Kolom bernama `password_hash`, BUKAN `password`.
 * - Kolom bernama `role_id` (int, FK ke tabel `roles`), BUKAN `role` (teks).
 * - TIDAK ADA kolom `no_hp` di tabel ini — kalau form Kelola User punya input
 *   No. HP, datanya untuk sementara tidak tersimpan sampai kolom itu ditambahkan
 *   ke tabel (lihat catatan di bawah untuk ALTER TABLE-nya).
 * - Kolom `username` WAJIB diisi (NOT NULL) — digenerate otomatis dari email
 *   lewat generate_username_unik() di config.php, karena form tidak selalu
 *   punya input username sendiri.
 * - Kolom `status` bertipe ENUM('Aktif', 'Non-Aktif') — perhatikan tanda hubung
 *   di 'Non-Aktif', beda dari penulisan biasa 'Nonaktif' di halaman lain.
 */
session_start();
require_once 'config.php';
require_login_page();
require_role([ROLE_OWNER, ROLE_SUPER_ADMIN, ROLE_ADMIN]);

header('Content-Type: application/json');

function respond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ---------- Baca input JSON ----------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(false, 'Data permintaan tidak valid.');
}

// ---------- Verifikasi CSRF ----------
if (empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    respond(false, 'Sesi tidak valid, silakan muat ulang halaman.');
}

$aksi = $input['aksi'] ?? '';
// Nama-nama ini harus sama persis dengan kolom nama_role di tabel `roles`.
$rolesValid = [ROLE_SUPER_ADMIN, ROLE_OWNER, ROLE_ADMIN, ROLE_OFFICER, ROLE_SECURITY, ROLE_USER];

global $pdo;

try {
    switch ($aksi) {

        /* ============ TAMBAH USER ============ */
        case 'tambah': {
            $nama  = trim($input['nama_lengkap'] ?? '');
            $email = trim($input['email'] ?? '');
            $hp    = trim($input['no_hp'] ?? '');
            $role  = $input['role'] ?? '';
            $password = $input['password'] ?? '';
            if ($password === '') {
                $password = 'password123'; // default untuk user baru
            }

            if ($nama === '' || $email === '') {
                respond(false, 'Nama dan email wajib diisi.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(false, 'Format email tidak valid.');
            }
            if (!in_array($role, $rolesValid, true)) {
                respond(false, 'Role tidak valid.');
            }
            if (strlen($password) < 8) {
                respond(false, 'Password minimal 8 karakter.');
            }

            $roleId = ambil_role_id_by_nama($role);
            if ($roleId === null) {
                respond(false, 'Role tidak ditemukan di database.');
            }

            // Cek email sudah dipakai atau belum
            $cekEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $cekEmail->execute([$email]);
            if ($cekEmail->fetch()) {
                respond(false, 'Email sudah terdaftar, gunakan email lain.');
            }

            $emailPrefix = substr($email, 0, strpos($email, '@') ?: strlen($email));
            $username = generate_username_unik($emailPrefix !== '' ? $emailPrefix : $nama);

            // Hash password sebelum disimpan — JANGAN simpan password asli.
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (nama_lengkap, username, email, no_hp, password_hash, role_id, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'Aktif', NOW())"
            );
            $stmt->execute([$nama, $username, $email, $hp, $passwordHash, $roleId]);

            respond(true, 'User baru berhasil ditambahkan.', ['id' => $pdo->lastInsertId(), 'username' => $username]);
        }

        /* ============ UBAH USER ============ */
        case 'ubah': {
            $id    = (int) ($input['id'] ?? 0);
            $nama  = trim($input['nama_lengkap'] ?? '');
            $email = trim($input['email'] ?? '');
            $hp    = trim($input['no_hp'] ?? '');
            $role  = $input['role'] ?? '';
            $password = $input['password'] ?? ''; // kosong = tidak diubah

            if ($id <= 0) respond(false, 'User tidak ditemukan.');
            if ($nama === '' || $email === '') respond(false, 'Nama dan email wajib diisi.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Format email tidak valid.');
            if (!in_array($role, $rolesValid, true)) respond(false, 'Role tidak valid.');

            $roleId = ambil_role_id_by_nama($role);
            if ($roleId === null) {
                respond(false, 'Role tidak ditemukan di database.');
            }

            $cekEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $cekEmail->execute([$email, $id]);
            if ($cekEmail->fetch()) {
                respond(false, 'Email sudah dipakai user lain.');
            }

            if ($password !== '') {
                if (strlen($password) < 8) {
                    respond(false, 'Password minimal 8 karakter.');
                }
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, role_id = ?, password_hash = ? WHERE id = ?"
                );
                $stmt->execute([$nama, $email, $hp, $roleId, $passwordHash, $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, role_id = ? WHERE id = ?"
                );
                $stmt->execute([$nama, $email, $hp, $roleId, $id]);
            }

            respond(true, 'Data user berhasil diperbarui.');
        }

        /* ============ HAPUS USER ============ */
        case 'hapus': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) respond(false, 'User tidak ditemukan.');

            if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                respond(false, 'Tidak bisa menghapus akun yang sedang digunakan.');
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            respond(true, 'User berhasil dihapus.');
        }

        /* ============ TOGGLE STATUS (Aktif/Non-Aktif) ============ */
        case 'toggle_status': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) respond(false, 'User tidak ditemukan.');

            $userLama = $pdo->prepare("SELECT status FROM users WHERE id = ?");
            $userLama->execute([$id]);
            $row = $userLama->fetch();
            if (!$row) respond(false, 'User tidak ditemukan.');

            // PENTING: nilai enum di database adalah 'Non-Aktif' (dengan tanda hubung).
            $statusBaru = ($row['status'] === 'Aktif') ? 'Non-Aktif' : 'Aktif';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$statusBaru, $id]);

            respond(true, 'Status user berhasil diperbarui.', ['status' => $statusBaru]);
        }

        default:
            respond(false, 'Aksi tidak dikenali.');
    }
} catch (Throwable $e) {
    // Jangan tampilkan detail error mentah ke client (bisa bocorkan struktur DB)
    error_log('aksi_kelola_user.php error: ' . $e->getMessage());
    respond(false, 'Terjadi kesalahan pada server, coba lagi.');
}