<?php
// cetak_kelulusan.php
require_once 'config/db.php';

$no_reg = sanitize($_GET['no_reg'] ?? '');
$phone = sanitize($_GET['phone'] ?? '');

$is_admin = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_id']);
$is_verified_session = !empty($_SESSION['verified_reg_' . $no_reg]);

if (empty($no_reg)) {
    die("Akses ditolak: Parameter Nomor Registrasi tidak lengkap.");
}

if (!$is_admin && !$is_verified_session && empty($phone)) {
    die("Akses ditolak: Nomor telepon orang tua wajib disertakan untuk verifikasi.");
}

// Ambil data pendaftaran
if (!empty($phone)) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $alt_phone = (substr($clean_phone, 0, 1) === '0') ? ('62' . substr($clean_phone, 1)) : ((substr($clean_phone, 0, 2) === '62') ? ('0' . substr($clean_phone, 2)) : $clean_phone);
    $stmt = $db->prepare("SELECT p.*, g.nama_gelombang, g.tahun_ajaran, g.tgl_ujian, g.tgl_pengumuman FROM pendaftaran p JOIN gelombang g ON p.gelombang_id = g.id WHERE p.no_registrasi = :no_reg AND (p.wa_ayah IN (:p1, :p2) OR p.wa_ibu IN (:p1, :p2) OR p.wa_wali IN (:p1, :p2))");
    $stmt->execute(['no_reg' => $no_reg, 'p1' => $clean_phone, 'p2' => $alt_phone]);
} else {
    $stmt = $db->prepare("SELECT p.*, g.nama_gelombang, g.tahun_ajaran, g.tgl_ujian, g.tgl_pengumuman FROM pendaftaran p JOIN gelombang g ON p.gelombang_id = g.id WHERE p.no_registrasi = :no_reg");
    $stmt->execute(['no_reg' => $no_reg]);
}
$pendaftar = $stmt->fetch();

if (!$pendaftar) {
    die("Akses ditolak: Data pendaftar tidak ditemukan.");
}

// Cek apakah lulus
if ($pendaftar['status_seleksi'] !== 'Lulus') {
    die("Akses ditolak: Calon santri belum dinyatakan lulus.");
}

// Cek jadwal pengumuman (admin bebas akses)
$tgl_sekarang = date('Y-m-d');
if (!$is_admin && $pendaftar['tgl_pengumuman'] && $pendaftar['tgl_pengumuman'] !== '0000-00-00' && $tgl_sekarang < $pendaftar['tgl_pengumuman']) {
    die("Akses ditolak: Pengumuman kelulusan belum dibuka.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Kelulusan - <?= $pendaftar['no_registrasi'] ?></title>
    <!-- Tailwind CSS v3 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3E2723',
                        secondary: '#8D6E63',
                        accent: '#F5F0EB',
                        dark: '#212121'
                    }
                }
            }
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        @page {
            margin: 0.8cm;
            size: A4 portrait;
        }
        @media print {
            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 pt-28 pb-10 px-4 screen-view">

    <!-- Sticky Preview Header Bar (Hidden on Print) -->
    <div id="preview-header" class="no-print fixed top-0 left-0 right-0 bg-white border-b border-gray-200 px-6 py-4 flex flex-col md:flex-row justify-between items-center shadow-sm z-50">
        <div class="flex items-center space-x-3 mb-3 md:mb-0">
            <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center text-primary">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <div>
                <h4 class="font-extrabold text-xs text-primary uppercase tracking-wider">Pratinjau Cetak: Surat Kelulusan</h4>
                <p class="text-[10px] text-gray-500 font-medium">Klik tombol Unduh Langsung (PDF) untuk mengunduh file bersih tanpa URL/header browser.</p>
            </div>
        </div>
        <div class="flex space-x-3">
            <button id="btn-unduh-pdf" onclick="unduhPDFLangsung();" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-xl shadow transition text-xs flex items-center space-x-2">
                <i class="fa-solid fa-file-pdf"></i>
                <span>Unduh Langsung (PDF)</span>
            </button>
            <button onclick="window.print();" class="bg-primary hover:bg-secondary text-white font-bold px-5 py-2.5 rounded-xl shadow transition text-xs flex items-center space-x-2">
                <i class="fa-solid fa-print"></i>
                <span>Cetak Printer</span>
            </button>
            <button onclick="window.close();" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-5 py-2.5 rounded-xl border border-gray-200 transition text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-xmark"></i>
                <span>Tutup</span>
            </button>
        </div>
    </div>

    <div class="w-[794px] max-w-full mx-auto shadow-2xl my-6 bg-white">
        <div id="printable-area" class="print-container w-full bg-white px-10 py-12 relative">

            <!-- Kop Surat Resmi -->
            <div class="flex items-center justify-between border-b-4 border-double border-primary pb-5 mb-6">
                <div class="w-20 h-20 flex-shrink-0 flex items-center justify-center">
                    <img src="assets/images/DESAIG LOGO BABUL JANNAH.webp" alt="Logo PP Babul Jannah" class="w-20 h-20 object-contain">
                </div>
                <div class="text-center flex-grow px-4">
                    <h1 class="text-xs font-bold text-gray-700 tracking-wider uppercase">Kementerian Agama Republik Indonesia</h1>
                    <h2 class="text-sm font-extrabold text-primary tracking-wide uppercase">Panitia Penerimaan Santri Baru (PSB)</h2>
                    <h3 class="text-xl font-black text-primary tracking-tight">PONDOK PESANTREN BABUL JANNAH</h3>
                    <p class="text-[11px] text-gray-600 font-medium mt-1">Putra: Jl. Sidorejo No.02 | Putri: Jl. Sidorejo No.22, Kel. Ratu Sima, Dumai Selatan, Dumai, Riau</p>
                    <p class="text-[10px] text-gray-500 italic">Telp/WA: 0811-7078-9988 | Email: pondokpesantrenbabuljannah2023@gmail.com</p>
                </div>
                <div class="w-20 h-20 flex-shrink-0 flex items-center justify-center">
                    <img src="assets/images/logo-kemenag-png-1png.webp" alt="Logo Kemenag" class="w-20 h-20 object-contain">
                </div>
            </div>

            <!-- Judul Dokumen -->
            <div class="text-center space-y-1 mb-10">
                <h3 class="text-lg font-extrabold text-dark uppercase tracking-wider underline">SURAT KEPUTUSAN HASIL SELEKSI</h3>
                <p class="text-xs text-gray-700 font-semibold">Nomor: B-<?= date('m.d', strtotime($pendaftar['created_at'])) ?>/PSB-BJ/<?= date('Y') ?></p>
            </div>

            <div class="space-y-4 text-sm text-gray-800 leading-relaxed text-justify mb-8">
                <p>Assalamu'alaikum Warahmatullahi Wabarakatuh,</p>
                <p>
                    Berdasarkan hasil tes tertulis, bacaan/hafalan Al-Qur'an, dan wawancara calon santri baru Pondok Pesantren Babul Jannah Tahun Ajaran <?= htmlspecialchars($pendaftar['tahun_ajaran']) ?>, maka Panitia Penerimaan Santri Baru (PSB) memutuskan bahwa:
                </p>
                
                <table class="w-full mt-4 mb-4 ml-4">
                    <tr>
                        <td class="w-1/3 py-1 font-semibold">Nomor Registrasi</td>
                        <td class="w-4 py-1">:</td>
                        <td class="w-2/3 py-1 font-bold"><?= htmlspecialchars($pendaftar['no_registrasi']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">Nama Lengkap</td>
                        <td class="py-1">:</td>
                        <td class="py-1 font-bold text-base"><?= htmlspecialchars($pendaftar['nama_santri']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">NISN</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= htmlspecialchars($pendaftar['nisn']) ?: '-' ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">Asal Sekolah</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= htmlspecialchars($pendaftar['nama_sekolah_asal']) ?></td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">Jalur Pendaftaran</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= htmlspecialchars($pendaftar['jalur_pendaftaran']) ?></td>
                    </tr>
                </table>

                <p>
                    Dengan ini dinyatakan <strong class="text-emerald-700 text-lg uppercase tracking-wider underline">L U L U S</strong> dan diterima sebagai calon santri baru Pondok Pesantren Babul Jannah.
                </p>

                <p class="mt-4">
                    Sehubungan dengan hal tersebut, kami mohon kepada Orang Tua/Wali santri untuk segera melakukan <strong>Konfirmasi Daftar Ulang</strong> selambat-lambatnya 7 (tujuh) hari setelah surat pengumuman ini diterbitkan. Apabila sampai batas waktu yang ditentukan tidak melakukan daftar ulang, maka calon santri dianggap mengundurkan diri.
                </p>

                <p class="mt-2">
                    Demikian surat keputusan ini dibuat untuk diketahui dan dipergunakan sebagaimana mestinya. Atas perhatian dan kepercayaan Bapak/Ibu, kami ucapkan jazakumullah khairan katsiran.
                </p>
                <p>Wassalamu'alaikum Warahmatullahi Wabarakatuh.</p>
            </div>

            <!-- Tanda Tangan Form -->
            <div class="grid grid-cols-2 gap-12 text-center text-sm mt-16 pt-8">
                <div class="space-y-20">
                    <p></p>
                    <div class="w-48 mx-auto font-bold uppercase text-dark"></div>
                </div>
                <div class="space-y-20 relative">
                    <p>Dumai, <?= format_tanggal(date('Y-m-d')) ?><br>Ketua Panitia PSB,</p>
                    
                    <!-- Placeholder untuk stempel dan tanda tangan -->
                    <div class="absolute left-1/2 top-10 transform -translate-x-1/2 opacity-30 text-emerald-600 text-6xl no-print">
                        <i class="fa-solid fa-stamp"></i>
                    </div>

                    <div class="w-48 border-b border-gray-700 mx-auto font-bold">( Ust. Syihabuddin Al-Hafizh )</div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function unduhPDFLangsung(autoClose = false) {
            const btn = document.getElementById('btn-unduh-pdf');
            const originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Sedang Mengunduh...</span>';
                btn.disabled = true;
            }

            const element = document.getElementById('printable-area');
            const opt = {
                margin:       0,
                filename:     'Surat_Kelulusan_<?= $pendaftar['no_registrasi'] ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollX: 0, scrollY: 0 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                if (btn) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i><span>Berhasil Diunduh!</span>';
                    btn.disabled = false;
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                    }, 3000);
                }
                if (autoClose) {
                    window.close();
                }
            });
        }
        
        // Jika dipanggil dengan parameter &download=1, otomatis langsung mengunduh
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('download') === '1') {
                const header = document.getElementById('preview-header');
                if (header) header.style.display = 'none'; // Sembunyikan header
                
                setTimeout(() => {
                    unduhPDFLangsung(true);
                }, 800);
            }
        });
    </script>
</body>
</html>
