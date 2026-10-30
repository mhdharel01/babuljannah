<?php
// index.php
require_once 'config/db.php';
$page_title = 'Beranda - Pondok Pesantren Babul Jannah';
$meta_desc = 'Pondok Pesantren Babul Jannah adalah lembaga pendidikan Islam terpadu di Dumai yang menggabungkan kurikulum formal dan diniyah.';
require_once 'includes/header.php';


// Ambil gelombang pendaftaran yang aktif (sedang buka atau akan buka)
$gelombang_aktif_list = [];
try {
    $stmt = $db->prepare("SELECT * FROM gelombang WHERE status = 'aktif' AND tgl_tutup >= CURDATE() ORDER BY tgl_buka ASC");
    $stmt->execute();
    $gelombang_aktif_list = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    $gelombang_aktif_list = [];
}

// Ambil 3 berita terbaru
$latest_news = [];
try {
    $stmt_berita = $db->query("SELECT * FROM berita ORDER BY id DESC LIMIT 3");
    $latest_news = $stmt_berita->fetchAll() ?: [];
} catch (Throwable $e) {
    $latest_news = [];
}

// Ambil fasilitas pondok
$fasilitas_list = [];
try {
    $stmt_fasilitas = $db->query("SELECT * FROM fasilitas ORDER BY urutan ASC, id ASC");
    $fasilitas_list = $stmt_fasilitas->fetchAll() ?: [];
} catch (Throwable $e) {
    $fasilitas_list = [];
}

// Ambil Biaya Formulir / Tes Seleksi (1 Nilai Global)
$biaya_tes_global = get_biaya_tes();

// Ambil Biaya Pendidikan
$master_biaya_list = [];
try {
    $stmt_biaya = $db->query("SELECT * FROM master_biaya_pendidikan ORDER BY id ASC");
    $master_biaya_list = $stmt_biaya ? $stmt_biaya->fetchAll() : [];
} catch (Throwable $e) {
    $master_biaya_list = [];
}

// Ambil tahun ajaran aktif secara dinamis dari gelombang
$tahun_ajaran_aktif = get_tahun_ajaran_aktif();
?>

<!-- Hero Premium Split Layout Section -->
<section class="relative min-h-[600px] flex items-center bg-[#FBF9F6] pt-28 pb-20 overflow-hidden no-print">
    <!-- Abstract Background shapes for premium feel -->
    <div class="absolute top-0 right-0 w-[50%] h-full bg-[#F3EDE6] rounded-l-[100px] -z-10 hidden lg:block"></div>
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-secondary/10 rounded-full blur-3xl -z-10"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Left Column: Content -->
            <div class="lg:col-span-6 space-y-6 text-left" data-aos="fade-right" data-aos-duration="1000">
                <span class="inline-flex items-center space-x-2 bg-secondary/15 border border-secondary/20 text-primary text-xs font-bold uppercase tracking-wider px-4 py-2 rounded-xl mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Penerimaan Santri Baru T.A <?= htmlspecialchars($tahun_ajaran_aktif) ?></span>
                </span>
                
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight text-primary leading-tight font-sans">
                    Membentuk Generasi <span class="text-secondary font-serif italic font-normal">Qur’ani</span>, Berilmu, Berakhlak Mulia, dan Berprestasi.
                </h1>
                
                <p class="text-sm md:text-base text-gray-650 leading-relaxed max-w-xl">
                    Pondok Pesantren Terpadu di Kota Dumai yang mengintegrasikan Kurikulum Pesantren (Tahfidz & Diniyah) dengan Pendidikan Formal (SMP IT Terakreditasi A & SMA IT Resmi). Lingkungan aman, asri, dan teratur.
                </p>

                <!-- Badge Highlights Premium -->
                <div class="flex flex-col sm:flex-row flex-wrap gap-3 pt-3 pb-6 relative z-10">
                    <div class="group flex items-center space-x-3 text-xs font-bold text-gray-800 bg-white/90 backdrop-blur-md px-4 py-2.5 rounded-xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] transition-all duration-300 hover:-translate-y-1">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white shadow-inner">
                            <i class="fa-solid fa-award text-[11px]"></i>
                        </div>
                        <span>SMP IT Terakreditasi "A"</span>
                    </div>
                    <div class="group flex items-center space-x-3 text-xs font-bold text-gray-800 bg-white/90 backdrop-blur-md px-4 py-2.5 rounded-xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] transition-all duration-300 hover:-translate-y-1">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white shadow-inner">
                            <i class="fa-solid fa-shield-cat text-[11px]"></i>
                        </div>
                        <span>Pesantren Ramah Anak</span>
                    </div>
                    <div class="group flex items-center space-x-3 text-xs font-bold text-gray-800 bg-white/90 backdrop-blur-md px-4 py-2.5 rounded-xl border border-white shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] transition-all duration-300 hover:-translate-y-1">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white shadow-inner">
                            <i class="fa-solid fa-file-signature text-[11px]"></i>
                        </div>
                        <span>Terdaftar Resmi Kemenag</span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-2">
                    <a href="daftar.php" class="bg-primary hover:bg-secondary text-white font-bold px-8 py-4 rounded-xl shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 text-center text-sm">
                        Daftar Online Sekarang <i class="fa-solid fa-arrow-right ml-1"></i>
                    </a>
                    <a href="profil.php" class="border-2 border-primary/20 hover:border-primary text-primary font-bold px-8 py-4 rounded-xl transition duration-300 text-center text-sm bg-white/50">
                        Profil Pesantren
                    </a>
                </div>

                <!-- Active Wave Widget (Minimalist Inline) -->
                <!-- Active Wave Widget (Minimalist Inline) -->
                <?php if (!empty($gelombang_aktif_list)): ?>
                    <div class="pt-4 space-y-2 border-t border-gray-200/60 max-w-md">
                        <?php foreach ($gelombang_aktif_list as $gel): 
                            $now = time();
                            $buka = strtotime($gel['tgl_buka']);
                            $status_waktu = ($now < $buka) ? 'upcoming' : 'open';
                        ?>
                            <div class="flex items-center space-x-3 text-xs font-semibold <?= $status_waktu === 'upcoming' ? 'text-amber-600' : 'text-gray-600' ?>">
                                <i class="fa-solid <?= $status_waktu === 'upcoming' ? 'fa-clock' : 'fa-bullhorn text-secondary' ?> text-base flex-shrink-0"></i>
                                <?php if ($status_waktu === 'upcoming'): ?>
                                    <span>Akan dibuka: <strong class="text-amber-700"><?= htmlspecialchars($gel['nama_gelombang']) ?><?= !empty($gel['tahun_ajaran']) ? ' (T.A ' . htmlspecialchars($gel['tahun_ajaran']) . ')' : '' ?></strong> pada <?= format_tanggal($gel['tgl_buka']) ?></span>
                                <?php else: ?>
                                    <span>Sedang dibuka: <strong class="text-primary"><?= htmlspecialchars($gel['nama_gelombang']) ?><?= !empty($gel['tahun_ajaran']) ? ' (T.A ' . htmlspecialchars($gel['tahun_ajaran']) . ')' : '' ?></strong> s/d <?= format_tanggal($gel['tgl_tutup']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column: Asymmetric Image Collage (Real Assets) -->
            <div class="lg:col-span-6 relative flex justify-center items-center h-[400px] sm:h-[450px]" data-aos="fade-left" data-aos-duration="1200">
                
                <!-- Background decorative border card -->
                <div class="absolute inset-0 m-auto w-72 h-[340px] border-2 border-secondary/35 rounded-3xl -rotate-6 translate-x-4 -z-10"></div>
                
                <!-- Photo 1: Classroom (Real Asset) -->
                <div class="absolute inset-0 m-auto w-52 h-64 sm:w-60 sm:h-72 bg-white p-3 rounded-2xl shadow-xl border border-gray-150 rotate-3 -translate-x-16 -translate-y-8 hover:rotate-0 hover:scale-105 transition-all duration-300 cursor-pointer">
                    <img src="assets/images/kelas.webp" alt="Ruang Belajar Babul Jannah" fetchpriority="high" class="w-full h-full object-cover rounded-xl bg-gray-50">
                    <div class="absolute bottom-5 left-5 bg-white bg-opacity-90 backdrop-blur-sm px-2.5 py-1 rounded-lg text-[9px] font-extrabold text-primary shadow">
                        Ruang Belajar Nyaman
                    </div>
                </div>

                <!-- Photo 2: Campus Environment (Real Asset) -->
                <div class="absolute inset-0 m-auto w-56 h-64 sm:w-64 sm:h-72 bg-white p-3 rounded-2xl shadow-2xl border border-gray-150 -rotate-6 translate-x-16 translate-y-12 hover:rotate-0 hover:scale-105 transition-all duration-300 cursor-pointer">
                    <img src="assets/images/smp.webp" alt="Kampus Babul Jannah" fetchpriority="high" class="w-full h-full object-cover rounded-xl bg-gray-50">
                    <div class="absolute bottom-5 left-5 bg-white bg-opacity-90 backdrop-blur-sm px-2.5 py-1 rounded-lg text-[9px] font-extrabold text-primary shadow">
                        Lingkungan Asri
                    </div>
                </div>

                <!-- Photo 3: Small Floating Card - Mosque (Real Asset) -->
                <div class="absolute inset-0 m-auto w-28 h-36 bg-white p-2 rounded-xl shadow-lg border border-gray-150 rotate-12 translate-x-32 -translate-y-24 hidden sm:block">
                    <img src="assets/images/mushola.webp" alt="Mushola Pesantren" fetchpriority="high" class="w-full h-full object-cover rounded-lg bg-gray-50">
                </div>

            </div>

        </div>
    </div>
</section>

<!-- Highlight Spesial: Pesantren Ramah Anak (Premium Trust Section) -->
<section class="py-20 relative overflow-hidden bg-white border-t border-b border-gray-150">
    <!-- Soft background accent -->
    <div class="absolute inset-0 bg-[radial-gradient(#10b981_1px,transparent_1px)] [background-size:24px_24px] opacity-[0.03] -z-10"></div>
    <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-50 rounded-full blur-3xl -z-10 -translate-y-1/2 translate-x-1/3"></div>
    <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-secondary/10 rounded-full blur-3xl -z-10 translate-y-1/2 -translate-x-1/3"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-16">
            
            <!-- Left Text Content -->
            <div class="lg:w-1/3 space-y-6 relative z-10" data-aos="fade-right">
                <span class="inline-flex items-center space-x-2 bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-extrabold px-4 py-1.5 rounded-full uppercase tracking-widest shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Trust & Safety</span>
                </span>
                <h2 class="text-3xl md:text-4xl font-extrabold leading-tight text-primary">
                    Kenapa Orang Tua Percaya Pada Babul Jannah?
                </h2>
                <div class="w-16 h-1.5 bg-secondary rounded-full"></div>
                <p class="text-sm text-gray-600 leading-relaxed font-medium">
                    Kami tidak hanya mendidik, tetapi juga melindungi. Kami berkomitmen penuh menghadirkan lingkungan belajar yang asri dan <strong class="text-primary">Bebas dari Kekerasan, Perundungan (Bullying), maupun Diskriminasi</strong>.
                </p>
            </div>
            
            <!-- Right Dynamic Cards -->
            <div class="lg:w-2/3 space-y-6 relative z-10" data-aos="fade-left">
                
                <!-- Hero Card -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-150 shadow-[0_10px_40px_-10px_rgba(0,0,0,0.08)] relative overflow-hidden group hover:shadow-[0_10px_40px_-10px_rgba(16,185,129,0.15)] transition-all duration-500">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-emerald-400 to-secondary"></div>
                    <i class="fa-solid fa-shield-halved absolute -right-6 -bottom-6 text-8xl text-emerald-50 group-hover:scale-110 transition-transform duration-700"></i>
                    
                    <p class="text-base md:text-lg font-extrabold text-gray-800 leading-relaxed relative z-10">
                        Pesantren Terpilih sebagai <span class="text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md inline-block mt-1 border border-emerald-100 shadow-sm">Pilot Pendamping Program Pesantren Ramah Anak Kemenag RI</span>
                    </p>
                    <p class="text-xs text-gray-600 mt-3 font-mono relative z-10">(Keputusan Dirjen Pendis Kemenag RI No. 1541 Tahun 2025)</p>
                </div>
                
                <!-- 3 Feature Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Card 1 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 hover:border-emerald-300 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 shadow-sm group cursor-default relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-red-50 to-transparent rounded-bl-full -z-10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-red-500 group-hover:text-white transition-all duration-300">
                            <i class="fa-solid fa-ban text-sm"></i>
                        </div>
                        <h4 class="font-bold text-sm text-gray-800 mb-2">Zero Tolerance Bullying</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed">Penanganan tegas & terukur untuk menjamin kenyamanan dan keamanan psikologis setiap santri.</p>
                    </div>
                    <!-- Card 2 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 hover:border-emerald-300 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 shadow-sm group cursor-default relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-blue-50 to-transparent rounded-bl-full -z-10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300">
                            <i class="fa-solid fa-user-shield text-sm"></i>
                        </div>
                        <h4 class="font-bold text-sm text-gray-800 mb-2">Pendampingan 24 Jam</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed">Asrama dijaga ketat dan didampingi langsung oleh tenaga Ustadz/Ustadzah berkompeten.</p>
                    </div>
                    <!-- Card 3 -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 hover:border-emerald-300 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 shadow-sm group cursor-default relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-emerald-50 to-transparent rounded-bl-full -z-10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-300">
                            <i class="fa-solid fa-heart-circle-check text-sm"></i>
                        </div>
                        <h4 class="font-bold text-sm text-gray-800 mb-2">Perlindungan Hak Anak</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed">Fokus menjamin hak dasar pertumbuhan, kesehatan, gizi, dan masa depan pendidikan santri.</p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- Animated Number Counter Bar (Statistik Interaktif) -->
<section class="py-12 bg-primary text-white relative overflow-hidden shadow-xl" data-aos="fade-up">
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-white/10">
            <div class="p-4" data-aos="zoom-in" data-aos-delay="100">
                <div class="text-3xl sm:text-4xl md:text-5xl font-extrabold font-serif tracking-tight text-accent flex justify-center items-center">
                    <span class="counter-value" data-target="<?= date('Y') - 2020 ?>">0</span><span>+</span>
                </div>
                <p class="text-xs sm:text-sm text-gray-300 font-medium uppercase tracking-wider mt-2">Tahun Berdiri & Mengabdi</p>
            </div>
            <div class="p-4" data-aos="zoom-in" data-aos-delay="200">
                <div class="text-3xl sm:text-4xl md:text-5xl font-extrabold font-serif tracking-tight text-accent flex justify-center items-center">
                    <span class="counter-value" data-target="326">0</span><span>+</span>
                </div>
                <p class="text-xs sm:text-sm text-gray-300 font-medium uppercase tracking-wider mt-2">Santri Aktif Mukim</p>
            </div>
            <div class="p-4" data-aos="zoom-in" data-aos-delay="300">
                <div class="text-3xl sm:text-4xl md:text-5xl font-extrabold font-serif tracking-tight text-accent flex justify-center items-center">
                    <span class="counter-value" data-target="98">0</span><span>%</span>
                </div>
                <p class="text-xs sm:text-sm text-gray-300 font-medium uppercase tracking-wider mt-2">Target Mutqin Tahfizh</p>
            </div>
            <div class="p-4" data-aos="zoom-in" data-aos-delay="400">
                <div class="text-3xl sm:text-4xl md:text-5xl font-extrabold font-serif tracking-tight text-accent flex justify-center items-center">
                    <span class="counter-value" data-target="8">0</span><span>+</span>
                </div>
                <p class="text-xs sm:text-sm text-gray-300 font-medium uppercase tracking-wider mt-2">Ekstrakurikuler & Prestasi</p>
            </div>
        </div>
    </div>
</section>

<!-- Sambutan Pimpinan Section -->
<section class="py-24 bg-white border-t border-gray-100" data-aos="fade-up">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Foto Pimpinan -->
            <div class="lg:col-span-5 flex justify-center" data-aos="zoom-in" data-aos-duration="1000">
                <div class="relative group w-full max-w-sm">
                    <div class="absolute -inset-2 bg-gradient-to-r from-secondary to-primary rounded-3xl blur opacity-25 group-hover:opacity-45 transition duration-500"></div>
                    <div class="relative bg-white p-3 rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
                        <img src="assets/images/pimpinan.webp" alt="H. Sutriono" loading="lazy" class="w-full h-[380px] object-cover rounded-2xl">
                        <div class="absolute bottom-6 left-6 right-6 bg-primary/95 backdrop-blur-sm p-4 rounded-xl border border-white border-opacity-10 text-white text-center">
                            <h4 class="font-extrabold text-sm tracking-wide">H. Sutriono</h4>
                            <p class="text-[9px] text-gray-300 font-bold uppercase tracking-wider mt-0.5">Pimpinan Pondok Pesantren</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Teks Sambutan -->
            <div class="lg:col-span-7 space-y-6" data-aos="fade-left" data-aos-duration="1000">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Kata Pengantar</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-primary leading-tight font-sans">
                    Membimbing Santri Menuju Ridha Ilahi & Kemaslahatan Umat
                </h2>
                <div class="w-16 h-1.5 bg-secondary rounded-full"></div>
                <div class="text-gray-600 text-xs md:text-sm leading-relaxed space-y-4 font-normal">
                    <p class="font-serif italic text-primary/85 text-sm">"Assalamu'alaikum Warahmatullahi Wabarakatuh,"</p>
                    <p>
                        Segala puji bagi Allah SWT yang senantiasa menuntun dan memberkahi perjuangan kita. Shalawat dan salam bagi Baginda Nabi Muhammad SAW. Kami menyambut dengan sukacita kedatangan calon santri dan para orang tua wali di gerbang pendaftaran digital kami.
                    </p>
                    <p>
                        Pondok Pesantren Babul Jannah adalah amanah dan sarana bagi kita bersama untuk mendidik putra-putri kita menjadi pribadi yang shalih, shalihah, mandiri, dan berilmu tinggi. Dengan sistem asrama yang kondusif, bimbingan tahfizh harian yang terarah, serta kurikulum terakreditasi resmi, kami siap melayani dan mendampingi proses tumbuh kembang intelektual serta spiritual santri.
                    </p>
                    <p class="font-serif italic text-primary/85 text-sm">"Wassalamu'alaikum Warahmatullahi Wabarakatuh."</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Jenjang Pendidikan Section -->
<section class="py-24 bg-cream border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-16">
        
        <div class="space-y-4 max-w-2xl mx-auto" data-aos="fade-down">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Program Pendidikan</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary">Pilihan Jenjang Unggulan</h2>
            <p class="text-xs text-gray-600">
                Kami menyediakan 3 pilihan program pendidikan formal terpadu yang dirancang sesuai usia dan target perkembangan santri.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- SMP IT Card (Real Asset: smp.webp) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group" data-aos="flip-up" data-aos-delay="100" data-aos-duration="800">
                <div>
                    <div class="h-48 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/smp.webp" alt="SMP IT Babul Jannah" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary to-transparent opacity-40"></div>
                        <span class="absolute bottom-4 left-4 bg-white/90 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">SMP IT</span>
                    </div>
                    <div class="p-6 text-left space-y-4">
                        <h3 class="text-base font-extrabold text-primary">SMP IT Babul Jannah</h3>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-semibold mb-1">NPSN: 70044787 &bull; Terakreditasi A</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Fokus pada pembentukan karakter dasar, kedisiplinan, pembiasaan ibadah, dan pencapaian target hafalan Al-Qur'an.</p>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="smp.php" class="block w-full text-center border-2 border-secondary/40 text-secondary hover:bg-secondary hover:text-white text-xs font-bold py-2.5 rounded-xl transition duration-300">Info Selengkapnya</a>
                </div>
            </div>

            <!-- SMA IT Card (Real Asset: IMG-20241023-WA0081.webp) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group" data-aos="flip-up" data-aos-delay="250" data-aos-duration="800">
                <div>
                    <div class="h-48 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/IMG-20241023-WA0081.webp" alt="SMA IT Babul Jannah" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary to-transparent opacity-40"></div>
                        <span class="absolute bottom-4 left-4 bg-white/90 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">SMA IT</span>
                    </div>
                    <div class="p-6 text-left space-y-4">
                        <h3 class="text-base font-extrabold text-primary">SMA IT Babul Jannah</h3>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-semibold mb-1">NPSN: 70051948</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Fokus pada pemanduan bakat/peminatan, kepemimpinan, persiapan persaingan akademik, dan bimbingan masuk Perguruan Tinggi.</p>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="sma.php" class="block w-full text-center border-2 border-secondary/40 text-secondary hover:bg-secondary hover:text-white text-xs font-bold py-2.5 rounded-xl transition duration-300">Info Selengkapnya</a>
                </div>
            </div>

            <!-- Takhasus Card (Real Asset: IMG-20250504-WA0073.webp) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-2 hover:shadow-xl transition-all duration-300 flex flex-col justify-between h-full group" data-aos="flip-up" data-aos-delay="400" data-aos-duration="800">
                <div>
                    <div class="h-48 overflow-hidden bg-gray-100 relative">
                        <img src="assets/images/IMG-20250504-WA0073.webp" alt="Program Takhasus" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary to-transparent opacity-40"></div>
                        <span class="absolute bottom-4 left-4 bg-white/90 backdrop-blur-sm text-primary font-extrabold text-[10px] px-3 py-1 rounded-lg uppercase shadow">Takhasus</span>
                    </div>
                    <div class="p-6 text-left space-y-4">
                        <h3 class="text-base font-extrabold text-primary">Unit Kepondokan</h3>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-semibold mb-1">NSP: 510214720016</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Program Takhasus Hafalan Al-Qur’an, kajian kitab, bahasa Arab/Inggris, serta pembentukan adab & akhlakul karimah.</p>
                    </div>
                </div>
                <div class="p-6 pt-0">
                    <a href="takhasus.php" class="block w-full text-center border-2 border-secondary/40 text-secondary hover:bg-secondary hover:text-white text-xs font-bold py-2.5 rounded-xl transition duration-300">Info Selengkapnya</a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Berita & Kegiatan Section -->
<section class="py-24 bg-white border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 border-b border-gray-150 pb-6" data-aos="fade-down">
            <div class="space-y-3">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Kabar Pondok</span>
                <h2 class="text-3xl font-extrabold text-primary tracking-tight">Berita & Informasi Kegiatan</h2>
                <div class="w-12 h-1 bg-secondary rounded-full"></div>
            </div>
            <a href="berita.php" class="bg-primary hover:bg-secondary text-white font-bold text-xs px-5 py-3 rounded-xl shadow transition duration-300 flex items-center space-x-1.5 self-start md:self-auto">
                <span>Lihat Semua Berita</span>
                <i class="fa-solid fa-chevron-right text-[9px]"></i>
            </a>
        </div>

        <!-- News Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php if (count($latest_news) === 0): ?>
                <div class="col-span-3 text-center py-12 text-gray-400 text-xs">Belum ada berita dipublikasikan.</div>
            <?php else: ?>
                <?php foreach ($latest_news as $idx => $news): ?>
                    <article class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden transform hover:-translate-y-1.5 hover:shadow-md transition-all duration-300 flex flex-col justify-between h-full group" data-aos="fade-up" data-aos-delay="<?= ($idx * 150) + 100 ?>">
                        <div>
                            <!-- News Image -->
                            <div class="relative h-48 overflow-hidden bg-gray-100">
                                <img src="<?= get_storage_url('berita', preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $news['gambar'])) ?>" alt="<?= htmlspecialchars($news['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute top-4 left-4 bg-primary text-accent text-[9px] font-bold tracking-wider px-3 py-1.5 rounded-xl uppercase shadow">
                                    <?= date('d M Y', strtotime($news['created_at'])) ?>
                                </div>
                            </div>
                            
                            <!-- News Content -->
                            <div class="p-6 space-y-3">
                                <h3 class="font-extrabold text-primary text-sm leading-snug group-hover:text-secondary transition-colors duration-200">
                                    <a href="berita/<?= $news['id'] ?>-<?= create_slug($news['judul']) ?>"><?= $news['judul'] ?></a>
                                </h3>
                                <p class="text-xs text-gray-600 leading-relaxed line-clamp-3 font-normal">
                                    <?= $news['kutipan'] ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-6 pt-0">
                            <a href="berita/<?= $news['id'] ?>-<?= create_slug($news['judul']) ?>" class="inline-flex items-center text-xs font-bold text-secondary hover:text-primary transition-colors group-hover:underline">
                                <span>Baca Selengkapnya</span>
                                <i class="fa-solid fa-arrow-right ml-1.5 text-[8px]"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- Testimoni Wali Santri & Alumni (NEW) -->
<section class="py-24 bg-white border-t border-gray-100 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Bukti Nyata</span>
            <h2 class="text-3xl font-extrabold text-primary font-sans">Apa Kata Wali Santri & Alumni?</h2>
            <p class="text-xs text-gray-600 max-w-lg mx-auto">
                Dengarkan kisah perubahan karakter, prestasi akademik, dan kenyamanan hidup di asrama langsung dari lisan mereka.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Testi 1 -->
            <div class="bg-[#FBF9F6] p-8 rounded-3xl border border-gray-150 relative space-y-6 flex flex-col justify-between hover:shadow-lg transition duration-300" data-aos="zoom-in" data-aos-delay="100">
                <div class="text-secondary text-4xl font-serif">“</div>
                <p class="text-xs text-gray-650 leading-relaxed font-normal flex-grow italic">
                    Sejak mondok di Babul Jannah, putra kami menunjukkan kemajuan akhlak yang luar biasa. Ia menjadi sangat hormat pada orang tua, rajin shalat subuh berjamaah di awal waktu secara mandiri, dan hafalannya kini sudah melampaui target minimal 3 juz.
                </p>
                <div class="border-t border-gray-200/60 pt-4 flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-xs text-primary font-sans">HR</div>
                    <div>
                        <strong class="text-primary text-xs block font-bold">H. Rahmad Wibowo</strong>
                        <span class="text-[9px] text-gray-400 block font-semibold">Wali Santri SMP IT - Bandung</span>
                    </div>
                </div>
            </div>

            <!-- Testi 2 -->
            <div class="bg-[#FBF9F6] p-8 rounded-3xl border border-gray-150 relative space-y-6 flex flex-col justify-between hover:shadow-lg transition duration-300" data-aos="zoom-in" data-aos-delay="250">
                <div class="text-secondary text-4xl font-serif">“</div>
                <p class="text-xs text-gray-650 leading-relaxed font-normal flex-grow italic">
                    Kami sangat bersyukur memercayakan putri kami di sini. Keseimbangan antara kurikulum dinas dengan pelajaran kepondokan salaf sangat nyata. Alhasil, ia bisa lolos seleksi SNBT Fakultas Kedokteran sekaligus menyelesaikan hafalan 6 juz.
                </p>
                <div class="border-t border-gray-200/60 pt-4 flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-xs text-primary font-sans">FT</div>
                    <div>
                        <strong class="text-primary text-xs block font-bold">dr. Fatimah Az-Zahra</strong>
                        <span class="text-[9px] text-gray-400 block font-semibold">Wali Santri SMA IT - Jakarta</span>
                    </div>
                </div>
            </div>

            <!-- Testi 3 -->
            <div class="bg-[#FBF9F6] p-8 rounded-3xl border border-gray-150 relative space-y-6 flex flex-col justify-between hover:shadow-lg transition duration-300" data-aos="zoom-in" data-aos-delay="400">
                <div class="text-secondary text-4xl font-serif">“</div>
                <p class="text-xs text-gray-650 leading-relaxed font-normal flex-grow italic">
                    Pondok Pesantren Babul Jannah memberikan saya pondasi keilmuan syar'i dan hafalan Al-Qur'an yang sangat kuat. Bimbingan dari para asatidzah yang bersanad membukakan jalan bagi saya untuk mendapatkan beasiswa penuh kuliah di Universitas Islam Madinah.
                </p>
                <div class="border-t border-gray-200/60 pt-4 flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-xs text-primary font-sans">MI</div>
                    <div>
                        <strong class="text-primary text-xs block font-bold">Ust. M. Ihsan, Lc.</strong>
                        <span class="text-[9px] text-gray-400 block font-semibold">Alumni Takhasus - Penerima Beasiswa UIM</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Infografis Alur PSB (Premium Timeline) -->
<section class="py-24 bg-[#FBF9F6] border-t border-gray-100/60 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-16">
        
        <div class="space-y-4 max-w-2xl mx-auto" data-aos="fade-down">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Prosedur PSB</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary font-sans">4 Langkah Mudah Alur Pendaftaran</h2>
            <p class="text-xs text-gray-600 max-w-lg mx-auto">
                Proses admisi penerimaan santri baru Pondok Pesantren Babul Jannah dirancang mudah, cepat, dan transparan secara online.
            </p>
        </div>

        <!-- Timeline Grid -->
        <div class="relative">
            <!-- Connecting Dotted Line (Desktop Only) -->
            <div class="hidden md:block absolute left-16 right-16 top-14 h-0.5 border-t-2 border-dashed border-secondary/30 -z-0"></div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative z-10">
                <!-- Step 1 -->
                <div class="bg-white p-8 rounded-3xl border border-gray-200/60 hover:border-secondary/40 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col items-center text-center space-y-5 group transform hover:-translate-y-1" data-aos="flip-left" data-aos-delay="100" data-aos-duration="800">
                    <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm shadow-md border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform">
                        01
                    </div>
                    <div class="w-14 h-14 bg-secondary/10 text-secondary rounded-2xl flex items-center justify-center text-2xl group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-extrabold text-primary text-sm">Formulir Online</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-normal">
                            Lengkapi data diri calon santri dan orang tua di menu <strong>Daftar Sekarang</strong>, lalu simpan Nomor Registrasi Anda.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="bg-white p-8 rounded-3xl border border-gray-200/60 hover:border-secondary/40 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col items-center text-center space-y-5 group transform hover:-translate-y-1" data-aos="flip-left" data-aos-delay="250" data-aos-duration="800">
                    <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm shadow-md border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform">
                        02
                    </div>
                    <div class="w-14 h-14 bg-secondary/10 text-secondary rounded-2xl flex items-center justify-center text-2xl group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-extrabold text-primary text-sm">Biaya Pendaftaran</h4>
                        <p class="text-[11px] text-gray-600 mt-0.5 leading-relaxed">
                            Transfer biaya seleksi sebesar <strong><?= format_rupiah($biaya_tes_global) ?></strong> ke rekening resmi, kemudian unggah foto struk bayar di halaman <strong>Cek Status</strong>.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="bg-white p-8 rounded-3xl border border-gray-200/60 hover:border-secondary/40 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col items-center text-center space-y-5 group transform hover:-translate-y-1" data-aos="flip-left" data-aos-delay="400" data-aos-duration="800">
                    <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm shadow-md border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform">
                        03
                    </div>
                    <div class="w-14 h-14 bg-secondary/10 text-secondary rounded-2xl flex items-center justify-center text-2xl group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-extrabold text-primary text-sm">Ujian Uji Seleksi</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-normal">
                            Unduh kartu ujian seleksi, ikuti tes tertulis & wawancara di lokasi pondok sesuai jadwal, dan tunggu pengumuman kelulusan.
                        </p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="bg-white p-8 rounded-3xl border border-gray-200/60 hover:border-secondary/40 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col items-center text-center space-y-5 group transform hover:-translate-y-1" data-aos="flip-left" data-aos-delay="550" data-aos-duration="800">
                    <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm shadow-md border-2 border-white ring-4 ring-secondary/15 group-hover:scale-110 transition-transform">
                        04
                    </div>
                    <div class="w-14 h-14 bg-secondary/10 text-secondary rounded-2xl flex items-center justify-center text-2xl group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div class="space-y-2">
                        <h4 class="font-extrabold text-primary text-sm">Daftar Ulang</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-normal">
                            Setelah dinyatakan LULUS seleksi, lakukan daftar ulang administrasi dengan mudah via WhatsApp konfirmasi langsung ke panitia.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Rincian Biaya Pendidikan & Estimasi (Tab Interaktif) -->
<section class="py-24 bg-white border-t border-gray-100 no-print" data-aos="fade-up">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Transparansi Biaya</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary font-sans">Rincian Biaya Pendidikan T.A <?= htmlspecialchars($tahun_ajaran_aktif) ?></h2>
            <p class="text-xs text-gray-600">
                Berikut adalah estimasi rincian biaya awal masuk dan bulanan untuk masing-masing jenjang pendidikan di Pondok Pesantren Babul Jannah.
            </p>
        </div>

        <!-- Tab Buttons -->
        <div class="flex justify-center space-x-2 sm:space-x-4" data-aos="zoom-in" data-aos-delay="100">
            <?php 
            $is_first = true;
            foreach($master_biaya_list as $mb): 
                $active_class = $is_first ? 'bg-primary text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200';
                
                // Icon mapping
                $icon = 'fa-graduation-cap';
                if (stripos($mb['kode_jenjang'], 'sma') !== false) $icon = 'fa-user-graduate';
                elseif (stripos($mb['kode_jenjang'], 'takh') !== false) $icon = 'fa-book-quran';
            ?>
            <button type="button" class="cost-tab-btn <?= $active_class ?> px-6 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-300 transform hover:-translate-y-0.5" data-tab="biaya-<?= $mb['kode_jenjang'] ?>">
                <i class="fa-solid <?= $icon ?> mr-2"></i><?= htmlspecialchars($mb['jenjang']) ?>
            </button>
            <?php 
            $is_first = false;
            endforeach; 
            ?>
        </div>

        <?php 
        $is_first_panel = true;
        foreach($master_biaya_list as $mb): 
            $panel_class = $is_first_panel ? 'block' : 'hidden';
            $total = $mb['uang_pangkal'] + $mb['seragam'] + $mb['kegiatan'] + $mb['spp_bulanan'];
        ?>
        <!-- Tab Panel: <?= $mb['jenjang'] ?> -->
        <div id="biaya-<?= $mb['kode_jenjang'] ?>" class="cost-tab-panel <?= $panel_class ?> animate-fadeIn">
            <div class="bg-[#FBF9F6] border border-secondary/20 rounded-3xl p-6 sm:p-8 shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div class="space-y-4">
                        <h3 class="text-xl font-extrabold text-primary flex items-center">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 mr-2.5 inline-block"></span>
                            Rincian Biaya <?= htmlspecialchars($mb['jenjang']) ?>
                        </h3>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            <?= htmlspecialchars($mb['deskripsi']) ?>
                        </p>
                        <ul class="space-y-3 text-xs text-gray-700 font-medium divide-y divide-gray-200/60">
                            <li class="pt-2 flex justify-between"><span>Uang Pangkal / Pembangunan:</span> <span class="font-bold text-primary"><?= format_rupiah($mb['uang_pangkal']) ?></span></li>
                            <li class="pt-2 flex justify-between"><span>Perlengkapan:</span> <span class="font-bold text-primary"><?= format_rupiah($mb['seragam']) ?></span></li>
                            <li class="pt-2 flex justify-between"><span>Biaya Semester:</span> <span class="font-bold text-primary"><?= format_rupiah($mb['kegiatan']) ?></span></li>
                            <li class="pt-2 flex justify-between text-emerald-700 font-bold"><span>SPP Bulanan:</span> <span><?= format_rupiah($mb['spp_bulanan']) ?> / bln</span></li>
                        </ul>
                    </div>
                    <div class="bg-primary text-white p-6 sm:p-8 rounded-2xl shadow-xl flex flex-col justify-center items-center text-center space-y-4 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-xl"></div>
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-secondary bg-white/10 px-3 py-1 rounded-full">Total Estimasi Masuk Awal</span>
                        <div class="text-3xl sm:text-4xl font-extrabold font-serif tracking-tight text-accent"><?= format_rupiah($total) ?></div>
                        <p class="text-[11px] text-gray-300 max-w-xs"><?= htmlspecialchars(html_entity_decode($mb['catatan'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></p>
                        <a href="daftar.php" class="inline-block bg-white text-primary hover:bg-secondary hover:text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow transition duration-200 w-full">Daftar Gelombang Ini</a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
        $is_first_panel = false;
        endforeach; 
        ?>
    </div>
</section>

<!-- Fasilitas & Sarana Pondok (Dynamic from DB) -->
<?php if (count($fasilitas_list) > 0): ?>
<section class="py-24 bg-[#FBF9F6] border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Sarana & Prasarana</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary font-sans">Fasilitas Pondok Pesantren</h2>
            <p class="text-xs text-gray-600 max-w-lg mx-auto">
                Kami menyediakan lingkungan belajar dan tinggal yang nyaman, aman, dan kondusif untuk mendukung perkembangan santri secara optimal.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($fasilitas_list as $idx => $fas): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-gray-200/60 overflow-hidden transform hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300 group flex flex-col" data-aos="zoom-in" data-aos-delay="<?= ($idx * 150) + 100 ?>">
                    <!-- Image or Icon Placeholder -->
                    <?php 
                        $img_src_idx = null;
                        if (!empty($fas['gambar'])) {
                            $dec_idx = json_decode($fas['gambar'], true);
                            $img_src_idx = is_array($dec_idx) ? ($dec_idx[0] ?? null) : $fas['gambar'];
                        }
                    ?>
                    <?php if (!empty($img_src_idx)): ?>
                    <div class="h-44 overflow-hidden bg-gray-100 relative">
                        <img src="<?= get_storage_url('fasilitas', $img_src_idx) ?>" alt="<?= htmlspecialchars($fas['nama']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-primary/30 to-transparent"></div>
                    </div>
                    <?php else: ?>
                    <div class="h-36 bg-gradient-to-br from-secondary/5 to-secondary/15 flex items-center justify-center relative">
                        <i class="<?= htmlspecialchars($fas['ikon']) ?> text-5xl text-secondary/30 group-hover:text-secondary/50 transition-colors duration-300"></i>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Content -->
                    <div class="p-6 space-y-3 flex-grow flex flex-col">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                                <i class="<?= htmlspecialchars($fas['ikon']) ?> text-lg"></i>
                            </div>
                            <h3 class="font-extrabold text-primary text-sm"><?= $fas['nama'] ?></h3>
                        </div>
                        <p class="text-xs text-gray-600 leading-relaxed font-normal flex-grow"><?= $fas['deskripsi'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<!-- Lokasi & Kontak Section (NEW) -->
<section class="py-24 bg-white border-t border-gray-100 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <div class="text-center space-y-4 max-w-2xl mx-auto" data-aos="fade-down">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Hubungi & Kunjungi Kami</span>
            <h2 class="text-3xl font-extrabold text-primary font-sans">Lokasi & Kontak Pesantren</h2>
            <p class="text-xs text-gray-600">
                Temukan lokasi fisik Pondok Pesantren Babul Jannah dan hubungi panitia untuk informasi pendaftaran offline.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <!-- Left Column: Google Maps Embed -->
            <div class="lg:col-span-7 h-[400px] rounded-3xl overflow-hidden border border-gray-200/80 shadow-md" data-aos="fade-right" data-aos-duration="1000">
                <iframe title="Peta Lokasi Pondok Pesantren Babul Jannah" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1145.1093402620484!2d101.42512192766331!3d1.6659447718410978!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d3a923b39cb2a7%3A0x32ba105ad0aed175!2sPONDOK%20PESANTREN%20BABUL%20JANNAH!5e0!3m2!1sid!2sid!4v1784998158784!5m2!1sid!2sid" class="w-full h-full border-0" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>

            <!-- Right Column: Contact Details -->
            <div class="lg:col-span-5 space-y-8" data-aos="fade-left" data-aos-duration="1000">
                <div class="space-y-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-sm">Alamat Pondok</h4>
                            <p class="text-[11px] text-gray-600 leading-relaxed font-normal">
                                <strong>Putra:</strong> Jl. Sidorejo No.02, Kel. Ratu Sima, Kec. Dumai Selatan, Kota Dumai, Riau<br>
                                <strong>Putri:</strong> Jl. Sidorejo No.22, Kel. Ratu Sima, Kec. Dumai Selatan, Kota Dumai, Riau
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-sm">Telepon / Call Center</h4>
                            <p class="text-xs text-gray-600 font-normal">
                                0811-7078-9988
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center text-lg flex-shrink-0">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div class="space-y-1">
                            <h4 class="font-extrabold text-primary text-sm">Surat Elektronik (Email)</h4>
                            <p class="text-xs text-gray-600 font-normal">
                                pondokpesantrenbabuljannah2023@gmail.com
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-4">
                    <a href="https://wa.me/6281170789988" target="_blank" class="flex-grow inline-flex items-center justify-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold px-6 py-3.5 rounded-2xl shadow transition text-xs">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>WhatsApp Panitia</span>
                    </a>
                    <a href="https://maps.google.com/?q=Pondok+Pesantren+Babul+Jannah+Dumai" target="_blank" class="flex-grow inline-flex items-center justify-center space-x-2 border border-primary text-primary hover:bg-cream/10 font-extrabold px-6 py-3.5 rounded-2xl transition text-xs bg-white">
                        <i class="fa-solid fa-route text-sm"></i>
                        <span>Petunjuk Rute</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- FAQ Interaktif (Accordion Tanya Jawab) -->
<section class="py-24 bg-[#FBF9F6] border-t border-gray-100 no-print" data-aos="fade-up">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-4" data-aos="fade-down">
            <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Pusat Informasi</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-primary font-sans">Pertanyaan yang Sering Diajukan (FAQ)</h2>
            <p class="text-xs text-gray-600">
                Jawaban cepat atas pertanyaan umum seputar kebijakan asrama, jadwal penjengukan, dan syarat pendaftaran santri baru.
            </p>
        </div>

        <div class="space-y-4">
            <!-- FAQ Item 1 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="100">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Apakah santri baru diperbolehkan membawa HP/Smartphone ke asrama?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    Demi menjaga fokus menghafal Al-Qur'an dan kegiatan belajar mengajar, santri <strong>tidak diperkenankan membawa HP/Smartphone</strong>. Pondok menyediakan layanan telepon umum asrama dan jadwal komunikasi video call rutin bersama wali santri melalui pengurus kepondokan.
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="200">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Bagaimana jadwal dan ketentuan penjengukan santri oleh orang tua?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    Penjengukan santri dijadwalkan secara rutin <strong>1 kali dalam sebulan</strong> pada hari Ahad minggu ke-2 atau ke-4 (sesuai kalender akademik). Wali santri diwajibkan membawa kartu wali santri dan mematuhi adab kunjung pondok.
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="300">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Apakah tersedia program beasiswa bagi santri berprestasi atau hafidz?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    <strong>Tentu ada!</strong> Pondok Pesantren Babul Jannah menyediakan program <em>Beasiswa Tahfizh</em> (gratis SPP bagi santri yang sudah memiliki hafalan minimal 5 Juz dengan tajwid mutqin) serta beasiswa bagi santri yatim/dhuafa yang berprestasi akademik tinggi.
                </div>
            </div>

            <!-- FAQ Item 4 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="400">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Bagaimana penanganan medis jika ada santri yang sakit di asrama?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    Pondok memiliki <strong>Poskestren (Pos Kesehatan Pesantren)</strong> yang dijaga oleh perawat/tenaga medis 24 jam. Jika santri membutuhkan penanganan lanjutan, pengurus akan segera merujuk ke RS/Puskesmas terdekat dan langsung menghubungi orang tua/wali santri.
                </div>
            </div>

            <!-- FAQ Item 5 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="500">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Apakah uang pangkal pendaftaran masuk bisa diangsur?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    Ya, uang pangkal investasi awal bagi calon santri yang dinyatakan <strong>LULUS SELEKSI</strong> dapat diangsur maksimal 3 kali selama semester pertama berjalan. Orang tua wajib menandatangani surat komitmen kesanggupan bermaterai dengan bagian administrasi keuangan.
                </div>
            </div>

            <!-- FAQ Item 6 -->
            <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" data-aos="fade-up" data-aos-delay="600">
                <button type="button" class="faq-accordion-btn w-full px-6 py-4 text-left font-bold text-sm text-primary flex justify-between items-center hover:bg-cream/50 transition-colors">
                    <span>Apa saja materi dalam ujian seleksi masuk?</span>
                    <i class="fa-solid fa-chevron-down faq-icon transition-transform duration-300 text-gray-400"></i>
                </button>
                <div class="hidden px-6 pb-4 pt-2 text-xs text-gray-600 leading-relaxed border-t border-gray-100 bg-gray-50/50">
                    Materi ujian seleksi masuk meliputi: Tes Akademis dasar secara tertulis (Matematika & Bahasa Inggris), Tes Membaca Al-Qur'an (tajwid & kelancaran), Tes Hafalan Jangka Pendek (lisan), serta Wawancara Komitmen Orang Tua & Kepribadian Calon Santri.
                </div>
            </div>
        </div>
    </div>
</section>



<?php
require_once 'includes/footer.php';
?>
