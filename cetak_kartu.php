<?php
// cetak_kartu.php
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
    <title>Kartu Peserta Ujian - <?= $pendaftar['no_registrasi'] ?></title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
    <div class="no-print fixed top-0 left-0 right-0 bg-white border-b border-gray-200 px-6 py-4 flex flex-col md:flex-row justify-between items-center shadow-sm z-50">
        <div class="flex items-center space-x-3 mb-3 md:mb-0">
            <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center text-primary">
                <i class="fa-solid fa-id-card text-lg"></i>
            </div>
            <div>
                <h4 class="font-extrabold text-xs text-primary uppercase tracking-wider">Pratinjau Cetak: Kartu Ujian</h4>
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

    <div id="printable-area" class="print-container w-[560px] mx-auto bg-white border-2 border-primary/60 rounded-2xl overflow-hidden relative">

        <!-- Header Bar -->
        <div class="bg-gradient-to-r from-primary to-secondary text-white px-5 py-3.5 flex items-center justify-between border-b-2 border-amber-400/50">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-amber-300 border border-white/20">
                    <i class="fa-solid fa-mosque text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xs font-black tracking-wide text-white uppercase">PSB Babul Jannah</h1>
                    <p class="text-[9px] text-amber-200/90 font-medium">Tahun Ajaran <?= $pendaftar['tahun_ajaran'] ?></p>
                </div>
            </div>
            <div class="bg-amber-400 text-primary px-3 py-1 rounded-lg font-black text-[10px] tracking-wider uppercase shadow-sm flex items-center space-x-1">
                <i class="fa-solid fa-id-badge"></i>
                <span>Kartu Ujian</span>
            </div>
        </div>

        <!-- Main Body -->
        <div class="p-4 grid grid-cols-12 gap-3 items-center">
            <!-- Left: Foto & Nomor Registrasi (3 cols) -->
            <div class="col-span-3 flex flex-col items-center">
                <div class="w-24 h-32 border-2 border-dashed border-primary/30 rounded-xl bg-amber-50/40 flex flex-col items-center justify-center text-primary/40 p-1.5 text-center shadow-inner relative">
                    <i class="fa-solid fa-user-graduate text-2xl mb-1 text-primary/30"></i>
                    <span class="text-[8px] font-bold text-primary/60 uppercase tracking-tight">Foto 3x4</span>
                </div>
                <div class="mt-2 w-full text-center bg-primary text-white font-mono font-bold text-[10px] py-1 px-1 rounded-lg shadow tracking-wide border border-amber-400/30">
                    <?= $pendaftar['no_registrasi'] ?>
                </div>
            </div>

            <!-- Middle: Data Penting Santri (6 cols) -->
            <div class="col-span-6 space-y-2">
                <div class="border-b border-gray-150 pb-1">
                    <span class="text-[8px] font-bold text-gray-400 uppercase tracking-wider block">Nama Calon Santri</span>
                    <h2 class="text-sm font-extrabold text-dark uppercase tracking-tight leading-tight truncate" title="<?= $pendaftar['nama_santri'] ?>"><?= $pendaftar['nama_santri'] ?></h2>
                </div>

                <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px]">
                    <div>
                        <span class="text-[8px] font-bold text-gray-400 uppercase tracking-wider block">Jenis Kelamin</span>
                        <span class="font-bold text-gray-800"><?= $pendaftar['jk'] ?></span>
                    </div>
                    <div>
                        <span class="text-[8px] font-bold text-gray-400 uppercase tracking-wider block">Jalur & Kategori</span>
                        <span class="font-bold text-secondary"><?= $pendaftar['jalur_pendaftaran'] ?> <span class="<?= (isset($pendaftar['kategori_santri']) && $pendaftar['kategori_santri'] === 'Pindahan') ? 'text-indigo-600' : 'text-gray-500' ?> text-[9px]">(<?= $pendaftar['kategori_santri'] ?? 'Baru' ?>)</span></span>
                    </div>
                    <div>
                        <span class="text-[8px] font-bold text-gray-400 uppercase tracking-wider block">Gelombang</span>
                        <span class="font-bold text-gray-800"><?= $pendaftar['nama_gelombang'] ?></span>
                    </div>
                    <div>
                        <span class="text-[8px] font-bold text-gray-400 uppercase tracking-wider block">Asal Sekolah</span>
                        <span class="font-bold text-gray-800 truncate block" title="<?= $pendaftar['nama_sekolah_asal'] ?>"><?= $pendaftar['nama_sekolah_asal'] ?></span>
                    </div>
                </div>

                <!-- Jadwal Ujian Highlight -->
                <div class="bg-amber-50/80 border border-amber-200/80 rounded-xl p-2 flex items-center justify-between">
                    <div class="flex items-center space-x-1.5">
                        <div class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center text-[10px] flex-shrink-0 shadow-sm">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <span class="text-[7px] font-bold text-amber-800 uppercase tracking-wider block">Jadwal Ujian</span>
                            <span class="text-[11px] font-extrabold text-amber-950">
                                <?= !empty($pendaftar['tgl_ujian']) ? format_tanggal($pendaftar['tgl_ujian']) : '10 Agustus 2026' ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[7px] font-bold text-amber-800 uppercase tracking-wider block">Waktu</span>
                        <span class="text-[11px] font-bold text-amber-950">08:00 WIB</span>
                    </div>
                </div>
            </div>

            <!-- Right: QR Code Verifikasi (3 cols) -->
            <div class="col-span-3 flex flex-col items-center justify-center border-l border-dashed border-gray-300 pl-3">
                <span class="text-[8px] font-extrabold text-primary uppercase tracking-wider block mb-1 text-center">QR Verifikasi</span>
                <div id="qrcode-box" class="p-1.5 bg-white border border-gray-300 rounded-xl shadow-sm flex items-center justify-center"></div>
                <span class="text-[7px] font-bold text-gray-500 mt-1.5 text-center leading-tight">Scan Kehadiran Saat Hari Ujian</span>
            </div>
        </div>

        <!-- Footer Note & Verification -->
        <div class="bg-gray-50 px-5 py-3 border-t border-gray-200/80 flex items-center justify-between text-[10px] text-gray-600">
            <div class="flex items-center space-x-1.5">
                <i class="fa-solid fa-circle-info text-secondary text-xs"></i>
                <span class="font-medium text-gray-600">Wajib dibawa saat tes & berbusana muslim rapi.</span>
            </div>
            <div class="font-extrabold text-primary text-[10px] flex items-center space-x-1 pl-3 border-l border-gray-250">
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                <span>Panitia PSB</span>
            </div>
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
            const w = element.offsetWidth + 4;
            const h = element.offsetHeight + 8;
            const opt = {
                margin:       0,
                filename:     'Kartu_Ujian_<?= $pendaftar['no_registrasi'] ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollX: 0, scrollY: 0 },
                pagebreak:    { mode: 'avoid-all' },
                jsPDF:        { unit: 'px', format: [w, h], orientation: w > h ? 'landscape' : 'portrait' }
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

        window.addEventListener('DOMContentLoaded', () => {
            const qrcodeBox = document.getElementById("qrcode-box");
            if (qrcodeBox && typeof QRCode !== 'undefined') {
                new QRCode(qrcodeBox, {
                    text: "<?= $pendaftar['no_registrasi'] ?>",
                    width: 86,
                    height: 86,
                    colorDark: "#3E2723",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }

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
