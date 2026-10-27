<?php
// admin/master_biaya.php
require_once '../config/db.php';

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'bendahara'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

$success_msg = '';
$error_msg = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update Biaya Formulir & Tes Seleksi (1 Tarif Tunggal)
        $biaya_tes = str_replace('.', '', $_POST['biaya_tes_pendaftaran'] ?? '350000');

        $stmt_global = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_pendaftaran', ?) ON DUPLICATE KEY UPDATE nilai = ?");
        $stmt_global->execute([$biaya_tes, $biaya_tes]);

        // Sinkronkan ke alias jenjang
        $stmt_smp = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_smp', ?) ON DUPLICATE KEY UPDATE nilai = ?");
        $stmt_smp->execute([$biaya_tes, $biaya_tes]);

        $stmt_sma = $db->prepare("INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_sma', ?) ON DUPLICATE KEY UPDATE nilai = ?");
        $stmt_sma->execute([$biaya_tes, $biaya_tes]);

        // Update Rincian Biaya 2 Kelompok (SMP IT & SMA IT)
        if (isset($_POST['biaya']) && is_array($_POST['biaya'])) {
            $stmt_update = $db->prepare("UPDATE master_biaya_pendidikan SET deskripsi = ?, uang_pangkal = ?, seragam = ?, kegiatan = ?, spp_bulanan = ?, catatan = ? WHERE id = ?");
            foreach ($_POST['biaya'] as $id => $data) {
                $uang_pangkal = str_replace('.', '', $data['uang_pangkal']);
                $seragam = str_replace('.', '', $data['seragam']);
                $kegiatan = str_replace('.', '', $data['kegiatan']);
                $spp_bulanan = str_replace('.', '', $data['spp_bulanan']);
                
                $stmt_update->execute([
                    $data['deskripsi'],
                    $uang_pangkal,
                    $seragam,
                    $kegiatan,
                    $spp_bulanan,
                    $data['catatan'],
                    $id
                ]);
            }
        }
        
        $db->commit();
        $success_msg = "Pengaturan Master Biaya berhasil diperbarui!";
    } catch (Exception $e) {
        $db->rollBack();
        $error_msg = "Gagal menyimpan perubahan: " . $e->getMessage();
    }
}

// Ambil Biaya Formulir & Tes Seleksi (1 Tarif)
$biaya_tes_pendaftaran = function_exists('get_biaya_tes') ? get_biaya_tes() : 350000;

// Ambil Data Rincian Biaya (2 Kelompok: SMP IT & SMA IT)
$biaya_pendidikan = $db->query("SELECT * FROM master_biaya_pendidikan ORDER BY id ASC")->fetchAll();

$page_title = 'Kelola Master Biaya - Admin Babul Jannah';
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        
        <!-- Header -->
        <?php include 'includes/header.php'; ?>

        <!-- Scrollable Main Container -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 lg:p-10 space-y-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-extrabold text-primary flex items-center">
                        <i class="fa-solid fa-money-bill-wave text-secondary mr-3"></i> Master Biaya Pendidikan
                    </h1>
                    <p class="text-gray-500 mt-2 font-medium">Kelola biaya formulir seleksi (1 tarif) dan rincian biaya masuk daftar ulang (2 kelompok: SMP IT &amp; SMA IT).</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-5 rounded-2xl shadow-sm flex items-center">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-xl mr-3"></i>
                    <p class="font-bold text-sm"><?= htmlspecialchars($success_msg) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 p-5 rounded-2xl shadow-sm flex items-center">
                    <i class="fa-solid fa-circle-exclamation text-red-600 text-xl mr-3"></i>
                    <p class="font-bold text-sm"><?= htmlspecialchars($error_msg) ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-8">
                
                <!-- Biaya Tes Seleksi (1 Tarif Tunggal) -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80">
                    <h2 class="text-lg font-bold text-gray-800 mb-2 border-b border-gray-100 pb-3 flex items-center">
                        <i class="fa-solid fa-tags text-primary mr-2"></i>Biaya Formulir &amp; Ujian Seleksi (1 Tarif)
                    </h2>
                    <p class="text-xs text-gray-500 mb-5">Biaya pendaftaran dan tes seleksi awal calon santri yang berlaku untuk seluruh jenjang.</p>
                    
                    <div class="max-w-md bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Biaya Pendaftaran / Tes Seleksi (Rp)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 font-bold text-xs">Rp</span>
                            <input type="text" name="biaya_tes_pendaftaran" value="<?= number_format($biaya_tes_pendaftaran, 0, '', '.') ?>" class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm font-bold text-gray-800 format-rupiah" required>
                        </div>
                    </div>
                </div>

                <!-- Rincian Biaya Per Jenjang -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200/80">
                    <h2 class="text-lg font-bold text-gray-800 mb-6 border-b border-gray-100 pb-3 flex items-center">
                        <i class="fa-solid fa-list-check text-primary mr-2"></i>Rincian Biaya Masuk & Daftar Ulang (Per Jenjang)
                    </h2>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <?php foreach($biaya_pendidikan as $bp): ?>
                        <div class="border border-gray-200 rounded-2xl p-5 bg-gray-50">
                            <h3 class="text-md font-bold text-primary mb-4 bg-primary/10 px-3 py-2 rounded-lg"><?= htmlspecialchars($bp['jenjang']) ?></h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Deskripsi Singkat</label>
                                    <textarea name="biaya[<?= $bp['id'] ?>][deskripsi]" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm"><?= htmlspecialchars($bp['deskripsi']) ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Uang Pangkal / Gedung</label>
                                        <input type="text" name="biaya[<?= $bp['id'] ?>][uang_pangkal]" value="<?= number_format($bp['uang_pangkal'], 0, '', '.') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm format-rupiah">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Seragam & Asrama</label>
                                        <input type="text" name="biaya[<?= $bp['id'] ?>][seragam]" value="<?= number_format($bp['seragam'], 0, '', '.') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm format-rupiah">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kegiatan per Semester</label>
                                        <input type="text" name="biaya[<?= $bp['id'] ?>][kegiatan]" value="<?= number_format($bp['kegiatan'], 0, '', '.') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm format-rupiah">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">SPP Bulanan</label>
                                        <input type="text" name="biaya[<?= $bp['id'] ?>][spp_bulanan]" value="<?= number_format($bp['spp_bulanan'], 0, '', '.') ?>" class="w-full px-3 py-2 border border-emerald-300 bg-emerald-50 rounded-lg focus:ring-primary focus:border-primary text-sm format-rupiah font-bold text-emerald-700">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Tambahan (Cicilan, dll)</label>
                                    <input type="text" name="biaya[<?= $bp['id'] ?>][catatan]" value="<?= htmlspecialchars($bp['catatan']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-8 rounded-xl shadow-lg transition duration-200 flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Simpan Perubahan Biaya
                    </button>
                </div>
            </form>
        </main>
    </div>

    <!-- Script for Rupiah Formatting -->
    <script>
        document.querySelectorAll('.format-rupiah').forEach(input => {
            const formatFn = function() {
                let value = this.value.replace(/[^,\d]/g, '').toString();
                let split = value.split(',');
                let sisa = split[0].length % 3;
                let rupiah = split[0].substr(0, sisa);
                let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

                if (ribuan) {
                    let separator = sisa ? '.' : '';
                    rupiah += separator + ribuan.join('.');
                }

                rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
                this.value = rupiah;
            };
            input.addEventListener('keyup', formatFn);
            input.addEventListener('input', formatFn);
        });
    </script>
</body>
</html>
