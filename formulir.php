<?php
// formulir.php
require_once 'config/db.php';
require_once 'config/mail.php';
require_once 'config/telegram.php';

$page_title = 'Formulir Pendaftaran - Pondok Pesantren Babul Jannah';
$meta_desc = 'Isi formulir pendaftaran santri baru Pondok Pesantren Babul Jannah Dumai secara online.';

// Ambil gelombang pendaftaran yang aktif (prioritaskan yang sedang buka)
$stmt = $db->prepare("SELECT * FROM gelombang WHERE status = 'aktif' ORDER BY CASE WHEN NOW() BETWEEN tgl_buka AND CONCAT(tgl_tutup, ' 23:59:59') THEN 1 WHEN NOW() < tgl_buka THEN 2 ELSE 3 END ASC, tgl_buka ASC LIMIT 1");
$stmt->execute();
$gelombang_aktif = $stmt->fetch();

$gelombang_status_waktu = 'closed';
if ($gelombang_aktif) {
    $now = time();
    $tgl_buka = strtotime($gelombang_aktif['tgl_buka']);
    $tgl_tutup = strtotime($gelombang_aktif['tgl_tutup'] . ' 23:59:59');
    
    if ($now < $tgl_buka) {
        $gelombang_status_waktu = 'upcoming';
    } elseif ($now > $tgl_tutup) {
        $gelombang_status_waktu = 'closed';
    } else {
        $gelombang_status_waktu = 'open';
    }
}

// Ambil Biaya Formulir / Tes Seleksi (1 Nilai Global)
$biaya_tes_global = get_biaya_tes();

$error = '';
$success = '';

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $gelombang_aktif && $gelombang_status_waktu === 'open') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.";
    } else {
        try {
        // 1. Generate No Registrasi (PPBJ-YYYY-XXX)
        $year = date('Y');
        $prefix = "PPBJ-" . $year . "-";
        $stmt_seq = $db->prepare("SELECT no_registrasi FROM pendaftaran WHERE no_registrasi LIKE :prefix ORDER BY id DESC LIMIT 1");
        $stmt_seq->execute(['prefix' => $prefix . '%']);
        $last_reg = $stmt_seq->fetch();
        
        if ($last_reg) {
            $last_num = (int) substr($last_reg['no_registrasi'], -3);
            $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_num = '001';
        }
        $no_registrasi = $prefix . $next_num;

        // 2. Ambil & Sanitasi Data
        $gelombang_id = $gelombang_aktif['id'];
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
        $alamat_lengkap = sanitize($_POST['alamat_lengkap'] ?? '');
        $provinsi = sanitize($_POST['provinsi'] ?? '');
        $kabupaten = sanitize($_POST['kabupaten'] ?? '');
        $kecamatan = sanitize($_POST['kecamatan'] ?? '');
        $kelurahan = sanitize($_POST['kelurahan'] ?? '');
        
        // Data Orang Tua / Wali
        $nama_ayah = sanitize($_POST['nama_ayah'] ?? '');
        $nik_ayah = sanitize($_POST['nik_ayah'] ?? '');
        $thn_lahir_ayah = sanitize($_POST['thn_lahir_ayah'] ?? '');
        $pendidikan_ayah = sanitize($_POST['pendidikan_ayah'] ?? '');
        $pekerjaan_ayah = sanitize($_POST['pekerjaan_ayah'] ?? '');
        $penghasilan_ayah = sanitize($_POST['penghasilan_ayah'] ?? '');
        $wa_ayah = sanitize($_POST['wa_ayah'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        
        $nama_ibu = sanitize($_POST['nama_ibu'] ?? '');
        $nik_ibu = sanitize($_POST['nik_ibu'] ?? '');
        $thn_lahir_ibu = sanitize($_POST['thn_lahir_ibu'] ?? '');
        $pendidikan_ibu = sanitize($_POST['pendidikan_ibu'] ?? '');
        $pekerjaan_ibu = sanitize($_POST['pekerjaan_ibu'] ?? '');
        $penghasilan_ibu = sanitize($_POST['penghasilan_ibu'] ?? '');
        $wa_ibu = sanitize($_POST['wa_ibu'] ?? '');
        
        $nama_wali = sanitize($_POST['nama_wali'] ?? '');
        $wa_wali = sanitize($_POST['wa_wali'] ?? '');
        
        // Data Sekolah Asal & Periodik
        $jenis_sekolah_asal = sanitize($_POST['jenis_sekolah_asal'] ?? '');
        $nama_sekolah_asal = sanitize($_POST['nama_sekolah_asal'] ?? '');
        $alamat_sekolah_asal = sanitize($_POST['alamat_sekolah_asal'] ?? '');
        $npsn_sekolah_asal = sanitize($_POST['npsn_sekolah_asal'] ?? '');
        
        $tinggi_badan = !empty($_POST['tinggi_badan']) ? (int)$_POST['tinggi_badan'] : null;
        $berat_badan = !empty($_POST['berat_badan']) ? (int)$_POST['berat_badan'] : null;
        $lingkar_kepala = !empty($_POST['lingkar_kepala']) ? (int)$_POST['lingkar_kepala'] : null;
        $riwayat_penyakit = sanitize($_POST['riwayat_penyakit'] ?? '');

        // 3. Simpan ke database
        $sql = "INSERT INTO pendaftaran (
                    no_registrasi, gelombang_id, jalur_pendaftaran,
                    nama_santri, nik_santri, nisn, no_kk, tempat_lahir, tgl_lahir, jk, status_anak, berkebutuhan_khusus, pip, alamat_lengkap, provinsi, kabupaten, kecamatan, kelurahan,
                    nama_ayah, nik_ayah, thn_lahir_ayah, pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, wa_ayah, email,
                    nama_ibu, nik_ibu, thn_lahir_ibu, pendidikan_ibu, pekerjaan_ibu, penghasilan_ibu, wa_ibu,
                    nama_wali, wa_wali,
                    jenis_sekolah_asal, nama_sekolah_asal, alamat_sekolah_asal, npsn_sekolah_asal,
                    tinggi_badan, berat_badan, lingkar_kepala, riwayat_penyakit
                ) VALUES (
                    :no_registrasi, :gelombang_id, :jalur_pendaftaran,
                    :nama_santri, :nik_santri, :nisn, :no_kk, :tempat_lahir, :tgl_lahir, :jk, :status_anak, :berkebutuhan_khusus, :pip, :alamat_lengkap, :provinsi, :kabupaten, :kecamatan, :kelurahan,
                    :nama_ayah, :nik_ayah, :thn_lahir_ayah, :pendidikan_ayah, :pekerjaan_ayah, :penghasilan_ayah, :wa_ayah, :email,
                    :nama_ibu, :nik_ibu, :thn_lahir_ibu, :pendidikan_ibu, :pekerjaan_ibu, :penghasilan_ibu, :wa_ibu,
                    :nama_wali, :wa_wali,
                    :jenis_sekolah_asal, :nama_sekolah_asal, :alamat_sekolah_asal, :npsn_sekolah_asal,
                    :tinggi_badan, :berat_badan, :lingkar_kepala, :riwayat_penyakit
                )";
                
        $stmt_insert = $db->prepare($sql);
        $stmt_insert->execute([
            ':no_registrasi' => $no_registrasi,
            ':gelombang_id' => $gelombang_id,
            ':jalur_pendaftaran' => $jalur_pendaftaran,
            ':nama_santri' => $nama_santri,
            ':nik_santri' => $nik_santri,
            ':nisn' => $nisn,
            ':no_kk' => $no_kk,
            ':tempat_lahir' => $tempat_lahir,
            ':tgl_lahir' => $tgl_lahir,
            ':jk' => $jk,
            ':status_anak' => $status_anak,
            ':berkebutuhan_khusus' => $berkebutuhan_khusus,
            ':pip' => $pip,
            ':alamat_lengkap' => $alamat_lengkap,
            ':provinsi' => $provinsi,
            ':kabupaten' => $kabupaten,
            ':kecamatan' => $kecamatan,
            ':kelurahan' => $kelurahan,
            ':nama_ayah' => $nama_ayah,
            ':nik_ayah' => $nik_ayah,
            ':thn_lahir_ayah' => $thn_lahir_ayah,
            ':pendidikan_ayah' => $pendidikan_ayah,
            ':pekerjaan_ayah' => $pekerjaan_ayah,
            ':penghasilan_ayah' => $penghasilan_ayah,
            ':wa_ayah' => $wa_ayah,
            ':email' => $email,
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
            ':riwayat_penyakit' => $riwayat_penyakit
        ]);

        // Kirim Notifikasi Email (Ke Santri & Admin Pesantren)
        send_email_pendaftaran_baru([
            'no_registrasi' => $no_registrasi,
            'nama_santri' => $nama_santri,
            'jalur_pendaftaran' => $jalur_pendaftaran,
            'wa_ayah' => $wa_ayah,
            'email' => $email
        ]);
        
        // Kirim Notifikasi ke Telegram Grup
        $tel_msg = "🆕 <b>PENDAFTAR BARU MASUK!</b>\n";
        $tel_msg .= "--------------------------------------\n";
        $tel_msg .= "<b>No. Reg:</b> <code>" . $no_registrasi . "</code>\n";
        $tel_msg .= "<b>Nama:</b> " . htmlspecialchars($nama_santri) . "\n";
        $tel_msg .= "<b>Jalur:</b> " . htmlspecialchars($jalur_pendaftaran) . "\n";
        $tel_msg .= "<b>No WA:</b> " . htmlspecialchars($wa_ayah) . "\n\n";
        $tel_msg .= "<i>Cek selengkapnya di:</i>\n";
        $tel_msg .= "👉 https://" . $_SERVER['HTTP_HOST'] . "/admin/login.php";
        send_telegram_msg($tel_msg);

        $_SESSION['success_reg'] = "Pendaftaran Berhasil! Silakan catat Nomor Registrasi Anda.";
        header("Location: status.php?registered=1&no_reg=" . urlencode($no_registrasi) . "&phone=" . urlencode($wa_ayah));
        exit();

    } catch (PDOException $e) {
        $error = "Terjadi kesalahan sistem saat menyimpan data: " . $e->getMessage();
    }
    }
}

require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Formulir Pendaftaran</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Silakan lengkapi seluruh kolom isian di bawah ini dengan data asli yang valid dan benar.
        </p>
    </div>
</section>

<!-- Formulir Section -->
<section class="py-12 bg-accent flex-grow no-print">
    <div class="max-w-7xl mx-auto px-4" id="daftar-form-container">
        
        <?php if (!$gelombang_aktif || $gelombang_status_waktu === 'closed'): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-6 rounded-2xl text-center space-y-4 shadow-sm">
            <i class="fa-solid fa-circle-xmark text-5xl text-red-600"></i>
            <h3 class="text-xl font-bold">Maaf, Pendaftaran Ditutup</h3>
            <p class="text-sm">Saat ini tidak ada gelombang pendaftaran online yang aktif. Untuk informasi lebih lanjut, silakan hubungi Call Center Panitia PSB Babul Jannah.</p>
            <a href="index.php" class="inline-block bg-primary text-white font-bold px-6 py-2 rounded-lg text-sm hover:bg-secondary">Kembali ke Beranda</a>
        </div>
        <?php elseif ($gelombang_status_waktu === 'upcoming'): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-6 rounded-2xl text-center space-y-4 shadow-sm">
            <i class="fa-solid fa-clock text-5xl text-amber-500"></i>
            <h3 class="text-xl font-bold">Pendaftaran Belum Dibuka</h3>
            <p class="text-sm text-amber-700">Formulir pendaftaran untuk <strong class="font-bold"><?= $gelombang_aktif['nama_gelombang'] ?></strong> baru akan dibuka pada <strong class="font-bold text-amber-900"><?= format_tanggal($gelombang_aktif['tgl_buka']) ?></strong>. Silakan kembali lagi nanti.</p>
            <a href="daftar.php" class="inline-block bg-primary text-white font-bold px-6 py-2 rounded-lg text-sm hover:bg-secondary">Kembali ke Informasi PSB</a>
        </div>
        <?php else: ?>

        <!-- Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Side: Progress & Form -->
            <div class="lg:col-span-8 space-y-8">

                <!-- Step Indicator Progress Bar -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
                    <div class="flex items-center justify-between relative">
                        <!-- Progress Line -->
                        <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-0.5 bg-gray-200 -z-0"></div>
                        
                        <!-- Indicators -->
                        <div class="flex flex-col items-center z-10 space-y-1.5">
                            <div class="step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 border-secondary text-secondary bg-white">1</div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-500">Jalur & Kelas</span>
                        </div>
                        <div class="flex flex-col items-center z-10 space-y-1.5">
                            <div class="step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border border-gray-300 text-gray-500 bg-white">2</div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-500">Data Santri</span>
                        </div>
                        <div class="flex flex-col items-center z-10 space-y-1.5">
                            <div class="step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border border-gray-300 text-gray-500 bg-white">3</div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-500">Orang Tua</span>
                        </div>
                        <div class="flex flex-col items-center z-10 space-y-1.5">
                            <div class="step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border border-gray-300 text-gray-500 bg-white">4</div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-500">Sekolah & Fisik</span>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm mb-6 flex items-center">
                    <i class="fa-solid fa-triangle-exclamation mr-2 text-red-600"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>

                <!-- Form Tag -->
                <form action="formulir.php" method="POST" novalidate class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-8">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <!-- STEP 1: Jalur & Jenjang -->
                    <div class="form-step space-y-6" data-step="1">
                        <div class="border-b border-gray-100 pb-3">
                            <h3 class="text-lg font-bold text-primary"><i class="fa-solid fa-compass mr-2"></i> Langkah 1: Jalur & Jenjang Pendidikan</h3>
                            <p class="text-xs text-gray-400">Pilih program pendidikan yang diinginkan.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-dark mb-2">Gelombang Pendaftaran</label>
                                <input type="text" value="<?= $gelombang_aktif['nama_gelombang'] ?> - T.A <?= $gelombang_aktif['tahun_ajaran'] ?>" disabled class="w-full bg-gray-100 border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="jalur_pendaftaran" class="block text-sm font-semibold text-dark mb-2">Jalur/Jenjang Pendidikan <span class="text-red-500">*</span></label>
                                <select name="jalur_pendaftaran" id="jalur_pendaftaran" required class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm text-dark focus:outline-none transition">
                                    <option value="">-- Pilih Jenjang --</option>
                                    <?php 
                                    $jenjang_allowed = $gelombang_aktif['jenjang_dibuka'] ?? 'Semua';
                                    if ($jenjang_allowed === 'Semua' || $jenjang_allowed === 'Hanya SMP'): 
                                    ?>
                                        <option value="SMP IT">SMP IT Babul Jannah</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($jenjang_allowed === 'Semua' || $jenjang_allowed === 'Hanya SMA'): ?>
                                        <option value="SMA IT">SMA IT Babul Jannah</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-4">
                            <button class="btn-next bg-primary hover:bg-secondary text-white font-bold px-6 py-2.5 rounded-lg text-sm shadow transition">Lanjut ke Data Santri <i class="fa-solid fa-arrow-right ml-1"></i></button>
                        </div>
                    </div>

                    <!-- STEP 2: Identitas Calon Santri -->
                    <div class="form-step hidden space-y-6" data-step="2">
                        <div class="border-b border-gray-100 pb-3">
                            <h3 class="text-lg font-bold text-primary"><i class="fa-solid fa-user mr-2"></i> Langkah 2: Identitas Calon Santri / Santriwati</h3>
                            <p class="text-xs text-gray-400">Pastikan data diisi sesuai akta kelahiran atau ijazah.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_santri" class="block text-sm font-semibold text-dark mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input type="text" name="nama_santri" id="nama_santri" required placeholder="Contoh: Muhammad Ali" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="jk" class="block text-sm font-semibold text-dark mb-2">Jenis Kelamin <span class="text-red-500">*</span></label>
                                <select name="jk" id="jk" required class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="Laki-laki">Laki-laki (Santri)</option>
                                    <option value="Perempuan">Perempuan (Santriwati)</option>
                                </select>
                            </div>
                            <div>
                                <label for="nik_santri" class="block text-sm font-semibold text-dark mb-2">NIK Santri (16 Digit) <span class="text-red-500">*</span></label>
                                <input type="text" name="nik_santri" id="nik_santri" required maxlength="16" placeholder="Masukkan 16 digit NIK" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="nisn" class="block text-sm font-semibold text-dark mb-2">NISN (10 Digit) <span class="text-red-500">*</span></label>
                                <input type="text" name="nisn" id="nisn" required maxlength="10" placeholder="Masukkan 10 digit NISN" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="no_kk" class="block text-sm font-semibold text-dark mb-2">No. Kartu Keluarga (KK) <span class="text-red-500">*</span></label>
                                <input type="text" name="no_kk" id="no_kk" required maxlength="16" placeholder="Masukkan 16 digit No. KK" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-dark mb-2">Alamat Email Aktif <span class="text-red-500">*</span></label>
                                <input type="email" name="email" id="email" required placeholder="contoh@gmail.com" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="tempat_lahir" class="block text-sm font-semibold text-dark mb-2">Tempat Lahir <span class="text-red-500">*</span></label>
                                        <input type="text" name="tempat_lahir" id="tempat_lahir" required placeholder="Kota Lahir" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                                    </div>
                                    <div>
                                        <label for="tgl_lahir" class="block text-sm font-semibold text-dark mb-2">Tanggal Lahir <span class="text-red-500">*</span></label>
                                        <input type="date" name="tgl_lahir" id="tgl_lahir" required class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="status_anak" class="block text-sm font-semibold text-dark mb-2">Status Anak</label>
                                <input type="text" name="status_anak" id="status_anak" placeholder="Contoh: Anak ke-2 dari 3 bersaudara" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-dark mb-2">Kebutuhan Khusus?</label>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <label class="inline-flex items-center"><input type="radio" name="berkebutuhan_khusus" value="Tidak" checked class="text-primary focus:ring-secondary"><span class="ml-2 text-sm">Tidak</span></label>
                                        <label class="inline-flex items-center"><input type="radio" name="berkebutuhan_khusus" value="Ya" class="text-primary focus:ring-secondary"><span class="ml-2 text-sm">Ya</span></label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-dark mb-2">Penerima PIP?</label>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <label class="inline-flex items-center"><input type="radio" name="pip" value="Tidak" checked class="text-primary focus:ring-secondary"><span class="ml-2 text-sm">Tidak</span></label>
                                        <label class="inline-flex items-center"><input type="radio" name="pip" value="Ya" class="text-primary focus:ring-secondary"><span class="ml-2 text-sm">Ya</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-2 space-y-4">
                                <label class="block text-sm font-semibold text-dark border-b border-gray-100 pb-2">Alamat Rumah Lengkap <span class="text-red-500">*</span></label>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="provinsi" class="block text-xs font-semibold text-gray-500 mb-1">Provinsi <span class="text-red-500">*</span></label>
                                        <select name="provinsi" id="provinsi" required class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                            <option value="">-- Memuat... --</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="kabupaten" class="block text-xs font-semibold text-gray-500 mb-1">Kabupaten/Kota <span class="text-red-500">*</span></label>
                                        <select name="kabupaten" id="kabupaten" required disabled class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none bg-gray-50 disabled:opacity-50">
                                            <option value="">-- Pilih Provinsi Dahulu --</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="kecamatan" class="block text-xs font-semibold text-gray-500 mb-1">Kecamatan <span class="text-red-500">*</span></label>
                                        <select name="kecamatan" id="kecamatan" required disabled class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none bg-gray-50 disabled:opacity-50">
                                            <option value="">-- Pilih Kota Dahulu --</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="kelurahan" class="block text-xs font-semibold text-gray-500 mb-1">Kelurahan/Desa <span class="text-red-500">*</span></label>
                                        <select name="kelurahan" id="kelurahan" required disabled class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none bg-gray-50 disabled:opacity-50">
                                            <option value="">-- Pilih Kecamatan Dahulu --</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="alamat_lengkap" class="block text-xs font-semibold text-gray-500 mb-1">Nama Jalan / RT / RW / Dusun <span class="text-red-500">*</span></label>
                                    <textarea name="alamat_lengkap" id="alamat_lengkap" rows="2" required placeholder="Tuliskan nama jalan, gang, nomor rumah, RT/RW..." class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <button class="btn-prev border border-gray-300 text-gray-600 hover:bg-gray-100 font-semibold px-5 py-2.5 rounded-lg text-sm transition"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali</button>
                            <button class="btn-next bg-primary hover:bg-secondary text-white font-bold px-6 py-2.5 rounded-lg text-sm shadow transition">Lanjut ke Data Orang Tua <i class="fa-solid fa-arrow-right ml-1"></i></button>
                        </div>
                    </div>

                    <!-- STEP 3: Identitas Orang Tua / Wali -->
                    <div class="form-step hidden space-y-6" data-step="3">
                        <div class="border-b border-gray-100 pb-3">
                            <h3 class="text-lg font-bold text-primary"><i class="fa-solid fa-users mr-2"></i> Langkah 3: Identitas Orang Tua & Wali</h3>
                            <p class="text-xs text-gray-400">Harap masukkan kontak WhatsApp aktif untuk pengiriman status pendaftaran.</p>
                        </div>

                        <!-- Data Ayah -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-secondary text-sm uppercase tracking-wider border-b border-gray-100 pb-1">Data Ayah Kandung</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="nama_ayah" class="block text-sm font-semibold text-dark mb-1">Nama Lengkap Ayah <span class="text-red-500">*</span></label>
                                    <input type="text" name="nama_ayah" id="nama_ayah" required placeholder="Nama Ayah" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="nik_ayah" class="block text-sm font-semibold text-dark mb-1">NIK Ayah (16 Digit)</label>
                                    <input type="text" name="nik_ayah" id="nik_ayah" maxlength="16" placeholder="NIK Ayah" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="wa_ayah" class="block text-sm font-semibold text-dark mb-1">No. HP/WA Ayah <span class="text-red-500">*</span></label>
                                    <input type="text" name="wa_ayah" id="wa_ayah" required placeholder="08XXXXXXXXXX" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>

                                <div>
                                    <label for="thn_lahir_ayah" class="block text-sm font-semibold text-dark mb-1">Tahun Lahir Ayah</label>
                                    <input type="text" name="thn_lahir_ayah" id="thn_lahir_ayah" maxlength="4" placeholder="Contoh: 1978" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="pendidikan_ayah" class="block text-sm font-semibold text-dark mb-1">Pendidikan Terakhir</label>
                                    <select name="pendidikan_ayah" id="pendidikan_ayah" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                        <option value="">-- Pilih --</option>
                                        <option value="SD">SD/Sederajat</option>
                                        <option value="SMP">SMP/Sederajat</option>
                                        <option value="SMA">SMA/Sederajat</option>
                                        <option value="D3">Diploma 3 (D3)</option>
                                        <option value="S1">Sarjana (S1)</option>
                                        <option value="S2">Magister (S2)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="pekerjaan_ayah" class="block text-sm font-semibold text-dark mb-1">Pekerjaan</label>
                                    <input type="text" name="pekerjaan_ayah" id="pekerjaan_ayah" placeholder="Contoh: PNS, Karyawan Swasta" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div class="md:col-span-3">
                                    <label for="penghasilan_ayah" class="block text-sm font-semibold text-dark mb-1">Penghasilan Bulanan</label>
                                    <select name="penghasilan_ayah" id="penghasilan_ayah" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                        <option value="">-- Pilih --</option>
                                        <option value="< Rp 1.000.000">Kurang dari Rp 1.000.000</option>
                                        <option value="Rp 1.000.000 - Rp 3.000.000">Rp 1.000.000 - Rp 3.000.000</option>
                                        <option value="Rp 3.000.000 - Rp 5.000.000">Rp 3.000.000 - Rp 5.000.000</option>
                                        <option value="> Rp 5.000.000">Lebih dari Rp 5.000.000</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Data Ibu -->
                        <div class="space-y-4 pt-4">
                            <h4 class="font-bold text-secondary text-sm uppercase tracking-wider border-b border-gray-100 pb-1">Data Ibu Kandung</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="nama_ibu" class="block text-sm font-semibold text-dark mb-1">Nama Lengkap Ibu <span class="text-red-500">*</span></label>
                                    <input type="text" name="nama_ibu" id="nama_ibu" required placeholder="Nama Ibu" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="nik_ibu" class="block text-sm font-semibold text-dark mb-1">NIK Ibu (16 Digit)</label>
                                    <input type="text" name="nik_ibu" id="nik_ibu" maxlength="16" placeholder="NIK Ibu" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="wa_ibu" class="block text-sm font-semibold text-dark mb-1">No. HP/WA Ibu</label>
                                    <input type="text" name="wa_ibu" id="wa_ibu" placeholder="08XXXXXXXXXX" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="thn_lahir_ibu" class="block text-sm font-semibold text-dark mb-1">Tahun Lahir Ibu</label>
                                    <input type="text" name="thn_lahir_ibu" id="thn_lahir_ibu" maxlength="4" placeholder="Contoh: 1982" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="pendidikan_ibu" class="block text-sm font-semibold text-dark mb-1">Pendidikan Terakhir</label>
                                    <select name="pendidikan_ibu" id="pendidikan_ibu" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                        <option value="">-- Pilih --</option>
                                        <option value="SD">SD/Sederajat</option>
                                        <option value="SMP">SMP/Sederajat</option>
                                        <option value="SMA">SMA/Sederajat</option>
                                        <option value="D3">Diploma 3 (D3)</option>
                                        <option value="S1">Sarjana (S1)</option>
                                        <option value="S2">Magister (S2)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="pekerjaan_ibu" class="block text-sm font-semibold text-dark mb-1">Pekerjaan</label>
                                    <input type="text" name="pekerjaan_ibu" id="pekerjaan_ibu" placeholder="Contoh: Ibu Rumah Tangga, Guru" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div class="md:col-span-3">
                                    <label for="penghasilan_ibu" class="block text-sm font-semibold text-dark mb-1">Penghasilan Bulanan</label>
                                    <select name="penghasilan_ibu" id="penghasilan_ibu" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                        <option value="">-- Pilih --</option>
                                        <option value="< Rp 1.000.000">Kurang dari Rp 1.000.000 / Tidak Ada</option>
                                        <option value="Rp 1.000.000 - Rp 3.000.000">Rp 1.000.000 - Rp 3.000.000</option>
                                        <option value="Rp 3.000.000 - Rp 5.000.000">Rp 3.000.000 - Rp 5.000.000</option>
                                        <option value="> Rp 5.000.000">Lebih dari Rp 5.000.000</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Data Wali (Opsional) -->
                        <div class="space-y-4 pt-4">
                            <h4 class="font-bold text-secondary text-sm uppercase tracking-wider border-b border-gray-100 pb-1">Data Wali (Opsional)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="nama_wali" class="block text-sm font-semibold text-dark mb-1">Nama Lengkap Wali</label>
                                    <input type="text" name="nama_wali" id="nama_wali" placeholder="Nama Wali" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label for="wa_wali" class="block text-sm font-semibold text-dark mb-1">No. HP/WA Wali</label>
                                    <input type="text" name="wa_wali" id="wa_wali" placeholder="No WA Wali" class="numeric-only w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <button class="btn-prev border border-gray-300 text-gray-600 hover:bg-gray-100 font-semibold px-5 py-2.5 rounded-lg text-sm transition"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali</button>
                            <button class="btn-next bg-primary hover:bg-secondary text-white font-bold px-6 py-2.5 rounded-lg text-sm shadow transition">Lanjut ke Data Sekolah <i class="fa-solid fa-arrow-right ml-1"></i></button>
                        </div>
                    </div>

                    <!-- STEP 4: Sekolah Asal & Data Periodik -->
                    <div class="form-step hidden space-y-6" data-step="4">
                        <div class="border-b border-gray-100 pb-3">
                            <h3 class="text-lg font-bold text-primary"><i class="fa-solid fa-graduation-cap mr-2"></i> Langkah 4: Sekolah Asal & Data Periodik</h3>
                            <p class="text-xs text-gray-400">Lengkapi data riwayat pendidikan sebelumnya dan riwayat kesehatan.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="jenis_sekolah_asal" class="block text-sm font-semibold text-dark mb-2">Jenis Sekolah Asal <span class="text-red-500">*</span></label>
                                <select name="jenis_sekolah_asal" id="jenis_sekolah_asal" required class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                                    <option value="">-- Pilih Jenis Sekolah --</option>
                                    <option value="SD/MI">SD / MI</option>
                                    <option value="SMP/MTs">SMP / MTs</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label for="nama_sekolah_asal" class="block text-sm font-semibold text-dark mb-2">Nama Sekolah Asal <span class="text-red-500">*</span></label>
                                <input type="text" name="nama_sekolah_asal" id="nama_sekolah_asal" required placeholder="Masukkan Nama Sekolah Asal" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="npsn_sekolah_asal" class="block text-sm font-semibold text-dark mb-2">NPSN Sekolah Asal</label>
                                <input type="text" name="npsn_sekolah_asal" id="npsn_sekolah_asal" placeholder="NPSN Sekolah (jika ada)" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label for="alamat_sekolah_asal" class="block text-sm font-semibold text-dark mb-2">Alamat Sekolah Asal</label>
                                <input type="text" name="alamat_sekolah_asal" id="alamat_sekolah_asal" placeholder="Kota/Kabupaten asal sekolah" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition">
                            </div>

                            <!-- Data Fisik / Periodik -->
                            <div class="md:col-span-2 border-t border-gray-100 pt-4 mt-2">
                                <h4 class="font-bold text-secondary text-sm uppercase tracking-wider mb-4">Data Fisik & Riwayat Penyakit</h4>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label for="tinggi_badan" class="block text-sm font-semibold text-dark mb-1">Tinggi Badan (cm)</label>
                                        <input type="number" name="tinggi_badan" id="tinggi_badan" placeholder="cm" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                    </div>
                                    <div>
                                        <label for="berat_badan" class="block text-sm font-semibold text-dark mb-1">Berat Badan (kg)</label>
                                        <input type="number" name="berat_badan" id="berat_badan" placeholder="kg" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                    </div>
                                    <div>
                                        <label for="lingkar_kepala" class="block text-sm font-semibold text-dark mb-1">Lingkar Kepala (cm)</label>
                                        <input type="number" name="lingkar_kepala" id="lingkar_kepala" placeholder="cm" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-lg px-3 py-2 text-sm focus:outline-none">
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label for="riwayat_penyakit" class="block text-sm font-semibold text-dark mb-2">Riwayat Penyakit (Bila Ada)</label>
                                <textarea name="riwayat_penyakit" id="riwayat_penyakit" rows="2" placeholder="Tuliskan riwayat penyakit bawaan, alergi, atau penanganan medis tertentu jika ada" class="w-full border border-gray-300 focus:border-secondary focus:ring-2 focus:ring-secondary focus:ring-opacity-25 rounded-lg px-4 py-2.5 text-sm focus:outline-none transition"></textarea>
                            </div>
                        </div>

                        <!-- Submit Warning -->
                        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 text-xs leading-relaxed mt-4">
                            <i class="fa-solid fa-triangle-exclamation mr-1.5 text-amber-700 text-sm"></i>
                            <strong>Pernyataan:</strong> Dengan mengirimkan data formulir ini, saya menyatakan bahwa seluruh data calon santri dan orang tua yang diisikan adalah benar dan valid sesuai dokumen resmi yang sah.
                        </div>

                        <div class="flex justify-between pt-4">
                            <button class="btn-prev border border-gray-300 text-gray-600 hover:bg-gray-100 font-semibold px-5 py-2.5 rounded-lg text-sm transition"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali</button>
                            <button type="submit" class="bg-primary hover:bg-secondary text-white font-extrabold px-8 py-2.5 rounded-lg text-sm shadow-md transition flex items-center space-x-1">
                                <i class="fa-solid fa-paper-plane mr-1"></i>
                                <span>Kirim Pendaftaran</span>
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Right Side: Sticky Info Sidebar (Col-span 4) -->
            <div class="lg:col-span-4 lg:sticky lg:top-24 space-y-6">
                <!-- Card 1: Alur & Linimasa Seleksi -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm space-y-4">
                    <h4 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2.5 flex items-center">
                        <i class="fa-solid fa-clock-rotate-left text-secondary mr-2"></i> Alur & Proses Seleksi PSB
                    </h4>
                    <div class="space-y-4 relative pl-3.5 before:absolute before:left-1 before:top-1.5 before:bottom-1.5 before:w-0.5 before:bg-gray-200">
                        <div class="relative">
                            <span class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full bg-secondary border border-white"></span>
                            <h5 class="text-xs font-bold text-primary">1. Pendaftaran Online</h5>
                            <p class="text-[9px] text-gray-500 font-medium mt-0.5">Mengisi data lengkap calon santri dan orang tua secara online.</p>
                        </div>
                        <div class="relative">
                            <span class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full bg-gray-300 border border-white"></span>
                            <h5 class="text-xs font-bold text-primary">2. Verifikasi & Cetak Kartu</h5>
                            <p class="text-[9px] text-gray-500 font-medium mt-0.5">Transfer biaya tes <?= format_rupiah($biaya_tes_global) ?>, validasi slip, dan cetak kartu ujian.</p>
                        </div>
                        <div class="relative">
                            <span class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full bg-gray-300 border border-white"></span>
                            <h5 class="text-xs font-bold text-primary">3. Pelaksanaan Ujian</h5>
                            <p class="text-[9px] text-gray-500 font-medium mt-0.5">Tes tilawah Al-Qur'an, tertulis, dan wawancara luring di pesantren.</p>
                        </div>
                        <div class="relative">
                            <span class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full bg-gray-300 border border-white"></span>
                            <h5 class="text-xs font-bold text-primary">4. Pengumuman Kelulusan</h5>
                            <p class="text-[9px] text-gray-500 font-medium mt-0.5">Hasil tes dapat dicek secara mandiri melalui menu Status & Kelulusan.</p>
                        </div>
                        <div class="relative">
                            <span class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full bg-gray-300 border border-white"></span>
                            <h5 class="text-xs font-bold text-primary">5. Daftar Ulang Santri</h5>
                            <p class="text-[9px] text-gray-500 font-medium mt-0.5">Pembayaran biaya sarana & perlengkapan bagi santri yang lulus.</p>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Informasi Pelaksanaan Ujian -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm space-y-4">
                    <h4 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2.5 flex items-center">
                        <i class="fa-solid fa-hotel text-secondary mr-2"></i> Informasi & Materi Ujian
                    </h4>
                    <div class="space-y-3.5 text-xs font-medium">
                        <div class="space-y-1">
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider block">Lokasi Ujian (Luring):</span>
                            <p class="text-[11px] text-primary font-bold">Kompleks Pondok Pesantren Babul Jannah</p>
                            <p class="text-[9px] text-gray-500 font-normal mt-0.5">Putra: Jl. Sidorejo No.02 | Putri: Jl. Sidorejo No.22, Kel. Ratu Sima, Dumai Selatan, Kota Dumai, Riau.</p>
                        </div>
                        <div class="space-y-1 pt-2 border-t border-gray-100">
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider block">Materi yang Diujikan:</span>
                            <ul class="text-[10px] text-gray-600 space-y-1.5 font-normal list-disc list-inside mt-1 pl-0.5">
                                <li><strong>Membaca Al-Qur'an</strong> (Kelancaran & Tajwid)</li>
                                <li><strong>Wawancara</strong> (Calon Santri & Wali)</li>
                                <li><strong>Tes Agama</strong> (Aqidah, Fiqih, Arab)</li>
                                <li><strong>Tes Umum</strong> (Matematika & IPA)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Syarat Dokumen -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm space-y-4">
                    <h4 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2.5 flex items-center">
                        <i class="fa-solid fa-folder-open text-secondary mr-2"></i> Syarat Berkas Dokumen Fisik
                    </h4>
                    <p class="text-[10px] text-gray-400 leading-relaxed font-normal">
                        Harap dipersiapkan dan dibawa saat pelaksanaan Ujian Seleksi ke lokasi pondok:
                    </p>
                    <ul class="text-xs text-gray-600 space-y-2.5 font-normal">
                        <li class="flex items-start"><i class="fa-solid fa-square-check text-secondary mr-2 mt-0.5"></i> <span>1 Lembar Fotokopi Kartu Keluarga (KK)</span></li>
                        <li class="flex items-start"><i class="fa-solid fa-square-check text-secondary mr-2 mt-0.5"></i> <span>1 Lembar Fotokopi Akta Kelahiran Santri</span></li>
                        <li class="flex items-start"><i class="fa-solid fa-square-check text-secondary mr-2 mt-0.5"></i> <span>3 Lembar Pas Foto 3x4 (Latar Belakang Biru)</span></li>
                        <li class="flex items-start"><i class="fa-solid fa-square-check text-secondary mr-2 mt-0.5"></i> <span>Fotokopi Rapor Sekolah Asal Terakhir</span></li>
                        <li class="flex items-start"><i class="fa-solid fa-square-check text-secondary mr-2 mt-0.5"></i> <span>Surat Keterangan Sehat dari Dokter</span></li>
                    </ul>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Multistep Form Logic JS -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const steps = document.querySelectorAll(".form-step");
    const indicators = document.querySelectorAll(".step-indicator");
    const nextButtons = document.querySelectorAll(".btn-next");
    const prevButtons = document.querySelectorAll(".btn-prev");
    let currentStep = 1;

    function showStep(stepNum) {
        steps.forEach(step => {
            if (parseInt(step.getAttribute("data-step")) === stepNum) {
                step.classList.remove("hidden");
            } else {
                step.classList.add("hidden");
            }
        });

        // Update progress bar indicators
        indicators.forEach((ind, index) => {
            const stepIndex = index + 1;
            if (stepIndex === stepNum) {
                ind.className = "step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 border-secondary text-secondary bg-white shadow-sm ring-2 ring-secondary ring-opacity-20";
            } else if (stepIndex < stepNum) {
                ind.className = "step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 border-emerald-600 bg-emerald-600 text-white shadow-sm";
                ind.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
            } else {
                ind.className = "step-indicator w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border border-gray-300 text-gray-500 bg-white";
                ind.innerHTML = stepIndex;
            }
        });
        
        // Scroll to top of container
        document.getElementById("daftar-form-container").scrollIntoView({ behavior: 'smooth' });
    }

    // Input validations for step 2
    function validateStep2() {
        const nama = document.getElementById("nama_santri").value.trim();
        const jk = document.getElementById("jk").value;
        const nik = document.getElementById("nik_santri").value.trim();
        const nisn = document.getElementById("nisn").value.trim();
        const kk = document.getElementById("no_kk").value.trim();
        const email = document.getElementById("email").value.trim();
        const tempat = document.getElementById("tempat_lahir").value.trim();
        const tgl = document.getElementById("tgl_lahir").value;
        const prov = document.getElementById("provinsi").value;
        const kab = document.getElementById("kabupaten").value;
        const alamat = document.getElementById("alamat_lengkap").value.trim();

        if (!nama || !jk || !nik || !nisn || !kk || !email || !tempat || !tgl || !alamat) {
            Swal.fire({ icon: 'warning', title: 'Data Belum Lengkap', text: 'Harap lengkapi semua kolom wajib (*) bertanda bintang.', confirmButtonColor: '#3E2723' });
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            Swal.fire({ icon: 'warning', title: 'Format Email Salah', text: 'Harap masukkan alamat email yang valid dan aktif.', confirmButtonColor: '#3E2723' });
            return false;
        }

        if (nik.length < 16) {
            Swal.fire({ icon: 'error', title: 'Format NIK Salah', text: 'NIK harus terdiri dari 16 digit.', confirmButtonColor: '#3E2723' });
            return false;
        }

        if (nisn.length < 10) {
            Swal.fire({ icon: 'error', title: 'Format NISN Salah', text: 'NISN harus terdiri dari 10 digit.', confirmButtonColor: '#3E2723' });
            return false;
        }

        if (kk.length < 16) {
            Swal.fire({ icon: 'error', title: 'Format KK Salah', text: 'Nomor Kartu Keluarga harus terdiri dari 16 digit.', confirmButtonColor: '#3E2723' });
            return false;
        }

        if (!prov || !kab) {
            Swal.fire({ icon: 'warning', title: 'Wilayah Belum Dipilih', text: 'Harap pilih Provinsi dan Kabupaten/Kota tempat tinggal.', confirmButtonColor: '#3E2723' });
            return false;
        }

        return true;
    }

    // Input validations for step 3
    function validateStep3() {
        const namaAyah = document.getElementById("nama_ayah").value.trim();
        const waAyah = document.getElementById("wa_ayah").value.trim();
        const namaIbu = document.getElementById("nama_ibu").value.trim();

        if (!namaAyah || !waAyah || !namaIbu) {
            Swal.fire({ icon: 'warning', title: 'Data Orang Tua Belum Lengkap', text: 'Harap lengkapi nama Ayah, Ibu, dan No. HP/WA Ayah.', confirmButtonColor: '#3E2723' });
            return false;
        }

        if (waAyah.length < 10) {
            Swal.fire({ icon: 'warning', title: 'Nomor HP/WA Kurang', text: 'Nomor HP/WA Ayah minimal 10 digit.', confirmButtonColor: '#3E2723' });
            return false;
        }

        return true;
    }

    // Input validations for step 4
    function validateStep4() {
        const jenisSekolah = document.getElementById("jenis_sekolah_asal").value;
        const namaSekolah = document.getElementById("nama_sekolah_asal").value.trim();

        if (!jenisSekolah || !namaSekolah) {
            Swal.fire({ icon: 'warning', title: 'Data Sekolah Belum Lengkap', text: 'Harap pilih Jenis Sekolah Asal dan isi Nama Sekolah Asal.', confirmButtonColor: '#3E2723' });
            return false;
        }
        return true;
    }

    nextButtons.forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            
            if (currentStep === 1) {
                const jenjang = document.getElementById("jalur_pendaftaran").value;
                if (!jenjang) {
                    Swal.fire({ icon: 'warning', title: 'Jalur Belum Dipilih', text: 'Silakan pilih Jalur/Jenjang Pendidikan terlebih dahulu.', confirmButtonColor: '#3E2723' });
                    return;
                }
                currentStep = 2;
                showStep(currentStep);
            } else if (currentStep === 2) {
                if (validateStep2()) {
                    currentStep = 3;
                    showStep(currentStep);
                }
            } else if (currentStep === 3) {
                if (validateStep3()) {
                    currentStep = 4;
                    showStep(currentStep);
                }
            }
        });
    });

    const formElement = document.querySelector('form[action="formulir.php"]');
    if (formElement) {
        formElement.addEventListener('submit', function(e) {
            if (!validateStep4()) {
                e.preventDefault();
            }
        });
    }

    prevButtons.forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });
    });

    // Validasi input numerik
    document.querySelectorAll(".numeric-only").forEach(input => {
        input.addEventListener("input", function(e) {
            this.value = this.value.replace(/[^0-9]/g, "");
        });
    });

    // === DYNAMIC WILAYAH API ===
    const provSelect = document.getElementById('provinsi');
    const kabSelect = document.getElementById('kabupaten');
    const kecSelect = document.getElementById('kecamatan');
    const kelSelect = document.getElementById('kelurahan');

    // Fetch Provinces
    fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">-- Pilih Provinsi --</option>';
            data.forEach(prov => {
                options += `<option value="${prov.name}" data-id="${prov.id}">${prov.name}</option>`;
            });
            provSelect.innerHTML = options;
        })
        .catch(error => {
            console.error('Error fetching provinces:', error);
            provSelect.innerHTML = '<option value="">-- Gagal memuat data --</option>';
        });

    provSelect.addEventListener('change', function() {
        kabSelect.innerHTML = '<option value="">-- Memuat... --</option>';
        kabSelect.disabled = true;
        kecSelect.innerHTML = '<option value="">-- Pilih Kota Dahulu --</option>';
        kecSelect.disabled = true;
        kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
        kelSelect.disabled = true;
        
        const selectedOption = this.options[this.selectedIndex];
        const provId = selectedOption.getAttribute('data-id');
        
        if (provId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
                    data.forEach(kab => {
                        options += `<option value="${kab.name}" data-id="${kab.id}">${kab.name}</option>`;
                    });
                    kabSelect.innerHTML = options;
                    kabSelect.disabled = false;
                });
        } else {
            kabSelect.innerHTML = '<option value="">-- Pilih Provinsi Dahulu --</option>';
        }
    });

    kabSelect.addEventListener('change', function() {
        kecSelect.innerHTML = '<option value="">-- Memuat... --</option>';
        kecSelect.disabled = true;
        kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
        kelSelect.disabled = true;
        
        const selectedOption = this.options[this.selectedIndex];
        const kabId = selectedOption.getAttribute('data-id');
        
        if (kabId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${kabId}.json`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kecamatan --</option>';
                    data.forEach(kec => {
                        options += `<option value="${kec.name}" data-id="${kec.id}">${kec.name}</option>`;
                    });
                    kecSelect.innerHTML = options;
                    kecSelect.disabled = false;
                });
        } else {
            kecSelect.innerHTML = '<option value="">-- Pilih Kota Dahulu --</option>';
        }
    });

    kecSelect.addEventListener('change', function() {
        kelSelect.innerHTML = '<option value="">-- Memuat... --</option>';
        kelSelect.disabled = true;
        
        const selectedOption = this.options[this.selectedIndex];
        const kecId = selectedOption.getAttribute('data-id');
        
        if (kecId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${kecId}.json`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">-- Pilih Kelurahan/Desa --</option>';
                    data.forEach(kel => {
                        options += `<option value="${kel.name}" data-id="${kel.id}">${kel.name}</option>`;
                    });
                    kelSelect.innerHTML = options;
                    kelSelect.disabled = false;
                });
        } else {
            kelSelect.innerHTML = '<option value="">-- Pilih Kecamatan Dahulu --</option>';
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
