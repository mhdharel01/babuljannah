<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (file_exists(__DIR__ . '/../config/storage.php')) {
    require_once __DIR__ . '/../config/storage.php';
}

if (!function_exists('get_storage_url')) {
    function get_storage_url($folder, $filename, $default = '', $is_admin = false) {
        if (empty($filename)) return $default;
        if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) return $filename;
        if (strpos($filename, 'assets/') === 0) return ($is_admin ? '../' : '') . $filename;
        $folder = trim(str_replace('\\', '/', $folder), '/');
        $filename = trim(str_replace('\\', '/', $filename), '/');
        $clean_path = !empty($folder) ? ($folder . '/' . $filename) : $filename;
        $encoded_path = implode('/', array_map('rawurlencode', explode('/', $clean_path)));
        $base = defined('SUPABASE_PROJECT_URL') ? SUPABASE_PROJECT_URL : 'https://bsnnbzkiblodvielwyyx.supabase.co';
        $bucket = defined('SUPABASE_BUCKET') ? SUPABASE_BUCKET : 'babuljannah';
        return rtrim($base, '/') . '/storage/v1/object/public/' . $bucket . '/' . $encoded_path;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $site_title = $page_title ?? 'Pondok Pesantren Babul Jannah - Dumai';
    $site_desc = $meta_desc ?? 'Pondok Pesantren Terpadu di Kota Dumai yang mengintegrasikan Kurikulum Pesantren dengan Pendidikan Formal.';
    
    // Tentukan Base URL secara otomatis agar relative path tidak rusak saat ada URL rewrite
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Ambil path folder tempat script ini berjalan (otomatis menyesuaikan apakah di localhost/folder atau production/root)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_path = str_replace('\\', '/', dirname($script_name));
    if ($base_path === '/' || $base_path === '\\' || $base_path === '.') {
        $base_path = '';
    }
    
    $base_url = ($is_https ? "https://" : "http://") . $host . $base_path . '/';
    
    $site_img = $meta_image ?? $base_url . 'assets/images/DESAIG LOGO BABUL JANNAH.webp';
    $current_url = ($is_https ? "https://" : "http://") . $host . ($_SERVER['REQUEST_URI'] ?? '');
    $current_page = basename($script_name, '.php');
    ?>
    <base href="<?= $base_url ?>">
    <title><?= $site_title ?></title>
    <meta name="description" content="<?= $site_desc ?>">
    <link rel="canonical" href="<?= $canonical_url ?? $current_url ?>">
    
    <!-- Schema.org EducationalOrganization -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "name": "Pondok Pesantren Babul Jannah",
      "url": "https://ponpesbabuljannah.com/",
      "logo": "https://ponpesbabuljannah.com/assets/images/DESAIG LOGO BABUL JANNAH.webp",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Jl. Sidorejo No.02 (Putra) / Jl. Sidorejo No.22 (Putri), Kel. Ratu Sima, Dumai Selatan",
        "addressLocality": "Dumai",
        "addressRegion": "Riau",
        "addressCountry": "ID"
      },
      "telephone": "+6281170789988",
      "sameAs": [
        "https://instagram.com/ponpes_babuljannah"
      ]
    }
    </script>
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="assets/images/DESAIG%20LOGO%20BABUL%20JANNAH.webp">
    <link rel="apple-touch-icon" href="assets/images/DESAIG%20LOGO%20BABUL%20JANNAH.webp">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonical_url ?? $current_url ?>">
    <meta property="og:title" content="<?= $site_title ?>">
    <meta property="og:description" content="<?= $site_desc ?>">
    <meta property="og:image" content="<?= $site_img ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= $canonical_url ?? $current_url ?>">
    <meta property="twitter:title" content="<?= $site_title ?>">
    <meta property="twitter:description" content="<?= $site_desc ?>">
    <meta property="twitter:image" content="<?= $site_img ?>">
    
    <!-- Google Fonts: Plus Jakarta Sans & Playfair Display (Lokal - All Weights) -->
    <link rel="stylesheet" href="assets/css/fonts.css">
    
    <!-- Font Awesome for Icons (Lokal) -->
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    
    <!-- AOS (Animate On Scroll) CSS (Lokal) -->
    <link rel="stylesheet" href="assets/vendor/aos/aos.css">
    
    <!-- Tailwind CSS Compiled -->
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    
    <!-- Custom Style (custom fonts, scrollbar & print rules) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-white text-dark min-h-screen flex flex-col overflow-x-hidden">

    <!-- Navigation Header -->
    <header class="w-full fixed top-0 z-50 bg-transparent no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="bg-white/95 backdrop-blur-md border border-gray-100 shadow-lg rounded-2xl px-6 h-20 flex justify-between items-center">
            <!-- Logo & Brand -->
            <a href="index.php" class="flex items-center space-x-3 group">
                <img src="assets/images/DESAIG LOGO BABUL JANNAH.webp" alt="Logo Babul Jannah" class="h-14 w-auto group-hover:scale-105 transition-transform duration-300">
                <div class="hidden sm:block">
                    <span class="block text-md font-extrabold text-primary tracking-wide leading-none">BABUL JANNAH</span>
                    <span class="block text-[9px] text-secondary font-bold tracking-wider uppercase mt-1">Pondok Pesantren</span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="hidden md:flex space-x-6 items-center">
                <a href="index.php" class="<?= $current_page === 'index' ? 'text-primary font-black border-b-2 border-primary pb-0.5' : 'text-dark hover:text-primary font-bold' ?> text-sm transition-colors duration-200">Beranda</a>
                
                <!-- Dropdown Menu -->
                <div class="relative group py-2">
                    <a href="profil.php" class="<?= in_array($current_page, ['profil', 'smp', 'sma', 'takhasus']) ? 'text-primary font-black border-b-2 border-primary pb-0.5' : 'text-dark hover:text-primary font-bold' ?> text-sm transition-colors duration-200 flex items-center space-x-1.5">
                        <span>Profil & Jenjang</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-hover:text-primary transition-colors"></i>
                    </a>
                    <!-- Dropdown List -->
                    <div class="absolute left-0 top-full hidden group-hover:block bg-white border border-gray-150 shadow-xl rounded-2xl py-3 w-52 transition-all duration-300 z-50">
                        <a href="profil.php" class="block px-4 py-2 text-xs font-bold <?= $current_page === 'profil' ? 'text-primary bg-cream' : 'text-gray-700 hover:bg-cream hover:text-primary' ?> transition">Profil Umum & Sejarah</a>
                        <div class="border-t border-gray-100 my-1.5"></div>
                        <a href="smp.php" class="block px-4 py-2 text-xs font-bold <?= $current_page === 'smp' ? 'text-primary bg-cream' : 'text-gray-700 hover:bg-cream hover:text-primary' ?> transition">SMP IT Babul Jannah</a>
                        <a href="sma.php" class="block px-4 py-2 text-xs font-bold <?= $current_page === 'sma' ? 'text-primary bg-cream' : 'text-gray-700 hover:bg-cream hover:text-primary' ?> transition">SMA IT Babul Jannah</a>
                        <a href="takhasus.php" class="block px-4 py-2 text-xs font-bold <?= $current_page === 'takhasus' ? 'text-primary bg-cream' : 'text-gray-700 hover:bg-cream hover:text-primary' ?> transition">Program Takhasus Tahfizh</a>
                    </div>
                </div>

                <a href="berita.php" class="<?= in_array($current_page, ['berita', 'berita_detail']) ? 'text-primary font-black border-b-2 border-primary pb-0.5' : 'text-dark hover:text-primary font-bold' ?> text-sm transition-colors duration-200">Berita</a>
                <a href="status.php" class="<?= $current_page === 'status' ? 'text-primary font-black border-b-2 border-primary pb-0.5' : 'text-dark hover:text-primary font-bold' ?> text-sm transition-colors duration-200">Status & Kelulusan</a>
                <a href="daftar.php" class="bg-primary hover:bg-secondary text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-md transition-all duration-300 transform hover:-translate-y-0.5">Daftar Sekarang</a>
            </nav>

            <!-- Mobile Menu Button (Hamburger) -->
            <div class="flex md:hidden">
                <button id="mobile-menu-btn" type="button" class="text-primary hover:text-secondary focus:outline-none p-2 rounded-md transition-colors" aria-label="Toggle Menu">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Dropdown Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border border-gray-150 bg-white/95 backdrop-blur-md shadow-lg rounded-2xl mx-auto mt-2 px-4 py-4 space-y-1 transition duration-300 ease-in-out absolute left-4 right-4 z-50">
            <a href="index.php" class="block px-3 py-2 rounded-xl text-sm font-bold <?= $current_page === 'index' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">Beranda</a>
            
            <div class="py-1">
                <span class="block px-3 py-1.5 text-[10px] font-extrabold uppercase text-gray-400 tracking-wider">Profil & Jenjang</span>
                <a href="profil.php" class="block pl-6 pr-3 py-1.5 rounded-xl text-xs font-bold <?= $current_page === 'profil' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">Profil Umum & Sejarah</a>
                <a href="smp.php" class="block pl-6 pr-3 py-1.5 rounded-xl text-xs font-bold <?= $current_page === 'smp' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">SMP IT Babul Jannah</a>
                <a href="sma.php" class="block pl-6 pr-3 py-1.5 rounded-xl text-xs font-bold <?= $current_page === 'sma' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">SMA IT Babul Jannah</a>
                <a href="takhasus.php" class="block pl-6 pr-3 py-1.5 rounded-xl text-xs font-bold <?= $current_page === 'takhasus' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">Program Takhasus Tahfizh</a>
            </div>

            <a href="berita.php" class="block px-3 py-2 rounded-xl text-sm font-bold <?= in_array($current_page, ['berita', 'berita_detail']) ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">Berita</a>
            <a href="status.php" class="block px-3 py-2 rounded-xl text-sm font-bold <?= $current_page === 'status' ? 'text-primary bg-cream' : 'text-dark hover:bg-cream hover:text-primary' ?>">Status & Kelulusan</a>
            <a href="daftar.php" class="block w-full text-center bg-primary text-white px-4 py-3 rounded-xl text-sm font-bold mt-2 hover:bg-secondary">Daftar Sekarang</a>
        </div>
        </div> <!-- End max-w-7xl -->
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">
