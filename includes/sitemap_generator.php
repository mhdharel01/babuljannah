<?php
// includes/sitemap_generator.php

function generate_sitemap($db, $domain = 'https://ponpesbabuljannah.com/') {
    // Pastikan domain diakhiri dengan slash
    $domain = rtrim($domain, '/') . '/';
    
    // Header XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    
    // Halaman Statis Utama
    $static_pages = [
        '', // index.php
        'profil.php',
        'berita.php',
        'daftar.php',
        'status.php',
        'formulir.php'
    ];
    
    $today = date('Y-m-d');
    
    foreach ($static_pages as $page) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($domain . $page) . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $today . '</lastmod>' . PHP_EOL;
        $xml .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
        $xml .= '    <priority>' . ($page === '' ? '1.0' : '0.8') . '</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }
    
    // Halaman Dinamis (Berita)
    try {
        $stmt = $db->query("SELECT id, judul, created_at FROM berita ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Gunakan slug friendly URL
            $slug = create_slug($row['judul']);
            $url = $domain . 'berita/' . $row['id'] . '-' . $slug;
            
            // Format tanggal ISO 8601
            $date = date('Y-m-d', strtotime($row['created_at']));
            
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $date . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>monthly</changefreq>' . PHP_EOL;
            $xml .= '    <priority>0.7</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }
    } catch (Exception $e) {
        // Abaikan error DB untuk sementara
    }
    
    $xml .= '</urlset>';
    
    // Tulis ke root sitemap.xml
    $sitemap_path = __DIR__ . '/../sitemap.xml';
    file_put_contents($sitemap_path, $xml);
}
?>
