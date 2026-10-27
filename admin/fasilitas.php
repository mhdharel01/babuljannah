<?php
// admin/fasilitas.php
require_once '../config/db.php';

// Proteksi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Ambil data untuk mode edit jika ada parameter edit
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
        $res_e = $stmt_e->fetch();
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Fasilitas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                // Normalize $_FILES['gambar'] structure
                $file_names = is_array($_FILES['gambar']['name']) ? $_FILES['gambar']['name'] : [$_FILES['gambar']['name']];
                $file_tmps = is_array($_FILES['gambar']['tmp_name']) ? $_FILES['gambar']['tmp_name'] : [$_FILES['gambar']['tmp_name']];
                $file_errors = is_array($_FILES['gambar']['error']) ? $_FILES['gambar']['error'] : [$_FILES['gambar']['error']];
                $file_sizes = is_array($_FILES['gambar']['size']) ? $_FILES['gambar']['size'] : [$_FILES['gambar']['size']];
                
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                foreach ($file_names as $i => $fname) {
                    if ($file_errors[$i] === UPLOAD_ERR_OK && !empty($fname)) {
                        $file_ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (in_array($file_ext, $allowed) && $file_sizes[$i] <= 5 * 1024 * 1024) {
                            $new_name = 'fasilitas_' . time() . '_' . $i . '_' . rand(100,999) . '.webp';
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
                                    if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
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
                                // Auto-resize jika gambar terlalu besar
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

                                if (imagewebp($image, $dest_path, 80)) {
                                    imagedestroy($image);
                                    if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
                                        if (supabase_upload($dest_path, 'fasilitas/' . $new_name, 'image/webp')) {
                                            @unlink($dest_path);
                                        }
                                    }
                                    $uploaded_images[] = $new_name;
                                } else {
                                    imagedestroy($image);
                                    $error = "Gagal memproses gambar.";
                                    break;
                                }
                            } else {
                                $error = "Gagal memproses gambar.";
                                break;
                            }
                        } else {
                            $error = "Salah satu file tidak valid (harus JPG/PNG/WEBP & maks 5MB).";
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
                        $success = "Fasilitas berhasil ditambahkan.";
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
                                // Hapus file fisik dan Cloud Storage jika ada
                                if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
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
                        
                        $success = "Fasilitas dan galeri foto berhasil diperbarui.";
                        $edit_mode = false;
                    }
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan fasilitas: " . $e->getMessage();
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
                        if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
                            supabase_delete('fasilitas/' . $img_del);
                        }
                        $f_path = '../uploads/fasilitas/' . $img_del;
                        if (file_exists($f_path)) { @unlink($f_path); }
                    }
                }
            }

            $stmt = $db->prepare("DELETE FROM fasilitas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Fasilitas berhasil dihapus.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus fasilitas: " . $e->getMessage();
        }
    }
}

// Ambil semua fasilitas
$stmt_all = $db->query("SELECT * FROM fasilitas ORDER BY urutan ASC, id ASC");
$fasilitas_list = $stmt_all->fetchAll();
?>
<?php include 'includes/head.php'; ?>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        <?php include 'includes/header.php'; ?>

        <main class="flex-grow p-8 overflow-y-auto space-y-8 bg-gray-50/50 relative">
            <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-2xl text-sm font-medium flex items-center shadow-sm">
                <i class="fa-solid fa-circle-check mr-3 text-emerald-600 text-lg"></i>
                <span><?= $success ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-2xl text-sm font-medium flex items-center shadow-sm">
                <i class="fa-solid fa-triangle-exclamation mr-3 text-red-600 text-lg"></i>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <!-- Tabel Daftar Fasilitas (Full Width) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center"><i class="fa-solid fa-list mr-2 text-secondary"></i> Daftar Fasilitas Pondok (<?= count($fasilitas_list) ?>)</h2>
                    <button onclick="document.getElementById('modal-fasilitas').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white font-bold px-4 py-2 rounded-xl text-sm transition shadow-sm flex items-center space-x-2">
                        <i class="fa-solid fa-plus"></i><span>Tambah Data</span>
                    </button>
                </div>
                
                <?php if (count($fasilitas_list) === 0): ?>
                    <div class="text-center py-16 text-gray-400 text-sm">
                        <i class="fa-solid fa-building text-5xl mb-3 block opacity-30"></i>
                        Belum ada fasilitas ditambahkan.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($fasilitas_list as $f): ?>
                            <div class="border border-gray-200/80 rounded-2xl p-6 space-y-4 hover:shadow-md transition-all group relative bg-white">
                                <!-- Action buttons -->
                                <div class="absolute top-4 right-4 flex items-center space-x-2 z-10">
                                    <a href="fasilitas.php?edit=<?= $f['id'] ?>" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200/60 transition shadow-sm" title="Edit Fasilitas">
                                        <i class="fa-solid fa-pen-to-square"></i><span>Edit</span>
                                    </a>
                                    <a href="fasilitas.php?action=delete&id=<?= $f['id'] ?>" onclick="return confirm('Hapus fasilitas ini?')" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200/60 transition shadow-sm" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i><span>Hapus</span>
                                    </a>
                                </div>
                                
                                <!-- Icon & Name -->
                                <div class="flex items-start space-x-3.5 pr-32">
                                    <div class="w-12 h-12 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center flex-shrink-0">
                                        <i class="<?= htmlspecialchars($f['ikon']) ?> text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-base leading-snug"><?= $f['nama'] ?></h4>
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span class="text-[10px] bg-gray-100 text-gray-700 font-bold px-2 py-0.5 rounded-md uppercase tracking-wider"><?= htmlspecialchars($f['kategori'] ?? 'umum') ?></span>
                                            <span class="text-xs text-gray-400 font-medium">Urutan: <?= $f['urutan'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="text-xs text-gray-600 leading-relaxed line-clamp-3"><?= $f['deskripsi'] ?></p>
                                
                                <?php 
                                    $imgs = [];
                                    if (!empty($f['gambar'])) {
                                        $decoded = json_decode($f['gambar'], true);
                                        $imgs = is_array($decoded) ? $decoded : [$f['gambar']];
                                    }
                                ?>
                                <?php if (!empty($imgs)): ?>
                                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-gray-100">
                                        <?php foreach ($imgs as $idx => $img_name): ?>
                                            <?php if ($idx < 3): ?>
                                                <div class="h-24 rounded-xl overflow-hidden bg-gray-100 relative group/img shadow-sm border border-gray-150">
                                                    <img src="<?= get_storage_url('fasilitas', $img_name, '', true) ?>" alt="<?= htmlspecialchars($f['nama']) ?>" class="w-full h-full object-cover">
                                                    <?php if ($idx === 0): ?>
                                                        <span class="absolute top-1 left-1 bg-secondary text-white text-[8px] font-extrabold px-1.5 py-0.5 rounded shadow">COVER</span>
                                                    <?php endif; ?>
                                                    <?php if ($idx === 2 && count($imgs) > 3): ?>
                                                        <div class="absolute inset-0 bg-black/60 backdrop-blur-[1px] flex items-center justify-center text-white text-xs font-extrabold">
                                                            +<?= count($imgs) - 3 ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-24 rounded-xl bg-gray-50 border-2 border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">
                                        <i class="fa-solid fa-image mr-2"></i> Belum ada foto
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- POP-UP MODAL TAMBAH/EDIT FASILITAS -->
            <div id="modal-fasilitas" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/80 w-full max-w-2xl my-auto relative max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-gray-100 px-8 py-5 flex-shrink-0">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square mr-2 text-secondary"></i> Edit Fasilitas
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle mr-2 text-secondary"></i> Tambah Fasilitas Baru
                            <?php endif; ?>
                        </h2>
                        <button onclick="document.getElementById('modal-fasilitas').classList.add('hidden'); <?php if($edit_mode) echo "window.location.href='fasilitas.php'"; ?>" class="text-gray-400 hover:text-red-500 transition focus:outline-none">
                            <i class="fa-solid fa-xmark text-2xl"></i>
                        </button>
                    </div>
                    
                    <div class="p-8 overflow-y-auto">
                        <form action="fasilitas.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="<?= $edit_mode ? 'edit_fasilitas' : 'add_fasilitas' ?>">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Fasilitas</label>
                                    <input type="text" name="nama" required placeholder="Contoh: Gedung SMP IT" value="<?= htmlspecialchars($edit_data['nama']) ?>"
                                        class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kategori</label>
                                    <select name="kategori" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none appearance-none">
                                        <option value="belajar" <?= $edit_data['kategori'] == 'belajar' ? 'selected' : '' ?>>Fasilitas Belajar</option>
                                        <option value="asrama" <?= $edit_data['kategori'] == 'asrama' ? 'selected' : '' ?>>Fasilitas Asrama</option>
                                        <option value="olahraga" <?= $edit_data['kategori'] == 'olahraga' ? 'selected' : '' ?>>Fasilitas Olahraga</option>
                                        <option value="ibadah" <?= $edit_data['kategori'] == 'ibadah' ? 'selected' : '' ?>>Fasilitas Ibadah</option>
                                        <option value="umum" <?= $edit_data['kategori'] == 'umum' ? 'selected' : '' ?>>Fasilitas Umum</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Deskripsi</label>
                                <textarea name="deskripsi" required rows="3" placeholder="Jelaskan fasilitas ini..."
                                    class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-none"><?= htmlspecialchars($edit_data['deskripsi']) ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Ikon FontAwesome (Solid)</label>
                                    <div class="relative">
                                        <input type="text" name="ikon" placeholder="fa-solid fa-building" value="<?= htmlspecialchars($edit_data['ikon']) ?>"
                                            class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl pl-10 pr-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                                            <i class="<?= htmlspecialchars($edit_data['ikon']) ?>"></i>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between">
                                        <span class="text-[10px] text-gray-500">Buka <a href="https://fontawesome.com/search?o=r&m=free&s=solid" target="_blank" class="text-secondary hover:underline">fontawesome.com</a></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Urutan Tampil (Opsional)</label>
                                    <input type="number" name="urutan" value="<?= htmlspecialchars($edit_data['urutan']) ?>"
                                        class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                                </div>
                            </div>

                            <!-- Image Upload and Preview Area -->
                            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-4">
                                    Galeri Foto Fasilitas
                                </label>
                                
                                <?php if ($edit_mode && !empty($edit_data['gambar'])): ?>
                                    <div class="mb-4 space-y-2">
                                        <span class="text-xs font-medium text-gray-600 block">Foto Saat Ini:</span>
                                        <div class="flex flex-wrap gap-3">
                                            <?php 
                                            $decoded_edit = json_decode($edit_data['gambar'], true);
                                            $imgs_edit = is_array($decoded_edit) ? $decoded_edit : [$edit_data['gambar']];
                                            foreach($imgs_edit as $idx => $img_edit): 
                                            ?>
                                                <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-300 group transition-all duration-300">
                                                    <input type="hidden" name="old_imgs[]" value="<?= htmlspecialchars($img_edit) ?>">
                                                    <img src="<?= get_storage_url('fasilitas', $img_edit, '', true) ?>" class="w-full h-full object-cover">
                                                    <!-- Option to delete specific photo -->
                                                    <label class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 cursor-pointer transition text-[10px]">
                                                        <input type="checkbox" name="delete_imgs[]" value="<?= htmlspecialchars($img_edit) ?>" class="hidden" onchange="this.parentElement.parentElement.classList.toggle('opacity-30'); this.parentElement.parentElement.classList.toggle('grayscale'); this.parentElement.classList.toggle('opacity-100'); this.nextElementSibling.classList.toggle('text-red-400');">
                                                        <i class="fa-solid fa-trash mb-1 text-red-400"></i> Hapus
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="border-2 border-dashed border-gray-300 bg-white rounded-xl p-6 text-center relative cursor-pointer hover:bg-gray-50 transition-all">
                                    <input type="file" name="gambar[]" id="gambar" accept="image/*" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        onchange="if(this.files.length>0) this.nextElementSibling.nextElementSibling.textContent = 'Terpilih ' + this.files.length + ' foto';">
                                    <i class="fa-solid fa-images text-3xl text-secondary mb-2 block"></i>
                                    <p class="text-xs text-gray-600 font-semibold"><?= $edit_mode ? 'Klik atau Drop untuk menambah foto baru' : 'Pilih satu atau banyak foto sekaligus' ?></p>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-gray-100">
                                <?php if ($edit_mode): ?>
                                    <a href="fasilitas.php" class="mr-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-xl text-sm transition">Batal</a>
                                <?php endif; ?>
                                <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-6 rounded-xl text-sm shadow-md hover:shadow-lg transition-all transform active:scale-[0.99] flex items-center space-x-2">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    <span><?= $edit_mode ? 'Simpan Perubahan' : 'Simpan Fasilitas' ?></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</body>
</html>