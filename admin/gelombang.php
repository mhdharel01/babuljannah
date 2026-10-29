<?php
// admin/gelombang.php
require_once __DIR__ . '/../config/db.php';

// Proteksi Autentikasi Admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC: superadmin dan panitia
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

$error = '';
$success = '';

// 1. Tangani Aksi Simpan Gelombang Baru
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_gelombang') {
    $nama_gelombang = sanitize($_POST['nama_gelombang'] ?? '');
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
    $tgl_buka = sanitize($_POST['tgl_buka'] ?? '');
    $tgl_tutup = sanitize($_POST['tgl_tutup'] ?? '');
    $tgl_ujian = sanitize($_POST['tgl_ujian'] ?? '');
    $tgl_pengumuman = sanitize($_POST['tgl_pengumuman'] ?? '');
    $jenjang_dibuka = sanitize($_POST['jenjang_dibuka'] ?? 'Semua');
    $status = sanitize($_POST['status'] ?? 'nonaktif');
    
    if (!empty($nama_gelombang) && !empty($tahun_ajaran) && !empty($tgl_buka) && !empty($tgl_tutup) && !empty($tgl_ujian) && !empty($tgl_pengumuman)) {
        if ($tgl_tutup < $tgl_buka) {
            $error = "Tanggal tutup pendaftaran tidak boleh lebih awal dari tanggal buka.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO gelombang (nama_gelombang, tahun_ajaran, tgl_buka, tgl_tutup, tgl_ujian, tgl_pengumuman, jenjang_dibuka, status) VALUES (:nama, :ta, :buka, :tutup, :ujian, :pengumuman, :jenjang, :status)");
                $stmt->execute([
                    'nama' => $nama_gelombang,
                    'ta' => $tahun_ajaran,
                    'buka' => $tgl_buka,
                    'tutup' => $tgl_tutup,
                    'ujian' => $tgl_ujian,
                    'pengumuman' => $tgl_pengumuman,
                    'jenjang' => $jenjang_dibuka,
                    'status' => $status
                ]);
                
                $success = "Gelombang pendaftaran baru '{$nama_gelombang}' berhasil ditambahkan!";
            } catch (PDOException $e) {
                $error = "Gagal menyimpan gelombang: " . $e->getMessage();
            }
        }
    } else {
        $error = "Silakan lengkapi semua field formulir gelombang pendaftaran.";
    }
}

// 2. Tangani Aksi Toggle Status Gelombang
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);
    $current_status = sanitize($_GET['current'] ?? '');
    
    if ($id > 0) {
        try {
            if ($current_status === 'aktif') {
                $stmt = $db->prepare("UPDATE gelombang SET status = 'nonaktif' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success = "Status gelombang berhasil dinonaktifkan (disembunyikan dari portal publik).";
            } else {
                $stmt = $db->prepare("UPDATE gelombang SET status = 'aktif' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success = "Status gelombang berhasil diaktifkan dan akan tampil di jadwal publik portal PSB.";
            }
        } catch (PDOException $e) {
            $error = "Gagal mengubah status gelombang: " . $e->getMessage();
        }
    }
}

// 3. Tangani Aksi Hapus Gelombang
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            // Cek apakah sudah ada santri yang mendaftar di gelombang ini
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE gelombang_id = :id");
            $check_stmt->execute(['id' => $id]);
            $total_p = (int)$check_stmt->fetchColumn();

            if ($total_p > 0) {
                $error = "Gelombang ini tidak dapat dihapus karena sudah memiliki <strong>{$total_p} santri terdaftar</strong>. Demi keamanan data, silakan ubah statusnya menjadi <em>Nonaktif</em> saja.";
            } else {
                $stmt = $db->prepare("DELETE FROM gelombang WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success = "Gelombang pendaftaran berhasil dihapus dari sistem.";
            }
        } catch (PDOException $e) {
            $error = "Gagal menghapus gelombang: " . $e->getMessage();
        }
    }
}

// 4. Tangani Aksi Edit Gelombang
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_gelombang') {
    $id = (int)($_POST['id'] ?? 0);
    $nama_gelombang = sanitize($_POST['nama_gelombang'] ?? '');
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
    $tgl_buka = sanitize($_POST['tgl_buka'] ?? '');
    $tgl_tutup = sanitize($_POST['tgl_tutup'] ?? '');
    $tgl_ujian = sanitize($_POST['tgl_ujian'] ?? '');
    $tgl_pengumuman = sanitize($_POST['tgl_pengumuman'] ?? '');
    $jenjang_dibuka = sanitize($_POST['jenjang_dibuka'] ?? 'Semua');
    $status = sanitize($_POST['status'] ?? 'nonaktif');
    
    if ($id > 0 && !empty($nama_gelombang) && !empty($tahun_ajaran) && !empty($tgl_buka) && !empty($tgl_tutup) && !empty($tgl_ujian) && !empty($tgl_pengumuman)) {
        if ($tgl_tutup < $tgl_buka) {
            $error = "Tanggal tutup pendaftaran tidak boleh lebih awal dari tanggal buka.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE gelombang SET nama_gelombang = :nama, tahun_ajaran = :ta, tgl_buka = :buka, tgl_tutup = :tutup, tgl_ujian = :ujian, tgl_pengumuman = :pengumuman, jenjang_dibuka = :jenjang, status = :status WHERE id = :id");
                $stmt->execute([
                    'nama' => $nama_gelombang,
                    'ta' => $tahun_ajaran,
                    'buka' => $tgl_buka,
                    'tutup' => $tgl_tutup,
                    'ujian' => $tgl_ujian,
                    'pengumuman' => $tgl_pengumuman,
                    'jenjang' => $jenjang_dibuka,
                    'status' => $status,
                    'id' => $id
                ]);
                
                $success = "Data dan jadwal gelombang '{$nama_gelombang}' berhasil diperbarui.";
                header("Location: gelombang.php?success=" . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = "Gagal memperbarui gelombang: " . $e->getMessage();
            }
        }
    } else {
        $error = "Silakan lengkapi semua field formulir edit.";
    }
}

// 5. Tangani Aksi Simpan Pengaturan Kuota Penerimaan Santri
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_kuota') {
    $kuota_smp_post = max(1, (int)($_POST['kuota_penerimaan_smp'] ?? 100));
    $kuota_sma_post = max(1, (int)($_POST['kuota_penerimaan_sma'] ?? 30));
    $tampilkan_catatan_post = isset($_POST['tampilkan_catatan_kuota']) ? '1' : '0';
    $catatan_custom_post = trim($_POST['catatan_kuota_gelombang'] ?? '');

    try {
        $stmt_set_kuota = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = ?");
        $stmt_set_kuota->execute(['kuota_penerimaan_smp', $kuota_smp_post, $kuota_smp_post]);
        $stmt_set_kuota->execute(['kuota_penerimaan_sma', $kuota_sma_post, $kuota_sma_post]);
        $stmt_set_kuota->execute(['tampilkan_catatan_kuota', $tampilkan_catatan_post, $tampilkan_catatan_post]);
        $stmt_set_kuota->execute(['catatan_kuota_gelombang', $catatan_custom_post, $catatan_custom_post]);

        $success = "Pengaturan target kuota penerimaan santri (SMP: {$kuota_smp_post}, SMA: {$kuota_sma_post}, Total: " . ($kuota_smp_post + $kuota_sma_post) . ") berhasil diperbarui!";
    } catch (PDOException $e) {
        $error = "Gagal menyimpan pengaturan kuota: " . $e->getMessage();
    }
}

// Ambil pesan dari URL jika diredirect
if (isset($_GET['success']) && empty($success)) {
    $success = sanitize($_GET['success']);
}

// Ambil data seluruh gelombang dengan agregasi pendaftar
$stmt_all = $db->query("
    SELECT g.*, 
           COUNT(p.id) AS total_pendaftar,
           SUM(CASE WHEN p.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) AS total_lunas,
           SUM(CASE WHEN p.status_seleksi = 'Lulus' THEN 1 ELSE 0 END) AS total_lulus
    FROM gelombang g
    LEFT JOIN pendaftaran p ON p.gelombang_id = g.id
    GROUP BY g.id
    ORDER BY g.id DESC
");
$gelombang_list = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// Cek apakah mode Edit aktif
$edit_data = null;
if (isset($_GET['edit_id']) && (int)$_GET['edit_id'] > 0) {
    $stmt_edit = $db->prepare("SELECT * FROM gelombang WHERE id = :id");
    $stmt_edit->execute(['id' => (int)$_GET['edit_id']]);
    $edit_data = $stmt_edit->fetch();
}

// Analisis Statistik Sistem
$total_gelombang = count($gelombang_list);
$total_aktif = 0;
$total_semua_pendaftar = 0;
$total_semua_lunas = 0;
$gelombang_berjalan = null;
$today = date('Y-m-d');

foreach ($gelombang_list as $g) {
    if ($g['status'] === 'aktif') {
        $total_aktif++;
        if ($today >= $g['tgl_buka'] && $today <= $g['tgl_tutup'] && !$gelombang_berjalan) {
            $gelombang_berjalan = $g;
        }
    }
    $total_semua_pendaftar += (int)$g['total_pendaftar'];
    $total_semua_lunas += (int)$g['total_lunas'];
}

// Ambil Pengaturan Kuota Santri Baru dari Database
$kuota_smp = 100;
$kuota_sma = 30;
$tampilkan_catatan_kuota = 1;
$catatan_kuota_custom = '';

try {
    $stmt_k = $db->query("SELECT kunci, nilai FROM pengaturan_sistem WHERE kunci IN ('kuota_penerimaan_smp', 'kuota_penerimaan_sma', 'tampilkan_catatan_kuota', 'catatan_kuota_gelombang')");
    while ($rk = $stmt_k->fetch(PDO::FETCH_ASSOC)) {
        if ($rk['kunci'] === 'kuota_penerimaan_smp' && is_numeric($rk['nilai'])) {
            $kuota_smp = (int)$rk['nilai'];
        } elseif ($rk['kunci'] === 'kuota_penerimaan_sma' && is_numeric($rk['nilai'])) {
            $kuota_sma = (int)$rk['nilai'];
        } elseif ($rk['kunci'] === 'tampilkan_catatan_kuota') {
            $tampilkan_catatan_kuota = (int)$rk['nilai'];
        } elseif ($rk['kunci'] === 'catatan_kuota_gelombang') {
            $catatan_kuota_custom = trim($rk['nilai']);
        }
    }
} catch (Throwable $e) {}

$total_kuota_semua = $kuota_smp + $kuota_sma;

// Hitung Keterisian Nyata Santri per Jenjang dari Database
$stat_jenjang = [
    'smp' => ['daftar' => 0, 'lunas' => 0, 'lulus' => 0],
    'sma' => ['daftar' => 0, 'lunas' => 0, 'lulus' => 0]
];
try {
    $stmt_jenjang = $db->query("
        SELECT jenjang,
               COUNT(*) as total_daftar,
               SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as total_lunas,
               SUM(CASE WHEN status_seleksi = 'Lulus' THEN 1 ELSE 0 END) as total_lulus
        FROM pendaftaran
        GROUP BY jenjang
    ");
    while ($rj = $stmt_jenjang->fetch(PDO::FETCH_ASSOC)) {
        $j_lower = strtolower($rj['jenjang'] ?? '');
        if (strpos($j_lower, 'smp') !== false) {
            $stat_jenjang['smp']['daftar'] += (int)$rj['total_daftar'];
            $stat_jenjang['smp']['lunas'] += (int)$rj['total_lunas'];
            $stat_jenjang['smp']['lulus'] += (int)$rj['total_lulus'];
        } elseif (strpos($j_lower, 'sma') !== false) {
            $stat_jenjang['sma']['daftar'] += (int)$rj['total_daftar'];
            $stat_jenjang['sma']['lunas'] += (int)$rj['total_lunas'];
            $stat_jenjang['sma']['lulus'] += (int)$rj['total_lulus'];
        }
    }
} catch (Throwable $e) {}

$pct_smp = min(100, round(($stat_jenjang['smp']['daftar'] / max(1, $kuota_smp)) * 100, 1));
$pct_sma = min(100, round(($stat_jenjang['sma']['daftar'] / max(1, $kuota_sma)) * 100, 1));
$total_daftar_semua = $stat_jenjang['smp']['daftar'] + $stat_jenjang['sma']['daftar'];
$pct_total = min(100, round(($total_daftar_semua / max(1, $total_kuota_semua)) * 100, 1));
$sisa_smp = max(0, $kuota_smp - $stat_jenjang['smp']['daftar']);
$sisa_sma = max(0, $kuota_sma - $stat_jenjang['sma']['daftar']);
$sisa_total = max(0, $total_kuota_semua - $total_daftar_semua);

// Helper penentuan status dan timeline waktu
if (!function_exists('get_gelombang_status_meta')) {
    function get_gelombang_status_meta($g, $today) {
        if ($g['status'] === 'nonaktif') {
            return [
                'code' => 'nonaktif',
                'label' => 'Nonaktif',
                'badge_bg' => 'bg-gray-100 text-gray-600 border-gray-300/80',
                'dot_color' => 'bg-gray-400',
                'desc' => 'Dinonaktifkan oleh panitia (tidak tampil di publik)',
                'is_open' => false,
                'progress' => 0
            ];
        }
        
        $buka = $g['tgl_buka'];
        $tutup = $g['tgl_tutup'];
        $ujian = $g['tgl_ujian'] ?? null;
        $pengumuman = $g['tgl_pengumuman'] ?? null;
        
        if ($today < $buka) {
            $diff = ceil((strtotime($buka) - strtotime($today)) / 86400);
            return [
                'code' => 'upcoming',
                'label' => 'Segera Dibuka',
                'badge_bg' => 'bg-amber-50 text-amber-800 border-amber-300/80',
                'dot_color' => 'bg-amber-500',
                'desc' => 'Dibuka dalam ' . $diff . ' hari lagi (' . date('d/m/Y', strtotime($buka)) . ')',
                'is_open' => false,
                'progress' => 5
            ];
        } elseif ($today >= $buka && $today <= $tutup) {
            $total_duration = max(1, (strtotime($tutup) - strtotime($buka)) / 86400);
            $elapsed = max(0, (strtotime($today) - strtotime($buka)) / 86400);
            $pct = min(100, max(5, round(($elapsed / $total_duration) * 100)));
            $sisa = ceil((strtotime($tutup) - strtotime($today)) / 86400);
            
            return [
                'code' => 'open',
                'label' => 'Sedang Dibuka',
                'badge_bg' => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                'dot_color' => 'bg-emerald-600 animate-pulse',
                'desc' => 'Tersisa ' . $sisa . ' hari pendaftaran (s/d ' . date('d/m/Y', strtotime($tutup)) . ')',
                'is_open' => true,
                'progress' => $pct
            ];
        } elseif ($ujian && $today <= $ujian) {
            return [
                'code' => 'ujian',
                'label' => 'Masa Ujian Seleksi',
                'badge_bg' => 'bg-indigo-50 text-indigo-800 border-indigo-300/80',
                'dot_color' => 'bg-indigo-600',
                'desc' => 'Pendaftaran ditutup, ujian pada ' . date('d/m/Y', strtotime($ujian)),
                'is_open' => false,
                'progress' => 80
            ];
        } elseif ($pengumuman && $today <= $pengumuman) {
            return [
                'code' => 'pengumuman',
                'label' => 'Menunggu Pengumuman',
                'badge_bg' => 'bg-purple-50 text-purple-800 border-purple-300/80',
                'dot_color' => 'bg-purple-600',
                'desc' => 'Pengumuman hasil seleksi pada ' . date('d/m/Y', strtotime($pengumuman)),
                'is_open' => false,
                'progress' => 95
            ];
        } else {
            return [
                'code' => 'closed',
                'label' => 'Periode Selesai',
                'badge_bg' => 'bg-slate-100 text-slate-700 border-slate-300',
                'dot_color' => 'bg-slate-500',
                'desc' => 'Seluruh tahapan seleksi gelombang ini telah rampung',
                'is_open' => false,
                'progress' => 100
            ];
        }
    }
}

$page_title = 'Kelola Gelombang Pendaftaran - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<style>
    /* ======================================================== */
    /* SINGLE SCROLLBAR & BRAND AESTHETICS                      */
    /* ======================================================== */
    html, body {
        height: 100%;
        max-height: 100vh;
        overflow: hidden !important;
    }
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

    /* Brand Tokens */
    .bg-brand-primary {
        background-color: #5C3E35 !important;
    }
    .bg-brand-deep {
        background-color: #432B25 !important;
    }
    .text-brand-primary {
        color: #5C3E35 !important;
    }
    .border-brand-primary {
        border-color: #5C3E35 !important;
    }

    /* Timeline Stepper Styles */
    .timeline-step {
        position: relative;
        padding-left: 1.75rem;
    }
    .timeline-step::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 1.25rem;
        bottom: -0.75rem;
        width: 2px;
        background-color: #E5E7EB;
    }
    .timeline-step:last-child::before {
        display: none;
    }
    .timeline-dot {
        position: absolute;
        left: 0.2rem;
        top: 0.2rem;
        width: 0.85rem;
        height: 0.85rem;
        border-radius: 9999px;
        border: 2px solid #FFFFFF;
        box-shadow: 0 0 0 2px #E5E7EB;
    }

    /* Quick Preset Buttons */
    .btn-preset {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 0.5rem;
        background-color: #F3F4F6;
        color: #4B5563;
        border: 1px solid #E5E7EB;
        transition: all 0.15s ease;
    }
    .btn-preset:hover {
        background-color: #E5E7EB;
        color: #1F2937;
        border-color: #D1D5DB;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Main Container (Single Clean Scrollbar) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & PORTAL STATUS BAR -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-calendar-week"></i>
                        <span>Pengaturan Gelombang &amp; Jadwal Seleksi</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">PSB T.A 2026/2027</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola tanggal pembukaan, penutupan formulir, tanggal ujian seleksi, dan pengumuman hasil kelulusan.
                    </p>
                </div>

                <!-- Portal Live Status & Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($gelombang_berjalan): ?>
                        <div class="flex items-center space-x-2 bg-emerald-50 border border-emerald-300 px-3 py-1.5 rounded-xl text-xs text-emerald-900 shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                            <span class="font-bold">Portal Live:</span>
                            <span class="font-black text-emerald-950"><?= htmlspecialchars($gelombang_berjalan['nama_gelombang']) ?></span>
                            <span class="text-[10px] bg-emerald-200/70 px-1.5 py-0.5 rounded font-bold text-emerald-800">Aktif</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center space-x-2 bg-amber-50 border border-amber-300 px-3 py-1.5 rounded-xl text-xs text-amber-900 shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="font-bold">Portal:</span>
                            <span class="font-black">Tidak Ada Gelombang Buka</span>
                        </div>
                    <?php endif; ?>

                    <!-- Shortcut to Public Website Section -->
                    <a href="../index.php#jadwal" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat Tampilan Jadwal di Website Publik">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat di Web</span>
                    </a>

                    <!-- Add New Trigger Button (Scroll to form) -->
                    <button type="button" onclick="focusFormGelombang()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tambah Gelombang Baru">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Gelombang Baru</span>
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <p class="font-bold text-xs"><?= $success ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                        <p class="font-bold text-xs"><?= $error ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- ======================================================== -->
            <!-- 2. TOP METRIC OVERVIEW CARDS (4 STATS) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                
                <!-- Stat 1: Gelombang Berjalan -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider block">Status Gelombang</span>
                        <div class="text-sm sm:text-base font-black text-gray-900 truncate">
                            <?= $gelombang_berjalan ? htmlspecialchars($gelombang_berjalan['nama_gelombang']) : 'Tutup / Off' ?>
                        </div>
                        <span class="text-[10px] text-emerald-700 font-bold block truncate">
                            <?= $gelombang_berjalan ? 'Pendaftaran Dibuka' : 'Semua Ditutup' ?>
                        </span>
                    </div>
                </div>

                <!-- Stat 2: Total Gelombang -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-700 border border-teal-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider block">Total Gelombang</span>
                        <div class="text-sm sm:text-base font-black text-gray-900">
                            <?= $total_gelombang ?> <span class="text-xs font-normal text-gray-500">Gelombang</span>
                        </div>
                        <span class="text-[10px] text-gray-500 font-medium block">
                            <?= $total_aktif ?> Aktif &bull; <?= ($total_gelombang - $total_aktif) ?> Nonaktif
                        </span>
                    </div>
                </div>

                <!-- Stat 3: Total Pendaftar -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider block">Santri Terdaftar</span>
                        <div class="text-sm sm:text-base font-black text-gray-900">
                            <?= number_format($total_semua_pendaftar, 0, ',', '.') ?> <span class="text-xs font-normal text-gray-500">Santri</span>
                        </div>
                        <span class="text-[10px] text-emerald-700 font-bold block">
                            <?= $total_semua_lunas ?> Terbayar Lunas
                        </span>
                    </div>
                </div>

                <!-- Stat 4: Jadwal Ujian Terdekat -->
                <?php
                    $next_exam = null;
                    foreach ($gelombang_list as $g) {
                        if ($g['status'] === 'aktif' && !empty($g['tgl_ujian']) && $g['tgl_ujian'] >= $today) {
                            if (!$next_exam || $g['tgl_ujian'] < $next_exam['tgl_ujian']) {
                                $next_exam = $g;
                            }
                        }
                    }
                ?>
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wider block">Tes Terdekat</span>
                        <div class="text-sm sm:text-base font-black text-gray-900 truncate">
                            <?= $next_exam ? date('d M Y', strtotime($next_exam['tgl_ujian'])) : '-' ?>
                        </div>
                        <span class="text-[10px] text-amber-800 font-bold block truncate">
                            <?= $next_exam ? htmlspecialchars($next_exam['nama_gelombang']) : 'Belum Dijadwalkan' ?>
                        </span>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 2.1 TARGET KUOTA PENERIMAAN & KEBIJAKAN GELOMBANG -->
            <!-- ======================================================== -->
            <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-5 space-y-4">
                
                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 border border-rose-200/80 flex items-center justify-center text-sm font-bold shadow-2xs">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm sm:text-base font-extrabold text-gray-900">
                                    Target Kuota Penerimaan Santri &amp; Pengumuman Gelombang
                                </h3>
                                <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800">
                                    Kebijakan PSB
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                Atur kapasitas daya tampung santri baru yang diterima (SMP IT &amp; SMA IT) serta teks catatan kuota yang tampil pada jadwal pendaftaran online.
                            </p>
                        </div>
                    </div>

                    <!-- Total Capacity Live Badge -->
                    <div class="flex items-center space-x-2 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl self-start sm:self-auto">
                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Total Kapasitas:</span>
                        <span class="text-xs font-black text-gray-900 font-mono" id="badgeTotalKuota"><?= $total_kuota_semua ?> Kursi</span>
                        <span class="text-[10px] text-emerald-700 font-extrabold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                            Sisa <?= $sisa_total ?>
                        </span>
                    </div>
                </div>

                <!-- Grid 2 Kolom: Form Pengaturan (Kiri) & Live Keterisian + Preview (Kanan) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                    
                    <!-- Kolom Kiri: Form Pengaturan Kuota & Kalimat Pengumuman (6 / 12) -->
                    <div class="lg:col-span-6 bg-gray-50/70 p-4 rounded-xl border border-gray-200/80 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-gray-800 flex items-center">
                                <i class="fa-solid fa-sliders text-brand-primary mr-1.5"></i> Form Pengaturan Kuota
                            </span>
                            <span class="text-[10px] text-gray-400">Sinkronisasi Realtime</span>
                        </div>

                        <form action="gelombang.php" method="POST" id="formKuota" class="space-y-3.5">
                            <input type="hidden" name="action" value="save_kuota">

                            <!-- Info Penegasan: Display Tetap -->
                            <div class="p-2.5 rounded-xl bg-amber-50/90 border border-amber-200/80 text-[11px] text-amber-900 flex items-start space-x-2">
                                <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5 text-xs flex-shrink-0"></i>
                                <div class="leading-relaxed">
                                    <strong>Display Informasi Resmi (Tetap):</strong> Angka kuota ini hanya sebagai informasi daya tampung yang ditampilkan di website dan <u>TIDAK AKAN BERKURANG</u> saat ada santri yang mendaftar.
                                </div>
                            </div>

                            <!-- Input Kuota SMP IT & SMA IT -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                
                                <!-- Kuota SMP IT -->
                                <div class="bg-white p-3 rounded-xl border border-teal-200 shadow-2xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label for="inputKuotaSmp" class="text-[11px] font-extrabold text-teal-950 flex items-center">
                                            <i class="fa-solid fa-school text-teal-700 mr-1 text-xs"></i> Kuota SMP IT
                                        </label>
                                        <span class="text-[9px] bg-teal-50 text-teal-800 font-black px-1.5 py-0.5 rounded border border-teal-200">
                                            T.A 2026/2027
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-1.5">
                                        <button type="button" onclick="adjustKuota('inputKuotaSmp', -5)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-teal-100 text-gray-600 hover:text-teal-900 font-black text-xs transition flex items-center justify-center">-</button>
                                        <input type="number" min="1" max="1000" id="inputKuotaSmp" name="kuota_penerimaan_smp" value="<?= $kuota_smp ?>" required class="flex-grow text-center font-black text-sm text-teal-950 border border-teal-300 rounded-lg py-1 px-2 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-600">
                                        <button type="button" onclick="adjustKuota('inputKuotaSmp', 5)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-teal-100 text-gray-600 hover:text-teal-900 font-black text-xs transition flex items-center justify-center">+</button>
                                        <span class="text-xs text-gray-500 font-bold">Santri</span>
                                    </div>
                                    <span class="text-[9px] text-gray-400 block">Kapasitas yang diterima di SMP IT</span>
                                </div>

                                <!-- Kuota SMA IT -->
                                <div class="bg-white p-3 rounded-xl border border-indigo-200 shadow-2xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label for="inputKuotaSma" class="text-[11px] font-extrabold text-indigo-950 flex items-center">
                                            <i class="fa-solid fa-graduation-cap text-indigo-700 mr-1 text-xs"></i> Kuota SMA IT
                                        </label>
                                        <span class="text-[9px] bg-indigo-50 text-indigo-800 font-black px-1.5 py-0.5 rounded border border-indigo-200">
                                            T.A 2026/2027
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-1.5">
                                        <button type="button" onclick="adjustKuota('inputKuotaSma', -5)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-900 font-black text-xs transition flex items-center justify-center">-</button>
                                        <input type="number" min="1" max="1000" id="inputKuotaSma" name="kuota_penerimaan_sma" value="<?= $kuota_sma ?>" required class="flex-grow text-center font-black text-sm text-indigo-950 border border-indigo-300 rounded-lg py-1 px-2 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600">
                                        <button type="button" onclick="adjustKuota('inputKuotaSma', 5)" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-900 font-black text-xs transition flex items-center justify-center">+</button>
                                        <span class="text-xs text-gray-500 font-bold">Santri</span>
                                    </div>
                                    <span class="text-[9px] text-gray-400 block">Kapasitas yang diterima di SMA IT</span>
                                </div>

                            </div>

                            <!-- Checkbox Toggle Tampilkan Pengumuman -->
                            <div class="bg-white p-3 rounded-xl border border-gray-200 flex items-start space-x-2.5">
                                <input type="checkbox" id="checkTampilkanCatatan" name="tampilkan_catatan_kuota" value="1" <?= $tampilkan_catatan_kuota ? 'checked' : '' ?> class="mt-0.5 rounded text-brand-primary focus:ring-brand-primary/20 w-4 h-4 cursor-pointer">
                                <label for="checkTampilkanCatatan" class="cursor-pointer">
                                    <span class="text-xs font-extrabold text-gray-900 block">Tampilkan Pengumuman Kuota di Halaman Informasi Jadwal</span>
                                    <span class="text-[10px] text-gray-500 leading-tight block mt-0.5">
                                        Jika dicentang, banner catatan kuota (seperti di screenshot Anda) akan aktif ditampilkan di halaman <code class="text-brand-primary font-bold">daftar.php</code>.
                                    </span>
                                </label>
                            </div>

                            <!-- Textarea Kustom Catatan Kebijakan -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label for="textareaCatatanKuota" class="text-[11px] font-extrabold text-gray-700">
                                        Kustomisasi Teks Catatan Kuota (Opsional)
                                    </label>
                                    <button type="button" onclick="resetCatatanDefault()" class="text-[10px] text-brand-primary hover:underline font-bold">
                                        Gunakan Kalimat Standar
                                    </button>
                                </div>
                                <textarea id="textareaCatatanKuota" name="catatan_kuota_gelombang" rows="2" placeholder="Kosongkan jika ingin menggunakan kalimat standar otomatis sistem..." class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2 text-xs text-gray-800 bg-white leading-relaxed"><?= htmlspecialchars($catatan_kuota_custom) ?></textarea>
                                <span class="text-[9px] text-gray-400 block">
                                    *Jika dikosongkan, sistem otomatis membuat kalimat: <em>"Catatan: Jika kuota ([SMP] SMP &amp; [SMA] SMA) sudah terpenuhi pada Gelombang 1, maka Gelombang berikut nya otomatis tidak akan dibuka."</em>
                                </span>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-[10px] text-gray-500">
                                    <i class="fa-solid fa-cloud-arrow-up text-emerald-600 mr-1"></i> Tersimpan di sistem
                                </span>
                                <button type="submit" class="bg-rose-700 hover:bg-rose-800 text-white font-extrabold py-2 px-4 rounded-xl text-xs shadow-xs hover:shadow transition flex items-center space-x-1.5 transform active:scale-95">
                                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                                    <span>Simpan Pengaturan Kuota</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Kolom Kanan: Live Keterisian & Pratinjau Tampilan Publik (6 / 12) -->
                    <div class="lg:col-span-6 space-y-3.5">
                        
                        <!-- Visual Tracker Keterisian Nyata -->
                        <div class="bg-white p-4 rounded-xl border border-gray-200/80 shadow-2xs space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold text-gray-800 flex items-center">
                                    <i class="fa-solid fa-chart-pie text-brand-primary mr-1.5"></i> Keterisian Kuota Saat Ini (Live)
                                </span>
                                <span class="text-[10px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                                    Dari Data Pendaftaran
                                </span>
                            </div>

                            <!-- Meter SMP IT -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-extrabold text-teal-950 flex items-center">
                                        <span class="w-2 h-2 rounded-full bg-teal-600 mr-1.5"></span> SMP IT
                                    </span>
                                    <div class="text-right">
                                        <span class="font-black text-gray-900"><?= $stat_jenjang['smp']['daftar'] ?></span>
                                        <span class="text-gray-400">/ <span id="labelKuotaSmpLive"><?= $kuota_smp ?></span> Santri</span>
                                        <span class="font-black text-teal-700 ml-1">(<?= $pct_smp ?>%)</span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div id="barSmpLive" class="bg-teal-600 h-2 rounded-full transition-all duration-300" style="width: <?= $pct_smp ?>%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-gray-400">
                                    <span><?= $stat_jenjang['smp']['lunas'] ?> Bayar &bull; <?= $stat_jenjang['smp']['lulus'] ?> Lulus</span>
                                    <span class="text-teal-800 font-bold" id="sisaSmpLive">Sisa <?= $sisa_smp ?> Kursi</span>
                                </div>
                            </div>

                            <!-- Meter SMA IT -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-extrabold text-indigo-950 flex items-center">
                                        <span class="w-2 h-2 rounded-full bg-indigo-600 mr-1.5"></span> SMA IT
                                    </span>
                                    <div class="text-right">
                                        <span class="font-black text-gray-900"><?= $stat_jenjang['sma']['daftar'] ?></span>
                                        <span class="text-gray-400">/ <span id="labelKuotaSmaLive"><?= $kuota_sma ?></span> Santri</span>
                                        <span class="font-black text-indigo-700 ml-1">(<?= $pct_sma ?>%)</span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div id="barSmaLive" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: <?= $pct_sma ?>%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-gray-400">
                                    <span><?= $stat_jenjang['sma']['lunas'] ?> Bayar &bull; <?= $stat_jenjang['sma']['lulus'] ?> Lulus</span>
                                    <span class="text-indigo-800 font-bold" id="sisaSmaLive">Sisa <?= $sisa_sma ?> Kursi</span>
                                </div>
                            </div>

                            <!-- Total Meter -->
                            <div class="space-y-1 pt-1 border-t border-gray-100">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-black text-gray-800 flex items-center">
                                        <span class="w-2 h-2 rounded-full bg-brand-primary mr-1.5"></span> Akumulasi Total
                                    </span>
                                    <div class="text-right">
                                        <span class="font-black text-gray-900"><?= $total_daftar_semua ?></span>
                                        <span class="text-gray-400">/ <span id="labelKuotaTotalLive"><?= $total_kuota_semua ?></span> Kursi</span>
                                        <span class="font-black text-brand-primary ml-1">(<?= $pct_total ?>%)</span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div id="barTotalLive" class="bg-brand-primary h-2 rounded-full transition-all duration-300" style="width: <?= $pct_total ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pratinjau Tampilan Pengumuman di Web Publik (daftar.php) -->
                        <div class="bg-white p-4 rounded-xl border border-gray-200/80 shadow-2xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold text-gray-800 flex items-center">
                                    <i class="fa-solid fa-eye text-rose-600 mr-1.5"></i> Pratinjau Tampilan di Web Publik
                                </span>
                                <a href="../daftar.php#jadwal" target="_blank" class="text-[10px] text-brand-primary hover:underline font-bold flex items-center space-x-1">
                                    <span>Buka daftar.php</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                </a>
                            </div>

                            <div id="previewBoxWrapper">
                                <div id="previewNoticeBox" class="text-xs text-red-600 font-semibold bg-red-50 py-3 px-4 rounded-xl border border-red-200 shadow-2xs leading-relaxed transition-all">
                                    <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>
                                    <span id="previewNoticeText">
                                        <?php if (!empty($catatan_kuota_custom)): ?>
                                            <?= nl2br(htmlspecialchars($catatan_kuota_custom)) ?>
                                        <?php else: ?>
                                            Catatan: Jika kuota (<strong id="prevSmp"><?= $kuota_smp ?> SMP</strong> &amp; <strong id="prevSma"><?= $kuota_sma ?> SMA</strong>) sudah terpenuhi pada Gelombang 1, maka Gelombang berikut nya otomatis <span class="font-bold underline">tidak akan dibuka</span>.
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <p id="previewNoticeHiddenMsg" class="text-xs text-gray-400 italic py-2 text-center hidden">
                                    (Pengumuman catatan kuota dinonaktifkan / disembunyikan dari web publik)
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. MAIN WORKSPACE: FORM GELOMBANG (KIRI) & DAFTAR (KANAN) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                
                <!-- ======================================================== -->
                <!-- A. FORM TAMBAH / EDIT GELOMBANG (4 / 12 COLS) -->
                <!-- ======================================================== -->
                <div id="formSection" class="lg:col-span-5 bg-white rounded-2xl shadow-xs border border-gray-200/80 p-5 space-y-4">
                    
                    <!-- Form Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-xl <?= $edit_data ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-brand-primary/10 text-brand-primary' ?> flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid <?= $edit_data ? 'fa-pen-to-square' : 'fa-calendar-plus' ?>"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-gray-900">
                                    <?= $edit_data ? 'Edit Jadwal Gelombang' : 'Tambah Gelombang Baru' ?>
                                </h2>
                                <p class="text-[11px] text-gray-500">
                                    <?= $edit_data ? 'Perbarui jadwal & status gelombang' : 'Buat periode pendaftaran santri baru' ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($edit_data): ?>
                            <a href="gelombang.php" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2.5 py-1 rounded-lg font-bold transition flex items-center space-x-1" title="Batalkan Mode Edit">
                                <i class="fa-solid fa-xmark text-xs"></i>
                                <span>Batal</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($edit_data): ?>
                        <div class="p-2.5 rounded-xl bg-blue-50/70 border border-blue-200 text-blue-900 text-xs flex items-center space-x-2">
                            <i class="fa-solid fa-info-circle text-blue-600"></i>
                            <span>Sedang mengedit <strong><?= htmlspecialchars($edit_data['nama_gelombang']) ?></strong> (ID: <?= $edit_data['id'] ?>).</span>
                        </div>
                    <?php endif; ?>

                    <form action="gelombang.php" method="POST" id="formGelombang" class="space-y-4">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="action" value="edit_gelombang">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add_gelombang">
                        <?php endif; ?>

                        <!-- 1. Nama Gelombang & Tahun Ajaran -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="nama_gelombang" class="block text-[11px] font-extrabold text-gray-700 uppercase tracking-wider mb-1">
                                    Nama Gelombang <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="nama_gelombang" id="nama_gelombang" required placeholder="Contoh: Gelombang 1" value="<?= htmlspecialchars($edit_data['nama_gelombang'] ?? '') ?>" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 bg-white transition">
                            </div>
                            <div>
                                <label for="tahun_ajaran" class="block text-[11px] font-extrabold text-gray-700 uppercase tracking-wider mb-1">
                                    Tahun Ajaran <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="tahun_ajaran" id="tahun_ajaran" required placeholder="Contoh: 2026/2027" value="<?= htmlspecialchars($edit_data['tahun_ajaran'] ?? '2026/2027') ?>" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 bg-white transition">
                            </div>
                        </div>

                        <!-- 2. Jenjang yang Dibuka -->
                        <div>
                            <label class="block text-[11px] font-extrabold text-gray-700 uppercase tracking-wider mb-1">
                                Jenjang yang Dibuka <span class="text-rose-500">*</span>
                            </label>
                            <?php $current_jenjang = $edit_data['jenjang_dibuka'] ?? 'Semua'; ?>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center <?= ($current_jenjang === 'Semua') ? 'border-brand-primary bg-brand-primary/5 text-brand-primary font-black' : 'border-gray-200 bg-gray-50/70 text-gray-600 font-semibold hover:bg-gray-100' ?>">
                                    <input type="radio" name="jenjang_dibuka" value="Semua" <?= ($current_jenjang === 'Semua') ? 'checked' : '' ?> class="sr-only">
                                    <i class="fa-solid fa-school-flag text-xs mb-1"></i>
                                    <span class="text-[10px]">Semua (SMP &amp; SMA)</span>
                                </label>
                                <label class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center <?= ($current_jenjang === 'Hanya SMP') ? 'border-teal-600 bg-teal-50 text-teal-800 font-black' : 'border-gray-200 bg-gray-50/70 text-gray-600 font-semibold hover:bg-gray-100' ?>">
                                    <input type="radio" name="jenjang_dibuka" value="Hanya SMP" <?= ($current_jenjang === 'Hanya SMP') ? 'checked' : '' ?> class="sr-only">
                                    <i class="fa-solid fa-school text-xs mb-1 text-teal-700"></i>
                                    <span class="text-[10px]">Hanya SMP IT</span>
                                </label>
                                <label class="flex flex-col items-center justify-center p-2 rounded-xl border cursor-pointer transition text-center <?= ($current_jenjang === 'Hanya SMA') ? 'border-indigo-600 bg-indigo-50 text-indigo-800 font-black' : 'border-gray-200 bg-gray-50/70 text-gray-600 font-semibold hover:bg-gray-100' ?>">
                                    <input type="radio" name="jenjang_dibuka" value="Hanya SMA" <?= ($current_jenjang === 'Hanya SMA') ? 'checked' : '' ?> class="sr-only">
                                    <i class="fa-solid fa-graduation-cap text-xs mb-1 text-indigo-700"></i>
                                    <span class="text-[10px]">Hanya SMA IT</span>
                                </label>
                            </div>
                        </div>

                        <!-- 3. Periode Pendaftaran (Tanggal Buka s/d Tanggal Tutup) -->
                        <div class="p-3 bg-gray-50/80 rounded-xl border border-gray-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-gray-700 uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-calendar-days text-brand-primary mr-1.5"></i> Periode Pendaftaran
                                </span>
                                <div class="flex items-center space-x-1">
                                    <button type="button" onclick="setDurasiPendaftaran(30)" class="btn-preset" title="Set Tanggal Tutup 30 hari dari Buka">+30 Hari</button>
                                    <button type="button" onclick="setDurasiPendaftaran(60)" class="btn-preset" title="Set Tanggal Tutup 60 hari dari Buka">+60 Hari</button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label for="tgl_buka" class="block text-[10px] font-bold text-gray-600 mb-1">Tanggal Buka</label>
                                    <input type="date" name="tgl_buka" id="tgl_buka" required value="<?= $edit_data['tgl_buka'] ?? '' ?>" class="w-full border border-gray-300 focus:border-brand-primary rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 bg-white">
                                </div>
                                <div>
                                    <label for="tgl_tutup" class="block text-[10px] font-bold text-gray-600 mb-1">Tanggal Tutup</label>
                                    <input type="date" name="tgl_tutup" id="tgl_tutup" required value="<?= $edit_data['tgl_tutup'] ?? '' ?>" class="w-full border border-gray-300 focus:border-brand-primary rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 bg-white">
                                </div>
                            </div>
                            <p id="durasiPreview" class="text-[10px] text-gray-500 font-medium"></p>
                        </div>

                        <!-- 4. Agenda Seleksi & Pengumuman -->
                        <div class="p-3 bg-gray-50/80 rounded-xl border border-gray-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-gray-700 uppercase tracking-wider flex items-center">
                                    <i class="fa-solid fa-clipboard-check text-amber-700 mr-1.5"></i> Agenda Ujian &amp; Pengumuman
                                </span>
                                <div class="flex items-center space-x-1">
                                    <button type="button" onclick="setJadwalUjianOtomatis()" class="btn-preset" title="Set Tanggal Ujian 7 hari setelah pendaftaran ditutup">Ujian +7h</button>
                                    <button type="button" onclick="setJadwalPengumumanOtomatis()" class="btn-preset" title="Set Tanggal Pengumuman 7 hari setelah ujian">Hasil +7h</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label for="tgl_ujian" class="block text-[10px] font-bold text-indigo-900 mb-1">
                                        <i class="fa-solid fa-file-pen text-indigo-600 mr-0.5"></i> Tanggal Ujian
                                    </label>
                                    <input type="date" name="tgl_ujian" id="tgl_ujian" required value="<?= $edit_data['tgl_ujian'] ?? '' ?>" class="w-full border border-gray-300 focus:border-indigo-600 rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 bg-white">
                                </div>
                                <div>
                                    <label for="tgl_pengumuman" class="block text-[10px] font-bold text-purple-900 mb-1">
                                        <i class="fa-solid fa-trophy text-purple-600 mr-0.5"></i> Pengumuman
                                    </label>
                                    <input type="date" name="tgl_pengumuman" id="tgl_pengumuman" required value="<?= $edit_data['tgl_pengumuman'] ?? '' ?>" class="w-full border border-gray-300 focus:border-purple-600 rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 bg-white">
                                </div>
                            </div>
                        </div>

                        <!-- 5. Status Publikasi -->
                        <div>
                            <label for="status" class="block text-[11px] font-extrabold text-gray-700 uppercase tracking-wider mb-1">
                                Status Publikasi Gelombang <span class="text-rose-500">*</span>
                            </label>
                            <select name="status" id="status" class="w-full border border-gray-300 focus:border-brand-primary rounded-xl px-3 py-2 text-xs font-bold text-gray-800 bg-white">
                                <option value="aktif" <?= ($edit_data && $edit_data['status'] === 'aktif') || (!$edit_data) ? 'selected' : '' ?>>
                                    🟢 Aktif (Tampilkan di Jadwal &amp; Formulir Web)
                                </option>
                                <option value="nonaktif" <?= ($edit_data && $edit_data['status'] === 'nonaktif') ? 'selected' : '' ?>>
                                    ⚪ Nonaktif (Sembunyikan dari Publik)
                                </option>
                            </select>
                            <p class="text-[10px] text-gray-500 mt-1 leading-normal">
                                Gelombang aktif akan otomatis dapat dipilih oleh calon santri saat periode pendaftaran telah tiba.
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <?php if ($edit_data): ?>
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-2.5 px-4 rounded-xl text-xs shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2">
                                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                                    <span>Simpan Perubahan Jadwal</span>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="w-full bg-brand-primary hover:bg-brand-deep text-white font-extrabold py-2.5 px-4 rounded-xl text-xs shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Tambahkan Gelombang Baru</span>
                                </button>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>

                <!-- ======================================================== -->
                <!-- B. DAFTAR GELOMBANG: DUAL VIEW (KARTU & TABEL) (7 / 12 COLS) -->
                <!-- ======================================================== -->
                <div class="lg:col-span-7 space-y-4">
                    
                    <!-- Header List & View Switcher -->
                    <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-800 flex items-center justify-center font-bold text-sm">
                                <i class="fa-solid fa-timeline"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-gray-900">Daftar Jadwal Gelombang</h3>
                                <p class="text-[11px] text-gray-500">Terdapat <?= count($gelombang_list) ?> gelombang dalam sistem</p>
                            </div>
                        </div>

                        <!-- View Toggle Tabs -->
                        <div class="flex items-center bg-gray-100 p-1 rounded-xl self-start sm:self-auto">
                            <button type="button" onclick="switchView('cards')" id="tabBtnCards" class="px-3 py-1 rounded-lg text-xs font-extrabold bg-white text-gray-800 shadow-2xs transition flex items-center space-x-1.5">
                                <i class="fa-solid fa-grip text-xs"></i>
                                <span>Kartu Alur</span>
                            </button>
                            <button type="button" onclick="switchView('table')" id="tabBtnTable" class="px-3 py-1 rounded-lg text-xs font-bold text-gray-500 hover:text-gray-800 transition flex items-center space-x-1.5">
                                <i class="fa-solid fa-table-list text-xs"></i>
                                <span>Tabel Data</span>
                            </button>
                        </div>
                    </div>

                    <!-- VIEW 1: TIMELINE CARDS (DEFAULT) -->
                    <div id="viewCards" class="space-y-4">
                        <?php if (count($gelombang_list) === 0): ?>
                            <div class="bg-white p-8 rounded-2xl border border-gray-200 text-center text-gray-400 space-y-2">
                                <i class="fa-regular fa-calendar-xmark text-3xl text-gray-300"></i>
                                <p class="text-xs font-bold">Belum ada gelombang pendaftaran yang dibuat.</p>
                                <p class="text-[11px]">Gunakan formulir di sebelah kiri untuk membuat gelombang pertama.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($gelombang_list as $g): 
                                $meta = get_gelombang_status_meta($g, $today);
                                $is_editing = ($edit_data && $edit_data['id'] == $g['id']);
                                $card_border = $is_editing ? 'border-blue-400 ring-2 ring-blue-100' : ($meta['is_open'] ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-gray-200/80');
                            ?>
                            <div class="bg-white rounded-2xl border <?= $card_border ?> shadow-xs p-5 space-y-4 transition hover:shadow-sm">
                                
                                <!-- Card Header -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-3 border-b border-gray-100">
                                    <div class="flex items-center space-x-2.5">
                                        <div class="w-9 h-9 rounded-xl <?= $meta['is_open'] ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700' ?> flex items-center justify-center font-black text-sm shadow-2xs">
                                            <?= preg_match('/\d+/', $g['nama_gelombang'], $m) ? $m[0] : 'G' ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <h4 class="text-sm font-black text-gray-900"><?= htmlspecialchars($g['nama_gelombang']) ?></h4>
                                                <span class="text-[10px] font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">T.A <?= htmlspecialchars($g['tahun_ajaran']) ?></span>
                                            </div>
                                            <div class="flex items-center space-x-2 text-[10px] text-gray-500 mt-0.5">
                                                <span>Jenjang: <strong class="text-gray-700"><?= htmlspecialchars($g['jenjang_dibuka'] ?? 'Semua') ?></strong></span>
                                                <span>&bull;</span>
                                                <span class="font-mono text-gray-400">ID: <?= $g['id'] ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Pill -->
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black border <?= $meta['badge_bg'] ?>">
                                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?= $meta['dot_color'] ?>"></span>
                                            <?= $meta['label'] ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Timeline 4 Tahapan PSB -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 py-1">
                                    
                                    <!-- 1. Buka -->
                                    <div class="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200/70">
                                        <div class="flex items-center space-x-1.5 text-gray-500 text-[10px] font-extrabold uppercase tracking-wider mb-1">
                                            <i class="fa-solid fa-calendar-check text-emerald-600"></i>
                                            <span>Buka</span>
                                        </div>
                                        <div class="text-xs font-black text-gray-900">
                                            <?= date('d M Y', strtotime($g['tgl_buka'])) ?>
                                        </div>
                                        <span class="text-[9px] text-gray-500 block">Pendaftaran mulai</span>
                                    </div>

                                    <!-- 2. Tutup -->
                                    <div class="p-2.5 rounded-xl bg-gray-50/80 border border-gray-200/70">
                                        <div class="flex items-center space-x-1.5 text-gray-500 text-[10px] font-extrabold uppercase tracking-wider mb-1">
                                            <i class="fa-solid fa-hourglass-end text-rose-600"></i>
                                            <span>Tutup</span>
                                        </div>
                                        <div class="text-xs font-black text-gray-900">
                                            <?= date('d M Y', strtotime($g['tgl_tutup'])) ?>
                                        </div>
                                        <span class="text-[9px] text-gray-500 block">Batas akhir berkas</span>
                                    </div>

                                    <!-- 3. Ujian Seleksi -->
                                    <div class="p-2.5 rounded-xl bg-indigo-50/50 border border-indigo-200/70">
                                        <div class="flex items-center space-x-1.5 text-indigo-900 text-[10px] font-extrabold uppercase tracking-wider mb-1">
                                            <i class="fa-solid fa-file-pen text-indigo-600"></i>
                                            <span>Ujian</span>
                                        </div>
                                        <div class="text-xs font-black text-indigo-950">
                                            <?= !empty($g['tgl_ujian']) ? date('d M Y', strtotime($g['tgl_ujian'])) : '-' ?>
                                        </div>
                                        <span class="text-[9px] text-indigo-700/80 block">Tes tertulis &amp; lisan</span>
                                    </div>

                                    <!-- 4. Pengumuman -->
                                    <div class="p-2.5 rounded-xl bg-purple-50/50 border border-purple-200/70">
                                        <div class="flex items-center space-x-1.5 text-purple-900 text-[10px] font-extrabold uppercase tracking-wider mb-1">
                                            <i class="fa-solid fa-trophy text-purple-600"></i>
                                            <span>Kelulusan</span>
                                        </div>
                                        <div class="text-xs font-black text-purple-950">
                                            <?= !empty($g['tgl_pengumuman']) ? date('d M Y', strtotime($g['tgl_pengumuman'])) : '-' ?>
                                        </div>
                                        <span class="text-[9px] text-purple-700/80 block">Pengumuman hasil</span>
                                    </div>

                                </div>

                                <!-- Progress Bar Waktu & Deskripsi Status -->
                                <div>
                                    <div class="flex items-center justify-between text-[10px] text-gray-500 font-bold mb-1">
                                        <span>Status Tahapan: <span class="text-gray-800"><?= $meta['desc'] ?></span></span>
                                        <span><?= $meta['progress'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full <?= $meta['is_open'] ? 'bg-emerald-500' : ($meta['code'] === 'upcoming' ? 'bg-amber-500' : 'bg-brand-primary') ?>" style="width: <?= $meta['progress'] ?>%"></div>
                                    </div>
                                </div>

                                <!-- Footer Card: Registrant Stats & Action Buttons -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-gray-100">
                                    
                                    <!-- Registrants Info -->
                                    <div class="flex items-center space-x-3 text-xs">
                                        <a href="pendaftar.php?gelombang_id=<?= $g['id'] ?>" class="inline-flex items-center space-x-1.5 text-brand-primary hover:text-brand-deep font-bold transition group" title="Lihat semua santri pada gelombang ini">
                                            <i class="fa-solid fa-users text-gray-400 group-hover:text-brand-primary"></i>
                                            <span><?= (int)$g['total_pendaftar'] ?> Santri</span>
                                            <i class="fa-solid fa-chevron-right text-[9px] text-gray-400 group-hover:translate-x-0.5 transition-transform"></i>
                                        </a>
                                        <span class="text-gray-300">|</span>
                                        <span class="text-[11px] text-emerald-700 font-bold">
                                            <i class="fa-solid fa-circle-check text-[10px] mr-1"></i><?= (int)$g['total_lunas'] ?> Lunas
                                        </span>
                                        <span class="text-gray-300">|</span>
                                        <span class="text-[11px] text-indigo-700 font-bold">
                                            <i class="fa-solid fa-graduation-cap text-[10px] mr-1"></i><?= (int)$g['total_lulus'] ?> Lulus
                                        </span>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center space-x-1.5 self-end sm:self-auto">
                                        <!-- Edit Button -->
                                        <a href="gelombang.php?edit_id=<?= $g['id'] ?>#formSection" class="inline-flex items-center space-x-1 bg-white hover:bg-blue-50 text-blue-700 border border-blue-200/80 px-2.5 py-1 rounded-lg text-xs font-bold transition shadow-2xs" title="Edit Rincian Gelombang">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            <span>Edit</span>
                                        </a>

                                        <!-- Toggle Status Button -->
                                        <?php if ($g['status'] === 'aktif'): ?>
                                            <a href="gelombang.php?action=toggle_status&id=<?= $g['id'] ?>&current=aktif" onclick="return confirm('Nonaktifkan gelombang <?= htmlspecialchars($g['nama_gelombang']) ?>? Gelombang ini akan disembunyikan dari portal pendaftaran publik.')" class="inline-flex items-center space-x-1 bg-white hover:bg-gray-100 text-gray-600 border border-gray-200 px-2.5 py-1 rounded-lg text-xs font-bold transition shadow-2xs" title="Nonaktifkan Gelombang">
                                                <i class="fa-solid fa-eye-slash text-xs text-gray-400"></i>
                                                <span>Nonaktifkan</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="gelombang.php?action=toggle_status&id=<?= $g['id'] ?>&current=nonaktif" class="inline-flex items-center space-x-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 px-2.5 py-1 rounded-lg text-xs font-bold transition shadow-2xs" title="Aktifkan Gelombang di Portal Publik">
                                                <i class="fa-solid fa-eye text-xs text-emerald-600"></i>
                                                <span>Aktifkan</span>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Delete Button -->
                                        <a href="gelombang.php?action=delete&id=<?= $g['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus <?= htmlspecialchars($g['nama_gelombang']) ?>? Jika sudah ada santri terdaftar, penghapusan akan dibatalkan secara otomatis.')" class="inline-flex items-center space-x-1 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 px-2 py-1 rounded-lg text-xs font-bold transition shadow-2xs" title="Hapus Gelombang">
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                        </a>
                                    </div>

                                </div>

                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- VIEW 2: TABULAR DATA VIEW (CLEAN DATA TABLE) -->
                    <div id="viewTable" class="bg-white rounded-2xl border border-gray-200/80 shadow-xs p-4 hidden space-y-3">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 uppercase tracking-wider font-extrabold">
                                        <th class="px-4 py-3">Gelombang</th>
                                        <th class="px-4 py-3">Jenjang</th>
                                        <th class="px-4 py-3">T.A</th>
                                        <th class="px-4 py-3">Periode Pendaftaran</th>
                                        <th class="px-4 py-3">Tgl Ujian</th>
                                        <th class="px-4 py-3">Pengumuman</th>
                                        <th class="px-4 py-3 text-center">Pendaftar</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                                    <?php foreach ($gelombang_list as $g): 
                                        $meta = get_gelombang_status_meta($g, $today);
                                    ?>
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="px-4 py-3 font-black text-gray-900"><?= htmlspecialchars($g['nama_gelombang']) ?></td>
                                        <td class="px-4 py-3">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold">
                                                <?= htmlspecialchars($g['jenjang_dibuka'] ?? 'Semua') ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($g['tahun_ajaran']) ?></td>
                                        <td class="px-4 py-3 text-gray-600">
                                            <?= date('d/m/Y', strtotime($g['tgl_buka'])) ?> &ndash; <?= date('d/m/Y', strtotime($g['tgl_tutup'])) ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="bg-indigo-50 text-indigo-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                                <?= !empty($g['tgl_ujian']) ? date('d/m/Y', strtotime($g['tgl_ujian'])) : '-' ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="bg-purple-50 text-purple-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                                <?= !empty($g['tgl_pengumuman']) ? date('d/m/Y', strtotime($g['tgl_pengumuman'])) : '-' ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center font-bold">
                                            <a href="pendaftar.php?gelombang_id=<?= $g['id'] ?>" class="text-brand-primary hover:underline">
                                                <?= (int)$g['total_pendaftar'] ?>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $meta['badge_bg'] ?>">
                                                <?= $meta['label'] ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center space-x-1.5">
                                            <a href="gelombang.php?edit_id=<?= $g['id'] ?>" class="text-blue-600 hover:text-blue-800 p-1 font-bold" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="gelombang.php?action=toggle_status&id=<?= $g['id'] ?>&current=<?= $g['status'] ?>" class="text-gray-500 hover:text-gray-800 p-1" title="Toggle Aktif/Nonaktif">
                                                <i class="fa-solid fa-power-off"></i>
                                            </a>
                                            <a href="gelombang.php?action=delete&id=<?= $g['id'] ?>" onclick="return confirm('Hapus gelombang ini?')" class="text-rose-600 hover:text-rose-800 p-1" title="Hapus">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>

    <!-- Client-side Interactive Script -->
    <script>
        // 1. Switch View: Cards vs Table
        function switchView(view) {
            const cards = document.getElementById('viewCards');
            const table = document.getElementById('viewTable');
            const btnCards = document.getElementById('tabBtnCards');
            const btnTable = document.getElementById('tabBtnTable');

            if (view === 'cards') {
                cards.classList.remove('hidden');
                table.classList.add('hidden');
                btnCards.className = 'px-3 py-1 rounded-lg text-xs font-extrabold bg-white text-gray-800 shadow-2xs transition flex items-center space-x-1.5';
                btnTable.className = 'px-3 py-1 rounded-lg text-xs font-bold text-gray-500 hover:text-gray-800 transition flex items-center space-x-1.5';
            } else {
                cards.classList.add('hidden');
                table.classList.remove('hidden');
                btnTable.className = 'px-3 py-1 rounded-lg text-xs font-extrabold bg-white text-gray-800 shadow-2xs transition flex items-center space-x-1.5';
                btnCards.className = 'px-3 py-1 rounded-lg text-xs font-bold text-gray-500 hover:text-gray-800 transition flex items-center space-x-1.5';
            }
        }

        // 2. Focus form smoothly
        function focusFormGelombang() {
            const formSec = document.getElementById('formSection');
            if (formSec) {
                formSec.scrollIntoView({ behavior: 'smooth' });
                const inputNama = document.getElementById('nama_gelombang');
                if (inputNama) inputNama.focus();
            }
        }

        // 3. Quick Date Presets
        function addDays(dateStr, days) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            d.setDate(d.getDate() + days);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function setDurasiPendaftaran(days) {
            const buka = document.getElementById('tgl_buka');
            const tutup = document.getElementById('tgl_tutup');
            if (!buka.value) {
                // Jika buka kosong, set hari ini
                const today = new Date();
                buka.value = today.toISOString().split('T')[0];
            }
            tutup.value = addDays(buka.value, days);
            updateDurasiPreview();
        }

        function setJadwalUjianOtomatis() {
            const tutup = document.getElementById('tgl_tutup');
            const ujian = document.getElementById('tgl_ujian');
            if (tutup.value) {
                ujian.value = addDays(tutup.value, 7);
            } else {
                alert('Silakan tentukan Tanggal Tutup pendaftaran terlebih dahulu.');
            }
        }

        function setJadwalPengumumanOtomatis() {
            const ujian = document.getElementById('tgl_ujian');
            const pengumuman = document.getElementById('tgl_pengumuman');
            if (ujian.value) {
                pengumuman.value = addDays(ujian.value, 7);
            } else {
                alert('Silakan tentukan Tanggal Ujian seleksi terlebih dahulu.');
            }
        }

        function updateDurasiPreview() {
            const buka = document.getElementById('tgl_buka').value;
            const tutup = document.getElementById('tgl_tutup').value;
            const preview = document.getElementById('durasiPreview');

            if (buka && tutup) {
                const diff = (new Date(tutup) - new Date(buka)) / (1000 * 60 * 60 * 24);
                if (diff < 0) {
                    preview.innerHTML = '<span class="text-rose-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Tanggal tutup tidak boleh sebelum tanggal buka!</span>';
                } else {
                    preview.innerHTML = `<span class="text-emerald-700 font-bold"><i class="fa-solid fa-clock mr-1"></i>Durasi pendaftaran: ${Math.round(diff)} hari</span>`;
                }
            } else {
                preview.innerHTML = '';
            }
        }

        document.getElementById('tgl_buka').addEventListener('change', updateDurasiPreview);
        document.getElementById('tgl_tutup').addEventListener('change', updateDurasiPreview);
        updateDurasiPreview();

        // Form Submit Validation
        document.getElementById('formGelombang').addEventListener('submit', function(e) {
            const buka = document.getElementById('tgl_buka').value;
            const tutup = document.getElementById('tgl_tutup').value;
            if (buka && tutup && tutup < buka) {
                e.preventDefault();
                alert('Kesalahan: Tanggal tutup pendaftaran tidak boleh lebih awal dari tanggal buka!');
                document.getElementById('tgl_tutup').focus();
            }
        });

        // ========================================================
        // 4. Interactive Kuota Adjuster & Live Preview
        // ========================================================
        const totalDaftarSmp = <?= (int)$stat_jenjang['smp']['daftar'] ?>;
        const totalDaftarSma = <?= (int)$stat_jenjang['sma']['daftar'] ?>;
        const totalDaftarAll = <?= (int)$total_daftar_semua ?>;

        function adjustKuota(inputId, delta) {
            const el = document.getElementById(inputId);
            if (!el) return;
            let val = parseInt(el.value, 10) || 0;
            val = Math.max(1, Math.min(1000, val + delta));
            el.value = val;
            updateKuotaLivePreview();
        }

        function resetCatatanDefault() {
            const textarea = document.getElementById('textareaCatatanKuota');
            if (textarea) {
                textarea.value = '';
                updateKuotaLivePreview();
            }
        }

        function updateKuotaLivePreview() {
            const smpVal = parseInt(document.getElementById('inputKuotaSmp').value, 10) || 1;
            const smaVal = parseInt(document.getElementById('inputKuotaSma').value, 10) || 1;
            const totalVal = smpVal + smaVal;
            const isVisible = document.getElementById('checkTampilkanCatatan').checked;
            const customText = (document.getElementById('textareaCatatanKuota').value || '').trim();

            // Update Badges & Labels
            const badgeTotal = document.getElementById('badgeTotalKuota');
            if (badgeTotal) badgeTotal.textContent = totalVal + ' Kursi';

            const lblSmp = document.getElementById('labelKuotaSmpLive');
            if (lblSmp) lblSmp.textContent = smpVal;
            const lblSma = document.getElementById('labelKuotaSmaLive');
            if (lblSma) lblSma.textContent = smaVal;
            const lblTotal = document.getElementById('labelKuotaTotalLive');
            if (lblTotal) lblTotal.textContent = totalVal;

            // Update Progress Bars
            const pctSmp = Math.min(100, Math.round((totalDaftarSmp / smpVal) * 100));
            const pctSma = Math.min(100, Math.round((totalDaftarSma / smaVal) * 100));
            const pctTotal = Math.min(100, Math.round((totalDaftarAll / totalVal) * 100));

            const barSmp = document.getElementById('barSmpLive');
            if (barSmp) barSmp.style.width = pctSmp + '%';
            const barSma = document.getElementById('barSmaLive');
            if (barSma) barSma.style.width = pctSma + '%';
            const barTotal = document.getElementById('barTotalLive');
            if (barTotal) barTotal.style.width = pctTotal + '%';

            // Update Sisa
            const sisaSmp = Math.max(0, smpVal - totalDaftarSmp);
            const sisaSma = Math.max(0, smaVal - totalDaftarSma);
            const sisaSmpEl = document.getElementById('sisaSmpLive');
            if (sisaSmpEl) sisaSmpEl.textContent = 'Sisa ' + sisaSmp + ' Kursi';
            const sisaSmaEl = document.getElementById('sisaSmaLive');
            if (sisaSmaEl) sisaSmaEl.textContent = 'Sisa ' + sisaSma + ' Kursi';

            // Update Notice Box Visibility & Text
            const noticeBox = document.getElementById('previewNoticeBox');
            const hiddenMsg = document.getElementById('previewNoticeHiddenMsg');
            const noticeText = document.getElementById('previewNoticeText');

            if (noticeBox && hiddenMsg) {
                if (isVisible) {
                    noticeBox.classList.remove('hidden');
                    hiddenMsg.classList.add('hidden');
                } else {
                    noticeBox.classList.add('hidden');
                    hiddenMsg.classList.remove('hidden');
                }
            }

            if (noticeText) {
                if (customText) {
                    noticeText.textContent = customText;
                } else {
                    noticeText.innerHTML = `Catatan: Jika kuota (<strong>${smpVal} SMP</strong> &amp; <strong>${smaVal} SMA</strong>) sudah terpenuhi pada Gelombang 1, maka Gelombang berikut nya otomatis <span class="font-bold underline">tidak akan dibuka</span>.`;
                }
            }
        }

        document.getElementById('inputKuotaSmp').addEventListener('input', updateKuotaLivePreview);
        document.getElementById('inputKuotaSma').addEventListener('input', updateKuotaLivePreview);
        document.getElementById('checkTampilkanCatatan').addEventListener('change', updateKuotaLivePreview);
        document.getElementById('textareaCatatanKuota').addEventListener('input', updateKuotaLivePreview);
    </script>

</body>
</html>
