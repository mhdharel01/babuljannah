<?php
// admin/scan_action.php
require_once '../config/db.php';

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi admin telah habis. Silakan login kembali.']);
    exit();
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak (RBAC)']);
    exit();
}

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'check') {
    $no_reg = sanitize($_GET['no_reg'] ?? $_POST['no_reg'] ?? '');
    $gelombang_id = sanitize($_GET['gelombang_id'] ?? $_POST['gelombang_id'] ?? '');

    if (empty($no_reg)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor Registrasi atau QR Code kosong.']);
        exit();
    }

    $sql = "SELECT p.*, g.nama_gelombang, g.tahun_ajaran, g.tgl_ujian 
            FROM pendaftaran p 
            JOIN gelombang g ON p.gelombang_id = g.id 
            WHERE p.no_registrasi = :no_reg";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['no_reg' => $no_reg]);
    $pendaftar = $stmt->fetch();

    if (!$pendaftar) {
        echo json_encode(['status' => 'error', 'message' => "Calon santri dengan Nomor Registrasi '$no_reg' tidak ditemukan!"]);
        exit();
    }

    // Cek apakah filter gelombang dipilih dan berbeda
    if (!empty($gelombang_id) && $pendaftar['gelombang_id'] != $gelombang_id) {
        echo json_encode([
            'status' => 'warning',
            'message' => "Perhatian: Santri ini mendaftar di " . $pendaftar['nama_gelombang'] . " (Berbeda dengan filter gelombang saat ini).",
            'data' => $pendaftar
        ]);
        exit();
    }

    echo json_encode(['status' => 'success', 'data' => $pendaftar]);
    exit();
} 

elseif ($action === 'attend') {
    $no_reg = sanitize($_POST['no_reg'] ?? $_GET['no_reg'] ?? '');
    
    if (empty($no_reg)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor Registrasi kosong.']);
        exit();
    }

    $stmt_check = $db->prepare("SELECT id, nama_santri, status_kehadiran, waktu_kehadiran FROM pendaftaran WHERE no_registrasi = :no_reg");
    $stmt_check->execute(['no_reg' => $no_reg]);
    $p = $stmt_check->fetch();

    if (!$p) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
        exit();
    }

    $waktu_sekarang = date('Y-m-d H:i:s');
    $stmt_update = $db->prepare("UPDATE pendaftaran SET status_kehadiran = 'Hadir', waktu_kehadiran = :waktu WHERE no_registrasi = :no_reg");
    $stmt_update->execute(['waktu' => $waktu_sekarang, 'no_reg' => $no_reg]);

    echo json_encode([
        'status' => 'success',
        'message' => "Kehadiran santri atas nama '" . $p['nama_santri'] . "' berhasil dicatat!",
        'waktu' => format_tanggal($waktu_sekarang) . ' - ' . date('H:i:s', strtotime($waktu_sekarang)) . ' WIB'
    ]);
    exit();
}

elseif ($action === 'stats') {
    $gelombang_id = sanitize($_GET['gelombang_id'] ?? '');

    $where = "WHERE status_pembayaran = 'Lunas'";
    $params = [];
    if (!empty($gelombang_id)) {
        $where .= " AND gelombang_id = :gid";
        $params['gid'] = $gelombang_id;
    }

    $stmt_tot = $db->prepare("SELECT COUNT(*) as total FROM pendaftaran $where");
    $stmt_tot->execute($params);
    $tot = $stmt_tot->fetch()['total'] ?? 0;

    $stmt_hdr = $db->prepare("SELECT COUNT(*) as hadir FROM pendaftaran $where AND status_kehadiran = 'Hadir'");
    $stmt_hdr->execute($params);
    $hdr = $stmt_hdr->fetch()['hadir'] ?? 0;

    $blm = $tot - $hdr;

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total' => (int)$tot,
            'hadir' => (int)$hdr,
            'belum' => (int)$blm
        ]
    ]);
    exit();
}
elseif ($action === 'unattend') {
    $no_reg = sanitize($_POST['no_reg'] ?? $_GET['no_reg'] ?? '');
    
    if (empty($no_reg)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor Registrasi kosong.']);
        exit();
    }

    $stmt_update = $db->prepare("UPDATE pendaftaran SET status_kehadiran = 'Belum Hadir', waktu_kehadiran = NULL WHERE no_registrasi = :no_reg");
    $stmt_update->execute(['no_reg' => $no_reg]);

    echo json_encode([
        'status' => 'success',
        'message' => "Status kehadiran santri ($no_reg) telah diubah menjadi Belum Hadir."
    ]);
    exit();
}

elseif ($action === 'list_table') {
    $gelombang_id = sanitize($_GET['gelombang_id'] ?? '');
    $filter_status = sanitize($_GET['filter_status'] ?? '');
    $search = sanitize($_GET['search'] ?? '');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($gelombang_id)) {
        $where .= " AND p.gelombang_id = :gid";
        $params['gid'] = $gelombang_id;
    }

    if ($filter_status === 'Hadir') {
        $where .= " AND p.status_kehadiran = 'Hadir'";
    } elseif ($filter_status === 'Belum') {
        $where .= " AND (p.status_kehadiran = 'Belum Hadir' OR p.status_kehadiran IS NULL)";
    }

    if (!empty($search)) {
        $where .= " AND (p.nama_santri LIKE :search OR p.no_registrasi LIKE :search OR p.nama_sekolah_asal LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    // Hitung total data
    $count_sql = "SELECT COUNT(*) as total FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id $where";
    $stmt_count = $db->prepare($count_sql);
    $stmt_count->execute($params);
    $total_data = (int)$stmt_count->fetch()['total'];
    $total_pages = ceil($total_data / $limit);

    $sql = "SELECT p.id, p.no_registrasi, p.nama_santri, p.jalur_pendaftaran, p.nama_sekolah_asal as sekolah_asal, p.status_pembayaran, p.status_kehadiran, p.waktu_kehadiran, g.nama_gelombang 
            FROM pendaftaran p 
            LEFT JOIN gelombang g ON p.gelombang_id = g.id 
            $where 

            ORDER BY (p.status_kehadiran = 'Hadir') DESC, p.waktu_kehadiran DESC, p.id DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll();

    // Format tanggal dan waktu untuk tampilan
    foreach ($list as &$row) {
        if ($row['status_kehadiran'] === 'Hadir' && !empty($row['waktu_kehadiran'])) {
            $row['waktu_formatted'] = date('H:i', strtotime($row['waktu_kehadiran'])) . ' WIB (' . date('d/m/Y', strtotime($row['waktu_kehadiran'])) . ')';
        } else {
            $row['waktu_formatted'] = '-';
            $row['status_kehadiran'] = 'Belum Hadir';
        }
    }

    echo json_encode([
        'status' => 'success',
        'count' => count($list),
        'total_data' => $total_data,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'limit' => $limit,
        'data' => $list
    ]);
    exit();
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali.']);
    exit();
}
?>
