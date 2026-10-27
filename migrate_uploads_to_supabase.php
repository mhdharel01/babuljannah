<?php
// migrate_uploads_to_supabase.php - Migrasi file lokal uploads/ ke Supabase Storage
header('Content-Type: text/plain; charset=utf-8');
@ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

echo "=== MEMULAI MIGRASI GAMBAR KE SUPABASE STORAGE ===\n\n";

if (file_exists(__DIR__ . '/config/storage.php')) {
    require_once __DIR__ . '/config/storage.php';
} else {
    // Fallback mandiri jika config/storage.php belum diunggah ke server
    if (!defined('SUPABASE_STORAGE_ENABLED')) define('SUPABASE_STORAGE_ENABLED', false);
    if (!defined('SUPABASE_PROJECT_URL')) define('SUPABASE_PROJECT_URL', '');
    if (!defined('SUPABASE_API_KEY')) define('SUPABASE_API_KEY', '');
    if (!defined('SUPABASE_BUCKET')) define('SUPABASE_BUCKET', 'babuljannah');

    function supabase_upload($local_file, $remote_path, $content_type = 'image/webp') {
        if (!file_exists($local_file)) return false;
        
        $remote_path = ltrim(str_replace('\\', '/', $remote_path), '/');
        $encoded_path = implode('/', array_map('rawurlencode', explode('/', $remote_path)));
        $url = rtrim(SUPABASE_PROJECT_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $encoded_path;
        $file_data = file_get_contents($local_file);
        if ($file_data === false) return false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: ' . $content_type,
            'x-upsert: true'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($http_code >= 200 && $http_code < 300);
    }
}

$uploads_root = __DIR__ . '/uploads';

if (!is_dir($uploads_root)) {
    die("Folder 'uploads' tidak ditemukan di direktori ini: $uploads_root\n");
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploads_root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$success_count = 0;
$fail_count = 0;

foreach ($iterator as $item) {
    if ($item->isFile()) {
        $filename = $item->getFilename();
        if ($filename === '.gitkeep') {
            continue;
        }

        $local_path = $item->getPathname();
        $relative_path = str_replace('\\', '/', substr($local_path, strlen($uploads_root) + 1));
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = 'image/webp';
        if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
        elseif ($ext === 'png') $mime = 'image/png';

        echo "Mengunggah: $relative_path ... ";
        flush();
        $ok = supabase_upload($local_path, $relative_path, $mime);
        if ($ok) {
            echo "BERHASIL (200)\n";
            $success_count++;
        } else {
            echo "GAGAL!\n";
            $fail_count++;
        }
        flush();
    }
}

echo "\n=== MIGRASI SELESAI ===\n";
echo "Total Berhasil: $success_count\n";
echo "Total Gagal   : $fail_count\n";
