<?php
// admin/pengaturan_email.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

// Proteksi login admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Proteksi RBAC khusus superadmin
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if ($admin_role !== 'superadmin') {
    die("Akses Ditolak. Anda tidak memiliki izin mengelola sistem notifikasi email.");
}

$error = '';
$success = '';
$mail_settings_file = __DIR__ . '/../config/mail_settings.json';
$log_file = __DIR__ . '/../logs/email_history.log';

// =========================================================================
// 1. TANGANI AKSI SIMPAN PENGATURAN EMAIL
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    
    // Aksi 1: Simpan Konfigurasi
    if ($_POST['action'] === 'save_mail_settings') {
        $mail_enabled = isset($_POST['mail_enabled']) && ($_POST['mail_enabled'] === '1' || $_POST['mail_enabled'] === 'on');
        $mail_from = sanitize($_POST['mail_from'] ?? 'info@babuljannah.ponpes.id');
        $mail_from_name = sanitize($_POST['mail_from_name'] ?? 'Panitia PSB Babul Jannah');
        
        if (!filter_var($mail_from, FILTER_VALIDATE_EMAIL)) {
            $error = "Alamat email pengirim (From) tidak valid. Harap gunakan format email yang benar.";
        } else {
            // Pisahkan daftar email berdasarkan baris baru atau koma
            $raw_emails = $_POST['admin_emails'] ?? '';
            $emails_array = [];
            $lines = preg_split('/[\r\n,]+/', $raw_emails);
            foreach ($lines as $line) {
                $clean_email = trim($line);
                if (!empty($clean_email)) {
                    if (filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {
                        $emails_array[] = strtolower($clean_email);
                    }
                }
            }
            
            // Minimal satu email admin penerima
            if (empty($emails_array)) {
                $emails_array = ['admin@babuljannah.ponpes.id'];
            }
            
            $new_settings = [
                'mail_enabled' => $mail_enabled,
                'mail_from' => $mail_from,
                'mail_from_name' => $mail_from_name,
                'admin_emails' => array_values(array_unique($emails_array))
            ];
            
            if (@file_put_contents($mail_settings_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
                $success = "Pengaturan notifikasi email berhasil diperbarui!";
            } else {
                $error = "Gagal menyimpan konfigurasi ke file config/mail_settings.json. Periksa izin akses folder server.";
            }
        }
    }

    // Aksi 2: Uji Coba Pengiriman Email (Live Email Tester)
    elseif ($_POST['action'] === 'test_email') {
        $test_recipient = sanitize($_POST['test_recipient'] ?? '');
        if (empty($test_recipient) || !filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) {
            $error = "Alamat email tujuan uji coba tidak valid. Silakan periksa kembali.";
        } else {
            // Ambil pengaturan saat ini untuk pengujian
            $cfg_from = MAIL_FROM;
            $cfg_from_name = MAIL_FROM_NAME;
            if (file_exists($mail_settings_file)) {
                $js = @json_decode(file_get_contents($mail_settings_file), true);
                if (!empty($js['mail_from'])) $cfg_from = $js['mail_from'];
                if (!empty($js['mail_from_name'])) $cfg_from_name = $js['mail_from_name'];
            }

            $test_subject = "[UJI COBA SISTEM] Tes Pengiriman Email PSB Babul Jannah - " . date('d/m/Y H:i');
            $test_html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);'>
                <div style='background: linear-gradient(135deg, #5C3E35 0%, #432B25 100%); padding: 30px 20px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 22px; font-weight: 800;'>Uji Coba Pengiriman Berhasil!</h2>
                    <p style='margin: 6px 0 0; font-size: 13px; color: #D4C3B8;'>Sistem Notifikasi PSB Pondok Pesantren Babul Jannah</p>
                </div>
                <div style='padding: 25px; color: #333333; line-height: 1.6; background-color: #ffffff;'>
                    <p>Assalamu'alaikum Wr. Wb.,</p>
                    <p>Ini adalah <strong>email uji coba resmi</strong> yang dikirim dari Panel Admin Pondok Pesantren Babul Jannah untuk memverifikasi gateway email Anda.</p>
                    
                    <div style='background-color: #FBF9F6; border-left: 4px solid #5C3E35; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <table style='width: 100%; font-size: 13px; border-collapse: collapse;'>
                            <tr><td style='width: 130px; font-weight: bold; color: #5C3E35; padding: 4px 0;'>Waktu Uji Coba</td><td>: " . date('d F Y, H:i:s') . " WIB</td></tr>
                            <tr><td style='font-weight: bold; color: #5C3E35; padding: 4px 0;'>Nama Pengirim</td><td>: " . htmlspecialchars($cfg_from_name) . "</td></tr>
                            <tr><td style='font-weight: bold; color: #5C3E35; padding: 4px 0;'>Alamat Pengirim</td><td>: " . htmlspecialchars($cfg_from) . "</td></tr>
                            <tr><td style='font-weight: bold; color: #5C3E35; padding: 4px 0;'>Tujuan Tes</td><td>: <strong>" . htmlspecialchars($test_recipient) . "</strong></td></tr>
                            <tr><td style='font-weight: bold; color: #5C3E35; padding: 4px 0;'>Status Gateway</td><td>: <span style='color: #15803D; font-weight: bold;'>Aktif &amp; Terhubung</span></td></tr>
                        </table>
                    </div>

                    <p style='font-size: 13px; color: #555;'>Seluruh notifikasi otomatis terkait pendaftaran santri baru dan verifikasi pembayaran akan dikirim menggunakan konfigurasi ini.</p>
                    <p style='margin-top: 25px; font-size: 13px;'>Wassalamu'alaikum Wr. Wb.,<br><strong>Panitia Penerimaan Santri Baru (PSB) Babul Jannah</strong></p>
                </div>
                <div style='background-color: #F3EDE6; padding: 15px; text-align: center; font-size: 11px; color: #777777;'>
                    &copy; " . date('Y') . " Pondok Pesantren Babul Jannah Dumai. Seluruh hak cipta dilindungi.
                </div>
            </div>";

            $sent = send_psb_email($test_recipient, $test_subject, $test_html);
            if ($sent) {
                $success = "Email uji coba berhasil dikirim ke <strong>" . htmlspecialchars($test_recipient) . "</strong> dan telah dicatat dalam riwayat log pengiriman!";
            } else {
                $error = "Pengiriman email uji coba tidak berhasil dikirim via server mail. Catatan uji coba tetap disimpan di log riwayat.";
            }
        }
    }

    // Aksi 3: Kosongkan Riwayat Log
    elseif ($_POST['action'] === 'clear_logs') {
        if (file_exists($log_file)) {
            @file_put_contents($log_file, "");
            $success = "Riwayat log pengiriman email berhasil dikosongkan secara permanen!";
        } else {
            $success = "Berkas log riwayat email saat ini sudah kosong.";
        }
    }
}

// =========================================================================
// 2. BACA KONFIGURASI SAAT INI
// =========================================================================
$cur_settings = [
    'mail_enabled' => defined('MAIL_ENABLED') ? MAIL_ENABLED : true,
    'mail_from' => defined('MAIL_FROM') ? MAIL_FROM : 'info@babuljannah.ponpes.id',
    'mail_from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Panitia PSB Babul Jannah',
    'admin_emails' => defined('ADMIN_EMAILS') && is_array(ADMIN_EMAILS) ? ADMIN_EMAILS : ['admin@babuljannah.ponpes.id']
];

if (file_exists($mail_settings_file)) {
    $json_data = @json_decode(file_get_contents($mail_settings_file), true);
    if ($json_data && is_array($json_data)) {
        $cur_settings = array_merge($cur_settings, $json_data);
    }
}

// =========================================================================
// 3. PARSING LOG PENGIRIMAN EMAIL TERKINI (RECENT EMAIL LOGS)
// =========================================================================
$parsed_logs = [];
$total_log_count = 0;

if (file_exists($log_file)) {
    $raw_log = @file_get_contents($log_file);
    if (!empty($raw_log)) {
        $blocks = explode("--------------------------------------------------\n", $raw_log);
        $blocks = array_filter(array_map('trim', $blocks));
        $total_log_count = count($blocks);
        
        // Ambil 15 log terbaru
        foreach (array_reverse($blocks) as $blk) {
            if (empty($blk)) continue;
            
            $log_item = [
                'time' => '-',
                'to' => '-',
                'subject' => '-',
                'summary' => ''
            ];
            
            if (preg_match('/^\[(.*?)\]\s+TO:\s+(.*?)\s+\|\s+SUBJECT:\s+(.*?)(?:\n|$)/i', $blk, $m)) {
                $log_item['time'] = trim($m[1]);
                $log_item['to'] = trim($m[2]);
                $log_item['subject'] = trim($m[3]);
            }
            
            if (preg_match('/CONTENT \(Summary\):\s*([\s\S]*)$/i', $blk, $m_sum)) {
                $log_item['summary'] = trim($m_sum[1]);
            }
            
            if ($log_item['time'] !== '-' || $log_item['to'] !== '-') {
                $parsed_logs[] = $log_item;
                if (count($parsed_logs) >= 15) break;
            }
        }
    }
}

// Deteksi status fungsi mail server PHP
$is_mail_function_available = function_exists('mail');

$page_title = 'Pengaturan Sistem Notifikasi Email - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<style>
    /* Brand Color Fallbacks */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-deep { background-color: #432B25 !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .border-brand-primary { border-color: #5C3E35 !important; }

    /* Custom Toggle Switch */
    .toggle-checkbox:checked {
        right: 0;
        border-color: #15803D;
        background-color: #15803D;
    }
    .toggle-checkbox:checked + .toggle-label {
        background-color: #BBF7D0;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Main Container (Single Transparent Scrollbar) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & STATUS BANNER (UI-UX PRO MAX) -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                        <span>Sistem Notifikasi Email Otomatis</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full <?= !empty($cur_settings['mail_enabled']) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' ?>"></span>
                        <span class="<?= !empty($cur_settings['mail_enabled']) ? 'text-emerald-700' : 'text-rose-700' ?> normal-case font-bold">
                            <?= !empty($cur_settings['mail_enabled']) ? 'Gateway Aktif' : 'Gateway Nonaktif' ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola konfigurasi pengirim, daftar email panitia penerima alert, serta uji coba pengiriman email tanpa menyentuh kode program.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Preview Templates Button -->
                    <button type="button" onclick="openModalPreviewTemplates()" class="inline-flex items-center space-x-1.5 bg-white hover:bg-gray-100 text-gray-700 font-bold py-2 px-3 rounded-xl text-xs border border-gray-200 shadow-xs hover:shadow transition" title="Lihat Template Email Sistem">
                        <i class="fa-solid fa-eye text-xs text-gray-400"></i>
                        <span>Preview Template</span>
                    </button>

                    <!-- Test Send Email Button -->
                    <button type="button" onclick="openModalTestEmail()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Kirim Email Uji Coba">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Uji Coba Kirim</span>
                    </button>
                </div>
            </div>

            <!-- Alerts Notification -->
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in duration-200">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <div class="text-xs font-bold"><?= $success ?></div>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in duration-200">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                        <div class="text-xs font-bold"><?= $error ?></div>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- ======================================================== -->
            <!-- 2. TOP METRIC OVERVIEW CARDS (4 KPI STATS) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                
                <!-- Stat 1: Status Gateway -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl <?= !empty($cur_settings['mail_enabled']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?> flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-power-off"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status Notifikasi</span>
                        <div class="flex items-center space-x-1.5">
                            <span class="text-base sm:text-lg font-black <?= !empty($cur_settings['mail_enabled']) ? 'text-emerald-800' : 'text-rose-800' ?>">
                                <?= !empty($cur_settings['mail_enabled']) ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                            <span class="inline-block w-2 h-2 rounded-full <?= !empty($cur_settings['mail_enabled']) ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Email Pengirim (From) -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-at"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Email Pengirim</span>
                        <div class="truncate text-xs sm:text-sm font-black text-gray-900" title="<?= htmlspecialchars($cur_settings['mail_from']) ?>">
                            <?= htmlspecialchars($cur_settings['mail_from']) ?>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Panitia Penerima -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Panitia Penerima</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-amber-800"><?= count($cur_settings['admin_emails']) ?></span>
                            <span class="text-[10px] text-amber-700 font-semibold">Alamat Email</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Total Log Pengiriman -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Riwayat Log</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-purple-800"><?= number_format($total_log_count) ?></span>
                            <span class="text-[10px] text-purple-700 font-semibold">Tercatat</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. FORM KONFIGURASI UTAMA NOTIFIKASI EMAIL -->
            <!-- ======================================================== -->
            <div class="bg-white rounded-3xl border border-gray-200/90 shadow-xs overflow-hidden">
                
                <!-- Card Header -->
                <div class="bg-gradient-to-r from-brand-deep to-brand-primary text-white p-5 sm:p-6 flex items-center justify-between">
                    <div class="space-y-1">
                        <h2 class="text-base sm:text-lg font-black flex items-center">
                            <i class="fa-solid fa-sliders mr-2.5 text-secondary"></i> Konfigurasi Pengirim &amp; Penerima Notifikasi
                        </h2>
                        <p class="text-xs text-[#D4C3B8]">
                            Atur kredensial nama pengirim resmi dan daftar email staf panitia yang menerima alert saat pendaftar baru masuk.
                        </p>
                    </div>
                    <div class="hidden sm:flex w-12 h-12 rounded-2xl bg-white/10 border border-white/20 items-center justify-center text-xl text-white">
                        <i class="fa-solid fa-envelopes-bulk"></i>
                    </div>
                </div>

                <!-- Form Content -->
                <form action="pengaturan_email.php" method="POST" class="p-5 sm:p-7 space-y-6" onsubmit="return handleSaveSettings(this)">
                    <input type="hidden" name="action" value="save_mail_settings">

                    <!-- Setting 1: Toggle Switch Status Aktif -->
                    <div class="p-4 sm:p-5 rounded-2xl border <?= !empty($cur_settings['mail_enabled']) ? 'bg-emerald-50/60 border-emerald-200' : 'bg-gray-50 border-gray-200' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-colors">
                        <div class="space-y-1">
                            <div class="flex items-center space-x-2">
                                <span class="font-black text-sm text-gray-900">Status Notifikasi Email Otomatis</span>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full <?= !empty($cur_settings['mail_enabled']) ? 'bg-emerald-200 text-emerald-800' : 'bg-gray-200 text-gray-700' ?>">
                                    <?= !empty($cur_settings['mail_enabled']) ? 'Aktif' : 'Dinonaktifkan' ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 max-w-xl">
                                Jika diaktifkan, sistem akan otomatis mengirimkan email konfirmasi ke calon santri dan notifikasi pendaftar baru ke seluruh email panitia.
                            </p>
                        </div>

                        <!-- Toggle Switch -->
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="mail_enabled" value="1" <?= !empty($cur_settings['mail_enabled']) ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-13 h-7 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- Setting 2: Nama & Email Pengirim -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        
                        <!-- Nama Pengirim (From Name) -->
                        <div class="space-y-1.5">
                            <label for="mail_from_name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Nama Pengirim (From Name) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-user-tie text-xs"></i>
                                </span>
                                <input type="text" name="mail_from_name" id="mail_from_name" required
                                       value="<?= htmlspecialchars($cur_settings['mail_from_name'] ?? '') ?>"
                                       placeholder="Contoh: Panitia PSB Babul Jannah"
                                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                            <p class="text-[11px] text-gray-400">Nama resmi yang akan muncul di inbox email calon santri dan panitia.</p>
                        </div>

                        <!-- Alamat Email Pengirim (From Email) -->
                        <div class="space-y-1.5">
                            <label for="mail_from" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Alamat Email Pengirim (From Email) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-at text-xs"></i>
                                </span>
                                <input type="email" name="mail_from" id="mail_from" required
                                       value="<?= htmlspecialchars($cur_settings['mail_from'] ?? '') ?>"
                                       placeholder="Contoh: info@babuljannah.ponpes.id"
                                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            </div>
                            <p class="text-[11px] text-gray-400">Gunakan email domain pesantren atau email resmi admin.</p>
                        </div>

                    </div>

                    <!-- Setting 3: Daftar Email Panitia Penerima Notifikasi -->
                    <div class="space-y-3 pt-2 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <label for="admin_emails" class="block text-xs font-bold text-gray-800 uppercase tracking-wider">
                                    Daftar Email Panitia Penerima Alert <span class="text-rose-500">*</span>
                                </label>
                                <p class="text-[11px] text-gray-500">
                                    Setiap ada santri baru yang mendaftar di portal, sistem akan mengirimkan notifikasi serentak ke semua email di bawah ini.
                                </p>
                            </div>
                            <span class="text-[10px] font-extrabold text-brand-primary bg-brand-primary/10 px-2.5 py-1 rounded-lg w-max">
                                <i class="fa-solid fa-users mr-1"></i> Multi-Recipient Ready
                            </span>
                        </div>

                        <!-- Visual Interactive Chips Container -->
                        <div id="email-chips-container" class="flex flex-wrap gap-2 p-3 bg-gray-50/80 rounded-2xl border border-gray-200/80 min-h-[46px] items-center">
                            <?php foreach ($cur_settings['admin_emails'] as $em): ?>
                                <span class="email-chip inline-flex items-center space-x-1.5 bg-white border border-gray-300 text-gray-800 px-3 py-1 rounded-xl text-xs font-bold shadow-2xs">
                                    <i class="fa-solid fa-envelope text-[10px] text-brand-primary"></i>
                                    <span><?= htmlspecialchars($em) ?></span>
                                    <button type="button" onclick="removeEmailChip('<?= htmlspecialchars($em) ?>')" class="text-gray-400 hover:text-rose-600 transition pl-1">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <!-- Raw Textarea for Bulk Editing -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-gray-600">Edit / Tambah Daftar Email (1 email per baris atau pisahkan dengan koma):</span>
                                <button type="button" onclick="syncChipsToTextarea()" class="text-brand-primary hover:underline font-bold">
                                    <i class="fa-solid fa-rotate mr-1"></i> Refresh Tampilan Chips
                                </button>
                            </div>
                            <textarea name="admin_emails" id="admin_emails" rows="4" required oninput="updateChipsFromTextarea()"
                                      placeholder="admin@babuljannah.ponpes.id&#10;panitia.psb@babuljannah.ponpes.id&#10;bendahara@babuljannah.ponpes.id"
                                      class="w-full p-3.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-2xl font-mono text-xs text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none leading-relaxed"><?= htmlspecialchars(implode("\n", $cur_settings['admin_emails'] ?? [])) ?></textarea>
                        </div>

                        <!-- System Tips Box -->
                        <div class="bg-amber-50/70 border border-amber-200/90 p-3.5 rounded-2xl flex items-start space-x-3 text-xs text-amber-900">
                            <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-shield-halved text-xs"></i>
                            </div>
                            <div class="text-[11px] leading-relaxed">
                                <strong class="block text-amber-950 font-bold mb-0.5">Proteksi Anti-Crash &amp; Logging Otomatis</strong>
                                Sistem dilengkapi validator email ganda. Jika salah satu server email tujuan lambat atau bermasalah, proses registrasi santri tetap berjalan mulus tanpa error 500, dan seluruh histori pengiriman dicatat otomatis ke berkas <strong>logs/email_history.log</strong>.
                            </div>
                        </div>

                    </div>

                    <!-- Footer Action Bar -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <div class="text-[11px] text-gray-400">
                            <i class="fa-solid fa-circle-info mr-1"></i> Perubahan disimpan langsung ke file <code>config/mail_settings.json</code>
                        </div>
                        <button type="submit" id="btn-save-settings" class="inline-flex items-center space-x-2 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md hover:shadow-lg transition-all transform active:scale-98">
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span id="btn-save-text">Simpan Pengaturan Email</span>
                        </button>
                    </div>

                </form>

            </div>

            <!-- ======================================================== -->
            <!-- 4. RIWAYAT LOG PENGIRIMAN EMAIL TERKINI (LOG VIEWER) -->
            <!-- ======================================================== -->
            <div id="section-log-email" class="bg-white rounded-3xl border border-gray-200/90 shadow-xs overflow-hidden space-y-4 p-5 sm:p-6">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <div class="space-y-0.5">
                        <div class="flex items-center space-x-2">
                            <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-list-check"></i>
                            </span>
                            <h3 class="text-sm font-black text-gray-900">Riwayat Pengiriman Email Terkini</h3>
                        </div>
                        <p class="text-xs text-gray-500 pl-10">
                            Memantau status pengiriman email notifikasi santri dan alert panitia secara transparan.
                        </p>
                    </div>

                    <div class="flex items-center space-x-2 self-start sm:self-auto">
                        <!-- Search in Logs -->
                        <div class="relative w-48 sm:w-56">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                            </span>
                            <input type="text" id="search-log" oninput="filterEmailLogs()" placeholder="Cari email / subjek..."
                                   class="w-full pl-7 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 focus:outline-none focus:border-brand-primary">
                        </div>

                        <!-- Clear Logs Button -->
                        <form action="pengaturan_email.php" method="POST" onsubmit="return confirmClearLogs(this)" class="inline">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-3 py-1.5 rounded-xl transition" title="Kosongkan Riwayat Log">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                <span class="hidden sm:inline">Reset Log</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Table Logs -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <th class="py-2.5 px-3.5 w-10 text-center">No</th>
                                <th class="py-2.5 px-3.5 w-36">Waktu Kirim</th>
                                <th class="py-2.5 px-3.5 w-52">Penerima (To)</th>
                                <th class="py-2.5 px-3.5">Subjek &amp; Ringkasan Konten</th>
                                <th class="py-2.5 px-3.5 w-24 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="log-table-body">
                            <?php if (empty($parsed_logs)): ?>
                                <tr id="empty-log-row">
                                    <td colspan="5" class="py-8 text-center text-gray-400">
                                        <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-30 text-gray-400"></i>
                                        <span class="font-bold text-xs">Belum Ada Riwayat Pengiriman Email</span>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Catatan pengiriman akan muncul secara otomatis ketika ada email terkirim.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($parsed_logs as $lg): ?>
                                    <tr class="log-row hover:bg-gray-50/70 transition">
                                        <td class="py-3 px-3.5 text-center font-bold text-gray-400 text-[11px]"><?= $no++ ?></td>
                                        <td class="py-3 px-3.5 font-bold text-gray-600 text-[11px] whitespace-nowrap">
                                            <i class="fa-regular fa-clock mr-1 text-gray-400"></i>
                                            <?= htmlspecialchars($lg['time']) ?>
                                        </td>
                                        <td class="py-3 px-3.5">
                                            <span class="inline-flex items-center text-[11px] font-bold text-gray-900 bg-gray-100 px-2 py-0.5 rounded-md break-all">
                                                <?= htmlspecialchars($lg['to']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3.5 space-y-0.5">
                                            <strong class="log-subject block text-xs text-gray-900 leading-snug">
                                                <?= htmlspecialchars($lg['subject']) ?>
                                            </strong>
                                            <?php if (!empty($lg['summary'])): ?>
                                                <p class="log-summary text-[11px] text-gray-500 line-clamp-1 max-w-xl">
                                                    <?= htmlspecialchars($lg['summary']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <i class="fa-solid fa-circle-check mr-1 text-emerald-500 text-[9px]"></i> Tercatat
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL: UJI COBA PENGIRIMAN EMAIL (TEST SEND) -->
    <!-- ======================================================== -->
    <div id="modal-test-email" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <div class="bg-gradient-to-r from-brand-deep to-brand-primary text-white p-5 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-paper-plane text-white"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm sm:text-base">Uji Coba Pengiriman Email</h3>
                        <p class="text-[11px] text-[#D4C3B8]">Kirim email tes langsung ke alamat inbox Anda.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModalTestEmail()" class="text-white/80 hover:text-white transition p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form action="pengaturan_email.php" method="POST" class="p-6 space-y-4" onsubmit="return handleTestSubmit(this)">
                <input type="hidden" name="action" value="test_email">

                <div class="space-y-1.5">
                    <label for="test_recipient" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                        Email Penerima Uji Coba <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </span>
                        <input type="email" name="test_recipient" id="test_recipient" required
                               placeholder="masukkan.email.anda@gmail.com"
                               class="w-full pl-9 pr-4 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                    </div>
                    <p class="text-[10px] text-gray-400">Gunakan email aktif (Gmail, Yahoo, dll.) untuk mengecek inbox Anda.</p>
                </div>

                <div class="bg-blue-50/80 border border-blue-200 p-3 rounded-xl text-[11px] text-blue-900 leading-relaxed flex items-start space-x-2">
                    <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 flex-shrink-0"></i>
                    <span>Email tes berisi format resmi Pondok Pesantren Babul Jannah untuk memverifikasi kesiapan gateway server.</span>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-gray-100">
                    <button type="button" onclick="closeModalTestEmail()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-xl text-xs transition">
                        Batal
                    </button>
                    <button type="submit" id="btn-submit-test" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-5 rounded-xl text-xs shadow-md transition">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span id="btn-test-text">Kirim Sekarang</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 6. POP-UP MODAL: PREVIEW TEMPLATE EMAIL SISTEM -->
    <!-- ======================================================== -->
    <div id="modal-preview-templates" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-2xl my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-brand-deep to-brand-primary text-white p-5 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-palette text-secondary"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm sm:text-base">Preview Template Notifikasi Email</h3>
                        <p class="text-[11px] text-[#D4C3B8]">Tampilan nyata yang diterima calon santri dan panitia.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModalPreviewTemplates()" class="text-white/80 hover:text-white transition p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-gray-100 px-5 pt-3 border-b border-gray-200 flex items-center space-x-2 overflow-x-auto flex-shrink-0 text-xs">
                <button type="button" onclick="switchPreviewTab('santri_baru', this)" class="preview-tab-btn py-2 px-3.5 rounded-t-xl font-bold transition bg-white text-brand-primary border-t-2 border-brand-primary shadow-xs">
                    <i class="fa-solid fa-user-graduate mr-1"></i> 1. Pendaftaran Baru (Santri)
                </button>
                <button type="button" onclick="switchPreviewTab('admin_alert', this)" class="preview-tab-btn py-2 px-3.5 rounded-t-xl font-bold transition text-gray-500 hover:text-gray-900">
                    <i class="fa-solid fa-bell mr-1"></i> 2. Alert Pendaftar (Panitia)
                </button>
                <button type="button" onclick="switchPreviewTab('verif_lunas', this)" class="preview-tab-btn py-2 px-3.5 rounded-t-xl font-bold transition text-gray-500 hover:text-gray-900">
                    <i class="fa-solid fa-circle-check mr-1"></i> 3. Verifikasi Lunas (Santri)
                </button>
            </div>

            <!-- Tab Content Viewport -->
            <div class="p-5 sm:p-6 overflow-y-auto custom-scrollbar flex-grow bg-gray-50/50">
                
                <!-- Tab 1: Pendaftaran Baru Santri -->
                <div id="tab-content-santri_baru" class="preview-tab-pane space-y-3">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-5 max-w-xl mx-auto space-y-4">
                        <div class="bg-[#2E7D32] text-white p-5 text-center rounded-xl">
                            <h4 class="font-black text-lg">Pendaftaran Diterima!</h4>
                            <p class="text-xs text-white/80">Panitia Penerimaan Santri Baru (PSB) Babul Jannah</p>
                        </div>
                        <div class="text-xs text-gray-700 space-y-3">
                            <p>Assalamu'alaikum Wr. Wb.,</p>
                            <p>Terima kasih telah mendaftar di <strong>Ponpes Babul Jannah</strong>. Berikut rincian data pendaftaran Anda:</p>
                            <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 font-mono space-y-1">
                                <div><strong>No. Registrasi:</strong> <span class="text-brand-primary font-bold">PSB-2026-0089</span></div>
                                <div><strong>Nama Santri:</strong> Ahmad Fathoni Al-Fatih</div>
                                <div><strong>Jalur:</strong> SMP IT Babul Jannah</div>
                                <div><strong>No. WhatsApp:</strong> 081234567890</div>
                            </div>
                            <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded-r-xl">
                                <strong class="block text-amber-900 font-bold">Bank Syariah Indonesia (BSI)</strong>
                                <span class="text-gray-700">No. Rekening: <strong>7300750308</strong> (A.n. Pesantren Babul Jannah)</span>
                            </div>
                            <div class="text-center pt-2">
                                <span class="inline-block bg-[#2E7D32] text-white font-bold py-2 px-5 rounded-xl text-xs shadow-xs">
                                    Buka Portal Cek Status
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Alert Panitia -->
                <div id="tab-content-admin_alert" class="preview-tab-pane hidden space-y-3">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-5 max-w-xl mx-auto space-y-4">
                        <div class="bg-[#1976D2] text-white p-5 text-center rounded-xl">
                            <h4 class="font-black text-lg">Notifikasi Pendaftar Baru</h4>
                            <p class="text-xs text-white/80">Sistem PSB Internal Pondok Pesantren Babul Jannah</p>
                        </div>
                        <div class="text-xs text-gray-700 space-y-3">
                            <p>Yth. Tim Panitia / Keuangan,</p>
                            <p>Telah masuk pendaftaran santri baru melalui sistem online dengan rincian berikut:</p>
                            <div class="bg-blue-50/60 p-3.5 rounded-xl border border-blue-200 font-mono space-y-1">
                                <div><strong>No. Registrasi:</strong> <span class="text-blue-700 font-bold">PSB-2026-0089</span></div>
                                <div><strong>Nama Santri:</strong> Ahmad Fathoni Al-Fatih</div>
                                <div><strong>Jalur:</strong> SMP IT Babul Jannah</div>
                                <div><strong>No. WA Ayah:</strong> 081234567890</div>
                            </div>
                            <p class="text-[11px] text-gray-500">Mohon pantau Dashboard Admin secara berkala untuk memverifikasi bukti pembayaran.</p>
                            <div class="text-center pt-2">
                                <span class="inline-block bg-[#1976D2] text-white font-bold py-2 px-5 rounded-xl text-xs shadow-xs">
                                    Buka Panel Admin
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Verifikasi Lunas -->
                <div id="tab-content-verif_lunas" class="preview-tab-pane hidden space-y-3">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs p-5 max-w-xl mx-auto space-y-4">
                        <div class="bg-[#2E7D32] text-white p-5 text-center rounded-xl">
                            <h4 class="font-black text-lg">PEMBAYARAN TERVERIFIKASI</h4>
                            <p class="text-xs text-white/80">Ponpes Babul Jannah</p>
                        </div>
                        <div class="text-xs text-gray-700 space-y-3">
                            <p>Assalamu'alaikum Wr. Wb.,</p>
                            <p>Alhamdulillah, bukti pembayaran pendaftaran Anda telah <strong>DIVERIFIKASI (Lunas Biaya Tes)</strong>.</p>
                            <div class="bg-emerald-50 p-3.5 rounded-xl border border-emerald-200 font-mono space-y-1">
                                <div><strong>No. Registrasi:</strong> PSB-2026-0089</div>
                                <div><strong>Status:</strong> <span class="text-emerald-700 font-bold">Lunas / Terverifikasi</span></div>
                            </div>
                            <p class="text-[11px] text-gray-600">Kartu Ujian dan Formulir Pendaftaran Resmi sudah dapat diunduh sekarang.</p>
                            <div class="text-center pt-2">
                                <span class="inline-block bg-brand-primary text-white font-bold py-2 px-5 rounded-xl text-xs shadow-xs">
                                    Unduh Kartu &amp; Formulir Sekarang
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-white border-t border-gray-150 flex items-center justify-end flex-shrink-0">
                <button type="button" onclick="closeModalPreviewTemplates()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-5 rounded-xl text-xs transition">
                    Tutup Preview
                </button>
            </div>

        </div>
    </div>

    <!-- ======================================================== -->
    <!-- JAVASCRIPT LOGIC (CHIPS, SEARCH, MODAL, SWEETALERT) -->
    <!-- ======================================================== -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            updateChipsFromTextarea();
        });

        // 1. Email Chips Visualization & Sync
        function updateChipsFromTextarea() {
            const textarea = document.getElementById('admin_emails');
            const container = document.getElementById('email-chips-container');
            if (!textarea || !container) return;

            const text = textarea.value || '';
            const lines = text.split(/[\r\n,]+/);
            const emails = [];

            lines.forEach(l => {
                const em = l.trim().toLowerCase();
                if (em && em.includes('@') && em.includes('.')) {
                    if (!emails.includes(em)) {
                        emails.push(em);
                    }
                }
            });

            container.innerHTML = '';
            if (emails.length === 0) {
                container.innerHTML = '<span class="text-xs text-gray-400 italic font-medium"><i class="fa-solid fa-circle-info mr-1"></i> Belum ada email panitia valid yang dimasukkan.</span>';
                return;
            }

            emails.forEach(em => {
                const chip = document.createElement('span');
                chip.className = 'email-chip inline-flex items-center space-x-1.5 bg-white border border-gray-300 text-gray-800 px-3 py-1 rounded-xl text-xs font-bold shadow-2xs animate-in fade-in duration-150';
                chip.innerHTML = `
                    <i class="fa-solid fa-envelope text-[10px] text-brand-primary"></i>
                    <span>${escapeHtml(em)}</span>
                    <button type="button" onclick="removeEmailChip('${escapeHtml(em)}')" class="text-gray-400 hover:text-rose-600 transition pl-1" title="Hapus Email">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                `;
                container.appendChild(chip);
            });
        }

        function removeEmailChip(emailToRemove) {
            const textarea = document.getElementById('admin_emails');
            if (!textarea) return;

            const text = textarea.value || '';
            const lines = text.split(/[\r\n,]+/);
            const updated = lines.map(l => l.trim()).filter(l => l.toLowerCase() !== emailToRemove.toLowerCase() && l.length > 0);
            
            textarea.value = updated.join("\n");
            updateChipsFromTextarea();
        }

        function syncChipsToTextarea() {
            updateChipsFromTextarea();
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // 2. Search Logs Table
        function filterEmailLogs() {
            const query = (document.getElementById('search-log').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.log-row');
            let visibleCount = 0;

            rows.forEach(r => {
                const text = r.textContent.toLowerCase();
                const isMatch = !query || text.includes(query);
                r.style.display = isMatch ? '' : 'none';
                if (isMatch) visibleCount++;
            });

            const emptyRow = document.getElementById('empty-log-row');
            if (emptyRow) {
                emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
            }
        }

        // 3. Test Email Modal
        function openModalTestEmail() {
            document.getElementById('modal-test-email').classList.remove('hidden');
            setTimeout(() => document.getElementById('test_recipient').focus(), 150);
        }

        function closeModalTestEmail() {
            document.getElementById('modal-test-email').classList.add('hidden');
        }

        function handleTestSubmit(form) {
            const btn = document.getElementById('btn-submit-test');
            const txt = document.getElementById('btn-test-text');
            if (btn && txt) {
                btn.disabled = true;
                txt.textContent = 'Mengirim Email Uji Coba...';
            }
            return true;
        }

        // 4. Preview Templates Modal & Tabs
        function openModalPreviewTemplates() {
            document.getElementById('modal-preview-templates').classList.remove('hidden');
        }

        function closeModalPreviewTemplates() {
            document.getElementById('modal-preview-templates').classList.add('hidden');
        }

        function switchPreviewTab(tabKey, btn) {
            document.querySelectorAll('.preview-tab-btn').forEach(b => {
                b.classList.remove('bg-white', 'text-brand-primary', 'border-t-2', 'border-brand-primary', 'shadow-xs');
                b.classList.add('text-gray-500');
            });
            if (btn) {
                btn.classList.add('bg-white', 'text-brand-primary', 'border-t-2', 'border-brand-primary', 'shadow-xs');
                btn.classList.remove('text-gray-500');
            }

            document.querySelectorAll('.preview-tab-pane').forEach(p => p.classList.add('hidden'));
            const targetPane = document.getElementById('tab-content-' + tabKey);
            if (targetPane) targetPane.classList.remove('hidden');
        }

        // 5. Form Save Loading Protection
        function handleSaveSettings(form) {
            const btn = document.getElementById('btn-save-settings');
            const txt = document.getElementById('btn-save-text');
            if (btn && txt) {
                btn.disabled = true;
                txt.textContent = 'Menyimpan Konfigurasi...';
            }
            return true;
        }

        // 6. Reset Log Confirmation with SweetAlert2
        function confirmClearLogs(form) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Kosongkan Riwayat Log Email?',
                    text: 'Seluruh catatan riwayat pengiriman email yang tersimpan di server akan dibersihkan secara permanen.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Kosongkan Log',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
                return false;
            }
            return confirm('Apakah Anda yakin ingin mengosongkan seluruh riwayat log email?');
        }

        // Keyboard Shortcut Esc
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModalTestEmail();
                closeModalPreviewTemplates();
            }
        });
    </script>

</body>
</html>
