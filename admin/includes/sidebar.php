<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);

if (!function_exists('get_sidebar_active')) {
    function get_sidebar_active($page_name, $current_page) {
        $is_active = ($current_page === $page_name) || ($current_page === 'edit_pendaftar.php' && $page_name === 'pendaftar.php');
        if ($is_active) {
            return 'bg-brand-secondary text-white font-bold border-l-4 border-white shadow-md';
        }
        return 'text-white/85 hover:text-white hover:bg-white/10 font-medium transition-all duration-150';
    }
}

if (!function_exists('get_sidebar_icon_active')) {
    function get_sidebar_icon_active($page_name, $current_page) {
        $is_active = ($current_page === $page_name) || ($current_page === 'edit_pendaftar.php' && $page_name === 'pendaftar.php');
        return $is_active ? 'text-white' : 'text-[#D4C3B8] group-hover:text-white transition-colors';
    }
}

$admin_role = $_SESSION['admin_role'] ?? 'panitia';
?>
<!-- Modern Sleek Desktop Sidebar (Brand Color: Deep Warm Primary #4A3029) -->
<aside class="w-64 bg-brand-deep text-white flex-shrink-0 flex-col hidden lg:flex border-r border-[#3A241E] h-full select-none relative z-20 shadow-2xl">
    
    <!-- Brand / Header Section -->
    <div class="h-20 flex items-center px-6 border-b border-[#3A241E] flex-shrink-0 bg-brand-dark/70">
        <a href="dashboard.php" class="flex items-center space-x-3.5 group">
            <div class="w-10 h-10 rounded-2xl bg-brand-secondary text-white border border-[#B8976C]/40 flex items-center justify-center text-lg shadow-md group-hover:scale-105 transition-all duration-200">
                <i class="fa-solid fa-mosque"></i>
            </div>
            <div class="space-y-0.5">
                <span class="block text-sm font-extrabold text-white tracking-wider">
                    BABUL JANNAH
                </span>
                <span class="inline-flex items-center text-[10px] font-black text-white/95 tracking-widest uppercase bg-brand-secondary/40 px-2 py-0.5 rounded-md border border-white/20">
                    PANEL PSB
                </span>
            </div>
        </a>
    </div>

    <!-- Navigation Scrollable Area -->
    <nav class="flex-grow py-5 px-3.5 space-y-6 overflow-y-auto custom-scrollbar">
        
        <!-- SECTION 1: UTAMA -->
        <div class="space-y-1">
            <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-widest block mb-2">Utama</span>
            
            <a href="dashboard.php" class="<?= get_sidebar_active('dashboard.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-gauge-high w-5 text-center text-sm <?= get_sidebar_icon_active('dashboard.php', $current_page) ?>"></i>
                <span>Dashboard</span>
            </a>

            <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])): ?>
            <a href="pendaftar.php" class="<?= get_sidebar_active('pendaftar.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-users w-5 text-center text-sm <?= get_sidebar_icon_active('pendaftar.php', $current_page) ?>"></i>
                <span>Data Pendaftar</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
            <a href="tambah_pendaftar.php" class="<?= get_sidebar_active('tambah_pendaftar.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-user-plus w-5 text-center text-sm <?= get_sidebar_icon_active('tambah_pendaftar.php', $current_page) ?>"></i>
                <span>Input Santri Baru</span>
            </a>
            
            <a href="scan.php" class="<?= get_sidebar_active('scan.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-qrcode w-5 text-center text-sm <?= get_sidebar_icon_active('scan.php', $current_page) ?>"></i>
                <span>Scan QR Hadir</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: ADMISI & KEUANGAN -->
        <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])): ?>
        <div class="space-y-1">
            <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-widest block mb-2">Admisi &amp; Keuangan</span>

            <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
            <a href="gelombang.php" class="<?= get_sidebar_active('gelombang.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-calendar-days w-5 text-center text-sm <?= get_sidebar_icon_active('gelombang.php', $current_page) ?>"></i>
                <span>Gelombang Pendaftaran</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['superadmin', 'bendahara'])): ?>
            <a href="master_biaya.php" class="<?= get_sidebar_active('master_biaya.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-money-bill-wave w-5 text-center text-sm <?= get_sidebar_icon_active('master_biaya.php', $current_page) ?>"></i>
                <span>Master Biaya</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($admin_role, ['superadmin', 'bendahara', 'yayasan'])): ?>
            <a href="laporan_keuangan.php" class="<?= get_sidebar_active('laporan_keuangan.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-sm <?= get_sidebar_icon_active('laporan_keuangan.php', $current_page) ?>"></i>
                <span>Laporan Keuangan</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- SECTION 3: KONTEN PORTAL -->
        <?php if (in_array($admin_role, ['superadmin', 'humas'])): ?>
        <div class="space-y-1">
            <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-widest block mb-2">Konten Portal</span>

            <a href="berita.php" class="<?= get_sidebar_active('berita.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-newspaper w-5 text-center text-sm <?= get_sidebar_icon_active('berita.php', $current_page) ?>"></i>
                <span>Kelola Berita</span>
            </a>

            <a href="fasilitas.php" class="<?= get_sidebar_active('fasilitas.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-building-columns w-5 text-center text-sm <?= get_sidebar_icon_active('fasilitas.php', $current_page) ?>"></i>
                <span>Sarana Fasilitas</span>
            </a>

            <a href="ekskul.php" class="<?= get_sidebar_active('ekskul.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-volleyball w-5 text-center text-sm <?= get_sidebar_icon_active('ekskul.php', $current_page) ?>"></i>
                <span>Ekstrakurikuler</span>
            </a>

            <a href="prestasi.php" class="<?= get_sidebar_active('prestasi.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-trophy w-5 text-center text-sm <?= get_sidebar_icon_active('prestasi.php', $current_page) ?>"></i>
                <span>Prestasi Santri</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- SECTION 4: SISTEM -->
        <?php if ($admin_role === 'superadmin'): ?>
        <div class="space-y-1">
            <span class="px-3 text-[10px] font-extrabold text-[#D4C3B8] uppercase tracking-widest block mb-2">Sistem</span>

            <a href="pengaturan_email.php" class="<?= get_sidebar_active('pengaturan_email.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-envelope w-5 text-center text-sm <?= get_sidebar_icon_active('pengaturan_email.php', $current_page) ?>"></i>
                <span>Pengaturan Email</span>
            </a>

            <a href="users.php" class="<?= get_sidebar_active('users.php', $current_page) ?> group flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs transition-all">
                <i class="fa-solid fa-users-gear w-5 text-center text-sm <?= get_sidebar_icon_active('users.php', $current_page) ?>"></i>
                <span>Manajemen Pengguna</span>
            </a>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Footer Profile Section -->
    <div class="p-4 border-t border-[#3A241E] bg-[#2E1E19] flex items-center justify-between flex-shrink-0">
        <div class="flex items-center space-x-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-brand-secondary text-white flex items-center justify-center font-black text-xs shadow-sm flex-shrink-0">
                <?= htmlspecialchars(strtoupper(substr($_SESSION['admin_nama'] ?? 'AD', 0, 2))) ?>
            </div>
            <div class="min-w-0">
                <span class="block text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?></span>
                <span class="block text-[10px] text-[#D4C3B8] font-bold uppercase"><?= htmlspecialchars($admin_role) ?></span>
            </div>
        </div>
        <a href="logout.php" class="w-8 h-8 rounded-xl bg-red-600/20 hover:bg-red-600 text-red-300 hover:text-white flex items-center justify-center text-xs transition" title="Keluar">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>

</aside>
