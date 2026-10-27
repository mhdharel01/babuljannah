<?php
// status.php
require_once 'config/db.php';
require_once 'config/telegram.php';

$pendaftar = null;
$searched = false;
$error = '';
$upload_success = '';
$edit_success = isset($_GET['edit_success']) ? "Data formulir pendaftaran berhasil diperbarui!" : '';

// Ambil Biaya Formulir / Tes Seleksi (1 Nilai Global)
$biaya_tes_global = get_biaya_tes();

// Handle unggah bukti transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bukti') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid atau telah kedaluwarsa. Silakan refresh halaman dan coba kembali.";
    } else {
        $no_reg_post = sanitize($_POST['no_registrasi'] ?? '');
        $phone_post = sanitize($_POST['phone'] ?? '');
        
        // Normalisasi nomor telepon
        $clean_phone = preg_replace('/[^0-9]/', '', $phone_post);
        $alt_phone = (substr($clean_phone, 0, 1) === '0') ? ('62' . substr($clean_phone, 1)) : ((substr($clean_phone, 0, 2) === '62') ? ('0' . substr($clean_phone, 2)) : $clean_phone);
        
        // Cari data pendaftar (cek wa_ayah, wa_ibu, atau wa_wali)
        $stmt = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang, COALESCE(g.tahun_ajaran, '-') as tahun_ajaran, g.tgl_ujian, g.tgl_pengumuman FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE p.no_registrasi = :no_reg AND (p.wa_ayah IN (:p1, :p2) OR p.wa_ibu IN (:p1, :p2) OR p.wa_wali IN (:p1, :p2))");
        $stmt->execute(['no_reg' => $no_reg_post, 'p1' => $clean_phone, 'p2' => $alt_phone]);
        $pendaftar = $stmt->fetch();

        if ($pendaftar) {
            $searched = true;
            $_SESSION['verified_reg_' . $pendaftar['no_registrasi']] = true;
            if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['bukti_bayar']['tmp_name'];
                $file_name = $_FILES['bukti_bayar']['name'];
                $file_size = $_FILES['bukti_bayar']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "Hanya file JPG, JPEG, dan PNG yang diperbolehkan.";
                } elseif ($file_size > 3 * 1024 * 1024) { // 3 MB
                    $error = "Ukuran file bukti bayar maksimal adalah 3 MB.";
                } else {
                    // Gunakan fungsi kompresi dan konversi WebP dari db.php
                    $prefix = "bukti_" . str_replace('-', '_', $no_reg_post);
                    $new_file_name = upload_and_convert_to_webp('bukti_bayar', 'uploads', $prefix);
                    
                    if ($new_file_name) {
                        // Update database
                        $stmt_update = $db->prepare("UPDATE pendaftaran SET bukti_bayar_tes = :bukti, tgl_upload_bayar = NOW(), status_pembayaran = 'Menunggu Verifikasi' WHERE id = :id");
                        $stmt_update->execute([
                            'bukti' => $new_file_name,
                            'id' => $pendaftar['id']
                        ]);
                        
                        $upload_success = "Bukti pembayaran berhasil diunggah! Status pendaftaran Anda kini: Menunggu Verifikasi.";
                        
                        // Notifikasi ke Telegram
                        $tel_msg = "💰 <b>BUKTI BAYAR DIUNGGAH!</b>\n";
                        $tel_msg .= "--------------------------------------\n";
                        $tel_msg .= "<b>No. Reg:</b> <code>" . $pendaftar['no_registrasi'] . "</code>\n";
                        $tel_msg .= "<b>Nama:</b> " . htmlspecialchars($pendaftar['nama_santri']) . "\n";
                        $tel_msg .= "<b>Jalur:</b> " . htmlspecialchars($pendaftar['jalur_pendaftaran']) . "\n\n";
                        $tel_msg .= "<i>Cek selengkapnya di:</i>\n";
                        $tel_msg .= "👉 https://" . $_SERVER['HTTP_HOST'] . "/admin/login.php";
                        send_telegram_msg($tel_msg);
                        
                        // Reload data terbaru
                        $stmt_reload = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang, COALESCE(g.tahun_ajaran, '-') as tahun_ajaran, g.tgl_ujian, g.tgl_pengumuman FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE p.id = :id");
                        $stmt_reload->execute(['id' => $pendaftar['id']]);
                        $pendaftar = $stmt_reload->fetch();
                    } else {
                        $error = "Gagal memproses file. Pastikan file bukan gambar yang rusak dan ukurannya sesuai.";
                    }
                }
            } else {
                $error = "Silakan pilih file bukti pembayaran terlebih dahulu.";
            }
        } else {
            $error = "Data pendaftar tidak ditemukan untuk proses unggah bukti transfer.";
        }
    }
}

// Handle pencarian status biasa
$no_reg = sanitize($_GET['no_reg'] ?? '');
$phone = sanitize($_GET['phone'] ?? '');

if (!empty($no_reg) && !empty($phone)) {
    $searched = true;
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $alt_phone = (substr($clean_phone, 0, 1) === '0') ? ('62' . substr($clean_phone, 1)) : ((substr($clean_phone, 0, 2) === '62') ? ('0' . substr($clean_phone, 2)) : $clean_phone);
    
    $stmt = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang, COALESCE(g.tahun_ajaran, '-') as tahun_ajaran, g.tgl_ujian, g.tgl_pengumuman FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE p.no_registrasi = :no_reg AND (p.wa_ayah IN (:p1, :p2) OR p.wa_ibu IN (:p1, :p2) OR p.wa_wali IN (:p1, :p2))");
    $stmt->execute(['no_reg' => $no_reg, 'p1' => $clean_phone, 'p2' => $alt_phone]);
    $pendaftar = $stmt->fetch();
    
    if (!$pendaftar) {
        $error = "Kombinasi Nomor Registrasi dan No. HP/WA Orang Tua tidak ditemukan. Silakan periksa kembali inputan Anda.";
    } else {
        $_SESSION['verified_reg_' . $pendaftar['no_registrasi']] = true;
    }
}

// Cek apakah tanggal pengumuman masih terkunci (belum tiba waktunya)
$belum_waktu_pengumuman = false;
$tgl_pengumuman_db = '';
if ($pendaftar) {
    $tgl_sekarang = date('Y-m-d');
    $tgl_pengumuman_db = $pendaftar['tgl_pengumuman'] ?? '';
    $belum_waktu_pengumuman = !empty($tgl_pengumuman_db) && ($tgl_pengumuman_db !== '0000-00-00') && ($tgl_sekarang < $tgl_pengumuman_db);
}

require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print" data-aos="fade-down">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Cek Status & Tagihan</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Gunakan Nomor Registrasi dan Nomor WA Orang Tua (Bapak/Ayah) untuk melihat perkembangan pendaftaran santri.
        </p>
    </div>
</section>

<section class="py-12 bg-accent flex-grow no-print" data-aos="fade-up">
    <div class="max-w-7xl mx-auto px-4">
        
        <div class="max-w-4xl mx-auto mb-6">
            <?php if (isset($_SESSION['success_reg'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm flex items-start space-x-3 mb-4">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg mt-0.5"></i>
                <div>
                    <strong class="font-bold block">Pendaftaran Berhasil!</strong>
                    <span>Pendaftaran Anda telah diterima. Silakan catat Nomor Registrasi Anda dan ikuti panduan transfer biaya pendaftaran di bagian bawah halaman ini.</span>
                </div>
            </div>
            <?php unset($_SESSION['success_reg']); endif; ?>

            <?php if ($upload_success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm flex items-start space-x-3 mb-4">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg mt-0.5"></i>
                <div>
                    <strong>Berhasil!</strong> <?= $upload_success ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($edit_success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm flex items-start space-x-3 mb-4">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg mt-0.5"></i>
                <div>
                    <strong>Berhasil!</strong> <?= $edit_success ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl text-sm flex items-start space-x-3 mb-4">
                <i class="fa-solid fa-circle-xmark text-red-600 text-lg mt-0.5"></i>
                <div>
                    <strong>Error!</strong> <?= $error ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$searched): ?>
            <!-- 2-Column Layout for Landing Page -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
                <!-- Left: Form -->
                <div class="lg:col-span-7 bg-white rounded-3xl shadow-sm border border-gray-150 p-8 flex flex-col justify-between">
                    <div class="space-y-4">
                        <div class="inline-flex items-center space-x-2 bg-secondary/10 text-primary text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Portal Status & Hasil Seleksi</span>
                        </div>
                        <h3 class="font-extrabold text-primary text-xl tracking-tight font-sans">Periksa Status & Pengumuman</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-normal">
                            Gunakan Nomor Registrasi pendaftaran dan Nomor HP/WhatsApp Orang Tua (Ayah) untuk memeriksa status verifikasi pembayaran, cetak kartu ujian, pengumuman seleksi berkas, hasil tes kelulusan santri baru, serta panduan konfirmasi proses daftar ulang.
                        </p>
                    </div>

                    <form action="status.php" method="GET" class="space-y-5 mt-8">
                        <div class="space-y-2">
                            <label for="no_reg" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">No. Registrasi Calon Santri</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-450">
                                    <i class="fa-solid fa-id-card-clip text-xs"></i>
                                </span>
                                <input type="text" name="no_reg" id="no_reg" value="<?= $no_reg ?>" required placeholder="CONTOH: PPBJ-2026-001" class="w-full border border-gray-250 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl pl-10 pr-4 py-3 text-xs focus:outline-none uppercase font-bold tracking-wider">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="phone" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">No. HP/WhatsApp Orang Tua (Ayah)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-450">
                                    <i class="fa-solid fa-phone text-xs"></i>
                                </span>
                                <input type="text" name="phone" id="phone" value="<?= $phone ?>" required placeholder="CONTOH: 081234567890" class="numeric-only w-full border border-gray-250 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl pl-10 pr-4 py-3 text-xs focus:outline-none">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-bold py-3.5 rounded-xl text-xs shadow transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            <span>Periksa Status & Hasil Kelulusan</span>
                        </button>
                    </form>
                </div>

                <!-- Right: Guide -->
                <div class="lg:col-span-5 bg-primary text-white rounded-3xl p-8 shadow-xl relative overflow-hidden flex flex-col justify-between min-h-[400px]">
                    <!-- Background Pattern Overlay -->
                    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-15 mix-blend-overlay"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-primary via-primary/85 to-transparent"></div>

                    <div class="relative z-10 space-y-4">
                        <span class="text-secondary font-bold text-[10px] uppercase tracking-widest block">Informasi PSB Online</span>
                        <h4 class="font-extrabold text-white text-lg font-sans">Layanan Status & Kelulusan</h4>
                        <div class="w-10 h-0.5 bg-secondary rounded"></div>
                    </div>

                    <div class="relative z-10 space-y-5 my-8">
                        <div class="flex items-start space-x-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center flex-shrink-0 text-secondary text-sm">
                                <i class="fa-solid fa-award"></i>
                            </div>
                            <div class="space-y-1">
                                <h5 class="font-bold text-xs">Cek Pengumuman Kelulusan</h5>
                                <p class="text-[10px] text-gray-300 leading-relaxed font-normal">Ketahui langsung hasil pengumuman kelulusan seleksi administrasi berkas dan ujian masuk (tes tulis/lisan & tahfizh) secara fokus dan transparan di sini.</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center flex-shrink-0 text-secondary text-sm">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div class="space-y-1">
                                <h5 class="font-bold text-xs">Unggah Bukti & Verifikasi Pembayaran</h5>
                                <p class="text-[10px] text-gray-300 leading-relaxed font-normal">Kirim slip bukti pembayaran administrasi pendaftaran awal untuk divalidasi oleh panitia PSB agar status menjadi lunas.</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center flex-shrink-0 text-secondary text-sm">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div class="space-y-1">
                                <h5 class="font-bold text-xs">Konfirmasi Daftar Ulang via WhatsApp</h5>
                                <p class="text-[10px] text-gray-300 leading-relaxed font-normal">Bagi calon santri yang dinyatakan lulus seleksi masuk, silakan langsung menghubungi panitia melalui WhatsApp untuk panduan prosedur proses daftar ulang.</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 pt-4 border-t border-white/10 flex items-center space-x-2 text-[9px] text-gray-350 font-bold uppercase tracking-wider">
                        <i class="fa-solid fa-headset text-secondary"></i>
                        <span>Pusat Bantuan PSB: wa.me/6281170789988</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Searched: Show Search Form on top (compact max-w-4xl) and Results below -->
            <div class="max-w-4xl mx-auto space-y-8">
                <!-- Header Action -->
                <div class="flex justify-between items-center bg-white rounded-2xl shadow-sm border border-gray-150 p-4 px-6">
                    <h3 class="font-bold text-primary text-sm flex items-center"><i class="fa-solid fa-address-card mr-2 text-secondary"></i> Dasbor Calon Santri</h3>
                    <a href="status.php" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg transition flex items-center space-x-1">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Tutup / Cari Data Lain</span>
                    </a>
                </div>

                <!-- Detail Pendaftar -->
                <?php if ($pendaftar): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-primary p-6 text-white flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <span class="text-xs uppercase font-semibold text-gray-300 tracking-wider">Nomor Registrasi</span>
                        <h2 class="text-2xl font-bold text-accent tracking-wider"><?= $pendaftar['no_registrasi'] ?></h2>
                    </div>
                    <div class="mt-4 md:mt-0 bg-white bg-opacity-10 px-4 py-2 rounded-xl border border-white border-opacity-15 text-sm">
                        <span>Status Pembayaran: </span>
                        <?php if ($pendaftar['status_pembayaran'] === 'Belum Bayar'): ?>
                            <span class="text-amber-400 font-bold"><i class="fa-solid fa-clock mr-1"></i> Belum Bayar</span>
                        <?php elseif ($pendaftar['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                            <span class="text-amber-300 font-bold animate-pulse"><i class="fa-solid fa-spinner mr-1"></i> Menunggu Verifikasi</span>
                        <?php else: ?>
                            <span class="text-emerald-400 font-bold"><i class="fa-solid fa-circle-check mr-1"></i> Lunas</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stepper Progress Indicator -->
                <div class="px-6 md:px-8 pt-6 pb-2">
                    <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0 md:space-x-4 bg-accent p-5 rounded-2xl border border-gray-150">
                        <!-- Step 1: Adm Ujian -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $pendaftar['status_pembayaran'] === 'Lunas' ? 'bg-emerald-600 text-white shadow-md' : 'bg-amber-500 text-white shadow-md' ?>">
                                <?php if ($pendaftar['status_pembayaran'] === 'Lunas'): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php else: ?>
                                    1
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="text-xs font-extrabold text-primary">Administrasi Ujian</h5>
                                <p class="text-[9px] text-gray-500 font-medium">
                                    <?= $pendaftar['status_pembayaran'] === 'Lunas' ? 'Lunas / Terverifikasi' : 'Proses Verifikasi' ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Arrow divider (desktop only) -->
                        <div class="hidden md:block text-gray-355"><i class="fa-solid fa-chevron-right text-xs"></i></div>

                        <!-- Step 2: Hasil Seleksi -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?php
                                if ($pendaftar['status_pembayaran'] !== 'Lunas') echo 'bg-gray-200 text-gray-400';
                                elseif ($belum_waktu_pengumuman) echo 'bg-amber-500 text-white shadow-md animate-pulse';
                                elseif ($pendaftar['status_seleksi'] === 'Belum Seleksi') echo 'bg-blue-500 text-white shadow-md animate-pulse';
                                elseif ($pendaftar['status_seleksi'] === 'Lulus') echo 'bg-emerald-600 text-white shadow-md';
                                else echo 'bg-red-500 text-white shadow-md';
                            ?>">
                                <?php if ($pendaftar['status_pembayaran'] === 'Lunas' && !$belum_waktu_pengumuman && $pendaftar['status_seleksi'] === 'Lulus'): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php elseif ($pendaftar['status_pembayaran'] === 'Lunas' && !$belum_waktu_pengumuman && $pendaftar['status_seleksi'] === 'Tidak Lulus'): ?>
                                    <i class="fa-solid fa-xmark"></i>
                                <?php elseif ($belum_waktu_pengumuman): ?>
                                    <i class="fa-solid fa-lock"></i>
                                <?php else: ?>
                                    2
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="text-xs font-extrabold text-primary">Hasil Seleksi Masuk</h5>
                                <p class="text-[9px] text-gray-500 font-medium">
                                    <?php
                                    if ($pendaftar['status_pembayaran'] !== 'Lunas') echo 'Menunggu Pembayaran';
                                    elseif ($belum_waktu_pengumuman) echo 'Menunggu Jadwal Rilis';
                                    elseif ($pendaftar['status_seleksi'] === 'Belum Seleksi') echo 'Menunggu Ujian';
                                    elseif ($pendaftar['status_seleksi'] === 'Lulus') echo 'Dinyatakan LULUS';
                                    else echo 'BELUM LULUS';
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Arrow divider (desktop only) -->
                        <div class="hidden md:block text-gray-355"><i class="fa-solid fa-chevron-right text-xs"></i></div>

                        <!-- Step 3: Daftar Ulang -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?php
                                if ($belum_waktu_pengumuman || $pendaftar['status_seleksi'] !== 'Lulus') echo 'bg-gray-200 text-gray-400';
                                else echo 'bg-primary text-white shadow-md';
                            ?>">
                                3
                            </div>
                            <div>
                                <h5 class="text-xs font-extrabold text-primary">Konfirmasi Daftar Ulang</h5>
                                <p class="text-[9px] text-gray-500 font-medium">
                                    <?= (!$belum_waktu_pengumuman && $pendaftar['status_seleksi'] === 'Lulus') ? 'Silakan Hubungi Panitia' : 'Menunggu Kelulusan' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area (Tanpa Tab dan Tanpa Rincian Tagihan) -->
                <div class="p-6 md:p-8 space-y-8">
                    <?php if (in_array($pendaftar['status_pembayaran'], ['Belum Bayar', 'Menunggu Verifikasi'])): ?>
                    <div class="bg-blue-50 border border-blue-200 p-5 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0 text-xl"><i class="fa-solid fa-pen-to-square"></i></div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-800">Perlu Memperbaiki Data?</h4>
                                <p class="text-xs text-gray-500">Anda dapat mengubah data diri selama pendaftaran belum terverifikasi.</p>
                            </div>
                        </div>
                        <form action="edit_data_santri.php" method="POST">
                            <input type="hidden" name="no_reg" value="<?= htmlspecialchars($no_reg) ?>">
                            <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-md transition w-full md:w-auto flex items-center justify-center space-x-2">
                                <span>Perbaiki Data Formulir</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Ringkasan Profil Calon Santri -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-gray-100">
                        <div class="space-y-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Calon Santri</span>
                            <h3 class="text-lg font-bold text-primary"><?= $pendaftar['nama_santri'] ?></h3>
                            <p class="text-sm text-gray-600">Jalur: <span class="font-bold text-secondary"><?= $pendaftar['jalur_pendaftaran'] ?></span></p>
                            <p class="text-sm text-gray-600">Jenis Kelamin: <?= $pendaftar['jk'] ?></p>
                        </div>
                        <div class="space-y-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Pendaftaran</span>
                            <p class="text-sm text-gray-600">Gelombang: <span class="font-bold"><?= $pendaftar['nama_gelombang'] ?> (T.A <?= $pendaftar['tahun_ajaran'] ?>)</span></p>
                            <p class="text-sm text-gray-600">Tanggal Daftar: <?= format_tanggal($pendaftar['created_at']) ?></p>
                        </div>
                    </div>

                    <!-- Lihat Detail Formulir (Accordion) -->
                    <div class="mb-8 border border-gray-150 rounded-2xl overflow-hidden bg-white shadow-sm">
                        <details class="group">
                            <summary class="flex items-center justify-between cursor-pointer p-4 md:p-5 bg-gray-50/50 hover:bg-gray-100/50 transition">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                        <i class="fa-solid fa-file-lines text-sm"></i>
                                    </div>
                                    <span class="font-bold text-gray-700 text-sm">Lihat Detail Data Formulir Anda</span>
                                </div>
                                <span class="text-gray-400 group-open:rotate-180 transition-transform duration-300">
                                    <i class="fa-solid fa-chevron-down text-sm"></i>
                                </span>
                            </summary>
                            <div class="p-5 md:p-6 border-t border-gray-150 bg-white space-y-8 text-sm">
                                <!-- Biodata -->
                                <div>
                                    <h4 class="font-bold text-primary border-b border-gray-100 pb-2 mb-3 text-xs uppercase tracking-wider">Biodata Santri</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6 text-gray-600">
                                        <div><span class="text-xs text-gray-400 block">NIK:</span> <?= htmlspecialchars($pendaftar['nik_santri']) ?></div>
                                        <div><span class="text-xs text-gray-400 block">NISN:</span> <?= htmlspecialchars($pendaftar['nisn']) ?: '-' ?></div>
                                        <div><span class="text-xs text-gray-400 block">No. KK:</span> <?= htmlspecialchars($pendaftar['no_kk']) ?></div>
                                        <div><span class="text-xs text-gray-400 block">Tempat, Tanggal Lahir:</span> <?= htmlspecialchars($pendaftar['tempat_lahir']) ?>, <?= format_tanggal($pendaftar['tgl_lahir']) ?></div>
                                        <div><span class="text-xs text-gray-400 block">Status dalam Keluarga:</span> <?= htmlspecialchars($pendaftar['status_anak']) ?></div>
                                        <div><span class="text-xs text-gray-400 block">Berkebutuhan Khusus:</span> <?= htmlspecialchars($pendaftar['berkebutuhan_khusus']) ?: 'Tidak Ada' ?></div>
                                        <div><span class="text-xs text-gray-400 block">Penerima PIP/KIP:</span> <?= htmlspecialchars($pendaftar['pip']) ?: 'Tidak' ?></div>
                                        <div class="md:col-span-2">
                                            <span class="text-xs text-gray-400 block">Alamat Rumah Lengkap:</span> 
                                            <?= nl2br(htmlspecialchars($pendaftar['alamat_lengkap'])) ?><br>
                                            <span class="text-gray-500 font-medium">Kel. <?= htmlspecialchars($pendaftar['kelurahan'] ?? '-') ?>, Kec. <?= htmlspecialchars($pendaftar['kecamatan'] ?? '-') ?>, <?= htmlspecialchars($pendaftar['kabupaten'] ?? '-') ?>, <?= htmlspecialchars($pendaftar['provinsi'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Data Orang Tua / Wali -->
                                <div>
                                    <h4 class="font-bold text-primary border-b border-gray-100 pb-2 mb-3 text-xs uppercase tracking-wider">Data Orang Tua & Wali</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-5 gap-x-6 text-gray-600">
                                        
                                        <!-- Ayah -->
                                        <div class="space-y-2">
                                            <div><span class="text-xs text-gray-400 block">Nama Ayah:</span> <span class="font-bold"><?= htmlspecialchars($pendaftar['nama_ayah']) ?></span></div>
                                            <div><span class="text-xs text-gray-400 block">NIK Ayah:</span> <?= htmlspecialchars($pendaftar['nik_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Tahun Lahir Ayah:</span> <?= htmlspecialchars($pendaftar['thn_lahir_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Pendidikan Ayah:</span> <?= htmlspecialchars($pendaftar['pendidikan_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Pekerjaan Ayah:</span> <?= htmlspecialchars($pendaftar['pekerjaan_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Penghasilan Ayah:</span> <?= htmlspecialchars($pendaftar['penghasilan_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">No. HP/WA Ayah:</span> <?= htmlspecialchars($pendaftar['wa_ayah']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Email Aktif:</span> <?= htmlspecialchars($pendaftar['email']) ?: '-' ?></div>
                                        </div>

                                        <!-- Ibu -->
                                        <div class="space-y-2">
                                            <div><span class="text-xs text-gray-400 block">Nama Ibu:</span> <span class="font-bold"><?= htmlspecialchars($pendaftar['nama_ibu']) ?></span></div>
                                            <div><span class="text-xs text-gray-400 block">NIK Ibu:</span> <?= htmlspecialchars($pendaftar['nik_ibu']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Tahun Lahir Ibu:</span> <?= htmlspecialchars($pendaftar['thn_lahir_ibu']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Pendidikan Ibu:</span> <?= htmlspecialchars($pendaftar['pendidikan_ibu']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Pekerjaan Ibu:</span> <?= htmlspecialchars($pendaftar['pekerjaan_ibu']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">Penghasilan Ibu:</span> <?= htmlspecialchars($pendaftar['penghasilan_ibu']) ?></div>
                                            <div><span class="text-xs text-gray-400 block">No. HP/WA Ibu:</span> <?= htmlspecialchars($pendaftar['wa_ibu']) ?></div>
                                        </div>

                                        <!-- Wali -->
                                        <?php if (!empty($pendaftar['nama_wali'])): ?>
                                        <div class="space-y-2 md:col-span-2 border-t border-gray-100 pt-3">
                                            <div><span class="text-xs text-gray-400 block">Nama Wali (Opsional):</span> <span class="font-bold"><?= htmlspecialchars($pendaftar['nama_wali']) ?></span></div>
                                            <div><span class="text-xs text-gray-400 block">No. HP/WA Wali:</span> <?= htmlspecialchars($pendaftar['wa_wali']) ?: '-' ?></div>
                                        </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                                
                                <!-- Data Tambahan -->
                                <div>
                                    <h4 class="font-bold text-primary border-b border-gray-100 pb-2 mb-3 text-xs uppercase tracking-wider">Sekolah Asal & Fisik</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-6 text-gray-600">
                                        <div><span class="text-xs text-gray-400 block">Asal Sekolah:</span> <?= htmlspecialchars((string)($pendaftar['nama_sekolah_asal'] ?? '-')) ?> (<?= htmlspecialchars((string)($pendaftar['jenis_sekolah_asal'] ?? '-')) ?>)</div>
                                        <div><span class="text-xs text-gray-400 block">NPSN Sekolah Asal:</span> <?= htmlspecialchars((string)($pendaftar['npsn_sekolah_asal'] ?? '-')) ?></div>
                                        <div class="md:col-span-2"><span class="text-xs text-gray-400 block">Alamat Sekolah Asal:</span> <?= htmlspecialchars((string)($pendaftar['alamat_sekolah_asal'] ?? '-')) ?></div>
                                        <div class="col-span-1 md:col-span-2 my-2 border-b border-gray-100"></div>
                                        <div><span class="text-xs text-gray-400 block">Tinggi / Berat Badan:</span> <?= htmlspecialchars((string)($pendaftar['tinggi_badan'] ?? '-')) ?> cm / <?= htmlspecialchars((string)($pendaftar['berat_badan'] ?? '-')) ?> kg</div>
                                        <div><span class="text-xs text-gray-400 block">Lingkar Kepala:</span> <?= htmlspecialchars((string)($pendaftar['lingkar_kepala'] ?? '-')) ?> cm</div>
                                        <div class="md:col-span-2"><span class="text-xs text-gray-400 block">Riwayat Penyakit:</span> <?= htmlspecialchars((string)($pendaftar['riwayat_penyakit'] ?? '')) ?: 'Tidak Ada' ?></div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    <?php if (($pendaftar['status_seleksi'] === 'Lulus' || $pendaftar['status_seleksi'] === 'Tidak Lulus') && !$belum_waktu_pengumuman): ?>
                        <!-- TAHAP FINAL: PENGUMUMAN HASIL SUDAH DIBUKA (Tanpa Verifikasi Ujian & Dokumen) -->
                        <?php if ($pendaftar['status_seleksi'] === 'Lulus'): ?>
                            <!-- Tambahkan library canvas-confetti -->
                            <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    setTimeout(function() {
                                        var el = document.getElementById("pengumuman-lulus");
                                        if (el) {
                                            el.scrollIntoView({ behavior: "smooth", block: "start" });
                                        }
                                    }, 400);

                                    var duration = 4 * 1000;
                                    var animationEnd = Date.now() + duration;
                                    var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 100 };
                        
                                    function randomInRange(min, max) {
                                        return Math.random() * (max - min) + min;
                                    }
                        
                                    var interval = setInterval(function() {
                                        var timeLeft = animationEnd - Date.now();
                        
                                        if (timeLeft <= 0) {
                                            return clearInterval(interval);
                                        }
                        
                                        var particleCount = 50 * (timeLeft / duration);
                                        confetti(Object.assign({}, defaults, { particleCount,
                                            origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
                                        }));
                                        confetti(Object.assign({}, defaults, { particleCount,
                                            origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
                                        }));
                                    }, 250);
                                });
                            </script>

                            <!-- Pengumuman LULUS SELEKSI (Fokus & Prestisius) -->
                            <div id="pengumuman-lulus" class="scroll-mt-32 bg-gradient-to-br from-emerald-900 via-emerald-800 to-emerald-950 text-white p-6 md:p-8 rounded-3xl shadow-2xl relative overflow-hidden border border-emerald-500/30 space-y-6">
                                <!-- Decorative Background Glow -->
                                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-secondary/20 rounded-full blur-3xl pointer-events-none"></div>
                                <div class="absolute -left-10 -top-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
                                
                                <!-- Header Pengumuman -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-white/15 relative z-10">
                                    <div class="flex items-center space-x-3.5">
                                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-secondary to-amber-500 text-white flex items-center justify-center text-2xl shadow-lg shadow-amber-500/20 flex-shrink-0 animate-bounce">
                                            <i class="fa-solid fa-award"></i>
                                        </div>
                                        <div>
                                            <span class="inline-block bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-widest mb-1">Surat Keputusan Resmi</span>
                                            <h5 class="font-extrabold text-white text-lg md:text-xl tracking-tight font-sans">SELAMAT! CALON SANTRI DINYATAKAN LULUS</h5>
                                        </div>
                                    </div>
                                    <span class="bg-secondary text-white text-xs font-extrabold px-4 py-2 rounded-xl shadow-md uppercase tracking-wider text-center self-start sm:self-center border border-amber-400/30">
                                        <i class="fa-solid fa-circle-check mr-1.5"></i> LULUS SELEKSI
                                    </span>
                                </div>

                                <!-- Isi Pengumuman Formal -->
                                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 relative z-10 space-y-4">
                                    <p class="text-xs md:text-sm text-emerald-100 leading-relaxed font-normal">
                                        Berdasarkan hasil rapat pleno Panitia Penerimaan Santri Baru (PSB) serta evaluasi ujian tes tertulis, tes bacaan/hafalan Al-Qur'an, dan wawancara, dengan ini diputuskan bahwa:
                                    </p>
                                    
                                    <div class="bg-emerald-950/60 p-4 rounded-xl border border-emerald-700/50 space-y-2 text-xs md:text-sm">
                                        <div class="flex flex-col sm:flex-row sm:justify-between py-1 border-b border-white/10">
                                            <span class="text-emerald-300 font-medium">Nama Calon Santri:</span>
                                            <span class="font-extrabold text-white text-sm md:text-base"><?= $pendaftar['nama_santri'] ?></span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between py-1 border-b border-white/10">
                                            <span class="text-emerald-300 font-medium">Nomor Registrasi:</span>
                                            <span class="font-bold text-amber-300 font-mono"><?= $pendaftar['no_registrasi'] ?></span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between py-1 border-b border-white/10">
                                            <span class="text-emerald-300 font-medium">Gelombang & Tahun Ajaran:</span>
                                            <span class="font-semibold text-white"><?= $pendaftar['nama_gelombang'] ?> (T.A <?= $pendaftar['tahun_ajaran'] ?>)</span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:justify-between py-1">
                                            <span class="text-emerald-300 font-medium">Jenjang Pendidikan:</span>
                                            <span class="font-bold text-secondary"><?= $pendaftar['jalur_pendaftaran'] ?></span>
                                        </div>
                                    </div>

                                    <p class="text-xs text-emerald-200/90 leading-relaxed italic font-serif">
                                        "Ahlan wa Sahlan di keluarga besar Pondok Pesantren Babul Jannah. Semoga langkah awal ini menjadi jalan keberkahan untuk menjadi generasi Rabbani penghafal Al-Qur'an yang berakhlak mulia."
                                    </p>
                                </div>

                                <!-- Bagian Instruksi Daftar Ulang Tanpa Biaya -->
                                <div class="bg-white/95 text-gray-800 p-6 rounded-2xl shadow-lg border border-gray-100 relative z-10 space-y-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 font-bold text-base">
                                            <i class="fa-solid fa-handshake-angle"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-extrabold text-primary text-sm">Prosedur & Konfirmasi Daftar Ulang</h6>
                                            <p class="text-[10px] text-gray-500 font-medium">Langkah wajib berikutnya untuk meresmikan status santri</p>
                                        </div>
                                    </div>

                                    <p class="text-xs text-gray-650 leading-relaxed">
                                        Untuk menyelesaikan administrasi dan proses <strong>Daftar Ulang</strong>, Anda tidak perlu khawatir karena rincian biaya pendidikan secara lengkap telah tercantum pada halaman utama informasi pendaftaran. Silakan langsung menghubungi Panitia PSB melalui tombol WhatsApp di bawah ini untuk konfirmasi dan petunjuk registrasi ulang.
                                    </p>

                                    <?php
                                    $wa_message = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\nAlhamdulillah, saya ingin mengonfirmasi kelulusan dan proses daftar ulang untuk putra/putri kami:\n- Nama: " . $pendaftar['nama_santri'] . "\n- No. Registrasi: " . $pendaftar['no_registrasi'] . "\n- Jenjang: " . $pendaftar['jalur_pendaftaran'] . "\n\nMohon petunjuk dan arahan untuk langkah administrasi daftar ulang selanjutnya. Terima kasih.";
                                    $wa_link = "https://wa.me/6281170789988?text=" . urlencode($wa_message);
                                    ?>
                                    <div class="flex flex-col sm:flex-row gap-3 w-full">
                                        <?php 
                                        $santri_phone = !empty($pendaftar['wa_ayah']) ? $pendaftar['wa_ayah'] : (!empty($pendaftar['wa_ibu']) ? $pendaftar['wa_ibu'] : (!empty($pendaftar['wa_wali']) ? $pendaftar['wa_wali'] : $phone));
                                        ?>
                                        <a href="cetak_kelulusan.php?no_reg=<?= htmlspecialchars($pendaftar['no_registrasi']) ?>&phone=<?= htmlspecialchars($santri_phone) ?>&download=1" target="_blank" class="inline-flex items-center justify-center space-x-2 bg-primary hover:bg-secondary text-white font-extrabold px-6 py-4 rounded-xl shadow-md hover:shadow-lg transition-all duration-200 text-xs w-full sm:w-1/2 group">
                                            <i class="fa-solid fa-download text-lg group-hover:-translate-y-1 transition-transform"></i>
                                            <span>Unduh Surat Kelulusan</span>
                                        </a>
                                        <a href="<?= $wa_link ?>" target="_blank" class="inline-flex items-center justify-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold px-6 py-4 rounded-xl shadow-md hover:shadow-lg transition-all duration-200 text-xs w-full sm:w-1/2 group">
                                            <i class="fa-brands fa-whatsapp text-lg group-hover:scale-110 transition-transform"></i>
                                            <span>Konfirmasi via WhatsApp</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Pengumuman TIDAK LULUS -->
                            <div class="bg-gradient-to-br from-red-900 via-red-800 to-red-950 text-white p-6 md:p-8 rounded-3xl shadow-xl relative overflow-hidden border border-red-500/30 space-y-5">
                                <div class="flex items-center space-x-3.5 pb-4 border-b border-white/15">
                                    <div class="w-12 h-12 rounded-2xl bg-white/10 text-red-300 flex items-center justify-center text-2xl flex-shrink-0">
                                        <i class="fa-solid fa-heart-crack"></i>
                                    </div>
                                    <div>
                                        <span class="inline-block bg-red-500/30 text-red-200 border border-red-400/30 text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-widest mb-1">Surat Keputusan Hasil Seleksi</span>
                                        <h5 class="font-extrabold text-white text-lg tracking-tight font-sans">Mohon Maaf, Calon Santri Belum Lulus</h5>
                                    </div>
                                </div>
                                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 space-y-3">
                                    <p class="text-xs md:text-sm text-red-100 leading-relaxed font-normal">
                                        Setelah melakukan proses penilaian ujian seleksi (tertulis, lisan, tilawah/hafalan Al-Qur'an, dan wawancara) secara objektif dan menyeluruh, panitia menyampaikan permohonan maaf bahwa calon santri atas nama <strong><?= $pendaftar['nama_santri'] ?></strong> dinyatakan <strong>BELUM MEMENUHI KRITERIA KELULUSAN</strong> Pondok Pesantren Babul Jannah pada Tahun Ajaran <?= $pendaftar['tahun_ajaran'] ?>.
                                    </p>
                                    <p class="text-xs text-red-200/90 leading-relaxed italic font-serif pt-2 border-t border-white/10">
                                        "Jangan berkecil hati dan tetap semangat. Tetaplah tekun belajar, memperbaiki diri, dan mencintai Al-Qur'an. Semoga Allah memberikan jalan terbaik dan kesuksesan di tempat pendidikan lainnya."
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif (($pendaftar['status_seleksi'] === 'Lulus' || $pendaftar['status_seleksi'] === 'Tidak Lulus') && $belum_waktu_pengumuman): ?>
                        <!-- TAHAP FINAL: PENGUMUMAN TERKUNCI (BELUM TANGGAL RILIS) -->
                        <div class="bg-gradient-to-br from-blue-900 via-indigo-950 to-slate-950 text-white p-6 md:p-8 rounded-3xl shadow-2xl relative overflow-hidden border border-blue-500/30 space-y-6">
                            <!-- Decorative Background Glow -->
                            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
                            <div class="absolute -left-10 -top-10 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                            
                            <!-- Header Pengumuman Terkunci -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-white/15 relative z-10">
                                <div class="flex items-center space-x-3.5">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-amber-500/20 flex-shrink-0">
                                        <i class="fa-solid fa-lock font-bold"></i>
                                    </div>
                                    <div>
                                        <span class="inline-block bg-amber-500/30 text-amber-200 border border-amber-400/30 text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-widest mb-1">Evaluasi Selesai</span>
                                        <h5 class="font-extrabold text-white text-lg md:text-xl tracking-tight font-sans">Pengumuman Hasil Seleksi Masih Terkunci</h5>
                                    </div>
                                </div>
                                <span class="bg-amber-500/20 text-amber-300 text-xs font-extrabold px-4 py-2 rounded-xl shadow-md uppercase tracking-wider text-center self-start sm:self-center border border-amber-400/30 flex items-center">
                                    <i class="fa-solid fa-clock-rotate-left mr-1.5 animate-spin"></i> MENUNGGU JADWAL RILIS
                                </span>
                            </div>

                            <!-- Isi Penjelasan Terkunci -->
                            <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 relative z-10 space-y-4 text-xs md:text-sm text-blue-100 leading-relaxed font-normal">
                                <p>
                                    Alhamdulillah, proses penilaian ujian tertulis, tilawah Al-Qur'an, dan wawancara untuk calon santri atas nama <strong class="text-white"><?= $pendaftar['nama_santri'] ?></strong> telah selesai diverifikasi oleh Dewan Guru dan Panitia PSB.
                                </p>
                                <p>
                                    Demi menjaga keserentakan dan profesionalisme, Surat Keputusan Hasil Kelulusan saat ini <strong>masih dikunci oleh sistem</strong> dan baru akan dapat diakses serta dibuka secara umum sesuai jadwal pengumuman resmi.
                                </p>
                                
                                <div class="bg-blue-950/80 p-5 rounded-2xl border border-blue-400/30 text-center space-y-2 my-2 shadow-inner">
                                    <span class="text-[10px] text-blue-300 uppercase tracking-widest font-extrabold block">Jadwal Pembukaan Akses Pengumuman:</span>
                                    <div class="text-xl md:text-2xl font-black text-amber-400 tracking-tight flex items-center justify-center space-x-2">
                                        <i class="fa-solid fa-calendar-check text-amber-500"></i>
                                        <span><?= format_tanggal($tgl_pengumuman_db) ?></span>
                                    </div>
                                    <p class="text-[10px] text-gray-400">Pukul 00:01 WIB secara serentak di portal ini</p>
                                </div>

                                <p class="text-xs text-blue-200/90 leading-relaxed italic font-serif pt-1 border-t border-white/10">
                                    "Silakan simpan nomor registrasi Anda dan kembali mengakses halaman ini tepat pada tanggal pengumuman tersebut. Terima kasih atas kesabaran dan kepercayan Bapak/Ibu kepada Pondok Pesantren Babul Jannah."
                                </p>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- TAHAP SELEKSI & UJIAN: Tampilkan Verifikasi Pembayaran & Materi Ujian -->
                        <!-- 1. Verifikasi Biaya Administrasi Ujian Seleksi -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-primary border-l-4 border-secondary pl-2 text-sm flex items-center">
                                <i class="fa-solid fa-clipboard-check text-secondary mr-2"></i> 1. Verifikasi Administrasi Ujian Seleksi
                            </h4>
                            
                            <?php if ($pendaftar['status_pembayaran'] === 'Belum Bayar'): ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-[#FBF9F6] p-6 rounded-2xl border border-gray-200">
                                    <div class="md:col-span-2 space-y-3">
                                        <h5 class="font-bold text-primary text-xs">Rincian Pembayaran Administrasi:</h5>
                                        <div class="text-xs text-gray-600 space-y-1">
                                            <div class="flex justify-between font-bold text-primary pt-1 text-sm">
                                                <span>Total Biaya Administrasi & Ujian</span>
                                                <span><?= format_rupiah(get_biaya_tes($pendaftar['jalur_pendaftaran'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                        <div class="bg-white p-4 rounded-xl border border-stone-200 shadow-2xs space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[11px] font-bold text-stone-500 uppercase tracking-wider flex items-center">
                                                    <i class="fa-solid fa-building-columns text-[#5C3E35] mr-1.5"></i> Transfer ke Rekening Resmi:
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    Bank BSI
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between bg-stone-50 p-2.5 rounded-xl border border-stone-200/80">
                                                <div>
                                                    <span class="block text-[11px] font-bold text-[#5C3E35]">Bank Syariah Indonesia (BSI)</span>
                                                    <span class="text-base sm:text-lg font-mono font-black text-[#5C3E35] tracking-wider">7300750308</span>
                                                </div>
                                                <button type="button" onclick="copyNorek('7300750308', this)" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-lg bg-white hover:bg-stone-100 text-[#5C3E35] border border-stone-200 text-xs font-bold transition shadow-2xs group" title="Salin Nomor Rekening">
                                                    <i class="fa-regular fa-copy group-hover:scale-110 transition-transform"></i>
                                                    <span class="copy-label">Salin</span>
                                                </button>
                                            </div>
                                            <p class="text-[11px] text-stone-600 font-medium">
                                                Atas Nama: <strong class="text-[#5C3E35] font-black">Pesantren Babul Jannah</strong>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="space-y-3">
                                        <h5 class="font-bold text-primary text-xs">Unggah Bukti Transfer:</h5>
                                        <form action="status.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="upload_bukti">
                                            <input type="hidden" name="no_registrasi" value="<?= htmlspecialchars($pendaftar['no_registrasi']) ?>">
                                            <input type="hidden" name="phone" value="<?= htmlspecialchars(!empty($pendaftar['wa_ayah']) ? $pendaftar['wa_ayah'] : (!empty($pendaftar['wa_ibu']) ? $pendaftar['wa_ibu'] : $phone)) ?>">
                                            <div id="dropzone-area" class="border-2 border-dashed border-gray-300 bg-white rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50 transition relative overflow-hidden group">
                                                <input type="file" name="bukti_bayar" required accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="previewSelectedFile(this)">
                                                <div id="dropzone-content" class="transition-all duration-300 py-2">
                                                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-secondary mb-1.5 group-hover:scale-110 transition-transform"></i>
                                                    <p class="text-[11px] text-gray-700 font-bold">Klik atau Pilih Gambar Struk Transfer</p>
                                                    <p class="text-[9px] text-gray-400 mt-0.5 font-medium">Format: JPG, JPEG, PNG (Maksimal 3MB)</p>
                                                </div>
                                                <div id="dropzone-preview" class="hidden flex flex-col items-center justify-center py-1">
                                                    <div class="w-16 h-16 rounded-lg overflow-hidden border-2 border-emerald-500 mb-2 shadow relative group bg-gray-100 flex items-center justify-center">
                                                        <img id="preview-img" src="" class="w-full h-full object-cover">
                                                    </div>
                                                    <div class="flex items-center space-x-1.5 bg-emerald-50 text-emerald-800 px-3 py-1 rounded-full border border-emerald-200">
                                                        <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                                        <span id="preview-filename" class="text-[10px] font-bold truncate max-w-[160px]"></span>
                                                    </div>
                                                    <p class="text-[9px] text-emerald-600 font-bold mt-1.5 animate-pulse">File siap dikirim! Klik tombol di bawah.</p>
                                                </div>
                                            </div>
                                            <button type="submit" class="w-full bg-secondary hover:bg-primary text-white font-bold py-2.5 rounded-xl text-xs shadow transition">Kirim Bukti Bayar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php elseif ($pendaftar['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                                <div class="bg-amber-50 border border-amber-200 text-amber-900 p-5 rounded-2xl space-y-3">
                                    <h5 class="font-bold text-amber-800 text-xs flex items-center"><i class="fa-solid fa-spinner animate-spin mr-2"></i> Bukti Bayar Menunggu Validasi</h5>
                                    <p class="text-[11px] text-amber-950 leading-relaxed font-normal">
                                        Bukti pembayaran <?= format_rupiah(get_biaya_tes($pendaftar['jalur_pendaftaran'] ?? '')) ?> yang Anda unggah sedang diperiksa oleh bendahara panitia PSB. Status akan diperbarui dalam 1x24 jam. Silakan lakukan pengecekan halaman ini secara berkala.
                                    </p>
                                    <?php if (!empty($pendaftar['bukti_bayar_tes'])): ?>
                                        <div class="pt-3 border-t border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/80 p-3.5 rounded-xl border border-amber-200 shadow-sm">
                                            <div class="flex items-center space-x-3 overflow-hidden">
                                                <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0 text-amber-700 overflow-hidden border border-amber-300">
                                                    <img src="<?= get_storage_url('', $pendaftar['bukti_bayar_tes']) ?>" class="w-full h-full object-cover" alt="Struk Transfer" onerror="this.style.display='none'">
                                                </div>
                                                <div class="truncate">
                                                    <span class="inline-block bg-amber-200/80 text-amber-900 text-[9px] font-extrabold px-2 py-0.5 rounded uppercase mb-0.5">File Terunggah</span>
                                                    <p class="text-xs font-bold text-gray-800 truncate max-w-[180px] sm:max-w-[220px]"><?= $pendaftar['bukti_bayar_tes'] ?></p>
                                                </div>
                                            </div>
                                            <a href="<?= get_storage_url('', $pendaftar['bukti_bayar_tes']) ?>" target="_blank" class="bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 rounded-xl text-[11px] font-bold flex items-center justify-center space-x-1.5 shadow transition flex-shrink-0">
                                                <i class="fa-solid fa-eye"></i>
                                                <span>Lihat Struk Saya</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Lunas -->
                                <div class="bg-emerald-50 border border-emerald-250 text-emerald-950 p-5 rounded-2xl space-y-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-circle-check text-lg"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-extrabold text-emerald-800 text-xs">Pembayaran Terverifikasi (Lunas Ujian)</h5>
                                            <p class="text-[10px] text-emerald-750/80 font-normal mt-0.5">Terima kasih, administrasi pendaftaran awal Anda telah selesai divalidasi oleh panitia.</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-3 pt-2 border-t border-emerald-200/50">
                                        <button onclick="unduhDokumenRahasia('kartu', '<?= $pendaftar['no_registrasi'] ?>', this); return false;" type="button" class="bg-primary hover:bg-secondary text-white font-bold px-4 py-2 rounded-xl text-[10px] shadow transition flex items-center space-x-1.5">
                                            <i class="fa-solid fa-file-pdf text-xs text-emerald-300"></i>
                                            <span>Unduh Kartu Ujian (PDF)</span>
                                        </button>
                                        <button onclick="unduhDokumenRahasia('formulir', '<?= $pendaftar['no_registrasi'] ?>', this); return false;" type="button" class="border border-primary text-primary hover:bg-white font-bold px-4 py-2 rounded-xl text-[10px] transition flex items-center space-x-1.5 bg-white/50">
                                            <i class="fa-solid fa-file-pdf text-xs text-red-600"></i>
                                            <span>Unduh Formulir Resmi (PDF)</span>
                                        </button>
                                    </div>
                                    <?php if (!empty($pendaftar['bukti_bayar_tes'])): ?>
                                        <div class="pt-3 border-t border-emerald-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/80 p-3.5 rounded-xl border border-emerald-200 shadow-sm">
                                            <div class="flex items-center space-x-3 overflow-hidden">
                                                <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 text-emerald-700 overflow-hidden border border-emerald-300">
                                                    <img src="<?= get_storage_url('', $pendaftar['bukti_bayar_tes']) ?>" class="w-full h-full object-cover" alt="Struk Transfer" onerror="this.style.display='none'">
                                                </div>
                                                <div class="truncate">
                                                    <span class="inline-block bg-emerald-200/80 text-emerald-900 text-[9px] font-extrabold px-2 py-0.5 rounded uppercase mb-0.5">Bukti Terverifikasi</span>
                                                    <p class="text-xs font-bold text-gray-800 truncate max-w-[180px] sm:max-w-[220px]"><?= $pendaftar['bukti_bayar_tes'] ?></p>
                                                </div>
                                            </div>
                                            <a href="<?= get_storage_url('', $pendaftar['bukti_bayar_tes']) ?>" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 rounded-xl text-[11px] font-bold flex items-center justify-center space-x-1.5 shadow transition flex-shrink-0">
                                                <i class="fa-solid fa-eye"></i>
                                                <span>Lihat Struk Saya</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Pengumuman Hasil Ujian Seleksi -->
                        <?php if ($pendaftar['status_pembayaran'] === 'Lunas'): ?>
                            <div class="space-y-6 pt-6 border-t border-gray-100">
                                <h4 class="font-bold text-primary border-l-4 border-secondary pl-2 text-sm flex items-center">
                                    <i class="fa-solid fa-file-contract text-secondary mr-2"></i> 2. Pengumuman Hasil Ujian Seleksi
                                </h4>
                                
                                <div class="space-y-6">
                                    <!-- Notifikasi Status -->
                                    <div class="bg-blue-50 border border-blue-200 p-5 rounded-2xl space-y-3 text-blue-900 shadow-sm">
                                        <h5 class="font-bold text-blue-800 text-xs flex items-center">
                                            <i class="fa-solid fa-hourglass-half mr-2 text-blue-600 animate-spin"></i> 
                                            Status: Calon Peserta Seleksi Aktif
                                        </h5>
                                        <p class="text-[11px] leading-relaxed font-normal">
                                            Pembayaran administrasi pendaftaran awal Anda telah terverifikasi. Saat ini Anda berada dalam antrean pelaksanaan ujian seleksi masuk. Silakan ikuti materi persiapan di bawah ini.
                                        </p>
                                        <?php if (!empty($pendaftar['tgl_ujian']) || !empty($pendaftar['tgl_pengumuman'])): ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-blue-200/50">
                                            <?php if (!empty($pendaftar['tgl_ujian'])): ?>
                                            <div class="flex items-center space-x-2.5 bg-white/60 p-3 rounded-xl border border-blue-100">
                                                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                                    <i class="fa-solid fa-calendar-day text-sm"></i>
                                                </div>
                                                <div>
                                                    <span class="block text-[9px] font-bold text-blue-500 uppercase tracking-wider">Tanggal Ujian</span>
                                                    <span class="block text-xs font-extrabold text-blue-900"><?= format_tanggal($pendaftar['tgl_ujian']) ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($pendaftar['tgl_pengumuman'])): ?>
                                            <div class="flex items-center space-x-2.5 bg-white/60 p-3 rounded-xl border border-blue-100">
                                                <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                                                    <i class="fa-solid fa-bullhorn text-sm"></i>
                                                </div>
                                                <div>
                                                    <span class="block text-[9px] font-bold text-amber-500 uppercase tracking-wider">Pengumuman Hasil</span>
                                                    <span class="block text-xs font-extrabold text-amber-900"><?= format_tanggal($pendaftar['tgl_pengumuman']) ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Informasi & Materi Ujian Card -->
                                    <div class="bg-white rounded-2xl border border-gray-150 p-6 shadow-sm space-y-5">
                                        <h5 class="font-extrabold text-primary text-xs uppercase tracking-wider flex items-center">
                                            <i class="fa-solid fa-book-open text-secondary mr-2"></i> Materi & Panduan Ujian Masuk
                                        </h5>
                                        <div class="w-12 h-0.5 bg-secondary rounded"></div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-[11px] leading-relaxed text-gray-600">
                                            <div class="bg-accent/40 p-4 rounded-xl border border-gray-100 space-y-3">
                                                <h6 class="font-bold text-primary flex items-center">
                                                    <i class="fa-solid fa-clipboard-list text-secondary mr-1.5"></i> Bidang yang Diuji:
                                                </h6>
                                                <ul class="space-y-2 pl-1">
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">•</span>
                                                        <div><strong>Membaca Al-Qur'an:</strong> Ujian kelancaran tilawah, hukum tajwid dasar, makhraj, dan hafalan surat pendek (Juz 30).</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">•</span>
                                                        <div><strong>Wawancara (Interview):</strong> Sesi tanya jawab lisan dengan calon santri (minat & mental) serta orang tua/wali (komitmen mondok).</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">•</span>
                                                        <div><strong>Tes Tertulis Agama:</strong> Pengetahuan Aqidah Akhlak, Fiqih ibadah dasar, Tarikh Islam, serta Bahasa Arab dasar.</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">•</span>
                                                        <div><strong>Tes Tertulis Umum:</strong> Logika Matematika dasar, Ilmu Pengetahuan Alam, serta Bahasa Indonesia.</div>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="bg-accent/40 p-4 rounded-xl border border-gray-100 space-y-3">
                                                <h6 class="font-bold text-primary flex items-center">
                                                    <i class="fa-solid fa-circle-exclamation text-secondary mr-1.5"></i> Persyaratan & Tata Tertib:
                                                </h6>
                                                <ul class="space-y-2 pl-1">
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">1.</span>
                                                        <div>Wajib mencetak dan membawa **Kartu Peserta Ujian** (klik tombol di atas) saat hari pelaksanaan.</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">2.</span>
                                                        <div>Peserta mengenakan busana muslim (baju koko putih/kopiah untuk putra, gamis/jilbab syar'i untuk putri) yang sopan.</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">3.</span>
                                                        <div>Hadir di kompleks Pondok Pesantren Babul Jannah paling lambat 30 menit sebelum jadwal ujian Anda dimulai.</div>
                                                    </li>
                                                    <li class="flex items-start">
                                                        <span class="text-secondary mr-2">4.</span>
                                                        <div><?php if (!empty($pendaftar['tgl_ujian'])): ?>Ujian dilaksanakan pada <strong><?= format_tanggal($pendaftar['tgl_ujian']) ?></strong>. Jadwal waktu dan ruang ujian spesifik akan dikabarkan via WhatsApp Ayah.<?php else: ?>Jadwal tanggal, jam, dan ruang ujian spesifik akan dikabarkan langsung oleh panitia PSB melalui WhatsApp Ayah.<?php endif; ?></div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Google Maps Location -->
                                        <div class="bg-accent/40 p-4 rounded-xl border border-gray-100 space-y-3 pt-3">
                                            <h6 class="font-bold text-primary flex items-center text-xs">
                                                <i class="fa-solid fa-map-location-dot text-secondary mr-1.5"></i> Peta Lokasi Pondok Pesantren Babul Jannah:
                                            </h6>
                                            <p class="text-[10px] text-gray-500 font-medium"><strong>Putra:</strong> Jl. Sidorejo No.02 | <strong>Putri:</strong> Jl. Sidorejo No.22, Kel. Ratu Sima, Dumai Selatan, Kota Dumai, Riau.</p>
                                            <div class="w-full h-48 rounded-xl overflow-hidden shadow-inner border border-gray-200/60 mt-2 no-print">
                                                <iframe title="Peta Lokasi Pondok Pesantren Babul Jannah" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1145.1093402620484!2d101.42512192766331!3d1.6659447718410978!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d3a923b39cb2a7%3A0x32ba105ad0aed175!2sPONDOK%20PESANTREN%20BABUL%20JANNAH!5e0!3m2!1sid!2sid!4v1784998158784!5m2!1sid!2sid" class="w-full h-full border-0" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; // End of $pendaftar ?>
        </div> <?php // End of max-w-4xl container ?>
        <?php endif; // End of !$searched else block ?>
    </div>
</section>

<script>
function unduhDokumenRahasia(jenis, noReg, btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Sedang Mengunduh...</span>';
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    btn.disabled = true;

    // Gunakan iframe tersembunyi di background agar halaman tidak berpindah dan tidak ada preview
    let iframe = document.getElementById('secret-pdf-frame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'secret-pdf-frame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '800px';
        iframe.style.height = '1100px';
        iframe.style.border = 'none';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        iframe.style.zIndex = '-9999';
        document.body.appendChild(iframe);
    }

    const phoneVal = '<?= !empty($pendaftar['wa_ayah']) ? htmlspecialchars($pendaftar['wa_ayah']) : (!empty($pendaftar['wa_ibu']) ? htmlspecialchars($pendaftar['wa_ibu']) : htmlspecialchars($phone)) ?>';
    const targetUrl = (jenis === 'kartu' ? 'cetak_kartu.php' : 'cetak_formulir.php') + '?no_reg=' + encodeURIComponent(noReg) + '&phone=' + encodeURIComponent(phoneVal) + '&download=1';
    
    // Dengarkan pesan selesai dari iframe
    const messageHandler = function(e) {
        if (e.data && e.data.status === 'pdf_done' && e.data.no_reg === noReg) {
            window.removeEventListener('message', messageHandler);
            btn.innerHTML = '<i class="fa-solid fa-check"></i><span>Berhasil Diunduh!</span>';
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            btn.disabled = false;
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 3000);
        }
    };
    window.addEventListener('message', messageHandler);

    iframe.src = targetUrl;
    
    // Fallback timer jika browser memblokir message
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
        btn.disabled = false;
    }, 5000);
}

function previewSelectedFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgEl = document.getElementById('preview-img');
            const nameEl = document.getElementById('preview-filename');
            const contentEl = document.getElementById('dropzone-content');
            const previewEl = document.getElementById('dropzone-preview');
            const areaEl = document.getElementById('dropzone-area');
            
            if (imgEl) imgEl.src = e.target.result;
            if (nameEl) nameEl.textContent = file.name;
            if (contentEl) contentEl.classList.add('hidden');
            if (previewEl) previewEl.classList.remove('hidden');
            if (areaEl) {
                areaEl.classList.remove('border-gray-300', 'bg-white');
                areaEl.classList.add('border-emerald-500', 'bg-emerald-50/20');
            }
        }
        reader.readAsDataURL(file);
    }
}

function copyNorek(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text);
    } else {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (err) {}
        textArea.remove();
    }
    const label = btn.querySelector('.copy-label');
    if (label) {
        const originalText = label.textContent;
        label.textContent = 'Tersalin!';
        btn.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-300');
        setTimeout(() => {
            label.textContent = originalText;
            btn.classList.remove('bg-emerald-50', 'text-emerald-700', 'border-emerald-300');
        }, 2000);
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
