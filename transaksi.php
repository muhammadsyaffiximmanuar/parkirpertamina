<?php
/**
 * Endpoint: /transaksi.php
 *   GET    -> daftar transaksi terkini (mendukung ?status=Masuk / ?status=Keluar)
 *   POST   -> catat kendaraan MASUK (buka transaksi baru, isi slot otomatis)
 *   PUT    -> catat kendaraan KELUAR (hitung biaya, pilih metode pembayaran,
 *             kosongkan slot). Setelah ini struk.php bisa dicetak.
 *
 * Dipakai oleh dashboard_petugas.php (tombol "Catat Masuk" / "Catat Keluar")
 * dan halaman transaksi (daftar transaksi).
 */
session_start();
require_once 'config.php';

$session = require_login();
if (!$pdo) {
    json_response(['success' => false, 'message' => 'Koneksi database tidak tersedia.'], 500);
}

switch ($_SERVER['REQUEST_METHOD']) {

    /* ===================== GET: daftar transaksi ===================== */
    case 'GET':
        $status = $_GET['status'] ?? null;

        if ($status) {
            $stmt = $pdo->prepare(
                'SELECT t.*, k.plat_nomor, k.tipe AS tipe_kendaraan, sp.kode_slot, l.nama_lantai,
                        TIMESTAMPDIFF(MINUTE, t.waktu_masuk, IFNULL(t.waktu_keluar, NOW())) AS durasi_menit,
                        u.nama_lengkap AS petugas
                 FROM transaksi t
                 JOIN kendaraan k ON k.id = t.kendaraan_id
                 LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
                 LEFT JOIN area a ON a.id = sp.area_id
                 LEFT JOIN lantai l ON l.id = a.lantai_id
                 LEFT JOIN users u ON u.id = t.petugas_id
                 WHERE t.status = :status
                 ORDER BY t.waktu_masuk DESC'
            );
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $pdo->query(
                'SELECT t.*, k.plat_nomor, k.tipe AS tipe_kendaraan, sp.kode_slot, l.nama_lantai,
                        TIMESTAMPDIFF(MINUTE, t.waktu_masuk, IFNULL(t.waktu_keluar, NOW())) AS durasi_menit,
                        u.nama_lengkap AS petugas
                 FROM transaksi t
                 JOIN kendaraan k ON k.id = t.kendaraan_id
                 LEFT JOIN slot_parkir sp ON sp.id = t.slot_id
                 LEFT JOIN area a ON a.id = sp.area_id
                 LEFT JOIN lantai l ON l.id = a.lantai_id
                 LEFT JOIN users u ON u.id = t.petugas_id
                 ORDER BY t.waktu_masuk DESC'
            );
        }
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    /* ============ POST: catat kendaraan MASUK (buka transaksi) ============ */
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $platNomor = trim($input['plat_nomor'] ?? '');
        if ($platNomor === '') {
            json_response(['success' => false, 'message' => "Plat nomor wajib diisi."], 422);
        }

        // Cari kendaraan berdasarkan plat nomor
        $kendaraan = db_fetch_one(
            'SELECT id, tipe FROM kendaraan WHERE plat_nomor = :p LIMIT 1',
            ['p' => $platNomor]
        );
        if (!$kendaraan) {
            json_response(['success' => false, 'message' => 'Kendaraan dengan plat nomor tersebut belum terdaftar. Daftarkan dulu di menu Kendaraan.'], 404);
        }

        // Pastikan kendaraan ini belum sedang parkir (status Masuk & belum keluar)
        $sedangParkir = db_fetch_one(
            "SELECT id FROM transaksi WHERE kendaraan_id = :k AND status = 'Masuk' LIMIT 1",
            ['k' => $kendaraan['id']]
        );
        if ($sedangParkir) {
            json_response(['success' => false, 'message' => 'Kendaraan ini sudah tercatat sedang parkir (belum keluar).'], 409);
        }

        // Pilih slot kosong secara otomatis, kecuali slot_id dikirim manual
        $slotId = !empty($input['slot_id']) ? (int) $input['slot_id'] : null;
        if ($slotId === null) {
            $slotKosong = db_fetch_one("SELECT id FROM slot_parkir WHERE status = 'Tersedia' LIMIT 1");
            $slotId = $slotKosong ? (int) $slotKosong['id'] : null;
        }
        if ($slotId === null) {
            json_response(['success' => false, 'message' => 'Tidak ada slot parkir yang tersedia saat ini.'], 409);
        }

        // Generate kode_parkir otomatis: PRK-2026-00001, dst.
        $count = (int) $pdo->query('SELECT COUNT(*) c FROM transaksi')->fetch()['c'];
        $kodeParkir = sprintf('PRK-%s-%05d', date('Y'), $count + 1);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO transaksi (kode_parkir, kendaraan_id, slot_id, waktu_masuk, status, petugas_id)
                 VALUES (:kode, :kendaraan_id, :slot_id, NOW(), \'Masuk\', :petugas_id)'
            );
            $stmt->execute([
                'kode'         => $kodeParkir,
                'kendaraan_id' => $kendaraan['id'],
                'slot_id'      => $slotId,
                'petugas_id'   => $session['user_id'],
            ]);
            $transaksiId = $pdo->lastInsertId();

            $pdo->prepare("UPDATE slot_parkir SET status = 'Terisi' WHERE id = :id")
                ->execute(['id' => $slotId]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Gagal mencatat kendaraan masuk.'], 500);
        }

        catat_log($session['user_id'], "Catat Kendaraan Masuk {$platNomor} ({$kodeParkir})", 'Manajemen Kendaraan');

        json_response([
            'success'      => true,
            'message'      => 'Kendaraan berhasil dicatat masuk.',
            'id'           => $transaksiId,
            'kode_parkir'  => $kodeParkir,
        ], 201);
        break;

    /* ====== PUT: catat kendaraan KELUAR (hitung biaya + metode bayar) ====== */
    case 'PUT':
        // Dibungkus try/catch umum: kalau ada error tak terduga (mis. tipe
        // data null, fungsi belum ada, dsb.) tetap dibalas JSON yang jelas,
        // bukan fatal error kosong yang bikin frontend salah kira "server
        // tidak terhubung".
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $id          = (int) ($input['id'] ?? 0);
            $kodeParkir  = trim($input['kode_parkir'] ?? '');
            $metodeBayar = trim($input['metode_bayar'] ?? '');

            if ($id <= 0 && $kodeParkir === '') {
                json_response(['success' => false, 'message' => 'ID atau kode parkir wajib diisi.'], 422);
            }

            $metodeValid = metode_pembayaran_tersedia();
            if (!array_key_exists($metodeBayar, $metodeValid)) {
                json_response(['success' => false, 'message' => 'Metode pembayaran tidak valid.'], 422);
            }

            // Ambil transaksi + tipe kendaraan.
            // Input bisa berupa kode_parkir (PRK-xxxx) ATAU plat nomor — form
            // catat_keluar.php mempersilakan keduanya (termasuk saat petugas
            // klik salah satu item "Masih Parkir" yang otomatis mengisi plat
            // nomor, bukan kode_parkir), jadi keduanya perlu didukung di sini.
            if ($id > 0) {
                $trx = db_fetch_one(
                    'SELECT t.*, k.tipe AS tipe_kendaraan, k.plat_nomor
                     FROM transaksi t JOIN kendaraan k ON k.id = t.kendaraan_id
                     WHERE t.id = :id LIMIT 1',
                    ['id' => $id]
                );
            } else {
                // 1) Coba cocokkan sebagai kode_parkir dulu.
                $trx = db_fetch_one(
                    'SELECT t.*, k.tipe AS tipe_kendaraan, k.plat_nomor
                     FROM transaksi t JOIN kendaraan k ON k.id = t.kendaraan_id
                     WHERE t.kode_parkir = :kode LIMIT 1',
                    ['kode' => $kodeParkir]
                );
                // 2) Kalau tidak ketemu, coba cocokkan sebagai plat nomor
                //    (transaksi yang masih berstatus 'Masuk' untuk plat itu).
                if (!$trx) {
                    $trx = db_fetch_one(
                        "SELECT t.*, k.tipe AS tipe_kendaraan, k.plat_nomor
                         FROM transaksi t JOIN kendaraan k ON k.id = t.kendaraan_id
                         WHERE k.plat_nomor = :plat AND t.status = 'Masuk'
                         ORDER BY t.waktu_masuk DESC LIMIT 1",
                        ['plat' => $kodeParkir]
                    );
                }
            }

            if (!$trx) {
                json_response(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
            }
            if ($trx['status'] === 'Keluar') {
                json_response(['success' => false, 'message' => 'Transaksi ini sudah tercatat keluar sebelumnya.'], 409);
            }

            $tarifPerJam = ambil_tarif_per_jam($trx['tipe_kendaraan'] ?? '');

            // Hitung durasi langsung via MySQL (bukan PHP strtotime), supaya
            // tidak ada selisih zona waktu antara PHP (Asia/Jakarta) dan
            // server MySQL (biasanya UTC di hosting gratis). Sebelumnya,
            // mencampur jam PHP dengan jam MySQL menyebabkan durasi
            // dihitung salah (bisa terhitung beberapa jam lebih lama dari
            // durasi sebenarnya), sehingga biaya parkir ikut membengkak.
            $durasiRow = db_fetch_one(
                'SELECT TIMESTAMPDIFF(MINUTE, waktu_masuk, NOW()) AS menit FROM transaksi WHERE id = :id',
                ['id' => $trx['id']],
                ['menit' => 0]
            );
            $durasiMenit = max(0, (int) $durasiRow['menit']);

            $biaya = hitung_biaya_parkir($durasiMenit, $tarifPerJam);
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE transaksi
                     SET waktu_keluar = NOW(), biaya = :biaya, status = 'Keluar', metode_bayar = :metode
                     WHERE id = :id"
                )->execute([
                    'biaya'  => $biaya,
                    'metode' => $metodeBayar,
                    'id'     => $trx['id'],
                ]);

                if (!empty($trx['slot_id'])) {
                    $pdo->prepare("UPDATE slot_parkir SET status = 'Tersedia' WHERE id = :id")
                        ->execute(['id' => $trx['slot_id']]);
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'Gagal mencatat kendaraan keluar.'], 500);
            }

            catat_log(
                $session['user_id'],
                "Catat Kendaraan Keluar {$trx['plat_nomor']} ({$trx['kode_parkir']}) - Bayar {$metodeBayar} " . format_rupiah($biaya),
                'Manajemen Kendaraan'
            );

            json_response([
                'success'      => true,
                'message'      => 'Kendaraan berhasil dicatat keluar.',
                'id'           => (int) $trx['id'],
                'kode_parkir'  => $trx['kode_parkir'],
                'biaya'        => $biaya,
                'metode_bayar' => $metodeBayar,
                'struk_url'    => 'struk.php?id=' . (int) $trx['id'],
            ]);
        } catch (Throwable $e) {
            // Menangkap TypeError/Error apapun yang lolos dari blok di atas,
            // supaya respons tetap JSON valid (bukan halaman fatal error
            // kosong yang bikin fetch() di frontend gagal parse).
            json_response([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses kendaraan keluar: ' . $e->getMessage(),
            ], 500);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}