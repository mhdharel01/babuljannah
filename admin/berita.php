<?php
// admin/berita.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/sitemap_generator.php';

// Proteksi akses admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Helper estimasi waktu baca (kata / 150)
if (!function_exists('estimasi_baca')) {
    function estimasi_baca($teks) {
        $clean = strip_tags((string)$teks);
        $words = preg_split('/\s+/', trim($clean));
        $count = count(array_filter($words));
        return max(1, (int)ceil($count / 150));
    }
}

// 1. Tangani Aksi Tambah & Edit Berita
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_berita' || $_POST['action'] === 'edit_berita') {
        $judul = sanitize($_POST['judul'] ?? '');
        $kutipan = sanitize($_POST['kutipan'] ?? '');
        $isi = trim($_POST['isi'] ?? ''); // Preserve HTML content
        
        if (!empty($judul) && !empty($kutipan) && !empty($isi)) {
            $gambar_name = null;
            
            // Handle file upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/berita/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $gambar_name = upload_and_convert_to_webp('gambar', $upload_dir, 'berita');
                if (!$gambar_name) {
                    $error = "File gambar tidak valid atau gagal diproses. Gunakan JPG, PNG, atau WEBP (Maksimal 5MB).";
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_berita') {
                        $img_final = $gambar_name ?? 'berita_1.webp';
                        $stmt = $db->prepare("INSERT INTO berita (judul, kutipan, isi, gambar) VALUES (:judul, :kutipan, :isi, :gambar)");
                        $stmt->execute([
                            'judul' => $judul,
                            'kutipan' => $kutipan,
                            'isi' => $isi,
                            'gambar' => $img_final
                        ]);
                        $success = "Berita baru berhasil dipublikasikan ke portal!";
                    } elseif ($_POST['action'] === 'edit_berita') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        if ($id_edit > 0) {
                            if ($gambar_name) {
                                // Hapus gambar lama jika ada
                                $stmt_old = $db->prepare("SELECT gambar FROM berita WHERE id = :id");
                                $stmt_old->execute(['id' => $id_edit]);
                                $old_b = $stmt_old->fetch();
                                if ($old_b && !empty($old_b['gambar'])) {
                                    supabase_delete('berita/' . $old_b['gambar']);
                                    @unlink('../uploads/berita/' . $old_b['gambar']);
                                }
                                $stmt = $db->prepare("UPDATE berita SET judul = :judul, kutipan = :kutipan, isi = :isi, gambar = :gambar WHERE id = :id");
                                $stmt->execute([
                                    'judul' => $judul,
                                    'kutipan' => $kutipan,
                                    'isi' => $isi,
                                    'gambar' => $gambar_name,
                                    'id' => $id_edit
                                ]);
                            } else {
                                $stmt = $db->prepare("UPDATE berita SET judul = :judul, kutipan = :kutipan, isi = :isi WHERE id = :id");
                                $stmt->execute([
                                    'judul' => $judul,
                                    'kutipan' => $kutipan,
                                    'isi' => $isi,
                                    'id' => $id_edit
                                ]);
                            }
                            $success = "Artikel berita berhasil diperbarui!";
                        }
                    }
                    
                    // Generate ulang sitemap XML secara otomatis
                    if (function_exists('generate_sitemap')) {
                        generate_sitemap($db);
                    }
                    
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan data berita: " . $e->getMessage();
                }
            }
        } else {
            $error = "Mohon lengkapi seluruh field (Judul, Kutipan Singkat, dan Isi Konten).";
        }
    }
}

// 2. Tangani Aksi Hapus Berita
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt_old = $db->prepare("SELECT gambar FROM berita WHERE id = :id");
            $stmt_old->execute(['id' => $id]);
            $old_b = $stmt_old->fetch();
            if ($old_b && !empty($old_b['gambar'])) {
                supabase_delete('berita/' . $old_b['gambar']);
                @unlink('../uploads/berita/' . $old_b['gambar']);
            }
            $stmt = $db->prepare("DELETE FROM berita WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Artikel berita berhasil dihapus permanen.";
            
            // Generate ulang sitemap
            if (function_exists('generate_sitemap')) {
                generate_sitemap($db);
            }
        } catch (PDOException $e) {
            $error = "Gagal menghapus berita: " . $e->getMessage();
        }
    }
}

// 3. Ambil data untuk mode edit via parameter URL (Fallback)
$edit_mode = false;
$edit_data = [
    'id' => 0,
    'judul' => '',
    'kutipan' => '',
    'isi' => '',
    'gambar' => ''
];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt_e = $db->prepare("SELECT * FROM berita WHERE id = :id");
        $stmt_e->execute(['id' => $edit_id]);
        $res_e = $stmt_e->fetch(PDO::FETCH_ASSOC);
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 4. Ambil semua berita dari database
$stmt_all = $db->query("SELECT * FROM berita ORDER BY id DESC");
$berita_list = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// 5. Analisis Statistik & Metrik KPI Berita
$total_berita = count($berita_list);
$current_month = date('Y-m');
$berita_bulan_ini = 0;
$berita_dengan_gambar = 0;
$berita_terbaru = null;

foreach ($berita_list as $b) {
    if (substr($b['created_at'], 0, 7) === $current_month) {
        $berita_bulan_ini++;
    }
    if (!empty($b['gambar'])) {
        $berita_dengan_gambar++;
    }
    if (!$berita_terbaru) {
        $berita_terbaru = $b;
    }
}

$page_title = 'Kelola Berita & Publikasi - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<style>
    /* Brand Color Fallbacks */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-deep { background-color: #432B25 !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .border-brand-primary { border-color: #5C3E35 !important; }

    /* Line clamp utilities */
    .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Live Preview Box Prose Styles */
    .preview-prose h2 {
        font-size: 1.25rem;
        font-weight: 800;
        color: #5C3E35;
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
        border-bottom: 2px solid #F3EDE6;
        padding-bottom: 0.25rem;
    }
    .preview-prose h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2F2522;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }
    .preview-prose p {
        margin-bottom: 0.85rem;
        line-height: 1.75;
        color: #374151;
    }
    .preview-prose blockquote {
        border-left: 4px solid #A17A6F;
        padding: 0.75rem 1rem;
        font-style: italic;
        color: #4A3029;
        background-color: #FDFBF7;
        border-radius: 0 0.75rem 0.75rem 0;
        margin: 1rem 0;
    }
    .preview-prose ul {
        list-style-type: disc;
        padding-left: 1.5rem;
        margin-bottom: 0.85rem;
    }
    .preview-prose ol {
        list-style-type: decimal;
        padding-left: 1.5rem;
        margin-bottom: 0.85rem;
    }
    .preview-prose li {
        margin-bottom: 0.25rem;
        color: #374151;
    }
    .preview-prose a {
        color: #5C3E35;
        text-decoration: underline;
        font-weight: 600;
    }
    .preview-prose hr {
        border: 0;
        border-top: 1px solid #E5E7EB;
        margin: 1.25rem 0;
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
                        <i class="fa-solid fa-newspaper"></i>
                        <span>Manajemen Publikasi &amp; Berita</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">Portal Ponpes Babul Jannah</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Publikasikan warta, agenda kegiatan, pengumuman santri, dan liputan pesantren untuk konsumsi publik.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Shortcut to Public News Page -->
                    <a href="../berita.php" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Buka Halaman Berita Publik">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat Portal Berita</span>
                    </a>

                    <!-- Add New News Button -->
                    <button type="button" onclick="openModalTambah()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tulis Berita Baru">
                        <i class="fa-solid fa-feather-pointed text-xs"></i>
                        <span>Tulis Berita Baru</span>
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
                
                <!-- Stat 1: Total Berita -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Artikel</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-gray-900"><?= number_format($total_berita) ?></span>
                            <span class="text-[10px] text-gray-500 font-semibold">Publikasi</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Berita Bulan Ini -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Terbit Bulan Ini</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-emerald-800"><?= number_format($berita_bulan_ini) ?></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">Artikel</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Terakhir Terbit -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Update Terakhir</span>
                        <div class="flex items-baseline space-x-1.5 truncate">
                            <?php if ($berita_terbaru): ?>
                                <span class="text-xs font-black text-gray-900 truncate" title="<?= htmlspecialchars($berita_terbaru['judul']) ?>">
                                    <?= date('d M Y', strtotime($berita_terbaru['created_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-semibold text-gray-400">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Media & Gambar -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-photo-film"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Media Ilustrasi</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-purple-800"><?= number_format($berita_dengan_gambar) ?></span>
                            <span class="text-[10px] text-purple-700 font-semibold">WebP Siap</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. SMART FILTER & SEARCH TOOLBAR (INSTANT LIVE FILTER) -->
            <!-- ======================================================== -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    
                    <!-- Search Input -->
                    <div class="relative flex-grow max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="search-berita" oninput="filterBerita()" placeholder="Cari judul berita, kata kunci konten, atau kutipan..."
                            class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/20 transition">
                        <button type="button" id="btn-clear-search" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Right Controls: Sort & View Mode Switcher -->
                    <div class="flex items-center space-x-3 flex-shrink-0">
                        
                        <!-- Sort Filter -->
                        <div class="flex items-center space-x-1.5 text-xs text-gray-600">
                            <label for="sort-berita" class="text-[11px] font-bold text-gray-500 hidden sm:inline">Urutkan:</label>
                            <select id="sort-berita" onchange="sortBeritaItems()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-brand-primary">
                                <option value="terbaru">Terbaru (ID Terbesar)</option>
                                <option value="terlama">Terlama (ID Terkecil)</option>
                                <option value="az">Judul (A - Z)</option>
                                <option value="za">Judul (Z - A)</option>
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

                <!-- Info Bar: Showing Count -->
                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-100">
                    <div>
                        Menampilkan <strong id="visible-count" class="text-gray-900"><?= $total_berita ?></strong> dari <strong class="text-gray-900"><?= $total_berita ?></strong> artikel publikasi
                    </div>
                    <div class="flex items-center space-x-2 text-[10px] text-gray-400">
                        <span><i class="fa-regular fa-clock mr-1"></i> Waktu baca terhitung otomatis</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4A. GRID VIEW: KARTU BERITA INTERAKTIF (DEFAULT VIEW) -->
            <!-- ======================================================== -->
            <div id="view-grid-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if ($total_berita === 0): ?>
                    <div class="col-span-full bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                        <i class="fa-solid fa-newspaper text-5xl mb-3 block opacity-30 text-brand-primary"></i>
                        <h4 class="text-sm font-bold text-gray-700">Belum Ada Berita Dipublikasikan</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Mulai publikasikan agenda kegiatan atau pengumuman pertama Anda dengan menekan tombol di bawah.</p>
                        <button type="button" onclick="openModalTambah()" class="mt-4 inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-4 rounded-xl text-xs shadow-xs transition">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tulis Berita Pertama</span>
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($berita_list as $b): 
                        $read_time = estimasi_baca($b['isi']);
                        $img_url = get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $b['gambar']), '', true);
                        $public_slug = create_slug($b['judul']);
                    ?>
                        <article class="berita-item-card bg-white rounded-2xl border border-gray-200/90 hover:border-brand-primary/40 shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between overflow-hidden group"
                            data-id="<?= $b['id'] ?>"
                            data-title="<?= htmlspecialchars(strtolower($b['judul'])) ?>"
                            data-excerpt="<?= htmlspecialchars(strtolower($b['kutipan'])) ?>"
                            data-date="<?= $b['created_at'] ?>">
                            
                            <div>
                                <!-- Image Container (16:9 Cover) -->
                                <div class="relative h-48 w-full bg-gray-100 overflow-hidden">
                                    <img src="<?= $img_url ?>" alt="<?= htmlspecialchars($b['judul']) ?>" 
                                         onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    
                                    <!-- Badges on Image -->
                                    <div class="absolute top-3 left-3 flex flex-wrap gap-1.5 z-10">
                                        <span class="inline-flex items-center text-[10px] font-bold bg-black/60 backdrop-blur-xs text-white px-2.5 py-1 rounded-lg shadow-xs">
                                            <i class="fa-regular fa-calendar mr-1 text-[9px] text-amber-300"></i>
                                            <?= format_tanggal($b['created_at']) ?>
                                        </span>
                                    </div>

                                    <div class="absolute top-3 right-3 z-10">
                                        <span class="inline-flex items-center text-[10px] font-bold bg-white/90 backdrop-blur-xs text-brand-primary px-2 py-0.5 rounded-lg shadow-xs border border-white/40">
                                            <i class="fa-regular fa-clock mr-1 text-[9px]"></i>
                                            <?= $read_time ?> mnt baca
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-4 sm:p-5 space-y-2">
                                    <!-- Title -->
                                    <h3 class="font-black text-gray-900 text-sm leading-snug line-clamp-2 group-hover:text-brand-primary transition-colors">
                                        <?= htmlspecialchars($b['judul']) ?>
                                    </h3>
                                    
                                    <!-- Excerpt -->
                                    <p class="text-xs text-gray-500 leading-relaxed line-clamp-3">
                                        <?= htmlspecialchars($b['kutipan']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Explicit Card Footer Action Bar (UI-UX Pro Max: No hidden buttons!) -->
                            <div class="p-4 pt-0 mt-3 border-t border-gray-100/90 pt-3 flex items-center justify-between gap-2 bg-gray-50/40 rounded-b-2xl">
                                
                                <!-- Public Link Button -->
                                <a href="../berita_detail.php?id=<?= $b['id'] ?>" target="_blank" class="inline-flex items-center space-x-1.5 text-xs font-bold text-gray-600 hover:text-brand-primary bg-white hover:bg-gray-100 border border-gray-200 px-2.5 py-1.5 rounded-xl shadow-2xs transition" title="Lihat Tampilan di Website">
                                    <i class="fa-solid fa-eye text-[11px] text-gray-400"></i>
                                    <span class="text-[11px]">Lihat</span>
                                </a>

                                <div class="flex items-center space-x-1.5">
                                    <!-- Edit Button (Opens Modal with Data Prefilled) -->
                                    <button type="button" 
                                            onclick="openModalEdit(this)"
                                            data-id="<?= $b['id'] ?>"
                                            data-judul="<?= htmlspecialchars($b['judul'], ENT_QUOTES) ?>"
                                            data-kutipan="<?= htmlspecialchars($b['kutipan'], ENT_QUOTES) ?>"
                                            data-isi="<?= htmlspecialchars($b['isi'], ENT_QUOTES) ?>"
                                            data-gambar="<?= htmlspecialchars($b['gambar'], ENT_QUOTES) ?>"
                                            data-gambar-url="<?= $img_url ?>"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-2.5 py-1.5 rounded-xl transition" title="Edit Artikel Berita">
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                        <span class="text-[11px]">Edit</span>
                                    </button>

                                    <!-- Delete Button (SweetAlert Confirmation) -->
                                    <button type="button"
                                            onclick="confirmDeleteBerita(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['judul'])) ?>')"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 px-2.5 py-1.5 rounded-xl transition" title="Hapus Artikel">
                                        <i class="fa-solid fa-trash text-[11px]"></i>
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
                                <th class="py-3 px-4 w-20">Cover</th>
                                <th class="py-3 px-4">Judul &amp; Kutipan</th>
                                <th class="py-3 px-4 w-32">Tanggal Terbit</th>
                                <th class="py-3 px-4 w-24 text-center">Baca</th>
                                <th class="py-3 px-4 w-36 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($total_berita === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-400">Belum ada berita dipublikasikan.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($berita_list as $b): 
                                    $img_url = get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $b['gambar']), '', true);
                                    $read_time = estimasi_baca($b['isi']);
                                ?>
                                    <tr class="berita-item-row hover:bg-gray-50/60 transition"
                                        data-id="<?= $b['id'] ?>"
                                        data-title="<?= htmlspecialchars(strtolower($b['judul'])) ?>"
                                        data-excerpt="<?= htmlspecialchars(strtolower($b['kutipan'])) ?>"
                                        data-date="<?= $b['created_at'] ?>">
                                        <td class="py-3.5 px-4 text-center font-bold text-gray-400"><?= $no++ ?></td>
                                        <td class="py-3.5 px-4">
                                            <img src="<?= $img_url ?>" alt="<?= htmlspecialchars($b['judul']) ?>" 
                                                 onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                                 class="w-12 h-12 rounded-xl object-cover border border-gray-200 bg-gray-100">
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <a href="../berita_detail.php?id=<?= $b['id'] ?>" target="_blank" class="font-extrabold text-gray-900 hover:text-brand-primary line-clamp-1 text-xs">
                                                <?= htmlspecialchars($b['judul']) ?>
                                            </a>
                                            <p class="text-[11px] text-gray-500 line-clamp-1 mt-0.5">
                                                <?= htmlspecialchars($b['kutipan']) ?>
                                            </p>
                                        </td>
                                        <td class="py-3.5 px-4 text-gray-600 font-semibold text-[11px] whitespace-nowrap">
                                            <i class="fa-regular fa-calendar mr-1 text-gray-400"></i>
                                            <?= format_tanggal($b['created_at']) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center text-gray-500 font-bold text-[11px] whitespace-nowrap">
                                            <?= $read_time ?> mnt
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center space-x-1.5">
                                                <a href="../berita_detail.php?id=<?= $b['id'] ?>" target="_blank" class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition" title="Lihat di Web">
                                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                                </a>
                                                <button type="button" 
                                                        onclick="openModalEdit(this)"
                                                        data-id="<?= $b['id'] ?>"
                                                        data-judul="<?= htmlspecialchars($b['judul'], ENT_QUOTES) ?>"
                                                        data-kutipan="<?= htmlspecialchars($b['kutipan'], ENT_QUOTES) ?>"
                                                        data-isi="<?= htmlspecialchars($b['isi'], ENT_QUOTES) ?>"
                                                        data-gambar="<?= htmlspecialchars($b['gambar'], ENT_QUOTES) ?>"
                                                        data-gambar-url="<?= $img_url ?>"
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Edit">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button type="button" 
                                                        onclick="confirmDeleteBerita(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['judul'])) ?>')"
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
            <div id="berita-empty-state" class="hidden bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-3 block opacity-30 text-gray-400"></i>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Berita yang Cocok</h4>
                <p class="text-xs text-gray-500 mt-1">Coba gunakan kata kunci pencarian yang lain.</p>
                <button type="button" onclick="clearSearch()" class="mt-3 inline-flex items-center space-x-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-1.5 px-3 rounded-xl text-xs transition">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset Pencarian</span>
                </button>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL TAMBAH & EDIT BERITA (UI-UX PRO MAX) -->
    <!-- ======================================================== -->
    <div id="modal-berita" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-4xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 sm:px-8 py-4.5 flex-shrink-0 bg-gray-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-primary text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-black text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Artikel Berita
                            <?php else: ?>
                                <i class="fa-solid fa-feather-pointed text-brand-primary mr-2"></i> Tulis Berita Baru
                            <?php endif; ?>
                        </h2>
                        <p class="text-[11px] text-gray-500">Isi formulir publikasi berita dengan lengkap dan rapi.</p>
                    </div>
                </div>

                <button type="button" onclick="closeModalBerita()" class="w-8 h-8 rounded-xl bg-gray-150 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Scrollable Body -->
            <div class="p-6 sm:p-8 overflow-y-auto custom-scrollbar flex-grow space-y-6">
                <form id="form-berita" action="berita.php" method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" id="form-action" value="<?= $edit_mode ? 'edit_berita' : 'add_berita' ?>">
                    <input type="hidden" name="id" id="form-id" value="<?= $edit_data['id'] ?>">

                    <!-- Field 1: Judul Berita -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Judul Berita <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="judul" id="form-judul" required placeholder="Contoh: Santri Babul Jannah Meraih Juara 1 MQK Tingkat Provinsi..."
                            value="<?= htmlspecialchars($edit_data['judul']) ?>"
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                    </div>

                    <!-- Field 2: Kutipan Singkat (Excerpt) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Kutipan Singkat (Excerpt) <span class="text-rose-500">*</span>
                            </label>
                            <span id="kutipan-char-count" class="text-[10px] text-gray-400 font-semibold">0 / 250 karakter</span>
                        </div>
                        <textarea name="kutipan" id="form-kutipan" required rows="2" maxlength="250" oninput="updateCharCounts()"
                            placeholder="Ringkasan 1-2 kalimat untuk kartu berita depan dan cuplikan SEO Google..."
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-none leading-relaxed"><?= htmlspecialchars($edit_data['kutipan']) ?></textarea>
                    </div>

                    <!-- Field 3: Isi Konten Berita with Formatting Toolbar & Live Preview -->
                    <div class="space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Isi Konten Berita <span class="text-rose-500">*</span>
                            </label>

                            <!-- Tab Tulis vs Pratinjau -->
                            <div class="flex items-center bg-gray-100 p-0.5 rounded-xl border border-gray-200 text-xs self-start">
                                <button type="button" id="btn-tab-edit" onclick="switchContentTab('edit')" class="px-3 py-1 rounded-lg font-bold transition bg-white text-brand-primary shadow-xs">
                                    <i class="fa-solid fa-pen-nib mr-1"></i> Tulis Konten
                                </button>
                                <button type="button" id="btn-tab-preview" onclick="switchContentTab('preview')" class="px-3 py-1 rounded-lg font-bold transition text-gray-500 hover:text-gray-700">
                                    <i class="fa-solid fa-eye mr-1"></i> Pratinjau Tampilan
                                </button>
                            </div>
                        </div>

                        <!-- TAB EDIT KONTEN -->
                        <div id="tab-edit-content" class="space-y-1.5">
                            
                            <!-- QUICK FORMATTING TOOLBAR -->
                            <div class="flex flex-wrap items-center gap-1 p-1.5 bg-gray-150 border border-gray-250 rounded-xl text-xs">
                                <button type="button" onclick="formatText('bold')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg font-black border border-gray-200 shadow-2xs" title="Teks Tebal (Bold)">
                                    <i class="fa-solid fa-bold text-xs"></i>
                                </button>
                                <button type="button" onclick="formatText('italic')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg italic border border-gray-200 shadow-2xs" title="Teks Miring (Italic)">
                                    <i class="fa-solid fa-italic text-xs"></i>
                                </button>
                                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                <button type="button" onclick="formatText('h2')" class="px-2 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg font-extrabold text-[11px] border border-gray-200 shadow-2xs" title="Subjudul Besar (H2)">
                                    H2
                                </button>
                                <button type="button" onclick="formatText('h3')" class="px-2 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg font-extrabold text-[11px] border border-gray-200 shadow-2xs" title="Subjudul Sedang (H3)">
                                    H3
                                </button>
                                <button type="button" onclick="formatText('p')" class="px-2 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg font-bold text-[11px] border border-gray-200 shadow-2xs" title="Paragraf Baru (P)">
                                    P
                                </button>
                                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                                <button type="button" onclick="formatText('quote')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg border border-gray-200 shadow-2xs" title="Kutipan (Quote)">
                                    <i class="fa-solid fa-quote-left text-xs"></i>
                                </button>
                                <button type="button" onclick="formatText('ul')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg border border-gray-200 shadow-2xs" title="Daftar Poin (Bullet List)">
                                    <i class="fa-solid fa-list-ul text-xs"></i>
                                </button>
                                <button type="button" onclick="formatText('ol')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg border border-gray-200 shadow-2xs" title="Daftar Angka (Numbered List)">
                                    <i class="fa-solid fa-list-ol text-xs"></i>
                                </button>
                                <button type="button" onclick="formatText('link')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg border border-gray-200 shadow-2xs" title="Sisipkan Link Tautan">
                                    <i class="fa-solid fa-link text-xs"></i>
                                </button>
                                <button type="button" onclick="formatText('hr')" class="px-2.5 py-1 bg-white hover:bg-gray-100 text-gray-800 rounded-lg border border-gray-200 shadow-2xs" title="Garis Pemisah (Divider)">
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <span class="ml-auto text-[10px] text-gray-400 font-medium hidden sm:inline px-1">
                                    <i class="fa-solid fa-wand-magic-sparkles text-amber-500 mr-1"></i> Klik untuk sisipkan format
                                </span>
                            </div>

                            <textarea name="isi" id="isi_konten" required rows="9"
                                placeholder="Ketik isi berita lengkap di sini. Anda dapat menggunakan tombol format di atas untuk membuat subjudul, teks tebal, daftar poin, dan kutipan..."
                                class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-3 text-xs leading-relaxed text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-y font-sans"><?= htmlspecialchars($edit_data['isi']) ?></textarea>
                        </div>

                        <!-- TAB PRATINJAU KONTEN -->
                        <div id="tab-preview-content" class="hidden">
                            <div class="border border-gray-200 rounded-2xl p-5 bg-white min-h-[220px] max-h-[350px] overflow-y-auto custom-scrollbar">
                                <div class="text-[10px] text-gray-400 uppercase font-extrabold tracking-wider border-b border-gray-100 pb-2 mb-3 flex items-center justify-between">
                                    <span><i class="fa-solid fa-eye text-brand-primary mr-1"></i> Simulasi Tampilan Artikel Publik</span>
                                    <span class="text-emerald-600 font-bold">Live Render</span>
                                </div>
                                <div id="preview-content-box" class="preview-prose text-xs text-gray-700 leading-relaxed">
                                    <!-- Rendered dynamically via JS -->
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Field 4: Upload Cover Image (Drag & Drop + Live Thumbnail Preview) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Gambar Ilustrasi / Foto Berita
                        </label>

                        <!-- Existing Image Section (in Edit Mode) -->
                        <div id="existing-image-box" class="<?= ($edit_mode && !empty($edit_data['gambar'])) ? '' : 'hidden' ?> bg-amber-50/50 p-4 rounded-2xl border border-amber-200/80 mb-3">
                            <div class="flex items-center space-x-3.5">
                                <div class="h-16 w-24 rounded-xl overflow-hidden bg-gray-100 border border-amber-300 flex-shrink-0 relative">
                                    <img id="existing-image-img" 
                                         src="<?= $edit_mode ? get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $edit_data['gambar']), '', true) : '' ?>" 
                                         class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <span class="inline-flex items-center text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md mb-1">
                                        <i class="fa-solid fa-image mr-1"></i> Foto Saat Ini
                                    </span>
                                    <p class="text-[11px] text-gray-600">Unggah foto baru di bawah ini hanya jika Anda ingin mengganti foto saat ini.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Drag & Drop Zone -->
                        <div id="dropzone-area" class="border-2 border-dashed border-gray-300 hover:border-brand-primary bg-gray-50/60 rounded-2xl p-5 text-center relative cursor-pointer hover:bg-gray-100/60 transition-all group">
                            <input type="file" name="gambar" id="input-gambar" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                   onchange="if(this.files[0]) handleImagePreview(this.files[0]);">
                            
                            <!-- Prompt state -->
                            <div id="image-dropzone-prompt" class="space-y-1.5">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-brand-primary/60 group-hover:text-brand-primary group-hover:scale-110 transition-all duration-200"></i>
                                <p class="text-xs text-gray-700 font-bold">Pilih atau seret gambar ke sini</p>
                                <p class="text-[10px] text-gray-400">Mendukung JPG, PNG, WEBP (Maksimal 5MB). Otomatis dioptimasi ke WebP modern.</p>
                            </div>

                            <!-- Live Selected Preview Thumbnail -->
                            <div id="image-preview-container" class="hidden flex items-center justify-between bg-white p-3 rounded-xl border border-gray-200 shadow-2xs z-20 relative">
                                <div class="flex items-center space-x-3">
                                    <img id="image-preview-img" src="" class="w-14 h-14 object-cover rounded-lg border border-gray-200 bg-gray-50 flex-shrink-0">
                                    <div class="text-left">
                                        <span id="image-preview-name" class="block text-xs font-bold text-gray-900 truncate max-w-[200px] sm:max-w-xs"></span>
                                        <span id="image-preview-size" class="block text-[10px] text-gray-400 font-semibold"></span>
                                        <span class="inline-block text-[10px] text-emerald-600 font-bold mt-0.5"><i class="fa-solid fa-circle-check mr-1"></i> Siap diupload</span>
                                    </div>
                                </div>
                                <button type="button" onclick="removeSelectedImage()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition" title="Batalkan Foto Baru">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModalBerita()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-berita" class="bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md hover:shadow-lg transition-all transform active:scale-98 flex items-center space-x-2">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span id="btn-submit-text"><?= $edit_mode ? 'Simpan Perubahan' : 'Publikasikan Berita' ?></span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <!-- ======================================================== -->
    <!-- JAVASCRIPT LOGIC (SEARCH, FILTER, MODAL, FORMATTER, SWEETALERT) -->
    <!-- ======================================================== -->
    <script>
        // Inisialisasi awal saat halaman siap
        document.addEventListener('DOMContentLoaded', function() {
            // Restore preferred view mode
            const savedView = localStorage.getItem('admin_berita_view_mode') || 'grid';
            setViewMode(savedView);
            
            // Inisialisasi character counts
            updateCharCounts();
            
            // Tutup modal jika user tekan tombol Esc
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('modal-berita');
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModalBerita();
                    }
                }
            });
        });

        // 1. Tampilan View Mode Switcher (Grid vs Table)
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
                localStorage.setItem('admin_berita_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                btnGrid.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnTable.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_berita_view_mode', 'grid');
            }
        }

        // 2. Client-side Live Search Filter
        function filterBerita() {
            const query = (document.getElementById('search-berita').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btn-clear-search');
            if (btnClear) {
                btnClear.classList.toggle('hidden', query.length === 0);
            }

            const cards = Array.from(document.querySelectorAll('.berita-item-card'));
            const rows = Array.from(document.querySelectorAll('.berita-item-row'));
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const excerpt = card.getAttribute('data-excerpt') || '';
                const match = !query || title.includes(query) || excerpt.includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const excerpt = row.getAttribute('data-excerpt') || '';
                const match = !query || title.includes(query) || excerpt.includes(query);
                row.style.display = match ? '' : 'none';
            });

            const countEl = document.getElementById('visible-count');
            if (countEl) countEl.textContent = visibleCount;

            const emptyEl = document.getElementById('berita-empty-state');
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-berita');
            if (searchInput) {
                searchInput.value = '';
                filterBerita();
                searchInput.focus();
            }
        }

        // 3. Client-side Sorting
        function sortBeritaItems() {
            const sortBy = document.getElementById('sort-berita').value;
            const gridContainer = document.getElementById('view-grid-container');
            const tableBody = document.querySelector('#view-table-container tbody');
            
            const cards = Array.from(document.querySelectorAll('.berita-item-card'));
            const rows = Array.from(document.querySelectorAll('.berita-item-row'));

            const sortFn = (a, b) => {
                if (sortBy === 'terbaru') {
                    return parseInt(b.getAttribute('data-id')) - parseInt(a.getAttribute('data-id'));
                } else if (sortBy === 'terlama') {
                    return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
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

        // 4. Modal Tambah & Edit Handling
        function openModalTambah() {
            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-feather-pointed text-brand-primary mr-2"></i> Tulis Berita Baru';
            document.getElementById('form-action').value = 'add_berita';
            document.getElementById('form-id').value = '';
            document.getElementById('form-judul').value = '';
            document.getElementById('form-kutipan').value = '';
            document.getElementById('isi_konten').value = '';
            document.getElementById('btn-submit-text').textContent = 'Publikasikan Berita';

            const existingImgBox = document.getElementById('existing-image-box');
            if (existingImgBox) existingImgBox.classList.add('hidden');

            removeSelectedImage();
            switchContentTab('edit');
            updateCharCounts();

            document.getElementById('modal-berita').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-judul').focus(), 150);
        }

        function openModalEdit(btn) {
            const id = btn.getAttribute('data-id');
            const judul = btn.getAttribute('data-judul') || '';
            const kutipan = btn.getAttribute('data-kutipan') || '';
            const isi = btn.getAttribute('data-isi') || '';
            const gambarUrl = btn.getAttribute('data-gambar-url') || '';

            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Artikel Berita';
            document.getElementById('form-action').value = 'edit_berita';
            document.getElementById('form-id').value = id;
            document.getElementById('form-judul').value = judul;
            document.getElementById('form-kutipan').value = kutipan;
            document.getElementById('isi_konten').value = isi;
            document.getElementById('btn-submit-text').textContent = 'Simpan Perubahan';

            const existingImgBox = document.getElementById('existing-image-box');
            const existingImgEl = document.getElementById('existing-image-img');
            if (existingImgBox && existingImgEl && gambarUrl) {
                existingImgEl.src = gambarUrl;
                existingImgBox.classList.remove('hidden');
            }

            removeSelectedImage();
            switchContentTab('edit');
            updateCharCounts();

            document.getElementById('modal-berita').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-judul').focus(), 150);
        }

        function closeModalBerita() {
            document.getElementById('modal-berita').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (window.location.search.includes('edit=')) {
                window.history.replaceState({}, document.title, 'berita.php');
            }
        }

        // 5. Quick Formatting Tools for Textarea
        function formatText(tagType) {
            const textarea = document.getElementById('isi_konten');
            if (!textarea) return;
            
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end);
            let replacement = '';

            switch (tagType) {
                case 'bold':
                    replacement = selected ? `<strong>${selected}</strong>` : `<strong>Teks Tebal</strong>`;
                    break;
                case 'italic':
                    replacement = selected ? `<em>${selected}</em>` : `<em>Teks Miring</em>`;
                    break;
                case 'h2':
                    replacement = selected ? `\n<h2>${selected}</h2>\n` : `\n<h2>Subjudul Utama</h2>\n`;
                    break;
                case 'h3':
                    replacement = selected ? `\n<h3>${selected}</h3>\n` : `\n<h3>Subjudul Artikel</h3>\n`;
                    break;
                case 'p':
                    replacement = selected ? `\n<p>${selected}</p>\n` : `\n<p>Ketik paragraf berita baru di sini...</p>\n`;
                    break;
                case 'quote':
                    replacement = selected ? `\n<blockquote>${selected}</blockquote>\n` : `\n<blockquote>"Kutipan atau arahan penting dari ustadz/pimpinan pondok."</blockquote>\n`;
                    break;
                case 'ul':
                    replacement = selected ? `\n<ul>\n  <li>${selected}</li>\n</ul>\n` : `\n<ul>\n  <li>Poin kegiatan pertama</li>\n  <li>Poin kegiatan kedua</li>\n  <li>Poin kegiatan ketiga</li>\n</ul>\n`;
                    break;
                case 'ol':
                    replacement = selected ? `\n<ol>\n  <li>${selected}</li>\n</ol>\n` : `\n<ol>\n  <li>Tahapan pertama</li>\n  <li>Tahapan kedua</li>\n</ol>\n`;
                    break;
                case 'link':
                    const url = prompt('Masukkan URL tautan:', 'https://');
                    if (url) {
                        replacement = selected ? `<a href="${url}" target="_blank">${selected}</a>` : `<a href="${url}" target="_blank">Klik tautan di sini</a>`;
                    } else {
                        return;
                    }
                    break;
                case 'hr':
                    replacement = `\n<hr>\n`;
                    break;
            }

            textarea.setRangeText(replacement, start, end, 'end');
            textarea.focus();
            updateLivePreview();
        }

        // 6. Switch Content Tab (Tulis vs Pratinjau)
        function switchContentTab(tab) {
            const editArea = document.getElementById('tab-edit-content');
            const previewArea = document.getElementById('tab-preview-content');
            const btnEdit = document.getElementById('btn-tab-edit');
            const btnPreview = document.getElementById('btn-tab-preview');

            if (tab === 'preview') {
                editArea.classList.add('hidden');
                previewArea.classList.remove('hidden');
                btnEdit.classList.remove('bg-white', 'text-brand-primary', 'shadow-xs');
                btnEdit.classList.add('text-gray-500');
                btnPreview.classList.add('bg-white', 'text-brand-primary', 'shadow-xs');
                btnPreview.classList.remove('text-gray-500');
                updateLivePreview();
            } else {
                editArea.classList.remove('hidden');
                previewArea.classList.add('hidden');
                btnPreview.classList.remove('bg-white', 'text-brand-primary', 'shadow-xs');
                btnPreview.classList.add('text-gray-500');
                btnEdit.classList.add('bg-white', 'text-brand-primary', 'shadow-xs');
                btnEdit.classList.remove('text-gray-500');
            }
        }

        function updateLivePreview() {
            const previewBox = document.getElementById('preview-content-box');
            const textarea = document.getElementById('isi_konten');
            if (!previewBox || !textarea) return;

            let content = textarea.value.trim();
            if (!content) {
                previewBox.innerHTML = '<p class="text-gray-400 italic text-center py-6">Belum ada konten untuk dipratinjau. Ketik isi berita di tab "Tulis Konten".</p>';
                return;
            }

            // Jika belum ada tag HTML sama sekali, wrap baris kosong dengan <p>
            if (!content.includes('<p>') && !content.includes('<div>') && !content.includes('<h3>') && !content.includes('<h2>')) {
                const paragraphs = content.split(/\n\s*\n/);
                content = paragraphs.map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`).join('');
            }
            previewBox.innerHTML = content;
        }

        // 7. Image Upload & Live Preview
        function handleImagePreview(file) {
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                alert('Silakan pilih berkas gambar yang valid (JPG, PNG, WEBP).');
                return;
            }
            const previewContainer = document.getElementById('image-preview-container');
            const previewImg = document.getElementById('image-preview-img');
            const previewName = document.getElementById('image-preview-name');
            const previewSize = document.getElementById('image-preview-size');
            const dropzonePrompt = document.getElementById('image-dropzone-prompt');

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

        function removeSelectedImage() {
            const fileInput = document.getElementById('input-gambar');
            if (fileInput) fileInput.value = '';
            const previewContainer = document.getElementById('image-preview-container');
            const dropzonePrompt = document.getElementById('image-dropzone-prompt');
            if (previewContainer && dropzonePrompt) {
                previewContainer.classList.add('hidden');
                dropzonePrompt.classList.remove('hidden');
            }
        }

        // 8. Character Counter
        function updateCharCounts() {
            const kutipan = document.getElementById('form-kutipan');
            const counter = document.getElementById('kutipan-char-count');
            if (kutipan && counter) {
                const len = kutipan.value.length;
                counter.textContent = `${len} / 250 karakter`;
                if (len >= 240) {
                    counter.classList.add('text-rose-600', 'font-black');
                    counter.classList.remove('text-gray-400');
                } else {
                    counter.classList.remove('text-rose-600', 'font-black');
                    counter.classList.add('text-gray-400');
                }
            }
        }

        // 9. Form Submit Spinner Protection
        function handleFormSubmit(form) {
            const btn = document.getElementById('btn-submit-berita');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Menyimpan...</span>';
            }
            return true;
        }

        // 10. SweetAlert Confirmation for Delete
        function confirmDeleteBerita(id, title) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Berita Ini?',
                    html: `Apakah Anda yakin ingin menghapus artikel:<br><strong class="text-rose-700">"${title}"</strong>?<br><br><span class="text-xs text-gray-500">Berkas gambar dan data artikel akan dihapus secara permanen dari server serta sitemap.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Hapus Berita',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `berita.php?action=delete&id=${id}`;
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus berita "${title}"?`)) {
                    window.location.href = `berita.php?action=delete&id=${id}`;
                }
            }
        }
    </script>

</body>
</html>