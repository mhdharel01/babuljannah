<?php
// takhasus.php
require_once 'config/db.php';
$page_title = "Program Takhasus Tahfizh 30 Juz - Pondok Pesantren Babul Jannah Dumai";
$meta_desc = "Program Takhasus Tahfizh Al-Qur'an 30 Juz Pondok Pesantren Babul Jannah Dumai dengan metode mutqin, bersanad, dan bimbingan asatidzah berkompeten.";
require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/IMG-20250504-WA0073.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Program Takhasus Tahfizh</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Program unggulan berorientasi khusus bagi santri yang bertekad menyelesaikan hafalan Al-Qur'an 30 Juz secara mutqin, bersertifikat syahadah, dan berkesempatan mengambil Sanad Qira'at.
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
                    <img src="assets/images/IMG-20250504-WA0073.webp" alt="Program Takhasus Tahfizh" class="w-full h-[320px] object-cover rounded-2xl bg-gray-50">
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/40 to-transparent"></div>
                </div>
            </div>
            <div class="lg:col-span-6 space-y-6">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Fokus Program</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-primary">Konsentrasi Penuh Tanpa Distraksi</h2>
                <div class="w-12 h-1 bg-secondary rounded"></div>
                <p class="text-xs md:text-sm text-gray-600 leading-relaxed">
                    Program Takhasus Tahfizh Al-Qur'an ditujukan bagi santri yang ingin berkonsentrasi penuh menghafal Al-Qur'an secara intensif tanpa dibebani mata pelajaran umum nasional secara penuh. Kurikulum difokuskan 100% pada interaksi aktif bersama Al-Qur'an selama 8 jam sehari. Target kelulusan adalah menyelesaikan hafalan 30 Juz secara mutqin dengan tajwid yang fasih dan pemahaman dasar ilmu Rasm Usmani.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Target Utama</span>
                        <strong class="text-primary text-sm block">Hafal 30 Juz Mutqin</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Jalur Sanad</span>
                        <strong class="text-primary text-sm block">Ujian Bersambung ke Rasulullah</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Durasi Program</span>
                        <strong class="text-primary text-sm block">1.5 - 2 Tahun Lulus</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Kuota Khusus</span>
                        <strong class="text-primary text-sm block">Maksimal 30 Santri terpilih</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metode & Skema Hafalan -->
        <div class="space-y-8">
            <div class="text-center space-y-2">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Metode Pembelajaran</span>
                <h3 class="text-2xl font-extrabold text-primary">Sistem Setoran & Evaluasi Harian</h3>
                </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Sabaq Card -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm space-y-3 transform hover:-translate-y-1 transition duration-300">
                    <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center font-bold text-sm">S</div>
                    <h4 class="font-extrabold text-primary text-sm">Sabaq (Hafalan Baru)</h4>
                    <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                        Menyetorkan ayat/halaman baru yang dihafal pada hari tersebut. Target harian minimal adalah **1 hingga 2 halaman** (setara setengah lembar atau satu lembar Al-Qur'an Madinah) dengan makhraj dan tajwid yang presisi.
                    </p>
                </div>

                <!-- Sabqi Card -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm space-y-3 transform hover:-translate-y-1 transition duration-300">
                    <div class="w-10 h-10 bg-secondary/15 text-secondary rounded-xl flex items-center justify-center font-bold text-sm">SB</div>
                    <h4 class="font-extrabold text-primary text-sm">Sabqi (Murajaah Dekat)</h4>
                    <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                        Mengulang kembali hafalan baru yang disetorkan dalam 1-2 minggu terakhir (minimal **5 halaman** per hari). Langkah ini sangat krusial untuk memastikan hafalan baru melekat erat di memori jangka pendek sebelum menjadi manzil.
                    </p>
                </div>

                <!-- Manzil Card -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm space-y-3 transform hover:-translate-y-1 transition duration-300">
                    <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center font-bold text-sm">M</div>
                    <h4 class="font-extrabold text-primary text-sm">Manzil (Murajaah Jauh)</h4>
                    <p class="text-[11px] text-gray-500 leading-relaxed font-normal">
                        Mengulang seluruh juz yang sudah dihafal dan dinyatakan lulus sebelumnya secara mandiri maupun berpasangan (*tasmi'*). Setiap santri wajib menyetorkan murajaah manzil minimal **1 Juz per hari** di depan mentor.
                    </p>
                </div>
            </div>
        </div>

        <!-- Ujian Kenaikan Juz & Tasmik Sekali Duduk -->
        <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-6">
            <h4 class="font-extrabold text-primary text-base flex items-center border-b border-gray-100 pb-3">
                <i class="fa-solid fa-microphone text-secondary mr-2.5"></i> Standarisasi Kelulusan & Ujian Tasmik Akbar
            </h4>
            <p class="text-xs text-gray-600 leading-relaxed font-normal">
                Untuk menjamin kualitas hafalan yang kuat (*mutqin*), santri wajib mengikuti ujian Tasmi' Bil Ghaib (membaca tanpa melihat mushaf) di depan penguji asatidzah dan halaqah santri dengan ketentuan sekali duduk:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
                <div class="bg-cream/40 p-4 rounded-2xl border border-gray-150 space-y-1">
                    <span class="block text-[9px] font-bold text-gray-400 uppercase">Tahap 1</span>
                    <strong class="text-primary text-sm block">Tasmik 5 Juz</strong>
                    <span class="block text-[9px] text-gray-500">Sekali Duduk</span>
                </div>
                <div class="bg-cream/40 p-4 rounded-2xl border border-gray-150 space-y-1">
                    <span class="block text-[9px] font-bold text-gray-400 uppercase">Tahap 2</span>
                    <strong class="text-primary text-sm block">Tasmik 10 Juz</strong>
                    <span class="block text-[9px] text-gray-500">Sekali Duduk</span>
                </div>
                <div class="bg-cream/40 p-4 rounded-2xl border border-gray-150 space-y-1">
                    <span class="block text-[9px] font-bold text-gray-400 uppercase">Tahap 3</span>
                    <strong class="text-primary text-sm block">Tasmik 20 Juz</strong>
                    <span class="block text-[9px] text-gray-500">Sekali Duduk</span>
                </div>
                <div class="bg-cream/40 p-4 rounded-2xl border border-gray-150 space-y-1 bg-gradient-to-br from-secondary/15 to-transparent">
                    <span class="block text-[9px] font-bold text-secondary uppercase">Tahap Puncak</span>
                    <strong class="text-primary text-sm block">Tasmik Akbar 30 Juz</strong>
                    <span class="block text-[9px] text-gray-500">Sekali Duduk (Syahadah)</span>
                </div>
            </div>
        </div>

        <!-- Fasilitas Khusus Takhasus -->
        <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-6">
            <h4 class="font-extrabold text-primary text-base flex items-center border-b border-gray-100 pb-3">
                <i class="fa-solid fa-award text-secondary mr-2.5"></i> Fasilitas Pendukung Khusus Santri Takhasus
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs text-gray-600 leading-relaxed font-normal">
                <ul class="space-y-3">
                    <li class="flex items-start">
                        <i class="fa-solid fa-check-circle text-success mr-2.5 text-sm mt-0.5"></i>
                        <span><strong>Gazebo Tahfizh Terbuka:</strong> Area taman sunyi dilengkapi gazebo bambu yang sejuk guna memberikan kenyamanan santri saat menghafal di luar ruangan.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fa-solid fa-check-circle text-success mr-2.5 text-sm mt-0.5"></i>
                        <span><strong>Halaqah Hall Semi-Indoor:</strong> Aula khusus yang tenang dan steril dari kebisingan untuk melangsungkan setoran tahfizh pagi, siang, dan malam.</span>
                    </li>
                </ul>
                <ul class="space-y-3">
                    <li class="flex items-start">
                        <i class="fa-solid fa-check-circle text-success mr-2.5 text-sm mt-0.5"></i>
                        <span><strong>Mentor Bersanad Khusus:</strong> Dibimbing langsung oleh para asatidzah Hafizh Al-Qur'an 30 Juz yang memiliki ijazah sanad riwayat bersambung ke Rasulullah SAW.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fa-solid fa-check-circle text-success mr-2.5 text-sm mt-0.5"></i>
                        <span><strong>Dapur Gizi Seimbang:</strong> Nutrisi makan harian santri dikontrol secara ketat guna menunjang stamina otak dan ketahanan fisik selama menghafal seharian.</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- CTA pendaftaran -->
        <div class="bg-primary text-white p-8 rounded-3xl text-center space-y-4 shadow-xl border border-secondary/20 max-w-4xl mx-auto">
            <h3 class="text-lg md:text-xl font-extrabold">Tertarik Mendaftarkan Putra-Putri Anda di Program Takhasus?</h3>
            <p class="text-xs text-gray-300 max-w-xl mx-auto leading-relaxed">
                Kuota sangat terbatas hanya 30 santri terpilih per tahun ajaran. Seleksi masuk meliputi kelancaran membaca Al-Qur'an dan daya ingat.
            </p>
            <div class="pt-2">
                <a href="daftar.php" class="inline-block bg-secondary hover:bg-white hover:text-primary text-white font-bold px-8 py-3 rounded-xl transition duration-300 text-xs shadow-md">Daftar Takhasus Sekarang <i class="fa-solid fa-chevron-right ml-1"></i></a>
            </div>
        </div>

    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
