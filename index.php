<?php
/**
 * index.php (ROUTER)
 *
 * Sebelumnya file ini berisi dashboard tunggal untuk semua role.
 * Sekarang isinya dipecah menjadi 3 dashboard terpisah sesuai role:
 *   - dashboard_owner.php   (Owner, Super Admin)
 *   - dashboard_admin.php   (Admin)
 *   - dashboard_petugas.php (Officer, Security)
 *
 * index.php kini hanya bertugas sebagai "pintu masuk": mengecek sesi
 * login, lalu mengarahkan (redirect) pengguna ke dashboard yang sesuai
 * dengan role-nya. Ini supaya link lama yang menuju index.php
 * (mis. di login.html) tidak perlu diubah.
 */
session_start();
require_once 'config.php';
require_login_page();

$role = $_SESSION['role'] ?? '';
header('Location: ' . dashboard_url_for_role($role));
exit;