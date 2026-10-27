<?php
// cetak_formulir.php
require_once 'config/db.php';

$no_reg = sanitize($_GET['no_reg'] ?? '');
$phone = sanitize($_GET['phone'] ?? '');

if (empty($no_reg)) {
    die("Akses ditolak: Nomor Registrasi tidak boleh kosong.");
}

$is_admin = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_id']);
$is_verified_session = !empty($_SESSION['verified_reg_' . $no_reg]);

// Ambil data pendaftaran
if (!empty($phone) && !$is_admin && !$is_verified_session) {
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

// Cek apakah pembayaran sudah lunas (admin bebas akses)
if (!$is_admin && $pendaftar['status_pembayaran'] !== 'Lunas') {
    die("Akses ditolak: Pembayaran administrasi tes belum terverifikasi.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Pendaftaran - <?= $pendaftar['no_registrasi'] ?></title>
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
            margin: 0.4cm;
            size: A4 portrait;
        }
        table, tr, td, th, tbody, thead, tfoot {
            page-break-inside: avoid !important;
        }
        .avoid-break {
            page-break-inside: avoid;
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
    <div class="no-print fixed top-0 left-0 right-0 bg-white border-b border-gray-200 px-6 py-4 flex flex-col md:flex-row justify-between items-center shadow-sm z-50">
        <div class="flex items-center space-x-3 mb-3 md:mb-0">
            <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center text-primary">
                <i class="fa-solid fa-file-invoice text-lg"></i>
            </div>
            <div>
                <h4 class="font-extrabold text-xs text-primary uppercase tracking-wider">Pratinjau Cetak: Formulir Pendaftaran</h4>
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
        <div id="printable-area" class="print-container w-full bg-white px-8 py-4 relative">
        <!-- HTML Table wrapper for repeating header on print -->
        <table class="w-full border-none">
            <thead>
                <tr>
                    <td class="pb-4">
                        <!-- Kop Surat Resmi (Repeats on every printed page) -->
        <div class="flex items-center justify-between border-b-4 border-double border-primary pb-4 mb-4">
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
                    </td>
                </tr>
            </thead>
            
            <tbody>
                <tr>
                    <td>
                        <!-- Judul Dokumen -->
        <div class="text-center space-y-1 mb-3">
            <h3 class="text-[13px] font-bold text-dark uppercase tracking-wider">FORMULIR PENDAFTARAN SANTRI BARU</h3>
            <p class="text-[10px] text-gray-500 font-semibold">Tahun Ajaran <?= $pendaftar['tahun_ajaran'] ?></p>
        </div>

        <!-- Nomor Registrasi Box -->
        <div class="border border-gray-300 rounded-lg p-2 mb-3 bg-gray-50 flex justify-between items-center text-[11px]">
            <div>
                <span>Nomor Registrasi: </span>
                <strong class="text-primary text-sm tracking-wider font-extrabold"><?= $pendaftar['no_registrasi'] ?></strong>
            </div>
            <div>
                <span>Jalur Pendaftaran: </span>
                <strong class="text-secondary font-bold text-sm uppercase"><?= $pendaftar['jalur_pendaftaran'] ?></strong>
            </div>
            <div>
                <span>Status/Kategori: </span>
                <strong class="font-bold uppercase <?= (isset($pendaftar['kategori_santri']) && $pendaftar['kategori_santri'] === 'Pindahan') ? 'text-indigo-600' : '' ?>"><?= $pendaftar['kategori_santri'] ?? 'Baru' ?></strong>
            </div>
            <div>
                <span>Gelombang: </span>
                <strong class="font-bold"><?= $pendaftar['nama_gelombang'] ?></strong>
            </div>
        </div>

        <!-- Section 1: Data Calon Santri -->
        <div class="space-y-0.5 mb-2 avoid-break">
            <h4 class="font-bold text-primary border-b border-gray-300 pb-0.5 text-[11px] uppercase">I. DATA CALON SANTRI / SANTRIWATI</h4>
            <table class="w-full text-[11px] table-fixed">
                <tr class="align-top border-b border-gray-100">
                    <td class="w-1/4 font-semibold py-0.5">Nama Lengkap</td>
                    <td class="w-4 py-0.5">:</td>
                    <td class="w-3/4 py-0.5 uppercase font-bold text-dark"><?= $pendaftar['nama_santri'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">NIK (Nomor Induk Kependudukan)</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['nik_santri'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">NISN (Nomor Induk Siswa Nasional)</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['nisn'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Nomor Kartu Keluarga (KK)</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['no_kk'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Tempat & Tanggal Lahir</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['tempat_lahir'] ?>, <?= format_tanggal($pendaftar['tgl_lahir']) ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Jenis Kelamin</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['jk'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Status Anak</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5"><?= $pendaftar['status_anak'] ?: '-' ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Berkebutuhan Khusus / Penerima PIP</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5">Kebutuhan Khusus: <?= $pendaftar['berkebutuhan_khusus'] ?> | Penerima PIP: <?= $pendaftar['pip'] ?></td>
                </tr>
                <tr class="align-top border-b border-gray-100">
                    <td class="font-semibold py-0.5">Alamat Lengkap Rumah</td>
                    <td class="py-0.5">:</td>
                    <td class="py-0.5 leading-relaxed"><?= $pendaftar['alamat_lengkap'] ?></td>
                </tr>
            </table>
        </div>

        <!-- Section 2: Data Orang Tua / Wali -->
        <div class="space-y-0.5 mb-2 avoid-break">
            <h4 class="font-bold text-primary border-b border-gray-300 pb-0.5 text-[11px] uppercase">II. DATA ORANG TUA KANDUNG & WALI</h4>
            <div class="grid grid-cols-2 gap-4 text-[11px]">
                <!-- Ayah -->
                <div class="space-y-2 border-r border-gray-200 pr-4">
                    <h5 class="font-bold text-secondary text-xs uppercase tracking-wide">A. DATA AYAH</h5>
                    <table class="w-full">
                        <tr class="align-top">
                            <td class="w-24 py-1">Nama Ayah</td>
                            <td class="w-3 py-1">:</td>
                            <td class="py-1 font-bold"><?= $pendaftar['nama_ayah'] ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">NIK Ayah</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['nik_ayah'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Thn Lahir / Pdk</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['thn_lahir_ayah'] ?: '-' ?> / <?= $pendaftar['pendidikan_ayah'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Pekerjaan</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['pekerjaan_ayah'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Penghasilan</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['penghasilan_ayah'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">No. HP/WA</td>
                            <td class="py-1">:</td>
                            <td class="py-1 font-semibold text-primary"><?= $pendaftar['wa_ayah'] ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Ibu -->
                <div class="space-y-2">
                    <h5 class="font-bold text-secondary text-xs uppercase tracking-wide">B. DATA IBU</h5>
                    <table class="w-full">
                        <tr class="align-top">
                            <td class="w-24 py-1">Nama Ibu</td>
                            <td class="w-3 py-1">:</td>
                            <td class="py-1 font-bold"><?= $pendaftar['nama_ibu'] ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">NIK Ibu</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['nik_ibu'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Thn Lahir / Pdk</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['thn_lahir_ibu'] ?: '-' ?> / <?= $pendaftar['pendidikan_ibu'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Pekerjaan</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['pekerjaan_ibu'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">Penghasilan</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['penghasilan_ibu'] ?: '-' ?></td>
                        </tr>
                        <tr class="align-top">
                            <td class="py-1">No. HP/WA</td>
                            <td class="py-1">:</td>
                            <td class="py-1"><?= $pendaftar['wa_ibu'] ?: '-' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php if ($pendaftar['nama_wali']): ?>
            <div class="border-t border-gray-100 pt-3 text-xs">
                <table class="w-full table-fixed">
                    <tr class="align-top">
                        <td class="w-1/4 font-semibold py-1">Nama Wali Santri</td>
                        <td class="w-4 py-1">:</td>
                        <td class="w-3/4 py-1 font-bold"><?= $pendaftar['nama_wali'] ?> (No. HP: <?= $pendaftar['wa_wali'] ?: '-' ?>)</td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Section 3: Asal Sekolah & Fisik -->
        <div class="space-y-0.5 mb-2 avoid-break">
            <h4 class="font-bold text-primary border-b border-gray-300 pb-0.5 text-[11px] uppercase">III. RIWAYAT SEKOLAH & DATA FISIK KESEHATAN</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[11px]">
                <table class="w-full">
                    <tr class="align-top">
                        <td class="w-36 py-1 font-semibold">Jenis Sekolah Asal</td>
                        <td class="w-3 py-1">:</td>
                        <td class="py-1"><?= $pendaftar['jenis_sekolah_asal'] ?></td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-1 font-semibold">Nama Sekolah Asal</td>
                        <td class="py-1">:</td>
                        <td class="py-1 font-bold"><?= $pendaftar['nama_sekolah_asal'] ?></td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-1 font-semibold">NPSN Sekolah Asal</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= $pendaftar['npsn_sekolah_asal'] ?: '-' ?></td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-1 font-semibold">Alamat Sekolah Asal</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= $pendaftar['alamat_sekolah_asal'] ?: '-' ?></td>
                    </tr>
                </table>
                
                <table class="w-full">
                    <tr class="align-top">
                        <td class="w-36 py-1 font-semibold">Tinggi / Berat Badan</td>
                        <td class="w-3 py-1">:</td>
                        <td class="py-1"><?= $pendaftar['tinggi_badan'] ?: '-' ?> cm / <?= $pendaftar['berat_badan'] ?: '-' ?> kg</td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-1 font-semibold">Lingkar Kepala</td>
                        <td class="py-1">:</td>
                        <td class="py-1"><?= $pendaftar['lingkar_kepala'] ?: '-' ?> cm</td>
                    </tr>
                    <tr class="align-top">
                        <td class="py-1 font-semibold">Riwayat Penyakit</td>
                        <td class="py-1">:</td>
                        <td class="py-1 text-red-700 font-medium"><?= $pendaftar['riwayat_penyakit'] ?: 'Tidak Ada' ?></td>
                    </tr>
                </table>
            </div>
        </div>

                <!-- Tanda Tangan Form -->
                <div class="grid grid-cols-2 gap-8 text-center text-[11px] mt-1 pt-1 border-t border-gray-200 avoid-break">
                    <div class="space-y-10">
                        <p>Orang Tua / Wali Calon Santri,</p>
                        <div class="w-48 border-b border-gray-700 mx-auto font-bold uppercase text-dark">( <?= $pendaftar['nama_ayah'] ?> )</div>
                    </div>
                    <div class="space-y-12">
                        <p>Petugas Penerima Pendaftaran,</p>
                        <div class="w-48 border-b border-gray-700 mx-auto text-gray-400 font-bold">( ............................................ )</div>
                    </div>
                </div>

                    </td>
                </tr>
            </tbody>
        </table>

        </div>
    </div>

    <script>
        function unduhPDFLangsung() {
            const btn = document.getElementById('btn-unduh-pdf');
            const originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Sedang Mengunduh...</span>';
                btn.disabled = true;
            }

            const element = document.getElementById('printable-area');
            const opt = {
                margin:       [10, 0, 10, 0],
                filename:     'Formulir_PSB_<?= $pendaftar['no_registrasi'] ?>.pdf',
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
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ status: 'pdf_done', no_reg: '<?= $pendaftar['no_registrasi'] ?>' }, '*');
                }
            });
        }

        // Jika dipanggil dengan parameter &download=1, otomatis langsung mengunduh
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('download') === '1') {
                setTimeout(() => {
                    unduhPDFLangsung();
                }, 800);
            }
        });
    </script>
</body>
</html>
