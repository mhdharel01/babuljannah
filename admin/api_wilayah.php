<?php
// admin/api_wilayah.php - Internal API for Wilayah & Coordinate Demographics
require_once __DIR__ . '/../config/db.php';

// Proteksi akses admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Silakan login sebagai admin.']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'regencies';

if ($action === 'regencies') {
    $file = __DIR__ . '/../assets/data/regencies_coords.json';
    if (file_exists($file)) {
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
    
    // Fallback if file not found
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Data koordinat tidak ditemukan']);
    exit;
}

if ($action === 'summary') {
    try {
        $stmt_prov = $db->query("SELECT provinsi, COUNT(*) as jumlah FROM pendaftaran WHERE provinsi IS NOT NULL AND provinsi != '' GROUP BY provinsi ORDER BY jumlah DESC");
        $prov_data = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

        $stmt_kab = $db->query("SELECT kabupaten, COUNT(*) as jumlah FROM pendaftaran WHERE kabupaten IS NOT NULL AND kabupaten != '' GROUP BY kabupaten ORDER BY jumlah DESC LIMIT 50");
        $kab_data = $stmt_kab->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'provinsi' => $prov_data,
            'kabupaten' => $kab_data
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
exit;
