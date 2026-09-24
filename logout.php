<?php
/**
 * Endpoint: /logout.php
 *
 * Menghapus sesi login pengguna, mencatat log logout, lalu:
 *  - Jika dipanggil lewat AJAX/fetch (header X-Requested-With atau
 *    Accept: application/json) -> balas JSON seperti sebelumnya,
 *    supaya kode JS lama yang mungkin masih memanggilnya via fetch()
 *    tetap berfungsi.
 *  - Jika dipanggil langsung lewat klik link/tombol biasa (mis. dari
 *    sidebar/topbar: <a href="logout.php">) -> redirect ke landing
 *    page (landingpage.php), bukan menampilkan teks JSON mentah.
 */
session_start();
require_once 'config.php';

if (!empty($_SESSION['user_id'])) {
    catat_log($_SESSION['user_id'], 'Logout', 'Login/Logout');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($isAjax) {
    json_response(['success' => true, 'message' => 'Logout berhasil.']);
}

header('Location: landingpage.php');
exit;