<?php
// admin/master_biaya.php
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

// Handle Form Submit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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

        // 2. Update Rincian Biaya Pendidikan (SMP IT & SMA IT)
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
                $deskripsi = trim($data['deskripsi'] ?? '');
                $catatan = trim($data['catatan'] ?? '');

                $stmt_update->execute([
                    $deskripsi,
                    $uang_pangkal,
                    $seragam,
                    $kegiatan,
                    $spp_bulanan,
                    $catatan,
                    $id
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

// Ambil Biaya Formulir & Tes Seleksi dari database
$biaya_tes_smp = 300000;
$biaya_tes_sma = 350000;
$biaya_tes_def = 350000;

try {
    $stmt_b = $db->query("SELECT kunci, nilai FROM pengaturan_sistem WHERE kunci IN ('biaya_tes_smp', 'biaya_tes_sma', 'biaya_tes_pendaftaran')");
    while ($rb = $stmt_b->fetch(PDO::FETCH_ASSOC)) {
        if ($rb['kunci'] === 'biaya_tes_smp' && is_numeric($rb['nilai'])) {
            $biaya_tes_smp = (int)$rb['nilai'];
        } elseif ($rb['kunci'] === 'biaya_tes_sma' && is_numeric($rb['nilai'])) {
            $biaya_tes_sma = (int)$rb['nilai'];
        } elseif ($rb['kunci'] === 'biaya_tes_pendaftaran' && is_numeric($rb['nilai'])) {
            $biaya_tes_def = (int)$rb['nilai'];
        }
    }
} catch (Throwable $e) {
    // Gunakan fallback
}

// Ambil Data Rincian Biaya (2 Kelompok: SMP IT & SMA IT)
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
        scrollbar-color: #CBD5E1 transparent;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #CBD5E1;
        border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #94A3B8;
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
                        <span class="text-emerald-700 normal-case font-bold">PSB T.A 2026/2027</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola biaya tes seleksi awal, uang pangkal, seragam, kegiatan, serta SPP bulanan santri baru.
                    </p>
                </div>

                <!-- Action Buttons & Quick Links -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Bank BSI Account Badge -->
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
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($success_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($error_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <form id="formMasterBiaya" action="" method="POST" class="space-y-5">
                
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
                <!-- 3. RINCIAN BIAYA DAFTAR ULANG & SPP (PER JENJANG) -->
                <!-- ======================================================== -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-2.5 px-1">
                        <div class="w-7 h-7 rounded-lg bg-brand-primary text-white flex items-center justify-center text-xs shadow-2xs">
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

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        <?php foreach($biaya_pendidikan as $bp): 
                            $is_smp = stripos($bp['jenjang'], 'SMP') !== false;
                            $theme_border = $is_smp ? 'border-teal-200' : 'border-indigo-200';
                            $theme_badge = $is_smp ? 'bg-teal-50 text-teal-800 border-teal-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200';
                            $theme_icon = $is_smp ? 'fa-school' : 'fa-graduation-cap';
                            $total_estimasi = $bp['uang_pangkal'] + $bp['seragam'] + $bp['kegiatan'] + $bp['spp_bulanan'];
                        ?>
                        <div class="bg-white rounded-2xl border <?= $theme_border ?> shadow-xs p-5 flex flex-col justify-between space-y-4">
                            
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
                                    <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/80">
                                        <i class="fa-solid fa-check text-[9px] mr-1"></i> Aktif di Web
                                    </span>
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

                                        <!-- 2. Seragam & Asrama -->
                                        <div class="p-2.5 bg-gray-50/70 rounded-xl border border-gray-200/80">
                                            <label class="block text-[10px] font-extrabold text-gray-600 uppercase tracking-wider mb-1">Seragam &amp; Asrama</label>
                                            <div class="input-rp-group">
                                                <span class="input-rp-addon">Rp</span>
                                                <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="seragam" name="biaya[<?= $bp['id'] ?>][seragam]" value="<?= number_format($bp['seragam'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                            </div>
                                        </div>

                                        <!-- 3. Kegiatan per Semester -->
                                        <div class="p-2.5 bg-gray-50/70 rounded-xl border border-gray-200/80">
                                            <label class="block text-[10px] font-extrabold text-gray-600 uppercase tracking-wider mb-1">Kegiatan per Semester</label>
                                            <div class="input-rp-group">
                                                <span class="input-rp-addon">Rp</span>
                                                <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="kegiatan" name="biaya[<?= $bp['id'] ?>][kegiatan]" value="<?= number_format($bp['kegiatan'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                            </div>
                                        </div>

                                        <!-- 4. SPP Bulanan (Highlight Emerald) -->
                                        <div class="p-2.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80">
                                            <label class="block text-[10px] font-extrabold text-emerald-800 uppercase tracking-wider mb-1">SPP Bulanan (Makan+Laundry)</label>
                                            <div class="input-rp-group input-rp-group-emerald">
                                                <span class="input-rp-addon">Rp</span>
                                                <input type="text" data-jenjang-id="<?= $bp['id'] ?>" data-field="spp" name="biaya[<?= $bp['id'] ?>][spp_bulanan]" value="<?= number_format($bp['spp_bulanan'], 0, '', '.') ?>" class="input-rp-control format-rupiah calc-trigger">
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Catatan Tambahan -->
                                    <div>
                                        <label class="block text-[11px] font-extrabold text-gray-700 mb-1">Catatan Tambahan (Ketentuan Cicilan, dll)</label>
                                        <input type="text" name="biaya[<?= $bp['id'] ?>][catatan]" value="<?= htmlspecialchars($bp['catatan']) ?>" placeholder="Contoh: Biaya dapat dicicil 2 kali atau dibayar tunai." class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:border-brand-primary text-xs text-gray-800 bg-gray-50/50">
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
                                    <span class="text-[10px] opacity-80 block">Pangkal + Seragam + Kegiatan + SPP Bulan ke-1</span>
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

    <!-- JavaScript: Rupiah Formatter & Realtime Calculator -->
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

        document.querySelectorAll('.format-rupiah').forEach(input => {
            input.addEventListener('input', function() {
                this.value = formatRupiahString(this.value);
            });
            input.addEventListener('keyup', function() {
                this.value = formatRupiahString(this.value);
            });
        });

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

        // 3. Tombol Samakan Semua Tarif Ujian Seleksi
        function samakanSemuaTarifTes() {
            const inputSmp = document.getElementById('inputBiayaSmp');
            const inputSma = document.getElementById('inputBiayaSma');
            const inputDef = document.getElementById('inputBiayaDef');

            const val = inputDef ? inputDef.value : (inputSma ? inputSma.value : '350.000');
            if (confirm('Samakan tarif tes seleksi untuk semua jenjang menjadi Rp ' + val + ' ?')) {
                if (inputSmp) inputSmp.value = val;
                if (inputSma) inputSma.value = val;
                if (inputDef) inputDef.value = val;
            }
        }
    </script>

</body>
</html>
