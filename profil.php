<?php
// profil.php
require_once 'config/db.php';
$page_title = 'Profil Pondok Pesantren Babul Jannah';
$meta_desc = 'Mengenal lebih dekat profil Pondok Pesantren Babul Jannah Dumai. Visi misi, sejarah, fasilitas, ekstrakurikuler, dan keunggulan kami.';



// Ambil data fasilitas dari database
try {
    $stmt_f = $db->query("SELECT * FROM fasilitas ORDER BY urutan ASC, id DESC");
    $fasilitas_list = $stmt_f->fetchAll();
} catch (PDOException $e) {
    $fasilitas_list = [];
}

// Ambil data prestasi
try {
    $stmt_p = $db->query("SELECT * FROM prestasi ORDER BY id DESC");
    $prestasi_list = $stmt_p->fetchAll();
} catch (PDOException $e) {
    $prestasi_list = [];
}

// Ambil data ekskul
try {
    $stmt_e = $db->query("SELECT * FROM ekskul ORDER BY urutan ASC, id DESC");
    $ekskul_list = $stmt_e->fetchAll();
} catch (PDOException $e) {
    $ekskul_list = [];
}

require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Profil & Visi Misi</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Mengenal lebih dekat asas perjuangan, cita-cita, dan pembinaan karakter terpadu di Pondok Pesantren Babul Jannah.
        </p>
    </div>
</section>

<!-- Visi Misi Section (Editorial Magazine Layout) -->
<section class="py-24 bg-accent relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
            <!-- Visi Column (Left 5 Columns on Desktop) -->
            <div class="lg:col-span-5 flex flex-col justify-between bg-primary rounded-3xl overflow-hidden relative shadow-xl min-h-[380px] p-8 text-white group">
                <!-- Cover Image Background -->
                <div class="absolute inset-0 bg-cover bg-center opacity-25 group-hover:scale-105 transition-transform duration-700" style="background-image: url('assets/images/mushola_2.webp');"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-primary via-primary/80 to-transparent"></div>
                
                <div class="relative z-10 space-y-4">
                    <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Cita-Cita Luhur</span>
                    <h2 class="text-2xl font-extrabold font-sans">Visi Utama Kami</h2>
                    <div class="w-10 h-1 bg-secondary rounded"></div>
                </div>

                <div class="relative z-10 bg-white/95 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-white/20 text-primary mt-6">
                    <span class="text-secondary text-3xl font-serif leading-none block h-4">“</span>
                    <p class="text-xs md:text-sm text-primary leading-relaxed font-serif italic text-center font-normal px-2">
                        Menjadi lembaga pendidikan Islam terkemuka yang melahirkan generasi Rabbani, hafal Al-Qur'an, berilmu luas, berkepribadian mulia, mandiri, dan bermanfaat bagi kejayaan umat Islam.
                    </p>
                    <span class="text-secondary text-3xl font-serif leading-none block h-4 text-right">”</span>
                </div>
            </div>

            <!-- Misi Column (Right 7 Columns on Desktop) -->
            <div class="lg:col-span-7 flex flex-col justify-center space-y-6">
                <div class="space-y-2">
                    <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Langkah Strategis</span>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-primary font-sans">Misi Pondok Pesantren</h2>
                    <div class="w-12 h-1 bg-secondary rounded"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Misi Item 1 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-start space-x-4 hover:shadow-md transition duration-300">
                        <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center font-extrabold text-xs flex-shrink-0">1</div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-xs">Pendidikan Tahfizh</h4>
                            <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Menyelenggarakan pendidikan tahfizh Al-Qur'an secara intensif dengan target mutqin dan ber-sanad.</p>
                        </div>
                    </div>

                    <!-- Misi Item 2 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-start space-x-4 hover:shadow-md transition duration-300">
                        <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center font-extrabold text-xs flex-shrink-0">2</div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-xs">Integrasi Kurikulum</h4>
                            <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Mengintegrasikan kurikulum pendidikan nasional (IPA & IPS) dengan kurikulum kepondokan salafiyah.</p>
                        </div>
                    </div>

                    <!-- Misi Item 3 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-start space-x-4 hover:shadow-md transition duration-300">
                        <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center font-extrabold text-xs flex-shrink-0">3</div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-xs">Penguasaan Bahasa</h4>
                            <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Membiasakan penggunaan bahasa Arab dan Inggris secara aktif dalam pergaulan harian santri.</p>
                        </div>
                    </div>

                    <!-- Misi Item 4 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-start space-x-4 hover:shadow-md transition duration-300">
                        <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center font-extrabold text-xs flex-shrink-0">4</div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-xs">Kemandirian & Adab</h4>
                            <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Membina kepemimpinan (leadership), kemandirian, kedisiplinan, dan adab islami sejak dini.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Sambutan Pimpinan (Editorial Quote) -->
<section class="py-20 bg-white border-t border-gray-150">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-[#FBF9F6] rounded-3xl p-8 md:p-12 border border-secondary/20 shadow-sm relative overflow-hidden">
            <i class="fa-solid fa-quote-right absolute top-4 right-8 text-8xl text-secondary/10"></i>
            <div class="relative z-10 space-y-6 text-center">
                <h2 class="text-2xl font-extrabold text-primary font-sans border-b-2 border-secondary/30 pb-4 inline-block">Sambutan Pimpinan Yayasan</h2>
                
                <div class="text-sm md:text-base text-gray-700 leading-relaxed font-serif space-y-4 text-justify">
                    <p class="font-bold text-primary text-center italic">"Assalamu’alaikum Warahmatullahi Wabarakatuh."</p>
                    <p>
                        Bismillah walhamdulillah. Selamat datang di portal resmi Pondok Pesantren Babul Jannah Kota Dumai. Berdirinya Pondok Pesantren Babul Jannah berawal dari niat suci dan ikhtiar bersama masyarakat untuk menghadirkan lembaga pendidikan Islam yang mampu membina generasi muda secara berkelanjutan. Nama <strong class="text-primary">Babul Jannah</strong> berarti <em class="text-secondary">"Pintu Surga"</em>. Kami meyakini bahwa kunci untuk membuka pintu kebaikan tersebut adalah melalui pendidikan ilmu agama, Al-Qur'an, dan adab yang ditanamkan sejak dini.
                    </p>
                    <p>
                        Yayasan dan seluruh jajaran Asatidz/Asatidzah berkomitmen penuh untuk memberikan pendidikan terbaik, membina akhlak, dan menjaga ananda sekalian layaknya anak kami sendiri di lingkungan yang aman, tenteram, dan ramah anak.
                    </p>
                    <p class="font-bold text-primary text-center italic">"Wassalamu’alaikum Warahmatullahi Wabarakatuh."</p>
                </div>
                
                <div class="pt-6 border-t border-gray-200 mt-6 flex flex-col items-center">
                    <div class="w-16 h-16 rounded-full bg-gray-200 overflow-hidden mb-3 border-2 border-secondary/50">
                        <img src="assets/images/pimpinan.webp" alt="H. Sutriono" class="w-full h-full object-cover">
                    </div>
                    <h4 class="font-extrabold text-primary text-sm">H. Sutriono</h4>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Pendiri & Pimpinan Yayasan</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Sejarah Pendirian & Garis Waktu Section (NEW) -->
<section id="sejarah" class="py-20 bg-accent border-t border-gray-150 scroll-mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Jejak Langkah</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Sejarah Perjalanan Pesantren</h2>
            <p class="text-sm text-gray-650">
                Menilik tonggak sejarah perkembangan fisik dan prestasi akademis Pondok Pesantren Babul Jannah dari tahun ke tahun.
            </p>
        </div>



        <!-- Vertical Timeline Layout -->
        <div class="relative max-w-4xl mx-auto">
            <!-- Central Line -->
            <div class="absolute left-4 md:left-1/2 top-0 bottom-0 w-0.5 bg-secondary/20 -translate-x-1/2"></div>

            <div class="space-y-12">
                <!-- Timeline Item 1 -->
                <div class="relative flex flex-col md:flex-row items-start md:justify-between group">
                    <div class="absolute left-4 md:left-1/2 w-4 h-4 bg-primary rounded-full -translate-x-1/2 border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform"></div>
                    <div class="pl-12 md:pl-0 md:w-[45%] text-left md:text-right space-y-2">
                        <span class="inline-block bg-secondary text-white font-extrabold text-[10px] px-3 py-1 rounded-lg">TAHUN 2020</span>
                        <h4 class="font-extrabold text-primary text-sm">Awal Mula Perintisan</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-normal">
                            Perjalanan dimulai dari aktivitas dakwah dan pembinaan umat di Masjid Raudhatus Sholihin. Melihat kebutuhan masyarakat Kota Dumai akan wadah pembinaan generasi muda yang berkesinambungan, Bapak H. Sutriono bersama para tokoh agama merintis pendirian pesantren.
                        </p>
                    </div>
                    <div class="hidden md:block w-[45%]"></div>
                </div>

                <!-- Timeline Item 2 -->
                <div class="relative flex flex-col md:flex-row items-start md:justify-between group">
                    <div class="absolute left-4 md:left-1/2 w-4 h-4 bg-primary rounded-full -translate-x-1/2 border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform"></div>
                    <div class="hidden md:block w-[45%]"></div>
                    <div class="pl-12 md:pl-0 md:w-[45%] text-left space-y-2">
                        <span class="inline-block bg-secondary text-white font-extrabold text-[10px] px-3 py-1 rounded-lg">14 JUNI 2020</span>
                        <h4 class="font-extrabold text-primary text-sm">Peletakan Batu Pertama</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-normal">
                            Peletakan batu pertama pembangunan secara resmi dilaksanakan bersama Kiyai Lutfi Basori. Berawal dari program khusus Tahfidz, pesantren terus berkembang pesat dan mulai menerima santri angkatan pertama.
                        </p>
                    </div>
                </div>

                <!-- Timeline Item 3 -->
                <div class="relative flex flex-col md:flex-row items-start md:justify-between group">
                    <div class="absolute left-4 md:left-1/2 w-4 h-4 bg-primary rounded-full -translate-x-1/2 border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform"></div>
                    <div class="pl-12 md:pl-0 md:w-[45%] text-left md:text-right space-y-2">
                        <span class="inline-block bg-secondary text-white font-extrabold text-[10px] px-3 py-1 rounded-lg">TAHUN 2023</span>
                        <h4 class="font-extrabold text-primary text-sm">Pendirian SMP IT & Asrama</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-normal">
                            Pembangunan gedung asrama permanen putra & putri beroperasi penuh. Pesantren secara resmi meluncurkan satuan pendidikan formal SMP IT Babul Jannah di bawah izin resmi kementerian terkait.
                        </p>
                    </div>
                    <div class="hidden md:block w-[45%]"></div>
                </div>

                <!-- Timeline Item 4 -->
                <div class="relative flex flex-col md:flex-row items-start md:justify-between group">
                    <div class="absolute left-4 md:left-1/2 w-4 h-4 bg-primary rounded-full -translate-x-1/2 border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform"></div>
                    <div class="hidden md:block w-[45%]"></div>
                    <div class="pl-12 md:pl-0 md:w-[45%] text-left space-y-2">
                        <span class="inline-block bg-secondary text-white font-extrabold text-[10px] px-3 py-1 rounded-lg">TAHUN 2024</span>
                        <h4 class="font-extrabold text-primary text-sm">SMA IT & Transformasi Digital</h4>
                        <p class="text-xs text-gray-600 leading-relaxed font-normal">
                            Meluncurkan jenjang SMA IT Babul Jannah. Pembangunan laboratorium terpadu (IPA, bahasa, komputer), implementasi sistem PSB online responsif, serta bersiap untuk pencapaian akreditasi terbaik.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Keunggulan Section -->
<section id="keunggulan" class="py-20 bg-white border-t border-gray-150 scroll-mt-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-4">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Nilai Lebih Kami</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Alasan Memilih Kami</h2>
            <p class="text-sm text-gray-650 max-w-2xl mx-auto">
                Inilah berbagai keunggulan mengapa Pondok Pesantren Babul Jannah menjadi pilihan tepat untuk membina putra-putri Anda.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Point 1 -->
            <div class="bg-[#FBF9F6] p-6 rounded-3xl border border-gray-150 flex flex-col items-center text-center space-y-4 shadow-sm hover:shadow-md transition">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center text-3xl"><i class="fa-solid fa-leaf"></i></div>
                <h4 class="font-extrabold text-primary text-sm">Lingkungan Asri & Ramah Anak</h4>
                <p class="text-xs text-gray-500 leading-relaxed">Kawasan pesantren yang asri, hijau, dengan sistem keamanan 24 jam untuk menciptakan zona bebas perundungan (bullying).</p>
            </div>
            <!-- Point 2 -->
            <div class="bg-[#FBF9F6] p-6 rounded-3xl border border-gray-150 flex flex-col items-center text-center space-y-4 shadow-sm hover:shadow-md transition">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center text-3xl"><i class="fa-solid fa-chalkboard-user"></i></div>
                <h4 class="font-extrabold text-primary text-sm">Pendidik Berpengalaman</h4>
                <p class="text-xs text-gray-500 leading-relaxed">Diasuh oleh ustadz/ah lulusan perguruan tinggi Timur Tengah & universitas dalam negeri yang ahli di bidangnya.</p>
            </div>
            <!-- Point 3 -->
            <div class="bg-[#FBF9F6] p-6 rounded-3xl border border-gray-150 flex flex-col items-center text-center space-y-4 shadow-sm hover:shadow-md transition">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center text-3xl"><i class="fa-solid fa-graduation-cap"></i></div>
                <h4 class="font-extrabold text-primary text-sm">Lulusan ke PTN Favorit</h4>
                <p class="text-xs text-gray-500 leading-relaxed">Kurikulum integrasi yang solid mempersiapkan santri masuk ke Perguruan Tinggi Negeri dan kampus luar negeri ternama.</p>
            </div>
        </div>
    </div>
</section>

<!-- Galeri Prestasi Section -->
<section id="prestasi" class="py-20 bg-accent border-t border-gray-150 scroll-mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Pencapaian Santri</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary flex items-center justify-center">
                <i class="fa-solid fa-trophy text-secondary mr-3 text-3xl"></i> Galeri Prestasi
            </h2>
            <p class="text-sm text-gray-650">
                Deretan pencapaian membanggakan yang diraih oleh para santri Babul Jannah di berbagai bidang kejuaraan.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <?php if (empty($prestasi_list)): ?>
                <div class="col-span-full text-center py-10 text-gray-400 text-sm">
                    Belum ada data prestasi yang diunggah.
                </div>
            <?php else: ?>
                <?php foreach ($prestasi_list as $p): ?>
                <?php $p_img = !empty($p['gambar']) ? get_storage_url('prestasi', $p['gambar'], 'assets/images/smp.webp') : 'assets/images/smp.webp'; ?>
                <div class="group relative overflow-hidden rounded-3xl border border-gray-200 shadow-sm hover:shadow-xl transition-all duration-300">
                    <div class="h-56 bg-gray-200 relative">
                        <img src="<?= $p_img ?>" alt="<?= htmlspecialchars($p['nama'] ?? 'Prestasi') ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700" onerror="this.src='assets/images/smp.webp'">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary/95 via-primary/40 to-transparent"></div>
                        <div class="absolute bottom-5 left-5 right-5">
                            <span class="bg-secondary text-white text-[10px] font-extrabold px-3 py-1 rounded-lg uppercase tracking-wider shadow-sm"><?= htmlspecialchars($p['kategori']) ?></span>
                            <h5 class="text-white font-bold text-sm mt-2.5 leading-snug"><?= htmlspecialchars($p['nama']) ?></h5>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<!-- Jenjang Pendidikan Hub Section -->
<section id="program" class="py-20 bg-white border-t border-gray-150">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Pilihan Program Studi</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Jenjang Pendidikan Terpadu</h2>
            <p class="text-sm text-gray-650">
                Pilih jenjang pendidikan di bawah ini untuk melihat rincian kurikulum akademis, target hafalan Al-Qur'an, kehidupan asrama, serta fasilitas penunjang khusus secara lengkap.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Card 1: SMP IT Babul Jannah -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group">
                <div>
                    <div class="h-52 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/smp.webp" alt="SMP IT Babul Jannah" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary/60 to-transparent"></div>
                        <span class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">SMP IT</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <h3 class="text-lg font-extrabold text-primary group-hover:text-secondary transition-colors">SMP IT Babul Jannah</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-normal">
                            Pendidikan terpadu asrama yang mensinergikan kurikulum dinas dengan pelajaran kepondokan salafiyah, didesain khusus untuk lulusan SD/MI.
                        </p>
                        <div class="pt-3 border-t border-gray-100 text-[11px] text-gray-600 space-y-1">
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Target Tahfizh Al-Qur'an: 3 Juz</div>
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Pembiasaan Percakapan Bahasa Arab</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="smp.php" class="block w-full text-center bg-primary hover:bg-secondary text-white text-xs font-bold py-3 rounded-xl transition duration-300 shadow-md">
                        Lihat Kurikulum & Detail <i class="fa-solid fa-chevron-right ml-1 text-[9px]"></i>
                    </a>
                </div>
            </div>

            <!-- Card 2: SMA IT Babul Jannah -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group">
                <div>
                    <div class="h-52 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/IMG-20241023-WA0081.webp" alt="SMA IT Babul Jannah" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary/60 to-transparent"></div>
                        <span class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">SMA IT</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <h3 class="text-lg font-extrabold text-primary group-hover:text-secondary transition-colors">SMA IT Babul Jannah</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-normal">
                            Program lanjutan persiapan perguruan tinggi negeri/swasta (IPA/IPS) dengan muatan kajian kitab kuning (Nahwu Al-Fiyah, Fiqih Fathul Qorib).
                        </p>
                        <div class="pt-3 border-t border-gray-100 text-[11px] text-gray-600 space-y-1">
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Target Tahfizh Al-Qur'an: 6 Juz</div>
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Kelas Persiapan Bimbingan UTBK</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="sma.php" class="block w-full text-center bg-primary hover:bg-secondary text-white text-xs font-bold py-3 rounded-xl transition duration-300 shadow-md">
                        Lihat Kurikulum & Detail <i class="fa-solid fa-chevron-right ml-1 text-[9px]"></i>
                    </a>
                </div>
            </div>

            <!-- Card 3: Program Takhasus Tahfizh -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group">
                <div>
                    <div class="h-52 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/IMG-20250504-WA0073.webp" alt="Program Takhasus Tahfizh" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary/60 to-transparent"></div>
                        <span class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">Takhasus</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <h3 class="text-lg font-extrabold text-primary group-hover:text-secondary transition-colors">Program Takhasus Tahfizh</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-normal">
                            Program akselerasi intensif bagi santri yang ingin fokus menghafal Al-Qur'an 30 Juz secara mutqin dan mengambil sanad qiraat bersambung.
                        </p>
                        <div class="pt-3 border-t border-gray-100 text-[11px] text-gray-600 space-y-1">
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Target Tahfizh Al-Qur'an: 30 Juz Mutqin</div>
                            <div class="flex items-center"><i class="fa-solid fa-check text-secondary mr-2"></i> Pengambilan Sanad Bersambung</div>
                        </div>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="takhasus.php" class="block w-full text-center bg-primary hover:bg-secondary text-white text-xs font-bold py-3 rounded-xl transition duration-300 shadow-md">
                        Lihat Kurikulum & Detail <i class="fa-solid fa-chevron-right ml-1 text-[9px]"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Dewan Pengurus & Asatidzah Utama Section (NEW) -->
<section id="pengurus" class="py-20 bg-[#FBF9F6] border-t border-gray-150 scroll-mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Pendidik Utama</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Struktur Pengurus & Dewan Asatidzah</h2>
            <p class="text-sm text-gray-650">
                Mengenal lebih dekat jajaran pengurus utama dan asatidzah yang membimbing langsung perkembangan santri sehari-hari.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Pengurus 1 -->
            <div class="bg-white rounded-3xl border border-gray-200/60 p-6 text-center space-y-4 shadow-sm hover:shadow-md transition duration-300">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-white ring-4 ring-secondary/10 shadow-sm">
                    <img src="assets/images/pimpinan-smp.webp" alt="AL MUHRIGA H, S.Pd" class="w-full h-full object-cover">
                </div>
                <div class="space-y-1">
                    <h4 class="font-extrabold text-primary text-sm">AL MUHRIGA H, S.Pd</h4>
                    <span class="text-[9px] text-secondary font-bold uppercase tracking-wider block">Pimpinan Pondok Pesantren</span>
                </div>
                <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                    Membimbing dan mengarahkan seluruh kegiatan serta pengembangan program pendidikan terpadu di Pondok Pesantren Babul Jannah.
                </p>
            </div>

            <!-- Pengurus 2 -->
            <div class="bg-white rounded-3xl border border-gray-200/60 p-6 text-center space-y-4 shadow-sm hover:shadow-md transition duration-300">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-white ring-4 ring-secondary/10 shadow-sm">
                    <img src="assets/images/pimpinan-smp.webp" alt="AL MUHRIGA H, S.Pd" class="w-full h-full object-cover">
                </div>
                <div class="space-y-1">
                    <h4 class="font-extrabold text-primary text-sm">AL MUHRIGA H, S.Pd</h4>
                    <span class="text-[9px] text-secondary font-bold uppercase tracking-wider block">Kepala Sekolah SMP IT</span>
                </div>
                <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                    Berkomitmen dalam mengelola pendidikan formal SMP IT, memastikan standar mutu kurikulum berjalan optimal dengan pembinaan karakter dan kedisiplinan.
                </p>
            </div>

            <!-- Pengurus 3 -->
            <div class="bg-white rounded-3xl border border-gray-200/60 p-6 text-center space-y-4 shadow-sm hover:shadow-md transition duration-300">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-white ring-4 ring-secondary/10 shadow-sm">
                    <img src="assets/images/kepala-sma.webp" alt="DWI NUGROHO PUTRI, S.Pd., Gr" class="w-full h-full object-cover">
                </div>
                <div class="space-y-1">
                    <h4 class="font-extrabold text-primary text-sm">DWI NUGROHO PUTRI, S.Pd., Gr</h4>
                    <span class="text-[9px] text-secondary font-bold uppercase tracking-wider block">Kepala Sekolah SMA IT</span>
                </div>
                <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                    Bertanggung jawab penuh atas pengelolaan pendidikan formal tingkat SMA IT, memantau kualitas pengajaran akademik dan integrasi nilai-nilai keislaman.
                </p>
            </div>
        </div>

    </div>
</section>

<!-- Galeri Fasilitas & Ruangan Section (NEW - Interactive Filtered Gallery) -->
<section id="fasilitas" class="py-24 bg-white border-t border-gray-200 scroll-mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Sarana & Prasarana</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Ruangan & Fisik Bangunan Pesantren</h2>
            <p class="text-xs text-gray-500">
                Pondok Pesantren Babul Jannah menyediakan infrastruktur modern terpadu guna menjamin kenyamanan belajar, tinggal, dan beribadah bagi seluruh santri.
            </p>
        </div>

        <!-- Filter Buttons -->
        <div class="flex flex-wrap justify-center gap-3 no-print">
            <button type="button" data-category="all" class="filter-btn bg-primary text-white text-xs font-bold px-5 py-2.5 rounded-full transition shadow-sm focus:outline-none">Semua Sarana</button>
            <button type="button" data-category="belajar" class="filter-btn bg-white text-primary hover:bg-cream border border-gray-200 text-xs font-bold px-5 py-2.5 rounded-full transition shadow-sm focus:outline-none">Ruang Belajar</button>
            <button type="button" data-category="hunian" class="filter-btn bg-white text-primary hover:bg-cream border border-gray-200 text-xs font-bold px-5 py-2.5 rounded-full transition shadow-sm focus:outline-none">Hunian & Asrama</button>
            <button type="button" data-category="ibadah" class="filter-btn bg-white text-primary hover:bg-cream border border-gray-200 text-xs font-bold px-5 py-2.5 rounded-full transition shadow-sm focus:outline-none">Sarana Ibadah</button>
            <button type="button" data-category="umum" class="filter-btn bg-white text-primary hover:bg-cream border border-gray-200 text-xs font-bold px-5 py-2.5 rounded-full transition shadow-sm focus:outline-none">Umum & Olahraga</button>
        </div>

        <!-- Facilities Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php if (!empty($fasilitas_list)): ?>
                <?php foreach ($fasilitas_list as $f): ?>
                    <?php 
                        $kat = !empty($f['kategori']) ? $f['kategori'] : 'umum';
                        $kat_label = 'Sarana Umum';
                        $fallback_img = 'assets/images/smp.webp';
                        if ($kat === 'belajar') { $kat_label = 'Ruang Belajar'; $fallback_img = 'assets/images/kelas.webp'; }
                        elseif ($kat === 'hunian') { $kat_label = 'Hunian & Asrama'; $fallback_img = 'assets/images/smp.webp'; }
                        elseif ($kat === 'ibadah') { $kat_label = 'Sarana Ibadah'; $fallback_img = 'assets/images/mushola_2.webp'; }
                        
                        // Decode gambar jika berupa JSON array (multi-upload)
                        $uploaded_imgs = [];
                        if (!empty($f['gambar'])) {
                            $dec = json_decode($f['gambar'], true);
                            if (is_array($dec)) {
                                foreach ($dec as $fn) $uploaded_imgs[] = get_storage_url('fasilitas', $fn);
                            } else {
                                $uploaded_imgs[] = get_storage_url('fasilitas', $f['gambar']);
                            }
                        }
                        
                        $img_src = !empty($uploaded_imgs) ? $uploaded_imgs[0] : $fallback_img;
                        $img_list = !empty($uploaded_imgs) ? $uploaded_imgs : [$fallback_img];
                        $img_json = json_encode(array_values(array_unique($img_list)));
                    ?>
                    <div class="facility-card bg-[#FBF9F6] rounded-3xl overflow-hidden border border-gray-150 shadow-sm flex flex-col justify-between h-full group hover:shadow-md transition-all duration-300" 
                         data-category="<?= $kat ?>" 
                         data-name="<?= htmlspecialchars($f['nama']) ?>" 
                         data-images='<?= $img_json ?>'>
                        <div>
                            <div class="h-48 overflow-hidden bg-gray-100 relative cursor-pointer">
                                <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($f['nama']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <span class="absolute top-4 left-4 bg-white/95 backdrop-blur-sm text-primary font-bold text-[9px] px-2.5 py-1 rounded-lg uppercase shadow-sm"><?= $kat_label ?></span>
                                <div class="absolute inset-0 bg-black/25 opacity-0 group-hover:opacity-100 flex items-center justify-center transition duration-300">
                                    <span class="bg-white/95 text-primary text-[10px] font-extrabold px-3 py-1.5 rounded-lg shadow flex items-center"><i class="fa-solid fa-magnifying-glass-plus mr-1.5"></i> Lihat Galeri</span>
                                </div>
                            </div>
                            <div class="p-6 space-y-2">
                                <h3 class="font-extrabold text-primary text-sm"><?= htmlspecialchars($f['nama']) ?></h3>
                                <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                                    <?= htmlspecialchars($f['deskripsi']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="p-6 pt-0 flex justify-between items-center border-t border-gray-100/50 mt-4 pt-4">
                            <span class="text-[10px] text-secondary font-bold"><i class="<?= htmlspecialchars($f['ikon'] ?? 'fa-solid fa-check') ?> mr-1.5"></i> <?= $kat_label ?></span>
                            <button type="button" class="open-gallery-btn text-[10px] font-extrabold text-secondary hover:text-primary transition flex items-center">
                                <span><?= count(array_unique($img_list)) ?> Foto</span> <i class="fa-solid fa-images ml-1.5"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-4 text-center py-12 text-gray-400 text-xs">
                    Belum ada data fasilitas.
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- Lightbox Modal Gallery (NEW) -->
<div id="gallery-modal" class="fixed inset-0 z-[100] hidden bg-black/90 backdrop-blur-sm flex flex-col justify-center items-center no-print">
    <!-- Close Button -->
    <button type="button" id="close-modal" class="absolute top-6 right-6 text-white/85 hover:text-white text-3xl focus:outline-none p-2" aria-label="Tutup Galeri">
        <i class="fa-solid fa-xmark"></i>
    </button>
    
    <!-- Modal Container -->
    <div class="relative w-full max-w-4xl px-4 flex flex-col items-center space-y-4">
        <!-- Main Image Slide -->
        <div class="relative w-full h-[300px] md:h-[500px] bg-[#1A1110] rounded-2xl overflow-hidden shadow-2xl flex items-center justify-center">
            <img id="modal-img" src="" alt="Gallery Image" class="max-w-full max-h-full object-contain">
            
            <!-- Nav Prev -->
            <button type="button" id="prev-slide" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/10 hover:bg-white/20 text-white rounded-full w-12 h-12 flex items-center justify-center focus:outline-none transition">
                <i class="fa-solid fa-chevron-left text-sm"></i>
            </button>
            <!-- Nav Next -->
            <button type="button" id="next-slide" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/10 hover:bg-white/20 text-white rounded-full w-12 h-12 flex items-center justify-center focus:outline-none transition">
                <i class="fa-solid fa-chevron-right text-sm"></i>
            </button>
        </div>
        
        <!-- Image Caption & Counter -->
        <div class="text-center space-y-1 text-white">
            <h4 id="modal-title" class="font-extrabold text-sm md:text-base">Nama Fasilitas</h4>
            <p id="modal-counter" class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Foto 1 dari 4</p>
        </div>
    </div>
</div>

<!-- Facility Gallery Filter & Lightbox Script -->
<script>
// 1. Gallery Filtering Logic
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        // Reset active state for all filter buttons
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('bg-primary', 'text-white');
            b.classList.add('bg-white', 'text-primary', 'hover:bg-cream', 'border', 'border-gray-200');
        });
        
        // Add active state to clicked button
        btn.classList.add('bg-primary', 'text-white');
        btn.classList.remove('bg-white', 'text-primary', 'hover:bg-cream', 'border', 'border-gray-200');
        
        const category = btn.getAttribute('data-category');
        document.querySelectorAll('.facility-card').forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// 2. Lightbox Carousel Logic
let currentImages = [];
let currentIdx = 0;
let currentTitle = "";

const modal = document.getElementById('gallery-modal');
const modalImg = document.getElementById('modal-img');
const modalTitle = document.getElementById('modal-title');
const modalCounter = document.getElementById('modal-counter');

// Helper to open gallery
function openGallery(card) {
    const imagesStr = card.getAttribute('data-images');
    currentTitle = card.getAttribute('data-name');
    
    try {
        currentImages = JSON.parse(imagesStr);
        currentIdx = 0;
        updateModalContent();
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden'); // Lock scroll
    } catch(err) {
        console.error("Error parsing gallery images", err);
    }
}

// Bind Click Events to Images and Buttons
document.querySelectorAll('.facility-card').forEach(card => {
    const imgWrapper = card.querySelector('.cursor-pointer');
    const btn = card.querySelector('.open-gallery-btn');
    
    if (imgWrapper) {
        imgWrapper.addEventListener('click', () => openGallery(card));
    }
    if (btn) {
        btn.addEventListener('click', () => openGallery(card));
    }
});

// Update modal display
function updateModalContent() {
    if (currentImages.length > 0) {
        modalImg.src = currentImages[currentIdx];
        modalTitle.textContent = currentTitle;
        modalCounter.textContent = `Foto ${currentIdx + 1} dari ${currentImages.length}`;
    }
}

// Navigation Slide Handlers
document.getElementById('next-slide').addEventListener('click', (e) => {
    e.stopPropagation();
    if (currentImages.length > 0) {
        currentIdx = (currentIdx + 1) % currentImages.length;
        updateModalContent();
    }
});

document.getElementById('prev-slide').addEventListener('click', (e) => {
    e.stopPropagation();
    if (currentImages.length > 0) {
        currentIdx = (currentIdx - 1 + currentImages.length) % currentImages.length;
        updateModalContent();
    }
});

// Close Handlers
function closeModal() {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden'); // Unlock scroll
}

document.getElementById('close-modal').addEventListener('click', closeModal);
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeModal();
    }
});

// Keyboard Accessibility
document.addEventListener('keydown', (e) => {
    if (!modal.classList.contains('hidden')) {
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowRight') document.getElementById('next-slide').click();
        if (e.key === 'ArrowLeft') document.getElementById('prev-slide').click();
    }
});
</script>

<!-- Ekstrakurikuler Section (NEW) -->
<section id="ekskul" class="py-20 bg-cream scroll-mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-sm uppercase tracking-wider block">Minat & Bakat</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Program Ekstrakurikuler Pilihan</h2>
            <p class="text-sm text-gray-650">
                Kami menyediakan program ekstrakurikuler unggulan guna mengasah potensi fisik, kepemimpinan, dan bakat seni santri secara seimbang.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <?php if (empty($ekskul_list)): ?>
                <div class="col-span-full text-center py-12 text-gray-400 text-sm">
                    <i class="fa-solid fa-star text-4xl mb-3 text-gray-300 block"></i>
                    Belum ada data ekstrakurikuler yang diunggah.
                </div>
            <?php else: ?>
                <?php foreach ($ekskul_list as $e): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-1.5 hover:shadow-md transition-all duration-300 flex flex-col h-full group">
                    <?php if (!empty($e['gambar'])): ?>
                    <?php $img_path = (strpos($e['gambar'], 'olahraga.webp') !== false) ? 'assets/images/' . $e['gambar'] : get_storage_url('ekskul', $e['gambar']); ?>
                    <div class="h-44 overflow-hidden bg-gray-100 relative">
                        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($e['nama']) ?>" 
                             onerror="this.onerror=null; this.src='assets/images/placeholder.webp';"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <?php else: ?>
                    <div class="h-44 bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-accent text-5xl relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-white opacity-5 text-9xl font-bold"><i class="<?= htmlspecialchars($e['ikon']) ?>"></i></div>
                        <i class="<?= htmlspecialchars($e['ikon']) ?> text-6xl text-accent opacity-90"></i>
                    </div>
                    <?php endif; ?>
                    <div class="p-6 space-y-2 flex-grow">
                        <h4 class="font-extrabold text-primary text-sm"><?= htmlspecialchars($e['nama']) ?></h4>
                        <p class="text-[11px] text-gray-500 leading-relaxed">
                            <?= htmlspecialchars($e['deskripsi']) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- Jadwal Aktivitas Harian Santri (Daily Routine - Visual Timeline) -->
<section id="jadwal-harian" class="py-24 bg-white border-t border-gray-200 scroll-mt-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Rutinitas Harian</span>
            <h2 class="text-3xl font-extrabold text-primary">Jadwal Harian Santri (Daily Routine)</h2>
            <p class="text-xs text-gray-500">
                Pondok Pesantren menerapkan disiplin waktu 24 jam penuh untuk menyeimbangkan kegiatan ibadah, akademik formal, dan istirahat santri.
            </p>
        </div>

        <!-- Visual Routine Timeline -->
        <div class="space-y-4 max-w-4xl mx-auto">
            
            <!-- Routine Item 1 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-moon"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">04.00 - 05.30 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Bangun Tidur & Subuh Berjamaah</strong>
                        <span class="text-gray-500">Diteruskan dengan Halaqah Zikir Pagi dan Tahfizh subuh bersama wali kamar.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 2 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">05.30 - 07.00 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Mandi & Sarapan Pagi</strong>
                        <span class="text-gray-500">Mandi pagi, piket kebersihan kamar asrama, sarapan bersama, dan persiapan masuk kelas.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 3 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">07.00 - 12.00 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">KBM Sekolah Formal</strong>
                        <span class="text-gray-500">Kegiatan Belajar Mengajar kurikulum nasional (SMP IT/SMA IT) secara aktif dan kondusif.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 4 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-cookie-bite"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">12.00 - 13.30 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Ishoma (Istirahat, Shalat, Makan)</strong>
                        <span class="text-gray-500">Shalat Zhuhur berjamaah di masjid, makan siang bergizi bersama, dan tidur siang sejenak.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 5 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-users-rectangle"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">13.30 - 15.00 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">KBM Sore & Ekstrakurikuler</strong>
                        <span class="text-gray-500">Kelas keterampilan bahasa asing, bela diri pencak silat, pramuka, atau olah seni kaligrafi.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 6 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-book-quran"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">15.00 - 17.30 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Ashar & Halaqah Al-Qur'an</strong>
                        <span class="text-gray-500">Shalat Ashar berjamaah dilanjutkan halaqah setoran hafalan Al-Qur'an sore bersama ustadz.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 7 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-mosque"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">17.30 - 20.00 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Maghrib, Makan Malam & Isya</strong>
                        <span class="text-gray-500">Membaca Ma'tsurat, makan malam bersama, dilanjutkan shalat Isya berjamaah.</span>
                    </div>
                </div>
            </div>

            <!-- Routine Item 8 -->
            <div class="bg-[#FBF9F6] p-5 rounded-2xl border border-gray-200/60 shadow-sm flex items-center space-x-5 hover:shadow-md transition duration-300">
                <div class="w-12 h-12 bg-secondary/15 text-primary rounded-xl flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-moon"></i>
                </div>
                <div class="flex-grow grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                    <span class="font-extrabold text-primary text-sm md:col-span-1">20.00 - 22.00 WIB</span>
                    <div class="text-xs md:col-span-3 leading-relaxed">
                        <strong class="text-primary block">Kajian Kitab Kuning & Istirahat Malam</strong>
                        <span class="text-gray-500">Mendalami nahwu/sharaf/fiqih, belajar mandiri tugas sekolah, dan tidur malam pukul 22.00 WIB.</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
