<?php
// daftar.php
require_once 'config/db.php';

// Ambil gelombang prioritas (sedang buka -> segera buka -> sudah lewat)
$stmt = $db->prepare("SELECT * FROM gelombang WHERE status = 'aktif' ORDER BY CASE WHEN NOW() BETWEEN tgl_buka AND CONCAT(tgl_tutup, ' 23:59:59') THEN 1 WHEN NOW() < tgl_buka THEN 2 ELSE 3 END ASC, tgl_buka ASC LIMIT 1");
$stmt->execute();
$gelombang_aktif = $stmt->fetch();

$gelombang_status_waktu = 'closed';
if ($gelombang_aktif) {
    $now = time();
    $tgl_buka = strtotime($gelombang_aktif['tgl_buka']);
    $tgl_tutup = strtotime($gelombang_aktif['tgl_tutup'] . ' 23:59:59');

    if ($now < $tgl_buka) {
        $gelombang_status_waktu = 'upcoming';
    } elseif ($now > $tgl_tutup) {
        $gelombang_status_waktu = 'closed';
    } else {
        $gelombang_status_waktu = 'open';
    }
}

// Ambil semua gelombang aktif untuk ditampilkan sebagai jadwal, beserta total pendaftar
$stmt_all_gelombang = $db->query("SELECT g.*, (SELECT COUNT(id) FROM pendaftaran p WHERE p.gelombang_id = g.id) as total_pendaftar FROM gelombang g WHERE status = 'aktif' ORDER BY tgl_buka ASC");
$semua_gelombang = $stmt_all_gelombang->fetchAll();



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

$page_title = "Informasi Pendaftaran & Biaya Masuk - Pondok Pesantren Babul Jannah";
$meta_desc = "Informasi alur pendaftaran, jadwal gelombang, persyaratan berkas, materi ujian seleksi, dan rincian biaya pendidikan Pondok Pesantren Babul Jannah Dumai.";
require_once 'includes/header.php';
?>

<!-- Header Banner -->
<section class="relative bg-primary pt-36 pb-20 overflow-hidden text-center text-white no-print" data-aos="fade-down">
    <!-- Subtle Background Image Overlay -->
    <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay">
    </div>
    <div class="absolute inset-0 bg-gradient-to-b from-primary/30 to-primary/95"></div>

    <div class="relative max-w-4xl mx-auto px-4 space-y-4">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight font-sans">Informasi Pendaftaran</h1>
        <p class="text-xs md:text-sm text-gray-300 leading-relaxed max-w-2xl mx-auto">
            Pelajari alur pendaftaran, materi seleksi, persyaratan dokumen, dan rincian biaya masuk sebelum mengisi
            formulir online.
        </p>
    </div>
</section>

<!-- Informasi PSB Section -->
<section class="py-12 bg-accent flex-grow no-print" data-aos="fade-up">
    <div class="max-w-6xl mx-auto px-4">
        <?php if ($gelombang_aktif && $gelombang_status_waktu === 'open'): ?>
        <!-- Informasi Gelombang Aktif Banner -->
        <div class="bg-primary text-white rounded-3xl p-6 md:p-8 shadow-md mb-10 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                <div class="space-y-2">
                    <span class="text-secondary font-bold text-[10px] uppercase tracking-widest block">Gelombang Aktif</span>
                    <h3 class="text-xl md:text-2xl font-extrabold text-white font-sans"><?= htmlspecialchars($gelombang_aktif['nama_gelombang']) ?> (T.A <?= htmlspecialchars($gelombang_aktif['tahun_ajaran']) ?>)</h3>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Masa pendaftaran dibuka dari tanggal <span class="font-bold text-white"><?= format_tanggal($gelombang_aktif['tgl_buka']) ?></span> s.d <span class="font-bold text-white"><?= format_tanggal($gelombang_aktif['tgl_tutup']) ?></span>.
                    </p>

                    <?php if (!empty($gelombang_aktif['tgl_ujian']) || !empty($gelombang_aktif['tgl_pengumuman'])): ?>
                    <div class="flex flex-wrap gap-3 mt-2">
                        <?php if (!empty($gelombang_aktif['tgl_ujian'])): ?>
                        <span class="inline-flex items-center space-x-1.5 bg-white/10 border border-white/15 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg">
                            <i class="fa-solid fa-calendar-day text-secondary"></i>
                            <span>Ujian: <?= format_tanggal($gelombang_aktif['tgl_ujian']) ?></span>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($gelombang_aktif['tgl_pengumuman'])): ?>
                        <span class="inline-flex items-center space-x-1.5 bg-white/10 border border-white/15 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg">
                            <i class="fa-solid fa-bullhorn text-secondary"></i>
                            <span>Pengumuman: <?= format_tanggal($gelombang_aktif['tgl_pengumuman']) ?></span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="formulir.php" class="bg-secondary hover:bg-white hover:text-primary text-white font-extrabold px-6 py-3.5 rounded-xl shadow transition duration-300 transform hover:-translate-y-0.5 text-xs flex items-center space-x-2">
                    <span>Mulai Isi Formulir Sekarang</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>



        <!-- Jadwal Seluruh Gelombang -->
        <?php if (!empty($semua_gelombang)): ?>
            <div class="mb-16">
                <div class="text-center space-y-2 mb-8">
                    <span class="text-secondary font-bold text-[10px] uppercase tracking-widest block">Informasi Waktu</span>
                    <h3 class="text-2xl font-extrabold text-primary font-sans">Jadwal Seluruh Gelombang</h3>
                    <p class="text-xs text-red-500 font-semibold max-w-xl mx-auto bg-red-50 py-2 px-4 rounded-lg border border-red-100">
                        <i class="fa-solid fa-circle-info mr-1"></i> Catatan: Jika kuota (100 SMP & 30 SMA) sudah terpenuhi pada Gelombang 1, maka Gelombang berikut nya otomatis <span class="font-bold underline">tidak akan dibuka</span>.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($semua_gelombang as $gel):
                        $now_g = time();
                        $buka_g = strtotime($gel['tgl_buka']);
                        $tutup_g = strtotime($gel['tgl_tutup'] . ' 23:59:59');

                        if ($gel['status'] == 'nonaktif') {
                            $s_color = 'bg-gray-100 text-gray-500 border-gray-200';
                            $s_text = 'Ditutup / Selesai';
                            $s_icon = 'fa-lock';
                        } elseif ($now_g < $buka_g) {
                            $s_color = 'bg-amber-50 text-amber-600 border-amber-200';
                            $s_text = 'Segera Buka';
                            $s_icon = 'fa-clock';
                        } elseif ($now_g > $tutup_g) {
                            $s_color = 'bg-red-50 text-red-600 border-red-200';
                            $s_text = 'Pendaftaran Berakhir';
                            $s_icon = 'fa-calendar-xmark';
                        } else {
                            $s_color = 'bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm';
                            $s_text = 'Sedang Buka';
                            $s_icon = 'fa-door-open';
                        }
                        ?>
                        <div
                            class="bg-white border border-gray-150 rounded-3xl p-6 hover:border-secondary/50 transition-colors duration-300">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="font-extrabold text-primary text-lg"><?= htmlspecialchars($gel['nama_gelombang']) ?></h4>
                                    <?php if ($gel['total_pendaftar'] > 0 && $now_g >= $buka_g): ?>
                                    <div class="text-[10px] font-bold text-gray-500 mt-1"><i class="fa-solid fa-users mr-1"></i> <?= $gel['total_pendaftar'] ?> Pendaftar</div>
                                    <?php endif; ?>
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2.5 py-1 rounded-lg border <?= $s_color ?> flex items-center space-x-1">
                                    <i class="fa-solid <?= $s_icon ?>"></i>
                                    <span><?= $s_text ?></span>
                                </span>
                            </div>

                            <div class="space-y-3 text-xs text-gray-600">
                                <div class="flex items-start space-x-3">
                                    <div
                                        class="w-6 h-6 rounded-full bg-cream flex items-center justify-center text-secondary mt-0.5">
                                        <i class="fa-solid fa-calendar-check text-[10px]"></i></div>
                                    <div>
                                        <span class="block text-[9px] font-bold text-gray-400 uppercase tracking-wider">Masa
                                            Pendaftaran</span>
                                        <span class="font-medium"><?= format_tanggal($gel['tgl_buka']) ?> -
                                            <?= format_tanggal($gel['tgl_tutup']) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($gel['tgl_ujian'])): ?>
                                    <div class="flex items-start space-x-3">
                                        <div
                                            class="w-6 h-6 rounded-full bg-cream flex items-center justify-center text-secondary mt-0.5">
                                            <i class="fa-solid fa-pen-to-square text-[10px]"></i></div>
                                        <div>
                                            <span class="block text-[9px] font-bold text-gray-400 uppercase tracking-wider">Tanggal
                                                Ujian</span>
                                            <span class="font-medium"><?= format_tanggal($gel['tgl_ujian']) ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gel['tgl_pengumuman'])): ?>
                                    <div class="flex items-start space-x-3">
                                        <div
                                            class="w-6 h-6 rounded-full bg-cream flex items-center justify-center text-secondary mt-0.5">
                                            <i class="fa-solid fa-bullhorn text-[10px]"></i></div>
                                        <div>
                                            <span
                                                class="block text-[9px] font-bold text-gray-400 uppercase tracking-wider">Pengumuman</span>
                                            <span class="font-medium"><?= format_tanggal($gel['tgl_pengumuman']) ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div> 
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Alur & Proses Seleksi (Horizontal Roadmap Stepper) -->
        <div class="mb-16">
            <div class="text-center space-y-2 mb-10">
                <span class="text-secondary font-bold text-[10px] uppercase tracking-widest block">Langkah
                    Registrasi</span>
                <h3 class="text-2xl font-extrabold text-primary font-sans">Tahapan & Proses Seleksi Calon Santri</h3>
                </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 relative">
                <!-- Step 1 -->
                <div class="flex flex-col items-center text-center space-y-3 relative group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white border border-gray-150 flex items-center justify-center text-primary font-serif text-lg font-bold shadow-sm group-hover:border-secondary group-hover:text-secondary transition duration-300">
                        01</div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-primary">Isi Formulir Online</h4>
                        <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Isi lengkap formulir data
                            santri & wali melalui sistem PSB.</p>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="flex flex-col items-center text-center space-y-3 relative group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white border border-gray-150 flex items-center justify-center text-primary font-serif text-lg font-bold shadow-sm group-hover:border-secondary group-hover:text-secondary transition duration-300">
                        02</div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-primary">Biaya Tes & Slip</h4>
                        <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Transfer administrasi
                            <?= format_rupiah($biaya_tes_global) ?> & unggah bukti transfer.</p>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="flex flex-col items-center text-center space-y-3 relative group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white border border-gray-150 flex items-center justify-center text-primary font-serif text-lg font-bold shadow-sm group-hover:border-secondary group-hover:text-secondary transition duration-300">
                        03</div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-primary">Cetak Ujian & Tes</h4>
                        <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Unduh Kartu Peserta Ujian lalu
                            ikuti ujian seleksi di pondok.</p>
                    </div>
                </div>
                <!-- Step 4 -->
                <div class="flex flex-col items-center text-center space-y-3 relative group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white border border-gray-150 flex items-center justify-center text-primary font-serif text-lg font-bold shadow-sm group-hover:border-secondary group-hover:text-secondary transition duration-300">
                        04</div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-primary">Cek Hasil Seleksi</h4>
                        <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Hasil kelulusan diumumkan
                            secara transparan di dashboard.</p>
                    </div>
                </div>
                <!-- Step 5 -->
                <div class="flex flex-col items-center text-center space-y-3 relative group">
                    <div
                        class="w-14 h-14 rounded-2xl bg-primary text-white flex items-center justify-center font-serif text-lg font-bold shadow-md transition duration-300">
                        05</div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-primary">Daftar Ulang Resmi</h4>
                        <p class="text-[10px] text-gray-500 leading-relaxed font-normal">Pelunasan biaya sarana &
                            seragam bagi yang lulus.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Persyaratan & Ujian (Premium 2-Column Split Section) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch mb-16">
            <!-- Left: Syarat Dokumen (Editorial Clean List) -->
            <div
                class="lg:col-span-6 bg-cream/35 border border-secondary/15 rounded-3xl p-8 flex flex-col justify-between">
                <div class="space-y-5">
                    <div class="space-y-2">
                        <span
                            class="text-secondary font-bold text-[9px] uppercase tracking-wider block">Kelengkapan</span>
                        <h4 class="font-extrabold text-primary text-lg font-sans flex items-center">
                            <i class="fa-solid fa-folder-open mr-2 text-secondary"></i> Persyaratan Dokumen Fisik
                        </h4>
                        <div class="w-10 h-0.5 bg-secondary rounded"></div>
                    </div>
                    <p class="text-[11px] text-gray-550 leading-relaxed font-normal">
                        Seluruh berkas administrasi di bawah wajib disiapkan oleh orang tua/wali dan diserahkan ke
                        panitia PSB saat pelaksanaan ujian seleksi luring di pesantren:
                    </p>
                    <ul class="text-xs text-gray-700 space-y-3 pt-2 font-normal">
                        <li class="flex items-center space-x-3">
                            <span
                                class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold"><i
                                    class="fa-solid fa-check"></i></span>
                            <span>1 Lembar Fotokopi Kartu Keluarga (KK)</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span
                                class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold"><i
                                    class="fa-solid fa-check"></i></span>
                            <span>1 Lembar Fotokopi Akta Kelahiran Calon Santri</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span
                                class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold"><i
                                    class="fa-solid fa-check"></i></span>
                            <span>3 Lembar Pas Foto Resmi 3x4 (Latar Belakang Biru)</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span
                                class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold"><i
                                    class="fa-solid fa-check"></i></span>
                            <span>Fotokopi Rapor Semester Terakhir dari Sekolah Asal</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span
                                class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold"><i
                                    class="fa-solid fa-check"></i></span>
                            <span>Surat Keterangan Sehat asli dari Dokter/Klinik</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right: Ujian & Materi (Bulletin Block) -->
            <div class="lg:col-span-6 bg-white border border-gray-150 rounded-3xl p-8 flex flex-col justify-between">
                <div class="space-y-5">
                    <div class="space-y-2">
                        <span
                            class="text-secondary font-bold text-[9px] uppercase tracking-wider block">Pelaksanaan</span>
                        <h4 class="font-extrabold text-primary text-lg font-sans flex items-center">
                            <i class="fa-solid fa-hotel mr-2 text-secondary"></i> Informasi & Materi Seleksi
                        </h4>
                        <div class="w-10 h-0.5 bg-secondary rounded"></div>
                    </div>

                    <div class="bg-accent/40 p-4 rounded-2xl border border-gray-100 text-xs">
                        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider block mb-1">Lokasi
                            Ujian Seleksi:</span>
                        <p class="font-bold text-primary">Kompleks Kampus Utama Pondok Pesantren Babul Jannah</p>
                        <p class="text-[10px] text-gray-500 leading-normal mt-0.5 mb-3.5">Putra: Jl. Sidorejo No.02 |
                            Putri: Jl. Sidorejo No.22, Kel. Ratu Sima, Dumai Selatan, Kota Dumai, Riau.</p>
                        <!-- Google Maps Embed -->
                        <div
                            class="w-full h-40 rounded-xl overflow-hidden shadow-inner border border-gray-200/60 no-print">
                            <iframe title="Peta Lokasi Pondok Pesantren Babul Jannah"
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1145.1093402620484!2d101.42512192766331!3d1.6659447718410978!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d3a923b39cb2a7%3A0x32ba105ad0aed175!2sPONDOK%20PESANTREN%20BABUL%20JANNAH!5e0!3m2!1sid!2sid!4v1784998158784!5m2!1sid!2sid"
                                class="w-full h-full border-0" allowfullscreen="" loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider block">Materi Ujian
                            (Tes Tulis & Lisan):</span>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs font-bold text-primary">
                            <div class="flex items-center space-x-2"><i
                                    class="fa-solid fa-circle text-[6px] text-secondary"></i> <span>Membaca Al-Qur'an
                                    (Tajwid)</span></div>
                            <div class="flex items-center space-x-2"><i
                                    class="fa-solid fa-circle text-[6px] text-secondary"></i> <span>Wawancara Santri &
                                    Ortu</span></div>
                            <div class="flex items-center space-x-2"><i
                                    class="fa-solid fa-circle text-[6px] text-secondary"></i> <span>Tes Pengetahuan
                                    Agama</span></div>
                            <div class="flex items-center space-x-2"><i
                                    class="fa-solid fa-circle text-[6px] text-secondary"></i> <span>Tes Akademik
                                    (Umum)</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: Investasi Pendidikan (Pricing Card Grid) -->
        <div class="mb-16">
            <div class="text-center space-y-2 mb-10">
                <span class="text-secondary font-bold text-[10px] uppercase tracking-widest block">Biaya Masuk</span>
                <h3 class="text-2xl font-extrabold text-primary font-sans">Rincian Investasi Pendidikan T.A 2026/2027
                </h3>
                </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <?php foreach ($master_biaya_list as $mb):
                    $is_unggulan = stripos($mb['jenjang'], 'SMA') !== false;
                    $border_class = $is_unggulan ? 'border-2 border-primary shadow-md lg:-translate-y-2' : 'border border-gray-150 shadow-sm hover:border-secondary';
                    $total = $mb['uang_pangkal'] + $mb['seragam'] + $mb['kegiatan'] + $mb['spp_bulanan'];
                    ?>
                    <!-- Plan: <?= htmlspecialchars($mb['jenjang']) ?> -->
                    <div
                        class="bg-white rounded-3xl <?= $border_class ?> overflow-hidden flex flex-col justify-between group transition duration-300 relative">
                        <?php if ($is_unggulan): ?>
                            <div class="bg-primary text-white text-[9px] font-bold uppercase tracking-wider text-center py-1.5">
                                Program Unggulan</div>
                        <?php endif; ?>
                        <div class="p-6 md:p-8 space-y-4 flex-grow">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Jenjang</span>
                                <span
                                    class="<?= $is_unggulan ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600' ?> text-[8px] font-extrabold uppercase px-2 py-0.5 rounded-full">Formal</span>
                            </div>
                            <h4 class="font-extrabold text-primary text-base"><?= htmlspecialchars($mb['jenjang']) ?></h4>
                            <div class="w-full h-px bg-gray-100"></div>
                            <div class="space-y-1">
                                <p class="text-2xl font-extrabold text-primary"><?= format_rupiah($total) ?></p>
                                <p class="text-[10px] text-gray-500 font-medium">Total Estimasi Masuk Awal</p>
                            </div>
                            <div
                                class="bg-accent/40 px-3.5 py-2.5 rounded-xl text-[10px] font-bold text-primary flex justify-between">
                                <span>SPP Bulanan</span>
                                <span class="text-secondary"><?= format_rupiah($mb['spp_bulanan']) ?></span>
                            </div>
                            <ul class="text-[11px] text-gray-550 leading-relaxed font-normal space-y-2.5 pt-2">
                                <li class="flex items-center space-x-2"><i
                                        class="fa-solid fa-circle-check text-secondary text-xs"></i> <span>Uang Pangkal /
                                        Pembangunan</span></li>
                                <li class="flex items-center space-x-2"><i
                                        class="fa-solid fa-circle-check text-secondary text-xs"></i> <span>Seragam &
                                        Perlengkapan: <?= format_rupiah($mb['seragam']) ?></span></li>
                                <li class="flex items-center space-x-2"><i
                                        class="fa-solid fa-circle-check text-secondary text-xs"></i> <span>Kegiatan per Semester: <?= format_rupiah($mb['kegiatan']) ?></span></li>
                                <li class="flex items-center space-x-2"><i
                                        class="fa-solid fa-circle-check text-secondary text-xs"></i>
                                    <span><?= htmlspecialchars($mb['deskripsi']) ?></span></li>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SECTION 4: Bottom CTA (Premium Action Banner) -->
        <div
            class="bg-primary text-white rounded-3xl p-8 md:p-12 text-center relative overflow-hidden shadow-xl max-w-4xl mx-auto">
            <!-- Backgroundsamaran -->
            <div
                class="absolute inset-0 bg-[url('assets/images/smp.webp')] bg-cover bg-center opacity-10 mix-blend-overlay">
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-primary via-primary/90 to-primary/40"></div>

            <div class="relative z-10 space-y-5 max-w-2xl mx-auto">
                <span class="text-secondary font-bold text-[9px] uppercase tracking-widest block">Mulai
                    Pendaftaran</span>
                <h4 class="font-extrabold text-white text-xl md:text-2xl font-sans">Sudah Memahami Prosedur & Syarat
                    PSB?</h4>
                <p class="text-xs text-gray-300 leading-relaxed">
                    Siapkan dokumen penting seperti NIK, NISN, Kartu Keluarga, dan nomor HP orang tua/wali sebelum
                    menekan tombol di bawah ini.
                </p>
                <div class="pt-4">
                    <?php if ($gelombang_aktif && $gelombang_status_waktu === 'open'): ?>
                        <a href="formulir.php"
                            class="inline-flex items-center space-x-3 bg-secondary hover:bg-white hover:text-primary text-white font-extrabold px-8 py-4 rounded-2xl shadow-lg transition duration-300 transform hover:-translate-y-0.5 text-xs">
                            <span>Lanjutkan Pengisian Formulir Pendaftaran</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <button disabled
                            class="inline-flex items-center space-x-3 bg-gray-500 text-white font-extrabold px-8 py-4 rounded-2xl shadow opacity-70 cursor-not-allowed text-xs">
                            <i class="fa-solid fa-lock"></i>
                            <span>Formulir Belum Tersedia</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'includes/footer.php';
?>