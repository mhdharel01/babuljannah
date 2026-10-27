<?php
// admin/dashboard.php
require_once '../config/db.php';
require_once '../config/mail.php';

// Proteksi halaman admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$admin_nama = $_SESSION['admin_nama'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'panitia';

// Handle Aksi Cepat Verifikasi dari Dashboard
$success_msg = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = sanitize($_GET['action']);
    $id = (int)$_GET['id'];
    if ($id > 0 && $action === 'confirm_payment') {
        $stmt = $db->prepare("UPDATE pendaftaran SET status_pembayaran = 'Lunas', tgl_lunas = COALESCE(tgl_lunas, NOW()) WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $success_msg = "Pembayaran santri berhasil diverifikasi (Lunas) langsung dari Dashboard!";
        
        // Kirim notifikasi email ke santri
        $stmt_m = $db->prepare("SELECT no_registrasi, nama_santri, wa_ayah, email FROM pendaftaran WHERE id = :id");
        $stmt_m->execute(['id' => $id]);
        $s_mail = $stmt_m->fetch();
        if ($s_mail) send_email_verifikasi_lunas($s_mail);
    }
}

// 1. Ambil Data Statistik Utama
$stmt_tot = $db->query("SELECT COUNT(*) as count FROM pendaftaran");
$total_pendaftar = (int)($stmt_tot->fetch()['count'] ?? 0);

$stmt_ver = $db->query("SELECT COUNT(*) as count FROM pendaftaran WHERE status_pembayaran = 'Menunggu Verifikasi'");
$menunggu_verifikasi = (int)($stmt_ver->fetch()['count'] ?? 0);

$stmt_lun = $db->query("SELECT COUNT(*) as count FROM pendaftaran WHERE status_pembayaran = 'Lunas'");
$lunas_tes = (int)($stmt_lun->fetch()['count'] ?? 0);

$stmt_lul = $db->query("SELECT COUNT(*) as count FROM pendaftaran WHERE status_seleksi = 'Lulus'");
$lulus_seleksi = (int)($stmt_lul->fetch()['count'] ?? 0);

// Pendaftar per jenjang (SMP vs SMA)
$stmt_jen = $db->query("SELECT jalur_pendaftaran, COUNT(*) as count FROM pendaftaran GROUP BY jalur_pendaftaran");
$jenjang_map = [];
foreach ($stmt_jen->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $jenjang_map[$row['jalur_pendaftaran']] = (int)$row['count'];
}
$count_smp = $jenjang_map['SMP IT'] ?? 0;
$count_sma = $jenjang_map['SMA IT'] ?? 0;

// 2. Ambil data gelombang pendaftaran saat ini
$stmt_gel = $db->query("SELECT * FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll(PDO::FETCH_ASSOC);

$active_gelombang = null;
foreach ($gelombang_list as $g) {
    if ($g['status'] === 'aktif') {
        $active_gelombang = $g;
        break;
    }
}

// 3. Ambil 5 pendaftar terbaru
$stmt_rec = $db->query("SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id ORDER BY p.id DESC LIMIT 5");
$recent_pendaftar = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

// 4. Data Grafik / Chart
// a. Tren Pendaftaran 7 Hari Terakhir (Kontinu 7 hari penuh)
$stmt_trend = $db->query("SELECT DATE(created_at) as tgl, COUNT(*) as jumlah FROM pendaftaran WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY tgl ASC");
$trend_raw = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);

$last_7_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $last_7_days[$date_key] = 0;
}
foreach ($trend_raw as $tr) {
    if (isset($last_7_days[$tr['tgl']])) {
        $last_7_days[$tr['tgl']] = (int)$tr['jumlah'];
    }
}
$trend_labels = array_map(function($d) {
    return date('d M', strtotime($d));
}, array_keys($last_7_days));
$trend_values = array_values($last_7_days);

// b. Komposisi Jalur Pendaftaran
$stmt_jalur = $db->query("SELECT jalur_pendaftaran, COUNT(*) as jumlah FROM pendaftaran GROUP BY jalur_pendaftaran");
$jalur_data = $stmt_jalur->fetchAll(PDO::FETCH_ASSOC);
$jalur_labels = [];
$jalur_values = [];
foreach ($jalur_data as $jd) {
    $jalur_labels[] = $jd['jalur_pendaftaran'] ?: 'Lainnya';
    $jalur_values[] = (int)$jd['jumlah'];
}

// c. Komposisi Jenis Kelamin
$stmt_jk = $db->query("SELECT jk, COUNT(*) as jumlah FROM pendaftaran GROUP BY jk");
$jk_raw = $stmt_jk->fetchAll(PDO::FETCH_ASSOC);
$jk_counts = ['Putra' => 0, 'Putri' => 0];
foreach ($jk_raw as $j) {
    $v = strtoupper(trim($j['jk'] ?? ''));
    if ($v === 'L' || strpos($v, 'LAKI') !== false) {
        $jk_counts['Putra'] += (int)$j['jumlah'];
    } elseif ($v === 'P' || strpos($v, 'PEREMPUAN') !== false) {
        $jk_counts['Putri'] += (int)$j['jumlah'];
    }
}
$jk_labels = ['Putra', 'Putri'];
$jk_values = [$jk_counts['Putra'], $jk_counts['Putri']];

// d. Demografi Asal (Hierarki: Provinsi -> Kabupaten -> Kecamatan)
$stmt_demo = $db->query("SELECT provinsi, kabupaten, kecamatan, COUNT(*) as jumlah FROM pendaftaran WHERE provinsi IS NOT NULL AND provinsi != '' GROUP BY provinsi, kabupaten, kecamatan ORDER BY provinsi, jumlah DESC");
$demo_raw = $stmt_demo->fetchAll(PDO::FETCH_ASSOC);

$prov_data_map = [];
$demo_tree = [];

foreach ($demo_raw as $row) {
    $prov = strtoupper(trim($row['provinsi']));
    $kab = $row['kabupaten'] ?: 'Lainnya';
    $kec = $row['kecamatan'] ?: 'Lainnya';
    $jml = (int)$row['jumlah'];
    
    if (!isset($prov_data_map[$prov])) $prov_data_map[$prov] = 0;
    $prov_data_map[$prov] += $jml;
    
    if (!isset($demo_tree[$prov])) $demo_tree[$prov] = ['total' => 0, 'kabupaten' => []];
    $demo_tree[$prov]['total'] += $jml;
    
    if (!isset($demo_tree[$prov]['kabupaten'][$kab])) $demo_tree[$prov]['kabupaten'][$kab] = ['total' => 0, 'kecamatan' => []];
    $demo_tree[$prov]['kabupaten'][$kab]['total'] += $jml;
    $demo_tree[$prov]['kabupaten'][$kab]['kecamatan'][$kec] = $jml;
}
arsort($prov_data_map);
uasort($demo_tree, function($a, $b) { return $b['total'] <=> $a['total']; });

// e. Top Kabupaten
$stmt_kab = $db->query("SELECT kabupaten, COUNT(*) as jumlah FROM pendaftaran WHERE kabupaten IS NOT NULL AND kabupaten != '' GROUP BY kabupaten ORDER BY jumlah DESC LIMIT 50");
$top_kab = $stmt_kab->fetchAll(PDO::FETCH_ASSOC);

// Server-side Coordinate Enrichment for instant map rendering
$regencies_file = __DIR__ . '/../assets/data/regencies_coords.json';
$regency_coords_lookup = [];
if (file_exists($regencies_file)) {
    $raw_coords = json_decode(file_get_contents($regencies_file), true);
    if (is_array($raw_coords)) {
        foreach ($raw_coords as $rc) {
            $lat = (float)$rc['latitude'];
            $lng = (float)$rc['longitude'];
            $rc_name = strtoupper(trim($rc['name']));
            $regency_coords_lookup[$rc_name] = [$lat, $lng];
            $clean_rc = trim(preg_replace('/^(KABUPATEN|KOTA ADMINISTRASI|KOTA ADM\.|KOTA|KODYA)\s+/i', '', $rc_name));
            if (!isset($regency_coords_lookup[$clean_rc])) {
                $regency_coords_lookup[$clean_rc] = [$lat, $lng];
            }
        }
    }
}

foreach ($top_kab as &$kab_item) {
    $raw_k = strtoupper(trim($kab_item['kabupaten']));
    $clean_k = trim(preg_replace('/^(KABUPATEN|KAB\.|KOTA ADMINISTRASI|KOTA ADM\.|KOTA|KODYA)\s+/i', '', $raw_k));
    $found_coords = $regency_coords_lookup[$raw_k] ?? $regency_coords_lookup[$clean_k] ?? null;
    if ($found_coords) {
        $kab_item['lat'] = $found_coords[0];
        $kab_item['lng'] = $found_coords[1];
    }
}
unset($kab_item);

// f. Top Kecamatan
$stmt_kec = $db->query("SELECT kecamatan, COUNT(*) as jumlah FROM pendaftaran WHERE kecamatan IS NOT NULL AND kecamatan != '' GROUP BY kecamatan ORDER BY jumlah DESC LIMIT 50");
$top_kec = $stmt_kec->fetchAll(PDO::FETCH_ASSOC);

$extra_js = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
';
$extra_css = '
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .leaflet-popup-content-wrapper {
            border-radius: 0.875rem !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15) !important;
            border: 1px solid #D7CCC8 !important;
            padding: 0 !important;
        }
        .leaflet-popup-content {
            margin: 12px 14px !important;
            line-height: 1.4 !important;
        }
        .leaflet-container {
            font-family: \'Plus Jakarta Sans\', sans-serif !important;
        }
    </style>
';

include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522]">

    <!-- Desktop Sidebar Navigation -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include 'includes/header.php'; ?>

        <!-- Scrollable Dashboard Main Grid -->
        <main class="flex-grow p-4 sm:p-6 lg:p-7 overflow-y-auto space-y-6 custom-scrollbar bg-gray-50/70">

            <!-- Success Alert (Aksi Cepat) -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-50 border-2 border-emerald-400 text-emerald-900 px-5 py-3.5 rounded-2xl shadow-sm flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-xl flex-shrink-0"></i>
                        <span class="font-bold text-xs sm:text-sm"><?= $success_msg ?></span>
                    </div>
                    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-950 font-bold p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Executive Welcome Hero Banner (Brand Primary Gradient #4A3029 -> #5C3E35) -->
            <div class="rounded-3xl p-6 sm:p-7 shadow-lg text-white relative overflow-hidden" style="background: linear-gradient(135deg, #3E2723 0%, #5C3E35 60%, #4A3029 100%); border: 1px solid #6E493F;">
                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="space-y-2.5 max-w-2xl">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full text-[11px] font-bold text-white bg-black/25 border border-white/20">
                            <i class="fa-solid fa-mosque text-xs text-[#E7D8CF]"></i>
                            <span>Sistem PSB Pondok Pesantren Babul Jannah Dumai</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                            Ahlan wa Sahlan, <?= htmlspecialchars($admin_nama) ?>! 👋
                        </h1>
                        <p class="text-xs sm:text-sm text-[#F3EDE6] leading-relaxed font-normal">
                            Pantau pergerakan pendaftaran calon santri baru, kelola validasi biaya seleksi, dan tinjau sebaran demografi asal pendaftar secara terpadu.
                        </p>
                        <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] font-semibold text-white/90">
                            <span class="bg-black/30 px-3 py-1 rounded-lg border border-white/15 flex items-center space-x-1.5">
                                <i class="fa-regular fa-calendar-check text-[#E7D8CF]"></i>
                                <span>T.A <?= $active_gelombang ? htmlspecialchars($active_gelombang['tahun_ajaran']) : '2026/2027' ?></span>
                            </span>
                            <span class="bg-black/30 px-3 py-1 rounded-lg border border-white/15 flex items-center space-x-1.5">
                                <i class="fa-solid fa-wave-square text-[#E7D8CF]"></i>
                                <span><?= $active_gelombang ? htmlspecialchars($active_gelombang['nama_gelombang']) : 'Gelombang Reguler' ?> (Aktif)</span>
                            </span>
                            <span class="bg-emerald-600/40 text-emerald-200 px-3 py-1 rounded-lg border border-emerald-400/40 flex items-center space-x-1.5 font-bold">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>Portal Aktif</span>
                            </span>
                        </div>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="flex flex-wrap sm:flex-nowrap lg:flex-col gap-2.5 flex-shrink-0">
                        <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                        <a href="tambah_pendaftar.php" class="inline-flex items-center justify-center space-x-2 bg-brand-secondary hover:bg-[#8D6559] text-white font-extrabold text-xs px-5 py-3 rounded-2xl shadow-md transition-all duration-150">
                            <i class="fa-solid fa-user-plus text-sm"></i>
                            <span>Input Santri Baru</span>
                        </a>
                        <a href="scan.php" class="inline-flex items-center justify-center space-x-2 bg-white/15 hover:bg-white/25 text-white font-bold text-xs px-5 py-3 rounded-2xl border border-white/20 transition-all duration-150">
                            <i class="fa-solid fa-qrcode text-sm"></i>
                            <span>Scan QR Presensi</span>
                        </a>
                        <?php endif; ?>
                        <a href="pendaftar.php" class="inline-flex items-center justify-center space-x-2 bg-white/15 hover:bg-white/25 text-white font-bold text-xs px-5 py-3 rounded-2xl border border-white/20 transition-all duration-150">
                            <i class="fa-solid fa-list-check text-sm"></i>
                            <span>Data Calon Santri</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Smart Attention Alert (Menunggu Verifikasi Pembayaran) -->
            <?php if ($menunggu_verifikasi > 0): ?>
                <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-lg flex-shrink-0 shadow-md">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-black uppercase tracking-wider text-amber-900">Perhatian Dibutuhkan</span>
                                <span class="bg-amber-200 text-amber-900 text-[10px] font-extrabold px-2 py-0.5 rounded-full"><?= $menunggu_verifikasi ?> Berkas Menunggu</span>
                            </div>
                            <p class="text-xs text-amber-800 font-medium mt-0.5">
                                Santri telah mengunggah bukti transfer biaya seleksi pendaftaran dan menunggu validasi bendahara/panitia.
                            </p>
                        </div>
                    </div>
                    <a href="pendaftar.php?filter_bayar=Menunggu+Verifikasi" class="inline-flex items-center space-x-1.5 bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl shadow-sm transition flex-shrink-0">
                        <span>Periksa Bukti Bayar</span>
                        <i class="fa-solid fa-arrow-right text-[11px]"></i>
                    </a>
                </div>
            <?php endif; ?>

            <!-- 4 Elevated Metric KPI Cards (Clean, High-Contrast, No Clutter) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- Card 1: Total Pendaftar -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Total Pendaftar</span>
                        <div class="w-11 h-11 rounded-xl bg-[#5C3E35]/10 text-brand-primary flex items-center justify-center text-xl flex-shrink-0 shadow-sm border border-[#5C3E35]/20">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-[#2F2522] tracking-tight">
                            <?= number_format($total_pendaftar) ?>
                        </div>
                        <div class="flex items-center space-x-2 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <span class="px-2 py-0.5 bg-gray-100 rounded-md font-bold text-gray-800">SMP: <?= $count_smp ?></span>
                            <span class="px-2 py-0.5 bg-gray-100 rounded-md font-bold text-gray-800">SMA: <?= $count_sma ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Menunggu Verifikasi -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Verifikasi Bayar</span>
                        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl flex-shrink-0 shadow-sm border border-amber-200">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-amber-600 tracking-tight">
                            <?= number_format($menunggu_verifikasi) ?>
                        </div>
                        <div class="flex items-center space-x-1.5 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold">
                            <?php if ($menunggu_verifikasi > 0): ?>
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                <span class="text-amber-800 font-bold">Perlu validasi struk transfer</span>
                            <?php else: ?>
                                <i class="fa-solid fa-check text-emerald-600 text-xs"></i>
                                <span class="text-emerald-700 font-semibold">Semua telah diverifikasi</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Lunas Biaya Seleksi -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Lunas Biaya Tes</span>
                        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl flex-shrink-0 shadow-sm border border-emerald-200">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-emerald-700 tracking-tight">
                            <?= number_format($lunas_tes) ?>
                        </div>
                        <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <span>Total Kas Masuk:</span>
                            <span class="font-bold text-emerald-800">Rp <?= number_format($lunas_tes * 350000, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Lulus Seleksi -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Lulus Seleksi</span>
                        <div class="w-11 h-11 rounded-xl bg-brand-secondary/20 text-brand-primary flex items-center justify-center text-xl flex-shrink-0 shadow-sm border border-brand-secondary/30">
                            <i class="fa-solid fa-award"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-brand-primary tracking-tight">
                            <?= number_format($lulus_seleksi) ?>
                        </div>
                        <div class="flex items-center space-x-1.5 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <i class="fa-solid fa-chart-pie text-brand-secondary text-xs"></i>
                            <span><?= $total_pendaftar > 0 ? round(($lulus_seleksi / $total_pendaftar) * 100) : 0 ?>% dari total pendaftar</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Charts Row (Tren Line Chart & Composition) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Trend Pendaftaran 7 Hari Terakhir (Col 8) -->
                <div class="lg:col-span-8 bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3.5 border-b border-gray-100 gap-2">
                        <div>
                            <h2 class="text-sm sm:text-base font-extrabold text-gray-900 flex items-center">
                                <i class="fa-solid fa-chart-line text-brand-primary mr-2.5 text-base"></i>
                                Tren Pendaftaran Calon Santri
                            </h2>
                            <p class="text-xs text-gray-500 font-medium">Jumlah pendaftar harian selama 7 hari terakhir</p>
                        </div>
                        <span class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 self-start sm:self-auto border border-gray-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-primary"></span>
                            <span>7 Hari Terakhir</span>
                        </span>
                    </div>

                    <div class="relative h-64 sm:h-72 w-full mt-4">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Komposisi Pendaftar (Jalur & Gender) (Col 4) -->
                <div class="lg:col-span-4 space-y-5">
                    
                    <!-- Jalur Pendaftaran Doughnut -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h2 class="text-xs sm:text-sm font-extrabold text-gray-900 flex items-center">
                                <i class="fa-solid fa-graduation-cap text-brand-secondary mr-2 text-sm"></i>
                                Jalur Pendaftaran
                            </h2>
                            <span class="text-[11px] font-bold text-gray-500"><?= count($jalur_labels) ?> Kategori</span>
                        </div>
                        <div class="relative h-44 w-full flex justify-center items-center mt-3">
                            <canvas id="jalurChart"></canvas>
                        </div>
                    </div>

                    <!-- Rasio Jenis Kelamin -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h2 class="text-xs sm:text-sm font-extrabold text-gray-900 flex items-center">
                                <i class="fa-solid fa-venus-mars text-blue-600 mr-2 text-sm"></i>
                                Komposisi Santri
                            </h2>
                            <div class="flex items-center space-x-2 text-[11px] font-bold">
                                <span class="text-blue-600">L: <?= $jk_counts['Putra'] ?></span>
                                <span class="text-gray-300">&bull;</span>
                                <span class="text-pink-600">P: <?= $jk_counts['Putri'] ?></span>
                            </div>
                        </div>
                        <div class="relative h-44 w-full flex justify-center items-center mt-3">
                            <canvas id="jkChart"></canvas>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Demografi Wilayah & Leaflet Map Row -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Map Container (Col 8) -->
                <div class="lg:col-span-8 bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3.5 border-b border-gray-100 gap-2">
                        <div>
                            <h2 class="text-sm sm:text-base font-extrabold text-gray-900 flex items-center">
                                <i class="fa-solid fa-map-location-dot text-brand-primary mr-2.5 text-base"></i>
                                Peta Demografi Asal Santri
                            </h2>
                            <p class="text-xs text-gray-500 font-medium">Peta sebaran daerah asal pendaftar di seluruh Indonesia</p>
                        </div>
                        <div class="flex space-x-1 bg-gray-100 p-1 rounded-xl text-xs font-bold self-start sm:self-auto border border-gray-200">
                            <button onclick="switchMapLevel('provinsi')" id="btn-map-provinsi" class="py-1 px-3 rounded-lg bg-white shadow-sm text-brand-primary transition-all font-bold">Provinsi</button>
                            <button onclick="switchMapLevel('kota')" id="btn-map-kota" class="py-1 px-3 rounded-lg text-gray-600 hover:text-gray-900 transition-all font-semibold">Kota/Kab</button>
                        </div>
                    </div>

                    <div class="relative h-[380px] w-full rounded-xl overflow-hidden border border-gray-200 mt-4 shadow-inner" style="z-index: 1;">
                        <div id="provMap" class="w-full h-full"></div>
                    </div>
                </div>

                <!-- Detailed Breakdown Container (Col 4) -->
                <div class="lg:col-span-4 bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col h-[460px]">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <h2 class="text-xs sm:text-sm font-extrabold text-gray-900 flex items-center">
                            <i class="fa-solid fa-layer-group mr-2 text-brand-secondary"></i> Detail Persebaran
                        </h2>
                        <span class="text-[11px] font-bold text-gray-500">Total <?= count($prov_data_map) ?> Prov</span>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="flex space-x-1 my-3 bg-gray-100 p-1 rounded-xl text-xs font-bold border border-gray-200">
                        <button onclick="switchTab('tab-hierarki')" id="btn-tab-hierarki" class="flex-1 py-1.5 px-2 rounded-lg bg-white shadow-sm text-brand-primary transition-all font-bold">Hierarki</button>
                        <button onclick="switchTab('tab-kota')" id="btn-tab-kota" class="flex-1 py-1.5 px-2 rounded-lg text-gray-600 hover:text-gray-900 transition-all font-semibold">Kota/Kab</button>
                        <button onclick="switchTab('tab-kec')" id="btn-tab-kec" class="flex-1 py-1.5 px-2 rounded-lg text-gray-600 hover:text-gray-900 transition-all font-semibold">Kecamatan</button>
                    </div>

                    <div class="flex-grow overflow-hidden relative mt-1">
                        <!-- Tab Hierarki -->
                        <div id="tab-hierarki" class="absolute inset-0 overflow-y-auto pr-1 space-y-3 custom-scrollbar transition-opacity duration-200 opacity-100">
                            <?php if (empty($demo_tree)): ?>
                                <div class="flex flex-col items-center justify-center h-full text-center py-12 px-4 text-gray-400">
                                    <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-600 flex items-center justify-center text-xl mb-3 shadow-sm border border-gray-200">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                    </div>
                                    <p class="text-xs font-bold text-gray-700">Belum Ada Data Wilayah</p>
                                    <p class="text-[11px] text-gray-400 mt-1">Data provinsi &amp; kabupaten santri akan otomatis muncul di sini.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($demo_tree as $provName => $provInfo): ?>
                                    <div class="bg-gray-50 rounded-xl p-3.5 border border-gray-200">
                                        <div class="flex justify-between items-center mb-2 pb-1.5 border-b border-gray-200">
                                            <h3 class="font-extrabold text-brand-primary text-xs uppercase tracking-tight"><?= $provName ?></h3>
                                            <span class="bg-brand-primary text-white text-[10px] font-black px-2 py-0.5 rounded-full"><?= $provInfo['total'] ?> Santri</span>
                                        </div>
                                        <div class="space-y-2 mt-2">
                                            <?php foreach($provInfo['kabupaten'] as $kabName => $kabInfo): ?>
                                                <div class="pl-2.5 border-l-2 border-brand-secondary">
                                                    <div class="flex justify-between text-xs font-bold text-gray-800">
                                                        <span><?= $kabName ?></span>
                                                        <span class="text-brand-primary font-extrabold"><?= $kabInfo['total'] ?></span>
                                                    </div>
                                                    <ul class="pl-2 space-y-0.5 mt-1">
                                                        <?php foreach($kabInfo['kecamatan'] as $kecName => $kecTotal): ?>
                                                            <li class="flex justify-between text-[11px] text-gray-600 font-medium">
                                                                <span>&bull; <?= $kecName ?></span>
                                                                <span class="font-bold text-gray-700"><?= $kecTotal ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Kota -->
                        <div id="tab-kota" class="absolute inset-0 overflow-y-auto pr-1 space-y-2 custom-scrollbar transition-opacity duration-200 opacity-0 pointer-events-none">
                            <?php if (empty($top_kab)): ?>
                                <div class="flex flex-col items-center justify-center h-full text-center py-12 px-4 text-gray-400">
                                    <p class="text-xs font-semibold text-gray-500">Belum ada data kota/kabupaten</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($top_kab as $kab): ?>
                                <div class="flex justify-between items-center bg-gray-50 p-2.5 rounded-xl border border-gray-200 hover:bg-gray-100 transition">
                                    <span class="text-xs font-bold text-gray-800 uppercase"><?= $kab['kabupaten'] ?></span>
                                    <span class="bg-brand-secondary/20 text-brand-primary text-xs font-black px-2.5 py-0.5 rounded-full border border-brand-secondary/40"><?= $kab['jumlah'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Kecamatan -->
                        <div id="tab-kec" class="absolute inset-0 overflow-y-auto pr-1 space-y-2 custom-scrollbar transition-opacity duration-200 opacity-0 pointer-events-none">
                            <?php if (empty($top_kec)): ?>
                                <div class="flex flex-col items-center justify-center h-full text-center py-12 px-4 text-gray-400">
                                    <p class="text-xs font-semibold text-gray-500">Belum ada data kecamatan</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($top_kec as $kec): ?>
                                <div class="flex justify-between items-center bg-gray-50 p-2.5 rounded-xl border border-gray-200 hover:bg-gray-100 transition">
                                    <span class="text-xs font-bold text-gray-800 uppercase"><?= $kec['kecamatan'] ?></span>
                                    <span class="bg-[#5C3E35]/10 text-brand-primary text-xs font-black px-2.5 py-0.5 rounded-full border border-[#5C3E35]/20"><?= $kec['jumlah'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Splits (Recent Registrations & Active Waves) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Left: Recent Pendaftar (Col 8) -->
                <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <div>
                            <h2 class="text-sm sm:text-base font-extrabold text-gray-900 flex items-center">
                                <i class="fa-solid fa-clock-rotate-left text-brand-secondary mr-2 text-base"></i>
                                Pendaftar Terbaru
                            </h2>
                            <p class="text-xs text-gray-500 font-medium">5 calon santri yang baru mendaftar ke sistem</p>
                        </div>
                        <a href="pendaftar.php" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 hover:bg-brand-primary hover:text-white text-gray-700 transition border border-gray-200">
                            <span>Lihat Semua</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 uppercase tracking-wider font-extrabold text-[11px]">
                                    <th class="px-4 py-3 rounded-l-lg">No. Registrasi</th>
                                    <th class="px-4 py-3">Calon Santri</th>
                                    <th class="px-4 py-3">Jalur</th>
                                    <th class="px-4 py-3">Status Bayar</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3 text-center rounded-r-lg">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                                <?php if (count($recent_pendaftar) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                            <i class="fa-solid fa-folder-open text-3xl mb-2 text-gray-300 block"></i>
                                            Belum ada santri terdaftar di sistem.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_pendaftar as $p): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3.5">
                                                <span class="font-mono text-xs font-extrabold text-gray-900 bg-gray-100 px-2 py-1 rounded-md border border-gray-200">
                                                    <?= htmlspecialchars($p['no_registrasi']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center space-x-2.5">
                                                    <div class="w-7 h-7 rounded-full bg-brand-primary text-white font-bold text-xs flex items-center justify-center flex-shrink-0">
                                                        <?= htmlspecialchars(strtoupper(substr($p['nama_santri'], 0, 1))) ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-bold text-gray-900 block text-xs uppercase leading-tight"><?= htmlspecialchars($p['nama_santri']) ?></span>
                                                        <span class="text-[10px] text-gray-400 font-medium"><?= htmlspecialchars($p['kabupaten'] ?: 'Indonesia') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#5C3E35]/10 text-brand-primary border border-[#5C3E35]/20">
                                                    <?= htmlspecialchars($p['jalur_pendaftaran']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <?php if ($p['status_pembayaran'] === 'Belum Bayar'): ?>
                                                    <span class="bg-gray-100 text-gray-700 text-[11px] px-2.5 py-1 rounded-full border border-gray-200 font-bold inline-flex items-center">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span> Belum Bayar
                                                    </span>
                                                <?php elseif ($p['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                                                    <div class="inline-flex items-center space-x-2">
                                                        <span class="bg-amber-100 text-amber-900 text-[11px] px-2.5 py-1 rounded-full border border-amber-300 font-bold inline-flex items-center">
                                                            <i class="fa-solid fa-spinner animate-spin mr-1.5 text-amber-600"></i> Menunggu
                                                        </span>
                                                        <button onclick="confirmQuickPayment(event, <?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama_santri'])) ?>')" class="bg-brand-primary hover:bg-brand-deep text-white text-[10px] px-2.5 py-1 rounded-lg font-bold shadow-sm transition inline-flex items-center space-x-1" title="Validasi Menjadi Lunas">
                                                            <i class="fa-solid fa-check"></i>
                                                            <span>Validasi</span>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="bg-emerald-50 text-emerald-800 text-[11px] px-2.5 py-1 rounded-full border border-emerald-300 font-bold inline-flex items-center">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span> Lunas
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 text-xs text-gray-500 font-medium">
                                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                            </td>
                                            <td class="px-4 py-3.5 text-center">
                                                <a href="edit_pendaftar.php?id=<?= $p['id'] ?>" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-brand-primary hover:text-white text-gray-600 inline-flex items-center justify-center text-xs transition" title="Lihat &amp; Edit Detail">
                                                    <i class="fa-solid fa-arrow-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Gelombang Aktif Summary (Col 4) -->
                <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                            <div>
                                <h2 class="text-sm sm:text-base font-extrabold text-gray-900 flex items-center">
                                    <i class="fa-solid fa-calendar-days text-brand-primary mr-2 text-base"></i>
                                    Gelombang PSB
                                </h2>
                                <p class="text-xs text-gray-500 font-medium">Jadwal periode penerimaan santri</p>
                            </div>
                            <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                            <a href="gelombang.php" class="inline-flex items-center space-x-1 px-3 py-1 rounded-xl text-xs font-bold bg-[#5C3E35]/10 text-brand-primary hover:bg-[#5C3E35]/20 border border-[#5C3E35]/20 transition">
                                <span>Kelola</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-3 mt-3.5">
                            <?php foreach ($gelombang_list as $g): ?>
                                <?php $is_aktif = ($g['status'] === 'aktif'); ?>
                                <div class="rounded-xl p-3.5 border transition-all <?= $is_aktif ? 'bg-amber-50/40 border-brand-secondary/40 shadow-sm' : 'bg-gray-50 border-gray-200 opacity-70' ?>">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <div class="flex items-center space-x-2">
                                            <strong class="text-xs sm:text-sm font-extrabold text-gray-900"><?= htmlspecialchars($g['nama_gelombang']) ?></strong>
                                            <span class="text-[10px] font-bold text-gray-500">(<?= htmlspecialchars($g['tahun_ajaran']) ?>)</span>
                                        </div>
                                        <?php if ($is_aktif): ?>
                                            <span class="bg-emerald-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full flex items-center shadow-sm">
                                                <span class="w-1.5 h-1.5 rounded-full bg-white mr-1.5 animate-pulse"></span>Buka
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-200 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full">Selesai</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-gray-600 space-y-1 text-xs font-medium">
                                        <div class="flex items-center space-x-1.5 text-gray-500">
                                            <i class="fa-regular fa-calendar text-brand-secondary text-[11px]"></i>
                                            <span><?= date('d M Y', strtotime($g['tgl_buka'])) ?> &mdash; <?= date('d M Y', strtotime($g['tgl_tutup'])) ?></span>
                                        </div>
                                        <?php if (!empty($g['tgl_ujian'])): ?>
                                        <div class="flex items-center space-x-1.5 text-gray-500">
                                            <i class="fa-solid fa-clipboard-question text-brand-primary text-[11px]"></i>
                                            <span>Tes Seleksi: <b class="text-gray-800 font-bold"><?= date('d M Y', strtotime($g['tgl_ujian'])) ?></b></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500 font-semibold">
                        <span>Portal Web Publik:</span>
                        <a href="../index.php" target="_blank" class="text-brand-primary hover:text-brand-deep font-bold inline-flex items-center space-x-1">
                            <span>Buka Halaman</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Script Inisialisasi Chart.js & Leaflet -->
    <script>
        // Chart.js Default Font Configuration
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = "#4B5563";

        const trendLabels = <?= json_encode($trend_labels) ?>;
        const trendValues = <?= json_encode($trend_values) ?>;
        
        const jalurLabels = <?= json_encode($jalur_labels) ?>;
        const jalurValues = <?= json_encode($jalur_values) ?>;
        
        const jkLabels = <?= json_encode($jk_labels) ?>;
        const jkValues = <?= json_encode($jk_values) ?>;

        // 1. Line Chart (Tren Pendaftaran)
        const canvasTrend = document.getElementById('trendChart');
        if (canvasTrend) {
            const ctxTrend = canvasTrend.getContext('2d');
            const gradientTrend = ctxTrend.createLinearGradient(0, 0, 0, 260);
            gradientTrend.addColorStop(0, 'rgba(92, 62, 53, 0.22)');
            gradientTrend.addColorStop(1, 'rgba(92, 62, 53, 0.0)');

            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Pendaftar Masuk',
                        data: trendValues,
                        borderColor: '#5C3E35',
                        backgroundColor: gradientTrend,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#A17A6F',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#3E2723',
                            titleColor: '#F3EDE6',
                            bodyColor: '#FFFFFF',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' Santri Mendaftar';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#6B7280' },
                            grid: { color: '#E5E7EB', borderDash: [4, 4] }
                        },
                        x: {
                            ticks: { color: '#4B5563', font: { weight: '600' } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // 2. Doughnut Chart (Jalur Pendaftaran)
        const canvasJalur = document.getElementById('jalurChart');
        if (canvasJalur) {
            const ctxJalur = canvasJalur.getContext('2d');
            new Chart(ctxJalur, {
                type: 'doughnut',
                data: {
                    labels: jalurLabels,
                    datasets: [{
                        data: jalurValues,
                        backgroundColor: ['#5C3E35', '#A17A6F', '#8D6E63', '#2F2522'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 10,
                                font: { size: 11, weight: 'bold' }
                            }
                        }
                    }
                }
            });
        }

        // 3. Doughnut Chart (Jenis Kelamin)
        const canvasJk = document.getElementById('jkChart');
        if (canvasJk) {
            const ctxJk = canvasJk.getContext('2d');
            new Chart(ctxJk, {
                type: 'doughnut',
                data: {
                    labels: jkLabels,
                    datasets: [{
                        data: jkValues,
                        backgroundColor: ['#2563EB', '#EC4899'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 10,
                                font: { size: 11, weight: 'bold' }
                            }
                        }
                    }
                }
            });
        }

        // 4. Map Demografi menggunakan Leaflet
        const provDataMap = <?= json_encode($prov_data_map) ?>;
        const topKabMapData = <?= json_encode($top_kab) ?>;
        
        // Koordinat 38 Provinsi di Indonesia
        const provCoords = {
            "ACEH": [4.6951, 96.7494],
            "SUMATERA UTARA": [2.1154, 99.5451],
            "SUMATERA BARAT": [-0.7399, 100.8000],
            "RIAU": [0.2933, 101.7068],
            "JAMBI": [-1.6116, 103.6131],
            "SUMATERA SELATAN": [-3.3194, 104.9147],
            "BENGKULU": [-3.7928, 102.2608],
            "LAMPUNG": [-4.5586, 105.4068],
            "KEPULAUAN BANGKA BELITUNG": [-2.7411, 106.4406],
            "BANGKA BELITUNG": [-2.7411, 106.4406],
            "KEPULAUAN RIAU": [3.9456, 108.1429],
            "KEPRI": [3.9456, 108.1429],
            "DKI JAKARTA": [-6.2088, 106.8456],
            "JAKARTA": [-6.2088, 106.8456],
            "DAERAH KHUSUS IBUKOTA JAKARTA": [-6.2088, 106.8456],
            "JAWA BARAT": [-6.9147, 107.6098],
            "JAWA TENGAH": [-7.1509, 110.1402],
            "DI YOGYAKARTA": [-7.7956, 110.3695],
            "D.I. YOGYAKARTA": [-7.7956, 110.3695],
            "DAERAH ISTIMEWA YOGYAKARTA": [-7.7956, 110.3695],
            "YOGYAKARTA": [-7.7956, 110.3695],
            "JAWA TIMUR": [-7.5360, 112.2384],
            "BANTEN": [-6.4058, 106.0640],
            "BALI": [-8.4095, 115.1889],
            "NUSA TENGGARA BARAT": [-8.6529, 117.3616],
            "NUSA TENGGARA TIMUR": [-8.6500, 121.0800],
            "KALIMANTAN BARAT": [-0.2787, 111.4753],
            "KALIMANTAN TENGAH": [-1.6815, 113.3824],
            "KALIMANTAN SELATAN": [-3.0926, 115.2838],
            "KALIMANTAN TIMUR": [0.5387, 116.4194],
            "KALIMANTAN UTARA": [3.0731, 116.0414],
            "SULAWESI UTARA": [0.6247, 123.9750],
            "SULAWESI TENGAH": [-1.4300, 121.4456],
            "SULAWESI SELATAN": [-3.6688, 119.9740],
            "SULAWESI TENGGARA": [-4.1449, 122.1746],
            "GORONTALO": [0.6999, 122.4467],
            "SULAWESI BARAT": [-2.8441, 119.2321],
            "MALUKU": [-3.2385, 130.1453],
            "MALUKU UTARA": [1.5701, 127.8088],
            "PAPUA": [-4.2699, 138.0804],
            "PAPUA BARAT": [-1.3361, 133.1747],
            "PAPUA SELATAN": [-7.5000, 139.5000],
            "PAPUA TENGAH": [-3.5000, 136.5000],
            "PAPUA PEGUNUNGAN": [-4.1000, 139.0000],
            "PAPUA BARAT DAYA": [-1.0000, 132.0000]
        };

        let kabCoordsCache = {};
        let currentMarkers = [];
        let mapLevelActive = 'provinsi';

        const map = L.map('provMap', {
            zoomControl: true,
            scrollWheelZoom: true
        }).setView([1.6671, 101.4432], 7);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" class="text-xs text-gray-500 hover:underline">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        function normalizeWilayahName(str) {
            if (!str) return '';
            return str.toUpperCase()
                .replace(/^(KABUPATEN|KAB\.|KOTA ADMINISTRASI|KOTA ADM\.|KOTA|KODYA)\s+/i, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function clearMarkers() {
            currentMarkers.forEach(m => map.removeLayer(m));
            currentMarkers = [];
        }

        async function ensureKabCoordsLoaded() {
            if (Object.keys(kabCoordsCache).length > 0) return;
            try {
                const res = await fetch('api_wilayah.php?action=regencies');
                if (res.ok) {
                    const data = await res.json();
                    if (Array.isArray(data)) {
                        data.forEach(k => {
                            const lat = parseFloat(k.latitude);
                            const lng = parseFloat(k.longitude);
                            const rawName = (k.name || '').toUpperCase().trim();
                            const cleanName = normalizeWilayahName(rawName);
                            kabCoordsCache[rawName] = [lat, lng];
                            if (cleanName) kabCoordsCache[cleanName] = [lat, lng];
                        });
                    }
                }
            } catch (err) {
                console.warn('Gagal memuat koordinat wilayah:', err);
            }
        }

        async function renderMapLevel(level) {
            mapLevelActive = level;
            clearMarkers();
            
            if (level === 'provinsi') {
                const counts = Object.values(provDataMap).map(v => parseInt(v));
                const maxSantri = counts.length > 0 ? Math.max(...counts) : 1;

                for (const [provName, countVal] of Object.entries(provDataMap)) {
                    const count = parseInt(countVal);
                    const cleanName = normalizeWilayahName(provName);
                    const coords = provCoords[provName] || provCoords[cleanName];
                    if (coords) {
                        const radius = Math.min(22, Math.max(8, 7 + (count / maxSantri) * 15));
                        const circle = L.circleMarker(coords, {
                            color: '#3E2723',
                            fillColor: '#A17A6F',
                            fillOpacity: 0.85,
                            radius: radius,
                            weight: 2
                        }).addTo(map);

                        circle.bindTooltip(`<b>${provName}</b><br>${count} Santri`, {
                            direction: 'top',
                            offset: [0, -radius]
                        });

                        circle.bindPopup(`
                            <div class="text-center font-sans p-1.5">
                                <span class="text-[10px] font-extrabold text-[#5C3E35] uppercase tracking-wider block">Provinsi Asal</span>
                                <b class="text-[#3E2723] text-sm block mt-0.5">${provName}</b>
                                <div class="mt-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-[#5C3E35] text-white shadow-sm">
                                    <i class="fa-solid fa-users mr-1.5"></i> ${count} Santri
                                </div>
                            </div>
                        `);
                        currentMarkers.push(circle);
                    }
                }
            } else if (level === 'kota') {
                await ensureKabCoordsLoaded();

                const counts = topKabMapData.map(v => parseInt(v.jumlah));
                const maxSantri = counts.length > 0 ? Math.max(...counts) : 1;

                topKabMapData.forEach(d => {
                    const count = parseInt(d.jumlah);
                    const rawName = (d.kabupaten || '').toUpperCase().trim();
                    const cleanName = normalizeWilayahName(rawName);
                    
                    let coords = (d.lat && d.lng) 
                        ? [parseFloat(d.lat), parseFloat(d.lng)] 
                        : (kabCoordsCache[rawName] || kabCoordsCache[cleanName] || null);
                    
                    if (coords) {
                        const radius = Math.min(20, Math.max(7, 6 + (count / maxSantri) * 13));
                        const circle = L.circleMarker(coords, {
                            color: '#1B5E20',
                            fillColor: '#2E7D32',
                            fillOpacity: 0.85,
                            radius: radius,
                            weight: 2
                        }).addTo(map);

                        circle.bindTooltip(`<b>${d.kabupaten}</b><br>${count} Santri`, {
                            direction: 'top',
                            offset: [0, -radius]
                        });

                        circle.bindPopup(`
                            <div class="text-center font-sans p-1.5">
                                <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider block">Kota / Kabupaten</span>
                                <b class="text-emerald-900 text-sm block mt-0.5">${d.kabupaten}</b>
                                <div class="mt-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-emerald-700 text-white shadow-sm">
                                    <i class="fa-solid fa-user-graduate mr-1.5"></i> ${count} Santri
                                </div>
                            </div>
                        `);
                        currentMarkers.push(circle);
                    }
                });
            }

            // Auto fit bounds
            if (currentMarkers.length > 0) {
                if (currentMarkers.length === 1) {
                    const singleCoord = currentMarkers[0].getLatLng();
                    map.setView(singleCoord, 8);
                } else {
                    const group = L.featureGroup(currentMarkers);
                    map.fitBounds(group.getBounds().pad(0.25), { maxZoom: 10 });
                }
            } else {
                map.setView([1.6671, 101.4432], 7);
            }

            setTimeout(() => { map.invalidateSize(); }, 150);
        }

        function switchMapLevel(level) {
            const btnProv = document.getElementById('btn-map-provinsi');
            const btnKota = document.getElementById('btn-map-kota');
            if (level === 'provinsi') {
                btnProv.classList.add('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
                btnProv.classList.remove('text-gray-600', 'font-semibold');
                btnKota.classList.add('text-gray-600', 'font-semibold');
                btnKota.classList.remove('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
            } else {
                btnKota.classList.add('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
                btnKota.classList.remove('text-gray-600', 'font-semibold');
                btnProv.classList.add('text-gray-600', 'font-semibold');
                btnProv.classList.remove('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
            }
            renderMapLevel(level);
        }

        // Initialize Map
        renderMapLevel('provinsi');

        window.addEventListener('load', () => { setTimeout(() => map.invalidateSize(), 200); });
        window.addEventListener('resize', () => { map.invalidateSize(); });

        // Tab Switching Logic (Hierarki / Kota / Kecamatan)
        function switchTab(tabId) {
            const tabs = ['tab-hierarki', 'tab-kota', 'tab-kec'];
            const btns = ['btn-tab-hierarki', 'btn-tab-kota', 'btn-tab-kec'];
            
            tabs.forEach(id => {
                const el = document.getElementById(id);
                if (id === tabId) {
                    el.classList.remove('opacity-0', 'pointer-events-none');
                    el.classList.add('opacity-100');
                } else {
                    el.classList.add('opacity-0', 'pointer-events-none');
                    el.classList.remove('opacity-100');
                }
            });

            btns.forEach(id => {
                const el = document.getElementById(id);
                if (id === 'btn-' + tabId) {
                    el.classList.add('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
                    el.classList.remove('text-gray-600', 'font-semibold');
                } else {
                    el.classList.remove('bg-white', 'shadow-sm', 'text-brand-primary', 'font-bold');
                    el.classList.add('text-gray-600', 'font-semibold');
                }
            });
        }

        // Quick Payment Confirmation Modal
        function confirmQuickPayment(e, id, nama) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Verifikasi Pembayaran?',
                    html: `Validasi pembayaran biaya seleksi pendaftaran santri <b>${nama}</b> menjadi <span class="text-emerald-700 font-bold">Lunas</span>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#5C3E35',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-check mr-1.5"></i> Ya, Validasi Lunas',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-2xl',
                        confirmButton: 'rounded-xl font-bold px-4 py-2 text-xs',
                        cancelButton: 'rounded-xl font-bold px-4 py-2 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `dashboard.php?action=confirm_payment&id=${id}`;
                    }
                });
            } else {
                if (confirm(`Validasi pembayaran santri ${nama} menjadi Lunas?`)) {
                    window.location.href = `dashboard.php?action=confirm_payment&id=${id}`;
                }
            }
        }
    </script>
</body>
</html>
