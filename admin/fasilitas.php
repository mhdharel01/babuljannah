<?php
// admin/fasilitas.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';

// Proteksi akses admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Ambil data untuk mode edit via parameter URL (Fallback)
$edit_mode = false;
$edit_data = [
    'id' => 0,
    'nama' => '',
    'deskripsi' => '',
    'kategori' => 'belajar',
    'ikon' => 'fa-solid fa-building',
    'urutan' => 0,
    'gambar' => ''
];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt_e = $db->prepare("SELECT * FROM fasilitas WHERE id = :id");
        $stmt_e->execute(['id' => $edit_id]);
        $res_e = $stmt_e->fetch(PDO::FETCH_ASSOC);
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Fasilitas
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_fasilitas' || $_POST['action'] === 'edit_fasilitas') {
        $nama = sanitize($_POST['nama'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $kategori = sanitize($_POST['kategori'] ?? 'umum');
        $ikon = sanitize($_POST['ikon'] ?? 'fa-solid fa-building');
        $urutan = (int)($_POST['urutan'] ?? 0);
        
        if (!empty($nama) && !empty($deskripsi)) {
            $uploaded_images = [];
            
            // Handle file upload (bisa single atau array multiple)
            if (isset($_FILES['gambar'])) {
                $upload_dir = '../uploads/fasilitas/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                
                // Normalisasi struktur $_FILES['gambar']
                $file_names = is_array($_FILES['gambar']['name']) ? $_FILES['gambar']['name'] : [$_FILES['gambar']['name']];
                $file_tmps = is_array($_FILES['gambar']['tmp_name']) ? $_FILES['gambar']['tmp_name'] : [$_FILES['gambar']['tmp_name']];
                $file_errors = is_array($_FILES['gambar']['error']) ? $_FILES['gambar']['error'] : [$_FILES['gambar']['error']];
                $file_sizes = is_array($_FILES['gambar']['size']) ? $_FILES['gambar']['size'] : [$_FILES['gambar']['size']];
                
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                foreach ($file_names as $i => $fname) {
                    if ($file_errors[$i] === UPLOAD_ERR_OK && !empty($fname)) {
                        $file_ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (in_array($file_ext, $allowed) && $file_sizes[$i] <= 5 * 1024 * 1024) {
                            $new_name = 'fasilitas_' . time() . '_' . $i . '_' . rand(100, 999) . '.webp';
                            $dest_path = rtrim($upload_dir, '/') . '/' . $new_name;
                            
                            $image = null;
                            if ($file_ext === 'png') {
                                $image = @imagecreatefrompng($file_tmps[$i]);
                                if ($image) {
                                    imagepalettetotruecolor($image);
                                    imagealphablending($image, true);
                                    imagesavealpha($image, true);
                                }
                            } elseif ($file_ext === 'webp') {
                                if (move_uploaded_file($file_tmps[$i], $dest_path)) {
                                    if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_upload')) {
                                        if (supabase_upload($dest_path, 'fasilitas/' . $new_name, 'image/webp')) {
                                            @unlink($dest_path);
                                        }
                                    }
                                    $uploaded_images[] = $new_name;
                                }
                                continue;
                            } else {
                                $image = @imagecreatefromjpeg($file_tmps[$i]);
                            }

                            if ($image) {
                                // Auto-resize jika gambar terlalu besar (maksimal lebar 1600px)
                                $orig_w = imagesx($image);
                                $orig_h = imagesy($image);
                                $max_width = 1600;
                                if ($orig_w > $max_width) {
                                    $new_w = $max_width;
                                    $new_h = (int)($orig_h * ($new_w / $orig_w));
                                    $resized = imagecreatetruecolor($new_w, $new_h);
                                    imagealphablending($resized, false);
                                    imagesavealpha($resized, true);
                                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
                                    imagedestroy($image);
                                    $image = $resized;
                                }

                                if (imagewebp($image, $dest_path, 82)) {
                                    imagedestroy($image);
                                    if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_upload')) {
                                        if (supabase_upload($dest_path, 'fasilitas/' . $new_name, 'image/webp')) {
                                            @unlink($dest_path);
                                        }
                                    }
                                    $uploaded_images[] = $new_name;
                                } else {
                                    imagedestroy($image);
                                    $error = "Gagal memproses salah satu gambar.";
                                    break;
                                }
                            } else {
                                $error = "Gagal memproses salah satu gambar.";
                                break;
                            }
                        } else {
                            $error = "Salah satu berkas tidak valid (harus JPG/PNG/WEBP & maksimal 5MB).";
                            break;
                        }
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_fasilitas') {
                        $gambar_val = !empty($uploaded_images) ? json_encode($uploaded_images) : null;
                        $stmt = $db->prepare("INSERT INTO fasilitas (nama, deskripsi, kategori, ikon, gambar, urutan) VALUES (:nama, :deskripsi, :kategori, :ikon, :gambar, :urutan)");
                        $stmt->execute([
                            'nama' => $nama,
                            'deskripsi' => $deskripsi,
                            'kategori' => $kategori,
                            'ikon' => $ikon,
                            'gambar' => $gambar_val,
                            'urutan' => $urutan
                        ]);
                        $success = "Data sarana & fasilitas berhasil ditambahkan!";
                    } elseif ($_POST['action'] === 'edit_fasilitas') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        
                        // Ambil daftar foto lama dari input form
                        $old_imgs = $_POST['old_imgs'] ?? [];
                        if (!is_array($old_imgs)) $old_imgs = [$old_imgs];
                        
                        // Ambil foto yang dihapus
                        $delete_imgs = $_POST['delete_imgs'] ?? [];
                        if (!is_array($delete_imgs)) $delete_imgs = [$delete_imgs];
                        
                        // Filter foto lama (buang yang dihapus)
                        $remaining_old = [];
                        foreach ($old_imgs as $oimg) {
                            if (!in_array($oimg, $delete_imgs) && !empty($oimg)) {
                                $remaining_old[] = $oimg;
                            } else if (!empty($oimg)) {
                                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                                    supabase_delete('fasilitas/' . $oimg);
                                }
                                $path_del = '../uploads/fasilitas/' . $oimg;
                                if (file_exists($path_del)) { @unlink($path_del); }
                            }
                        }
                        
                        // Gabungkan foto lama yang tersisa dengan foto baru yang diupload
                        $final_images = array_merge($remaining_old, $uploaded_images);
                        
                        // Atur Cover (Foto Utama) ke index 0
                        $cover_img = $_POST['cover_img'] ?? '';
                        if (!empty($cover_img) && in_array($cover_img, $final_images)) {
                            $final_images = array_diff($final_images, [$cover_img]);
                            array_unshift($final_images, $cover_img);
                        }
                        
                        $final_images = array_values($final_images);
                        $gambar_val = !empty($final_images) ? json_encode($final_images) : null;
                        
                        $stmt = $db->prepare("UPDATE fasilitas SET nama = :nama, deskripsi = :deskripsi, kategori = :kategori, ikon = :ikon, gambar = :gambar, urutan = :urutan WHERE id = :id");
                        $stmt->execute([
                            'nama' => $nama,
                            'deskripsi' => $deskripsi,
                            'kategori' => $kategori,
                            'ikon' => $ikon,
                            'gambar' => $gambar_val,
                            'urutan' => $urutan,
                            'id' => $id_edit
                        ]);
                        
                        $success = "Sarana fasilitas dan galeri dokumentasi foto berhasil diperbarui!";
                        $edit_mode = false;
                    }
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan data fasilitas: " . $e->getMessage();
                }
            }
        } else {
            $error = "Nama dan deskripsi fasilitas wajib diisi.";
        }
    }
}

// 2. Tangani Aksi Hapus Fasilitas
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            // Ambil data foto untuk dihapus dari Cloud Storage dan lokal
            $stmt_f = $db->prepare("SELECT gambar FROM fasilitas WHERE id = :id");
            $stmt_f->execute(['id' => $id]);
            $row_f = $stmt_f->fetch();
            if ($row_f && !empty($row_f['gambar'])) {
                $imgs_del = json_decode($row_f['gambar'], true);
                if (!is_array($imgs_del)) $imgs_del = [$row_f['gambar']];
                foreach ($imgs_del as $img_del) {
                    if (!empty($img_del)) {
                        if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED && function_exists('supabase_delete')) {
                            supabase_delete('fasilitas/' . $img_del);
                        }
                        $f_path = '../uploads/fasilitas/' . $img_del;
                        if (file_exists($f_path)) { @unlink($f_path); }
                    }
                }
            }

            $stmt = $db->prepare("DELETE FROM fasilitas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Data sarana fasilitas berhasil dihapus permanen.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus fasilitas: " . $e->getMessage();
        }
    }
}

// 3. Ambil semua fasilitas dari database
$stmt_all = $db->query("SELECT * FROM fasilitas ORDER BY urutan ASC, id ASC");
$fasilitas_list = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// 4. Analisis Statistik & Metrik KPI Fasilitas
$total_fasilitas = count($fasilitas_list);
$total_foto = 0;
$kategori_counts = [
    'belajar' => 0,
    'asrama' => 0,
    'ibadah' => 0,
    'olahraga' => 0,
    'umum' => 0
];

foreach ($fasilitas_list as $f) {
    $kat = strtolower($f['kategori'] ?? 'umum');
    if (isset($kategori_counts[$kat])) {
        $kategori_counts[$kat]++;
    } else {
        $kategori_counts['umum']++;
    }
    
    if (!empty($f['gambar'])) {
        $dec = json_decode($f['gambar'], true);
        if (is_array($dec)) {
            $total_foto += count($dec);
        } else {
            $total_foto += 1;
        }
    }
}

// Helper badge kategori styling
if (!function_exists('get_kategori_badge_meta')) {
    function get_kategori_badge_meta($kategori) {
        switch (strtolower(trim((string)$kategori))) {
            case 'belajar':
                return [
                    'label' => 'Belajar & Kelas',
                    'class' => 'bg-blue-50 text-blue-700 border-blue-200/80',
                    'dot' => 'bg-blue-500'
                ];
            case 'ibadah':
                return [
                    'label' => 'Ibadah & Dakwah',
                    'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
                    'dot' => 'bg-emerald-500'
                ];
            case 'asrama':
                return [
                    'label' => 'Asrama & Hunian',
                    'class' => 'bg-amber-50 text-amber-700 border-amber-200/80',
                    'dot' => 'bg-amber-500'
                ];
            case 'olahraga':
                return [
                    'label' => 'Olahraga & Jasmani',
                    'class' => 'bg-orange-50 text-orange-700 border-orange-200/80',
                    'dot' => 'bg-orange-500'
                ];
            default:
                return [
                    'label' => 'Umum & Penunjang',
                    'class' => 'bg-purple-50 text-purple-700 border-purple-200/80',
                    'dot' => 'bg-purple-500'
                ];
        }
    }
}

$page_title = 'Kelola Sarana & Fasilitas - Admin Babul Jannah';
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
                        <i class="fa-solid fa-building-columns"></i>
                        <span>Sarana &amp; Prasarana Pesantren</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">Portal Ponpes Babul Jannah</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola data gedung, ruang kelas, asrama santri, sarana ibadah, dan galeri dokumentasi foto pesantren.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Shortcut to Public Profile Section -->
                    <a href="../profil.php#fasilitas" target="_blank" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat di Halaman Profil Web">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400"></i>
                        <span>Lihat di Web</span>
                    </a>

                    <!-- Add New Facility Button -->
                    <button type="button" onclick="openModalTambah()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tambah Fasilitas Baru">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Tambah Fasilitas</span>
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
                
                <!-- Stat 1: Total Fasilitas -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Sarana</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-gray-900"><?= number_format($total_fasilitas) ?></span>
                            <span class="text-[10px] text-gray-500 font-semibold">Fasilitas</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Total Foto Galeri -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Foto Dokumentasi</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-emerald-800"><?= number_format($total_foto) ?></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">Berkas WebP</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Belajar & Ibadah -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Belajar &amp; Ibadah</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-blue-800"><?= number_format($kategori_counts['belajar'] + $kategori_counts['ibadah']) ?></span>
                            <span class="text-[10px] text-blue-700 font-semibold">Sarana Utama</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Asrama, Olahraga & Umum -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Hunian &amp; Penunjang</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-purple-800"><?= number_format($kategori_counts['asrama'] + $kategori_counts['olahraga'] + $kategori_counts['umum']) ?></span>
                            <span class="text-[10px] text-purple-700 font-semibold">Fasilitas</span>
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
                        <input type="text" id="search-fasilitas" oninput="filterFasilitas()" placeholder="Cari nama fasilitas, kategori, atau deskripsi sarana..."
                            class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/20 transition">
                        <button type="button" id="btn-clear-search" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Right Controls: Sort & View Mode Switcher -->
                    <div class="flex items-center space-x-3 flex-shrink-0">
                        
                        <!-- Sort Filter -->
                        <div class="flex items-center space-x-1.5 text-xs text-gray-600">
                            <label for="sort-fasilitas" class="text-[11px] font-bold text-gray-500 hidden sm:inline">Urutkan:</label>
                            <select id="sort-fasilitas" onchange="sortFasilitasItems()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-brand-primary">
                                <option value="urutan">Urutan Tampil (ASC)</option>
                                <option value="az">Nama (A - Z)</option>
                                <option value="za">Nama (Z - A)</option>
                                <option value="terbanyak">Foto Terbanyak</option>
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

                <!-- Row 2: Category Filter Tabs Pills -->
                <div class="flex items-center space-x-1.5 overflow-x-auto custom-scrollbar pb-1 text-xs">
                    <button type="button" onclick="setKategoriFilter('all', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap bg-brand-primary text-white shadow-xs">
                        Semua Kategori (<?= $total_fasilitas ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('belajar', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-graduation-cap mr-1 text-blue-500"></i> Belajar (<?= $kategori_counts['belajar'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('asrama', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-bed mr-1 text-amber-500"></i> Asrama (<?= $kategori_counts['asrama'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('ibadah', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-mosque mr-1 text-emerald-500"></i> Ibadah (<?= $kategori_counts['ibadah'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('olahraga', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-futbol mr-1 text-orange-500"></i> Olahraga (<?= $kategori_counts['olahraga'] ?>)
                    </button>
                    <button type="button" onclick="setKategoriFilter('umum', this)" class="btn-kategori-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-utensils mr-1 text-purple-500"></i> Umum (<?= $kategori_counts['umum'] ?>)
                    </button>
                </div>

                <!-- Info Bar: Showing Count -->
                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-100">
                    <div>
                        Menampilkan <strong id="visible-count" class="text-gray-900"><?= $total_fasilitas ?></strong> dari <strong class="text-gray-900"><?= $total_fasilitas ?></strong> sarana fasilitas
                    </div>
                    <div class="flex items-center space-x-2 text-[10px] text-gray-400">
                        <span><i class="fa-solid fa-circle-info mr-1"></i> Klik thumbnail foto untuk melihat resolusi penuh</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4A. GRID VIEW: KARTU FASILITAS INTERAKTIF (DEFAULT VIEW) -->
            <!-- ======================================================== -->
            <div id="view-grid-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if ($total_fasilitas === 0): ?>
                    <div class="col-span-full bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                        <i class="fa-solid fa-building-columns text-5xl mb-3 block opacity-30 text-brand-primary"></i>
                        <h4 class="text-sm font-bold text-gray-700">Belum Ada Fasilitas Ditambahkan</h4>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Mulai tambahkan gedung, asrama, ruang kelas, atau sarana ibadah pertama Anda.</p>
                        <button type="button" onclick="openModalTambah()" class="mt-4 inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-4 rounded-xl text-xs shadow-xs transition">
                            <i class="fa-solid fa-plus"></i>
                            <span>Tambah Fasilitas Pertama</span>
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($fasilitas_list as $f): 
                        $kat_meta = get_kategori_badge_meta($f['kategori'] ?? 'umum');
                        
                        $imgs = [];
                        if (!empty($f['gambar'])) {
                            $decoded = json_decode($f['gambar'], true);
                            $imgs = is_array($decoded) ? $decoded : [$f['gambar']];
                        }
                        $count_imgs = count($imgs);
                        $cover_img = !empty($imgs) ? get_storage_url('fasilitas', $imgs[0], '', true) : '';
                    ?>
                        <article class="fasilitas-item-card bg-white rounded-2xl border border-gray-200/90 hover:border-brand-primary/40 shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between overflow-hidden group"
                            data-id="<?= $f['id'] ?>"
                            data-urutan="<?= (int)$f['urutan'] ?>"
                            data-title="<?= htmlspecialchars(strtolower($f['nama'])) ?>"
                            data-desc="<?= htmlspecialchars(strtolower($f['deskripsi'])) ?>"
                            data-kategori="<?= htmlspecialchars(strtolower($f['kategori'] ?? 'umum')) ?>"
                            data-photo-count="<?= $count_imgs ?>">
                            
                            <div>
                                <!-- Card Header: Icon, Name, Category & Urutan -->
                                <div class="p-4 sm:p-5 pb-3.5 space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-11 h-11 rounded-2xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-lg flex-shrink-0 group-hover:bg-brand-primary group-hover:text-white transition-colors duration-300 shadow-2xs">
                                                <i class="<?= htmlspecialchars($f['ikon']) ?>"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-black text-gray-900 text-sm leading-snug line-clamp-1 group-hover:text-brand-primary transition-colors">
                                                    <?= htmlspecialchars($f['nama']) ?>
                                                </h3>
                                                <div class="flex items-center space-x-2 mt-1">
                                                    <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md border <?= $kat_meta['class'] ?>">
                                                        <span class="w-1.5 h-1.5 rounded-full <?= $kat_meta['dot'] ?> mr-1.5"></span>
                                                        <?= $kat_meta['label'] ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <span class="text-[10px] font-extrabold bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md flex-shrink-0" title="Urutan Prioritas Tampil">
                                            #<?= (int)$f['urutan'] ?>
                                        </span>
                                    </div>

                                    <!-- Description -->
                                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-3">
                                        <?= htmlspecialchars($f['deskripsi']) ?>
                                    </p>
                                </div>

                                <!-- Photo Gallery Preview -->
                                <div class="px-4 sm:px-5 pb-3">
                                    <?php if (!empty($imgs)): ?>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <?php foreach ($imgs as $idx => $img_name): ?>
                                                <?php if ($idx < 3): 
                                                    $curr_img_url = get_storage_url('fasilitas', $img_name, '', true);
                                                ?>
                                                    <div class="h-20 rounded-xl overflow-hidden bg-gray-100 relative group/img cursor-pointer border border-gray-200 shadow-2xs"
                                                         onclick="openPhotoLightbox('<?= $curr_img_url ?>', '<?= htmlspecialchars(addslashes($f['nama'])) ?> - Foto <?= $idx + 1 ?>')">
                                                        <img src="<?= $curr_img_url ?>" alt="<?= htmlspecialchars($f['nama']) ?>" 
                                                             onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';"
                                                             class="w-full h-full object-cover group-hover/img:scale-105 transition duration-300">
                                                        
                                                        <?php if ($idx === 0): ?>
                                                            <span class="absolute top-1 left-1 bg-black/60 backdrop-blur-xs text-white text-[8px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">
                                                                COVER
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($idx === 2 && $count_imgs > 3): ?>
                                                            <div class="absolute inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center text-white text-xs font-black">
                                                                +<?= $count_imgs - 3 ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-20 rounded-xl bg-gray-50 border-2 border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">
                                            <i class="fa-regular fa-image mr-1.5"></i> Belum ada foto dokumentasi
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Footer Action Bar (UI-UX Pro Max: Explicit & Accessible) -->
                            <div class="p-3.5 sm:px-5 border-t border-gray-100/90 flex items-center justify-between gap-2 bg-gray-50/50 rounded-b-2xl">
                                
                                <!-- Photo Count Badge -->
                                <span class="inline-flex items-center text-[11px] font-bold text-gray-500">
                                    <i class="fa-solid fa-camera mr-1 text-gray-400"></i>
                                    <?= $count_imgs ?> Foto
                                </span>

                                <div class="flex items-center space-x-1.5">
                                    <!-- Edit Button -->
                                    <button type="button" 
                                            onclick="openModalEdit(this)"
                                            data-id="<?= $f['id'] ?>"
                                            data-nama="<?= htmlspecialchars($f['nama'], ENT_QUOTES) ?>"
                                            data-deskripsi="<?= htmlspecialchars($f['deskripsi'], ENT_QUOTES) ?>"
                                            data-kategori="<?= htmlspecialchars($f['kategori'] ?? 'umum', ENT_QUOTES) ?>"
                                            data-ikon="<?= htmlspecialchars($f['ikon'], ENT_QUOTES) ?>"
                                            data-urutan="<?= (int)$f['urutan'] ?>"
                                            data-gambar='<?= htmlspecialchars(json_encode($imgs), ENT_QUOTES) ?>'
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-2.5 py-1.5 rounded-xl transition" title="Edit Fasilitas">
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                        <span class="text-[11px]">Edit</span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button"
                                            onclick="confirmDeleteFasilitas(<?= $f['id'] ?>, '<?= htmlspecialchars(addslashes($f['nama'])) ?>')"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 px-2.5 py-1.5 rounded-xl transition" title="Hapus Fasilitas">
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
                                <th class="py-3 px-4">Nama Sarana &amp; Deskripsi</th>
                                <th class="py-3 px-4 w-36">Kategori</th>
                                <th class="py-3 px-4 w-28 text-center">Dokumentasi</th>
                                <th class="py-3 px-4 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($total_fasilitas === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-400">Belum ada fasilitas ditambahkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fasilitas_list as $f): 
                                    $kat_meta = get_kategori_badge_meta($f['kategori'] ?? 'umum');
                                    $imgs = [];
                                    if (!empty($f['gambar'])) {
                                        $dec = json_decode($f['gambar'], true);
                                        $imgs = is_array($dec) ? $dec : [$f['gambar']];
                                    }
                                    $cover_url = !empty($imgs) ? get_storage_url('fasilitas', $imgs[0], '', true) : '';
                                ?>
                                    <tr class="fasilitas-item-row hover:bg-gray-50/60 transition"
                                        data-id="<?= $f['id'] ?>"
                                        data-urutan="<?= (int)$f['urutan'] ?>"
                                        data-title="<?= htmlspecialchars(strtolower($f['nama'])) ?>"
                                        data-desc="<?= htmlspecialchars(strtolower($f['deskripsi'])) ?>"
                                        data-kategori="<?= htmlspecialchars(strtolower($f['kategori'] ?? 'umum')) ?>"
                                        data-photo-count="<?= count($imgs) ?>">
                                        <td class="py-3.5 px-4 text-center font-black text-gray-500">
                                            #<?= (int)$f['urutan'] ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="w-8 h-8 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-sm mx-auto">
                                                <i class="<?= htmlspecialchars($f['ikon']) ?>"></i>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="font-extrabold text-gray-900 line-clamp-1 text-xs">
                                                <?= htmlspecialchars($f['nama']) ?>
                                            </span>
                                            <p class="text-[11px] text-gray-500 line-clamp-1 mt-0.5">
                                                <?= htmlspecialchars($f['deskripsi']) ?>
                                            </p>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md border <?= $kat_meta['class'] ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $kat_meta['dot'] ?> mr-1.5"></span>
                                                <?= $kat_meta['label'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <?php if (!empty($cover_url)): ?>
                                                <div class="inline-flex items-center space-x-1.5 cursor-pointer" onclick="openPhotoLightbox('<?= $cover_url ?>', '<?= htmlspecialchars(addslashes($f['nama'])) ?>')">
                                                    <img src="<?= $cover_url ?>" class="w-7 h-7 rounded-lg object-cover border border-gray-200 bg-gray-100">
                                                    <span class="text-xs font-bold text-gray-700"><?= count($imgs) ?> Foto</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-[11px]">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center space-x-1.5">
                                                <button type="button" 
                                                        onclick="openModalEdit(this)"
                                                        data-id="<?= $f['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($f['nama'], ENT_QUOTES) ?>"
                                                        data-deskripsi="<?= htmlspecialchars($f['deskripsi'], ENT_QUOTES) ?>"
                                                        data-kategori="<?= htmlspecialchars($f['kategori'] ?? 'umum', ENT_QUOTES) ?>"
                                                        data-ikon="<?= htmlspecialchars($f['ikon'], ENT_QUOTES) ?>"
                                                        data-urutan="<?= (int)$f['urutan'] ?>"
                                                        data-gambar='<?= htmlspecialchars(json_encode($imgs), ENT_QUOTES) ?>'
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Edit">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button type="button" 
                                                        onclick="confirmDeleteFasilitas(<?= $f['id'] ?>, '<?= htmlspecialchars(addslashes($f['nama'])) ?>')"
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
            <div id="fasilitas-empty-state" class="hidden bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                <i class="fa-solid fa-magnifying-glass text-4xl mb-3 block opacity-30 text-gray-400"></i>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Fasilitas yang Cocok</h4>
                <p class="text-xs text-gray-500 mt-1">Coba gunakan kata kunci pencarian atau kategori filter lainnya.</p>
                <button type="button" onclick="resetAllFilters()" class="mt-3 inline-flex items-center space-x-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-1.5 px-3 rounded-xl text-xs transition">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset Semua Filter</span>
                </button>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL TAMBAH & EDIT FASILITAS (UI-UX PRO MAX) -->
    <!-- ======================================================== -->
    <div id="modal-fasilitas" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-2xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 sm:px-8 py-4.5 flex-shrink-0 bg-gray-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-primary text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-black text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Sarana Fasilitas
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Fasilitas Baru
                            <?php endif; ?>
                        </h2>
                        <p class="text-[11px] text-gray-500">Lengkapi informasi sarana, kategori, ikon, dan unggah foto dokumentasi.</p>
                    </div>
                </div>

                <button type="button" onclick="closeModalFasilitas()" class="w-8 h-8 rounded-xl bg-gray-150 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Scrollable Body -->
            <div class="p-6 sm:p-8 overflow-y-auto custom-scrollbar flex-grow space-y-5">
                <form id="form-fasilitas" action="fasilitas.php" method="POST" enctype="multipart/form-data" class="space-y-5" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" id="form-action" value="<?= $edit_mode ? 'edit_fasilitas' : 'add_fasilitas' ?>">
                    <input type="hidden" name="id" id="form-id" value="<?= $edit_data['id'] ?>">

                    <!-- Field 1: Nama Fasilitas & Kategori -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                Nama Fasilitas <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="nama" id="form-nama" required placeholder="Contoh: Gedung Kelas SMP IT, Asrama Putra..."
                                value="<?= htmlspecialchars($edit_data['nama']) ?>"
                                class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                Kategori <span class="text-rose-500">*</span>
                            </label>
                            <select name="kategori" id="form-kategori" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-bold">
                                <option value="belajar" <?= $edit_data['kategori'] === 'belajar' ? 'selected' : '' ?>>📚 Fasilitas Belajar &amp; Kelas</option>
                                <option value="asrama" <?= $edit_data['kategori'] === 'asrama' ? 'selected' : '' ?>>🛏️ Fasilitas Asrama &amp; Hunian</option>
                                <option value="ibadah" <?= $edit_data['kategori'] === 'ibadah' ? 'selected' : '' ?>>🕌 Fasilitas Ibadah &amp; Dakwah</option>
                                <option value="olahraga" <?= $edit_data['kategori'] === 'olahraga' ? 'selected' : '' ?>>⚽ Fasilitas Olahraga &amp; Jasmani</option>
                                <option value="umum" <?= $edit_data['kategori'] === 'umum' ? 'selected' : '' ?>>🍽️ Fasilitas Umum &amp; Penunjang</option>
                            </select>
                        </div>
                    </div>

                    <!-- Field 2: Deskripsi Fasilitas -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Deskripsi Fasilitas <span class="text-rose-500">*</span>
                            </label>
                            <span id="desc-char-count" class="text-[10px] text-gray-400 font-semibold">0 karakter</span>
                        </div>
                        <textarea name="deskripsi" id="form-deskripsi" required rows="3" oninput="updateDescCharCount()"
                            placeholder="Jelaskan daya tampung, perlengkapan, kenyamanan, atau keunggulan fasilitas ini..."
                            class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-4 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-none leading-relaxed"><?= htmlspecialchars($edit_data['deskripsi']) ?></textarea>
                    </div>

                    <!-- Field 3: Ikon Picker & Urutan Tampil (Rock Solid Responsive Layout) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                        
                        <!-- Ikon Picker with Presets (Takes 2 of 3 Columns) -->
                        <div class="sm:col-span-2 space-y-2">
                            <label for="form-ikon" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Ikon Fasilitas (FontAwesome) <span class="text-rose-500">*</span>
                            </label>
                            
                            <div class="flex items-center space-x-2.5">
                                <div class="w-11 h-11 rounded-xl bg-brand-primary/10 text-brand-primary flex items-center justify-center text-xl flex-shrink-0 border border-brand-primary/20 shadow-2xs">
                                    <i id="preview-icon" class="<?= htmlspecialchars($edit_data['ikon'] ?: 'fa-solid fa-building') ?>"></i>
                                </div>
                                <div class="relative flex-grow">
                                    <input type="text" name="ikon" id="form-ikon" value="<?= htmlspecialchars($edit_data['ikon'] ?: 'fa-solid fa-building') ?>" oninput="updateIconPreview(this.value)"
                                        placeholder="fa-solid fa-building"
                                        class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3.5 py-2.5 text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none font-mono font-bold">
                                </div>
                            </div>

                            <!-- Preset Icon Buttons (Horizontally Wrapping Pills) -->
                            <div class="pt-1">
                                <span class="text-[10px] text-gray-500 font-bold block mb-1.5 flex items-center">
                                    <i class="fa-solid fa-wand-magic-sparkles text-brand-primary mr-1 text-[9px]"></i> Preset Ikon Cepat:
                                </span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-school')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Sekolah / Kelas">
                                        <i class="fa-solid fa-school mr-1.5 text-teal-600"></i> Kelas
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-mosque')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Masjid / Mushala">
                                        <i class="fa-solid fa-mosque mr-1.5 text-emerald-600"></i> Masjid
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-bed')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Asrama">
                                        <i class="fa-solid fa-bed mr-1.5 text-indigo-600"></i> Asrama
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-book')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Perpustakaan">
                                        <i class="fa-solid fa-book mr-1.5 text-blue-600"></i> Perpus
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-futbol')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Lapangan Olahraga">
                                        <i class="fa-solid fa-futbol mr-1.5 text-orange-600"></i> Lapangan
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-utensils')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Dapur / Kantin">
                                        <i class="fa-solid fa-utensils mr-1.5 text-rose-600"></i> Kantin
                                    </button>
                                    <button type="button" onclick="selectPresetIcon('fa-solid fa-chalkboard')" class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-brand-primary hover:text-white rounded-lg text-[10px] font-bold text-gray-700 transition shadow-2xs" title="Papan Tulis / Lab">
                                        <i class="fa-solid fa-chalkboard mr-1.5 text-purple-600"></i> Lab
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
                            <span class="text-[10px] text-gray-400 block leading-tight">Angka kecil tampil paling awal di web.</span>
                        </div>

                    </div>

                    <!-- Field 4: Galeri Foto Fasilitas (Existing Photos + Drag & Drop Upload Zone) -->
                    <div class="space-y-3 bg-gray-50/80 p-4 sm:p-5 rounded-2xl border border-gray-200/80">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider">
                                <i class="fa-solid fa-images text-brand-primary mr-1"></i> Galeri Dokumentasi Foto
                            </label>
                            <span class="text-[10px] text-gray-400 font-semibold">WebP / JPG / PNG (Maks 5MB)</span>
                        </div>

                        <!-- Existing Photos Container (in Edit Mode) -->
                        <div id="existing-photos-wrapper" class="<?= ($edit_mode && !empty($edit_data['gambar'])) ? '' : 'hidden' ?> space-y-2">
                            <span class="text-[11px] font-bold text-gray-600 block">Foto Saat Ini:</span>
                            <div id="existing-photos-grid" class="flex flex-wrap gap-2.5">
                                <?php if ($edit_mode && !empty($edit_data['gambar'])): 
                                    $dec_m = json_decode($edit_data['gambar'], true);
                                    $imgs_m = is_array($dec_m) ? $dec_m : [$edit_data['gambar']];
                                    foreach ($imgs_m as $idx => $img_item):
                                        $img_url_item = get_storage_url('fasilitas', $img_item, '', true);
                                ?>
                                    <div class="existing-photo-card relative w-24 h-24 rounded-xl overflow-hidden border border-gray-300 bg-gray-100 group shadow-2xs">
                                        <input type="hidden" name="old_imgs[]" value="<?= htmlspecialchars($img_item) ?>">
                                        <img src="<?= $img_url_item ?>" class="w-full h-full object-cover">
                                        
                                        <!-- Cover Badge or Radio -->
                                        <?php if ($idx === 0): ?>
                                            <span class="absolute top-1 left-1 bg-brand-primary text-white text-[8px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">
                                                COVER
                                            </span>
                                        <?php endif; ?>

                                        <!-- Delete overlay checkbox toggle -->
                                        <label class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 cursor-pointer transition text-[10px]">
                                            <input type="checkbox" name="delete_imgs[]" value="<?= htmlspecialchars($img_item) ?>" class="hidden" 
                                                   onchange="toggleDeleteExistingPhoto(this)">
                                            <i class="fa-solid fa-trash mb-1 text-rose-400 text-sm"></i>
                                            <span class="font-bold">Hapus</span>
                                        </label>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <!-- New Photos Upload Dropzone -->
                        <div id="fasilitas-dropzone" class="border-2 border-dashed border-gray-300 hover:border-brand-primary bg-white rounded-2xl p-5 text-center relative cursor-pointer hover:bg-gray-50/80 transition-all group">
                            <input type="file" name="gambar[]" id="input-gambar-fasilitas" accept="image/*" multiple 
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                   onchange="handleNewPhotosSelected(this.files)">
                            
                            <div id="dropzone-prompt" class="space-y-1.5">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-brand-primary/60 group-hover:text-brand-primary group-hover:scale-110 transition-all duration-200"></i>
                                <p class="text-xs text-gray-700 font-bold">Pilih atau seret beberapa foto sekaligus ke sini</p>
                                <p class="text-[10px] text-gray-400">Dapat memilih lebih dari 1 foto. Sistem otomatis mengonversi ke WebP teroptimasi.</p>
                            </div>

                            <!-- Live Multi-Photo Preview Grid for Newly Selected Files -->
                            <div id="new-photos-preview-grid" class="hidden grid grid-cols-3 sm:grid-cols-4 gap-2 pt-2 z-20 relative">
                                <!-- Generated dynamically via JS -->
                            </div>
                        </div>

                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeModalFasilitas()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-fasilitas" class="bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md hover:shadow-lg transition-all transform active:scale-98 flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span id="btn-submit-text"><?= $edit_mode ? 'Simpan Perubahan' : 'Simpan Fasilitas' ?></span>
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
            // Restore saved view mode (grid vs table)
            const savedView = localStorage.getItem('admin_fasilitas_view_mode') || 'grid';
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
                    const modal = document.getElementById('modal-fasilitas');
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModalFasilitas();
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
                localStorage.setItem('admin_fasilitas_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                btnGrid.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnTable.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_fasilitas_view_mode', 'grid');
            }
        }

        // 2. Kategori Pill Filter
        function setKategoriFilter(kat, btn) {
            currentKategoriFilter = kat;

            // Update button styles
            document.querySelectorAll('.btn-kategori-tab').forEach(b => {
                b.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                b.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            if (btn) {
                btn.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
            }

            filterFasilitas();
        }

        // 3. Search & Category Combined Filter
        function filterFasilitas() {
            const query = (document.getElementById('search-fasilitas').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btn-clear-search');
            if (btnClear) {
                btnClear.classList.toggle('hidden', query.length === 0);
            }

            const cards = Array.from(document.querySelectorAll('.fasilitas-item-card'));
            const rows = Array.from(document.querySelectorAll('.fasilitas-item-row'));
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const desc = card.getAttribute('data-desc') || '';
                const kat = card.getAttribute('data-kategori') || '';

                const matchesQuery = !query || title.includes(query) || desc.includes(query) || kat.includes(query);
                const matchesKat = (currentKategoriFilter === 'all') || (kat === currentKategoriFilter);

                const isVisible = matchesQuery && matchesKat;
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const desc = row.getAttribute('data-desc') || '';
                const kat = row.getAttribute('data-kategori') || '';

                const matchesQuery = !query || title.includes(query) || desc.includes(query) || kat.includes(query);
                const matchesKat = (currentKategoriFilter === 'all') || (kat === currentKategoriFilter);

                row.style.display = (matchesQuery && matchesKat) ? '' : 'none';
            });

            const countEl = document.getElementById('visible-count');
            if (countEl) countEl.textContent = visibleCount;

            const emptyEl = document.getElementById('fasilitas-empty-state');
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-fasilitas');
            if (searchInput) {
                searchInput.value = '';
                filterFasilitas();
                searchInput.focus();
            }
        }

        function resetAllFilters() {
            clearSearch();
            const firstTab = document.querySelector('.btn-kategori-tab');
            setKategoriFilter('all', firstTab);
        }

        // 4. Sorting Function
        function sortFasilitasItems() {
            const sortBy = document.getElementById('sort-fasilitas').value;
            const gridContainer = document.getElementById('view-grid-container');
            const tableBody = document.querySelector('#view-table-container tbody');

            const cards = Array.from(document.querySelectorAll('.fasilitas-item-card'));
            const rows = Array.from(document.querySelectorAll('.fasilitas-item-row'));

            const sortFn = (a, b) => {
                if (sortBy === 'urutan') {
                    return parseInt(a.getAttribute('data-urutan') || 0) - parseInt(b.getAttribute('data-urutan') || 0);
                } else if (sortBy === 'az') {
                    return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
                } else if (sortBy === 'za') {
                    return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
                } else if (sortBy === 'terbanyak') {
                    return parseInt(b.getAttribute('data-photo-count') || 0) - parseInt(a.getAttribute('data-photo-count') || 0);
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
            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-plus-circle text-brand-primary mr-2"></i> Tambah Fasilitas Baru';
            document.getElementById('form-action').value = 'add_fasilitas';
            document.getElementById('form-id').value = '';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-kategori').value = 'belajar';
            document.getElementById('form-deskripsi').value = '';
            document.getElementById('form-ikon').value = 'fa-solid fa-building';
            document.getElementById('form-urutan').value = '0';
            document.getElementById('btn-submit-text').textContent = 'Simpan Fasilitas';

            updateIconPreview('fa-solid fa-building');
            updateDescCharCount();

            // Clear existing and new photos preview
            const existingWrapper = document.getElementById('existing-photos-wrapper');
            if (existingWrapper) existingWrapper.classList.add('hidden');
            const existingGrid = document.getElementById('existing-photos-grid');
            if (existingGrid) existingGrid.innerHTML = '';

            resetNewPhotosDropzone();

            document.getElementById('modal-fasilitas').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function openModalEdit(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama') || '';
            const deskripsi = btn.getAttribute('data-deskripsi') || '';
            const kategori = btn.getAttribute('data-kategori') || 'umum';
            const ikon = btn.getAttribute('data-ikon') || 'fa-solid fa-building';
            const urutan = btn.getAttribute('data-urutan') || '0';
            const rawGambar = btn.getAttribute('data-gambar') || '[]';

            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600 mr-2"></i> Edit Sarana Fasilitas';
            document.getElementById('form-action').value = 'edit_fasilitas';
            document.getElementById('form-id').value = id;
            document.getElementById('form-nama').value = nama;
            document.getElementById('form-kategori').value = kategori;
            document.getElementById('form-deskripsi').value = deskripsi;
            document.getElementById('form-ikon').value = ikon;
            document.getElementById('form-urutan').value = urutan;
            document.getElementById('btn-submit-text').textContent = 'Simpan Perubahan';

            updateIconPreview(ikon);
            updateDescCharCount();

            // Populate existing photos
            const existingWrapper = document.getElementById('existing-photos-wrapper');
            const existingGrid = document.getElementById('existing-photos-grid');
            
            let imgs = [];
            try {
                imgs = JSON.parse(rawGambar);
            } catch(e) {
                imgs = [];
            }

            if (imgs && imgs.length > 0) {
                existingGrid.innerHTML = '';
                imgs.forEach((imgItem, idx) => {
                    const imgUrl = `../uploads/fasilitas/${imgItem}`;
                    const isCover = (idx === 0);
                    const card = document.createElement('div');
                    card.className = 'existing-photo-card relative w-24 h-24 rounded-xl overflow-hidden border border-gray-300 bg-gray-100 group shadow-2xs transition-all';
                    card.innerHTML = `
                        <input type="hidden" name="old_imgs[]" value="${imgItem}">
                        <img src="${imgUrl}" onerror="this.onerror=null; this.src='../assets/images/placeholder.webp';" class="w-full h-full object-cover">
                        ${isCover ? '<span class="absolute top-1 left-1 bg-brand-primary text-white text-[8px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">COVER</span>' : ''}
                        <label class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 cursor-pointer transition text-[10px]">
                            <input type="checkbox" name="delete_imgs[]" value="${imgItem}" class="hidden" onchange="toggleDeleteExistingPhoto(this)">
                            <i class="fa-solid fa-trash mb-1 text-rose-400 text-sm"></i>
                            <span class="font-bold">Hapus</span>
                        </label>
                    `;
                    existingGrid.appendChild(card);
                });
                existingWrapper.classList.remove('hidden');
            } else {
                existingWrapper.classList.add('hidden');
                existingGrid.innerHTML = '';
            }

            resetNewPhotosDropzone();

            document.getElementById('modal-fasilitas').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function closeModalFasilitas() {
            document.getElementById('modal-fasilitas').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (window.location.search.includes('edit=')) {
                window.history.replaceState({}, document.title, 'fasilitas.php');
            }
        }

        // 6. Icon Picker Logic
        function updateIconPreview(iconClass) {
            const preview = document.getElementById('preview-icon');
            if (preview) {
                preview.className = iconClass || 'fa-solid fa-building';
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

        // 7. Multi-Photo Upload & Preview Handling
        function handleNewPhotosSelected(files) {
            if (!files || files.length === 0) return;
            const previewGrid = document.getElementById('new-photos-preview-grid');
            const dropPrompt = document.getElementById('dropzone-prompt');
            
            previewGrid.innerHTML = '';
            Array.from(files).forEach((file, idx) => {
                if (!file.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'relative rounded-xl overflow-hidden border border-gray-200 bg-gray-50 h-20 shadow-2xs group';
                    item.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover">
                        <span class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[8px] font-bold p-0.5 truncate text-center">
                            ${(file.size / 1024).toFixed(0)} KB
                        </span>
                    `;
                    previewGrid.appendChild(item);
                };
                reader.readAsDataURL(file);
            });

            previewGrid.classList.remove('hidden');
            dropPrompt.classList.add('hidden');
        }

        function resetNewPhotosDropzone() {
            const fileInput = document.getElementById('input-gambar-fasilitas');
            if (fileInput) fileInput.value = '';
            const previewGrid = document.getElementById('new-photos-preview-grid');
            const dropPrompt = document.getElementById('dropzone-prompt');
            if (previewGrid && dropPrompt) {
                previewGrid.innerHTML = '';
                previewGrid.classList.add('hidden');
                dropPrompt.classList.remove('hidden');
            }
        }

        function toggleDeleteExistingPhoto(checkbox) {
            const card = checkbox.closest('.existing-photo-card');
            if (!card) return;
            if (checkbox.checked) {
                card.classList.add('opacity-30', 'grayscale');
            } else {
                card.classList.remove('opacity-30', 'grayscale');
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
            const btn = document.getElementById('btn-submit-fasilitas');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Menyimpan...</span>';
            }
            return true;
        }

        // 10. SweetAlert2 Confirmation for Deleting Facility
        function confirmDeleteFasilitas(id, nama) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Fasilitas Ini?',
                    html: `Apakah Anda yakin ingin menghapus data sarana:<br><strong class="text-rose-700">"${nama}"</strong>?<br><br><span class="text-xs text-gray-500">Seluruh foto galeri dokumentasi dan data fasilitas akan dihapus secara permanen.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Hapus Fasilitas',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `fasilitas.php?action=delete&id=${id}`;
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus fasilitas "${nama}"?`)) {
                    window.location.href = `fasilitas.php?action=delete&id=${id}`;
                }
            }
        }
    </script>

</body>
</html>