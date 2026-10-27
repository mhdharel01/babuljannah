<?php
// admin/scan.php
require_once '../config/db.php';

// Proteksi login admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Proteksi Hak Akses (RBAC)
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman scanner presensi.");
}

// Ambil semua gelombang untuk filter dropdown
$stmt_gel = $db->query("SELECT * FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll();

// Default gelombang terpilih (dari GET, gelombang aktif, atau gelombang pertama)
$selected_gelombang = (int)($_GET['gelombang_id'] ?? 0);
if ($selected_gelombang === 0 && !empty($gelombang_list)) {
    foreach ($gelombang_list as $g) {
        if ($g['status'] === 'aktif') {
            $selected_gelombang = $g['id'];
            break;
        }
    }
    if ($selected_gelombang === 0) {
        $selected_gelombang = $gelombang_list[0]['id'];
    }
}

$extra_js = '<script src="https://unpkg.com/html5-qrcode"></script>';
include 'includes/head.php';
?>
<style>
    /* Styling Animasi Laser Scanner */
    @keyframes laserScan {
        0% { top: 8%; opacity: 0.8; }
        50% { top: 88%; opacity: 1; }
        100% { top: 8%; opacity: 0.8; }
    }
    .scanner-laser-line {
        position: absolute;
        left: 5%;
        width: 90%;
        height: 3px;
        background: linear-gradient(90deg, transparent, #10B981, #34D399, #10B981, transparent);
        box-shadow: 0 0 14px 3px rgba(16, 185, 129, 0.7);
        animation: laserScan 2.4s ease-in-out infinite;
        pointer-events: none;
        z-index: 20;
    }
    /* Sudut Frame Bidik Target */
    .scan-bracket {
        position: absolute;
        width: 24px;
        height: 24px;
        border-color: #10B981;
        pointer-events: none;
        z-index: 20;
    }
    .scan-bracket-tl { top: 12px; left: 12px; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 8px; }
    .scan-bracket-tr { top: 12px; right: 12px; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 8px; }
    .scan-bracket-bl { bottom: 12px; left: 12px; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 8px; }
    .scan-bracket-br { bottom: 12px; right: 12px; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 8px; }

    #reader video {
        border-radius: 1rem !important;
        object-fit: cover !important;
        width: 100% !important;
        height: 100% !important;
    }
</style>

<body class="bg-[#FBF9F6] text-[#2F2522] flex h-screen overflow-hidden antialiased">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-screen overflow-hidden min-w-0">
        
        <?php
        $header_custom_title = '
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-[#5C3E35]/10 text-[#5C3E35] flex items-center justify-center text-sm font-bold flex-shrink-0 shadow-2xs">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm sm:text-base lg:text-lg font-black text-[#2F2522] tracking-tight truncate flex items-center">
                        Presensi & Scanner Ujian
                    </h1>
                    <p class="text-[11px] text-gray-500 font-medium hidden md:block truncate">
                        Verifikasi kehadiran fisik santri secara live saat pelaksanaan ujian seleksi
                    </p>
                </div>
            </div>';

        include 'includes/header.php';
        ?>

        <!-- Scrollable Workspace Body -->
        <main class="flex-grow overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-6 pb-20">

                <!-- Control & Filter Bar (Breadcrumb + Gelombang + Jam Live + Controls) -->
                <div class="bg-white rounded-2xl p-4 sm:px-5 border border-gray-200/90 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Left: Breadcrumb -->
                    <nav class="flex items-center space-x-2 text-xs font-semibold text-gray-500 min-w-0">
                        <a href="dashboard.php" class="hover:text-[#5C3E35] transition flex items-center flex-shrink-0">
                            <i class="fa-solid fa-house text-gray-400 mr-1.5 text-[11px]"></i> Dashboard
                        </a>
                        <span class="text-gray-300">/</span>
                        <span class="text-[#5C3E35] font-black truncate">Presensi Ujian Live</span>
                    </nav>

                    <!-- Right: Gelombang Dropdown, Live Clock & Audio/Fullscreen Toggles -->
                    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                        <!-- Filter Gelombang -->
                        <div class="flex items-center space-x-2 bg-stone-50 border border-stone-200/90 px-3 py-1.5 rounded-xl shadow-2xs">
                            <i class="fa-solid fa-filter text-[#5C3E35] text-xs"></i>
                            <span class="text-[11px] font-bold text-gray-600 hidden sm:inline">Gelombang:</span>
                            <select id="filter-gelombang" onchange="changeGelombang(this.value)" class="bg-transparent text-xs font-black text-[#5C3E35] focus:outline-none cursor-pointer">
                                <option value="">-- Semua Gelombang --</option>
                                <?php foreach ($gelombang_list as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= ($selected_gelombang == $g['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['nama_gelombang'] . ' (T.A ' . $g['tahun_ajaran'] . ')' . ($g['status'] === 'aktif' ? ' ★ Aktif' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Live Digital Clock -->
                        <div class="flex items-center space-x-2 bg-stone-50 text-stone-700 border border-stone-200/90 px-3 py-1.5 rounded-xl text-xs font-mono font-bold shadow-2xs">
                            <i class="fa-regular fa-clock text-[#A17A6F]"></i>
                            <span id="live-clock">--:--:-- WIB</span>
                        </div>

                        <!-- Audio Beep Toggle -->
                        <button type="button" id="btn-toggle-sound" onclick="toggleSound()" class="p-2 rounded-xl text-xs font-bold bg-stone-50 hover:bg-stone-100 text-stone-700 border border-stone-200/90 transition shadow-2xs" title="Suara Beep: Aktif">
                            <i id="icon-sound" class="fa-solid fa-volume-high text-[#5C3E35]"></i>
                        </button>

                        <!-- Fullscreen Desk Mode -->
                        <button type="button" onclick="toggleFullScreen()" class="p-2 rounded-xl text-xs font-bold bg-stone-50 hover:bg-stone-100 text-stone-700 border border-stone-200/90 transition shadow-2xs" title="Mode Layar Penuh">
                            <i class="fa-solid fa-expand text-gray-600"></i>
                        </button>
                    </div>
                </div>

                <!-- Stats Banner: 3 Kartu Metrik Live -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Total Peserta Terdaftar (Lunas) -->
                    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/90 shadow-2xs flex items-center justify-between transition hover:shadow-xs">
                        <div class="space-y-1">
                            <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block">Total Peserta Ujian (Lunas)</span>
                            <h3 id="stat-total" class="text-2xl sm:text-3xl font-black text-[#2F2522]">0</h3>
                            <span class="text-[11px] text-gray-500 flex items-center font-medium">
                                <i class="fa-solid fa-id-card mr-1.5 text-[#A17A6F]"></i> Terverifikasi Berkas & Biaya
                            </span>
                        </div>
                        <div class="w-13 h-13 rounded-2xl bg-[#5C3E35]/10 text-[#5C3E35] flex items-center justify-center text-xl shadow-2xs flex-shrink-0">
                            <i class="fa-solid fa-users-viewfinder"></i>
                        </div>
                    </div>

                    <!-- Sudah Hadir (Check-in Live) -->
                    <div class="bg-gradient-to-br from-emerald-800 to-emerald-950 text-white rounded-3xl p-5 sm:p-6 border border-emerald-700 shadow-md flex items-center justify-between relative overflow-hidden transition hover:shadow-lg">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/20 rounded-full blur-xl pointer-events-none"></div>
                        <div class="space-y-1 z-10">
                            <span class="text-[11px] font-extrabold text-emerald-200 uppercase tracking-wider block">Sudah Hadir (Check-in)</span>
                            <div class="flex items-baseline space-x-2">
                                <h3 id="stat-hadir" class="text-2xl sm:text-3xl font-black text-white">0</h3>
                                <span id="stat-percent" class="text-xs font-black bg-emerald-500/30 text-emerald-100 px-2 py-0.5 rounded-full border border-emerald-400/30">0%</span>
                            </div>
                            <span class="text-[11px] text-emerald-200 flex items-center font-medium">
                                <i class="fa-solid fa-circle-check mr-1.5 text-emerald-400"></i> Absensi Fisik Terverifikasi
                            </span>
                        </div>
                        <div class="w-13 h-13 rounded-2xl bg-white/10 text-emerald-200 flex items-center justify-center text-xl border border-white/20 shadow-xs flex-shrink-0 z-10">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>

                    <!-- Belum Hadir / Antri -->
                    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/90 shadow-2xs flex items-center justify-between transition hover:shadow-xs">
                        <div class="space-y-1">
                            <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block">Belum Hadir / Antri</span>
                            <h3 id="stat-belum" class="text-2xl sm:text-3xl font-black text-amber-600">0</h3>
                            <span class="text-[11px] text-gray-500 flex items-center font-medium">
                                <i class="fa-regular fa-clock mr-1.5 text-amber-500"></i> Menunggu Kedatangan Santri
                            </span>
                        </div>
                        <div class="w-13 h-13 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl border border-amber-200 shadow-2xs flex-shrink-0">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>

                <!-- Scanner Live & Verification Result (2 Columns Grid) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- Left Column: Kamera Scanner & Input Cepat (5 Cols) -->
                    <div class="lg:col-span-5 space-y-6">
                        
                        <!-- Kamera Scanner Live Card -->
                        <div class="bg-white rounded-3xl border border-gray-200/90 p-5 sm:p-6 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 class="font-extrabold text-[#2F2522] text-sm flex items-center">
                                    <i class="fa-solid fa-camera mr-2 text-[#5C3E35]"></i> Kamera Scanner Live
                                </h3>
                                <div class="flex items-center space-x-2">
                                    <span id="cam-status" class="bg-stone-100 text-stone-600 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider">
                                        Standby
                                    </span>
                                </div>
                            </div>

                            <!-- Pilihan Kamera (Jika ada lebih dari 1 kamera / Kamera Belakang HP) -->
                            <div id="cam-selection-wrapper" class="hidden">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Perangkat Kamera:</label>
                                <select id="cam-select" onchange="changeCameraDevice(this.value)" class="w-full bg-stone-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#5C3E35]/20">
                                </select>
                            </div>

                            <!-- Video Scanner Viewport -->
                            <div class="bg-stone-900 rounded-2xl overflow-hidden min-h-[280px] flex flex-col items-center justify-center relative border border-stone-800 shadow-inner">
                                <!-- Laser Line & Target Brackets (Hanya aktif saat scan berjalan) -->
                                <div id="laser-effect" class="hidden scanner-laser-line"></div>
                                <div id="brackets-effect" class="hidden">
                                    <div class="scan-bracket scan-bracket-tl"></div>
                                    <div class="scan-bracket scan-bracket-tr"></div>
                                    <div class="scan-bracket scan-bracket-bl"></div>
                                    <div class="scan-bracket scan-bracket-br"></div>
                                </div>

                                <div id="reader" class="w-full h-full"></div>

                                <!-- Placeholder saat kamera mati -->
                                <div id="cam-placeholder" class="text-center p-6 space-y-3 z-10">
                                    <div class="w-16 h-16 rounded-2xl bg-white/10 text-white/60 mx-auto flex items-center justify-center text-3xl shadow-xs">
                                        <i class="fa-solid fa-video-slash"></i>
                                    </div>
                                    <p class="text-xs text-stone-300 font-medium max-w-xs leading-relaxed">
                                        Arahkan kamera ke QR Code pada pojok Kartu Peserta Ujian santri untuk presensi instan.
                                    </p>
                                    <button type="button" onclick="startScanner()" class="inline-flex items-center space-x-2 bg-[#5C3E35] hover:bg-[#4A3029] text-white font-black py-2.5 px-5 rounded-xl text-xs shadow-md transition transform active:scale-95">
                                        <i class="fa-solid fa-play text-xs"></i>
                                        <span>Aktifkan Kamera Sekarang</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Control Buttons -->
                            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                                <button id="btn-start-cam" onclick="startScanner()" class="flex-1 bg-[#5C3E35] hover:bg-[#4A3029] text-white font-extrabold py-3 px-4 rounded-xl text-xs shadow-sm transition flex items-center justify-center space-x-2">
                                    <i class="fa-solid fa-play"></i>
                                    <span>Aktifkan Kamera</span>
                                </button>
                                <button id="btn-stop-cam" onclick="stopScanner()" class="hidden flex-1 bg-red-600 hover:bg-red-700 text-white font-extrabold py-3 px-4 rounded-xl text-xs shadow-sm transition flex items-center justify-center space-x-2">
                                    <i class="fa-solid fa-stop"></i>
                                    <span>Matikan Kamera</span>
                                </button>
                                
                                <!-- Opsi Scan dari File Gambar (Alternatif jika kamera browser terblokir) -->
                                <label class="cursor-pointer px-4 py-3 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1.5 shadow-2xs border border-stone-200" title="Scan dari File Gambar QR">
                                    <i class="fa-solid fa-image text-gray-500"></i>
                                    <span class="hidden sm:inline">Upload QR</span>
                                    <input type="file" id="qr-file-input" accept="image/*" onchange="scanFromImage(this)" class="hidden">
                                </label>
                            </div>
                        </div>

                        <!-- Manual Input Card -->
                        <div class="bg-white rounded-3xl border border-gray-200/90 p-5 sm:p-6 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 class="font-extrabold text-[#2F2522] text-sm flex items-center">
                                    <i class="fa-solid fa-keyboard mr-2 text-[#A17A6F]"></i> Input Manual No. Registrasi
                                </h3>
                                <span class="text-[10px] text-gray-400 font-bold">Alternatif</span>
                            </div>
                            
                            <form onsubmit="manualSearch(event)" class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor Registrasi Santri</label>
                                    <div class="flex space-x-2">
                                        <input type="text" id="manual-reg-input" placeholder="Contoh: PPBJ-2026-001" class="flex-1 bg-stone-50 border border-gray-300 focus:bg-white focus:border-[#5C3E35] focus:ring-2 focus:ring-[#5C3E35]/20 rounded-xl px-4 py-2.5 text-xs sm:text-sm font-mono font-bold uppercase transition focus:outline-none">
                                        <button type="submit" class="bg-[#5C3E35] hover:bg-[#4A3029] text-white font-black px-5 py-2.5 rounded-xl text-xs shadow-sm transition flex items-center space-x-1.5 flex-shrink-0">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                            <span>Periksa</span>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400">
                                    Gunakan ini jika kartu santri rusak, basah, atau kamera perangkat tidak dapat membaca barcode.
                                </p>
                            </form>
                        </div>

                    </div>

                    <!-- Right Column: Card Hasil Pemindaian & Verifikasi (7 Cols) -->
                    <div class="lg:col-span-7">
                        
                        <!-- Placeholder Sebelum Scan -->
                        <div id="result-placeholder" class="bg-white rounded-3xl border border-gray-200/90 p-8 sm:p-12 text-center shadow-sm flex flex-col items-center justify-center min-h-[460px] space-y-4">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-[#5C3E35]/10 text-[#5C3E35] flex items-center justify-center text-4xl shadow-inner border border-[#5C3E35]/20">
                                <i class="fa-solid fa-qrcode animate-pulse"></i>
                            </div>
                            <div class="space-y-1 max-w-sm">
                                <h4 class="text-base sm:text-lg font-black text-[#2F2522]">Menunggu Pemindaian Barcode</h4>
                                <p class="text-xs text-gray-500 leading-relaxed font-medium">
                                    Arahkan kamera ke QR Code pada kartu ujian santri atau ketikkan nomor registrasi untuk menampilkan biodata dan verifikasi kehadiran secara instan.
                                </p>
                            </div>
                        </div>

                        <!-- Scanned Student Result Card -->
                        <div id="result-card" class="hidden bg-white rounded-3xl border-2 border-[#5C3E35]/50 shadow-xl overflow-hidden transition-all duration-300">
                            <!-- Card Header -->
                            <div class="bg-gradient-to-r from-[#5C3E35] to-[#4A3029] text-white p-6 sm:p-7 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div class="flex items-center space-x-4">
                                    <div id="res-avatar" class="w-14 h-14 rounded-2xl bg-white/10 text-white flex items-center justify-center font-black text-2xl border border-white/20 flex-shrink-0">
                                        S
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="text-[10px] bg-white/20 text-white font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-widest">Santri Terdata</span>
                                            <span id="res-jk-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/30 text-blue-100 border border-blue-400/30">Putra</span>
                                        </div>
                                        <h2 id="res-nama" class="text-xl sm:text-2xl font-black text-white tracking-wide uppercase">NAMA SANTRI</h2>
                                        <p id="res-noreg" class="text-xs sm:text-sm font-mono font-bold text-amber-300 mt-0.5">PPBJ-XXXX-XXX</p>
                                    </div>
                                </div>
                                <div id="res-badge-pay" class="bg-emerald-500 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-md flex items-center space-x-1.5 border border-white/20">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>Lunas</span>
                                </div>
                            </div>

                            <!-- Card Body (Biodata Ringkas Santri) -->
                            <div class="p-6 sm:p-8 space-y-6">
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-stone-50 p-5 rounded-2xl border border-stone-200/80 text-xs">
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Gelombang & T.A</span>
                                        <span id="res-gelombang" class="font-bold text-[#5C3E35] text-sm">Gelombang 1</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Jalur Pendidikan</span>
                                        <span id="res-jalur" class="font-bold text-[#A17A6F] text-sm">SMP IT</span>
                                    </div>
                                    <div class="pt-2 border-t border-stone-200 sm:col-span-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Asal Sekolah</span>
                                            <span id="res-sekolah" class="font-bold text-gray-800 text-sm">SD Negeri 1</span>
                                        </div>
                                        <div id="res-ortu-wrapper">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Kontak Wali / Ortu</span>
                                            <a id="res-ortu-wa" href="#" target="_blank" class="font-bold text-[#5C3E35] hover:underline flex items-center text-xs">
                                                <i class="fa-brands fa-whatsapp text-emerald-600 mr-1 text-sm"></i>
                                                <span id="res-ortu-text">Ayah / Ortu</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Alert Peringatan Pembayaran (Muncul jika Belum Lunas) -->
                                <div id="box-warning-bayar" class="hidden bg-amber-50 border-l-4 border-amber-500 p-4 rounded-xl text-xs text-amber-900 space-y-1 shadow-2xs">
                                    <div class="flex items-center space-x-2 font-black text-amber-800">
                                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                                        <span>PERINGATAN STATUS PEMBAYARAN BELUM LUNAS</span>
                                    </div>
                                    <p class="leading-relaxed">
                                        Santri ini tercatat belum melunasi biaya seleksi. Mohon arahkan santri/orang tua ke loket bendahara panitia untuk verifikasi pembayaran sebelum memasuki ruangan ujian.
                                    </p>
                                </div>

                                <!-- Attendance Status Decision Box: Belum Hadir -->
                                <div id="box-belum-hadir" class="bg-amber-50/70 border-2 border-amber-300 p-6 rounded-2xl text-center space-y-4 shadow-sm">
                                    <div class="flex items-center justify-center space-x-2 text-amber-800 font-extrabold text-sm">
                                        <i class="fa-solid fa-clock text-lg"></i>
                                        <span>Status Kehadiran: BELUM CHECK-IN UJIAN</span>
                                    </div>
                                    <p class="text-xs text-amber-900 leading-relaxed max-w-md mx-auto font-medium">
                                        Calon santri ini telah terdaftar dalam sistem. Klik tombol konfirmasi di bawah ini untuk mencatat kedatangannya sekarang.
                                    </p>
                                    <button type="button" onclick="confirmAttendance()" class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-black py-4 px-6 rounded-2xl shadow-lg hover:shadow-xl text-sm md:text-base tracking-wide transition-all transform hover:-translate-y-0.5 flex items-center justify-center space-x-2.5">
                                        <i class="fa-solid fa-check-to-slot text-xl text-emerald-200"></i>
                                        <span>KONFIRMASI HADIR UJIAN (CHECK-IN SEKARANG)</span>
                                    </button>
                                </div>

                                <!-- Already Attended Box: Sudah Hadir -->
                                <div id="box-sudah-hadir" class="hidden bg-emerald-50/80 border-2 border-emerald-400 p-6 rounded-2xl text-center space-y-4 shadow-sm">
                                    <div class="w-16 h-16 rounded-2xl bg-emerald-600 text-white mx-auto flex items-center justify-center text-3xl shadow-md">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <div class="space-y-1">
                                        <span class="inline-block bg-emerald-200 text-emerald-900 font-black text-[10px] uppercase tracking-widest px-3 py-1 rounded-full mb-1">
                                            Terverifikasi Hadir
                                        </span>
                                        <h3 class="text-lg font-black text-emerald-950">Santri Telah Terdata Hadir Ujian</h3>
                                        <p id="res-waktu-hadir" class="text-xs font-bold text-emerald-800">Dicatat pada: --</p>
                                    </div>
                                    <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                                        <button type="button" onclick="resetView()" class="bg-[#5C3E35] hover:bg-[#4A3029] text-white font-extrabold px-6 py-2.5 rounded-xl text-xs shadow-sm transition">
                                            <i class="fa-solid fa-qrcode mr-1.5"></i> Scan Santri Berikutnya
                                        </button>
                                        <button type="button" onclick="cancelAttendanceModal()" class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold px-4 py-2.5 rounded-xl text-xs transition">
                                            <i class="fa-solid fa-xmark mr-1"></i> Batalkan Kehadiran
                                        </button>
                                    </div>
                                </div>

                            </div>

                            <!-- Card Footer: Quick Actions -->
                            <div class="bg-stone-50 px-6 py-4 border-t border-gray-200 flex flex-wrap justify-between items-center gap-3 text-xs">
                                <a id="res-link-detail" href="#" target="_blank" class="inline-flex items-center space-x-1.5 font-bold text-[#5C3E35] hover:underline">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i>
                                    <span>Buka Halaman Detail Profil Santri</span>
                                </a>
                                <button type="button" onclick="resetView()" class="text-gray-500 hover:text-red-600 font-bold transition flex items-center space-x-1">
                                    <i class="fa-solid fa-xmark"></i>
                                    <span>Tutup Hasil</span>
                                </button>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Tabel Laporan Rekapitulasi Presensi Live -->
                <div class="bg-white rounded-3xl p-6 md:p-8 border border-gray-200/90 shadow-sm space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-5">
                        <div>
                            <span class="text-xs font-bold text-[#A17A6F] uppercase tracking-wider block">
                                <i class="fa-solid fa-clipboard-check mr-1.5"></i> Pelaporan Live Kehadiran
                            </span>
                            <h2 class="text-lg font-black text-[#2F2522] mt-0.5">Tabel Rekapitulasi Kehadiran Peserta Ujian</h2>
                            <p class="text-xs text-gray-500">Daftar calon santri, waktu check-in, dan aksi cepat panitia</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <button type="button" onclick="loadAttendanceTable()" class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-gray-700 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-2xs">
                                <i class="fa-solid fa-rotate mr-1 text-[#5C3E35]"></i>
                                <span>Refresh Data</span>
                            </button>
                            <a id="btn-export-excel" href="export.php" target="_blank" class="px-4 py-2 bg-[#15803D] hover:bg-[#166534] text-white rounded-xl text-xs font-bold transition flex items-center space-x-2 shadow-sm">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Ekspor Laporan (Excel)</span>
                            </a>
                        </div>
                    </div>

                    <!-- Filter & Search Bar Tabel -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-stone-50/70 p-4 rounded-2xl border border-stone-200/60 items-center">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Filter Status Kehadiran</label>
                            <select id="filter-status-table" onchange="loadAttendanceTable()" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35]">
                                <option value="">-- Semua Peserta (Hadir & Belum) --</option>
                                <option value="Hadir">✅ Sudah Hadir (Check-in)</option>
                                <option value="Belum">⏳ Belum Hadir / Antri</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Cari Peserta (Nama / No. Reg / Asal Sekolah)</label>
                            <div class="relative">
                                <input type="text" id="search-table-input" oninput="debounceSearchTable()" placeholder="Ketik nama atau nomor registrasi santri..." class="w-full bg-white border border-gray-300 rounded-xl pl-9 pr-8 py-2 text-xs font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35]">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                <button type="button" onclick="clearSearchTable()" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 text-xs hidden" id="btn-clear-search">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table Container -->
                    <div class="overflow-x-auto rounded-2xl border border-gray-200/90">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-stone-100/80 text-gray-600 text-[11px] uppercase tracking-wider font-extrabold border-b border-gray-200">
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">No. Registrasi & Nama Santri</th>
                                    <th class="py-3.5 px-4">Jalur & Asal Sekolah</th>
                                    <th class="py-3.5 px-4">Biaya Pendaftaran</th>
                                    <th class="py-3.5 px-4">Status Kehadiran</th>
                                    <th class="py-3.5 px-4">Waktu Check-in</th>
                                    <th class="py-3.5 px-4 text-center">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-tbody" class="divide-y divide-gray-100 text-xs font-medium text-gray-700">
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-gray-400">
                                        <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block text-[#5C3E35]"></i>
                                        Memuat data kehadiran...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Container -->
                    <div id="table-pagination-container"></div>
                </div>

            </div>
        </main>
    </div>

    <!-- Script Logika Presensi Live & Web Audio Scanner -->
    <script>
    let html5QrCode = null;
    let currentScannedReg = '';
    let currentScannedStudent = null;
    let isScanning = false;
    let selectedGelombangId = '<?= $selected_gelombang ?>';
    let isSoundMuted = localStorage.getItem('babuljannah_scan_mute') === 'true';

    // Update icon sound saat inisialisasi
    function updateSoundIcon() {
        const btn = document.getElementById('btn-toggle-sound');
        const icon = document.getElementById('icon-sound');
        if (!btn || !icon) return;
        if (isSoundMuted) {
            icon.className = 'fa-solid fa-volume-xmark text-gray-400';
            btn.title = 'Suara Beep: Senyap (Bisu)';
        } else {
            icon.className = 'fa-solid fa-volume-high text-[#5C3E35]';
            btn.title = 'Suara Beep: Aktif';
        }
    }

    function toggleSound() {
        isSoundMuted = !isSoundMuted;
        localStorage.setItem('babuljannah_scan_mute', isSoundMuted ? 'true' : 'false');
        updateSoundIcon();
        if (!isSoundMuted) {
            playBeep(true);
        }
    }

    // Sound Beep Generator via Web Audio API (Dual Pitch Chime)
    function playBeep(success = true) {
        if (isSoundMuted) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            if (success) {
                // Cheerful 2-tone chime
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1174.66, ctx.currentTime + 0.12);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.22);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.24);
            } else {
                // Low alert tone
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(280, ctx.currentTime);
                gain.gain.setValueAtTime(0.25, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.26);
            }
        } catch (e) {
            console.log("Audio not supported:", e);
        }
    }

    // Live Clock
    function updateClock() {
        const el = document.getElementById('live-clock');
        if (!el) return;
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        el.textContent = `${h}:${m}:${s} WIB`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Fullscreen Toggle
    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    // Ganti filter gelombang
    function changeGelombang(val) {
        selectedGelombangId = val;
        loadStats();
        resetView();
        loadAttendanceTable(1);
    }

    // Load Live Attendance Stats
    function loadStats() {
        fetch(`scan_action.php?action=stats&gelombang_id=${selectedGelombangId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const tot = data.stats.total || 0;
                    const hdr = data.stats.hadir || 0;
                    const blm = data.stats.belum || 0;

                    document.getElementById('stat-total').textContent = tot;
                    document.getElementById('stat-hadir').textContent = hdr;
                    document.getElementById('stat-belum').textContent = blm;

                    const percent = tot > 0 ? Math.round((hdr / tot) * 100) : 0;
                    const pctEl = document.getElementById('stat-percent');
                    if (pctEl) pctEl.textContent = `${percent}%`;
                }
            })
            .catch(err => console.error(err));
    }

    // Table search debounce
    let searchTimeout = null;
    function debounceSearchTable() {
        const val = document.getElementById('search-table-input').value;
        const btnClear = document.getElementById('btn-clear-search');
        if (btnClear) {
            btnClear.classList.toggle('hidden', val.length === 0);
        }
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadAttendanceTable(1);
        }, 300);
    }

    function clearSearchTable() {
        const input = document.getElementById('search-table-input');
        if (input) {
            input.value = '';
            debounceSearchTable();
        }
    }

    function updateExportLink() {
        const filterStatus = document.getElementById('filter-status-table')?.value || '';
        const exportBtn = document.getElementById('btn-export-excel');
        if (exportBtn) {
            let url = `export.php?filter_gelombang=${selectedGelombangId}`;
            if (filterStatus === 'Hadir') url += '&filter_hadir=Hadir';
            else if (filterStatus === 'Belum') url += '&filter_hadir=Belum';
            exportBtn.href = url;
        }
    }

    // Load Live Attendance Table
    function loadAttendanceTable(page = 1) {
        updateExportLink();
        const tbody = document.getElementById('attendance-tbody');
        const paginationContainer = document.getElementById('table-pagination-container');
        if (!tbody) return;

        const filterStatus = document.getElementById('filter-status-table')?.value || '';
        const search = document.getElementById('search-table-input')?.value || '';

        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-2 text-[#5C3E35]"></i> Memuat data kehadiran...</td></tr>`;

        fetch(`scan_action.php?action=list_table&gelombang_id=${selectedGelombangId}&filter_status=${encodeURIComponent(filterStatus)}&search=${encodeURIComponent(search)}&page=${page}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.count === 0) {
                        tbody.innerHTML = `<tr><td colspan="7" class="py-12 text-center text-gray-400 font-medium"><i class="fa-solid fa-user-clock text-3xl mb-2 block text-gray-300"></i>Tidak ada peserta ujian yang cocok dengan filter saat ini.</td></tr>`;
                        if (paginationContainer) paginationContainer.innerHTML = '';
                        return;
                    }
                    let html = '';
                    const offset = (data.current_page - 1) * data.limit;
                    data.data.forEach((item, idx) => {
                        const badgePay = item.status_pembayaran === 'Lunas' 
                            ? `<span class="bg-emerald-50 text-emerald-800 text-[10px] px-2.5 py-1 rounded-lg font-bold border border-emerald-200 inline-flex items-center"><i class="fa-solid fa-check mr-1"></i>Lunas</span>` 
                            : `<span class="bg-amber-50 text-amber-800 text-[10px] px-2.5 py-1 rounded-lg font-bold border border-amber-200 inline-flex items-center"><i class="fa-solid fa-clock mr-1"></i>Belum Lunas</span>`;
                        
                        const isHadir = item.status_kehadiran === 'Hadir';
                        const badgeHadir = isHadir 
                            ? `<span class="bg-emerald-600 text-white text-[10px] px-3 py-1 rounded-full font-black shadow-xs inline-flex items-center"><i class="fa-solid fa-user-check mr-1.5"></i>HADIR</span>`
                            : `<span class="bg-stone-100 text-stone-600 text-[10px] px-3 py-1 rounded-full font-bold inline-flex items-center border border-stone-200"><i class="fa-regular fa-clock mr-1.5 text-stone-400"></i>Belum Hadir</span>`;

                        const actionBtn = isHadir
                            ? `<button onclick="toggleAttendanceTable('${item.no_registrasi}', false)" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-[11px] font-bold transition border border-red-200 inline-flex items-center shadow-2xs" title="Batalkan presensi"><i class="fa-solid fa-xmark mr-1"></i>Batal Hadir</button>`
                            : `<button onclick="toggleAttendanceTable('${item.no_registrasi}', true)" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-xl text-[11px] font-bold transition border border-emerald-200 inline-flex items-center shadow-2xs" title="Tandai hadir ujian"><i class="fa-solid fa-check mr-1"></i>Tandai Hadir</button>`;

                        html += `
                        <tr class="hover:bg-stone-50/80 transition">
                            <td class="py-3 px-4 text-center font-bold text-gray-400">${offset + idx + 1}</td>
                            <td class="py-3 px-4">
                                <a href="detail_pendaftar.php?id=${item.id}" target="_blank" class="font-black text-[#5C3E35] hover:underline block text-xs">${item.nama_santri}</a>
                                <span class="font-mono text-[10px] text-gray-500 bg-stone-100 border border-stone-200 px-1.5 py-0.5 rounded mt-0.5 inline-block">${item.no_registrasi}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-gray-800 block text-xs">${item.jalur_pendaftaran || '-'}</span>
                                <span class="text-[11px] text-gray-500 block truncate max-w-[200px]">${item.sekolah_asal || '-'}</span>
                            </td>
                            <td class="py-3 px-4">${badgePay}</td>
                            <td class="py-3 px-4">${badgeHadir}</td>
                            <td class="py-3 px-4 font-mono text-[11px] text-gray-600">${item.waktu_formatted}</td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-1.5">
                                    ${actionBtn}
                                    <a href="detail_pendaftar.php?id=${item.id}" target="_blank" class="p-1.5 bg-stone-100 hover:bg-[#5C3E35] text-stone-600 hover:text-white rounded-lg text-xs transition" title="Lihat Profil Santri">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;
                    
                    if (paginationContainer) {
                        renderPagination(paginationContainer, data.current_page, data.total_pages, data.total_data, offset, data.limit);
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500 font-bold">Gagal memuat data.</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">Terjadi kesalahan koneksi server.</td></tr>`;
            });
    }

    function renderPagination(container, currentPage, totalPages, totalData, offset, limit) {
        let endData = offset + limit;
        if (endData > totalData) endData = totalData;
        let startData = totalData === 0 ? 0 : offset + 1;
        
        let html = `
        <div class="border-t border-gray-100 bg-stone-50/50 p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-b-2xl">
            <div class="text-xs text-gray-500 font-medium">
                Menampilkan <span class="font-bold text-gray-700">${startData}</span> sampai <span class="font-bold text-gray-700">${endData}</span> dari total <span class="font-black text-[#5C3E35] bg-[#5C3E35]/10 px-2 py-0.5 rounded-md">${totalData}</span> peserta ujian
            </div>
        `;
        
        if (totalPages > 1) {
            html += `<nav class="flex items-center space-x-1">`;
            
            // Prev Button
            if (currentPage > 1) {
                html += `<button onclick="loadAttendanceTable(${currentPage - 1})" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-[#5C3E35] hover:text-white transition shadow-2xs"><i class="fa-solid fa-chevron-left text-[10px]"></i></button>`;
            } else {
                html += `<span class="w-8 h-8 flex items-center justify-center rounded-xl bg-stone-100 border border-stone-200 text-gray-300 cursor-not-allowed"><i class="fa-solid fa-chevron-left text-[10px]"></i></span>`;
            }
            
            html += `<div class="flex space-x-1 px-1">`;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += `<button onclick="loadAttendanceTable(1)" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-600 hover:bg-[#5C3E35] hover:text-white transition shadow-2xs">1</button>`;
                if (startPage > 2) html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += `<span class="w-8 h-8 flex items-center justify-center rounded-xl bg-[#5C3E35] text-xs font-black text-white shadow-xs">${i}</span>`;
                } else {
                    html += `<button onclick="loadAttendanceTable(${i})" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-600 hover:bg-[#5C3E35] hover:text-white transition shadow-2xs">${i}</button>`;
                }
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>`;
                html += `<button onclick="loadAttendanceTable(${totalPages})" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-600 hover:bg-[#5C3E35] hover:text-white transition shadow-2xs">${totalPages}</button>`;
            }
            html += `</div>`;
            
            // Next Button
            if (currentPage < totalPages) {
                html += `<button onclick="loadAttendanceTable(${currentPage + 1})" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-[#5C3E35] hover:text-white transition shadow-2xs"><i class="fa-solid fa-chevron-right text-[10px]"></i></button>`;
            } else {
                html += `<span class="w-8 h-8 flex items-center justify-center rounded-xl bg-stone-100 border border-stone-200 text-gray-300 cursor-not-allowed"><i class="fa-solid fa-chevron-right text-[10px]"></i></span>`;
            }
            
            html += `</nav>`;
        }
        
        html += `</div>`;
        container.innerHTML = html;
    }

    // Toggle Attendance from Table
    function toggleAttendanceTable(noReg, setHadir) {
        if (!confirm(`Apakah Anda yakin ingin ${setHadir ? 'menandai HADIR' : 'MEMBATALKAN kehadiran'} santri dengan No. Reg ${noReg}?`)) return;

        const formData = new FormData();
        formData.append('action', setHadir ? 'attend' : 'unattend');
        formData.append('no_reg', noReg);

        fetch('scan_action.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                playBeep(setHadir);
                loadStats();
                loadAttendanceTable();
                if (currentScannedReg === noReg) {
                    processScan(noReg);
                }
            } else {
                playBeep(false);
                alert("❌ Gagal: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Terjadi kesalahan sistem saat memperbarui kehadiran.");
        });
    }

    // Start Scanner Camera
    function startScanner(selectedCameraId = null) {
        const placeholder = document.getElementById('cam-placeholder');
        const startBtn = document.getElementById('btn-start-cam');
        const stopBtn = document.getElementById('btn-stop-cam');
        const statusEl = document.getElementById('cam-status');
        const laser = document.getElementById('laser-effect');
        const brackets = document.getElementById('brackets-effect');

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        // Ambil daftar kamera jika belum
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                const select = document.getElementById('cam-select');
                const wrapper = document.getElementById('cam-selection-wrapper');
                if (select && wrapper && devices.length > 1) {
                    wrapper.classList.remove('hidden');
                    select.innerHTML = '';
                    devices.forEach((dev, i) => {
                        const opt = document.createElement('option');
                        opt.value = dev.id;
                        opt.text = dev.label || `Kamera ${i + 1}`;
                        select.appendChild(opt);
                    });
                }
            }
        }).catch(() => {});

        placeholder.style.display = 'none';
        startBtn.classList.add('hidden');
        stopBtn.classList.remove('hidden');
        laser.classList.remove('hidden');
        brackets.classList.remove('hidden');

        statusEl.textContent = '⚡ Memindai...';
        statusEl.className = 'bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full text-[10px] font-black animate-pulse';
        isScanning = true;

        const config = {
            fps: 15,
            qrbox: { width: 240, height: 240 }
        };

        const cameraIdOrConfig = selectedCameraId ? { deviceId: { exact: selectedCameraId } } : { facingMode: "environment" };

        html5QrCode.start(
            cameraIdOrConfig,
            config,
            (decodedText) => {
                if (decodedText && decodedText !== currentScannedReg) {
                    currentScannedReg = decodedText.trim();
                    playBeep(true);
                    processScan(currentScannedReg);
                }
            },
            () => {}
        ).catch((err) => {
            alert("Gagal mengaktifkan kamera: " + err + ". Pastikan browser telah diberikan izin mengakses kamera.");
            stopScanner();
        });
    }

    function changeCameraDevice(devId) {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().then(() => {
                startScanner(devId);
            });
        }
    }

    // Stop Scanner Camera
    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('cam-placeholder').style.display = 'block';
                document.getElementById('btn-start-cam').classList.remove('hidden');
                document.getElementById('btn-stop-cam').classList.add('hidden');
                document.getElementById('laser-effect').classList.add('hidden');
                document.getElementById('brackets-effect').classList.add('hidden');

                const statusEl = document.getElementById('cam-status');
                statusEl.textContent = 'Standby';
                statusEl.className = 'bg-stone-100 text-stone-600 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider';
                isScanning = false;
            }).catch(err => console.error(err));
        }
    }

    // Scan dari File Gambar (Upload QR)
    function scanFromImage(inputEl) {
        if (!inputEl.files || inputEl.files.length === 0) return;
        const file = inputEl.files[0];

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        html5QrCode.scanFile(file, true)
            .then(decodedText => {
                currentScannedReg = decodedText.trim();
                playBeep(true);
                processScan(currentScannedReg);
                inputEl.value = '';
            })
            .catch(err => {
                playBeep(false);
                alert("QR Code tidak terdeteksi pada gambar yang diunggah. Pastikan gambar jelas dan tidak blur.");
                inputEl.value = '';
            });
    }

    // Manual input search
    function manualSearch(e) {
        e.preventDefault();
        const input = document.getElementById('manual-reg-input').value.trim();
        if (!input) return;
        currentScannedReg = input;
        playBeep(true);
        processScan(input);
    }

    // Process scan result
    function processScan(noReg) {
        fetch(`scan_action.php?action=check&no_reg=${encodeURIComponent(noReg)}&gelombang_id=${selectedGelombangId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'error') {
                    playBeep(false);
                    alert("❌ " + data.message);
                    currentScannedReg = '';
                    return;
                }
                if (data.status === 'warning') {
                    alert("⚠️ " + data.message);
                }

                currentScannedStudent = data.data;
                showResultCard(data.data);
            })
            .catch(err => {
                console.error(err);
                alert("Terjadi kesalahan koneksi server saat memeriksa santri.");
                currentScannedReg = '';
            });
    }

    // Display student identity & attendance state
    function showResultCard(student) {
        document.getElementById('result-placeholder').classList.add('hidden');
        const card = document.getElementById('result-card');
        card.classList.remove('hidden');

        document.getElementById('res-nama').textContent = student.nama_santri;
        document.getElementById('res-noreg').textContent = student.no_registrasi;
        document.getElementById('res-gelombang').textContent = `${student.nama_gelombang} (T.A ${student.tahun_ajaran})`;
        document.getElementById('res-jalur').textContent = student.jalur_pendaftaran;
        document.getElementById('res-sekolah').textContent = student.nama_sekolah_asal || 'Tidak dicantumkan';

        // Avatar initial & Gender
        const avatar = document.getElementById('res-avatar');
        const jkBadge = document.getElementById('res-jk-badge');
        const initial = student.nama_santri ? student.nama_santri.charAt(0).toUpperCase() : 'S';
        avatar.textContent = initial;

        if (student.jk === 'Perempuan') {
            avatar.className = 'w-14 h-14 rounded-2xl bg-pink-500/20 text-pink-200 flex items-center justify-center font-black text-2xl border border-pink-400/30 flex-shrink-0';
            jkBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-pink-500/30 text-pink-100 border border-pink-400/30';
            jkBadge.textContent = 'Santri Putri';
        } else {
            avatar.className = 'w-14 h-14 rounded-2xl bg-blue-500/20 text-blue-200 flex items-center justify-center font-black text-2xl border border-blue-400/30 flex-shrink-0';
            jkBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/30 text-blue-100 border border-blue-400/30';
            jkBadge.textContent = 'Santri Putra';
        }

        // WhatsApp Ortu
        const waWrap = document.getElementById('res-ortu-wrapper');
        const waLink = document.getElementById('res-ortu-wa');
        const waText = document.getElementById('res-ortu-text');
        const phone = student.wa_ayah || student.wa_ibu || student.wa_wali || '';
        if (phone) {
            let cleanPhone = phone.replace(/[^0-9]/g, '');
            if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
            else if (!cleanPhone.startsWith('62')) cleanPhone = '62' + cleanPhone;

            waLink.href = `https://wa.me/${cleanPhone}`;
            waText.textContent = `${student.nama_ayah || 'Wali Santri'} (${phone})`;
            waWrap.classList.remove('hidden');
        } else {
            waWrap.classList.add('hidden');
        }

        // Payment status & Warning
        const badgePay = document.getElementById('res-badge-pay');
        const boxWarningPay = document.getElementById('box-warning-bayar');

        if (student.status_pembayaran === 'Lunas') {
            badgePay.className = 'bg-emerald-500 text-white px-3.5 py-1.5 rounded-xl font-black text-xs shadow-md flex items-center space-x-1.5 border border-white/20';
            badgePay.innerHTML = '<i class="fa-solid fa-circle-check"></i><span>Lunas</span>';
            boxWarningPay.classList.add('hidden');
        } else {
            badgePay.className = 'bg-amber-500 text-white px-3.5 py-1.5 rounded-xl font-black text-xs shadow-md flex items-center space-x-1.5 border border-white/20 animate-pulse';
            badgePay.innerHTML = '<i class="fa-solid fa-clock"></i><span>Belum Lunas</span>';
            boxWarningPay.classList.remove('hidden');
        }

        // Link ke detail pendaftar
        const linkDetail = document.getElementById('res-link-detail');
        if (linkDetail) {
            linkDetail.href = `detail_pendaftar.php?id=${student.id}`;
        }

        // Attendance state
        const boxBelum = document.getElementById('box-belum-hadir');
        const boxSudah = document.getElementById('box-sudah-hadir');

        if (student.status_kehadiran === 'Hadir') {
            boxBelum.classList.add('hidden');
            boxSudah.classList.remove('hidden');
            document.getElementById('res-waktu-hadir').textContent = 'Dicatat pada: ' + (student.waktu_kehadiran || 'Hari ini');
        } else {
            boxSudah.classList.add('hidden');
            boxBelum.classList.remove('hidden');
        }

        // Scroll halus di mobile
        if (window.innerWidth < 1024) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Confirm & Record Attendance via AJAX
    function confirmAttendance() {
        if (!currentScannedReg) return;

        const formData = new FormData();
        formData.append('action', 'attend');
        formData.append('no_reg', currentScannedReg);

        fetch('scan_action.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                playBeep(true);
                document.getElementById('box-belum-hadir').classList.add('hidden');
                const boxSudah = document.getElementById('box-sudah-hadir');
                boxSudah.classList.remove('hidden');
                document.getElementById('res-waktu-hadir').textContent = 'Dicatat pada: ' + data.waktu;
                
                loadStats();
                loadAttendanceTable();
            } else {
                playBeep(false);
                alert("❌ Gagal: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Gagal memproses kehadiran.");
        });
    }

    // Batalkan kehadiran dari card hasil
    function cancelAttendanceModal() {
        if (!currentScannedReg) return;
        toggleAttendanceTable(currentScannedReg, false);
    }

    // Reset View for next scan
    function resetView() {
        currentScannedReg = '';
        currentScannedStudent = null;
        document.getElementById('manual-reg-input').value = '';
        document.getElementById('result-card').classList.add('hidden');
        document.getElementById('result-placeholder').classList.remove('hidden');
    }

    // Inisialisasi awal
    window.addEventListener('DOMContentLoaded', () => {
        updateSoundIcon();
        loadStats();
        loadAttendanceTable(1);
    });
    </script>
</body>
</html>
