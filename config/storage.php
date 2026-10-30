<?php
// config/storage.php - Cloud Storage (Supabase Storage) Configuration & Helpers

require_once __DIR__ . '/env.php';

if (!defined('SUPABASE_STORAGE_ENABLED')) {
    define('SUPABASE_STORAGE_ENABLED', env('SUPABASE_STORAGE_ENABLED', false));
}

if (!defined('SUPABASE_PROJECT_URL')) {
    define('SUPABASE_PROJECT_URL', env('SUPABASE_PROJECT_URL', ''));
}

if (!defined('SUPABASE_API_KEY')) {
    define('SUPABASE_API_KEY', env('SUPABASE_API_KEY', ''));
}

if (!defined('SUPABASE_BUCKET')) {
    define('SUPABASE_BUCKET', env('SUPABASE_BUCKET', 'babuljannah'));
}

if (!function_exists('supabase_upload')) {
    /**
     * Upload a local file to Supabase Storage
     * 
     * @param string $local_file Path to local file
     * @param string $remote_path Path inside bucket (e.g. 'berita/img_123.webp')
     * @param string $content_type MIME type
     * @return bool True on success, false on failure
     */
    function supabase_upload($local_file, $remote_path, $content_type = 'image/webp') {
        if (!SUPABASE_STORAGE_ENABLED || !file_exists($local_file)) {
            return false;
        }
        
        $remote_path = ltrim(str_replace('\\', '/', $remote_path), '/');
        $encoded_path = implode('/', array_map('rawurlencode', explode('/', $remote_path)));
        $url = rtrim(SUPABASE_PROJECT_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $encoded_path;
        $file_data = file_get_contents($local_file);
        if ($file_data === false) {
            return false;
        }

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

if (!function_exists('supabase_delete')) {
    /**
     * Delete a file from Supabase Storage
     * 
     * @param string $remote_path Path inside bucket (e.g. 'berita/img_123.webp')
     * @return bool True on success, false on failure
     */
    function supabase_delete($remote_path) {
        if (!SUPABASE_STORAGE_ENABLED || empty($remote_path)) {
            return false;
        }

        $remote_path = ltrim(str_replace('\\', '/', $remote_path), '/');
        $url = rtrim(SUPABASE_PROJECT_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET;
        $payload = json_encode(['prefixes' => [$remote_path]]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($http_code >= 200 && $http_code < 300);
    }
}

if (!function_exists('get_storage_url')) {
    /**
     * Universal Storage URL Resolver
     * 
     * Returns the public CDN URL if Supabase is enabled, or fallback to local uploads/
     * 
     * @param string $folder Subfolder e.g. 'berita', 'fasilitas', 'ekskul', 'prestasi', or ''
     * @param string $filename The image filename
     * @param string $default Fallback image path if filename is empty
     * @param bool $is_admin Set true if called from admin/ directory for local fallback
     * @return string Public image URL
     */
    function get_storage_url($folder, $filename, $default = '', $is_admin = false) {
        if (empty($filename)) {
            return $default;
        }

        // Already a full URL or assets/ path
        if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
            return $filename;
        }
        if (strpos($filename, 'assets/') === 0) {
            return ($is_admin ? '../' : '') . $filename;
        }

        // Clean paths & normalize folder
        $folder = trim(str_replace('\\', '/', (string)$folder), '/');
        $filename = ltrim(str_replace('\\', '/', (string)$filename), '/');

        // Strip redundant 'uploads/' or 'uploads' prefix if passed
        if ($folder === 'uploads') {
            $folder = '';
        } elseif (strpos($folder, 'uploads/') === 0) {
            $folder = substr($folder, 8);
        }
        if (strpos($filename, 'uploads/') === 0) {
            $filename = substr($filename, 8);
        }

        $clean_path = !empty($folder) ? ($folder . '/' . $filename) : $filename;

        if (defined('SUPABASE_STORAGE_ENABLED') && SUPABASE_STORAGE_ENABLED) {
            $encoded_path = implode('/', array_map('rawurlencode', explode('/', $clean_path)));
            return rtrim(SUPABASE_PROJECT_URL, '/') . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $encoded_path;
        }

        $prefix = $is_admin ? '../uploads/' : 'uploads/';
        return $prefix . $clean_path;
    }
}
