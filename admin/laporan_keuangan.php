<?php
// admin/laporan_keuangan.php
require_once __DIR__ . '/../config/db.php';

// Cek autentikasi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Hak Akses (RBAC) - Hanya superadmin, bendahara, dan yayasan
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'bendahara', 'yayasan'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk melihat laporan keuangan.");
}

// Ambil Biaya Pendaftaran resmi dari pengaturan_sistem
$biaya_tes_smp = 300000;
$biaya_tes_sma = 350000;
$biaya_tes_def = 350000;

try {
    $stmt_set = $db->query("SELECT kunci, nilai FROM pengaturan_sistem WHERE kunci IN ('biaya_tes_smp', 'biaya_tes_sma', 'biaya_tes_pendaftaran')");
    while ($r_set = $stmt_set->fetch(PDO::FETCH_ASSOC)) {
        if ($r_set['kunci'] === 'biaya_tes_smp' && is_numeric($r_set['nilai'])) {
            $biaya_tes_smp = (int)$r_set['nilai'];
        } elseif ($r_set['kunci'] === 'biaya_tes_sma' && is_numeric($r_set['nilai'])) {
            $biaya_tes_sma = (int)$r_set['nilai'];
        } elseif ($r_set['kunci'] === 'biaya_tes_pendaftaran' && is_numeric($r_set['nilai'])) {
            $biaya_tes_def = (int)$r_set['nilai'];
        }
    }
} catch (Throwable $e) {
    // Fallback default
}

// Helper penentu nominal santri berdasarkan jenjang
if (!function_exists('get_nominal_santri')) {
    function get_nominal_santri($jalur, $biaya_smp, $biaya_sma, $biaya_def) {
        if (stripos($jalur, 'SMP') !== false) {
            return $biaya_smp;
        }
        if (stripos($jalur, 'SMA') !== false) {
            return $biaya_sma;
        }
        return $biaya_def;
    }
}

// Ambil daftar gelombang untuk filter dropdown
$daftar_gelombang = [];
try {
    $stmt_g = $db->query("SELECT id, nama_gelombang, tahun_ajaran, status FROM gelombang ORDER BY id ASC");
    $daftar_gelombang = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $daftar_gelombang = [];
}

// -------------------------------------------------------------
// FILTER & SEARCH PARAMETERS
// -------------------------------------------------------------
$q = trim((string)($_GET['q'] ?? ''));
$filter_gelombang = trim((string)($_GET['gelombang'] ?? ''));
$filter_metode = trim((string)($_GET['metode'] ?? ''));
$filter_jalur = trim((string)($_GET['jalur'] ?? ''));
$filter_tgl_mulai = trim((string)($_GET['tgl_mulai'] ?? ''));
$filter_tgl_selesai = trim((string)($_GET['tgl_selesai'] ?? ''));
$quick_period = trim((string)($_GET['quick_period'] ?? ''));

// Presets Periode Cepat
if ($quick_period === 'today') {
    $filter_tgl_mulai = date('Y-m-d');
    $filter_tgl_selesai = date('Y-m-d');
} elseif ($quick_period === 'yesterday') {
    $filter_tgl_mulai = date('Y-m-d', strtotime('-1 day'));
    $filter_tgl_selesai = date('Y-m-d', strtotime('-1 day'));
} elseif ($quick_period === 'this_week') {
    $filter_tgl_mulai = date('Y-m-d', strtotime('monday this week'));
    $filter_tgl_selesai = date('Y-m-d');
} elseif ($quick_period === 'this_month') {
    $filter_tgl_mulai = date('Y-m-01');
    $filter_tgl_selesai = date('Y-m-t');
} elseif ($quick_period === 'last_month') {
    $filter_tgl_mulai = date('Y-m-01', strtotime('first day of last month'));
    $filter_tgl_selesai = date('Y-m-t', strtotime('last day of last month'));
}

// Bangun SQL Conditions
$where_clauses = ["p.status_pembayaran = 'Lunas'"];
$params = [];

if ($q !== '') {
    $where_clauses[] = "(p.no_registrasi LIKE :q OR p.nama_santri LIKE :q OR p.nisn LIKE :q)";
    $params[':q'] = "%$q%";
}

if ($filter_gelombang !== '') {
    $where_clauses[] = "p.gelombang_id = :gelombang";
    $params[':gelombang'] = $filter_gelombang;
}

if ($filter_metode !== '') {
    if ($filter_metode === 'Transfer') {
        $where_clauses[] = "p.metode_pembayaran = 'Transfer'";
    } elseif ($filter_metode === 'Tunai/Cash') {
        $where_clauses[] = "p.metode_pembayaran = 'Tunai/Cash'";
    } elseif ($filter_metode === 'Lainnya') {
        $where_clauses[] = "(p.metode_pembayaran NOT IN ('Transfer', 'Tunai/Cash') OR p.metode_pembayaran IS NULL)";
    }
}

if ($filter_jalur !== '') {
    $where_clauses[] = "p.jalur_pendaftaran = :jalur";
    $params[':jalur'] = $filter_jalur;
}

if ($filter_tgl_mulai !== '') {
    $where_clauses[] = "DATE(p.tgl_lunas) >= :tgl_mulai";
    $params[':tgl_mulai'] = $filter_tgl_mulai;
}

if ($filter_tgl_selesai !== '') {
    $where_clauses[] = "DATE(p.tgl_lunas) <= :tgl_selesai";
    $params[':tgl_selesai'] = $filter_tgl_selesai;
}

$where_sql = implode(' AND ', $where_clauses);

// -------------------------------------------------------------
// HANDLE EXPORT KE EXCEL
// -------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $stmt_exp = $db->prepare("
        SELECT 
            p.id,
            p.no_registrasi,
            p.nama_santri,
            p.nisn,
            p.jalur_pendaftaran,
            p.metode_pembayaran,
            p.tgl_lunas,
            p.gelombang_id,
            g.nama_gelombang,
            g.tahun_ajaran
        FROM pendaftaran p
        LEFT JOIN gelombang g ON p.gelombang_id = g.id
        WHERE $where_sql
        ORDER BY p.tgl_lunas DESC, p.id DESC
    ");
    $stmt_exp->execute($params);
    $data_exp = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

    $filename = "Laporan_Keuangan_Babul_Jannah_" . date('Ymd_His') . ".xls";
    if (!headers_sent()) {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Cache-Control: max-age=0");
    }

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8' /><style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }";
    echo "th { background-color: #5C3E35; color: #ffffff; padding: 10px; font-weight: bold; border: 1px solid #3E2723; text-align: center; }";
    echo "td { padding: 8px; border: 1px solid #cccccc; vertical-align: middle; }";
    echo ".total-row { background-color: #F5EBE6; font-weight: bold; font-size: 12px; }";
    echo ".text-center { text-align: center; }";
    echo ".text-right { text-align: right; }";
    echo ".title { font-size: 16px; font-weight: bold; color: #5C3E35; text-align: center; }";
    echo ".subtitle { font-size: 11px; color: #555555; text-align: center; }";
    echo "</style></head><body>";

    echo "<table>";
    echo "<tr><td colspan='9' class='title'>PONDOK PESANTREN MODERN BABUL JANNAH</td></tr>";
    echo "<tr><td colspan='9' class='subtitle'>LAPORAN KEUANGAN PENERIMAAN SANTRI BARU (PSB)</td></tr>";
    echo "<tr><td colspan='9' class='subtitle'>Rekening Resmi: Bank Syariah Indonesia (BSI) 7300750308 a.n. Pesantren Babul Jannah</td></tr>";
    echo "<tr><td colspan='9' class='subtitle'>Waktu Cetak: " . date('d/m/Y H:i:s') . " WIB | Operator: " . htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') . "</td></tr>";
    echo "<tr><td colspan='9'></td></tr>";

    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>No. Registrasi</th>";
    echo "<th>Nama Calon Santri</th>";
    echo "<th>NISN</th>";
    echo "<th>Jalur / Jenjang</th>";
    echo "<th>Gelombang</th>";
    echo "<th>Saluran Pembayaran</th>";
    echo "<th>Tanggal Validasi Lunas</th>";
    echo "<th>Nominal Biaya (Rp)</th>";
    echo "</tr>";
    echo "</thead><tbody>";

    $no = 1;
    $grand_total = 0;
    $tot_transfer = 0;
    $tot_tunai = 0;
    $tot_lainnya = 0;

    foreach ($data_exp as $row) {
        $nom = get_nominal_santri($row['jalur_pendaftaran'] ?? '', $biaya_tes_smp, $biaya_tes_sma, $biaya_tes_def);
        $grand_total += $nom;

        $channel = 'Kasir Panitia (Tunai)';
        if ($row['metode_pembayaran'] === 'Transfer') {
            $channel = 'Bank Syariah Indonesia (BSI)';
            $tot_transfer += $nom;
        } elseif ($row['metode_pembayaran'] === 'Tunai/Cash') {
            $channel = 'Kas Tunai Panitia';
            $tot_tunai += $nom;
        } else {
            $channel = 'Panitia / Manual';
            $tot_lainnya += $nom;
        }

        $tgl_text = $row['tgl_lunas'] ? date('d/m/Y H:i', strtotime($row['tgl_lunas'])) : '-';

        echo "<tr>";
        echo "<td class='text-center'>{$no}</td>";
        echo "<td class='text-center'>{$row['no_registrasi']}</td>";
        echo "<td>{$row['nama_santri']}</td>";
        echo "<td class='text-center'>'{$row['nisn']}</td>";
        echo "<td>{$row['jalur_pendaftaran']}</td>";
        echo "<td>" . ($row['nama_gelombang'] ? "{$row['nama_gelombang']} ({$row['tahun_ajaran']})" : '-') . "</td>";
        echo "<td>{$channel}</td>";
        echo "<td class='text-center'>{$tgl_text}</td>";
        echo "<td class='text-right'>" . number_format($nom, 0, ',', '.') . "</td>";
        echo "</tr>";
        $no++;
    }

    echo "<tr class='total-row'>";
    echo "<td colspan='8' class='text-right'>TOTAL PENERIMAAN KAS</td>";
    echo "<td class='text-right'>Rp " . number_format($grand_total, 0, ',', '.') . "</td>";
    echo "</tr>";

    echo "<tr><td colspan='9'></td></tr>";
    echo "<tr style='font-size:11px;'><td colspan='9'><strong>Ringkasan Saluran Penerimaan Kas:</strong></td></tr>";
    echo "<tr style='font-size:11px;'><td colspan='3'>Rekening BSI: Rp " . number_format($tot_transfer, 0, ',', '.') . "</td><td colspan='3'>Kas Tunai Panitia: Rp " . number_format($tot_tunai, 0, ',', '.') . "</td><td colspan='3'>Lainnya / Manual: Rp " . number_format($tot_lainnya, 0, ',', '.') . "</td></tr>";

    echo "</tbody></table></body></html>";
    exit;
}

// -------------------------------------------------------------
// STATISTIK KEUANGAN GLOBAL (All-Time Dashboard Overview)
// -------------------------------------------------------------
$kpi_global = [
    'total_lunas' => 0,
    'total_uang_lunas' => 0,
    'transfer_lunas' => 0,
    'transfer_uang' => 0,
    'tunai_lunas' => 0,
    'tunai_uang' => 0,
    'lainnya_lunas' => 0,
    'lainnya_uang' => 0,
    'smp_lunas' => 0,
    'smp_uang' => 0,
    'sma_lunas' => 0,
    'sma_uang' => 0,
    'pending_verifikasi' => 0,
    'pending_uang' => 0,
    'belum_bayar' => 0,
    'belum_bayar_uang' => 0
];

try {
    $stmt_kpi = $db->query("
        SELECT 
            status_pembayaran,
            metode_pembayaran,
            jalur_pendaftaran,
            COUNT(*) as jumlah
        FROM pendaftaran
        GROUP BY status_pembayaran, metode_pembayaran, jalur_pendaftaran
    ");
    $kpi_rows = $stmt_kpi->fetchAll(PDO::FETCH_ASSOC);

    foreach ($kpi_rows as $row) {
        $status = $row['status_pembayaran'];
        $metode_bayar = $row['metode_pembayaran'];
        $jalur_santri = $row['jalur_pendaftaran'] ?? '';
        $cnt = (int)$row['jumlah'];
        $nom_satuan = get_nominal_santri($jalur_santri, $biaya_tes_smp, $biaya_tes_sma, $biaya_tes_def);
        $subtotal = $cnt * $nom_satuan;

        if ($status === 'Lunas') {
            $kpi_global['total_lunas'] += $cnt;
            $kpi_global['total_uang_lunas'] += $subtotal;

            if ($metode_bayar === 'Transfer') {
                $kpi_global['transfer_lunas'] += $cnt;
                $kpi_global['transfer_uang'] += $subtotal;
            } elseif ($metode_bayar === 'Tunai/Cash') {
                $kpi_global['tunai_lunas'] += $cnt;
                $kpi_global['tunai_uang'] += $subtotal;
            } else {
                $kpi_global['lainnya_lunas'] += $cnt;
                $kpi_global['lainnya_uang'] += $subtotal;
            }

            if (stripos($jalur_santri, 'SMP') !== false) {
                $kpi_global['smp_lunas'] += $cnt;
                $kpi_global['smp_uang'] += $subtotal;
            } elseif (stripos($jalur_santri, 'SMA') !== false) {
                $kpi_global['sma_lunas'] += $cnt;
                $kpi_global['sma_uang'] += $subtotal;
            }
        } elseif ($status === 'Menunggu Verifikasi') {
            $kpi_global['pending_verifikasi'] += $cnt;
            $kpi_global['pending_uang'] += $subtotal;
        } elseif ($status === 'Belum Bayar') {
            $kpi_global['belum_bayar'] += $cnt;
            $kpi_global['belum_bayar_uang'] += $subtotal;
        }
    }
} catch (Throwable $e) {
    // Abaikan
}

// -------------------------------------------------------------
// STATISTIK DATA TERFILTER (Filtered Context)
// -------------------------------------------------------------
$total_data_terfilter = 0;
$total_uang_terfilter = 0;

try {
    $stmt_tot = $db->prepare("
        SELECT 
            p.jalur_pendaftaran,
            p.metode_pembayaran
        FROM pendaftaran p
        LEFT JOIN gelombang g ON p.gelombang_id = g.id
        WHERE $where_sql
    ");
    $stmt_tot->execute($params);
    $all_filtered = $stmt_tot->fetchAll(PDO::FETCH_ASSOC);

    $total_data_terfilter = count($all_filtered);
    foreach ($all_filtered as $rf) {
        $nom = get_nominal_santri($rf['jalur_pendaftaran'] ?? '', $biaya_tes_smp, $biaya_tes_sma, $biaya_tes_def);
        $total_uang_terfilter += $nom;
    }
} catch (Throwable $e) {
    // Abaikan
}

// -------------------------------------------------------------
// SETUP PAGINATION & FETCH TABLE ROWS
// -------------------------------------------------------------
$limit_options = [10, 25, 50, 100];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, $limit_options)) {
    $limit = 25;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$total_pages = ceil($total_data_terfilter / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Fetch Data Tabel
$pendaftar_lunas = [];
try {
    $sql_table = "
        SELECT 
            p.id,
            p.no_registrasi,
            p.nama_santri,
            p.nisn,
            p.jalur_pendaftaran,
            p.metode_pembayaran,
            p.status_pembayaran,
            p.tgl_lunas,
            p.tgl_upload_bayar,
            p.bukti_bayar_tes,
            p.gelombang_id,
            g.nama_gelombang,
            g.tahun_ajaran
        FROM pendaftaran p
        LEFT JOIN gelombang g ON p.gelombang_id = g.id
        WHERE $where_sql
        ORDER BY p.tgl_lunas DESC, p.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt_table = $db->prepare($sql_table);
    foreach ($params as $k => $v) {
        $stmt_table->bindValue($k, $v);
    }
    $stmt_table->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_table->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_table->execute();
    $pendaftar_lunas = $stmt_table->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pendaftar_lunas = [];
}

// Hitung subtotal halaman aktif
$subtotal_halaman = 0;
foreach ($pendaftar_lunas as $pl) {
    $subtotal_halaman += get_nominal_santri($pl['jalur_pendaftaran'] ?? '', $biaya_tes_smp, $biaya_tes_sma, $biaya_tes_def);
}

// Cek status filter aktif
$has_active_filter = ($q !== '' || $filter_gelombang !== '' || $filter_metode !== '' || $filter_jalur !== '' || $filter_tgl_mulai !== '' || $filter_tgl_selesai !== '' || $quick_period !== '');

// URL parameter untuk ekspor & navigasi pagination
$current_filter_params = $_GET;
unset($current_filter_params['export'], $current_filter_params['page']);
$export_url_query = http_build_query(array_merge($current_filter_params, ['export' => 'excel']));

// Hitung persentase saluran
$pct_bsi = $kpi_global['total_uang_lunas'] > 0 ? round(($kpi_global['transfer_uang'] / $kpi_global['total_uang_lunas']) * 100) : 0;
$pct_tunai = $kpi_global['total_uang_lunas'] > 0 ? round(($kpi_global['tunai_uang'] / $kpi_global['total_uang_lunas']) * 100) : 0;
$pct_lain = max(0, 100 - $pct_bsi - $pct_tunai);

// Hitung persentase jenjang
$pct_smp = $kpi_global['total_uang_lunas'] > 0 ? round(($kpi_global['smp_uang'] / $kpi_global['total_uang_lunas']) * 100) : 0;
$pct_sma = $kpi_global['total_uang_lunas'] > 0 ? round(($kpi_global['sma_uang'] / $kpi_global['total_uang_lunas']) * 100) : 0;
$pct_lain_jenjang = max(0, 100 - $pct_smp - $pct_sma);
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<!-- Custom CSS: Anti-Double Scroll & Solid Colors Fallback -->
<style>
    /* 1. Hilangkan Double Scroll: Kunci Viewport Browser */
    html, body {
        height: 100% !important;
        max-height: 100vh !important;
        overflow: hidden !important;
    }

    /* 2. Scrollbar Ramping & Modern untuk Area Konten */
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(92, 62, 53, 0.22) transparent;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent !important;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: rgba(92, 62, 53, 0.22) !important;
        border-radius: 9999px !important;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: rgba(92, 62, 53, 0.45) !important;
    }

    /* 3. Warna Solid & Gradasi Brand Resmi Babul Jannah */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-dark { background-color: #3E2723 !important; }
    .bg-brand-deep { background-color: #4A3029 !important; }
    .bg-brand-secondary { background-color: #A17A6F !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .text-brand-secondary { color: #A17A6F !important; }

    /* Kartu Utama: Total Pemasukan Riil (Dipastikan Solid Coklat Mewah & Teks Kontras Putih) */
    .kpi-card-utama {
        background: linear-gradient(135deg, #4A3029 0%, #5C3E35 60%, #3E2723 100%) !important;
        color: #FFFFFF !important;
        border: 1px solid #6E493F !important;
        box-shadow: 0 4px 14px rgba(62, 39, 35, 0.18) !important;
    }
    .kpi-card-utama .kpi-label {
        color: #F3EDE6 !important;
    }
    .kpi-card-utama .kpi-number {
        color: #FFFFFF !important;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.25);
    }
    .kpi-card-utama .kpi-badge {
        background-color: rgba(255, 255, 255, 0.22) !important;
        color: #FFFFFF !important;
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
    }

    /* Kartu Kas Bank BSI */
    .kpi-card-bsi {
        background: #FFFFFF !important;
        border: 1px solid #BFDBFE !important;
    }
    .kpi-card-bsi .kpi-number {
        color: #1E40AF !important;
    }
    .kpi-card-bsi .kpi-badge {
        background-color: #EFF6FF !important;
        color: #1D4ED8 !important;
        border: 1px solid #BFDBFE !important;
    }

    /* Kartu Kas Tunai */
    .kpi-card-tunai {
        background: #FFFFFF !important;
        border: 1px solid #FDE68A !important;
    }
    .kpi-card-tunai .kpi-number {
        color: #B45309 !important;
    }
    .kpi-card-tunai .kpi-badge {
        background-color: #FFFBEB !important;
        color: #B45309 !important;
        border: 1px solid #FDE68A !important;
    }

    /* Kartu Potensi Tertunda */
    .kpi-card-pending {
        background: #FFFFFF !important;
        border: 1px solid #E9D5FF !important;
    }
    .kpi-card-pending .kpi-number {
        color: #6B21A8 !important;
    }
    .kpi-card-pending .kpi-badge {
        background-color: #FAF5FF !important;
        color: #7E22CE !important;
        border: 1px solid #E9D5FF !important;
    }

    /* Warna Bar Progress Terjamin Solid (Tidak Bergantung Purge Tailwind) */
    .bar-seg-bsi { background-color: #2563EB !important; }
    .bar-seg-tunai { background-color: #D97706 !important; }
    .bar-seg-lainnya { background-color: #9CA3AF !important; }

    .bar-seg-smp { background-color: #0D9488 !important; }
    .bar-seg-sma { background-color: #4F46E5 !important; }
    .bar-seg-kepondokan { background-color: #D97706 !important; }

    /* Badge Tabel Terjamin Solid & Kontras */
    .badge-smp-it {
        background-color: #CCFBF1 !important;
        color: #0F766E !important;
        border: 1px solid #99F6E4 !important;
    }
    .badge-sma-it {
        background-color: #E0E7FF !important;
        color: #3730A3 !important;
        border: 1px solid #C7D2FE !important;
    }
    .badge-ponpes {
        background-color: #FEF3C7 !important;
        color: #92400E !important;
        border: 1px solid #FDE68A !important;
    }
    .badge-chan-bsi {
        background-color: #EFF6FF !important;
        color: #1D4ED8 !important;
        border: 1px solid #BFDBFE !important;
    }
    .badge-chan-tunai {
        background-color: #FFFBEB !important;
        color: #B45309 !important;
        border: 1px solid #FDE68A !important;
    }
    .badge-chan-manual {
        background-color: #F3F4F6 !important;
        color: #4B5563 !important;
        border: 1px solid #E5E7EB !important;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Dashboard Main Grid (Hanya 1 Scrollbar Bersih Ini) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & REKONSILIASI BAR (COMPACT & CLEAN) -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Rekapitulasi Keuangan PSB</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">T.A <?= htmlspecialchars(function_exists('get_tahun_ajaran_aktif') ? get_tahun_ajaran_aktif() : '2026/2027') ?></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Rekonsiliasi mutasi Bank BSI, penerimaan tunai panitia, dan rincian biaya pendaftaran santri baru.
                    </p>
                </div>

                <!-- Action Buttons & Bank Rekening Badge -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Bank BSI Quick Copy Pill -->
                    <div class="flex items-center space-x-2 bg-emerald-50/90 border border-emerald-300/80 px-3 py-1.5 rounded-xl text-xs text-emerald-900 shadow-2xs">
                        <div class="w-6 h-6 rounded-md bg-emerald-700 text-white flex items-center justify-center font-black text-[10px] shadow-xs">
                            BSI
                        </div>
                        <div class="leading-tight">
                            <span class="text-[9px] text-emerald-700 block font-bold uppercase tracking-wider">Rekening Resmi</span>
                            <span class="font-mono font-black text-emerald-950 text-xs">7300750308</span>
                        </div>
                        <button type="button" onclick="navigator.clipboard.writeText('7300750308'); alert('Nomor rekening BSI 7300750308 berhasil disalin!');" class="p-1 text-emerald-700 hover:text-emerald-950 hover:bg-emerald-100 rounded-md transition" title="Salin No Rekening">
                            <i class="fa-regular fa-copy text-xs"></i>
                        </button>
                    </div>

                    <!-- Tombol Export Excel -->
                    <a href="?<?= htmlspecialchars($export_url_query) ?>" class="inline-flex items-center space-x-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Unduh Laporan Keuangan format Excel">
                        <i class="fa-solid fa-file-excel text-xs"></i>
                        <span>Export Excel (.xls)</span>
                    </a>

                    <!-- Tombol Cetak / Print -->
                    <button type="button" onclick="window.print()" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Cetak Dokumen Resmi">
                        <i class="fa-solid fa-print text-xs text-gray-500"></i>
                        <span>Cetak</span>
                    </button>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 2. KARTU METRIK UTAMA (SOLID COLORS & GUARANTEED CONTRAST) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- 1. Total Pemasukan Valid (Brand Gradient Solid Coklat Mewah) -->
                <div class="kpi-card-utama p-5 rounded-2xl flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider kpi-label flex items-center gap-1.5">
                                <i class="fa-solid fa-vault text-amber-300"></i> Total Pemasukan Riil
                            </span>
                            <span class="kpi-badge text-[10px] font-black px-2.5 py-0.5 rounded-full">
                                <?= number_format($kpi_global['total_lunas']) ?> Transaksi
                            </span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black tracking-tight kpi-number mb-1">
                            Rp <?= number_format($kpi_global['total_uang_lunas'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div class="pt-2.5 border-t border-white/15 flex items-center justify-between text-[11px] kpi-label">
                        <span>Penerimaan Sah Lunas</span>
                        <span class="text-emerald-300 font-extrabold"><i class="fa-solid fa-circle-check"></i> 100% Valid</span>
                    </div>
                </div>

                <!-- 2. Mutasi Masuk Bank BSI (Transfer) -->
                <div class="kpi-card-bsi p-5 rounded-2xl shadow-xs flex flex-col justify-between hover:border-blue-400 transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                <i class="fa-solid fa-building-columns text-blue-600"></i> Kas Bank BSI
                            </span>
                            <span class="kpi-badge text-[10px] font-black px-2.5 py-0.5 rounded-full">
                                <?= number_format($kpi_global['transfer_lunas']) ?> Santri
                            </span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black tracking-tight kpi-number mb-1">
                            Rp <?= number_format($kpi_global['transfer_uang'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div class="pt-2.5 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                        <span>Mutasi No. 7300750308</span>
                        <span class="text-blue-700 font-extrabold"><?= $pct_bsi ?>% Porsi Kas</span>
                    </div>
                </div>

                <!-- 3. Penerimaan Kasir Panitia (Tunai / Cash) -->
                <div class="kpi-card-tunai p-5 rounded-2xl shadow-xs flex flex-col justify-between hover:border-amber-400 transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                <i class="fa-solid fa-hand-holding-dollar text-amber-600"></i> Kas Tunai Loket
                            </span>
                            <span class="kpi-badge text-[10px] font-black px-2.5 py-0.5 rounded-full">
                                <?= number_format($kpi_global['tunai_lunas']) ?> Santri
                            </span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black tracking-tight kpi-number mb-1">
                            Rp <?= number_format($kpi_global['tunai_uang'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div class="pt-2.5 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                        <span>Fisik Kasir Panitia</span>
                        <span class="text-amber-700 font-extrabold"><?= $pct_tunai ?>% Porsi Kas</span>
                    </div>
                </div>

                <!-- 4. Piutang & Dana Tertunda (Belum Validasi / Belum Bayar) -->
                <div class="kpi-card-pending p-5 rounded-2xl shadow-xs flex flex-col justify-between hover:border-purple-400 transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                <i class="fa-solid fa-clock-rotate-left text-purple-600"></i> Potensi Tertunda
                            </span>
                            <span class="kpi-badge text-[10px] font-black px-2.5 py-0.5 rounded-full">
                                <?= number_format($kpi_global['pending_verifikasi'] + $kpi_global['belum_bayar']) ?> Anak
                            </span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black tracking-tight kpi-number mb-1">
                            Rp <?= number_format($kpi_global['pending_uang'] + $kpi_global['belum_bayar_uang'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div class="pt-2.5 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                        <span><?= $kpi_global['pending_verifikasi'] ?> Tunggu Slip, <?= $kpi_global['belum_bayar'] ?> Belum</span>
                        <a href="pendaftar.php?status=Menunggu+Verifikasi" class="text-purple-700 font-extrabold hover:underline">Verifikasi <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. VISUAL ANALYTICS: SOLID CHANNEL RATIO & JENJANG -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                <!-- Rasio Saluran Pembayaran (BSI vs Tunai) -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm border border-blue-100">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-xs sm:text-sm text-gray-900">Komposisi Saluran Pembayaran</h3>
                                <p class="text-[10px] text-gray-400">Rasio mutasi rekening BSI vs tunai kasir sekretariat</p>
                            </div>
                        </div>
                        <span class="text-xs font-black text-gray-700">
                            Rp <?= number_format($kpi_global['total_uang_lunas'], 0, ',', '.') ?>
                        </span>
                    </div>

                    <!-- Progress Bar Multi-Segment dengan Warna Terjamin -->
                    <div class="w-full bg-gray-200 h-3.5 rounded-full overflow-hidden flex shadow-inner mb-3">
                        <?php if ($pct_bsi > 0): ?>
                            <div style="width: <?= $pct_bsi ?>%" class="bar-seg-bsi h-full transition-all" title="BSI Transfer: <?= $pct_bsi ?>%"></div>
                        <?php endif; ?>
                        <?php if ($pct_tunai > 0): ?>
                            <div style="width: <?= $pct_tunai ?>%" class="bar-seg-tunai h-full transition-all" title="Tunai: <?= $pct_tunai ?>%"></div>
                        <?php endif; ?>
                        <?php if ($pct_lain > 0): ?>
                            <div style="width: <?= $pct_lain ?>%" class="bar-seg-lainnya h-full transition-all" title="Lainnya: <?= $pct_lain ?>%"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Legend Items -->
                    <div class="grid grid-cols-3 gap-2 text-xs pt-2 border-t border-gray-100">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-bsi flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">BSI (<?= $pct_bsi ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold">Rp <?= number_format($kpi_global['transfer_uang'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-tunai flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">Tunai (<?= $pct_tunai ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold">Rp <?= number_format($kpi_global['tunai_uang'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-lainnya flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">Lainnya (<?= $pct_lain ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold">Rp <?= number_format($kpi_global['lainnya_uang'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kontribusi per Jenjang Pendidikan -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm border border-teal-100">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-xs sm:text-sm text-gray-900">Penerimaan Berdasarkan Jenjang</h3>
                                <p class="text-[10px] text-gray-400">SMP: Rp <?= number_format($biaya_tes_smp, 0, ',', '.') ?> | SMA: Rp <?= number_format($biaya_tes_sma, 0, ',', '.') ?></p>
                            </div>
                        </div>
                        <span class="text-xs font-black text-gray-700">
                            <?= number_format($kpi_global['total_lunas']) ?> Santri Lunas
                        </span>
                    </div>

                    <!-- Progress Bar Multi-Segment dengan Warna Terjamin -->
                    <div class="w-full bg-gray-200 h-3.5 rounded-full overflow-hidden flex shadow-inner mb-3">
                        <?php if ($pct_smp > 0): ?>
                            <div style="width: <?= $pct_smp ?>%" class="bar-seg-smp h-full transition-all" title="SMP IT: <?= $pct_smp ?>%"></div>
                        <?php endif; ?>
                        <?php if ($pct_sma > 0): ?>
                            <div style="width: <?= $pct_sma ?>%" class="bar-seg-sma h-full transition-all" title="SMA IT: <?= $pct_sma ?>%"></div>
                        <?php endif; ?>
                        <?php if ($pct_lain_jenjang > 0): ?>
                            <div style="width: <?= $pct_lain_jenjang ?>%" class="bar-seg-kepondokan h-full transition-all" title="Kepondokan: <?= $pct_lain_jenjang ?>%"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Legend Items -->
                    <div class="grid grid-cols-3 gap-2 text-xs pt-2 border-t border-gray-100">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-smp flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">SMP IT (<?= $pct_smp ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold"><?= $kpi_global['smp_lunas'] ?> Santri - Rp <?= number_format($kpi_global['smp_uang'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-sma flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">SMA IT (<?= $pct_sma ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold"><?= $kpi_global['sma_lunas'] ?> Santri - Rp <?= number_format($kpi_global['sma_uang'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bar-seg-kepondokan flex-shrink-0"></span>
                            <div>
                                <div class="font-bold text-gray-800 text-[11px]">Kepondokan (<?= $pct_lain_jenjang ?>%)</div>
                                <div class="text-[10px] text-gray-500 font-semibold"><?= number_format($kpi_global['total_lunas'] - $kpi_global['smp_lunas'] - $kpi_global['sma_lunas']) ?> Santri</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 4. SMART FILTER & SEARCH TOOLBAR -->
            <!-- ======================================================== -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <form method="GET" action="laporan_keuangan.php" class="space-y-3.5">
                    
                    <!-- Quick Period Buttons Row -->
                    <div class="flex flex-wrap items-center justify-between gap-2.5 pb-2.5 border-b border-gray-100">
                        <div class="flex items-center space-x-2">
                            <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-regular fa-calendar-check text-brand-primary"></i> Periode Cepat:
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                <a href="laporan_keuangan.php" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= (!$has_active_filter || $quick_period === '') && $filter_tgl_mulai === '' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Semua Waktu
                                </a>
                                <a href="laporan_keuangan.php?quick_period=today" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $quick_period === 'today' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Hari Ini
                                </a>
                                <a href="laporan_keuangan.php?quick_period=yesterday" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $quick_period === 'yesterday' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Kemarin
                                </a>
                                <a href="laporan_keuangan.php?quick_period=this_week" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $quick_period === 'this_week' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Minggu Ini
                                </a>
                                <a href="laporan_keuangan.php?quick_period=this_month" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $quick_period === 'this_month' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Bulan Ini
                                </a>
                                <a href="laporan_keuangan.php?quick_period=last_month" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $quick_period === 'last_month' ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    Bulan Lalu
                                </a>
                            </div>
                        </div>

                        <!-- Reset Link -->
                        <?php if ($has_active_filter): ?>
                            <a href="laporan_keuangan.php" class="text-xs text-rose-600 hover:text-rose-800 font-extrabold flex items-center space-x-1 transition">
                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                <span>Reset Filter</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Filter Controls Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                        
                        <!-- Search Box (Col 4) -->
                        <div class="lg:col-span-4">
                            <label class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-1">Pencarian Santri</label>
                            <div class="relative">
                                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="No. Registrasi, Nama, atau NISN..." class="w-full pl-9 pr-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Filter Gelombang (Col 2) -->
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-1">Gelombang</label>
                            <select name="gelombang" class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition font-semibold text-gray-700">
                                <option value="">Semua Gelombang</option>
                                <?php foreach ($daftar_gelombang as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= $filter_gelombang == $g['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['nama_gelombang']) ?> (<?= htmlspecialchars($g['tahun_ajaran']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filter Saluran Bayar (Col 2) -->
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-1">Saluran Bayar</label>
                            <select name="metode" class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition font-semibold text-gray-700">
                                <option value="">Semua Saluran</option>
                                <option value="Transfer" <?= $filter_metode === 'Transfer' ? 'selected' : '' ?>>Transfer Bank BSI</option>
                                <option value="Tunai/Cash" <?= $filter_metode === 'Tunai/Cash' ? 'selected' : '' ?>>Tunai / Kasir Panitia</option>
                                <option value="Lainnya" <?= $filter_metode === 'Lainnya' ? 'selected' : '' ?>>Manual / Lainnya</option>
                            </select>
                        </div>

                        <!-- Filter Jenjang / Jalur (Col 2) -->
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-500 uppercase tracking-wider mb-1">Jenjang / Jalur</label>
                            <select name="jalur" class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition font-semibold text-gray-700">
                                <option value="">Semua Jenjang</option>
                                <option value="SMP IT" <?= $filter_jalur === 'SMP IT' ? 'selected' : '' ?>>SMP IT (Rp <?= number_format($biaya_tes_smp, 0, ',', '.') ?>)</option>
                                <option value="SMA IT" <?= $filter_jalur === 'SMA IT' ? 'selected' : '' ?>>SMA IT (Rp <?= number_format($biaya_tes_sma, 0, ',', '.') ?>)</option>
                                <option value="Kepondokan" <?= $filter_jalur === 'Kepondokan' ? 'selected' : '' ?>>Kepondokan</option>
                            </select>
                        </div>

                        <!-- Submit Button (Col 2) -->
                        <div class="lg:col-span-2 flex items-end">
                            <button type="submit" class="w-full bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3 rounded-xl text-xs shadow-xs transition flex items-center justify-center space-x-1.5 h-[36px]">
                                <i class="fa-solid fa-filter text-xs"></i>
                                <span>Terapkan Filter</span>
                            </button>
                        </div>

                    </div>

                    <!-- Date Range Inputs -->
                    <div class="flex flex-wrap items-center gap-2.5 pt-1 text-xs text-gray-600">
                        <span class="font-extrabold text-gray-500 text-[10px] uppercase tracking-wider">Rentang Tanggal Lunas:</span>
                        <div class="flex items-center space-x-1.5">
                            <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($filter_tgl_mulai) ?>" class="px-2.5 py-1 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-brand-primary">
                            <span class="text-gray-400">s/d</span>
                            <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($filter_tgl_selesai) ?>" class="px-2.5 py-1 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-brand-primary">
                        </div>
                        <button type="submit" class="px-2.5 py-1 text-[11px] font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                            Cari Tanggal
                        </button>
                    </div>

                </form>

                <!-- Filter Notification Pill -->
                <?php if ($has_active_filter): ?>
                    <div class="mt-3 pt-2.5 border-t border-gray-100 flex flex-wrap items-center justify-between text-xs bg-amber-50/70 border border-amber-200/60 p-2.5 rounded-xl text-amber-900">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-circle-info text-amber-600"></i>
                            <span>
                                Hasil Filter: <strong><?= number_format($total_data_terfilter) ?> transaksi lunas</strong> ditemukan. 
                                Total Kas: <strong class="text-emerald-700 font-extrabold">Rp <?= number_format($total_uang_terfilter, 0, ',', '.') ?></strong>
                            </span>
                        </div>
                        <a href="laporan_keuangan.php" class="font-black text-amber-800 hover:text-amber-950 underline text-xs">
                            Bersihkan Filter
                        </a>
                    </div>
                <?php endif; ?>

            </div>

            <!-- ======================================================== -->
            <!-- 5. TABEL REKAPITULASI KEUANGAN PSB -->
            <!-- ======================================================== -->
            <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xs overflow-hidden">
                
                <!-- Table Header Bar -->
                <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/50">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-sm">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-xs sm:text-sm text-gray-900">Rincian Transaksi Pembayaran Valid</h3>
                            <p class="text-[10px] text-gray-400">Daftar calon santri yang biaya seleksinya telah lunas</p>
                        </div>
                    </div>

                    <!-- Limit Row Selector -->
                    <div class="flex items-center space-x-2 text-xs text-gray-500 self-end sm:self-auto">
                        <span class="text-[11px] font-bold">Baris:</span>
                        <div class="flex space-x-1">
                            <?php foreach ($limit_options as $lo): ?>
                                <?php 
                                $limit_url_params = $_GET;
                                $limit_url_params['limit'] = $lo;
                                $limit_url_params['page'] = 1;
                                ?>
                                <a href="?<?= http_build_query($limit_url_params) ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition <?= $limit == $lo ? 'bg-brand-primary text-white shadow-2xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                    <?= $lo ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead>
                            <tr class="text-gray-400 font-bold uppercase tracking-wider text-[10px] border-b border-gray-100 bg-gray-50/60">
                                <th class="px-4 py-3 text-center w-10">No</th>
                                <th class="px-4 py-3">Santri & Registrasi</th>
                                <th class="px-4 py-3">Jenjang / Jalur</th>
                                <th class="px-4 py-3">Gelombang</th>
                                <th class="px-4 py-3 text-center">Saluran Bayar</th>
                                <th class="px-4 py-3">Waktu Lunas</th>
                                <th class="px-4 py-3 text-center">Struk / Bukti</th>
                                <th class="px-4 py-3 text-right">Nominal Biaya</th>
                                <th class="px-4 py-3 text-center w-14">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php if (count($pendaftar_lunas) > 0): ?>
                                <?php 
                                $iter = $offset + 1;
                                foreach ($pendaftar_lunas as $p): 
                                    $nominal = get_nominal_santri($p['jalur_pendaftaran'] ?? '', $biaya_tes_smp, $biaya_tes_sma, $biaya_tes_def);
                                    $has_bukti = !empty($p['bukti_bayar_tes']);
                                    $bukti_url = $has_bukti ? get_storage_url('', $p['bukti_bayar_tes'], '', true) : '';
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <!-- No -->
                                    <td class="px-4 py-3.5 text-center font-bold text-gray-400">
                                        <?= $iter++ ?>
                                    </td>

                                    <!-- Santri & Registrasi -->
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center space-x-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-brand-primary/10 text-brand-primary flex items-center justify-center font-black text-xs flex-shrink-0">
                                                <?= strtoupper(substr($p['nama_santri'], 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <a href="detail_pendaftar.php?id=<?= $p['id'] ?>" class="font-extrabold text-gray-900 hover:text-brand-primary transition block truncate max-w-[200px]" title="<?= htmlspecialchars($p['nama_santri']) ?>">
                                                    <?= htmlspecialchars($p['nama_santri']) ?>
                                                </a>
                                                <div class="flex items-center space-x-2 text-[10px] text-gray-400 font-mono mt-0.5">
                                                    <span class="text-brand-primary font-bold"><?= htmlspecialchars($p['no_registrasi']) ?></span>
                                                    <?php if (!empty($p['nisn'])): ?>
                                                        <span>• NISN: <?= htmlspecialchars($p['nisn']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Jenjang / Jalur -->
                                    <td class="px-4 py-3.5">
                                        <?php if (stripos($p['jalur_pendaftaran'], 'SMP') !== false): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black badge-smp-it">
                                                <i class="fa-solid fa-school text-[8px] mr-1"></i> SMP IT
                                            </span>
                                        <?php elseif (stripos($p['jalur_pendaftaran'], 'SMA') !== false): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black badge-sma-it">
                                                <i class="fa-solid fa-graduation-cap text-[8px] mr-1"></i> SMA IT
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black badge-ponpes">
                                                <i class="fa-solid fa-mosque text-[8px] mr-1"></i> <?= htmlspecialchars($p['jalur_pendaftaran'] ?? 'Reguler') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Gelombang -->
                                    <td class="px-4 py-3.5 text-gray-600">
                                        <?php if (!empty($p['nama_gelombang'])): ?>
                                            <span class="font-bold text-gray-800"><?= htmlspecialchars($p['nama_gelombang']) ?></span>
                                            <span class="text-[10px] text-gray-400 block"><?= htmlspecialchars($p['tahun_ajaran'] ?? '') ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Saluran Bayar -->
                                    <td class="px-4 py-3.5 text-center">
                                        <?php if ($p['metode_pembayaran'] === 'Transfer'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-black badge-chan-bsi shadow-2xs">
                                                <i class="fa-solid fa-building-columns text-[8px] mr-1 text-blue-600"></i> BSI Transfer
                                            </span>
                                        <?php elseif ($p['metode_pembayaran'] === 'Tunai/Cash'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-black badge-chan-tunai shadow-2xs">
                                                <i class="fa-solid fa-hand-holding-dollar text-[8px] mr-1 text-amber-600"></i> Tunai Kasir
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold badge-chan-manual">
                                                Panitia
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tanggal Lunas -->
                                    <td class="px-4 py-3.5 text-gray-600">
                                        <?php if (!empty($p['tgl_lunas'])): ?>
                                            <div class="font-bold text-gray-800"><?= date('d M Y', strtotime($p['tgl_lunas'])) ?></div>
                                            <div class="text-[10px] text-gray-400 font-mono"><?= date('H:i', strtotime($p['tgl_lunas'])) ?> WIB</div>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Bukti Struk -->
                                    <td class="px-4 py-3.5 text-center">
                                        <?php if ($has_bukti): ?>
                                            <button type="button" onclick="openStrukModal('<?= htmlspecialchars($bukti_url, ENT_QUOTES) ?>', '<?= htmlspecialchars($p['no_registrasi'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['nama_santri'], ENT_QUOTES) ?>', '<?= number_format($nominal, 0, ',', '.') ?>', '<?= $p['tgl_lunas'] ? date('d M Y, H:i', strtotime($p['tgl_lunas'])) : '-' ?>', '<?= htmlspecialchars($p['metode_pembayaran'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['bukti_bayar_tes'], ENT_QUOTES) ?>')" class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-100 hover:bg-brand-primary hover:text-white text-gray-700 transition group shadow-2xs" title="Pratinjau Foto Struk Pembayaran">
                                                <i class="fa-regular fa-image text-brand-primary group-hover:text-white transition"></i>
                                                <span>Lihat Struk</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-[10px] text-gray-400 italic">Kwitansi Loket</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Nominal Biaya -->
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="text-xs sm:text-sm font-black text-emerald-700">
                                            Rp <?= number_format($nominal, 0, ',', '.') ?>
                                        </span>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-4 py-3.5 text-center">
                                        <a href="detail_pendaftar.php?id=<?= $p['id'] ?>" class="w-6 h-6 rounded-md bg-gray-100 hover:bg-brand-primary hover:text-white text-gray-500 inline-flex items-center justify-center transition shadow-2xs" title="Buka Detail Santri">
                                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-gray-400">
                                        <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-300 flex items-center justify-center text-2xl mx-auto mb-2">
                                            <i class="fa-solid fa-receipt"></i>
                                        </div>
                                        <h4 class="text-sm font-bold text-gray-700 mb-0.5">Tidak Ada Data Pembayaran</h4>
                                        <p class="text-xs text-gray-500 max-w-sm mx-auto">
                                            Tidak ditemukan data transaksi lunas sesuai filter yang Anda terapkan.
                                        </p>
                                        <?php if ($has_active_filter): ?>
                                            <a href="laporan_keuangan.php" class="inline-flex items-center space-x-1 mt-3 px-3 py-1.5 bg-brand-primary text-white rounded-lg text-xs font-bold shadow-xs hover:bg-brand-deep transition">
                                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                                <span>Reset Filter</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                        <!-- Table Footer / Subtotal Rows -->
                        <?php if (count($pendaftar_lunas) > 0): ?>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50/80 font-bold text-xs text-gray-800">
                            <tr>
                                <td colspan="7" class="px-4 py-2.5 text-right uppercase tracking-wider text-[10px] text-gray-500">
                                    Subtotal Halaman Ini (<?= count($pendaftar_lunas) ?> Data):
                                </td>
                                <td class="px-4 py-2.5 text-right font-black text-gray-900 text-xs sm:text-sm">
                                    Rp <?= number_format($subtotal_halaman, 0, ',', '.') ?>
                                </td>
                                <td></td>
                            </tr>
                            <tr class="bg-brand-primary/5 text-brand-primary border-t border-brand-primary/10">
                                <td colspan="7" class="px-4 py-3 text-right uppercase tracking-wider text-[11px] font-black">
                                    Grand Total Sesuai Filter (<?= number_format($total_data_terfilter) ?> Transaksi):
                                </td>
                                <td class="px-4 py-3 text-right font-black text-sm sm:text-base text-emerald-800">
                                    Rp <?= number_format($total_uang_terfilter, 0, ',', '.') ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>

                    </table>
                </div>

                <!-- Pagination & Record Summary -->
                <div class="border-t border-gray-100 bg-gray-50/50 p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 font-medium">
                        Menampilkan <span class="font-extrabold text-gray-800"><?= $total_data_terfilter > 0 ? ($offset + 1) : 0 ?></span> s/d <span class="font-extrabold text-gray-800"><?= min($offset + $limit, $total_data_terfilter) ?></span> dari total <span class="font-extrabold text-brand-primary bg-brand-primary/10 px-2 py-0.5 rounded-md"><?= number_format($total_data_terfilter) ?></span> transaksi valid
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center space-x-1 text-xs">
                        <!-- Prev Button -->
                        <?php if ($page > 1): ?>
                            <?php 
                            $prev_params = $_GET;
                            $prev_params['page'] = $page - 1;
                            ?>
                            <a href="?<?= http_build_query($prev_params) ?>" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 transition shadow-2xs" title="Halaman Sebelumnya">
                                <i class="fa-solid fa-chevron-left text-[9px]"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left text-[9px]"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <div class="flex space-x-1 px-1">
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                $p1_params = $_GET;
                                $p1_params['page'] = 1;
                                echo '<a href="?' . http_build_query($p1_params) . '" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 font-bold text-gray-700 hover:bg-gray-100 transition shadow-2xs">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="w-7 h-7 flex items-center justify-center text-gray-400 font-bold">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): 
                                $pi_params = $_GET;
                                $pi_params['page'] = $i;
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-brand-primary border border-brand-primary font-black text-white shadow-xs"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query($pi_params) ?>" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 font-bold text-gray-700 hover:bg-gray-100 transition shadow-2xs"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="w-7 h-7 flex items-center justify-center text-gray-400 font-bold">...</span>';
                                }
                                $last_params = $_GET;
                                $last_params['page'] = $total_pages;
                                echo '<a href="?' . http_build_query($last_params) . '" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 font-bold text-gray-700 hover:bg-gray-100 transition shadow-2xs">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>

                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <?php 
                            $next_params = $_GET;
                            $next_params['page'] = $page + 1;
                            ?>
                            <a href="?<?= http_build_query($next_params) ?>" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 transition shadow-2xs" title="Halaman Selanjutnya">
                                <i class="fa-solid fa-chevron-right text-[9px]"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right text-[9px]"></i>
                            </span>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 6. MODAL PRATINJAU BUKTI PEMBAYARAN (IMAGE LIGHTBOX) -->
    <!-- ======================================================== -->
    <div id="strukModal" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100 transform transition-all flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/70">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-sm">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-gray-900" id="modalSantriNama">Pratinjau Bukti Pembayaran</h4>
                        <p class="text-[10px] text-gray-500 font-mono" id="modalNoReg">PPBJ-XXXX-XXX</p>
                    </div>
                </div>
                <button type="button" onclick="closeStrukModal()" class="w-8 h-8 rounded-full bg-gray-200/80 hover:bg-gray-300 text-gray-600 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Modal Body (Image) -->
            <div class="p-4 overflow-y-auto flex-1 flex flex-col items-center justify-center bg-gray-900/5 min-h-[250px]">
                <img id="modalImg" src="" alt="Bukti Transfer" class="max-h-[50vh] w-auto max-w-full rounded-xl object-contain shadow-md border border-gray-200 bg-white" onerror="this.onerror=null; this.src='../assets/img/default-struk.png';">
            </div>

            <!-- Modal Footer (Details & Buttons) -->
            <div class="p-4 sm:p-5 border-t border-gray-100 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div>
                    <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Nominal Divalidasi</span>
                    <span class="text-sm font-black text-emerald-700" id="modalNominal">Rp 0</span>
                </div>
                <div class="flex items-center space-x-2">
                    <a id="modalDownloadBtn" href="#" target="_blank" download class="px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs inline-flex items-center space-x-1.5 transition">
                        <i class="fa-solid fa-download"></i>
                        <span>Unduh Struk</span>
                    </a>
                    <a id="modalNewTabBtn" href="#" target="_blank" class="px-3.5 py-2 rounded-xl bg-brand-primary hover:bg-brand-deep text-white font-bold text-xs inline-flex items-center space-x-1.5 transition shadow-xs">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        <span>Buka Penuh</span>
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 7. CSS PRINT FORMAT (TAMPILAN CETAK RESMI PESANTREN) -->
    <!-- ======================================================== -->
    <style>
        @media print {
            aside, header, nav, form, .no-print, button, #strukModal {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, sans-serif !important;
            }
            main {
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            .print-header {
                display: block !important;
                border-bottom: 3px double #000000;
                padding-bottom: 15px;
                margin-bottom: 20px;
                text-align: center;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 10px !important;
            }
            th, td {
                border: 1px solid #333333 !important;
                padding: 6px 8px !important;
            }
            th {
                background-color: #f0f0f0 !important;
                color: #000000 !important;
            }
        }
        .print-header {
            display: none;
        }
    </style>

    <!-- Kop Surat Resmi Khusus Cetak -->
    <div class="print-header">
        <h2 style="font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase;">PONDOK PESANTREN MODERN BABUL JANNAH</h2>
        <p style="font-size: 10pt; margin: 3px 0 0 0;">Panitia Penerimaan Santri Baru (PSB) Tahun Ajaran <?= htmlspecialchars(function_exists('get_tahun_ajaran_aktif') ? get_tahun_ajaran_aktif() : '2026/2027') ?></p>
        <p style="font-size: 9pt; margin: 2px 0 0 0; color: #555;">Rekening Resmi: Bank Syariah Indonesia (BSI) 7300750308 a.n. Pesantren Babul Jannah</p>
        <h3 style="font-size: 12pt; font-weight: bold; margin: 12px 0 0 0; text-decoration: underline;">LAPORAN REKAPITULASI KEUANGAN PSB</h3>
        <p style="font-size: 8pt; margin: 3px 0 0 0;">Dicetak pada: <?= date('d F Y, H:i') ?> WIB oleh <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Bendahara') ?></p>
    </div>

    <!-- Javascript Modal Handlers -->
    <script>
        function openStrukModal(imageUrl, noReg, namaSantri, nominal, tglLunas, metode, fileName) {
            document.getElementById('modalSantriNama').textContent = namaSantri;
            document.getElementById('modalNoReg').textContent = noReg + ' • ' + (metode || 'Transfer');
            document.getElementById('modalNominal').textContent = 'Rp ' + nominal;
            
            const imgEl = document.getElementById('modalImg');
            imgEl.onerror = function() {
                if (fileName && !this.src.includes('../uploads/')) {
                    this.src = '../uploads/' + fileName;
                }
            };
            imgEl.src = imageUrl;

            const dlBtn = document.getElementById('modalDownloadBtn');
            dlBtn.href = imageUrl;

            const tabBtn = document.getElementById('modalNewTabBtn');
            tabBtn.href = imageUrl;

            const modal = document.getElementById('strukModal');
            modal.classList.remove('hidden');
        }

        function closeStrukModal() {
            const modal = document.getElementById('strukModal');
            modal.classList.add('hidden');
        }

        // Close on ESC key or background click
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStrukModal();
            }
        });

        document.getElementById('strukModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStrukModal();
            }
        });
    </script>

</body>
</html>
