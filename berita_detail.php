<?php
// berita_detail.php
require_once 'config/db.php';
// Ambil ID berita
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Akses ditolak: ID Berita tidak valid.");
}

// Query berita detail
$stmt = $db->prepare("SELECT * FROM berita WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$news = $stmt->fetch();

if (!$news) {
    require_once 'includes/header.php';
    echo "
    <section class='py-20 bg-accent text-center no-print flex-grow flex items-center justify-center'>
        <div class='max-w-md w-full bg-white p-8 rounded-2xl shadow-sm border border-gray-150 space-y-4'>
            <i class='fa-solid fa-circle-exclamation text-red-500 text-5xl'></i>
            <h2 class='text-xl font-bold text-primary'>Berita Tidak Ditemukan</h2>
            <p class='text-xs text-gray-500'>Artikel yang Anda cari tidak tersedia atau telah dihapus.</p>
            <a href='berita.php' class='inline-block bg-primary hover:bg-secondary text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow transition'>Kembali ke Daftar Berita</a>
        </div>
    </section>";
    require_once 'includes/footer.php';
    exit;
}

// Set SEO Meta Tags
$page_title = htmlspecialchars($news['judul']) . ' - Babul Jannah';
$meta_desc = htmlspecialchars(strip_tags($news['kutipan']));
$meta_image = get_storage_url('berita', $news['gambar']);

require_once 'includes/header.php';

$article_url = "https://ponpesbabuljannah.com/berita/" . $news['id'] . "-" . create_slug($news['judul']);
$article_date = date('c', strtotime($news['created_at']));
?>
<!-- Schema.org Article -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "<?= addslashes(htmlspecialchars($news['judul'])) ?>",
  "image": [
    "<?= get_storage_url('berita', $news['gambar']) ?>"
  ],
  "datePublished": "<?= $article_date ?>",
  "author": [{
      "@type": "Organization",
      "name": "Admin Babul Jannah",
      "url": "https://ponpesbabuljannah.com/"
    }]
}
</script>
<?php
// Ambil berita lainnya untuk rekomendasi (maksimal 3, kecuali yang sedang dibaca)
$stmt_others = $db->prepare("SELECT * FROM berita WHERE id != :id ORDER BY id DESC LIMIT 3");
$stmt_others->execute(['id' => $id]);
$other_news = $stmt_others->fetchAll();

// Ambil gelombang aktif untuk widget
$stmt_gel = $db->prepare("SELECT * FROM gelombang WHERE status = 'aktif' AND NOW() BETWEEN tgl_buka AND CONCAT(tgl_tutup, ' 23:59:59') LIMIT 1");
$stmt_gel->execute();
$gelombang_aktif = $stmt_gel->fetch();
?>

<!-- Breadcrumbs Bar -->
<div class="bg-cream pt-28 pb-3 border-b border-gray-200 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-gray-500 font-semibold space-x-2 flex items-center">
        <a href="index.php" class="hover:text-primary"><i class="fa-solid fa-house text-[10px]"></i> Beranda</a>
        <span>/</span>
        <a href="berita.php" class="hover:text-primary">Berita</a>
        <span>/</span>
        <span class="text-primary font-bold truncate max-w-[200px] md:max-w-md"><?= $news['judul'] ?></span>
    </div>
</div>

<style>
    .article-content h2 {
        font-size: 1.35rem;
        font-weight: 800;
        color: #5C3E35;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        border-bottom: 2px solid #F3EDE6;
        padding-bottom: 0.35rem;
    }
    .article-content h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2F2522;
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }
    .article-content p {
        margin-bottom: 1rem;
        line-height: 1.85;
        color: #374151;
    }
    .article-content blockquote {
        border-left: 4px solid #A17A6F;
        padding: 0.85rem 1.25rem;
        font-style: italic;
        color: #4A3029;
        background-color: #FDFBF7;
        border-radius: 0 0.85rem 0.85rem 0;
        margin: 1.25rem 0;
    }
    .article-content ul {
        list-style-type: disc;
        padding-left: 1.75rem;
        margin-bottom: 1rem;
    }
    .article-content ol {
        list-style-type: decimal;
        padding-left: 1.75rem;
        margin-bottom: 1rem;
    }
    .article-content li {
        margin-bottom: 0.35rem;
        color: #374151;
        line-height: 1.7;
    }
    .article-content a {
        color: #5C3E35;
        text-decoration: underline;
        font-weight: 600;
        transition: color 0.15s;
    }
    .article-content a:hover {
        color: #A17A6F;
    }
    .article-content hr {
        border: 0;
        border-top: 1px solid #E5E7EB;
        margin: 1.75rem 0;
    }
</style>

<!-- Main Section -->
<section class="py-12 bg-accent flex-grow no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left: Article Content -->
            <article class="lg:col-span-8 bg-white rounded-3xl shadow-sm border border-gray-150 p-6 md:p-8 space-y-6">
                <!-- Cover Image -->
                <div class="relative h-64 md:h-[400px] overflow-hidden rounded-2xl bg-gray-100 border border-gray-200">
                    <img src="<?= get_storage_url('berita', $news['gambar']) ?>" alt="<?= htmlspecialchars($news['judul']) ?>" 
                         onerror="this.onerror=null; this.src='assets/images/placeholder.webp';"
                         class="w-full h-full object-cover">
                </div>

                <!-- Meta Info -->
                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400 font-semibold border-b border-gray-100 pb-3">
                    <span><i class="fa-regular fa-calendar-days text-secondary mr-1.5"></i> <?= format_tanggal($news['created_at']) ?></span>
                    <span>•</span>
                    <span><i class="fa-regular fa-user text-secondary mr-1.5"></i> Admin PSB Babul Jannah</span>
                    <span>•</span>
                    <span><i class="fa-regular fa-clock text-secondary mr-1.5"></i> <?= max(1, (int)ceil(count(array_filter(preg_split('/\s+/', strip_tags($news['isi'])))) / 150)) ?> menit baca</span>
                </div>

                <!-- Title -->
                <h1 class="text-2xl md:text-3xl font-extrabold text-primary leading-tight">
                    <?= htmlspecialchars($news['judul']) ?>
                </h1>

                <!-- Body Text -->
                <div class="article-content text-xs md:text-sm text-gray-700 leading-relaxed">
                    <?php
                    $raw_content = $news['isi'];
                    if (strpos($raw_content, '<p>') === false && strpos($raw_content, '<div>') === false && strpos($raw_content, '<h') === false) {
                        $p_items = explode("\n\n", $raw_content);
                        foreach ($p_items as $pi) {
                            $pi = trim($pi);
                            if ($pi) {
                                echo '<p>' . nl2br($pi) . '</p>';
                            }
                        }
                    } else {
                        echo $raw_content;
                    }
                    ?>
                </div>

                <!-- Share Bar & Back button -->
                <div class="pt-6 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <a href="berita.php" class="inline-flex items-center text-xs font-bold text-secondary hover:text-primary transition-colors">
                        <i class="fa-solid fa-chevron-left mr-1.5 text-[9px]"></i>
                        <span>Kembali ke Daftar Berita</span>
                    </a>

                    <!-- Social Share -->
                    <div class="flex items-center space-x-2 text-xs">
                        <span class="text-gray-400 font-bold text-[11px] mr-1">Bagikan:</span>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($news['judul'] . ' - Baca selengkapnya di: ' . $article_url) ?>" target="_blank" class="w-8 h-8 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-600 flex items-center justify-center transition" title="Bagikan ke WhatsApp">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($article_url) ?>" target="_blank" class="w-8 h-8 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-600 flex items-center justify-center transition" title="Bagikan ke Facebook">
                            <i class="fa-brands fa-facebook-f text-xs"></i>
                        </a>
                        <button type="button" onclick="navigator.clipboard.writeText('<?= $article_url ?>'); alert('Tautan berita berhasil disalin ke papan klip!');" class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center transition" title="Salin Tautan">
                            <i class="fa-solid fa-link text-xs"></i>
                        </button>
                    </div>
                </div>
            </article>

            <!-- Right: Sidebar Widgets -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Widget 1: Berita Lainnya -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-150 p-6 space-y-4">
                    <h3 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2 flex items-center">
                        <i class="fa-solid fa-newspaper text-secondary mr-2"></i> Berita Terkini Lainnya
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if (count($other_news) === 0): ?>
                            <p class="text-xs text-gray-400">Tidak ada berita lain.</p>
                        <?php else: ?>
                            <?php foreach ($other_news as $other): ?>
                                <a href="berita/<?= $other['id'] ?>-<?= create_slug($other['judul']) ?>" class="flex space-x-3 items-center group pb-3 border-b border-gray-100 last:border-0 last:pb-0 block">
                                    <div class="w-16 h-16 rounded-xl bg-gray-100 flex-shrink-0 overflow-hidden border">
                                        <img src="<?= get_storage_url('berita', $other['gambar']) ?>" alt="<?= htmlspecialchars($other['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </div>
                                    <div class="space-y-1 overflow-hidden">
                                        <h4 class="text-[11px] font-extrabold text-primary leading-snug group-hover:text-secondary transition-colors duration-200 truncate">
                                            <?= $other['judul'] ?>
                                        </h4>
                                        <span class="text-[9px] text-gray-400 font-semibold block"><i class="fa-regular fa-calendar mr-1"></i> <?= date('d M Y', strtotime($other['created_at'])) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Widget 2: Gelombang PSB -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-150 p-6 space-y-4">
                    <h3 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2 flex items-center">
                        <i class="fa-solid fa-graduation-cap text-secondary mr-2"></i> Info PSB Online
                    </h3>
                    
                    <?php if ($gelombang_aktif): ?>
                        <div class="text-xs space-y-3">
                            <p class="leading-relaxed">Segera daftarkan putra/putri Anda pada gelombang penerimaan yang aktif.</p>
                            <div class="bg-cream p-3 rounded-xl border border-gray-200">
                                <span class="block text-[9px] text-gray-450 font-bold uppercase">Gelombang:</span>
                                <strong class="text-primary text-xs block mt-0.5"><?= $gelombang_aktif['nama_gelombang'] ?></strong>
                                <span class="block text-[9px] text-gray-450 font-bold uppercase mt-2">Periode:</span>
                                <span class="text-gray-650 block mt-0.5"><?= format_tanggal($gelombang_aktif['tgl_buka']) ?> - <?= format_tanggal($gelombang_aktif['tgl_tutup']) ?></span>
                            </div>
                            <a href="daftar.php" class="block w-full text-center bg-primary hover:bg-secondary text-white font-bold py-2.5 rounded-xl transition duration-300 shadow">Daftar Sekarang</a>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-center py-4 text-gray-500">
                            <i class="fa-solid fa-circle-exclamation text-amber-500 text-2xl mb-1.5 block"></i>
                            <span>Pendaftaran online saat ini sedang ditutup.</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
