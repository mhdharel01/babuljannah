<?php
// admin/master_biaya.php - UI-UX Pro Max Master Biaya & Full CRUD Jenjang
require_once __DIR__ . '/../config/db.php';

// Cek autentikasi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC - Hanya superadmin dan bendahara
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'bendahara'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk mengelola master biaya.");
}

$success_msg = '';
$error_msg = '';

// =========================================================================
// 1. TANGANI AKSI CRUD (TAMBAH JENJANG, HAPUS JENJANG, UPDATE BANK, UPDATE BIAYA)
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'update_all';

    // AKSI A: Tambah Jenjang / Paket Biaya Baru
    if ($action === 'add_jenjang') {
        $nama_jenjang = trim(sanitize($_POST['nama_jenjang'] ?? ''));
        $kode_jenjang = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['kode_jenjang'] ?? '')));
        
        if (empty($kode_jenjang) && !empty($nama_jenjang)) {
            $kode_jenjang = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '_', $nama_jenjang)));
        }

        if (empty($nama_jenjang) || empty($kode_jenjang)) {
            $error_msg = "Nama jenjang dan kode jenjang tidak boleh kosong!";
        } else {
            // Cek apakah kode sudah digunakan
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM master_biaya_pendidikan WHERE kode_jenjang = ?");
            $stmt_check->execute([$kode_jenjang]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_msg = "Kode jenjang '{$kode_jenjang}' sudah terdaftar. Gunakan kode lain.";
            } else {
                $uang_pangkal = (int)str_replace('.', '', $_POST['uang_pangkal'] ?? 0);
                $seragam = (int)str_replace('.', '', $_POST['seragam'] ?? 0);
                $kegiatan = (int)str_replace('.', '', $_POST['kegiatan'] ?? 0);
                $spp_bulanan = (int)str_replace('.', '', $_POST['spp_bulanan'] ?? 0);
                $deskripsi = trim(sanitize(html_entity_decode($_POST['deskripsi'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $catatan = trim(sanitize(html_entity_decode($_POST['catatan'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                try {
                    $stmt_ins = $db->prepare("
                        INSERT INTO master_biaya_pendidikan 
                        (kode_jenjang, jenjang, deskripsi, uang_pangkal, seragam, kegiatan, spp_bulanan, catatan) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_ins->execute([
                        $kode_jenjang,
                        $nama_jenjang,
                        $deskripsi,
                        $uang_pangkal,
                        $seragam,
                        $kegiatan,
                        $spp_bulanan,
                        $catatan
                    ]);
                    $success_msg = "Jenjang '{$nama_jenjang}' berhasil ditambahkan ke Master Biaya!";
                } catch (Throwable $e) {
                    $error_msg = "Gagal menambahkan jenjang: " . $e->getMessage();
                }
            }
        }
    }

    // AKSI B: Hapus Jenjang
    elseif ($action === 'delete_jenjang') {
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id > 0) {
            try {
                // Cek nama jenjang sebelum dihapus
                $stmt_cek = $db->prepare("SELECT jenjang FROM master_biaya_pendidikan WHERE id = ?");
                $stmt_cek->execute([$del_id]);
                $jenjang_nama = $stmt_cek->fetchColumn();

                $stmt_del = $db->prepare("DELETE FROM master_biaya_pendidikan WHERE id = ?");
                $stmt_del->execute([$del_id]);
                $success_msg = "Jenjang '" . ($jenjang_nama ?: "ID #$del_id") . "' berhasil dihapus dari Master Biaya!";
            } catch (Throwable $e) {
                $error_msg = "Gagal menghapus jenjang: " . $e->getMessage();
            }
        }
    }

    // AKSI C: Update Informasi Rekening Bank
    elseif ($action === 'update_bank') {
        $bank_nama = trim(sanitize($_POST['bank_nama'] ?? 'Bank Syariah Indonesia (BSI)'));
        $bank_rekening = trim(sanitize($_POST['bank_rekening'] ?? '7300750308'));
        $bank_atas_nama = trim(sanitize($_POST['bank_atas_nama'] ?? 'Pesantren Babul Jannah'));

        try {
            $stmt_set = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = ?");
            $stmt_set->execute(['bank_nama', $bank_nama, $bank_nama]);
            $stmt_set->execute(['bank_rekening', $bank_rekening, $bank_rekening]);
            $stmt_set->execute(['bank_atas_nama', $bank_atas_nama, $bank_atas_nama]);
            $success_msg = "Informasi rekening bank pembayaran resmi berhasil diperbarui!";
        } catch (Throwable $e) {
            $error_msg = "Gagal menyimpan data rekening: " . $e->getMessage();
        }
    }

    // AKSI D: Simpan Semua Pengaturan Biaya (Update All)
    elseif ($action === 'update_all') {
        try {
            $db->beginTransaction();

            // 1. Update Biaya Tes Seleksi per Jenjang & Global
            $biaya_smp_post = (int)str_replace('.', '', $_POST['biaya_tes_smp'] ?? '300000');
            $biaya_sma_post = (int)str_replace('.', '', $_POST['biaya_tes_sma'] ?? '350000');
            $biaya_def_post = (int)str_replace('.', '', $_POST['biaya_tes_pendaftaran'] ?? '350000');

            $stmt_set = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = ?");
            $stmt_set->execute(['biaya_tes_smp', $biaya_smp_post, $biaya_smp_post]);
            $stmt_set->execute(['biaya_tes_sma', $biaya_sma_post, $biaya_sma_post]);
            $stmt_set->execute(['biaya_tes_pendaftaran', $biaya_def_post, $biaya_def_post]);

            // 2. Update Rincian Biaya Pendidikan untuk Setiap Jenjang yang Terdaftar
            if (isset($_POST['biaya']) && is_array($_POST['biaya'])) {
                $stmt_update = $db->prepare("
                    UPDATE master_biaya_pendidikan 
                    SET deskripsi = ?, uang_pangkal = ?, seragam = ?, kegiatan = ?, spp_bulanan = ?, catatan = ? 
                    WHERE id = ?
                ");

                foreach ($_POST['biaya'] as $id => $data) {
                    $uang_pangkal = (int)str_replace('.', '', $data['uang_pangkal'] ?? 0);
                    $seragam = (int)str_replace('.', '', $data['seragam'] ?? 0);
                    $kegiatan = (int)str_replace('.', '', $data['kegiatan'] ?? 0);
                    $spp_bulanan = (int)str_replace('.', '', $data['spp_bulanan'] ?? 0);
                    $deskripsi = trim(sanitize(html_entity_decode($data['deskripsi'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    $catatan = trim(sanitize(html_entity_decode($data['catatan'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                    $stmt_update->execute([
                        $deskripsi,
                        $uang_pangkal,
                        $seragam,
                        $kegiatan,
                        $spp_bulanan,
                        $catatan,
                        (int)$id
                    ]);
                }
            }

            $db->commit();
            $success_msg = "Pengaturan Master Biaya Pendidikan & Formulir berhasil diperbarui!";
        } catch (Throwable $e) {
            $db->rollBack();
            $error_msg = "Gagal menyimpan perubahan: " . $e->getMessage();
        }
    }
}

// =========================================================================
// 2. AMBIL DATA TERBARU DARI DATABASE
// =========================================================================

// Biaya Formulir & Tes Masuk
$biaya_tes_smp = 300000;
$biaya_tes_sma = 350000;
$biaya_tes_def = 350000;

// Info Rekening Bank Pembayaran Resmi
$bank_nama = 'Bank Syariah Indonesia (BSI)';
$bank_rekening = '7300750308';
$bank_atas_nama = 'Pesantren Babul Jannah';

try {
    $stmt_b = $db->query("SELECT kunci, nilai FROM pengaturan_sistem WHERE kunci IN ('biaya_tes_smp', 'biaya_tes_sma', 'biaya_tes_pendaftaran', 'bank_nama', 'bank_rekening', 'bank_atas_nama')");
    while ($rb = $stmt_b->fetch(PDO::FETCH_ASSOC)) {
        if ($rb['kunci'] === 'biaya_tes_smp' && is_numeric($rb['nilai'])) {
            $biaya_tes_smp = (int)$rb['nilai'];
        } elseif ($rb['kunci'] === 'biaya_tes_sma' && is_numeric($rb['nilai'])) {
            $biaya_tes_sma = (int)$rb['nilai'];
        } elseif ($rb['kunci'] === 'biaya_tes_pendaftaran' && is_numeric($rb['nilai'])) {
            $biaya_tes_def = (int)$rb['nilai'];
        } elseif ($rb['kunci'] === 'bank_nama' && !empty($rb['nilai'])) {
            $bank_nama = $rb['nilai'];
        } elseif ($rb['kunci'] === 'bank_rekening' && !empty($rb['nilai'])) {
            $bank_rekening = $rb['nilai'];
        } elseif ($rb['kunci'] === 'bank_atas_nama' && !empty($rb['nilai'])) {
            $bank_atas_nama = $rb['nilai'];
        }
    }
} catch (Throwable $e) {
    // Fallback default
}

// Ambil Data Rincian Biaya (Semua Jenjang Dinamis)
$biaya_pendidikan = [];
try {
    $biaya_pendidikan = $db->query("SELECT * FROM master_biaya_pendidikan ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $biaya_pendidikan = [];
}

$page_title = 'Kelola Master Biaya - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<!-- Custom CSS: Anti-Double Scroll, Sleek Scrollbar & Solid Colors -->
<style>
    /* 1. Kunci Viewport: Hilangkan Dobel Scrollbar */
    html, body {
        height: 100% !important;
        max-height: 100vh !important;
        overflow: hidden !important;
    }

    /* 2. Scrollbar Ramping & Halus untuk Area Utama */
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

    /* 3. Warna Solid Brand Resmi Babul Jannah */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-dark { background-color: #3E2723 !important; }
    .bg-brand-deep { background-color: #4A3029 !important; }
    .bg-brand-secondary { background-color: #A17A6F !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .text-brand-secondary { color: #A17A6F !important; }

    /* Kartu Estimasi Total Masuk Awal (Solid Dark Gradient) */
    .card-total-masuk {
        background: linear-gradient(135deg, #4A3029 0%, #5C3E35 60%, #3E2723 100%) !important;
        color: #FFFFFF !important;
        border: 1px solid #6E493F !important;
        box-shadow: 0 4px 14px rgba(62, 39, 35, 0.2) !important;
    }
    .card-total-masuk * {
        color: #FFFFFF !important;
    }
    .card-total-masuk .total-amount {
        color: #FDE047 !important;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    /* ======================================================== */
    /* INPUT GROUP RUPIAH ANTI-MENUMPUK (100% BULLETPROOF)      */
    /* ======================================================== */
    .input-rp-group {
        display: flex !important;
        align-items: stretch !important;
        width: 100% !important;
        background-color: #FFFFFF !important;
        border: 1px solid #D1D5DB !important;
        border-radius: 0.75rem !important;
        overflow: hidden !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }
    .input-rp-group:focus-within {
        border-color: #5C3E35 !important;
        box-shadow: 0 0 0 3px rgba(92, 62, 53, 0.15) !important;
    }
    .input-rp-addon {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 10px !important;
        background-color: #F3F4F6 !important;
        color: #4B5563 !important;
        font-weight: 800 !important;
        font-size: 11px !important;
        border-right: 1px solid #E5E7EB !important;
        user-select: none !important;
        flex-shrink: 0 !important;
    }
    .input-rp-control {
        flex: 1 1 auto !important;
        width: 100% !important;
        border: none !important;
        outline: none !important;
        padding: 7px 10px !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        color: #1F2937 !important;
        background-color: #FFFFFF !important;
    }
    .input-rp-control:focus {
        outline: none !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Varian Khusus SPP (Emerald) */
    .input-rp-group-emerald {
        border-color: #A7F3D0 !important;
        background-color: #F0FDF4 !important;
    }
    .input-rp-group-emerald:focus-within {
        border-color: #059669 !important;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15) !important;
    }
    .input-rp-group-emerald .input-rp-addon {
        background-color: #DCFCE7 !important;
        color: #065F46 !important;
        border-right: 1px solid #A7F3D0 !important;
    }
    .input-rp-group-emerald .input-rp-control {
        color: #064E3B !important;
        font-weight: 900 !important;
        background-color: #F0FDF4 !important;
    }

    /* Varian Khusus Tes SMP (Teal) */
    .input-rp-group-teal {
        border-color: #99F6E4 !important;
        background-color: #FFFFFF !important;
    }
    .input-rp-group-teal:focus-within {
        border-color: #0D9488 !important;
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15) !important;
    }
    .input-rp-group-teal .input-rp-addon {
        background-color: #CCFBF1 !important;
        color: #0F766E !important;
        border-right: 1px solid #99F6E4 !important;
        font-size: 12px !important;
        padding: 0 12px !important;
    }
    .input-rp-group-teal .input-rp-control {
        font-size: 14px !important;
        font-weight: 900 !important;
        color: #134E4A !important;
        padding: 8px 12px !important;
    }

    /* Varian Khusus Tes SMA (Indigo) */
    .input-rp-group-indigo {
        border-color: #C7D2FE !important;
        background-color: #FFFFFF !important;
    }
    .input-rp-group-indigo:focus-within {
        border-color: #4F46E5 !important;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important;
    }
    .input-rp-group-indigo .input-rp-addon {
        background-color: #E0E7FF !important;
        color: #3730A3 !important;
        border-right: 1px solid #C7D2FE !important;
        font-size: 12px !important;
        padding: 0 12px !important;
    }
    .input-rp-group-indigo .input-rp-control {
        font-size: 14px !important;
        font-weight: 900 !important;
        color: #312E81 !important;
        padding: 8px 12px !important;
    }

    /* Varian Khusus Tes Global / Kepondokan (Amber) */
    .input-rp-group-amber {
        border-color: #FDE68A !important;
        background-color: #FFFFFF !important;
    }
    .input-rp-group-amber:focus-within {
        border-color: #D97706 !important;
        box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15) !important;
    }
    .input-rp-group-amber .input-rp-addon {
        background-color: #FEF3C7 !important;
        color: #92400E !important;
        border-right: 1px solid #FDE68A !important;
        font-size: 12px !important;
        padding: 0 12px !important;
    }
    .input-rp-group-amber .input-rp-control {
        font-size: 14px !important;
        font-weight: 900 !important;
        color: #78350F !important;
        padding: 8px 12px !important;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Main Container (Hanya 1 Scrollbar Ini) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & INFO BAR -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        <span>Master Konfigurasi Biaya</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">PSB T.A <?= htmlspecialchars(function_exists('get_tahun_ajaran_aktif') ? get_tahun_ajaran_aktif() : '2026/2027') ?></span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola biaya tes seleksi awal, uang pangkal, perlengkapan, biaya semester, serta SPP bulanan santri baru.
                    </p>
                </div>

                <!-- Action Buttons & Quick Links -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Bank Account Badge (Clickable to Edit) -->
                    <div class="flex items-center space-x-2 bg-emerald-50/90 border border-emerald-300/80 px-3 py-1.5 rounded-xl text-xs text-emerald-900 shadow-2xs">
                        <div class="w-6 h-6 rounded-md bg-emerald-700 text-white flex items-center justify-center font-black text-[10px] shadow-xs">
                            <i class="fa-solid fa-building-columns text-[10px]"></i>
                        </div>
                        <div class="leading-tight">
                            <span class="text-[9px] text-emerald-700 block font-bold uppercase tracking-wider"><?= htmlspecialchars($bank_nama) ?></span>
                            <span class="font-mono font-black text-emerald-950 text-xs"><?= htmlspecialchars($bank_rekening) ?></span>
                        </div>
                        <button type="button" onclick="openModalBank()" class="p-1 text-emerald-700 hover:text-emerald-950 hover:bg-emerald-100 rounded-md transition" title="Ubah Rekening Bank">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                    </div>

                    <!-- Shortcut to Public Website Section -->
                    <a href="../index.php#biaya" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat Tampilan Biaya di Website Publik">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat di Web</span>
                    </a>

                    <!-- Save Trigger Button -->
                    <button type="button" onclick="document.getElementById('formMasterBiaya').submit();" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-4 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Simpan Perubahan Master Biaya">
                        <i class="fa-solid fa-floppy-disk text-xs"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_msg): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($success_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($error_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Main Form Update Semua Biaya -->
            <form id="formMasterBiaya" action="" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="update_all">
                
                <!-- ======================================================== -->
                <!-- 2. BIAYA FORMULIR & TES SELEKSI (PER JENJANG & GLOBAL) -->
                <!-- ======================================================== -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 mb-4 border-b border-gray-100">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-sm font-bold">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div>
                                <h2 class="text-sm sm:text-base font-extrabold text-gray-900">
                                    Biaya Formulir &amp; Ujian Seleksi Calon Santri
                                </h2>
                                <p class="text-[11px] text-gray-500">
                                    Biaya administrasi awal yang dibayarkan wali santri sebelum mengikuti ujian masuk pondok.
                                </p>
                            </div>
                        </div>

                        <!-- Quick Sync Button -->
                        <button type="button" onclick="samakanSemuaTarifTes()" class="text-xs font-bold text-brand-primary hover:text-brand-deep hover:underline inline-flex items-center space-x-1 self-start sm:self-auto">
                            <i class="fa-solid fa-arrows-rotate text-[10px]"></i>
                            <span>Samakan Tarif Semua Jenjang</span>
                        </button>
                    </div>

                    <!-- Grid 3 Tarif Biaya Tes -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        
                        <!-- 1. Biaya Tes SMP IT -->
                        <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-teal-100 text-teal-800 border border-teal-300/70">
                                        <i class="fa-solid fa-school text-[8px] mr-1"></i> SMP IT
                                    </span>
                                    <span class="text-[10px] text-teal-700 font-bold">Seleksi Masuk</span>
                                </div>
                                <label class="block text-xs font-extrabold text-teal-950 mb-1.5">Tarif Biaya Tes SMP IT</label>
                                <div class="input-rp-group input-rp-group-teal">
                                    <span class="input-rp-addon">Rp</span>
                                    <input type="text" id="inputBiayaSmp" name="biaya_tes_smp" value="<?= number_format($biaya_tes_smp, 0, '', '.') ?>" class="input-rp-control format-rupiah" required>
                                </div>
                            </div>
                            <p class="text-[10px] text-teal-700/80 mt-2 font-medium">
                                Digunakan di formulir pendaftaran, email konfirmasi, dan invoice tes santri SMP IT.
                            </p>
                        </div>

                        <!-- 2. Biaya Tes SMA IT -->
                        <div class="p-4 rounded-xl bg-indigo-50/60 border border-indigo-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-indigo-100 text-indigo-800 border border-indigo-300/70">
                                        <i class="fa-solid fa-graduation-cap text-[8px] mr-1"></i> SMA IT
                                    </span>
                                    <span class="text-[10px] text-indigo-700 font-bold">Seleksi Masuk</span>
                                </div>
                                <label class="block text-xs font-extrabold text-indigo-950 mb-1.5">Tarif Biaya Tes SMA IT</label>
                                <div class="input-rp-group input-rp-group-indigo">
                                    <span class="input-rp-addon">Rp</span>
                                    <input type="text" id="inputBiayaSma" name="biaya_tes_sma" value="<?= number_format($biaya_tes_sma, 0, '', '.') ?>" class="input-rp-control format-rupiah" required>
                                </div>
                            </div>
                            <p class="text-[10px] text-indigo-700/80 mt-2 font-medium">
                                Digunakan di formulir pendaftaran, email konfirmasi, dan invoice tes santri SMA IT.
                            </p>
                        </div>

                        <!-- 3. Biaya Tes Global / Kepondokan -->
                        <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300/70">
                                        <i class="fa-solid fa-mosque text-[8px] mr-1"></i> Global / Kepondokan
                                    </span>
                                    <span class="text-[10px] text-amber-700 font-bold">Tarif Standar</span>
                                </div>
                                <label class="block text-xs font-extrabold text-amber-950 mb-1.5">Tarif Standar / Kepondokan</label>
                                <div class="input-rp-group input-rp-group-amber">
                                    <span class="input-rp-addon">Rp</span>
                                    <input type="text" id="inputBiayaDef" name="biaya_tes_pendaftaran" value="<?= number_format($biaya_tes_def, 0, '', '.') ?>" class="input-rp-control format-rupiah" required>
                                </div>
                            </div>
                            <p class="text-[10px] text-amber-700/80 mt-2 font-medium">
                                Berlaku sebagai tarif umum, jalur Kepondokan, dan fallback utama sistem.
                            </p>
                        </div>

                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- 3. RINCIAN BIAYA DAFTAR ULANG & SPP (PER JENJANG) - FULL CRUD -->
                <!-- ======================================================== -->
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-xl bg-brand-primary text-white flex items-center justify-center text-xs shadow-2xs">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-extrabold text-gray-900">
                                    Rincian Biaya Masuk Awal &amp; SPP Bulanan (Per Jenjang)
                                </h3>
                                <p class="text-[11px] text-gray-500">
                                    Komponen biaya daftar ulang yang tampil di website publik saat santri dinyatakan lulus seleksi.
                                </p>
                            </div>
                        </div>

                        <!-- Tombol Tambah Jenjang Baru -->
                        <button type="button" onclick="openModalTambahJenjang()" class="inline-flex items-center space-x-2 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-4 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95 self-start sm:self-auto">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Tambah Jenjang Biaya</span>
                        </button>
                    </div>

                    <?php if (empty($biaya_pendidikan)): ?>
                        <div class="bg-white rounded-2xl border border-gray-200/80 p-8 text-center">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto text-xl mb-3">
                                <i class="fa-solid fa-circle-question"></i>
                            </div>
                            <h4 class="font-extrabold text-gray-900 text-sm">Belum Ada Rincian Biaya Jenjang</h4>
                            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Klik tombol "+ Tambah Jenjang Biaya" di atas untuk menambahkan rincian biaya jenjang pertama Anda.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                            <?php foreach($biaya_pendidikan as $bp): 
                                $is_smp = stripos($bp['jenjang'], 'SMP') !== false;
                                $is_sma = stripos($bp['jenjang'], 'SMA') !== false;
                                
                                if ($is_smp) {
                                    $theme_border = 'border-teal-200';
                                    $theme_badge = 'bg-teal-50 text-teal-800 border-teal-200';
                                    $theme_icon = 'fa-school';
                                } elseif ($is_sma) {
                                    $theme_border = 'border-indigo-200';
                                    $theme_badge = 'bg-indigo-50 text-indigo-800 border-indigo-200';
                                    $theme_icon = 'fa-graduation-cap';
                                } else {
                                    $theme_border = 'border-amber-200';
                                    $theme_badge = 'bg-amber-50 text-amber-800 border-amber-200';
                                    $theme_icon = 'fa-book-quran';
                                }
                                $total_estimasi = $bp['uang_pangkal'] + $bp['seragam'] + $bp['kegiatan'] + $bp['spp_bulanan'];
                            ?>
                            <div class="bg-white rounded-2xl border <?= $theme_border ?> shadow-xs p-5 flex flex-col justify-between space-y-4 relative group">
                                
                                <!-- Header Card Jenjang -->
                                <div>
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-black <?= $theme_badge ?> border">
                                                <i class="fa-solid <?= $theme_icon ?> mr-1.5 text-xs"></i>
                                                <?= htmlspecialchars($bp['jenjang']) ?>
                                            </span>
                                            <span class="text-[10px] text-gray-400 font-mono uppercase">ID: <?= $bp['id'] ?> (<?= htmlspecialchars($bp['kode_jenjang']) ?>)</span>
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/80">
                                                <i class="fa-solid fa-check text-[9px] mr-1"></i> Aktif di Web
                                            </span>
                                            
                                            <!-- Tombol Hapus Jenjang -->
                                            <button type="button" onclick="confirmHapusJenjang(<?= $bp['id'] ?>, '<?= htmlspecialchars(addslashes($bp['jenjang'])) ?>')" class="w-7 h-7 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 hover:text-rose-700 transition" title="Hapus Jenjang Biaya Ini">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Form Fields Jenjang -->
                                    <div class="mt-4 space-y-3.5">
                                        
                                        <!-- Deskripsi -->
                                        <div>
                                            <label class="block text-[11px] font-extrabold text-gray-700 mb-1">Deskripsi / Penjelasan Singkat</label>
                                            <textarea name="biaya[<?= $bp['id'] ?>][deskripsi]" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary text-xs leading-relaxed text-gray-800 bg-gray-50/50"><?= htmlspecialchars($bp['deskripsi']) ?></textarea>
                                        </div>

                                        <!-- Grid 4 Komponen Biaya -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            
                                            <!-- 1. Uang Pangkal -->
                                            <div class="p-2.5 bg-gray-50/70 rounded-xl border border-gray-200/80">
                                                <label class="block text-[10px] font-extrabold text-gray-600 uppercase tracking-wider mb-1">Uang Pangkal / Pembangunan</label>
                                                <div class="input-rp-group">
                                                    <span class="input-rp-addon">Rp</span>
                                                    <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="pangkal" name="biaya[<?= $bp['id'] ?>][uang_pangkal]" value="<?= number_format($bp['uang_pangkal'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                                </div>
                                            </div>

                                            <!-- 2. Perlengkapan -->
                                            <div class="p-2.5 bg-gray-50/70 rounded-xl border border-gray-200/80">
                                                <label class="block text-[10px] font-extrabold text-gray-600 uppercase tracking-wider mb-1">Perlengkapan</label>
                                                <div class="input-rp-group">
                                                    <span class="input-rp-addon">Rp</span>
                                                    <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="seragam" name="biaya[<?= $bp['id'] ?>][seragam]" value="<?= number_format($bp['seragam'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                                </div>
                                            </div>

                                            <!-- 3. Biaya Semester -->
                                            <div class="p-2.5 bg-gray-50/70 rounded-xl border border-gray-200/80">
                                                <label class="block text-[10px] font-extrabold text-gray-600 uppercase tracking-wider mb-1">Biaya Semester</label>
                                                <div class="input-rp-group">
                                                    <span class="input-rp-addon">Rp</span>
                                                    <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="kegiatan" name="biaya[<?= $bp['id'] ?>][kegiatan]" value="<?= number_format($bp['kegiatan'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                                </div>
                                            </div>

                                            <!-- 4. SPP Bulanan (Highlight Emerald) -->
                                            <div class="p-2.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80">
                                                <label class="block text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mb-1">SPP Bulanan</label>
                                                <div class="input-rp-group input-rp-group-emerald">
                                                    <span class="input-rp-addon">Rp</span>
                                                    <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="spp" name="biaya[<?= $bp['id'] ?>][spp_bulanan]" value="<?= number_format($bp['spp_bulanan'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Catatan Tambahan -->
                                        <div>
                                            <label class="block text-[11px] font-extrabold text-gray-700 mb-1">Catatan Tambahan (Ketentuan Cicilan, dll)</label>
                                            <input type="text" name="biaya[<?= $bp['id'] ?>][catatan]" value="<?= htmlspecialchars(html_entity_decode($bp['catatan'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>" placeholder="Contoh: Biaya dapat dicicil 2 kali atau dibayar tunai." class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:border-brand-primary text-xs text-gray-800 bg-gray-50/50">
                                        </div>

                                    </div>
                                </div>

                                <!-- Live Estimasi Total Masuk Awal (Live Preview Widget) -->
                                <div class="card-total-masuk p-4 rounded-xl flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider block opacity-90">
                                            <i class="fa-solid fa-calculator mr-1"></i> Estimasi Total Masuk Awal (Live)
                                        </span>
                                        <div class="text-xl sm:text-2xl font-black total-amount mt-0.5" id="totalPreview_<?= $bp['id'] ?>">
                                            Rp <?= number_format($total_estimasi, 0, ',', '.') ?>
                                        </div>
                                        <span class="text-[10px] opacity-80 block">Pangkal + Perlengkapan + Biaya Semester + SPP Bulan ke-1</span>
                                    </div>
                                    <div class="text-right hidden sm:block">
                                        <span class="text-[11px] font-bold bg-white/20 px-2.5 py-1 rounded-lg">
                                            <?= htmlspecialchars($bp['jenjang']) ?>
                                        </span>
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ======================================================== -->
                <!-- 4. BOTTOM ACTION TOOLBAR -->
                <!-- ======================================================== -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 font-medium">
                        <i class="fa-solid fa-shield-halved text-emerald-600 mr-1.5"></i>
                        Perubahan akan langsung tersinkron ke portal publik PSB dan kartu peserta calon santri.
                    </div>

                    <div class="flex items-center space-x-2.5 w-full sm:w-auto">
                        <button type="reset" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 font-bold text-xs transition">
                            Reset Form
                        </button>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-brand-primary hover:bg-brand-deep text-white font-black text-xs shadow-md hover:shadow-lg transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span>Simpan Semua Pengaturan Biaya</span>
                        </button>
                    </div>
                </div>

            </form>

        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: TAMBAH JENJANG / PAKET BIAYA BARU -->
    <!-- ========================================================================= -->
    <div id="modalTambahJenjang" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="card-header-brand flex items-center justify-between border-b border-[#3A241E] px-6 py-4.5 flex-shrink-0" style="background: linear-gradient(135deg, #4A3029 0%, #5C3E35 100%) !important; color: #FFFFFF !important;">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-white/10 text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-folder-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Tambah Jenjang Biaya Baru</h3>
                        <p class="text-[11px] text-[#D4C3B8]">Tambahkan rincian paket biaya pendidikan baru ke dalam sistem.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModalTambahJenjang()" class="text-white/70 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form action="" method="POST" class="p-6 overflow-y-auto space-y-4 custom-scrollbar text-xs">
                <input type="hidden" name="action" value="add_jenjang">

                <!-- Nama & Kode Jenjang -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block font-extrabold text-gray-700 mb-1">Nama Jenjang Pendidikan <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_jenjang" required placeholder="Contoh: Program Khusus Takhasus" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs">
                    </div>
                    <div>
                        <label class="block font-extrabold text-gray-700 mb-1">Kode Jenjang (Slug) <span class="text-rose-500">*</span></label>
                        <input type="text" name="kode_jenjang" required placeholder="Contoh: takhasus" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs font-mono lowercase">
                    </div>
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="block font-extrabold text-gray-700 mb-1">Deskripsi Singkat</label>
                    <textarea name="deskripsi" rows="2" placeholder="Rincian estimasi biaya pendidikan awal santri baru..." class="w-full px-3.5 py-2 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs"></textarea>
                </div>

                <!-- 4 Komponen Biaya -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-extrabold text-gray-700 mb-1">Uang Pangkal / Pembangunan</label>
                        <div class="input-rp-group">
                            <span class="input-rp-addon">Rp</span>
                            <input type="text" name="uang_pangkal" value="0" class="input-rp-control format-rupiah modal-calc" id="modalPangkal">
                        </div>
                    </div>
                    <div>
                        <label class="block font-extrabold text-gray-700 mb-1">Perlengkapan</label>
                        <div class="input-rp-group">
                            <span class="input-rp-addon">Rp</span>
                            <input type="text" name="seragam" value="0" class="input-rp-control format-rupiah modal-calc" id="modalSeragam">
                        </div>
                    </div>
                    <div>
                        <label class="block font-extrabold text-gray-700 mb-1">Biaya Semester</label>
                        <div class="input-rp-group">
                            <span class="input-rp-addon">Rp</span>
                            <input type="text" name="kegiatan" value="0" class="input-rp-control format-rupiah modal-calc" id="modalKegiatan">
                        </div>
                    </div>
                    <div>
                        <label class="block font-extrabold text-emerald-800 mb-1">SPP Bulanan</label>
                        <div class="input-rp-group input-rp-group-emerald">
                            <span class="input-rp-addon">Rp</span>
                            <input type="text" name="spp_bulanan" value="0" class="input-rp-control format-rupiah modal-calc" id="modalSpp">
                        </div>
                    </div>
                </div>

                <!-- Catatan Tambahan -->
                <div>
                    <label class="block font-extrabold text-gray-700 mb-1">Catatan Tambahan</label>
                    <input type="text" name="catatan" placeholder="Contoh: Biaya dapat dicicil 2 kali atau dibayar tunai." class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs">
                </div>

                <!-- Preview Total Masuk Awal -->
                <div class="card-total-masuk p-3.5 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider block opacity-90">Estimasi Total Masuk Awal (Preview)</span>
                        <div class="text-lg font-black total-amount mt-0.5" id="modalTotalPreview">Rp 0</div>
                    </div>
                    <span class="text-[10px] opacity-80">Pangkal + Perlengkapan + Biaya Semester + SPP ke-1</span>
                </div>

                <!-- Footer Buttons -->
                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" onclick="closeModalTambahJenjang()" class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold text-xs">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-brand-primary hover:bg-brand-deep text-white font-extrabold text-xs shadow-xs hover:shadow transition">
                        Simpan Jenjang Baru
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: UBAH INFORMASI REKENING BANK PEMBAYARAN -->
    <!-- ========================================================================= -->
    <div id="modalBank" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-md my-auto relative overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <div class="card-header-brand flex items-center justify-between border-b border-[#3A241E] px-6 py-4.5" style="background: linear-gradient(135deg, #4A3029 0%, #5C3E35 100%) !important; color: #FFFFFF !important;">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-white/10 text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-white">Rekening Resmi Pembayaran</h3>
                        <p class="text-[11px] text-[#D4C3B8]">Rekening tujuan transfer biaya formulir santri.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModalBank()" class="text-white/70 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="" method="POST" class="p-6 space-y-4 text-xs">
                <input type="hidden" name="action" value="update_bank">

                <div>
                    <label class="block font-extrabold text-gray-700 mb-1">Nama Bank Resmi</label>
                    <input type="text" name="bank_nama" value="<?= htmlspecialchars($bank_nama) ?>" required placeholder="Contoh: Bank Syariah Indonesia (BSI)" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs font-semibold">
                </div>

                <div>
                    <label class="block font-extrabold text-gray-700 mb-1">Nomor Rekening</label>
                    <input type="text" name="bank_rekening" value="<?= htmlspecialchars($bank_rekening) ?>" required placeholder="Contoh: 7300750308" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs font-mono font-bold">
                </div>

                <div>
                    <label class="block font-extrabold text-gray-700 mb-1">Atas Nama Rekening (A.N.)</label>
                    <input type="text" name="bank_atas_nama" value="<?= htmlspecialchars($bank_atas_nama) ?>" required placeholder="Contoh: Pesantren Babul Jannah" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 text-xs font-semibold">
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" onclick="closeModalBank()" class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 font-bold text-xs">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-brand-primary hover:bg-brand-deep text-white font-extrabold text-xs shadow-xs hover:shadow transition">
                        Simpan Rekening
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Hidden Form for Delete Jenjang -->
    <form id="formDeleteJenjang" action="" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete_jenjang">
        <input type="hidden" name="id" id="deleteJenjangId" value="0">
    </form>

    <!-- JavaScript: Rupiah Formatter, Modals, & Realtime Calculator -->
    <script>
        // 1. Masking Format Rupiah saat Mengetik
        function formatRupiahString(val) {
            let number_string = val.replace(/[^,\d]/g, '').toString();
            let split = number_string.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            return split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        }

        function cleanNumber(val) {
            if (!val) return 0;
            return parseInt(val.toString().replace(/\./g, '').replace(/,/g, '')) || 0;
        }

        function attachRupiahMasking(container = document) {
            container.querySelectorAll('.format-rupiah').forEach(input => {
                input.addEventListener('input', function() {
                    this.value = formatRupiahString(this.value);
                });
                input.addEventListener('keyup', function() {
                    this.value = formatRupiahString(this.value);
                });
            });
        }
        attachRupiahMasking();

        // 2. Kalkulasi Otomatis Total Biaya Masuk Awal (Live Calculator)
        function recalculateJenjangTotal(jenjangId) {
            const pangkalInput = document.querySelector(`input[data-jenjang-id="${jenjangId}"][data-field="pangkal"]`);
            const seragamInput = document.querySelector(`input[data-jenjang-id="${jenjangId}"][data-field="seragam"]`);
            const kegiatanInput = document.querySelector(`input[data-jenjang-id="${jenjangId}"][data-field="kegiatan"]`);
            const sppInput = document.querySelector(`input[data-jenjang-id="${jenjangId}"][data-field="spp"]`);
            const previewEl = document.getElementById(`totalPreview_${jenjangId}`);

            if (!previewEl) return;

            const p = cleanNumber(pangkalInput ? pangkalInput.value : 0);
            const s = cleanNumber(seragamInput ? seragamInput.value : 0);
            const k = cleanNumber(kegiatanInput ? kegiatanInput.value : 0);
            const spp = cleanNumber(sppInput ? sppInput.value : 0);

            const grandTotal = p + s + k + spp;
            previewEl.textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        }

        document.querySelectorAll('.calc-trigger').forEach(input => {
            const jenjangId = input.getAttribute('data-jenjang-id');
            input.addEventListener('input', () => recalculateJenjangTotal(jenjangId));
            input.addEventListener('keyup', () => recalculateJenjangTotal(jenjangId));
            input.addEventListener('change', () => recalculateJenjangTotal(jenjangId));
        });

        // 3. Kalkulasi Otomatis di Modal Tambah Jenjang
        function recalculateModalTotal() {
            const p = cleanNumber(document.getElementById('modalPangkal')?.value || 0);
            const s = cleanNumber(document.getElementById('modalSeragam')?.value || 0);
            const k = cleanNumber(document.getElementById('modalKegiatan')?.value || 0);
            const spp = cleanNumber(document.getElementById('modalSpp')?.value || 0);
            const total = p + s + k + spp;
            const preview = document.getElementById('modalTotalPreview');
            if (preview) preview.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        document.querySelectorAll('.modal-calc').forEach(input => {
            input.addEventListener('input', recalculateModalTotal);
            input.addEventListener('keyup', recalculateModalTotal);
            input.addEventListener('change', recalculateModalTotal);
        });

        // 4. Modal Tambah Jenjang Controls
        function openModalTambahJenjang() {
            document.getElementById('modalTambahJenjang').classList.remove('hidden');
        }
        function closeModalTambahJenjang() {
            document.getElementById('modalTambahJenjang').classList.add('hidden');
        }

        // 5. Modal Bank Controls
        function openModalBank() {
            document.getElementById('modalBank').classList.remove('hidden');
        }
        function closeModalBank() {
            document.getElementById('modalBank').classList.add('hidden');
        }

        // 6. Konfirmasi Hapus Jenjang dengan SweetAlert2
        function confirmHapusJenjang(id, nama) {
            Swal.fire({
                title: 'Hapus Jenjang ' + nama + '?',
                text: 'Data rincian biaya jenjang ini akan dihapus dari sistem dan tidak tampil lagi di web.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E11D48',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus Jenjang',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteJenjangId').value = id;
                    document.getElementById('formDeleteJenjang').submit();
                }
            });
        }

        // 7. Tombol Samakan Semua Tarif Ujian Seleksi
        function samakanSemuaTarifTes() {
            const inputSmp = document.getElementById('inputBiayaSmp');
            const inputSma = document.getElementById('inputBiayaSma');
            const inputDef = document.getElementById('inputBiayaDef');

            const val = inputDef ? inputDef.value : (inputSma ? inputSma.value : '350.000');
            Swal.fire({
                title: 'Samakan Tarif Tes?',
                text: 'Samakan tarif tes seleksi untuk semua jenjang menjadi Rp ' + val + '?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#5C3E35',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Samakan',
                cancelButtonText: 'Batal'
            }).then((res) => {
                if (res.isConfirmed) {
                    if (inputSmp) inputSmp.value = val;
                    if (inputSma) inputSma.value = val;
                    if (inputDef) inputDef.value = val;
                }
            });
        }

        // Close on ESC
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModalTambahJenjang();
                closeModalBank();
            }
        });
    </script>

</body>
</html>
