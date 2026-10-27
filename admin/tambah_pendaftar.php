<?php
// admin/tambah_pendaftar.php
require_once '../config/db.php';

// Proteksi login admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Proteksi Hak Akses (RBAC)
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk menginput data pendaftar.");
}

$error = '';
$success = '';
$new_santri_id = 0;
$new_no_reg = '';

// Ambil daftar gelombang pendaftaran aktif & tersimpan
$stmt_gel = $db->query("SELECT id, nama_gelombang, tahun_ajaran, tgl_buka, tgl_tutup, status FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $kategori_santri    = sanitize($_POST['kategori_santri'] ?? 'Baru');
        $gelombang_id       = (int)($_POST['gelombang_id'] ?? 0);
        $jalur_pendaftaran  = sanitize($_POST['jalur_pendaftaran'] ?? '');

        // 1. Data Calon Santri
        $nama_santri        = sanitize($_POST['nama_santri'] ?? '');
        $nik_santri         = sanitize($_POST['nik_santri'] ?? '');
        $nisn               = sanitize($_POST['nisn'] ?? '');
        $no_kk              = sanitize($_POST['no_kk'] ?? '');
        $tempat_lahir       = sanitize($_POST['tempat_lahir'] ?? '');
        $tgl_lahir          = sanitize($_POST['tgl_lahir'] ?? '');
        $jk                 = sanitize($_POST['jk'] ?? 'Laki-laki');
        $status_anak        = sanitize($_POST['status_anak'] ?? '');
        $berkebutuhan_khusus = sanitize($_POST['berkebutuhan_khusus'] ?? 'Tidak');
        $pip                = sanitize($_POST['pip'] ?? 'Tidak');

        // Wilayah Domisili
        $provinsi           = sanitize($_POST['provinsi'] ?? '');
        $kabupaten          = sanitize($_POST['kabupaten'] ?? '');
        $kecamatan          = sanitize($_POST['kecamatan'] ?? '');
        $kelurahan          = sanitize($_POST['kelurahan'] ?? '');
        $alamat_lengkap     = sanitize($_POST['alamat_lengkap'] ?? '');

        // 2. Data Orang Tua / Wali & Kontak Notifikasi
        $nama_ayah          = sanitize($_POST['nama_ayah'] ?? '');
        $nik_ayah           = sanitize($_POST['nik_ayah'] ?? '');
        $thn_lahir_ayah     = sanitize($_POST['thn_lahir_ayah'] ?? '');
        $pendidikan_ayah    = sanitize($_POST['pendidikan_ayah'] ?? '');
        $pekerjaan_ayah     = sanitize($_POST['pekerjaan_ayah'] ?? '');
        $penghasilan_ayah   = sanitize($_POST['penghasilan_ayah'] ?? '');
        $wa_ayah            = sanitize($_POST['wa_ayah'] ?? '');
        $email              = sanitize($_POST['email'] ?? '');

        $nama_ibu           = sanitize($_POST['nama_ibu'] ?? '');
        $nik_ibu            = sanitize($_POST['nik_ibu'] ?? '');
        $thn_lahir_ibu      = sanitize($_POST['thn_lahir_ibu'] ?? '');
        $pendidikan_ibu     = sanitize($_POST['pendidikan_ibu'] ?? '');
        $pekerjaan_ibu      = sanitize($_POST['pekerjaan_ibu'] ?? '');
        $penghasilan_ibu    = sanitize($_POST['penghasilan_ibu'] ?? '');
        $wa_ibu             = sanitize($_POST['wa_ibu'] ?? '');

        $nama_wali          = sanitize($_POST['nama_wali'] ?? '');
        $wa_wali            = sanitize($_POST['wa_wali'] ?? '');

        // 3. Sekolah Asal & Data Fisik
        $jenis_sekolah_asal = sanitize($_POST['jenis_sekolah_asal'] ?? 'SD');
        $nama_sekolah_asal  = sanitize($_POST['nama_sekolah_asal'] ?? '');
        $alamat_sekolah_asal = sanitize($_POST['alamat_sekolah_asal'] ?? '');
        $npsn_sekolah_asal  = sanitize($_POST['npsn_sekolah_asal'] ?? '');

        $tinggi_badan       = !empty($_POST['tinggi_badan']) ? (int)$_POST['tinggi_badan'] : null;
        $berat_badan        = !empty($_POST['berat_badan']) ? (int)$_POST['berat_badan'] : null;
        $lingkar_kepala     = !empty($_POST['lingkar_kepala']) ? (int)$_POST['lingkar_kepala'] : null;
        $riwayat_penyakit   = sanitize($_POST['riwayat_penyakit'] ?? '');

        // 4. Status Administrasi PSB
        $status_pembayaran  = sanitize($_POST['status_pembayaran'] ?? 'Belum Bayar');
        $metode_pembayaran  = sanitize($_POST['metode_pembayaran'] ?? 'Tunai/Cash');
        $status_seleksi     = sanitize($_POST['status_seleksi'] ?? 'Belum Seleksi');

        // Validasi field utama
        if (empty($nama_santri) || empty($nik_santri) || $gelombang_id <= 0 || empty($jalur_pendaftaran)) {
            $error = "Mohon lengkapi data wajib: Nama Calon Santri, NIK (16 digit), Jalur Pendidikan, dan Gelombang Pendaftaran.";
        } elseif (strlen($nik_santri) < 16) {
            $error = "NIK Calon Santri harus berisi 16 digit angka sesuai Kartu Keluarga.";
        } else {
            // Cek apakah NIK sudah pernah didaftarkan
            $stmt_check_nik = $db->prepare("SELECT id, no_registrasi, nama_santri FROM pendaftaran WHERE nik_santri = :nik LIMIT 1");
            $stmt_check_nik->execute(['nik' => $nik_santri]);
            $exist_nik = $stmt_check_nik->fetch();

            if ($exist_nik) {
                $error = "NIK {$nik_santri} sudah terdaftar atas nama {$exist_nik['nama_santri']} (No. Reg: {$exist_nik['no_registrasi']}). Silakan gunakan NIK lain atau edit data santri tersebut.";
            } else {
                // Generate Nomor Registrasi Otomatis (PPBJ-YYYY-XXX)
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

                $tgl_lunas = ($status_pembayaran === 'Lunas') ? date('Y-m-d H:i:s') : null;
                $metode_final = ($status_pembayaran === 'Lunas') ? $metode_pembayaran : 'Belum Bayar';

                $sql = "INSERT INTO pendaftaran (
                    no_registrasi, kategori_santri, gelombang_id, jalur_pendaftaran,
                    nama_santri, nik_santri, nisn, no_kk, tempat_lahir, tgl_lahir, jk, 
                    status_anak, berkebutuhan_khusus, pip,
                    provinsi, kabupaten, kecamatan, kelurahan, alamat_lengkap,
                    nama_ayah, nik_ayah, thn_lahir_ayah, pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, wa_ayah,
                    email,
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
                    :email,
                    :nama_ibu, :nik_ibu, :thn_lahir_ibu, :pendidikan_ibu, :pekerjaan_ibu, :penghasilan_ibu, :wa_ibu,
                    :nama_wali, :wa_wali,
                    :jenis_sekolah_asal, :nama_sekolah_asal, :alamat_sekolah_asal, :npsn_sekolah_asal,
                    :tinggi_badan, :berat_badan, :lingkar_kepala, :riwayat_penyakit,
                    :bayar, :metode, :tgl_lunas, :seleksi
                )";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':no_reg'               => $no_registrasi,
                    ':kategori'             => $kategori_santri,
                    ':gel_id'               => $gelombang_id,
                    ':jalur'                => $jalur_pendaftaran,
                    ':nama'                 => $nama_santri,
                    ':nik'                  => $nik_santri,
                    ':nisn'                 => $nisn,
                    ':kk'                   => $no_kk,
                    ':tmp_lahir'            => $tempat_lahir,
                    ':tgl_lahir'            => !empty($tgl_lahir) ? $tgl_lahir : null,
                    ':jk'                   => $jk,
                    ':status_anak'          => $status_anak,
                    ':berkebutuhan_khusus'  => $berkebutuhan_khusus,
                    ':pip'                  => $pip,
                    ':provinsi'             => $provinsi,
                    ':kabupaten'            => $kabupaten,
                    ':kecamatan'            => $kecamatan,
                    ':kelurahan'            => $kelurahan,
                    ':alamat'               => $alamat_lengkap,
                    ':nama_ayah'            => $nama_ayah,
                    ':nik_ayah'             => $nik_ayah,
                    ':thn_lahir_ayah'       => $thn_lahir_ayah,
                    ':pendidikan_ayah'      => $pendidikan_ayah,
                    ':pekerjaan_ayah'       => $pekerjaan_ayah,
                    ':penghasilan_ayah'     => $penghasilan_ayah,
                    ':wa_ayah'              => $wa_ayah,
                    ':email'                => $email,
                    ':nama_ibu'             => $nama_ibu,
                    ':nik_ibu'              => $nik_ibu,
                    ':thn_lahir_ibu'        => $thn_lahir_ibu,
                    ':pendidikan_ibu'       => $pendidikan_ibu,
                    ':pekerjaan_ibu'        => $pekerjaan_ibu,
                    ':penghasilan_ibu'      => $penghasilan_ibu,
                    ':wa_ibu'               => $wa_ibu,
                    ':nama_wali'            => $nama_wali,
                    ':wa_wali'              => $wa_wali,
                    ':jenis_sekolah_asal'   => $jenis_sekolah_asal,
                    ':nama_sekolah_asal'    => $nama_sekolah_asal,
                    ':alamat_sekolah_asal'  => $alamat_sekolah_asal,
                    ':npsn_sekolah_asal'    => $npsn_sekolah_asal,
                    ':tinggi_badan'         => $tinggi_badan,
                    ':berat_badan'          => $berat_badan,
                    ':lingkar_kepala'       => $lingkar_kepala,
                    ':riwayat_penyakit'     => $riwayat_penyakit,
                    ':bayar'                => $status_pembayaran,
                    ':metode'               => $metode_final,
                    ':tgl_lunas'            => $tgl_lunas,
                    ':seleksi'              => $status_seleksi
                ]);

                $new_santri_id = (int)$db->lastInsertId();
                $new_no_reg = $no_registrasi;

                $db->commit();
                $success = "Santri baru berhasil didaftarkan secara manual dengan Nomor Registrasi: {$no_registrasi}";
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Terjadi kegagalan sistem saat menyimpan data: " . $e->getMessage();
    }
}

include 'includes/head.php';
?>
<body class="bg-[#FBF9F6] text-[#2F2522] flex h-screen overflow-hidden antialiased">
    
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-screen overflow-hidden min-w-0">
        
        <?php
        $header_custom_title = '
            <div class="flex items-center space-x-3 min-w-0">
                <a href="pendaftar.php" class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-[#5C3E35] text-gray-600 hover:text-white flex items-center justify-center transition flex-shrink-0 shadow-2xs group" title="Kembali ke Data Pendaftar">
                    <i class="fa-solid fa-arrow-left text-xs group-hover:-translate-x-0.5 transition-transform"></i>
                </a>
                <div class="min-w-0">
                    <h1 class="text-sm sm:text-base lg:text-lg font-black text-[#2F2522] tracking-tight truncate flex items-center">
                        <i class="fa-solid fa-user-plus mr-2 text-[#5C3E35]"></i> Input Santri Baru Manual
                    </h1>
                    <p class="text-[11px] text-gray-500 font-medium hidden md:block truncate">
                        Formulir pendaftaran manual oleh panitia dengan bypass status pembayaran & kelulusan
                    </p>
                </div>
            </div>';

        include 'includes/header.php';
        ?>

        <!-- Scrollable Content Body -->
        <main class="flex-grow overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="max-w-5xl mx-auto space-y-6 pb-20">

                <!-- Breadcrumb Navigation & Top Back Button Bar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:px-5 rounded-2xl border border-gray-200/90 shadow-2xs">
                    <nav class="flex items-center space-x-2 text-xs font-semibold text-gray-500">
                        <a href="dashboard.php" class="hover:text-[#5C3E35] transition flex items-center">
                            <i class="fa-solid fa-house text-gray-400 mr-1.5 text-[11px]"></i> Dashboard
                        </a>
                        <span class="text-gray-300">/</span>
                        <a href="pendaftar.php" class="hover:text-[#5C3E35] transition">Data Pendaftar</a>
                        <span class="text-gray-300">/</span>
                        <span class="text-[#5C3E35] font-black">Input Santri Baru</span>
                    </nav>
                    <a href="pendaftar.php" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-extrabold bg-[#5C3E35]/10 hover:bg-[#5C3E35] text-[#5C3E35] hover:text-white transition shadow-2xs group w-fit border border-[#5C3E35]/20" title="Kembali ke Tabel Data Pendaftar">
                        <i class="fa-solid fa-arrow-left text-xs group-hover:-translate-x-0.5 transition-transform"></i>
                        <span>&larr; Kembali ke Data Pendaftar</span>
                    </a>
                </div>

                <!-- Alert Feedback -->
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-2xl flex items-start justify-between shadow-xs">
                        <div class="flex items-start space-x-3">
                            <i class="fa-solid fa-circle-exclamation text-red-600 text-lg mt-0.5 flex-shrink-0"></i>
                            <div>
                                <h4 class="text-xs font-black text-red-900 uppercase tracking-wider">Perhatian</h4>
                                <p class="text-xs font-semibold text-red-800 mt-0.5 leading-relaxed"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-700 text-sm font-bold">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-5 rounded-2xl shadow-xs space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-3">
                                <i class="fa-solid fa-circle-check text-emerald-600 text-xl mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <h4 class="text-xs font-black text-emerald-900 uppercase tracking-wider">Pendaftaran Berhasil Disimpan</h4>
                                    <p class="text-xs font-semibold text-emerald-800 mt-1 leading-relaxed"><?= htmlspecialchars($success) ?></p>
                                </div>
                            </div>
                            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-emerald-400 hover:text-emerald-700 text-sm font-bold">&times;</button>
                        </div>
                        <?php if ($new_santri_id > 0): ?>
                            <div class="pt-2 border-t border-emerald-200/80 flex flex-wrap items-center gap-2">
                                <a href="detail_pendaftar.php?id=<?= $new_santri_id ?>" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-[#5C3E35] hover:bg-[#4A3029] text-white rounded-xl text-xs font-bold transition shadow-xs">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                    <span>Lihat Detail Santri #<?= htmlspecialchars($new_no_reg) ?></span>
                                </a>
                                <a href="tambah_pendaftar.php" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-100 rounded-xl text-xs font-bold transition shadow-2xs">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Input Santri Baru Lainnya</span>
                                </a>
                                <a href="pendaftar.php" class="inline-flex items-center space-x-1.5 px-3.5 py-2 text-xs font-bold text-emerald-900 hover:underline">
                                    <span>&larr; Kembali ke Tabel Utama</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Informative Banner: Fitur Panitia -->
                <div class="bg-gradient-to-r from-[#5C3E35]/10 via-[#A17A6F]/10 to-amber-50/60 p-4 sm:p-5 rounded-2xl border border-[#5C3E35]/20 flex items-start space-x-3.5">
                    <div class="w-9 h-9 rounded-xl bg-[#5C3E35] text-white flex items-center justify-center text-sm flex-shrink-0 shadow-2xs mt-0.5">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div class="text-xs text-[#2F2522]">
                        <span class="font-black text-[#5C3E35] uppercase tracking-wide">Mode Input Khusus Panitia:</span>
                        <p class="text-gray-600 mt-0.5 leading-relaxed">
                            Formulir ini digunakan untuk meregistrasi santri secara offline/langsung. Anda dapat langsung mengonfirmasi status pembayaran lunas (Tunai/Transfer) dan status kelulusan awal tanpa bergantung pada jadwal buka/tutup gelombang publik.
                        </p>
                    </div>
                </div>

                <form id="formTambahSantri" method="POST" action="tambah_pendaftar.php" class="space-y-6">

                    <!-- SEKSI 1: Administrasi & Konfigurasi PSB -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/90 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-[#5C3E35] text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                    <i class="fa-solid fa-sliders"></i>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-[#2F2522]">1. Administrasi & Konfigurasi PSB</h2>
                                    <p class="text-xs text-gray-500">Tentukan gelombang, jenjang, kategori, serta status pembayaran & kelulusan</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold bg-[#5C3E35]/10 text-[#5C3E35]">
                                <i class="fa-solid fa-shield mr-1.5 text-xs"></i> Otorisasi Admin
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <!-- Kategori Santri -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Kategori Santri <span class="text-red-500">*</span>
                                </label>
                                <select name="kategori_santri" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition" required>
                                    <option value="Baru">Santri Baru (Reguler)</option>
                                    <option value="Pindahan">Santri Pindahan</option>
                                </select>
                            </div>

                            <!-- Gelombang Pendaftaran -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Gelombang Pendaftaran <span class="text-red-500">*</span>
                                </label>
                                <select name="gelombang_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition" required>
                                    <option value="">-- Pilih Gelombang --</option>
                                    <?php foreach ($gelombang_list as $g): ?>
                                        <option value="<?= $g['id'] ?>">
                                            <?= htmlspecialchars($g['nama_gelombang'] . ' (T.A ' . $g['tahun_ajaran'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Jalur / Jenjang Pendidikan -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Jalur Jenjang Pendidikan <span class="text-red-500">*</span>
                                </label>
                                <select name="jalur_pendaftaran" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition" required>
                                    <option value="SMP IT">SMP IT Babul Jannah</option>
                                    <option value="SMA IT">SMA IT Babul Jannah</option>
                                    <option value="Kepondokan">Kepondokan (Takhasus Tahfizh)</option>
                                </select>
                            </div>

                            <!-- Status Pembayaran -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Status Pembayaran <span class="text-red-500">*</span>
                                </label>
                                <select id="input_status_pembayaran" name="status_pembayaran" onchange="toggleMetode()" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition" required>
                                    <option value="Lunas">Lunas (Bypass Panitia)</option>
                                    <option value="Belum Bayar">Belum Bayar</option>
                                </select>
                            </div>

                            <!-- Metode Pembayaran (Muncul Jika Lunas) -->
                            <div id="metode_container">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Metode Pembayaran
                                </label>
                                <select name="metode_pembayaran" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="Tunai/Cash">Tunai / Cash (Langsung ke Panitia)</option>
                                    <option value="Transfer">Transfer Bank</option>
                                </select>
                            </div>

                            <!-- Status Kelulusan / Seleksi -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Status Kelulusan Awal <span class="text-red-500">*</span>
                                </label>
                                <select name="status_seleksi" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition" required>
                                    <option value="Belum Seleksi">Belum Seleksi (Default)</option>
                                    <option value="Lulus">Lulus (Bypass Panitia)</option>
                                    <option value="Tidak Lulus">Tidak Lulus</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 2: Identitas Calon Santri -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/90 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-[#A17A6F]/20 text-[#5C3E35] flex items-center justify-center font-bold text-sm shadow-2xs">
                                    <i class="fa-solid fa-user-graduate"></i>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-[#2F2522]">2. Identitas Calon Santri</h2>
                                    <p class="text-xs text-gray-500">Data pokok calon santri sesuai akta kelahiran dan kartu keluarga</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 font-medium"><span class="text-red-500">*</span> Wajib diisi</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Nama Lengkap -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Nama Lengkap Santri <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nama_santri" required placeholder="Contoh: Muhammad Fatih Al-Ayyubi" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- NIK Santri -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        NIK Calon Santri (16 Digit) <span class="text-red-500">*</span>
                                    </label>
                                    <span id="nik_counter" class="text-[10px] font-mono text-gray-400">0/16</span>
                                </div>
                                <input type="text" id="nik_santri" name="nik_santri" required maxlength="16" placeholder="16 digit NIK santri" oninput="updateNikCounter()" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold font-mono text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- NISN -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    NISN (Nomor Induk Siswa Nasional)
                                </label>
                                <input type="text" name="nisn" maxlength="10" placeholder="10 digit NISN (Jika ada)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold font-mono text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- No. KK -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Nomor Kartu Keluarga (KK)
                                </label>
                                <input type="text" name="no_kk" maxlength="16" placeholder="16 digit No. KK" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold font-mono text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- Tempat Lahir -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Tempat Lahir
                                </label>
                                <input type="text" name="tempat_lahir" placeholder="Kota / Kabupaten Kelahiran" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- Tanggal Lahir + Auto Age -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Tanggal Lahir
                                    </label>
                                    <span id="badge_usia" class="hidden px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800"></span>
                                </div>
                                <input type="date" id="tgl_lahir" name="tgl_lahir" onchange="hitungUsia()" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- Jenis Kelamin Visual Radio -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Jenis Kelamin <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="jk" value="Laki-laki" checked class="peer sr-only">
                                        <div class="flex items-center space-x-2.5 p-3 rounded-xl border border-gray-200 bg-white peer-checked:border-blue-600 peer-checked:bg-blue-50/70 peer-checked:text-blue-900 transition">
                                            <i class="fa-solid fa-mars text-blue-600 text-base"></i>
                                            <div>
                                                <span class="block text-xs font-bold">Laki-laki</span>
                                                <span class="block text-[10px] text-gray-400 font-medium">Santri Putra</span>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="jk" value="Perempuan" class="peer sr-only">
                                        <div class="flex items-center space-x-2.5 p-3 rounded-xl border border-gray-200 bg-white peer-checked:border-pink-600 peer-checked:bg-pink-50/70 peer-checked:text-pink-900 transition">
                                            <i class="fa-solid fa-venus text-pink-600 text-base"></i>
                                            <div>
                                                <span class="block text-xs font-bold">Perempuan</span>
                                                <span class="block text-[10px] text-gray-400 font-medium">Santri Putri</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Status Anak -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    Status Anak dalam Keluarga
                                </label>
                                <input type="text" name="status_anak" placeholder="Contoh: Anak ke-2 dari 3 bersaudara" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <!-- ABK & PIP -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                        Kebutuhan Khusus?
                                    </label>
                                    <select name="berkebutuhan_khusus" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                        <option value="Tidak">Tidak</option>
                                        <option value="Ya">Ya</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                        Penerima PIP/KIP?
                                    </label>
                                    <select name="pip" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                        <option value="Tidak">Tidak</option>
                                        <option value="Ya">Ya</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 3: Alamat Domisili & Wilayah -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/90 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-[#A17A6F]/20 text-[#5C3E35] flex items-center justify-center font-bold text-sm shadow-2xs">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-[#2F2522]">3. Alamat Domisili Santri</h2>
                                    <p class="text-xs text-gray-500">Alamat tempat tinggal lengkap dan hirarki wilayah administratif</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Provinsi</label>
                                <select name="provinsi" id="provinsi" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="">-- Memuat Provinsi... --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kabupaten / Kota</label>
                                <select name="kabupaten" id="kabupaten" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="">-- Pilih Provinsi Dahulu --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kecamatan</label>
                                <select name="kecamatan" id="kecamatan" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="">-- Pilih Kab/Kota Dahulu --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Kelurahan / Desa</label>
                                <select name="kelurahan" id="kelurahan" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="">-- Pilih Kecamatan Dahulu --</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Lengkap (Jalan, RT/RW, Dusun, Patokan)</label>
                                <textarea name="alamat_lengkap" rows="3" placeholder="Jl. Pesantren No. XX, RT 01 / RW 02, Dusun Sukamaju" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 4: Data Orang Tua & Kontak Notifikasi -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/90 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-[#A17A6F]/20 text-[#5C3E35] flex items-center justify-center font-bold text-sm shadow-2xs">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-[#2F2522]">4. Data Orang Tua & Kontak Notifikasi</h2>
                                    <p class="text-xs text-gray-500">Nomor WhatsApp dan email aktif untuk pengiriman kartu ujian dan hasil pengumuman</p>
                                </div>
                            </div>
                        </div>

                        <!-- Email Notifikasi Utama -->
                        <div class="p-4 rounded-2xl bg-[#5C3E35]/5 border border-[#5C3E35]/15">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center">
                                <i class="fa-solid fa-envelope text-[#5C3E35] mr-2"></i> Email Utama Notifikasi PSB
                            </label>
                            <input type="email" name="email" placeholder="contoh: orangtua@gmail.com" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm font-semibold text-gray-800 focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            <p class="text-[11px] text-gray-500 mt-1">Kartu ujian, bukti validasi, dan surat kelulusan dapat dikirimkan otomatis ke alamat email ini.</p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Data Ayah -->
                            <div class="space-y-4 p-5 bg-[#FBF9F6] rounded-2xl border border-stone-200/80">
                                <h3 class="font-bold text-[#5C3E35] text-sm border-b border-stone-200 pb-2 flex items-center">
                                    <i class="fa-solid fa-user-tie text-[#A17A6F] mr-2"></i> Data Ayah Kandung
                                </h3>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Ayah</label>
                                    <input type="text" name="nama_ayah" placeholder="Nama lengkap ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ayah (16 Digit)</label>
                                    <input type="text" name="nik_ayah" maxlength="16" placeholder="NIK ayah" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold font-mono">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                        <input type="text" name="thn_lahir_ayah" placeholder="Contoh: 1980" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                        <input type="text" name="pendidikan_ayah" placeholder="S1 / SMA / dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                        <input type="text" name="pekerjaan_ayah" placeholder="PNS / Swasta / dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                        <input type="text" name="penghasilan_ayah" placeholder="Rentang gaji" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        No. WhatsApp Ayah <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-3 text-xs font-bold text-gray-400">+62</span>
                                        <input type="text" name="wa_ayah" required placeholder="81234567890" class="w-full bg-white border border-gray-300 rounded-xl pl-12 pr-3 py-2.5 text-xs sm:text-sm font-bold text-[#5C3E35]">
                                    </div>
                                </div>
                            </div>

                            <!-- Data Ibu -->
                            <div class="space-y-4 p-5 bg-[#FBF9F6] rounded-2xl border border-stone-200/80">
                                <h3 class="font-bold text-[#5C3E35] text-sm border-b border-stone-200 pb-2 flex items-center">
                                    <i class="fa-solid fa-user text-[#A17A6F] mr-2"></i> Data Ibu Kandung
                                </h3>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Ibu</label>
                                    <input type="text" name="nama_ibu" placeholder="Nama lengkap ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">NIK Ibu (16 Digit)</label>
                                    <input type="text" name="nik_ibu" maxlength="16" placeholder="NIK ibu" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold font-mono">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Tahun Lahir</label>
                                        <input type="text" name="thn_lahir_ibu" placeholder="Contoh: 1985" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pendidikan</label>
                                        <input type="text" name="pendidikan_ibu" placeholder="S1 / SMA / dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Pekerjaan</label>
                                        <input type="text" name="pekerjaan_ibu" placeholder="IRT / Guru / dll" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">Penghasilan</label>
                                        <input type="text" name="penghasilan_ibu" placeholder="Rentang gaji" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Ibu</label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-3 text-xs font-bold text-gray-400">+62</span>
                                        <input type="text" name="wa_ibu" placeholder="81234567890" class="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                            </div>

                            <!-- Data Wali (Opsional) -->
                            <div class="lg:col-span-2 p-5 bg-[#FBF9F6] rounded-2xl border border-stone-200/80 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <h3 class="font-bold text-[#5C3E35] text-sm flex items-center">
                                        <i class="fa-solid fa-user-shield text-[#A17A6F] mr-2"></i> Data Wali (Opsional / Jika Santri Bersama Wali)
                                    </h3>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Nama Lengkap Wali</label>
                                    <input type="text" name="nama_wali" placeholder="Nama lengkap wali" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">No. WhatsApp Wali</label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-3 text-xs font-bold text-gray-400">+62</span>
                                        <input type="text" name="wa_wali" placeholder="81234567890" class="w-full bg-white border border-gray-200 rounded-xl pl-12 pr-3 py-2.5 text-xs sm:text-sm font-semibold">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 5: Asal Sekolah & Rekam Medis / Fisik -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/90 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-2xl bg-[#A17A6F]/20 text-[#5C3E35] flex items-center justify-center font-bold text-sm shadow-2xs">
                                    <i class="fa-solid fa-school"></i>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-black text-[#2F2522]">5. Sekolah Asal & Rekam Medis Santri</h2>
                                    <p class="text-xs text-gray-500">Pendidikan formal sebelumnya dan data kondisi kesehatan fisik</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Jenis Sekolah Asal</label>
                                <select name="jenis_sekolah_asal" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                    <option value="SD">SD (Sekolah Dasar)</option>
                                    <option value="MI">MI (Madrasah Ibtidaiyah)</option>
                                    <option value="SMP">SMP</option>
                                    <option value="MTs">MTs (Madrasah Tsanawiyah)</option>
                                    <option value="Pesantren">Pesantren / PKBM / Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Nama Sekolah Asal</label>
                                <input type="text" name="nama_sekolah_asal" placeholder="Nama sekolah sebelumnya" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">NPSN Sekolah Asal</label>
                                <input type="text" name="npsn_sekolah_asal" maxlength="12" placeholder="8 digit NPSN (Jika ada)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold font-mono text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Alamat Sekolah Asal</label>
                                <input type="text" name="alamat_sekolah_asal" placeholder="Kota / Alamat sekolah asal" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>

                            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tinggi (cm)</label>
                                    <input type="number" name="tinggi_badan" min="50" max="250" placeholder="150" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Berat (kg)</label>
                                    <input type="number" name="berat_badan" min="15" max="200" placeholder="45" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Lingkar Kepala</label>
                                    <input type="number" name="lingkar_kepala" min="20" max="100" placeholder="52" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Riwayat Penyakit Khusus / Alergi</label>
                                <input type="text" name="riwayat_penyakit" placeholder="Tuliskan jika memiliki riwayat penyakit berat / asma / alergi (atau tulis 'Tidak Ada')" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-gray-800 focus:bg-white focus:ring-2 focus:ring-[#5C3E35]/20 focus:border-[#5C3E35] transition">
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Sticky Action Bar -->
                    <div class="sticky bottom-0 z-30 bg-white/95 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-gray-200/90 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center space-x-3 text-xs text-gray-600 font-medium w-full sm:w-auto">
                            <i class="fa-solid fa-shield-halved text-[#5C3E35] text-base flex-shrink-0"></i>
                            <span class="truncate">Pastikan data NIK dan pilihan jenjang santri sudah sesuai sebelum disimpan.</span>
                        </div>
                        <div class="flex items-center space-x-3 w-full sm:w-auto justify-end">
                            <a href="pendaftar.php" class="flex-1 sm:flex-initial px-5 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 font-extrabold text-xs transition text-center shadow-xs">
                                Batal & Kembali
                            </a>
                            <button type="submit" id="btnSubmitForm" class="flex-1 sm:flex-initial px-7 py-3 rounded-xl bg-[#5C3E35] hover:bg-[#4A3029] text-white font-black text-xs shadow-md transition flex items-center justify-center space-x-2 group">
                                <i class="fa-solid fa-floppy-disk group-hover:scale-110 transition-transform"></i>
                                <span>Simpan Data Santri Baru</span>
                            </button>
                        </div>
                    </div>

                </form>

            </div>
        </main>
    </div>

    <!-- Script Pendukung: Interaktivitas UI & Wilayah Cascade -->
    <script>
        function toggleMetode() {
            const status = document.getElementById('input_status_pembayaran').value;
            const container = document.getElementById('metode_container');
            if (container) {
                container.style.display = (status === 'Lunas') ? 'block' : 'none';
            }
        }

        function updateNikCounter() {
            const val = document.getElementById('nik_santri').value.trim();
            const counter = document.getElementById('nik_counter');
            if (counter) {
                counter.textContent = val.length + '/16';
                if (val.length === 16) {
                    counter.className = 'text-[10px] font-mono text-emerald-600 font-bold';
                } else {
                    counter.className = 'text-[10px] font-mono text-gray-400';
                }
            }
        }

        function hitungUsia() {
            const tglVal = document.getElementById('tgl_lahir').value;
            const badge = document.getElementById('badge_usia');
            if (!tglVal || !badge) return;

            const tgl = new Date(tglVal);
            const now = new Date();
            if (isNaN(tgl.getTime())) return;

            let years = now.getFullYear() - tgl.getFullYear();
            let months = now.getMonth() - tgl.getMonth();
            if (months < 0 || (months === 0 && now.getDate() < tgl.getDate())) {
                years--;
                months += 12;
            }

            badge.textContent = `Usia: ${years} Thn ${months} Bln`;
            badge.classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleMetode();
            updateNikCounter();
            hitungUsia();

            // Cegah double submit
            const form = document.getElementById('formTambahSantri');
            const btnSubmit = document.getElementById('btnSubmitForm');
            if (form && btnSubmit) {
                form.addEventListener('submit', function() {
                    btnSubmit.disabled = true;
                    btnSubmit.classList.add('opacity-75', 'cursor-wait');
                    btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Menyimpan...';
                });
            }

            // Wilayah Cascade
            const provSelect = document.getElementById('provinsi');
            const kabSelect  = document.getElementById('kabupaten');
            const kecSelect  = document.getElementById('kecamatan');
            const kelSelect  = document.getElementById('kelurahan');

            if (!provSelect || !kabSelect) return;

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
                kabSelect.innerHTML = '<option value="">-- Memuat Kabupaten/Kota... --</option>';
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
                kecSelect.innerHTML = '<option value="">-- Memuat Kecamatan... --</option>';
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
                        })
                        .catch(() => {
                            kecSelect.innerHTML = '<option value="">-- Gagal memuat daftar kecamatan --</option>';
                        });
                } else {
                    kecSelect.innerHTML = '<option value="">-- Pilih Kab/Kota Dahulu --</option>';
                }
            });

            kecSelect.addEventListener('change', function() {
                const selectedOpt = this.options[this.selectedIndex];
                const kecId = selectedOpt ? selectedOpt.getAttribute('data-id') : null;
                kelSelect.innerHTML = '<option value="">-- Memuat Kelurahan/Desa... --</option>';

                if (kecId) {
                    fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${kecId}.json`)
                        .then(res => res.json())
                        .then(data => {
                            let options = '<option value="">-- Pilih Kelurahan/Desa --</option>';
                            data.forEach(kel => {
                                const klName = kel.name.toUpperCase();
                                options += `<option value="${klName}" data-id="${kel.id}">${klName}</option>`;
                            });
                            kelSelect.innerHTML = options;
                        })
                        .catch(() => {
                            kelSelect.innerHTML = '<option value="">-- Gagal memuat daftar kelurahan --</option>';
                        });
                } else {
                    kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
                }
            });
        });
    </script>
</body>
</html>
