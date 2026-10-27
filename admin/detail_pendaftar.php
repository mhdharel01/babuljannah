<?php
// admin/detail_pendaftar.php
require_once '../config/db.php';
require_once '../config/mail.php';

// Proteksi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: pendaftar.php");
    exit();
}

$error = '';
$success = '';

// Helper untuk format URL WhatsApp resmi
function get_wa_url_custom($phone, $nama, $no_reg, $status_bayar, $status_seleksi) {
    if (empty($phone)) return '#';
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    } elseif (strpos($clean, '62') !== 0) {
        $clean = '62' . $clean;
    }
    $pesan = "Assalamu'alaikum Wr. Wb. Bapak/Ibu wali santri *{$nama}* (No. Registrasi: *{$no_reg}*).\n\nKami dari Panitia PSB Pondok Pesantren Babul Jannah ingin menginformasikan perihal pendaftaran ananda:\n- Status Pembayaran: *{$status_bayar}*\n- Hasil Seleksi: *{$status_seleksi}*\n\nJika ada pertanyaan lebih lanjut, silakan balas pesan ini. Jazakumullahu khairan.";
    return "https://wa.me/{$clean}?text=" . rawurlencode($pesan);
}

// 1. Tangani Aksi POST dari Halaman Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1.1 Verifikasi Cepat Pembayaran (Lunas / Tolak)
    if ($action === 'verify_payment' && in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])) {
        $status_bayar = clean_cell_val($_POST['status_pembayaran'] ?? '');
        $metode = clean_cell_val($_POST['metode_pembayaran'] ?? 'Transfer');

        if (in_array($status_bayar, ['Lunas', 'Belum Bayar'])) {
            try {
                $stmt_curr = $db->prepare("SELECT * FROM pendaftaran WHERE id = :id");
                $stmt_curr->execute(['id' => $id]);
                $curr_data = $stmt_curr->fetch();

                if ($curr_data) {
                    $tgl_lunas = ($status_bayar === 'Lunas') ? date('Y-m-d H:i:s') : null;
                    $stmt_up = $db->prepare("UPDATE pendaftaran SET status_pembayaran = :status, metode_pembayaran = :metode, tgl_lunas = :tgl_lunas WHERE id = :id");
                    $stmt_up->execute([
                        'status' => $status_bayar,
                        'metode' => $metode,
                        'tgl_lunas' => $tgl_lunas,
                        'id' => $id
                    ]);

                    if ($status_bayar === 'Lunas') {
                        $success = "Pembayaran berhasil diverifikasi LUNAS!";
                        if ($curr_data['status_pembayaran'] !== 'Lunas') {
                            $curr_data['status_pembayaran'] = 'Lunas';
                            send_email_verifikasi_lunas($curr_data);
                        }
                    } else {
                        $success = "Pembayaran ditolak. Status dikembalikan ke 'Belum Bayar'.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Gagal memproses pembayaran: " . $e->getMessage();
            }
        }
    }

    // 1.2 Update Status Seleksi & Kehadiran & Pembayaran
    if ($action === 'update_all_status' && in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])) {
        $status_bayar = clean_cell_val($_POST['status_pembayaran'] ?? '');
        $metode = clean_cell_val($_POST['metode_pembayaran'] ?? 'Transfer');
        $status_seleksi = clean_cell_val($_POST['status_seleksi'] ?? '');
        $status_kehadiran = clean_cell_val($_POST['status_kehadiran'] ?? 'Belum Hadir');

        try {
            $stmt_old = $db->prepare("SELECT * FROM pendaftaran WHERE id = :id");
            $stmt_old->execute(['id' => $id]);
            $old_data = $stmt_old->fetch();

            if ($old_data) {
                $tgl_lunas = ($status_bayar === 'Lunas') ? ($old_data['tgl_lunas'] ?? date('Y-m-d H:i:s')) : null;
                $waktu_kehadiran = ($status_kehadiran === 'Hadir') ? ($old_data['waktu_kehadiran'] ?? date('Y-m-d H:i:s')) : null;

                $stmt_up = $db->prepare("UPDATE pendaftaran SET 
                    status_pembayaran = :bayar,
                    metode_pembayaran = :metode,
                    tgl_lunas = :tgl_lunas,
                    status_seleksi = :seleksi,
                    status_kehadiran = :kehadiran,
                    waktu_kehadiran = :waktu_kehadiran
                    WHERE id = :id");
                
                $stmt_up->execute([
                    'bayar' => $status_bayar,
                    'metode' => $metode,
                    'tgl_lunas' => $tgl_lunas,
                    'seleksi' => $status_seleksi,
                    'kehadiran' => $status_kehadiran,
                    'waktu_kehadiran' => $waktu_kehadiran,
                    'id' => $id
                ]);

                $success = "Status pendaftar berhasil diperbarui.";

                if ($status_bayar === 'Lunas' && $old_data['status_pembayaran'] !== 'Lunas') {
                    $old_data['status_pembayaran'] = 'Lunas';
                    send_email_verifikasi_lunas($old_data);
                }
            }
        } catch (PDOException $e) {
            $error = "Gagal memperbarui status: " . $e->getMessage();
        }
    }
}

// 2. Ambil data santri lengkap
$stmt = $db->prepare("SELECT p.*, COALESCE(g.nama_gelombang, 'Gelombang Umum') as nama_gelombang, g.tahun_ajaran, g.tgl_ujian 
    FROM pendaftaran p 
    LEFT JOIN gelombang g ON p.gelombang_id = g.id 
    WHERE p.id = :id");
$stmt->execute(['id' => $id]);
$santri = $stmt->fetch();

if (!$santri) {
    header("Location: pendaftar.php?error=not_found");
    exit();
}

// Hitung Biaya Pendaftaran Sesuai Jenjang
$biaya_pendaftaran = 350000;
if (stripos($santri['jalur_pendaftaran'] ?? '', 'SMP') !== false) {
    $biaya_pendaftaran = (int)($db->query("SELECT nilai FROM pengaturan_sistem WHERE kunci = 'biaya_tes_smp'")->fetchColumn() ?: 300000);
} elseif (stripos($santri['jalur_pendaftaran'] ?? '', 'SMA') !== false) {
    $biaya_pendaftaran = (int)($db->query("SELECT nilai FROM pengaturan_sistem WHERE kunci = 'biaya_tes_sma'")->fetchColumn() ?: 350000);
} else {
    $biaya_pendaftaran = (int)($db->query("SELECT nilai FROM pengaturan_sistem WHERE kunci = 'biaya_tes_pendaftaran'")->fetchColumn() ?: 350000);
}

$is_putra = (strtoupper($santri['jk'] ?? '') === 'L' || stripos($santri['jk'] ?? '', 'LAKI') !== false);

// Hitung usia
$usia_teks = '-';
if (!empty($santri['tgl_lahir']) && $santri['tgl_lahir'] !== '0000-00-00') {
    try {
        $birth = new DateTime($santri['tgl_lahir']);
        $today = new DateTime();
        $diff = $today->diff($birth);
        $usia_teks = $diff->y . ' Tahun ' . $diff->m . ' Bulan';
    } catch (Exception $e) {
        $usia_teks = '-';
    }
}

$page_title = 'Detail Santri: ' . htmlspecialchars($santri['nama_santri']) . ' - PSB Babul Jannah';
include 'includes/head.php';
?>
<body class="bg-[#F8F9FA] flex h-screen overflow-hidden font-sans text-gray-800">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-screen overflow-hidden">

        <?php
        $header_custom_title = '
            <a href="pendaftar.php" class="p-2.5 bg-gray-100 hover:bg-gray-200 rounded-xl transition text-[#5C3E35] mr-3 inline-flex items-center justify-center shadow-xs" title="Kembali ke Daftar Pendaftar">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div>
                <div class="flex items-center space-x-2">
                    <h1 class="text-xl font-extrabold text-[#2F2522]">Detail Profil Calon Santri</h1>
                    <span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded text-[11px] font-mono font-bold">' . htmlspecialchars($santri['no_registrasi']) . '</span>
                </div>
                <p class="text-xs text-gray-500 font-medium mt-0.5 hidden sm:block">Informasi lengkap biodata, orang tua, pembayaran, dan hasil seleksi</p>
            </div>';

        $header_custom_right = '
            <div class="flex items-center space-x-2">
                ' . (in_array($admin_role, ['superadmin', 'panitia']) ? '
                <a href="edit_pendaftar.php?id=' . $santri['id'] . '" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                    <i class="fa-solid fa-user-pen text-xs"></i>
                    <span>Edit Biodata</span>
                </a>' : '') . '
                
                <a href="pendaftar.php" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs">
                    <i class="fa-solid fa-table-list text-xs text-gray-500"></i>
                    <span>Daftar Pendaftar</span>
                </a>
            </div>';

        include 'includes/header.php';
        ?>

        <!-- Scrollable Body -->
        <main class="flex-grow p-4 sm:p-6 lg:p-8 overflow-y-auto space-y-6">

            <!-- Alert Notifikasi -->
            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl flex items-center justify-between shadow-xs">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                        <p class="text-xs font-bold text-emerald-900"><?= htmlspecialchars($success) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 text-xs font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl flex items-center justify-between shadow-xs">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-circle-exclamation text-red-600 text-lg"></i>
                        <p class="text-xs font-bold text-red-900"><?= htmlspecialchars($error) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 text-xs font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Hero Banner: Profil Singkat & Status Ringkas -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start sm:items-center space-x-4">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl <?= $is_putra ? 'bg-blue-50 text-blue-700 border-2 border-blue-200' : 'bg-pink-50 text-pink-700 border-2 border-pink-200' ?> flex items-center justify-center font-black text-2xl sm:text-3xl flex-shrink-0 shadow-sm">
                            <?= htmlspecialchars(strtoupper(substr($santri['nama_santri'], 0, 1))) ?>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg sm:text-2xl font-black text-[#2F2522] uppercase tracking-tight"><?= htmlspecialchars($santri['nama_santri']) ?></h2>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $is_putra ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' ?>">
                                    <?= $is_putra ? 'Santri Putra' : 'Santri Putri' ?>
                                </span>
                                <?php if (($santri['kategori_santri'] ?? '') === 'Pindahan'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-100 text-indigo-800 border border-indigo-200">Pindahan</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 font-medium mt-1.5">
                                <span class="flex items-center text-gray-700 font-semibold">
                                    <i class="fa-solid fa-graduation-cap text-[#5C3E35] mr-1.5"></i> <?= htmlspecialchars($santri['jalur_pendaftaran']) ?>
                                </span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-calendar-check text-[#5C3E35] mr-1"></i> <?= htmlspecialchars($santri['nama_gelombang']) ?></span>
                                <span>&bull;</span>
                                <span><i class="fa-regular fa-clock text-gray-400 mr-1"></i> Daftar: <?= date('d M Y H:i', strtotime($santri['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Badges Grid -->
                    <div class="flex flex-wrap items-center gap-3 bg-gray-50 p-3.5 rounded-xl border border-gray-100">
                        <!-- Status Pembayaran -->
                        <div class="text-center px-3 py-1.5 bg-white rounded-lg border border-gray-200 shadow-2xs">
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">Pembayaran</span>
                            <?php if ($santri['status_pembayaran'] === 'Lunas'): ?>
                                <span class="inline-flex items-center text-xs font-black text-emerald-700 mt-0.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5"></span> Lunas
                                </span>
                            <?php elseif ($santri['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                                <span class="inline-flex items-center text-xs font-black text-amber-600 mt-0.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 mr-1.5 animate-ping"></span> Menunggu
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center text-xs font-black text-gray-600 mt-0.5">
                                    <span class="w-2 h-2 rounded-full bg-gray-400 mr-1.5"></span> Belum Bayar
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Status Seleksi -->
                        <div class="text-center px-3 py-1.5 bg-white rounded-lg border border-gray-200 shadow-2xs">
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">Hasil Seleksi</span>
                            <?php if ($santri['status_seleksi'] === 'Lulus'): ?>
                                <span class="inline-flex items-center text-xs font-black text-[#5C3E35] mt-0.5">
                                    <i class="fa-solid fa-award mr-1 text-[#A17A6F]"></i> Lulus
                                </span>
                            <?php elseif ($santri['status_seleksi'] === 'Tidak Lulus'): ?>
                                <span class="inline-flex items-center text-xs font-black text-red-600 mt-0.5">
                                    <i class="fa-solid fa-circle-xmark mr-1"></i> Tidak Lulus
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center text-xs font-black text-gray-500 mt-0.5">
                                    <i class="fa-regular fa-clock mr-1"></i> Belum Seleksi
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Status Presensi Ujian -->
                        <div class="text-center px-3 py-1.5 bg-white rounded-lg border border-gray-200 shadow-2xs">
                            <span class="block text-[10px] text-gray-400 font-bold uppercase">Presensi Ujian</span>
                            <?php if ($santri['status_kehadiran'] === 'Hadir'): ?>
                                <span class="inline-flex items-center text-xs font-black text-emerald-700 mt-0.5">
                                    <i class="fa-solid fa-circle-check mr-1 text-emerald-500"></i> Hadir
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center text-xs font-black text-gray-500 mt-0.5">
                                    <i class="fa-regular fa-clock mr-1"></i> Belum Hadir
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid 2 Kolom (Kiri: Informasi Lengkap Santri, Kanan: Pembayaran & Aksi Cepat) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- KOLOM KIRI (2 Kolom di Desktop): Biodata & Data Detail -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Kartu Bukti Pembayaran & Keuangan -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-receipt mr-2 text-[#5C3E35]"></i> Status Pembayaran &amp; Bukti Transfer
                            </h3>
                            <span class="text-xs font-bold text-gray-500">
                                Biaya Pendaftaran: <span class="text-[#5C3E35] font-black">Rp <?= number_format($biaya_pendaftaran, 0, ',', '.') ?></span>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 space-y-2 text-xs">
                                <div class="flex justify-between py-1 border-b border-gray-200">
                                    <span class="text-gray-500 font-medium">Metode Pembayaran:</span>
                                    <span class="font-bold text-gray-800"><?= htmlspecialchars($santri['metode_pembayaran'] ?: 'Transfer Bank') ?></span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-gray-200">
                                    <span class="text-gray-500 font-medium">Tanggal Upload Bukti:</span>
                                    <span class="font-bold text-gray-800"><?= !empty($santri['tgl_upload_bayar']) ? date('d M Y H:i', strtotime($santri['tgl_upload_bayar'])) : '-' ?></span>
                                </div>
                                <div class="flex justify-between py-1">
                                    <span class="text-gray-500 font-medium">Tanggal Pelunasan:</span>
                                    <span class="font-bold text-emerald-700"><?= !empty($santri['tgl_lunas']) ? date('d M Y H:i', strtotime($santri['tgl_lunas'])) : 'Belum Lunas' ?></span>
                                </div>
                            </div>

                            <!-- Pratinjau Struk / Upload Area -->
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 flex flex-col justify-center items-center text-center">
                                <?php if (!empty($santri['bukti_bayar_tes'])): ?>
                                    <?php $bukti_full_url = get_storage_url('uploads', $santri['bukti_bayar_tes'], '', true); ?>
                                    <div class="relative group cursor-pointer" onclick="openBuktiModal('<?= htmlspecialchars($bukti_full_url, ENT_QUOTES) ?>')">
                                        <img src="<?= htmlspecialchars($bukti_full_url, ENT_QUOTES) ?>" alt="Struk Transfer" class="h-32 w-auto max-w-full object-cover rounded-lg border border-gray-300 shadow-xs group-hover:opacity-90 transition">
                                        <div class="absolute inset-0 bg-black/40 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-white text-xs font-bold space-x-1">
                                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            <span>Perbesar Struk</span>
                                        </div>
                                    </div>
                                    <div class="mt-2.5 flex items-center space-x-2">
                                        <a href="<?= htmlspecialchars($bukti_full_url, ENT_QUOTES) ?>" target="_blank" download class="text-[11px] font-bold text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                            <i class="fa-solid fa-download"></i>
                                            <span>Unduh File Struk</span>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-gray-400 py-4">
                                        <i class="fa-regular fa-image text-3xl mb-2 text-gray-300"></i>
                                        <p class="text-xs font-semibold text-gray-500">Belum ada struk transfer yang diunggah.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Verifikasi Cepat Bukti (Khusus Admin / Panitia / Bendahara) -->
                        <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])): ?>
                            <div class="bg-[#FBF9F6] border border-[#5C3E35]/20 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-xs font-extrabold text-[#2F2522]">Tindakan Cepat Pembayaran</h4>
                                    <p class="text-[11px] text-gray-500">Verifikasi struk transfer atau kembalikan status jika bukti tidak sesuai.</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi pembayaran LUNAS untuk santri ini?')">
                                        <input type="hidden" name="action" value="verify_payment">
                                        <input type="hidden" name="status_pembayaran" value="Lunas">
                                        <input type="hidden" name="metode_pembayaran" value="<?= htmlspecialchars($santri['metode_pembayaran'] ?: 'Transfer') ?>">
                                        <button type="submit" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center space-x-1.5" style="background-color: #15803D !important; color: #FFFFFF !important;">
                                            <i class="fa-solid fa-check-circle"></i>
                                            <span>Verifikasi Lunas</span>
                                        </button>
                                    </form>

                                    <form method="POST" action="" onsubmit="return confirm('Tolak bukti pembayaran dan kembalikan status ke Belum Bayar?')">
                                        <input type="hidden" name="action" value="verify_payment">
                                        <input type="hidden" name="status_pembayaran" value="Belum Bayar">
                                        <input type="hidden" name="metode_pembayaran" value="<?= htmlspecialchars($santri['metode_pembayaran'] ?: 'Transfer') ?>">
                                        <button type="submit" class="px-3.5 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl text-xs font-bold transition shadow-xs flex items-center space-x-1.5">
                                            <i class="fa-solid fa-ban"></i>
                                            <span>Tolak Bukti</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Kartu Biodata Calon Santri -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-id-card mr-2 text-[#5C3E35]"></i> Biodata Calon Santri
                            </h3>
                            <span class="text-xs text-gray-400 font-mono">NIK: <?= htmlspecialchars($santri['nik_santri']) ?></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs">
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Nama Lengkap</span>
                                <span class="font-extrabold text-gray-900 mt-0.5 block"><?= htmlspecialchars($santri['nama_santri']) ?></span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">No. Registrasi</span>
                                <span class="font-extrabold font-mono text-[#5C3E35] mt-0.5 block"><?= htmlspecialchars($santri['no_registrasi']) ?></span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">NISN &amp; No. KK</span>
                                <span class="font-bold text-gray-800 mt-0.5 block">
                                    NISN: <?= htmlspecialchars($santri['nisn'] ?: '-') ?><br>
                                    KK: <?= htmlspecialchars($santri['no_kk'] ?: '-') ?>
                                </span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Tempat, Tanggal Lahir</span>
                                <span class="font-bold text-gray-800 mt-0.5 block">
                                    <?= htmlspecialchars($santri['tempat_lahir'] ?: '-') ?>, 
                                    <?= !empty($santri['tgl_lahir']) ? date('d F Y', strtotime($santri['tgl_lahir'])) : '-' ?>
                                </span>
                                <span class="text-[10px] text-gray-500 font-semibold">(Usia: <?= $usia_teks ?>)</span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Jenis Kelamin</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['jk'] ?: '-') ?></span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Status dalam Keluarga</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['status_anak'] ?: 'Anak Kandung') ?></span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Fisik Santri</span>
                                <span class="font-bold text-gray-800 mt-0.5 block">
                                    TB: <?= htmlspecialchars($santri['tinggi_badan'] ? $santri['tinggi_badan'] . ' cm' : '-') ?> &bull; 
                                    BB: <?= htmlspecialchars($santri['berat_badan'] ? $santri['berat_badan'] . ' kg' : '-') ?><br>
                                    LK: <?= htmlspecialchars($santri['lingkar_kepala'] ? $santri['lingkar_kepala'] . ' cm' : '-') ?>
                                </span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Kebutuhan Khusus / PIP</span>
                                <span class="font-bold text-gray-800 mt-0.5 block">
                                    ABK: <?= htmlspecialchars($santri['berkebutuhan_khusus'] ?: 'Tidak') ?><br>
                                    PIP: <?= htmlspecialchars($santri['pip'] ?: 'Tidak') ?>
                                </span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 sm:col-span-2 md:col-span-1">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Riwayat Penyakit</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['riwayat_penyakit'] ?: 'Tidak Ada') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Kartu Sekolah Asal -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-school mr-2 text-[#5C3E35]"></i> Riwayat Sekolah Asal
                            </h3>
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-bold"><?= htmlspecialchars($santri['jenis_sekolah_asal'] ?: 'Sekolah') ?></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Nama Sekolah Asal</span>
                                <span class="font-extrabold text-gray-900 mt-0.5 block text-sm"><?= htmlspecialchars($santri['nama_sekolah_asal'] ?: '-') ?></span>
                                <span class="text-[11px] text-gray-500 font-mono mt-0.5 block">NPSN: <?= htmlspecialchars($santri['npsn_sekolah_asal'] ?: '-') ?></span>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Alamat Sekolah Asal</span>
                                <span class="font-medium text-gray-800 mt-0.5 block leading-relaxed"><?= nl2br(htmlspecialchars($santri['alamat_sekolah_asal'] ?: '-')) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Kartu Alamat & Domisili -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-map-location-dot mr-2 text-[#5C3E35]"></i> Alamat &amp; Domisili Tempat Tinggal
                            </h3>
                            <?php 
                            $maps_query = rawurlencode(($santri['alamat_lengkap'] ?: '') . ' ' . ($santri['kelurahan'] ?: '') . ' ' . ($santri['kecamatan'] ?: '') . ' ' . ($santri['kabupaten'] ?: '') . ' ' . ($santri['provinsi'] ?: ''));
                            ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $maps_query ?>" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                <span>Buka Google Maps</span>
                            </a>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Provinsi</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['provinsi'] ?: '-') ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Kabupaten / Kota</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['kabupaten'] ?: '-') ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Kecamatan</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['kecamatan'] ?: '-') ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <span class="text-gray-400 font-bold uppercase text-[10px] block">Kelurahan / Desa</span>
                                <span class="font-bold text-gray-800 mt-0.5 block"><?= htmlspecialchars($santri['kelurahan'] ?: '-') ?></span>
                            </div>
                        </div>

                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">Alamat Lengkap / Jalan / RT / RW</span>
                            <span class="font-medium text-gray-800 mt-1 block leading-relaxed"><?= nl2br(htmlspecialchars($santri['alamat_lengkap'] ?: '-')) ?></span>
                        </div>
                    </div>

                </div>

                <!-- KOLOM KANAN (1 Kolom di Desktop): Aksi Status Cepat, Orang Tua, Dokumen -->
                <div class="space-y-6">

                    <!-- Kartu Panel Kontrol Status Terpadu -->
                    <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                        <div class="pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-sliders mr-2 text-[#5C3E35]"></i> Update Status Cepat
                            </h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Ubah status seleksi, presensi, dan pembayaran</p>
                        </div>

                        <form method="POST" action="" class="space-y-3.5 text-xs">
                            <input type="hidden" name="action" value="update_all_status">

                            <!-- Status Pembayaran -->
                            <div>
                                <label class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Status Pembayaran</label>
                                <select name="status_pembayaran" class="w-full border border-gray-300 focus:border-brand-primary rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none">
                                    <option value="Belum Bayar" <?= $santri['status_pembayaran'] === 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                                    <option value="Menunggu Verifikasi" <?= $santri['status_pembayaran'] === 'Menunggu Verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                                    <option value="Lunas" <?= $santri['status_pembayaran'] === 'Lunas' ? 'selected' : '' ?>>Lunas (Terverifikasi)</option>
                                </select>
                            </div>

                            <!-- Metode Pembayaran -->
                            <div>
                                <label class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Metode Pembayaran</label>
                                <select name="metode_pembayaran" class="w-full border border-gray-300 focus:border-brand-primary rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none">
                                    <option value="Transfer" <?= ($santri['metode_pembayaran'] ?? '') === 'Transfer' ? 'selected' : '' ?>>Transfer Bank</option>
                                    <option value="Tunai" <?= ($santri['metode_pembayaran'] ?? '') === 'Tunai' ? 'selected' : '' ?>>Tunai di Lokasi / Bendahara</option>
                                </select>
                            </div>

                            <!-- Hasil Seleksi (Panitia & Superadmin) -->
                            <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                            <div>
                                <label class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Hasil Seleksi Santri</label>
                                <select name="status_seleksi" class="w-full border border-gray-300 focus:border-brand-primary rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none">
                                    <option value="Belum Seleksi" <?= $santri['status_seleksi'] === 'Belum Seleksi' ? 'selected' : '' ?>>Belum Seleksi</option>
                                    <option value="Lulus" <?= $santri['status_seleksi'] === 'Lulus' ? 'selected' : '' ?>>Lulus Seleksi</option>
                                    <option value="Tidak Lulus" <?= $santri['status_seleksi'] === 'Tidak Lulus' ? 'selected' : '' ?>>Tidak Lulus</option>
                                </select>
                            </div>

                            <!-- Status Kehadiran Presensi Ujian -->
                            <div>
                                <label class="block font-bold text-gray-700 uppercase text-[10px] mb-1">Presensi Ujian Seleksi</label>
                                <select name="status_kehadiran" class="w-full border border-gray-300 focus:border-brand-primary rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none">
                                    <option value="Belum Hadir" <?= ($santri['status_kehadiran'] ?? '') !== 'Hadir' ? 'selected' : '' ?>>Belum Hadir</option>
                                    <option value="Hadir" <?= ($santri['status_kehadiran'] ?? '') === 'Hadir' ? 'selected' : '' ?>>Hadir di Lokasi Ujian</option>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="status_seleksi" value="<?= htmlspecialchars($santri['status_seleksi']) ?>">
                                <input type="hidden" name="status_kehadiran" value="<?= htmlspecialchars($santri['status_kehadiran'] ?? 'Belum Hadir') ?>">
                            <?php endif; ?>

                            <button type="submit" class="w-full bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-4 rounded-xl transition shadow-sm text-xs flex items-center justify-center space-x-1.5" style="background-color: #5C3E35 !important; color: #FFFFFF !important;">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Simpan Perubahan Status</span>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Kartu Kontak Orang Tua & Wali -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                        <div class="pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-users mr-2 text-[#5C3E35]"></i> Orang Tua / Wali
                            </h3>
                        </div>

                        <!-- Ayah -->
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-700 flex items-center">
                                    <i class="fa-solid fa-user-tie text-blue-600 mr-1.5"></i> Ayah Kandung
                                </span>
                                <?php if (!empty($santri['wa_ayah'])): ?>
                                    <a href="<?= get_wa_url_custom($santri['wa_ayah'], $santri['nama_santri'], $santri['no_registrasi'], $santri['status_pembayaran'], $santri['status_seleksi']) ?>" target="_blank" class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold flex items-center space-x-1" title="Chat WhatsApp Ayah">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>Chat WA</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-0.5 text-gray-600">
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($santri['nama_ayah'] ?: '-') ?></p>
                                <p>NIK: <?= htmlspecialchars($santri['nik_ayah'] ?: '-') ?></p>
                                <p>Pekerjaan: <?= htmlspecialchars($santri['pekerjaan_ayah'] ?: '-') ?> (<?= htmlspecialchars($santri['pendidikan_ayah'] ?: '-') ?>)</p>
                                <p>Penghasilan: <?= htmlspecialchars($santri['penghasilan_ayah'] ?: '-') ?></p>
                                <p class="font-mono text-emerald-700 font-bold">WA: <?= htmlspecialchars($santri['wa_ayah'] ?: '-') ?></p>
                            </div>
                        </div>

                        <!-- Ibu -->
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-700 flex items-center">
                                    <i class="fa-solid fa-person-dress text-pink-600 mr-1.5"></i> Ibu Kandung
                                </span>
                                <?php if (!empty($santri['wa_ibu'])): ?>
                                    <a href="<?= get_wa_url_custom($santri['wa_ibu'], $santri['nama_santri'], $santri['no_registrasi'], $santri['status_pembayaran'], $santri['status_seleksi']) ?>" target="_blank" class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold flex items-center space-x-1" title="Chat WhatsApp Ibu">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>Chat WA</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-0.5 text-gray-600">
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($santri['nama_ibu'] ?: '-') ?></p>
                                <p>NIK: <?= htmlspecialchars($santri['nik_ibu'] ?: '-') ?></p>
                                <p>Pekerjaan: <?= htmlspecialchars($santri['pekerjaan_ibu'] ?: '-') ?> (<?= htmlspecialchars($santri['pendidikan_ibu'] ?: '-') ?>)</p>
                                <p>Penghasilan: <?= htmlspecialchars($santri['penghasilan_ibu'] ?: '-') ?></p>
                                <p class="font-mono text-emerald-700 font-bold">WA: <?= htmlspecialchars($santri['wa_ibu'] ?: '-') ?></p>
                            </div>
                        </div>

                        <!-- Wali jika ada -->
                        <?php if (!empty($santri['nama_wali'])): ?>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-700 flex items-center">
                                    <i class="fa-solid fa-user-shield text-amber-600 mr-1.5"></i> Wali Santri
                                </span>
                                <?php if (!empty($santri['wa_wali'])): ?>
                                    <a href="<?= get_wa_url_custom($santri['wa_wali'], $santri['nama_santri'], $santri['no_registrasi'], $santri['status_pembayaran'], $santri['status_seleksi']) ?>" target="_blank" class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold flex items-center space-x-1" title="Chat WhatsApp Wali">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>Chat WA</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-0.5 text-gray-600">
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($santri['nama_wali']) ?></p>
                                <p class="font-mono text-emerald-700 font-bold">WA: <?= htmlspecialchars($santri['wa_wali'] ?: '-') ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Email -->
                        <div class="text-xs text-gray-600 px-1">
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">Email Notifikasi</span>
                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($santri['email'] ?: 'Tidak ada email terdaftar') ?></span>
                        </div>
                    </div>

                    <!-- Kartu Cetak Dokumen Resmi PDF -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
                        <div class="pb-3 border-b border-gray-100">
                            <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                                <i class="fa-solid fa-print mr-2 text-[#5C3E35]"></i> Dokumen Resmi
                            </h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Cetak dan unduh berkas pendaftaran santri</p>
                        </div>

                        <?php if ($santri['status_pembayaran'] === 'Lunas'): ?>
                            <div class="space-y-2">
                                <a href="../cetak_kartu.php?no_reg=<?= urlencode($santri['no_registrasi']) ?>" target="_blank" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold py-2.5 px-4 rounded-xl transition text-xs flex items-center justify-between shadow-2xs">
                                    <span class="flex items-center space-x-2">
                                        <i class="fa-solid fa-id-badge text-emerald-600 text-sm"></i>
                                        <span>Cetak Kartu Ujian (PDF)</span>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-emerald-600"></i>
                                </a>

                                <a href="../cetak_formulir.php?no_reg=<?= urlencode($santri['no_registrasi']) ?>" target="_blank" class="w-full bg-red-50 hover:bg-red-100 text-red-800 border border-red-200 font-bold py-2.5 px-4 rounded-xl transition text-xs flex items-center justify-between shadow-2xs">
                                    <span class="flex items-center space-x-2">
                                        <i class="fa-solid fa-file-pdf text-red-600 text-sm"></i>
                                        <span>Cetak Formulir Lengkap (PDF)</span>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-red-600"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-center text-xs text-amber-800">
                                <i class="fa-solid fa-lock text-amber-600 mb-1 text-sm block"></i>
                                <span class="font-bold">Dokumen Terkunci</span>
                                <p class="text-[11px] text-amber-700 mt-0.5">Kartu Ujian dan Formulir PDF dapat dicetak setelah status pembayaran Lunas.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </main>
    </div>

    <!-- Modal Pratinjau Struk Besar -->
    <div id="modalBukti" class="fixed inset-0 z-50 bg-black/75 hidden items-center justify-center p-4 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl animate-fade-in">
            <div class="px-6 py-4 bg-[#5C3E35] text-white flex items-center justify-between">
                <h4 class="font-bold text-sm flex items-center">
                    <i class="fa-solid fa-file-invoice mr-2 text-[#A17A6F]"></i> Bukti Transfer Pembayaran
                </h4>
                <button type="button" onclick="closeBuktiModal()" class="text-white hover:text-gray-300 text-lg font-bold">&times;</button>
            </div>
            <div class="p-4 bg-gray-100 flex items-center justify-center max-h-[75vh] overflow-auto">
                <img id="modalBuktiImg" src="" alt="Bukti Transfer" class="max-w-full max-h-[70vh] object-contain rounded-lg border border-gray-300 shadow">
            </div>
            <div class="px-6 py-3 bg-white border-t border-gray-200 flex items-center justify-between">
                <span class="text-xs text-gray-500 font-mono">No. Reg: <?= htmlspecialchars($santri['no_registrasi']) ?></span>
                <div class="flex items-center space-x-2">
                    <a id="modalBuktiDownload" href="#" target="_blank" download class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs">
                        <i class="fa-solid fa-download mr-1"></i> Unduh Asli
                    </a>
                    <button type="button" onclick="closeBuktiModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-xs font-bold transition">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openBuktiModal(imgUrl) {
            document.getElementById('modalBuktiImg').src = imgUrl;
            document.getElementById('modalBuktiDownload').href = imgUrl;
            document.getElementById('modalBukti').classList.remove('hidden');
            document.getElementById('modalBukti').classList.add('flex');
        }

        function closeBuktiModal() {
            document.getElementById('modalBukti').classList.add('hidden');
            document.getElementById('modalBukti').classList.remove('flex');
        }
    </script>
</body>
</html>
