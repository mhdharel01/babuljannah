<?php
// admin/edit_pendaftar.php
require_once '../config/db.php';

// Cek login
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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: pendaftar.php");
    exit();
}

// 1. Proses Update Jika Ada Submit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_data'])) {
    try {
        $jalur_pendaftaran = sanitize($_POST['jalur_pendaftaran'] ?? '');
        $kategori_santri = sanitize($_POST['kategori_santri'] ?? 'Baru');
        
        // Data Santri
        $nama_santri = sanitize($_POST['nama_santri'] ?? '');
        $nik_santri = sanitize($_POST['nik_santri'] ?? '');
        $nisn = sanitize($_POST['nisn'] ?? '');
        $no_kk = sanitize($_POST['no_kk'] ?? '');
        $tempat_lahir = sanitize($_POST['tempat_lahir'] ?? '');
        $tgl_lahir = sanitize($_POST['tgl_lahir'] ?? '');
        $jk = sanitize($_POST['jk'] ?? '');
        $status_anak = sanitize($_POST['status_anak'] ?? '');
        $berkebutuhan_khusus = sanitize($_POST['berkebutuhan_khusus'] ?? 'Tidak');
        $pip = sanitize($_POST['pip'] ?? 'Tidak');
        $provinsi = sanitize($_POST['provinsi'] ?? '');
        $kabupaten = sanitize($_POST['kabupaten'] ?? '');
        $kecamatan = sanitize($_POST['kecamatan'] ?? '');
        $kelurahan = sanitize($_POST['kelurahan'] ?? '');
        $alamat_lengkap = sanitize($_POST['alamat_lengkap'] ?? '');
        
        // Data Orang Tua / Wali
        $nama_ayah = sanitize($_POST['nama_ayah'] ?? '');
        $nik_ayah = sanitize($_POST['nik_ayah'] ?? '');
        $thn_lahir_ayah = sanitize($_POST['thn_lahir_ayah'] ?? '');
        $pendidikan_ayah = sanitize($_POST['pendidikan_ayah'] ?? '');
        $pekerjaan_ayah = sanitize($_POST['pekerjaan_ayah'] ?? '');
        $penghasilan_ayah = sanitize($_POST['penghasilan_ayah'] ?? '');
        $wa_ayah = sanitize($_POST['wa_ayah'] ?? '');
        
        $nama_ibu = sanitize($_POST['nama_ibu'] ?? '');
        $nik_ibu = sanitize($_POST['nik_ibu'] ?? '');
        $thn_lahir_ibu = sanitize($_POST['thn_lahir_ibu'] ?? '');
        $pendidikan_ibu = sanitize($_POST['pendidikan_ibu'] ?? '');
        $pekerjaan_ibu = sanitize($_POST['pekerjaan_ibu'] ?? '');
        $penghasilan_ibu = sanitize($_POST['penghasilan_ibu'] ?? '');
        $wa_ibu = sanitize($_POST['wa_ibu'] ?? '');
        
        $nama_wali = sanitize($_POST['nama_wali'] ?? '');
        $wa_wali = sanitize($_POST['wa_wali'] ?? '');
        
        // Data Sekolah Asal & Fisik
        $jenis_sekolah_asal = sanitize($_POST['jenis_sekolah_asal'] ?? '');
        $nama_sekolah_asal = sanitize($_POST['nama_sekolah_asal'] ?? '');
        $alamat_sekolah_asal = sanitize($_POST['alamat_sekolah_asal'] ?? '');
        $npsn_sekolah_asal = sanitize($_POST['npsn_sekolah_asal'] ?? '');
        
        $tinggi_badan = !empty($_POST['tinggi_badan']) ? (int)$_POST['tinggi_badan'] : null;
        $berat_badan = !empty($_POST['berat_badan']) ? (int)$_POST['berat_badan'] : null;
        $lingkar_kepala = !empty($_POST['lingkar_kepala']) ? (int)$_POST['lingkar_kepala'] : null;
        $riwayat_penyakit = sanitize($_POST['riwayat_penyakit'] ?? '');

        if (empty($nama_santri) || empty($nik_santri)) {
            $error = "Nama Santri dan NIK wajib diisi.";
        } else {
            $sql_update = "UPDATE pendaftaran SET 
                jalur_pendaftaran = :jalur_pendaftaran,
                kategori_santri = :kategori_santri,
                nama_santri = :nama_santri, nik_santri = :nik_santri, nisn = :nisn, no_kk = :no_kk, 
                tempat_lahir = :tempat_lahir, tgl_lahir = :tgl_lahir, jk = :jk, status_anak = :status_anak, 
                berkebutuhan_khusus = :berkebutuhan_khusus, pip = :pip,
                provinsi = :provinsi, kabupaten = :kabupaten, kecamatan = :kecamatan, kelurahan = :kelurahan,
                alamat_lengkap = :alamat_lengkap,
                nama_ayah = :nama_ayah, nik_ayah = :nik_ayah, thn_lahir_ayah = :thn_lahir_ayah, 
                pendidikan_ayah = :pendidikan_ayah, pekerjaan_ayah = :pekerjaan_ayah, penghasilan_ayah = :penghasilan_ayah, wa_ayah = :wa_ayah,
                nama_ibu = :nama_ibu, nik_ibu = :nik_ibu, thn_lahir_ibu = :thn_lahir_ibu, 
                pendidikan_ibu = :pendidikan_ibu, pekerjaan_ibu = :pekerjaan_ibu, penghasilan_ibu = :penghasilan_ibu, wa_ibu = :wa_ibu,
                nama_wali = :nama_wali, wa_wali = :wa_wali,
                jenis_sekolah_asal = :jenis_sekolah_asal, nama_sekolah_asal = :nama_sekolah_asal, 
                alamat_sekolah_asal = :alamat_sekolah_asal, npsn_sekolah_asal = :npsn_sekolah_asal,
                tinggi_badan = :tinggi_badan, berat_badan = :berat_badan, lingkar_kepala = :lingkar_kepala, riwayat_penyakit = :riwayat_penyakit
                WHERE id = :id";
                
            $stmt_upd = $db->prepare($sql_update);
            $stmt_upd->execute([
                ':jalur_pendaftaran' => $jalur_pendaftaran,
                ':kategori_santri' => $kategori_santri,
                ':nama_santri' => $nama_santri, ':nik_santri' => $nik_santri, ':nisn' => $nisn, ':no_kk' => $no_kk,
                ':tempat_lahir' => $tempat_lahir, ':tgl_lahir' => $tgl_lahir, ':jk' => $jk, ':status_anak' => $status_anak,
                ':berkebutuhan_khusus' => $berkebutuhan_khusus, ':pip' => $pip,
                ':provinsi' => $provinsi, ':kabupaten' => $kabupaten, ':kecamatan' => $kecamatan, ':kelurahan' => $kelurahan,
                ':alamat_lengkap' => $alamat_lengkap,
                ':nama_ayah' => $nama_ayah, ':nik_ayah' => $nik_ayah, ':thn_lahir_ayah' => $thn_lahir_ayah,
                ':pendidikan_ayah' => $pendidikan_ayah, ':pekerjaan_ayah' => $pekerjaan_ayah, ':penghasilan_ayah' => $penghasilan_ayah, ':wa_ayah' => $wa_ayah,
                ':nama_ibu' => $nama_ibu, ':nik_ibu' => $nik_ibu, ':thn_lahir_ibu' => $thn_lahir_ibu,
                ':pendidikan_ibu' => $pendidikan_ibu, ':pekerjaan_ibu' => $pekerjaan_ibu, ':penghasilan_ibu' => $penghasilan_ibu, ':wa_ibu' => $wa_ibu,
                ':nama_wali' => $nama_wali, ':wa_wali' => $wa_wali,
                ':jenis_sekolah_asal' => $jenis_sekolah_asal, ':nama_sekolah_asal' => $nama_sekolah_asal,
                ':alamat_sekolah_asal' => $alamat_sekolah_asal, ':npsn_sekolah_asal' => $npsn_sekolah_asal,
                ':tinggi_badan' => $tinggi_badan, ':berat_badan' => $berat_badan, ':lingkar_kepala' => $lingkar_kepala, ':riwayat_penyakit' => $riwayat_penyakit,
                ':id' => $id
            ]);
            
            $success = "Data pendaftaran santri berhasil diperbarui.";
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan database: " . $e->getMessage();
    }
}

// 2. Ambil data santri pendaftar
$stmt = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, 'Gelombang Umum') as nama_gelombang, g.tahun_ajaran FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE p.id = :id");
$stmt->execute(['id' => $id]);
$santri = $stmt->fetch();

if (!$santri) {
    header("Location: pendaftar.php");
    exit();
}
?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden font-sans text-gray-800">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-screen overflow-hidden">

        <?php
        $header_custom_title = '
            <a href="pendaftar.php" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-xl transition text-primary mr-4" title="Kembali ke Daftar Pendaftar">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-primary flex items-center">
                    <i class="fa-solid fa-user-pen text-secondary mr-2.5"></i> Edit Data Lengkap Pendaftar
                </h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5 hidden sm:block">Koreksi kesalahan input data santri, orang tua, dan sekolah asal</p>
            </div>';
        $header_custom_right = '
            <span class="bg-primary/10 text-primary px-4 py-2 rounded-xl text-xs font-extrabold border border-primary/20">
                <i class="fa-solid fa-hashtag mr-1"></i> No. Reg: ' . htmlspecialchars($santri['no_registrasi']) . '
            </span>';
        include 'includes/header.php';
        ?>

        <!-- Scrollable Content -->
        <main class="flex-grow overflow-y-auto p-8 bg-gray-100">
            <div class="max-w-5xl mx-auto space-y-8 pb-16">

                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-3xl flex items-center space-x-3 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation text-xl text-red-600 flex-shrink-0"></i>
                        <span class="font-bold text-sm"><?= $error ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-3xl flex items-center space-x-3 shadow-sm">
                        <i class="fa-solid fa-circle-check text-xl text-emerald-600 flex-shrink-0"></i>
                        <span class="font-bold text-sm"><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Edit Lengkap -->
                <form action="edit_pendaftar.php?id=<?= $santri['id'] ?>" method="POST" class="space-y-8">
                    <input type="hidden" name="id" value="<?= $santri['id'] ?>">
                    <input type="hidden" name="update_data" value="1">

                    <!-- Card 1: Informasi Registrasi & Jalur -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-200/80 space-y-6">
                        <h2 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 flex items-center">
                            <i class="fa-solid fa-info-circle text-secondary mr-2.5"></i> Informasi Registrasi & Jalur
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">No. Registrasi</label>
                                <input type="text" value="<?= $santri['no_registrasi'] ?>" readonly class="w-full bg-gray-100 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Gelombang Pendaftaran</label>
                                <input type="text" value="<?= $santri['nama_gelombang'] ?> (T.A <?= $santri['tahun_ajaran'] ?>)" readonly class="w-full bg-gray-100 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jalur Pendidikan <span class="text-red-500">*</span></label>
                                <select name="jalur_pendaftaran" required class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="SMP IT" <?= $santri['jalur_pendaftaran'] === 'SMP IT' ? 'selected' : '' ?>>SMP IT Babul Jannah</option>
                                    <option value="SMA IT" <?= $santri['jalur_pendaftaran'] === 'SMA IT' ? 'selected' : '' ?>>SMA IT Babul Jannah</option>
                                    <option value="Kepondokan" <?= $santri['jalur_pendaftaran'] === 'Kepondokan' ? 'selected' : '' ?>>Kepondokan (Takhasus Tahfizh)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Santri <span class="text-red-500">*</span></label>
                                <select name="kategori_santri" required class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="Baru" <?= (isset($santri['kategori_santri']) && $santri['kategori_santri'] === 'Baru') ? 'selected' : '' ?>>Baru (Reguler)</option>
                                    <option value="Pindahan" <?= (isset($santri['kategori_santri']) && $santri['kategori_santri'] === 'Pindahan') ? 'selected' : '' ?>>Pindahan</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Data Diri Calon Santri -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-200/80 space-y-6">
                        <h2 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 flex items-center">
                            <i class="fa-solid fa-user-graduate text-secondary mr-2.5"></i> Data Diri Calon Santri
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Lengkap Santri <span class="text-red-500">*</span></label>
                                <input type="text" name="nama_santri" value="<?= htmlspecialchars($santri['nama_santri']) ?>" required class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NIK Santri (16 Digit) <span class="text-red-500">*</span></label>
                                <input type="text" name="nik_santri" value="<?= htmlspecialchars($santri['nik_santri']) ?>" required maxlength="16" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NISN</label>
                                <input type="text" name="nisn" value="<?= htmlspecialchars($santri['nisn'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">No. Kartu Keluarga (KK)</label>
                                <input type="text" name="no_kk" value="<?= htmlspecialchars($santri['no_kk'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($santri['tempat_lahir'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal Lahir</label>
                                <input type="date" name="tgl_lahir" value="<?= $santri['tgl_lahir'] ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenis Kelamin</label>
                                <select name="jk" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="Laki-laki" <?= ($santri['jk'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= ($santri['jk'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Anak</label>
                                <input type="text" name="status_anak" value="<?= htmlspecialchars($santri['status_anak'] ?? '') ?>" placeholder="Anak kandung ke-X" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berkebutuhan Khusus?</label>
                                <select name="berkebutuhan_khusus" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="Tidak" <?= ($santri['berkebutuhan_khusus'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                                    <option value="Ya" <?= ($santri['berkebutuhan_khusus'] ?? '') === 'Ya' ? 'selected' : '' ?>>Ya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Penerima PIP?</label>
                                <select name="pip" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="Tidak" <?= ($santri['pip'] ?? 'Tidak') === 'Tidak' ? 'selected' : '' ?>>Tidak</option>
                                    <option value="Ya" <?= ($santri['pip'] ?? '') === 'Ya' ? 'selected' : '' ?>>Ya</option>
                                </select>
                            </div>
                            <!-- Wilayah Domisili -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Provinsi</label>
                                <select name="provinsi" id="provinsi" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" data-selected="<?= htmlspecialchars($santri['provinsi'] ?? '') ?>">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <?php if (!empty($santri['provinsi'])): ?>
                                        <option value="<?= htmlspecialchars($santri['provinsi']) ?>" selected><?= htmlspecialchars($santri['provinsi']) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kabupaten / Kota</label>
                                <select name="kabupaten" id="kabupaten" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" data-selected="<?= htmlspecialchars($santri['kabupaten'] ?? '') ?>">
                                    <option value="">-- Pilih Kab/Kota --</option>
                                    <?php if (!empty($santri['kabupaten'])): ?>
                                        <option value="<?= htmlspecialchars($santri['kabupaten']) ?>" selected><?= htmlspecialchars($santri['kabupaten']) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kecamatan</label>
                                <select name="kecamatan" id="kecamatan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" data-selected="<?= htmlspecialchars($santri['kecamatan'] ?? '') ?>">
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <?php if (!empty($santri['kecamatan'])): ?>
                                        <option value="<?= htmlspecialchars($santri['kecamatan']) ?>" selected><?= htmlspecialchars($santri['kecamatan']) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kelurahan / Desa</label>
                                <select name="kelurahan" id="kelurahan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" data-selected="<?= htmlspecialchars($santri['kelurahan'] ?? '') ?>">
                                    <option value="">-- Pilih Kelurahan --</option>
                                    <?php if (!empty($santri['kelurahan'])): ?>
                                        <option value="<?= htmlspecialchars($santri['kelurahan']) ?>" selected><?= htmlspecialchars($santri['kelurahan']) ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Lengkap (Jalan / RT / RW)</label>
                                <textarea name="alamat_lengkap" rows="3" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition"><?= htmlspecialchars($santri['alamat_lengkap'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Data Orang Tua / Wali -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-200/80 space-y-6">
                        <h2 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 flex items-center">
                            <i class="fa-solid fa-users text-secondary mr-2.5"></i> Data Orang Tua / Wali
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Ayah -->
                            <div class="space-y-4 p-5 bg-gray-50 rounded-2xl border border-gray-200/60">
                                <h3 class="font-bold text-primary text-sm border-b pb-2"><i class="fa-solid fa-user-tie text-secondary mr-1.5"></i> Data Ayah Kandung</h3>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Ayah</label>
                                    <input type="text" name="nama_ayah" value="<?= htmlspecialchars($santri['nama_ayah'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ayah</label>
                                    <input type="text" name="nik_ayah" value="<?= htmlspecialchars($santri['nik_ayah'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                        <input type="text" name="thn_lahir_ayah" value="<?= htmlspecialchars($santri['thn_lahir_ayah'] ?? '') ?>" placeholder="Contoh: 1980" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                        <input type="text" name="pendidikan_ayah" value="<?= htmlspecialchars($santri['pendidikan_ayah'] ?? '') ?>" placeholder="S1/SMA/dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                        <input type="text" name="pekerjaan_ayah" value="<?= htmlspecialchars($santri['pekerjaan_ayah'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                        <input type="text" name="penghasilan_ayah" value="<?= htmlspecialchars($santri['penghasilan_ayah'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">No. WhatsApp Ayah <span class="text-red-500">*</span></label>
                                    <input type="text" name="wa_ayah" value="<?= htmlspecialchars($santri['wa_ayah'] ?? '') ?>" required class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm font-bold text-primary">
                                </div>
                            </div>

                            <!-- Ibu -->
                            <div class="space-y-4 p-5 bg-gray-50 rounded-2xl border border-gray-200/60">
                                <h3 class="font-bold text-primary text-sm border-b pb-2"><i class="fa-solid fa-user text-secondary mr-1.5"></i> Data Ibu Kandung</h3>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Ibu</label>
                                    <input type="text" name="nama_ibu" value="<?= htmlspecialchars($santri['nama_ibu'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ibu</label>
                                    <input type="text" name="nik_ibu" value="<?= htmlspecialchars($santri['nik_ibu'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                        <input type="text" name="thn_lahir_ibu" value="<?= htmlspecialchars($santri['thn_lahir_ibu'] ?? '') ?>" placeholder="Contoh: 1985" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                        <input type="text" name="pendidikan_ibu" value="<?= htmlspecialchars($santri['pendidikan_ibu'] ?? '') ?>" placeholder="S1/SMA/dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                        <input type="text" name="pekerjaan_ibu" value="<?= htmlspecialchars($santri['pekerjaan_ibu'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                        <input type="text" name="penghasilan_ibu" value="<?= htmlspecialchars($santri['penghasilan_ibu'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Ibu</label>
                                    <input type="text" name="wa_ibu" value="<?= htmlspecialchars($santri['wa_ibu'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>

                            <!-- Wali (Opsional) -->
                            <div class="md:col-span-2 p-5 bg-gray-50 rounded-2xl border border-gray-200/60 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <h3 class="font-bold text-primary text-sm"><i class="fa-solid fa-user-shield text-secondary mr-1.5"></i> Data Wali (Opsional / Jika Ada)</h3>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Wali</label>
                                    <input type="text" name="nama_wali" value="<?= htmlspecialchars($santri['nama_wali'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Wali</label>
                                    <input type="text" name="wa_wali" value="<?= htmlspecialchars($santri['wa_wali'] ?? '') ?>" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Sekolah Asal & Data Fisik -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-200/80 space-y-6">
                        <h2 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 flex items-center">
                            <i class="fa-solid fa-school text-secondary mr-2.5"></i> Sekolah Asal & Data Fisik
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenis Sekolah Asal</label>
                                <select name="jenis_sekolah_asal" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                    <option value="SD" <?= ($santri['jenis_sekolah_asal'] ?? '') === 'SD' ? 'selected' : '' ?>>SD (Sekolah Dasar)</option>
                                    <option value="MI" <?= ($santri['jenis_sekolah_asal'] ?? '') === 'MI' ? 'selected' : '' ?>>MI (Madrasah Ibtidaiyah)</option>
                                    <option value="SMP" <?= ($santri['jenis_sekolah_asal'] ?? '') === 'SMP' ? 'selected' : '' ?>>SMP</option>
                                    <option value="MTs" <?= ($santri['jenis_sekolah_asal'] ?? '') === 'MTs' ? 'selected' : '' ?>>MTs (Madrasah Tsanawiyah)</option>
                                    <option value="Pesantren" <?= ($santri['jenis_sekolah_asal'] ?? '') === 'Pesantren' ? 'selected' : '' ?>>Pesantren / PKBM / Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Sekolah Asal</label>
                                <input type="text" name="nama_sekolah_asal" value="<?= htmlspecialchars($santri['nama_sekolah_asal'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NPSN Sekolah Asal</label>
                                <input type="text" name="npsn_sekolah_asal" value="<?= htmlspecialchars($santri['npsn_sekolah_asal'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Sekolah Asal</label>
                                <input type="text" name="alamat_sekolah_asal" value="<?= htmlspecialchars($santri['alamat_sekolah_asal'] ?? '') ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tinggi (cm)</label>
                                    <input type="number" name="tinggi_badan" value="<?= $santri['tinggi_badan'] ?? '' ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berat (kg)</label>
                                    <input type="number" name="berat_badan" value="<?= $santri['berat_badan'] ?? '' ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Lingkar Kepala</label>
                                    <input type="number" name="lingkar_kepala" value="<?= $santri['lingkar_kepala'] ?? '' ?>" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Riwayat Penyakit Berat / Khusus</label>
                                <input type="text" name="riwayat_penyakit" value="<?= htmlspecialchars($santri['riwayat_penyakit'] ?? '') ?>" placeholder="Tidak ada / Sebutkan jika ada" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <a href="pendaftar.php" class="px-6 py-3 rounded-2xl text-sm font-bold bg-gray-200 text-gray-700 hover:bg-gray-300 transition flex items-center space-x-2">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Batal & Kembali</span>
                        </a>
                        <button type="submit" class="px-8 py-3.5 rounded-2xl text-sm font-extrabold bg-primary text-white hover:bg-secondary shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5 flex items-center space-x-2.5">
                            <i class="fa-solid fa-floppy-disk text-md"></i>
                            <span>Simpan Perubahan Data Santri</span>
                        </button>
                    </div>
                </form>

            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const provSelect = document.getElementById('provinsi');
        const kabSelect = document.getElementById('kabupaten');
        const kecSelect = document.getElementById('kecamatan');
        const kelSelect = document.getElementById('kelurahan');

        if (!provSelect || !kabSelect) return;

        const selProv = (provSelect.getAttribute('data-selected') || '').toUpperCase().trim();
        const selKab = (kabSelect.getAttribute('data-selected') || '').toUpperCase().trim();
        const selKec = (kecSelect.getAttribute('data-selected') || '').toUpperCase().trim();
        const selKel = (kelSelect.getAttribute('data-selected') || '').toUpperCase().trim();

        // 38 Provinsi Indonesia Fallback
        const fallbackProvinces = [
            "ACEH", "SUMATERA UTARA", "SUMATERA BARAT", "RIAU", "JAMBI", "SUMATERA SELATAN", "BENGKULU", "LAMPUNG",
            "KEPULAUAN BANGKA BELITUNG", "KEPULAUAN RIAU", "DKI JAKARTA", "JAWA BARAT", "JAWA TENGAH", "DI YOGYAKARTA",
            "JAWA TIMUR", "BANTEN", "BALI", "NUSA TENGGARA BARAT", "NUSA TENGGARA TIMUR", "KALIMANTAN BARAT",
            "KALIMANTAN TENGAH", "KALIMANTAN SELATAN", "KALIMANTAN TIMUR", "KALIMANTAN UTARA", "SULAWESI UTARA",
            "SULAWESI TENGAH", "SULAWESI SELATAN", "SULAWESI TENGGARA", "GORONTALO", "SULAWESI BARAT", "MALUKU",
            "MALUKU UTARA", "PAPUA BARAT", "PAPUA", "PAPUA SELATAN", "PAPUA TENGAH", "PAPUA PEGUNUNGAN", "PAPUA BARAT DAYA"
        ];

        fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
            .then(res => res.json())
            .then(data => {
                let options = '<option value="">-- Pilih Provinsi --</option>';
                let activeProvId = null;
                data.forEach(prov => {
                    const pName = prov.name.toUpperCase();
                    const isSelected = (pName === selProv);
                    if (isSelected) activeProvId = prov.id;
                    options += `<option value="${pName}" data-id="${prov.id}" ${isSelected ? 'selected' : ''}>${pName}</option>`;
                });
                provSelect.innerHTML = options;
                if (activeProvId) loadKabupaten(activeProvId, selKab);
            })
            .catch(() => {
                // Gunakan fallback provinsi jika offline
                let options = '<option value="">-- Pilih Provinsi --</option>';
                fallbackProvinces.forEach(p => {
                    const isSelected = (p === selProv);
                    options += `<option value="${p}" ${isSelected ? 'selected' : ''}>${p}</option>`;
                });
                provSelect.innerHTML = options;
            });

        provSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const provId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kabSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            kecSelect.innerHTML = '<option value="">-- Pilih Kab/Kota Dahulu --</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
            if (provId) loadKabupaten(provId, null);
            else kabSelect.innerHTML = '<option value="">-- Pilih Provinsi Dahulu --</option>';
        });

        function loadKabupaten(provId, autoSelectName) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                .then(res => res.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
                    let activeKabId = null;
                    data.forEach(kab => {
                        const kName = kab.name.toUpperCase();
                        const isSelected = (kName === autoSelectName);
                        if (isSelected) activeKabId = kab.id;
                        options += `<option value="${kName}" data-id="${kab.id}" ${isSelected ? 'selected' : ''}>${kName}</option>`;
                    });
                    kabSelect.innerHTML = options;
                    if (activeKabId) loadKecamatan(activeKabId, selKec);
                })
                .catch(() => {
                    kabSelect.innerHTML = '<option value="">-- Gagal memuat daftar kota --</option>';
                });
        }

        kabSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const kabId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kecSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
            if (kabId) loadKecamatan(kabId, null);
            else kecSelect.innerHTML = '<option value="">-- Pilih Kab/Kota Dahulu --</option>';
        });

        function loadKecamatan(kabId, autoSelectName) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${kabId}.json`)
                .then(res => res.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kecamatan --</option>';
                    let activeKecId = null;
                    data.forEach(kec => {
                        const kcName = kec.name.toUpperCase();
                        const isSelected = (kcName === autoSelectName);
                        if (isSelected) activeKecId = kec.id;
                        options += `<option value="${kcName}" data-id="${kec.id}" ${isSelected ? 'selected' : ''}>${kcName}</option>`;
                    });
                    kecSelect.innerHTML = options;
                    if (activeKecId) loadKelurahan(activeKecId, selKel);
                });
        }

        kecSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const kecId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kelSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            if (kecId) loadKelurahan(kecId, null);
            else kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
        });

        function loadKelurahan(kecId, autoSelectName) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${kecId}.json`)
                .then(res => res.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kelurahan --</option>';
                    data.forEach(kel => {
                        const klName = kel.name.toUpperCase();
                        const isSelected = (klName === autoSelectName);
                        options += `<option value="${klName}" data-id="${kel.id}" ${isSelected ? 'selected' : ''}>${klName}</option>`;
                    });
                    kelSelect.innerHTML = options;
                });
        }
    });
    </script>
</body>
</html>
