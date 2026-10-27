<?php
// api/v1/santri.php - REST API Data Santri untuk Integrasi Eksternal (Sistem Akademik, dll)
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode HTTP tidak diizinkan. Gunakan GET.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// 1. Verifikasi Keamanan via API Key
$provided_key = '';

if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $provided_key = trim($_SERVER['HTTP_X_API_KEY']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($auth, 'Bearer ') === 0) {
        $provided_key = trim(substr($auth, 7));
    } else {
        $provided_key = $auth;
    }
} elseif (!empty($_GET['api_key'])) {
    $provided_key = trim($_GET['api_key']);
}

$valid_key = env('API_SECRET_KEY', 'babuljannah_psb_api_secret_2026');

if (empty($provided_key) || !hash_equals($valid_key, $provided_key)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan pada header X-API-KEY.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. Filter & Parameter Query
$status_seleksi = isset($_GET['status_seleksi']) ? sanitize($_GET['status_seleksi']) : '';
$status_bayar   = isset($_GET['status_pembayaran']) ? sanitize($_GET['status_pembayaran']) : '';
$jalur          = isset($_GET['jalur']) ? sanitize($_GET['jalur']) : '';
$tahun_ajaran   = isset($_GET['tahun_ajaran']) ? sanitize($_GET['tahun_ajaran']) : '';
$no_registrasi  = isset($_GET['no_registrasi']) ? sanitize($_GET['no_registrasi']) : '';
$search         = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 250) $limit = 250;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

// 3. Bangun Query SQL
$where_clauses = ['1=1'];
$params = [];

if (!empty($status_seleksi)) {
    $where_clauses[] = 'p.status_seleksi = :status_seleksi';
    $params['status_seleksi'] = $status_seleksi;
}

if (!empty($status_bayar)) {
    $where_clauses[] = 'p.status_pembayaran = :status_bayar';
    $params['status_bayar'] = $status_bayar;
}

if (!empty($jalur)) {
    $where_clauses[] = 'p.jalur_pendaftaran LIKE :jalur';
    $params['jalur'] = '%' . $jalur . '%';
}

if (!empty($tahun_ajaran)) {
    $where_clauses[] = 'g.tahun_ajaran = :tahun_ajaran';
    $params['tahun_ajaran'] = $tahun_ajaran;
}

if (!empty($no_registrasi)) {
    $where_clauses[] = 'p.no_registrasi = :no_reg';
    $params['no_reg'] = $no_registrasi;
}

if (!empty($search)) {
    $where_clauses[] = '(p.nama_santri LIKE :search OR p.no_registrasi LIKE :search OR p.nik_santri LIKE :search OR p.nisn LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$where_sql = implode(' AND ', $where_clauses);

try {
    // Hitung Total Data
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE {$where_sql}");
    $stmt_count->execute($params);
    $total_data = (int)$stmt_count->fetchColumn();

    // Ambil Data Santri
    $sql = "SELECT 
                p.id,
                p.no_registrasi,
                p.nama_santri,
                p.nik_santri,
                p.nisn,
                p.no_kk,
                p.jk as jenis_kelamin,
                p.tempat_lahir,
                p.tgl_lahir as tanggal_lahir,
                p.kategori_santri,
                p.status_anak,
                p.jalur_pendaftaran,
                p.status_pembayaran,
                p.status_seleksi,
                p.status_kehadiran,
                p.tgl_lunas,
                p.nama_ayah,
                p.nik_ayah,
                p.pekerjaan_ayah,
                p.wa_ayah,
                p.nama_ibu,
                p.nik_ibu,
                p.pekerjaan_ibu,
                p.wa_ibu,
                p.nama_wali,
                p.wa_wali,
                p.provinsi,
                p.kabupaten,
                p.kecamatan,
                p.kelurahan as desa_kelurahan,
                p.alamat_lengkap,
                p.nama_sekolah_asal,
                p.npsn_sekolah_asal,
                p.email,
                p.created_at,
                COALESCE(g.nama_gelombang, '-') as nama_gelombang,
                COALESCE(g.tahun_ajaran, '-') as tahun_ajaran
            FROM pendaftaran p 
            LEFT JOIN gelombang g ON p.gelombang_id = g.id 
            WHERE {$where_sql}
            ORDER BY p.id ASC
            LIMIT :limit OFFSET :offset";

    $stmt_data = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt_data->bindValue(':' . $k, $v);
    }
    $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_data->execute();
    $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = $total_data > 0 ? (int)ceil($total_data / $limit) : 0;

    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'timestamp' => date('c'),
        'meta' => [
            'total_data' => $total_data,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $total_pages,
            'filter' => [
                'status_seleksi' => $status_seleksi ?: null,
                'status_pembayaran' => $status_bayar ?: null,
                'jalur' => $jalur ?: null,
                'tahun_ajaran' => $tahun_ajaran ?: null,
                'no_registrasi' => $no_registrasi ?: null
            ]
        ],
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 500,
        'message' => 'Terjadi kesalahan internal server saat mengambil data.',
        'debug' => env('APP_DEBUG', false) ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
