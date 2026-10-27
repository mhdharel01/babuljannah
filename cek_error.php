<?php
// cek_error.php - Diagnosa Lengkap Server Live
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tangkap Fatal Error seketika jika ada script yang crash
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style='background:#fee; border:2px solid red; padding:15px; margin:20px 0; font-family:sans-serif;'>";
        echo "<h2 style='color:red; margin-top:0;'>FATAL ERROR TERDETEKSI:</h2>";
        echo "<strong>Pesan:</strong> " . htmlspecialchars($err['message']) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($err['file']) . "<br>";
        echo "<strong>Baris:</strong> " . htmlspecialchars($err['line']) . "<br>";
        echo "</div>";
    }
});

echo "<div style='font-family:Segoe UI, sans-serif; max-width:900px; margin:20px auto; padding:20px; line-height:1.6;'>";
echo "<h1 style='border-bottom:2px solid #044434; color:#044434; padding-bottom:10px;'>DIAGNOSA SERVER LIVE BABUL JANNAH</h1>";

// 1. Cek storage.php
echo "<h3>1. File config/storage.php</h3>";
if (file_exists(__DIR__ . '/config/storage.php')) {
    echo "<p style='color:green;'>✓ File config/storage.php DITEMUKAN.</p>";
    require_once __DIR__ . '/config/storage.php';
} else {
    echo "<p style='color:orange;'>! config/storage.php belum ada di server hosting.</p>";
}

// 2. Cek Koneksi Database
echo "<h3>2. Koneksi Database (config/db.php)</h3>";
if (file_exists(__DIR__ . '/config/db.php')) {
    try {
        require_once __DIR__ . '/config/db.php';
        echo "<p style='color:green;'>✓ config/db.php BERHASIL DIMUAT dan Database Terhubung!</p>";

        // Cek Tabel-Tabel Kunci
        echo "<h4>Status Tabel Database:</h4><ul>";
        $tabel_cek = ['users', 'gelombang', 'pendaftaran', 'berita', 'fasilitas', 'prestasi', 'ekskul', 'pengaturan_sistem', 'master_biaya_pendidikan'];
        foreach ($tabel_cek as $tbl) {
            try {
                $q = $db->query("SELECT 1 FROM `$tbl` LIMIT 1");
                echo "<li style='color:green;'>Tabel <strong>$tbl</strong>: ADA ✓</li>";
            } catch (Throwable $te) {
                echo "<li style='color:red;'>Tabel <strong>$tbl</strong>: TIDAK DITEMUKAN ✗ (" . htmlspecialchars($te->getMessage()) . ")</li>";
            }
        }
        echo "</ul>";

    } catch (Throwable $e) {
        echo "<p style='color:red; font-weight:bold;'>ERROR DATABASE: " . htmlspecialchars($e->getMessage()) . "<br>Di file: " . htmlspecialchars($e->getFile()) . " baris " . $e->getLine() . "</p>";
    }
} else {
    echo "<p style='color:red;'>File config/db.php TIDAK DITEMUKAN!</p>";
}

// 3. Cek Uji Coba Muat includes/header.php
echo "<h3>3. Cek includes/header.php</h3>";
if (file_exists(__DIR__ . '/includes/header.php')) {
    echo "<p style='color:green;'>✓ includes/header.php DITEMUKAN.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>✗ ERROR FATAL: Folder includes/ atau file header.php TIDAK DITEMUKAN di server hosting!</p>";
}

// 4. Cek Error Log Server
echo "<h3>4. File error_log Server (Terakhir 30 Baris)</h3>";
if (file_exists(__DIR__ . '/error_log')) {
    $lines = file(__DIR__ . '/error_log');
    $last_lines = array_slice($lines, -30);
    echo "<pre style='background:#f4f4f4; padding:12px; border:1px solid #ccc; max-height:300px; overflow:auto; font-size:12px;'>";
    echo htmlspecialchars(implode("", $last_lines));
    echo "</pre>";
} else {
    echo "<p style='color:gray;'>File error_log belum dibuat di root (tidak ada catatan error Apache/LiteSpeed).</p>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p style='color:#044434; font-weight:bold;'>Hasil diagnosa selesai ditampilkan.</p>";
echo "</div>";
