<?php
// admin/prestasi.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';

// Proteksi akses admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'humas', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin mengelola prestasi santri.");
}

$error = '';
$success = '';

// Daftar kategori resmi sesuai ENUM database
$allowed_kategori = ['Keagamaan', 'Akademik', 'Seni & Bahasa', 'Olahraga / Fisik', 'Lainnya'];

// Ambil data untuk mode edit via parameter URL (Fallback)
$edit_mode = false;
$edit_data = [
    'id' => 0,
    'nama' => '',
    'kategori' => 'Lainnya',
    'gambar' => ''
];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt_e = $db->prepare("SELECT * FROM prestasi WHERE id = :id");
        $stmt_e->execute(['id' => $edit_id]);
        $res_e = $stmt_e->fetch(PDO::FETCH_ASSOC);
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Prestasi
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_prestasi' || $_POST['action'] === 'edit_prestasi') {
        $nama = sanitize($_POST['nama'] ?? '');
        $kategori = sanitize($_POST['kategori'] ?? 'Lainnya');
        if (!in_array($kategori, $allowed_kategori)) {
            $kategori = 'Lainnya';
        }
        
        if (!empty($nama)) {
            $gambar_val = null;
            
            // Handle file upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/prestasi/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $gambar_val = upload_and_convert_to_webp('gambar', $upload_dir, 'prestasi');
                if (!$gambar_val) {
                    $error = "File gambar tidak valid atau gagal diproses (harus JPG/PNG/WEBP & maksimal 5MB).";
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_prestasi') {
                        if (empty($gambar_val)) {
                            $error = "Foto sertifikat atau piala prestasi wajib diunggah untuk data baru.";
                        } else {
                            $stmt = $db->prepare("INSERT INTO prestasi (nama, kategori, gambar) VALUES (:nama, :kategori, :gambar)");
                            $stmt->execute([
                                'nama' => $nama,
                                'kategori' => $kategori,
                                'gambar' => $gambar_val
                            ]);
                            $success = "Prestasi santri berhasil dipublikasikan ke portal!";
                        }
                    } elseif ($_POST['action'] === 'edit_prestasi') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        $old_img = $_POST['old_img'] ?? '';
                        
                        // Jika ada gambar baru diupload, hapus file lama
                        if (!empty($gambar_val)) {
                            $final_gambar = $gambar_val;
                            if (!empty($old_img)) {
                                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                                    supabase_delete('prestasi/' . $old_img);
                                }
                                if (file_exists('../uploads/prestasi/' . $old_img)) {
                                    @unlink('../uploads/prestasi/' . $old_img);
                                }
                            }
                        } else {
                            $final_gambar = $old_img;
                        }
                        
                        $stmt = $db->prepare("UPDATE prestasi SET nama = :nama, kategori = :kategori, gambar = :gambar WHERE id = :id");
                        $stmt->execute([
                            'nama' => $nama,
                            'kategori' => $kategori,
                            'gambar' => $final_gambar,
                            'id' => $id_edit
                        ]);
                        
                        $success = "Data prestasi santri berhasil diperbarui!";
                        $edit_mode = false;
                    }
                } catch (PDOException $e) {
                    $error = "Kesalahan Database: " . $e->getMessage();
                }
            }
        } else {
            $error = "Nama prestasi atau kejuaraan wajib diisi.";
        }
    }
}

// 2. Tangani Aksi Hapus Prestasi
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    if ($id_del > 0) {
        try {
            $s_img = $db->prepare("SELECT gambar FROM prestasi WHERE id = :id");
            $s_img->execute(['id' => $id_del]);
            $res = $s_img->fetch();
            
            if ($res && !empty($res['gambar'])) {
                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                    supabase_delete('prestasi/' . $res['gambar']);
                }
                if (file_exists('../uploads/prestasi/' . $res['gambar'])) {
                    @unlink('../uploads/prestasi/' . $res['gambar']);
                }
            }
            
            $stmt = $db->prepare("DELETE FROM prestasi WHERE id = :id");
            $stmt->execute(['id' => $id_del]);
            $success = "Data prestasi santri berhasil dihapus permanen.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus prestasi: " . $e->getMessage();
        }
    }
}

// 3. Ambil daftar semua prestasi untuk ditampilkan
try {
    $stmt_list = $db->query("SELECT * FROM prestasi ORDER BY id DESC");
    $prestasi_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prestasi_list = [];
}

// 4. Analisis Statistik & Metrik KPI Prestasi
$total_prestasi = count($prestasi_list);
$kategori_counts = [
    'Keagamaan' => 0,
    'Akademik' => 0,
    'Seni & Bahasa' => 0,
    'Olahraga / Fisik' => 0,
    'Lainnya' => 0
];

foreach ($prestasi_list as $p) {
    $kat = $p['kategori'] ?? 'Lainnya';
    if (isset($kategori_counts[$kat])) {
        $kategori_counts[$kat]++;
    } else {
        $kategori_counts['Lainnya']++;
    }
}

// Helper badge kategori styling
if (!function_exists('get_prestasi_badge_meta')) {
    function get_prestasi_badge_meta($kategori) {
        switch ((string)$kategori) {
            case 'Keagamaan':
                return [
                    'label' => 'Keagamaan & Tahfidz',
                    'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
                    'dot' => 'bg-emerald-500',
                    'icon' => 'fa-solid fa-book-quran'
                ];
            case 'Akademik':
                return [
                    'label' => 'Akademik & Sains',
                    'class' => 'bg-blue-50 text-blue-800 border-blue-200/80',
                    'dot' => 'bg-blue-500',
                    'icon' => 'fa-solid fa-graduation-cap'
                ];
            case 'Seni & Bahasa':
                return [
                    'label' => 'Seni & Bahasa',
                    'class' => 'bg-purple-50 text-purple-800 border-purple-200/80',
                    'dot' => 'bg-purple-500',
                    'icon' => 'fa-solid fa-paintbrush'
                ];
            case 'Olahraga / Fisik':
                return [
                    'label' => 'Olahraga & Jasmani',
                    'class' => 'bg-orange-50 text-orange-800 border-orange-200/80',
                    'dot' => 'bg-orange-500',
                    'icon' => 'fa-solid fa-medal'
                ];
            default:
                return [
                    'label' => 'Penghargaan Lainnya',
                    'class' => 'bg-amber-50 text-amber-800 border-amber-200/80',
                    'dot' => 'bg-amber-500',
                    'icon' => 'fa-solid fa-award'
                ];
        }
    }
}

$page_title = 'Kelola Prestasi Santri - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<style>
    /* Brand Color Fallbacks */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-deep { background-color: #432B25 !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .border-brand-primary { border-color: #5C3E35 !important; }

    /* Line clamp utilities */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Main Container (Single Transparent Scrollbar) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & PORTAL STATUS BAR (UI-UX PRO MAX) -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-trophy"></i>
                        <span>Galeri Prestasi &amp; Kejuaraan Santri</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">Portal Ponpes Babul Jannah</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola dokumentasi sertifikat, medali, piala, dan catatan prestasi santri di tingkat kota, provinsi, hingga nasional.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Shortcut to Public Profile Section -->
                    <a href="../profil.php#prestasi" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat di Halaman Profil Web">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat di Web</span>
                    </a>

                    <!-- Add New Prestasi Button -->
                    <button type="button" onclick="openModalTambah()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tambah Prestasi Baru">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Tambah Prestasi</span>
                    </button>
                </div>
            </div>

            <!-- Alerts Notification -->
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
            <!-- 2. TOP METRIC OVERVIEW CARDS (4 KPI STATS) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                
                <!-- Stat 1: Total Prestasi -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Prestasi</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-gray-900"><?= number_format($total_prestasi) ?></span>
                            <span class="text-[10px] text-gray-500 font-semibold">Kejuaraan</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Keagamaan / Tahfidz -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-book-quran"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Keagamaan &amp; MTQ</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-emerald-800"><?= number_format($kategori_counts['Keagamaan']) ?></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">Piagam</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Sains & Akademik -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Akademik &amp; OSN</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-blue-800"><?= number_format($kategori_counts['Akademik']) ?></span>
                            <span class="text-[10px] text-blue-700 font-semibold">Medali</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Seni, Bahasa & Olahraga -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-medal"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Seni &amp; Olahraga</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-purple-800"><?= number_format($kategori_counts['Seni & Bahasa'] + $kategori_counts['Olahraga / Fisik'] + $kategori_counts['Lainnya']) ?></span>
                            <span class="text-[10px] text-purple-700 font-semibold">Piala</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. SMART FILTER, SEARCH & CATEGORY PILLS (UI-UX PRO MAX) -->
            <!-- ======================================================== -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs space-y-3.5">
                
                <!-- Row 1: Search Input & Controls -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    
                    <!-- Search Input -->
                    <div class="relative flex-grow max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="search-prestasi" oninput="filterPrestasi()" placeholder="Cari nama kejuaraan, juara 1 tahfidz, olimpiade sains..."
                            class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/20 transition">
                        <button type="button" id="btn-clear-search" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Right Controls: Sort & View Mode Switcher -->
                    <div class="flex items-center space-x-3 flex-shrink-0">
                        
                        <!-- Sort Filter -->
                        <div class="flex items-center space-x-1.5 text-xs text-gray-600">
                            <label for="sort-prestasi" class="text-[11px] font-bold text-gray-500 hidden sm:inline">Urutkan:</label>
                            <select id="sort-prestasi" onchange="sortPrestasiItems()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-brand-primary">
                                <option value="terbaru">Terbaru Ditambahkan</option>
                                <option value="terlama">Terlama Ditambahkan</option>
                                <option value="az">Nama (A - Z)</option>
                                <option value="za">Nama (Z - A)</option>
                            </select>
                        </div>

                        <!-- View Switcher (Grid vs Table) -->
                        <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200/80 text-xs">
                            <button type="button" id="btn-view-grid" onclick="setViewMode('grid')" class="p-1.5 px-2.5 rounded-lg font-bold transition flex items-center space-x-1.5 bg-brand-primary text-white shadow-xs" title="Tampilan Grid Kartu Visual">
                                <i class="fa-solid fa-grip"></i>
                                <span class="hidden sm:inline text-[11px]">Grid</span>
                            </button>
                            <button type="button" id="btn-view-table" onclick="setViewMode('table')" class="p-1.5 px-2.5 rounded-lg font-bold transition flex items-center space-x-1.5 text-gray-500 hover:bg-gray-200" title="Tampilan Tabel Data Ringkas">
                                <i class="fa-solid fa-list"></i>
                                <span class="hidden sm:inline text-[11px]">Tabel</span>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Row 2: Category Filter Tabs Pills -->
                <div class="flex items-center space-x-1.5 overflow-x-auto custom-scrollbar pb-1 text-xs">
                    <button type="button" onclick="setKategoriFilter('all', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap bg-brand-primary text-white shadow-xs">
                        Semua Kategori (<?= $total_prestasi ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('Keagamaan', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-book-quran mr-1 text-emerald-500"></i> Keagamaan (<?= $kategori_counts['Keagamaan'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('Akademik', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-graduation-cap mr-1 text-blue-500"></i> Akademik (<?= $kategori_counts['Akademik'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('Seni & Bahasa', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-paintbrush mr-1 text-purple-500"></i> Seni &amp; Bahasa (<?= $kategori_counts['Seni & Bahasa'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('Olahraga / Fisik', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-medal mr-1 text-orange-500"></i> Olahraga (<?= $kategori_counts['Olahraga / Fisik'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('Lainnya', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-award mr-1 text-amber-500"></i> Lainnya (<?= $kategori_counts['Lainnya'] ?>)
                    </button>
                </div>

                <!-- Info Bar: Showing Count -->
                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-100">
                    <div>
                        Menampilkan <strong id="visible-count" class="text-gray-900"><?= $total_prestasi ?></strong> dari <strong class="text-gray-900"><?= $total_prestasi ?></strong> prestasi santri
                    </div>
                    <div class="flex items-center space-x-2 text-[10px] text-gray-400">
                        <span><i class="fa-solid fa-circle-info mr-1"></i> Klik foto piala/sertifikat untuk membuka gambar resolusi penuh</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4A. GRID VIEW: KARTU PRESTASI INTERAKTIF (DEFAULT VIEW) -->
            <!-- ======================================================== -->
            <div id="view-grid-container" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                <?php if ($total_prestasi === 0): ?>
                    <div class="col-span-full bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                        <i class="fa-solid fa-trophy text-5xl mb-3 block opacity-30 text-brand-primary"></i>
                        <h4 class="text-sm font-bold text-gray-700">Belum Ada Data Prestasi</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Publikasikan piala, sertifikat, atau catatan prestasi membanggakan santri.</p>
                        <button type="button" onclick="openModalTambah()" class="mt-4 inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-4 rounded-xl text-xs shadow-xs transition">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tambah Prestasi Pertama</span>
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($prestasi_list as $p): 
                        $kat_meta = get_prestasi_badge_meta($p['kategori'] ?? 'Lainnya');
                        $img_url = get_storage_url('prestasi', $p['gambar'], '', true);
                    ?>
                        <article class="prestasi-item-card bg-white rounded-2xl border border-gray-200/90 hover:border-brand-primary/40 shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between overflow-hidden group"
                            data-id="<?= $p['id'] ?>"
                            data-title="<?= htmlspecialchars(strtolower($p['nama'])) ?>"
                            data-kategori="<?= htmlspecialchars(strtolower($p['kategori'] ?? 'Lainnya')) ?>">
                            
                            <div>
                                <!-- Image Container with Lightbox Trigger -->
                                <div class="aspect-[4/3] w-full bg-gray-100 relative overflow-hidden cursor-pointer"
                                     onclick="openPhotoLightbox('<?= $img_url ?>', '<?= htmlspecialchars(addslashes($p['nama'])) ?> (<?= htmlspecialchars(addslashes($p['kategori'])) ?>)')">
                                    <img src="<?= $img_url ?>" alt="<?= htmlspecialchars($p['nama']) ?>" 
                                         onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>
                                    
                                    <!-- Category Pill Badge -->
                                    <div class="absolute top-3 left-3 z-10">
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-0.5 rounded-lg shadow-xs border bg-white/95 backdrop-blur-xs <?= $kat_meta['class'] ?>">
                                            <i class="<?= $kat_meta['icon'] ?> mr-1.5 text-[9px]"></i>
                                            <?= htmlspecialchars($p['kategori']) ?>
                                        </span>
                                    </div>

                                    <!-- Trophy watermark icon -->
                                    <div class="absolute bottom-2.5 right-3 z-10 text-white/80 group-hover:text-amber-300 transition-colors">
                                        <i class="fa-solid fa-trophy text-sm drop-shadow"></i>
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-4 space-y-1.5">
                                    <h3 class="font-black text-gray-900 text-xs sm:text-sm leading-snug line-clamp-2 group-hover:text-brand-primary transition-colors">
                                        <?= htmlspecialchars($p['nama']) ?>
                                    </h3>
                                    <span class="block text-[10px] text-gray-400 font-semibold">
                                        <i class="fa-regular fa-calendar mr-1"></i>
                                        <?= date('d M Y', strtotime($p['created_at'])) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Footer Action Bar (UI-UX Pro Max: Explicit & Accessible) -->
                            <div class="p-3 sm:px-4 border-t border-gray-100 flex items-center justify-between gap-2 bg-gray-50/50 rounded-b-2xl">
                                
                                <!-- Lightbox Button -->
                                <button type="button" 
                                        onclick="openPhotoLightbox('<?= $img_url ?>', '<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                                        class="inline-flex items-center space-x-1 text-xs font-bold text-gray-600 hover:text-brand-primary transition" title="Lihat Foto Fullscreen">
                                    <i class="fa-solid fa-magnifying-glass-plus text-[11px] text-gray-400"></i>
                                    <span class="text-[11px]">Foto</span>
                                </button>

                                <div class="flex items-center space-x-1.5">
                                    <!-- Edit Button -->
                                    <button type="button" 
                                            onclick="openModalEdit(this)"
                                            data-id="<?= $p['id'] ?>"
                                            data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>"
                                            data-kategori="<?= htmlspecialchars($p['kategori'] ?? 'Lainnya', ENT_QUOTES) ?>"
                                            data-gambar="<?= htmlspecialchars($p['gambar'], ENT_QUOTES) ?>"
                                            data-gambar-url="<?= $img_url ?>"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-2 py-1 rounded-xl transition" title="Edit Data Prestasi">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                        <span class="text-[11px]">Edit</span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button"
                                            onclick="confirmDeletePrestasi(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 px-2 py-1 rounded-xl transition" title="Hapus Prestasi">
                                        <i class="fa-solid fa-trash text-[10px]"></i>
                                        <span class="text-[11px]">Hapus</span>
                                    </button>
                                </div>

                            </div>

                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ======================================================== -->
            <!-- 4B. TABLE VIEW: TABEL DATA RINGKAS (ALTERNATIF VIEW) -->
            <!-- ======================================================== -->
            <div id="view-table-container" class="hidden bg-white rounded-2xl border border-gray-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4 w-20">Foto</th>
                                <th class="py-3 px-4">Nama Kejuaraan / Prestasi</th>
                                <th class="py-3 px-4 w-40">Kategori</th>
                                <th class="py-3 px-4 w-32">Tanggal Unggah</th>
                                <th class="py-3 px-4 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($total_prestasi === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-400">Belum ada data prestasi santri.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($prestasi_list as $p): 
                                    $kat_meta = get_prestasi_badge_meta($p['kategori'] ?? 'Lainnya');
                                    $img_url = get_storage_url('prestasi', $p['gambar'], '', true);
                                ?>
                                    <tr class="prestasi-item-row hover:bg-gray-50/60 transition"
                                        data-id="<?= $p['id'] ?>"
                                        data-title="<?= htmlspecialchars(strtolower($p['nama'])) ?>"
                                        data-kategori="<?= htmlspecialchars(strtolower($p['kategori'] ?? 'Lainnya')) ?>">
                                        <td class="py-3 px-4 text-center font-black text-gray-400"><?= $no++ ?></td>
                                        <td class="py-3 px-4">
                                            <img src="<?= $img_url ?>" alt="<?= htmlspecialchars($p['nama']) ?>" 
                                                 onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                                 onclick="openPhotoLightbox('<?= $img_url ?>', '<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                                                 class="w-12 h-10 rounded-lg object-cover border border-gray-200 bg-gray-100 cursor-pointer hover:scale-105 transition">
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="font-extrabold text-gray-900 text-xs">
                                                <?= htmlspecialchars($p['nama']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md border <?= $kat_meta['class'] ?>">
                                                <i class="<?= $kat_meta['icon'] ?> mr-1 text-[9px]"></i>
                                                <?= htmlspecialchars($p['kategori']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-gray-500 font-semibold text-[11px] whitespace-nowrap">
                                            <?= date('d M Y', strtotime($p['created_at'])) ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex items-center justify-center space-x-1.5">
                                                <button type="button" 
                                                        onclick="openModalEdit(this)"
                                                        data-id="<?= $p['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>"
                                                        data-kategori="<?= htmlspecialchars($p['kategori'] ?? 'Lainnya', ENT_QUOTES) ?>"
                                                        data-gambar="<?= htmlspecialchars($p['gambar'], ENT_QUOTES) ?>"
                                                        data-gambar-url="<?= $img_url ?>"
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Edit">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button type="button" 
                                                        onclick="confirmDeletePrestasi(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition" title="Hapus">
                                                    <i class="fa-solid fa-trash text-[10px]"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty Search State -->
            <div id="prestasi-empty-state" class="hidden bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-3 block opacity-30 text-gray-400"></i>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Prestasi yang Cocok</h4>
                <p class="text-xs text-gray-500 mt-1">Coba gunakan kata kunci pencarian atau kategori filter lainnya.</p>
                <button type="button" onclick="resetAllFilters()" class="mt-3 inline-flex items-center space-x-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-1.5 px-3 rounded-xl text-xs transition">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset Semua Filter</span>
                </button>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL TAMBAH & EDIT PRESTASI (UI-UX PRO MAX) -->
    <!-- ======================================================== -->
    <div id="modal-prestasi" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 sm:px-8 py-4.5 flex-shrink-0 bg-gray-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-primary text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-black text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Prestasi Santri
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Prestasi Baru
                            <?php endif; ?>
                        </h2>
                        <p class="text-[11px] text-gray-500">Lengkapi data kejuaraan, pilih kategori, dan unggah foto dokumentasi.</p>
                    </div>
                </div>

                <button type="button" onclick="closeModalPrestasi()" class="w-8 h-8 rounded-xl bg-gray-150 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Scrollable Body -->
            <div class="p-6 sm:p-8 overflow-y-auto custom-scrollbar flex-grow space-y-5">
                <form id="form-prestasi" action="prestasi.php" method="POST" enctype="multipart/form-data" class="space-y-5" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" id="form-action" value="<?= $edit_mode ? 'edit_prestasi' : 'add_prestasi' ?>">
                    <input type="hidden" name="id" id="form-id" value="<?= $edit_data['id'] ?>">
                    <input type="hidden" name="old_img" id="form-old-img" value="<?= htmlspecialchars($edit_data['gambar']) ?>">

                    <!-- Field 1: Nama Prestasi -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Nama Prestasi / Kejuaraan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama" id="form-nama" required placeholder="Contoh: Juara 1 Tahfidz 10 Juz Tingkat Provinsi, Medali Emas OSN Sains..."
                            value="<?= htmlspecialchars($edit_data['nama']) ?>"
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                    </div>

                    <!-- Field 2: Kategori Bidang Sesuai ENUM -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Kategori Bidang <span class="text-rose-500">*</span>
                        </label>
                        <select name="kategori" id="form-kategori" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                            <option value="Keagamaan" <?= $edit_data['kategori'] === 'Keagamaan' ? 'selected' : '' ?>>📖 Keagamaan (Tahfidz, Tilawah, MTQ, Fiqih)</option>
                            <option value="Akademik" <?= $edit_data['kategori'] === 'Akademik' ? 'selected' : '' ?>>🎓 Akademik (Olimpiade Sains, OSN, KSM, Matematika)</option>
                            <option value="Seni & Bahasa" <?= $edit_data['kategori'] === 'Seni & Bahasa' ? 'selected' : '' ?>>🎨 Seni &amp; Bahasa (Kaligrafi, Pidato 3 Bahasa, Debat)</option>
                            <option value="Olahraga / Fisik" <?= $edit_data['kategori'] === 'Olahraga / Fisik' ? 'selected' : '' ?>>⚽ Olahraga / Fisik (Pencak Silat, Panahan, Futsal)</option>
                            <option value="Lainnya" <?= $edit_data['kategori'] === 'Lainnya' ? 'selected' : '' ?>>🏅 Lainnya / Penghargaan Khusus</option>
                        </select>
                    </div>

                    <!-- Field 3: Foto Dokumentasi Prestasi -->
                    <div class="space-y-3 bg-gray-50/80 p-4 sm:p-5 rounded-2xl border border-gray-200/80">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider">
                                <i class="fa-solid fa-camera text-brand-primary mr-1"></i> Foto Piagam / Sertifikat / Piala <span id="req-star" class="text-rose-500"><?= $edit_mode ? '' : '*' ?></span>
                            </label>
                            <span class="text-[10px] text-gray-400 font-semibold">WebP / JPG / PNG (Maks 5MB)</span>
                        </div>

                        <!-- Existing Photo Preview (in Edit Mode) -->
                        <div id="existing-photo-box" class="<?= ($edit_mode && !empty($edit_data['gambar'])) ? '' : 'hidden' ?> p-3.5 bg-white rounded-xl border border-gray-200 shadow-2xs">
                            <div class="flex items-center space-x-3.5">
                                <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 flex-shrink-0 relative">
                                    <img id="existing-photo-img" src="<?= $edit_mode ? get_storage_url('prestasi', $edit_data['gambar'], '', true) : '' ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="space-y-1 min-w-0">
                                    <span class="inline-flex items-center text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                                        <i class="fa-solid fa-image mr-1"></i> Foto Saat Ini
                                    </span>
                                    <p class="text-[11px] text-gray-500">Pilih foto baru di bawah jika Anda ingin mengganti foto saat ini.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Drag & Drop Upload Zone -->
                        <div id="prestasi-dropzone" class="border-2 border-dashed border-gray-300 hover:border-brand-primary bg-white rounded-2xl p-5 text-center relative cursor-pointer hover:bg-gray-50/80 transition-all group">
                            <input type="file" name="gambar" id="input-gambar-prestasi" accept="image/*" 
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                   onchange="handleNewPhotoSelected(this.files)">
                            
                            <div id="dropzone-prompt" class="space-y-1.5">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-brand-primary/60 group-hover:text-brand-primary group-hover:scale-110 transition-all duration-200"></i>
                                <p class="text-xs text-gray-700 font-bold">Pilih atau seret foto sertifikat/piala ke sini</p>
                                <p class="text-[10px] text-gray-400">Otomatis dioptimasi ke format WebP untuk loading instan.</p>
                            </div>

                            <!-- Live Selected Thumbnail Preview -->
                            <div id="new-photo-preview" class="hidden flex items-center justify-between bg-white p-2.5 rounded-xl border border-gray-200 shadow-2xs z-20 relative">
                                <div class="flex items-center space-x-3 min-w-0">
                                    <img id="new-photo-img" src="" class="w-14 h-14 object-cover rounded-lg border border-gray-200 bg-gray-50 flex-shrink-0">
                                    <div class="text-left min-w-0">
                                        <span id="new-photo-name" class="block text-xs font-bold text-gray-900 truncate max-w-[200px] sm:max-w-xs"></span>
                                        <span id="new-photo-size" class="block text-[10px] text-gray-400 font-semibold"></span>
                                        <span class="inline-block text-[10px] text-emerald-600 font-bold mt-0.5"><i class="fa-solid fa-circle-check mr-1"></i> Siap diupload</span>
                                    </div>
                                </div>
                                <button type="button" onclick="removeSelectedNewPhoto()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition" title="Batalkan Foto">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModalPrestasi()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-prestasi" class="bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md hover:shadow-lg transition-all transform active:scale-98 flex items-center space-x-2">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span id="btn-submit-text"><?= $edit_mode ? 'Simpan Perubahan' : 'Publikasikan Prestasi' ?></span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 6. BUILT-IN PHOTO LIGHTBOX MODAL -->
    <!-- ======================================================== -->
    <div id="lightbox-modal" class="hidden fixed inset-0 z-60 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4" onclick="closePhotoLightbox()">
        <div class="relative max-w-4xl max-h-[90vh] bg-transparent flex flex-col items-center" onclick="event.stopPropagation()">
            <button type="button" onclick="closePhotoLightbox()" class="absolute -top-10 right-0 text-white hover:text-rose-400 text-2xl transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <img id="lightbox-img" src="" class="max-h-[80vh] w-auto rounded-2xl object-contain shadow-2xl border border-white/20">
            <span id="lightbox-caption" class="text-white text-xs font-bold mt-2.5 text-center drop-shadow"></span>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- JAVASCRIPT LOGIC (SEARCH, FILTER, PILLS, MODAL, PREVIEW) -->
    <!-- ======================================================== -->
    <script>
        let currentKategoriFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            // Restore saved view mode
            const savedView = localStorage.getItem('admin_prestasi_view_mode') || 'grid';
            setViewMode(savedView);

            // Keyboard shortcut Esc
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const lightbox = document.getElementById('lightbox-modal');
                    if (lightbox && !lightbox.classList.contains('hidden')) {
                        closePhotoLightbox();
                        return;
                    }
                    const modal = document.getElementById('modal-prestasi');
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModalPrestasi();
                    }
                }
            });
        });

        // 1. View Mode Switcher
        function setViewMode(mode) {
            const gridEl = document.getElementById('view-grid-container');
            const tableEl = document.getElementById('view-table-container');
            const btnGrid = document.getElementById('btn-view-grid');
            const btnTable = document.getElementById('btn-view-table');

            if (mode === 'table') {
                gridEl.classList.add('hidden');
                tableEl.classList.remove('hidden');
                btnTable.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnGrid.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_prestasi_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                btnGrid.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnTable.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_prestasi_view_mode', 'grid');
            }
        }

        // 2. Kategori Pill Filter
        function setKategoriFilter(kat, btn) {
            currentKategoriFilter = kat;

            document.querySelectorAll('.btn-kategori-tab').forEach(b => {
                b.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                b.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            if (btn) {
                btn.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
            }

            filterPrestasi();
        }

        // 3. Search & Filter Handling
        function filterPrestasi() {
            const query = (document.getElementById('search-prestasi').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btn-clear-search');
            if (btnClear) {
                btnClear.classList.toggle('hidden', query.length === 0);
            }

            const cards = Array.from(document.querySelectorAll('.prestasi-item-card'));
            const rows = Array.from(document.querySelectorAll('.prestasi-item-row'));
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const kat = card.getAttribute('data-kategori') || '';

                const matchesQuery = !query || title.includes(query) || kat.includes(query);
                const matchesKat = (currentKategoriFilter === 'all') || (kat === currentKategoriFilter.toLowerCase());

                const isVisible = matchesQuery && matchesKat;
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const kat = row.getAttribute('data-kategori') || '';

                const matchesQuery = !query || title.includes(query) || kat.includes(query);
                const matchesKat = (currentKategoriFilter === 'all') || (kat === currentKategoriFilter.toLowerCase());

                row.style.display = (matchesQuery && matchesKat) ? '' : 'none';
            });

            const countEl = document.getElementById('visible-count');
            if (countEl) countEl.textContent = visibleCount;

            const emptyEl = document.getElementById('prestasi-empty-state');
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-prestasi');
            if (searchInput) {
                searchInput.value = '';
                filterPrestasi();
                searchInput.focus();
            }
        }

        function resetAllFilters() {
            clearSearch();
            const firstTab = document.querySelector('.btn-kategori-tab');
            setKategoriFilter('all', firstTab);
        }

        // 4. Sorting Function
        function sortPrestasiItems() {
            const sortBy = document.getElementById('sort-prestasi').value;
            const gridContainer = document.getElementById('view-grid-container');
            const tableBody = document.querySelector('#view-table-container tbody');

            const cards = Array.from(document.querySelectorAll('.prestasi-item-card'));
            const rows = Array.from(document.querySelectorAll('.prestasi-item-row'));

            const sortFn = (a, b) => {
                if (sortBy === 'terbaru') {
                    return parseInt(b.getAttribute('data-id') || 0) - parseInt(a.getAttribute('data-id') || 0);
                } else if (sortBy === 'terlama') {
                    return parseInt(a.getAttribute('data-id') || 0) - parseInt(b.getAttribute('data-id') || 0);
                } else if (sortBy === 'az') {
                    return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
                } else if (sortBy === 'za') {
                    return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
                }
                return 0;
            };

            cards.sort(sortFn).forEach(el => gridContainer.appendChild(el));
            rows.sort(sortFn).forEach(el => tableBody.appendChild(el));
        }

        // 5. Modal Tambah & Edit Handling
        function openModalTambah() {
            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Prestasi Baru';
            document.getElementById('form-action').value = 'add_prestasi';
            document.getElementById('form-id').value = '';
            document.getElementById('form-old-img').value = '';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-kategori').value = 'Keagamaan';
            document.getElementById('btn-submit-text').textContent = 'Publikasikan Prestasi';
            document.getElementById('req-star').textContent = '*';

            // Hide existing photo box
            const existingBox = document.getElementById('existing-photo-box');
            if (existingBox) existingBox.classList.add('hidden');

            removeSelectedNewPhoto();

            document.getElementById('modal-prestasi').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function openModalEdit(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama') || '';
            const kategori = btn.getAttribute('data-kategori') || 'Lainnya';
            const gambar = btn.getAttribute('data-gambar') || '';
            const gambarUrl = btn.getAttribute('data-gambar-url') || '';

            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Prestasi Santri';
            document.getElementById('form-action').value = 'edit_prestasi';
            document.getElementById('form-id').value = id;
            document.getElementById('form-old-img').value = gambar;
            document.getElementById('form-nama').value = nama;
            document.getElementById('form-kategori').value = kategori;
            document.getElementById('btn-submit-text').textContent = 'Simpan Perubahan';
            document.getElementById('req-star').textContent = '';

            // Existing photo box handling
            const existingBox = document.getElementById('existing-photo-box');
            const existingImg = document.getElementById('existing-photo-img');

            if (existingBox && existingImg && gambarUrl) {
                existingImg.src = gambarUrl;
                existingBox.classList.remove('hidden');
            } else if (existingBox) {
                existingBox.classList.add('hidden');
            }

            removeSelectedNewPhoto();

            document.getElementById('modal-prestasi').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function closeModalPrestasi() {
            document.getElementById('modal-prestasi').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (window.location.search.includes('edit=')) {
                window.history.replaceState({}, document.title, 'prestasi.php');
            }
        }

        // 6. Photo Upload & Preview Handling
        function handleNewPhotoSelected(files) {
            if (!files || files.length === 0) return;
            const file = files[0];
            if (!file.type.startsWith('image/')) {
                alert('Silakan pilih berkas gambar yang valid (JPG, PNG, WEBP).');
                return;
            }

            const previewContainer = document.getElementById('new-photo-preview');
            const previewImg = document.getElementById('new-photo-img');
            const previewName = document.getElementById('new-photo-name');
            const previewSize = document.getElementById('new-photo-size');
            const dropzonePrompt = document.getElementById('dropzone-prompt');

            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewName.textContent = file.name;
                previewSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                previewContainer.classList.remove('hidden');
                dropzonePrompt.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        function removeSelectedNewPhoto() {
            const fileInput = document.getElementById('input-gambar-prestasi');
            if (fileInput) fileInput.value = '';
            const previewContainer = document.getElementById('new-photo-preview');
            const dropzonePrompt = document.getElementById('dropzone-prompt');
            if (previewContainer && dropzonePrompt) {
                previewContainer.classList.add('hidden');
                dropzonePrompt.classList.remove('hidden');
            }
        }

        // 7. Lightbox Modal Viewer
        function openPhotoLightbox(src, caption) {
            const modal = document.getElementById('lightbox-modal');
            const img = document.getElementById('lightbox-img');
            const cap = document.getElementById('lightbox-caption');
            if (modal && img) {
                img.src = src;
                if (cap) cap.textContent = caption || '';
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closePhotoLightbox() {
            const modal = document.getElementById('lightbox-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // 8. Form Submit Spinner Protection
        function handleFormSubmit(form) {
            const btn = document.getElementById('btn-submit-prestasi');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Menyimpan...</span>';
            }
            return true;
        }

        // 9. SweetAlert2 Confirmation for Deleting Prestasi
        function confirmDeletePrestasi(id, nama) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Prestasi Ini?',
                    html: `Apakah Anda yakin ingin menghapus data prestasi:<br><strong class="text-rose-700">"${nama}"</strong>?<br><br><span class="text-xs text-gray-500">Berkas foto sertifikat dan seluruh data prestasi akan dihapus secara permanen.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Hapus Prestasi',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `prestasi.php?action=delete&id=${id}`;
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus prestasi "${nama}"?`)) {
                    window.location.href = `prestasi.php?action=delete&id=${id}`;
                }
            }
        }
    </script>

</body>
</html>