<?php
// admin/pengaturan_email.php
require_once '../config/db.php';
require_once '../config/mail.php';

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if ($admin_role !== 'superadmin') {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

$error = '';
$success = '';
$mail_settings_file = __DIR__ . '/../config/mail_settings.json';

// Tangani simpan pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mail_settings') {
    $mail_enabled = isset($_POST['mail_enabled']) && $_POST['mail_enabled'] === '1';
    $mail_from = sanitize($_POST['mail_from'] ?? 'info@babuljannah.ponpes.id');
    $mail_from_name = sanitize($_POST['mail_from_name'] ?? 'Panitia PSB Babul Jannah');
    
    // Pisahkan daftar email berdasar baris baru atau koma
    $raw_emails = $_POST['admin_emails'] ?? '';
    $emails_array = [];
    $lines = preg_split('/[\r\n,]+/', $raw_emails);
    foreach ($lines as $line) {
        $clean_email = trim($line);
        if (!empty($clean_email) && filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {
            $emails_array[] = $clean_email;
        }
    }
    
    // Jika array kosong, default ke email admin utama
    if (empty($emails_array)) {
        $emails_array = ['admin@babuljannah.ponpes.id'];
    }
    
    $new_settings = [
        'mail_enabled' => $mail_enabled,
        'mail_from' => $mail_from,
        'mail_from_name' => $mail_from_name,
        'admin_emails' => array_values(array_unique($emails_array))
    ];
    
    if (file_put_contents($mail_settings_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
        $success = "Pengaturan notifikasi email berhasil diperbarui!";
    } else {
        $error = "Gagal menyimpan konfigurasi ke file mail_settings.json. Periksa hak akses file.";
    }
}

// Baca konfigurasi saat ini untuk ditampilkan di form
$cur_settings = [
    'mail_enabled' => MAIL_ENABLED,
    'mail_from' => MAIL_FROM,
    'mail_from_name' => MAIL_FROM_NAME,
    'admin_emails' => is_array(ADMIN_EMAILS) ? ADMIN_EMAILS : [ADMIN_EMAILS]
];

if (file_exists($mail_settings_file)) {
    $json_data = @json_decode(file_get_contents($mail_settings_file), true);
    if ($json_data) {
        $cur_settings = array_merge($cur_settings, $json_data);
    }
}
?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-screen overflow-y-auto">
        
        <?php
        $header_custom_title = '<h1 class="text-xl font-bold text-dark">Pengaturan Sistem Notifikasi Email</h1>';
        $header_custom_right = '
            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1.5 rounded-full border border-green-300 flex items-center gap-1.5">
                <i class="fa-solid fa-circle-check text-green-600"></i> UI Config Ready
            </span>';
        include 'includes/header.php'; 
        ?>

        <!-- Main Container -->
        <main class="p-8 max-w-4xl mx-auto w-full">
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm flex items-center justify-between">
                    <div><i class="fa-solid fa-triangle-exclamation mr-2"></i><?= $error ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center justify-between">
                    <div><i class="fa-solid fa-check-circle mr-2"></i><?= $success ?></div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-md border border-gray-200 overflow-hidden">
                <div class="bg-primary text-white p-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Konfigurasi Pengirim & Penerima Email</h2>
                        <p class="text-xs text-gray-300 mt-1">Atur daftar email panitia dan nama pengirim tanpa perlu mengedit kode programming.</p>
                    </div>
                    <i class="fa-solid fa-envelopes-bulk text-3xl text-secondary opacity-80"></i>
                </div>

                <form action="pengaturan_email.php" method="POST" class="p-8 space-y-6">
                    <input type="hidden" name="action" value="save_mail_settings">

                    <!-- Status Aktif -->
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="block font-bold text-dark text-sm">Status Notifikasi Email Otomatis</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Aktifkan untuk mengirim email otomatis ke santri dan panitia saat pendaftaran & validasi.</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="mail_enabled" value="1" <?= !empty($cur_settings['mail_enabled']) ? 'checked' : '' ?> class="text-secondary focus:ring-secondary">
                                <span class="ml-2 text-sm font-semibold text-green-700">Aktif (Ya)</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="mail_enabled" value="0" <?= empty($cur_settings['mail_enabled']) ? 'checked' : '' ?> class="text-secondary focus:ring-secondary">
                                <span class="ml-2 text-sm font-semibold text-red-700">Nonaktif (Tidak)</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nama Pengirim -->
                        <div>
                            <label for="mail_from_name" class="block text-sm font-bold text-dark mb-1">Nama Pengirim Email <span class="text-red-500">*</span></label>
                            <input type="text" name="mail_from_name" id="mail_from_name" value="<?= htmlspecialchars($cur_settings['mail_from_name'] ?? '') ?>" required class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl px-4 py-2.5 text-sm">
                            <p class="text-[11px] text-gray-500 mt-1">Contoh: Panitia PSB Babul Jannah</p>
                        </div>
                        <!-- Email Pengirim -->
                        <div>
                            <label for="mail_from" class="block text-sm font-bold text-dark mb-1">Alamat Email Pengirim (From) <span class="text-red-500">*</span></label>
                            <input type="email" name="mail_from" id="mail_from" value="<?= htmlspecialchars($cur_settings['mail_from'] ?? '') ?>" required class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl px-4 py-2.5 text-sm">
                            <p class="text-[11px] text-gray-500 mt-1">Contoh: info@babuljannah.ponpes.id</p>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Daftar Email Admin -->
                    <div>
                        <label for="admin_emails" class="block text-sm font-bold text-dark mb-1">Daftar Email Panitia (Penerima Notifikasi Pendaftar Baru) <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-600 mb-2">Anda bisa memasukkan lebih dari 1 email (ketik satu email per baris atau pisahkan dengan tanda koma). Seluruh email di bawah ini akan menerima notifikasi serentak saat ada santri baru mendaftar.</p>
                        <?php 
                            $emails_str = implode("\n", $cur_settings['admin_emails'] ?? []);
                        ?>
                        <textarea name="admin_emails" id="admin_emails" rows="5" required placeholder="admin@babuljannah.ponpes.id&#10;panitia.psb@babuljannah.ponpes.id&#10;bendahara@babuljannah.ponpes.id" class="w-full border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl p-3 font-mono text-sm leading-relaxed"><?= htmlspecialchars($emails_str) ?></textarea>
                        <div class="mt-2 bg-amber-50 border border-amber-200 p-3 rounded-lg flex items-start space-x-2 text-xs text-amber-800">
                            <i class="fa-solid fa-lightbulb mt-0.5 text-amber-600 flex-shrink-0"></i>
                            <span>Tips: Sistem anti-crash kami akan menguji validitas format setiap email di atas dan otomatis mencatat seluruh riwayat pengiriman di <strong>logs/email_history.log</strong>.</span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-secondary hover:bg-primary text-white font-bold py-3 px-8 rounded-xl shadow-lg transition-all flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan Pengaturan Email</span>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
