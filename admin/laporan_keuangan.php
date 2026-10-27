<?php
// admin/laporan_keuangan.php
require_once '../config/db.php';

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'bendahara', 'yayasan'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

// Ambil Biaya Pendaftaran dari database (pengaturan_sistem), fallback 350000
$BIAYA_PENDAFTARAN = 350000;
try {
    $stmt_b = $db->query("SELECT nilai FROM pengaturan_sistem WHERE kunci = 'biaya_tes_pendaftaran'");
    $val_b = $stmt_b ? $stmt_b->fetchColumn() : null;
    if ($val_b && is_numeric($val_b)) {
        $BIAYA_PENDAFTARAN = (int)$val_b;
    }
} catch (Throwable $e) {
    $BIAYA_PENDAFTARAN = 350000;
}

// Handle Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Laporan_Keuangan_PSB_" . date('Ymd_His') . ".xls");
    
    // Cek query
    $stmt = $db->prepare("SELECT no_registrasi, nama_santri, jalur_pendaftaran, metode_pembayaran, tgl_lunas FROM pendaftaran WHERE status_pembayaran = 'Lunas' ORDER BY tgl_lunas DESC");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th colspan='6'>LAPORAN KEUANGAN PENDAFTARAN PSB</th></tr>";
    echo "<tr><th colspan='6'>Tanggal Cetak: " . date('d/m/Y H:i') . "</th></tr>";
    echo "<tr></tr>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>No Registrasi</th>";
    echo "<th>Nama Santri</th>";
    echo "<th>Jalur</th>";
    echo "<th>Metode Pembayaran</th>";
    echo "<th>Tanggal Validasi / Lunas</th>";
    echo "<th>Nominal (Rp)</th>";
    echo "</tr>";
    
    $no = 1;
    $total = 0;
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>{$no}</td>";
        echo "<td>{$row['no_registrasi']}</td>";
        echo "<td>{$row['nama_santri']}</td>";
        echo "<td>{$row['jalur_pendaftaran']}</td>";
        echo "<td>{$row['metode_pembayaran']}</td>";
        echo "<td>" . ($row['tgl_lunas'] ? date('d/m/Y H:i', strtotime($row['tgl_lunas'])) : '-') . "</td>";
        echo "<td>" . number_format($BIAYA_PENDAFTARAN, 0, ',', '.') . "</td>";
        echo "</tr>";
        $total += $BIAYA_PENDAFTARAN;
        $no++;
    }
    echo "<tr><th colspan='6'>TOTAL PEMASUKAN</th><th>" . number_format($total, 0, ',', '.') . "</th></tr>";
    echo "</table>";
    exit();
}

// Statistik Dasbor Keuangan
$stmt_stats = $db->query("
    SELECT 
        COUNT(*) as total_lunas,
        SUM(CASE WHEN metode_pembayaran = 'Transfer' THEN 1 ELSE 0 END) as total_transfer,
        SUM(CASE WHEN metode_pembayaran = 'Tunai/Cash' THEN 1 ELSE 0 END) as total_tunai
    FROM pendaftaran 
    WHERE status_pembayaran = 'Lunas'
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_uang = $stats['total_lunas'] * $BIAYA_PENDAFTARAN;
$uang_transfer = $stats['total_transfer'] * $BIAYA_PENDAFTARAN;
$uang_tunai = $stats['total_tunai'] * $BIAYA_PENDAFTARAN;

// Setup Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

// Total Data untuk Pagination
$stmt_total = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status_pembayaran = 'Lunas'");
$total_data = $stmt_total->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Data Tabel dengan Pagination
$stmt_table = $db->prepare("SELECT no_registrasi, nama_santri, jalur_pendaftaran, metode_pembayaran, tgl_lunas FROM pendaftaran WHERE status_pembayaran = 'Lunas' ORDER BY tgl_lunas DESC LIMIT :limit OFFSET :offset");
$stmt_table->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_table->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_table->execute();
$pendaftar_lunas = $stmt_table->fetchAll(PDO::FETCH_ASSOC);

$header_custom_right = '
    <a href="?export=excel" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-5 rounded-xl text-sm shadow-md transition flex items-center space-x-2">
        <i class="fa-solid fa-file-excel"></i>
        <span>Export Excel</span>
    </a>
';

?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-50 text-gray-800 font-sans antialiased min-h-screen flex">

    <?php include 'includes/sidebar.php'; ?>

    <div class="flex-1 min-w-0 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <?php include 'includes/header.php'; ?>

        <main class="flex-grow p-6 overflow-y-auto space-y-6">
            
            <!-- Cards Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-2">
                <!-- Total Income -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl shadow-inner">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Total Pemasukan</div>
                        <div class="text-2xl font-extrabold text-gray-900">Rp <?= number_format($total_uang, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Via Transfer -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl shadow-inner">
                        <i class="fa-solid fa-money-check-dollar"></i>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Via Transfer Bank</div>
                        <div class="text-2xl font-extrabold text-gray-900">Rp <?= number_format($uang_transfer, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Via Tunai -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl shadow-inner">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Via Tunai / Cash</div>
                        <div class="text-2xl font-extrabold text-gray-900">Rp <?= number_format($uang_tunai, 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Harga Formulir -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="w-14 h-14 rounded-2xl bg-gray-100 text-gray-800 flex items-center justify-center text-2xl shadow-inner">
                        <i class="fa-solid fa-tag"></i>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Biaya Tes / Anak</div>
                        <div class="text-2xl font-extrabold text-gray-900">Rp <?= number_format($BIAYA_PENDAFTARAN, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 class="font-extrabold text-gray-800"><i class="fa-solid fa-list-check mr-2 text-primary"></i> Riwayat Validasi Pembayaran</h3>
                </div>
                
                <div class="overflow-x-auto p-2">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead>
                            <tr class="text-gray-400 font-bold uppercase tracking-wider text-[10px] border-b border-gray-100 bg-gray-50/30">
                                <th class="px-6 py-4 rounded-tl-2xl">No. Reg</th>
                                <th class="px-6 py-4">Nama Santri</th>
                                <th class="px-6 py-4">Jalur</th>
                                <th class="px-6 py-4 text-center">Metode Bayar</th>
                                <th class="px-6 py-4 rounded-tr-2xl">Waktu Validasi Lunas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-gray-700">
                            <?php if (count($pendaftar_lunas) > 0): ?>
                                <?php foreach ($pendaftar_lunas as $p): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 font-extrabold text-primary"><?= htmlspecialchars($p['no_registrasi']) ?></td>
                                    <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($p['nama_santri']) ?></td>
                                    <td class="px-6 py-4 text-xs font-medium text-gray-500"><?= htmlspecialchars($p['jalur_pendaftaran']) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($p['metode_pembayaran'] === 'Transfer'): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100"><i class="fa-solid fa-money-check-dollar mr-1.5"></i> Transfer</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100"><i class="fa-solid fa-hand-holding-dollar mr-1.5"></i> Tunai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-medium text-gray-500">
                                        <?php if ($p['tgl_lunas']): ?>
                                            <?= date('d M Y, H:i', strtotime($p['tgl_lunas'])) ?>
                                        <?php else: ?>
                                            <span class="text-gray-300 italic">Data lama (tanpa jam)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-gray-400">
                                        <i class="fa-solid fa-inbox text-4xl mb-3 text-gray-300"></i>
                                        <p class="text-sm font-medium">Belum ada data pembayaran lunas.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination & Info -->
                <div class="border-t border-gray-100 bg-gray-50/50 p-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 rounded-b-3xl">
                    <div class="text-sm text-gray-500 font-medium">
                        Menampilkan <span class="font-bold text-gray-700"><?= min($offset + 1, $total_data) ?></span> sampai <span class="font-bold text-gray-700"><?= min($offset + $limit, $total_data) ?></span> dari total <span class="font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-md"><?= $total_data ?></span> data pembayaran
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center space-x-1">
                        <!-- Prev Button -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-primary hover:border-gray-300 transition shadow-sm">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <div class="flex space-x-1 px-2">
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="w-9 h-9 flex items-center justify-center text-gray-400">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-primary border-primary text-sm font-bold text-white shadow-md"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="w-9 h-9 flex items-center justify-center text-gray-400">...</span>';
                                }
                                echo '<a href="?page='.$total_pages.'" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 hover:text-primary transition shadow-sm">'.$total_pages.'</a>';
                            }
                            ?>
                        </div>

                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-primary hover:border-gray-300 transition shadow-sm">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 border border-gray-200 text-gray-300 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>
</body>
</html>
