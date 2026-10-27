<?php
// admin/gelombang.php
require_once '../config/db.php';

// Proteksi// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

$error = '';
$success = '';

// 1. Tangani Aksi Simpan Gelombang Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_gelombang') {
    $nama_gelombang = sanitize($_POST['nama_gelombang'] ?? '');
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
    $tgl_buka = sanitize($_POST['tgl_buka'] ?? '');
    $tgl_tutup = sanitize($_POST['tgl_tutup'] ?? '');
    $tgl_ujian = sanitize($_POST['tgl_ujian'] ?? '');
    $tgl_pengumuman = sanitize($_POST['tgl_pengumuman'] ?? '');
    $jenjang_dibuka = sanitize($_POST['jenjang_dibuka'] ?? 'Semua');
    $status = sanitize($_POST['status'] ?? 'nonaktif');
    
    if (!empty($nama_gelombang) && !empty($tahun_ajaran) && !empty($tgl_buka) && !empty($tgl_tutup) && !empty($tgl_ujian) && !empty($tgl_pengumuman)) {
        try {
            // Jika gelombang baru diset aktif, biarkan gelombang lain tetap ada (tidak perlu auto-nonaktif)
            
            $stmt = $db->prepare("INSERT INTO gelombang (nama_gelombang, tahun_ajaran, tgl_buka, tgl_tutup, tgl_ujian, tgl_pengumuman, jenjang_dibuka, status) VALUES (:nama, :ta, :buka, :tutup, :ujian, :pengumuman, :jenjang, :status)");
            $stmt->execute([
                'nama' => $nama_gelombang,
                'ta' => $tahun_ajaran,
                'buka' => $tgl_buka,
                'tutup' => $tgl_tutup,
                'ujian' => $tgl_ujian,
                'pengumuman' => $tgl_pengumuman,
                'jenjang' => $jenjang_dibuka,
                'status' => $status
            ]);
            
            $success = "Gelombang pendaftaran baru berhasil ditambahkan.";
        } catch (PDOException $e) {
            $error = "Gagal menyimpan gelombang: " . $e->getMessage();
        }
    } else {
        $error = "Silakan lengkapi semua field formulir.";
    }
}

// 2. Tangani Aksi Toggle Status Gelombang
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);
    $current_status = sanitize($_GET['current'] ?? '');
    
    if ($id > 0) {
        try {
            if ($current_status === 'aktif') {
                $stmt = $db->prepare("UPDATE gelombang SET status = 'nonaktif' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success = "Gelombang berhasil dinonaktifkan.";
            } else {
                // Aktifkan gelombang ini tanpa mematikan yang lain
                $stmt = $db->prepare("UPDATE gelombang SET status = 'aktif' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success = "Gelombang berhasil diaktifkan dan akan ditampilkan di jadwal publik.";
            }
        } catch (PDOException $e) {
            $error = "Gagal mengubah status gelombang: " . $e->getMessage();
        }
    }
}

// 3. Tangani Aksi Hapus Gelombang
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM gelombang WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $success = "Gelombang pendaftaran berhasil dihapus.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus gelombang (mungkin gelombang ini sudah memiliki data pendaftar): " . $e->getMessage();
        }
    }
}

// 4. Tangani Aksi Edit Gelombang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_gelombang') {
    $id = (int)($_POST['id'] ?? 0);
    $nama_gelombang = sanitize($_POST['nama_gelombang'] ?? '');
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
    $tgl_buka = sanitize($_POST['tgl_buka'] ?? '');
    $tgl_tutup = sanitize($_POST['tgl_tutup'] ?? '');
    $tgl_ujian = sanitize($_POST['tgl_ujian'] ?? '');
    $tgl_pengumuman = sanitize($_POST['tgl_pengumuman'] ?? '');
    $jenjang_dibuka = sanitize($_POST['jenjang_dibuka'] ?? 'Semua');
    $status = sanitize($_POST['status'] ?? 'nonaktif');
    
    if ($id > 0 && !empty($nama_gelombang) && !empty($tahun_ajaran) && !empty($tgl_buka) && !empty($tgl_tutup) && !empty($tgl_ujian) && !empty($tgl_pengumuman)) {
        try {
            // Tidak auto-nonaktifkan yang lain saat edit agar multiple gelombang bisa tampil di publik
            
            $stmt = $db->prepare("UPDATE gelombang SET nama_gelombang = :nama, tahun_ajaran = :ta, tgl_buka = :buka, tgl_tutup = :tutup, tgl_ujian = :ujian, tgl_pengumuman = :pengumuman, jenjang_dibuka = :jenjang, status = :status WHERE id = :id");
            $stmt->execute([
                'nama' => $nama_gelombang,
                'ta' => $tahun_ajaran,
                'buka' => $tgl_buka,
                'tutup' => $tgl_tutup,
                'ujian' => $tgl_ujian,
                'pengumuman' => $tgl_pengumuman,
                'jenjang' => $jenjang_dibuka,
                'status' => $status,
                'id' => $id
            ]);
            
            $success = "Data dan jadwal gelombang berhasil diperbarui.";
            header("Location: gelombang.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = "Gagal memperbarui gelombang: " . $e->getMessage();
        }
    } else {
        $error = "Silakan lengkapi semua field formulir edit.";
    }
}

// Ambil pesan dari URL jika diredirect
if (isset($_GET['success']) && empty($success)) {
    $success = sanitize($_GET['success']);
}

// Ambil semua data gelombang
$stmt_all = $db->query("SELECT * FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_all->fetchAll();

// Cek apakah mode Edit aktif
$edit_data = null;
if (isset($_GET['edit_id']) && (int)$_GET['edit_id'] > 0) {
    $stmt_edit = $db->prepare("SELECT * FROM gelombang WHERE id = :id");
    $stmt_edit->execute(['id' => (int)$_GET['edit_id']]);
    $edit_data = $stmt_edit->fetch();
}
?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        
        <?php include 'includes/header.php'; ?>

        <!-- Scrollable content -->
        <main class="flex-grow p-6 overflow-y-auto space-y-6">
            
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

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Form Tambah/Edit Gelombang -->
                <div class="lg:col-span-5 bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6 h-fit">
                    <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center">
                            <?php if ($edit_data): ?>
                                <i class="fa-solid fa-pen-to-square mr-2 text-blue-600"></i> Edit Jadwal Gelombang
                            <?php else: ?>
                                <i class="fa-solid fa-calendar-plus mr-2 text-secondary"></i> Tambah Gelombang Baru
                            <?php endif; ?>
                        </h2>
                        <?php if ($edit_data): ?>
                            <a href="gelombang.php" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg font-bold transition flex items-center">
                                <i class="fa-solid fa-xmark mr-1"></i> Batal
                            </a>
                        <?php endif; ?>
                    </div>
                    <form action="gelombang.php" method="POST" class="space-y-5">
                        <?php if ($edit_data): ?>
                            <input type="hidden" name="action" value="edit_gelombang">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add_gelombang">
                        <?php endif; ?>
                        
                        <div>
                            <label for="nama_gelombang" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Gelombang</label>
                            <input type="text" name="nama_gelombang" id="nama_gelombang" required placeholder="Contoh: Gelombang 1" value="<?= $edit_data['nama_gelombang'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                        </div>

                        <div>
                            <label for="tahun_ajaran" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" id="tahun_ajaran" required placeholder="Contoh: 2026/2027" value="<?= $edit_data['tahun_ajaran'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tgl_buka" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal Buka</label>
                                <input type="date" name="tgl_buka" id="tgl_buka" required value="<?= $edit_data['tgl_buka'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                            <div>
                                <label for="tgl_tutup" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal Tutup</label>
                                <input type="date" name="tgl_tutup" id="tgl_tutup" required value="<?= $edit_data['tgl_tutup'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tgl_ujian" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal Ujian</label>
                                <input type="date" name="tgl_ujian" id="tgl_ujian" required value="<?= $edit_data['tgl_ujian'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                            <div>
                                <label for="tgl_pengumuman" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tgl Pengumuman</label>
                                <input type="date" name="tgl_pengumuman" id="tgl_pengumuman" required value="<?= $edit_data['tgl_pengumuman'] ?? '' ?>" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="jenjang_dibuka" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenjang yang Dibuka</label>
                            <select name="jenjang_dibuka" id="jenjang_dibuka" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                                <option value="Semua" <?= ($edit_data && ($edit_data['jenjang_dibuka'] ?? 'Semua') === 'Semua') ? 'selected' : '' ?>>Semua Jenjang (SMP & SMA)</option>
                                <option value="Hanya SMP" <?= ($edit_data && ($edit_data['jenjang_dibuka'] ?? '') === 'Hanya SMP') ? 'selected' : '' ?>>Hanya SMP IT</option>
                                <option value="Hanya SMA" <?= ($edit_data && ($edit_data['jenjang_dibuka'] ?? '') === 'Hanya SMA') ? 'selected' : '' ?>>Hanya SMA IT</option>
                            </select>
                        </div>

                        <div>
                            <label for="status" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Awal</label>
                            <select name="status" id="status" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-xl px-4 py-3 text-sm text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                                <option value="nonaktif" <?= ($edit_data && $edit_data['status'] === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                                <option value="aktif" <?= ($edit_data && $edit_data['status'] === 'aktif') ? 'selected' : '' ?>>Aktif (Tampilkan di Jadwal Publik)</option>
                            </select>
                        </div>

                        <?php if ($edit_data): ?>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl text-sm shadow-md hover:shadow-lg transition-all transform active:scale-[0.99] flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Simpan Perubahan Jadwal</span>
                            </button>
                        <?php else: ?>
                            <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-bold py-3.5 rounded-xl text-sm shadow-md hover:shadow-lg transition-all transform active:scale-[0.99] flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Simpan Gelombang</span>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Right: Daftar Gelombang Table -->
                <div class="lg:col-span-7 bg-white rounded-3xl shadow-sm border border-gray-200/80 p-8 space-y-6">
                    <div class="border-b border-gray-100 pb-4">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center"><i class="fa-solid fa-list mr-2 text-secondary"></i> Daftar Gelombang Pendaftaran</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 uppercase tracking-wider font-bold text-xs">
                                    <th class="px-5 py-4">Nama Gelombang</th>
                                    <th class="px-5 py-4">Jenjang</th>
                                    <th class="px-5 py-4">Tahun Ajaran</th>
                                    <th class="px-5 py-4">Periode Pendaftaran</th>
                                    <th class="px-5 py-4">Tgl Ujian</th>
                                    <th class="px-5 py-4">Tgl Pengumuman</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                                <?php if (count($gelombang_list) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="px-5 py-8 text-center text-gray-400">Belum ada gelombang pendaftaran dibuat.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($gelombang_list as $g): ?>
                                        <tr class="hover:bg-gray-50/80 transition-colors <?= ($edit_data && $edit_data['id'] == $g['id']) ? 'bg-blue-50/60' : '' ?>">
                                            <td class="px-5 py-4 font-bold text-gray-900"><?= $g['nama_gelombang'] ?></td>
                                            <td class="px-5 py-4 font-semibold">
                                                <span class="bg-gray-100 text-gray-700 text-[10px] px-2 py-1 rounded-md border border-gray-200"><?= htmlspecialchars($g['jenjang_dibuka'] ?? 'Semua') ?></span>
                                            </td>
                                            <td class="px-5 py-4 font-semibold text-gray-700"><?= $g['tahun_ajaran'] ?></td>
                                            <td class="px-5 py-4 text-gray-600 text-xs font-semibold">
                                                <?= date('d/m/Y', strtotime($g['tgl_buka'])) ?> - <?= date('d/m/Y', strtotime($g['tgl_tutup'])) ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <?php if (!empty($g['tgl_ujian'])): ?>
                                                    <span class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-lg border border-blue-200/60 font-bold">
                                                        <?= date('d/m/Y', strtotime($g['tgl_ujian'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <?php if (!empty($g['tgl_pengumuman'])): ?>
                                                    <span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-lg border border-amber-200/60 font-bold">
                                                        <?= date('d/m/Y', strtotime($g['tgl_pengumuman'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <?php if ($g['status'] === 'aktif'): ?>
                                                    <span class="bg-emerald-50 text-emerald-800 text-xs px-3 py-1 rounded-full border border-emerald-200 font-bold flex items-center w-fit"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>Aktif</span>
                                                <?php else: ?>
                                                    <span class="bg-gray-100 text-gray-500 text-xs px-3 py-1 rounded-full font-bold">Nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4 text-center space-x-2">
                                                <a href="gelombang.php?edit_id=<?= $g['id'] ?>" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 transition" title="Edit Jadwal & Tanggal">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                    <span>Edit</span>
                                                </a>
                                                <a href="gelombang.php?action=toggle_status&id=<?= $g['id'] ?>&current=<?= $g['status'] ?>" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 transition" title="Ubah Status Aktif">
                                                    <span>Toggle</span>
                                                </a>
                                                <a href="gelombang.php?action=delete&id=<?= $g['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus gelombang ini?')" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-red-50 hover:bg-red-100 text-red-700 border border-red-200/60 transition" title="Hapus Gelombang">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

</body>
</html>
