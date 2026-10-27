<?php
// admin/tambah_pendaftar.php
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

// Ambil list gelombang
$stmt_gel = $db->query("SELECT id, nama_gelombang, tahun_ajaran FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $kategori_santri = sanitize($_POST['kategori_santri'] ?? 'Baru');
        $gelombang_id = (int)($_POST['gelombang_id'] ?? 0);
        $jalur_pendaftaran = sanitize($_POST['jalur_pendaftaran'] ?? '');
        
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
        
        $status_pembayaran = sanitize($_POST['status_pembayaran'] ?? 'Belum Bayar');
        $metode_pembayaran = sanitize($_POST['metode_pembayaran'] ?? 'Tunai/Cash');
        $status_seleksi = sanitize($_POST['status_seleksi'] ?? 'Belum Seleksi');
        
        if (empty($nama_santri) || empty($nik_santri) || $gelombang_id === 0) {
            $error = "Nama, NIK, dan Gelombang wajib diisi.";
        } else {
            // Generate No Registrasi (PPBJ-YYYY-XXX)
            $tahun = date('Y');
            $stmt_last = $db->query("SELECT no_registrasi FROM pendaftaran WHERE no_registrasi LIKE 'PPBJ-$tahun-%' ORDER BY id DESC LIMIT 1");
            $last_reg = $stmt_last->fetchColumn();
            
            if ($last_reg) {
                $last_number = (int)substr($last_reg, 10);
                $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $new_number = '001';
            }
            $no_registrasi = "PPBJ-$tahun-$new_number";

            $sql = "INSERT INTO pendaftaran (
                no_registrasi, kategori_santri, gelombang_id, jalur_pendaftaran,
                nama_santri, nik_santri, nisn, no_kk, tempat_lahir, tgl_lahir, jk, 
                status_anak, berkebutuhan_khusus, pip,
                provinsi, kabupaten, kecamatan, kelurahan, alamat_lengkap,
                nama_ayah, nik_ayah, thn_lahir_ayah, pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, wa_ayah,
                nama_ibu, nik_ibu, thn_lahir_ibu, pendidikan_ibu, pekerjaan_ibu, penghasilan_ibu, wa_ibu,
                nama_wali, wa_wali,
                jenis_sekolah_asal, nama_sekolah_asal, alamat_sekolah_asal, npsn_sekolah_asal,
                tinggi_badan, berat_badan, lingkar_kepala, riwayat_penyakit,
                status_pembayaran, metode_pembayaran, tgl_lunas, status_seleksi
            ) VALUES (
                :no_reg, :kategori, :gel_id, :jalur,
                :nama, :nik, :nisn, :kk, :tmp_lahir, :tgl_lahir, :jk, 
                :status_anak, :berkebutuhan_khusus, :pip,
                :provinsi, :kabupaten, :kecamatan, :kelurahan, :alamat,
                :nama_ayah, :nik_ayah, :thn_lahir_ayah, :pendidikan_ayah, :pekerjaan_ayah, :penghasilan_ayah, :wa_ayah,
                :nama_ibu, :nik_ibu, :thn_lahir_ibu, :pendidikan_ibu, :pekerjaan_ibu, :penghasilan_ibu, :wa_ibu,
                :nama_wali, :wa_wali,
                :jenis_sekolah_asal, :nama_sekolah_asal, :alamat_sekolah_asal, :npsn_sekolah_asal,
                :tinggi_badan, :berat_badan, :lingkar_kepala, :riwayat_penyakit,
                :bayar, :metode, :tgl_lunas, :seleksi
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':no_reg' => $no_registrasi,
                ':kategori' => $kategori_santri,
                ':gel_id' => $gelombang_id,
                ':jalur' => $jalur_pendaftaran,
                ':nama' => $nama_santri,
                ':nik' => $nik_santri,
                ':nisn' => $nisn,
                ':kk' => $no_kk,
                ':tmp_lahir' => $tempat_lahir,
                ':tgl_lahir' => $tgl_lahir,
                ':jk' => $jk,
                ':status_anak' => $status_anak,
                ':berkebutuhan_khusus' => $berkebutuhan_khusus,
                ':pip' => $pip,
                ':provinsi' => $provinsi,
                ':kabupaten' => $kabupaten,
                ':kecamatan' => $kecamatan,
                ':kelurahan' => $kelurahan,
                ':alamat' => $alamat_lengkap,
                ':nama_ayah' => $nama_ayah,
                ':nik_ayah' => $nik_ayah,
                ':thn_lahir_ayah' => $thn_lahir_ayah,
                ':pendidikan_ayah' => $pendidikan_ayah,
                ':pekerjaan_ayah' => $pekerjaan_ayah,
                ':penghasilan_ayah' => $penghasilan_ayah,
                ':wa_ayah' => $wa_ayah,
                ':nama_ibu' => $nama_ibu,
                ':nik_ibu' => $nik_ibu,
                ':thn_lahir_ibu' => $thn_lahir_ibu,
                ':pendidikan_ibu' => $pendidikan_ibu,
                ':pekerjaan_ibu' => $pekerjaan_ibu,
                ':penghasilan_ibu' => $penghasilan_ibu,
                ':wa_ibu' => $wa_ibu,
                ':nama_wali' => $nama_wali,
                ':wa_wali' => $wa_wali,
                ':jenis_sekolah_asal' => $jenis_sekolah_asal,
                ':nama_sekolah_asal' => $nama_sekolah_asal,
                ':alamat_sekolah_asal' => $alamat_sekolah_asal,
                ':npsn_sekolah_asal' => $npsn_sekolah_asal,
                ':tinggi_badan' => $tinggi_badan,
                ':berat_badan' => $berat_badan,
                ':lingkar_kepala' => $lingkar_kepala,
                ':riwayat_penyakit' => $riwayat_penyakit,
                ':bayar' => $status_pembayaran,
                ':metode' => ($status_pembayaran === 'Lunas') ? $metode_pembayaran : 'Belum Bayar',
                ':tgl_lunas' => ($status_pembayaran === 'Lunas') ? date('Y-m-d H:i:s') : null,
                ':seleksi' => $status_seleksi
            ]);
            
            $db->commit();
            $success = "Pendaftar berhasil ditambahkan secara manual dengan No Registrasi: " . $no_registrasi;
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}
?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        
        <?php
        $header_custom_title = '
            <div>
                <h1 class="text-xl font-black text-gray-800 tracking-tight flex items-center">
                    <i class="fa-solid fa-user-plus mr-3 text-primary"></i> Input Pendaftar Manual
                </h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5 hidden sm:block">Tambahkan data santri baru secara manual (Bypass validasi gelombang)</p>
            </div>';
        
        include 'includes/header.php';
        ?>

        <!-- Scrollable Content -->
        <main class="flex-grow overflow-y-auto p-8 bg-gray-100">
            <div class="max-w-5xl mx-auto space-y-8 pb-16">
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-3xl flex items-center space-x-3 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-xl text-red-600 flex-shrink-0"></i>
                    <span class="font-bold text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-3xl flex items-center space-x-3 shadow-sm">
                    <i class="fa-solid fa-circle-check text-xl text-emerald-600 flex-shrink-0"></i>
                    <span class="font-bold text-sm"><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="tambah_pendaftar.php" class="space-y-8">
                
                <!-- Card 1: Pengaturan Admin -->
                <div class="bg-indigo-50/50 rounded-3xl p-8 shadow-sm border border-indigo-200/80 space-y-6">
                    <h2 class="text-lg font-bold text-indigo-900 border-b border-indigo-100 pb-3 flex items-center">
                        <i class="fa-solid fa-gear text-indigo-500 mr-2.5"></i> Pengaturan Admin (Akses Khusus)
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Santri</label>
                            <select name="kategori_santri" class="w-full bg-white border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" required>
                                <option value="Baru">Santri Baru (Reguler)</option>
                                <option value="Pindahan">Santri Pindahan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Gelombang Pendaftaran</label>
                            <select name="gelombang_id" class="w-full bg-white border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" required>
                                <option value="">-- Pilih Gelombang --</option>
                                <?php foreach ($gelombang_list as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_gelombang'] . ' (' . $g['tahun_ajaran'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Pembayaran</label>
                            <select id="input_status_pembayaran" name="status_pembayaran" onchange="toggleMetode()" class="w-full bg-white border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" required>
                                <option value="Lunas">Lunas (Bypass)</option>
                                <option value="Belum Bayar">Belum Bayar</option>
                            </select>
                        </div>
                        <div id="metode_container">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                            <select name="metode_pembayaran" class="w-full bg-white border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                                <option value="Tunai/Cash">Tunai / Cash (Langsung ke Admin)</option>
                                <option value="Transfer">Transfer Bank</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Kelulusan</label>
                            <select name="status_seleksi" class="w-full bg-white border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition" required>
                                <option value="Lulus">Lulus (Bypass)</option>
                                <option value="Belum Seleksi">Belum Seleksi</option>
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
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenjang Pendidikan <span class="text-red-500">*</span></label>
                            <select name="jalur_pendaftaran" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition" required>
                                <option value="SMP IT">SMP IT Babul Jannah</option>
                                <option value="SMA IT">SMA IT Babul Jannah</option>
                                <option value="Kepondokan">Kepondokan (Takhasus Tahfizh)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Lengkap Santri <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_santri" required class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NIK Santri (16 Digit) <span class="text-red-500">*</span></label>
                            <input type="text" name="nik_santri" required maxlength="16" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NISN</label>
                            <input type="text" name="nisn" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">No. Kartu Keluarga (KK)</label>
                            <input type="text" name="no_kk" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tanggal Lahir</label>
                            <input type="date" name="tgl_lahir" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenis Kelamin</label>
                            <select name="jk" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Anak</label>
                            <input type="text" name="status_anak" placeholder="Anak kandung ke-X" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berkebutuhan Khusus?</label>
                            <select name="berkebutuhan_khusus" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Penerima PIP?</label>
                            <select name="pip" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </select>
                        </div>
                        <!-- Wilayah Domisili -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Provinsi</label>
                            <select name="provinsi" id="provinsi" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="">-- Pilih Provinsi --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kabupaten / Kota</label>
                            <select name="kabupaten" id="kabupaten" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="">-- Pilih Kab/Kota --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kecamatan</label>
                            <select name="kecamatan" id="kecamatan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kelurahan / Desa</label>
                            <select name="kelurahan" id="kelurahan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                                <option value="">-- Pilih Kelurahan --</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Lengkap (Jalan / RT / RW)</label>
                            <textarea name="alamat_lengkap" rows="3" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition"></textarea>
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
                                <input type="text" name="nama_ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ayah</label>
                                <input type="text" name="nik_ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                    <input type="text" name="thn_lahir_ayah" placeholder="Contoh: 1980" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                    <input type="text" name="pendidikan_ayah" placeholder="S1/SMA/dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                    <input type="text" name="pekerjaan_ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                    <input type="text" name="penghasilan_ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">No. WhatsApp Ayah <span class="text-red-500">*</span></label>
                                <input type="text" name="wa_ayah" required class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm font-bold text-primary">
                            </div>
                        </div>

                        <!-- Ibu -->
                        <div class="space-y-4 p-5 bg-gray-50 rounded-2xl border border-gray-200/60">
                            <h3 class="font-bold text-primary text-sm border-b pb-2"><i class="fa-solid fa-user text-secondary mr-1.5"></i> Data Ibu Kandung</h3>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Nama Ibu</label>
                                <input type="text" name="nama_ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ibu</label>
                                <input type="text" name="nik_ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                    <input type="text" name="thn_lahir_ibu" placeholder="Contoh: 1985" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                    <input type="text" name="pendidikan_ibu" placeholder="S1/SMA/dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                    <input type="text" name="pekerjaan_ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                    <input type="text" name="penghasilan_ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Ibu</label>
                                <input type="text" name="wa_ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                        </div>

                        <!-- Wali (Opsional) -->
                        <div class="md:col-span-2 p-5 bg-gray-50 rounded-2xl border border-gray-200/60 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <h3 class="font-bold text-primary text-sm"><i class="fa-solid fa-user-shield text-secondary mr-1.5"></i> Data Wali (Opsional / Jika Ada)</h3>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Nama Wali</label>
                                <input type="text" name="nama_wali" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Wali</label>
                                <input type="text" name="wa_wali" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold">
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
                                <option value="SD">SD (Sekolah Dasar)</option>
                                <option value="MI">MI (Madrasah Ibtidaiyah)</option>
                                <option value="SMP">SMP</option>
                                <option value="MTs">MTs (Madrasah Tsanawiyah)</option>
                                <option value="Pesantren">Pesantren / PKBM / Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Sekolah Asal</label>
                            <input type="text" name="nama_sekolah_asal" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NPSN Sekolah Asal</label>
                            <input type="text" name="npsn_sekolah_asal" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Sekolah Asal</label>
                            <input type="text" name="alamat_sekolah_asal" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3 md:col-span-2">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tinggi (cm)</label>
                                <input type="number" name="tinggi_badan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berat (kg)</label>
                                <input type="number" name="berat_badan" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Lingkar Kepala</label>
                                <input type="number" name="lingkar_kepala" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                            </div>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Riwayat Penyakit Berat / Khusus</label>
                            <input type="text" name="riwayat_penyakit" placeholder="Tidak ada / Sebutkan jika ada" class="w-full bg-gray-50/80 border border-gray-200/80 rounded-2xl px-4 py-3 text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition">
                        </div>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="bg-gray-50 p-6 rounded-3xl border border-gray-200/80 flex flex-col sm:flex-row items-center justify-between shadow-inner mt-8">
                    <p class="text-sm text-gray-500 font-medium mb-4 sm:mb-0">
                        <i class="fa-solid fa-shield-halved text-emerald-500 mr-2"></i>Pastikan data inti sudah benar sebelum disimpan.
                    </p>
                    <div class="flex space-x-3 w-full sm:w-auto">
                        <a href="pendaftar.php" class="w-full sm:w-auto px-6 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all text-center">Batal</a>
                        <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-primary text-white font-bold rounded-xl shadow-lg shadow-primary/30 hover:bg-secondary hover:shadow-secondary/30 focus:outline-none focus:ring-2 focus:ring-secondary/50 transition-all transform active:scale-95 flex items-center justify-center">
                            <i class="fa-solid fa-save mr-2"></i> Simpan Pendaftar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
<script>
    function toggleMetode() {
        const status = document.getElementById('input_status_pembayaran').value;
        const container = document.getElementById('metode_container');
        if (status === 'Lunas') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
    
    // Inisialisasi saat load
    document.addEventListener('DOMContentLoaded', function() {
        toggleMetode();

        const provSelect = document.getElementById('provinsi');
        const kabSelect = document.getElementById('kabupaten');
        const kecSelect = document.getElementById('kecamatan');
        const kelSelect = document.getElementById('kelurahan');

        if (!provSelect || !kabSelect) return;

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
                data.forEach(prov => {
                    const pName = prov.name.toUpperCase();
                    options += `<option value="${pName}" data-id="${prov.id}">${pName}</option>`;
                });
                provSelect.innerHTML = options;
            })
            .catch(() => {
                let options = '<option value="">-- Pilih Provinsi --</option>';
                fallbackProvinces.forEach(p => {
                    options += `<option value="${p}">${p}</option>`;
                });
                provSelect.innerHTML = options;
            });

        provSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const provId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kabSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            kecSelect.innerHTML = '<option value="">-- Pilih Kab/Kota Dahulu --</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
            
            if (provId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                    .then(res => res.json())
                    .then(data => {
                        let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
                        data.forEach(kab => {
                            const kName = kab.name.toUpperCase();
                            options += `<option value="${kName}" data-id="${kab.id}">${kName}</option>`;
                        });
                        kabSelect.innerHTML = options;
                    })
                    .catch(() => {
                        kabSelect.innerHTML = '<option value="">-- Gagal memuat daftar kota --</option>';
                    });
            } else {
                kabSelect.innerHTML = '<option value="">-- Pilih Provinsi Dahulu --</option>';
            }
        });

        kabSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const kabId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kecSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
            
            if (kabId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${kabId}.json`)
                    .then(res => res.json())
                    .then(data => {
                        let options = '<option value="">-- Pilih Kecamatan --</option>';
                        data.forEach(kec => {
                            const kcName = kec.name.toUpperCase();
                            options += `<option value="${kcName}" data-id="${kec.id}">${kcName}</option>`;
                        });
                        kecSelect.innerHTML = options;
                    });
            } else {
                kecSelect.innerHTML = '<option value="">-- Pilih Kab/Kota Dahulu --</option>';
            }
        });

        kecSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            const kecId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
            kelSelect.innerHTML = '<option value="">-- Memuat... --</option>';
            
            if (kecId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${kecId}.json`)
                    .then(res => res.json())
                    .then(data => {
                        let options = '<option value="">-- Pilih Kelurahan --</option>';
                        data.forEach(kel => {
                            const klName = kel.name.toUpperCase();
                            options += `<option value="${klName}" data-id="${kel.id}">${klName}</option>`;
                        });
                        kelSelect.innerHTML = options;
                    });
            } else {
                kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
            }
        });
    });
</script>
</html>
