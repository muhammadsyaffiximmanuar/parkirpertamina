<?php
/**
 * Endpoint: POST /login.php
 * Dipanggil oleh form di login.html (field: username, password)
 * Mengembalikan JSON dan menyimpan sesi login jika berhasil.
 */
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

if (!$pdo) {
    json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    json_response(['success' => false, 'message' => 'Username dan kata sandi wajib diisi.'], 422);
}

$stmt = $pdo->prepare(
    'SELECT u.id, u.nama_lengkap, u.username, u.password_hash, u.status, r.nama_role
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.username = :username
     LIMIT 1'
);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    if ($user) {
        catat_log($user['id'], 'Login Gagal - Kata Sandi Salah', 'Login/Logout');
    }
    json_response(['success' => false, 'message' => 'Username atau kata sandi salah.'], 401);
}

if ($user['status'] !== 'Aktif') {
    json_response(['success' => false, 'message' => 'Akun Anda tidak aktif. Hubungi Super Admin.'], 403);
}

// Regenerasi ID sesi untuk mencegah session fixation.
session_regenerate_id(true);
$_SESSION['user_id']      = $user['id'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['role']         = $user['nama_role'];

catat_log($user['id'], 'Login Berhasil', 'Login/Logout');

json_response([
    'success'  => true,
    'message'  => 'Login berhasil.',
    'redirect' => dashboard_url_for_role($user['nama_role']),
    'user'     => [
        'id'           => $user['id'],
        'nama_lengkap' => $user['nama_lengkap'],
        'role'         => $user['nama_role'],
    ],
]);