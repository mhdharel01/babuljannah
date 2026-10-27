<?php
// admin/scan.php
require_once '../config/db.php';

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

// Ambil semua gelombang untuk filter dropdown
$stmt_gel = $db->query("SELECT * FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll();

// Default gelombang terpilih (dari GET atau gelombang aktif atau yang pertama)
$selected_gelombang = (int)($_GET['gelombang_id'] ?? 0);
if ($selected_gelombang === 0 && !empty($gelombang_list)) {
    foreach ($gelombang_list as $g) {
        if ($g['status'] === 'aktif') {
            $selected_gelombang = $g['id'];
            break;
        }
    }
    if ($selected_gelombang === 0) {
        $selected_gelombang = $gelombang_list[0]['id'];
    }
}
?>
<?php
$extra_js = '<script src="https://unpkg.com/html5-qrcode"></script>
';
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        
        <?php
        $header_custom_title = '
            <div class="flex items-center space-x-4">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Scanner Kehadiran & Check-in Ujian</h1>
                    <p class="text-xs text-gray-500 hidden sm:block">Scan barcode kartu ujian santri untuk mencatat absensi secara live.</p>
                </div>
            </div>';

        $gelombang_options = '<option value="">-- Semua Gelombang --</option>';
        foreach ($gelombang_list as $g) {
            $selected = ($selected_gelombang == $g['id']) ? 'selected' : '';
            $aktif = ($g['status'] === 'aktif') ? '★ Aktif' : '';
            $gelombang_options .= '<option value="' . $g['id'] . '" ' . $selected . '>' . $g['nama_gelombang'] . ' (T.A ' . $g['tahun_ajaran'] . ') ' . $aktif . '</option>';
        }

        $header_custom_right = '
            <!-- Gelombang Filter -->
            <div class="flex items-center space-x-3 bg-accent/60 p-2 rounded-xl border border-gray-200 self-stretch md:self-auto justify-between">
                <span class="text-xs font-bold text-primary uppercase tracking-wider flex items-center pl-2">
                    <i class="fa-solid fa-filter mr-1.5 text-secondary"></i> Filter Gelombang:
                </span>
                <select id="filter-gelombang" onchange="changeGelombang(this.value)" class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs font-bold text-primary focus:outline-none focus:ring-2 focus:ring-secondary">
                    ' . $gelombang_options . '
                </select>
            </div>';

        include 'includes/header.php';
        ?>

        <main class="flex-grow p-6 md:p-8 overflow-y-auto space-y-8 bg-gray-50/50">
            <div class="max-w-7xl mx-auto w-full space-y-8">
            
                <!-- Stats Banner (3 Cards) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Peserta -->
                    <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Total Peserta Ujian (Lunas)</span>
                            <h3 id="stat-total" class="text-3xl font-black text-primary mt-1">0</h3>
                            <span class="text-[11px] text-gray-500 mt-0.5 block">Siap Mengikuti Ujian Tes</span>
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl">
                            <i class="fa-solid fa-users-viewfinder"></i>
                        </div>
                    </div>

                    <!-- Sudah Hadir -->
                    <div class="bg-gradient-to-br from-emerald-800 to-emerald-950 text-white rounded-2xl p-6 border border-emerald-700 shadow-lg flex items-center justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/20 rounded-full blur-xl pointer-events-none"></div>
                        <div>
                            <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider block">Sudah Hadir (Check-in)</span>
                            <h3 id="stat-hadir" class="text-3xl font-black text-white mt-1">0</h3>
                            <span class="text-[11px] text-emerald-200 mt-0.5 flex items-center">
                                <i class="fa-solid fa-circle-check mr-1 text-emerald-400"></i> Terverifikasi Hadir
                            </span>
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-white/10 text-emerald-300 flex items-center justify-center text-2xl border border-white/15">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>

                <!-- Belum Hadir -->
                <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Belum Hadir / Antri</span>
                        <h3 id="stat-belum" class="text-3xl font-black text-amber-600 mt-1">0</h3>
                        <span class="text-[11px] text-gray-500 mt-0.5 block">Menunggu Kedatangan</span>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl border border-amber-200">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
            </div>

            <!-- Scanner & Result 2-Column Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column: Camera Scanner & Manual Input (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- Camera Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-150 pb-3">
                            <h3 class="font-bold text-primary text-sm flex items-center">
                                <i class="fa-solid fa-camera mr-2 text-secondary"></i> Kamera Scanner Live
                            </h3>
                            <span id="cam-status" class="bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full text-[10px] font-bold">Standby</span>
                        </div>

                        <!-- Video Scanner Area -->
                        <div class="bg-dark rounded-xl overflow-hidden min-h-[260px] flex flex-col items-center justify-center relative border-2 border-primary/30 shadow-inner">
                            <div id="reader" class="w-full h-full"></div>
                            <div id="cam-placeholder" class="text-center p-6 space-y-3">
                                <div class="w-16 h-16 rounded-full bg-white/10 text-white/50 mx-auto flex items-center justify-center text-3xl">
                                    <i class="fa-solid fa-video-slash"></i>
                                </div>
                                <p class="text-xs text-gray-400 font-medium max-w-xs">Tekan tombol di bawah untuk mengaktifkan kamera pemindai barcode pada laptop/HP Anda.</p>
                            </div>
                        </div>

                        <!-- Camera Control Buttons -->
                        <div class="flex flex-col sm:flex-row gap-2 pt-1">
                            <button id="btn-start-cam" onclick="startScanner()" class="flex-1 bg-primary hover:bg-secondary text-white font-bold py-3 px-4 rounded-xl text-xs shadow transition flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-play"></i>
                                <span>Aktifkan Kamera</span>
                            </button>
                            <button id="btn-stop-cam" onclick="stopScanner()" class="hidden flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl text-xs shadow transition flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-stop"></i>
                                <span>Matikan Kamera</span>
                            </button>
                        </div>
                    </div>

                    <!-- Manual Input Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-4">
                        <h3 class="font-bold text-primary text-sm flex items-center border-b border-gray-150 pb-3">
                            <i class="fa-solid fa-keyboard mr-2 text-secondary"></i> Input Manual No. Registrasi
                        </h3>
                        <form onsubmit="manualSearch(event)" class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nomor Registrasi Santri</label>
                                <div class="flex space-x-2">
                                    <input type="text" id="manual-reg-input" placeholder="Contoh: PPBJ-2026-001" class="flex-1 border border-gray-300 focus:border-secondary focus:ring-1 focus:ring-secondary rounded-xl px-4 py-2.5 text-xs font-bold uppercase focus:outline-none">
                                    <button type="submit" class="bg-secondary hover:bg-primary text-white font-bold px-5 py-2.5 rounded-xl text-xs shadow transition flex items-center space-x-1.5 flex-shrink-0">
                                        <i class="fa-solid fa-search"></i>
                                        <span>Periksa</span>
                                    </button>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-400 italic">Gunakan ini jika kartu santri rusak atau kamera HP tidak dapat membaca barcode.</p>
                        </form>
                    </div>

                </div>

                <!-- Right Column: Profile & Check-in Verification Card (7 cols) -->
                <div class="lg:col-span-7">
                    
                    <!-- Before Scan Placeholder -->
                    <div id="result-placeholder" class="bg-white rounded-2xl border border-gray-200 p-12 text-center shadow-sm flex flex-col items-center justify-center min-h-[460px] space-y-4">
                        <div class="w-24 h-24 rounded-full bg-accent text-secondary flex items-center justify-center text-4xl shadow-inner border border-secondary/30">
                            <i class="fa-solid fa-qrcode animate-pulse"></i>
                        </div>
                        <div class="space-y-1 max-w-sm">
                            <h4 class="text-base font-extrabold text-primary">Menunggu Pemindaian QR Code</h4>
                            <p class="text-xs text-gray-500 leading-relaxed">Arahkan kamera ke barcode pada sudut atas Kartu Ujian santri untuk menampilkan biodata dan verifikasi kehadiran secara instan.</p>
                        </div>
                    </div>

                    <!-- Scanned Student Result Card -->
                    <div id="result-card" class="hidden bg-white rounded-2xl border-2 border-primary/60 shadow-xl overflow-hidden transition-all duration-300">
                        <!-- Card Header -->
                        <div class="bg-gradient-to-r from-primary to-secondary text-white p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <span class="text-[10px] bg-white/20 text-white font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-widest mb-1.5 inline-block">Hasil Pemindaian Barcode</span>
                                <h2 id="res-nama" class="text-2xl font-black text-white tracking-wide">NAMA SANTRI</h2>
                                <p id="res-noreg" class="text-sm font-mono font-bold text-amber-300 mt-0.5">PPBJ-XXXX-XXX</p>
                            </div>
                            <div id="res-badge-pay" class="bg-emerald-500 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-md flex items-center space-x-1.5 border border-white/20">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Lunas</span>
                            </div>
                        </div>

                        <!-- Card Body (Student Info) -->
                        <div class="p-6 md:p-8 space-y-6">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-accent/50 p-5 rounded-2xl border border-gray-200/80 text-xs">
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Gelombang & TA</span>
                                    <span id="res-gelombang" class="font-bold text-primary text-sm">Gelombang 1</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Jalur Pendaftaran</span>
                                    <span id="res-jalur" class="font-bold text-secondary text-sm">SMP IT</span>
                                </div>
                                <div class="pt-2 border-t border-gray-200 md:col-span-2">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Asal Sekolah</span>
                                    <span id="res-sekolah" class="font-bold text-gray-800 text-sm">SD Negeri 1</span>
                                </div>
                            </div>

                            <!-- Attendance Status Box -->
                            <div id="box-belum-hadir" class="bg-amber-50 border-2 border-amber-300 p-6 rounded-2xl text-center space-y-4 shadow-sm">
                                <div class="flex items-center justify-center space-x-2 text-amber-800 font-extrabold text-sm">
                                    <i class="fa-solid fa-clock text-lg"></i>
                                    <span>Status: BELUM CHECK-IN KEHADIRAN UJIAN</span>
                                </div>
                                <p class="text-xs text-amber-900 leading-relaxed max-w-md mx-auto font-medium">
                                    Santri ini terdaftar dan berhak mengikuti ujian tes seleksi masuk. Klik tombol besar di bawah ini untuk mencatat kedatangannya sekarang.
                                </p>
                                <button onclick="confirmAttendance()" class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-black py-4 px-6 rounded-xl shadow-xl hover:shadow-2xl text-sm md:text-base tracking-wide transition-all transform hover:-translate-y-0.5 flex items-center justify-center space-x-2">
                                    <i class="fa-solid fa-check-to-slot text-xl text-emerald-200"></i>
                                    <span>✅ TANDAI HADIR UJIAN (CHECK-IN SEKARANG)</span>
                                </button>
                            </div>

                            <!-- Already Attended Box -->
                            <div id="box-sudah-hadir" class="hidden bg-emerald-50 border-2 border-emerald-400 p-6 rounded-2xl text-center space-y-4 shadow-sm">
                                <div class="w-16 h-16 rounded-full bg-emerald-600 text-white mx-auto flex items-center justify-center text-3xl shadow-lg">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <div>
                                    <span class="inline-block bg-emerald-200 text-emerald-900 font-black text-[10px] uppercase tracking-widest px-3 py-1 rounded-full mb-2">Terverifikasi Hadir</span>
                                    <h3 class="text-lg font-extrabold text-emerald-950">Santri Telah Check-in Kehadiran!</h3>
                                    <p id="res-waktu-hadir" class="text-xs font-bold text-emerald-800 mt-1">Dicatat pada: 2026-07-27 08:15:00 WIB</p>
                                </div>
                                <div class="pt-2">
                                    <button onclick="resetView()" class="bg-primary hover:bg-secondary text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow transition">
                                        <i class="fa-solid fa-qrcode mr-1.5"></i> Scan Santri Berikutnya
                                    </button>
                                </div>
                            </div>

                        </div>

                        <!-- Card Footer -->
                        <div class="bg-gray-50 p-4 border-t border-gray-200 flex justify-between items-center text-xs">
                            <span class="text-gray-500 font-medium">Panitia PSB Babul Jannah</span>
                            <button onclick="resetView()" class="text-gray-500 hover:text-red-600 font-bold transition flex items-center space-x-1">
                                <i class="fa-solid fa-xmark"></i>
                                <span>Tutup Hasil</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Tabel Laporan & Rekapitulasi Kehadiran Peserta -->
            <div class="bg-white rounded-3xl p-6 md:p-8 border border-gray-200 shadow-sm space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-5">
                    <div>
                        <span class="text-xs font-bold text-secondary uppercase tracking-wider block"><i class="fa-solid fa-clipboard-check mr-1.5"></i> Pelaporan Live Kehadiran</span>
                        <h2 class="text-lg font-black text-primary mt-0.5">Tabel Rekapitulasi Kehadiran Peserta Ujian</h2>
                        <p class="text-xs text-gray-500">Daftar calon santri beserta status kedatangan, waktu check-in, dan aksi cepat panitia.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button onclick="loadAttendanceTable()" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-sm">
                            <i class="fa-solid fa-rotate mr-1"></i><span>Refresh Tabel</span>
                        </button>
                        <a id="btn-export-excel" href="export.php" target="_blank" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white rounded-xl text-xs font-bold transition flex items-center space-x-2 shadow-md">
                            <i class="fa-solid fa-file-excel"></i><span>Ekspor Laporan Kehadiran (Excel)</span>
                        </a>
                    </div>
                </div>

                <!-- Filter & Search Bar Tabel -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-gray-50/70 p-4 rounded-2xl border border-gray-200/60 items-center">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Filter Status Kehadiran</label>
                        <select id="filter-status-table" onchange="loadAttendanceTable()" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-secondary">
                            <option value="">-- Semua Peserta (Hadir & Belum) --</option>
                            <option value="Hadir">✅ Sudah Hadir (Check-in)</option>
                            <option value="Belum">⏳ Belum Hadir / Antri</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Cari Peserta (Nama / No. Reg / Sekolah Asal)</label>
                        <div class="relative">
                            <input type="text" id="search-table-input" oninput="debounceSearchTable()" placeholder="Ketik nama atau nomor registrasi..." class="w-full bg-white border border-gray-300 rounded-xl pl-9 pr-4 py-2 text-xs font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-secondary">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto rounded-2xl border border-gray-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100/80 text-gray-600 text-[11px] uppercase tracking-wider font-bold border-b border-gray-200">
                                <th class="py-3.5 px-4 text-center w-12">No</th>
                                <th class="py-3.5 px-4">No. Registrasi & Nama Santri</th>
                                <th class="py-3.5 px-4">Jalur & Sekolah Asal</th>
                                <th class="py-3.5 px-4">Status Pembayaran</th>
                                <th class="py-3.5 px-4">Status Kehadiran</th>
                                <th class="py-3.5 px-4">Waktu Check-in</th>
                                <th class="py-3.5 px-4 text-center">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-tbody" class="divide-y divide-gray-100 text-xs font-medium text-gray-700">
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400">
                                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block text-secondary"></i>
                                    Memuat data kehadiran...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Container -->
                <div id="table-pagination-container"></div>
            </div>

        </div>
    </main>
    </div>

    <script>
    let html5QrCode = null;
    let currentScannedReg = '';
    let isScanning = false;
    let selectedGelombangId = '<?= $selected_gelombang ?>';

    // Sound Beep Generator via Web Audio API
    function playBeep(success = true) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = success ? 'sine' : 'sawtooth';
            osc.frequency.setValueAtTime(success ? 880 : 300, ctx.currentTime); // 880Hz for success
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.18);
        } catch (e) {
            console.log("Audio beep tidak didukung pada browser ini.");
        }
    }

    // Ganti filter gelombang
    function changeGelombang(val) {
        selectedGelombangId = val;
        loadStats();
        resetView();
        loadAttendanceTable();
    }

    // Load Live Attendance Stats
    function loadStats() {
        fetch(`scan_action.php?action=stats&gelombang_id=${selectedGelombangId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('stat-hadir').textContent = data.stats.hadir;
                    document.getElementById('stat-belum').textContent = data.stats.belum;
                }
            })
            .catch(err => console.error(err));
    }

    // Table search debounce
    let searchTimeout = null;
    function debounceSearchTable() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadAttendanceTable();
        }, 300);
    }

    function updateExportLink() {
        const filterStatus = document.getElementById('filter-status-table')?.value || '';
        const exportBtn = document.getElementById('btn-export-excel');
        if (exportBtn) {
            let url = `export.php?filter_gelombang=${selectedGelombangId}`;
            if (filterStatus === 'Hadir') url += '&filter_hadir=Hadir';
            else if (filterStatus === 'Belum') url += '&filter_hadir=Belum';
            exportBtn.href = url;
        }
    }

    // Load Live Attendance Table
    function loadAttendanceTable(page = 1) {
        updateExportLink();
        const tbody = document.getElementById('attendance-tbody');
        const paginationContainer = document.getElementById('table-pagination-container');
        if (!tbody) return;

        const filterStatus = document.getElementById('filter-status-table')?.value || '';
        const search = document.getElementById('search-table-input')?.value || '';

        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data kehadiran...</td></tr>`;

        fetch(`scan_action.php?action=list_table&gelombang_id=${selectedGelombangId}&filter_status=${encodeURIComponent(filterStatus)}&search=${encodeURIComponent(search)}&page=${page}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.count === 0) {
                        tbody.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-gray-400 font-medium"><i class="fa-solid fa-user-clock text-3xl mb-2 block text-gray-300"></i>Tidak ada data peserta ujian yang sesuai dengan filter saat ini.</td></tr>`;
                        if (paginationContainer) paginationContainer.innerHTML = '';
                        return;
                    }
                    let html = '';
                    const offset = (data.current_page - 1) * data.limit;
                    data.data.forEach((item, idx) => {
                        const badgePay = item.status_pembayaran === 'Lunas' 
                            ? `<span class="bg-emerald-50 text-emerald-800 text-[10px] px-2.5 py-1 rounded-md font-bold border border-emerald-200 inline-flex items-center"><i class="fa-solid fa-check mr-1"></i>Lunas</span>` 
                            : `<span class="bg-amber-50 text-amber-800 text-[10px] px-2.5 py-1 rounded-md font-bold border border-amber-200 inline-flex items-center"><i class="fa-solid fa-clock mr-1"></i>Belum Lunas</span>`;
                        
                        const isHadir = item.status_kehadiran === 'Hadir';
                        const badgeHadir = isHadir 
                            ? `<span class="bg-emerald-600 text-white text-[10px] px-3 py-1 rounded-full font-bold shadow-sm inline-flex items-center"><i class="fa-solid fa-user-check mr-1.5"></i>HADIR</span>`
                            : `<span class="bg-gray-100 text-gray-600 text-[10px] px-3 py-1 rounded-full font-bold inline-flex items-center border border-gray-200"><i class="fa-regular fa-clock mr-1.5"></i>Belum Hadir</span>`;

                        const actionBtn = isHadir
                            ? `<button onclick="toggleAttendanceTable('${item.no_registrasi}', false)" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-[11px] font-bold transition border border-red-200 inline-flex items-center" title="Batalkan kehadiran"><i class="fa-solid fa-xmark mr-1"></i>Batal Hadir</button>`
                            : `<button onclick="toggleAttendanceTable('${item.no_registrasi}', true)" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-[11px] font-bold transition border border-emerald-200 inline-flex items-center" title="Tandai hadir"><i class="fa-solid fa-check mr-1"></i>Tandai Hadir</button>`;

                        html += `
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-4 text-center font-bold text-gray-500">${offset + idx + 1}</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-primary block">${item.nama_santri}</span>
                                <span class="font-mono text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded mt-0.5 inline-block">${item.no_registrasi}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-gray-800 block">${item.jalur_pendaftaran || '-'}</span>
                                <span class="text-[11px] text-gray-500 block">${item.sekolah_asal || '-'}</span>
                            </td>
                            <td class="py-3 px-4">${badgePay}</td>
                            <td class="py-3 px-4">${badgeHadir}</td>
                            <td class="py-3 px-4 font-mono text-gray-600">${item.waktu_formatted}</td>
                            <td class="py-3 px-4 text-center">${actionBtn}</td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;
                    
                    // Render Pagination
                    if (paginationContainer) {
                        renderPagination(paginationContainer, data.current_page, data.total_pages, data.total_data, offset, data.limit);
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500 font-bold">Gagal memuat data.</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">Terjadi kesalahan koneksi server.</td></tr>`;
            });
    }

    function renderPagination(container, currentPage, totalPages, totalData, offset, limit) {
        let endData = offset + limit;
        if (endData > totalData) endData = totalData;
        let startData = totalData === 0 ? 0 : offset + 1;
        
        let html = `
        <div class="border-t border-gray-100 bg-gray-50/50 p-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 rounded-b-3xl">
            <div class="text-sm text-gray-500 font-medium">
                Menampilkan <span class="font-bold text-gray-700">${startData}</span> sampai <span class="font-bold text-gray-700">${endData}</span> dari total <span class="font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-md">${totalData}</span> data pendaftar
            </div>
        `;
        
        if (totalPages > 1) {
            html += `<nav class="flex items-center space-x-1">`;
            
            // Prev Button
            if (currentPage > 1) {
                html += `<button onclick="loadAttendanceTable(${currentPage - 1})" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-primary hover:border-gray-300 transition shadow-sm"><i class="fa-solid fa-chevron-left text-xs"></i></button>`;
            } else {
                html += `<span class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed"><i class="fa-solid fa-chevron-left text-xs"></i></span>`;
            }
            
            html += `<div class="flex space-x-1 px-2">`;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += `<button onclick="loadAttendanceTable(1)" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm">1</button>`;
                if (startPage > 2) html += `<span class="w-9 h-9 flex items-center justify-center text-gray-400">...</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += `<span class="w-9 h-9 flex items-center justify-center rounded-xl bg-primary border-primary text-sm font-bold text-white shadow-md">${i}</span>`;
                } else {
                    html += `<button onclick="loadAttendanceTable(${i})" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm">${i}</button>`;
                }
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="w-9 h-9 flex items-center justify-center text-gray-400">...</span>`;
                html += `<button onclick="loadAttendanceTable(${totalPages})" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm">${totalPages}</button>`;
            }
            html += `</div>`;
            
            // Next Button
            if (currentPage < totalPages) {
                html += `<button onclick="loadAttendanceTable(${currentPage + 1})" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-primary hover:border-gray-300 transition shadow-sm"><i class="fa-solid fa-chevron-right text-xs"></i></button>`;
            } else {
                html += `<span class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed"><i class="fa-solid fa-chevron-right text-xs"></i></span>`;
            }
            
            html += `</nav>`;
        }
        
        html += `</div>`;
        container.innerHTML = html;
    }

    // Toggle Attendance from Table
    function toggleAttendanceTable(noReg, setHadir) {
        if (!confirm(`Apakah Anda yakin ingin ${setHadir ? 'menandai HADIR' : 'MEMBATALKAN kehadiran'} untuk santri dengan No. Reg ${noReg}?`)) return;

        const formData = new FormData();
        formData.append('action', setHadir ? 'attend' : 'unattend');
        formData.append('no_reg', noReg);

        fetch('scan_action.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                playBeep(setHadir);
                loadStats();
                loadAttendanceTable();
                if (currentScannedReg === noReg) {
                    processScan(noReg);
                }
            } else {
                alert("❌ Gagal: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Terjadi kesalahan sistem.");
        });
    }

    // Start Scanner Camera
    function startScanner() {
        const placeholder = document.getElementById('cam-placeholder');
        const startBtn = document.getElementById('btn-start-cam');
        const stopBtn = document.getElementById('btn-stop-cam');
        const statusEl = document.getElementById('cam-status');

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
        }

        placeholder.style.display = 'none';
        startBtn.classList.add('hidden');
        stopBtn.classList.remove('hidden');
        statusEl.textContent = '⚡ Memindai...';
        statusEl.className = 'bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full text-[10px] font-bold animate-pulse';
        isScanning = true;

        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 220, height: 220 }
            },
            (decodedText, decodedResult) => {
                // Saat QR Code Terbaca!
                if (decodedText && decodedText !== currentScannedReg) {
                    currentScannedReg = decodedText.trim();
                    playBeep(true);
                    processScan(currentScannedReg);
                }
            },
            (errorMessage) => {
                // Abaikan frame kosong / gagal scan per frame
            }
        ).catch((err) => {
            alert("Gagal mengaktifkan kamera: " + err + ". Pastikan Anda telah memberikan izin kamera pada browser.");
            stopScanner();
        });
    }

    // Stop Scanner Camera
    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('cam-placeholder').style.display = 'block';
                document.getElementById('btn-start-cam').classList.remove('hidden');
                document.getElementById('btn-stop-cam').classList.add('hidden');
                const statusEl = document.getElementById('cam-status');
                statusEl.textContent = 'Standby';
                statusEl.className = 'bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full text-[10px] font-bold';
                isScanning = false;
            }).catch(err => console.error(err));
        }
    }

    // Manual input search
    function manualSearch(e) {
        e.preventDefault();
        const input = document.getElementById('manual-reg-input').value.trim();
        if (!input) return;
        currentScannedReg = input;
        playBeep(true);
        processScan(input);
    }

    // Process scan result
    function processScan(noReg) {
        fetch(`scan_action.php?action=check&no_reg=${encodeURIComponent(noReg)}&gelombang_id=${selectedGelombangId}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'error') {
                    playBeep(false);
                    alert("❌ ERROR: " + data.message);
                    currentScannedReg = '';
                    return;
                }
                if (data.status === 'warning') {
                    alert("⚠️ " + data.message);
                }

                showResultCard(data.data);
            })
            .catch(err => {
                console.error(err);
                alert("Terjadi kesalahan koneksi server.");
                currentScannedReg = '';
            });
    }

    // Display student identity & attendance state
    function showResultCard(student) {
        document.getElementById('result-placeholder').classList.add('hidden');
        const card = document.getElementById('result-card');
        card.classList.remove('hidden');

        document.getElementById('res-nama').textContent = student.nama_santri;
        document.getElementById('res-noreg').textContent = student.no_registrasi;
        document.getElementById('res-gelombang').textContent = `${student.nama_gelombang} (T.A ${student.tahun_ajaran})`;
        document.getElementById('res-jalur').textContent = student.jalur_pendaftaran;
        document.getElementById('res-sekolah').textContent = student.nama_sekolah_asal;

        // Payment badge
        const badgePay = document.getElementById('res-badge-pay');
        if (student.status_pembayaran === 'Lunas') {
            badgePay.className = 'bg-emerald-500 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-md flex items-center space-x-1.5 border border-white/20';
            badgePay.innerHTML = '<i class="fa-solid fa-circle-check"></i><span>Lunas</span>';
        } else {
            badgePay.className = 'bg-amber-500 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs shadow-md flex items-center space-x-1.5 border border-white/20 animate-pulse';
            badgePay.innerHTML = '<i class="fa-solid fa-clock"></i><span>Belum Lunas</span>';
        }

        // Attendance state
        const boxBelum = document.getElementById('box-belum-hadir');
        const boxSudah = document.getElementById('box-sudah-hadir');

        if (student.status_kehadiran === 'Hadir') {
            boxBelum.classList.add('hidden');
            boxSudah.classList.remove('hidden');
            document.getElementById('res-waktu-hadir').textContent = 'Dicatat pada: ' + (student.waktu_kehadiran || 'Hari ini');
        } else {
            boxSudah.classList.add('hidden');
            boxBelum.classList.remove('hidden');
        }

        // Smooth scroll to card on mobile
        if (window.innerWidth < 1024) {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Confirm & Record Attendance via AJAX
    function confirmAttendance() {
        if (!currentScannedReg) return;

        const formData = new FormData();
        formData.append('action', 'attend');
        formData.append('no_reg', currentScannedReg);

        fetch('scan_action.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                playBeep(true);
                // Switch to attended view
                document.getElementById('box-belum-hadir').classList.add('hidden');
                const boxSudah = document.getElementById('box-sudah-hadir');
                boxSudah.classList.remove('hidden');
                document.getElementById('res-waktu-hadir').textContent = 'Dicatat pada: ' + data.waktu;
                
                // Refresh live stats and table
                loadStats();
                loadAttendanceTable();
            } else {
                playBeep(false);
                alert("❌ Gagal: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Gagal memproses kehadiran.");
        });
    }

    // Reset View for next scan
    function resetView() {
        currentScannedReg = '';
        document.getElementById('manual-reg-input').value = '';
        document.getElementById('result-card').classList.add('hidden');
        document.getElementById('result-placeholder').classList.remove('hidden');
    }

    // Initial load
    window.addEventListener('DOMContentLoaded', () => {
        loadStats();
        loadAttendanceTable();
    });
    </script>
</body>
</html>
