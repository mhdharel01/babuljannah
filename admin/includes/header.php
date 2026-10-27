<?php
// admin/includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Admin PSB';
$page_subtitle = 'Panel Manajemen Sistem Penerimaan Santri Baru';

if ($current_page == 'dashboard.php') {
    $page_title = 'Dashboard Utama';
    $page_subtitle = 'Ringkasan statistik, demografi santri, dan alur pendaftaran';
} elseif ($current_page == 'pendaftar.php' || $current_page == 'edit_pendaftar.php') {
    $page_title = 'Data Pendaftar';
    $page_subtitle = 'Kelola validasi berkas, pembayaran, dan kelulusan calon santri';
} elseif ($current_page == 'tambah_pendaftar.php') {
    $page_title = 'Input Santri Baru';
    $page_subtitle = 'Formulir pendaftaran manual santri oleh panitia';
} elseif ($current_page == 'scan.php') {
    $page_title = 'Scan QR Kehadiran';
    $page_subtitle = 'Presensi kehadiran santri saat pelaksanaan ujian seleksi';
} elseif ($current_page == 'gelombang.php') {
    $page_title = 'Kelola Gelombang';
    $page_subtitle = 'Atur tanggal buka, tutup, kuota, dan jadwal seleksi';
} elseif ($current_page == 'berita.php') {
    $page_title = 'Kelola Berita';
    $page_subtitle = 'Publikasi artikel, kabar pesantren, dan informasi resmi';
} elseif ($current_page == 'fasilitas.php') {
    $page_title = 'Kelola Fasilitas';
    $page_subtitle = 'Dokumentasi sarana prasarana kampus pondok';
} elseif ($current_page == 'ekskul.php') {
    $page_title = 'Kelola Ekstrakurikuler';
    $page_subtitle = 'Daftar kegiatan pengembangan minat dan bakat santri';
} elseif ($current_page == 'prestasi.php') {
    $page_title = 'Kelola Prestasi';
    $page_subtitle = 'Pencapaian dan rekam jejak juara santri';
} elseif ($current_page == 'master_biaya.php') {
    $page_title = 'Master Biaya';
    $page_subtitle = 'Konfigurasi biaya seleksi dan rincian daftar ulang';
} elseif ($current_page == 'pengaturan_email.php') {
    $page_title = 'Pengaturan Email';
    $page_subtitle = 'Konfigurasi SMTP dan template email notifikasi sistem';
} elseif ($current_page == 'laporan_keuangan.php') {
    $page_title = 'Laporan Keuangan PSB';
    $page_subtitle = 'Rekapitulasi penerimaan kas biaya pendaftaran santri';
} elseif ($current_page == 'users.php') {
    $page_title = 'Kelola Pengguna';
    $page_subtitle = 'Manajemen akun panitia, hak akses peran, dan keamanan';
}

if (!function_exists('get_menu_class_mobile')) {
    function get_menu_class_mobile($page_name, $current_page) {
        $is_active = ($current_page === $page_name) || ($current_page === 'edit_pendaftar.php' && $page_name === 'pendaftar.php');
        if ($is_active) {
            return 'bg-brand-secondary text-white font-bold border-l-4 border-white';
        }
        return 'text-white/80 hover:bg-white/10 hover:text-white transition';
    }
}

$admin_role = $_SESSION['admin_role'] ?? 'panitia';
?>
<!-- Modern Sticky Navbar Header -->
<header class="h-20 bg-white border-b border-gray-200/90 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-30 flex-shrink-0 sticky top-0 shadow-2xs transition-all">
    
    <!-- Left: Mobile Trigger & Page Title -->
    <div class="flex items-center space-x-3 sm:space-x-4 flex-1 min-w-0 mr-3 sm:mr-6">
        <!-- Hamburger Button (Mobile Only) -->
        <button type="button" class="lg:hidden w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center transition-colors focus:outline-none flex-shrink-0" onclick="document.getElementById('mobile-sidebar').classList.remove('hidden')">
            <i class="fa-solid fa-bars-staggered text-lg"></i>
        </button>

        <?php if (!empty($header_custom_title)): ?>
            <div class="flex items-center min-w-0 flex-1">
                <?= $header_custom_title ?>
            </div>
        <?php else: ?>
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg lg:text-xl font-extrabold text-[#2F2522] tracking-tight truncate flex items-center">
                    <?= htmlspecialchars($page_title) ?>
                </h1>
                <p class="text-[11px] text-gray-500 font-medium hidden sm:block truncate">
                    <?= htmlspecialchars($page_subtitle) ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right: Status Badge, Quick Links, & Profile -->
    <div class="flex items-center space-x-2 sm:space-x-3 flex-shrink-0">
        <!-- Live System Status Badge (Hanya tampil di layar 2XL agar tidak menghimpit judul di layar laptop) -->
        <div class="hidden 2xl:flex items-center space-x-2 bg-emerald-50 text-emerald-800 border border-emerald-200/80 px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>PSB T.A 2026/2027 Online</span>
        </div>

        <!-- Date Pill (Tampil di layar XL+) -->
        <div class="hidden xl:flex items-center space-x-2 bg-gray-50 text-gray-600 border border-gray-200/70 px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap">
            <i class="fa-regular fa-calendar text-brand-primary"></i>
            <span><?= function_exists('format_tanggal') ? format_tanggal(date('Y-m-d')) : date('d M Y') ?></span>
        </div>

        <!-- Shortcut: Buka Portal Web Publik -->
        <a href="../index.php" target="_blank" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs whitespace-nowrap" title="Lihat Tampilan Website Publik">
            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
            <span class="hidden md:inline">Lihat Web</span>
        </a>

        <!-- User Profile Pill -->
        <div class="flex items-center space-x-2.5 pl-2 sm:pl-3 border-l border-gray-200">
            <div class="w-9 h-9 rounded-xl bg-brand-primary flex items-center justify-center text-white font-black text-xs shadow-xs border border-brand-secondary/40 flex-shrink-0">
                <?= htmlspecialchars(strtoupper(substr($_SESSION['admin_nama'] ?? 'AD', 0, 2))) ?>
            </div>
            <div class="hidden lg:block text-left max-w-[130px]">
                <span class="block text-xs font-bold text-gray-800 leading-tight truncate">
                    <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?>
                </span>
                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-wider truncate">
                    <?= htmlspecialchars($admin_role) ?>
                </span>
            </div>
        </div>

    </div>
</header>

<!-- Mobile Navigation Drawer Overlay -->
<div id="mobile-sidebar" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden lg:hidden transition-opacity" onclick="this.classList.add('hidden')">
    <div class="w-72 bg-brand-deep text-white h-full flex flex-col border-r border-[#3A241E] shadow-2xl relative" onclick="event.stopPropagation()">
        
        <!-- Mobile Drawer Header -->
        <div class="h-20 flex items-center justify-between px-6 border-b border-[#3A241E] bg-brand-dark/70">
            <a href="dashboard.php" class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-brand-secondary text-white flex items-center justify-center text-base shadow-sm">
                    <i class="fa-solid fa-mosque"></i>
                </div>
                <div>
                    <span class="block text-sm font-extrabold text-white tracking-wide">PANITIA PSB</span>
                    <span class="block text-[10px] text-[#D4C3B8] font-bold uppercase">Babul Jannah</span>
                </div>
            </a>
            <button type="button" class="text-[#D4C3B8] hover:text-white text-lg p-2 transition" onclick="document.getElementById('mobile-sidebar').classList.add('hidden')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Mobile Drawer Navigation -->
        <nav class="flex-grow py-5 px-3 space-y-5 overflow-y-auto custom-scrollbar">
            
            <div class="space-y-1">
                <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-wider block mb-1">Utama</span>
                <a href="dashboard.php" class="<?= get_menu_class_mobile('dashboard.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-gauge-high w-5 text-center text-sm"></i>
                    <span>Dashboard</span>
                </a>
                <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])): ?>
                <a href="pendaftar.php" class="<?= get_menu_class_mobile('pendaftar.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-users w-5 text-center text-sm"></i>
                    <span>Data Pendaftar</span>
                </a>
                <?php endif; ?>
                <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                <a href="tambah_pendaftar.php" class="<?= get_menu_class_mobile('tambah_pendaftar.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-user-plus w-5 text-center text-sm"></i>
                    <span>Input Santri Baru</span>
                </a>
                <a href="scan.php" class="<?= get_menu_class_mobile('scan.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-qrcode w-5 text-center text-sm"></i>
                    <span>Scan QR Hadir</span>
                </a>
                <?php endif; ?>
            </div>

            <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])): ?>
            <div class="space-y-1">
                <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-wider block mb-1">Admisi &amp; Keuangan</span>
                <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                <a href="gelombang.php" class="<?= get_menu_class_mobile('gelombang.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-calendar-days w-5 text-center text-sm"></i>
                    <span>Gelombang Pendaftaran</span>
                </a>
                <?php endif; ?>
                <?php if (in_array($admin_role, ['superadmin', 'bendahara'])): ?>
                <a href="master_biaya.php" class="<?= get_menu_class_mobile('master_biaya.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm"></i>
                    <span>Master Biaya</span>
                </a>
                <?php endif; ?>
                <?php if (in_array($admin_role, ['superadmin', 'bendahara', 'yayasan'])): ?>
                <a href="laporan_keuangan.php" class="<?= get_menu_class_mobile('laporan_keuangan.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-sm"></i>
                    <span>Laporan Keuangan</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['superadmin', 'humas'])): ?>
            <div class="space-y-1">
                <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-wider block mb-1">Konten Portal</span>
                <a href="berita.php" class="<?= get_menu_class_mobile('berita.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-newspaper w-5 text-center text-sm"></i>
                    <span>Kelola Berita</span>
                </a>
                <a href="fasilitas.php" class="<?= get_menu_class_mobile('fasilitas.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-building-columns w-5 text-center text-sm"></i>
                    <span>Sarana Fasilitas</span>
                </a>
                <a href="ekskul.php" class="<?= get_menu_class_mobile('ekskul.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-volleyball w-5 text-center text-sm"></i>
                    <span>Ekstrakurikuler</span>
                </a>
                <a href="prestasi.php" class="<?= get_menu_class_mobile('prestasi.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-trophy w-5 text-center text-sm"></i>
                    <span>Prestasi Santri</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if ($admin_role === 'superadmin'): ?>
            <div class="space-y-1">
                <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-wider block mb-1">Sistem</span>
                <a href="pengaturan_email.php" class="<?= get_menu_class_mobile('pengaturan_email.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-envelope w-5 text-center text-sm"></i>
                    <span>Pengaturan Email</span>
                </a>
                <a href="users.php" class="<?= get_menu_class_mobile('users.php', $current_page) ?> px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center space-x-3">
                    <i class="fa-solid fa-users-gear w-5 text-center text-sm"></i>
                    <span>Manajemen Pengguna</span>
                </a>
            </div>
            <?php endif; ?>

        </nav>

        <!-- Mobile Drawer Footer -->
        <div class="p-4 border-t border-[#3A241E] bg-[#2E1E19] flex items-center justify-between flex-shrink-0">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-brand-secondary text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                    <?= htmlspecialchars(strtoupper(substr($_SESSION['admin_nama'] ?? 'AD', 0, 2))) ?>
                </div>
                <div class="min-w-0">
                    <span class="block text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?></span>
                    <span class="block text-[10px] text-[#D4C3B8] font-semibold uppercase"><?= htmlspecialchars($admin_role) ?></span>
                </div>
            </div>
            <a href="logout.php" class="px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600 text-red-300 hover:text-white text-xs font-bold transition">
                Keluar
            </a>
        </div>

    </div>
</div>
