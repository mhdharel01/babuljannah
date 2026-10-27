<?php
// admin/berita.php
require_once '../config/db.php';
require_once '../includes/sitemap_generator.php';

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
        $res_e = $stmt_e->fetch();
        if ($res_e) {
            $edit_mode = true;
            $edit_data = $res_e;
        }
    }
}

// 1. Tangani Aksi Tambah & Edit Berita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_berita' || $_POST['action'] === 'edit_berita') {
        $judul = sanitize($_POST['judul'] ?? '');
        $kutipan = sanitize($_POST['kutipan'] ?? '');
        $isi = $_POST['isi'] ?? ''; // Don't over-sanitize HTML content
        
        if (!empty($judul) && !empty($kutipan) && !empty($isi)) {
            $gambar_name = null;
            
            // Handle file upload
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/berita/';
                $gambar_name = upload_and_convert_to_webp('gambar', $upload_dir, 'berita');
                if (!$gambar_name) {
                    $error = "File gambar tidak valid atau gagal diproses. Gunakan JPG/PNG/WEBP (max 5MB).";
                }
            }
            
            if (empty($error)) {
                try {
                    if ($_POST['action'] === 'add_berita') {
                        $img_final = $gambar_name ?? 'berita_1.png';
                        $stmt = $db->prepare("INSERT INTO berita (judul, kutipan, isi, gambar) VALUES (:judul, :kutipan, :isi, :gambar)");
                        $stmt->execute([
                            'judul' => $judul,
                            'kutipan' => $kutipan,
                            'isi' => $isi,
                            'gambar' => $img_final
                        ]);
                        $success = "Berita baru berhasil dipublikasikan.";
                    } elseif ($_POST['action'] === 'edit_berita') {
                        $id_edit = (int)($_POST['id'] ?? 0);
                        if ($gambar_name) {
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
                        $success = "Berita berhasil diperbarui.";
                        $edit_mode = false;
                    }
                    
                    // Generate ulang sitemap
                    generate_sitemap($db);
                    
                } catch (PDOException $e) {
                    $error = "Gagal menyimpan berita: " . $e->getMessage();
                }
            }
        } else {
            $error = "Silakan lengkapi semua field formulir.";
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
            $success = "Berita berhasil dihapus.";
            
            // Generate ulang sitemap
            generate_sitemap($db);
        } catch (PDOException $e) {
            $error = "Gagal menghapus berita: " . $e->getMessage();
        }
    }
}

// Ambil semua berita
$stmt_all = $db->query("SELECT * FROM berita ORDER BY id DESC");
$berita_list = $stmt_all->fetchAll();
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

            <!-- Tabel Daftar Berita (Full Width) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center"><i class="fa-solid fa-newspaper mr-2 text-secondary"></i> Daftar Berita (<?= count($berita_list) ?>)</h2>
                    <button onclick="document.getElementById('modal-berita').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white font-bold px-4 py-2 rounded-xl text-sm transition shadow-sm flex items-center space-x-2">
                        <i class="fa-solid fa-plus"></i><span>Tambah Berita</span>
                    </button>
                </div>
                
                <?php if (count($berita_list) === 0): ?>
                    <div class="text-center py-16 text-gray-400 text-sm">
                        <i class="fa-regular fa-newspaper text-5xl mb-3 block opacity-30"></i>
                        Belum ada berita dipublikasikan.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($berita_list as $b): ?>
                            <div class="border border-gray-200/80 rounded-2xl overflow-hidden hover:shadow-lg transition-all group relative bg-white flex flex-col h-full">
                                <!-- Action buttons -->
                                <div class="absolute top-3 right-3 flex flex-col gap-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="berita.php?edit=<?= $b['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-xl bg-blue-500 text-white hover:bg-blue-600 shadow-md transition" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="berita.php?action=delete&id=<?= $b['id'] ?>" onclick="return confirm('Hapus berita ini?')" class="w-8 h-8 flex items-center justify-center rounded-xl bg-red-500 text-white hover:bg-red-600 shadow-md transition" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                                
                                <div class="h-48 w-full bg-gray-100 relative overflow-hidden">
                                    <img src="<?= get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $b['gambar']), '', true) ?>" alt="<?= htmlspecialchars($b['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                </div>
                                <div class="p-5 flex-grow flex flex-col">
                                    <div class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-2 flex items-center">
                                        <i class="fa-regular fa-calendar mr-1.5"></i> <?= format_tanggal($b['created_at']) ?>
                                    </div>
                                    <h4 class="font-extrabold text-gray-900 text-base leading-snug mb-2 line-clamp-2"><?= $b['judul'] ?></h4>
                                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-3 mb-4 flex-grow"><?= $b['kutipan'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- POP-UP MODAL TAMBAH/EDIT BERITA -->
            <div id="modal-berita" class="<?= $edit_mode ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/80 w-full max-w-4xl my-auto relative max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-gray-100 px-8 py-5 flex-shrink-0">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center">
                            <?php if ($edit_mode): ?>
                                <i class="fa-solid fa-pen-to-square mr-2 text-secondary"></i> Edit Berita
                            <?php else: ?>
                                <i class="fa-solid fa-plus-circle mr-2 text-secondary"></i> Tambah Berita Baru
                            <?php endif; ?>
                        </h2>
                        <button onclick="document.getElementById('modal-berita').classList.add('hidden'); <?php if($edit_mode) echo "window.location.href='berita.php'"; ?>" class="text-gray-400 hover:text-red-500 transition focus:outline-none">
                            <i class="fa-solid fa-xmark text-2xl"></i>
                        </button>
                    </div>
                    
                    <div class="p-8 overflow-y-auto">
                        <form action="berita.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="<?= $edit_mode ? 'edit_berita' : 'add_berita' ?>">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                            <?php endif; ?>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Judul Berita</label>
                                <input type="text" name="judul" required placeholder="Judul berita..." value="<?= htmlspecialchars($edit_data['judul']) ?>"
                                    class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kutipan Singkat</label>
                                <textarea name="kutipan" required rows="2" placeholder="Ringkasan singkat berita..."
                                    class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-none"><?= htmlspecialchars($edit_data['kutipan']) ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Isi Konten Berita</label>
                                <textarea name="isi" required rows="8" placeholder="Ketik isi berita lengkap di sini..."
                                    class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none resize-y"><?= htmlspecialchars($edit_data['isi']) ?></textarea>
                            </div>

                            <?php if ($edit_mode && !empty($edit_data['gambar'])): ?>
                                <div class="bg-gray-50/80 p-5 rounded-2xl border border-gray-200/80">
                                    <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider mb-3">
                                        <i class="fa-solid fa-image mr-1.5 text-secondary"></i> Foto Saat Ini
                                    </label>
                                    <div class="h-40 w-40 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 relative shadow-sm">
                                        <img src="<?= get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $edit_data['gambar']), '', true) ?>" class="w-full h-full object-cover">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    <?= $edit_mode ? 'Ganti Gambar Berita (Opsional)' : 'Upload Gambar Berita' ?>
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
                                    <a href="berita.php" class="mr-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-xl text-sm transition">Batal</a>
                                <?php endif; ?>
                                <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-6 rounded-xl text-sm shadow-md hover:shadow-lg transition-all transform active:scale-[0.99] flex items-center space-x-2">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span><?= $edit_mode ? 'Simpan Perubahan' : 'Publikasikan Berita' ?></span>
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