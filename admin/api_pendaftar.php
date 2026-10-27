<?php
// admin/api_pendaftar.php - Internal Admin API for Asynchronous AJAX Operations
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/db.php';
require_once '../config/mail.php';

// Proteksi sesi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi admin tidak valid. Silakan login kembali.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'panitia';
$action = sanitize($_REQUEST['action'] ?? '');

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter action wajib diisi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        // 1. Update Status Pembayaran / Seleksi
        case 'update_status':
            if (!in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = (int)($_POST['id'] ?? 0);
            $status_bayar = sanitize($_POST['status_pembayaran'] ?? '');
            $status_seleksi = sanitize($_POST['status_seleksi'] ?? '');

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID pendaftar tidak valid.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Cek data lama
            $stmt_old = $db->prepare("SELECT status_pembayaran, email, no_registrasi, nama_santri, wa_ayah FROM pendaftaran WHERE id = :id");
            $stmt_old->execute(['id' => $id]);
            $old_data = $stmt_old->fetch();

            if (!$old_data) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Data pendaftar tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("UPDATE pendaftaran SET 
                status_pembayaran = :bayar, 
                status_seleksi = :seleksi,
                tgl_lunas = CASE WHEN :bayar = 'Lunas' THEN COALESCE(tgl_lunas, NOW()) ELSE NULL END 
                WHERE id = :id");
            $stmt->execute([
                'bayar' => $status_bayar,
                'seleksi' => $status_seleksi,
                'id' => $id
            ]);

            // Kirim notifikasi jika status pembayaran berubah menjadi Lunas
            if ($old_data['status_pembayaran'] !== 'Lunas' && $status_bayar === 'Lunas') {
                send_email_verifikasi_lunas($old_data);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Status pendaftar berhasil diperbarui.',
                'data' => [
                    'id' => $id,
                    'status_pembayaran' => $status_bayar,
                    'status_seleksi' => $status_seleksi
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 2. Konfirmasi Pembayaran Cepat
        case 'confirm_payment':
            if (!in_array($admin_role, ['superadmin', 'bendahara'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Hanya bendahara atau superadmin yang dapat memverifikasi pembayaran.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID pendaftar tidak valid.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt_old = $db->prepare("SELECT status_pembayaran, email, no_registrasi, nama_santri, wa_ayah FROM pendaftaran WHERE id = :id");
            $stmt_old->execute(['id' => $id]);
            $old_data = $stmt_old->fetch();

            if (!$old_data) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Data pendaftar tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("UPDATE pendaftaran SET status_pembayaran = 'Lunas', tgl_lunas = COALESCE(tgl_lunas, NOW()) WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($old_data['status_pembayaran'] !== 'Lunas') {
                send_email_verifikasi_lunas($old_data);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Pembayaran berhasil diverifikasi (Status: Lunas).',
                'id' => $id
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 3. Hapus Pendaftar & Auto-Reset Urutan Jika Kosong
        case 'delete':
            if (!in_array($admin_role, ['superadmin', 'panitia'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID pendaftar tidak valid.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM pendaftaran WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // Cek apakah data pendaftar sekarang sudah 0 (kosong)
            $count_remaining = (int)$db->query("SELECT COUNT(*) FROM pendaftaran")->fetchColumn();
            if ($count_remaining === 0) {
                $db->query("ALTER TABLE pendaftaran AUTO_INCREMENT = 1");
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Data pendaftar berhasil dihapus.',
                'remaining' => $count_remaining
            ], JSON_UNESCAPED_UNICODE);
            break;

        // 4. Detail Data Santri Lengkap
        case 'detail':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang, COALESCE(g.tahun_ajaran, '-') as tahun_ajaran 
                FROM pendaftaran p 
                LEFT JOIN gelombang g ON p.gelombang_id = g.id 
                WHERE p.id = :id");
            $stmt->execute(['id' => $id]);
            $santri = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$santri) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'status' => 'success',
                'data' => $santri
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
