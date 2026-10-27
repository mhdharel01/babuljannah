<?php
// admin/prestasi.php
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
    'kategori' => 'Lainnya',
    'gambar' => ''
];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt_e = $db->prepare("SELECT * FROM prestasi WHERE id = :id");
        $stmt_e->execute(['id' => $edit_id]);
        $res_e = $stmt_e->fetch();
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Prestasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_prestasi' || $_POST['action'] === 'edit_prestasi') {
        $nama = sanitize($_POST['nama'] ?? '');
        $kategori = sanitize($_POST['kategori'] ?? 'Lainnya');
        
        if (!empty($nama)) {
            $gambar_val = null;
            
            // Handle file upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/prestasi/';
                $gambar_val = upload_and_convert_to_webp('gambar', $upload_dir, 'prestasi');
                if (!$gambar_val) {
                    $error = "File gambar tidak valid atau gagal diproses. Gunakan JPG/PNG/WEBP (max 5MB).";
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_prestasi') {
                        if (empty($gambar_val)) {
                            $error = "Gambar prestasi wajib diunggah untuk data baru.";
                        } else {
                            $stmt = $db->prepare("INSERT INTO prestasi (nama, kategori, gambar) VALUES (:nama, :kategori, :gambar)");
                            $stmt->execute([
                                'nama' => $nama,
                                'kategori' => $kategori,
                                'gambar' => $gambar_val
                            ]);
                            $success = "Prestasi berhasil ditambahkan.";
                        }
                    } elseif ($_POST['action'] === 'edit_prestasi') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        
                        $old_img = $_POST['old_img'] ?? '';
                        
                        // Jika ada gambar baru diupload, hapus yang lama
                        if (!empty($gambar_val)) {
                            $final_gambar = $gambar_val;
                            if (!empty($old_img)) {
                                supabase_delete('prestasi/' . $old_img);
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
                        
                        $success = "Prestasi berhasil diperbarui.";
                        $edit_mode = false; // Reset mode
                    }
                } catch (PDOException $e) {
                    $error = "Kesalahan Database: " . $e->getMessage();
                }
            }
        } else {
            $error = "Nama prestasi wajib diisi.";
        }
    }
}

// 2. Tangani Aksi Hapus Prestasi (Menggunakan GET dari link)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    try {
        // Ambil nama file sebelum dihapus
        $s_img = $db->prepare("SELECT gambar FROM prestasi WHERE id = :id");
        $s_img->execute(['id' => $id_del]);
        $res = $s_img->fetch();
        
        if ($res && !empty($res['gambar'])) {
            supabase_delete('prestasi/' . $res['gambar']);
            if (file_exists('../uploads/prestasi/' . $res['gambar'])) {
                @unlink('../uploads/prestasi/' . $res['gambar']);
            }
        }
        
        $stmt = $db->prepare("DELETE FROM prestasi WHERE id = :id");
        $stmt->execute(['id' => $id_del]);
        $success = "Prestasi berhasil dihapus.";
    } catch (PDOException $e) {
        $error = "Gagal menghapus: " . $e->getMessage();
    }
}


// Ambil daftar prestasi untuk tabel
try {
    $stmt_list = $db->query("SELECT * FROM prestasi ORDER BY id DESC");
    $prestasi_list = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $prestasi_list = [];
}

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

            <!-- Tabel Daftar Prestasi (Full Width) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center"><i class="fa-solid fa-trophy mr-2 text-secondary"></i> Daftar Prestasi Santri (<?= count($prestasi_list) ?>)</h2>
                    <button onclick="document.getElementById('modal-prestasi').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white font-bold px-4 py-2 rounded-xl text-sm transition shadow-sm flex items-center space-x-2">
                        <i class="fa-solid fa-plus"></i><span>Tambah Data</span>
                    </button>
                </div>
                
                <?php if (count($prestasi_list) === 0): ?>
                    <div class="text-center py-16 text-gray-400 text-sm">
                        <i class="fa-solid fa-medal text-5xl mb-3 block opacity-30"></i>
                        Belum ada data prestasi.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($prestasi_list as $p): ?>
                            <div class="border border-gray-200/80 rounded-2xl overflow-hidden hover:shadow-lg transition-all group relative bg-white">
                                <!-- Action buttons -->
                                <div class="absolute top-2 right-2 flex flex-col gap-1.5 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="prestasi.php?edit=<?= $p['id'] ?>" class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-500/90 text-white hover:bg-blue-600 backdrop-blur-sm shadow transition" title="Edit">
                                        <i class="fa-solid fa-pen text-[10px]"></i>
                                    </a>
                                    <a href="prestasi.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('Hapus prestasi ini?')" class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-500/90 text-white hover:bg-red-600 backdrop-blur-sm shadow transition" title="Hapus">
                                        <i class="fa-solid fa-trash text-[10px]"></i>
                                    </a>
                                </div>
                                
                                <div class="aspect-[4/3] w-full bg-gray-100 relative">
                                    <img src="<?= get_storage_url('prestasi', $p['gambar'], '', true) ?>" alt="<?= htmlspecialchars($p['nama']) ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="p-4 border-t border-gray-100 bg-white">
                                    <h4 class="font-bold text-gray-900 text-sm leading-tight mb-1"><?= $p['nama'] ?></h4>
                                    <span class="text-[10px] bg-secondary/10 text-secondary font-bold px-2 py-0.5 rounded-md uppercase tracking-wider inline-block">
                                        <?= htmlspecialchars($p['kategori'] ?? 'Lainnya') ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- POP-UP MODAL TAMBAH/EDIT PRESTASI -->
            <div id="modal-prestasi" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/80 w-full max-w-xl my-auto relative max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-gray-100 px-8 py-5 flex-shrink-0">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square mr-2 text-secondary"></i> Edit Prestasi
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle mr-2 text-secondary"></i> Tambah Prestasi Baru
                            <?php endif; ?>
                        </h2>
                        <button onclick="document.getElementById('modal-prestasi').classList.add('hidden'); <?php if($edit_mode) echo "window.location.href='prestasi.php'"; ?>" class="text-gray-400 hover:text-red-500 transition focus:outline-none">
                            <i class="fa-solid fa-xmark text-2xl"></i>
                        </button>
                    </div>
                    
                    <div class="p-8 overflow-y-auto">
                        <form action="prestasi.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="action" value="<?= $edit_mode ? 'edit_prestasi' : 'add_prestasi' ?>">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                                <input type="hidden" name="old_img" value="<?= htmlspecialchars($edit_data['gambar']) ?>">
                            <?php endif; ?>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Prestasi / Santri</label>
                                <input type="text" name="nama" required placeholder="Contoh: Juara 1 Tahfidz - Ahmad..." value="<?= htmlspecialchars($edit_data['nama']) ?>"
                                    class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kategori Bidang</label>
                                <select name="kategori" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none appearance-none">
                                    <option value="Akademik" <?= $edit_data['kategori'] == 'Akademik' ? 'selected' : '' ?>>Akademik</option>
                                    <option value="Non-Akademik" <?= $edit_data['kategori'] == 'Non-Akademik' ? 'selected' : '' ?>>Non-Akademik</option>
                                    <option value="Keagamaan" <?= $edit_data['kategori'] == 'Keagamaan' ? 'selected' : '' ?>>Keagamaan / Tahfidz</option>
                                    <option value="Lainnya" <?= $edit_data['kategori'] == 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>

                            <?php if ($edit_mode && !empty($edit_data['gambar'])): ?>
                                <div class="bg-gray-50/80 p-5 rounded-2xl border border-gray-200/80 flex items-center space-x-4">
                                    <div class="h-24 w-24 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 relative shadow-sm flex-shrink-0">
                                        <img src="<?= get_storage_url('prestasi', $edit_data['gambar'], '', true) ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider mb-1">Foto Saat Ini</label>
                                        <p class="text-xs text-gray-500">Anda dapat mengganti foto dengan memilih file baru di bawah ini.</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    <?= $edit_mode ? 'Ganti Foto Prestasi (Opsional)' : 'Upload Foto Prestasi' ?>
                                </label>
                                <div class="border-2 border-dashed border-gray-300 bg-gray-50/50 rounded-2xl p-6 text-center relative cursor-pointer hover:bg-gray-100/80 transition-all">
                                    <input type="file" name="gambar" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        onchange="if(this.files[0]) this.nextElementSibling.nextElementSibling.textContent = 'Terpilih: ' + this.files[0].name;">
                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-secondary mb-2 block"></i>
                                    <p class="text-xs text-gray-600 font-semibold"><?= $edit_mode ? 'Biarkan kosong jika tidak ingin ganti' : 'Pilih gambar (JPG/PNG/WEBP, Max 5MB)' ?></p>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-gray-100">
                                <?php if ($edit_mode): ?>
                                    <a href="prestasi.php" class="mr-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-xl text-sm transition">Batal</a>
                                <?php endif; ?>
                                <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-6 rounded-xl text-sm shadow-md hover:shadow-lg transition-all transform active:scale-[0.99] flex items-center space-x-2">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span><?= $edit_mode ? 'Simpan Perubahan' : 'Simpan Prestasi' ?></span>
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