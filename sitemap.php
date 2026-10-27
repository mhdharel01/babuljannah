<?php
// sitemap.php
require_once 'config/db.php';

$base_url = "https://ponpesbabuljannah.com";

header("Content-Type: application/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Halaman-halaman statis utama
$static_pages = [
    '/',
    '/profil.php',
    '/sma.php',
    '/smp.php',
    '/takhasus.php',
    '/berita.php',
    '/daftar.php',
    '/status.php'
];

foreach ($static_pages as $page) {
    echo "<url>";
    echo "<loc>" . $base_url . $page . "</loc>";
    echo "<changefreq>weekly</changefreq>";
    echo "<priority>" . ($page === '/' ? '1.0' : '0.8') . "</priority>";
    echo "</url>";
}

// Halaman Berita Dinamis
$stmt = $db->query("SELECT id, judul, created_at FROM berita ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    $slug = create_slug($row['judul']);
    $url = $base_url . "/berita/" . $row['id'] . "-" . $slug;
    $date = date('Y-m-d', strtotime($row['created_at']));
    
    echo "<url>";
    echo "<loc>" . $url . "</loc>";
    echo "<lastmod>" . $date . "</lastmod>";
    echo "<changefreq>monthly</changefreq>";
    echo "<priority>0.6</priority>";
    echo "</url>";
}

echo '</urlset>';
?>
