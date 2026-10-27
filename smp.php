<?php
// smp.php
require_once 'config/db.php';
$page_title = "SMP IT Babul Jannah - Pondok Pesantren Babul Jannah Dumai";
$meta_desc = "Pendidikan tingkat menengah pertama SMP IT Babul Jannah Dumai yang memadukan kurikulum Diknas dan kepesantrenan dengan target hafalan 3 Juz Al-Qur'an.";
require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">SMP IT Babul Jannah</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Membentuk lulusan SMP yang unggul dalam hafalan Al-Qur'an (3 Juz), berkarakter akhlak karimah, dan menguasai dasar-dasar sains serta bahasa asing.
        </p>
    </div>
</section>

<!-- Main Details Section -->
<section class="py-16 bg-accent no-print flex-grow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        
        <!-- Intro Grid (Real Image + Quick Facts) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-6">
                <div class="relative bg-white p-3 rounded-3xl shadow-xl border border-gray-150 overflow-hidden">
                    <img src="assets/images/smp.webp" alt="SMP IT Babul Jannah Campus" class="w-full h-[320px] object-cover rounded-2xl">
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/40 to-transparent"></div>
                </div>
            </div>
            <div class="lg:col-span-6 space-y-6">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Visi & Keunggulan</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-primary">Pondasi Karakter & Ilmu Seimbang</h2>
                <div class="w-12 h-1 bg-secondary rounded"></div>
                <p class="text-xs md:text-sm text-gray-600 leading-relaxed">
                    SMP IT Babul Jannah dirancang khusus untuk memandu transisi santri usia remaja (lulusan SD/MI) menuju kemandirian berlandaskan nilai-nilai Rabbani. Melalui metode *intensive mentoring* 24 jam di asrama, kami memastikan santri mendapatkan hak akademis umum dan penanaman adab kepondokan secara seimbang.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Tahfizh Qur'an</span>
                        <strong class="text-primary text-sm block">Target Minimal 3 Juz</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Bahasa Pengantar</span>
                        <strong class="text-primary text-sm block">Dasar Arab & Inggris</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Kapasitas Kelas</span>
                        <strong class="text-primary text-sm block">Maksimal 30 Santri/Kelas</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Akreditasi</span>
                        <strong class="text-primary text-sm block">Resmi Kementerian Pendidikan</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kurikulum & Tabel Pelajaran -->
        <div class="space-y-8">
            <div class="text-center space-y-2">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Struktur Kurikulum</span>
                <h3 class="text-2xl font-extrabold text-primary">Daftar Mata Pelajaran Terpadu</h3>
                </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Kurikulum Kepondokan (Salafiyah) -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-6">
                    <h4 class="font-extrabold text-primary text-base flex items-center border-b border-gray-100 pb-3">
                        <i class="fa-solid fa-book-open text-secondary mr-2.5"></i> Kurikulum Kepondokan (Salafiyah)
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600">
                            <thead>
                                <tr class="bg-cream/40 text-primary border-b border-gray-200">
                                    <th class="py-2.5 px-3 font-extrabold">Mata Pelajaran</th>
                                    <th class="py-2.5 px-3 font-extrabold text-center">Kitab Rujukan / Fokus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Tahfizh & Tajwid</td>
                                    <td class="py-3 px-3 text-center">Setoran Juz 30, 29, 1 / Matan Al-Jazariyah</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Aqidah & Akhlak</td>
                                    <td class="py-3 px-3 text-center">Kitab Aqidatul Awam & Taysirul Kholaq</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Fiqih Ibadah</td>
                                    <td class="py-3 px-3 text-center">Kitab Safinatun Najah (Madzhab Syafii)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Nahwu & Sharaf</td>
                                    <td class="py-3 px-3 text-center">Kitab Jurumiyah & Al-Amtsilah At-Tashrifiyah</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Hadits Pilihan</td>
                                    <td class="py-3 px-3 text-center">Hadits Arbain An-Nawawiyah (1 - 20)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Durusul Lughah</td>
                                    <td class="py-3 px-3 text-center">Penerapan Percakapan Harian Gontory</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kurikulum Nasional (Diknas) -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-6">
                    <h4 class="font-extrabold text-primary text-base flex items-center border-b border-gray-100 pb-3">
                        <i class="fa-solid fa-graduation-cap text-secondary mr-2.5"></i> Kurikulum Nasional (Diknas)
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600">
                            <thead>
                                <tr class="bg-cream/40 text-primary border-b border-gray-200">
                                    <th class="py-2.5 px-3 font-extrabold">Mata Pelajaran</th>
                                    <th class="py-2.5 px-3 font-extrabold text-center">Fokus Kompetensi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Matematika</td>
                                    <td class="py-3 px-3 text-center">Aljabar, Geometri Dasar, Logika Numerik</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Ilmu Pengetahuan Alam (IPA)</td>
                                    <td class="py-3 px-3 text-center">Fisika, Biologi, Kimia Dasar (Teori & Praktikum)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Ilmu Pengetahuan Sosial (IPS)</td>
                                    <td class="py-3 px-3 text-center">Sejarah Indonesia, Geografi, Ekonomi Dasar</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Bahasa Indonesia</td>
                                    <td class="py-3 px-3 text-center">Karya Tulis, Analisis Teks, Retorika Dasar</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Bahasa Inggris</td>
                                    <td class="py-3 px-3 text-center">Grammar Dasar, English Speaking & Listening</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Pendidikan Pancasila (PPKn)</td>
                                    <td class="py-3 px-3 text-center">Wawasan Kebangsaan, Bela Negara, Etika Sosial</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kehidupan Asrama & Ekstrakurikuler -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
            
            <!-- Asrama -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-4">
                <h4 class="font-extrabold text-primary text-base border-b border-gray-100 pb-3 flex items-center">
                    <i class="fa-solid fa-home text-secondary mr-2.5"></i> Pembinaan Karakter & Asrama
                </h4>
                <p class="text-xs text-gray-500 leading-relaxed font-normal">
                    Santri SMP IT ditempatkan di gedung asrama khusus pemula dengan pendampingan intensif dari pengasuh (*Musyrif/Musyrifah*). Fokus asrama adalah membiasakan kemandirian dasar seperti mencuci piring sendiri, merapikan kasur, shalat berjamaah tepat waktu, menjaga kebersihan, dan melatih sosialisasi yang baik tanpa ketergantungan gadget.
                </p>
            </div>

            <!-- Ekstrakurikuler -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-4">
                <h4 class="font-extrabold text-primary text-base border-b border-gray-100 pb-3 flex items-center">
                    <i class="fa-solid fa-users-gear text-secondary mr-2.5"></i> Kegiatan Ekstrakurikuler Pilihan
                </h4>
                <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-primary">
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-compass text-secondary mr-2"></i> Pramuka (Wajib)</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-futbol text-secondary mr-2"></i> Futsal & Badminton</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-pen-fancy text-secondary mr-2"></i> Kaligrafi (Khat)</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-bullhorn text-secondary mr-2"></i> Muhadharah Pidato</div>
                </div>
            </div>

        </div>

        <!-- CTA pendaftaran -->
        <div class="bg-primary text-white p-8 rounded-3xl text-center space-y-4 shadow-xl border border-secondary/20 max-w-4xl mx-auto">
            <h3 class="text-lg md:text-xl font-extrabold">Tertarik Mendaftarkan Putra-Putri Anda di SMP IT?</h3>
            <p class="text-xs text-gray-300 max-w-xl mx-auto leading-relaxed">
                Kuota terbatas hanya 90 santri per tahun ajaran baru. Segera amankan kuota pendaftaran secara online.
            </p>
            <div class="pt-2">
                <a href="daftar.php" class="inline-block bg-secondary hover:bg-white hover:text-primary text-white font-bold px-8 py-3 rounded-xl transition duration-300 text-xs shadow-md">Daftar SMP IT Sekarang <i class="fa-solid fa-chevron-right ml-1"></i></a>
            </div>
        </div>

    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
