<?php
// config/db.php
require_once __DIR__ . '/env.php';

// Timezone Indonesia (WIB = UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Deteksi otomatis apakah berjalan di Localhost (XAMPP) atau Server Hosting (Hostinger)
$is_local = (
    env('APP_ENV') === 'local' ||
    (php_sapi_name() === 'cli' && empty($_SERVER['HTTP_HOST'])) ||
    (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false))
);

if ($is_local) {
    // Database Localhost (XAMPP)
    $host = env('DB_HOST_LOCAL', 'localhost');
    $db_name = env('DB_NAME_LOCAL', 'babul_jannah');
    $username = env('DB_USER_LOCAL', 'root');
    $password = env('DB_PASS_LOCAL', '');
    ini_set('display_errors', env('APP_DEBUG', true) ? 1 : 0);
    error_reporting(E_ALL);
} else {
    // Database Server Hosting (Hostinger)
    $host = env('DB_HOST_PROD', 'localhost');
    $db_name = env('DB_NAME_PROD', 'u168859970_Babul_jannah');
    $username = env('DB_USER_PROD', 'u168859970_babuljannah');
    $password = env('DB_PASS_PROD', '');
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}


try {
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Paksa MySQL ikut timezone WIB agar NOW() juga menghasilkan WIB
    $db->exec("SET time_zone = '+07:00'");

    // Otomatis migrasi struktur tabel jika ada update skema baru (seperti penambahan kolom email)
    try {
        $db->query("SELECT email FROM pendaftaran LIMIT 1");
    } catch (Exception $ex) {
        @$db->exec("ALTER TABLE pendaftaran ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER wa_ayah");
    }
    try {
        $db->query("SELECT status_kehadiran FROM pendaftaran LIMIT 1");
    } catch (Exception $ex) {
        @$db->exec("ALTER TABLE pendaftaran ADD COLUMN status_kehadiran ENUM('Belum Hadir', 'Hadir') DEFAULT 'Belum Hadir' AFTER status_seleksi");
    }
    
    // Pastikan tabel prestasi ada
    @$db->exec("CREATE TABLE IF NOT EXISTS prestasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        kategori ENUM('Keagamaan', 'Akademik', 'Seni & Bahasa', 'Olahraga / Fisik', 'Lainnya') DEFAULT 'Lainnya',
        gambar VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Pastikan tabel pengaturan_sistem ada
    @$db->exec("CREATE TABLE IF NOT EXISTS pengaturan_sistem (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kunci VARCHAR(100) NOT NULL UNIQUE,
        nilai TEXT DEFAULT NULL
    )");
    @$db->exec("INSERT IGNORE INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_pendaftaran', '350000')");
    @$db->exec("INSERT IGNORE INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_smp', '300000')");
    @$db->exec("INSERT IGNORE INTO pengaturan_sistem (kunci, nilai) VALUES ('biaya_tes_sma', '350000')");

    // Pastikan tabel master_biaya_pendidikan ada
    @$db->exec("CREATE TABLE IF NOT EXISTS master_biaya_pendidikan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_jenjang VARCHAR(20) NOT NULL UNIQUE,
        jenjang VARCHAR(100) NOT NULL,
        deskripsi TEXT DEFAULT NULL,
        uang_pangkal INT DEFAULT 0,
        seragam INT DEFAULT 0,
        kegiatan INT DEFAULT 0,
        spp_bulanan INT DEFAULT 0,
        catatan VARCHAR(255) DEFAULT NULL
    )");

    // Pastikan tabel ekskul ada
    @$db->exec("CREATE TABLE IF NOT EXISTS ekskul (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        deskripsi TEXT NOT NULL,
        ikon VARCHAR(100) DEFAULT 'fa-solid fa-star',
        gambar VARCHAR(255) NOT NULL,
        urutan INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    try {
        $db->query("SELECT waktu_kehadiran FROM pendaftaran LIMIT 1");
    } catch (Exception $ex) {
        @$db->exec("ALTER TABLE pendaftaran ADD COLUMN waktu_kehadiran DATETIME NULL DEFAULT NULL AFTER status_kehadiran");
    }
} catch (PDOException $e) {
    if ($is_local) {
        die("Koneksi database gagal: " . $e->getMessage() . ". Silakan buat database 'babul_jannah' terlebih dahulu dan import 'database.sql'.");
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Maaf, terjadi kendala koneksi ke database. Silakan coba beberapa saat lagi atau hubungi administrator.");
    }
}

// Mulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper function untuk sanitasi input
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim((string)($data ?? ''))), ENT_QUOTES, 'UTF-8');
}

// Helper untuk format Rupiah
function format_rupiah($angka)
{
    return "Rp " . number_format((float)($angka ?? 0), 0, ',', '.');
}

// Helper untuk format tanggal Indonesia
function format_tanggal($tanggal)
{
    if (!$tanggal || $tanggal === '0000-00-00' || $tanggal === '0000-00-00 00:00:00')
        return '-';
    $time = strtotime($tanggal);
    if ($time === false)
        return '-';
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $split = explode('-', date('Y-m-d', $time));
    return (int)$split[2] . ' ' . ($bulan[(int)$split[1]] ?? '') . ' ' . $split[0];
}

// Include Cloud Storage (Supabase)
if (file_exists(__DIR__ . '/storage.php')) {
    require_once __DIR__ . '/storage.php';
}

if (!defined('SUPABASE_STORAGE_ENABLED')) define('SUPABASE_STORAGE_ENABLED', env('SUPABASE_STORAGE_ENABLED', false));
if (!defined('SUPABASE_PROJECT_URL')) define('SUPABASE_PROJECT_URL', env('SUPABASE_PROJECT_URL', ''));
if (!defined('SUPABASE_API_KEY')) define('SUPABASE_API_KEY', env('SUPABASE_API_KEY', ''));
if (!defined('SUPABASE_BUCKET')) define('SUPABASE_BUCKET', env('SUPABASE_BUCKET', 'babuljannah'));

if (!function_exists('get_storage_url')) {
    function get_storage_url($folder, $filename, $default = '', $is_admin = false) {
        if (empty($filename)) return $default;
        if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) return $filename;
        if (strpos($filename, 'assets/') === 0) return ($is_admin ? '../' : '') . $filename;
        
        $clean_folder = trim((string)$folder, '/\\');
        $clean_filename = trim((string)$filename, '/\\');
        $clean_path = !empty($clean_folder) ? ($clean_folder . '/' . $clean_filename) : $clean_filename;
        
        // Cek apakah file lokal ada di folder uploads
        $base_root = dirname(__DIR__);
        $local_paths = [
            $base_root . '/' . $clean_path,
            $base_root . '/uploads/' . $clean_filename
        ];
        foreach ($local_paths as $lp) {
            if (file_exists($lp)) {
                $rel = (strpos($lp, 'uploads') !== false) ? ('uploads/' . $clean_filename) : $clean_path;
                return ($is_admin ? '../' : '') . $rel;
            }
        }
        
        // Jika tidak ada di lokal atau storage cloud aktif, arahkan ke Supabase URL
        $base_url = rtrim(defined('SUPABASE_PROJECT_URL') ? SUPABASE_PROJECT_URL : env('SUPABASE_PROJECT_URL', 'https://bsnnbzkiblodvielwyyx.supabase.co'), '/');
        $bucket = defined('SUPABASE_BUCKET') ? SUPABASE_BUCKET : env('SUPABASE_BUCKET', 'babuljannah');
        return $base_url . '/storage/v1/object/public/' . $bucket . '/' . $clean_path;
    }
}

// Helper untuk mengambil biaya formulir & tes seleksi (1 nilai biaya seleksi)
if (!function_exists('get_biaya_tes')) {
    function get_biaya_tes($jenjang = '') {
        global $db;
        try {
            if ($db) {
                $stmt = $db->query("SELECT nilai FROM pengaturan_sistem WHERE kunci = 'biaya_tes_pendaftaran' LIMIT 1");
                $val = $stmt ? $stmt->fetchColumn() : null;
                if ($val !== false && $val !== null && is_numeric($val)) {
                    return (int)$val;
                }
            }
        } catch (Exception $e) {
            // Ignore & fallback
        }
        return 350000;
    }
}

// Helper untuk upload gambar, auto-resize, konversi WebP, dan simpan ke Cloud Storage (Supabase)
function upload_and_convert_to_webp($file_input_name, $upload_dir, $prefix = 'img', $max_width = 1600)
{
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $file_tmp = $_FILES[$file_input_name]['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($file_ext, $allowed) || $_FILES[$file_input_name]['size'] > 5 * 1024 * 1024) {
        return false; // Not allowed or too big
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = $prefix . '_' . time() . '_' . rand(100, 999) . '.webp';
    $dest_path = rtrim($upload_dir, '/') . '/' . $new_filename;

    // Convert to WebP & Auto-resize
    $image = null;
    if ($file_ext === 'png') {
        $image = @imagecreatefrompng($file_tmp);
        if ($image) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }
    } elseif ($file_ext === 'webp') {
        // Already WebP, move then upload
        if (move_uploaded_file($file_tmp, $dest_path)) {
            if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
                $clean_dir = trim(str_replace(['../uploads', 'uploads', './uploads'], '', $upload_dir), '/\\');
                $remote_path = !empty($clean_dir) ? ($clean_dir . '/' . $new_filename) : $new_filename;
                if (supabase_upload($dest_path, $remote_path, 'image/webp')) {
                    @unlink($dest_path);
                }
            }
            return $new_filename;
        }
        return false;
    } else {
        $image = @imagecreatefromjpeg($file_tmp);
    }

    if ($image) {
        // Auto-resize jika resolusi terlalu besar (hemat memori & kuota storage)
        $orig_w = imagesx($image);
        $orig_h = imagesy($image);
        if ($orig_w > $max_width) {
            $new_w = $max_width;
            $new_h = (int)($orig_h * ($new_w / $orig_w));
            $resized = imagecreatetruecolor($new_w, $new_h);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
            imagedestroy($image);
            $image = $resized;
        }

        if (imagewebp($image, $dest_path, 80)) {
            imagedestroy($image);

            // Upload ke Cloud Storage Supabase
            if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
                $clean_dir = trim(str_replace(['../uploads', 'uploads', './uploads'], '', $upload_dir), '/\\');
                $remote_path = !empty($clean_dir) ? ($clean_dir . '/' . $new_filename) : $new_filename;
                if (supabase_upload($dest_path, $remote_path, 'image/webp')) {
                    // Berhasil upload ke cloud: hapus berkas lokal agar harddisk server tidak penuh
                    @unlink($dest_path);
                }
            }

            return $new_filename;
        }
        imagedestroy($image);
    }
    
    return false;
}

// Helper untuk membuat URL Slug yang ramah SEO (Friendly URL)
// Helper untuk membuat URL Slug yang ramah SEO (Friendly URL)
function create_slug($string) {
    $slug = preg_replace('/[^a-zA-Z0-9\s-]/', '', strtolower($string));
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// CSRF Protection Helpers
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}
?>