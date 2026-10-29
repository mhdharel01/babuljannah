<?php
// admin/ekskul.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';

// Proteksi akses admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'humas', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin mengelola ekstrakurikuler.");
}

$error = '';
$success = '';

// Ambil data untuk mode edit via parameter URL (Fallback)
$edit_mode = false;
$edit_data = [
    'id' => 0,
    'nama' => '',
    'deskripsi' => '',
    'ikon' => 'fa-solid fa-star',
    'urutan' => 0,
    'gambar' => ''
];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt_e = $db->prepare("SELECT * FROM ekskul WHERE id = :id");
        $stmt_e->execute(['id' => $edit_id]);
        $res_e = $stmt_e->fetch(PDO::FETCH_ASSOC);
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Ekskul
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_ekskul' || $_POST['action'] === 'edit_ekskul') {
        $nama = sanitize($_POST['nama'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $ikon = sanitize($_POST['ikon'] ?? 'fa-solid fa-star');
        $urutan = (int)($_POST['urutan'] ?? 0);
        
        if (!empty($nama) && !empty($deskripsi)) {
            $gambar_val = null;
            
            // Handle file upload (single image for ekskul)
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/ekskul/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $gambar_val = upload_and_convert_to_webp('gambar', $upload_dir, 'ekskul');
                if (!$gambar_val) {
                    $error = "File gambar tidak valid atau gagal diproses (harus JPG/PNG/WEBP & maksimal 5MB).";
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_ekskul') {
                        $stmt = $db->prepare("INSERT INTO ekskul (nama, deskripsi, ikon, gambar, urutan) VALUES (:nama, :deskripsi, :ikon, :gambar, :urutan)");
                        $stmt->execute([
                            'nama' => $nama,
                            'deskripsi' => $deskripsi,
                            'ikon' => $ikon,
                            'gambar' => $gambar_val,
                            'urutan' => $urutan
                        ]);
                        $success = "Ekstrakurikuler berhasil ditambahkan!";
                    } elseif ($_POST['action'] === 'edit_ekskul') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        
                        $old_img = $_POST['old_img'] ?? '';
                        $delete_img = isset($_POST['delete_img']) && $_POST['delete_img'] == '1';
                        
                        if ($gambar_val) {
                            // Gambar baru diupload, hapus gambar lama
                            if ($old_img) {
                                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                                    supabase_delete('ekskul/' . $old_img);
                                }
                                if (file_exists('../uploads/ekskul/' . $old_img)) {
                                    @unlink('../uploads/ekskul/' . $old_img);
                                }
                            }
                        } else {
                            if ($delete_img) {
                                // User minta hapus gambar lama tanpa ganti baru
                                if ($old_img) {
                                    if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                                        supabase_delete('ekskul/' . $old_img);
                                    }
                                    if (file_exists('../uploads/ekskul/' . $old_img)) {
                                        @unlink('../uploads/ekskul/' . $old_img);
                                    }
                                }
                                $gambar_val = null;
                            } else {
                                // Pakai gambar lama
                                $gambar_val = $old_img;
                            }
                        }
                        
                        $stmt = $db->prepare("UPDATE ekskul SET nama = :nama, deskripsi = :deskripsi, ikon = :ikon, gambar = :gambar, urutan = :urutan WHERE id = :id");
                        $stmt->execute([
                            'nama' => $nama,
                            'deskripsi' => $deskripsi,
                            'ikon' => $ikon,
                            'gambar' => $gambar_val,
                            'urutan' => $urutan,
                            'id' => $id_edit
                        ]);
                        
                        $success = "Data ekstrakurikuler berhasil diperbarui!";
                        $edit_mode = false;
                    }
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan data ekstrakurikuler: " . $e->getMessage();
                }
            }
        } else {
            $error = "Nama dan deskripsi ekstrakurikuler wajib diisi.";
        }
    }
}

// 2. Tangani Aksi Hapus Ekskul
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt_cek = $db->prepare("SELECT gambar FROM ekskul WHERE id = :id");
            $stmt_cek->execute(['id' => $id]);
            $res = $stmt_cek->fetch();
            if ($res && !empty($res['gambar'])) {
                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                    supabase_delete('ekskul/' . $res['gambar']);
                }
                $file = '../uploads/ekskul/' . $res['gambar'];
                if (file_exists($file)) @unlink($file);
            }
            
            $stmt = $db->prepare("DELETE FROM ekskul WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Ekstrakurikuler berhasil dihapus permanen.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus ekstrakurikuler: " . $e->getMessage();
        }
    }
}

// 3. Ambil semua data ekskul
$stmt_all = $db->query("SELECT * FROM ekskul ORDER BY urutan ASC, id ASC");
$ekskul_list = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// 4. Analisis Statistik & Metrik KPI Ekskul
$total_ekskul = count($ekskul_list);
$ekskul_dengan_gambar = 0;
$ekskul_tanpa_gambar = 0;
$ekskul_max_urutan = 0;

foreach ($ekskul_list as $e) {
    if (!empty($e['gambar'])) {
        $ekskul_dengan_gambar++;
    } else {
        $ekskul_tanpa_gambar++;
    }
    if ((int)$e['urutan'] > $ekskul_max_urutan) {
        $ekskul_max_urutan = (int)$e['urutan'];
    }
}

$page_title = 'Kelola Ekstrakurikuler - Admin Babul Jannah';
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
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
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
                        <i class="fa-solid fa-star"></i>
                        <span>Pengembangan Minat &amp; Bakat Santri</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">Portal Ponpes Babul Jannah</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola kegiatan ekstrakurikuler, olahraga sunnah, seni Islam, keterampilan dakwah, dan kepemimpinan santri.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Shortcut to Public Profile Ekskul Section -->
                    <a href="../profil.php#ekskul" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat di Halaman Profil Web">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat di Web</span>
                    </a>

                    <!-- Add New Ekskul Button -->
                    <button type="button" onclick="openModalTambah()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tambah Ekskul Baru">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Tambah Ekskul</span>
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
                
                <!-- Stat 1: Total Ekstrakurikuler -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Ekskul</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-gray-900"><?= number_format($total_ekskul) ?></span>
                            <span class="text-[10px] text-gray-500 font-semibold">Program</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Dokumentasi Visual -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Dokumentasi Foto</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-emerald-800"><?= number_format($ekskul_dengan_gambar) ?></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">Memiliki Foto</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Karakter & Minat Bakat -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Minat &amp; Bakat</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-blue-800">100%</span>
                            <span class="text-[10px] text-blue-700 font-semibold">Aktif Dibina</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Prioritas Urutan -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-arrow-down-1-9"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Urutan Terakhir</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-purple-800">#<?= $ekskul_max_urutan ?></span>
                            <span class="text-[10px] text-purple-700 font-semibold">Tersusun Rapi</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. SMART FILTER & SEARCH TOOLBAR (UI-UX PRO MAX) -->
            <!-- ======================================================== -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs space-y-3.5">
                
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    
                    <!-- Search Input -->
                    <div class="relative flex-grow max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="search-ekskul" oninput="filterEkskul()" placeholder="Cari nama kegiatan ekskul, panahan, silat, kaligrafi..."
                            class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/20 transition">
                        <button type="button" id="btn-clear-search" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Right Controls: Filter Foto, Sort & View Mode Switcher -->
                    <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
                        
                        <!-- Filter Foto Pill Buttons -->
                        <div class="flex items-center bg-gray-100 p-0.5 rounded-xl border border-gray-200/80 text-xs">
                            <button type="button" onclick="setPhotoFilter('all', this)" class="btn-foto-tab px-2.5 py-1 rounded-lg font-bold transition bg-brand-primary text-white shadow-xs">
                                Semua (<?= $total_ekskul ?>)
                            </button>
                            <button type="button" onclick="setPhotoFilter('with', this)" class="btn-foto-tab px-2.5 py-1 rounded-lg font-bold transition text-gray-600 hover:text-gray-900">
                                Berfoto (<?= $ekskul_dengan_gambar ?>)
                            </button>
                            <button type="button" onclick="setPhotoFilter('without', this)" class="btn-foto-tab px-2.5 py-1 rounded-lg font-bold transition text-gray-600 hover:text-gray-900">
                                Ikon Saja (<?= $ekskul_tanpa_gambar ?>)
                            </button>
                        </div>

                        <!-- Sort Filter -->
                        <div class="flex items-center space-x-1 text-xs text-gray-600">
                            <select id="sort-ekskul" onchange="sortEkskulItems()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-brand-primary">
                                <option value="urutan">Urutan (#ASC)</option>
                                <option value="az">Nama (A - Z)</option>
                                <option value="za">Nama (Z - A)</option>
                                <option value="terbaru">Terbaru Ditambahkan</option>
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
                        Menampilkan <strong id="visible-count" class="text-gray-900"><?= $total_ekskul ?></strong> dari <strong class="text-gray-900"><?= $total_ekskul ?></strong> program ekstrakurikuler
                    </div>
                    <div class="flex items-center space-x-2 text-[10px] text-gray-400">
                        <span><i class="fa-solid fa-circle-info mr-1"></i> Klik foto dokumentasi untuk melihat gambar resolusi penuh</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4A. GRID VIEW: KARTU EKSKUL INTERAKTIF (DEFAULT VIEW) -->
            <!-- ======================================================== -->
            <div id="view-grid-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <?php if ($total_ekskul === 0): ?>
                    <div class="col-span-full bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                        <i class="fa-solid fa-star text-5xl mb-3 block opacity-30 text-brand-primary"></i>
                        <h4 class="text-sm font-bold text-gray-700">Belum Ada Ekstrakurikuler Ditambahkan</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Mulai tambahkan kegiatan ekstrakurikuler pertama untuk santri.</p>
                        <button type="button" onclick="openModalTambah()" class="mt-4 inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-4 rounded-xl text-xs shadow-xs transition">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tambah Ekskul Pertama</span>
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($ekskul_list as $e): 
                        $has_img = !empty($e['gambar']);
                        $img_path = '';
                        if ($has_img) {
                            $img_path = (strpos($e['gambar'], 'olahraga.webp') !== false) 
                                ? '../assets/images/' . $e['gambar'] 
                                : get_storage_url('ekskul', $e['gambar'], '', true);
                        }
                    ?>
                        <article class="ekskul-item-card bg-white rounded-2xl border border-gray-200/90 hover:border-brand-primary/40 shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between overflow-hidden group"
                            data-id="<?= $e['id'] ?>"
                            data-urutan="<?= (int)$e['urutan'] ?>"
                            data-title="<?= htmlspecialchars(strtolower($e['nama'])) ?>"
                            data-desc="<?= htmlspecialchars(strtolower($e['deskripsi'])) ?>"
                            data-has-img="<?= $has_img ? '1' : '0' ?>">
                            
                            <div>
                                <!-- Image or Stylized Icon Banner -->
                                <?php if ($has_img): ?>
                                    <div class="h-44 w-full bg-gray-100 relative overflow-hidden cursor-pointer"
                                         onclick="openPhotoLightbox('<?= htmlspecialchars($img_path) ?>', '<?= htmlspecialchars(addslashes($e['nama'])) ?>')">
                                        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($e['nama']) ?>" 
                                             onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                                        
                                        <!-- Urutan Badge -->
                                        <div class="absolute top-3 right-3 z-10">
                                            <span class="inline-flex items-center text-[10px] font-bold bg-black/60 backdrop-blur-xs text-white px-2 py-0.5 rounded-lg shadow-xs">
                                                Urutan #<?= (int)$e['urutan'] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="h-36 bg-gradient-to-br from-brand-primary via-[#744E43] to-brand-deep relative overflow-hidden flex items-center justify-center" style="background: linear-gradient(135deg, #5C3E35 0%, #744E43 50%, #4A3029 100%) !important;">
                                        <div class="absolute -right-4 -bottom-4 text-white opacity-10 text-8xl font-black">
                                            <i class="<?= htmlspecialchars($e['ikon']) ?>"></i>
                                        </div>
                                        <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-xs border border-white/20 flex items-center justify-center text-white text-3xl shadow-md group-hover:scale-110 transition-transform duration-300">
                                            <i class="<?= htmlspecialchars($e['ikon']) ?>"></i>
                                        </div>
                                        
                                        <!-- Urutan Badge -->
                                        <div class="absolute top-3 right-3 z-10">
                                            <span class="inline-flex items-center text-[10px] font-bold bg-white/20 backdrop-blur-xs text-white px-2 py-0.5 rounded-lg border border-white/20">
                                                Urutan #<?= (int)$e['urutan'] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Content -->
                                <div class="p-4 sm:p-5 space-y-2 relative">
                                    <?php if ($has_img): ?>
                                        <!-- Floating Icon Badge Over Image -->
                                        <div class="w-10 h-10 -mt-8 rounded-xl bg-white border border-gray-200 text-brand-primary flex items-center justify-center text-base shadow-sm relative z-10 group-hover:bg-brand-primary group-hover:text-white transition-colors duration-300">
                                            <i class="<?= htmlspecialchars($e['ikon']) ?>"></i>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Title -->
                                    <h3 class="font-black text-gray-900 text-sm leading-snug line-clamp-1 group-hover:text-brand-primary transition-colors">
                                        <?= htmlspecialchars($e['nama']) ?>
                                    </h3>
                                    
                                    <!-- Description -->
                                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-3">
                                        <?= htmlspecialchars($e['deskripsi']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Card Footer Action Bar (UI-UX Pro Max: Explicit & Accessible) -->
                            <div class="p-3.5 sm:px-5 border-t border-gray-100 flex items-center justify-between gap-2 bg-gray-50/50 rounded-b-2xl">
                                
                                <!-- Status Indicator -->
                                <span class="inline-flex items-center text-[10px] font-bold <?= $has_img ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-gray-500 bg-gray-100' ?> px-2 py-0.5 rounded-md">
                                    <i class="<?= $has_img ? 'fa-solid fa-camera' : 'fa-regular fa-image' ?> mr-1 text-[9px]"></i>
                                    <?= $has_img ? 'Dokumentasi' : 'Ikon Saja' ?>
                                </span>

                                <div class="flex items-center space-x-1.5">
                                    <!-- Edit Button -->
                                    <button type="button" 
                                            onclick="openModalEdit(this)"
                                            data-id="<?= $e['id'] ?>"
                                            data-nama="<?= htmlspecialchars($e['nama'], ENT_QUOTES) ?>"
                                            data-deskripsi="<?= htmlspecialchars($e['deskripsi'], ENT_QUOTES) ?>"
                                            data-ikon="<?= htmlspecialchars($e['ikon'], ENT_QUOTES) ?>"
                                            data-urutan="<?= (int)$e['urutan'] ?>"
                                            data-gambar="<?= htmlspecialchars($e['gambar'] ?? '', ENT_QUOTES) ?>"
                                            data-gambar-url="<?= htmlspecialchars($img_path) ?>"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-2.5 py-1.5 rounded-xl transition" title="Edit Ekstrakurikuler">
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                        <span class="text-[11px]">Edit</span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button"
                                            onclick="confirmDeleteEkskul(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nama'])) ?>')"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 px-2.5 py-1.5 rounded-xl transition" title="Hapus Ekstrakurikuler">
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
                                <th class="py-3 px-4 w-12 text-center">Urutan</th>
                                <th class="py-3 px-4 w-12 text-center">Ikon</th>
                                <th class="py-3 px-4">Nama Program &amp; Deskripsi</th>
                                <th class="py-3 px-4 w-32 text-center">Foto Dokumentasi</th>
                                <th class="py-3 px-4 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($total_ekskul === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-400">Belum ada ekstrakurikuler ditambahkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ekskul_list as $e): 
                                    $has_img = !empty($e['gambar']);
                                    $img_path = '';
                                    if ($has_img) {
                                        $img_path = (strpos($e['gambar'], 'olahraga.webp') !== false) 
                                            ? '../assets/images/' . $e['gambar'] 
                                            : get_storage_url('ekskul', $e['gambar'], '', true);
                                    }
                                ?>
                                    <tr class="ekskul-item-row hover:bg-gray-50/60 transition"
                                        data-id="<?= $e['id'] ?>"
                                        data-urutan="<?= (int)$e['urutan'] ?>"
                                        data-title="<?= htmlspecialchars(strtolower($e['nama'])) ?>"
                                        data-desc="<?= htmlspecialchars(strtolower($e['deskripsi'])) ?>"
                                        data-has-img="<?= $has_img ? '1' : '0' ?>">
                                        <td class="py-3.5 px-4 text-center font-black text-gray-500">
                                            #<?= (int)$e['urutan'] ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="w-8 h-8 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-sm mx-auto">
                                                <i class="<?= htmlspecialchars($e['ikon']) ?>"></i>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="font-extrabold text-gray-900 line-clamp-1 text-xs">
                                                <?= htmlspecialchars($e['nama']) ?>
                                            </span>
                                            <p class="text-[11px] text-gray-500 line-clamp-1 mt-0.5">
                                                <?= htmlspecialchars($e['deskripsi']) ?>
                                            </p>
                                        </td>
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <?php if ($has_img): ?>
                                                <div class="inline-flex items-center space-x-1.5 cursor-pointer" onclick="openPhotoLightbox('<?= htmlspecialchars($img_path) ?>', '<?= htmlspecialchars(addslashes($e['nama'])) ?>')">
                                                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-8 h-8 rounded-lg object-cover border border-gray-200 bg-gray-100">
                                                    <span class="text-[11px] font-bold text-emerald-700">Ada Foto</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-[11px] italic">Ikon Only</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center space-x-1.5">
                                                <button type="button" 
                                                        onclick="openModalEdit(this)"
                                                        data-id="<?= $e['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($e['nama'], ENT_QUOTES) ?>"
                                                        data-deskripsi="<?= htmlspecialchars($e['deskripsi'], ENT_QUOTES) ?>"
                                                        data-ikon="<?= htmlspecialchars($e['ikon'], ENT_QUOTES) ?>"
                                                        data-urutan="<?= (int)$e['urutan'] ?>"
                                                        data-gambar="<?= htmlspecialchars($e['gambar'] ?? '', ENT_QUOTES) ?>"
                                                        data-gambar-url="<?= htmlspecialchars($img_path) ?>"
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Edit">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button type="button" 
                                                        onclick="confirmDeleteEkskul(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['nama'])) ?>')"
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
            <div id="ekskul-empty-state" class="hidden bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-3 block opacity-30 text-gray-400"></i>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Ekstrakurikuler yang Cocok</h4>
                <p class="text-xs text-gray-500 mt-1">Coba gunakan kata kunci pencarian yang lain.</p>
                <button type="button" onclick="clearSearch()" class="mt-3 inline-flex items-center space-x-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-1.5 px-3 rounded-xl text-xs transition">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset Pencarian</span>
                </button>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL TAMBAH & EDIT EKSKUL (UI-UX PRO MAX) -->
    <!-- ======================================================== -->
    <div id="modal-ekskul" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-2xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 sm:px-8 py-4.5 flex-shrink-0 bg-gray-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-primary text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-black text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Ekstrakurikuler
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Ekskul Baru
                            <?php endif; ?>
                        </h2>
                        <p class="text-[11px] text-gray-500">Lengkapi nama kegiatan, deskripsi, ikon, dan foto dokumentasi.</p>
                    </div>
                </div>

                <button type="button" onclick="closeModalEkskul()" class="w-8 h-8 rounded-xl bg-gray-150 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Scrollable Body -->
            <div class="p-6 sm:p-8 overflow-y-auto custom-scrollbar flex-grow space-y-5">
                <form id="form-ekskul" action="ekskul.php" method="POST" enctype="multipart/form-data" class="space-y-5" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" id="form-action" value="<?= $edit_mode ? 'edit_ekskul' : 'add_ekskul' ?>">
                    <input type="hidden" name="id" id="form-id" value="<?= $edit_data['id'] ?>">
                    <input type="hidden" name="old_img" id="form-old-img" value="<?= htmlspecialchars($edit_data['gambar'] ?? '') ?>">

                    <!-- Field 1: Nama Ekskul -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Nama Kegiatan Ekstrakurikuler <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama" id="form-nama" required placeholder="Contoh: Panahan Sunnah, Seni Kaligrafi, Pencak Silat, Pramuka..."
                            value="<?= htmlspecialchars($edit_data['nama']) ?>"
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                    </div>

                    <!-- Field 2: Deskripsi Lengkap -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Deskripsi Kegiatan &amp; Manfaat <span class="text-rose-500">*</span>
                            </label>
                            <span id="desc-char-count" class="text-[10px] text-gray-400 font-semibold">0 karakter</span>
                        </div>
                        <textarea name="deskripsi" id="form-deskripsi" required rows="3" oninput="updateDescCharCount()"
                            placeholder="Jelaskan aktivitas, jadwal latihan, pembinaan karakter, atau manfaat kegiatan ini bagi santri..."
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-none leading-relaxed"><?= htmlspecialchars($edit_data['deskripsi']) ?></textarea>
                    </div>

                    <!-- Field 3: Ikon Picker & Urutan Tampil (Rock Solid Responsive Layout) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                        
                        <!-- Ikon Picker with Islamic/Pesantren Presets (Takes 2 of 3 Columns) -->
                        <div class="sm:col-span-2 space-y-2">
                            <label for="form-ikon" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Ikon FontAwesome (Solid) <span class="text-rose-500">*</span>
                            </label>
                            
                            <div class="flex items-center space-x-2.5">
                                <div class="w-11 h-11 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-xl flex-shrink-0 border border-brand-primary/20 shadow-2xs">
                                    <i id="preview-icon" class="<?= htmlspecialchars($edit_data['ikon'] ?: 'fa-solid fa-star') ?>"></i>
                                </div>
                                <div class="relative flex-grow">
                                    <input type="text" name="ikon" id="form-ikon" value="<?= htmlspecialchars($edit_data['ikon'] ?: 'fa-solid fa-star') ?>" oninput="updateIconPreview(this.value)"
                                        placeholder="fa-solid fa-star"
                                        class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3.5 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-mono font-bold">
                                </div>
                            </div>

                            <!-- Preset Icon Buttons for Ekskul (Horizontally Wrapping Pills) -->
                            <div class="pt-1">
                                <span class="text-[10px] text-gray-500 font-bold block mb-1.5 flex items-center">
                                    <i class="fa-solid fa-wand-magic-sparkles text-brand-primary mr-1 text-[9px]"></i> Preset Ikon Populer Pesantren:
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-bullseye')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Panahan">
                                        <i class="fa-solid fa-bullseye mr-1.5 text-red-500"></i> Panahan
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-horse')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Berkuda">
                                        <i class="fa-solid fa-horse mr-1.5 text-amber-600"></i> Berkuda
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-user-shield')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Pencak Silat">
                                        <i class="fa-solid fa-user-shield mr-1.5 text-emerald-600"></i> Silat
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-paintbrush')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Kaligrafi">
                                        <i class="fa-solid fa-paintbrush mr-1.5 text-purple-600"></i> Kaligrafi
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-bullhorn')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Pidato 3 Bahasa">
                                        <i class="fa-solid fa-bullhorn mr-1.5 text-blue-600"></i> Pidato
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-campground')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Pramuka">
                                        <i class="fa-solid fa-campground mr-1.5 text-amber-700"></i> Pramuka
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-futbol')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Futsal / Bola">
                                        <i class="fa-solid fa-futbol mr-1.5 text-indigo-600"></i> Futsal
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-book-quran')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Tahfidz / Tilawah">
                                        <i class="fa-solid fa-book-quran mr-1.5 text-emerald-700"></i> Tilawah
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Urutan Tampil (Takes 1 of 3 Columns) -->
                        <div class="sm:col-span-1 space-y-2">
                            <label for="form-urutan" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Urutan Tampil <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" name="urutan" id="form-urutan" value="<?= (int)$edit_data['urutan'] ?>" min="0" max="999"
                                class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                            <span class="text-[10px] text-gray-400 block leading-tight">Angka kecil tampil paling awal di web (1, 2, 3..).</span>
                        </div>

                    </div>

                    <!-- Field 4: Foto Dokumentasi Ekskul -->
                    <div class="space-y-3 bg-gray-50/80 p-4 sm:p-5 rounded-2xl border border-gray-200/80">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider">
                                <i class="fa-solid fa-camera text-brand-primary mr-1"></i> Foto Dokumentasi Kegiatan (Opsional)
                            </label>
                            <span class="text-[10px] text-gray-400 font-semibold">WebP / JPG / PNG (Maks 5MB)</span>
                        </div>

                        <!-- Existing Photo Preview (in Edit Mode) -->
                        <div id="existing-photo-box" class="<?= ($edit_mode && !empty($edit_data['gambar'])) ? '' : 'hidden' ?> p-3.5 bg-white rounded-xl border border-gray-200 shadow-2xs">
                            <div class="flex items-center space-x-3.5">
                                <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 flex-shrink-0 relative">
                                    <?php 
                                    $cur_img_path = '';
                                    if ($edit_mode && !empty($edit_data['gambar'])) {
                                        $cur_img_path = (strpos($edit_data['gambar'], 'olahraga.webp') !== false) 
                                            ? '../assets/images/' . $edit_data['gambar'] 
                                            : get_storage_url('ekskul', $edit_data['gambar'], '', true);
                                    }
                                    ?>
                                    <img id="existing-photo-img" src="<?= htmlspecialchars($cur_img_path) ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="space-y-1.5 min-w-0">
                                    <span class="inline-flex items-center text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                                        <i class="fa-solid fa-image mr-1"></i> Foto Saat Ini
                                    </span>
                                    <p class="text-[11px] text-gray-500">Unggah foto baru di bawah untuk mengganti, atau centang tombol di bawah untuk menghapus.</p>
                                    
                                    <label class="inline-flex items-center space-x-2 cursor-pointer text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 px-2.5 py-1 rounded-lg border border-rose-200/60 transition">
                                        <input type="checkbox" name="delete_img" id="form-delete-img" value="1" onchange="toggleDeleteExistingPhoto(this)">
                                        <span>Hapus Foto Ini</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Drag & Drop Upload Zone -->
                        <div id="ekskul-dropzone" class="border-2 border-dashed border-gray-300 hover:border-brand-primary bg-white rounded-2xl p-5 text-center relative cursor-pointer hover:bg-gray-50/80 transition-all group">
                            <input type="file" name="gambar" id="input-gambar-ekskul" accept="image/*" 
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                   onchange="handleNewPhotoSelected(this.files)">
                            
                            <div id="dropzone-prompt" class="space-y-1.5">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-brand-primary/60 group-hover:text-brand-primary group-hover:scale-110 transition-all duration-200"></i>
                                <p class="text-xs text-gray-700 font-bold">Pilih atau seret foto kegiatan ke sini</p>
                                <p class="text-[10px] text-gray-400">Otomatis dioptimasi dan dikonversi ke WebP untuk performa cepat.</p>
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
                        <button type="button" onclick="closeModalEkskul()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-ekskul" class="bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md hover:shadow-lg transition-all transform active:scale-98 flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span id="btn-submit-text"><?= $edit_mode ? 'Simpan Perubahan' : 'Simpan Ekskul' ?></span>
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
    <!-- JAVASCRIPT LOGIC (SEARCH, FILTER, MODAL, PREVIEW) -->
    <!-- ======================================================== -->
    <script>
        let currentPhotoFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            // Restore saved view mode
            const savedView = localStorage.getItem('admin_ekskul_view_mode') || 'grid';
            setViewMode(savedView);
            
            // Inisialisasi char count
            updateDescCharCount();

            // Keyboard shortcut Esc
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const lightbox = document.getElementById('lightbox-modal');
                    if (lightbox && !lightbox.classList.contains('hidden')) {
                        closePhotoLightbox();
                        return;
                    }
                    const modal = document.getElementById('modal-ekskul');
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModalEkskul();
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
                localStorage.setItem('admin_ekskul_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                btnGrid.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnTable.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_ekskul_view_mode', 'grid');
            }
        }

        // 2. Photo Filter Tab (all, with, without)
        function setPhotoFilter(filterType, btn) {
            currentPhotoFilter = filterType;

            document.querySelectorAll('.btn-foto-tab').forEach(b => {
                b.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                b.classList.add('text-gray-600', 'hover:text-gray-900');
            });
            if (btn) {
                btn.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btn.classList.remove('text-gray-600', 'hover:text-gray-900');
            }

            filterEkskul();
        }

        // 3. Search & Filter Handling
        function filterEkskul() {
            const query = (document.getElementById('search-ekskul').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btn-clear-search');
            if (btnClear) {
                btnClear.classList.toggle('hidden', query.length === 0);
            }

            const cards = Array.from(document.querySelectorAll('.ekskul-item-card'));
            const rows = Array.from(document.querySelectorAll('.ekskul-item-row'));
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const desc = card.getAttribute('data-desc') || '';
                const hasImg = card.getAttribute('data-has-img') === '1';

                const matchesQuery = !query || title.includes(query) || desc.includes(query);
                let matchesPhoto = true;
                if (currentPhotoFilter === 'with') matchesPhoto = hasImg;
                if (currentPhotoFilter === 'without') matchesPhoto = !hasImg;

                const isVisible = matchesQuery && matchesPhoto;
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const desc = row.getAttribute('data-desc') || '';
                const hasImg = row.getAttribute('data-has-img') === '1';

                const matchesQuery = !query || title.includes(query) || desc.includes(query);
                let matchesPhoto = true;
                if (currentPhotoFilter === 'with') matchesPhoto = hasImg;
                if (currentPhotoFilter === 'without') matchesPhoto = !hasImg;

                row.style.display = (matchesQuery && matchesPhoto) ? '' : 'none';
            });

            const countEl = document.getElementById('visible-count');
            if (countEl) countEl.textContent = visibleCount;

            const emptyEl = document.getElementById('ekskul-empty-state');
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-ekskul');
            if (searchInput) {
                searchInput.value = '';
                filterEkskul();
                searchInput.focus();
            }
        }

        // 4. Sorting Function
        function sortEkskulItems() {
            const sortBy = document.getElementById('sort-ekskul').value;
            const gridContainer = document.getElementById('view-grid-container');
            const tableBody = document.querySelector('#view-table-container tbody');

            const cards = Array.from(document.querySelectorAll('.ekskul-item-card'));
            const rows = Array.from(document.querySelectorAll('.ekskul-item-row'));

            const sortFn = (a, b) => {
                if (sortBy === 'urutan') {
                    return parseInt(a.getAttribute('data-urutan') || 0) - parseInt(b.getAttribute('data-urutan') || 0);
                } else if (sortBy === 'az') {
                    return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
                } else if (sortBy === 'za') {
                    return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
                } else if (sortBy === 'terbaru') {
                    return parseInt(b.getAttribute('data-id') || 0) - parseInt(a.getAttribute('data-id') || 0);
                }
                return 0;
            };

            cards.sort(sortFn).forEach(el => gridContainer.appendChild(el));
            rows.sort(sortFn).forEach(el => tableBody.appendChild(el));
        }

        // 5. Modal Tambah & Edit Handling
        function openModalTambah() {
            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Ekskul Baru';
            document.getElementById('form-action').value = 'add_ekskul';
            document.getElementById('form-id').value = '';
            document.getElementById('form-old-img').value = '';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-deskripsi').value = '';
            document.getElementById('form-ikon').value = 'fa-solid fa-star';
            document.getElementById('form-urutan').value = '0';
            document.getElementById('btn-submit-text').textContent = 'Simpan Ekskul';

            updateIconPreview('fa-solid fa-star');
            updateDescCharCount();

            // Hide existing photo box
            const existingBox = document.getElementById('existing-photo-box');
            if (existingBox) existingBox.classList.add('hidden');
            const delCheckbox = document.getElementById('form-delete-img');
            if (delCheckbox) delCheckbox.checked = false;

            removeSelectedNewPhoto();

            document.getElementById('modal-ekskul').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function openModalEdit(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama') || '';
            const deskripsi = btn.getAttribute('data-deskripsi') || '';
            const ikon = btn.getAttribute('data-ikon') || 'fa-solid fa-star';
            const urutan = btn.getAttribute('data-urutan') || '0';
            const gambar = btn.getAttribute('data-gambar') || '';
            const gambarUrl = btn.getAttribute('data-gambar-url') || '';

            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Ekstrakurikuler';
            document.getElementById('form-action').value = 'edit_ekskul';
            document.getElementById('form-id').value = id;
            document.getElementById('form-old-img').value = gambar;
            document.getElementById('form-nama').value = nama;
            document.getElementById('form-deskripsi').value = deskripsi;
            document.getElementById('form-ikon').value = ikon;
            document.getElementById('form-urutan').value = urutan;
            document.getElementById('btn-submit-text').textContent = 'Simpan Perubahan';

            updateIconPreview(ikon);
            updateDescCharCount();

            // Existing photo box handling
            const existingBox = document.getElementById('existing-photo-box');
            const existingImg = document.getElementById('existing-photo-img');
            const delCheckbox = document.getElementById('form-delete-img');

            if (delCheckbox) delCheckbox.checked = false;

            if (existingBox && existingImg && gambarUrl) {
                existingImg.src = gambarUrl;
                existingBox.classList.remove('hidden', 'opacity-40', 'grayscale');
            } else if (existingBox) {
                existingBox.classList.add('hidden');
            }

            removeSelectedNewPhoto();

            document.getElementById('modal-ekskul').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function closeModalEkskul() {
            document.getElementById('modal-ekskul').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (window.location.search.includes('edit=')) {
                window.history.replaceState({}, document.title, 'ekskul.php');
            }
        }

        // 6. Icon Picker Logic
        function updateIconPreview(iconClass) {
            const preview = document.getElementById('preview-icon');
            if (preview) {
                preview.className = iconClass || 'fa-solid fa-star';
            }
        }

        function selectPresetIcon(iconClass) {
            const input = document.getElementById('form-ikon');
            if (input) {
                input.value = iconClass;
                updateIconPreview(iconClass);
            }
        }

        function updateDescCharCount() {
            const textarea = document.getElementById('form-deskripsi');
            const counter = document.getElementById('desc-char-count');
            if (textarea && counter) {
                counter.textContent = `${textarea.value.length} karakter`;
            }
        }

        // 7. Single Photo Upload & Preview Handling
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
            const fileInput = document.getElementById('input-gambar-ekskul');
            if (fileInput) fileInput.value = '';
            const previewContainer = document.getElementById('new-photo-preview');
            const dropzonePrompt = document.getElementById('dropzone-prompt');
            if (previewContainer && dropzonePrompt) {
                previewContainer.classList.add('hidden');
                dropzonePrompt.classList.remove('hidden');
            }
        }

        function toggleDeleteExistingPhoto(checkbox) {
            const box = document.getElementById('existing-photo-box');
            if (!box) return;
            if (checkbox.checked) {
                box.classList.add('opacity-40', 'grayscale');
            } else {
                box.classList.remove('opacity-40', 'grayscale');
            }
        }

        // 8. Lightbox Modal Viewer
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

        // 9. Form Submit Spinner Protection
        function handleFormSubmit(form) {
            const btn = document.getElementById('btn-submit-ekskul');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Menyimpan...</span>';
            }
            return true;
        }

        // 10. SweetAlert2 Confirmation for Deleting Ekskul
        function confirmDeleteEkskul(id, nama) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Ekstrakurikuler?',
                    html: `Apakah Anda yakin ingin menghapus kegiatan:<br><strong class="text-rose-700">"${nama}"</strong>?<br><br><span class="text-xs text-gray-500">Berkas foto dan seluruh data ekstrakurikuler akan dihapus secara permanen.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Hapus Ekskul',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `ekskul.php?action=delete&id=${id}`;
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus ekstrakurikuler "${nama}"?`)) {
                    window.location.href = `ekskul.php?action=delete&id=${id}`;
                }
            }
        }
    </script>

</body>
</html>
