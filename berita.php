<?php
// berita.php
require_once 'config/db.php';

$page_title = 'Berita & Informasi - Pondok Pesantren Babul Jannah';
$meta_desc = 'Kumpulan berita, pengumuman, dan informasi terbaru dari Pondok Pesantren Babul Jannah Dumai.';

require_once 'includes/header.php';

// Ambil parameter pencarian
$search = sanitize($_GET['search'] ?? '');

// Pengaturan Pagination
$limit = 6; // Jumlah berita per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Query total record untuk menghitung halaman
$count_sql = "SELECT COUNT(*) FROM berita WHERE 1=1";
$count_params = [];
if (!empty($search)) {
    $count_sql .= " AND (judul LIKE :search OR kutipan LIKE :search OR isi LIKE :search)";
    $count_params['search'] = '%' . $search . '%';
}
$stmt_count = $db->prepare($count_sql);
$stmt_count->execute($count_params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $limit;

// Query berita per halaman
$query_sql = "SELECT * FROM berita WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query_sql .= " AND (judul LIKE :search OR kutipan LIKE :search OR isi LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
$query_sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$berita_list = $stmt->fetchAll();

// Ambil gelombang aktif untuk widget samping
$stmt_gel = $db->prepare("SELECT * FROM gelombang WHERE status = 'aktif' AND NOW() BETWEEN tgl_buka AND CONCAT(tgl_tutup, ' 23:59:59') LIMIT 1");
$stmt_gel->execute();
$gelombang_aktif = $stmt_gel->fetch();
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print" data-aos="fade-down">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/IMG-20240801-WA0004.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Berita & Kegiatan</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Ikuti perkembangan informasi terbaru, agenda kegiatan, pengumuman, serta artikel menarik seputar Pondok Pesantren Babul Jannah.
        </p>
    </div>
</section>

<!-- Content Grid -->
<section class="py-12 bg-accent flex-grow no-print" data-aos="fade-up">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Side: News List -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Search Bar -->
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-150">
                    <form action="berita.php" method="GET" class="flex items-center space-x-2">
                        <div class="relative flex-grow">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fa-solid fa-magnifying-glass text-sm"></i>
                            </span>
                            <input type="text" name="search" value="<?= $search ?>" placeholder="Cari artikel berita atau kegiatan..." class="w-full border border-gray-350 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl pl-10 pr-4 py-2.5 text-xs focus:outline-none transition">
                        </div>
                        <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold px-5 py-2.5 rounded-xl text-xs shadow transition">Cari</button>
                    </form>
                </div>

                <!-- Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (count($berita_list) === 0): ?>
                        <div class="col-span-2 bg-white rounded-2xl p-12 text-center text-gray-400 border border-gray-150 text-sm">
                            <i class="fa-solid fa-newspaper text-4xl text-gray-300 mb-2 block"></i>
                            <span>Tidak ditemukan berita yang cocok dengan kata kunci pencarian Anda.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($berita_list as $news): ?>
                            <article class="bg-white rounded-3xl shadow-sm border border-gray-150 overflow-hidden transform hover:-translate-y-1 hover:shadow-md transition-all duration-300 flex flex-col justify-between h-full group">
                                <div>
                                    <!-- News Image -->
                                    <div class="relative h-56 overflow-hidden bg-gray-100">
                                        <img src="<?= get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $news['gambar'])) ?>" alt="<?= htmlspecialchars($news['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        <div class="absolute top-4 left-4 bg-primary text-accent text-xs font-bold tracking-wider px-3 py-1.5 rounded-xl uppercase shadow">
                                            <?= date('d M Y', strtotime($news['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="p-5 space-y-2">
                                        <h3 class="font-extrabold text-primary text-sm leading-snug group-hover:text-secondary transition-colors duration-200">
                                            <a href="berita/<?= $news['id'] ?>-<?= create_slug($news['judul']) ?>"><?= $news['judul'] ?></a>
                                        </h3>
                                        <p class="text-[11px] text-gray-500 leading-relaxed line-clamp-3">
                                            <?= $news['kutipan'] ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="p-5 pt-0">
                                    <a href="berita/<?= $news['id'] ?>-<?= create_slug($news['judul']) ?>" class="inline-flex items-center text-xs font-bold text-secondary hover:text-primary transition-colors group-hover:underline">
                                        <span>Baca Selengkapnya</span>
                                        <i class="fa-solid fa-arrow-right ml-1 text-[9px]"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center items-center space-x-2 pt-8">
                        <!-- Prev Button -->
                        <?php if ($page > 1): ?>
                            <a href="berita.php?page=<?= $page - 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="bg-white hover:bg-cream text-primary border border-gray-200 w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold transition shadow-sm hover:shadow">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="berita.php?page=<?= $i ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold transition shadow-sm border <?= $i === $page ? 'bg-primary border-primary text-white shadow-md' : 'bg-white hover:bg-cream text-primary border-gray-200 hover:shadow' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="berita.php?page=<?= $page + 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="bg-white hover:bg-cream text-primary border border-gray-200 w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold transition shadow-sm hover:shadow">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: Sidebar Widgets -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Widget 1: Info Gelombang PSB -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-150 p-6 space-y-4">
                    <h3 class="font-extrabold text-primary text-sm border-b border-gray-100 pb-2 flex items-center">
                        <i class="fa-solid fa-graduation-cap text-secondary mr-2"></i> Info Pendaftaran (PSB)
                    </h3>
                    
                    <?php if ($gelombang_aktif): ?>
                        <div class="text-xs space-y-3">
                            <p class="leading-relaxed">Pendaftaran santri baru tahun ajaran <strong><?= $gelombang_aktif['tahun_ajaran'] ?></strong> telah dibuka.</p>
                            <div class="bg-cream p-3.5 rounded-xl border border-gray-200">
                                <span class="block text-[9px] text-gray-400 font-bold uppercase">Gelombang:</span>
                                <strong class="text-primary text-xs block mt-0.5"><?= $gelombang_aktif['nama_gelombang'] ?></strong>
                                <span class="block text-[9px] text-gray-400 font-bold uppercase mt-2">Periode:</span>
                                <span class="text-gray-600 block mt-0.5"><?= format_tanggal($gelombang_aktif['tgl_buka']) ?> - <?= format_tanggal($gelombang_aktif['tgl_tutup']) ?></span>
                            </div>
                            <a href="daftar.php" class="block w-full text-center bg-secondary hover:bg-primary text-white font-bold py-2.5 rounded-xl transition duration-300 shadow">Daftar Sekarang</a>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-center py-4 text-gray-500">
                            <i class="fa-solid fa-circle-exclamation text-amber-500 text-2xl mb-1.5 block"></i>
                            <span>Pendaftaran online saat ini sedang ditutup.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Widget 2: Kontak Panitia -->
                <div class="bg-primary text-accent rounded-3xl p-6 space-y-4 border border-secondary shadow-md">
                    <h3 class="font-extrabold text-sm border-b border-white border-opacity-15 pb-2 flex items-center text-white">
                        <i class="fa-solid fa-headset text-secondary mr-2"></i> Layanan Informasi
                    </h3>
                    <p class="text-[11px] text-gray-300 leading-relaxed">
                        Butuh bantuan mengenai tata cara pendaftaran, masalah transfer, atau syarat kelulusan? Silakan hubungi langsung pusat informasi kami:
                    </p>
                    <a href="https://wa.me/6281170789988" target="_blank" class="inline-flex items-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl shadow w-full justify-center text-xs transition">
                        <i class="fa-brands fa-whatsapp text-md"></i>
                        <span>WhatsApp Panitia</span>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
