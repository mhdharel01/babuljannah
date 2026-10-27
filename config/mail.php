<?php
// config/mail.php - Sistem Notifikasi Email PSB Babul Jannah

require_once __DIR__ . '/env.php';

// Baca konfigurasi dari file JSON jika ada (diatur dari halaman Admin Panel)
$mail_settings_file = __DIR__ . '/mail_settings.json';
if (file_exists($mail_settings_file)) {
    $mail_json = @json_decode(file_get_contents($mail_settings_file), true);
    define('MAIL_ENABLED', $mail_json['mail_enabled'] ?? env('MAIL_ENABLED', true));
    define('MAIL_FROM', $mail_json['mail_from'] ?? env('MAIL_FROM', 'info@babuljannah.ponpes.id'));
    define('MAIL_FROM_NAME', $mail_json['mail_from_name'] ?? env('MAIL_FROM_NAME', 'Panitia PSB Babul Jannah'));
    define('ADMIN_EMAILS', $mail_json['admin_emails'] ?? ['admin@babuljannah.ponpes.id']);
} else {
    define('MAIL_ENABLED', env('MAIL_ENABLED', true));
    define('MAIL_FROM', env('MAIL_FROM', 'info@babuljannah.ponpes.id'));
    define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Panitia PSB Babul Jannah'));
    define('ADMIN_EMAILS', [
        'admin@babuljannah.ponpes.id',
        'panitia.psb@babuljannah.ponpes.id',
        'bendahara@babuljannah.ponpes.id'
    ]);
}

/**
 * Fungsi dasar pengiriman email yang aman dan anti-crash (Mendukung array/banyak penerima)
 */
function send_psb_email($to, $subject, $html_message) {
    if (!MAIL_ENABLED || empty($to)) {
        return false;
    }

    // Ubah format penerima jika berupa array menjadi string yang dipisahkan koma
    if (is_array($to)) {
        $to_list = implode(', ', array_filter(array_map('trim', $to)));
    } else {
        $to_list = trim($to);
    }

    if (empty($to_list)) {
        return false;
    }

    // 1. Catat ke file log (sangat berguna untuk pengecekan saat offline / XAMPP)
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . '/email_history.log';
    $log_entry = "[" . date('Y-m-d H:i:s') . "] TO: {$to_list} | SUBJECT: {$subject}\n" . 
                 "CONTENT (Summary): " . substr(strip_tags($html_message), 0, 200) . "...\n" . 
                 "--------------------------------------------------\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);

    // 2. Kirim email menggunakan fungsi mail() native PHP dengan header HTML
    try {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">" . "\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($to_list, $subject, $html_message, $headers);
        return true;
    } catch (Throwable $e) {
        // Abaikan error agar alur pendaftaran tidak terganggu jika server mail belum aktif/disabled
        return false;
    }
}

/**
 * Email 1: Notifikasi Pendaftaran Baru (Ke Santri & Admin)
 */
function send_email_pendaftaran_baru($data) {
    global $db;
    $jalur = $data['jalur_pendaftaran'] ?? '';
    $biaya_tes = function_exists('get_biaya_tes') ? get_biaya_tes($jalur) : 350000;

    // A. Kirim ke Santri / Orang Tua (jika email diisi)
    if (!empty($data['email'])) {
        $subject = "Pendaftaran Berhasil - [" . $data['no_registrasi'] . "] PSB Ponpes Babul Jannah";
        
        // HTML Template untuk Notifikasi Pendaftaran (Tema Hijau/Alam)
        $html_santri = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden;'>
            <div style='background-color: #2E7D32; padding: 30px 20px; text-align: center;'>
                <h2 style='margin: 0; color: #FFFFFF; font-size: 24px;'>Pendaftaran Diterima!</h2>
                <p style='margin: 5px 0 0; font-size: 12px; color: #F5F0EB;'>Panitia Penerimaan Santri Baru (PSB)</p>
            </div>
            <div style='padding: 25px; color: #333333; line-height: 1.6;'>
                <p>Assalamu'alaikum Wr. Wb.,</p>
                <p>Terima kasih telah mendaftar di <strong>Ponpes Babul Jannah</strong>. Berikut adalah rincian data pendaftaran Anda:</p>
                <table style='width: 100%; background-color: #f9f9f9; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #eeeeee;'>
                    <tr><td style='width: 140px; font-weight: bold;'>No. Registrasi</td><td>: <span style='color: #3E2723; font-weight: bold; font-size: 16px;'>" . $data['no_registrasi'] . "</span></td></tr>
                    <tr><td style='font-weight: bold;'>Nama Santri</td><td>: " . htmlspecialchars($data['nama_santri']) . "</td></tr>
                    <tr><td style='font-weight: bold;'>Jalur Pilihan</td><td>: " . htmlspecialchars($data['jalur_pendaftaran']) . "</td></tr>
                    <tr><td style='font-weight: bold;'>No. WhatsApp</td><td>: " . htmlspecialchars($data['wa_ayah']) . "</td></tr>
                </table>
                <h4 style='color: #3E2723; margin-top: 20px;'>Langkah Selanjutnya (Pembayaran Biaya Tes):</h4>
                <p>Silakan lakukan pembayaran biaya pendaftaran & tes seleksi sebesar <strong>" . (function_exists('format_rupiah') ? format_rupiah($biaya_tes) : ('Rp ' . number_format($biaya_tes, 0, ',', '.'))) . "</strong> ke rekening resmi pesantren:</p>
                <div style='background-color: #FFF8E1; border-left: 4px solid #FFA000; padding: 12px; margin: 15px 0;'>
                    <strong>Bank Syariah Indonesia (BSI)</strong><br>
                    No. Rekening: <strong>7123456789</strong><br>
                    A.n.: <strong>PSB Ponpes Babul Jannah</strong>
                </div>
                <p>Setelah transfer, harap segera unggah bukti pembayaran melalui Portal Cek Status menggunakan Nomor Registrasi dan No. WhatsApp Anda.</p>
                <p style='text-align: center; margin-top: 25px;'>
                    <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/status.php?no_reg=" . urlencode($data['no_registrasi']) . "&phone=" . urlencode($data['wa_ayah']) . "' style='background-color: #2E7D32; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Buka Portal Cek Status</a>
                </p>
                <p style='margin-top: 30px; font-size: 13px;'>Wassalamu'alaikum Wr. Wb.,<br><strong>Panitia PSB Ponpes Babul Jannah</strong></p>
            </div>
            <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 11px; color: #777777;'>
                &copy; " . date('Y') . " Pondok Pesantren Babul Jannah. All rights reserved.
            </div>
        </div>";
        send_psb_email($data['email'], $subject, $html_santri);
    }

    // B. Kirim Notifikasi ke Admin Pesantren
    $subject_admin = "[ADMIN NOTIF] Pendaftar Baru: " . $data['nama_santri'] . " (" . $data['no_registrasi'] . ")";
    $html_admin = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #1976D2; padding: 25px 20px; text-align: center;'>
            <h2 style='margin: 0; color: #FFFFFF; font-size: 22px;'>Notifikasi Pendaftar Baru</h2>
            <p style='margin: 5px 0 0; font-size: 13px; color: #E3F2FD;'>Sistem PSB Internal Babul Jannah</p>
        </div>
        <div style='padding: 25px; color: #333333; line-height: 1.6;'>
            <p>Yth. Tim Panitia / Keuangan,</p>
            <p>Telah masuk pendaftaran santri baru melalui sistem online. Berikut rincian datanya:</p>
            <table style='width: 100%; background-color: #f8fafd; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #e3f2fd; font-size: 14px;'>
                <tr><td style='width: 130px; font-weight: bold; padding-bottom: 8px;'>No. Registrasi</td><td style='padding-bottom: 8px;'>: <span style='color: #1565C0; font-weight: bold;'>" . $data['no_registrasi'] . "</span></td></tr>
                <tr><td style='font-weight: bold; padding-bottom: 8px;'>Nama Santri</td><td style='padding-bottom: 8px;'>: " . htmlspecialchars($data['nama_santri']) . "</td></tr>
                <tr><td style='font-weight: bold; padding-bottom: 8px;'>Jalur Pilihan</td><td style='padding-bottom: 8px;'>: " . htmlspecialchars($data['jalur_pendaftaran']) . "</td></tr>
                <tr><td style='font-weight: bold; padding-bottom: 8px;'>No. WA Ayah</td><td style='padding-bottom: 8px;'>: " . htmlspecialchars($data['wa_ayah']) . "</td></tr>
                <tr><td style='font-weight: bold;'>Email Pendaftar</td><td>: " . htmlspecialchars($data['email'] ?? '-') . "</td></tr>
            </table>
            <p style='font-size: 13px; color: #666;'>Mohon pantau Dashboard Admin secara berkala untuk memverifikasi apabila santri yang bersangkutan telah mengunggah bukti transfer biaya pendaftaran.</p>
            <p style='text-align: center; margin-top: 25px;'>
                <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/admin/login.php' style='background-color: #1976D2; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Buka Admin Panel</a>
            </p>
        </div>
        <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 11px; color: #777777;'>
            Pesan ini dikirim otomatis oleh Sistem Server PSB Ponpes Babul Jannah.
        </div>
    </div>";
    send_psb_email(ADMIN_EMAILS, $subject_admin, $html_admin);
}

/**
 * Email 2: Notifikasi Pembayaran Lunas (Ke Santri)
 */
function send_email_verifikasi_lunas($data) {
    if (empty($data['email'])) {
        return false;
    }

    $subject = "Pembayaran Terverifikasi (Lunas Ujian) - [" . $data['no_registrasi'] . "] PSB Babul Jannah";
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #2E7D32; color: #ffffff; padding: 20px; text-align: center;'>
            <h2 style='margin: 0;'>PEMBAYARAN TERVERIFIKASI</h2>
            <p style='margin: 5px 0 0; font-size: 13px;'>Ponpes Babul Jannah</p>
        </div>
        <div style='padding: 25px; color: #333333; line-height: 1.6;'>
            <p>Assalamu'alaikum Wr. Wb.,</p>
            <p>Alhamdulillah, bukti pembayaran pendaftaran Anda telah <strong>DIVERIFIKASI (Lunas Biaya Tes)</strong> oleh Panitia PSB.</p>
            <div style='background-color: #E8F5E9; border-left: 4px solid #2E7D32; padding: 15px; margin: 15px 0;'>
                <strong>No. Registrasi:</strong> " . $data['no_registrasi'] . "<br>
                <strong>Nama Santri:</strong> " . htmlspecialchars($data['nama_santri']) . "<br>
                <strong>Status Pembayaran:</strong> <span style='color: #2E7D32; font-weight: bold;'>Lunas / Terverifikasi</span>
            </div>
            <h4 style='color: #3E2723; margin-top: 20px;'>Akses Kartu Ujian & Formulir:</h4>
            <p>Anda sekarang sudah dapat mengunduh dan mencetak <strong>Kartu Ujian Seleksi</strong> dan <strong>Formulir Pendaftaran Resmi</strong> secara langsung melalui Portal Cek Status.</p>
            <p style='text-align: center; margin-top: 25px;'>
                <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/status.php?no_reg=" . urlencode($data['no_registrasi']) . "&phone=" . urlencode($data['wa_ayah']) . "' style='background-color: #3E2723; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Unduh Kartu & Formulir Sekarang</a>
            </p>
            <p style='margin-top: 30px; font-size: 13px;'>Wassalamu'alaikum Wr. Wb.,<br><strong>Panitia PSB Ponpes Babul Jannah</strong></p>
        </div>
        <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 11px; color: #777777;'>
            &copy; " . date('Y') . " Pondok Pesantren Babul Jannah. All rights reserved.
        </div>
    </div>";
    
    send_psb_email($data['email'], $subject, $html);
}
?>
