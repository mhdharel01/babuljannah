<?php
// sma.php
require_once 'config/db.php';
$page_title = "SMA IT Babul Jannah - Pondok Pesantren Babul Jannah Dumai";
$meta_desc = "Program pendidikan unggulan SMA IT Babul Jannah Dumai dengan fokus kurikulum terpadu nasional, kitab kuning salafiyah, tahfizh Al-Qur'an 6 juz, dan persiapan seleksi PTN.";
require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/IMG-20241023-WA0081.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>
    
    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">SMA IT Babul Jannah</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Mempersiapkan calon intelektual muslim berwawasan global, menguasai kitab kuning salafiyah, hafalan Al-Qur'an (6 Juz), dan sukses lolos seleksi Perguruan Tinggi (PTN/LN).
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
                    <img src="assets/images/IMG-20241023-WA0081.webp" alt="SMA IT Babul Jannah Campus" class="w-full h-[320px] object-cover rounded-2xl bg-gray-50">
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/40 to-transparent"></div>
                </div>
            </div>
            <div class="lg:col-span-6 space-y-6">
                <span class="text-secondary font-bold text-xs uppercase tracking-wider block">Program Akademik</span>
                <h2 class="text-2xl md:text-3xl font-extrabold text-primary">Persiapan Karir & Kepemimpinan Islam</h2>
                <div class="w-12 h-1 bg-secondary rounded"></div>
                <p class="text-xs md:text-sm text-gray-600 leading-relaxed">
                    SMA IT Babul Jannah menyelenggarakan pendidikan tingkat menengah atas terpadu dengan pilihan jurusan **IPA (Sains)** dan **IPS (Sosial)**. Fokus utama kurikulum SMA adalah membekali santri agar siap menghadapi persaingan dunia akademis tinggi (UTBK/SNBT, seleksi beasiswa luar negeri seperti Universitas Al-Azhar Kairo atau Madinah) dengan landasan spiritual yang teguh melalui penguasaan kitab kuning salafiyah.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Tahfizh Qur'an</span>
                        <strong class="text-primary text-sm block">Target Minimal 6 Juz</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Jurusan Pilihan</span>
                        <strong class="text-primary text-sm block">IPA & IPS Terakreditasi A</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Bimbingan Khusus</span>
                        <strong class="text-primary text-sm block">Kelas Intensif UTBK & PTN</strong>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-gray-150 space-y-1">
                        <span class="block text-[9px] font-bold text-secondary uppercase tracking-widest">Bahasa Wajib</span>
                        <strong class="text-primary text-sm block">Aktif Arab & Inggris</strong>
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
                                    <td class="py-3 px-3 font-semibold text-primary">Tahfizh Al-Qur'an</td>
                                    <td class="py-3 px-3 text-center">Setoran Juz 1 - 6 / Murajaah Mandiri</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Tafsir Al-Qur'an</td>
                                    <td class="py-3 px-3 text-center">Tafsir Jalalain (Karya Imam Jalaluddin)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Fiqih Muamalah & Ibadah</td>
                                    <td class="py-3 px-3 text-center">Kitab Fathul Qorib Al-Mujib (Karya Ibnu Qosim)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Hadits Hukum</td>
                                    <td class="py-3 px-3 text-center">Kitab Bulughul Maram (Karya Ibnu Hajar)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Nahwu Tingkat Lanjut</td>
                                    <td class="py-3 px-3 text-center">Kitab Al-Fiyah Ibnu Malik (Bait Pilihan)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Balaghah & Sirah</td>
                                    <td class="py-3 px-3 text-center">Kajian Sastra Arab & Sejarah Nabawiyah</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kurikulum Nasional (IPA/IPS) -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-6">
                    <h4 class="font-extrabold text-primary text-base flex items-center border-b border-gray-100 pb-3">
                        <i class="fa-solid fa-graduation-cap text-secondary mr-2.5"></i> Kurikulum Nasional & UTBK
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600">
                            <thead>
                                <tr class="bg-cream/40 text-primary border-b border-gray-200">
                                    <th class="py-2.5 px-3 font-extrabold">Mata Pelajaran</th>
                                    <th class="py-2.5 px-3 font-extrabold text-center">Program IPA / IPS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Matematika</td>
                                    <td class="py-3 px-3 text-center">Matematika Wajib & Kalkulus Peminatan (IPA)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Fisika & Sosiologi</td>
                                    <td class="py-3 px-3 text-center">Mekanika & Termodinamika (IPA) / Struktur Sosial (IPS)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Kimia & Geografi</td>
                                    <td class="py-3 px-3 text-center">Stoikiometri & Kimia Organik (IPA) / Litosfer & Kartografi (IPS)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Biologi & Ekonomi</td>
                                    <td class="py-3 px-3 text-center">Genetika & Ekologi (IPA) / Akuntansi & Mikro-Makro (IPS)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Bahasa Inggris Academics</td>
                                    <td class="py-3 px-3 text-center">Persiapan Ujian TOEFL / IELTS & Reading Comprehension</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-3 font-semibold text-primary">Bimbingan UTBK</td>
                                    <td class="py-3 px-3 text-center">Latihan Tryout SNBT, Penalaran Matematika, & TPS harian</td>
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
                    <i class="fa-solid fa-home text-secondary mr-2.5"></i> Pembinaan Asrama Mandiri
                </h4>
                <p class="text-xs text-gray-500 leading-relaxed font-normal">
                    Santri tingkat SMA IT menempati asrama mandiri dengan fasilitas lemari individu dan AC. Pembinaan difokuskan pada kedewasaan akhlak, kemampuan berorganisasi (*Ikatan Pelajar Pesantren*), kedisiplinan mengelola jadwal harian secara mandiri, latihan kepemimpinan khutbah jumat, serta bimbingan konseling karir masa depan oleh psikolog pesantren.
                </p>
            </div>

            <!-- Ekstrakurikuler -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-250 shadow-sm space-y-4">
                <h4 class="font-extrabold text-primary text-base border-b border-gray-100 pb-3 flex items-center">
                    <i class="fa-solid fa-users-gear text-secondary mr-2.5"></i> Kegiatan Ekstrakurikuler SMA
                </h4>
                <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-primary">
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-bullseye text-secondary mr-2"></i> Panahan & Berkuda</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-laptop-code text-secondary mr-2"></i> Club Robotik & TI</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-newspaper text-secondary mr-2"></i> Jurnalistik & Sastra</div>
                    <div class="bg-cream/40 p-3.5 rounded-xl border border-gray-150 flex items-center"><i class="fa-solid fa-shield text-secondary mr-2"></i> Pencak Silat Tapak Suci</div>
                </div>
            </div>

        </div>

        <!-- CTA pendaftaran -->
        <div class="bg-primary text-white p-8 rounded-3xl text-center space-y-4 shadow-xl border border-secondary/20 max-w-4xl mx-auto">
            <h3 class="text-lg md:text-xl font-extrabold">Tertarik Mendaftarkan Putra-Putri Anda di SMA IT?</h3>
            <p class="text-xs text-gray-300 max-w-xl mx-auto leading-relaxed">
                Kuota terbatas hanya 60 santri per tahun ajaran baru. Segera daftarkan putra-putri Anda untuk mengikuti seleksi online.
            </p>
            <div class="pt-2">
                <a href="daftar.php" class="inline-block bg-secondary hover:bg-white hover:text-primary text-white font-bold px-8 py-3 rounded-xl transition duration-300 text-xs shadow-md">Daftar SMA IT Sekarang <i class="fa-solid fa-chevron-right ml-1"></i></a>
            </div>
        </div>

    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
