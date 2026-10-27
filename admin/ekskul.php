<?php
// admin/ekskul.php
require_once '../config/db.php';

// Proteksi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'humas'])) {
    die("Akses Ditolak. Anda tidak memiliki izin mengelola ekstrakurikuler.");
}

$error = '';
$success = '';

// Ambil data untuk mode edit jika ada parameter edit
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
        $res_e = $stmt_e->fetch();
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Ekskul
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_ekskul' || $_POST['action'] === 'edit_ekskul') {
        $nama = sanitize($_POST['nama'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $ikon = sanitize($_POST['ikon'] ?? 'fa-solid fa-star');
        $urutan = (int)($_POST['urutan'] ?? 0);
        
        if (!empty($nama) && !empty($deskripsi)) {
            $gambar_val = null;
            
            // Handle file upload (single image for ekskul)
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $gambar_val = upload_and_convert_to_webp('gambar', '../uploads/ekskul/', 'ekskul');
                if (!$gambar_val) {
                    $error = "File gambar tidak valid atau gagal diproses (harus JPG/PNG/WEBP & maks 5MB).";
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
                        $success = "Ekstrakurikuler berhasil ditambahkan.";
                    } elseif ($_POST['action'] === 'edit_ekskul') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        
                        $old_img = $_POST['old_img'] ?? '';
                        $delete_img = isset($_POST['delete_img']) && $_POST['delete_img'] == '1';
                        
                        if ($gambar_val) {
                            // Gambar baru diupload, hapus gambar lama
                            if ($old_img) {
                                supabase_delete('ekskul/' . $old_img);
                                if (file_exists('../uploads/ekskul/' . $old_img)) {
                                    @unlink('../uploads/ekskul/' . $old_img);
                                }
                            }
                        } else {
                            if ($delete_img) {
                                // User minta hapus gambar lama tanpa ganti baru
                                if ($old_img) {
                                    supabase_delete('ekskul/' . $old_img);
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
                        
                        $success = "Ekstrakurikuler berhasil diperbarui.";
                        $edit_mode = false;
                    }
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan ekstrakurikuler: " . $e->getMessage();
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
            // Cek gambar untuk dihapus fisiknya
            $stmt_cek = $db->prepare("SELECT gambar FROM ekskul WHERE id = :id");
            $stmt_cek->execute(['id' => $id]);
            $res = $stmt_cek->fetch();
            if ($res && $res['gambar']) {
                supabase_delete('ekskul/' . $res['gambar']);
                $file = '../uploads/ekskul/' . $res['gambar'];
                if (file_exists($file)) @unlink($file);
            }
            
            $stmt = $db->prepare("DELETE FROM ekskul WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Ekstrakurikuler berhasil dihapus.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus ekstrakurikuler: " . $e->getMessage();
        }
    }
}

// Ambil semua ekskul
$stmt_all = $db->query("SELECT * FROM ekskul ORDER BY urutan ASC, id ASC");
$ekskul_list = $stmt_all->fetchAll();
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

            <!-- Tabel Daftar Ekskul -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center"><i class="fa-solid fa-star mr-2 text-secondary"></i> Daftar Ekstrakurikuler (<?= count($ekskul_list) ?>)</h2>
                    <button onclick="document.getElementById('modal-ekskul').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white font-bold px-4 py-2 rounded-xl text-sm transition shadow-sm flex items-center space-x-2">
                        <i class="fa-solid fa-plus"></i><span>Tambah Ekskul</span>
                    </button>
                </div>
                
                <?php if (count($ekskul_list) === 0): ?>
                    <div class="text-center py-16 text-gray-400 text-sm">
                        <i class="fa-solid fa-star text-5xl mb-3 block opacity-30"></i>
                        Belum ada ekstrakurikuler ditambahkan.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($ekskul_list as $e): ?>
                            <div class="border border-gray-200/80 rounded-2xl p-6 space-y-4 hover:shadow-md transition-all group relative bg-white flex flex-col h-full">
                                <!-- Action buttons -->
                                <div class="absolute top-4 right-4 flex items-center space-x-2 z-10">
                                    <a href="ekskul.php?edit=<?= $e['id'] ?>" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200/60 transition shadow-sm" title="Edit Ekskul">
                                        <i class="fa-solid fa-pen-to-square"></i><span>Edit</span>
                                    </a>
                                    <a href="ekskul.php?action=delete&id=<?= $e['id'] ?>" onclick="return confirm('Hapus ekstrakurikuler ini?')" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200/60 transition shadow-sm" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i><span>Hapus</span>
                                    </a>
                                </div>
                                
                                <div class="flex items-start space-x-3.5 pr-32">
                                    <div class="w-12 h-12 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center flex-shrink-0">
                                        <i class="<?= htmlspecialchars($e['ikon']) ?> text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 leading-tight"><?= htmlspecialchars($e['nama']) ?></h3>
                                        <span class="inline-block mt-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Urutan: <?= $e['urutan'] ?></span>
                                    </div>
                                </div>
                                
                                <div class="text-sm text-gray-600 line-clamp-3 leading-relaxed flex-grow">
                                    <?= htmlspecialchars($e['deskripsi']) ?>
                                </div>
                                
                                <?php if (!empty($e['gambar'])): ?>
                                <div class="h-32 rounded-xl overflow-hidden mt-4 relative">
                                    <?php
                                    $img_path = (strpos($e['gambar'], 'olahraga.webp') !== false) ? '../assets/images/' . $e['gambar'] : get_storage_url('ekskul', $e['gambar'], '', true);
                                    ?>
                                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-full h-full object-cover">
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Form Tambah/Edit Ekskul -->
    <div id="modal-ekskul" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-[100] flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal()"></div>
        
        <!-- Modal Content -->
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl relative z-10 max-h-[90vh] flex flex-col transform transition-all">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fa-solid <?= $edit_mode ? 'fa-pen-to-square' : 'fa-star' ?> text-secondary mr-2.5"></i>
                    <?= $edit_mode ? 'Edit Data Ekstrakurikuler' : 'Tambah Ekstrakurikuler Baru' ?>
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 hover:bg-red-50 w-8 h-8 rounded-full flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Modal Body (Scrollable) -->
            <div class="p-8 overflow-y-auto">
                <form action="ekskul.php" method="POST" enctype="multipart/form-data" id="form-ekskul" class="space-y-6">
                    <input type="hidden" name="action" value="<?= $edit_mode ? 'edit_ekskul' : 'add_ekskul' ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                        <input type="hidden" name="old_img" value="<?= htmlspecialchars($edit_data['gambar']) ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Ekskul <span class="text-red-500">*</span></label>
                            <input type="text" name="nama" required value="<?= htmlspecialchars($edit_data['nama']) ?>" placeholder="Contoh: Panahan, Pramuka, Kaligrafi" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Deskripsi Lengkap <span class="text-red-500">*</span></label>
                            <textarea name="deskripsi" required rows="4" placeholder="Jelaskan manfaat dan aktivitas ekskul ini..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition"><?= htmlspecialchars($edit_data['deskripsi']) ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Ikon (Font Awesome)</label>
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-gray-500 shrink-0" id="icon-preview">
                                    <i class="<?= htmlspecialchars($edit_data['ikon']) ?> text-xl"></i>
                                </div>
                                <input type="text" name="ikon" id="ikon-input" value="<?= htmlspecialchars($edit_data['ikon']) ?>" placeholder="fa-solid fa-star" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" onkeyup="document.querySelector('#icon-preview i').className = this.value + ' text-xl'">
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1.5 font-medium">Cari ikon gratis di <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" class="text-blue-500 hover:underline">fontawesome.com</a></p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Urutan Tampil</label>
                            <input type="number" name="urutan" value="<?= $edit_data['urutan'] ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            <p class="text-[10px] text-gray-400 mt-1.5 font-medium">Angka kecil tampil lebih dulu (1, 2, 3..)</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Foto Dokumentasi (Opsional)</label>
                            
                            <?php if ($edit_mode && !empty($edit_data['gambar'])): ?>
                                <?php $img_path = (strpos($edit_data['gambar'], 'olahraga.webp') !== false) ? '../assets/images/' . $edit_data['gambar'] : get_storage_url('ekskul', $edit_data['gambar'], '', true); ?>
                                <div class="mb-4 flex items-center space-x-4">
                                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-24 h-24 object-cover rounded-xl border border-gray-200">
                                    <div>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer bg-red-50 text-red-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-100 transition">
                                            <input type="checkbox" name="delete_img" value="1" class="rounded border-red-300 text-red-600 focus:ring-red-500">
                                            <span>Hapus Foto Ini</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mt-1">Jika Anda mengupload foto baru, foto ini akan otomatis terganti.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <input type="file" name="gambar" accept="image/jpeg, image/png, image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 file:transition-colors file:cursor-pointer cursor-pointer border border-gray-200 rounded-xl p-1.5 bg-gray-50">
                            <p class="text-[10px] text-gray-400 mt-2 font-medium">Format: JPG/PNG/WEBP. Ukuran Maks: 5MB.</p>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-8 py-5 border-t border-gray-100 bg-gray-50 rounded-b-3xl flex justify-end items-center space-x-3">
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-gray-600 hover:bg-gray-200 transition">Batal</button>
                <button type="submit" form="form-ekskul" class="px-6 py-2.5 rounded-xl text-sm font-extrabold bg-primary text-white hover:bg-secondary shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5 flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i><span>Simpan Data</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('modal-ekskul').classList.add('hidden');
            <?php if ($edit_mode): ?>
                window.location.href = 'ekskul.php';
            <?php endif; ?>
        }
    </script>
</body>
</html>
